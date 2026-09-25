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

namespace local_dimensions\task;

use core_competency\api;
use core_competency\template;

/**
 * Tests for the template cohort sync task when core would refuse to create the plans.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\task\sync_template_cohort
 */
final class sync_template_cohort_test extends \advanced_testcase {
    /** @var int Template id. */
    protected $templateid;

    /** @var \stdClass The cohort attached to the template. */
    protected $cohort;

    /**
     * A visible template with a cohort of two members attached to it.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $this->templateid = (int) $generator->get_plugin_generator('core_competency')
            ->create_template(['visible' => 1])->get('id');
        $this->cohort = $generator->create_cohort();
        foreach ([$generator->create_user(), $generator->create_user()] as $member) {
            cohort_add_member($this->cohort->id, $member->id);
        }
        api::create_template_cohort($this->templateid, (int) $this->cohort->id);
    }

    /**
     * Run the task for the fixture's pair as the current user.
     *
     * @return string What the task printed.
     */
    protected function run_task(): string {
        $task = new sync_template_cohort();
        $task->set_custom_data([
            'templateid' => $this->templateid,
            'cohortid' => (int) $this->cohort->id,
            'recreateunlinked' => false,
        ]);
        ob_start();
        try {
            $task->execute();
        } finally {
            $output = ob_get_clean();
        }
        return $output;
    }

    /**
     * Plans created from the fixture's template.
     *
     * @return int
     */
    protected function plans(): int {
        global $DB;

        return $DB->count_records('competency_plan', ['templateid' => $this->templateid]);
    }

    /**
     * A user who may read the template gets its plans: the control every refusal below departs from.
     *
     * @return void
     */
    public function test_the_task_creates_the_plans(): void {
        $this->assertSame('', $this->run_task());

        $this->assertSame(2, $this->plans());
    }

    /**
     * A template hidden after the task was queued ends the task instead of throwing.
     *
     * @return void
     */
    public function test_a_hidden_template_finishes_without_plans(): void {
        $template = new template($this->templateid);
        $template->set('visible', 0);
        $template->update();

        $this->assertStringContainsString('the template is hidden', $this->run_task());

        $this->assertSame(0, $this->plans());
    }

    /**
     * A task user who can no longer view the template ends the task instead of throwing.
     *
     * @return void
     */
    public function test_a_user_who_cannot_view_the_template_finishes_without_plans(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->assertFalse((new template($this->templateid))->can_read());

        $this->assertStringContainsString('cannot view the template', $this->run_task());

        $this->assertSame(0, $this->plans());
    }

    /**
     * Competencies turned off after the task was queued end the task instead of throwing.
     *
     * @return void
     */
    public function test_disabled_competencies_finish_without_plans(): void {
        set_config('enabled', 0, 'core_competency');

        $this->assertStringContainsString('competencies are disabled', $this->run_task());

        $this->assertSame(0, $this->plans());
    }

    /**
     * A cohort row gone while its relation stays ends the task instead of throwing.
     *
     * @return void
     */
    public function test_a_missing_cohort_finishes_without_plans(): void {
        global $DB;

        $DB->delete_records('cohort', ['id' => $this->cohort->id]);

        $this->assertStringContainsString('the cohort no longer exists', $this->run_task());

        $this->assertSame(0, $this->plans());
    }

    /**
     * A hidden cohort the task user may not view ends the task instead of throwing.
     *
     * @return void
     */
    public function test_a_hidden_cohort_the_user_cannot_view_finishes_without_plans(): void {
        global $DB;

        $DB->set_field('cohort', 'visible', 0, ['id' => $this->cohort->id]);
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        $systemcontext = \context_system::instance();
        assign_capability('moodle/competency:templateview', CAP_ALLOW, $roleid, $systemcontext->id);
        role_assign($roleid, (int) $user->id, $systemcontext->id);
        $this->setUser($user);
        $this->assertTrue((new template($this->templateid))->can_read());
        $this->assertFalse(has_capability('moodle/cohort:view', $systemcontext));

        $this->assertStringContainsString('cannot view the hidden cohort', $this->run_task());

        $this->assertSame(0, $this->plans());
    }
}
