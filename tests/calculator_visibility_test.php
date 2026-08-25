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
     * Work the learner cannot open YET counts; work they can never see does not.
     *
     * A date restriction set to "display greyed" is the case the whole standard turns on: the
     * learner sees the activity and will have to do it, so it belongs in the denominator even
     * though uservisible is false for it today. The hide-entirely activity beside it is the
     * control - it proves the assertion is measuring the greyed one's inclusion rather than a
     * predicate that simply counts everything.
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

        /* One of the two the learner can see. The hide-entirely activity is invisible to them,
           so it is not theirs to do and 50 rather than 33 is the honest answer. */
        $this->assertSame(50, $this->bar());
        $this->assertSame(50, $this->ring());
    }

    /**
     * A restriction the learner can never satisfy leaves their workload entirely.
     *
     * The same activity, the same "display greyed" setting, measured for a learner inside the
     * group and for one outside it. The member is the control: it proves the activity is one the
     * count can see, so the non-member's exclusion is the group rule and not the fixture. Without
     * this, a learner outside the group could never reach 100% in the course.
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

        /* Control: the member has finished the same single activity, and reads 50 rather than
           100 precisely because the group-only one IS in their workload. If it counted for
           nobody, both learners would read 100 and the assertion below would prove nothing. */
        $this->assertSame(50, $this->bar((int) $member->id));

        // For a learner who will never be in that group it is not work at all.
        $this->assertSame(100, $this->bar());
        $this->assertSame(100, $this->ring());
    }

    /**
     * An activity available but not listed on the course page is still work the learner owes.
     *
     * Stealth is the mirror image of the greyed case - openable right now, but not listed - and
     * core's own denominator drops it. The rings have always counted it; the bar must agree.
     * The control is the hidden activity, which is neither listed nor openable and must stay out.
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
     * This is the clause the other cases cannot reach. For an ordinary student a hidden activity
     * is excluded twice over - not listed on the course page and not openable - so nothing here
     * depends on testing $cm->visible. Grant that same student
     * moodle/course:viewhiddenactivities, though, and uservisible flips to true, the union half
     * of the predicate admits it, and the workload of a course would start depending on who was
     * looking at it. Reading $cm->visible first settles it: hidden is hidden for everybody, which
     * is what the standard says.
     *
     * The plain activity is the control: it proves the elevated learner is being measured at all.
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
     * The show flag is the whole point of the fixture rather than a detail: true renders the
     * activity greyed with its reason, false removes it from the page altogether, and the two
     * land on opposite sides of the standard.
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
