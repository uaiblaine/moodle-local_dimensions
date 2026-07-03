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
 * Search users who can still be assigned to a learning plan template (assign-user picker).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core_competency\template;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_dimensions\helper;

/**
 * Web service: paginated search of users without a plan for the template.
 *
 * Core refuses a second plan from the same template for a user (whether it was created
 * individually or by cohort sync), so users who already have one are excluded here and the
 * picker never suggests someone who cannot actually be added.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_assignable_users extends external_api {
    /** @var int Hard cap on the page size. */
    const MAX_LIMIT = 100;

    /**
     * Define the input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'templateid' => new external_value(PARAM_INT, 'Learning plan template id'),
            'query' => new external_value(PARAM_RAW_TRIMMED, 'Search text', VALUE_DEFAULT, ''),
            'limitfrom' => new external_value(PARAM_INT, 'Offset for pagination', VALUE_DEFAULT, 0),
            'limitnum' => new external_value(PARAM_INT, 'Page size', VALUE_DEFAULT, 25),
        ]);
    }

    /**
     * Search active users without a plan created from the template.
     *
     * Matches the query against the user's full name, and — when the caller may view user
     * identity — the email, ID number and username too, which are then echoed back in the
     * identity string for the suggestion label.
     *
     * @param int $templateid The template id.
     * @param string $query Search text.
     * @param int $limitfrom Offset.
     * @param int $limitnum Page size.
     * @return array Keys: items (list of {id, fullname, identity}), total (int).
     */
    public static function execute(int $templateid, string $query = '', int $limitfrom = 0, int $limitnum = 25): array {
        global $CFG, $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'templateid' => $templateid,
            'query' => $query,
            'limitfrom' => $limitfrom,
            'limitnum' => $limitnum,
        ]);
        $query = $params['query'];
        $limitfrom = max(0, $params['limitfrom']);
        $limitnum = $params['limitnum'] > 0 ? min($params['limitnum'], self::MAX_LIMIT) : 25;

        $template = template::get_record(['id' => $params['templateid']]);
        if (!$template) {
            return ['items' => [], 'total' => 0];
        }
        $context = $template->get_context();
        self::validate_context($context);
        require_capability('moodle/competency:templatemanage', $context);
        $canviewidentity = has_capability('moodle/site:viewuseridentity', $context);

        // Active users only, minus everyone who already has a plan created from this template.
        $where = 'u.deleted = 0 AND u.suspended = 0 AND u.confirmed = 1 AND u.id <> :guestid';
        $sqlparams = ['guestid' => (int) $CFG->siteguest, 'templateid' => (int) $template->get('id')];
        $where .= ' AND u.id NOT IN (SELECT p.userid FROM {competency_plan} p WHERE p.templateid = :templateid)';

        if ($query !== '') {
            $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
            $likes = [helper::sql_like_ai($fullname, ':q1')];
            $likevalue = '%' . $DB->sql_like_escape($query) . '%';
            $sqlparams['q1'] = $likevalue;
            if ($canviewidentity) {
                $likes[] = helper::sql_like_ai('u.email', ':q2');
                $likes[] = helper::sql_like_ai('u.idnumber', ':q3');
                $likes[] = helper::sql_like_ai('u.username', ':q4');
                $sqlparams['q2'] = $likevalue;
                $sqlparams['q3'] = $likevalue;
                $sqlparams['q4'] = $likevalue;
            }
            $where .= ' AND (' . implode(' OR ', $likes) . ')';
        }

        $total = (int) $DB->count_records_sql("SELECT COUNT(1) FROM {user} u WHERE $where", $sqlparams);

        $namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $records = $DB->get_records_sql(
            "SELECT u.id, u.email, u.idnumber, $namefields
               FROM {user} u
              WHERE $where
           ORDER BY u.lastname ASC, u.firstname ASC, u.id ASC",
            $sqlparams,
            $limitfrom,
            $limitnum
        );

        $items = [];
        foreach ($records as $record) {
            $identity = '';
            if ($canviewidentity) {
                $identity = implode(', ', array_filter([$record->email, $record->idnumber]));
            }
            $items[] = [
                'id' => (int) $record->id,
                'fullname' => fullname($record),
                'identity' => $identity,
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
                'id' => new external_value(PARAM_INT, 'User id'),
                'fullname' => new external_value(PARAM_RAW, 'User full name'),
                'identity' => new external_value(PARAM_RAW, 'Identity fields shown next to the name (may be empty)'),
            ])),
            'total' => new external_value(PARAM_INT, 'Total matches'),
        ]);
    }
}
