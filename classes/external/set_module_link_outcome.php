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
 * Set the rule outcome of a course-module-competency link.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core\context\module as context_module;
use core_competency\api;
use core_competency\course_module_competency;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Web service: set the rule outcome of a module-competency link.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_module_link_outcome extends external_api {
    /**
     * Define the input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'competencyid' => new external_value(PARAM_INT, 'The competency id'),
            'cmid' => new external_value(PARAM_INT, 'The course module id'),
            'ruleoutcome' => new external_value(PARAM_INT, 'The rule outcome value'),
        ]);
    }

    /**
     * Set the rule outcome of the activity link.
     *
     * @param int $competencyid The competency id.
     * @param int $cmid The course module id.
     * @param int $ruleoutcome The rule outcome value.
     * @return array Key: success (bool).
     */
    public static function execute(int $competencyid, int $cmid, int $ruleoutcome): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'competencyid' => $competencyid,
            'cmid' => $cmid,
            'ruleoutcome' => $ruleoutcome,
        ]);
        $competencyid = $params['competencyid'];
        $cmid = $params['cmid'];
        $ruleoutcome = $params['ruleoutcome'];

        if (!array_key_exists($ruleoutcome, course_module_competency::get_ruleoutcome_list())) {
            throw new \invalid_parameter_exception('Invalid rule outcome');
        }

        $modcontext = context_module::instance($cmid);
        self::validate_context($modcontext);

        $link = course_module_competency::get_record(
            ['competencyid' => $competencyid, 'cmid' => $cmid],
            MUST_EXIST
        );
        $success = api::set_course_module_competency_ruleoutcome($link, $ruleoutcome);

        return ['success' => (bool) $success];
    }

    /**
     * Define the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the outcome was saved'),
        ]);
    }
}
