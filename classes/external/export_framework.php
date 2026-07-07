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
 * Export a competency framework (+ the plugin custom fields) as CSV (Competency hub Frameworks tab).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core_competency\competency_framework;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_dimensions\local\framework_csv_serializer;

/**
 * Web service: serialize one framework and its competency tree to CSV, returned as a string.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class export_framework extends external_api {
    /**
     * Define the input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'frameworkid' => new external_value(PARAM_INT, 'The framework id to export'),
        ]);
    }

    /**
     * Build the CSV for the framework.
     *
     * @param int $frameworkid The framework id.
     * @return array{filename: string, content: string}
     */
    public static function execute(int $frameworkid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['frameworkid' => $frameworkid]);

        $framework = new competency_framework($params['frameworkid']);
        // Context comes from the framework itself, so a course-category framework validates here.
        self::validate_context($framework->get_context());
        require_capability('moodle/competency:competencyview', $framework->get_context());

        \core_php_time_limit::raise();
        raise_memory_limit(MEMORY_EXTRA);

        return framework_csv_serializer::export_framework(
            $params['frameworkid'],
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
        ]);
    }
}
