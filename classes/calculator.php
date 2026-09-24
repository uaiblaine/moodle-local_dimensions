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
     * The current user's course card data: lock state, card shape and per-section progress.
     *
     * Subsection contents count towards their parent section. The caller must first check
     * that the viewer may see the course at all ({@see helper::readable_competency_courses()},
     * as get_course_progress does): section names are returned even for a locked course,
     * because the card shows them blurred behind its lock overlay. Progress is computed only
     * for an unlocked, enrolled viewer.
     *
     * @param int $courseid
     * @return array
     */
    public static function get_course_section_progress($courseid) {
        global $DB, $USER;

        // Load the course ensuring all properties.
        $course = $DB->get_record('course', ['id' => $courseid], '*', \MUST_EXIST);

        // Point $COURSE at this course for the helpers below. The finally block restores it even
        // after an exception, so one failing course in the external function's per-course loop
        // cannot leak the wrong $COURSE into the others.
        global $COURSE;
        $savedcourse = $COURSE ?? null;
        $COURSE = $course;
        try {
            $modinfo = get_fast_modinfo($course);
            $sections = $modinfo->get_section_info_all();
            $completion = new \completion_info($course);

            /* The lock and its dates are resolved before the completion check: a locked course
               with completion off must still report the lock, not "Completion disabled". */

            $locked = self::is_locked($course, $USER->id);

            /* A locked card always takes the timeline shape with nothing named. The activity
               and section bodies carry live links, while the locked timeline has its section
               URLs blanked below and pointer-events: none in styles.css, so the lock overlay
               stays the only thing a locked learner can reach. Unlocked, the tracker and the
               plan both get their shape from resolve_card_shape(). */
            $shape = $locked
                ? ['mode' => constants::CARDMODE_TIMELINE, 'activity' => null, 'section' => null]
                : self::resolve_card_shape((int) $course->id, $USER->id);

            // Keep enrollment check for activity loop (extra security, though locked already covers it).
            $coursecontext = \core\context\course::instance($course->id);
            $isenrolled = is_enrolled($coursecontext, $USER->id, '', true);

            // A future enrolment start date wins over the course start date.
            $availabilitydate = self::get_availability_date($course, $USER->id);
            $formattedstartdate = userdate($availabilitydate, '%d/%m/%Y');

            // Determine if this is an enrollment start date (user enrolled but not yet active).
            $isenrolmentstart = false;
            if ($locked) {
                $enrolstartdate = self::get_enrolment_start_date($course, $USER->id);
                $isenrolmentstart = ($enrolstartdate !== null);
            }

            /* Only a locked card uses these: it words the date as an invitation, so the client
               needs to know whether the date is still ahead and whether the learner can join
               instead of waiting. Each enrolment question walks the course's enrol instances.
               Pending is asked only when joining is not on offer: a course can have a pending
               application on one instance and an open way in on another, and joining now wins. */
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

            /* Map each section to the delegated sections of its subsection activities, skipping
               subsections the learner cannot reach so their contents do not count towards the
               parent. A subsection being deleted matters most: core flags only the subsection
               module, so the activities inside keep deletioninprogress = 0 and uservisible =
               true until the adhoc delete task runs, while the course page hides the whole
               subsection at once (section_info ties a delegated section's uservisible to its
               module).

               uservisible, not counts_towards_progress(), is the right test here: a subsection
               released later is shown greyed with its contents unlisted, so counting them would
               add activities the learner cannot see. */
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
                /* Plain spelling: get_course_progress hands it to progress_card_body, whose double
                   stashes escape it once. */
                $sectionname = format_string(
                    $sectionname,
                    true,
                    ['context' => \core\context\course::instance($course->id), 'escape' => false]
                );

                $percentage = null;
                $hasactivities = false;
                // A restricted section shows a lock icon instead of a percentage, so its progress is not computed.

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

                // A restricted section links to the course page, which explains the restriction.
                if ($sectionlocked) {
                    $url = (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
                } else {
                    $url = (new \moodle_url('/course/section.php', ['id' => $section->id]))->out(false);
                }

                // A locked course shows no section links or section lock icons: its overlay covers the card.
                if ($locked) {
                    $url = '';
                    $sectionlocked = false;
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
     * The percentage a progress ring shows for a completed-out-of-total count.
     *
     * Both ends are reserved: only a full count returns 100 and only an empty one returns 0.
     * Plain rounding would turn 199 of 200 into 100, which the external function shows as
     * "completed", and 1 of 201 into 0, which reads as "not started"; those return 99 and 1.
     *
     * @param int $completed How many of the counted activities the user has completed.
     * @param int $total How many activities are counted (in a section or the whole course).
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
     * The course's overall completion percentage for a user.
     *
     * Does not call core_completion\progress::get_course_progress_percentage(). On Moodle 4.5
     * (MDL-60912, fixed in 5.0.7 and 5.1.4) its numerator, count_modules_completed(), still
     * counts a module being deleted that its denominator, completion_info::get_activities(),
     * drops, so it can overstate progress; and its denominator ignores visibility, so a hidden
     * activity keeps a learner below 100%. The fixed helpers (get_user_activities_with_completion()
     * and count_modules_completed() with a module list) do not exist on 4.5.
     *
     * Activities are selected by counts_towards_progress(), the predicate the section rings on
     * the same card use, and each is read through completion_info::get_data() in the same walk,
     * so the numerator cannot drift from the denominator.
     *
     * The course is fetched by id because modinfo checks its cache against $course->cacherev: a
     * stale course record would silently yield a stale module list.
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

        /* $COURSE is swapped and restored as in get_course_section_progress(): a module's
           dynamic-data callbacks may read it. */
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
     * The single predicate for the course bar and the section rings, which share a card. An
     * activity counts when all three hold:
     *
     * 1. Completion is tracked on it.
     * 2. The learner can see it: $cm->visible, and either listed on the course page or openable
     *    now. Both halves are needed: a date-restricted activity shown greyed is listed but not
     *    openable, and a stealth activity ("Make available but don't show on course page") is
     *    openable but not listed. Both are work the learner owes; core's is_visible_on_course_page()
     *    alone would drop the stealth one.
     * 3. It is theirs to do, now or later. filter_user_list() applies only the conditions that
     *    are permanent for a person - group, grouping, profile (see
     *    {@see \core_availability\tree_node::is_applied_to_user_lists()}) - so a date-locked
     *    activity still counts while one restricted to another group does not, which would
     *    otherwise make 100% unreachable.
     *
     * The explicit $cm->visible test also evens out a core change: for a teacher-hidden activity
     * that also shows a restriction, is_visible_on_course_page() is true on 4.5 and false once
     * MDL-66780 is in (5.1.5, 5.2.1). deletioninprogress is tested explicitly because
     * is_visible_on_course_page() returns null, not false, for such a module.
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
     * null means there was nothing to measure and reads as 0. The 0-100 clamp is a safety net
     * so that no numerator can draw a bar past full.
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
     *   trackable module the learner can open. Either way there is no sequence to draw, and
     *   a progress bar could only ever read 0% or 100%.
     * - section: exactly one section to draw (see collect_card_sections()), however many
     *   activities it holds. A one-row timeline naming a section usually called "General"
     *   informs nobody.
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

        /* counts_towards_progress() includes an activity released later, but the activity shape
           renders a "go to activity" button, so the activity must also be uservisible. A course
           whose only activity has not opened yet falls through to the section or timeline shape,
           which shows the same 0% without a link that goes nowhere. */
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
                /* $tracked (capped at 2) already answers "is there at least one", which is all
                   describe_section() needs. */
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
     * Mirrors format_singleactivity's get_activitytype() and get_main_activity() (Moodle 5.1+):
     * the format option 'activitytype' names the module type, and the first module of that type
     * in section 0 is the one the course page redirects to, so the card names the same one. On
     * 4.5 the format searches every section and moves its match into section 0 when the course
     * page loads. core_courseformat\main_activity_interface is not used because it only exists
     * from 5.1. The format alone does not mean a single activity: switching a course to it keeps
     * the old sections and modules.
     *
     * Unlike core, a hidden match is not force-shown: a candidate being deleted or not
     * uservisible yields null. uservisible, not counts_towards_progress(), is the test because
     * the card links to this activity, so the learner must be able to open it now.
     *
     * @param \stdClass $course The course record.
     * @param \course_modinfo $modinfo Its modinfo for the reading user.
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
            // a subsection delegate, or, on 5.1+, hidden from courses by an admin).
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
     * Uses counts_towards_progress(), like the percentages, so "does this course boil down to
     * one activity" is asked of the same set: one open activity beside one released later is
     * two, not one. Whether a single activity may be offered as a link is asked separately by
     * resolve_card_shape().
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
     * A qualifying section counts even when empty, so a course with one empty section takes
     * the section shape: the card still offers a way in, and a one-row timeline would say less.
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
            /* Plain, as is describe_section()'s name: accordion.js and progress_card_body each
               escape it once. */
            'name' => $cm->get_formatted_name(['escape' => false]),
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
     * @param bool $tracked Whether the section holds at least one activity that counts
     *                      towards progress; false draws no percentage.
     * @return array Keys name, hasownname, url and tracked.
     */
    private static function describe_section(\stdClass $course, \section_info $section, bool $tracked): array {
        $ownname = trim((string) ($section->name ?? ''));
        $context = \core\context\course::instance($course->id);

        return [
            'name' => $ownname !== '' ? format_string($ownname, true, ['context' => $context, 'escape' => false]) : '',
            'hasownname' => $ownname !== '',
            'url' => (new \moodle_url('/course/section.php', ['id' => $section->id]))->out(false),
            'tracked' => $tracked,
        ];
    }

    /**
     * Whether the course is locked for the user.
     *
     * Unlocked only when the user is actively enrolled and holds the role with shortname
     * 'student' in the course or a parent context.
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
     * The enrolment leg is only evaluated for the current $USER; for anyone else only an
     * active enrolment counts. A pending enrol_apply application satisfies neither leg, so this
     * returns false for it; see current_user_has_pending_application() for the state to show.
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
     * Dispatches per enrolment plugin because there is no generic question to ask:
     * enrol_plugin::can_self_enrol() returns false in the base class, and enrol_self is the
     * only core plugin that overrides it.
     *
     * enrol_self: can_self_enrol() covers instance status, the enrolment window, the
     * max-enrolled cap, an existing enrolment and the customint5 cohort restriction. It returns
     * true, a reason string, or null (customint5 names a deleted cohort), never false, so only
     * `=== true` counts.
     *
     * enrol_apply is optional: only builds whose plugin object provides allow_apply() are
     * handled. allow_apply() covers instance status, the new-applications flag, the enrolment
     * window and the cohort restriction; an existing application and the customint3 cap are
     * checked here, as enrol_apply's submit_application() does under its lock. Keep them in
     * step with that method.
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
                // Defensive: enrol_get_instances() already drops instances whose plugin code is missing.
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
            if (self::apply_is_full($instance, $plugin)) {
                continue;
            }
            return true;
        }

        return false;
    }

    /**
     * Whether the current $USER has an enrol_apply application still awaiting a decision.
     *
     * enrol_apply stores a pending application as a suspended user_enrolments row with no
     * enrolment period (enrol_apply::apply()), so is_enrolled() with onlyactive says no and
     * current_user_can_enrol() will not offer a second application. Without this state the card
     * would show the same padlock as for somebody who was never eligible.
     *
     * Only apply instances are read: a suspended row on a manual or self instance is an
     * administrative suspension, not an application. The enrolment row is matched rather than
     * enrol_apply_applicationinfo, which is deleted as soon as a decision is taken (approval
     * makes the row active; cancellation unenrols the user).
     *
     * The timeend clause excludes an approval that expired: with enrol_apply's expiredaction set
     * to a suspend value, core's process_expirations() suspends the lapsed active row again and
     * leaves timeend in the past. enrol_apply writes pending and waiting-list rows with
     * timeend = 0 (apply() sets no period; moving a row to the waiting list clears it).
     *
     * Same rule as enrol_apply\local\queue::awaiting_decision_where(), copied rather than called
     * so the integration stays optional. Keep the two in step.
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

    /**
     * Whether an enrol_apply instance has no place left.
     *
     * Asks the plugin's is_full() so the cap keeps enrol_apply's own definition, which does not
     * count expired enrolments (its default expiredaction, ENROL_EXT_REMOVED_KEEP, leaves them
     * in place). The call goes through the plugin object, guarded by is_callable(), because
     * naming a class in the optional enrol_apply namespace would fail to autoload on a site
     * without the plugin. Builds without is_full() count every enrolment row against
     * customint3, which is what "full" means for them.
     *
     * @param \stdClass $instance Enrol instance belonging to the apply plugin.
     * @param \enrol_plugin $plugin The apply plugin instance.
     * @return bool True when the instance has no place left.
     */
    private static function apply_is_full(\stdClass $instance, \enrol_plugin $plugin): bool {
        global $DB;

        if (is_callable([$plugin, 'is_full'])) {
            return (bool) $plugin->is_full($instance);
        }

        $cap = (int) $instance->customint3;

        return $cap > 0 && $DB->count_records('user_enrolments', ['enrolid' => $instance->id]) >= $cap;
    }
}
