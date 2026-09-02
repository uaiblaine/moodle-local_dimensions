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

/**
 * The locked category entry lists the category and its descendants; the site entry lists one context.
 *
 * @covers \local_dimensions\output\dynamictabs\frameworks
 * @covers \local_dimensions\output\dynamictabs\structure
 * @covers \local_dimensions\output\dynamictabs\plans
 */
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
     * Export a tab for the parent category, locked or not, and return the names it lists.
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
     * The site entry stays 'self' even for a category picked in the bar: a stale locked flag is
     * never inferred from the context type.
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
}
