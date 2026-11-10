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

use advanced_testcase;
use context_coursecat;
use context_system;
use local_dimensions\helper;
use local_dimensions\output\central\contextbar;
use local_dimensions\output\dynamictabs\frameworks;
use local_dimensions\output\dynamictabs\plans;
use local_dimensions\output\dynamictabs\structure;

/**
 * The locked category entry leaves out what lives in an unlisted subcategory.
 *
 * Entered on a listed category, the hub lists that category and its descendants. Each framework and
 * template is kept or withheld by the category it lives in, as local_unlistedcourses answers for the
 * viewer, and the locked bar's subtree counts follow the same rule. Without local_unlistedcourses
 * nothing changes. The real-plugin tests skip where it is not installed (4.5 and 5.1 CI).
 *
 * The class-level covers tags stay in docblock form on purpose: moodle-cs on the 4.05 leg cannot
 * see PHP attributes.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\local\unlisted_categories
 * @covers     \local_dimensions\helper
 * @covers     \local_dimensions\output\dynamictabs\frameworks
 * @covers     \local_dimensions\output\dynamictabs\structure
 * @covers     \local_dimensions\output\dynamictabs\plans
 * @covers     \local_dimensions\output\central\contextbar
 */
final class hub_unlisted_children_test extends advanced_testcase {
    /**
     * Forget local_unlistedcourses' per-request memos, which outlive a test's rollback.
     *
     * @return void
     */
    private function reset_unlisted_memos(): void {
        if (class_exists('\local_unlistedcourses\category_access')) {
            \local_unlistedcourses\category_discoverability::reset_caches();
            \local_unlistedcourses\category_access::reset_caches();
        }
    }

    /**
     * Start every test with local_unlistedcourses' memos empty.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->reset_unlisted_memos();
    }

    /**
     * Leave local_unlistedcourses' memos empty for the next test class.
     *
     * @return void
     */
    protected function tearDown(): void {
        $this->reset_unlisted_memos();
        parent::tearDown();
    }

