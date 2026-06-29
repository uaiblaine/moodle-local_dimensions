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
 * List role assignments over a learning plan's linked cohorts, with assignable roles and sync status.
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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use tool_cohortroles\cohort_role_assignment;

/**
 * Web service: cohort role assignments for a template's cohorts (+ assignable roles + status).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class list_template_cohort_roles extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'templateid' => new external_value(PARAM_INT, 'Learning plan template id'),
        ]);
    }

    /**
     * Return assignable roles, the plan's cohorts, and existing assignments over them.
     *
     * @param int $templateid Template id.
     * @return array Keys: canmanage (bool), roles (list of {id,name}), cohorts (list of {cohortid,name,members}),
     *               assignments (list of {id,userid,userfullname,roleid,rolename,cohortid,cohortname,status,
     *               syncedcount,membercount}).
     */
    public static function execute(int $templateid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['templateid' => $templateid]);

        $system = context_system::instance();
        self::validate_context($system);
        require_capability('moodle/role:manage', $system);

        $template = template::get_record(['id' => $params['templateid']]);
        if (!$template) {
            return ['canmanage' => false, 'roles' => [], 'cohorts' => [], 'assignments' => []];
        }
        require_capability('moodle/competency:templateview', $template->get_context());

        // Assignable user-context roles.
        $rolenames = role_get_names();
        $roles = [];
        foreach (get_roles_for_contextlevels(CONTEXT_USER) as $roleid) {
            $roleid = (int) $roleid;
            if (isset($rolenames[$roleid])) {
                $roles[] = ['id' => $roleid, 'name' => $rolenames[$roleid]->localname];
            }
        }

        // The plan's linked cohorts.
        $cohorts = [];
        $cohortids = [];
        foreach (template_cohort::get_relations_by_templateid($template->get('id')) as $relation) {
            $cohortid = (int) $relation->get('cohortid');
            $cohort = $DB->get_record('cohort', ['id' => $cohortid], 'id, name, contextid');
            if (!$cohort) {
                continue;
            }
            $cohortids[] = $cohortid;
            $cohorts[] = [
                'cohortid' => $cohortid,
                'name' => format_string($cohort->name, true, ['context' => \context::instance_by_id($cohort->contextid)]),
                'members' => (int) $DB->count_records('cohort_members', ['cohortid' => $cohortid]),
            ];
        }

        // Existing assignments whose cohort is one of the plan's cohorts.
        $assignments = [];
        if (!empty($cohortids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($cohortids, SQL_PARAMS_NAMED, 'c');
            $rows = cohort_role_assignment::get_records_select("cohortid $insql", $inparams, 'userid, roleid');
            $cohortnames = array_column($cohorts, 'name', 'cohortid');
            $cohortmembers = array_column($cohorts, 'members', 'cohortid');
            foreach ($rows as $row) {
                $userid = (int) $row->get('userid');
                $roleid = (int) $row->get('roleid');
                $cohortid = (int) $row->get('cohortid');
                $member = (int) ($cohortmembers[$cohortid] ?? 0);
                $synced = self::synced_count($userid, $roleid, $cohortid);
                $user = $DB->get_record('user', ['id' => $userid], '*', IGNORE_MISSING);
                $assignments[] = [
                    'id' => (int) $row->get('id'),
                    'userid' => $userid,
                    'userfullname' => $user ? fullname($user) : (string) $userid,
                    'roleid' => $roleid,
                    'rolename' => isset($rolenames[$roleid]) ? $rolenames[$roleid]->localname : (string) $roleid,
                    'cohortid' => $cohortid,
                    'cohortname' => (string) ($cohortnames[$cohortid] ?? $cohortid),
                    'status' => ($member > 0 && $synced >= $member) ? 'synced' : 'pending',
                    'syncedcount' => $synced,
                    'membercount' => $member,
                ];
            }
        }

        return ['canmanage' => true, 'roles' => $roles, 'cohorts' => $cohorts, 'assignments' => $assignments];
    }

    /**
     * Count how many of a cohort's members already have the role assigned by tool_cohortroles.
     *
     * @param int $userid The role holder.
     * @param int $roleid The role.
     * @param int $cohortid The cohort.
     * @return int Number of synced member user-contexts.
     */
    private static function synced_count(int $userid, int $roleid, int $cohortid): int {
        global $DB;
        $sql = "SELECT COUNT(DISTINCT ra.contextid)
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :usercontext
                  JOIN {cohort_members} cm ON cm.userid = ctx.instanceid AND cm.cohortid = :cohortid
                 WHERE ra.component = :component
                   AND ra.roleid = :roleid
                   AND ra.userid = :userid";
        return (int) $DB->count_records_sql($sql, [
            'usercontext' => CONTEXT_USER,
            'cohortid' => $cohortid,
            'component' => 'tool_cohortroles',
            'roleid' => $roleid,
            'userid' => $userid,
        ]);
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'canmanage' => new external_value(PARAM_BOOL, 'Whether the user can manage cohort roles'),
            'roles' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Role id'),
                'name' => new external_value(PARAM_TEXT, 'Localised role name'),
            ])),
            'cohorts' => new external_multiple_structure(new external_single_structure([
                'cohortid' => new external_value(PARAM_INT, 'Cohort id'),
                'name' => new external_value(PARAM_TEXT, 'Cohort name'),
                'members' => new external_value(PARAM_INT, 'Member count'),
            ])),
            'assignments' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Assignment id'),
                'userid' => new external_value(PARAM_INT, 'Role holder user id'),
                'userfullname' => new external_value(PARAM_TEXT, 'Role holder full name'),
                'roleid' => new external_value(PARAM_INT, 'Role id'),
                'rolename' => new external_value(PARAM_TEXT, 'Localised role name'),
                'cohortid' => new external_value(PARAM_INT, 'Cohort id'),
                'cohortname' => new external_value(PARAM_TEXT, 'Cohort name'),
                'status' => new external_value(PARAM_ALPHA, 'pending or synced'),
                'syncedcount' => new external_value(PARAM_INT, 'Members already assigned'),
                'membercount' => new external_value(PARAM_INT, 'Total cohort members'),
            ])),
        ]);
    }
}
