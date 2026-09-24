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

use core_external\external_api;

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
     * With the ID number listed in showuseridentity, identity-capable viewers match by email and ID
     * number and see both, in the configured order and in their plain spelling.
     *
     * @return void
     */
    public function test_search_matches_identity_fields(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('showuseridentity', 'email,idnumber,department');
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $template = $ccg->create_template(['visible' => 1]);
        $templateid = (int) $template->get('id');
        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'Delta',
            'lastname' => 'Identity',
            'email' => 'delta.identity@example.com',
            'idnumber' => 'EMP-9',
            'department' => 'R&D < Ops',
        ]);

        $byemail = search_assignable_users::execute($templateid, 'delta.identity@example.com', 0, 25);
        $ids = array_map(static fn($item): int => (int) $item['id'], $byemail['items']);
        $this->assertContains((int) $user->id, $ids);

        $match = $this->find($this->search($templateid, 'EMP-9'), (int) $user->id);
        $this->assertNotNull($match);
        // Plain values: the picker escapes the label itself, exactly once.
        $this->assertSame('delta.identity@example.com, EMP-9, R&D < Ops', $match['identity']);
    }

    /**
     * By default (showuseridentity is email alone) neither the ID number nor the username is
     * searched or returned, even for a viewer holding moodle/site:viewuseridentity; the email is.
     *
     * @return void
     */
    public function test_default_identity_is_the_email_alone(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();
        // Precondition: the site default this test is about.
        $this->assertSame('email', $CFG->showuseridentity);
        $templateid = $this->create_template();
        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'Epsilon',
            'lastname' => 'Default',
            'username' => 'loginonlyq7',
            'email' => 'epsilon.default@example.com',
            'idnumber' => 'EMPQ-7',
        ]);

        // Control: the email is searchable and shown, so the search itself works.
        $match = $this->find($this->search($templateid, 'epsilon.default@'), (int) $user->id);
        $this->assertNotNull($match);
        $this->assertSame('epsilon.default@example.com', $match['identity']);

        $this->assertNull($this->find($this->search($templateid, 'EMPQ-7'), (int) $user->id));
        $this->assertNull($this->find($this->search($templateid, 'loginonlyq7'), (int) $user->id));
    }

    /**
     * A field leaves the search when it leaves showuseridentity: with the username listed alone, the
     * username matches and is shown, and the email no longer matches.
     *
     * @return void
     */
    public function test_only_listed_identity_fields_are_searched(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('showuseridentity', 'username');
        $templateid = $this->create_template();
        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'Zeta',
            'lastname' => 'Listed',
            'username' => 'zetaloginq8',
            'email' => 'zeta.unlisted@example.com',
        ]);

        $match = $this->find($this->search($templateid, 'zetaloginq8'), (int) $user->id);
        $this->assertNotNull($match);
        $this->assertSame('zetaloginq8', $match['identity']);

        $this->assertNull($this->find($this->search($templateid, 'zeta.unlisted@'), (int) $user->id));
    }

    /**
     * A caller without moodle/site:viewuseridentity searches by name only and sees no identity at
     * all; granting the capability (the control) brings the email back.
     *
     * @return void
     */
    public function test_without_viewuseridentity_no_identity_is_searched_or_shown(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $templateid = $this->create_template();
        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'Eta',
            'lastname' => 'Private',
            'email' => 'eta.private@example.com',
        ]);

        $systemcontext = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/competency:templatemanage', CAP_ALLOW, $roleid, $systemcontext->id);
        assign_capability('moodle/competency:planmanage', CAP_ALLOW, $roleid, $systemcontext->id);
        $manager = $this->getDataGenerator()->create_user();
        role_assign($roleid, (int) $manager->id, $systemcontext->id);
        $this->setUser($manager);

        $byname = $this->find($this->search($templateid, 'Eta Private'), (int) $user->id);
        $this->assertNotNull($byname);
        $this->assertSame('', $byname['identity']);
        $this->assertNull($this->find($this->search($templateid, 'eta.private@'), (int) $user->id));

        assign_capability('moodle/site:viewuseridentity', CAP_ALLOW, $roleid, $systemcontext->id);
        accesslib_clear_all_caches_for_unit_testing();
        $byemail = $this->find($this->search($templateid, 'eta.private@'), (int) $user->id);
        $this->assertNotNull($byemail);
        $this->assertSame('eta.private@example.com', $byemail['identity']);
    }

    /**
     * A showuseridentity entry that is not one of core's user-table identity columns (config.php
     * can set anything) is never selected, searched or shown.
     *
     * @return void
     */
    public function test_an_unknown_identity_entry_is_never_selected(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('showuseridentity', 'email,password');
        $templateid = $this->create_template();
        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'Theta',
            'lastname' => 'Hashed',
            'email' => 'theta.hashed@example.com',
            'password' => 'Secret-123',
        ]);

        $match = $this->find($this->search($templateid, 'Theta Hashed'), (int) $user->id);
        $this->assertNotNull($match);
        $this->assertSame('theta.hashed@example.com', $match['identity']);
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

    /**
     * A template to search against.
     *
     * @return int The template id.
     */
    private function create_template(): int {
        $template = $this->getDataGenerator()->get_plugin_generator('core_competency')->create_template(['visible' => 1]);
        return (int) $template->get('id');
    }

    /**
     * Run the search as the current user and clean the result through the returns structure.
     *
     * @param int $templateid The template id.
     * @param string $query The search text.
     * @return array The cleaned result.
     */
    private function search(int $templateid, string $query): array {
        return external_api::clean_returnvalue(
            search_assignable_users::execute_returns(),
            search_assignable_users::execute($templateid, $query, 0, 25)
        );
    }

    /**
     * The item for one user in a search result, or null when the user was not offered.
     *
     * @param array $result A cleaned search result.
     * @param int $userid The user id.
     * @return array|null
     */
    private function find(array $result, int $userid): ?array {
        foreach ($result['items'] as $item) {
            if ((int) $item['id'] === $userid) {
                return $item;
            }
        }
        return null;
    }
}
