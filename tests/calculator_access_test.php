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
 * Tests for calculator::user_can_access_course.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\calculator::user_can_access_course
 */
final class calculator_access_test extends \advanced_testcase {
    /**
     * Active enrolment makes a course accessible; a course with no enrolment and no self instance does not.
     *
     * @return void
     */
    public function test_active_enrolment_is_accessible(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $enrolled = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $enrolled->id);
        $none = $this->getDataGenerator()->create_course();

        $this->assertTrue(calculator::user_can_access_course($enrolled, (int) $user->id));
        $this->assertFalse(calculator::user_can_access_course($none, (int) $user->id));
    }

    /**
     * An available self-enrol instance makes an un-enrolled course accessible.
     *
     * @return void
     */
    public function test_self_enrollable_is_accessible(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $course = $this->getDataGenerator()->create_course();
        $self = enrol_get_plugin('self');
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'self'], '*', MUST_EXIST);
        $self->update_status($instance, ENROL_INSTANCE_ENABLED);

        $this->assertTrue(calculator::user_can_access_course($course, (int) $user->id));
    }
}
