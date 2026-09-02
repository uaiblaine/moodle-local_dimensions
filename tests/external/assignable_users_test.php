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

namespace local_dimensions\external;

/**
 * Tests for the search_assignable_users external function.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\external\search_assignable_users
 */
final class assignable_users_test extends \advanced_testcase {
    /**
     * Users who already have a plan from the template, and inactive users, never appear.
     *
     * @return void
     */
    public function test_search_excludes_planned_and_inactive_users(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $template = $ccg->create_template(['visible' => 1]);
        $templateid = (int) $template->get('id');
        $planned = $this->getDataGenerator()->create_user(['firstname' => 'Gamma', 'lastname' => 'Planned']);
        $free = $this->getDataGenerator()->create_user(['firstname' => 'Gamma', 'lastname' => 'Free']);
        $suspended = $this->getDataGenerator()->create_user([
            'firstname' => 'Gamma',
            'lastname' => 'Suspended',
            'suspended' => 1,
        ]);

        add_template_user_plan::execute($templateid, (int) $planned->id);

        $result = search_assignable_users::execute($templateid, 'Gamma', 0, 25);
        $ids = array_map(static fn($item): int => (int) $item['id'], $result['items']);
        $this->assertContains((int) $free->id, $ids);
        $this->assertNotContains((int) $planned->id, $ids);
        $this->assertNotContains((int) $suspended->id, $ids);
    }

    /**
     * Identity-capable viewers can match by email and ID number and see the identity string.
     *
     * @return void
     */
    public function test_search_matches_identity_fields(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $template = $ccg->create_template(['visible' => 1]);
        $templateid = (int) $template->get('id');
        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'Delta',
            'lastname' => 'Identity',
            'email' => 'delta.identity@example.com',
            'idnumber' => 'EMP-9',
        ]);

        $byemail = search_assignable_users::execute($templateid, 'delta.identity@example.com', 0, 25);
        $ids = array_map(static fn($item): int => (int) $item['id'], $byemail['items']);
        $this->assertContains((int) $user->id, $ids);

        $byidnumber = search_assignable_users::execute($templateid, 'EMP-9', 0, 25);
        $match = null;
        foreach ($byidnumber['items'] as $item) {
            if ((int) $item['id'] === (int) $user->id) {
                $match = $item;
            }
        }
        $this->assertNotNull($match);
        $this->assertStringContainsString('EMP-9', $match['identity']);
    }

    /**
     * A manager scoped to one course category is offered nobody: the picker lists only users the
     * caller may create a plan for (planmanage in the user's own context), never the whole site.
     *
     * @return void
     */
    public function test_a_category_manager_is_offered_nobody_they_cannot_plan_for(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $category = $this->getDataGenerator()->create_category();
        $categorycontext = \context_coursecat::instance((int) $category->id);
        $template = $ccg->create_template(['visible' => 1, 'contextid' => $categorycontext->id]);
        $templateid = (int) $template->get('id');
        $someone = $this->getDataGenerator()->create_user(['firstname' => 'Delta', 'lastname' => 'Someone']);

        // Control: the administrator (planmanage everywhere) is offered the user.
        $offered = search_assignable_users::execute($templateid, 'Delta')['items'];
        $ids = array_map(static fn($item): int => (int) $item['id'], $offered);
        $this->assertContains((int) $someone->id, $ids);

        $manager = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/competency:templatemanage', CAP_ALLOW, $roleid, $categorycontext->id);
        role_assign($roleid, (int) $manager->id, $categorycontext->id);
        $this->setUser($manager);

        $result = search_assignable_users::execute($templateid, 'Delta');
        $this->assertSame(0, $result['total']);
        $this->assertSame([], $result['items']);
    }
}
