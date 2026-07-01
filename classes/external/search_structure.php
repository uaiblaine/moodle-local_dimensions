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
 * Search competencies within a single framework (with ancestor path) for the Structure tab.
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
 * Web service: framework-scoped competency search returning each hit's ancestor path.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_structure extends external_api {
    /** @var int Minimum query length before a search runs. */
    const MIN_QUERY_LENGTH = 2;

    /** @var int Hard cap on the page size. */
    const MAX_LIMIT = 100;

    /**
     * Define the parameters for the search_structure external function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'frameworkid' => new external_value(PARAM_INT, 'Competency framework id to search within'),
            'query' => new external_value(PARAM_RAW_TRIMMED, 'Search text (matches shortname or idnumber)'),
            'limitfrom' => new external_value(PARAM_INT, 'Offset for pagination', VALUE_DEFAULT, 0),
            'limitnum' => new external_value(PARAM_INT, 'Page size', VALUE_DEFAULT, 25),
        ]);
    }

    /**
     * Search competencies in one readable framework, returning each hit's ancestor chain.
     *
     * @param int $frameworkid Framework id to search within.
     * @param string $query Search text.
     * @param int $limitfrom Offset.
     * @param int $limitnum Page size.
     * @return array Keys: items (list of competency records with ancestor path), total (int).
     */
    public static function execute(int $frameworkid, string $query, int $limitfrom = 0, int $limitnum = 25): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'frameworkid' => $frameworkid,
            'query' => $query,
            'limitfrom' => $limitfrom,
            'limitnum' => $limitnum,
        ]);
        $frameworkid = $params['frameworkid'];
        $query = $params['query'];
        $limitfrom = max(0, $params['limitfrom']);
        $limitnum = $params['limitnum'] > 0 ? min($params['limitnum'], self::MAX_LIMIT) : 25;

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/competency:competencyview', $context);

        if (\core_text::strlen($query) < self::MIN_QUERY_LENGTH) {
            return ['items' => [], 'total' => 0];
        }

        $framework = competency_framework::get_record(['id' => $frameworkid]);
        if (!$framework || !competency_framework::can_read_context($framework->get_context())) {
            return ['items' => [], 'total' => 0];
        }

        $fwcontext = $framework->get_context();
        $like = $DB->sql_like('shortname', ':q1', false) . ' OR ' . $DB->sql_like('idnumber', ':q2', false);
        $likevalue = '%' . $DB->sql_like_escape($query) . '%';
        $selectparams = ['fw' => $frameworkid, 'q1' => $likevalue, 'q2' => $likevalue];
        $where = "competencyframeworkid = :fw AND ($like)";

        $total = $DB->count_records_select('competency', $where, $selectparams);
        $records = $DB->get_records_select(
            'competency',
            $where,
            $selectparams,
            'shortname ASC',
            '*',
            $limitfrom,
            $limitnum
        );

        // Build every hit's ancestor breadcrumb in one batch (shared with list_related_competencies).
        $pathsbyid = [];
        foreach ($records as $record) {
            $pathsbyid[(int) $record->id] = $record->path;
        }
        $breadcrumbs = helper::competency_breadcrumbs($pathsbyid, $fwcontext);

        $items = [];
        foreach ($records as $record) {
            $crumbs = $breadcrumbs[(int) $record->id] ?? ['path' => '', 'pathids' => []];
            $items[] = [
                'id' => (int) $record->id,
                'shortname' => format_string($record->shortname, true, ['context' => $fwcontext]),
                'idnumber' => (string) $record->idnumber,
                'path' => $crumbs['path'],
                'pathids' => $crumbs['pathids'],
            ];
        }

        return ['items' => $items, 'total' => (int) $total];
    }

    /**
     * Define the return structure for the search_structure external function.
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
            'total' => new external_value(PARAM_INT, 'Total matches across all pages'),
        ]);
    }
}
