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
 * Apply the ticked part of a previewed learning plan CSV import (Competency hub).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_dimensions\event\templates_imported;
use local_dimensions\form\import_templates_dynamic_form;
use local_dimensions\local\template_csv_importer;
use local_dimensions\local\template_csv_serializer;
use local_dimensions\local\template_import_verdict;
use local_dimensions\output\central\template_import_preview;

/**
 * Web service: write the selected rows of a previewed import, and report each outcome.
 *
 * Note what the browser does NOT send: no competency ids, no template ids, no field values.
 * A selection is a choice among options the server itself computed, re-checked against a
 * projection the server rebuilds from the file and the database as they are now.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class apply_import_templates extends external_api {
    /**
     * Define the input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'draftitemid' => new external_value(PARAM_INT, 'The draft file area holding the uploaded CSV'),
            'contextid' => new external_value(PARAM_INT, 'The context to write to'),
            'delimiter' => new external_value(PARAM_ALPHA, 'CSV delimiter name', VALUE_DEFAULT, 'comma'),
            'encoding' => new external_value(PARAM_RAW, 'CSV character encoding', VALUE_DEFAULT, 'UTF-8'),
            'updateexisting' => new external_value(PARAM_BOOL, 'Whether matched templates are updated', VALUE_DEFAULT, false),
            'selections' => new external_multiple_structure(
                new external_single_structure([
                    'itemkey' => new external_value(PARAM_ALPHANUM, 'The projected item key'),
                    'verdict' => new external_value(PARAM_ALPHA, 'The verdict the operator was shown'),
                    'fingerprint' => new external_value(PARAM_ALPHANUM, 'The projection fingerprint they decided on'),
                    'remedy' => new external_value(PARAM_ALPHA, 'The chosen remedy', VALUE_DEFAULT, 'none'),
                    'links' => new external_multiple_structure(
                        new external_value(PARAM_ALPHANUM, 'A ticked competency link item key'),
                        'The competency links to write',
                        VALUE_DEFAULT,
                        []
                    ),
                    'remaps' => new external_multiple_structure(
                        new external_single_structure([
                            'token' => new external_value(PARAM_ALPHANUMEXT, 'The CSV column token'),
                            'value' => new external_value(PARAM_TEXT, 'The option label to store, or empty to clear'),
                        ]),
                        'Chosen replacements for option labels this site does not have',
                        VALUE_DEFAULT,
                        []
                    ),
                ]),
                'The rows the operator ticked'
            ),
        ]);
    }

    /**
     * Apply the selections.
     *
     * @param int $draftitemid The draft file area holding the uploaded CSV.
     * @param int $contextid The context to write to.
     * @param string $delimiter CSV delimiter name.
     * @param string $encoding CSV character encoding.
     * @param bool $updateexisting Whether matched templates are updated.
     * @param array $selections The rows the operator ticked.
     * @return array{results: array, counts: array}
     * @throws \moodle_exception When the context is unusable or the file has gone.
     */
    public static function execute(
        int $draftitemid,
        int $contextid,
        string $delimiter = 'comma',
        string $encoding = 'UTF-8',
        bool $updateexisting = false,
        array $selections = []
    ): array {
        global $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), [
            'draftitemid' => $draftitemid,
            'contextid' => $contextid,
            'delimiter' => $delimiter,
            'encoding' => $encoding,
            'updateexisting' => $updateexisting,
            'selections' => $selections,
        ]);

        try {
            $context = \context::instance_by_id($params['contextid']);
        } catch (\moodle_exception $e) {
            throw new \moodle_exception('invalidcontext', 'error');
        }
        self::validate_context($context);
        require_capability('moodle/competency:templatemanage', $context);

        \core_php_time_limit::raise();
        raise_memory_limit(MEMORY_EXTRA);

        // A draft area emptied by cron cleanup gets its own message rather than degrading to
        // every single item reporting that it has gone.
        $text = import_templates_dynamic_form::read_uploaded_csv((int) $params['draftitemid']);
        if (trim($text) === '') {
            throw new \moodle_exception('central_plans_import_filegone', 'local_dimensions');
        }
        $parsed = template_csv_serializer::parse($text, $params['encoding'], $params['delimiter']);
        if ($parsed['error'] !== '') {
            throw new \moodle_exception('errorwithcsv', 'error', '', $parsed['error']);
        }

        $importer = new template_csv_importer($parsed, $context, (bool) $params['updateexisting']);
        $results = $importer->apply($params['selections']);

        $plan = $importer->get_plan();
        $output = $PAGE->get_renderer('core');
        $counts = array_fill_keys(template_import_verdict::outcomes(), 0);
        $payload = [];
        foreach ($results as $result) {
            $counts[$result['outcome']] = ($counts[$result['outcome']] ?? 0) + 1;
            $item = $plan ? $plan->get_item($result['itemkey']) : null;
            $payload[] = [
                'itemkey' => $result['itemkey'],
                'outcome' => $result['outcome'],
                'message' => $result['message'],
                'html' => $item === null ? '' : $output->render_from_template(
                    'local_dimensions/central/plans_import_row',
                    template_import_preview::export_row($item, $result['outcome'])
                ),
            ];
        }

        templates_imported::create([
            'context' => $context,
            'other' => $counts,
        ])->trigger();

        return ['results' => $payload, 'counts' => $counts];
    }

    /**
     * Define the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'results' => new external_multiple_structure(
                new external_single_structure([
                    'itemkey' => new external_value(PARAM_ALPHANUM, 'The item key'),
                    'outcome' => new external_value(PARAM_ALPHA, 'What happened to it'),
                    'message' => new external_value(PARAM_TEXT, 'The failure message, when there is one'),
                    'html' => new external_value(PARAM_RAW, 'The repainted row, or empty when the row has gone'),
                ]),
                'One entry per selection, in the order they were sent'
            ),
            'counts' => new external_single_structure([
                'created' => new external_value(PARAM_INT, 'Templates created'),
                'updated' => new external_value(PARAM_INT, 'Templates updated'),
                'skipped' => new external_value(PARAM_INT, 'Selections not applied'),
                'changed' => new external_value(PARAM_INT, 'Selections refused because the projection moved'),
                'gone' => new external_value(PARAM_INT, 'Selections whose row is no longer in the file'),
                'failed' => new external_value(PARAM_INT, 'Selections whose write failed'),
            ]),
        ]);
    }
}
