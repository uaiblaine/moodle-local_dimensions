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
 * External API to get courses linked to a competency with enrollment filter.
 *
 * It answers only for a competency the given plan reaches, the rule
 * view-competency.php applies (plan_access::competency_scope()). It then runs
 * its own query over competency_coursecomp and resolves the enrolment-filter
 * cascade (competency -> plan's template -> global setting) to filter courses
 * by the plan owner's enrolment. Each surviving course also carries its rule
 * outcome, the competency's activity links inside it, what the viewer can do
 * with it (open, enrol, pending or locked) and its card shape: its single
 * activity or single section when it resolves to one, otherwise the timeline.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core\context\system as context_system;
use core\context\course as context_course;
use local_dimensions\calculator;
use local_dimensions\constants;
use local_dimensions\local\plan_access;

/**
 * External API to get courses linked to a competency with enrollment filter.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_competency_courses extends external_api {
    /** @var string The viewer is actively enrolled and can open the course. */
    private const ACCESS_OPEN = 'open';

    /** @var string The viewer is not enrolled but a way in is open to them right now. */
    private const ACCESS_ENROL = 'enrol';

    /** @var string The viewer has applied to join and is waiting for a decision. */
    private const ACCESS_PENDING = 'pending';

    /** @var string The viewer can neither open the course nor join it. */
    private const ACCESS_LOCKED = 'locked';

    /**
     * Define input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'competencyid' => new external_value(PARAM_INT, 'The competency ID'),
            'planid' => new external_value(
                PARAM_INT,
                'The id of a learning plan the viewer may read and that reaches the competency'
            ),
        ]);
    }

    /**
     * Get courses linked to a competency, filtered by enrollment setting.
     *
     * @param int $competencyid The competency ID
     * @param int $planid The learning plan ID, required: it gates the competency and drives the enrolment-filter cascade
     * @return array Filtered list of courses, each with its rule outcome and linked activities
     * @throws \moodle_exception 'invalidplan' when no plan has that id, 'competency_id_missing' when the plan does not reach
     *     the competency.
     * @throws \required_capability_exception When the current user may not read the plan or its owner's user
     *     competencies.
     */
    public static function execute($competencyid, $planid) {
        global $USER, $DB;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'competencyid' => $competencyid,
            'planid' => $planid,
        ]);
        $competencyid = $params['competencyid'];
        $planid = $params['planid'];

        // Context validation.
        $systemcontext = context_system::instance();
        self::validate_context($systemcontext);
        require_capability('local/dimensions:view', $systemcontext);

        /* The same gate as view-competency.php, before anything about the competency is read: a plan
           the viewer may read (a refusal is core's own error, see plan_access), and a competency that
           plan reaches. Without it any competency id would list its courses and activities. */
        $plan = plan_access::read_plan($planid);
        $scope = plan_access::require_competency_in_scope($plan, $competencyid);

        /* The cards describe the plan owner, who is not the viewer when staff review a learner's plan:
           the enrolment filter, progress, the activities and the card shape are the owner's. Only
           access and its lock date are the viewer's, because they decide what clicking the card does.
           So the viewer must also be allowed to read the owner's user competencies, as the tracker page
           and its card services require: read_plan() accepts planviewdraft alone on a draft plan, which
           grants nothing about the learner. The accordion never asks for such a viewer, whose rows have
           no detail region, but the service is callable directly. */
        $ownerid = plan_access::require_owner_readable($plan);

        // Outside the plan the plan layer of the cascade does not apply (competency -> global only).
        $templateid = $scope === plan_access::SCOPE_PLAN ? (int) $plan->get('templateid') : 0;

        /* Get all courses linked to the competency (visible only). The unique index
           courseidcompetencyid guarantees one row per course, so selecting the link's
           ruleoutcome alongside cannot multiply the cards. */
        $sql = "SELECT DISTINCT c.id, c.fullname, c.shortname, c.visible, cc.ruleoutcome
                  FROM {competency_coursecomp} cc
                  JOIN {course} c ON c.id = cc.courseid
                 WHERE cc.competencyid = :competencyid AND c.visible = 1
              ORDER BY c.fullname ASC";
        $courses = $DB->get_records_sql($sql, ['competencyid' => $competencyid]);

        // Resolve the enrolment filter through the cascade (competency -> plan -> global).
        $filtermode = \local_dimensions\helper::resolve_enrollmentfilter_for_view($competencyid, $templateid);
        if ($filtermode !== \local_dimensions\constants::ENROLLMENTFILTER_ALL) {
            $courses = \local_dimensions\calculator::filter_courses_by_enrollment($courses, $ownerid, $filtermode);
        }

        // Activity links are resolved only for the courses that survived the filter.
        $activitiesbycourse = self::get_linked_activities($competencyid, array_keys($courses), $ownerid);

        // Build the response with course image and progress.
        $result = [];
        foreach ($courses as $course) {
            $coursecontext = context_course::instance($course->id);

            // Get course image URL.
            $courseimage = '';
            $courseobj = new \core_course_list_element($course);
            foreach ($courseobj->get_course_overviewfiles() as $file) {
                $isimage = $file->is_valid_image();
                if ($isimage) {
                    $courseimage = \moodle_url::make_pluginfile_url(
                        $file->get_contextid(),
                        $file->get_component(),
                        $file->get_filearea(),
                        null,
                        $file->get_filepath(),
                        $file->get_filename()
                    )->out(false);
                    break;
                }
            }

            /* Not core_completion\progress::get_course_progress_percentage(), which miscounts on
               Moodle 4.5 (MDL-60912); see calculator::course_completion_percentage(). */
            $progress = calculator::course_completion_percentage((int) $course->id, $ownerid);

            /* What the viewer can do with this course. Not calculator::is_locked(), which also
               locks anyone enrolled without the student role, such as staff reviewing a plan;
               the question here is whether this viewer can open the course. */
            $access = self::ACCESS_OPEN;
            $lockdate = 0;
            $isenrolstart = false;
            if (!is_enrolled($coursecontext, $USER->id, '', true)) {
                /* A pending enrol_apply application gets its own state rather than the padlock;
                   see calculator::current_user_has_pending_application(). Enrol is checked
                   first: a course can offer an open way in beside a pending application. */
                if (\local_dimensions\calculator::current_user_can_enrol((int) $course->id)) {
                    $access = self::ACCESS_ENROL;
                } else if (\local_dimensions\calculator::current_user_has_pending_application((int) $course->id)) {
                    $access = self::ACCESS_PENDING;
                } else {
                    $access = self::ACCESS_LOCKED;
                }
            }
            if ($access === self::ACCESS_LOCKED) {
                // Only a locked card needs the full course record, for its availability dates.
                $fullcourse = get_course($course->id);
                $lockdate = (int) \local_dimensions\calculator::get_availability_date($fullcourse, $USER->id);
                $isenrolstart = \local_dimensions\calculator::get_enrolment_start_date(
                    $fullcourse,
                    $USER->id
                ) !== null;
            }

            /* Names travel plain (tags stripped, nothing escaped): accordion.js escapes each one
               once where it writes it into the page. */
            $row = [
                'id' => (int) $course->id,
                'fullname' => format_string($course->fullname, true, ['context' => $coursecontext, 'escape' => false]),
                'shortname' => format_string($course->shortname, true, ['context' => $coursecontext, 'escape' => false]),
                'courseimage' => $courseimage,
                'progress' => $progress,
                'visible' => 1,
                'ruleoutcome' => (int) $course->ruleoutcome,
                'access' => $access,
                'lockdate' => $lockdate,
                'isenrolstart' => $isenrolstart,
                'activities' => $activitiesbycourse[(int) $course->id] ?? [],
            ];

            /* The resolver the tracker uses too, so both views agree on a course's shape. Only an
               open card resolves one; the others stay on the timeline, since naming an activity
               the viewer cannot open helps nobody. */
            $row['cardmode'] = constants::CARDMODE_TIMELINE;
            if ($access === self::ACCESS_OPEN) {
                $shape = \local_dimensions\calculator::resolve_card_shape(
                    (int) $course->id,
                    $ownerid
                );
                $row['cardmode'] = $shape['mode'];
                if ($shape['activity'] !== null) {
                    $row['activity'] = $shape['activity'];
                }
                if ($shape['section'] !== null) {
                    $row['section'] = $shape['section'];
                }
            }

            $result[] = $row;
        }

        return $result;
    }

    /**
     * The competency's activity links inside the given courses, grouped by course.
     *
     * Mirrors the section cascade in calculator::get_course_section_progress(): the raw
     * visibility flags decide what is skipped, uservisible decides what is locked, and a
     * locked row links to the course page rather than the activity, where core explains
     * the restriction. Modules are read from modinfo rather than from the link rows, so a
     * link that outlived its module simply never matches.
     *
     * The course-level lock is not applied: the plan accordion has no locked overlay to explain
     * it, and calculator::is_locked() would strip the links from staff whose course card still
     * opens.
     *
     * @param int $competencyid The competency id.
     * @param array $courseids Ids of the courses that survived the enrolment filter.
     * @param int $userid The plan owner, whose visibility and completion the rows describe.
     * @return array Course id => list of activity rows.
     */
    private static function get_linked_activities(int $competencyid, array $courseids, int $userid): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/completionlib.php');

        if (empty($courseids)) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
        $links = $DB->get_records_sql(
            "SELECT mc.cmid, mc.ruleoutcome, cm.course
               FROM {competency_modulecomp} mc
               JOIN {course_modules} cm ON cm.id = mc.cmid
              WHERE mc.competencyid = :competencyid AND cm.course $insql",
            ['competencyid' => $competencyid] + $inparams
        );

        $outcomesbycourse = [];
        foreach ($links as $link) {
            $outcomesbycourse[(int) $link->course][(int) $link->cmid] = (int) $link->ruleoutcome;
        }

        $result = [];
        foreach ($outcomesbycourse as $courseid => $outcomes) {
            $modinfo = get_fast_modinfo($courseid, $userid);
            $sectionbyid = [];
            foreach ($modinfo->get_section_info_all() as $sectioninfo) {
                $sectionbyid[(int) $sectioninfo->id] = $sectioninfo;
            }
            $completion = new \completion_info(get_course($courseid));
            $courseurl = (new \moodle_url('/course/view.php', ['id' => $courseid]))->out(false);

            // Walking modinfo rather than the link rows keeps the activities in course order.
            $rows = [];
            foreach ($modinfo->get_cms() as $cm) {
                if (!isset($outcomes[(int) $cm->id]) || $cm->deletioninprogress) {
                    continue;
                }
                $section = $sectionbyid[(int) $cm->section] ?? null;
                if ($section === null || !$section->visible || !$cm->visible || !$cm->visibleoncoursepage) {
                    continue;
                }

                // A section hidden entirely by a restriction takes its activities with it.
                $sectionlocked = false;
                if (!$section->uservisible) {
                    if (empty($section->availableinfo)) {
                        continue;
                    }
                    $sectionlocked = true;
                }

                $locked = false;
                if (!$cm->uservisible) {
                    /* cm_info does not copy a section's availableinfo down to its modules, so a
                       module under a restricted section arrives here with an empty one. Without
                       the inherited flag it would be dropped as "hide entirely" when the section
                       rule shows it greyed. */
                    if (!$sectionlocked && empty($cm->availableinfo)) {
                        continue;
                    }
                    $locked = true;
                }

                if (!$cm->has_view()) {
                    $url = '';
                } else if ($locked) {
                    $url = $courseurl;
                } else {
                    $url = $cm->url->out(false);
                }

                $hascompletion = $completion->is_enabled($cm) != COMPLETION_TRACKING_NONE;
                $iscompleted = false;
                if ($hascompletion) {
                    $cmdata = $completion->get_data($cm, true, $userid);
                    $iscompleted = $cmdata->completionstate == COMPLETION_COMPLETE
                        || $cmdata->completionstate == COMPLETION_COMPLETE_PASS;
                }

                $rows[] = [
                    'cmid' => (int) $cm->id,
                    'name' => $cm->get_formatted_name(['escape' => false]),
                    'modtype' => (string) $cm->modfullname,
                    'iconurl' => $cm->get_icon_url()->out(false),
                    // Cast: a module that answers the feature with false rather than null yields a bool.
                    'purpose' => (string) plugin_supports('mod', $cm->modname, FEATURE_MOD_PURPOSE, MOD_PURPOSE_OTHER),
                    'url' => $url,
                    'locked' => $locked,
                    'ruleoutcome' => $outcomes[(int) $cm->id],
                    'has_completion' => $hascompletion,
                    'is_completed' => $iscompleted,
                ];
            }

            $result[$courseid] = $rows;
        }

        return $result;
    }

    /**
     * Define return structure.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Course ID'),
                'fullname' => new external_value(PARAM_RAW, 'Course full name, plain text'),
                'shortname' => new external_value(PARAM_RAW, 'Course short name, plain text'),
                'courseimage' => new external_value(PARAM_URL, 'Course image URL', VALUE_OPTIONAL),
                'progress' => new external_value(PARAM_INT, 'The plan owner\'s course completion percentage'),
                'visible' => new external_value(PARAM_INT, 'Course visibility'),
                'ruleoutcome' => new external_value(PARAM_INT, 'What completing the course does to the competency'),
                'access' => new external_value(
                    PARAM_ALPHA,
                    'What the viewer can do with the course: open, enrol, pending or locked'
                ),
                'lockdate' => new external_value(PARAM_INT, 'Availability timestamp when locked, 0 otherwise'),
                'isenrolstart' => new external_value(PARAM_BOOL, 'Whether the lock date is an enrolment start date'),
                'cardmode' => new external_value(
                    PARAM_ALPHA,
                    'Which shape the card takes: activity, section or timeline'
                ),
                'activity' => new external_single_structure(
                    [
                        'cmid' => new external_value(PARAM_INT, 'Course module id'),
                        'name' => new external_value(PARAM_RAW, 'Activity name, plain text'),
                        'url' => new external_value(PARAM_URL, 'Activity URL, empty when it has no view page'),
                        'completed' => new external_value(PARAM_BOOL, 'Whether the plan owner completed the activity'),
                        'tracked' => new external_value(PARAM_BOOL, 'Whether completion is tracked for it'),
                    ],
                    'The single trackable activity, present only when the course resolves to exactly one',
                    VALUE_OPTIONAL
                ),
                'section' => new external_single_structure(
                    [
                        'name' => new external_value(PARAM_TEXT, 'Section name, plain text, empty when Moodle generated it'),
                        'hasownname' => new external_value(PARAM_BOOL, 'Whether a teacher named the section'),
                        'url' => new external_value(PARAM_URL, 'URL of the section'),
                        'tracked' => new external_value(PARAM_BOOL, 'Whether the section holds a tracked activity'),
                    ],
                    'The course\'s only section, present only when cardmode is section',
                    VALUE_OPTIONAL
                ),
                'activities' => new external_multiple_structure(
                    new external_single_structure([
                        'cmid' => new external_value(PARAM_INT, 'Course module id'),
                        'name' => new external_value(PARAM_RAW, 'Activity name, plain text'),
                        'modtype' => new external_value(PARAM_RAW, 'Localised module type name'),
                        'iconurl' => new external_value(PARAM_URL, 'Activity icon URL'),
                        'purpose' => new external_value(PARAM_ALPHANUMEXT, 'Module purpose, the icon container class'),
                        'url' => new external_value(
                            PARAM_URL,
                            'Activity URL, the course URL when restricted, empty when the module has no view page'
                        ),
                        'locked' => new external_value(PARAM_BOOL, 'Whether an access restriction applies to the plan owner'),
                        'ruleoutcome' => new external_value(PARAM_INT, 'What completing the activity does to the competency'),
                        'has_completion' => new external_value(PARAM_BOOL, 'Whether completion is tracked'),
                        'is_completed' => new external_value(PARAM_BOOL, 'Whether the plan owner completed the activity'),
                    ])
                ),
            ])
        );
    }
}
