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
 * Lightweight external API returning completion + lock status for a list of
 * courses. Used by the view-competency hybrid loader to render completed and
 * locked cards immediately, before issuing the heavier per-course progress
 * call.
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
use local_dimensions\calculator;
use core\context\system as context_system;

/**
 * Get completion + lock status for many courses in a single batched call.
 */
class get_courses_completion_status extends external_api {
    /**
     * Parameters definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'courseids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Course ID'),
                'List of course IDs',
            ),
        ]);
    }

    /**
     * Return the completion + lock status for each requested course.
     *
     * @param int[] $courseids
     * @return array<int, array{courseid:int,iscompleted:bool,islocked:bool,enabled:bool}>
     */
    public static function execute($courseids) {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['courseids' => $courseids]);
        $courseids = $params['courseids'];

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/dimensions:view', $context);

        $results = [];
        foreach ($courseids as $cid) {
            $cid = (int) $cid;
            try {
                $course = $DB->get_record('course', ['id' => $cid], '*', \MUST_EXIST);
                $completion = new \completion_info($course);
                $enabled = $completion->is_enabled();
                $iscompleted = false;
                if ($enabled) {
                    $iscompleted = (bool) $completion->is_course_complete($USER->id);
                }
                $islocked = (bool) calculator::is_locked($course, $USER->id);
                $results[] = [
                    'courseid' => $cid,
                    'enabled' => (bool) $enabled,
                    'iscompleted' => $iscompleted,
                    'islocked' => $islocked,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'courseid' => $cid,
                    'enabled' => false,
                    'iscompleted' => false,
                    'islocked' => false,
                ];
            }
        }
        return $results;
    }

    /**
     * Returns definition.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'courseid' => new external_value(PARAM_INT, 'Course ID'),
                'enabled' => new external_value(PARAM_BOOL, 'Whether completion tracking is enabled'),
                'iscompleted' => new external_value(PARAM_BOOL, 'Whether the course is fully completed'),
                'islocked' => new external_value(PARAM_BOOL, 'Whether the course is locked for the user'),
            ])
        );
    }
}
