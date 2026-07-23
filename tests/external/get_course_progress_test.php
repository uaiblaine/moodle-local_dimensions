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
 * Tests for the tracker's course progress web service.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\external\get_course_progress
 */
final class get_course_progress_test extends \advanced_testcase {
    /**
     * Run the service for one course and clean the payload through the returns structure.
     *
     * clean_returnvalue strips keys the structure does not declare, silently, so only the
     * cleaned payload proves the allowlist actually carries what execute() built.
     *
     * @param int $courseid The course id.
     * @return array The cleaned row for that course.
     */
    private function cleaned_row_for(int $courseid): array {
        $result = external_api::clean_returnvalue(
            get_course_progress::execute_returns(),
            get_course_progress::execute([$courseid])
        );

        return $result[0];
    }

    /**
     * A course with completion tracking off returns cleanly, with no notices.
     *
     * Regression test for the payload guard: the calculator returns only the enabled flag in
     * that case, so every other key has to be defaulted before the returns structure sees it.
     *
     * @return void
     */
    public function test_execute_handles_a_completion_disabled_course(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 0]);
        $this->getDataGenerator()->enrol_user((int) $user->id, (int) $course->id, 'student');
        $this->setUser($user);

        $row = $this->cleaned_row_for((int) $course->id);

        $this->assertFalse($row['enabled']);
        $this->assertFalse($row['locked']);
        $this->assertSame([], $row['sections']);
        $this->assertArrayNotHasKey('activity', $row);
    }

    /**
     * A course that boils down to one trackable activity carries it; a busier one does not.
     *
     * @return void
     */
    public function test_execute_returns_the_activity_only_for_a_single_activity_course(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->libdir . '/completionlib.php');
        $this->setAdminUser();
        set_config('enablecompletion', 1);

        $single = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $busy = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $single->id,
            'name' => 'Weekly reflection',
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        foreach (['First task', 'Second task'] as $name) {
            $this->getDataGenerator()->create_module('page', [
                'course' => $busy->id,
                'name' => $name,
                'completion' => COMPLETION_TRACKING_MANUAL,
            ]);
        }

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int) $user->id, (int) $single->id, 'student');
        $this->getDataGenerator()->enrol_user((int) $user->id, (int) $busy->id, 'student');
        $this->setUser($user);

        $row = $this->cleaned_row_for((int) $single->id);
        $this->assertArrayHasKey('activity', $row);
        $this->assertSame('Weekly reflection', $row['activity']['name']);
        $this->assertStringContainsString('/mod/page/view.php?id=' . $page->cmid, $row['activity']['url']);
        $this->assertFalse($row['activity']['completed']);

        // An untouched activity that is now complete flips the flag the card reads.
        $completion = new \completion_info(get_course((int) $single->id));
        $completion->update_state(
            get_coursemodule_from_id('page', (int) $page->cmid),
            COMPLETION_COMPLETE,
            (int) $user->id
        );
        $this->assertTrue($this->cleaned_row_for((int) $single->id)['activity']['completed']);

        $this->assertArrayNotHasKey('activity', $this->cleaned_row_for((int) $busy->id));
    }

    /**
     * A locked course with self-enrolment open says so, and dates its own opening.
     *
     * @return void
     */
    public function test_execute_reports_self_enrolment_on_a_locked_course(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['startdate' => time() + WEEKSECS]);
        $self = enrol_get_plugin('self');
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'self'], '*', MUST_EXIST);
        $self->update_status($instance, ENROL_INSTANCE_ENABLED);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $row = $this->cleaned_row_for((int) $course->id);

        $this->assertTrue($row['locked']);
        $this->assertTrue($row['can_self_enrol']);
        $this->assertTrue($row['is_future_date']);
    }
}
