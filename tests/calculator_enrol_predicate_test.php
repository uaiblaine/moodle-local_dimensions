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
 * Tests for the per-plugin enrolability predicate behind every locked card.
 *
 * The enrol_apply tests skip when that optional plugin is not installed. CI installs it on the
 * Moodle 5.1 and 5.2 jobs, so a skip there is lost coverage; on 4.5 the skip is expected,
 * because enrol_apply supports Moodle 5.1 and later only.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\calculator::current_user_can_enrol
 * @covers     \local_dimensions\calculator::current_user_has_pending_application
 * @covers     \local_dimensions\calculator::current_user_can_self_enrol
 */
final class calculator_enrol_predicate_test extends \advanced_testcase {
    /**
     * Switch enrol_apply on for this test, or skip when the optional plugin is absent.
     *
     * @return \enrol_plugin The apply plugin.
     */
    private function require_apply_plugin(): \enrol_plugin {
        $plugin = enrol_get_plugin('apply');
        if (!$plugin || !is_callable([$plugin, 'allow_apply'])) {
            $this->markTestSkipped('enrol_apply is not installed on this site.');
        }

        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        return $plugin;
    }

    /**
     * Add an enabled apply instance accepting applications, overridden by the given fields.
     *
     * @param \enrol_plugin $plugin The apply plugin.
     * @param \stdClass $course The course to add the instance to.
     * @param array $fields Instance fields to override, keyed by column name.
     * @return \stdClass The stored enrol record.
     */
    private function add_apply_instance(\enrol_plugin $plugin, \stdClass $course, array $fields = []): \stdClass {
        global $DB;

        $id = $plugin->add_instance($course, $fields + [
            'status' => ENROL_INSTANCE_ENABLED,
            'customint3' => 0,
            'customint5' => 0,
            'customint6' => 1,
        ]);

        return $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Enable the self instance every new course carries, so the course offers self enrolment.
     *
     * @param \stdClass $course The course.
     * @return void
     */
    private function open_self_enrolment(\stdClass $course): void {
        global $DB;

        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'self'], '*', MUST_EXIST);
        enrol_get_plugin('self')->update_status($instance, ENROL_INSTANCE_ENABLED);
    }

    /**
     * A course whose only instance is the disabled default offers no way in.
     *
     * @return void
     */
    public function test_a_course_with_no_open_instance_is_not_joinable(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $course = $this->getDataGenerator()->create_course();

        $this->assertFalse(calculator::current_user_can_enrol((int) $course->id));
        $this->assertFalse(calculator::current_user_has_pending_application((int) $course->id));
    }

    /**
     * An open self enrolment instance still makes the course joinable.
     *
     * @return void
     */
    public function test_open_self_enrolment_is_still_recognised(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $course = $this->getDataGenerator()->create_course();
        $this->open_self_enrolment($course);

        $this->assertTrue(calculator::current_user_can_enrol((int) $course->id));
    }

    /**
     * The superseded name still answers, and says so.
     *
     * @return void
     */
    public function test_the_deprecated_alias_delegates_and_warns(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $course = $this->getDataGenerator()->create_course();
        $this->open_self_enrolment($course);

        $this->assertTrue(calculator::current_user_can_self_enrol((int) $course->id));
        $this->assertDebuggingCalled();
    }

    /**
     * A course reachable only through enrol_apply is a way in, not a padlock.
     *
     * enrol_apply does not override can_self_enrol(), so asking that method alone reports an
     * eligible applicant as unable to join.
     *
     * @return void
     */
    public function test_an_apply_only_course_is_joinable(): void {
        $this->resetAfterTest();
        $plugin = $this->require_apply_plugin();
        $this->setUser($this->getDataGenerator()->create_user());

        $course = $this->getDataGenerator()->create_course();
        $this->add_apply_instance($plugin, $course);

        /* Control: the default self instance is still shut, so the answer below can only
           come from the apply leg. */
        $this->assertNotSame(
            true,
            enrol_get_plugin('self')->can_self_enrol($this->self_instance($course), false)
        );
        $this->assertTrue(calculator::current_user_can_enrol((int) $course->id));
    }

    /**
     * Read the course's default self instance, whatever state it is in.
     *
     * @param \stdClass $course The course.
     * @return \stdClass The self enrol record.
     */
    private function self_instance(\stdClass $course): \stdClass {
        global $DB;

        return $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'self'], '*', MUST_EXIST);
    }

    /**
     * A disabled apply instance is not a way in, so the walk must not stop at finding one.
     *
     * @return void
     */
    public function test_a_disabled_apply_instance_is_not_a_way_in(): void {
        $this->resetAfterTest();
        $plugin = $this->require_apply_plugin();
        $this->setUser($this->getDataGenerator()->create_user());

        $course = $this->getDataGenerator()->create_course();
        $this->add_apply_instance($plugin, $course, ['status' => ENROL_INSTANCE_DISABLED]);

        $this->assertFalse(calculator::current_user_can_enrol((int) $course->id));
    }

    /**
     * The cohort restriction on an apply instance is honoured in both directions.
     *
     * @return void
     */
    public function test_the_apply_cohort_restriction_is_honoured(): void {
        $this->resetAfterTest();
        $plugin = $this->require_apply_plugin();

        $cohort = $this->getDataGenerator()->create_cohort();
        $course = $this->getDataGenerator()->create_course();
        $this->add_apply_instance($plugin, $course, ['customint5' => (int) $cohort->id]);

        $outsider = $this->getDataGenerator()->create_user();
        $this->setUser($outsider);
        $this->assertFalse(calculator::current_user_can_enrol((int) $course->id));

        $member = $this->getDataGenerator()->create_user();
        cohort_add_member((int) $cohort->id, (int) $member->id);
        $this->setUser($member);
        $this->assertTrue(calculator::current_user_can_enrol((int) $course->id));
    }

    /**
     * The customint3 places cap lives outside allow_apply(), so the predicate must check it.
     *
     * @return void
     */
    public function test_an_exhausted_apply_places_cap_closes_the_door(): void {
        $this->resetAfterTest();
        $plugin = $this->require_apply_plugin();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->add_apply_instance($plugin, $course, ['customint3' => 1]);

        // One place, and somebody else has taken it.
        $sitting = $this->getDataGenerator()->create_user();
        $plugin->enrol_user($instance, (int) $sitting->id, null, 0, 0, ENROL_USER_SUSPENDED);

        $latecomer = $this->getDataGenerator()->create_user();
        $this->setUser($latecomer);
        $this->assertFalse(calculator::current_user_can_enrol((int) $course->id));

        // Control: allow_apply() itself accepts this user, so the refusal above can only be the cap.
        $this->assertTrue($plugin->allow_apply($instance) === true);
    }

    /**
     * A cap with room left is not a refusal.
     *
     * @return void
     */
    public function test_an_apply_places_cap_with_room_left_still_admits(): void {
        $this->resetAfterTest();
        $plugin = $this->require_apply_plugin();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->add_apply_instance($plugin, $course, ['customint3' => 2]);
        $sitting = $this->getDataGenerator()->create_user();
        $plugin->enrol_user($instance, (int) $sitting->id, null, 0, 0, ENROL_USER_SUSPENDED);

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertTrue(calculator::current_user_can_enrol((int) $course->id));
    }

    /**
     * With both plugins on the course, either one saying yes is enough.
     *
     * @return void
     */
    public function test_self_and_apply_are_both_consulted(): void {
        $this->resetAfterTest();
        $plugin = $this->require_apply_plugin();

        // Apply is shut to this user by its cohort; the open self instance still lets them in.
        $cohort = $this->getDataGenerator()->create_cohort();
        $selfcourse = $this->getDataGenerator()->create_course();
        $this->add_apply_instance($plugin, $selfcourse, ['customint5' => (int) $cohort->id]);
        $this->open_self_enrolment($selfcourse);

        // The mirror image: self is shut, apply is open.
        $applycourse = $this->getDataGenerator()->create_course();
        $this->add_apply_instance($plugin, $applycourse);

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertTrue(calculator::current_user_can_enrol((int) $selfcourse->id));
        $this->assertTrue(calculator::current_user_can_enrol((int) $applycourse->id));
    }

    /**
     * A pending application is its own state: not enrolled, not joinable, and not a padlock.
     *
     * @return void
     */
    public function test_a_pending_application_is_neither_open_nor_joinable(): void {
        $this->resetAfterTest();
        $plugin = $this->require_apply_plugin();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->add_apply_instance($plugin, $course);

        $applicant = $this->getDataGenerator()->create_user();
        // Exactly what enrol_apply_plugin::apply() writes: suspended, no role, no enrolment period.
        $plugin->enrol_user($instance, (int) $applicant->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $this->setUser($applicant);

        $context = \core\context\course::instance($course->id);
        $this->assertFalse(is_enrolled($context, $applicant, '', true));
        $this->assertFalse(calculator::current_user_can_enrol((int) $course->id));
        $this->assertTrue(calculator::current_user_has_pending_application((int) $course->id));
        $this->assertFalse(calculator::user_can_access_course($course, (int) $applicant->id));

        /* The 'enrolledorself' filter needs no pending branch, because the application is a
           real enrolment row and the onlyactive=false test already counts it. */
        $this->assertTrue(calculator::user_enrolled_or_self_enrolable($course, (int) $applicant->id));
    }

    /**
     * Pending is scoped to apply instances: an administrative suspension is not an application.
     *
     * @return void
     */
    public function test_a_suspended_manual_enrolment_is_not_a_pending_application(): void {
        global $DB;

        $this->resetAfterTest();
        $plugin = $this->require_apply_plugin();

        $course = $this->getDataGenerator()->create_course();
        $applyinstance = $this->add_apply_instance($plugin, $course);

        $suspended = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user(
            (int) $suspended->id,
            (int) $course->id,
            'student',
            'manual',
            0,
            0,
            ENROL_USER_SUSPENDED
        );
        $this->setUser($suspended);
        $this->assertFalse(calculator::current_user_has_pending_application((int) $course->id));

        /* Control: the same suspended state on the apply instance of the same course is
           pending, so the assertion above cannot pass by the check never running. */
        $applicant = $this->getDataGenerator()->create_user();
        $plugin->enrol_user($applyinstance, (int) $applicant->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $this->setUser($applicant);
        $this->assertTrue(calculator::current_user_has_pending_application((int) $course->id));

        // And the suspended manual row really is there, so the negative above is about scope.
        $this->assertTrue($DB->record_exists('user_enrolments', [
            'userid' => $suspended->id,
            'status' => ENROL_USER_SUSPENDED,
        ]));
    }

    /**
     * An approved enrolment that later expired is not a pending application.
     *
     * With expiredaction set to suspend, core's process_expirations() re-suspends a lapsed active
     * row and leaves timeend in the past, so only the timeend clause of
     * {@see calculator::current_user_has_pending_application()} tells it from a fresh application.
     *
     * @return void
     */
    public function test_an_expired_approved_enrolment_is_not_a_pending_application(): void {
        $this->resetAfterTest();
        $plugin = $this->require_apply_plugin();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->add_apply_instance($plugin, $course);

        /* What an approval followed by an expiry leaves behind: suspended again, with the
           enrolment period it was given on approval still stamped and now in the past. */
        $lapsed = $this->getDataGenerator()->create_user();
        $plugin->enrol_user(
            $instance,
            (int) $lapsed->id,
            null,
            time() - (2 * WEEKSECS),
            time() - WEEKSECS,
            ENROL_USER_SUSPENDED
        );
        $this->setUser($lapsed);
        $this->assertFalse(calculator::current_user_has_pending_application((int) $course->id));

        /* Control: the same suspended state with no period, which is what apply() writes, is
           pending, so the assertion above is about the dates. */
        $applicant = $this->getDataGenerator()->create_user();
        $plugin->enrol_user($instance, (int) $applicant->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $this->setUser($applicant);
        $this->assertTrue(calculator::current_user_has_pending_application((int) $course->id));

        // And a period that has NOT run out yet is still an application awaiting a decision.
        $waiting = $this->getDataGenerator()->create_user();
        $plugin->enrol_user($instance, (int) $waiting->id, null, 0, time() + WEEKSECS, ENROL_USER_SUSPENDED);
        $this->setUser($waiting);
        $this->assertTrue(calculator::current_user_has_pending_application((int) $course->id));
    }

    /**
     * An approved application stops being pending and becomes an ordinary open course.
     *
     * @return void
     */
    public function test_an_approved_application_is_no_longer_pending(): void {
        $this->resetAfterTest();
        $plugin = $this->require_apply_plugin();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->add_apply_instance($plugin, $course);

        $applicant = $this->getDataGenerator()->create_user();
        $plugin->enrol_user($instance, (int) $applicant->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $this->setUser($applicant);
        $this->assertTrue(calculator::current_user_has_pending_application((int) $course->id));

        $plugin->update_user_enrol($instance, (int) $applicant->id, ENROL_USER_ACTIVE);

        $this->assertFalse(calculator::current_user_has_pending_application((int) $course->id));
        $this->assertTrue(is_enrolled(\core\context\course::instance($course->id), $applicant, '', true));
    }
}
