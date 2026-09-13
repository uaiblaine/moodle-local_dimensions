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

use core_competency\api;
use core_competency\plan;

/**
 * Tests the core competency view events the learner pages log.
 *
 * A class-level docblock rather than a CoversClass attribute: moodle-cs on the 4.05 leg cannot see
 * attributes, and this plugin still supports 4.5.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\local\view_events
 */
final class view_events_test extends \advanced_testcase {
    /**
     * The plan overview logs core's plan viewed event, for the plan on screen.
     *
     * @return void
     */
    public function test_plan_viewed_logs_the_core_plan_event(): void {
        $this->resetAfterTest();
        $fixture = $this->create_plan_fixture(plan::STATUS_ACTIVE);

        $this->setUser($fixture->owner);
        $sink = $this->redirectEvents();
        view_events::plan_viewed(api::read_plan($fixture->planid));
        $events = $this->events_of($sink->get_events(), \core\event\competency_plan_viewed::class);
        $sink->close();

        $this->assertCount(1, $events);
        $this->assertSame($fixture->planid, (int) $events[0]->objectid);
        $this->assertSame((int) $fixture->owner->id, (int) $events[0]->userid);
    }

    /**
     * A competency of a plan that is not complete logs user_competency_viewed_in_plan.
     *
     * @return void
     */
    public function test_competency_in_an_active_plan_logs_viewed_in_plan(): void {
        $this->resetAfterTest();
        $fixture = $this->create_plan_fixture(plan::STATUS_ACTIVE);

        $this->setUser($fixture->owner);
        $sink = $this->redirectEvents();
        $logged = view_events::competency_viewed_in_plan(api::read_plan($fixture->planid), $fixture->inplanid);
        $all = $sink->get_events();
        $sink->close();

        $this->assertTrue($logged);
        $events = $this->events_of($all, \core\event\competency_user_competency_viewed_in_plan::class);
        $this->assertCount(1, $events);
        $this->assertSame((int) $fixture->owner->id, (int) $events[0]->relateduserid);
        $this->assertSame($fixture->planid, (int) $events[0]->other['planid']);
        $this->assertSame($fixture->inplanid, (int) $events[0]->other['competencyid']);
        $this->assertCount(0, $this->events_of($all, \core\event\competency_user_competency_plan_viewed::class));
    }

    /**
     * A competency of a completed plan logs user_competency_plan_viewed, the only event core accepts there.
     *
     * @return void
     */
    public function test_competency_in_a_completed_plan_logs_user_competency_plan_viewed(): void {
        $this->resetAfterTest();
        $fixture = $this->create_plan_fixture(plan::STATUS_ACTIVE);
        $this->setAdminUser();
        api::complete_plan($fixture->planid);

        $this->setUser($fixture->owner);
        $plan = api::read_plan($fixture->planid);
        $this->assertSame(plan::STATUS_COMPLETE, (int) $plan->get('status'));

        $sink = $this->redirectEvents();
        $logged = view_events::competency_viewed_in_plan($plan, $fixture->inplanid);
        $all = $sink->get_events();
        $sink->close();

        $this->assertTrue($logged);
        $events = $this->events_of($all, \core\event\competency_user_competency_plan_viewed::class);
        $this->assertCount(1, $events);
        $this->assertSame((int) $fixture->owner->id, (int) $events[0]->relateduserid);
        $this->assertCount(0, $this->events_of($all, \core\event\competency_user_competency_viewed_in_plan::class));
    }

    /**
     * A related competency outside the plan is skipped, while one inside the same plan still logs.
     *
     * The control matters: without it a helper that never logged anything would pass the skip.
     *
     * @return void
     */
    public function test_competency_outside_the_plan_is_skipped(): void {
        $this->resetAfterTest();
        $fixture = $this->create_plan_fixture(plan::STATUS_ACTIVE);

        $this->setUser($fixture->owner);
        $plan = api::read_plan($fixture->planid);
        $sink = $this->redirectEvents();

        $this->assertFalse(view_events::competency_viewed_in_plan($plan, $fixture->outsideid));
        $this->assertCount(0, $sink->get_events());

        $this->assertTrue(view_events::competency_viewed_in_plan($plan, $fixture->inplanid));
        $this->assertCount(
            1,
            $this->events_of($sink->get_events(), \core\event\competency_user_competency_viewed_in_plan::class)
        );

        // A caller that already knows the competency is outside the plan is taken at its word.
        $this->assertFalse(view_events::competency_viewed_in_plan($plan, $fixture->inplanid, false));
        $this->assertCount(
            1,
            $this->events_of($sink->get_events(), \core\event\competency_user_competency_viewed_in_plan::class)
        );
        $sink->close();
    }

