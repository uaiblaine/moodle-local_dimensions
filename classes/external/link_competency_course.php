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
 * Link a competency to a course.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core\context\course as context_course;
use core_competency\api;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use moodle_url;

/**
 * Web service: link a competency to a course (core enforces the course-context capability).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class link_competency_course extends external_api {
    /**
     * Define the input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'competencyid' => new external_value(PARAM_INT, 'The competency id'),
            'courseid' => new external_value(PARAM_INT, 'The course id'),
        ]);
    }

    /**
     * Link the competency to the course and return the new course row.
     *
     * @param int $competencyid The competency id.
     * @param int $courseid The course id.
     * @return array The course row {courseid, fullname, shortname, visible, ruleoutcome, modulecount,
     *               hascompletion, canmanage, courseurl, completionurl}.
     */
    public static function execute(int $competencyid, int $courseid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'competencyid' => $competencyid,
            'courseid' => $courseid,
        ]);
        $competencyid = $params['competencyid'];
        $courseid = $params['courseid'];

        $coursecontext = context_course::instance($courseid);
        self::validate_context($coursecontext);

        api::add_competency_to_course($courseid, $competencyid);

        $course = $DB->get_record(
            'course',
            ['id' => $courseid],
            'id, fullname, shortname, visible, enablecompletion',
            MUST_EXIST
        );
        $link = $DB->get_record(
            'competency_coursecomp',
            ['competencyid' => $competencyid, 'courseid' => $courseid],
            'ruleoutcome',
            MUST_EXIST
        );
        $hascompletion = !empty($course->enablecompletion)
            && $DB->record_exists('course_completion_criteria', ['course' => $courseid]);

        $row = [
            'courseid' => (int) $course->id,
            'fullname' => format_string($course->fullname, true, ['context' => $coursecontext]),
            'shortname' => format_string($course->shortname, true, ['context' => $coursecontext]),
            'visible' => (int) $course->visible,
            'ruleoutcome' => (int) $link->ruleoutcome,
            'modulecount' => 0,
            'hascompletion' => (int) $hascompletion,
            'canmanage' => (int) has_capability('moodle/competency:coursecompetencymanage', $coursecontext),
            'courseurl' => (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
        ];
        if (has_capability('moodle/course:update', $coursecontext)) {
            $row['completionurl'] = (new moodle_url('/course/completion.php', ['id' => $courseid]))->out(false);
        }
        return $row;
    }

    /**
     * Define the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'fullname' => new external_value(PARAM_RAW, 'Course full name'),
            'shortname' => new external_value(PARAM_RAW, 'Course short name'),
            'visible' => new external_value(PARAM_INT, 'Course visibility'),
            'ruleoutcome' => new external_value(PARAM_INT, 'Course competency rule outcome'),
            'modulecount' => new external_value(PARAM_INT, 'Number of linked activities'),
            'hascompletion' => new external_value(PARAM_INT, 'Whether the course has completion criteria configured'),
            'canmanage' => new external_value(PARAM_INT, 'Whether the user can manage links in this course'),
            'courseurl' => new external_value(PARAM_URL, 'URL of the course page'),
            'completionurl' => new external_value(
                PARAM_URL,
                'URL of the course completion settings (only when the user may edit them)',
                VALUE_OPTIONAL
            ),
        ]);
    }
}
