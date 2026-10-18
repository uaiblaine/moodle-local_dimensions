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

use core\context\{course as context_course, module as context_module};
use core_competency\evidence;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\manager;
use core_reportbuilder\tests\core_reportbuilder_testcase;
use local_dimensions\reportbuilder\local\helpers\rating;

/**
 * Tests for the competency evidence report source.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\reportbuilder\datasource\competency_evidence
 * @covers     \local_dimensions\reportbuilder\local\entities\evidence
 */
final class competency_evidence_test extends core_reportbuilder_testcase {
    /**
     * A learner's competency with two evidences in one course: the course completion, logged from
     * the course's page, and a rating a teacher set in the course, with a note.
     *
     * @return int The report id, sorted by action, with the columns the tests read.
     */
    private function create_fixture(): int {
        $this->setAdminUser();
        rating::reset();

        /** @var \core_competency_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework(['shortname' => 'Framework']);
        $competency = $generator->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'shortname' => 'Teamwork',
        ]);
        $learner = $this->getDataGenerator()->create_user(['firstname' => 'Lea', 'lastname' => 'Learner']);
        $teacher = $this->getDataGenerator()->create_user(['firstname' => 'Tom', 'lastname' => 'Teacher']);
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Course A']);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'name' => 'Reading']);
        $usercompetency = $generator->create_user_competency([
            'competencyid' => $competency->get('id'),
            'userid' => $learner->id,
        ]);

        $generator->create_evidence([
            'usercompetencyid' => $usercompetency->get('id'),
            'action' => evidence::ACTION_LOG,
            'contextid' => context_module::instance($page->cmid)->id,
            'descidentifier' => 'evidence_coursecompleted',
            'desca' => json_encode('Course A'),
        ]);
        $generator->create_evidence([
            'usercompetencyid' => $usercompetency->get('id'),
            'action' => evidence::ACTION_OVERRIDE,
            'grade' => 3,
            'contextid' => context_course::instance($course->id)->id,
            'descidentifier' => 'evidence_manualoverrideincourse',
            'desca' => json_encode('Course A'),
            'actionuserid' => $teacher->id,
            'note' => 'Well done',
        ]);

        /** @var \core_reportbuilder_generator $rbgenerator */
        $rbgenerator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $rbgenerator->create_report(['name' => 'Evidence', 'source' => competency_evidence::class, 'default' => 0]);
        $columns = [
            'evidence:action',
            'user:fullname',
            'competency:name',
            'evidence:description',
            'evidence:rating',
            'course:fullname',
            'evidenceauthor:fullname',
            'evidence:note',
        ];
        foreach ($columns as $index => $column) {
            $rbgenerator->create_column([
                'reportid' => $report->get('id'),
                'uniqueidentifier' => $column,
                'sortenabled' => (int) ($index === 0),
            ]);
        }
        return (int) $report->get('id');
    }

    /**
     * Each evidence is a row, with its course found from a course or an activity context.
     *
     * @return void
     */
    public function test_content(): void {
        $this->resetAfterTest();
        $reportid = $this->create_fixture();

        $this->assertEquals([
            ['Log', 'Lea Learner', 'Teamwork', "The course 'Course A' was completed.", '', 'Course A', '', ''],
            [
                'Override the rating',
                'Lea Learner',
                'Teamwork',
                "The competency rating was manually set in the course 'Course A'.",
                'C',
                'Course A',
                'Tom Teacher',
                'Well done',
            ],
        ], array_map('array_values', $this->get_custom_report_content($reportid)));
    }

    /**
     * Stored text reaches the report as escaped HTML: the description's arguments and the rating's scale item.
     *
     * @return void
     */
    public function test_stored_text_is_escaped(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        rating::reset();

        /** @var \core_competency_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $scale = $this->getDataGenerator()->create_scale(['scale' => 'Low,Mid <img src=x onerror=alert(1)> & Q,High']);
        $framework = $generator->create_framework([
            'shortname' => 'Framework',
            'scaleid' => $scale->id,
            'scaleconfiguration' => json_encode([
                ['scaleid' => $scale->id],
                ['id' => 1, 'scaledefault' => 1, 'proficient' => 0],
                ['id' => 3, 'scaledefault' => 0, 'proficient' => 1],
            ]),
        ]);
        $competency = $generator->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $learner = $this->getDataGenerator()->create_user();
        $usercompetency = $generator->create_user_competency([
            'competencyid' => $competency->get('id'),
            'userid' => $learner->id,
        ]);
        $generator->create_evidence([
            'usercompetencyid' => $usercompetency->get('id'),
            'action' => evidence::ACTION_OVERRIDE,
            'grade' => 2,
            'contextid' => \context_system::instance()->id,
            'descidentifier' => 'evidence_coursecompleted',
            'desca' => json_encode('<b>x</b> & <i>y</i>'),
        ]);

        /** @var \core_reportbuilder_generator $rbgenerator */
        $rbgenerator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $rbgenerator->create_report(['name' => 'Evidence', 'source' => competency_evidence::class, 'default' => 0]);
        foreach (['evidence:description', 'evidence:rating'] as $column) {
            $rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => $column]);
        }

        $row = array_values($this->get_custom_report_content((int) $report->get('id'))[0]);
        $this->assertSame("The course 'x &amp; y' was completed.", $row[0]);
        $this->assertStringNotContainsString('<img', $row[1]);
        $this->assertSame('Mid  &amp; Q', $row[1]);
    }

    /**
     * The action condition keeps the evidences of that action.
     *
     * @return void
     */
    public function test_action_condition(): void {
        $this->resetAfterTest();
        $reportid = $this->create_fixture();

        /** @var \core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $generator->create_condition(['reportid' => $reportid, 'uniqueidentifier' => 'evidence:action']);
        manager::get_report_from_persistent(new \core_reportbuilder\local\models\report($reportid))->set_condition_values([
            'evidence:action_operator' => select::EQUAL_TO,
            'evidence:action_value' => evidence::ACTION_OVERRIDE,
        ]);

        $content = $this->get_custom_report_content($reportid);
        $this->assertCount(1, $content);
        $this->assertSame('Override the rating', array_values($content[0])[0]);
    }

    /**
     * The source is offered only while competencies are enabled.
     *
     * @return void
     */
    public function test_is_available(): void {
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $this->assertTrue(competency_evidence::is_available());
        set_config('enabled', 0, 'core_competency');
        $this->assertFalse(competency_evidence::is_available());
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

        $this->datasource_stress_test_columns(competency_evidence::class);
        $this->datasource_stress_test_columns_aggregation(competency_evidence::class);
        $this->datasource_stress_test_conditions(competency_evidence::class, 'evidence:note');
    }
}
