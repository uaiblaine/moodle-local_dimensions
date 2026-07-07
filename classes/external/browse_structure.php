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
 * Browse a framework's competencies as a paginated lazy tree for the Competency hub Structure tab.
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
 * Web service: framework-scoped children-of-parent browse for the Structure tab, paginated.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class browse_structure extends external_api {
    /** @var int Hard cap on the page size. */
    const MAX_LIMIT = 100;

    /**
     * Define the parameters for the browse_structure external function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'frameworkid' => new external_value(PARAM_INT, 'Competency framework id'),
            'parentid' => new external_value(PARAM_INT, 'Parent competency id (0 = roots)', VALUE_DEFAULT, 0),
            'limitfrom' => new external_value(PARAM_INT, 'Offset for pagination', VALUE_DEFAULT, 0),
            'limitnum' => new external_value(
                PARAM_INT,
                'Page size',
                VALUE_DEFAULT,
                helper::STRUCTURE_PAGE_SIZE
            ),
        ]);
    }

    /**
     * Return one page of a parent's direct children within a framework.
     *
     * @param int $frameworkid Framework id.
     * @param int $parentid Parent competency id (0 = roots).
     * @param int $limitfrom Offset.
     * @param int $limitnum Page size.
     * @return array Keys: items (list of structure nodes), total (int).
     */
    public static function execute(
        int $frameworkid,
        int $parentid = 0,
        int $limitfrom = 0,
        int $limitnum = helper::STRUCTURE_PAGE_SIZE
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'frameworkid' => $frameworkid,
            'parentid' => $parentid,
            'limitfrom' => $limitfrom,
            'limitnum' => $limitnum,
        ]);
        $frameworkid = $params['frameworkid'];
        $parentid = max(0, $params['parentid']);
        $limitfrom = max(0, $params['limitfrom']);
        $limitnum = $params['limitnum'] > 0 ? min($params['limitnum'], self::MAX_LIMIT) : helper::STRUCTURE_PAGE_SIZE;

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/competency:competencyview', $context);

        $framework = competency_framework::get_record(['id' => $frameworkid]);
        if (!$framework || !competency_framework::can_read_context($framework->get_context())) {
            return ['items' => [], 'total' => 0];
        }

        $filters = ['competencyframeworkid' => $frameworkid, 'parentid' => $parentid];
        $total = competency::count_records($filters);
        $records = competency::get_records($filters, 'sortorder', 'ASC', $limitfrom, $limitnum);
        $items = helper::structure_nodes($records, $framework, $framework->get_context());

        return ['items' => $items, 'total' => (int) $total];
    }

    /**
     * Define the return structure for the browse_structure external function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Competency id'),
                'parentid' => new external_value(PARAM_INT, 'Parent competency id (0 = root)'),
                'shortname' => new external_value(PARAM_TEXT, 'Competency short name'),
                'idnumber' => new external_value(PARAM_RAW, 'Competency ID number'),
                'taxonomy' => new external_value(PARAM_TEXT, 'Localised taxonomy label for the node level'),
                'scale' => new external_value(PARAM_TEXT, 'Effective scale name (framework default when inherited)'),
                'description' => new external_value(PARAM_TEXT, 'Plain-text competency description for the detail pane'),
                'coursecount' => new external_value(PARAM_INT, 'Number of linked courses'),
                'activitycount' => new external_value(PARAM_INT, 'Number of linked course-module activities'),
                'templatecount' => new external_value(PARAM_INT, 'Number of learning plan templates bundling the competency'),
                'depth' => new external_value(PARAM_INT, 'Tree depth (0 = root)'),
                'indent' => new external_value(PARAM_INT, 'Indent in pixels (depth * 22)'),
                'haschildren' => new external_value(PARAM_BOOL, 'Whether the competency has child competencies'),
                'canmanage' => new external_value(PARAM_BOOL, 'Whether the caller may manage (reorder/move) the competency'),
                'ruletype' => new external_value(PARAM_RAW, 'Rule class name, or null when no rule'),
                'ruleoutcome' => new external_value(PARAM_INT, 'Rule outcome code (0 = none)'),
                'ruleconfig' => new external_value(PARAM_RAW, 'Rule configuration JSON, or null'),
                'rulelabel' => new external_value(PARAM_TEXT, 'Localized competency rule label'),
            ])),
            'total' => new external_value(PARAM_INT, 'Total direct children for this parent across all pages'),
        ]);
    }
}
