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
 * List where a competency is used: courses, activities and learning plan templates.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core\context\system as context_system;
use core_competency\api;
use core_competency\competency;
use core_competency\competency_framework;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use moodle_url;

/**
 * Web service: read-only usage lists for one competency, backing the clickable
 * counters on the Structure tab detail pane (linked courses / activities / plans).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class competency_usage extends external_api {
    /**
     * Define the parameters for the competency_usage external function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'competencyid' => new external_value(PARAM_INT, 'Competency id'),
        ]);
    }

    /**
     * Collect the courses, activities and learning plan templates using the competency.
     *
     * Courses and activities are filtered by the core API to what the caller may see;
     * the template list mirrors what the hub Plans tab shows.
     *
     * @param int $competencyid Competency id.
     * @return array Keys: courses, activities, templates (lists of display rows).
     */
    public static function execute(int $competencyid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['competencyid' => $competencyid]);
        $competencyid = $params['competencyid'];

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/competency:competencyview', $context);

        $competency = competency::get_record(['id' => $competencyid], MUST_EXIST);
        $framework = competency_framework::get_record(
            ['id' => $competency->get('competencyframeworkid')],
            MUST_EXIST
        );
        if (!competency_framework::can_read_context($framework->get_context())) {
            throw new \required_capability_exception(
                $framework->get_context(),
                'moodle/competency:competencyview',
                'nopermissions',
                ''
            );
        }

        // Courses (core filters each by the caller's per-course capabilities).
        $courses = [];
        $activities = [];
        foreach (api::list_courses_using_competency($competencyid) as $course) {
            $coursecontext = \core\context\course::instance($course->id);
            $coursename = format_string($course->fullname, true, ['context' => $coursecontext]);
            $courseshortname = format_string($course->shortname, true, ['context' => $coursecontext]);
            $courses[] = [
                'id' => (int) $course->id,
                'name' => $coursename,
                'shortname' => $courseshortname,
                'url' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            ];

            // Activities linked within this course, labelled with their course.
            $modinfo = get_fast_modinfo($course->id);
            foreach (api::list_course_modules_using_competency($competencyid, $course->id) as $cmid) {
                $cm = $modinfo->cms[$cmid] ?? null;
                if (!$cm) {
                    continue;
                }
                $cmurl = $cm->url;
                $activities[] = [
                    'cmid' => (int) $cmid,
                    'name' => format_string($cm->name, true, ['context' => $coursecontext]),
                    'coursename' => $coursename,
                    'courseshortname' => $courseshortname,
                    'url' => $cmurl ? $cmurl->out(false) : '',
                ];
            }
        }

        // Learning plan templates bundling the competency (hub "Plans" naming).
        $templates = [];
        foreach (api::list_templates_using_competency($competencyid) as $template) {
            $templates[] = [
                'id' => (int) $template->get('id'),
                'name' => format_string($template->get('shortname')),
                'visible' => (bool) $template->get('visible'),
            ];
        }

        return ['courses' => $courses, 'activities' => $activities, 'templates' => $templates];
    }

    /**
     * Define the return structure for the competency_usage external function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courses' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Course id'),
                'name' => new external_value(PARAM_TEXT, 'Course full name'),
                'shortname' => new external_value(PARAM_TEXT, 'Course short name'),
                'url' => new external_value(PARAM_URL, 'Course view URL'),
            ])),
            'activities' => new external_multiple_structure(new external_single_structure([
                'cmid' => new external_value(PARAM_INT, 'Course module id'),
                'name' => new external_value(PARAM_TEXT, 'Activity name'),
                'coursename' => new external_value(PARAM_TEXT, 'Course full name'),
                'courseshortname' => new external_value(PARAM_TEXT, 'Course short name'),
                'url' => new external_value(PARAM_URL, 'Activity view URL, empty when the module has no view page'),
            ])),
            'templates' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Template id'),
                'name' => new external_value(PARAM_TEXT, 'Template name'),
                'visible' => new external_value(PARAM_BOOL, 'Whether the template is enabled'),
            ])),
        ]);
    }
}
