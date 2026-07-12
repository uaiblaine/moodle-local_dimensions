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
 * Lightweight poll of the enrolment-method task queue for the Enrolment methods tab.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core_competency\template;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_dimensions\local\enrol_methods;
use local_dimensions\task\process_enrol_method;

/**
 * Web service: which combinations are still pending, and the fresh configured state of rows.
 *
 * The pending list covers the template's linked courses for the selected method + cohort,
 * whatever template originally queued the task (the combination itself is what is busy).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_enrol_queue_status extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'templateid' => new external_value(PARAM_INT, 'Learning plan template id'),
            'cohortid' => new external_value(PARAM_INT, 'Cohort the statuses are evaluated against'),
            'method' => new external_value(PARAM_ALPHA, 'Enrolment method: cohort or self'),
            'courseids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Course id'),
                'Courses the client is tracking',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * Report pending combinations and the fresh configured state of the tracked courses.
     *
     * @param int $templateid Template id.
     * @param int $cohortid Cohort id.
     * @param string $method 'cohort' or 'self'.
     * @param array $courseids Courses the client is tracking (may be empty).
     * @return array Keys: pendingcourseids, items.
     */
    public static function execute(int $templateid, int $cohortid, string $method, array $courseids = []): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'templateid' => $templateid,
            'cohortid' => $cohortid,
            'method' => $method,
            'courseids' => $courseids,
        ]);
        if (!in_array($params['method'], [process_enrol_method::METHOD_COHORT, process_enrol_method::METHOD_SELF], true)) {
            throw new \invalid_parameter_exception('Invalid enrolment method.');
        }
        $template = template::get_record(['id' => $params['templateid']]);
        if (!$template) {
            return ['pendingcourseids' => [], 'items' => []];
        }
        $context = $template->get_context();
        self::validate_context($context);
        require_capability('moodle/competency:templatemanage', $context);

        $linked = [];
        foreach (enrol_methods::competency_course_ids($template->get('id')) as $ids) {
            foreach ($ids as $id) {
                $linked[$id] = true;
            }
        }
        $pendingids = [];
        foreach (\core\task\manager::get_adhoc_tasks(process_enrol_method::class) as $task) {
            $data = $task->get_custom_data();
            if (empty($data->courseid) || empty($data->method) || empty($data->cohortid)) {
                continue;
            }
            $courseid = (int) $data->courseid;
            if ((string) $data->method !== $params['method'] || (int) $data->cohortid !== $params['cohortid']) {
                continue;
            }
            if (isset($linked[$courseid]) && !isset($pendingids[$courseid])) {
                $pendingids[$courseid] = true;
            }
        }

        $tracked = array_values(array_unique(array_map('intval', $params['courseids'])));
        $statuses = enrol_methods::status_map($tracked, $params['cohortid']);
        $items = [];
        foreach ($tracked as $courseid) {
            $items[] = [
                'courseid' => $courseid,
                'configured' => (bool) $statuses[$courseid][$params['method']]->configured,
            ];
        }
        return ['pendingcourseids' => array_keys($pendingids), 'items' => $items];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'pendingcourseids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Course id with a pending task for the method + cohort')
            ),
            'items' => new external_multiple_structure(new external_single_structure([
                'courseid' => new external_value(PARAM_INT, 'Course id'),
                'configured' => new external_value(PARAM_BOOL, 'Whether the method is configured now'),
            ])),
        ]);
    }
}
