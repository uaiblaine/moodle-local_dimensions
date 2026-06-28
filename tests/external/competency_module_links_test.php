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

namespace local_dimensions\external;

/**
 * Tests for the competency activity-link external functions.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\external\get_competency_module_links
 * @covers     \local_dimensions\external\link_competency_module
 * @covers     \local_dimensions\external\unlink_competency_module
 * @covers     \local_dimensions\external\set_module_link_outcome
 */
final class competency_module_links_test extends \advanced_testcase {
    /**
     * Create a visible competency, a course linked to it, and two activities.
     *
     * @return array [int $competencyid, int $courseid, int $cmid1, int $cmid2]
     */
    private function fixture(): array {
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework(['visible' => 1]);
        $competency = $ccg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $competencyid = (int) $competency->get('id');
        $course = $this->getDataGenerator()->create_course();
        link_competency_course::execute($competencyid, (int) $course->id);
        $a1 = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $a2 = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        return [$competencyid, (int) $course->id, (int) $a1->cmid, (int) $a2->cmid];
    }

    /**
     * The read splits linked vs available activities.
     *
     * @return void
     */
    public function test_split_linked_available(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$competencyid, $courseid, $cmid1] = $this->fixture();

        link_competency_module::execute($competencyid, $cmid1);

        $result = get_competency_module_links::execute($competencyid, $courseid);
        $linkedids = array_map(static fn($m): int => (int) $m['cmid'], $result['linked']);
        $availableids = array_map(static fn($m): int => (int) $m['cmid'], $result['available']);
        $this->assertContains($cmid1, $linkedids);
        $this->assertNotContains($cmid1, $availableids);
        $this->assertSame(1, (int) $result['canmanage']);
    }

    /**
     * Link / set outcome / unlink an activity round-trips.
     *
     * @return void
     */
    public function test_link_set_outcome_unlink_module(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$competencyid, , $cmid1] = $this->fixture();

        $added = link_competency_module::execute($competencyid, $cmid1);
        $this->assertSame($cmid1, (int) $added['cmid']);
        $this->assertSame(1, $DB->count_records('competency_modulecomp',
            ['competencyid' => $competencyid, 'cmid' => $cmid1]));

        $outcome = \core_competency\course_module_competency::OUTCOME_RECOMMEND;
        $this->assertTrue(set_module_link_outcome::execute($competencyid, $cmid1, $outcome)['success']);
        $record = $DB->get_record('competency_modulecomp', ['competencyid' => $competencyid, 'cmid' => $cmid1]);
        $this->assertSame($outcome, (int) $record->ruleoutcome);

        $this->assertTrue(unlink_competency_module::execute($competencyid, $cmid1)['success']);
        $this->assertSame(0, $DB->count_records('competency_modulecomp',
            ['competencyid' => $competencyid, 'cmid' => $cmid1]));
    }
}
