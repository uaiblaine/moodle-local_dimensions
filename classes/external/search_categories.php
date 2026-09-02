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
 * Search course categories for the Competency hub's context picker.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core\context\system as context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_dimensions\helper;

/**
 * Web service: paginated, name-matched search of the course categories a viewer may pick.
 *
 * The picker used to be rendered with every category the viewer could see, which a site with
 * thousands of categories cannot afford - on the server (a context and up to four capability
 * checks per category) nor in the browser (thousands of options for the autocomplete). Each
 * hit carries the plain nested name and both counts, so the bar's headline counter never
 * needs a second round-trip.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_categories extends external_api {
    /** @var int Hard cap on the page size. */
    const MAX_LIMIT = 50;

    /**
     * Define the input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'query' => new external_value(PARAM_RAW_TRIMMED, 'Search text matched against the category name', VALUE_DEFAULT, ''),
            'includehidden' => new external_value(PARAM_BOOL, 'Include hidden categories the viewer may see', VALUE_DEFAULT, false),
            'limitnum' => new external_value(PARAM_INT, 'Page size', VALUE_DEFAULT, 25),
        ]);
    }

    /**
     * Search the categories the viewer may see and read competencies in.
     *
     * The system context is the login gate only: visibility is decided per category by core
     * (hidden ones need viewhiddencategories there), and competency readability per category
     * unless the viewer already reads at the site, in which case no category can widen it.
     *
     * @param string $query Search text; empty returns the first page in tree order.
     * @param bool $includehidden Whether hidden categories the viewer may see are included.
     * @param int $limitnum Page size.
     * @return array Keys: items (list of {id, name, frameworkcount, templatecount, hidden}), total (int).
     */
    public static function execute(string $query = '', bool $includehidden = false, int $limitnum = 25): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'query' => $query,
            'includehidden' => $includehidden,
            'limitnum' => $limitnum,
        ]);
        self::validate_context(context_system::instance());

        $limit = $params['limitnum'] > 0 ? min($params['limitnum'], self::MAX_LIMIT) : 25;
        $items = helper::central_category_search($params['query'], (bool) $params['includehidden'], $limit);

        return ['items' => $items, 'total' => count($items)];
    }

    /**
     * Define the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Course category id'),
                'name' => new external_value(PARAM_TEXT, 'Nested category name, plain spelling (parents joined by " / ")'),
                'frameworkcount' => new external_value(PARAM_INT, 'Visible frameworks in the category'),
                'templatecount' => new external_value(PARAM_INT, 'Visible learning plan templates in the category'),
                'hidden' => new external_value(PARAM_BOOL, 'Whether the category is hidden'),
            ])),
            'total' => new external_value(PARAM_INT, 'Number of hits returned'),
        ]);
    }
}
