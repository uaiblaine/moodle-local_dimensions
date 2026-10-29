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

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The plan page offers the evidence-submission button only to the owner viewing their own plan.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_dimensions\local\plan_access::class)]
final class plan_access_evidence_button_test extends \advanced_testcase {
    /**
     * A plan of a fresh learner, with the site setting on.
     *
     * @return array [\core_competency\plan $plan, \stdClass $owner]
     */
    private function build_plan(): array {
        $this->resetAfterTest();
        set_config('enableevidencesubmitbutton', 1, 'local_dimensions');
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $owner = $this->getDataGenerator()->create_user();
        $plan = $ccg->create_plan(['userid' => $owner->id]);
        return [$plan, $owner];
    }

    /**
     * The owner, who may manage their own evidence, is offered the button.
     *
     * @return void
     */
    public function test_owner_with_manageown_is_offered_the_button(): void {
        [$plan, $owner] = $this->build_plan();
        $this->setUser($owner);

        $this->assertTrue(plan_access::can_submit_evidence($plan));
    }

    /**
     * A teacher or manager viewing someone's plan is not, though they may manage that user's evidence.
     *
     * @return void
     */
    public function test_a_viewer_is_not_offered_the_button(): void {
        [$plan] = $this->build_plan();
        $viewer = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        $usercontext = \context_user::instance((int) $plan->get('userid'));
        foreach (['moodle/competency:usercompetencyview', 'moodle/competency:userevidencemanage'] as $cap) {
            assign_capability($cap, CAP_ALLOW, $roleid, $usercontext->id);
        }
        role_assign($roleid, (int) $viewer->id, $usercontext->id);
        $this->setUser($viewer);

        $this->assertTrue(\core_competency\user_evidence::can_manage_user((int) $plan->get('userid')));
        $this->assertFalse(plan_access::can_submit_evidence($plan));
    }

    /**
     * With the setting off nobody is offered it, the owner included.
     *
     * @return void
     */
    public function test_setting_off_hides_the_button_from_the_owner(): void {
        [$plan, $owner] = $this->build_plan();
        set_config('enableevidencesubmitbutton', 0, 'local_dimensions');
        $this->setUser($owner);

        $this->assertFalse(plan_access::can_submit_evidence($plan));
    }

    /**
     * An owner who may not manage their own evidence is not offered it.
     *
     * @return void
     */
    public function test_owner_without_manageown_is_not_offered_the_button(): void {
        global $DB;
        [$plan, $owner] = $this->build_plan();
        $userroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
        assign_capability(
            'moodle/competency:userevidencemanageown',
            CAP_PROHIBIT,
            $userroleid,
            \context_system::instance()->id,
            true
        );
        $this->setUser($owner);

        $this->assertFalse(plan_access::can_submit_evidence($plan));
    }
}
