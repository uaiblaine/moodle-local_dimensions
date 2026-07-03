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
 * Browse a framework's competencies as a lazy tree, or search within it, for the Competency hub.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core\context\system as context_system;
use core_competency\competency_framework;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_dimensions\helper;

/**
 * Web service: framework-scoped competency browse (lazy children) and search, paginated.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class browse_competencies extends external_api {
    /** @var int Minimum query length before search mode runs. */
    const MIN_QUERY_LENGTH = 2;

    /** @var int Hard cap on the page size. */
    const MAX_LIMIT = 100;

    /**
     * Define the parameters for the browse_competencies external function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'frameworkid' => new external_value(PARAM_INT, 'Competency framework id'),
            'parentid' => new external_value(PARAM_INT, 'Parent competency id (0 = roots)', VALUE_DEFAULT, 0),
            'query' => new external_value(PARAM_RAW_TRIMMED, 'Search text; non-empty switches to search mode', VALUE_DEFAULT, ''),
            'limitfrom' => new external_value(PARAM_INT, 'Offset for pagination', VALUE_DEFAULT, 0),
            'limitnum' => new external_value(PARAM_INT, 'Page size', VALUE_DEFAULT, 25),
        ]);
    }

    /**
     * Browse children of a parent, or search within the framework.
     *
     * @param int $frameworkid Framework id.
     * @param int $parentid Parent competency id (0 = roots); ignored in search mode.
     * @param string $query Search text; non-empty switches to search mode.
     * @param int $limitfrom Offset.
     * @param int $limitnum Page size.
     * @return array Keys: items (list of {id, shortname, idnumber, haschildren, path}), total (int).
     */
    public static function execute(
        int $frameworkid,
        int $parentid = 0,
        string $query = '',
        int $limitfrom = 0,
        int $limitnum = 25
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'frameworkid' => $frameworkid,
            'parentid' => $parentid,
            'query' => $query,
            'limitfrom' => $limitfrom,
            'limitnum' => $limitnum,
        ]);
        $frameworkid = $params['frameworkid'];
        $parentid = max(0, $params['parentid']);
        $query = $params['query'];
        $limitfrom = max(0, $params['limitfrom']);
        $limitnum = $params['limitnum'] > 0 ? min($params['limitnum'], self::MAX_LIMIT) : 50;

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/competency:competencyview', $context);

        $framework = competency_framework::get_record(['id' => $frameworkid]);
        if (!$framework || !competency_framework::can_read_context($framework->get_context())) {
            return ['items' => [], 'total' => 0];
        }

        $searchmode = \core_text::strlen($query) >= self::MIN_QUERY_LENGTH;
        $selectparams = ['fw' => $frameworkid];
        if ($searchmode) {
            $like = helper::sql_like_ai('shortname', ':q1') . ' OR ' . helper::sql_like_ai('idnumber', ':q2');
            $likevalue = '%' . $DB->sql_like_escape($query) . '%';
            $selectparams += ['q1' => $likevalue, 'q2' => $likevalue];
            $where = "competencyframeworkid = :fw AND ($like)";
        } else {
            $selectparams['parent'] = $parentid;
            $where = "competencyframeworkid = :fw AND parentid = :parent";
        }

        $total = $DB->count_records_select('competency', $where, $selectparams);
        $records = $DB->get_records_select('competency', $where, $selectparams, 'sortorder ASC', '*', $limitfrom, $limitnum);

        $items = [];
        if (!empty($records)) {
            $haschildren = self::has_children_map(array_map(static fn($r) => (int) $r->id, $records));
            // Ancestor breadcrumbs for the whole page in one batch (shared helper).
            $pathsbyid = [];
            foreach ($records as $record) {
                $pathsbyid[(int) $record->id] = $record->path;
            }
            $breadcrumbs = helper::competency_breadcrumbs($pathsbyid, $context);
            foreach ($records as $record) {
                $items[] = [
                    'id' => (int) $record->id,
                    'shortname' => format_string($record->shortname, true, ['context' => $context]),
                    'idnumber' => (string) $record->idnumber,
                    'haschildren' => !empty($haschildren[(int) $record->id]),
                    'path' => $breadcrumbs[(int) $record->id]['path'] ?? '',
                ];
            }
        }

        return ['items' => $items, 'total' => (int) $total];
    }

    /**
     * Map each competency id in the page to whether it has children (one batch query).
     *
     * @param array $ids Competency ids on the page.
     * @return array Set keyed by competency id that has at least one child.
     */
    private static function has_children_map(array $ids): array {
        global $DB;
        if (empty($ids)) {
            return [];
        }
        [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'p');
        $parents = $DB->get_fieldset_select('competency', 'DISTINCT parentid', "parentid $insql", $inparams);
        $map = [];
        foreach ($parents as $pid) {
            $map[(int) $pid] = true;
        }
        return $map;
    }

    /**
     * Define the return structure for the browse_competencies external function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Competency id'),
                'shortname' => new external_value(PARAM_TEXT, 'Competency short name'),
                'idnumber' => new external_value(PARAM_RAW, 'Competency ID number'),
                'haschildren' => new external_value(PARAM_BOOL, 'Whether the competency has child competencies'),
                'path' => new external_value(PARAM_TEXT, 'Human-readable ancestor path (empty for roots/browse roots)'),
            ])),
            'total' => new external_value(PARAM_INT, 'Total matches for this query/parent across all pages'),
        ]);
    }
}
