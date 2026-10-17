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

use core_reportbuilder\tests\core_reportbuilder_testcase;
use local_dimensions\reportbuilder\local\helpers\rating;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the competency ratings in courses report source.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(course_competency_ratings::class)]
#[CoversClass(\local_dimensions\reportbuilder\local\entities\usercompetency_course::class)]
final class course_competency_ratings_test extends core_reportbuilder_testcase {
    /**
     * One competency rated in two courses: C and proficient for Lea in course A, A and not
     * proficient for Tom in course B. Lea belongs to the cohort "Group".
     *
     * @return int The report id, sorted by course.
     */
    private function create_fixture(): int {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');
        $this->setAdminUser();
        rating::reset();

        /** @var \core_competency_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework(['shortname' => 'Framework']);
        $competency = $generator->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'shortname' => 'Teamwork',
        ]);
        $lea = $this->getDataGenerator()->create_user(['firstname' => 'Lea', 'lastname' => 'Learner']);
        $tom = $this->getDataGenerator()->create_user(['firstname' => 'Tom', 'lastname' => 'Trainee']);
        $cohort = $this->getDataGenerator()->create_cohort(['name' => 'Group']);
        cohort_add_member($cohort->id, $lea->id);
        $coursea = $this->getDataGenerator()->create_course(['fullname' => 'Course A']);
        $courseb = $this->getDataGenerator()->create_course(['fullname' => 'Course B']);
        $generator->create_user_competency_course([
            'userid' => $lea->id,
            'competencyid' => $competency->get('id'),
            'courseid' => $coursea->id,
            'grade' => 3,
            'proficiency' => true,
        ]);
        $generator->create_user_competency_course([
            'userid' => $tom->id,
            'competencyid' => $competency->get('id'),
            'courseid' => $courseb->id,
            'grade' => 1,
            'proficiency' => false,
        ]);

        /** @var \core_reportbuilder_generator $rbgenerator */
        $rbgenerator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $rbgenerator->create_report([
            'name' => 'Ratings',
            'source' => course_competency_ratings::class,
            'default' => 0,
        ]);
        $columns = [
            'course:fullname',
            'user:fullname',
            'competency:name',
            'usercompetency_course:rating',
            'usercompetency_course:proficient',
            'usercohort:name',
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
     * Each rating in a course is a row, named from the competency's scale.
     *
     * @return void
     */
    public function test_content(): void {
        $this->resetAfterTest();
        $reportid = $this->create_fixture();

        $this->assertEquals([
            ['Course A', 'Lea Learner', 'Teamwork', 'C', 'Yes', 'Group'],
            ['Course B', 'Tom Trainee', 'Teamwork', 'A', 'No', ''],
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
        $report = $generator->create_report(['name' => 'Default', 'source' => course_competency_ratings::class, 'default' => 1]);
        $this->assertCount(2, $this->get_custom_report_content($report->get('id')));
    }

    /**
     * The source is offered only while competencies are enabled.
     *
     * @return void
     */
    public function test_is_available(): void {
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $this->assertTrue(course_competency_ratings::is_available());
        set_config('enabled', 0, 'core_competency');
        $this->assertFalse(course_competency_ratings::is_available());
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

        $this->datasource_stress_test_columns(course_competency_ratings::class);
        $this->datasource_stress_test_columns_aggregation(course_competency_ratings::class);
        $this->datasource_stress_test_conditions(course_competency_ratings::class, 'competency:name');
    }
}
