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
 * This webservice runs its own query over competency_coursecomp and resolves
 * the enrolment-filter cascade (competency -> plan's template -> global
 * setting) to filter courses based on the user's enrollment status. Each
 * surviving course also carries its rule outcome and the competency's
 * activity links inside it.
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

/**
 * External API to get courses linked to a competency with enrollment filter.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_competency_courses extends external_api {
    /**
     * Define input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'competencyid' => new external_value(PARAM_INT, 'The competency ID'),
            'planid' => new external_value(PARAM_INT, 'The learning plan ID (drives the enrolment-filter cascade)'),
        ]);
    }

    /**
     * Get courses linked to a competency, filtered by enrollment setting.
     *
     * @param int $competencyid The competency ID
     * @param int $planid The learning plan ID (drives the enrolment-filter cascade)
     * @return array Filtered list of courses, each with its rule outcome and linked activities
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
        // The accordion only lists the plan's own competencies, so the plan's template applies.
        $templateid = 0;
        if ($planid > 0) {
            try {
                $templateid = (int) \core_competency\api::read_plan($planid)->get('templateid');
            } catch (\Exception $e) {
                $templateid = 0;
            }
        }
        $filtermode = \local_dimensions\helper::resolve_enrollmentfilter_for_view($competencyid, $templateid);
        if ($filtermode !== \local_dimensions\constants::ENROLLMENTFILTER_ALL) {
            $courses = \local_dimensions\calculator::filter_courses_by_enrollment($courses, $USER->id, $filtermode);
        }

        // Activity links are resolved only for the courses that survived the filter.
        $activitiesbycourse = self::get_linked_activities($competencyid, array_keys($courses));

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

            // Get course progress for the current user.
            $progress = 0;
            $completion = new \completion_info(get_course($course->id));
            if ($completion->is_enabled()) {
                $progressvalue = \core_completion\progress::get_course_progress_percentage(
                    get_course($course->id),
                    $USER->id
                );
                if ($progressvalue !== null) {
                    $progress = (int) round($progressvalue);
                }
            }

            $result[] = [
                'id' => (int) $course->id,
                'fullname' => format_string($course->fullname, true, ['context' => $coursecontext]),
                'shortname' => format_string($course->shortname, true, ['context' => $coursecontext]),
                'courseimage' => $courseimage,
                'progress' => $progress,
                'visible' => 1,
                'ruleoutcome' => (int) $course->ruleoutcome,
                'activities' => $activitiesbycourse[(int) $course->id] ?? [],
            ];
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
     * The course-level lock is deliberately not applied here: unlike the tracker card, the
     * plan accordion has no locked overlay to carry the message, and calculator::is_locked()
     * also reports true for anyone enrolled without the student role - which would strip the
     * links from staff whose course card link right above still works.
     *
     * @param int $competencyid The competency id.
     * @param array $courseids Ids of the courses that survived the enrolment filter.
     * @return array Course id => list of activity rows.
     */
    private static function get_linked_activities(int $competencyid, array $courseids): array {
        global $CFG, $DB, $USER;
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
            $modinfo = get_fast_modinfo($courseid);
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
                    $cmdata = $completion->get_data($cm, true, $USER->id);
                    $iscompleted = $cmdata->completionstate == COMPLETION_COMPLETE
                        || $cmdata->completionstate == COMPLETION_COMPLETE_PASS;
                }

                $rows[] = [
                    'cmid' => (int) $cm->id,
                    'name' => $cm->get_formatted_name(),
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
                'fullname' => new external_value(PARAM_RAW, 'Course full name'),
                'shortname' => new external_value(PARAM_RAW, 'Course short name'),
                'courseimage' => new external_value(PARAM_URL, 'Course image URL', VALUE_OPTIONAL),
                'progress' => new external_value(PARAM_INT, 'Course completion progress percentage'),
                'visible' => new external_value(PARAM_INT, 'Course visibility'),
                'ruleoutcome' => new external_value(PARAM_INT, 'What completing the course does to the competency'),
                'activities' => new external_multiple_structure(
                    new external_single_structure([
                        'cmid' => new external_value(PARAM_INT, 'Course module id'),
                        'name' => new external_value(PARAM_RAW, 'Activity name'),
                        'modtype' => new external_value(PARAM_RAW, 'Localised module type name'),
                        'iconurl' => new external_value(PARAM_URL, 'Activity icon URL'),
                        'purpose' => new external_value(PARAM_ALPHANUMEXT, 'Module purpose, the icon container class'),
                        'url' => new external_value(
                            PARAM_URL,
                            'Activity URL, the course URL when restricted, empty when the module has no view page'
                        ),
                        'locked' => new external_value(PARAM_BOOL, 'Whether an access restriction applies'),
                        'ruleoutcome' => new external_value(PARAM_INT, 'What completing the activity does to the competency'),
                        'has_completion' => new external_value(PARAM_BOOL, 'Whether completion is tracked'),
                        'is_completed' => new external_value(PARAM_BOOL, 'Whether the user completed the activity'),
                    ])
                ),
            ])
        );
    }
}
