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
 * List a competency's related competencies (same framework) with ancestor path.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core_competency\api;
use core_competency\competency;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_dimensions\helper;

/**
 * Web service: list the related competencies of a competency.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class list_related_competencies extends external_api {
    /**
     * Define the parameters for the list_related_competencies external function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'competencyid' => new external_value(PARAM_INT, 'Competency id to list related competencies for'),
        ]);
    }

    /**
     * Return a competency's related competencies with each hit's ancestor path.
     *
     * @param int $competencyid Competency id.
     * @return array Keys: items (list of related competency records with ancestor path).
     */
    public static function execute(int $competencyid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['competencyid' => $competencyid]);

        $competency = new competency($params['competencyid']);
        $context = $competency->get_context();
        self::validate_context($context);

        // The api enforces competencyview/competencymanage on this context.
        $related = api::list_related_competencies($params['competencyid']);
        if (empty($related)) {
            return ['items' => []];
        }

        $pathsbyid = [];
        foreach ($related as $rel) {
            $pathsbyid[(int) $rel->get('id')] = $rel->get('path');
        }
        $breadcrumbs = helper::competency_breadcrumbs($pathsbyid, $context);

        $items = [];
        foreach ($related as $rel) {
            $id = (int) $rel->get('id');
            $crumbs = $breadcrumbs[$id] ?? ['path' => '', 'pathids' => []];
            $items[] = [
                'id' => $id,
                'shortname' => format_string($rel->get('shortname'), true, ['context' => $context]),
                'idnumber' => (string) $rel->get('idnumber'),
                'path' => $crumbs['path'],
                'pathids' => $crumbs['pathids'],
            ];
        }

        return ['items' => $items];
    }

    /**
     * Define the return structure for the list_related_competencies external function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Competency id'),
                'shortname' => new external_value(PARAM_TEXT, 'Competency short name'),
                'idnumber' => new external_value(PARAM_RAW, 'Competency ID number'),
                'path' => new external_value(PARAM_TEXT, 'Ancestor breadcrumb (shortnames, root to parent)'),
                'pathids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'Ancestor competency id'),
                    'Ancestor id chain root to parent (empty for a root competency)'
                ),
            ])),
        ]);
    }
}
