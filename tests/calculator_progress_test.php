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
}
