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
 * Tests for calculator::filter_courses_by_enrollment in 'enrolledorself' mode.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\calculator::filter_courses_by_enrollment
 */
final class calculator_filter_test extends \advanced_testcase {
    /**
     * enrolledorself keeps active, future-dated and self-enrolable courses; drops the rest.
     *
     * @return void
     */
    public function test_enrolledorself_keeps_enrolled_and_self_enrolable(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $active = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $active->id, 'student');

        // Future-dated enrolment: counts for onlyactive=false, not for onlyactive=true.
        $future = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $future->id, null, 'manual', time() + DAYSECS);

        $selfcourse = $this->getDataGenerator()->create_course();
        $self = enrol_get_plugin('self');
        $instance = $DB->get_record('enrol', ['courseid' => $selfcourse->id, 'enrol' => 'self'], '*', MUST_EXIST);
        $self->update_status($instance, ENROL_INSTANCE_ENABLED);

        // No enrolment and the default self instance stays disabled -> dropped.
        $none = $this->getDataGenerator()->create_course();

        $courses = [
            $active->id => $active,
            $future->id => $future,
            $selfcourse->id => $selfcourse,
            $none->id => $none,
        ];
        $filtered = calculator::filter_courses_by_enrollment(
            $courses,
            (int) $user->id,
            constants::ENROLLMENTFILTER_ENROLLEDORSELF
        );
        $ids = array_map('intval', array_column($filtered, 'id'));

        $this->assertContains((int) $active->id, $ids);
        $this->assertContains((int) $future->id, $ids);
        $this->assertContains((int) $selfcourse->id, $ids);
        $this->assertNotContains((int) $none->id, $ids);
    }

    /**
     * For another user's plan the self-enrol leg is skipped (degrades to enrolled-only).
     *
     * @return void
     */
    public function test_enrolledorself_other_user_skips_self_leg(): void {
        global $DB;
        $this->resetAfterTest();
        $learner = $this->getDataGenerator()->create_user();
        $manager = $this->getDataGenerator()->create_user();

        $enrolled = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($learner->id, $enrolled->id, 'student');

        $selfcourse = $this->getDataGenerator()->create_course();
        $self = enrol_get_plugin('self');
        $instance = $DB->get_record('enrol', ['courseid' => $selfcourse->id, 'enrol' => 'self'], '*', MUST_EXIST);
        $self->update_status($instance, ENROL_INSTANCE_ENABLED);

        // Viewer is the manager, not the learner.
        $this->setUser($manager);

        $filtered = calculator::filter_courses_by_enrollment(
            [$enrolled->id => $enrolled, $selfcourse->id => $selfcourse],
            (int) $learner->id,
            constants::ENROLLMENTFILTER_ENROLLEDORSELF
        );
        $ids = array_map('intval', array_column($filtered, 'id'));

        $this->assertContains((int) $enrolled->id, $ids);
        $this->assertNotContains((int) $selfcourse->id, $ids);
    }
}
