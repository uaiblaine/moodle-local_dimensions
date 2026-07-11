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
 * Remove a cohort role assignment scoped to a learning plan's linked cohort (+ queue background sync).
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
use tool_cohortroles\cohort_role_assignment;

/**
 * Web service: delete a cohort role assignment that belongs to a template's cohort.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class remove_cohort_role extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'templateid' => new external_value(PARAM_INT, 'Learning plan template id'),
            'assignmentid' => new external_value(PARAM_INT, 'Cohort role assignment id'),
        ]);
    }

    /**
     * Delete the assignment after verifying it belongs to the template's cohorts, then queue the sync.
     *
     * @param int $templateid Template id.
     * @param int $assignmentid Assignment id.
     * @return array Key: success (bool).
     */
    public static function execute(int $templateid, int $assignmentid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'templateid' => $templateid,
            'assignmentid' => $assignmentid,
        ]);

        $system = context_system::instance();
        self::validate_context($system);
        require_capability('moodle/role:manage', $system);

        $template = template::get_record(['id' => $params['templateid']]);
        if (!$template) {
            throw new \moodle_exception('invalidtemplate', 'local_dimensions');
        }
        require_capability('moodle/competency:templateview', $template->get_context());

        $assignment = cohort_role_assignment::get_record(['id' => $params['assignmentid']]);
        if (!$assignment) {
            return ['success' => false];
        }
        // Scope: the assignment's cohort must be linked to this template.
        if (!template_cohort::get_relation($template->get('id'), (int) $assignment->get('cohortid'))->get('id')) {
            throw new \moodle_exception('central_roles_cohortnotlinked', 'local_dimensions');
        }

        $result = \tool_cohortroles\api::delete_cohort_role_assignment($params['assignmentid']);
        sync_cohort_roles::queue((int) $USER->id);

        if ($result) {
            // Counterpart of cohort_role_added: log who removed the mapping.
            \local_dimensions\event\cohort_role_removed::create([
                'context' => $system,
                'objectid' => (int) $params['assignmentid'],
                'relateduserid' => (int) $assignment->get('userid'),
                'other' => [
                    'templateid' => (int) $template->get('id'),
                    'cohortid' => (int) $assignment->get('cohortid'),
                    'roleid' => (int) $assignment->get('roleid'),
                ],
            ])->trigger();
        }

        return ['success' => (bool) $result];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the assignment was removed'),
        ]);
    }
}
