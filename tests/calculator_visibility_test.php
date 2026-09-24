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
 * Which activities belong in a learner's required workload, for the bar and the rings alike.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\calculator::get_course_section_progress
 * @covers     \local_dimensions\calculator::course_completion_percentage
 */
final class calculator_visibility_test extends \advanced_testcase {
    /** @var \stdClass The course under test. */
    private $course;

    /** @var \stdClass The learner whose card is being measured. */
    private $user;

    /**
     * Work the learner cannot open yet counts; work they cannot see does not.
     *
     * A date restriction shown greyed keeps the activity in the denominator although uservisible
     * is false today: the learner sees it and will have to do it. The hide-entirely activity is
     * the control, proving the predicate does not simply count everything.
     *
     * @return void
     */
    public function test_a_restriction_the_learner_will_outlast_counts(): void {
        $this->build_course();
        $done = $this->add_activity();
        $greyed = $this->add_activity();
        $this->restrict($greyed, ['type' => 'date', 'd' => '>=', 't' => time() + DAYSECS], true);
        $hidden = $this->add_activity();
        $this->restrict($hidden, ['type' => 'date', 'd' => '>=', 't' => time() + DAYSECS], false);
        $this->complete([$done]);
        $this->refresh();

        // One of the two the learner can see; the hide-entirely activity is not theirs to do.
        $this->assertSame(50, $this->bar());
        $this->assertSame(50, $this->ring());
    }

