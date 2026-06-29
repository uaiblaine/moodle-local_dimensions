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
 * Assign a user-context role to a user over a learning plan's linked cohort (+ queue background sync).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core\context\system as context_system;
use core_competency\template;
use core_competency\template_cohort;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_dimensions\task\sync_cohort_roles;

/**
 * Web service: create a cohort role assignment scoped to a template's cohort.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_cohort_role extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'templateid' => new external_value(PARAM_INT, 'Learning plan template id'),
            'userid' => new external_value(PARAM_INT, 'User to receive the role'),
            'roleid' => new external_value(PARAM_INT, 'Role id (must be assignable at user context)'),
            'cohortid' => new external_value(PARAM_INT, 'Cohort id (must be linked to the template)'),
        ]);
    }

    /**
     * Create the assignment after scope/role validation, then queue the background sync.
     *
     * @param int $templateid Template id.
     * @param int $userid Role holder user id.
     * @param int $roleid Role id.
     * @param int $cohortid Cohort id.
     * @return array Key: success (bool).
     */
    public static function execute(int $templateid, int $userid, int $roleid, int $cohortid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'templateid' => $templateid,
            'userid' => $userid,
            'roleid' => $roleid,
            'cohortid' => $cohortid,
        ]);

        $system = context_system::instance();
        self::validate_context($system);
        require_capability('moodle/role:manage', $system);

        $template = template::get_record(['id' => $params['templateid']]);
        if (!$template) {
            throw new \moodle_exception('invalidtemplate', 'local_dimensions');
        }
        require_capability('moodle/competency:templateview', $template->get_context());

        // Scope: the cohort must be linked to this template.
        if (!template_cohort::get_relation($template->get('id'), $params['cohortid'])->get('id')) {
            throw new \moodle_exception('central_roles_cohortnotlinked', 'local_dimensions');
        }
        // The role must be assignable at user context.
        if (!in_array((int) $params['roleid'], array_map('intval', get_roles_for_contextlevels(CONTEXT_USER)), true)) {
            throw new \moodle_exception('central_roles_invalidrole', 'local_dimensions');
        }

        \tool_cohortroles\api::create_cohort_role_assignment((object) [
            'userid' => $params['userid'],
            'roleid' => $params['roleid'],
            'cohortid' => $params['cohortid'],
        ]);
        sync_cohort_roles::queue((int) $USER->id);

        return ['success' => true];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the assignment was created or already existed'),
        ]);
    }
}
