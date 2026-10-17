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

use core_competency\course_competency;
use core_competency\course_competency_settings;
use core_competency\course_module_competency;
use core_reportbuilder\local\filters\{boolean_select, select};
use core_reportbuilder\manager;
use core_reportbuilder\tests\core_reportbuilder_testcase;
use local_dimensions\constants;
use local_dimensions\customfield\competency_handler;
use local_dimensions\helper;
use local_dimensions\reportbuilder\local\entities\{course_competency as course_competency_entity, module_competency};

/**
 * Tests for the course and activity competencies report source.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\reportbuilder\datasource\course_competencies
 * @covers     \local_dimensions\reportbuilder\local\entities\course_competency
 * @covers     \local_dimensions\reportbuilder\local\entities\module_competency
 */
final class course_competencies_test extends core_reportbuilder_testcase {
    /**
     * One competency in two courses, linked to one live activity of the first course.
     *
     * Course A holds the competency with "Complete" and follows the site's "push ratings" default
     * (on); its page "Reading" holds it with "Recommend" and "Override". Course B holds it with
     * "Evidence" and switches "push ratings" off. A second activity of course A, pending deletion,
     * holds it too and must not show.
     *
     * @return void
     */
    private function create_fixture(): void {
        global $DB;
        $this->setAdminUser();
        set_config('pushcourseratingstouserplans', 1, 'core_competency');
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);

