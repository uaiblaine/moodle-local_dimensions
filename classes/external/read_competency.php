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
use core_external\external_value;
use local_dimensions\helper;

/**
 * Web service: read a competency plus local_dimensions customfields.
 *
 * Mirrors `core_competency_read_competency` and appends a `customfields` array.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class read_competency extends external_api {
    use customfields_io;

    /**
     * Describe the web service parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Database record id for the competency', VALUE_REQUIRED),
        ]);
    }

    /**
     * Read the competency + customfields.
     *
     * @param int $id Database record id for the competency.
     * @return array Exported competency record plus a customfields key.
     */
    public static function execute(int $id): array {
        global $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), ['id' => $id]);

        $competency = api::read_competency($params['id']);
        $context = $competency->get_context();
        self::validate_context($context);

        $output = $PAGE->get_renderer('core');
        $exporter = new competency_exporter($competency, ['context' => $context]);
        $payload = (array) $exporter->export($output);
        $payload['customfields'] = self::read_customfields((int) $competency->get('id'), helper::AREA_COMPETENCY);
        return $payload;
    }

    /**
     * Describe the web service return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return self::with_customfields(competency_exporter::get_read_structure());
    }
}
