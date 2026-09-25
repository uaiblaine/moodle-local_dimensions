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
     * The completion-disabled branch must still produce every key the returns structure requires.
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
     * A lodged application reaches the tracker card as its own state, not as self-enrolment.
     *
     * The card body renders three mutually exclusive shapes off can_self_enrol and is_pending,
     * so both flags have to arrive, and the precedence between them has to hold.
     *
     * Skipped where enrol_apply is not installed, which includes every Moodle 4.5 site:
     * enrol_apply supports Moodle 5.1 and later only.
     *
     * @return void
     */
    public function test_execute_reports_a_pending_application_on_a_locked_course(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $plugin = enrol_get_plugin('apply');
        if (!$plugin || !is_callable([$plugin, 'allow_apply'])) {
            $this->markTestSkipped('enrol_apply is not installed on this site.');
        }
        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        $course = $this->getDataGenerator()->create_course();
        $instanceid = $plugin->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'customint3' => 0,
            'customint5' => 0,
            'customint6' => 1,
        ]);
        $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
        $this->link_competency((int) $course->id);

        // Nobody has applied yet: the card is an invitation.
        $newcomer = $this->getDataGenerator()->create_user();
        $this->setUser($newcomer);
        $row = $this->cleaned_row_for((int) $course->id);
        $this->assertTrue($row['locked']);
        $this->assertTrue($row['can_self_enrol']);
        $this->assertFalse($row['is_pending']);

        // Having applied, the same card becomes a wait rather than an invitation.
        $applicant = $this->getDataGenerator()->create_user();
        $plugin->enrol_user($instance, (int) $applicant->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $this->setUser($applicant);
        $row = $this->cleaned_row_for((int) $course->id);
        $this->assertTrue($row['locked']);
        $this->assertFalse($row['can_self_enrol']);
        $this->assertTrue($row['is_pending']);
    }

    /**
     * An open way in outranks an application already lodged elsewhere on the same course.
     *
     * @return void
     */
    public function test_execute_prefers_an_open_enrolment_over_a_pending_application(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $plugin = enrol_get_plugin('apply');
        if (!$plugin || !is_callable([$plugin, 'allow_apply'])) {
            $this->markTestSkipped('enrol_apply is not installed on this site.');
        }
        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        $course = $this->getDataGenerator()->create_course();
        $instanceid = $plugin->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'customint3' => 0,
            'customint5' => 0,
            'customint6' => 1,
        ]);
        $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
        $self = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'self'], '*', MUST_EXIST);
        enrol_get_plugin('self')->update_status($self, ENROL_INSTANCE_ENABLED);
        $this->link_competency((int) $course->id);

        $applicant = $this->getDataGenerator()->create_user();
        $plugin->enrol_user($instance, (int) $applicant->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $this->setUser($applicant);

        $row = $this->cleaned_row_for((int) $course->id);

        // Both are true of this learner; the one that can be acted on now wins.
        $this->assertTrue($row['can_self_enrol']);
        $this->assertFalse($row['is_pending']);
    }

    /**
     * Writes the singleactivity format's 'activitytype' option directly.
     *
     * See calculator_card_shape_test::set_singleactivity_type() for why create_course() cannot
     * set it.
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
     * set_singleactivity_type() is required: with the site default (forum) resolve_main_activity()
     * finds no match and the count-based fallback decides the shape instead. The second, tracked
     * url module makes two trackable candidates, so only the format match can yield
     * CARDMODE_ACTIVITY.
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
     * The ids come from the client and local/dimensions:view is held by every authenticated user,
     * so the per-course gate is all that keeps a hidden course's structure private. The course is
     * linked and completion-tracked, so without that gate the row would carry its section names
     * and start date.
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
     * The tracker builds its card list from competency_coursecomp, so an unlinked id did not come
     * from the page. It gets the same locked row as a hidden course, so a caller probing ids
     * cannot tell the two apart.
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

    /**
     * The error field is cleaned to its PARAM_TEXT spelling, so a message carrying debuginfo cannot
     * fail the whole response, every other course's row included.
     *
     * No fixture makes the calculator throw for a course that passes the readable gate, so the field
     * is produced by calling its producer directly; the row is then cleaned through the real returns
     * structure. The DML exception carries its SQL in the message here, as under developer debugging.
     *
     * @return void
     */
    public function test_an_error_message_with_markup_keeps_the_response_valid(): void {
        $exception = new \dml_read_exception(
            'boom',
            'SELECT 1 FROM {user_enrolments} WHERE status <> :active AND timeend > :now',
            ['<b>R&D < Ops</b>']
        );
        $error = (new \ReflectionMethod(get_course_progress::class, 'error_text'))->invoke(null, $exception);

        $this->assertStringContainsString(get_string('dmlreadexception', 'error'), $error);
        // Tags and the SQL operator stripped, text kept in its plain spelling.
        $this->assertStringContainsString('R&D < Ops', $error);
        $this->assertStringNotContainsString('<b>', $error);
        $this->assertStringNotContainsString('<>', $error);

        $cleaned = external_api::clean_returnvalue(get_course_progress::execute_returns(), [[
            'courseid' => 2,
            'enabled' => false,
            'locked' => false,
            'formatted_start_date' => '',
            'sections' => [],
            'error' => $error,
        ]]);
        $this->assertSame($error, $cleaned[0]['error']);
    }
    /**
     * An \Error thrown while one course is read becomes that course's error row; the other
     * courses still answer and the response stays valid.
     *
     * The calculator cannot be made to throw from a fixture, so a subclass throws a TypeError for
     * one course and reads every other course through the real calculator.
     *
     * @return void
     */
    public function test_an_error_in_one_course_leaves_the_others_answered(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $broken = $this->getDataGenerator()->create_course(['enablecompletion' => 0]);
        $healthy = $this->getDataGenerator()->create_course(['enablecompletion' => 0]);
        foreach ([$broken, $healthy] as $course) {
            $this->getDataGenerator()->enrol_user((int) $user->id, (int) $course->id, 'student');
        }
        $this->link_competency((int) $broken->id, (int) $healthy->id);
        $this->setUser($user);
        $service = new class extends get_course_progress {
            /** @var int The course whose read throws. */
            public static $brokenid = 0;

            /**
             * Throw for the broken course, read every other one.
             *
             * @param int $courseid The course id.
             * @return array
             */
            protected static function progress_data(int $courseid): array {
                if ($courseid === self::$brokenid) {
                    throw new \TypeError('A <b>typed</b> argument received null');
                }
                return parent::progress_data($courseid);
            }
        };
        $service::$brokenid = (int) $broken->id;

        $rows = external_api::clean_returnvalue(
            get_course_progress::execute_returns(),
            $service::execute([(int) $broken->id, (int) $healthy->id])
        );

        $this->assertCount(2, $rows);
        $this->assertSame((int) $broken->id, $rows[0]['courseid']);
        $this->assertSame('A typed argument received null', $rows[0]['error']);
        $this->assertSame([], $rows[0]['sections']);
        // The control: the other course is read normally.
        $this->assertSame((int) $healthy->id, $rows[1]['courseid']);
        $this->assertSame('', $rows[1]['error']);
        $this->assertFalse($rows[1]['locked']);
    }
}
