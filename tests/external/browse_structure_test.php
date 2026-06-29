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

use core_competency\api;

/**
 * Tests for the browse_structure external function.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\external\browse_structure
 */
final class browse_structure_test extends \advanced_testcase {
    /**
     * Roots carry depth/indent/parentid/haschildren and a non-empty taxonomy; children carry depth 1.
     *
     * @return void
     */
    public function test_roots_and_children(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework();
        $fwid = (int) $framework->get('id');

        $root = $ccg->create_competency(['competencyframeworkid' => $fwid, 'shortname' => 'Root A']);
        $child = $ccg->create_competency(['competencyframeworkid' => $fwid, 'parentid' => $root->get('id'),
            'shortname' => 'Child A1']);

        $roots = browse_structure::execute($fwid, 0, 0, 50);
        $this->assertSame(1, $roots['total']);
        $this->assertCount(1, $roots['items']);
        $node = $roots['items'][0];
        $this->assertSame((int) $root->get('id'), $node['id']);
        $this->assertSame(0, $node['parentid']);
        $this->assertSame(0, $node['depth']);
        $this->assertSame(0, $node['indent']);
        $this->assertTrue($node['haschildren']);
        $this->assertNotEmpty($node['taxonomy']);
        $this->assertNull($node['ruletype']);
        $this->assertNull($node['ruleconfig']);

        $children = browse_structure::execute($fwid, (int) $root->get('id'), 0, 50);
        $this->assertSame(1, $children['total']);
        $childnode = $children['items'][0];
        $this->assertSame((int) $child->get('id'), $childnode['id']);
        $this->assertSame((int) $root->get('id'), $childnode['parentid']);
        $this->assertSame(1, $childnode['depth']);
        $this->assertSame(22, $childnode['indent']);
        $this->assertFalse($childnode['haschildren']);
    }

    /**
     * Pagination honours limitfrom/limitnum while total reflects all siblings.
     *
     * @return void
     */
    public function test_pagination(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework();
        $fwid = (int) $framework->get('id');
        for ($i = 0; $i < 5; $i++) {
            $ccg->create_competency(['competencyframeworkid' => $fwid, 'shortname' => 'Root ' . $i]);
        }

        $page = browse_structure::execute($fwid, 0, 0, 2);
        $this->assertSame(5, $page['total']);
        $this->assertCount(2, $page['items']);

        $next = browse_structure::execute($fwid, 0, 4, 2);
        $this->assertSame(5, $next['total']);
        $this->assertCount(1, $next['items']);
    }

    /**
     * A linked course is reflected in the node's coursecount.
     *
     * @return void
     */
    public function test_coursecount(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework();
        $fwid = (int) $framework->get('id');
        $comp = $ccg->create_competency(['competencyframeworkid' => $fwid, 'shortname' => 'Linked']);

        $course = $this->getDataGenerator()->create_course();
        api::add_competency_to_course($course->id, $comp->get('id'));

        $roots = browse_structure::execute($fwid, 0, 0, 50);
        $this->assertSame(1, $roots['items'][0]['coursecount']);
    }

    /**
     * Rule fields (ruletype/ruleoutcome/ruleconfig) round-trip through the shaper.
     *
     * @return void
     */
    public function test_rule_fields(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework();
        $fwid = (int) $framework->get('id');
        $comp = $ccg->create_competency(['competencyframeworkid' => $fwid, 'shortname' => 'Ruled']);

        $config = '{"base":{"points":2},"competencies":[]}';
        $DB->set_field('competency', 'ruletype', 'core_competency\\competency_rule_points', ['id' => $comp->get('id')]);
        $DB->set_field('competency', 'ruleoutcome', 1, ['id' => $comp->get('id')]);
        $DB->set_field('competency', 'ruleconfig', $config, ['id' => $comp->get('id')]);

        $roots = browse_structure::execute($fwid, 0, 0, 50);
        $node = $roots['items'][0];
        $this->assertSame('core_competency\\competency_rule_points', $node['ruletype']);
        $this->assertSame(1, $node['ruleoutcome']);
        $this->assertSame($config, $node['ruleconfig']);
    }

    /**
     * An unknown framework returns an empty result rather than erroring.
     *
     * @return void
     */
    public function test_unknown_framework_is_empty(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $result = browse_structure::execute(123456, 0, 0, 50);
        $this->assertSame(0, $result['total']);
        $this->assertSame([], $result['items']);
    }
}
