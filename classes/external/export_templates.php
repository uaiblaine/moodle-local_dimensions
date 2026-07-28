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
 * Export learning plan templates (+ the plugin custom fields) as CSV (Competency hub Plans tab).
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
use local_dimensions\local\template_csv_serializer;

/**
 * Web service: serialize one or more learning plan templates and their links to CSV.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class export_templates extends external_api {
    /**
     * Define the input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'templateids' => new external_value(PARAM_SEQUENCE, 'Comma-separated template ids to export'),
            'contextid' => new external_value(PARAM_INT, 'The hub context the export is requested from'),
        ]);
    }

    /**
     * Build the CSV for the requested templates.
     *
     * @param string $templateids Comma-separated template ids.
     * @param int $contextid The hub context the export is requested from.
     * @return array{filename: string, content: string, frameworks: array}
     * @throws \moodle_exception When no usable template id was supplied.
     */
    public static function execute(string $templateids, int $contextid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'templateids' => $templateids,
            'contextid' => $contextid,
        ]);

        /* validate_context() sets $PAGE's context, and a second change away from a course
           category emits a debugging() notice, so it is called exactly ONCE on the requesting
           hub context. Per-template access is then checked with require_capability(), which
           does not touch $PAGE. */
        $context = self::get_context_from_params(['contextid' => $params['contextid']]);
        self::validate_context($context);

        $ids = [];
        foreach (explode(',', (string) $params['templateids']) as $rawid) {
            $templateid = (int) trim($rawid);
            if ($templateid > 0) {
                $ids[$templateid] = $templateid;
            }
        }
        if (empty($ids)) {
            throw new \moodle_exception('central_plans_export_none', 'local_dimensions');
        }

        $usable = [];
        foreach ($ids as $templateid) {
            $tpl = template::get_record(['id' => $templateid]);
            if (!$tpl) {
                continue;
            }
            require_capability('moodle/competency:templateview', $tpl->get_context());
            $usable[] = $templateid;
        }
        if (empty($usable)) {
            throw new \moodle_exception('central_plans_export_none', 'local_dimensions');
        }

        \core_php_time_limit::raise();
        raise_memory_limit(MEMORY_EXTRA);

        return template_csv_serializer::export_templates(
            $usable,
            (bool) get_config('local_dimensions', 'enablecustomscss')
        );
    }

    /**
     * Define the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'filename' => new external_value(PARAM_FILE, 'Suggested download filename'),
            'content' => new external_value(PARAM_RAW, 'The CSV content'),
            'frameworks' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Framework id'),
                    'idnumber' => new external_value(PARAM_TEXT, 'Framework ID number'),
                    'shortname' => new external_value(PARAM_TEXT, 'Framework name'),
                ]),
                'Frameworks the exported links reference, offerable as companion downloads'
            ),
        ]);
    }
}
