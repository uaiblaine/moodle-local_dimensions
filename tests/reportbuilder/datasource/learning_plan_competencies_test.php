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

use core_competency\api;
use core_competency\plan;
use core_reportbuilder\tests\core_reportbuilder_testcase;
use local_dimensions\reportbuilder\local\helpers\rating;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the learning plan competencies report source.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(learning_plan_competencies::class)]
#[CoversClass(\local_dimensions\reportbuilder\local\entities\plan_competency::class)]
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
     * Two plans from one template, one completed before the template changed: Alpha and Beta were
     * its competencies at completion; afterwards Alpha left and Delta joined.
     *
     * @return int The report id, with the plan and competency names sorted.
     */
    private function create_template_change_fixture(): int {
        $this->setAdminUser();

        /** @var \core_competency_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework(['shortname' => 'Framework']);
        $competencies = [];
        foreach (['Alpha', 'Beta', 'Delta'] as $name) {
            $competencies[$name] = $generator->create_competency([
                'competencyframeworkid' => $framework->get('id'),
                'shortname' => $name,
            ]);
        }
        $template = $generator->create_template(['shortname' => 'Template']);
        foreach (['Alpha', 'Beta'] as $name) {
            $generator->create_template_competency([
                'templateid' => $template->get('id'),
                'competencyid' => $competencies[$name]->get('id'),
            ]);
        }
        $completed = $generator->create_plan([
            'name' => 'Completed plan',
            'userid' => $this->getDataGenerator()->create_user()->id,
            'templateid' => $template->get('id'),
            'status' => plan::STATUS_ACTIVE,
        ]);
        $generator->create_plan([
            'name' => 'Live plan',
            'userid' => $this->getDataGenerator()->create_user()->id,
            'templateid' => $template->get('id'),
            'status' => plan::STATUS_ACTIVE,
        ]);

        // Completion archives Alpha and Beta; the template then changes.
        api::complete_plan($completed);
        api::remove_competency_from_template($template->get('id'), $competencies['Alpha']->get('id'));
        api::add_competency_to_template($template->get('id'), $competencies['Delta']->get('id'));

        /** @var \core_reportbuilder_generator $rbgenerator */
        $rbgenerator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $rbgenerator->create_report([
            'name' => 'Template change',
            'source' => learning_plan_competencies::class,
            'default' => 0,
        ]);
        foreach (['plan:name', 'competency:name'] as $column) {
            $rbgenerator->create_column([
                'reportid' => $report->get('id'),
                'uniqueidentifier' => $column,
                'sortenabled' => 1,
            ]);
        }
        return (int) $report->get('id');
    }

    /**
     * A completed template-based plan lists the competencies archived at completion, not the template's current ones.
     *
     * @return void
     */
    public function test_completed_template_plan_lists_the_archived_competencies(): void {
        $this->resetAfterTest();
        $reportid = $this->create_template_change_fixture();

        $rows = array_map('array_values', $this->get_custom_report_content($reportid));
        $this->assertSame(
            [['Completed plan', 'Alpha'], ['Completed plan', 'Beta']],
            array_values(array_filter($rows, static fn(array $row): bool => $row[0] === 'Completed plan'))
        );
    }

    /**
     * Control: an active plan from the same template follows the template's current competencies.
     *
     * @return void
     */
    public function test_active_template_plan_follows_the_current_template(): void {
        $this->resetAfterTest();
        $reportid = $this->create_template_change_fixture();

        $rows = array_map('array_values', $this->get_custom_report_content($reportid));
        $this->assertSame(
            [['Live plan', 'Beta'], ['Live plan', 'Delta']],
            array_values(array_filter($rows, static fn(array $row): bool => $row[0] === 'Live plan'))
        );
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
