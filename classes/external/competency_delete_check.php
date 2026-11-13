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
 * Tell what deleting a competency would destroy and whether core would refuse it.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core_competency\api;
use core_competency\competency;
use core_competency\competency_framework;
use core_competency\course_competency;
use core_competency\plan_competency;
use core_competency\template_competency;
use core_competency\user_competency;
use core_competency\user_competency_plan;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Web service: a read-only pre-flight for deleting a competency, behind the hub v2 delete dialogues.
 *
 * Core's api::delete_competency() answers false, with no reason, when the competency or any of its
 * descendants is in use. This service runs the same decision before anything is attempted and
 * counts the reasons, so the page can name them. It returns counts and flags only: no names and
 * no user ids. The counts span every context (plans, templates, courses and users are not
 * confined to the framework's context), so they are exact only for a caller holding the capability
 * in the system context; a manager of a course category's framework gets presence only (0 or 1).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class competency_delete_check extends external_api {
    /**
     * Define the parameters for the competency_delete_check external function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'competencyid' => new external_value(PARAM_INT, 'Competency id'),
        ]);
    }

    /**
     * Check what deleting the competency (and every descendant) would involve.
     *
     * `candelete` is the decision: exactly competency::can_all_be_deleted() over the competency and
     * its descendants, as api::delete_competency() asks it. The counts are informational and
     * measured over the same set of ids. `blockedcompetencies` counts the competencies of that set
     * holding at least one blocker among the five tables; a badge criterion is not counted there
     * (the badge criteria have no cheap per-competency count), only flagged by `badges`.
     *
     * The capability is required in the framework's context, but the counts read plans, templates,
     * courses and users site-wide. `exact` is true only when the caller holds
     * `moodle/competency:competencymanage` in the system context; otherwise `templates`, `plans`,
     * `courses`, `users` and `blockedcompetencies` are reduced to presence (0 or 1), so a category
     * manager learns which reasons apply and not how much exists elsewhere.
     *
     * @param int $competencyid Competency id.
     * @return array Keys: candelete, descendants, parenthasrule, exact, templates, plans, courses, users,
     *               badges, blockedcompetencies.
     * @throws \required_capability_exception When the caller cannot manage the framework's competencies.
     */
    public static function execute(int $competencyid): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/badgeslib.php');

        $params = self::validate_parameters(self::execute_parameters(), ['competencyid' => $competencyid]);
        $competencyid = $params['competencyid'];
        api::require_enabled();

        $competency = competency::get_record(['id' => $competencyid], MUST_EXIST);
        $framework = competency_framework::get_record(
            ['id' => $competency->get('competencyframeworkid')],
            MUST_EXIST
        );
        $context = $framework->get_context();
        self::validate_context($context);
        require_capability('moodle/competency:competencymanage', $context);

        $descendants = array_map('intval', competency::get_descendants_ids($competency));
        $ids = array_merge([(int) $competency->get('id')], $descendants);

        $parent = $competency->get_parent();
        $parenthasrule = $parent !== null && (string) $parent->get('ruletype') !== '';

        // A named placeholder appears once per statement: each IN fragment gets a prefix of its own.
        [$tplsql, $tplparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'tpl');
        $templates = $DB->count_records_sql(
            'SELECT COUNT(DISTINCT templateid) FROM {' . template_competency::TABLE . '} WHERE competencyid ' . $tplsql,
            $tplparams
        );
        [$plansql, $planparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'pln');
        $plans = $DB->count_records_sql(
            'SELECT COUNT(DISTINCT planid) FROM {' . plan_competency::TABLE . '} WHERE competencyid ' . $plansql,
            $planparams
        );
        [$crssql, $crsparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'crs');
        $courses = $DB->count_records_sql(
            'SELECT COUNT(DISTINCT courseid) FROM {' . course_competency::TABLE . '} WHERE competencyid ' . $crssql,
            $crsparams
        );

        // Users with a record or rating, live or archived in a completed plan: one user counted once.
        [$ucsql, $ucparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'uc');
        [$ucpsql, $ucpparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'ucp');
        $users = $DB->count_records_sql(
            'SELECT COUNT(DISTINCT u.userid) FROM (
                 SELECT userid FROM {' . user_competency::TABLE . '} WHERE competencyid ' . $ucsql . '
                 UNION
                 SELECT userid FROM {' . user_competency_plan::TABLE . '} WHERE competencyid ' . $ucpsql . '
             ) u',
            $ucparams + $ucpparams
        );

        // Competencies holding at least one of the five table blockers.
        $blocked = [];
        $tables = [
            template_competency::TABLE,
            plan_competency::TABLE,
            course_competency::TABLE,
            user_competency::TABLE,
            user_competency_plan::TABLE,
        ];
        foreach ($tables as $index => $table) {
            [$sql, $sqlparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'blk' . $index);
            foreach ($DB->get_fieldset_select($table, 'DISTINCT competencyid', 'competencyid ' . $sql, $sqlparams) as $id) {
                $blocked[(int) $id] = true;
            }
        }

        // The counts are site-wide: only a site-level manager may read them exactly.
        $exact = has_capability('moodle/competency:competencymanage', \core\context\system::instance());
        $shown = static fn(int $count): int => $exact ? $count : min($count, 1);

        return [
            'candelete' => competency::can_all_be_deleted($ids),
            'descendants' => count($descendants),
            'parenthasrule' => $parenthasrule,
            'exact' => $exact,
            'templates' => $shown((int) $templates),
            'plans' => $shown((int) $plans),
            'courses' => $shown((int) $courses),
            'users' => $shown((int) $users),
            'badges' => badge_award_criteria_competency_has_records_for_competencies($ids),
            'blockedcompetencies' => $shown(count($blocked)),
        ];
    }

    /**
     * Define the return structure for the competency_delete_check external function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'candelete' => new external_value(PARAM_BOOL, 'Whether core would delete the competency and its descendants'),
            'descendants' => new external_value(PARAM_INT, 'How many descendant competencies would be deleted too'),
            'parenthasrule' => new external_value(PARAM_BOOL, 'Whether the parent has a rule, which core resets on deletion'),
            'exact' => new external_value(
                PARAM_BOOL,
                'Whether the counts are exact: true only for a manager in the system context, because the counts span '
                . 'contexts the framework\'s manager may not read; otherwise they are presence only (0 or 1)'
            ),
            'templates' => new external_value(PARAM_INT, 'Learning plan templates using them (0 or 1 unless exact)'),
            'plans' => new external_value(PARAM_INT, 'Learning plans using them (0 or 1 unless exact)'),
            'courses' => new external_value(PARAM_INT, 'Courses linked to them (0 or 1 unless exact)'),
            'users' => new external_value(
                PARAM_INT,
                'Users with a record or rating for them, live or archived (0 or 1 unless exact)'
            ),
            'badges' => new external_value(PARAM_BOOL, 'Whether a badge criterion uses them'),
            'blockedcompetencies' => new external_value(
                PARAM_INT,
                'How many of them hold a blocker, badges left out (0 or 1 unless exact)'
            ),
        ]);
    }
}
