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

declare(strict_types=1);

namespace local_dimensions\external;

use core_competency\api;
use core_competency\external\template_exporter;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use local_dimensions\helper;

/**
 * Web service: create a learning plan template plus local_dimensions customfields.
 *
 * Mirrors `core_competency_create_template` and accepts an additional
 * `customfields` array of `{shortname, value}` records. The response is the
 * native `template_exporter::get_read_structure()` shape with an appended
 * `customfields` array.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_template extends external_api {
    use customfields_io;

    /**
     * Parameters: native template create shape + customfields.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'template' => template_exporter::get_create_structure(),
            'customfields' => self::customfields_input_structure(),
        ]);
    }

    /**
     * Create the template, persist customfields, return the merged record.
     *
     * @param array $template Native template fields (per template_exporter::get_create_structure)
     * @param array $customfields List of {shortname, value} entries
     * @return array Template exporter shape + customfields array
     */
    public static function execute(array $template, array $customfields = []): array {
        global $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), [
            'template' => $template,
            'customfields' => $customfields,
        ]);

        $templateparams = $params['template'];
        $context = self::get_context_from_params($templateparams);
        self::validate_context($context);

        // Mirror native external::create_template — strip the context-resolution
        // helper fields and force contextid on the record.
        unset($templateparams['contextlevel']);
        unset($templateparams['instanceid']);
        $templateparams = (object) $templateparams;
        $templateparams->contextid = $context->id;

        $created = api::create_template($templateparams);
        $templateid = (int) $created->get('id');

        self::apply_customfields($templateid, helper::AREA_LP, $params['customfields'], true);

        $output = $PAGE->get_renderer('core');
        $exporter = new template_exporter($created);
        $payload = (array) $exporter->export($output);
        $payload['customfields'] = self::read_customfields($templateid, helper::AREA_LP);
        return $payload;
    }

    /**
     * Returns: native template read shape + customfields array.
     */
    public static function execute_returns(): external_single_structure {
        return self::with_customfields(template_exporter::get_read_structure());
    }
}
