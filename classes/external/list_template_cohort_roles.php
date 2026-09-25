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
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_dimensions\helper;
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

        // Every role name below is the plain spelling: the roles pane writes them through
        // textContent. An assignment may hold any role, so names cover them all.
        $rolenames = helper::plain_role_names(array_map(static fn($role): int => (int) $role->id, get_all_roles()));

        // Assignable user-context roles.
        $roles = [];
        foreach (get_roles_for_contextlevels(CONTEXT_USER) as $roleid) {
            $roleid = (int) $roleid;
            if (isset($rolenames[$roleid])) {
                $roles[] = ['id' => $roleid, 'name' => $rolenames[$roleid]];
            }
        }

        // The plan's linked cohorts, with their member counts and contexts, in one query.
        $ctxfields = \context_helper::get_preload_record_columns_sql('ctx');
        $cohortrecords = $DB->get_records_sql(
            "SELECT c.id, c.name, c.contextid, $ctxfields,
                    (SELECT COUNT(1) FROM {cohort_members} cm WHERE cm.cohortid = c.id) AS members
               FROM {competency_templatecohort} tc
               JOIN {cohort} c ON c.id = tc.cohortid
               JOIN {context} ctx ON ctx.id = c.contextid
              WHERE tc.templateid = :templateid
           ORDER BY tc.id",
            ['templateid' => $template->get('id')]
        );
        $cohorts = [];
        foreach ($cohortrecords as $cohort) {
            \context_helper::preload_from_record($cohort);
            $cohorts[] = [
                'cohortid' => (int) $cohort->id,
                'name' => format_string(
                    $cohort->name,
                    true,
                    ['context' => \context::instance_by_id($cohort->contextid), 'escape' => false]
                ),
                'members' => (int) $cohort->members,
            ];
        }
        $cohortids = array_column($cohorts, 'cohortid');

        // Existing assignments whose cohort is one of the plan's cohorts.
        $assignments = [];
        if (!empty($cohortids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($cohortids, SQL_PARAMS_NAMED, 'c');
            $rows = cohort_role_assignment::get_records_select("cohortid $insql", $inparams, 'userid, roleid');
            $holderids = array_unique(array_map(static fn($row): int => (int) $row->get('userid'), $rows));
            $users = $holderids ? $DB->get_records_list('user', 'id', $holderids) : [];
            $synced = self::synced_counts($cohortids);
            $cohortnames = array_column($cohorts, 'name', 'cohortid');
            $cohortmembers = array_column($cohorts, 'members', 'cohortid');
            foreach ($rows as $row) {
                $userid = (int) $row->get('userid');
                $roleid = (int) $row->get('roleid');
                $cohortid = (int) $row->get('cohortid');
                $member = (int) ($cohortmembers[$cohortid] ?? 0);
                $syncedcount = $synced[$userid . '_' . $roleid . '_' . $cohortid] ?? 0;
                $user = $users[$userid] ?? null;
                $assignments[] = [
                    'id' => (int) $row->get('id'),
                    'userid' => $userid,
                    // Returned raw (PARAM_RAW), like the participants grid's names: under PARAM_TEXT a stored
                    // name holding "<" before a letter fails the returns check, and the whole listing with it.
                    'userfullname' => $user ? fullname($user) : (string) $userid,
                    'roleid' => $roleid,
                    'rolename' => $rolenames[$roleid] ?? (string) $roleid,
                    'cohortid' => $cohortid,
                    'cohortname' => (string) ($cohortnames[$cohortid] ?? $cohortid),
                    'status' => ($member > 0 && $syncedcount >= $member) ? 'synced' : 'pending',
                    'syncedcount' => $syncedcount,
                    'membercount' => $member,
                ];
            }
        }

        return ['canmanage' => true, 'roles' => $roles, 'cohorts' => $cohorts, 'assignments' => $assignments];
    }

    /**
     * How many of each cohort's members already hold each role tool_cohortroles assigned, in one query.
     *
     * A member in two of the cohorts counts towards both, as tool_cohortroles assigns per cohort.
     *
     * @param array $cohortids The cohort ids.
     * @return array Map of "userid_roleid_cohortid" to the number of member user contexts holding the role.
     */
    private static function synced_counts(array $cohortids): array {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal($cohortids, SQL_PARAMS_NAMED, 'sc');
        $sql = "SELECT ra.userid, ra.roleid, cm.cohortid, COUNT(DISTINCT ra.contextid) AS synced
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :usercontext
                  JOIN {cohort_members} cm ON cm.userid = ctx.instanceid
                 WHERE ra.component = :component
                   AND cm.cohortid $insql
              GROUP BY ra.userid, ra.roleid, cm.cohortid";
        $params += ['usercontext' => CONTEXT_USER, 'component' => 'tool_cohortroles'];
        $counts = [];
        $records = $DB->get_recordset_sql($sql, $params);
        foreach ($records as $record) {
            $counts[$record->userid . '_' . $record->roleid . '_' . $record->cohortid] = (int) $record->synced;
        }
        $records->close();
        return $counts;
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
                'userfullname' => new external_value(PARAM_RAW, 'Role holder full name, unformatted: write it as text'),
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
