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
     * Writes the singleactivity format's 'activitytype' option straight into
     * course_format_options and rebuilds the course cache.
     *
     * create_course()'s 'activitytype' key is silently dropped: it flows through
     * base::update_format_options() -> validate_format_options() ->
     * course_format_options(true), which filters the option's legal values through
     * has_capability("mod/{$activity}:addinstance", ...). create_course() runs before
     * setUser(), so $USER->id is still 0 at that point, and has_capability() returns
     * false unconditionally for user id 0 on any write/risky capability (mod/page:addinstance
     * is both) - before any role lookup. The option is therefore never in the allowed
     * select values, validate_format_options() drops it, and the course keeps the site
     * default (forum). Calling setAdminUser() first sidesteps the capability gate but not
     * a second trap: format_singleactivity::course_format_options() caches its
     * capability-filtered list in a function-static that resetAfterTest() never clears, so
     * in a shared CI process the outcome would depend on whichever test ran first. Writing
     * the row directly and rebuilding is immune to both: rebuild_course_cache() calls
     * core_courseformat\base::reset_course_cache(), which clears the per-course format
     * instance (and its formatoptions cache) so the next get_format_options() call rereads
     * this row from the database.
     *
     * Table shape verified in course/format/classes/base.php (update_format_options(),
     * the $DB->insert_record('course_format_options', ...) call) and in
     * lib/db/install.xml: columns are id, courseid, format, sectionid, name, value; a
     * course-level (non-section) option is stored with sectionid = 0, matching what
     * update_format_options() passes when its own $sectionid argument is null.
     *
     * @param int $courseid the course to set the option on
     * @param string $activitytype the modname to store, e.g. 'page'
     * @return void
     */
    private function set_singleactivity_type(int $courseid, string $activitytype): void {
        global $DB;

        $DB->insert_record('course_format_options', (object) [
            'courseid' => $courseid,
            'format' => 'singleactivity',
            'sectionid' => 0,
            'name' => 'activitytype',
            'value' => $activitytype,
        ]);
        rebuild_course_cache($courseid, true);
    }

    /**
     * A single-activity course names its activity, tracked or not.
     *
     * @return void
     */
    public function test_single_activity_format_resolves_to_the_activity(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([
            'format' => 'singleactivity',
            'enablecompletion' => 1,
        ]);
        $this->set_singleactivity_type((int) $course->id, 'page');
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
            'enablecompletion' => 0,
        ]);
        $this->set_singleactivity_type((int) $course->id, 'page');
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
            'enablecompletion' => 1,
        ]);
        $this->set_singleactivity_type((int) $course->id, 'page');
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
     * The decoy is also given manual completion tracking, so two modules are trackable and
     * the count-based fallback branch cannot land on CARDMODE_ACTIVITY by itself - only
     * resolve_main_activity() actually matching the configured 'activitytype' can.
     *
     * @return void
     */
    public function test_single_activity_format_ignores_a_leftover_of_another_type(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([
            'format' => 'singleactivity',
            'enablecompletion' => 1,
        ]);
        $this->set_singleactivity_type((int) $course->id, 'page');
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->getDataGenerator()->create_module('url', [
            'course' => $course->id,
            'name' => 'Leftover link from the old format',
            'completion' => COMPLETION_TRACKING_MANUAL,
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
