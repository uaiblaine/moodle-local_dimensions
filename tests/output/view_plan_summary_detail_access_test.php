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
use core_competency\plan;
use local_dimensions\external\get_user_competency_summary_in_plan;

/**
 * Tests who is offered each competency's detail on the plan overview.
 *
 * Core reads a draft plan through the draft capabilities (plan::can_read_user_draft()), but every
 * competency detail goes through api::get_plan_competency(), which checks
 * user_competency::can_read_user() - a check that never consults them. A viewer holding only
 * moodle/competency:planviewdraft therefore reads the plan and its list, and every expanded
 * competency failed. The page now withholds the detail from that viewer instead of offering a
 * control that can only fail.
 *
 * A class-level docblock rather than a CoversClass attribute: moodle-cs on the 4.05 leg cannot see
 * attributes, and this plugin still supports 4.5.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\output\view_plan_summary_page
 */
final class view_plan_summary_detail_access_test extends \advanced_testcase {
    /**
     * The defect this guards: the draft-only reader reads the plan, but not a competency's detail.
     *
     * Asserts core's side of the mismatch, so the page's decision below stays tied to a real
     * refusal: if core ever lets this viewer load the detail, this test goes red and the
     * withholding can go.
     *
     * @return void
     */
    public function test_draft_reader_reads_the_plan_but_cannot_load_a_competency_detail(): void {
        $this->resetAfterTest();
        $fixture = $this->create_plan(plan::STATUS_DRAFT);
        $this->setUser($this->create_draft_reader());

        $this->assertSame($fixture->planid, (int) api::read_plan($fixture->planid)->get('id'));

        $this->expectException(\required_capability_exception::class);
        get_user_competency_summary_in_plan::execute($fixture->competencyids[0], $fixture->planid);
    }

    /**
     * The draft-only reader still gets the whole list, flagged as having no detail to open.
     *
     * @return void
     */
    public function test_draft_reader_gets_the_list_without_detail(): void {
        $this->resetAfterTest();
        $fixture = $this->create_plan(plan::STATUS_DRAFT);
        $this->setUser($this->create_draft_reader());

        $data = $this->export($fixture->planid);

        $this->assertCount(2, $data['competencies']);
        $this->assertFalse($data['candetail']);
    }

    /**
     * Viewers who can read user competencies keep the detail: the owner and an administrator.
     *
     * The control for the test above, so a flag that is simply always false cannot pass.
     *
     * @return void
     */
    public function test_viewers_who_can_read_user_competencies_keep_the_detail(): void {
        $this->resetAfterTest();
        $active = $this->create_plan(plan::STATUS_ACTIVE);
        $draft = $this->create_plan(plan::STATUS_DRAFT);

        $this->setUser($active->owner);
        $this->assertTrue($this->export($active->planid)['candetail']);

        $this->setAdminUser();
        $this->assertTrue($this->export($draft->planid)['candetail']);
    }

    /**
     * Without detail, no row renders a control or a detail region, and the page says why once.
     *
     * The same template rendered for an administrator is the control: it must carry both.
     *
     * @return void
     */
    public function test_the_template_withholds_the_detail_controls(): void {
        global $OUTPUT;

        $this->resetAfterTest();
        $fixture = $this->create_plan(plan::STATUS_DRAFT);
        $notice = get_string('detail_unavailable', 'local_dimensions');

        $this->setUser($this->create_draft_reader());
        $html = $OUTPUT->render_from_template('local_dimensions/view_plan_summary', $this->export($fixture->planid));
        $this->assertStringContainsString('Alpha competency', $html);
        $this->assertStringNotContainsString('competency-content-', $html);
        $this->assertStringNotContainsString('aria-expanded', $this->accordion_region($html));
        $this->assertSame(1, substr_count($html, $notice));

        $this->setAdminUser();
        $html = $OUTPUT->render_from_template('local_dimensions/view_plan_summary', $this->export($fixture->planid));
        $this->assertStringContainsString('competency-content-' . $fixture->competencyids[0], $html);
        $this->assertStringNotContainsString($notice, $html);
    }

    /**
     * The competency list region of a rendered page, so an assertion cannot pass on the toolbar.
     *
     * @param string $html The rendered page.
     * @return string The markup from the accordion's opening tag to the end of the page.
     */
    private function accordion_region(string $html): string {
        $start = strpos($html, 'id="local-dimensions-viewplan-accordion"');
        $this->assertNotFalse($start, 'The rendered page has no competency list.');
        return substr($html, $start);
    }

    /**
     * Export the renderable for a plan, read as the current user.
     *
     * @param int $planid The plan id.
     * @return array The Mustache context.
     */
    private function export(int $planid): array {
        global $PAGE;

        $page = new view_plan_summary_page(api::read_plan($planid));
        return $page->export_for_template($PAGE->get_renderer('core'));
    }

    /**
     * A user whose only competency capability is reading draft plans, at the system context.
     *
     * @return \stdClass The user.
     */
    private function create_draft_reader(): \stdClass {
        $reader = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        $syscontext = \context_system::instance();
        assign_capability('moodle/competency:planviewdraft', CAP_ALLOW, $roleid, $syscontext->id);
        role_assign($roleid, $reader->id, $syscontext->id);
        accesslib_clear_all_caches_for_unit_testing();

        return $reader;
    }

    /**
     * A plan holding two competencies, owned by a new user.
     *
     * @param int $status The plan status.
     * @return \stdClass The owner, the plan id and the competency ids in plan order.
     */
    private function create_plan(int $status): \stdClass {
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework(['visible' => 1]);
        $owner = $this->getDataGenerator()->create_user();
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
