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
use core_competency\competency_framework;

/**
 * Tests for helper::structure_nodes() activity-count and rule-label enrichment.
 *
 * @covers \local_dimensions\helper::structure_nodes
 */
final class helper_structure_nodes_test extends advanced_testcase {
    /**
     * Drop the handlers' cached field lists, which would outlive the rollback of the rows they hold.
     *
     * @return void
     */
    protected function tearDown(): void {
        customfield\lp_handler::create()->reset_configuration_cache();
        customfield\competency_handler::create()->reset_configuration_cache();
        parent::tearDown();
    }

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
     * Pins a core assumption the node query depends on: the colour fields are customfield text
     * fields, whose datafield() is `charvalue`, yet {@see helper::structure_nodes()} reads the
     * generic `value` column. That works because
     * {@see \core_customfield\data_controller::instance_form_save()} writes the submitted value to
     * both columns; if core stopped mirroring, the colour queries would silently return nothing.
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

    /**
     * A blank line in a select's option text does not shift the label a node shows.
     *
     * Core's select skips blank lines, so with the options "Alpha", "", "Bravo" it offers two
     * choices and stores Bravo as index 2.
     *
     * @return void
     */
    public function test_a_blank_option_line_does_not_shift_the_type_label(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);

        $field = helper::find_field_by_shortname(constants::CFIELD_TYPE, helper::AREA_COMPETENCY);
        $this->assertNotNull($field);
        $fieldid = (int) $field->get('id');
        $config = json_decode((string) $DB->get_field('customfield_field', 'configdata', ['id' => $fieldid]), true);
        $config['options'] = "Alpha\n\nBravo";
        $DB->set_field('customfield_field', 'configdata', json_encode($config), ['id' => $fieldid]);
        customfield\competency_handler::create()->reset_configuration_cache();

        // Precondition: core's own select lists Bravo at index 2.
        $field = helper::find_field_by_shortname(constants::CFIELD_TYPE, helper::AREA_COMPETENCY);
        $this->assertSame(['', 'Alpha', 'Bravo'], array_values($field->get_options()));

        $cgen = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $cgen->create_framework();
        $competency = $cgen->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $competencyid = (int) $competency->get('id');
        customfield\competency_handler::create()->instance_form_save(
            (object) ['id' => $competencyid, 'customfield_' . constants::CFIELD_TYPE => 2],
            false
        );

        $nodes = helper::structure_nodes([$competency], $framework, $framework->get_context());

        $this->assertCount(1, $nodes);
        $this->assertSame('Bravo', $nodes[0]['type']);
        // The CSV export reads the same stored index through the field controller.
        $this->assertSame('Bravo', helper::read_competency_select_label($competencyid, constants::CFIELD_TYPE));
    }

    /**
     * Each node names the taxonomy its framework sets for the node's level, in core's words.
     *
     * @return void
     */
    public function test_nodes_name_the_taxonomy_of_their_level(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $cgen = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $cgen->create_framework([
            'taxonomies' => implode(',', [
                competency_framework::TAXONOMY_DOMAIN,
                competency_framework::TAXONOMY_SKILL,
            ]),
        ]);
        $root = $cgen->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $child = $cgen->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'parentid' => $root->get('id'),
        ]);

        $nodes = helper::structure_nodes([$root, $child], $framework, $framework->get_context());

        $this->assertCount(2, $nodes);
        $domain = (string) competency_framework::get_taxonomy_from_constant(competency_framework::TAXONOMY_DOMAIN);
        $skill = (string) competency_framework::get_taxonomy_from_constant(competency_framework::TAXONOMY_SKILL);
        $this->assertNotSame($domain, $skill);
        $this->assertSame($domain, $nodes[0]['taxonomy']);
        $this->assertSame($skill, $nodes[1]['taxonomy']);
    }
}
