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
 * Fetch a single competency's fresh Structure-tab node for an in-place refresh after editing.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core\context\system as context_system;
use core_competency\competency;
use core_competency\competency_framework;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_dimensions\helper;

/**
 * Web service: return one competency's fresh tree-node shape (plus its ancestor id chain) so the
 * Structure tab can refresh that node in place after an edit, without reloading the whole tab.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_structure_node extends external_api {
    /**
     * Define the parameters for the get_structure_node external function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'competencyid' => new external_value(PARAM_INT, 'Competency id'),
        ]);
    }

    /**
     * Return the fresh tree node for a competency, plus its ancestor id chain.
     *
     * @param int $competencyid Competency id.
     * @return array Keys: found (bool), node (structure node, when found), pathids (ancestor ids).
     */
    public static function execute(int $competencyid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['competencyid' => $competencyid]);
        $competencyid = $params['competencyid'];

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/competency:competencyview', $context);

        $competency = competency::get_record(['id' => $competencyid]);
        if (!$competency) {
            return ['found' => false, 'pathids' => []];
        }

        $framework = competency_framework::get_record(['id' => $competency->get('competencyframeworkid')]);
        if (!$framework || !competency_framework::can_read_context($framework->get_context())) {
            return ['found' => false, 'pathids' => []];
        }

        $nodes = helper::structure_nodes([$competency], $framework, $framework->get_context());
        if (empty($nodes)) {
            return ['found' => false, 'pathids' => []];
        }

        // Ancestor id chain root -> parent (the non-zero segments of competency.path), so the
        // client can reveal the node at its new location if it was reparented during the edit.
        $path = trim((string) $competency->get('path'), '/');
        $pathids = array_values(array_filter(
            array_map('intval', $path === '' ? [] : explode('/', $path)),
            static fn(int $id): bool => $id > 0
        ));

        return ['found' => true, 'node' => $nodes[0], 'pathids' => $pathids];
    }

    /**
     * Define the return structure for the get_structure_node external function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'found' => new external_value(PARAM_BOOL, 'Whether the competency was found and readable'),
            'node' => browse_structure::node_structure(VALUE_OPTIONAL),
            'pathids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Ancestor competency id (root -> parent)'),
                'Ancestor id chain for revealing the node',
                VALUE_OPTIONAL
            ),
        ]);
    }
}
