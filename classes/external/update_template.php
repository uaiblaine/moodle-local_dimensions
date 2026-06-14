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
 * Web service: update a learning plan template plus local_dimensions customfields.
 *
 * Mirrors `core_competency_update_template` plus an additional `customfields`
 * array. Unlike the native function (which returns only a boolean), this
 * wrapper returns the full updated record so callers don't need a second
 * `read_template` round-trip.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_template extends external_api {
    use customfields_io;

    /**
     * Describe the web service parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'template' => template_exporter::get_update_structure(),
            'customfields' => self::customfields_input_structure(),
        ]);
    }

    /**
     * Update the template, persist customfields, return the merged record.
     *
     * @param array $template Template record fields to update.
     * @param array $customfields Form-shaped customfield values to persist.
     * @return array Merged template record plus a customfields key.
     */
    public static function execute(array $template, array $customfields = []): array {
        global $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), [
            'template' => $template,
            'customfields' => $customfields,
        ]);

        $templateparams = $params['template'];
        $existing = api::read_template($templateparams['id']);
        $context = $existing->get_context();
        self::validate_context($context);

        api::update_template((object) $templateparams);

        $templateid = (int) $templateparams['id'];
        self::apply_customfields($templateid, helper::AREA_LP, $params['customfields'], false);

        // Re-read after writes so the response reflects the persisted state.
        $updated = api::read_template($templateid);
        $output = $PAGE->get_renderer('core');
        $exporter = new template_exporter($updated);
        $payload = (array) $exporter->export($output);
        $payload['customfields'] = self::read_customfields($templateid, helper::AREA_LP);
        return $payload;
    }

    /**
     * Describe the web service return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return self::with_customfields(template_exporter::get_read_structure());
    }
}
