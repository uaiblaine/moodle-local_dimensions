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
 * Attach a cohort to a template and queue background plan generation.
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
use local_dimensions\task\sync_template_cohort as sync_task;

/**
 * Web service: attach a cohort to a template (+ queue sync).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_template_cohort extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'templateid' => new external_value(PARAM_INT, 'Learning plan template id'),
            'cohortid' => new external_value(PARAM_INT, 'Cohort id'),
        ]);
    }

    /**
     * Attach the cohort and queue the background sync.
     *
     * @param int $templateid Template id.
     * @param int $cohortid Cohort id.
     * @return array Key: success (bool).
     */
    public static function execute(int $templateid, int $cohortid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'templateid' => $templateid,
            'cohortid' => $cohortid,
        ]);
        $template = template::get_record(['id' => $params['templateid']]);
        if (!$template) {
            return ['success' => false];
        }
        $context = $template->get_context();
        self::validate_context($context);
        require_capability('moodle/competency:templatemanage', $context);

        $relation = api::create_template_cohort($template->get('id'), $params['cohortid']);
        sync_task::queue($template->get('id'), $params['cohortid'], (int) $USER->id);

        // Core fires no event for the template-cohort relation; log the decision.
        \local_dimensions\event\template_cohort_added::create([
            'context' => $context,
            'objectid' => (int) $relation->get('id'),
            'other' => [
                'templateid' => (int) $template->get('id'),
                'cohortid' => (int) $params['cohortid'],
                'syncqueued' => true,
            ],
        ])->trigger();

        return ['success' => true];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the cohort was attached'),
        ]);
    }
}
