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

    /**
     * The section percentage reports the share of its activities the user has completed.
     *
     * @return void
     */
    public function test_section_percentage_counts_completed_activities(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1, 'numsections' => 1]);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $cms = $this->create_tracked_activities($course, 4);
        $this->setUser($user);
        $this->complete_activities($course, $user->id, array_slice($cms, 0, 1));

        $section = $this->find_section($course->id, 1);

        $this->assertTrue($section['has_activities']);
        $this->assertSame(25, (int) $section['percentage']);
    }

    /**
     * A section one activity short of finished never reports a round hundred.
     *
     * 199 of 200 rounds to 100, and get_course_progress reads any 100 as "completed" and
     * swaps the ring for the done icon - so the card claimed a finished section while an
     * activity was still open.
     *
     * @return void
     */
    public function test_section_one_activity_short_never_reports_a_hundred(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1, 'numsections' => 1]);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $cms = $this->create_tracked_activities($course, 200);
        $this->setUser($user);
        $this->complete_activities($course, $user->id, array_slice($cms, 0, 199));

        $section = $this->find_section($course->id, 1);

        $this->assertSame(99, (int) $section['percentage']);
    }

    /**
     * A subsection scheduled for deletion stops contributing its activities to its parent.
     *
     * Deleting a subsection flags only the subsection module itself: every activity inside its
     * delegated section keeps deletioninprogress = 0 and uservisible = true until
     * mod_subsection's delete_instance() runs in the adhoc task, while the course page
     * withdraws the whole subsection the moment it is flagged. The parent's ring therefore went
     * on counting activities the learner could no longer reach - until the next cron run, or for
     * good on a site whose delete task keeps failing.
     *
     * The first assertion is the control. It proves the cascade is switched on for this course,
     * so the second cannot pass by the subsection's activities never having been counted at all.
     *
     * @return void
     */
    public function test_subsection_scheduled_for_deletion_stops_counting(): void {
        $this->resetAfterTest();

        /* Subsections ship on every supported branch but are disabled by default on 4.5, where
           get_course_mods() then filters the module type out of modinfo entirely. Core's own
           tests enable it the same way (lib/tests/modinfolib_test.php). */
        $manager = \core_plugin_manager::resolve_plugininfo_class('mod');
        $manager::enable_plugin('subsection', 1);

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1, 'numsections' => 1]);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        // Two activities directly in section 1, and two more inside a subsection of it.
        $top = $this->create_tracked_activities($course, 2);
        $subcmid = $this->create_subsection_with_activities($course, 2);

        $this->setUser($user);
        $this->complete_activities($course, $user->id, array_slice($top, 0, 1));

        // Control: the subsection's two activities do reach the parent's count, so one of four.
        $this->assertSame(25, (int) $this->find_section($course->id, 1)['percentage']);

        $this->schedule_deletion($course, $subcmid);

        // Only the two activities outside the subsection count now, one of them complete.
        $this->assertSame(50, (int) $this->find_section($course->id, 1)['percentage']);
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
     * Returns one section's row from the calculator payload.
     *
     * @param int $courseid The course to calculate.
     * @param int $index The zero-based position of the section in the returned list.
     * @return array The section row.
     */
    private function find_section(int $courseid, int $index): array {
        $data = calculator::get_course_section_progress($courseid);
        $this->assertArrayHasKey($index, $data['sections']);

        return $data['sections'][$index];
    }

    /**
     * Creates a subsection in section 1 holding tracked activities.
     *
     * @param \stdClass $course The course to add it to.
     * @param int $count How many activities to place inside it.
     * @return int The subsection's own course module id.
     */
    private function create_subsection_with_activities(\stdClass $course, int $count): int {
        $subsection = $this->getDataGenerator()->create_module(
            'subsection',
            [
                'course' => $course->id,
                'section' => 1,
            ]
        );
        $cmid = (int) $subsection->cmid;

        \course_modinfo::clear_instance_cache();
        $delegated = get_fast_modinfo($course->id)->get_cm($cmid)->get_delegated_section_info();

        for ($i = 0; $i < $count; $i++) {
            $this->getDataGenerator()->create_module(
                'page',
                [
                    'course' => $course->id,
                    'section' => $delegated->sectionnum,
                    'completion' => COMPLETION_TRACKING_MANUAL,
                ]
            );
        }
        \course_modinfo::clear_instance_cache();

        return $cmid;
    }

    /**
     * Puts a module into the state core's asynchronous deletion leaves it in.
     *
     * The state is built directly rather than through the delete API because that API is a
     * different function on each supported branch - course_delete_module() is current on 5.0
     * and 5.1 and deprecated on 5.2 in favour of formatactions::cm()->delete() (MDL-86856).
     * Both end in the same two writes core's delete_async() performs, and those two writes are
     * the whole of the state under test: the flag, and a cleared course cache.
     *
     * @param \stdClass $course The course the module belongs to.
     * @param int $cmid The course module scheduled for deletion.
     * @return void
     */
    private function schedule_deletion(\stdClass $course, int $cmid): void {
        global $DB;

        $DB->set_field('course_modules', 'deletioninprogress', '1', ['id' => $cmid]);
        rebuild_course_cache($course->id, true);
        \course_modinfo::clear_instance_cache();
    }
}
