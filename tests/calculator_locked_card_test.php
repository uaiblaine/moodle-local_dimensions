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
     * A learner enrolled under a site's own student-archetype role is not locked out.
     *
     * The shortname is the site's to choose, so a learner role renamed or created under another
     * one must open the course just like core's "student".
     *
     * @return void
     */
    public function test_a_custom_learner_role_opens_the_course(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();

        $learnerroleid = create_role('Aluno', 'aluno', '', 'student');
        $learner = $generator->create_user();
        $generator->enrol_user($learner->id, $course->id, $learnerroleid);
        $this->assertFalse(calculator::is_locked($course, (int) $learner->id));

        // Controls: an enrolled teacher holds no learner role, and a stranger is not enrolled.
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->assertTrue(calculator::is_locked($course, (int) $teacher->id));
        $stranger = $generator->create_user();
        $this->assertTrue(calculator::is_locked($course, (int) $stranger->id));
    }

    /**
     * The role named "student" still opens the course on a site that cleared its archetype.
     *
     * @return void
     */
    public function test_the_student_shortname_counts_without_its_archetype(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $DB->set_field('role', 'archetype', '', ['shortname' => 'student']);
        $studentroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        // Precondition: the archetype no longer names the role, so only its shortname can.
        $this->assertArrayNotHasKey($studentroleid, get_archetype_roles('student'));

        $learner = $generator->create_user();
        $generator->enrol_user($learner->id, $course->id, 'student');

        $this->assertFalse(calculator::is_locked($course, (int) $learner->id));
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