        /** @var \core_competency_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework(['shortname' => 'Framework']);
        $competency = $generator->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'shortname' => 'Teamwork',
        ]);
        competency_handler::create()->instance_form_save((object) [
            'id' => $competency->get('id'),
            'customfield_' . constants::CFIELD_TYPE => 1,
        ], true);

        $coursea = $this->getDataGenerator()->create_course(['fullname' => 'Course A']);
        $courseb = $this->getDataGenerator()->create_course(['fullname' => 'Course B']);
        $generator->create_course_competency([
            'courseid' => $coursea->id,
            'competencyid' => $competency->get('id'),
            'ruleoutcome' => course_competency::OUTCOME_COMPLETE,
        ]);
        $generator->create_course_competency([
            'courseid' => $courseb->id,
            'competencyid' => $competency->get('id'),
            'ruleoutcome' => course_competency::OUTCOME_EVIDENCE,
        ]);
        (new course_competency_settings(0, (object) [
            'courseid' => $courseb->id,
            'pushratingstouserplans' => 0,
        ]))->create();

        $page = $this->getDataGenerator()->create_module('page', ['course' => $coursea->id, 'name' => 'Reading']);
        $generator->create_course_module_competency([
            'cmid' => $page->cmid,
            'competencyid' => $competency->get('id'),
            'ruleoutcome' => course_module_competency::OUTCOME_RECOMMEND,
            'overridegrade' => 1,
        ]);
        $retired = $this->getDataGenerator()->create_module('page', ['course' => $coursea->id, 'name' => 'Retired']);
        $generator->create_course_module_competency([
            'cmid' => $retired->cmid,
            'competencyid' => $competency->get('id'),
        ]);
        $DB->set_field('course_modules', 'deletioninprogress', 1, ['id' => $retired->cmid]);
        rebuild_course_cache((int) $coursea->id, true);
    }

    /**
     * A report of this source with the given columns, sorted by course.
     *
     * @param string[] $columns Column identifiers, the first sorted ascending.
     * @return int The report id.
     */
    private function create_report(array $columns): int {
        /** @var \core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report(['name' => 'Courses', 'source' => course_competencies::class, 'default' => 0]);
        foreach ($columns as $index => $column) {
            $generator->create_column([
                'reportid' => $report->get('id'),
                'uniqueidentifier' => $column,
                'sortenabled' => (int) ($index === 0),
            ]);
        }
        return (int) $report->get('id');
    }

    /**
     * Each course link is a row; the activity columns fill only on the course that holds the activity.
     *
     * @return void
     */
    public function test_course_and_activity_content(): void {
        $this->resetAfterTest();
        $this->create_fixture();

        $reportid = $this->create_report([
            'course:fullname',
            'competency:name',
            'framework:name',
            'competency_extras:customfield_' . constants::CFIELD_TYPE,
            'course_competency:ruleoutcome',
            'course_competency:pushratingstouserplans',
            'module_competency:activityname',
            'module_competency:activitytype',
            'module_competency:ruleoutcome',
            'module_competency:overridegrade',
        ]);

        // Same, not equal: a Yes/No column that lost its formatting returns true, which equals 'Yes'.
        $type = trim(explode("\n", get_string('type_options', 'local_dimensions'))[0]);
        $this->assertSame([
            [
                'Course A', 'Teamwork', 'Framework', $type,
                get_string('coursecompetencyoutcome_complete', 'core_competency'), get_string('yes'),
                'Reading', get_string('modulename', 'mod_page'),
                get_string('coursecompetencyoutcome_recommend', 'core_competency'), get_string('yes'),
            ],
            [
                'Course B', 'Teamwork', 'Framework', $type,
                get_string('coursecompetencyoutcome_evidence', 'core_competency'), get_string('no'),
                '', '', '', '',
            ],
        ], array_map('array_values', $this->get_custom_report_content($reportid)));
    }

    /**
     * A new report shows the course, the competency and both rules.
     *
     * @return void
     */
    public function test_default_report(): void {
        $this->resetAfterTest();
        $this->create_fixture();

        /** @var \core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report(['name' => 'Courses', 'source' => course_competencies::class]);

        $this->assertSame([
            ['Course A', 'Teamwork', get_string('coursecompetencyoutcome_complete', 'core_competency'), 'Reading',
                get_string('coursecompetencyoutcome_recommend', 'core_competency')],
            ['Course B', 'Teamwork', get_string('coursecompetencyoutcome_evidence', 'core_competency'), '', ''],
        ], array_map('array_values', $this->get_custom_report_content($report->get('id'))));
    }

    /**
     * Conditions on the plugin's entities and the courses they keep.
     *
     * @return array[]
     */
    public static function condition_provider(): array {
        return [
            'Course rule' => [
                'course_competency:ruleoutcome', select::EQUAL_TO, course_competency::OUTCOME_COMPLETE, ['Course A'],
            ],
            'Push ratings, site default' => [
                'course_competency:pushratingstouserplans', boolean_select::CHECKED, null, ['Course A'],
            ],
            'Activity type' => ['module_competency:activitytype', select::EQUAL_TO, 'page', ['Course A']],
            'Activity rule' => [
                'module_competency:ruleoutcome', select::EQUAL_TO, course_module_competency::OUTCOME_RECOMMEND, ['Course A'],
            ],
            'Override grade' => ['module_competency:overridegrade', boolean_select::CHECKED, null, ['Course A']],
        ];
    }

    /**
     * A condition narrows the rows to the courses that match it.
     *
     * @param string $condition Condition identifier.
     * @param int $operator Filter operator.
     * @param int|string|null $value Filter value, null for a boolean filter.
     * @param string[] $expected Course names left.
     * @return void
     * @dataProvider condition_provider
     */
    public function test_condition(string $condition, int $operator, int|string|null $value, array $expected): void {
        $this->resetAfterTest();
        $this->create_fixture();

        $reportid = $this->create_report(['course:fullname']);
        /** @var \core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $generator->create_condition(['reportid' => $reportid, 'uniqueidentifier' => $condition]);

        $values = ["{$condition}_operator" => $operator];
        if ($value !== null) {
            $values["{$condition}_value"] = $value;
        }
        manager::get_report_from_persistent(new \core_reportbuilder\local\models\report($reportid))
            ->set_condition_values($values);

        $content = $this->get_custom_report_content($reportid);
        $this->assertSame($expected, array_map(static fn(array $row): string => reset($row), $content));
    }

    /**
     * The source is offered only while competencies are enabled.
     *
     * @return void
     */
    public function test_is_available(): void {
        $this->resetAfterTest();

        set_config('enabled', 1, 'core_competency');
        $this->assertTrue(course_competencies::is_available());

        set_config('enabled', 0, 'core_competency');
        $this->assertFalse(course_competencies::is_available());
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

        $this->datasource_stress_test_columns(course_competencies::class);
        $this->datasource_stress_test_columns_aggregation(course_competencies::class);
        $this->datasource_stress_test_conditions(course_competencies::class, 'competency:name');
    }
}
