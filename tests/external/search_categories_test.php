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
 * Tests for the Competency hub's course category search.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use advanced_testcase;
use context_coursecat;
use context_system;
use local_dimensions\helper;

/**
 * The picker's search: name match, plain nested names, counts, visibility and readability.
 *
 * @covers \local_dimensions\external\search_categories
 * @covers \local_dimensions\helper
 */
final class search_categories_test extends advanced_testcase {
    /**
     * Categories with an ampersand, a child, and a hidden sibling, plus a framework in the parent.
     *
     * @return array Keys: parent, child, hidden (stdClass categories).
     */
    private function fixture(): array {
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $generator = $this->getDataGenerator();
        $parent = $generator->create_category(['name' => 'Arts & Crafts']);
        $child = $generator->create_category(['name' => 'Pottery', 'parent' => $parent->id]);
        $hidden = $generator->create_category(['name' => 'Pottery archive', 'visible' => 0]);
        $ccg = $generator->get_plugin_generator('core_competency');
        $ccg->create_framework(['contextid' => context_coursecat::instance((int) $parent->id)->id]);
        $ccg->create_template(['contextid' => context_coursecat::instance((int) $child->id)->id]);
        return ['parent' => $parent, 'child' => $child, 'hidden' => $hidden];
    }

    /**
     * Names by id from a result.
     *
     * @param array $result The service result.
     * @return array id => name
     */
    private function names(array $result): array {
        $names = [];
        foreach ($result['items'] as $item) {
            $names[(int) $item['id']] = $item['name'];
        }
        return $names;
    }

    /**
     * A query matches the name, hits carry the plain nested name and both counts.
     *
     * @return void
     */
    public function test_hits_carry_plain_nested_names_and_counts(): void {
        $this->resetAfterTest();
        $f = $this->fixture();

        $result = search_categories::execute('pottery');
        $names = $this->names($result);
        $this->assertSame(['Arts & Crafts / Pottery'], array_values(array_intersect_key($names, [(int) $f['child']->id => 1])));
        $this->assertArrayNotHasKey((int) $f['hidden']->id, $names, 'Hidden categories stay out unless asked for');

        $byid = [];
        foreach (search_categories::execute('')['items'] as $item) {
            $byid[(int) $item['id']] = $item;
        }
        $this->assertSame('Arts & Crafts', $byid[(int) $f['parent']->id]['name']);
        $this->assertSame(1, $byid[(int) $f['parent']->id]['frameworkcount']);
        $this->assertSame(0, $byid[(int) $f['parent']->id]['templatecount']);
        $this->assertSame(1, $byid[(int) $f['child']->id]['templatecount']);
        $this->assertFalse($byid[(int) $f['parent']->id]['hidden']);
    }

    /**
     * Hidden categories are offered only when asked for, and only to a viewer who may see them.
     *
     * @return void
     */
    public function test_hidden_categories_follow_the_flag_and_the_viewer(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $hiddenid = (int) $f['hidden']->id;

        $this->assertArrayNotHasKey($hiddenid, $this->names(search_categories::execute('archive', false)));
        $withhidden = search_categories::execute('archive', true);
        $this->assertArrayHasKey($hiddenid, $this->names($withhidden));
        $this->assertTrue($withhidden['items'][0]['hidden']);

        // A plain user may not see hidden categories, whatever the flag says.
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertArrayNotHasKey($hiddenid, $this->names(search_categories::execute('archive', true)));
    }

    /**
     * The page size is honoured and capped.
     *
     * @return void
     */
    public function test_the_limit_is_honoured(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        for ($i = 1; $i <= 6; $i++) {
            $this->getDataGenerator()->create_category(['name' => "Bulk $i"]);
        }

        $this->assertCount(3, search_categories::execute('Bulk', false, 3)['items']);
        $this->assertCount(6, search_categories::execute('Bulk', false, 25)['items']);
        $this->assertLessThanOrEqual(50, count(search_categories::execute('', false, 500)['items']));
    }

    /**
     * A manager scoped to one category is offered that category only; a site reader is not
     * checked per category.
     *
     * @return void
     */
    public function test_readability_is_checked_per_hit_for_a_category_scoped_viewer(): void {
        global $CFG;
        $this->resetAfterTest();
        $f = $this->fixture();
        $mine = $this->getDataGenerator()->create_category(['name' => 'Mine']);
        $minecontext = context_coursecat::instance((int) $mine->id);

        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/competency:competencymanage', CAP_ALLOW, $roleid, $minecontext->id);
        role_assign($roleid, (int) $user->id, $minecontext->id);
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

        $names = $this->names(search_categories::execute(''));
        $this->assertSame([(int) $mine->id => 'Mine'], $names);
        $this->assertNull(helper::central_category_option((int) $f['parent']->id));
        $this->assertSame('Mine', helper::central_category_option((int) $mine->id)['name']);
    }
}
