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

/**
 * Tests for the courses and cohorts the enrolment queue poll answers for.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core_competency\api as competencyapi;
use core_external\external_api;
use local_dimensions\task\process_enrol_method;

/**
 * The poll answers only for courses the Enrolment methods tab lists, and only for linked cohorts.
 *
 * The covers tag stays in this docblock because the plugin still supports Moodle 4.5, whose
 * moodle-cs cannot see the CoversClass attribute and reports every method as uncovered.
 *
 * @covers \local_dimensions\external\get_enrol_queue_status
 */
final class enrol_queue_status_scope_test extends \advanced_testcase {
    /** @var string Web service under test. */
    private const WSNAME = 'local_dimensions_get_enrol_queue_status';

    /**
     * A template with one competency over two courses, plus a course outside the template.
     *
     * Every course, the outside one included, already carries a cohort sync instance for the
     * template's cohort, so a status leaking out of scope would read as configured. The manager
     * holds templatemanage at the site but the enrolment capabilities only in the allowed and
     * the outside course.
     *
     * @return array Keys: templateid, cohortid, othercohortid, allowedid, deniedid, outsideid, userid.
     */
    private function build_fixture(): array {
        global $DB;

        $this->setAdminUser();
        $dg = $this->getDataGenerator();
        $lpg = $dg->get_plugin_generator('core_competency');
        $framework = $lpg->create_framework();
        $competency = $lpg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $template = $lpg->create_template();
        $lpg->create_template_competency([
            'templateid' => $template->get('id'),
            'competencyid' => $competency->get('id'),
        ]);
        $allowed = $dg->create_course(['shortname' => 'ALLOWED']);
        $denied = $dg->create_course(['shortname' => 'DENIED']);
        $outside = $dg->create_course(['shortname' => 'OUTSIDE']);
        foreach ([$allowed, $denied] as $course) {
            $lpg->create_course_competency(['competencyid' => $competency->get('id'), 'courseid' => $course->id]);
        }
        $cohort = $dg->create_cohort();
        $othercohort = $dg->create_cohort();
        competencyapi::create_template_cohort($template->get('id'), $cohort->id);
        $studentroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        $plugin = enrol_get_plugin('cohort');
        foreach ([$allowed, $denied, $outside] as $course) {
            $plugin->add_instance($course, ['customint1' => $cohort->id, 'roleid' => $studentroleid]);
        }

        $user = $dg->create_user();
        $syscontext = \context_system::instance();
        $tplroleid = $dg->create_role();
        assign_capability('moodle/competency:templatemanage', CAP_ALLOW, $tplroleid, $syscontext->id);
        role_assign($tplroleid, (int) $user->id, $syscontext->id);
        $enrolroleid = $dg->create_role();
        foreach (process_enrol_method::REQUIRED_CAPS as $cap) {
            assign_capability($cap, CAP_ALLOW, $enrolroleid, $syscontext->id);
        }
        foreach ([$allowed, $outside] as $course) {
            role_assign($enrolroleid, (int) $user->id, \context_course::instance($course->id)->id);
        }

        return [
            'templateid' => (int) $template->get('id'),
            'cohortid' => (int) $cohort->id,
            'othercohortid' => (int) $othercohort->id,
            'allowedid' => (int) $allowed->id,
            'deniedid' => (int) $denied->id,
            'outsideid' => (int) $outside->id,
            'userid' => (int) $user->id,
        ];
    }

    /**
     * Call the poll as the current user.
     *
     * @param array $fixture Fixture from build_fixture().
     * @param int $cohortid Cohort the statuses are evaluated against.
     * @param array $courseids Tracked course ids.
     * @return array The call_external_function() response.
     */
    private function poll(array $fixture, int $cohortid, array $courseids): array {
        $_POST['sesskey'] = sesskey();
        return external_api::call_external_function(self::WSNAME, [
            'templateid' => $fixture['templateid'],
            'cohortid' => $cohortid,
            'method' => process_enrol_method::METHOD_COHORT,
            'courseids' => $courseids,
        ]);
    }

    /**
     * Statuses come back only for linked courses the caller may configure.
     *
     * @return void
     */
    public function test_items_cover_only_linked_courses_the_caller_may_configure(): void {
        $this->resetAfterTest();
        $fixture = $this->build_fixture();
        $this->setUser($fixture['userid']);

        $response = $this->poll(
            $fixture,
            $fixture['cohortid'],
            [$fixture['allowedid'], $fixture['deniedid'], $fixture['outsideid']]
        );
        $this->assertFalse($response['error'], json_encode($response));
        // The control: the allowed, linked course still reports its configured instance.
        $this->assertSame(
            [['courseid' => $fixture['allowedid'], 'configured' => true]],
            $response['data']['items']
        );
    }

    /**
     * A task queued on a linked course the caller may not configure is not reported as pending.
     *
     * @return void
     */
    public function test_pending_covers_only_courses_the_caller_may_configure(): void {
        $this->resetAfterTest();
        $fixture = $this->build_fixture();
        $studentroleid = (int) array_key_first(get_archetype_roles('student'));
        foreach ([$fixture['allowedid'], $fixture['deniedid']] as $courseid) {
            process_enrol_method::queue(
                process_enrol_method::ACTION_REMOVE,
                $courseid,
                process_enrol_method::METHOD_COHORT,
                $fixture['cohortid'],
                $studentroleid,
                $fixture['templateid'],
                (int) get_admin()->id
            );
        }
        $this->setUser($fixture['userid']);

        $response = $this->poll($fixture, $fixture['cohortid'], [$fixture['allowedid']]);
        $this->assertFalse($response['error'], json_encode($response));
        // The control: the allowed course's own task is still reported.
        $this->assertSame([$fixture['allowedid']], array_map('intval', $response['data']['pendingcourseids']));
    }

    /**
     * A cohort not attached to the template is refused, as by the tab's other services.
     *
     * @return void
     */
    public function test_a_cohort_not_linked_to_the_template_is_refused(): void {
        $this->resetAfterTest();
        $fixture = $this->build_fixture();
        $this->setUser($fixture['userid']);

        // The control: the linked cohort is answered for the same course.
        $response = $this->poll($fixture, $fixture['cohortid'], [$fixture['allowedid']]);
        $this->assertFalse($response['error'], json_encode($response));
        $this->assertCount(1, $response['data']['items']);

        $response = $this->poll($fixture, $fixture['othercohortid'], [$fixture['allowedid']]);
        $this->assertTrue($response['error']);
        $this->assertSame('central_roles_cohortnotlinked', $response['exception']->errorcode);
    }
}
