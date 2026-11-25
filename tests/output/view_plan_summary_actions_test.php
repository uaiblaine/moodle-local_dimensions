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

namespace local_dimensions\output;

use core_competency\api;
use core_competency\external\plan_exporter;
use core_competency\plan;
use local_dimensions\local\plan_access;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests the plan overview's address-opened competency and its actions row.
 *
 * The competency named by view-plan.php's competencyid is handed to the page module only when it is
 * one of the competencies the page lists and the viewer may open a detail; every other value leaves
 * the page exactly as without it. The review actions are core's own rule (plan_exporter's
 * isrequestreviewallowed and iscancelreviewrequestallowed), never re-derived; the link back to core's
 * plan page is named after what the viewer may do there.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_dimensions\output\view_plan_summary_page::class)]
#[CoversClass(\local_dimensions\local\plan_access::class)]
final class view_plan_summary_actions_test extends \advanced_testcase {
    /**
     * A competency of the plan, opened by its owner, is handed to the page module.
     *
     * The control of every refusal below: the same export accepts the right id.
     *
     * @return void
     */
    public function test_a_competency_of_the_plan_is_handed_over(): void {
        $this->resetAfterTest();
        $fixture = $this->create_plan(plan::STATUS_ACTIVE);
        $this->setUser($fixture->owner);

        $data = $this->export($fixture->planid, $fixture->competencyids[1]);

        $this->assertTrue($data['candetail']);
        $this->assertSame($fixture->competencyids[1], $data['opencompetencyid']);
    }

    /**
     * Any other value is ignored silently: the page is exactly the plain overview.
     *
     * A competency of another plan of the same owner, one that does not exist, a negative id and zero
     * all give the export the page gives without the parameter, so "not in the plan" and "does not
     * exist" are the same answer.
     *
     * @return void
     */
    public function test_an_id_outside_the_plan_is_ignored_silently(): void {
        global $DB;

        $this->resetAfterTest();
        $fixture = $this->create_plan(plan::STATUS_ACTIVE);
        $other = $this->create_plan(plan::STATUS_ACTIVE, $fixture->owner);
        $missing = (int) $DB->get_field_sql('SELECT MAX(id) FROM {competency}') + 1000;
        $this->setUser($fixture->owner);

        $plain = $this->export($fixture->planid, 0);
        $this->assertSame(0, $plain['opencompetencyid']);

        $cases = [
            'a competency of another plan' => $other->competencyids[0],
            'a competency that does not exist' => $missing,
            'a negative id' => -$fixture->competencyids[0],
        ];
        foreach ($cases as $case => $competencyid) {
            $this->assertEquals($plain, $this->export($fixture->planid, $competencyid), $case);
        }
    }

    /**
     * Without detail there is nothing to open, so the id is not handed over.
     *
     * The viewer reads the draft plan through moodle/competency:planviewdraft alone, which core's
     * detail check refuses; an administrator opening the same competency of the same plan is the control.
     *
     * @return void
     */
    public function test_the_id_is_handed_over_only_with_detail(): void {
        $this->resetAfterTest();
        $fixture = $this->create_plan(plan::STATUS_DRAFT);

        $this->setUser($this->create_role_user(['moodle/competency:planviewdraft']));
        $data = $this->export($fixture->planid, $fixture->competencyids[0]);
        $this->assertFalse($data['candetail']);
        $this->assertSame(0, $data['opencompetencyid']);

        $this->setAdminUser();
        $data = $this->export($fixture->planid, $fixture->competencyids[0]);
        $this->assertTrue($data['candetail']);
        $this->assertSame($fixture->competencyids[0], $data['opencompetencyid']);
    }

