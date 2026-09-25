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

namespace local_dimensions\local;

use core_competency\plan;

/**
 * Tests for the plan status labels.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\local\plan_status
 */
final class plan_status_test extends \basic_testcase {
    /**
     * Each status reads core's own label for it, and an unknown status reads nothing.
     *
     * @return void
     */
    public function test_each_status_reads_cores_label(): void {
        $expected = [
            plan::STATUS_DRAFT => get_string('planstatusdraft', 'core_competency'),
            plan::STATUS_ACTIVE => get_string('planstatusactive', 'core_competency'),
            plan::STATUS_COMPLETE => get_string('planstatuscomplete', 'core_competency'),
            plan::STATUS_WAITING_FOR_REVIEW => get_string('planstatuswaitingforreview', 'core_competency'),
            plan::STATUS_IN_REVIEW => get_string('planstatusinreview', 'core_competency'),
        ];
        foreach ($expected as $status => $label) {
            $this->assertSame($label, plan_status::label($status));
        }
        $this->assertSame('', plan_status::label(-1));
    }

    /**
     * Every string id in the class is a literal, which is what lets the string checker verify it.
     *
     * @return void
     */
    public function test_every_string_id_is_a_literal(): void {
        global $CFG;

        $source = file_get_contents($CFG->dirroot . '/local/dimensions/classes/local/plan_status.php');
        preg_match_all('/get_string\(\s*([^,)]*)/', $source, $matches);

        $this->assertCount(5, $matches[1]);
        foreach ($matches[1] as $argument) {
            $this->assertMatchesRegularExpression("/^'[a-z]+'$/", trim($argument));
        }
    }
}
