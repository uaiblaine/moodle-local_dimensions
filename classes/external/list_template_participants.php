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
 * List the participants (template plans) of a learning plan template, filtered + paginated.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core_competency\template;
use core_competency\template_cohort;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_dimensions\helper;
use local_dimensions\local\plan_status;

/**
 * Web service: filtered, paginated participant grid for a template.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class list_template_participants extends external_api {
    /** @var int Hard cap on the page size. */
    const MAX_LIMIT = 100;

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'templateid' => new external_value(PARAM_INT, 'Learning plan template id'),
            'cohortid' => new external_value(PARAM_INT, 'Filter by attached cohort id (0 = all)', VALUE_DEFAULT, 0),
            'query' => new external_value(PARAM_RAW_TRIMMED, 'Name search', VALUE_DEFAULT, ''),
            'includeindividual' => new external_value(PARAM_BOOL, 'Include unlinked individual plans', VALUE_DEFAULT, false),
            'limitfrom' => new external_value(PARAM_INT, 'Offset', VALUE_DEFAULT, 0),
            'limitnum' => new external_value(PARAM_INT, 'Page size', VALUE_DEFAULT, 50),
        ]);
    }

    /**
     * List template participants.
     *
     * @param int $templateid Template id.
     * @param int $cohortid Attached cohort filter (0 = all).
     * @param string $query Name search.
     * @param bool $includeindividual Include unlinked plans.
     * @param int $limitfrom Offset.
     * @param int $limitnum Page size.
     * @return array Keys: items (list), total (int).
     */
    public static function execute(
        int $templateid,
        int $cohortid = 0,
        string $query = '',
        bool $includeindividual = false,
        int $limitfrom = 0,
        int $limitnum = 50
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'templateid' => $templateid,
            'cohortid' => $cohortid,
            'query' => $query,
            'includeindividual' => $includeindividual,
            'limitfrom' => $limitfrom,
            'limitnum' => $limitnum,
        ]);
        $template = template::get_record(['id' => $params['templateid']]);
        if (!$template) {
            return ['items' => [], 'total' => 0];
        }
        $context = $template->get_context();
        self::validate_context($context);
        require_capability('moodle/competency:templateview', $context);
        $tid = (int) $template->get('id');

        // Attached cohorts (id => name); validate the cohort filter against them.
        $attached = [];
        foreach (template_cohort::get_relations_by_templateid($tid) as $relation) {
            $cid = (int) $relation->get('cohortid');
            $cohort = $DB->get_record('cohort', ['id' => $cid], 'id, name');
            if ($cohort) {
                $attached[$cid] = format_string($cohort->name, true, ['context' => $context]);
            }
        }
        $cohortfilter = (int) $params['cohortid'];
        if ($cohortfilter > 0 && !isset($attached[$cohortfilter])) {
            $cohortfilter = 0;
        }

        // Plans tied to this template (linked, plus unlinked individual ones when requested).
        $where = '(p.templateid = :tid';
        $sqlparams = ['tid' => $tid];
        if ($params['includeindividual']) {
            $where .= ' OR (p.templateid IS NULL AND p.origtemplateid = :otid)';
            $sqlparams['otid'] = $tid;
        }
        $where .= ')';

        $joins = '';
        if ($cohortfilter > 0) {
            $joins .= ' JOIN {cohort_members} cmf ON cmf.userid = p.userid AND cmf.cohortid = :cohortfilter ';
            $sqlparams['cohortfilter'] = $cohortfilter;
        }

        $namesql = '';
        if ($params['query'] !== '') {
            $namesql = ' AND ' . helper::sql_like_ai($DB->sql_fullname('u.firstname', 'u.lastname'), ':q');
            $sqlparams['q'] = '%' . $DB->sql_like_escape($params['query']) . '%';
        }

        $userfields = implode(', ', array_map(static fn($f) => "u.$f", \core_user\fields::get_name_fields()));
        $from = "{competency_plan} p
                 JOIN {user} u ON u.id = p.userid AND u.deleted = 0
                 $joins
                WHERE $where $namesql";

        $total = $DB->count_records_sql("SELECT COUNT(p.id) FROM $from", $sqlparams);
        $limitnum = $params['limitnum'] > 0 ? min($params['limitnum'], self::MAX_LIMIT) : 50;
        $records = $DB->get_records_sql(
            "SELECT p.id AS planid, p.userid, p.templateid, p.status, $userfields
               FROM $from
           ORDER BY u.lastname, u.firstname, p.id",
            $sqlparams,
            max(0, (int) $params['limitfrom']),
            $limitnum
        );

        $items = [];
        if (!empty($records)) {
            $membership = self::cohort_membership(
                array_keys($attached),
                array_map(static fn($r) => (int) $r->userid, $records),
                $context
            );
            $modelname = format_string($template->get('shortname'), true, ['context' => $context]);
            foreach ($records as $record) {
                $isindividual = $record->templateid === null;
                $cohorts = $membership[(int) $record->userid] ?? [];
                $items[] = [
                    'planid' => (int) $record->planid,
                    'userid' => (int) $record->userid,
                    'fullname' => fullname($record),
                    'status' => (int) $record->status,
                    'statuslabel' => plan_status::label((int) $record->status),
                    'isindividual' => $isindividual,
                    'modelo' => $isindividual ? '' : $modelname,
                    'cohorts' => implode(', ', $cohorts),
                ];
            }
        }

        return ['items' => $items, 'total' => (int) $total];
    }

    /**
     * Map each page user to the attached-cohort names they belong to (one batch query).
     *
     * @param array $attachedids Attached cohort ids.
     * @param array $userids Page user ids.
     * @param \context $context Context for formatting cohort names.
     * @return array Map of user id => list of cohort names.
     */
    private static function cohort_membership(array $attachedids, array $userids, \context $context): array {
        global $DB;
        if (empty($attachedids) || empty($userids)) {
            return [];
        }
        [$insqlc, $paramsc] = $DB->get_in_or_equal($attachedids, SQL_PARAMS_NAMED, 'c');
        [$insqlu, $paramsu] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
        $rows = $DB->get_records_sql(
            "SELECT cm.id, cm.userid, co.name
               FROM {cohort_members} cm
               JOIN {cohort} co ON co.id = cm.cohortid
              WHERE cm.cohortid $insqlc AND cm.userid $insqlu",
            $paramsc + $paramsu
        );
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->userid][] = format_string($row->name, true, ['context' => $context]);
        }
        return $map;
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(new external_single_structure([
                'planid' => new external_value(PARAM_INT, 'Plan id'),
                'userid' => new external_value(PARAM_INT, 'User id'),
                'fullname' => new external_value(PARAM_RAW, 'User full name'),
                'status' => new external_value(PARAM_INT, 'Plan status code'),
                'statuslabel' => new external_value(PARAM_TEXT, 'Plan status label'),
                'isindividual' => new external_value(PARAM_BOOL, 'Whether the plan is unlinked (individual)'),
                'modelo' => new external_value(PARAM_TEXT, 'Linked template name, empty when individual'),
                'cohorts' => new external_value(PARAM_TEXT, 'Attached cohorts the user belongs to, comma-joined'),
            ])),
            'total' => new external_value(PARAM_INT, 'Total matching participants'),
        ]);
    }
}
