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
     * Pins the Moodle 4.5 core defect {@see calculator::course_completion_percentage()} works
     * around: core keeps a completed module that is being deleted in its numerator, so deleting
     * one of two completed activities out of four would read 67% instead of 33%.
     *
     * The first assertion is the control: both completed activities count before the deletion,
     * so the second cannot pass because the deleted one never counted.
     *
     * Only the 4.5 run discriminates: core on 5.1.4+ and 5.2 also answers 33.
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
     * On Moodle 4.5 core's denominator, completion_info::get_activities(), keeps hidden tracked
     * activities, so the learner could never reach 100% and the bar would disagree with the
     * section ring. 5.1 and 5.2 exclude them through get_user_activities_with_completion(), which
     * the calculator reproduces; the test checks that the bar and the ring agree.
     *
     * The first assertion is the control: the second activity counts before it is hidden.
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
     * An activity the learner cannot open yet still counts.
     *
     * The bar and the rings measure what is left to do, not only what is open now. A
     * date-restricted activity shown greyed is not uservisible; leaving it out would show 100%
     * while work remains and then drop back on the release date. calculator_visibility_test
     * covers the rest of that rule; this pins the bar.
     *
     * The first assertion is the control: the third activity counts before the restriction.
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
     * Built directly rather than through the delete API, which differs by branch (see
     * calculator_progress_test::schedule_deletion()); the flag and a rebuilt course cache are
     * the whole state under test.
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
     * showc true keeps the module listed (greyed) on the course page, so it stays in the
     * learner's workload although it cannot be opened yet; that is the point of the fixture.
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
