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

use core_external\external_api;

/**
 * Tests for the tracker's batched completion + lock service.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\external\get_courses_completion_status
 */
final class get_courses_completion_status_test extends \advanced_testcase {
    /**
     * Run the service and clean the payload through the returns structure, keyed by course id.
     *
     * @param array $courseids The course ids to ask about.
     * @return array Course id => cleaned row.
     */
    private function cleaned_rows_for(array $courseids): array {
        $result = external_api::clean_returnvalue(
            get_courses_completion_status::execute_returns(),
            get_courses_completion_status::execute($courseids)
        );

        $rows = [];
        foreach ($result as $row) {
            $rows[(int) $row['courseid']] = $row;
        }

        return $rows;
    }

    /**
     * Link a fresh competency to the given courses, so the service will answer about them.
     *
     * See get_course_progress_test::link_competency(), which does the same for the sibling
     * service - the two share one gate and have to be set up the same way.
     *
     * @param int ...$courseids The courses to link.
     * @return void
     */
    private function link_competency(int ...$courseids): void {
        $this->setAdminUser();

        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework();
        $competency = $ccg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        foreach ($courseids as $courseid) {
            \core_competency\api::add_competency_to_course($courseid, (int) $competency->get('id'));
        }

        $this->setUser(null);
    }

    /**
     * An enrolled student on a linked course is reported unlocked and not yet complete.
     *
     * @return void
     */
    public function test_execute_reports_an_enrolled_student(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->link_competency((int) $course->id);
        $this->setUser($user);

        $rows = $this->cleaned_rows_for([(int) $course->id]);

        $this->assertFalse($rows[(int) $course->id]['islocked']);
        $this->assertFalse($rows[(int) $course->id]['iscompleted']);
    }

    /**
     * A hidden course answers "locked" without consulting it.
     *
     * What this service returns is only the caller's own booleans, so nothing structural leaks
     * either way - but it feeds the same card list as get_course_progress and has to answer
     * for exactly the same set of courses, or the tracker would prioritise a card the other
     * service then refuses to fill.
     *
     * @return void
     */
    public function test_execute_locks_a_hidden_course(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([
            'visible' => 0,
            'enablecompletion' => 1,
        ]);
        $this->link_competency((int) $course->id);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $rows = $this->cleaned_rows_for([(int) $course->id]);

        $this->assertTrue($rows[(int) $course->id]['islocked']);
        $this->assertFalse($rows[(int) $course->id]['iscompleted']);
    }

    /**
     * A course with no competency link answers "locked" however enrolled the caller is.
     *
     * @return void
     */
    public function test_execute_locks_an_unlinked_course(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($user);

        $rows = $this->cleaned_rows_for([(int) $course->id]);

        $this->assertTrue($rows[(int) $course->id]['islocked']);
    }

    /**
     * Every requested id gets a row back, in order, gated or not.
     *
     * The client pairs the rows with its own cards by course id and falls back to loading
     * every card unprioritised when the call fails, so a missing row would silently drop a
     * card's completion tab state rather than raise anything.
     *
     * @return void
     */
    public function test_execute_answers_for_every_requested_id(): void {
        $this->resetAfterTest();
        $linked = $this->getDataGenerator()->create_course();
        $unlinked = $this->getDataGenerator()->create_course();
        $this->link_competency((int) $linked->id);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $rows = $this->cleaned_rows_for([(int) $linked->id, (int) $unlinked->id, -1]);

        $this->assertCount(3, $rows);
        $this->assertArrayHasKey((int) $linked->id, $rows);
        $this->assertArrayHasKey((int) $unlinked->id, $rows);
        $this->assertTrue($rows[-1]['islocked']);
    }
}
