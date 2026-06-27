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
 * List the cohorts attached to a learning plan template, with member and plan counts.
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

/**
 * Web service: cohorts attached to a template + counts.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class list_template_cohorts extends external_api {
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
     * List attached cohorts with member and plan counts.
     *
     * @param int $templateid Template id.
     * @return array Keys: cohorts (list of {cohortid, name, members, plans}).
     */
    public static function execute(int $templateid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['templateid' => $templateid]);
        $template = template::get_record(['id' => $params['templateid']]);
        if (!$template) {
            return ['cohorts' => []];
        }
        $context = $template->get_context();
        self::validate_context($context);
        require_capability('moodle/competency:templateview', $context);

        $cohorts = [];
        foreach (template_cohort::get_relations_by_templateid($template->get('id')) as $relation) {
            $cohortid = (int) $relation->get('cohortid');
            $cohort = $DB->get_record('cohort', ['id' => $cohortid], 'id, name');
            if (!$cohort) {
                continue;
            }
            $members = $DB->count_records('cohort_members', ['cohortid' => $cohortid]);
            $plans = $DB->count_records_sql(
                "SELECT COUNT(p.id)
                   FROM {competency_plan} p
                  WHERE p.templateid = :tid
                    AND p.userid IN (SELECT cm.userid FROM {cohort_members} cm WHERE cm.cohortid = :cid)",
                ['tid' => $template->get('id'), 'cid' => $cohortid]
            );
            $cohorts[] = [
                'cohortid' => $cohortid,
                'name' => format_string($cohort->name, true, ['context' => $context]),
                'members' => (int) $members,
                'plans' => (int) $plans,
            ];
        }
        return ['cohorts' => $cohorts];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cohorts' => new external_multiple_structure(new external_single_structure([
                'cohortid' => new external_value(PARAM_INT, 'Cohort id'),
                'name' => new external_value(PARAM_TEXT, 'Cohort name'),
                'members' => new external_value(PARAM_INT, 'Number of cohort members'),
                'plans' => new external_value(PARAM_INT, 'Plans from this template for cohort members'),
            ])),
        ]);
    }
}