    /**
     * The review flags are plan_exporter's, for every status, both kinds of plan and three viewers.
     *
     * The viewers: the owner holding moodle/competency:planrequestreviewown, the owner refused it, and
     * a manager. Each pair of flags is compared with the exporter core's plan page draws its buttons
     * from, and the grid is checked to reach both values of both flags, so a flag stuck at one value
     * cannot pass.
     *
     * @return void
     */
    public function test_the_review_flags_are_the_core_exporters(): void {
        global $DB, $PAGE;

        $this->resetAfterTest();
        // The exporter draws the reviewer's picture for a plan in review, which reads the page's context.
        $PAGE->set_context(\context_system::instance());
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $template = $ccg->create_template();
        $manager = $this->getDataGenerator()->create_user();
        $managerroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($managerroleid, $manager->id, \context_system::instance()->id);

        $owners = [
            'owner with the capability' => $this->create_role_user(['moodle/competency:planviewowndraft']),
            'owner without the capability' => $this->create_role_user(
                ['moodle/competency:planviewowndraft'],
                ['moodle/competency:planrequestreviewown']
            ),
        ];
        $statuses = [
            plan::STATUS_DRAFT,
            plan::STATUS_WAITING_FOR_REVIEW,
            plan::STATUS_IN_REVIEW,
            plan::STATUS_ACTIVE,
            plan::STATUS_COMPLETE,
        ];

        $seen = ['isrequestreviewallowed' => [], 'iscancelreviewrequestallowed' => []];
        foreach ($owners as $ownerlabel => $owner) {
            foreach ($statuses as $status) {
                foreach ([0, (int) $template->get('id')] as $templateid) {
                    $plan = $ccg->create_plan([
                        'userid' => $owner->id,
                        'status' => $status,
                        'templateid' => $templateid ?: null,
                        'reviewerid' => $manager->id,
                    ]);
                    foreach ([$ownerlabel => $owner, 'a manager' => $manager] as $viewerlabel => $viewer) {
                        $this->setUser($viewer);
                        $plan = new plan((int) $plan->get('id'));
                        $exporter = new plan_exporter($plan, ['template' => $plan->get_template()]);
                        $core = $exporter->export($PAGE->get_renderer('core'));
                        $ours = plan_access::review_actions($plan);
                        $label = "{$viewerlabel}, status {$status}, " . ($templateid ? 'template-based' : 'individual');
                        foreach (array_keys($seen) as $flag) {
                            $this->assertSame((bool) $core->$flag, $ours[$flag], "{$flag}: {$label}");
                            $seen[$flag][(int) $ours[$flag]] = true;
                        }
                    }
                }
            }
        }
        foreach ($seen as $flag => $values) {
            $this->assertCount(2, $values, "{$flag} never took both values, so the grid proves nothing about it.");
        }
    }

    /**
     * The page's actions row offers the review action core allows, beside the plan's draft status.
     *
     * @return void
     */
    public function test_the_actions_row_offers_cores_review_action(): void {
        $this->resetAfterTest();
        $owner = $this->create_role_user(['moodle/competency:planviewowndraft']);
        $draft = $this->create_plan(plan::STATUS_DRAFT, $owner);
        $waiting = $this->create_plan(plan::STATUS_WAITING_FOR_REVIEW, $owner);
        $this->setUser($owner);

        $actions = $this->export($draft->planid, 0)['actions'];
        $this->assertTrue($actions['isrequestreviewallowed']);
        $this->assertFalse($actions['iscancelreviewrequestallowed']);
        $this->assertTrue($actions['hasreviewaction']);
        $this->assertTrue($actions['hasstatus']);
        $this->assertSame(get_string('planstatusdraft', 'core_competency'), $actions['status']['label']);

        $actions = $this->export($waiting->planid, 0)['actions'];
        $this->assertFalse($actions['isrequestreviewallowed']);
        $this->assertTrue($actions['iscancelreviewrequestallowed']);
        $this->assertSame(get_string('planstatuswaitingforreview', 'core_competency'), $actions['status']['label']);
    }

    /**
     * A plan out of its draft states has no status and no action in the row, only the link.
     *
     * @return void
     */
    public function test_a_plan_out_of_draft_has_no_status_and_no_action(): void {
        $this->resetAfterTest();
        foreach ([plan::STATUS_ACTIVE, plan::STATUS_COMPLETE] as $status) {
            $fixture = $this->create_plan($status);
            $this->setUser($fixture->owner);

            $actions = $this->export($fixture->planid, 0)['actions'];
            $this->assertFalse($actions['hasstatus'], "status {$status}");
            $this->assertArrayNotHasKey('status', $actions, "status {$status}");
            $this->assertFalse($actions['isrequestreviewallowed'], "status {$status}");
            $this->assertFalse($actions['iscancelreviewrequestallowed'], "status {$status}");
            $this->assertFalse($actions['hasreviewaction'], "status {$status}");
            $this->assertNotEmpty($actions['planurl'], "status {$status}");
        }
    }

    /**
     * The link back to core's page says "Manage plan" exactly when the viewer may manage the plan.
     *
     * A viewer who only reads the plan gets the neutral label; an administrator, the managing one.
     * Both carry core's plan address with noredirect, which a theme reads.
     *
     * @return void
     */
    public function test_the_link_label_follows_can_manage(): void {
        $this->resetAfterTest();
        $fixture = $this->create_plan(plan::STATUS_ACTIVE);
        $expected = (new \moodle_url('/admin/tool/lp/plan.php', ['id' => $fixture->planid, 'noredirect' => 1]))->out(false);

        $this->setUser($this->create_role_user(['moodle/competency:planview']));
        $this->assertFalse(api::read_plan($fixture->planid)->can_manage());
        $actions = $this->export($fixture->planid, 0)['actions'];
        $this->assertSame(get_string('plan_link_core', 'local_dimensions'), $actions['planlinklabel']);
        $this->assertSame($expected, $actions['planurl']);

        $this->setAdminUser();
        $this->assertTrue(api::read_plan($fixture->planid)->can_manage());
        $actions = $this->export($fixture->planid, 0)['actions'];
        $this->assertSame(get_string('plan_link_manage', 'local_dimensions'), $actions['planlinklabel']);
        $this->assertSame($expected, $actions['planurl']);
    }

