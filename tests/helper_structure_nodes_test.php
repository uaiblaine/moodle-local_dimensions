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

    /**
     * A node carries the custom colours set on its competency.
     *
     * This pins an assumption the node query depends on rather than a behaviour of our own.
     * The colour fields are customfield **text** fields, whose data_controller::datafield() is
     * `charvalue` — yet {@see helper::structure_nodes()} reads them from the generic `value`
     * column. That is correct, because core's data_controller::instance_form_save() writes the
     * submitted value to BOTH the type-specific column and `value`
     * (`customfield/classes/data_controller.php:206-210`). If core ever stopped mirroring, three
     * of this plugin's batch queries would silently start returning empty colours, and this test
     * is what would say so.
     *
     * @return void
     */
    public function test_nodes_carry_the_competency_custom_colours(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);

        $cgen = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $cgen->create_framework();
        $parent = $cgen->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'shortname' => 'Parent',
        ]);
        $child = $cgen->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'parentid' => $parent->get('id'),
            'shortname' => 'Coloured',
        ]);

        // Through the handler, which is the only way the plugin ever writes these values.
        $formdata = (object) (['id' => (int) $child->get('id')] + helper::customfields_to_formdata([
            'cf_bgcolor' => '#ff0000',
            'cf_textcolor' => '#ffffff',
        ], helper::AREA_COMPETENCY));
        customfield\competency_handler::create()->instance_form_save($formdata, true);

        $records = competency::get_records(
            ['competencyframeworkid' => $framework->get('id'), 'parentid' => $parent->get('id')],
            'sortorder',
            'ASC'
        );
        $nodes = helper::structure_nodes($records, $framework, $framework->get_context());

        $this->assertCount(1, $nodes);
        $this->assertSame('#ff0000', (string) $nodes[0]['bgcolor']);
        $this->assertSame('#ffffff', (string) $nodes[0]['textcolor']);
    }
}
