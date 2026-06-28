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
 * Search courses the user may link to a competency (add-course picker).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core\context\course as context_course;
use core\context\system as context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_dimensions\helper;

/**
 * Web service: paginated search of courses linkable to a competency.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_linkable_courses extends external_api {
    /** @var int Hard cap on the page size. */
    const MAX_LIMIT = 100;

    /**
     * Define the input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'competencyid' => new external_value(PARAM_INT, 'The competency id'),
            'query' => new external_value(PARAM_RAW_TRIMMED, 'Search text', VALUE_DEFAULT, ''),
            'limitfrom' => new external_value(PARAM_INT, 'Offset for pagination', VALUE_DEFAULT, 0),
            'limitnum' => new external_value(PARAM_INT, 'Page size', VALUE_DEFAULT, 25),
        ]);
    }

    /**
     * Search manageable courses, excluding already-linked ones and the site course.
     *
     * @param int $competencyid The competency id.
     * @param string $query Search text.
     * @param int $limitfrom Offset.
     * @param int $limitnum Page size.
     * @return array Keys: items (list of {id, fullname, shortname}), total (int).
     */
    public static function execute(int $competencyid, string $query = '', int $limitfrom = 0, int $limitnum = 25): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'competencyid' => $competencyid,
            'query' => $query,
            'limitfrom' => $limitfrom,
            'limitnum' => $limitnum,
        ]);
        $competencyid = $params['competencyid'];
        $query = $params['query'];
        $limitfrom = max(0, $params['limitfrom']);
        $limitnum = $params['limitnum'] > 0 ? min($params['limitnum'], self::MAX_LIMIT) : 25;

        $systemcontext = context_system::instance();
        self::validate_context($systemcontext);
        require_capability('moodle/competency:competencyview', $systemcontext);

        $manageable = helper::manageable_course_ids();
        if ($manageable === []) {
            return ['items' => [], 'total' => 0];
        }

        // Build WHERE: not the site course, not already linked, optionally restricted to manageable ids.
        $where = 'c.id <> :siteid';
        $sqlparams = ['siteid' => SITEID, 'competencyid' => $competencyid];
        $where .= ' AND c.id NOT IN (SELECT cc.courseid FROM {competency_coursecomp} cc WHERE cc.competencyid = :competencyid)';

        if ($manageable !== null) {
            [$insql, $inparams] = $DB->get_in_or_equal($manageable, SQL_PARAMS_NAMED, 'mc');
            $where .= " AND c.id $insql";
            $sqlparams += $inparams;
        }
        if ($query !== '') {
            $like = $DB->sql_like('c.fullname', ':q1', false) . ' OR ' . $DB->sql_like('c.shortname', ':q2', false);
            $likevalue = '%' . $DB->sql_like_escape($query) . '%';
            $where .= " AND ($like)";
            $sqlparams['q1'] = $likevalue;
            $sqlparams['q2'] = $likevalue;
        }

        $total = (int) $DB->count_records_sql("SELECT COUNT(1) FROM {course} c WHERE $where", $sqlparams);

        $records = $DB->get_records_sql(
            "SELECT c.id, c.fullname, c.shortname FROM {course} c WHERE $where ORDER BY c.fullname ASC",
            $sqlparams,
            $limitfrom,
            $limitnum
        );

        $items = [];
        foreach ($records as $record) {
            $coursecontext = context_course::instance((int) $record->id);
            $items[] = [
                'id' => (int) $record->id,
                'fullname' => format_string($record->fullname, true, ['context' => $coursecontext]),
                'shortname' => format_string($record->shortname, true, ['context' => $coursecontext]),
            ];
        }

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Define the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Course id'),
                'fullname' => new external_value(PARAM_RAW, 'Course full name'),
                'shortname' => new external_value(PARAM_RAW, 'Course short name'),
            ])),
            'total' => new external_value(PARAM_INT, 'Total matches'),
        ]);
    }
}