    /**
     * A restriction the learner can never satisfy leaves their workload entirely.
     *
     * A group restriction shown greyed excludes the activity for a non-member, who could otherwise
     * never reach 100%. The member is the control: the same activity counts for them, so the
     * non-member's exclusion comes from the group rule and not from the fixture.
     *
     * @return void
     */
    public function test_a_restriction_the_learner_can_never_satisfy_does_not_count(): void {
        $this->build_course();
        $done = $this->add_activity();
        $grouponly = $this->add_activity();

        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $member = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($member->id, $this->course->id, 'student');
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $member->id]);

        $this->restrict($grouponly, ['type' => 'group', 'id' => (int) $group->id], true);
        $this->complete([$done]);
        $this->complete([$done], (int) $member->id);
        $this->refresh();

        /* Control: the member has finished the same single activity and reads 50, because the
           group-only one is in their workload. */
        $this->assertSame(50, $this->bar((int) $member->id));

        // For a learner who will never be in that group it is not work at all.
        $this->assertSame(100, $this->bar());
        $this->assertSame(100, $this->ring());
    }

    /**
     * An activity available but not listed on the course page is still work the learner owes.
     *
     * A stealth activity is openable but not listed, so is_visible_on_course_page() alone would
     * drop it. The hidden activity is the control: neither listed nor openable, it must stay out.
     *
     * @return void
     */
    public function test_a_stealth_activity_counts(): void {
        global $CFG;
        $this->build_course();
        $CFG->allowstealth = 1;

        $done = $this->add_activity();
        $stealth = $this->add_activity();
        $hidden = $this->add_activity();
        $this->complete([$done]);

        set_coursemodule_visible($stealth, 1, 0);
        set_coursemodule_visible($hidden, 0);
        $this->refresh();

        // One of the two that are the learner's to do; the hidden one is nobody's.
        $this->assertSame(50, $this->bar());
        $this->assertSame(50, $this->ring());
    }

    /**
     * A hidden activity stays out even for a learner who is allowed to see hidden activities.
     *
     * With moodle/course:viewhiddenactivities, uservisible is true for a hidden activity, so only
     * the explicit $cm->visible test keeps the workload from depending on who is looking. The
     * plain activity is the control: it proves the elevated learner is measured at all.
     *
     * @return void
     */
    public function test_a_hidden_activity_stays_out_even_for_a_learner_who_may_see_it(): void {
        $this->build_course();
        $done = $this->add_activity();
        $hidden = $this->add_activity();
        $this->complete([$done]);

        set_coursemodule_visible($hidden, 0);

        // Still a tracked student, but one who may look behind the eye icon.
        $context = \core\context\course::instance($this->course->id);
        $studentroleid = $this->get_student_role_id();
        role_change_permission($studentroleid, $context, 'moodle/course:viewhiddenactivities', CAP_ALLOW);

        $this->refresh();

        $modinfo = get_fast_modinfo($this->course->id, (int) $this->user->id);
        // Precondition: without the $cm->visible test the union really would admit this one.
        $this->assertTrue($modinfo->get_cm($hidden)->uservisible);

        // The hidden activity is nobody's work, however privileged the viewer.
        $this->assertSame(100, $this->bar());
        $this->assertSame(100, $this->ring());
    }

    /**
     * The id of the student role.
     *
     * @return int
     */
    private function get_student_role_id(): int {
        global $DB;

        return (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
    }

    /**
     * The bar and the ring answer the same question on a course holding every awkward case.
     *
     * The two numbers sit on one card, so any disagreement reads as a bug. This is the invariant
     * the shared predicate exists to hold; the per-case tests above say what the shared answer
     * should be.
     *
     * @return void
     */
    public function test_the_bar_and_the_ring_never_disagree(): void {
        global $CFG;
        $this->build_course();
        $CFG->allowstealth = 1;

        $done = $this->add_activity();
        $this->add_activity();
        $greyed = $this->add_activity();
        $this->restrict($greyed, ['type' => 'date', 'd' => '>=', 't' => time() + DAYSECS], true);
        $hideentirely = $this->add_activity();
        $this->restrict($hideentirely, ['type' => 'date', 'd' => '>=', 't' => time() + DAYSECS], false);
        $stealth = $this->add_activity();
        $hidden = $this->add_activity();
        $this->complete([$done]);

        set_coursemodule_visible($stealth, 1, 0);
        set_coursemodule_visible($hidden, 0);
        $this->refresh();

        // Counted: done, open, greyed, stealth. Not counted: hide-entirely, hidden.
        $this->assertSame(25, $this->bar());
        $this->assertSame(25, $this->ring());
    }

    /**
     * Creates the course and the learner, and enrols one in the other.
     *
     * @return void
     */
    private function build_course(): void {
        global $CFG;

        $this->resetAfterTest();
        $CFG->enableavailability = 1;
        $this->setAdminUser();

        $this->user = $this->getDataGenerator()->create_user();
        $this->course = $this->getDataGenerator()->create_course([
            'enablecompletion' => 1,
            'numsections' => 1,
        ]);
        $this->getDataGenerator()->enrol_user($this->user->id, $this->course->id, 'student');
    }

    /**
     * Adds one activity with manual completion tracking to section 1.
     *
     * @return int Its course module id.
     */
    private function add_activity(): int {
        $module = $this->getDataGenerator()->create_module(
            'page',
            [
                'course' => $this->course->id,
                'section' => 1,
                'completion' => COMPLETION_TRACKING_MANUAL,
            ]
        );

        return (int) $module->cmid;
    }

    /**
     * Puts one availability condition on an activity.
     *
     * The show flag matters: true lists the activity greyed with its reason, false removes it
     * from the course page altogether.
     *
     * @param int $cmid The activity to restrict.
     * @param array $condition One core availability condition, as it is stored.
     * @param bool $show Whether the activity stays on the course page, greyed.
     * @return void
     */
    private function restrict(int $cmid, array $condition, bool $show): void {
        global $DB;

        $availability = json_encode([
            'op' => '&',
            'c' => [$condition],
            'showc' => [$show],
        ]);
        $DB->set_field('course_modules', 'availability', $availability, ['id' => $cmid]);
    }

    /**
     * Marks activities complete for the learner.
     *
     * @param array $cmids The course module ids to complete.
     * @param int|null $userid Who completes them, defaulting to the learner.
     * @return void
     */
    private function complete(array $cmids, ?int $userid = null): void {
        $userid = $userid ?? (int) $this->user->id;
        $completion = new \completion_info($this->course);
        $modinfo = get_fast_modinfo($this->course, $userid);
        foreach ($cmids as $cmid) {
            $completion->update_state($modinfo->get_cm($cmid), COMPLETION_COMPLETE, $userid);
        }
    }

    /**
     * Rebuilds the course cache and becomes the learner.
     *
     * @return void
     */
    private function refresh(): void {
        rebuild_course_cache($this->course->id, true);
        \course_modinfo::clear_instance_cache();
        $this->setUser($this->user);
    }

    /**
     * The course-level percentage the card's bar would show.
     *
     * @param int|null $userid Whose bar, defaulting to the learner.
     * @return int
     */
    private function bar(?int $userid = null): int {
        return calculator::course_completion_percentage(
            (int) $this->course->id,
            $userid ?? (int) $this->user->id
        );
    }

    /**
     * The percentage the card's ring would show for section 1.
     *
     * @return int
     */
    private function ring(): int {
        $data = calculator::get_course_section_progress($this->course->id);

        return (int) $data['sections'][1]['percentage'];
    }
}
