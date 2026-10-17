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

declare(strict_types=1);

namespace local_dimensions\reportbuilder\datasource;

use core_reportbuilder\manager;
use core_reportbuilder\tests\core_reportbuilder_testcase;
use local_dimensions\constants;
use local_dimensions\helper;

/**
 * Tests for the competencies report source with the Dimensions custom fields.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\reportbuilder\datasource\dimensions_competencies
 * @covers     \local_dimensions\reportbuilder\local\entities\competency_extras
 */
final class dimensions_competencies_test extends core_reportbuilder_testcase {
    /**
     * A competency's tag reaches the report through `competency_extras`.
     *
     * @return void
     */
    public function test_competency_customfield_column(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);

        /** @var \core_competency_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework(['shortname' => 'Framework']);
        $competency = $generator->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'shortname' => 'Teamwork',
        ]);
        \local_dimensions\customfield\competency_handler::create()->instance_form_save((object) [
            'id' => $competency->get('id'),
            'customfield_' . constants::CFIELD_TYPE => 1,
        ], true);

        /** @var \core_reportbuilder_generator $rbgenerator */
        $rbgenerator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $rbgenerator->create_report([
            'name' => 'Competencies',
            'source' => dimensions_competencies::class,
            'default' => 0,
        ]);
        $rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'competency:name']);
        $rbgenerator->create_column([
            'reportid' => $report->get('id'),
            'uniqueidentifier' => 'competency_extras:customfield_' . constants::CFIELD_TYPE,
        ]);

        $options = explode("\n", get_string('type_options', 'local_dimensions'));
        $content = array_map('array_values', $this->get_custom_report_content($report->get('id')));
        $this->assertEquals([['Teamwork', trim($options[0])]], $content);
    }

    /**
     * The fields that only style or steer the learner pages are not offered; the others are.
     *
     * @return void
     */
    public function test_presentation_fields_are_not_offered(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);

        /** @var \core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report([
            'name' => 'Competencies',
            'source' => dimensions_competencies::class,
            'default' => 0,
        ]);
        $source = manager::get_report_from_persistent($report);

        foreach (constants::REPORT_HIDDEN_CFIELDS as $shortname) {
            $this->assertNull($source->get_column("competency_extras:customfield_{$shortname}"), $shortname);
        }
        foreach ([constants::CFIELD_TAG1, constants::CFIELD_TAG2, constants::CFIELD_TYPE] as $shortname) {
            $this->assertNotNull($source->get_column("competency_extras:customfield_{$shortname}"), $shortname);
            $this->assertNotNull($source->get_filter("competency_extras:customfield_{$shortname}"), $shortname);
        }
    }

    /**
     * Every column, aggregation and condition of the source builds a report that runs.
     *
     * @return void
     */
    public function test_stress_datasource(): void {
        if (!PHPUNIT_LONGTEST) {
            $this->markTestSkipped('PHPUNIT_LONGTEST is not defined');
        }
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);

        /** @var \core_competency_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework();
        $competency = $generator->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $generator->create_user_competency([
            'competencyid' => $competency->get('id'),
            'userid' => $this->getDataGenerator()->create_user()->id,
        ]);

        $this->datasource_stress_test_columns(dimensions_competencies::class);
        $this->datasource_stress_test_columns_aggregation(dimensions_competencies::class);
        $this->datasource_stress_test_conditions(dimensions_competencies::class, 'framework:idnumber');
    }
}
