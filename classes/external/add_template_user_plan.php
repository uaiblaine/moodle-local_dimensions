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
 * Assign a learning plan template to an individual user (idempotent).
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

/**
 * Web service: create a plan from the template for one user, unless one already exists.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_template_user_plan extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'templateid' => new external_value(PARAM_INT, 'Learning plan template id'),
            'userid' => new external_value(PARAM_INT, 'User id'),
        ]);
    }

    /**
     * Create the plan unless the user already has a linked plan for this template.
     *
     * @param int $templateid Template id.
     * @param int $userid User id.
     * @return array Keys: success (bool), created (bool).
     */
    public static function execute(int $templateid, int $userid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'templateid' => $templateid,
            'userid' => $userid,
        ]);
        $template = template::get_record(['id' => $params['templateid']]);
        if (!$template) {
            return ['success' => false, 'created' => false];
        }
        $context = $template->get_context();
        self::validate_context($context);
        require_capability('moodle/competency:templatemanage', $context);

        if ($DB->record_exists('competency_plan', ['templateid' => $template->get('id'), 'userid' => $params['userid']])) {
            return ['success' => true, 'created' => false];
        }
        api::create_plan_from_template($template->get('id'), $params['userid']);
        return ['success' => true, 'created' => true];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the request succeeded'),
            'created' => new external_value(PARAM_BOOL, 'Whether a new plan was created'),
        ]);
    }
}
