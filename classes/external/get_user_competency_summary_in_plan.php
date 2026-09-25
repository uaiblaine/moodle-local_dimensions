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
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core\context\system as context_system;
use core_competency\api;
use local_dimensions\helper;

/**
 * Wrapper for tool_lp_data_for_user_competency_summary_in_plan, returning its data as JSON.
 *
 * Called over AJAX, the core service can hit an unset $PAGE->context, which moodle_page turns into
 * a coding_exception under developer debugging and a debugging notice otherwise.
 * external_function_info() resolves its return structure before the function runs, and the
 * exporters behind it (user_summary_exporter, via core_user's property definitions) call
 * get_list_of_themes(), which loads every theme's name string; a theme whose lang file reads $PAGE
 * (theme_scholastica does) then asks for the context. This wrapper declares a PARAM_RAW return
 * and calls the core function as a plain PHP method once validate_context() has run.
 *
 * The competency in the result also gains the taxonomy data and scale description the plugin's
 * accordion shows.
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
     * Calls the core tool_lp external function as a PHP method; see the class docblock.
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

        // Sets $PAGE's context before anything below can load theme strings.
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/dimensions:view', $context);

        // A plain method call, so its return structure is never resolved. It checks access to
        // the plan itself.
        $result = \tool_lp\external::data_for_user_competency_summary_in_plan(
            $params['competencyid'],
            $params['planid']
        );

        $competency = api::read_competency($params['competencyid']);
        $framework = api::read_framework($competency->get('competencyframeworkid'));
        $taxonomydata = helper::get_competency_taxonomy_data($competency, $framework);

        if (!empty($result->usercompetencysummary) && !empty($result->usercompetencysummary->competency)) {
            $result->usercompetencysummary->competency->taxonomy = (object) $taxonomydata;
            $result->usercompetencysummary->competency->scaledescription =
                self::resolve_show_scale_description()
                    ? self::get_scale_description($competency)
                    : '';
        }

        return json_encode($result);
    }

    /**
     * Whether the "About this scale" link is enabled.
     *
     * Unset reads as on, matching the checkbox's default, so an upgraded site that never saved
     * the setting keeps the link.
     *
     * @return bool
     */
    protected static function resolve_show_scale_description(): bool {
        $value = get_config('local_dimensions', 'showscaledescription');

        return $value === false || $value === '' ? true : (bool) $value;
    }

    /**
     * Read the competency's rating scale description, for the "About this scale" modal.
     *
     * Core resolves a competency's scale from the competency itself, falling back to its
     * framework. Most scales have no description at all, in which case this returns '' and the
     * client renders no button.
     *
     * competency::get_scale() fetches and loads the scale unguarded, so a competency pointing
     * at a deleted scale raises. That would take the whole accordion detail down over an
     * optional extra, so it is caught here and treated as "no description".
     *
     * Formatted once, from the stored text. grade_scale::get_description() already returns
     * formatted HTML (filtered in the page's context, and not cleaned), so formatting its result
     * again would run the filters twice and clean their output. The file URLs are rewritten the
     * way that method does, since the scale's files live in the system context.
     *
     * @param \core_competency\competency $competency The competency being summarised.
     * @return string Formatted description HTML, or an empty string.
     */
    protected static function get_scale_description(\core_competency\competency $competency): string {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        try {
            $scale = $competency->get_scale();
        } catch (\Throwable $e) {
            return '';
        }

        if (empty($scale) || trim((string) $scale->description) === '') {
            return '';
        }

        $description = file_rewrite_pluginfile_urls(
            $scale->description,
            'pluginfile.php',
            context_system::instance()->id,
            'grade',
            'scale',
            $scale->id
        );
        return format_text($description, $scale->descriptionformat, ['context' => $competency->get_context()]);
    }

    /**
     * Define return type.
     *
     * A JSON string rather than the core exporter structure; see the class docblock.
     *
     * @return external_value
     */
    public static function execute_returns() {
        return new external_value(PARAM_RAW, 'JSON-encoded user competency summary in plan data');
    }
}
