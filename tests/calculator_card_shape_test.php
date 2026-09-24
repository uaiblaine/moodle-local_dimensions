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
     * create_course() silently drops an 'activitytype' key: the format filters the option's
     * legal values through has_capability('mod/<type>:addinstance'), which always refuses a
     * write capability to user id 0, and no user is set yet. The row already exists, because
     * {@see \core_courseformat\base::update_format_options()} stored the site default while the
     * course was created, so this updates it; an insert would hit the 'formatoption' unique
     * index. rebuild_course_cache() resets the cached format instance so the new value is read.
     *
     * @param int $courseid The course id.
     * @param string $activitytype The modname to store, e.g. 'page'.
     * @return void
     */
    private function set_singleactivity_type(int $courseid, string $activitytype): void {
        global $DB;

        $DB->set_field('course_format_options', 'value', $activitytype, [
            'courseid' => $courseid,
            'format' => 'singleactivity',
            'sectionid' => 0,
            'name' => 'activitytype',
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
     * This branch decides whether the card reads "Completed" or "Not completed".
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
     * the module matching the format's 'activitytype' option, not the first visible module.
     * The url decoy is created first, so it sits earlier in section 0's sequence, and it is
     * tracked too, so the count-based fallback cannot reach CARDMODE_ACTIVITY on its own;
     * only resolve_main_activity() matching 'activitytype' can.
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
     * The activity is not yet completed, so this also pins that completed reads false rather
     * than defaulting to true, and that the URL is non-empty.
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
        $this->assertFalse($shape['activity']['completed']);
        $this->assertNotSame('', $shape['activity']['url']);
    }

    /**
     * An activity released later is workload, so the course no longer looks like one activity.
     *
     * The shape resolver must count the same activities as the percentages: one open activity
     * beside one released later is two pieces of work, so a card showing the open one alone
     * (ticked once done) would contradict a bar reading 50%.
     *
     * The first assertion is the control: the same course without the second activity is still
     * an activity card, so the second assertion measures that activity's arrival.
     *
     * @return void
     */
    public function test_an_activity_released_later_stops_the_course_looking_like_one_activity(): void {
        global $CFG;

        $this->resetAfterTest();
        $CFG->enableavailability = 1;
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1, 'numsections' => 1]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Open now',
            'section' => 1,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $this->setUser($user);

        // Control: one piece of work, and it is an activity card.
        $shape = calculator::resolve_card_shape((int) $course->id, (int) $user->id);
        $this->assertSame(constants::CARDMODE_ACTIVITY, $shape['mode']);
        $this->assertSame('Open now', $shape['activity']['name']);

        $this->setAdminUser();
        $later = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Opens next week',
            'section' => 1,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $this->restrict_until_tomorrow((int) $later->cmid);
        rebuild_course_cache($course->id, true);
        \course_modinfo::clear_instance_cache();
        $this->setUser($user);

        // Two pieces of work now, so the card must stop reporting a single activity.
        $shape = calculator::resolve_card_shape((int) $course->id, (int) $user->id);
        $this->assertNotSame(constants::CARDMODE_ACTIVITY, $shape['mode']);
        $this->assertNull($shape['activity']);
    }

    /**
     * A course whose only work has not opened yet is not offered as an activity card.
     *
     * The activity shape renders a button to the activity, so it may only name one the learner
     * can open now. Work released later still counts for the percentages, so without that check
     * a course holding exactly one such activity would get a dead button.
     *
     * The control is the same course before the restriction is applied.
     *
     * @return void
     */
    public function test_a_course_of_one_unopened_activity_is_not_an_activity_card(): void {
        global $CFG;

        $this->resetAfterTest();
        $CFG->enableavailability = 1;
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1, 'numsections' => 1]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $only = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Opens next week',
            'section' => 1,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $this->setUser($user);

        // Control: while it is open, this really is a one-activity course.
        $shape = calculator::resolve_card_shape((int) $course->id, (int) $user->id);
        $this->assertSame(constants::CARDMODE_ACTIVITY, $shape['mode']);

        $this->setAdminUser();
        $this->restrict_until_tomorrow((int) $only->cmid);
        rebuild_course_cache($course->id, true);
        \course_modinfo::clear_instance_cache();
        $this->setUser($user);

        // Still one piece of work, but nothing the card may link to.
        $shape = calculator::resolve_card_shape((int) $course->id, (int) $user->id);
        $this->assertNotSame(constants::CARDMODE_ACTIVITY, $shape['mode']);
        $this->assertNull($shape['activity']);
    }

    /**
     * Puts a future date restriction on an activity, leaving it shown greyed.
     *
     * Shown rather than hidden on purpose: a hidden restriction takes the activity out of the
     * learner's workload, while a shown one keeps it in as work the card has to account for.
     *
     * @param int $cmid The activity to restrict.
     * @return void
     */
    private function restrict_until_tomorrow(int $cmid): void {
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

    /**
     * A single untracked module leaves no trackable candidate, so the course does not take the
     * activity shape. Covers the per-module COMPLETION_TRACKING_NONE guard in
     * counts_towards_progress(); the next test covers the course-level guard.
     *
     * @return void
     */
    public function test_a_lone_untracked_module_does_not_resolve_to_the_activity(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([
            'numsections' => 3,
            'enablecompletion' => 1,
        ]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Untracked',
            'completion' => COMPLETION_TRACKING_NONE,
        ]);

        $this->setUser($user);
        $shape = calculator::resolve_card_shape((int) $course->id, (int) $user->id);

        $this->assertNotSame(constants::CARDMODE_ACTIVITY, $shape['mode']);
        $this->assertNull($shape['activity']);
    }

    /**
     * Course-level completion switched off: nothing is trackable, so the non-format path
     * cannot resolve to the activity shape either. Unlike
     * test_single_activity_format_without_completion_still_names_it(), this course is not in
     * the singleactivity format, so resolve_main_activity() cannot short-circuit and this
     * covers collect_trackable_cms()'s course-level completion guard.
     *
     * @return void
     */
    public function test_completion_disabled_does_not_resolve_to_the_activity(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([
            'numsections' => 3,
            'enablecompletion' => 0,
        ]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Anything',
        ]);

        $this->setUser($user);
        $shape = calculator::resolve_card_shape((int) $course->id, (int) $user->id);

        $this->assertNotSame(constants::CARDMODE_ACTIVITY, $shape['mode']);
        $this->assertNull($shape['activity']);
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
        $this->assertTrue($shape['section']['tracked']);
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

    /**
     * The shape resolver and the progress walk agree about a hidden section's activities.
     *
     * collect_trackable_cms() walks the whole course without filtering by section, while
     * get_course_section_progress() skips hidden sections outright. They agree only because a
     * module in a hidden section is never uservisible to a student: the topics format allows
     * stealth ("available but not shown") only in section 0 and visible sections. If that
     * changed, the card could name an activity the progress walk never counts.
     *
     * @return void
     */
    public function test_hidden_section_activity_is_invisible_to_both_paths(): void {
        global $CFG;

        $this->resetAfterTest();
        $CFG->allowstealth = 1;

        $course = $this->getDataGenerator()->create_course([
            'numsections' => 2,
            'enablecompletion' => 1,
        ]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // The course's only tracked activity, in a section the teacher then hides.
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'section' => 1,
            'name' => 'Hidden away',
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        /* Hidden through sectionactions::update(), which 4.5, 5.1 and 5.2 all ship with the same
           signature. set_section_visible() is deprecated on 5.2 (MDL-86861) and its replacement,
           sectionactions::set_visibility(), does not exist before 5.2; both end in this call, which
           also hides the section's modules. */
        \core_courseformat\formatactions::section($course->id)->update(
            get_fast_modinfo($course->id)->get_section_info(1),
            ['visible' => 0]
        );

        // The state the rest of the test depends on: the section and the activity in it are hidden.
        $modinfo = get_fast_modinfo($course->id);
        $this->assertSame(0, (int) $modinfo->get_section_info(1)->visible);
        $this->assertSame(0, (int) $modinfo->get_cm($page->cmid)->visible);

        $this->setUser($user);
        $shape = calculator::resolve_card_shape((int) $course->id, (int) $user->id);
        $data = calculator::get_course_section_progress((int) $course->id);

        // The resolver must not name it.
        $this->assertNull($shape['activity']);

        /* And the progress walk neither lists the hidden section nor counts its activity anywhere. The two
           visible sections are the control: the walk did run, and it still lists what it should. */
        $hiddenurl = (new \moodle_url('/course/section.php', ['id' => $modinfo->get_section_info(1)->id]))->out(false);
        $this->assertCount(2, $data['sections']);
        $this->assertNotContains($hiddenurl, array_column($data['sections'], 'url'));
        foreach ($data['sections'] as $section) {
            $this->assertFalse($section['has_activities']);
        }
    }
}
