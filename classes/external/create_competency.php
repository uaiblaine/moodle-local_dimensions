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
use core_competency\external\competency_exporter;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use local_dimensions\helper;

/**
 * Web service: create a competency plus local_dimensions customfields.
 *
 * Mirrors `core_competency_create_competency` with an added `customfields`
 * input. Context is resolved from the competency's framework, matching the
 * native function's contract.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_competency extends external_api {
    use customfields_io;

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'competency' => competency_exporter::get_create_structure(),
            'customfields' => self::customfields_input_structure(),
        ]);
    }

    /**
     * Create the competency, persist customfields, return the merged record.
     *
     * @param array $competency Competency record fields to create.
     * @param array $customfields Form-shaped customfield values to persist.
     * @return array Merged competency record plus a customfields key.
     */
    public static function execute(array $competency, array $customfields = []): array {
        global $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), [
            'competency' => $competency,
            'customfields' => $customfields,
        ]);

        $competencyparams = $params['competency'];
        $framework = api::read_framework($competencyparams['competencyframeworkid']);
        $context = $framework->get_context();
        self::validate_context($context);

        $created = api::create_competency((object) $competencyparams);
        $competencyid = (int) $created->get('id');

        self::apply_customfields($competencyid, helper::AREA_COMPETENCY, $params['customfields'], true);

        $output = $PAGE->get_renderer('core');
        $exporter = new competency_exporter($created, ['context' => $context]);
        $payload = (array) $exporter->export($output);
        $payload['customfields'] = self::read_customfields($competencyid, helper::AREA_COMPETENCY);
        return $payload;
    }

    public static function execute_returns(): external_single_structure {
        return self::with_customfields(competency_exporter::get_read_structure());
    }
}
