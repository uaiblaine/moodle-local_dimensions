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
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions;


/**
 * Calculator class for course progress calculations.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class calculator {
    /**
     * Calculates the progress of course sections (including subsections)
     *
     * Access contract: this reads and names every visible section of the course, so the
     * CALLER must first establish that the viewer may be told about that course at all -
     * helper::readable_competency_courses() is that gate, and get_course_progress applies it
     * before every call. Progress itself is already suppressed for a viewer who is not
     * enrolled (the locked branch below), but the section names are not, by design: the card
     * shows them blurred behind its lock overlay.
     *
     * @param int $courseid
     * @return array
     */
    public static function get_course_section_progress($courseid) {
        global $DB, $USER;

        // Load the course ensuring all properties.
        $course = $DB->get_record('course', ['id' => $courseid], '*', \MUST_EXIST);

        // Define temporary global context. Restore is unconditional via the
        // try/finally below so an exception in any of the helpers called
        // between here and the bottom return cannot leak the wrong $COURSE
        // into the rest of the request (the external service calls this in
        // a per-course loop, so a single failure must not poison siblings).
        global $COURSE;
        $savedcourse = $COURSE ?? null;
        $COURSE = $course;
        try {
            $modinfo = get_fast_modinfo($course);
            $sections = $modinfo->get_section_info_all();
            $completion = new \completion_info($course);

            /* Resolve the lock and its dates BEFORE the completion check. A course can be
               locked and have completion tracking switched off at the same time, and the
               lock is the more important of the two facts: returning early without it left
               the card claiming "Completion disabled" to a user who cannot open the course
               at all. */

            // Check centralized lock status.
            $locked = self::is_locked($course, $USER->id);

            /* The lock outranks every card shape and must be known before the shape is
               resolved: the activity and section bodies both carry a live link (and section,
               a real progress ring), and neither is hardened for a locked card the way the
               timeline is - its section URLs are blanked server-side and styles.css gives it
               pointer-events: none. A locked card is forced to the timeline shape with
               nothing named, so the overlay stays the only thing a locked learner can reach.
               One resolver still answers the shape for both views once unlocked, so the
               tracker and the plan can never disagree about the same open course. */
            $shape = $locked
                ? ['mode' => constants::CARDMODE_TIMELINE, 'activity' => null, 'section' => null]
                : self::resolve_card_shape((int) $course->id, $USER->id);

            // Keep enrollment check for activity loop (extra security, though locked already covers it).
            $coursecontext = \core\context\course::instance($course->id);
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

            /* A locked card reframes the date as an invitation rather than a restriction, so
               the client needs to know whether it still lies ahead - and whether the learner
               can simply join instead of waiting. All three are asked only when the card is
               locked: each enrolment answer costs a walk through the course's instances.

               Pending is asked last and only when joining is not on offer. A course can carry
               a pending application on one instance and an open way in on another, and being
               able to walk in now outranks waiting for somebody to decide. */
            $canenrol = $locked && self::current_user_can_enrol((int) $course->id);
            $ispending = $locked && !$canenrol
                && self::current_user_has_pending_application((int) $course->id);
            $isfuturedate = $locked && $availabilitydate > time();

            $courseurl = (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false);

            if (!$completion->is_enabled()) {
                return [
                    'enabled' => false,
                    'locked' => $locked,
                    'formatted_start_date' => $formattedstartdate,
                    'is_enrolment_start' => $isenrolmentstart,
                    'can_self_enrol' => $canenrol,
                    'is_pending' => $ispending,
                    'is_future_date' => $isfuturedate,
                    'course_url' => $courseurl,
                    'sections' => [],
                    'cardmode' => $shape['mode'],
                    'activity' => $shape['activity'],
                    'section' => $shape['section'],
                ];
            }

            // Map hierarchy (Subsections).
            // Parent section ID maps to child section IDs.
            $childrenmap = [];
            $sectionbyid = [];

            foreach ($sections as $s) {
                $sectionbyid[$s->id] = $s;
            }

            /* Build children_map by finding subsection activities and their delegated sections.

               A subsection the learner cannot reach must not cascade its contents into the
               parent's count, and a subsection being deleted is the case that bites. Core flags
               only the subsection module itself, so every activity inside its delegated section
               keeps deletioninprogress = 0 and uservisible = true until mod_subsection's
               delete_instance() runs in the adhoc task - while the course page withdraws the
               whole subsection the moment it is flagged, because section_info ties a delegated
               section's uservisible to its parent module. Without this guard the ring goes on
               counting activities the learner can no longer see, until the next cron run - or
               for good, since a delete task that keeps failing never clears the flag.

               Spelled with uservisible rather than through counts_towards_progress(): the
               question here is whether the learner can reach the subsection at all, not whether
               its contents are work. A subsection released later renders as a greyed card whose
               contents the course page does not list, so cascading them in would add activities
               nobody can see - which is the opposite of the rule the counter follows. */
            foreach ($modinfo->cms as $cm) {
                if ($cm->modname === 'subsection') {
                    if ($cm->deletioninprogress || !$cm->uservisible) {
                        continue;
                    }
                    $delegated = $cm->get_delegated_section_info();
                    if ($delegated) {
                        // The subsection CM is in section $cm->section.
                        // It delegates to section $delegated->id.
                        $childrenmap[$cm->section][] = $delegated->id;
                    }
                }
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

                /* An unnamed section stores NULL, not '' - which is the normal case, since
                   most sections take their displayed name ("Topic 1") from get_section_name.
                   trim(null) is deprecated in PHP 8.1+. */
                $sectionname = $section->name ?? '';
                if (trim($sectionname) === '') {
                    $sectionname = get_section_name($course, $section);
                }
                $sectionname = format_string(
                    $sectionname,
                    true,
                    ['context' => \core\context\course::instance($course->id)]
                );

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

                        if (self::counts_towards_progress($cm, (int) $USER->id)) {
                            $total++;
                            $cmdata = $completion->get_data($cm, true, $USER->id);
                            $iscomplete = $cmdata->completionstate == \COMPLETION_COMPLETE
                                || $cmdata->completionstate == \COMPLETION_COMPLETE_PASS;
                            if ($iscomplete) {
                                $completed++;
                            }
                        }
                    }

                    $percentage = self::progress_percentage($completed, $total);
                    $hasactivities = $percentage !== null;
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

            return [
                'enabled' => true,
                'locked' => $locked,
                'formatted_start_date' => $formattedstartdate,
                'is_enrolment_start' => $isenrolmentstart,
                'can_self_enrol' => $canenrol,
                'is_pending' => $ispending,
                'is_future_date' => $isfuturedate,
                'course_url' => $courseurl,
                'sections' => $results,
                'cardmode' => $shape['mode'],
                'activity' => $shape['activity'],
                'section' => $shape['section'],
            ];
        } finally {
            $COURSE = $savedcourse;
        }
    }

    /**
     * The percentage a progress ring may honestly show for a completed-out-of-total count.
     *
     * round() alone lies at both ends. A section of 200 activities with 199 done rounds to
     * 100, and the external function reads any 100 as "completed" and swaps the ring for the
     * done icon - so the card claims a finished section while one activity is still open. The
     * mirror is just as wrong: 1 of 201 rounds to 0, and 0 reads as "not started", erasing
     * work the learner has already done. Both ends are therefore reserved: only a genuinely
     * full count reaches 100, and only a genuinely empty one reaches 0.
     *
     * @param int $completed How many of the section's activities the user has completed.
     * @param int $total How many activities the section counts.
     * @return int|null The percentage 0-100, or null when there is nothing to measure.
     */
    public static function progress_percentage(int $completed, int $total): ?int {
        if ($total <= 0) {
            return null;
        }

        $percentage = (int) round(($completed / $total) * 100);

        if ($percentage >= 100 && $completed < $total) {
            return 99;
        }
        if ($percentage <= 0 && $completed > 0) {
            return 1;
        }

        return $percentage;
    }

    /**
     * The course's overall completion percentage.
     *
     * core_completion\progress::get_course_progress_percentage() is deliberately not called.
     * On Moodle 4.5 its numerator is not a subset of its denominator (MDL-60912, fixed in 5.0.7
     * and 5.1.4, never backported): the denominator, completion_info::get_activities(), drops a
     * module flagged deletioninprogress, while the numerator, count_modules_completed(), takes
     * no module list on that branch and still counts that module's completion row. A learner who
     * completed two of four activities and then had one of those two deleted read 67% where 33%
     * was the truth - and clamp_percentage() cannot catch it, because the value never passes 100.
     * 4.5's denominator is also wider than the later branches: it applies no visibility filter at
     * all, so a hidden activity counted and a learner could never reach 100% in a course holding
     * one. Neither of the 5.1+ helpers that fix these exists on 4.5 to call.
     *
     * What counts is counts_towards_progress(), the one predicate the section rings use as well -
     * the two numbers share a card, so they must answer the same question. Read that method for
     * where the line falls and why.
     *
     * The numerator is read through completion_info::get_data() rather than core's own COUNT
     * query, which is the way the rest of this plugin reads completion and which cannot fall out
     * of step with the denominator the way a separate statement can.
     *
     * The course is read by id rather than accepted as an object, the same contract
     * get_course_section_progress() keeps: modinfo validates its cache against $course->cacherev,
     * so a caller holding a record fetched before an activity changed would otherwise be served a
     * stale module list, silently.
     *
     * @param int $courseid The course to measure.
     * @param int $userid The user whose visibility and completion are read.
     * @return int The percentage 0-100; 0 when there is nothing to measure.
     * @throws \dml_missing_record_exception If the course id does not resolve.
     */
    public static function course_completion_percentage(int $courseid, int $userid): int {
        global $CFG, $COURSE, $DB;
        require_once($CFG->libdir . '/completionlib.php');

        $course = $DB->get_record('course', ['id' => $courseid], '*', \MUST_EXIST);

        $completion = new \completion_info($course);
        if (!$completion->is_enabled() || !$completion->is_tracked_user($userid)) {
            return 0;
        }

        // Core asks this first too: a course its own criteria call finished is finished.
        if ($completion->is_course_complete($userid)) {
            return 100;
        }

        /* Same temporary global context, and the same unconditional restore, as
           get_course_section_progress(): reading a module's dynamic data runs callbacks that may
           consult $COURSE, and the external service calls this in a per-course loop, so one
           failure must not poison its siblings. */
        $savedcourse = $COURSE ?? null;
        $COURSE = $course;
        try {
            $modinfo = get_fast_modinfo($course, $userid);

            $completed = 0;
            $total = 0;

            foreach ($modinfo->get_cms() as $cm) {
                if (!self::counts_towards_progress($cm, $userid)) {
                    continue;
                }

                $total++;
                $cmdata = $completion->get_data($cm, true, $userid);
                $iscomplete = $cmdata->completionstate == \COMPLETION_COMPLETE
                    || $cmdata->completionstate == \COMPLETION_COMPLETE_PASS;
                if ($iscomplete) {
                    $completed++;
                }
            }

            return self::clamp_percentage(self::progress_percentage($completed, $total));
        } finally {
            $COURSE = $savedcourse;
        }
    }

    /**
     * Whether an activity belongs in this learner's required workload.
     *
     * One predicate for the course bar and for the section rings, because the two numbers sit on
     * the same card and any difference between them reads as a bug. Three cumulative conditions,
     * and each rules out a different thing:
     *
     * 1. Completion is tracked on it. Untracked work has no state to report.
     * 2. The learner can SEE it. Hidden by the eye icon, sitting in a hidden section, or
     *    restricted with "hide entirely" - none of those exist as far as the learner knows, so
     *    none can be work they owe.
     * 3. It is theirs to do, now or later. This is the subtle one, and core already draws the
     *    line: core_availability\condition::is_applied_to_user_lists() marks the conditions that
     *    are PERMANENT for a given person - group, grouping and profile - and leaves the ones
     *    that merely have not come round yet - date, grade, completion-of-something-else. So a
     *    date-locked activity stays in the denominator, because it will open for this learner
     *    and they will have to do it; an activity restricted to a group they are not in leaves
     *    it, because no amount of studying will ever unlock it. Counting the latter would make
     *    100% unreachable for that learner, for good.
     *
     * Condition 2 is deliberately the UNION of "listed on the course page" and "openable right
     * now" rather than core's is_visible_on_course_page() alone, because neither half is
     * sufficient by itself. A date-restricted activity shown greyed is listed but not openable
     * (uservisible false, is_visible_on_course_page true). A STEALTH activity - "available but
     * don't show on the course page" - is the mirror: openable right now and reachable from
     * whatever links to it, but not listed (uservisible true, is_visible_on_course_page false).
     * Both are work the learner owes. Core drops the stealth one; this does not.
     *
     * The explicit $cm->visible test is not redundant with either half. It also settles a branch
     * difference: MDL-66780 changed the final clause of cm_info::update_user_visible() in 5.x, so
     * an activity a teacher hid that ALSO carries a shown restriction reports
     * is_visible_on_course_page() as true on 4.5 and false on 5.1/5.2. Testing $cm->visible up
     * front gives the same answer on all four supported branches, and it is the answer condition
     * 2 implies anyway.
     *
     * The deletioninprogress test cannot be dropped either, and not only because core forces
     * uservisible false for such a module: is_visible_on_course_page() returns NULL for it, since
     * update_user_visible() returns before ever assigning the property it reads. That is falsy
     * today by accident of an uninitialised property, which is not something to rely on.
     *
     * @param \cm_info $cm The activity to judge.
     * @param int $userid The learner whose visibility and restrictions are read.
     * @return bool
     */
    private static function counts_towards_progress(\cm_info $cm, int $userid): bool {
        if ($cm->modname === 'subsection' || $cm->deletioninprogress) {
            return false;
        }

        if ($cm->completion == \COMPLETION_TRACKING_NONE) {
            return false;
        }

        if (!$cm->visible || !($cm->is_visible_on_course_page() || $cm->uservisible)) {
            return false;
        }

        $users = [$userid => (object) ['id' => $userid]];

        return !empty((new \core_availability\info_module($cm))->filter_user_list($users));
    }

    /**
     * A raw percentage, normalised to what a progress bar may display.
     *
     * null means there was nothing to measure and reads as 0. The 0-100 clamp is the outer
     * safety net rather than the fix it once was: course_completion_percentage() no longer
     * channels core's own value, which could exceed 100 on the branches without the MDL-60912
     * fix (4.5 throughout, below 5.0.7 and below 5.1.4). It stays so that no future numerator
     * can render a bar past full.
     *
     * @param float|null $raw The value to normalise.
     * @return int The percentage clamped to 0-100; null becomes 0.
     */
    public static function clamp_percentage(?float $raw): int {
        if ($raw === null) {
            return 0;
        }

        return (int) max(0, min(100, round($raw)));
    }

    /**
     * Collects all CMs (Course Modules) of a section and its descendants recursively.
     *
     * @param int $sectionid The section ID to collect CMs from.
     * @param array $childrenmap Map of parent section IDs to arrays of child section IDs.
     * @param array $sectionbyid Map of section IDs to section_info objects.
     * @param \course_modinfo $modinfo The course module info object.
     * @return \cm_info[] Array of course module info objects.
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
     * The shape the course card should take, and the data that shape needs.
     *
     * Three shapes, first match wins:
     * - activity: the course is in the single-activity format, or it boils down to one
     *   trackable module. Either way there is no sequence to draw, and a progress bar
     *   could only ever read 0% or 100%.
     * - section: one visible section holding several activities. A timeline of a single
     *   row naming a section usually called "General" informs nobody.
     * - timeline: everything else.
     *
     * The course-level lock is the caller's business and takes precedence over all three.
     *
     * @param int $courseid The course id.
     * @param int $userid The user whose completion and visibility are read.
     * @return array Keys mode, activity and section; activity and section are null unless
     *               mode names them.
     */
    public static function resolve_card_shape(int $courseid, int $userid): array {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        $course = get_course($courseid);
        $completion = new \completion_info($course);
        $modinfo = get_fast_modinfo($course, $userid);

        $main = self::resolve_main_activity($course, $modinfo);
        if ($main !== null) {
            return [
                'mode' => constants::CARDMODE_ACTIVITY,
                'activity' => self::describe_activity($main, $completion, $userid),
                'section' => null,
            ];
        }

        // Two is enough to answer "is there exactly one", and stops the walk early.
        $tracked = self::collect_trackable_cms($modinfo, $completion, $userid, 2);

        /* Two questions, deliberately kept apart. counts_towards_progress() says how much work
           this course is, and it includes an activity that is released later - which is what
           stops the card claiming a finished course. uservisible says whether that activity can
           be OFFERED, and the activity shape does exactly that: describe_activity() hands the
           template a URL and it renders a "go to activity" button. So a course whose one piece of
           work has not opened yet is real, but it is not an activity card - naming it would be a
           button that goes nowhere. It falls through to the section or timeline shape, which
           reports the same 0% without promising a way in. */
        if (count($tracked) === 1 && $tracked[0]->uservisible) {
            return [
                'mode' => constants::CARDMODE_ACTIVITY,
                'activity' => self::describe_activity($tracked[0], $completion, $userid),
                'section' => null,
            ];
        }

        $sections = self::collect_card_sections($modinfo);
        if (count($sections) === 1) {
            return [
                'mode' => constants::CARDMODE_SECTION,
                'activity' => null,
                /* $tracked was already walked above (capped at 2) to answer "is there
                   exactly one" - its non-emptiness also answers "is there at least one",
                   which is all describe_section() needs, so it is reused rather than
                   walked a second time. */
                'section' => self::describe_section($course, $sections[0], count($tracked) > 0),
            ];
        }

        return [
            'mode' => constants::CARDMODE_TIMELINE,
            'activity' => null,
            'section' => null,
        ];
    }

    /**
     * The activity of a single-activity-format course, when it has one.
     *
     * core_courseformat\main_activity_interface::get_main_activity() answers this
     * directly, but it arrived in Moodle 5.1 and this plugin supports 4.5 upward. An
     * instanceof against a missing interface returns false rather than failing, so that
     * branch would silently never fire on two of the four branches CI runs. The format
     * string is on the course record everywhere, so detecting the format that way is
     * unchanged - but the format does NOT guarantee a single activity: Moodle neither
     * deletes sections nor modules when a course's format is switched to singleactivity,
     * nor when the format's activity type is changed later, so a course migrated from
     * another format keeps its old modules around. This mirrors
     * format_singleactivity::get_activitytype() + get_main_activity()
     * (course/format/singleactivity/lib.php): the format option 'activitytype' names the
     * one module type that counts, and only section 0 is searched for the first module of
     * that type - exactly what format_singleactivity::page_set_course() redirects the
     * learner to, so the card must name the same one. Unlike core, this method does not
     * force-show a hidden match: a deletioninprogress or non-uservisible candidate falls
     * through to null instead, and the caller's next branch handles it.
     *
     * @param \stdClass $course The course record.
     * @param \course_modinfo $modinfo Its modinfo for the reading user.
     * The uservisible guard below is the same question resolve_card_shape() asks before taking
     * the activity shape, and deliberately NOT counts_towards_progress(): this method's job is to
     * name an activity the card will link to, so it must be one the learner can actually open. An
     * activity that is real work but not yet released is counted by the percentages and left
     * unnamed here.
     *
     * @return \cm_info|null The activity, or null when the format is not single-activity,
     *                       its activity type is unset or unavailable, or the configured
     *                       module is missing, being deleted, or not visible to the user.
     */
    private static function resolve_main_activity(\stdClass $course, \course_modinfo $modinfo): ?\cm_info {
        global $CFG;

        if (($course->format ?? '') !== 'singleactivity') {
            return null;
        }

        require_once($CFG->dirroot . '/course/format/lib.php');
        $options = course_get_format($course)->get_format_options();
        $activitytype = $options['activitytype'] ?? '';
        if ($activitytype === '' || !array_key_exists($activitytype, \format_singleactivity::get_supported_activities())) {
            // Unset, or names a type the format itself would not offer (no view page,
            // a subsection delegate, or hidden from the course by an admin).
            return null;
        }

        $found = null;
        foreach ($modinfo->sections[0] ?? [] as $cmid) {
            if ($modinfo->cms[$cmid]->modname === $activitytype) {
                // Core takes the first match in section 0 and stops there; mirror that
                // instead of continuing past it if this candidate fails our guards below.
                $found = $modinfo->cms[$cmid];
                break;
            }
        }

        if ($found === null || $found->deletioninprogress || !$found->uservisible) {
            return null;
        }

        return $found;
    }

    /**
     * The modules that make up this learner's workload in the course, up to a limit.
     *
     * Counts with counts_towards_progress(), the same predicate the bar and the rings use, so
     * "does this course boil down to one activity" is asked of the same set of activities the
     * percentages are measured over. It did not used to be: this walk gated on uservisible, so a
     * course of one open activity beside one released-later activity looked like a course of ONE,
     * took the activity shape, and drew a completed tick over a course that was half undone.
     *
     * Whether the single activity found may be OFFERED as a link is a different question, and
     * the caller asks it separately - see resolve_card_shape().
     *
     * @param \course_modinfo $modinfo The course modinfo.
     * @param \completion_info $completion The course completion info.
     * @param int $userid The learner whose workload this is.
     * @param int $limit Stop once this many are found.
     * @return array List of cm_info.
     */
    private static function collect_trackable_cms(
        \course_modinfo $modinfo,
        \completion_info $completion,
        int $userid,
        int $limit
    ): array {
        if (!$completion->is_enabled()) {
            return [];
        }

        $found = [];
        foreach ($modinfo->get_cms() as $cm) {
            if (!self::counts_towards_progress($cm, $userid)) {
                continue;
            }
            $found[] = $cm;
            if (count($found) >= $limit) {
                break;
            }
        }

        return $found;
    }

    /**
     * The sections the card would draw, mirroring the timeline's own filter.
     *
     * A qualifying section counts whether or not it holds any modules: a course with one
     * empty section still resolves to the section shape rather than the timeline. That is
     * a deliberate choice - the card's job there is to offer a way in, and a one-row
     * timeline would say less than the section shape does - not an oversight.
     *
     * @param \course_modinfo $modinfo The course modinfo.
     * @return array List of section_info.
     */
    private static function collect_card_sections(\course_modinfo $modinfo): array {
        $sections = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            // A delegated section belongs to its subsection activity, never to the card.
            if (!empty($section->component)) {
                continue;
            }
            if (!$section->visible) {
                continue;
            }
            // Hidden entirely, with no availability text to show: the timeline skips it too.
            if (!$section->uservisible && empty($section->availableinfo)) {
                continue;
            }
            $sections[] = $section;
        }

        return $sections;
    }

    /**
     * Describe one activity for the card.
     *
     * @param \cm_info $cm The module.
     * @param \completion_info $completion The course completion info.
     * @param int $userid The user whose completion is read.
     * @return array Keys cmid, name, url, completed and tracked.
     */
    private static function describe_activity(\cm_info $cm, \completion_info $completion, int $userid): array {
        $tracked = $completion->is_enabled()
            && $completion->is_enabled($cm) != \COMPLETION_TRACKING_NONE;

        $completed = false;
        if ($tracked) {
            $data = $completion->get_data($cm, true, $userid);
            $completed = $data->completionstate == \COMPLETION_COMPLETE
                || $data->completionstate == \COMPLETION_COMPLETE_PASS;
        }

        return [
            'cmid' => (int) $cm->id,
            'name' => $cm->get_formatted_name(),
            'url' => $cm->url ? $cm->url->out(false) : '',
            'completed' => $completed,
            'tracked' => $tracked,
        ];
    }

    /**
     * Describe the card's single section.
     *
     * hasownname reports whether a teacher named the section: Moodle stores NULL when the
     * label is generated ("Topic 1", "General"), and repeating a generated label under the
     * course name informs nobody. The name is returned empty in that case rather than
     * filled with the generated label, so the caller cannot render it by accident.
     *
     * @param \stdClass $course The course record.
     * @param \section_info $section The section.
     * @param bool $tracked Whether the section holds at least one activity with completion
     *                      tracking on - false draws no percentage, the same honesty the
     *                      activity shape already carries.
     * @return array Keys name, hasownname, url and tracked.
     */
    private static function describe_section(\stdClass $course, \section_info $section, bool $tracked): array {
        $ownname = trim((string) ($section->name ?? ''));
        $context = \core\context\course::instance($course->id);

        return [
            'name' => $ownname !== '' ? format_string($ownname, true, ['context' => $context]) : '',
            'hasownname' => $ownname !== '',
            'url' => (new \moodle_url('/course/section.php', ['id' => $section->id]))->out(false),
            'tracked' => $tracked,
        ];
    }

    /**
     * Checks if user has access to content (Enrolled + Student Role)
     *
     * @param stdClass $course Course object
     * @param int $userid User ID
     * @return bool True if locked
     */
    public static function is_locked($course, $userid) {
        $coursecontext = \core\context\course::instance($course->id);

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
     * @param string $filtermode One of 'all', 'enrolled', 'active', 'enrolledorself'
     * @return array Filtered array of course records
     */
    public static function filter_courses_by_enrollment(array $courses, int $userid, string $filtermode): array {
        if ($filtermode === 'all' || empty($courses)) {
            return $courses;
        }

        if ($filtermode === constants::ENROLLMENTFILTER_ENROLLEDORSELF) {
            $filtered = [];
            foreach ($courses as $key => $course) {
                if (self::user_enrolled_or_self_enrolable($course, $userid)) {
                    $filtered[$key] = $course;
                }
            }
            return $filtered;
        }

        // Active mode: only actively enrolled (is_enrolled with onlyactive=true).
        // Enrolled mode: any enrollment record (is_enrolled with onlyactive=false).
        $onlyactive = ($filtermode === 'active');

        $filtered = [];
        foreach ($courses as $key => $course) {
            $coursecontext = \core\context\course::instance($course->id);
            if (is_enrolled($coursecontext, $userid, '', $onlyactive)) {
                $filtered[$key] = $course;
            }
        }

        return $filtered;
    }

    /**
     * Whether the user can actually open a course: actively enrolled, or able to enrol themselves.
     *
     * The enrolment branch only answers for the current $USER (the per-plugin predicates it
     * dispatches to are all $USER-scoped).
     *
     * A pending enrol_apply application is neither leg: it writes a suspended enrolment row, so
     * is_enrolled() with onlyactive says no, and the apply adapter refuses to offer a second
     * application. Such a learner genuinely cannot open the course, which is what this returns -
     * see current_user_has_pending_application() for the state a card should show them instead.
     *
     * @param \stdClass $course A course record with at least an id.
     * @param int $userid The user id.
     * @return bool
     */
    public static function user_can_access_course(\stdClass $course, int $userid): bool {
        global $USER;

        $coursecontext = \core\context\course::instance($course->id);
        if (is_enrolled($coursecontext, $userid, '', true)) {
            return true;
        }

        if ($userid !== (int) $USER->id) {
            return false;
        }

        return self::current_user_can_enrol((int) $course->id);
    }

    /**
     * Whether the current $USER may enrol themselves into the course right now.
     *
     * Scoped to $USER by the per-plugin predicates below; callers must gate on
     * $userid === $USER->id.
     *
     * This dispatches per enrolment plugin rather than asking one question of all of them,
     * because there is no generic question to ask. enrol_plugin::can_self_enrol() is an
     * unconditional `return false;` in the base class and enrol_self is the only plugin in the
     * whole of 5.2 that overrides it, so a loop written against it reports every other method
     * as "cannot" - which is how a course whose only way in is enrol_apply came to be drawn
     * with a padlock and no path through it, for applicants who were perfectly eligible.
     *
     * enrol_self: can_self_enrol() already enforces instance status, the enrolment window, the
     * max-enrolled cap, an enrolment the user already holds and the customint5 cohort
     * restriction, so a plan's synced restriction cohort is honoured for free. It returns true,
     * a string, or null - when customint5 names a cohort that has since been deleted
     * (enrol/self/lib.php) - but never false, so only `=== true` fails closed.
     *
     * enrol_apply (this fleet's fork, and optional - the is_callable() guard is what makes it
     * so): its predicate is allow_apply(), covering instance status, the new-applications flag,
     * the enrolment window and the cohort restriction with its -1 unresolved sentinel. Two
     * checks live outside it, and are mirrored here precisely because enrol_self keeps its
     * equivalents inside can_self_enrol(): an application already lodged, and the customint3
     * places cap. Keep them in step with enrol_apply::submit_application(), which is the
     * authority - it re-checks both under a lock before writing.
     *
     * @param int $courseid The course id.
     * @return bool
     */
    public static function current_user_can_enrol(int $courseid): bool {
        global $DB, $USER;

        $plugins = [];
        foreach (enrol_get_instances($courseid, true) as $instance) {
            if ($instance->enrol !== 'self' && $instance->enrol !== 'apply') {
                continue;
            }
            if (!array_key_exists($instance->enrol, $plugins)) {
                // Null for a plugin that is not installed, which is the normal case for apply.
                $plugins[$instance->enrol] = enrol_get_plugin($instance->enrol);
            }
            $plugin = $plugins[$instance->enrol];
            if (!$plugin) {
                continue;
            }

            if ($instance->enrol === 'self') {
                if ($plugin->can_self_enrol($instance, false) === true) {
                    return true;
                }
                continue;
            }

            if (!is_callable([$plugin, 'allow_apply'])) {
                // Some other build of enrol_apply; this adapter knows nothing about it.
                continue;
            }
            if ($DB->record_exists('user_enrolments', ['userid' => $USER->id, 'enrolid' => $instance->id])) {
                // An application is already lodged, or an enrolment already held.
                continue;
            }
            if ($plugin->allow_apply($instance) !== true) {
                continue;
            }
            $cap = (int) $instance->customint3;
            if ($cap > 0 && $DB->count_records('user_enrolments', ['enrolid' => $instance->id]) >= $cap) {
                continue;
            }
            return true;
        }

        return false;
    }

    /**
     * Whether the current $USER has an enrol_apply application still awaiting a decision.
     *
     * A pending application is a user_enrolments row on an apply instance written with
     * ENROL_USER_SUSPENDED and no enrolment period (enrol_apply::apply()), so it answers no to
     * both of the questions a card asks: is_enrolled() with onlyactive is false, and
     * current_user_can_enrol() declines to offer a second application. Without a state of its
     * own such a learner gets the padlock - the same card shown to somebody who was never
     * eligible - and the one thing it cannot say is the one thing they need to know.
     *
     * Scoped to apply instances on purpose. A suspended row on a manual or self instance is an
     * administrative suspension, not an application, and reading it as one would tell a
     * suspended learner to wait for a decision nobody is going to take.
     *
     * Matched by enrolment state rather than through enrol_apply_applicationinfo, because that
     * row is deleted the moment a decision is taken while the enrolment row outlives it. An
     * approval turns the row active, which the enrolled branch catches first, and a
     * cancellation unenrols the user outright.
     *
     * Expiry is the third outcome, and it is why the timeend clause below is not optional. When
     * a site sets enrol_apply's expiredaction to one of the two suspend values, core's
     * process_expirations() flips a lapsed ACTIVE row back to ENROL_USER_SUSPENDED and leaves
     * timeend in the past - so a status-only test reads an approval that simply ran out as a
     * fresh application, and tells that learner to wait for a decision nobody will ever take.
     * Pending and waiting-list rows always carry timeend = 0: apply() stamps no period and the
     * waiting state does not touch the dates, so only a once-approved row can have one.
     *
     * The authority is enrol_apply\local\queue::awaiting_decision_where(), whose docblock names
     * this exact clause as the one that gets left out. That class cannot be called from here -
     * naming it would make an optional integration a hard dependency - so this is a deliberate
     * third copy of a two-copy rule. Keep it in step by hand.
     *
     * @param int $courseid The course id.
     * @return bool
     */
    public static function current_user_has_pending_application(int $courseid): bool {
        global $DB, $USER;

        foreach (enrol_get_instances($courseid, true) as $instance) {
            if ($instance->enrol !== 'apply') {
                continue;
            }
            $pending = $DB->record_exists_select(
                'user_enrolments',
                'userid = :userid AND enrolid = :enrolid AND status <> :active
                     AND (timeend = 0 OR timeend > :now)',
                [
                    'userid' => $USER->id,
                    'enrolid' => $instance->id,
                    'active' => ENROL_USER_ACTIVE,
                    'now' => time(),
                ]
            );
            if ($pending) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the current $USER can self-enrol into the course via an enabled self instance.
     *
     * @deprecated Since v1.1 - the predicate is no longer self-only. Use current_user_can_enrol().
     * @param int $courseid The course id.
     * @return bool
     */
    public static function current_user_can_self_enrol(int $courseid): bool {
        debugging(
            'calculator::current_user_can_self_enrol() is deprecated, use current_user_can_enrol() instead.',
            DEBUG_DEVELOPER
        );

        return self::current_user_can_enrol($courseid);
    }

    /**
     * Whether the user is enrolled (incl. future/suspended) or — for the current $USER — may join.
     *
     * Membership test for the 'enrolledorself' display filter: the existing 'enrolled' semantics
     * (is_enrolled onlyactive=false, so future-dated and suspended enrolments count) plus the linked
     * courses the current viewer could enrol themselves into. That leg is evaluable only for
     * $USER, so when staff view another learner's plan it degrades to enrolled-only.
     *
     * A pending enrol_apply application needs no branch here: it writes a real user_enrolments
     * row, so the onlyactive=false test above already counts it as enrolled.
     *
     * @param \stdClass $course A course record with at least an id.
     * @param int $userid The user id.
     * @return bool
     */
    public static function user_enrolled_or_self_enrolable(\stdClass $course, int $userid): bool {
        global $USER;

        $coursecontext = \core\context\course::instance($course->id);
        if (is_enrolled($coursecontext, $userid, '', false)) {
            return true;
        }

        if ($userid !== (int) $USER->id) {
            return false;
        }

        return self::current_user_can_enrol((int) $course->id);
    }
}
