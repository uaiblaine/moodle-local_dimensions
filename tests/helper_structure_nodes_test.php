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
 * Tests for helper::structure_nodes() node enrichment (activity count + rule label).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions;

use advanced_testcase;
use core_competency\api;
use core_competency\competency;

/**
 * Tests for helper::structure_nodes() activity-count and rule-label enrichment.
 *
 * @covers \local_dimensions\helper::structure_nodes
 */
final class helper_structure_nodes_test extends advanced_testcase {
    /**
     * A node reports its linked-course count, linked-activity count and a rule label.
     *
     * @return void
     */
    public function test_nodes_carry_courses_activities_and_rule_label(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $cgen = $generator->get_plugin_generator('core_competency');
        $framework = $cgen->create_framework();
        $parent = $cgen->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'shortname' => 'Parent',
        ]);
        $child = $cgen->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'parentid' => $parent->get('id'),
            'shortname' => 'Child',
        ]);

        // Link the child to one course and one course-module activity.
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id]);
        api::add_competency_to_course($course->id, $child->get('id'));
        api::add_competency_to_course_module($page->cmid, $child->get('id'));

        $context = $framework->get_context();
        $records = competency::get_records(
            ['competencyframeworkid' => $framework->get('id'), 'parentid' => $parent->get('id')],
            'sortorder',
            'ASC'
        );

        $nodes = helper::structure_nodes($records, $framework, $context);

        $this->assertCount(1, $nodes);
        $node = $nodes[0];
        $this->assertSame((int) $child->get('id'), (int) $node['id']);
        $this->assertSame(1, (int) $node['coursecount']);
        $this->assertArrayHasKey('activitycount', $node);
        $this->assertSame(1, (int) $node['activitycount']);
        $this->assertArrayHasKey('rulelabel', $node);
        $this->assertNotSame('', (string) $node['rulelabel']);
    }
}
