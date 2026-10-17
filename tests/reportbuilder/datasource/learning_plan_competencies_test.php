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

use core_competency\plan;
use core_reportbuilder\tests\core_reportbuilder_testcase;
use local_dimensions\reportbuilder\local\helpers\rating;

/**
 * Tests for the learning plan competencies report source.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\reportbuilder\datasource\learning_plan_competencies
 * @covers     \local_dimensions\reportbuilder\local\entities\plan_competency
 */
final class learning_plan_competencies_test extends core_reportbuilder_testcase {
    /**
     * Two plans of one learner, rated on the framework's A-D scale.
     *
     * "Active plan" follows a template with Alpha and Beta; Alpha is rated B and proficient.
     * "Done plan" has no template and holds Gamma itself; it is complete, its archive rates Gamma
     * D and proficient, while the learner's current Gamma is A and not proficient.
     *
     * @return int The report id, sorted by plan then competency.
     */
    private function create_fixture(): int {
        $this->setAdminUser();
        rating::reset();

        /** @var \core_competency_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework(['shortname' => 'Framework']);
        $competencies = [];
        foreach (['Alpha', 'Beta', 'Gamma'] as $name) {
            $competencies[$name] = $generator->create_competency([
                'competencyframeworkid' => $framework->get('id'),
                'shortname' => $name,
            ]);
        }
        $learner = $this->getDataGenerator()->create_user();

        $template = $generator->create_template(['shortname' => 'Template']);
        foreach (['Alpha', 'Beta'] as $name) {
            $generator->create_template_competency([
                'templateid' => $template->get('id'),
                'competencyid' => $competencies[$name]->get('id'),
            ]);
        }
        $generator->create_plan([
            'name' => 'Active plan',
            'userid' => $learner->id,
            'templateid' => $template->get('id'),
            'status' => plan::STATUS_ACTIVE,
        ]);
        $generator->create_user_competency([
            'userid' => $learner->id,
            'competencyid' => $competencies['Alpha']->get('id'),
            'grade' => 2,
            'proficiency' => true,
        ]);

        $done = $generator->create_plan(['name' => 'Done plan', 'userid' => $learner->id, 'status' => plan::STATUS_COMPLETE]);
        $generator->create_plan_competency(['planid' => $done->get('id'), 'competencyid' => $competencies['Gamma']->get('id')]);
        $generator->create_user_competency_plan([
            'userid' => $learner->id,
            'competencyid' => $competencies['Gamma']->get('id'),
            'planid' => $done->get('id'),
            'grade' => 4,
            'proficiency' => true,
        ]);
        $generator->create_user_competency([
            'userid' => $learner->id,
            'competencyid' => $competencies['Gamma']->get('id'),
            'grade' => 1,
            'proficiency' => false,
        ]);

        /** @var \core_reportbuilder_generator $rbgenerator */
        $rbgenerator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $rbgenerator->create_report([
            'name' => 'Plan competencies',
            'source' => learning_plan_competencies::class,
            'default' => 0,
        ]);
        $columns = [
            'plan:name',
            'competency:name',
            'plan_competency:fromtemplate',
            'plan_competency:proficient',
            'plan_competency:rating',
            'usercompetency:rating',
        ];
        foreach ($columns as $index => $column) {
            $rbgenerator->create_column([
                'reportid' => $report->get('id'),
                'uniqueidentifier' => $column,
                'sortenabled' => (int) ($index < 2),
            ]);
        }
        return (int) $report->get('id');
    }

    /**
     * Template competencies and a plan's own both show; a completed plan reads its archive.
     *
     * @return void
     */
    public function test_content(): void {
        $this->resetAfterTest();
        $reportid = $this->create_fixture();

        $this->assertEquals([
            ['Active plan', 'Alpha', 'Yes', 'Yes', 'B', 'B'],
            ['Active plan', 'Beta', 'Yes', '', '', ''],
            ['Done plan', 'Gamma', 'No', 'Yes', 'D', 'A'],
        ], array_map('array_values', $this->get_custom_report_content($reportid)));
    }

    /**
     * The default report builds and runs.
     *
     * @return void
     */
    public function test_default_report(): void {
        $this->resetAfterTest();
        $this->create_fixture();

        /** @var \core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report([
            'name' => 'Default',
            'source' => learning_plan_competencies::class,
            'default' => 1,
        ]);
        $this->assertCount(3, $this->get_custom_report_content($report->get('id')));
    }

    /**
     * The source is offered only while competencies are enabled.
     *
     * @return void
     */
    public function test_is_available(): void {
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $this->assertTrue(learning_plan_competencies::is_available());
        set_config('enabled', 0, 'core_competency');
        $this->assertFalse(learning_plan_competencies::is_available());
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
        $this->create_fixture();

        $this->datasource_stress_test_columns(learning_plan_competencies::class);
        $this->datasource_stress_test_columns_aggregation(learning_plan_competencies::class);
        $this->datasource_stress_test_conditions(learning_plan_competencies::class, 'plan:name');
    }
}
