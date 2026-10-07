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
 * Tests for the enrolment start date a locked course card announces.
 *
 * The date is promised only for an enrolment that will open on its own: an active row on an enabled
 * instance whose period has not ended by the time it starts. Each condition has its own test, with a
 * control that the same row passes while it meets the condition. None of them needs enrol_apply.
 *
 * The class-level covers tag stays in docblock form on purpose: moodle-cs on the 4.05 leg cannot
 * see PHP attributes, and the plugin still supports 4.5.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\calculator
 */
final class calculator_enrolment_start_test extends \advanced_testcase {
    /**
     * Enrol a user on the course's manual instance with the given period and row status.
     *
     * @param \stdClass $course The course.
     * @param \stdClass $user The user.
     * @param int $timestart The enrolment start.
     * @param int $timeend The enrolment end, 0 for none.
     * @param int $status The row status, ENROL_USER_ACTIVE or ENROL_USER_SUSPENDED.
     * @return void
     */
    private function enrol(
        \stdClass $course,
        \stdClass $user,
        int $timestart,
        int $timeend = 0,
        int $status = ENROL_USER_ACTIVE
    ): void {
        $this->getDataGenerator()->enrol_user(
            (int) $user->id,
            (int) $course->id,
            'student',
            'manual',
            $timestart,
            $timeend,
            $status
        );
    }

    /**
     * An active row that starts in the future gives its start date.
     *
     * @return void
     */
    public function test_an_active_future_row_gives_its_date(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $start = time() + 5 * DAYSECS;
        $this->enrol($course, $user, $start);

        $this->assertSame($start, calculator::get_enrolment_start_date($course, (int) $user->id));
    }

    /**
     * A suspended row never becomes active by itself, so it gives no date.
     *
     * @return void
     */
    public function test_a_suspended_future_row_gives_no_date(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $control = $this->getDataGenerator()->create_user();
        $suspended = $this->getDataGenerator()->create_user();
        $start = time() + 5 * DAYSECS;
        $this->enrol($course, $control, $start);
        $this->enrol($course, $suspended, $start, 0, ENROL_USER_SUSPENDED);

        $this->assertSame($start, calculator::get_enrolment_start_date($course, (int) $control->id));
        $this->assertNull(calculator::get_enrolment_start_date($course, (int) $suspended->id));
    }

    /**
     * An active row on a disabled instance gives no date, and gives it again once the instance is enabled.
     *
     * @return void
     */
    public function test_a_future_row_on_a_disabled_instance_gives_no_date(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $start = time() + 5 * DAYSECS;
        $this->enrol($course, $user, $start);
        $this->assertSame($start, calculator::get_enrolment_start_date($course, (int) $user->id));

        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        enrol_get_plugin('manual')->update_status($instance, ENROL_INSTANCE_DISABLED);
        $this->assertNull(calculator::get_enrolment_start_date($course, (int) $user->id));

        enrol_get_plugin('manual')->update_status($instance, ENROL_INSTANCE_ENABLED);
        $this->assertSame($start, calculator::get_enrolment_start_date($course, (int) $user->id));
    }

    /**
     * A period that ends before, or when, it starts never opens.
     *
     * @return void
     */
    public function test_a_period_that_ends_before_it_starts_gives_no_date(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $start = time() + 5 * DAYSECS;

        // Controls: no end, and an end after the start, both open.
        $open = $this->getDataGenerator()->create_user();
        $this->enrol($course, $open, $start);
        $this->assertSame($start, calculator::get_enrolment_start_date($course, (int) $open->id));
        $bounded = $this->getDataGenerator()->create_user();
        $this->enrol($course, $bounded, $start, $start + DAYSECS);
        $this->assertSame($start, calculator::get_enrolment_start_date($course, (int) $bounded->id));

        $endsbefore = $this->getDataGenerator()->create_user();
        $this->enrol($course, $endsbefore, $start, time() + DAYSECS);
        $this->assertNull(calculator::get_enrolment_start_date($course, (int) $endsbefore->id));
        $endsatstart = $this->getDataGenerator()->create_user();
        $this->enrol($course, $endsatstart, $start, $start);
        $this->assertNull(calculator::get_enrolment_start_date($course, (int) $endsatstart->id));
    }

    /**
     * Rows that cannot open are skipped, not merely ignored when they are the only one.
     *
     * The earliest row is suspended and an active one comes later, so the date is the later one's.
     *
     * @return void
     */
    public function test_the_earliest_row_that_can_open_wins(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $soon = time() + 2 * DAYSECS;
        $later = time() + 9 * DAYSECS;
        $this->enrol($course, $user, $soon, 0, ENROL_USER_SUSPENDED);

        // The self instance every course carries, enabled, so the two rows are different enrolments.
        $self = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'self'], '*', MUST_EXIST);
        enrol_get_plugin('self')->update_status($self, ENROL_INSTANCE_ENABLED);
        enrol_get_plugin('self')->enrol_user($self, (int) $user->id, null, $later, 0, ENROL_USER_ACTIVE);

        $this->assertSame($later, calculator::get_enrolment_start_date($course, (int) $user->id));
    }

    /**
     * A row that cannot open leaves the card on the course start date, in no scheduled state.
     *
     * @return void
     */
    public function test_a_suspended_row_leaves_the_card_on_the_course_start_date(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $coursestart = make_timestamp(2031, 1, 15, 12);
        $course = $generator->create_course(['startdate' => $coursestart]);
        $control = $generator->create_user();
        $suspended = $generator->create_user();
        $start = time() + 5 * DAYSECS;
        $this->enrol($course, $control, $start);
        $this->enrol($course, $suspended, $start, 0, ENROL_USER_SUSPENDED);
        $format = get_string('strftimedatefullshort', 'langconfig');

        // Control: the active row is a scheduled enrolment, on its own date.
        $this->setUser($control);
        $row = calculator::get_course_section_progress((int) $course->id);
        $this->assertTrue($row['locked']);
        $this->assertSame('scheduled', $row['state']['state']);
        $this->assertSame($start, $row['state']['date']);
        $this->assertSame(userdate($start, $format), $row['formatted_start_date']);

        $this->setUser($suspended);
        $row = calculator::get_course_section_progress((int) $course->id);
        $this->assertTrue($row['locked']);
        $this->assertSame('none', $row['state']['state']);
        $this->assertSame(userdate($coursestart, $format), $row['formatted_start_date']);
    }
}
