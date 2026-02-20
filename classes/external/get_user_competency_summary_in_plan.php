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
 * External API wrapper for tool_lp_data_for_user_competency_summary_in_plan.
 *
 * This wrapper exists to avoid a coding_exception that occurs when the core
 * webservice tool_lp_data_for_user_competency_summary_in_plan is called via AJAX.
 *
 * The issue: when the AJAX framework calls external_function_info() it resolves
 * the return type structure via _returns(). The core service's _returns() uses
 * complex exporters (user_competency_summary_in_plan_exporter) that chain into
 * user_summary_exporter → core\user::fill_properties_cache() → get_list_of_themes()
 * → theme_config->get_theme_name() → get_string(). If a theme's language file
 * (e.g. theme_scholastica) accesses $PAGE properties, it triggers
 * moodle_page->magic_get_context() BEFORE the AJAX framework has set the page
 * context, causing a coding_exception.
 *
 * This wrapper solves it by using a trivial _returns() (PARAM_RAW) that does NOT
 * trigger the exporter chain. The actual core API call happens inside execute()
 * where the page context has already been properly set.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->libdir/externallib.php");

use external_api;
use external_function_parameters;
use external_value;

/**
 * Wrapper for tool_lp_data_for_user_competency_summary_in_plan.
 *
 * Returns the same data as the core service but as a JSON-encoded string,
 * avoiding the exporter-based return type definition that causes context issues.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_user_competency_summary_in_plan extends external_api {
    /**
     * Define input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'competencyid' => new external_value(PARAM_INT, 'The competency ID'),
            'planid' => new external_value(PARAM_INT, 'The plan ID'),
        ]);
    }

    /**
     * Get competency summary data for a user's learning plan.
     *
     * Calls the core tool_lp external function directly as a PHP method call,
     * bypassing the webservice framework's external_function_info() chain that
     * would trigger the problematic exporter type resolution.
     *
     * @param int $competencyid The competency ID
     * @param int $planid The plan ID
     * @return string JSON-encoded competency summary data (same structure as tool_lp service)
     */
    public static function execute($competencyid, $planid) {
        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'competencyid' => $competencyid,
            'planid' => $planid,
        ]);

        // Set context BEFORE any code that might trigger theme/string loading.
        // This is the key fix: the context is available when the core API
        // internally uses exporters and renderers.
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/dimensions:view', $context);

        // Call the core tool_lp external function directly (PHP method call).
        // This does NOT trigger external_function_info() or _returns(), so
        // the exporter type resolution chain is never invoked by the framework.
        // The function internally calls validate_context() and uses the API +
        // exporters at runtime, where context is already set.
        $result = \tool_lp\external::data_for_user_competency_summary_in_plan(
            $params['competencyid'],
            $params['planid']
        );

        return json_encode($result);
    }

    /**
     * Define return type.
     *
     * Returns PARAM_RAW (JSON string) instead of a complex exporter-based structure.
     * This trivial return definition avoids the exporter chain that triggers
     * theme loading and the subsequent $PAGE->context exception.
     *
     * @return external_value
     */
    public static function execute_returns() {
        return new external_value(PARAM_RAW, 'JSON-encoded user competency summary in plan data');
    }
}
