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
 * Tests for the Competency hub's context bar.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\output\central;

use advanced_testcase;
use context_coursecat;
use context_system;
use local_dimensions\helper;

/**
 * The context bar locked to an entry category, and the System switch offered only to those who may use it.
 *
 * @covers \local_dimensions\output\central\contextbar
 */
final class contextbar_test extends advanced_testcase {
    /**
     * Export the bar as the current user.
     *
     * @param contextbar $bar
     * @return array
     */
    private function export(contextbar $bar): array {
        global $PAGE;
        return $bar->export_for_template($PAGE->get_renderer('core'));
    }

    /**
     * Entered from a category's menu, the bar shows that category by name and nothing to switch.
     *
     * @return void
     */
    public function test_a_locked_bar_names_the_category_and_keeps_only_its_option(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $category = $this->getDataGenerator()->create_category(['name' => 'Engineering']);
        $this->getDataGenerator()->create_category(['name' => 'Elsewhere']);

        $data = $this->export(new contextbar('coursecat', (int) $category->id, false, true));

        $this->assertTrue($data['locked']);
        $this->assertSame('Engineering', $data['lockedcategoryname']);
        $this->assertSame('coursecat', $data['contexttype']);
        $this->assertSame((int) $category->id, $data['selectedcategoryid']);
        $this->assertCount(1, $data['categoryoptions']);
        $this->assertTrue($data['categoryoptions'][0]['selected']);
        $this->assertNull($data['hiddencatstoggle']);
    }

    /**
     * The lock only means something with a readable category; the site entry never locks.
     *
     * @return void
     */
    public function test_the_lock_is_ignored_without_a_category(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $category = $this->getDataGenerator()->create_category();

        $this->assertFalse($this->export(new contextbar('system', 0, false, true))['locked']);
        $this->assertFalse($this->export(new contextbar('coursecat', 0, false, true))['locked']);
        $unlocked = $this->export(new contextbar('coursecat', (int) $category->id, false, false));
        $this->assertFalse($unlocked['locked']);
        $this->assertGreaterThanOrEqual(1, count($unlocked['categoryoptions']));
    }

    /**
     * A manager scoped to one category gets no System switch and no site-wide totals.
     *
     * The authenticated-user default for competencyview is withdrawn at the site so the manager
     * genuinely cannot read the system context; the administrator control keeps the switch.
     *
     * @return void
     */
    public function test_the_system_switch_is_offered_only_to_those_who_may_read_the_site(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $generator->create_framework(['shortname' => 'Site-wide']);
        $category = $this->getDataGenerator()->create_category();
        $categorycontext = context_coursecat::instance((int) $category->id);

        $this->assertTrue($this->export(new contextbar('coursecat', (int) $category->id))['cansystem']);

        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/competency:competencymanage', CAP_ALLOW, $roleid, $categorycontext->id);
        role_assign($roleid, (int) $user->id, $categorycontext->id);
        assign_capability(
            'moodle/competency:competencyview',
            CAP_PREVENT,
            (int) $CFG->defaultuserroleid,
            context_system::instance()->id,
            true
        );
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($user);
        $this->assertFalse(helper::can_read_competency_context(context_system::instance()), 'Precondition');

        $data = $this->export(new contextbar('coursecat', (int) $category->id));
        $this->assertFalse($data['cansystem']);
        $this->assertSame(0, $data['systemframeworkcount']);
        $this->assertSame(0, $data['systemtemplatecount']);
    }
}
