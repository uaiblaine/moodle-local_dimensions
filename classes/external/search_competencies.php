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
 * Search competencies across readable frameworks for the Competency hub.
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
 * Web service: paginated competency search (cross-framework, readable frameworks only).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_competencies extends external_api {
    /** @var int Minimum query length before a search runs. */
    const MIN_QUERY_LENGTH = 2;

    /** @var int Hard cap on the page size. */
    const MAX_LIMIT = 100;

    /**
     * Define the parameters for the search_competencies external function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'query' => new external_value(PARAM_RAW_TRIMMED, 'Search text (matches shortname or idnumber)'),
            'limitfrom' => new external_value(PARAM_INT, 'Offset for pagination', VALUE_DEFAULT, 0),
            'limitnum' => new external_value(PARAM_INT, 'Page size', VALUE_DEFAULT, 25),
        ]);
    }

    /**
     * Search competencies in frameworks the user can read.
     *
     * @param string $query Search text.
     * @param int $limitfrom Offset.
     * @param int $limitnum Page size.
     * @return array Keys: items (list of {id, shortname, idnumber, frameworktag}), total (int).
     */
    public static function execute(string $query, int $limitfrom = 0, int $limitnum = 25): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'query' => $query,
            'limitfrom' => $limitfrom,
            'limitnum' => $limitnum,
        ]);
        $query = $params['query'];
        $limitfrom = max(0, $params['limitfrom']);
        $limitnum = $params['limitnum'] > 0 ? min($params['limitnum'], self::MAX_LIMIT) : 25;

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/competency:competencyview', $context);

        if (\core_text::strlen($query) < self::MIN_QUERY_LENGTH) {
            return ['items' => [], 'total' => 0];
        }

        // Readable frameworks → id => display tag (frameworks are few; filter by context readability).
        $tags = [];
        foreach (competency_framework::get_records([], 'shortname', 'ASC') as $framework) {
            if (competency_framework::can_read_context($framework->get_context())) {
                $idnumber = (string) $framework->get('idnumber');
                $tags[(int) $framework->get('id')] = $idnumber !== '' ? $idnumber : $framework->get('shortname');
            }
        }
        if (empty($tags)) {
            return ['items' => [], 'total' => 0];
        }

        [$insql, $inparams] = $DB->get_in_or_equal(array_keys($tags), SQL_PARAMS_NAMED, 'fw');
        $like = helper::sql_like_ai('shortname', ':q1') . ' OR ' . helper::sql_like_ai('idnumber', ':q2');
        $likevalue = '%' . $DB->sql_like_escape($query) . '%';
        $selectparams = $inparams + ['q1' => $likevalue, 'q2' => $likevalue];
        $where = "competencyframeworkid $insql AND ($like)";

        $total = $DB->count_records_select('competency', $where, $selectparams);

        $items = [];
        $records = $DB->get_records_select('competency', $where, $selectparams, 'shortname ASC', '*', $limitfrom, $limitnum);
        foreach ($records as $record) {
            $items[] = [
                'id' => (int) $record->id,
                'shortname' => format_string($record->shortname, true, ['context' => $context]),
                'idnumber' => (string) $record->idnumber,
                'frameworktag' => format_string($tags[(int) $record->competencyframeworkid] ?? '', true, ['context' => $context]),
            ];
        }

        return ['items' => $items, 'total' => (int) $total];
    }

    /**
     * Define the return structure for the search_competencies external function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Competency id'),
                'shortname' => new external_value(PARAM_TEXT, 'Competency short name'),
                'idnumber' => new external_value(PARAM_RAW, 'Competency ID number'),
                'frameworktag' => new external_value(PARAM_TEXT, 'Origin framework tag'),
            ])),
            'total' => new external_value(PARAM_INT, 'Total matches across all pages'),
        ]);
    }
}
