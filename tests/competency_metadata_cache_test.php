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

namespace local_dimensions;

use local_dimensions\customfield\competency_handler;

/**
 * Tests for the competency metadata the learner views read their tag and type labels from.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\competency_metadata_cache
 */
final class competency_metadata_cache_test extends \advanced_testcase {
    /**
     * Drop the handler's cached field list, which would outlive the rollback of the rows it holds.
     *
     * @return void
     */
    protected function tearDown(): void {
        competency_handler::create()->reset_configuration_cache();
        parent::tearDown();
    }

    /**
     * A blank line in a select's option text does not shift the label a competency shows.
     *
     * Core's select skips blank lines, so with the options "Alpha", "", "Bravo" it offers two
     * choices and stores Bravo as index 2.
     *
     * @return void
     */
    public function test_a_blank_option_line_does_not_shift_the_tag_label(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);

        $field = helper::find_field_by_shortname(constants::CFIELD_TAG1, helper::AREA_COMPETENCY);
        $this->assertNotNull($field);
        $fieldid = (int) $field->get('id');
        $config = json_decode((string) $DB->get_field('customfield_field', 'configdata', ['id' => $fieldid]), true);
        $config['options'] = "Alpha\n\nBravo";
        $DB->set_field('customfield_field', 'configdata', json_encode($config), ['id' => $fieldid]);
        competency_handler::create()->reset_configuration_cache();

        // Precondition: core's own select lists Bravo at index 2.
        $field = helper::find_field_by_shortname(constants::CFIELD_TAG1, helper::AREA_COMPETENCY);
        $this->assertSame(['', 'Alpha', 'Bravo'], array_values($field->get_options()));

        $cgen = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $cgen->create_framework();
        $competencyid = (int) $cgen->create_competency(['competencyframeworkid' => $framework->get('id')])->get('id');
        competency_handler::create()->instance_form_save(
            (object) ['id' => $competencyid, 'customfield_' . constants::CFIELD_TAG1 => 2],
            false
        );
        competency_metadata_cache::purge_all();

        $single = competency_metadata_cache::get_competency_metadata($competencyid);
        competency_metadata_cache::purge_all();
        $batch = competency_metadata_cache::get_many([$competencyid])[$competencyid];

        $this->assertSame('Bravo', $single['tag1']);
        $this->assertSame('Bravo', $batch['tag1']);
    }
}
