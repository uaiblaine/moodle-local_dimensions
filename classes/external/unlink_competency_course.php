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
 * Unlink a competency from a course (cascades activity links).
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

/**
 * Web service: unlink a competency from a course.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unlink_competency_course extends external_api {
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
     * Unlink the competency from the course.
     *
     * @param int $competencyid The competency id.
     * @param int $courseid The course id.
     * @return array Key: success (bool).
     */
    public static function execute(int $competencyid, int $courseid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'competencyid' => $competencyid,
            'courseid' => $courseid,
        ]);
        $competencyid = $params['competencyid'];
        $courseid = $params['courseid'];

        $coursecontext = context_course::instance($courseid);
        self::validate_context($coursecontext);

        $success = api::remove_competency_from_course($courseid, $competencyid);

        return ['success' => (bool) $success];
    }

    /**
     * Define the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the link was removed'),
        ]);
    }
}
