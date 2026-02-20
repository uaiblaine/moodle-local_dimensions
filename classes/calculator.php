<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Calculator class for course progress and section calculations.
 *
 * @package    local_dimensions
 * @copyright  2024 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/completionlib.php');

/**
 * Calculator class for course progress calculations.
 *
 * @package    local_dimensions
 * @copyright  2024 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class calculator {
    /**
     * Calculates the progress of course sections (including subsections)
     *
     * @param int $courseid
     * @return array
     */
    public static function get_course_section_progress($courseid) {
        global $DB, $USER;

        // Load the course ensuring all properties.
        $course = $DB->get_record('course', ['id' => $courseid], '*', \MUST_EXIST);

        // Define temporary global context.
        global $COURSE;
        $savedcourse = isset($COURSE) ? $COURSE : null;
        $COURSE = $course;

        $modinfo = get_fast_modinfo($course);
        $sections = $modinfo->get_section_info_all();
        $completion = new \completion_info($course);

        if (!$completion->is_enabled()) {
            if ($savedcourse) {
                $COURSE = $savedcourse;
            }
            return ['enabled' => false];
        }

        // Map hierarchy (Subsections).
        // Parent section ID maps to child section IDs.
        $childrenmap = [];
        $sectionbyid = [];

        foreach ($sections as $s) {
            $sectionbyid[$s->id] = $s;
        }

        // Build children_map by finding subsection activities and their delegated sections.
        foreach ($modinfo->cms as $cm) {
            if ($cm->modname === 'subsection') {
                $delegated = $cm->get_delegated_section_info();
                if ($delegated) {
                    // The subsection CM is in section $cm->section.
                    // It delegates to section $delegated->id.
                    $childrenmap[$cm->section][] = $delegated->id;
                }
            }
        }

        // Check centralized lock status.
        $locked = self::is_locked($course, $USER->id);

        // Keep enrollment check for activity loop (extra security, though locked already covers it).
        $coursecontext = \context_course::instance($course->id);
        $isenrolled = is_enrolled($coursecontext, $USER->id, '', true);

        // Requested format: %d/%m/%Y.
        // Use enrollment start date if the user is enrolled with a future timestart.
        $availabilitydate = self::get_availability_date($course, $USER->id);
        $formattedstartdate = userdate($availabilitydate, '%d/%m/%Y');

        // Determine if this is an enrollment start date (user enrolled but not yet active).
        $isenrolmentstart = false;
        if ($locked) {
            $enrolstartdate = self::get_enrolment_start_date($course, $USER->id);
            $isenrolmentstart = ($enrolstartdate !== null);
        }

        $results = [];

        foreach ($sections as $section) {
            // Skip delegated sections (subsections) at the root loop - we only want the main ones.
            // Subsections will be calculated recursively within main ones.
            if (!empty($section->component)) {
                continue;
            }

            // 1. Filter by visibility (Eye icon).
            if (!$section->visible) {
                continue;
            }

            // 2. Check Availability / Restrictions.
            $sectionlocked = false;

            // If the user cannot access the section (uservisible is false).
            if (!$section->uservisible) {
                // If it is set to "Hide entirely" (availableinfo is empty), skip it.
                if (empty($section->availableinfo)) {
                    continue;
                }

                // Otherwise ("Show restricted" - has availableinfo), mark as locked and skip calculation.
                $sectionlocked = true;
            }

            $sectionname = $section->name;
            if (trim($sectionname) === '') {
                $sectionname = get_section_name($course, $section);
            }

            $percentage = null;
            $hasactivities = false;
            // The existing course-level lock overrides everything, but if course is unlocked, we check section lock.
            // However, verify if we should calculate progress for a locked section?
            // The requirement says: "instead of showing the percentage, show a lock icon" -> no progress calculation.

            $calculateprogress = !$locked && $isenrolled && !$sectionlocked;

            if ($calculateprogress) {
                // Recursive collection of all activities in this section AND its children.
                $allcms = self::get_section_cms_recursive($section->id, $childrenmap, $sectionbyid, $modinfo);

                $total = 0;
                $completed = 0;

                foreach ($allcms as $cm) {
                    if ($cm->modname === 'subsection') {
                        // Do not count the 'subsection' activity itself, only its content.
                        continue;
                    }

                    if ($cm->completion != \COMPLETION_TRACKING_NONE && $cm->uservisible) {
                        $total++;
                        $cmdata = $completion->get_data($cm, true, $USER->id);
                        if (
                            $cmdata->completionstate == \COMPLETION_COMPLETE
                            || $cmdata->completionstate == \COMPLETION_COMPLETE_PASS
                        ) {
                            $completed++;
                        }
                    }
                }

                if ($total > 0) {
                    $percentage = round(($completed / $total) * 100);
                    $hasactivities = true;
                }
            }

            // Define URL: if locked, go to Course Page. Else, Section anchor.
            if ($sectionlocked) {
                // Link to course page to see restriction details.
                $url = (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
            } else {
                $url = (new \moodle_url('/course/section.php', ['id' => $section->id]))->out(false);
            }

            // FINAL OVERRIDE: If Course is Locked (non-enrolled), remove link and icon.
            if ($locked) {
                $url = ''; // No link.
                $sectionlocked = false; // No lock icon (overlay handles it).
            }

            $results[] = [
                'name' => $sectionname,
                'percentage' => $percentage,
                'has_activities' => $hasactivities, // True when percentage is not null.
                'url' => $url,
                'locked' => $sectionlocked,
            ];
        }

        // Restore original global context if present.
        if ($savedcourse) {
            $COURSE = $savedcourse;
        }

        return [
            'enabled' => true,
            'locked' => $locked,
            'formatted_start_date' => $formattedstartdate,
            'is_enrolment_start' => $isenrolmentstart,
            'course_url' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'sections' => $results,
        ];
    }

    /**
     * Collects all CMs (Course Modules) of a section and its descendants recursively
     */
    private static function get_section_cms_recursive($sectionid, $childrenmap, $sectionbyid, $modinfo) {
        $cms = [];

        // 1. Process current section.
        if (isset($sectionbyid[$sectionid])) {
            $sec = $sectionbyid[$sectionid];
            $sequence = (string) $sec->sequence;
            if ($sequence !== '') {
                $cmids = explode(',', $sequence);
                foreach ($cmids as $cmid) {
                    if (!empty($cmid) && isset($modinfo->cms[$cmid])) {
                        $cms[] = $modinfo->cms[$cmid];
                    }
                }
            }
        }

        // 2. Process children recursively.
        if (isset($childrenmap[$sectionid])) {
            foreach ($childrenmap[$sectionid] as $childsecid) {
                // Check visibility of subsection.
                if (isset($sectionbyid[$childsecid]) && !$sectionbyid[$childsecid]->visible) {
                    continue;
                }
                $childcms = self::get_section_cms_recursive($childsecid, $childrenmap, $sectionbyid, $modinfo);
                $cms = array_merge($cms, $childcms);
            }
        }

        return $cms;
    }

    /**
     * Checks if user has access to content (Enrolled + Student Role)
     *
     * @param stdClass $course Course object
     * @param int $userid User ID
     * @return bool True if locked
     */
    public static function is_locked($course, $userid) {
        $coursecontext = \context_course::instance($course->id);

        // 1. Check active enrollment.
        if (!is_enrolled($coursecontext, $userid, '', true)) {
            return true;
        }

        // 2. Check student role.
        $roles = get_user_roles($coursecontext, $userid);
        foreach ($roles as $role) {
            if ($role->shortname === 'student') {
                return false;
            }
        }

        return true;
    }

    /**
     * Gets the most relevant availability date for a locked course.
     *
     * If the user has an enrollment with a future timestart, returns that date.
     * Otherwise, returns the course start date.
     *
     * @param \stdClass $course Course object
     * @param int $userid User ID
     * @return int Unix timestamp of the availability date
     */
    public static function get_availability_date($course, $userid) {
        $enrolstart = self::get_enrolment_start_date($course, $userid);
        if ($enrolstart !== null) {
            return $enrolstart;
        }
        return $course->startdate;
    }

    /**
     * Gets the user's enrollment start date if they have a future enrollment.
     *
     * Checks user_enrolments joined with enrol for a record with timestart > now.
     *
     * @param \stdClass $course Course object
     * @param int $userid User ID
     * @return int|null Unix timestamp of enrollment start, or null if not found
     */
    public static function get_enrolment_start_date($course, $userid) {
        global $DB;

        $sql = "SELECT ue.timestart
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE e.courseid = :courseid
                   AND ue.userid = :userid
                   AND ue.timestart > :now
              ORDER BY ue.timestart ASC";

        $record = $DB->get_record_sql($sql, [
            'courseid' => $course->id,
            'userid' => $userid,
            'now' => time(),
        ], IGNORE_MULTIPLE);

        if ($record && !empty($record->timestart)) {
            return (int) $record->timestart;
        }

        return null;
    }

    /**
     * Filter courses based on the enrollment filter setting.
     *
     * @param array $courses Array of course records (must have ->id property)
     * @param int $userid The user ID to check enrollment for
     * @param string $filtermode One of 'all', 'enrolled', 'active'
     * @return array Filtered array of course records
     */
    public static function filter_courses_by_enrollment(array $courses, int $userid, string $filtermode): array {
        if ($filtermode === 'all' || empty($courses)) {
            return $courses;
        }

        // Active mode: only actively enrolled (is_enrolled with onlyactive=true).
        // Enrolled mode: any enrollment record (is_enrolled with onlyactive=false).
        $onlyactive = ($filtermode === 'active');

        $filtered = [];
        foreach ($courses as $key => $course) {
            $coursecontext = \context_course::instance($course->id);
            if (is_enrolled($coursecontext, $userid, '', $onlyactive)) {
                $filtered[$key] = $course;
            }
        }

        return $filtered;
    }
}
