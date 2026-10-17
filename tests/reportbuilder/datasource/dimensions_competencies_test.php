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

use core_reportbuilder\local\filters\number;
use core_reportbuilder\manager;
use core_reportbuilder\tests\core_reportbuilder_testcase;
use local_dimensions\constants;
use local_dimensions\helper;
use local_dimensions\reportbuilder\local\helpers\rating;

/**
 * Tests for the competencies report source with the Dimensions custom fields.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\reportbuilder\datasource\dimensions_competencies
 * @covers     \local_dimensions\reportbuilder\local\entities\competency_extras
 * @covers     \local_dimensions\reportbuilder\local\entities\usercompetency
 * @covers     \local_dimensions\reportbuilder\local\helpers\rating
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
     * Competencies rated on two scales: the framework's (A-D) and one competency's own (Low-High).
     *
     * Each competency has `$users` rated users, all with the second item of its scale.
     *
     * @param int $users Rated users per competency.
     * @return int The report id, with competency name, user name and rating columns.
     */
    private function create_rated_report(int $users): int {
        $this->setAdminUser();
        rating::reset();

        /** @var \core_competency_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework(['shortname' => 'Framework']);
        $own = $this->getDataGenerator()->create_scale(['scale' => 'Low,High']);
        $competencies = [
            $generator->create_competency(['competencyframeworkid' => $framework->get('id'), 'shortname' => 'Framework scale']),
            $generator->create_competency([
                'competencyframeworkid' => $framework->get('id'),
                'shortname' => 'Own scale',
                'scaleid' => $own->id,
                'scaleconfiguration' => json_encode([['scaleid' => $own->id], ['id' => 2, 'scaledefault' => 1, 'proficient' => 1]]),
            ]),
        ];
        for ($i = 0; $i < $users; $i++) {
            $user = $this->getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => sprintf('%02d', $i)]);
            foreach ($competencies as $competency) {
                $generator->create_user_competency([
                    'competencyid' => $competency->get('id'),
                    'userid' => $user->id,
                    'proficiency' => true,
                    'grade' => 2,
                ]);
            }
        }

        /** @var \core_reportbuilder_generator $rbgenerator */
        $rbgenerator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $rbgenerator->create_report(['name' => 'Ratings', 'source' => dimensions_competencies::class, 'default' => 0]);
        foreach (['competency:name', 'user:lastname', 'usercompetency:rating'] as $index => $column) {
            $rbgenerator->create_column([
                'reportid' => $report->get('id'),
                'uniqueidentifier' => $column,
                'sortenabled' => (int) ($index < 2),
            ]);
        }
        return (int) $report->get('id');
    }

    /**
     * The rating names the item of the competency's own scale, or else of the framework's.
     *
     * @return void
     */
    public function test_rating_names_the_scale_item(): void {
        $this->resetAfterTest();
        $reportid = $this->create_rated_report(1);

        $this->assertEquals([
            ['Framework scale', '00', 'B'],
            ['Own scale', '00', 'High'],
        ], array_map('array_values', $this->get_custom_report_content($reportid)));
    }

    /**
     * Naming the ratings costs no query per row: a page of 20 rows reads what a page of 2 reads.
     *
     * Core's rating column reads the competency and its scale for every row; the plugin's reads
     * each scale once per request, so with the scales read, more rows add no reads.
     *
     * @return void
     */
    public function test_rating_reads_do_not_grow_with_rows(): void {
        global $DB;
        $this->resetAfterTest();
        $reportid = $this->create_rated_report(10);
        $this->assertCount(20, $this->get_custom_report_content($reportid, 20));

        $reads = $DB->perf_get_reads();
        $this->assertCount(2, $this->get_custom_report_content($reportid, 2));
        $tworeads = $DB->perf_get_reads() - $reads;

        $reads = $DB->perf_get_reads();
        $this->assertCount(20, $this->get_custom_report_content($reportid, 20));
        $twentyreads = $DB->perf_get_reads() - $reads;

        $this->assertSame($tworeads, $twentyreads);
    }

    /**
     * The rating condition keeps the rows whose rating sits at the given position.
     *
     * @return void
     */
    public function test_rating_condition(): void {
        $this->resetAfterTest();
        $reportid = $this->create_rated_report(1);

        /** @var \core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $generator->create_condition(['reportid' => $reportid, 'uniqueidentifier' => 'usercompetency:rating']);
        $source = manager::get_report_from_persistent(new \core_reportbuilder\local\models\report($reportid));

        $source->set_condition_values(['usercompetency:rating_operator' => number::EQUAL_TO, 'usercompetency:rating_value1' => 2]);
        $this->assertCount(2, $this->get_custom_report_content($reportid));

        $source->set_condition_values(['usercompetency:rating_operator' => number::EQUAL_TO, 'usercompetency:rating_value1' => 1]);
        $this->assertCount(0, $this->get_custom_report_content($reportid));
    }

    /**
     * A new report starts with the competency's Dimensions type and Tag 1.
     *
     * @return void
     */
    public function test_default_columns(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);

        /** @var \core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report(['name' => 'Default', 'source' => dimensions_competencies::class, 'default' => 1]);

        $columns = array_values($DB->get_records_menu(
            'reportbuilder_column',
            ['reportid' => $report->get('id')],
            'columnorder',
            'id, uniqueidentifier'
        ));
        $this->assertSame([
            'framework:name',
            'competency:name',
            'competency_extras:customfield_' . constants::CFIELD_TYPE,
            'competency_extras:customfield_' . constants::CFIELD_TAG1,
            'user:fullname',
            'usercompetency:proficient',
        ], $columns);
    }

    /**
     * The source is offered only while competencies are enabled.
     *
     * @return void
     */
    public function test_is_available(): void {
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $this->assertTrue(dimensions_competencies::is_available());
        set_config('enabled', 0, 'core_competency');
        $this->assertFalse(dimensions_competencies::is_available());
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