    /**
     * The rendered row draws the review action hidden until the module arms it, and always the link.
     *
     * Without JavaScript the reader sees the status and the link, never a dead button.
     *
     * @return void
     */
    public function test_the_rendered_row_hides_the_action_until_armed(): void {
        global $OUTPUT;

        $this->resetAfterTest();
        $owner = $this->create_role_user(['moodle/competency:planviewowndraft']);
        $draft = $this->create_plan(plan::STATUS_DRAFT, $owner);
        $active = $this->create_plan(plan::STATUS_ACTIVE, $owner);
        $this->setUser($owner);

        $html = $OUTPUT->render_from_template('local_dimensions/view_plan_summary', $this->export($draft->planid, 0));
        $row = $this->actions_region($html);
        $this->assertMatchesRegularExpression(
            '~<button[^>]*data-action="local-dimensions-plan-request-review"[^>]*\shidden~',
            $row
        );
        $this->assertStringContainsString(get_string('requestreview', 'tool_lp'), $row);
        $this->assertStringContainsString(get_string('planstatusdraft', 'core_competency'), $row);
        $this->assertStringContainsString('noredirect=1', $row);

        $html = $OUTPUT->render_from_template('local_dimensions/view_plan_summary', $this->export($active->planid, 0));
        $row = $this->actions_region($html);
        $this->assertStringNotContainsString('<button', $row);
        $this->assertStringNotContainsString('local-dimensions-status', $row);
        $this->assertStringContainsString('noredirect=1', $row);
    }

    /**
     * The actions row of a rendered page, so an assertion cannot pass on another part of it.
     *
     * @param string $html The rendered page.
     * @return string The row's markup.
     */
    private function actions_region(string $html): string {
        $found = preg_match('~<div[^>]*data-region="local-dimensions-plan-actions".*?</div>~s', $html, $match);
        $this->assertSame(1, $found, 'The rendered page has no actions row.');
        return $match[0];
    }

    /**
     * Export the renderable for a plan, read as the current user.
     *
     * @param int $planid The plan id.
     * @param int $competencyid The competency id the address named, 0 for none.
     * @return array The Mustache context.
     */
    private function export(int $planid, int $competencyid): array {
        global $PAGE;

        $page = new view_plan_summary_page(api::read_plan($planid), $competencyid);
        return $page->export_for_template($PAGE->get_renderer('core'));
    }

    /**
     * A user holding a new system role that allows and prohibits the given capabilities.
     *
     * @param array $allow Capability names to allow.
     * @param array $prohibit Capability names to prohibit, overriding the authenticated user role.
     * @return \stdClass The user.
     */
    private function create_role_user(array $allow, array $prohibit = []): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        $syscontext = \context_system::instance();
        foreach ($allow as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, $syscontext->id);
        }
        foreach ($prohibit as $capability) {
            assign_capability($capability, CAP_PROHIBIT, $roleid, $syscontext->id);
        }
        role_assign($roleid, $user->id, $syscontext->id);
        accesslib_clear_all_caches_for_unit_testing();

        return $user;
    }

    /**
     * An individual plan holding two competencies.
     *
     * @param int $status The plan status.
     * @param \stdClass|null $owner The owner, or null for a new user.
     * @return \stdClass The owner, the plan id and the competency ids in plan order.
     */
    private function create_plan(int $status, ?\stdClass $owner = null): \stdClass {
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework(['visible' => 1]);
        $owner = $owner ?? $this->getDataGenerator()->create_user();
        $plan = $ccg->create_plan(['userid' => $owner->id, 'status' => $status]);

        $ids = [];
        foreach (['Alpha competency', 'Bravo competency'] as $shortname) {
            $competency = $ccg->create_competency([
                'competencyframeworkid' => $framework->get('id'),
                'shortname' => $shortname,
            ]);
            $ids[] = (int) $competency->get('id');
            $ccg->create_plan_competency(['planid' => $plan->get('id'), 'competencyid' => end($ids)]);
        }

        return (object) ['owner' => $owner, 'planid' => (int) $plan->get('id'), 'competencyids' => $ids];
    }
}
