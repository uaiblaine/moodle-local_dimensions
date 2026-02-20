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
 * This webservice wraps tool_lp_list_courses_using_competency and applies
 * the summaryenrollmentfilter admin setting to filter courses based on
 * the user's enrollment status.
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
        ]);
    }

    /**
     * Get courses linked to a competency, filtered by enrollment setting.
     *
     * @param int $competencyid The competency ID
     * @return array Filtered list of courses
     */
    public static function execute($competencyid) {
        global $USER, $DB;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'competencyid' => $competencyid,
        ]);
        $competencyid = $params['competencyid'];

        // Context validation.
        $systemcontext = context_system::instance();
        self::validate_context($systemcontext);
        require_capability('local/dimensions:view', $systemcontext);

        // Get all courses linked to the competency (visible only).
        $sql = "SELECT DISTINCT c.id, c.fullname, c.shortname, c.visible
                  FROM {competency_coursecomp} cc
                  JOIN {course} c ON c.id = cc.courseid
                 WHERE cc.competencyid = :competencyid AND c.visible = 1
              ORDER BY c.fullname ASC";
        $courses = $DB->get_records_sql($sql, ['competencyid' => $competencyid]);

        // Apply enrollment filter from admin settings.
        $filtermode = get_config('local_dimensions', 'summaryenrollmentfilter');
        if (!empty($filtermode) && $filtermode !== 'all') {
            require_once(__DIR__ . '/../calculator.php');
            $courses = \local_dimensions\calculator::filter_courses_by_enrollment(
                $courses,
                $USER->id,
                $filtermode
            );
        }

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
            ];
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
            ])
        );
    }
}
