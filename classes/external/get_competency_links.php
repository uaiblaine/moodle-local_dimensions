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
 * List the courses linked to a competency, with rule outcome and per-course manage flag.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core\context\course as context_course;
use core\context\system as context_system;
use core_competency\competency;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_dimensions\helper;
use moodle_url;

/**
 * Web service: paginated list of courses linked to a competency.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_competency_links extends external_api {
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
            'query' => new external_value(PARAM_RAW_TRIMMED, 'Filter by course name', VALUE_DEFAULT, ''),
            'limitfrom' => new external_value(PARAM_INT, 'Offset for pagination', VALUE_DEFAULT, 0),
            'limitnum' => new external_value(PARAM_INT, 'Page size', VALUE_DEFAULT, 25),
        ]);
    }

    /**
     * List the courses linked to the competency.
     *
     * @param int $competencyid The competency id.
     * @param string $query Filter by course fullname/shortname.
     * @param int $limitfrom Offset.
     * @param int $limitnum Page size.
     * @return array Keys: items (list of course rows), total (int), canlink (bool).
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

        $competency = new competency($competencyid);
        $canlink = (bool) $competency->get_framework()->get('visible');

        $where = 'cc.competencyid = :competencyid';
        $sqlparams = ['competencyid' => $competencyid];
        if ($query !== '') {
            $like = helper::sql_like_ai('c.fullname', ':q1') . ' OR ' . helper::sql_like_ai('c.shortname', ':q2');
            $likevalue = '%' . $DB->sql_like_escape($query) . '%';
            $where .= " AND ($like)";
            $sqlparams['q1'] = $likevalue;
            $sqlparams['q2'] = $likevalue;
        }

        // Scope the count and list to the courses the viewer may manage.
        $coursefilter = helper::manageable_course_constraint('cc.courseid', 'mgc');
        if ($coursefilter === null) {
            return ['items' => [], 'total' => 0, 'canlink' => $canlink];
        }
        $where .= $coursefilter[0];
        $sqlparams += $coursefilter[1];

        $countsql = "SELECT COUNT(1)
                       FROM {competency_coursecomp} cc
                       JOIN {course} c ON c.id = cc.courseid
                      WHERE $where";
        $total = (int) $DB->count_records_sql($countsql, $sqlparams);

        $recordsql = "SELECT cc.id AS linkid, cc.ruleoutcome, c.id AS courseid, c.fullname, c.shortname, c.visible,
                             c.enablecompletion
                        FROM {competency_coursecomp} cc
                        JOIN {course} c ON c.id = cc.courseid
                       WHERE $where
                    ORDER BY c.fullname ASC";
        $records = $DB->get_records_sql($recordsql, $sqlparams, $limitfrom, $limitnum);

        // Grouped queries across the page's courses: linked-activity counts and whether
        // course completion criteria exist (drives the completion-rule badge).
        $modulecounts = [];
        $criteriacounts = [];
        if (!empty($records)) {
            $courseids = array_map(static fn($r): int => (int) $r->courseid, $records);
            [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cm');
            $inparams['competencyid'] = $competencyid;
            $modulecounts = $DB->get_records_sql_menu(
                "SELECT cm.course, COUNT(1)
                   FROM {competency_modulecomp} mc
                   JOIN {course_modules} cm ON cm.id = mc.cmid
                  WHERE cm.course $insql AND mc.competencyid = :competencyid AND cm.deletioninprogress = 0
               GROUP BY cm.course",
                $inparams
            );
            [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cr');
            $criteriacounts = $DB->get_records_sql_menu(
                "SELECT ccrit.course, COUNT(1)
                   FROM {course_completion_criteria} ccrit
                  WHERE ccrit.course $insql
               GROUP BY ccrit.course",
                $inparams
            );
        }

        $items = [];
        foreach ($records as $record) {
            $courseid = (int) $record->courseid;
            $coursecontext = context_course::instance($courseid);
            $hascompletion = !empty($record->enablecompletion) && !empty($criteriacounts[$courseid]);
            $item = [
                'courseid' => $courseid,
                'fullname' => format_string($record->fullname, true, ['context' => $coursecontext]),
                'shortname' => format_string($record->shortname, true, ['context' => $coursecontext]),
                'visible' => (int) $record->visible,
                'ruleoutcome' => (int) $record->ruleoutcome,
                'modulecount' => (int) ($modulecounts[$courseid] ?? 0),
                'hascompletion' => (int) $hascompletion,
                'canmanage' => (int) has_capability('moodle/competency:coursecompetencymanage', $coursecontext),
                'courseurl' => (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
            ];
            if (has_capability('moodle/course:update', $coursecontext)) {
                $item['completionurl'] = (new moodle_url('/course/completion.php', ['id' => $courseid]))->out(false);
            }
            $items[] = $item;
        }

        return ['items' => $items, 'total' => $total, 'canlink' => $canlink];
    }

    /**
     * Define the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(new external_single_structure([
                'courseid' => new external_value(PARAM_INT, 'Course id'),
                'fullname' => new external_value(PARAM_RAW, 'Course full name'),
                'shortname' => new external_value(PARAM_RAW, 'Course short name'),
                'visible' => new external_value(PARAM_INT, 'Course visibility'),
                'ruleoutcome' => new external_value(PARAM_INT, 'Course competency rule outcome'),
                'modulecount' => new external_value(PARAM_INT, 'Number of linked activities in the course'),
                'hascompletion' => new external_value(PARAM_INT, 'Whether the course has completion criteria configured'),
                'canmanage' => new external_value(PARAM_INT, 'Whether the user can manage links in this course'),
                'courseurl' => new external_value(PARAM_URL, 'URL of the course page'),
                'completionurl' => new external_value(
                    PARAM_URL,
                    'URL of the course completion settings (only when the user may edit them)',
                    VALUE_OPTIONAL
                ),
            ])),
            'total' => new external_value(PARAM_INT, 'Total linked courses'),
            'canlink' => new external_value(PARAM_BOOL, 'Whether new course links are allowed (framework visible)'),
        ]);
    }
}
