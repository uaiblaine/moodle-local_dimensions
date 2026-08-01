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

namespace local_dimensions\external;

use core_external\external_api;

/**
 * Tests for the tracker's course progress web service.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\external\get_course_progress
 */
final class get_course_progress_test extends \advanced_testcase {
    /**
     * Run the service for one course and clean the payload through the returns structure.
     *
     * clean_returnvalue strips keys the structure does not declare, silently, so only the
     * cleaned payload proves the allowlist actually carries what execute() built.
     *
     * @param int $courseid The course id.
     * @return array The cleaned row for that course.
     */
    private function cleaned_row_for(int $courseid): array {
        $result = external_api::clean_returnvalue(
            get_course_progress::execute_returns(),
            get_course_progress::execute([$courseid])
        );

        return $result[0];
    }

    /**
     * Link a fresh competency to the given courses, so the service will answer about them.
     *
     * The service gates on helper::readable_competency_courses(), which recognises only a
     * course carrying at least one competency link - the tracker itself lists no other kind.
     * Creating the framework needs manage rights, so this runs as admin and logs out again;
     * every caller sets its own user immediately afterwards.
     *
     * @param int ...$courseids The courses to link.
     * @return void
     */
    private function link_competency(int ...$courseids): void {
        $this->setAdminUser();

        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework();
        $competency = $ccg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        foreach ($courseids as $courseid) {
            \core_competency\api::add_competency_to_course($courseid, (int) $competency->get('id'));
        }

        $this->setUser(null);
    }

