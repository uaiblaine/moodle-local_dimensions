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
 * Project an uploaded learning plan CSV against this site, writing nothing (Competency hub).
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
use local_dimensions\form\import_templates_dynamic_form;
use local_dimensions\local\template_csv_serializer;
use local_dimensions\local\template_import_analyser;
use local_dimensions\output\central\template_import_preview;

/**
 * Web service: render the projected result of a learning plan CSV import.
 *
 * A read function in the strict sense — the analyser it drives holds no write at all, so calling
 * it twice on the same file changes nothing but the answer, if the site moved in between.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class preview_import_templates extends external_api {
    /**
     * Define the input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'draftitemid' => new external_value(PARAM_INT, 'The draft file area holding the uploaded CSV'),
            'contextid' => new external_value(PARAM_INT, 'The context the import would write to'),
            'delimiter' => new external_value(PARAM_ALPHA, 'CSV delimiter name', VALUE_DEFAULT, 'comma'),
            'encoding' => new external_value(PARAM_RAW, 'CSV character encoding', VALUE_DEFAULT, 'UTF-8'),
            'updateexisting' => new external_value(PARAM_BOOL, 'Whether matched templates are updated', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Build the preview.
     *
     * @param int $draftitemid The draft file area holding the uploaded CSV.
     * @param int $contextid The context the import would write to.
     * @param string $delimiter CSV delimiter name.
     * @param string $encoding CSV character encoding.
     * @param bool $updateexisting Whether matched templates are updated.
     * @return array{html: string, counts: array, missingframeworks: array, canapply: bool}
     * @throws \moodle_exception When the context is unusable or the file cannot be read.
     */
    public static function execute(
        int $draftitemid,
        int $contextid,
        string $delimiter = 'comma',
        string $encoding = 'UTF-8',
        bool $updateexisting = false
    ): array {
        global $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), [
            'draftitemid' => $draftitemid,
            'contextid' => $contextid,
            'delimiter' => $delimiter,
            'encoding' => $encoding,
            'updateexisting' => $updateexisting,
        ]);

        // An id that names no context reaches this as an unhandled moodle_exception otherwise,
        // which the modal can only show as a stack trace.
        try {
            $context = \context::instance_by_id($params['contextid']);
        } catch (\moodle_exception $e) {
            throw new \moodle_exception('invalidcontext', 'error');
        }
        self::validate_context($context);
        require_capability('moodle/competency:templatemanage', $context);

        \core_php_time_limit::raise();
        raise_memory_limit(MEMORY_EXTRA);

        $text = import_templates_dynamic_form::read_uploaded_csv((int) $params['draftitemid']);
        if (trim($text) === '') {
            throw new \moodle_exception('central_plans_import_filegone', 'local_dimensions');
        }
        $parsed = template_csv_serializer::parse($text, $params['encoding'], $params['delimiter']);
        if ($parsed['error'] !== '') {
            throw new \moodle_exception('errorwithcsv', 'error', '', $parsed['error']);
        }

        $analyser = new template_import_analyser($parsed, $context, (bool) $params['updateexisting']);
        $plan = $analyser->analyse();

        $renderable = new template_import_preview($plan, [
            'draftitemid' => (int) $params['draftitemid'],
            'contextid' => (int) $context->id,
            'delimiter' => (string) $params['delimiter'],
            'encoding' => (string) $params['encoding'],
            'updateexisting' => empty($params['updateexisting']) ? 0 : 1,
        ]);
        $output = $PAGE->get_renderer('core');

        $missing = [];
        foreach ($plan->get_missing_structures() as $structure) {
            $missing[] = [
                'idnumber' => (string) $structure['idnumber'],
                'shortname' => (string) $structure['shortname'],
            ];
        }

        return [
            'html' => $output->render_from_template(
                'local_dimensions/central/plans_import_preview',
                $renderable->export_for_template($output)
            ),
            'counts' => $plan->get_counts(),
            'missingframeworks' => $missing,
            'canapply' => $renderable->can_apply(),
        ];
    }

    /**
     * Define the return structure.
     *
     * Every count is declared as its own flat scalar: clean_returnvalue() silently strips keys a
     * structure does not declare, so a nested preview payload would fail by rendering a blank
     * cell with no error anywhere. The key set mirrors template_import_plan::get_counts().
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'html' => new external_value(PARAM_RAW, 'The rendered preview body'),
            'counts' => new external_single_structure([
                'total' => new external_value(PARAM_INT, 'Rows projected'),
                'create' => new external_value(PARAM_INT, 'Rows that would create a template'),
                'update' => new external_value(PARAM_INT, 'Rows that would update a template'),
                'insync' => new external_value(PARAM_INT, 'Rows that would change nothing'),
                'skip' => new external_value(PARAM_INT, 'Rows held back because updating is off'),
                'conflict' => new external_value(PARAM_INT, 'Rows needing a decision'),
                'blocked' => new external_value(PARAM_INT, 'Rows that cannot be applied as they stand'),
                'orphanlink' => new external_value(PARAM_INT, 'Competency rows with no template row'),
                'selectable' => new external_value(PARAM_INT, 'Rows that can be ticked'),
                'preselected' => new external_value(PARAM_INT, 'Rows ticked by default'),
                'links' => new external_value(PARAM_INT, 'Competency links projected'),
                'linksmatched' => new external_value(PARAM_INT, 'Competency links that resolved'),
                'linksunresolved' => new external_value(PARAM_INT, 'Competency links that did not resolve'),
            ]),
            'missingframeworks' => new external_multiple_structure(
                new external_single_structure([
                    'idnumber' => new external_value(PARAM_TEXT, 'Structure ID number'),
                    'shortname' => new external_value(PARAM_TEXT, 'Structure name'),
                ]),
                'Structures the file needs and this site does not have'
            ),
            'canapply' => new external_value(PARAM_BOOL, 'Whether anything in the file can be applied'),
        ]);
    }
}
