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

/**
 * Tests for the browse_competencies external function.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\external\browse_competencies
 */
final class browse_competencies_test extends \advanced_testcase {
    /**
     * Browse roots, then children, and verify haschildren + structure order.
     *
     * @return void
     */
    public function test_browse_tree(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework();
        $fwid = (int) $framework->get('id');

        $root = $ccg->create_competency(['competencyframeworkid' => $fwid, 'shortname' => 'Root A']);
        $child1 = $ccg->create_competency(['competencyframeworkid' => $fwid, 'parentid' => $root->get('id'),
            'shortname' => 'Child 1']);
        $ccg->create_competency(['competencyframeworkid' => $fwid, 'parentid' => $root->get('id'),
            'shortname' => 'Child 2']);

        $roots = browse_competencies::execute($fwid, 0, '', 0, 50);
        $this->assertSame(1, $roots['total']);
        $this->assertCount(1, $roots['items']);
        $this->assertSame((int) $root->get('id'), $roots['items'][0]['id']);
        $this->assertTrue($roots['items'][0]['haschildren']);
        $this->assertSame('', $roots['items'][0]['path']);

        $children = browse_competencies::execute($fwid, (int) $root->get('id'), '', 0, 50);
        $this->assertSame(2, $children['total']);
        $this->assertSame((int) $child1->get('id'), $children['items'][0]['id']);
        $this->assertFalse($children['items'][0]['haschildren']);
    }

    /**
     * Search returns flat matches across depth with a human-readable path.
     *
     * @return void
     */
    public function test_search_with_path(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework();
        $fwid = (int) $framework->get('id');

        $root = $ccg->create_competency(['competencyframeworkid' => $fwid, 'shortname' => 'Communication']);
        $ccg->create_competency(['competencyframeworkid' => $fwid, 'parentid' => $root->get('id'),
            'shortname' => 'Active listening']);

        $result = browse_competencies::execute($fwid, 0, 'listening', 0, 50);
        $this->assertSame(1, $result['total']);
        $this->assertSame('Active listening', $result['items'][0]['shortname']);
        $this->assertSame('Communication', $result['items'][0]['path']);
    }

    /**
     * Pagination honours limitfrom/limitnum while total reflects all matches.
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

        $page = browse_competencies::execute($fwid, 0, '', 0, 2);
        $this->assertSame(5, $page['total']);
        $this->assertCount(2, $page['items']);
    }

    /**
     * An unknown framework returns an empty result rather than erroring.
     *
     * @return void
     */
    public function test_unknown_framework_is_empty(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $result = browse_competencies::execute(123456, 0, '', 0, 50);
        $this->assertSame(0, $result['total']);
        $this->assertSame([], $result['items']);
    }
}
