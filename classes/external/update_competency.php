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
 * Web service: update a competency plus local_dimensions customfields.
 *
 * Mirrors `core_competency_update_competency`. Returns the full updated record
 * (not just a boolean) so callers avoid a follow-up read.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_competency extends external_api {
    use customfields_io;

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'competency' => competency_exporter::get_update_structure(),
            'customfields' => self::customfields_input_structure(),
        ]);
    }

    /**
     * Update the competency, persist customfields, return the merged record.
     */
    public static function execute(array $competency, array $customfields = []): array {
        global $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), [
            'competency' => $competency,
            'customfields' => $customfields,
        ]);

        $competencyparams = $params['competency'];
        $existing = api::read_competency($competencyparams['id']);
        $context = $existing->get_context();
        self::validate_context($context);

        api::update_competency((object) $competencyparams);

        $competencyid = (int) $competencyparams['id'];
        self::apply_customfields($competencyid, helper::AREA_COMPETENCY, $params['customfields'], false);

        $updated = api::read_competency($competencyid);
        $output = $PAGE->get_renderer('core');
        $exporter = new competency_exporter($updated, ['context' => $context]);
        $payload = (array) $exporter->export($output);
        $payload['customfields'] = self::read_customfields($competencyid, helper::AREA_COMPETENCY);
        return $payload;
    }

    public static function execute_returns(): external_single_structure {
        return self::with_customfields(competency_exporter::get_read_structure());
    }
}
