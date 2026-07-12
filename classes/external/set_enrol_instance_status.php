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
 * Enable or disable a course's enrol instance for one (method, cohort) combination.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core_competency\template;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_dimensions\local\enrol_methods;
use local_dimensions\task\process_enrol_method;

/**
 * Web service: flip the enabled state of the enrol instance(s) matching a combination.
 *
 * Unlike apply/remove this is a light metadata update, so it runs synchronously via
 * enrol_plugin::update_status() (which fires core's enrol_instance_updated event), exactly
 * like core's own /enrol/instances.php toggle. Every instance matching the combination is
 * set to the requested state; a combination with no instance is a graceful no-op.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_enrol_instance_status extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'templateid' => new external_value(PARAM_INT, 'Learning plan template id'),
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'cohortid' => new external_value(PARAM_INT, 'Cohort the method is bound to'),
            'method' => new external_value(PARAM_ALPHA, 'Enrolment method: cohort or self'),
            'enabled' => new external_value(PARAM_BOOL, 'Whether the instance should be enabled'),
        ]);
    }

    /**
     * Set the enabled state of the matching enrol instance(s).
     *
     * @param int $templateid Template id.
     * @param int $courseid Course id.
     * @param int $cohortid Cohort id (must be attached to the template).
     * @param string $method 'cohort' or 'self'.
     * @param bool $enabled Requested state.
     * @return array Keys: configured (bool), active (bool).
     */
    public static function execute(int $templateid, int $courseid, int $cohortid, string $method, bool $enabled): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'templateid' => $templateid,
            'courseid' => $courseid,
            'cohortid' => $cohortid,
            'method' => $method,
            'enabled' => $enabled,
        ]);
        if (!in_array($params['method'], [process_enrol_method::METHOD_COHORT, process_enrol_method::METHOD_SELF], true)) {
            throw new \invalid_parameter_exception('Invalid enrolment method.');
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
        $coursecontext = \context_course::instance($params['courseid']);
        foreach (process_enrol_method::REQUIRED_CAPS as $cap) {
            require_capability($cap, $coursecontext);
        }

        $plugin = enrol_get_plugin($params['method']);
        $instances = process_enrol_method::get_instances($params['courseid'], $params['method'], $params['cohortid']);
        if (!$plugin || !$instances) {
            return ['configured' => false, 'active' => false];
        }
        $target = $params['enabled'] ? ENROL_INSTANCE_ENABLED : ENROL_INSTANCE_DISABLED;
        foreach ($instances as $instance) {
            if ((int) $instance->status !== $target) {
                $plugin->update_status($instance, $target);
            }
        }
        return ['configured' => true, 'active' => $params['enabled']];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'configured' => new external_value(PARAM_BOOL, 'Whether any matching instance exists'),
            'active' => new external_value(PARAM_BOOL, 'Whether the instance(s) are enabled now'),
        ]);
    }
}
