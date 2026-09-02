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

    /**
     * A locked bar counts the whole subtree, and names the category in the plain spelling.
     *
     * The picker's per-category counts are 'self' counts; the locked entry lists descendants too,
     * so its headline count must agree with the list. Category names come back from core already
     * escaped, and the double stash escapes again, so the export must carry the plain ampersand.
     *
     * @return void
     */
    public function test_a_locked_bar_counts_the_subtree_and_keeps_the_plain_name(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $generator = $this->getDataGenerator();
        $ccg = $generator->get_plugin_generator('core_competency');
        $parent = $generator->create_category(['name' => 'Arts & Crafts']);
        $child = $generator->create_category(['name' => 'Pottery', 'parent' => $parent->id]);
        $parentcontext = context_coursecat::instance((int) $parent->id);
        $childcontext = context_coursecat::instance((int) $child->id);
        $ccg->create_framework(['contextid' => $parentcontext->id]);
        $ccg->create_framework(['contextid' => $childcontext->id]);
        $ccg->create_framework(['contextid' => $childcontext->id, 'visible' => 0]);
        $ccg->create_template(['contextid' => $childcontext->id]);

        $locked = $this->export(new contextbar('coursecat', (int) $parent->id, false, true));
        $this->assertSame('Arts & Crafts', $locked['lockedcategoryname']);
        $this->assertSame(2, $locked['categoryoptions'][0]['frameworkcount']);
        $this->assertSame(1, $locked['categoryoptions'][0]['templatecount']);
        $this->assertSame(2, $locked['selectedframeworkcount']);
        $this->assertSame(1, $locked['selectedtemplatecount']);

        $unlocked = $this->export(new contextbar('coursecat', (int) $parent->id, false, false));
        $selected = array_values(array_filter($unlocked['categoryoptions'], fn(array $o): bool => $o['selected']));
        $this->assertSame('Arts & Crafts', $selected[0]['name']);
        $this->assertSame(1, $selected[0]['frameworkcount']);
        $this->assertSame(0, $selected[0]['templatecount']);
    }
}
