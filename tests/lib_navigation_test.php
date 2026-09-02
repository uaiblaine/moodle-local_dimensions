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
 * Tests for the course-category navigation callback.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions;

use advanced_testcase;
use context_coursecat;
use navigation_node;

/**
 * Who gets the Competency hub in a category's "More" menu, and where the node points.
 *
 * Core does not unit-test tool_lp's twin callback, so the function is called directly on a bare
 * container node, which is all settings_navigation::load_category_settings() hands it.
 *
 * @coversNothing
 */
final class lib_navigation_test extends advanced_testcase {
    /**
     * Load the plugin's lib.php once per test.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        require_once(__DIR__ . '/../lib.php');
    }

    /**
     * Run the callback for a category and return the hub node it added, if any.
     *
     * @param \context $context The course category context.
     * @return navigation_node|null
     */
    private function hub_node(\context $context): ?navigation_node {
        $root = navigation_node::create('category', null, navigation_node::TYPE_CONTAINER, null, 'categorysettings');
        local_dimensions_extend_navigation_category_settings($root, $context);
        $node = $root->find('local_dimensions_central', navigation_node::TYPE_SETTING);
        return $node ?: null;
    }

    /**
     * Create a user holding one capability in a fresh category, and return both.
     *
     * @param string $capability The capability to grant at the category.
     * @return array Keys: user (stdClass), context (context_coursecat).
     */
    private function create_category_user(string $capability): array {
        $category = $this->getDataGenerator()->create_category();
        $context = context_coursecat::instance((int) $category->id);
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability($capability, CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, (int) $user->id, $context->id);
        return ['user' => $user, 'context' => $context];
    }

    /**
     * A manager of frameworks or of templates in the category gets the node, forced into the
     * "More" menu, pointing at the hub with that category's context on the URL.
     *
     * @return void
     */
    public function test_a_category_manager_gets_a_node_pointing_at_their_category(): void {
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');

        foreach (['moodle/competency:competencymanage', 'moodle/competency:templatemanage'] as $capability) {
            $fixture = $this->create_category_user($capability);
            $this->setUser($fixture['user']);

            $node = $this->hub_node($fixture['context']);
            $this->assertNotNull($node, "$capability must earn the node");
            $this->assertTrue($node->forceintomoremenu, 'The node belongs in the More menu, like tool_lp\'s');
            $this->assertSame(get_string('central', 'local_dimensions'), $node->text);
            $this->assertSame((string) $fixture['context']->id, $node->action->get_param('pagecontextid'));
            $this->assertStringContainsString('/local/dimensions/central.php', $node->action->out(false));
        }
    }

    /**
     * Reading is an authenticated-user default at every category, so a plain user gets no node:
     * the menu entry is for people who manage, not for everyone who may look.
     *
     * @return void
     */
    public function test_a_plain_user_gets_no_node(): void {
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $category = $this->getDataGenerator()->create_category();
        $context = context_coursecat::instance((int) $category->id);
        $this->setUser($this->getDataGenerator()->create_user());

        $this->assertTrue(has_capability('moodle/competency:competencyview', $context), 'Precondition: reading is a default');
        $this->assertNull($this->hub_node($context));
    }

    /**
     * With competencies disabled site-wide nothing is added, whoever asks.
     *
     * @return void
     */
    public function test_nothing_is_added_while_competencies_are_disabled(): void {
        $this->resetAfterTest();
        set_config('enabled', 0, 'core_competency');
        $fixture = $this->create_category_user('moodle/competency:competencymanage');
        $this->setUser($fixture['user']);

        $this->assertNull($this->hub_node($fixture['context']));
    }
}