    /**
     * A course with completion tracking off returns cleanly, with no notices.
     *
     * Regression test for the payload guard: the calculator returns only the enabled flag in
     * that case, so every other key has to be defaulted before the returns structure sees it.
     *
     * @return void
     */
    public function test_execute_handles_a_completion_disabled_course(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 0]);
        $this->getDataGenerator()->enrol_user((int) $user->id, (int) $course->id, 'student');
        $this->link_competency((int) $course->id);
        $this->setUser($user);

        $row = $this->cleaned_row_for((int) $course->id);

        $this->assertFalse($row['enabled']);
        $this->assertFalse($row['locked']);
        $this->assertSame([], $row['sections']);
        $this->assertArrayNotHasKey('activity', $row);
    }

    /**
     * A course that boils down to one trackable activity carries it; a busier one does not.
     *
     * @return void
     */
    public function test_execute_returns_the_activity_only_for_a_single_activity_course(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->libdir . '/completionlib.php');
        $this->setAdminUser();
        set_config('enablecompletion', 1);

        $single = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $busy = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $single->id,
            'name' => 'Weekly reflection',
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        foreach (['First task', 'Second task'] as $name) {
            $this->getDataGenerator()->create_module('page', [
                'course' => $busy->id,
                'name' => $name,
                'completion' => COMPLETION_TRACKING_MANUAL,
            ]);
        }

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int) $user->id, (int) $single->id, 'student');
        $this->getDataGenerator()->enrol_user((int) $user->id, (int) $busy->id, 'student');
        $this->link_competency((int) $single->id, (int) $busy->id);
        $this->setUser($user);

        $row = $this->cleaned_row_for((int) $single->id);
        $this->assertArrayHasKey('activity', $row);
        $this->assertSame('Weekly reflection', $row['activity']['name']);
        $this->assertStringContainsString('/mod/page/view.php?id=' . $page->cmid, $row['activity']['url']);
        $this->assertFalse($row['activity']['completed']);

        // An untouched activity that is now complete flips the flag the card reads.
        $completion = new \completion_info(get_course((int) $single->id));
        $completion->update_state(
            get_coursemodule_from_id('page', (int) $page->cmid),
            COMPLETION_COMPLETE,
            (int) $user->id
        );
        $this->assertTrue($this->cleaned_row_for((int) $single->id)['activity']['completed']);

        $this->assertArrayNotHasKey('activity', $this->cleaned_row_for((int) $busy->id));
    }

    /**
     * A locked course with self-enrolment open says so, and dates its own opening.
     *
     * @return void
     */
    public function test_execute_reports_self_enrolment_on_a_locked_course(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['startdate' => time() + WEEKSECS]);
        $self = enrol_get_plugin('self');
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'self'], '*', MUST_EXIST);
        $self->update_status($instance, ENROL_INSTANCE_ENABLED);

        $user = $this->getDataGenerator()->create_user();
        $this->link_competency((int) $course->id);
        $this->setUser($user);

        $row = $this->cleaned_row_for((int) $course->id);

        $this->assertTrue($row['locked']);
        $this->assertTrue($row['can_self_enrol']);
        $this->assertTrue($row['is_future_date']);
    }

    /**
     * Writes the singleactivity format's 'activitytype' option directly, exactly like
     * calculator_card_shape_test::set_singleactivity_type() - see that method's docblock for
     * why create_course()'s own 'activitytype' key is silently dropped and cannot be used
     * instead.
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
     * A single-activity course reports the activity shape and names its activity.
     *
     * Without set_singleactivity_type() the course keeps the site's default activitytype
     * (forum), so resolve_main_activity() finds no match and returns null - this test would
     * then pass through the count-based fallback branch instead of the format branch its name
     * and this comment claim to cover. The second, differently-typed module (url) is also
     * tracked, so with two trackable candidates that fallback branch cannot land on
     * CARDMODE_ACTIVITY by itself either - only the format match can.
     *
     * @return void
     */
    public function test_execute_reports_the_activity_card_shape(): void {
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
        $this->getDataGenerator()->create_module('url', [
            'course' => $course->id,
            'name' => 'Leftover link from the old format',
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $this->link_competency((int) $course->id);
        $this->setUser($user);
        $row = $this->cleaned_row_for((int) $course->id);

        $this->assertSame(\local_dimensions\constants::CARDMODE_ACTIVITY, $row['cardmode']);
        $this->assertSame('Submit portfolio', $row['activity']['name']);
        $this->assertTrue($row['activity']['tracked']);
    }

    /**
     * A one-section course reports the section shape and carries the section's URL.
     *
     * @return void
     */
    public function test_execute_reports_the_section_card_shape(): void {
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

        $this->link_competency((int) $course->id);
        $this->setUser($user);
        $row = $this->cleaned_row_for((int) $course->id);

        $this->assertSame(\local_dimensions\constants::CARDMODE_SECTION, $row['cardmode']);
        $this->assertFalse($row['section']['hasownname']);
        $this->assertStringContainsString('/course/section.php', $row['section']['url']);
        $this->assertTrue($row['section']['tracked']);
    }

    /**
     * A hidden course tells an ordinary caller nothing, not even its section names.
     *
     * The service takes a raw id list from the client and the capability gating it is held by
     * every authenticated user, so the only thing standing between a caller and the structure
     * of a course they cannot see is the per-course gate. The course here is linked and
     * completion-tracked, so without that gate the row would carry its section names and its
     * start date.
     *
     * @return void
     */
    public function test_execute_withholds_the_structure_of_a_hidden_course(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([
            'visible' => 0,
            'enablecompletion' => 1,
            'startdate' => time() - WEEKSECS,
        ]);
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Confidential briefing',
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $this->link_competency((int) $course->id);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $row = $this->cleaned_row_for((int) $course->id);

        $this->assertTrue($row['locked']);
        $this->assertFalse($row['enabled']);
        $this->assertSame([], $row['sections']);
        $this->assertSame('', $row['formatted_start_date']);
        $this->assertSame('', $row['course_url']);
    }

    /**
     * A course carrying no competency link is none of this service's business.
     *
     * The tracker builds its card list from competency_coursecomp, so an id that is not in
     * there did not come from the page - and gets the same silent, locked row a hidden course
     * gets, which is what keeps the two cases indistinguishable to a caller probing ids.
     *
     * @return void
     */
    public function test_execute_withholds_the_structure_of_an_unlinked_course(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Unrelated activity',
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($user);

        $row = $this->cleaned_row_for((int) $course->id);

        $this->assertTrue($row['locked']);
        $this->assertSame([], $row['sections']);
        $this->assertSame('', $row['formatted_start_date']);
    }
}