    /**
     * A listed category "Parent" with an unlisted child "Hidden child", a framework and a template
     * in each, a plain user, and a template reader holding templateview at the site only.
     *
     * @return array Keys: parent, child (stdClass categories), user, reader (stdClass users).
     */
    private function fixture(): array {
        if (!class_exists('\local_unlistedcourses\category_discoverability')) {
            $this->markTestSkipped('local_unlistedcourses is not installed on this site.');
        }
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $generator = $this->getDataGenerator();
        $ccg = $generator->get_plugin_generator('core_competency');
        $parent = $generator->create_category(['name' => 'Parent']);
        $child = $generator->create_category(['name' => 'Hidden child', 'parent' => $parent->id]);
        foreach (['parent' => $parent, 'child' => $child] as $label => $category) {
            $contextid = context_coursecat::instance((int) $category->id)->id;
            $ccg->create_framework(['shortname' => "Framework in $label", 'contextid' => $contextid]);
            $ccg->create_template(['shortname' => "Template in $label", 'contextid' => $contextid]);
        }
        \local_unlistedcourses\category_discoverability::set_state(
            (int) $child->id,
            \local_unlistedcourses\category_discoverability::STATE_UNLISTED
        );

        $reader = $generator->create_user();
        $roleid = $generator->create_role();
        assign_capability('moodle/competency:templateview', CAP_ALLOW, $roleid, context_system::instance()->id);
        role_assign($roleid, (int) $reader->id, context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();

        return ['parent' => $parent, 'child' => $child, 'user' => $generator->create_user(), 'reader' => $reader];
    }

    /**
     * The names a tab lists on the locked entry of a category, sorted.
     *
     * @param string $tabclass The tab class.
     * @param int $categoryid The category id.
     * @param string $listkey The export key holding the list.
     * @return array
     */
    private function names(string $tabclass, int $categoryid, string $listkey): array {
        global $PAGE;
        $tab = new $tabclass(['contexttype' => 'coursecat', 'categoryid' => $categoryid, 'locked' => 1]);
        $data = $tab->export_for_template($PAGE->get_renderer('core'));
        $names = array_map(static fn(array $row): string => (string) ($row['shortname'] ?? $row['name']), $data[$listkey]);
        sort($names);
        return $names;
    }

    /**
     * The locked bar of a category, exported as the current user.
     *
     * @param int $categoryid The category id.
     * @return array
     */
    private function bar(int $categoryid): array {
        global $PAGE;
        return (new contextbar('coursecat', $categoryid, false, true))->export_for_template($PAGE->get_renderer('core'));
    }

    /**
     * Frameworks and Structure leave out the unlisted child's framework for an unrelated viewer.
     *
     * @return void
     */
    public function test_framework_lists_withhold_the_unlisted_child(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $parentid = (int) $f['parent']->id;
        $both = ['Framework in child', 'Framework in parent'];
        $this->assertSame($both, $this->names(frameworks::class, $parentid, 'frameworks'), 'Control: the administrator');
        $this->assertSame($both, $this->names(structure::class, $parentid, 'frameworks'), 'Control: the administrator');

        $this->setUser($f['user']);
        $this->assertSame(['Framework in parent'], $this->names(frameworks::class, $parentid, 'frameworks'));
        $this->assertSame(['Framework in parent'], $this->names(structure::class, $parentid, 'frameworks'));
    }

    /**
     * A role in the unlisted child is the plugin's relationship: its holder keeps the child's framework.
     *
     * @return void
     */
    public function test_a_role_in_the_child_keeps_its_framework(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $childcontext = context_coursecat::instance((int) $f['child']->id);
        role_assign($this->getDataGenerator()->create_role(), (int) $f['user']->id, $childcontext->id);

        $this->setUser($f['user']);
        $this->assertSame(
            ['Framework in child', 'Framework in parent'],
            $this->names(frameworks::class, (int) $f['parent']->id, 'frameworks')
        );
    }

    /**
     * Learning plans leave out the unlisted child's template for a site-wide template reader.
     *
     * @return void
     */
    public function test_the_plans_list_withholds_the_unlisted_child(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $parentid = (int) $f['parent']->id;
        $this->assertSame(['Template in child', 'Template in parent'], $this->names(plans::class, $parentid, 'templates'));

        $this->setUser($f['reader']);
        $this->assertSame(['Template in parent'], $this->names(plans::class, $parentid, 'templates'));
    }

    /**
     * The locked bar's subtree counts leave out the unlisted child's framework and template.
     *
     * @return void
     */
    public function test_the_subtree_counts_withhold_the_unlisted_child(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $parentid = (int) $f['parent']->id;
        $admin = $this->bar($parentid)['categoryoptions'][0];
        $this->assertSame([2, 2], [$admin['frameworkcount'], $admin['templatecount']], 'Control: the administrator');

        $this->setUser($f['reader']);
        $reader = $this->bar($parentid)['categoryoptions'][0];
        $this->assertSame([1, 1], [$reader['frameworkcount'], $reader['templatecount']]);
        $this->assertSame(1, helper::count_frameworks_in_subtree(context_coursecat::instance($parentid)));
        $this->assertSame(1, helper::count_templates_in_subtree(context_coursecat::instance($parentid)));
    }

    /**
     * Without local_unlistedcourses the locked entry lists and counts the whole subtree, as before.
     *
     * @return void
     */
    public function test_a_site_without_the_plugin_lists_the_whole_subtree(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $parentid = (int) $f['parent']->id;
        $property = new \ReflectionProperty(unlisted_categories::class, 'categoryapi');
        try {
            $property->setValue(null, 'local_dimensions\\local\\no_such_category_api');
            $this->setUser($f['reader']);
            $frameworknames = $this->names(frameworks::class, $parentid, 'frameworks');
            $this->assertSame(['Framework in child', 'Framework in parent'], $frameworknames);
            $this->assertSame(['Template in child', 'Template in parent'], $this->names(plans::class, $parentid, 'templates'));
            $this->assertSame(2, helper::count_templates_in_subtree(context_coursecat::instance($parentid)));
        } finally {
            $property->setValue(null, unlisted_categories::CATEGORY_API);
        }
    }

    /**
     * filter_by_context() keeps keys and system-context items, and drops a withheld category's item.
     *
     * @return void
     */
    public function test_filter_by_context_keeps_keys_and_the_site(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $site = $ccg->create_framework(['shortname' => 'Site framework']);
        $inchild = \core_competency\competency_framework::get_record(['shortname' => 'Framework in child']);
        $items = [70 => $site, 80 => $inchild];

        $this->setUser($f['user']);
        $this->assertSame([70 => $site], unlisted_categories::filter_by_context($items));
        $this->assertSame([], unlisted_categories::filter_by_context([]));
    }
}
