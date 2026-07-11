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
 * Link a competency to a course module (activity).
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
 * Web service: link a competency to a course module.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class link_competency_module extends external_api {
    /**
     * Define the input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'competencyid' => new external_value(PARAM_INT, 'The competency id'),
            'cmid' => new external_value(PARAM_INT, 'The course module id'),
        ]);
    }

    /**
     * Link the competency to the activity and return its display row.
     *
     * @param int $competencyid The competency id.
     * @param int $cmid The course module id.
     * @return array The activity row {cmid, name, modname, iconurl, ruleoutcome, canmanage}.
     */
    public static function execute(int $competencyid, int $cmid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'competencyid' => $competencyid,
            'cmid' => $cmid,
        ]);
        $competencyid = $params['competencyid'];
        $cmid = $params['cmid'];

        $modcontext = context_module::instance($cmid);
        self::validate_context($modcontext);

        api::add_competency_to_course_module($cmid, $competencyid);

        [$course, $cm] = get_course_and_cm_from_cmid($cmid);
        $link = course_module_competency::get_record(
            ['competencyid' => $competencyid, 'cmid' => $cmid],
            MUST_EXIST
        );

        // Core fires no event for the module link lifecycle; log the decision.
        \local_dimensions\event\module_link_added::create([
            'context' => $modcontext,
            'objectid' => (int) $link->get('id'),
            'other' => ['competencyid' => $competencyid, 'cmid' => $cmid],
        ])->trigger();

        return [
            'cmid' => (int) $cm->id,
            'name' => $cm->get_formatted_name(),
            'modname' => $cm->modname,
            'iconurl' => $cm->get_icon_url()->out(false),
            'ruleoutcome' => (int) $link->get('ruleoutcome'),
            'canmanage' => (int) has_capability('moodle/competency:coursecompetencymanage', $modcontext),
        ];
    }

    /**
     * Define the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'name' => new external_value(PARAM_RAW, 'Activity name'),
            'modname' => new external_value(PARAM_PLUGIN, 'Module type'),
            'iconurl' => new external_value(PARAM_URL, 'Activity icon URL', VALUE_OPTIONAL),
            'ruleoutcome' => new external_value(PARAM_INT, 'Module competency rule outcome'),
            'canmanage' => new external_value(PARAM_INT, 'Whether the user can manage activity links'),
        ]);
    }
}
