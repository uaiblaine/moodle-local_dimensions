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

namespace local_dimensions;

/**
 * Tests for calculator::get_course_section_progress.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\calculator::get_course_section_progress
 */
final class calculator_progress_test extends \advanced_testcase {
    /**
     * A locked course reports its lock even when completion tracking is switched off.
     *
     * Regression test: the completion check used to return before the lock was resolved,
     * so the card told a user who cannot open the course that completion was disabled.
     *
     * @return void
     */
    public function test_lock_is_reported_when_completion_is_disabled(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Completion off, and the user is not enrolled - so the course is locked.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 0]);

        $data = calculator::get_course_section_progress($course->id);

        $this->assertFalse($data['enabled']);
        $this->assertTrue($data['locked']);
        $this->assertArrayHasKey('formatted_start_date', $data);
        $this->assertArrayHasKey('course_url', $data);
        $this->assertSame([], $data['sections']);
    }

    /**
     * An enrolled student on a completion-disabled course is reported unlocked.
     *
     * @return void
     */
    public function test_enrolled_student_is_not_locked_when_completion_is_disabled(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 0]);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $data = calculator::get_course_section_progress($course->id);

        $this->assertFalse($data['enabled']);
        $this->assertFalse($data['locked']);
    }

    /**
     * With completion on, an enrolled student gets the section payload unlocked.
     *
     * @return void
     */
    public function test_completion_enabled_returns_sections(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1, 'numsections' => 2]);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $data = calculator::get_course_section_progress($course->id);

        $this->assertTrue($data['enabled']);
        $this->assertFalse($data['locked']);
        $this->assertIsArray($data['sections']);
    }

    /**
     * The section percentage reports the share of its activities the user has completed.
     *
     * @return void
     */
    public function test_section_percentage_counts_completed_activities(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1, 'numsections' => 1]);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $cms = $this->create_tracked_activities($course, 4);
        $this->setUser($user);
        $this->complete_activities($course, $user->id, array_slice($cms, 0, 1));

        $section = $this->find_section($course->id, 1);

        $this->assertTrue($section['has_activities']);
        $this->assertSame(25, (int) $section['percentage']);
    }

    /**
     * A section one activity short of finished never reports a round hundred.
     *
     * 199 of 200 rounds to 100, and get_course_progress reads any 100 as "completed" and
     * swaps the ring for the done icon - so the card claimed a finished section while an
     * activity was still open.
     *
     * @return void
     */
    public function test_section_one_activity_short_never_reports_a_hundred(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1, 'numsections' => 1]);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $cms = $this->create_tracked_activities($course, 200);
        $this->setUser($user);
        $this->complete_activities($course, $user->id, array_slice($cms, 0, 199));

        $section = $this->find_section($course->id, 1);

        $this->assertSame(99, (int) $section['percentage']);
    }

    /**
     * Creates activities with manual completion tracking in section 1.
     *
     * @param \stdClass $course The course to add them to.
     * @param int $count How many to create.
     * @return array The created course module ids.
     */
    private function create_tracked_activities(\stdClass $course, int $count): array {
        $cmids = [];
        for ($i = 0; $i < $count; $i++) {
            $module = $this->getDataGenerator()->create_module(
                'page',
                [
                    'course' => $course->id,
                    'section' => 1,
                    'completion' => COMPLETION_TRACKING_MANUAL,
                ]
            );
            $cmids[] = (int) $module->cmid;
        }

        return $cmids;
    }

    /**
     * Marks the given course modules complete for a user.
     *
     * @param \stdClass $course The course they belong to.
     * @param int $userid The user completing them.
     * @param array $cmids The course module ids to complete.
     * @return void
     */
    private function complete_activities(\stdClass $course, int $userid, array $cmids): void {
        $completion = new \completion_info($course);
        $modinfo = get_fast_modinfo($course, $userid);
        foreach ($cmids as $cmid) {
            $completion->update_state($modinfo->get_cm($cmid), COMPLETION_COMPLETE, $userid);
        }
    }

    /**
     * Returns one section's row from the calculator payload.
     *
     * @param int $courseid The course to calculate.
     * @param int $index The zero-based position of the section in the returned list.
     * @return array The section row.
     */
    private function find_section(int $courseid, int $index): array {
        $data = calculator::get_course_section_progress($courseid);
        $this->assertArrayHasKey($index, $data['sections']);

        return $data['sections'][$index];
    }
}
