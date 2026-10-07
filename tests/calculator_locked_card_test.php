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
 * Tests for the course card's lock: who it opens for, and how its date reads.
 *
 * The class-level covers tag stays in docblock form on purpose: moodle-cs on the 4.05 leg cannot
 * see PHP attributes, and the plugin still supports 4.5.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\calculator
 */
final class calculator_locked_card_test extends \advanced_testcase {
    /**
     * Enrol a user on the course's manual instance with no role, as an instance that assigns none does.
     *
     * The data generator falls back to the instance's own role when it is given none, so it cannot
     * make this enrolment.
     *
     * @param \stdClass $course The course.
     * @param \stdClass $user The user.
     * @return void
     */
    private function enrol_without_role(\stdClass $course, \stdClass $user): void {
        global $DB;

        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        enrol_get_plugin('manual')->enrol_user($instance, (int) $user->id, 0);
    }

    /**
     * An active enrolment opens the course whatever role it carries, or none.
     *
     * The question is whether the user can open the course, as the plan accordion asks it: a learner
     * under a role the site renamed, a teacher, and a user enrolled through an instance that assigns
     * no role all open it. The controls are the users an active enrolment is meant to exclude.
     *
     * @return void
     */
    public function test_an_active_enrolment_opens_the_course_whatever_its_role(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();

        $learnerroleid = create_role('Aluno', 'aluno', '', 'student');
        $learner = $generator->create_user();
        $generator->enrol_user($learner->id, $course->id, $learnerroleid);
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $roleless = $generator->create_user();
        $this->enrol_without_role($course, $roleless);
        // Precondition: the third enrolment carries no role at all.
        $this->assertSame([], get_user_roles(\core\context\course::instance($course->id), $roleless->id));

        foreach ([$learner, $teacher, $roleless] as $user) {
            $this->assertFalse(calculator::is_locked($course, (int) $user->id), "User {$user->id} should open the course");
            $this->assertFalse(calculator::is_locked_for_viewer($course, (int) $user->id, (int) $user->id));
        }

        // Controls: somebody never enrolled, a suspended enrolment and one that has not started are locked.
        $stranger = $generator->create_user();
        $this->assertTrue(calculator::is_locked($course, (int) $stranger->id));
        $suspended = $generator->create_user();
        $generator->enrol_user($suspended->id, $course->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $this->assertTrue(calculator::is_locked($course, (int) $suspended->id));
        $scheduled = $generator->create_user();
        $generator->enrol_user($scheduled->id, $course->id, 'student', 'manual', time() + WEEKSECS);
        $this->assertTrue(calculator::is_locked($course, (int) $scheduled->id));
    }

    /**
     * The lock the card carries is the viewer's enrolment, whoever the card describes.
     *
     * @return void
     */
    public function test_the_lock_for_a_viewer_is_the_viewers_enrolment_not_the_owners(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $enrolled = $generator->create_user();
        $outsider = $generator->create_user();
        $generator->enrol_user($enrolled->id, $course->id, 'student');

        $this->assertFalse(calculator::is_locked_for_viewer($course, (int) $outsider->id, (int) $enrolled->id));
        $this->assertTrue(calculator::is_locked_for_viewer($course, (int) $enrolled->id, (int) $outsider->id));
    }

    /**
     * A user enrolled in the course is never offered enrolment, even with a way in open.
     *
     * The user's enrolment carries no role. The course's self instance is open, so a locked card
     * would have offered it; a user who is not enrolled in the same course is offered it, which is
     * what makes the negative below mean something.
     *
     * @return void
     */
    public function test_an_enrolled_user_is_not_offered_enrol_to_start(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $self = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'self'], '*', MUST_EXIST);
        enrol_get_plugin('self')->update_status($self, ENROL_INSTANCE_ENABLED);

        $roleless = $generator->create_user();
        $this->enrol_without_role($course, $roleless);
        $stranger = $generator->create_user();

        $this->setUser($stranger);
        $row = calculator::get_course_section_progress((int) $course->id);
        $this->assertTrue($row['locked']);
        $this->assertSame('open', $row['state']['state']);
        $this->assertNotNull($row['state']['actionurl']);

        $this->setUser($roleless);
        $row = calculator::get_course_section_progress((int) $course->id);
        $this->assertFalse($row['locked']);
        $this->assertSame('enrolled', $row['state']['state']);
        $this->assertNull($row['state']['actionurl']);
        $this->assertNull($row['state']['routeurl']);
    }

    /**
     * A locked card's date follows the viewer's language pack, not one fixed day/month order.
     *
     * @return void
     */
    public function test_the_locked_card_date_follows_the_language_pack(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $start = make_timestamp(2031, 1, 15, 12);
        $course = $generator->create_course(['startdate' => $start]);
        $this->setUser($generator->create_user());

        $format = get_string('strftimedatefullshort', 'langconfig');
        // Precondition: the pack's format differs from the one the card used to hardcode.
        $this->assertNotSame(userdate($start, '%d/%m/%Y'), userdate($start, $format));

        $data = calculator::get_course_section_progress((int) $course->id);

        $this->assertTrue($data['locked']);
        $this->assertSame(userdate($start, $format), $data['formatted_start_date']);
    }
}
