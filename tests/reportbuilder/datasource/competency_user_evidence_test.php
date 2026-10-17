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

use core\context\user as context_user;
use core_competency\user_competency;
use core_reportbuilder\tests\core_reportbuilder_testcase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the evidence of prior learning report source.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(competency_user_evidence::class)]
#[CoversClass(\local_dimensions\reportbuilder\local\entities\user_evidence::class)]
final class competency_user_evidence_test extends core_reportbuilder_testcase {
    /**
     * One learner with two evidences of prior learning: a certificate with one file, linked to a
     * competency under review, and a draft linked to nothing.
     *
     * @return int The report id, sorted by evidence name.
     */
    private function create_fixture(): int {
        $this->setAdminUser();

        /** @var \core_competency_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework(['shortname' => 'Framework']);
        $competency = $generator->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'shortname' => 'Teamwork',
        ]);
        $learner = $this->getDataGenerator()->create_user(['firstname' => 'Lea', 'lastname' => 'Learner']);
        $generator->create_user_competency([
            'competencyid' => $competency->get('id'),
            'userid' => $learner->id,
            'status' => user_competency::STATUS_IN_REVIEW,
        ]);

        $certificate = $generator->create_user_evidence(['userid' => $learner->id, 'name' => 'Certificate']);
        $generator->create_user_evidence_competency([
            'userevidenceid' => $certificate->get('id'),
            'competencyid' => $competency->get('id'),
        ]);
        get_file_storage()->create_file_from_string([
            'contextid' => context_user::instance($learner->id)->id,
            'component' => 'core_competency',
            'filearea' => 'userevidence',
            'itemid' => $certificate->get('id'),
            'filepath' => '/',
            'filename' => 'certificate.pdf',
        ], 'pdf-bytes');
        $generator->create_user_evidence(['userid' => $learner->id, 'name' => 'Draft']);

        /** @var \core_reportbuilder_generator $rbgenerator */
        $rbgenerator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $rbgenerator->create_report([
            'name' => 'Prior learning',
            'source' => competency_user_evidence::class,
            'default' => 0,
        ]);
        $columns = ['user_evidence:name', 'user:fullname', 'user_evidence:files', 'competency:name', 'usercompetency:status'];
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
     * Each evidence and linked competency is a row; an unlinked evidence keeps one, empty, row.
     *
     * @return void
     */
    public function test_content(): void {
        $this->resetAfterTest();
        $reportid = $this->create_fixture();

        $this->assertEquals([
            [
                'Certificate',
                'Lea Learner',
                1,
                'Teamwork',
                (string) user_competency::get_status_name(user_competency::STATUS_IN_REVIEW),
            ],
            ['Draft', 'Lea Learner', 0, '', ''],
        ], array_map('array_values', $this->get_custom_report_content($reportid)));
    }

    /**
     * The source is offered only while competencies are enabled.
     *
     * @return void
     */
    public function test_is_available(): void {
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $this->assertTrue(competency_user_evidence::is_available());
        set_config('enabled', 0, 'core_competency');
        $this->assertFalse(competency_user_evidence::is_available());
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

        $this->datasource_stress_test_columns(competency_user_evidence::class);
        $this->datasource_stress_test_columns_aggregation(competency_user_evidence::class);
        $this->datasource_stress_test_conditions(competency_user_evidence::class, 'user_evidence:name');
    }
}
