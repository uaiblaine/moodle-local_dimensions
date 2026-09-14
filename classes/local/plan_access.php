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
use core_competency\plan;

/**
 * Reads a learning plan for view-plan.php and view-competency.php, reporting only a missing plan as invalid.
 *
 * Both pages used to turn every exception from api::read_plan() into 'invalidplan'. Two refusals
 * came out as "Invalid learning plan" that way:
 * - the permission error a learner meets on their own draft, waiting-for-review or in-review plan,
 *   since no default archetype holds moodle/competency:planviewowndraft;
 * - the error an administrator meets with competencies turned off.
 * Only a plan that cannot be found is invalid now. Every other failure surfaces as core's own error,
 * as it does on admin/tool/lp/plan.php, which reads the plan with no catch at all.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plan_access {
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
}