    /**
     * A viewer who reads a draft plan through the draft capability alone is skipped, not raised.
     *
     * plan::can_read() accepts planviewdraft for a draft plan; api::get_plan_competency() checks
     * user_competency::can_read_user(), which never consults it and would throw.
     *
     * @return void
     */
    public function test_viewer_who_cannot_read_user_competencies_is_skipped(): void {
        $this->resetAfterTest();
        $fixture = $this->create_plan_fixture(plan::STATUS_DRAFT);

        $reviewer = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        $syscontext = \context_system::instance();
        assign_capability('moodle/competency:planviewdraft', CAP_ALLOW, $roleid, $syscontext->id);
        role_assign($roleid, $reviewer->id, $syscontext->id);

        $this->setUser($reviewer);
        // Precondition: the page itself is readable for this viewer.
        $plan = api::read_plan($fixture->planid);
        $sink = $this->redirectEvents();
        $logged = view_events::competency_viewed_in_plan($plan, $fixture->inplanid);
        $events = $sink->get_events();
        $sink->close();

        $this->assertFalse($logged);
        $this->assertCount(0, $events);

        // Control: a viewer who can read user competencies has the same draft view logged.
        $this->setAdminUser();
        $this->assertTrue(view_events::competency_viewed_in_plan(api::read_plan($fixture->planid), $fixture->inplanid));
    }

    /**
     * A manager looking at someone else's plan is logged as core logs it: the viewer and the owner.
     *
     * @return void
     */
    public function test_manager_viewing_another_users_plan_is_logged_with_both_users(): void {
        $this->resetAfterTest();
        $fixture = $this->create_plan_fixture(plan::STATUS_ACTIVE);

        $manager = $this->getDataGenerator()->create_user();
        $managerroleid = (int) $this->getDataGenerator()->create_role(['archetype' => 'manager']);
        role_assign($managerroleid, $manager->id, \context_system::instance()->id);

        $this->setUser($manager);
        $sink = $this->redirectEvents();
        view_events::competency_viewed_in_plan(api::read_plan($fixture->planid), $fixture->inplanid);
        $events = $this->events_of($sink->get_events(), \core\event\competency_user_competency_viewed_in_plan::class);
        $sink->close();

        $this->assertCount(1, $events);
        $this->assertSame((int) $manager->id, (int) $events[0]->userid);
        $this->assertSame((int) $fixture->owner->id, (int) $events[0]->relateduserid);
    }

    /**
     * The events of one class, in the order they were triggered.
     *
     * @param array $events Events captured by a sink.
     * @param string $class The fully qualified event class name.
     * @return array
     */
    private function events_of(array $events, string $class): array {
        return array_values(array_filter($events, static function ($event) use ($class) {
            return $event instanceof $class;
        }));
    }

    /**
     * A plan holding one competency, plus a second competency of the same framework outside it.
     *
     * @param int $status The plan status.
     * @return \stdClass The owner, the plan id, the in-plan competency id and the outside competency id.
     */
    private function create_plan_fixture(int $status): \stdClass {
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework();
        $inplan = $ccg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $outside = $ccg->create_competency(['competencyframeworkid' => $framework->get('id')]);

        $owner = $this->getDataGenerator()->create_user();
        $plan = $ccg->create_plan(['userid' => $owner->id, 'status' => $status]);
        $ccg->create_plan_competency(['planid' => $plan->get('id'), 'competencyid' => $inplan->get('id')]);

        return (object) [
            'owner' => $owner,
            'planid' => (int) $plan->get('id'),
            'inplanid' => (int) $inplan->get('id'),
            'outsideid' => (int) $outside->get('id'),
        ];
    }
}
