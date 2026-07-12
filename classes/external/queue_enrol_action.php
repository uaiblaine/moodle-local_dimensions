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
 * Queue bulk apply/remove of an enrolment method over template-linked courses.
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
 * Web service: enqueue one adhoc task per (course, method, cohort) combination.
 *
 * The scan-then-queue section runs under the shared 'enrolqueue' lock so two concurrent
 * requests cannot double-queue the same combination; a combination already pending reports
 * 'processing' and is left alone. No audit event fires here - the task fires one per real
 * change when it executes.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class queue_enrol_action extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'templateid' => new external_value(PARAM_INT, 'Learning plan template id'),
            'cohortid' => new external_value(PARAM_INT, 'Cohort the method is bound to'),
            'method' => new external_value(PARAM_ALPHA, 'Enrolment method: cohort or self'),
            'roleid' => new external_value(PARAM_INT, 'Role the method assigns (apply only)'),
            'action' => new external_value(PARAM_ALPHA, 'Action: apply or remove'),
            'courseids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Course id'),
                'Selected course ids'
            ),
        ]);
    }

    /**
     * Validate and enqueue one task per selected course.
     *
     * @param int $templateid Template id.
     * @param int $cohortid Cohort id (must be attached to the template).
     * @param string $method 'cohort' or 'self'.
     * @param int $roleid Role id (validated against the eligible set on apply).
     * @param string $action 'apply' or 'remove'.
     * @param array $courseids Selected course ids.
     * @return array Keys: results (per-course status), queued (count).
     */
    public static function execute(
        int $templateid,
        int $cohortid,
        string $method,
        int $roleid,
        string $action,
        array $courseids
    ): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'templateid' => $templateid,
            'cohortid' => $cohortid,
            'method' => $method,
            'roleid' => $roleid,
            'action' => $action,
            'courseids' => $courseids,
        ]);
        if (!in_array($params['method'], [process_enrol_method::METHOD_COHORT, process_enrol_method::METHOD_SELF], true)) {
            throw new \invalid_parameter_exception('Invalid enrolment method.');
        }
        if (!in_array($params['action'], [process_enrol_method::ACTION_APPLY, process_enrol_method::ACTION_REMOVE], true)) {
            throw new \invalid_parameter_exception('Invalid action.');
        }
        $template = template::get_record(['id' => $params['templateid']]);
        if (!$template) {
            throw new \invalid_parameter_exception('Invalid template.');
        }
        $context = $template->get_context();
        self::validate_context($context);
        require_capability('moodle/competency:templatemanage', $context);
        if (!enrol_methods::cohort_linked($template->get('id'), $params['cohortid'])) {
            throw new \moodle_exception('central_roles_cohortnotlinked', 'local_dimensions');
        }
        if (!enrol_is_enabled($params['method'])) {
            throw new \moodle_exception('central_enrol_methoddisabled', 'local_dimensions');
        }
        if ($params['action'] === process_enrol_method::ACTION_APPLY) {
            $eligible = enrol_methods::eligible_roles($context);
            if (!isset($eligible[$params['roleid']])) {
                throw new \invalid_parameter_exception('Role is not eligible for enrolment methods.');
            }
        }

        // A course qualifies when it is linked to the template and the user may configure it.
        $bycompetency = enrol_methods::competency_course_ids($template->get('id'));
        $linked = [];
        foreach ($bycompetency as $ids) {
            foreach ($ids as $id) {
                $linked[$id] = true;
            }
        }
        $requested = array_values(array_unique(array_map('intval', $params['courseids'])));
        $allowed = enrol_methods::allowed_map(array_filter($requested, static function (int $id) use ($linked): bool {
            return isset($linked[$id]);
        }));

        $lockfactory = \core\lock\lock_config::get_lock_factory('local_dimensions');
        $lock = $lockfactory->get_lock('enrolqueue', 10);
        if (!$lock) {
            throw new \moodle_exception('central_enrol_busy', 'local_dimensions');
        }
        $results = [];
        $queued = 0;
        try {
            $pending = process_enrol_method::pending_map();
            foreach ($requested as $courseid) {
                $key = process_enrol_method::key($courseid, $params['method'], $params['cohortid']);
                if (!isset($linked[$courseid]) || !isset($allowed[$courseid])) {
                    $results[] = ['courseid' => $courseid, 'status' => 'skipped'];
                } else if (isset($pending[$key])) {
                    $results[] = ['courseid' => $courseid, 'status' => 'processing'];
                } else {
                    process_enrol_method::queue(
                        $params['action'],
                        $courseid,
                        $params['method'],
                        $params['cohortid'],
                        $params['roleid'],
                        (int) $template->get('id'),
                        (int) $USER->id
                    );
                    $pending[$key] = true;
                    $results[] = ['courseid' => $courseid, 'status' => 'queued'];
                    $queued++;
                }
            }
        } finally {
            $lock->release();
        }
        return ['results' => $results, 'queued' => $queued];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'results' => new external_multiple_structure(new external_single_structure([
                'courseid' => new external_value(PARAM_INT, 'Course id'),
                'status' => new external_value(PARAM_ALPHA, 'Outcome: queued|processing|skipped'),
            ])),
            'queued' => new external_value(PARAM_INT, 'How many tasks were queued'),
        ]);
    }
}
