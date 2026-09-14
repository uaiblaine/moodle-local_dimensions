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

namespace local_dimensions\local;

use core_competency\plan;

/**
 * Tests which failures of reading a plan the learner pages report as an invalid plan.
 *
 * view-plan.php and view-competency.php used to turn every exception from api::read_plan() into
 * 'invalidplan'. So a learner refused their own draft plan was told the plan did not exist, and so
 * was an administrator whose site had competencies turned off. Only a plan that cannot be found is
 * invalid now; everything else surfaces as core's own error, as admin/tool/lp/plan.php shows it.
 *
 * A class-level docblock rather than a CoversClass attribute: moodle-cs on the 4.05 leg cannot see
 * attributes, and this plugin still supports 4.5.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\local\plan_access
 */
final class plan_access_test extends \advanced_testcase {
    /**
     * The owner reads their own active plan.
     *
     * @return void
     */
    public function test_owner_reads_an_active_plan(): void {
        $this->resetAfterTest();
        [$owner, $planid] = $this->create_plan(plan::STATUS_ACTIVE);
        $this->setUser($owner);

        $this->assertSame($planid, (int) plan_access::read_plan($planid)->get('id'));
    }

    /**
     * A plan id with no record is an invalid plan.
     *
     * @return void
     */
    public function test_missing_plan_is_invalid(): void {
        $this->resetAfterTest();
        [, $planid] = $this->create_plan(plan::STATUS_ACTIVE);
        $this->setAdminUser();

        $this->assert_invalid_plan($planid + 1000);
    }

    /**
     * Ids that can never name a plan are invalid too.
     *
     * core_competency\persistent loads nothing for an id of zero or less, and api::read_plan() then
     * fails looking up the context of user 0 - a dml_missing_record_exception about a user, not a
     * plan. This pins that it still reads as an invalid plan, so a change in core cannot quietly turn
     * it into a raw database error.
     *
     * @return void
     */
    public function test_ids_below_one_are_invalid(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assert_invalid_plan(0);
        $this->assert_invalid_plan(-1);
    }

    /**
     * The owner of a draft plan is refused by core, and is told so rather than told it does not exist.
     *
     * No default archetype holds moodle/competency:planviewowndraft, so this is what every learner on
     * an unmodified site meets with their own draft, waiting-for-review or in-review plan.
     *
     * @return void
     */
    public function test_owner_refused_a_draft_plan_gets_the_permission_error(): void {
        $this->resetAfterTest();
        [$owner, $planid] = $this->create_plan(plan::STATUS_DRAFT);
        $this->setUser($owner);

        $this->expectException(\required_capability_exception::class);
        plan_access::read_plan($planid);
    }

    /**
     * With competencies turned off the page says so, not that the plan is invalid.
     *
     * @return void
     */
    public function test_disabled_competencies_surface_as_core_error(): void {
        $this->resetAfterTest();
        [$owner, $planid] = $this->create_plan(plan::STATUS_ACTIVE);
        set_config('enabled', 0, 'core_competency');
        $this->setUser($owner);

        try {
            plan_access::read_plan($planid);
            $this->fail('Reading a plan with competencies disabled must throw.');
        } catch (\moodle_exception $e) {
            $this->assertSame('competenciesarenotenabled', $e->errorcode);
        }
    }

    /**
     * Assert that reading a plan id fails as the plugin's invalid plan error.
     *
     * @param int $planid The plan id to read.
     * @return void
     */
    private function assert_invalid_plan(int $planid): void {
        try {
            plan_access::read_plan($planid);
            $this->fail('Plan id ' . $planid . ' must not read.');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidplan', $e->errorcode, 'Plan id ' . $planid);
            $this->assertSame('local_dimensions', $e->module, 'Plan id ' . $planid);
        }
    }

    /**
     * A plan owned by a new user.
     *
     * @param int $status The plan status.
     * @return array The owner and the plan id.
     */
    private function create_plan(int $status): array {
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $owner = $this->getDataGenerator()->create_user();
        $plan = $ccg->create_plan(['userid' => $owner->id, 'status' => $status]);

        return [$owner, (int) $plan->get('id')];
    }
}
