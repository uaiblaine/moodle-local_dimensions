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
 * Toggle a competency framework's visibility (Competency hub Frameworks tab).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core_competency\api;
use core_competency\competency_framework;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Web service: set a framework's visible flag (partial update preserves the scale config).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_framework_visibility extends external_api {
    /**
     * Define the input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'frameworkid' => new external_value(PARAM_INT, 'The framework id'),
            'visible' => new external_value(PARAM_INT, 'Target visibility (1 visible, 0 hidden)'),
        ]);
    }

    /**
     * Set the framework's visibility.
     *
     * @param int $frameworkid The framework id.
     * @param int $visible Target visibility.
     * @return array Key: success (bool).
     */
    public static function execute(int $frameworkid, int $visible): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'frameworkid' => $frameworkid,
            'visible' => $visible,
        ]);
        $frameworkid = $params['frameworkid'];
        $visible = $params['visible'] ? 1 : 0;

        $framework = new competency_framework($frameworkid);
        self::validate_context($framework->get_context());

        // Partial record: api::update_framework loads the framework first, so the scale id,
        // scale configuration and context are preserved. Core re-checks competencymanage.
        $record = (object) ['id' => $frameworkid, 'visible' => $visible];
        $success = api::update_framework($record);

        return ['success' => (bool) $success];
    }

    /**
     * Define the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the visibility was saved'),
        ]);
    }
}
