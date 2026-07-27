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
 * Tests the resolver that decides which shape a course card takes.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\calculator::resolve_card_shape
 */
final class calculator_card_shape_test extends \advanced_testcase {
    /**
     * A single-activity course names its activity, tracked or not.
     *
     * @return void
     */
    public function test_single_activity_format_resolves_to_the_activity(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([
            'format' => 'singleactivity',
            'activitytype' => 'page',
            'enablecompletion' => 1,
        ]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Submit portfolio',
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $this->setUser($user);
        $shape = calculator::resolve_card_shape((int) $course->id, (int) $user->id);

        $this->assertSame(constants::CARDMODE_ACTIVITY, $shape['mode']);
        $this->assertSame('Submit portfolio', $shape['activity']['name']);
        $this->assertTrue($shape['activity']['tracked']);
        $this->assertNull($shape['section']);
    }

    /**
     * The format branch survives completion being switched off, which the count cannot.
     *
     * @return void
     */
    public function test_single_activity_format_without_completion_still_names_it(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([
            'format' => 'singleactivity',
            'activitytype' => 'page',
            'enablecompletion' => 0,
        ]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Read the brief',
        ]);

        $this->setUser($user);
        $shape = calculator::resolve_card_shape((int) $course->id, (int) $user->id);

        $this->assertSame(constants::CARDMODE_ACTIVITY, $shape['mode']);
        $this->assertSame('Read the brief', $shape['activity']['name']);
        $this->assertFalse($shape['activity']['tracked']);
        $this->assertFalse($shape['activity']['completed']);
    }

    /**
     * A completed activity is reported as completed.
     *
     * This branch decides whether the card reads "Completed" or "Not completed", and it
     * is the coverage that `tests/calculator_single_activity_test.php` gained in review
     * before Task 4 deletes it — it must not be lost with the file.
     *
     * @return void
     */
    public function test_completed_activity_is_reported_completed(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([
            'format' => 'singleactivity',
            'activitytype' => 'page',
            'enablecompletion' => 1,
        ]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Submit portfolio',
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $completion = new \completion_info(get_course((int) $course->id));
        $cm = get_coursemodule_from_id('page', (int) $page->cmid, 0, false, MUST_EXIST);
        $completion->update_state($cm, COMPLETION_COMPLETE, (int) $user->id);

        $this->setUser($user);
        $shape = calculator::resolve_card_shape((int) $course->id, (int) $user->id);

        $this->assertTrue($shape['activity']['completed']);
        $this->assertSame((int) $page->cmid, $shape['activity']['cmid']);
    }

    /**
     * A leftover module of a different type must not steal the slot: the resolver names
     * the module matching the format's own 'activitytype' option, not merely the first
     * user-visible module it meets. The url module is created before the page module, so
     * it sits earlier in section 0's sequence - the exact ordering the pre-fix code
     * (which walked every module and returned the first match) would have picked wrong.
     *
     * @return void
     */
    public function test_single_activity_format_ignores_a_leftover_of_another_type(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([
            'format' => 'singleactivity',
            'activitytype' => 'page',
            'enablecompletion' => 1,
        ]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->getDataGenerator()->create_module('url', [
            'course' => $course->id,
            'name' => 'Leftover link from the old format',
        ]);
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Submit portfolio',
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $this->setUser($user);
        $shape = calculator::resolve_card_shape((int) $course->id, (int) $user->id);

        $this->assertSame(constants::CARDMODE_ACTIVITY, $shape['mode']);
        $this->assertSame('Submit portfolio', $shape['activity']['name']);
    }

    /**
     * A course that boils down to one tracked activity takes the same shape.
     *
     * @return void
     */
    public function test_one_tracked_activity_resolves_to_the_activity(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([
            'numsections' => 3,
            'enablecompletion' => 1,
        ]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'The only tracked thing',
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Untracked reading',
        ]);

        $this->setUser($user);
        $shape = calculator::resolve_card_shape((int) $course->id, (int) $user->id);

        $this->assertSame(constants::CARDMODE_ACTIVITY, $shape['mode']);
        $this->assertSame('The only tracked thing', $shape['activity']['name']);
    }

    /**
     * One section with several tracked activities takes the section shape, and reports
     * that its name was generated rather than authored.
     *
     * @return void
     */
    public function test_single_generated_section_resolves_to_the_section(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([
            'numsections' => 0,
            'enablecompletion' => 1,
        ]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        foreach (['First', 'Second'] as $name) {
            $this->getDataGenerator()->create_module('page', [
                'course' => $course->id,
                'name' => $name,
                'completion' => COMPLETION_TRACKING_MANUAL,
            ]);
        }

        $this->setUser($user);
        $shape = calculator::resolve_card_shape((int) $course->id, (int) $user->id);

        $this->assertSame(constants::CARDMODE_SECTION, $shape['mode']);
        $this->assertFalse($shape['section']['hasownname']);
        $this->assertSame('', $shape['section']['name']);
        $this->assertStringContainsString('/course/section.php', $shape['section']['url']);
        $this->assertNull($shape['activity']);
    }

    /**
     * An authored section name is reported and carried.
     *
     * @return void
     */
    public function test_authored_section_name_is_carried(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([
            'numsections' => 0,
            'enablecompletion' => 1,
        ]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        foreach (['First', 'Second'] as $name) {
            $this->getDataGenerator()->create_module('page', [
                'course' => $course->id,
                'name' => $name,
                'completion' => COMPLETION_TRACKING_MANUAL,
            ]);
        }
        $DB->set_field('course_sections', 'name', 'Main pathway', ['course' => $course->id, 'section' => 0]);
        rebuild_course_cache((int) $course->id, true);

        $this->setUser($user);
        $shape = calculator::resolve_card_shape((int) $course->id, (int) $user->id);

        $this->assertSame(constants::CARDMODE_SECTION, $shape['mode']);
        $this->assertTrue($shape['section']['hasownname']);
        $this->assertSame('Main pathway', $shape['section']['name']);
    }

    /**
     * Several sections keep the timeline.
     *
     * @return void
     */
    public function test_several_sections_resolve_to_the_timeline(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([
            'numsections' => 3,
            'enablecompletion' => 1,
        ]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        foreach (['First', 'Second'] as $name) {
            $this->getDataGenerator()->create_module('page', [
                'course' => $course->id,
                'name' => $name,
                'completion' => COMPLETION_TRACKING_MANUAL,
            ]);
        }

        $this->setUser($user);
        $shape = calculator::resolve_card_shape((int) $course->id, (int) $user->id);

        $this->assertSame(constants::CARDMODE_TIMELINE, $shape['mode']);
        $this->assertNull($shape['activity']);
        $this->assertNull($shape['section']);
    }
}
