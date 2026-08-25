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
 * Tests for calculator::course_completion_percentage.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\calculator::course_completion_percentage
 */
final class calculator_course_percentage_test extends \advanced_testcase {
    /**
     * A deleted activity leaves the numerator as well as the denominator.
     *
     * This is the defect the method exists for. On Moodle 4.5 core's own helper drops a module
     * flagged for deletion from its denominator but keeps that module's completion row in its
     * numerator, because count_modules_completed() takes no module list on that branch
     * (MDL-60912, fixed in 5.0.7 and 5.1.4, never backported). Deleting an activity the learner
     * had ALREADY COMPLETED therefore made the bar jump: two of four became two of three, 67%
     * where 33% was the truth, and clamp_percentage() cannot see it because the value never
     * passes 100.
     *
     * The first assertion is the control. It proves both completed activities really are in the
     * count, so the second cannot pass by the deleted one never having counted at all.
     *
     * Note this scenario discriminates only on 4.5: 5.1 and 5.2 answer 33 through core as well,
     * and this method reproduces core's later definition rather than replacing it, so on those
     * branches no test can tell the two apart. That is the intended outcome, not a gap -
     * test_an_activity_restricted_until_later_still_counts() is the pin that holds on all three.
     *
     * @return void
     */
    public function test_a_deleted_completed_activity_leaves_the_percentage(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1, 'numsections' => 1]);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $cmids = $this->create_tracked_activities($course, 4);
        $this->complete_activities($course, (int) $user->id, array_slice($cmids, 0, 2));
        $this->setUser($user);

        // Control: four activities count, two of them complete.
        $this->assertSame(50, calculator::course_completion_percentage((int) $course->id, (int) $user->id));

        // Delete one of the two the learner had completed.
        $this->schedule_deletion((int) $course->id, $cmids[1]);

        // One of the three survivors is complete. The deleted one must leave both halves.
        $this->assertSame(33, calculator::course_completion_percentage((int) $course->id, (int) $user->id));
    }

    /**
     * An activity hidden from the learner leaves the denominator.
     *
     * On Moodle 4.5 core applies no visibility filter to its denominator at all -
     * completion_info::get_activities() keeps every trackable module, hidden or not - so a
     * learner could never reach 100% in a course holding a hidden tracked activity, and the
     * card showed a 50% bar above a 100% section ring for the same person. 5.1 and 5.2 already
     * exclude it through get_user_activities_with_completion(), which this method reproduces.
     *
     * The first assertion is the control: it proves the second activity is one the count can
     * see, so what follows measures its exclusion rather than its absence.
     *
     * @return void
     */
    public function test_a_hidden_activity_is_not_counted(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1, 'numsections' => 1]);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $cmids = $this->create_tracked_activities($course, 2);
        $this->complete_activities($course, (int) $user->id, array_slice($cmids, 0, 1));
        $this->setUser($user);

        // Control: both count, one of them complete.
        $this->assertSame(50, calculator::course_completion_percentage((int) $course->id, (int) $user->id));

        $this->setAdminUser();
        set_coursemodule_visible($cmids[1], 0);
        rebuild_course_cache($course->id, true);
        \course_modinfo::clear_instance_cache();
        $this->setUser($user);

        // The learner has finished everything left to them, and the ring agrees.
        $bar = calculator::course_completion_percentage((int) $course->id, (int) $user->id);
        $ring = calculator::get_course_section_progress($course->id)['sections'][1]['percentage'];

        $this->assertSame(100, $bar);
        $this->assertSame(100, (int) $ring);
    }

    /**
     * An activity the learner cannot open YET still counts.
     *
     * This is the line between "what is left to do" and "what is open right now", and both the
     * bar and the rings must answer the first. A date-restricted activity shown greyed is
     * uservisible = false, so counting only what is openable would let the number reach a
     * finished-looking 100% while released-later work remained - and then walk BACKWARDS on the
     * release date. calculator_visibility_test covers the rest of that rule; this pins the bar.
     *
     * The first assertion is the control: it proves the third activity counts before the
     * restriction, so the second measures the restriction rather than the fixture.
     *
     * @return void
     */
    public function test_an_activity_restricted_until_later_still_counts(): void {
        global $CFG;

        $this->resetAfterTest();
        $CFG->enableavailability = 1;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1, 'numsections' => 1]);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $cmids = $this->create_tracked_activities($course, 3);
        $this->complete_activities($course, (int) $user->id, array_slice($cmids, 0, 1));
        $this->setUser($user);

        // Control: all three count, one of them complete.
        $this->assertSame(33, calculator::course_completion_percentage((int) $course->id, (int) $user->id));

        $this->restrict_but_show($cmids[2]);
        rebuild_course_cache($course->id, true);
        \course_modinfo::clear_instance_cache();

        // Still one of three: the work has not gone away, it has only not opened yet.
        $this->assertSame(33, calculator::course_completion_percentage((int) $course->id, (int) $user->id));
    }

    /**
     * A course with completion switched off, or nothing trackable in it, reports zero.
     *
     * @return void
     */
    public function test_nothing_to_measure_reports_zero(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $off = $this->getDataGenerator()->create_course(['enablecompletion' => 0]);
        $empty = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->getDataGenerator()->enrol_user($user->id, $off->id, 'student');
        $this->getDataGenerator()->enrol_user($user->id, $empty->id, 'student');
        $this->setUser($user);

        $this->assertSame(0, calculator::course_completion_percentage((int) $off->id, (int) $user->id));
        $this->assertSame(0, calculator::course_completion_percentage((int) $empty->id, (int) $user->id));
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
     * Puts a module into the state core's asynchronous deletion leaves it in.
     *
     * Built directly rather than through the delete API because that API is a different function
     * on each supported branch - see the twin helper in calculator_progress_test - and these two
     * writes are the whole of the state under test.
     *
     * @param int $courseid The course the module belongs to.
     * @param int $cmid The course module scheduled for deletion.
     * @return void
     */
    private function schedule_deletion(int $courseid, int $cmid): void {
        global $DB;

        $DB->set_field('course_modules', 'deletioninprogress', '1', ['id' => $cmid]);
        rebuild_course_cache($courseid, true);
        \course_modinfo::clear_instance_cache();
    }

    /**
     * Restricts a module by a future date, leaving it shown greyed rather than hidden.
     *
     * showc true is what makes core count the module and the section ring skip it, which is the
     * whole point of the fixture.
     *
     * @param int $cmid The course module to restrict.
     * @return void
     */
    private function restrict_but_show(int $cmid): void {
        global $DB;

        $availability = json_encode([
            'op' => '&',
            'c' => [
                [
                    'type' => 'date',
                    'd' => '>=',
                    't' => time() + DAYSECS,
                ],
            ],
            'showc' => [true],
        ]);
        $DB->set_field('course_modules', 'availability', $availability, ['id' => $cmid]);
    }
}
