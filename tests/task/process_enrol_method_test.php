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

namespace local_dimensions\task;

use local_dimensions\event\enrol_method_applied;
use local_dimensions\event\enrol_method_removed;

/**
 * Tests for the bulk enrolment-method adhoc task.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\task\process_enrol_method
 * @covers     \local_dimensions\event\enrol_method_applied
 * @covers     \local_dimensions\event\enrol_method_removed
 */
final class process_enrol_method_test extends \advanced_testcase {
    /**
     * Build the shared fixture: template + competency + linked course + cohort with a member.
     *
     * @return array Keys: templateid, competencyid, course, cohortid, memberid, roleid (student).
     */
    private function build_fixture(): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/cohort/lib.php');

        $generator = $this->getDataGenerator();
        $lpg = $generator->get_plugin_generator('core_competency');
        $framework = $lpg->create_framework();
        $competency = $lpg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $template = $lpg->create_template(['shortname' => 'T1']);
        $lpg->create_template_competency([
            'templateid' => $template->get('id'),
            'competencyid' => $competency->get('id'),
        ]);
        $course = $generator->create_course();
        $lpg->create_course_competency([
            'competencyid' => $competency->get('id'),
            'courseid' => $course->id,
        ]);
        $cohort = $generator->create_cohort();
        $member = $generator->create_user();
        cohort_add_member($cohort->id, $member->id);
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);

        return [
            'templateid' => (int) $template->get('id'),
            'competencyid' => (int) $competency->get('id'),
            'course' => $course,
            'cohortid' => (int) $cohort->id,
            'memberid' => (int) $member->id,
            'roleid' => (int) $studentrole->id,
        ];
    }

    /**
     * Execute the task directly with the given payload, running as the given user.
     *
     * @param array $data Custom data (action, courseid, method, cohortid, roleid, templateid).
     * @param int $userid Requesting user id.
     * @return void
     */
    private function run_task(array $data, int $userid): void {
        $task = new process_enrol_method();
        $task->set_custom_data($data);
        $task->set_userid($userid);
        $task->execute();
    }

    /**
     * Payload for the fixture combination.
     *
     * @param array $fixture Fixture from build_fixture().
     * @param string $action Task action.
     * @param string $method Enrol method.
     * @return array
     */
    private function payload(array $fixture, string $action, string $method): array {
        return [
            'action' => $action,
            'courseid' => (int) $fixture['course']->id,
            'method' => $method,
            'cohortid' => $fixture['cohortid'],
            'roleid' => $fixture['roleid'],
            'templateid' => $fixture['templateid'],
        ];
    }

    /**
     * Queueing creates one deduplicated task per combination and pending_map() reflects it.
     *
     * @return void
     */
    public function test_queue_and_pending_map(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $fixture = $this->build_fixture();
        $courseid = (int) $fixture['course']->id;
        $admin = (int) get_admin()->id;

        process_enrol_method::queue(
            process_enrol_method::ACTION_APPLY,
            $courseid,
            process_enrol_method::METHOD_COHORT,
            $fixture['cohortid'],
            $fixture['roleid'],
            $fixture['templateid'],
            $admin
        );
        $tasks = \core\task\manager::get_adhoc_tasks(process_enrol_method::class);
        $this->assertCount(1, $tasks);
        $task = reset($tasks);
        $data = $task->get_custom_data();
        $this->assertSame(process_enrol_method::ACTION_APPLY, $data->action);
        $this->assertSame($courseid, (int) $data->courseid);
        $this->assertSame(process_enrol_method::METHOD_COHORT, $data->method);
        $this->assertSame($fixture['cohortid'], (int) $data->cohortid);
        $this->assertSame($fixture['roleid'], (int) $data->roleid);
        $this->assertSame($fixture['templateid'], (int) $data->templateid);
        $this->assertSame($admin, (int) $task->get_userid());

        // An identical payload is deduplicated by the core checkforexisting safety net.
        process_enrol_method::queue(
            process_enrol_method::ACTION_APPLY,
            $courseid,
            process_enrol_method::METHOD_COHORT,
            $fixture['cohortid'],
            $fixture['roleid'],
            $fixture['templateid'],
            $admin
        );
        $this->assertCount(1, \core\task\manager::get_adhoc_tasks(process_enrol_method::class));

        // A different combination queues in parallel and both appear in the pending map.
        process_enrol_method::queue(
            process_enrol_method::ACTION_APPLY,
            $courseid,
            process_enrol_method::METHOD_SELF,
            $fixture['cohortid'],
            $fixture['roleid'],
            $fixture['templateid'],
            $admin
        );
        $map = process_enrol_method::pending_map();
        $this->assertCount(2, $map);
        $cohortkey = process_enrol_method::key($courseid, process_enrol_method::METHOD_COHORT, $fixture['cohortid']);
        $selfkey = process_enrol_method::key($courseid, process_enrol_method::METHOD_SELF, $fixture['cohortid']);
        $this->assertTrue(isset($map[$cohortkey]));
        $this->assertTrue(isset($map[$selfkey]));
    }

    /**
     * Applying the cohort method creates the instance, enrols the members and fires the event.
     *
     * @return void
     */
    public function test_apply_cohort_creates_instance_and_enrols(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $fixture = $this->build_fixture();
        $courseid = (int) $fixture['course']->id;
        $admin = (int) get_admin()->id;

        $sink = $this->redirectEvents();
        $this->run_task($this->payload($fixture, process_enrol_method::ACTION_APPLY, process_enrol_method::METHOD_COHORT), $admin);

        $instances = process_enrol_method::get_instances($courseid, process_enrol_method::METHOD_COHORT, $fixture['cohortid']);
        $this->assertCount(1, $instances);
        $instance = reset($instances);
        $this->assertSame($fixture['cohortid'], (int) $instance->customint1);
        $this->assertSame($fixture['roleid'], (int) $instance->roleid);
        $this->assertSame(0, (int) $instance->customint2);
        $this->assertTrue(is_enrolled(\context_course::instance($courseid), $fixture['memberid']));

        $events = array_values(array_filter($sink->get_events(), static function ($event) {
            return $event instanceof enrol_method_applied;
        }));
        $this->assertCount(1, $events);
        $this->assertSame((int) $instance->id, (int) $events[0]->objectid);
        $this->assertSame($admin, (int) $events[0]->userid);
        $this->assertSame($fixture['templateid'], (int) $events[0]->other['templateid']);
        $this->assertSame($fixture['cohortid'], (int) $events[0]->other['cohortid']);
        $this->assertSame(process_enrol_method::METHOD_COHORT, $events[0]->other['method']);
        $this->assertSame($fixture['roleid'], (int) $events[0]->other['roleid']);
        $sink->close();
    }

    /**
     * A second apply run for the same combination is a no-op and fires no event.
     *
     * @return void
     */
    public function test_apply_is_idempotent(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $fixture = $this->build_fixture();
        $courseid = (int) $fixture['course']->id;
        $admin = (int) get_admin()->id;
        $payload = $this->payload($fixture, process_enrol_method::ACTION_APPLY, process_enrol_method::METHOD_COHORT);

        $this->run_task($payload, $admin);
        $sink = $this->redirectEvents();
        $this->run_task($payload, $admin);

        $instances = process_enrol_method::get_instances($courseid, process_enrol_method::METHOD_COHORT, $fixture['cohortid']);
        $this->assertCount(1, $instances);
        $events = array_filter($sink->get_events(), static function ($event) {
            return $event instanceof enrol_method_applied;
        });
        $this->assertCount(0, $events);
        $sink->close();
    }

    /**
     * Applying self enrolment restricts it to the cohort and does not mass-enrol anyone.
     *
     * @return void
     */
    public function test_apply_self_sets_cohort_restriction(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $fixture = $this->build_fixture();
        $courseid = (int) $fixture['course']->id;
        $admin = (int) get_admin()->id;

        $this->run_task($this->payload($fixture, process_enrol_method::ACTION_APPLY, process_enrol_method::METHOD_SELF), $admin);

        $instances = process_enrol_method::get_instances($courseid, process_enrol_method::METHOD_SELF, $fixture['cohortid']);
        $this->assertCount(1, $instances);
        $instance = reset($instances);
        $this->assertSame($fixture['cohortid'], (int) $instance->customint5);
        $this->assertSame($fixture['roleid'], (int) $instance->roleid);
        $defaults = enrol_get_plugin('self')->get_instance_defaults();
        $this->assertSame((int) $defaults['status'], (int) $instance->status);
        $this->assertFalse(is_enrolled(\context_course::instance($courseid), $fixture['memberid']));
    }

    /**
     * Removing deletes every matching instance (manual duplicates included) and unenrols users.
     *
     * @return void
     */
    public function test_remove_deletes_all_matching_instances(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $fixture = $this->build_fixture();
        $courseid = (int) $fixture['course']->id;
        $admin = (int) get_admin()->id;

        // Two duplicate instances for the same cohort, as a manual admin could have created.
        $plugin = enrol_get_plugin('cohort');
        $fields = ['customint1' => $fixture['cohortid'], 'roleid' => $fixture['roleid'], 'customint2' => 0];
        $plugin->add_instance($fixture['course'], $fields);
        $plugin->add_instance($fixture['course'], $fields);
        $this->assertTrue(is_enrolled(\context_course::instance($courseid), $fixture['memberid']));

        $sink = $this->redirectEvents();
        $this->run_task($this->payload($fixture, process_enrol_method::ACTION_REMOVE, process_enrol_method::METHOD_COHORT), $admin);

        $instances = process_enrol_method::get_instances($courseid, process_enrol_method::METHOD_COHORT, $fixture['cohortid']);
        $this->assertCount(0, $instances);
        $this->assertFalse(is_enrolled(\context_course::instance($courseid), $fixture['memberid']));

        $events = array_values(array_filter($sink->get_events(), static function ($event) {
            return $event instanceof enrol_method_removed;
        }));
        $this->assertCount(2, $events);
        $this->assertGreaterThan(0, (int) $events[0]->objectid);
        $this->assertSame(process_enrol_method::METHOD_COHORT, $events[0]->other['method']);
        $sink->close();
    }

    /**
     * Removing a combination that has no instance is silent and fires no event.
     *
     * @return void
     */
    public function test_remove_missing_is_silent(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $fixture = $this->build_fixture();
        $admin = (int) get_admin()->id;

        $sink = $this->redirectEvents();
        $this->run_task($this->payload($fixture, process_enrol_method::ACTION_REMOVE, process_enrol_method::METHOD_COHORT), $admin);
        $events = array_filter($sink->get_events(), static function ($event) {
            return $event instanceof enrol_method_removed;
        });
        $this->assertCount(0, $events);
        $sink->close();
    }

    /**
     * The guards skip silently: revoked capabilities, disabled plugin, vanished course, bad payload.
     *
     * @return void
     */
    public function test_guards_skip_silently(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $fixture = $this->build_fixture();
        $courseid = (int) $fixture['course']->id;
        $admin = (int) get_admin()->id;
        $payload = $this->payload($fixture, process_enrol_method::ACTION_APPLY, process_enrol_method::METHOD_COHORT);

        // Requester without the course-level enrolment capabilities.
        $nobody = (int) $this->getDataGenerator()->create_user()->id;
        $this->run_task($payload, $nobody);
        $this->assertCount(0, process_enrol_method::get_instances($courseid, 'cohort', $fixture['cohortid']));

        // Enrol plugin disabled sitewide.
        set_config('enrol_plugins_enabled', 'manual,guest');
        $this->run_task($payload, $admin);
        $this->assertCount(0, process_enrol_method::get_instances($courseid, 'cohort', $fixture['cohortid']));
        set_config('enrol_plugins_enabled', 'manual,guest,self,cohort');

        // Unknown action or missing role are rejected before touching anything.
        $this->run_task(array_merge($payload, ['action' => 'explode']), $admin);
        $this->run_task(array_merge($payload, ['roleid' => 0]), $admin);
        $this->assertCount(0, process_enrol_method::get_instances($courseid, 'cohort', $fixture['cohortid']));

        // Course deleted between queueing and execution.
        delete_course($courseid, false);
        $this->run_task($payload, $admin);
        $this->assertCount(0, process_enrol_method::get_instances($courseid, 'cohort', $fixture['cohortid']));
    }

    /**
     * The task refuses a role the requester cannot assign in the course; an assignable one applies.
     *
     * @return void
     */
    public function test_apply_skips_a_role_not_assignable_in_the_course(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $fixture = $this->build_fixture();
        $courseid = (int) $fixture['course']->id;
        $context = \context_course::instance($courseid);
        $teacherid = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        // The role may be assigned in a course only when its assign matrix allows it from the requester's role.
        $manager = (int) $this->getDataGenerator()->create_user()->id;
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'enrolcfg']);
        foreach (process_enrol_method::REQUIRED_CAPS as $cap) {
            assign_capability($cap, CAP_ALLOW, $roleid, \context_system::instance()->id);
        }
        assign_capability('moodle/role:assign', CAP_ALLOW, $roleid, \context_system::instance()->id);
        role_assign($roleid, $manager, $context->id);
        core_role_set_assign_allowed($roleid, $fixture['roleid']);

        $payload = $this->payload($fixture, process_enrol_method::ACTION_APPLY, process_enrol_method::METHOD_COHORT);
        $this->run_task(array_merge($payload, ['roleid' => $teacherid]), $manager);
        $this->assertCount(0, process_enrol_method::get_instances($courseid, 'cohort', $fixture['cohortid']));

        $this->run_task($payload, $manager);
        $this->assertCount(1, process_enrol_method::get_instances($courseid, 'cohort', $fixture['cohortid']));
    }

    /**
     * The task refuses a cohort the course cannot see (another category); a system cohort applies.
     *
     * Runs with the session user set to somebody else, to show the check follows the task's user.
     *
     * @return void
     */
    public function test_apply_skips_a_cohort_not_visible_from_the_course(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $fixture = $this->build_fixture();
        $courseid = (int) $fixture['course']->id;
        $dg = $this->getDataGenerator();
        $othercat = $dg->create_category();
        $foreign = $dg->create_cohort(['contextid' => \context_coursecat::instance($othercat->id)->id]);
        $admin = (int) get_admin()->id;
        $this->setUser($dg->create_user());

        $payload = $this->payload($fixture, process_enrol_method::ACTION_APPLY, process_enrol_method::METHOD_COHORT);
        $this->run_task(array_merge($payload, ['cohortid' => (int) $foreign->id]), $admin);
        $this->assertCount(0, process_enrol_method::get_instances($courseid, 'cohort', (int) $foreign->id));

        $this->run_task($payload, $admin);
        $this->assertCount(1, process_enrol_method::get_instances($courseid, 'cohort', $fixture['cohortid']));
    }

    /**
     * A hidden cohort in a parent context applies only for a requester who may view cohorts there.
     *
     * @return void
     */
    public function test_apply_skips_a_hidden_cohort_the_requester_cannot_view(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $fixture = $this->build_fixture();
        $courseid = (int) $fixture['course']->id;
        $dg = $this->getDataGenerator();
        $categorycontext = \context_coursecat::instance((int) $fixture['course']->category);
        $hidden = $dg->create_cohort(['contextid' => $categorycontext->id, 'visible' => 0]);

        // A requester who may configure enrolment and assign the role, but holds no cohort:view.
        $requester = (int) $dg->create_user()->id;
        $roleid = $dg->create_role(['shortname' => 'enrolcfg']);
        foreach (process_enrol_method::REQUIRED_CAPS as $cap) {
            assign_capability($cap, CAP_ALLOW, $roleid, \context_system::instance()->id);
        }
        assign_capability('moodle/role:assign', CAP_ALLOW, $roleid, \context_system::instance()->id);
        role_assign($roleid, $requester, $categorycontext->id);
        core_role_set_assign_allowed($roleid, $fixture['roleid']);

        $payload = array_merge(
            $this->payload($fixture, process_enrol_method::ACTION_APPLY, process_enrol_method::METHOD_COHORT),
            ['cohortid' => (int) $hidden->id]
        );
        $this->run_task($payload, $requester);
        $this->assertCount(0, process_enrol_method::get_instances($courseid, 'cohort', (int) $hidden->id));

        assign_capability('moodle/cohort:view', CAP_ALLOW, $roleid, $categorycontext->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->run_task($payload, $requester);
        $this->assertCount(1, process_enrol_method::get_instances($courseid, 'cohort', (int) $hidden->id));
    }
}
