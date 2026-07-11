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
 * Duplicate a learning plan template including the plugin's custom data.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core_competency\api;
use core_competency\template;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_dimensions\helper;

/**
 * Web service: duplicate a template, then copy the plugin data core misses.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class duplicate_template extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'templateid' => new external_value(PARAM_INT, 'Learning plan template id to duplicate'),
        ]);
    }

    /**
     * Duplicate the template and complete the copy with plugin-side data.
     *
     * Core's duplicate copies only the template row and its competency links
     * (the created event carries no source id, so this wrapper is the only
     * place that knows both templates). Cohort links are deliberately not
     * copied — see helper::copy_template_plugin_data().
     *
     * @param int $templateid Template id.
     * @return array Key: id (int) of the duplicated template.
     */
    public static function execute(int $templateid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'templateid' => $templateid,
        ]);
        $template = template::get_record(['id' => $params['templateid']], MUST_EXIST);
        $context = $template->get_context();
        self::validate_context($context);
        require_capability('moodle/competency:templatemanage', $context);

        $duplicate = api::duplicate_template($params['templateid']);
        $newid = (int) $duplicate->get('id');

        $counts = helper::copy_template_plugin_data($params['templateid'], $newid);

        // Core fired competency_template_created for the copy; this records the
        // plugin-side completion (source template + copied payload).
        \local_dimensions\event\template_duplicated::create([
            'context' => $context,
            'objectid' => $newid,
            'other' => [
                'sourceid' => (int) $params['templateid'],
                'copiedfields' => (int) $counts['fields'],
                'copiedfiles' => (int) $counts['files'],
            ],
        ])->trigger();

        return ['id' => $newid];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Id of the duplicated template'),
        ]);
    }
}
