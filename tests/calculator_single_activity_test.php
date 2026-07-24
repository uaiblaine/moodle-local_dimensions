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
 * Tests the single-trackable-activity resolver used by the plan's course cards.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\calculator::resolve_single_activity
 */
final class calculator_single_activity_test extends \advanced_testcase {
    /**
     * Exactly one tracked activity: the card gets its name and state instead of a bar.
     *
     * @return void
     */
    public function test_one_tracked_activity_is_returned(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Weekly reflection',
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $this->setUser($user);
        $activity = calculator::resolve_single_activity((int) $course->id, (int) $user->id);

        $this->assertIsArray($activity);
        $this->assertSame('Weekly reflection', $activity['name']);
        $this->assertFalse($activity['completed']);
        $this->assertNotSame('', $activity['url']);
    }

    /**
     * Two tracked activities: there is a sequence, so the card keeps its progress bar.
     *
     * @return void
     */
    public function test_two_tracked_activities_return_null(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        foreach (['First', 'Second'] as $name) {
            $this->getDataGenerator()->create_module('page', [
                'course' => $course->id,
                'name' => $name,
                'completion' => COMPLETION_TRACKING_MANUAL,
            ]);
        }

        $this->setUser($user);

        $this->assertNull(calculator::resolve_single_activity((int) $course->id, (int) $user->id));
    }

    /**
     * A lone module with completion switched off is not trackable, so there is nothing to show.
     *
     * @return void
     */
    public function test_untracked_activity_returns_null(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Untracked',
            'completion' => COMPLETION_TRACKING_NONE,
        ]);

        $this->setUser($user);

        $this->assertNull(calculator::resolve_single_activity((int) $course->id, (int) $user->id));
    }

    /**
     * Course-level completion off: nothing is trackable at all.
     *
     * @return void
     */
    public function test_completion_disabled_returns_null(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 0]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Anything',
        ]);

        $this->setUser($user);

        $this->assertNull(calculator::resolve_single_activity((int) $course->id, (int) $user->id));
    }

    /**
     * A completed activity flips the flag the card reads to true.
     *
     * @return void
     */
    public function test_completed_activity_returns_completed_true(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->libdir . '/completionlib.php');
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Weekly reflection',
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $this->setUser($user);
        $completion = new \completion_info(get_course((int) $course->id));
        $completion->update_state(
            get_coursemodule_from_id('page', (int) $page->cmid),
            COMPLETION_COMPLETE,
            (int) $user->id
        );

        $activity = calculator::resolve_single_activity((int) $course->id, (int) $user->id);

        $this->assertIsArray($activity);
        $this->assertTrue($activity['completed']);
    }
}
