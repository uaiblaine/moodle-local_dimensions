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

namespace local_dimensions\external;

use core_competency\api;
use core_competency\competency;
use core_competency\competency_framework;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_dimensions\hook\competency_panel_sections;

/**
 * Web service: the sections other plugins add to the Competency hub's panel for one competency.
 *
 * The competency's row decides the framework and its context, never the client. The viewer must be logged in,
 * not a guest, and may read the competency as core's read_competency() requires (competencyview or
 * competencymanage in the framework's context); only then is {@see competency_panel_sections} dispatched, and
 * the answer is the hook's sections as an allowlist. With no listener the dispatch sends no statement, so the
 * call costs its guards alone. A lookup by id is not filtered by local_unlistedcourses' category rule, as no
 * lookup by id of the hub's services is: core's own read_competency() answers the same id.
 *
 * A listener's own exception is not caught, on purpose: a broken listener should be seen. The call then fails as a
 * whole, and the panel shows its one quiet line ("Some details could not be loaded.") in place of every section,
 * the rest of the panel as it was. Nothing a listener passes to the hook throws (it drops and says why).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_competency_panel_sections extends external_api {
    /**
     * The parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'competencyid' => new external_value(PARAM_INT, 'Competency id'),
        ]);
    }

    /**
     * The sections the listeners add for one competency, for the current user.
     *
     * @param int $competencyid The competency.
     * @return array Keys: competencyid, sections (see execute_returns()).
     * @throws \dml_missing_record_exception When the competency does not exist.
     * @throws \require_login_exception When the viewer is not logged in.
     * @throws \moodle_exception noguest for the guest.
     * @throws \required_capability_exception When the viewer may not read the competency.
     */
    public static function execute(int $competencyid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['competencyid' => $competencyid]);
        $competency = competency::get_record(['id' => $params['competencyid']], MUST_EXIST);
        $frameworkid = (int) $competency->get('competencyframeworkid');
        $framework = competency_framework::get_record(['id' => $frameworkid], MUST_EXIST);
        $context = $framework->get_context();
        self::validate_context($context);
        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }
        api::require_enabled();
        if (!competency_framework::can_read_context($context)) {
            throw new \required_capability_exception($context, 'moodle/competency:competencyview', 'nopermissions', '');
        }

        $hook = new competency_panel_sections(
            (int) $competency->get('id'),
            $frameworkid,
            $context,
            competency_framework::can_manage_context($context)
        );
        \core\di::get(\core\hook\manager::class)->dispatch($hook);

        return ['competencyid' => (int) $competency->get('id'), 'sections' => $hook->get_sections()];
    }

    /**
     * The answer: the hook's sections field for field, every text PARAM_TEXT and every URL local.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'competencyid' => new external_value(PARAM_INT, 'The competency'),
            'sections' => new external_multiple_structure(new external_single_structure([
                'key' => new external_value(PARAM_ALPHANUMEXT, 'The section key, prefixed with its component'),
                'component' => new external_value(PARAM_COMPONENT, 'The plugin that added it'),
                'title' => new external_value(PARAM_TEXT, 'The title, plain text'),
                'rows' => new external_multiple_structure(new external_single_structure([
                    'label' => new external_value(PARAM_TEXT, 'The label, plain text'),
                    'value' => new external_value(PARAM_TEXT, 'The value, plain text'),
                    'tone' => new external_value(PARAM_ALPHA, 'A status tone, or empty for none'),
                ])),
                'note' => new external_value(PARAM_TEXT, 'A note, plain text, or empty'),
                'linklabel' => new external_value(PARAM_TEXT, 'The link label, plain text, or empty'),
                'linkurl' => new external_value(PARAM_LOCALURL, 'The link, a page of this site, or empty'),
            ])),
        ]);
    }
}
