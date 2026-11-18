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
 * Tests for the Competency hub tabs' listing scope on the locked category entry.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\output\dynamictabs;

use advanced_testcase;
use context_coursecat;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The locked category entry lists the category and its descendants; the site entry lists one context.
 */
#[CoversClass(\local_dimensions\output\dynamictabs\frameworks::class)]
#[CoversClass(\local_dimensions\output\dynamictabs\structure::class)]
#[CoversClass(\local_dimensions\output\dynamictabs\plans::class)]
final class scope_test extends advanced_testcase {
    /**
     * A category with a child category, one framework and one template in each.
     *
     * @return array Keys: parent (stdClass), child (stdClass).
     */
    private function fixture(): array {
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $generator = $this->getDataGenerator();
        $ccg = $generator->get_plugin_generator('core_competency');
        $parent = $generator->create_category(['name' => 'Parent']);
        $child = $generator->create_category(['name' => 'Child', 'parent' => $parent->id]);
        foreach (['parent' => $parent, 'child' => $child] as $label => $category) {
            $contextid = context_coursecat::instance((int) $category->id)->id;
            $ccg->create_framework(['shortname' => "Framework in $label", 'contextid' => $contextid]);
            $ccg->create_template(['shortname' => "Template in $label", 'contextid' => $contextid]);
        }
        return ['parent' => $parent, 'child' => $child];
    }

    /**
     * Export a tab for a category, locked or not, and return the names it lists.
     *
     * @param string $tabclass The tab class.
     * @param int $categoryid The category id.
     * @param bool $locked Whether the pane is the locked category entry.
     * @param string $listkey The export key holding the list.
     * @return array Sorted names.
     */
    private function names(string $tabclass, int $categoryid, bool $locked, string $listkey): array {
        global $PAGE;
        $tab = new $tabclass(['contexttype' => 'coursecat', 'categoryid' => $categoryid, 'locked' => (int) $locked]);
        $data = $tab->export_for_template($PAGE->get_renderer('core'));
        $names = array_map(static fn(array $row): string => (string) ($row['shortname'] ?? $row['name']), $data[$listkey]);
        sort($names);
        return $names;
    }

    /**
     * Frameworks and structure list the child category's frameworks only on the locked entry.
     *
     * @return void
     */
    public function test_frameworks_and_structure_widen_to_descendants_only_when_locked(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $parentid = (int) $f['parent']->id;

        $this->assertSame(['Framework in parent'], $this->names(frameworks::class, $parentid, false, 'frameworks'));
        $this->assertSame(
            ['Framework in child', 'Framework in parent'],
            $this->names(frameworks::class, $parentid, true, 'frameworks')
        );

        $this->assertSame(['Framework in parent'], $this->names(structure::class, $parentid, false, 'frameworks'));
        $this->assertSame(
            ['Framework in child', 'Framework in parent'],
            $this->names(structure::class, $parentid, true, 'frameworks')
        );
    }

    /**
     * Learning plans list the child category's templates only on the locked entry.
     *
     * @return void
     */
    public function test_plans_widen_to_descendants_only_when_locked(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $parentid = (int) $f['parent']->id;

        $this->assertSame(['Template in parent'], $this->names(plans::class, $parentid, false, 'templates'));
        $this->assertSame(
            ['Template in child', 'Template in parent'],
            $this->names(plans::class, $parentid, true, 'templates')
        );
    }

    /**
     * Locked on the child category, the listing covers the child's subtree and never reaches up to
     * the parent.
     *
     * @return void
     */
    public function test_the_child_entry_does_not_see_the_parent(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $childid = (int) $f['child']->id;

        $this->assertSame(['Framework in child'], $this->names(frameworks::class, $childid, true, 'frameworks'));
        $this->assertSame(['Template in child'], $this->names(plans::class, $childid, true, 'templates'));
    }

    /**
     * The locked entry of a category a user manages keeps out the hidden templates of a
     * subcategory where they only read templates; the subcategory's visible template and the
     * parent's hidden one (which they manage) stay, as the administrator (the control) sees all.
     *
     * @return void
     */
    public function test_a_hidden_template_follows_its_own_category_rights(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $parentcontext = context_coursecat::instance((int) $f['parent']->id);
        $childcontext = context_coursecat::instance((int) $f['child']->id);
        $ccg->create_template(['shortname' => 'Hidden in parent', 'contextid' => $parentcontext->id, 'visible' => 0]);
        $ccg->create_template(['shortname' => 'Hidden in child', 'contextid' => $childcontext->id, 'visible' => 0]);
        $all = ['Hidden in child', 'Hidden in parent', 'Template in child', 'Template in parent'];
        $this->assertSame($all, $this->names(plans::class, (int) $f['parent']->id, true, 'templates'), 'Control');

        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/competency:templatemanage', CAP_ALLOW, $roleid, $parentcontext->id);
        assign_capability('moodle/competency:templateview', CAP_ALLOW, $roleid, $parentcontext->id);
        assign_capability('moodle/competency:templatemanage', CAP_PROHIBIT, $roleid, $childcontext->id);
        role_assign($roleid, (int) $user->id, $parentcontext->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($user);

        $this->assertSame(
            ['Hidden in parent', 'Template in child', 'Template in parent'],
            $this->names(plans::class, (int) $f['parent']->id, true, 'templates')
        );
    }
}
