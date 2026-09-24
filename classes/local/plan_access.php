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
 * Reading a learning plan for the learner pages.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\local;

use core_competency\api;
use core_competency\competency;
use core_competency\competency_framework;
use core_competency\plan;
use core_competency\related_competency;
use local_dimensions\helper;

/**
 * Reads a learning plan for view-plan.php and view-competency.php, reporting only a missing plan as invalid.
 *
 * Every other failure surfaces as core's own error, as on admin/tool/lp/plan.php, which reads the
 * plan with no catch at all. Reporting them as 'invalidplan' would tell a learner refused their own
 * draft, waiting-for-review or in-review plan (no default archetype holds
 * moodle/competency:planviewowndraft), or an administrator with competencies turned off, that the
 * plan does not exist.
 *
 * It also decides which competencies a plan reaches, for view-competency.php and the accordion's rule
 * data and course cards: see competency_scope().
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plan_access {
    /** @var string The competency is one of the plan's own. */
    public const SCOPE_PLAN = 'plan';

    /** @var string A competency related to one of the plan's own, linked from the accordion's related section. */
    public const SCOPE_RELATED = 'related';

    /** @var string A child counted by the rule of one of the plan's own, linked from the accordion's Rules tab. */
    public const SCOPE_RULECHILD = 'rulechild';

    /**
     * Read a plan the current user may view.
     *
     * A missing plan comes out of api::read_plan() as a dml_missing_record_exception: from the plan
     * row for an id with no record, and from the context lookup of user 0 for an id of zero or less,
     * which core_competency\persistent never loads. Both mean there is no plan to show.
     *
     * @param int $planid The plan id from the request.
     * @return plan The plan.
     * @throws \moodle_exception 'invalidplan' when no plan has that id.
     * @throws \moodle_exception 'competenciesarenotenabled' from core_competency when competencies are turned off.
     * @throws \required_capability_exception When the current user may not read the plan.
     */
    public static function read_plan(int $planid): plan {
        try {
            return api::read_plan($planid);
        } catch (\dml_missing_record_exception $e) {
            throw new \moodle_exception('invalidplan', 'local_dimensions');
        }
    }

    /**
     * How the learner pages reach a competency from a plan, or null when they do not.
     *
     * Reading the plan says nothing about an arbitrary competency id. The plan's own competencies are
     * in scope, as on admin/tool/lp/user_competency_in_plan.php, whose api::get_plan_competency()
     * refuses any other. The accordion links outside them in exactly two places, and each is honoured
     * only where it is rendered:
     * - a related competency, linked only when showrelated and showrelatedlink both resolve on for the
     *   plan's template (view-plan.php resolves them the same way);
     * - a child counted by the rule of one of the plan's competencies, linked from the Rules tab. Core
     *   accepts only direct children in a rule (competency_rule_points::validate_config()).
     * Both also need what core asks before listing related competencies or reading a rule's children:
     * competencyview or competencymanage in the competency's own context.
     *
     * An id with no competency is out of scope like any other, so the answer never tells a missing
     * competency from one the user may not reach.
     *
     * @param plan $plan A plan the current user may read.
     * @param int $competencyid The competency id from the request.
     * @return string|null One of the SCOPE_* constants, or null when the competency is out of scope.
     */
    public static function competency_scope(plan $plan, int $competencyid): ?string {
        // A completed plan lists the competencies archived when it was completed.
        $plancompetencies = [];
        foreach ($plan->get_competencies() as $plancompetency) {
            $plancompetencies[(int) $plancompetency->get('id')] = $plancompetency;
        }
        if (isset($plancompetencies[$competencyid])) {
            return self::SCOPE_PLAN;
        }

        $competency = competency::get_record(['id' => $competencyid]);
        if (!$competency || !competency_framework::can_read_context($competency->get_context())) {
            return null;
        }

        $parent = $plancompetencies[(int) $competency->get('parentid')] ?? null;
        if (
            $parent
            && (int) $parent->get('ruleoutcome') !== competency::OUTCOME_NONE
            && !empty($parent->get('ruletype'))
        ) {
            return self::SCOPE_RULECHILD;
        }

        $templateid = (int) $plan->get('templateid');
        if (
            helper::resolve_showrelated_for_template($templateid)
            && helper::resolve_showrelatedlink_for_template($templateid)
            && array_intersect_key(related_competency::get_related_competencies($competencyid), $plancompetencies)
        ) {
            return self::SCOPE_RELATED;
        }

        return null;
    }

    /**
     * Require a competency to be in the plan's scope for the current user.
     *
     * @param plan $plan A plan the current user may read.
     * @param int $competencyid The competency id from the request.
     * @return string One of the SCOPE_* constants.
     * @throws \moodle_exception 'competency_id_missing' when the competency is out of scope or does not exist.
     */
    public static function require_competency_in_scope(plan $plan, int $competencyid): string {
        $scope = self::competency_scope($plan, $competencyid);
        if ($scope === null) {
            throw new \moodle_exception('competency_id_missing', 'local_dimensions');
        }
        return $scope;
    }
}
