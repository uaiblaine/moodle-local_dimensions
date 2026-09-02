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
 * Tests for the plugin's Site administration registrations.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions;

use admin_externalpage;
use advanced_testcase;
use context_system;

/**
 * Who finds what in the admin tree: the hub is gated by its capability, the rest by site config.
 *
 * settings.php runs inside admin_get_root(), so the assertions rebuild the tree as each user.
 *
 * @coversNothing
 */
final class settings_registration_test extends advanced_testcase {
    /**
     * Rebuild the admin tree for the current user and locate a section by name.
     *
     * @param string $section The admin tree section name.
     * @return \part_of_admin_tree|null
     */
    private function locate(string $section) {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');
        return admin_get_root(true, true)->locate($section, true);
    }

    /**
     * A system-level competency manager without site configuration reaches the hub, and only the hub.
     *
     * @return void
     */
    public function test_a_system_competency_manager_finds_the_hub_but_not_the_site_pages(): void {
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');

        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/competency:competencymanage', CAP_ALLOW, $roleid, context_system::instance()->id);
        role_assign($roleid, (int) $user->id, context_system::instance()->id);
        $this->setUser($user);

        $hub = $this->locate('local_dimensions_central');
        $this->assertInstanceOf(admin_externalpage::class, $hub);
        $this->assertTrue($hub->check_access());

        $this->assertNull($this->locate('local_dimensions_settings'));
        $this->assertNull($this->locate('local_dimensions_customfield'));
        $this->assertNull($this->locate('local_dimensions_customfield_template'));
    }

    /**
     * A user without the capability finds the page in the tree but is refused by it.
     *
     * The page is registered for everyone; the capability is what admits, exactly as tool_lp does.
     *
     * @return void
     */
    public function test_a_plain_user_is_refused_by_the_hub_page(): void {
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $this->setUser($this->getDataGenerator()->create_user());

        $hub = $this->locate('local_dimensions_central');
        $this->assertInstanceOf(admin_externalpage::class, $hub);
        $this->assertFalse($hub->check_access());
    }

    /**
     * The site administrator still gets every page, and nothing is registered while competencies are off.
     *
     * @return void
     */
    public function test_the_administrator_gets_every_page_and_disabled_competencies_register_nothing(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        set_config('enabled', 1, 'core_competency');
        $pages = ['local_dimensions_central', 'local_dimensions_customfield', 'local_dimensions_customfield_template'];
        foreach ($pages as $section) {
            $this->assertInstanceOf(admin_externalpage::class, $this->locate($section), $section);
        }
        $this->assertNotNull($this->locate('local_dimensions_settings'));

        set_config('enabled', 0, 'core_competency');
        $this->assertNull($this->locate('local_dimensions_central'));
        $this->assertNull($this->locate('local_dimensions_settings'));
    }
}
