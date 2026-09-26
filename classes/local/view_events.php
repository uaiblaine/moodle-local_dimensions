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
 * Core competency view events for the learner pages.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\local;

use core_competency\api;
use core_competency\plan;
use core_competency\user_competency;
use local_dimensions\helper;

/**
 * Logs the core competency view events for the plugin's learner pages, the way tool_lp's pages do.
 *
 * The plan overview and the competency tracker stand in for admin/tool/lp/plan.php and
 * admin/tool/lp/user_competency_in_plan.php, so they log the same events. Both methods run before
 * $OUTPUT->header(). The overview's accordion and grid load each competency's detail lazily, so
 * that view is logged from amd/src/accordion.js through core's own web services instead, once per
 * competency per page.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class view_events {
    /**
     * Log that a plan was viewed, as admin/tool/lp/plan.php does.
     *
     * @param plan $plan A plan the current user has already read through api::read_plan().
     * @return void
     */
    public static function plan_viewed(plan $plan): void {
        api::plan_viewed($plan);
    }

    /**
     * Log that one competency of a plan was viewed, as admin/tool/lp/user_competency_in_plan.php does.
     *
     * Core has two events for this and refuses the wrong one: a completed plan logs
     * user_competency_plan_viewed, every other status user_competency_viewed_in_plan.
     *
     * Two cases are skipped rather than raised, because a log call must never take the page down.
     * A related-competency link opens the tracker for a competency outside the plan, where
     * api::get_plan_competency() throws. And plan::can_read() accepts the draft capabilities for a
     * draft plan while user_competency::can_read_user() never does, so a viewer holding only those
     * reads the plan but cannot have this view logged. The tracker page refuses that viewer before it
     * gets here (plan_access::require_owner_readable()); the guard keeps the log call from throwing
     * whoever calls it.
     *
     * On a plan that is not complete, api::get_plan_competency() creates the user competency row
     * when it is missing, exactly as core's own page does.
     *
     * @param plan $plan A plan the current user has already read through api::read_plan().
     * @param int $competencyid The competency on screen.
     * @param bool|null $inplan Whether the competency belongs to the plan, when the caller already knows;
     *     null looks it up, which costs a list_plan_competencies() call.
     * @return bool Whether an event was triggered.
     */
    public static function competency_viewed_in_plan(plan $plan, int $competencyid, ?bool $inplan = null): bool {
        if (!user_competency::can_read_user((int) $plan->get('userid'))) {
            return false;
        }
        if (!($inplan ?? helper::competency_in_plan($competencyid, $plan))) {
            return false;
        }

        $plancompetency = api::get_plan_competency($plan, $competencyid);
        if ((int) $plan->get('status') === plan::STATUS_COMPLETE) {
            api::user_competency_plan_viewed($plancompetency->usercompetencyplan);
        } else {
            api::user_competency_viewed_in_plan($plancompetency->usercompetency, (int) $plan->get('id'));
        }

        return true;
    }
}
