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

use core_competency\api as competencyapi;
use local_dimensions\task\process_enrol_method;

/**
 * Tests for the Enrolment methods tab web services.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\external\list_enrol_competencies
 * @covers     \local_dimensions\external\list_enrol_courses
 * @covers     \local_dimensions\external\queue_enrol_action
 * @covers     \local_dimensions\external\get_enrol_queue_status
 * @covers     \local_dimensions\local\enrol_methods
 */
final class enrol_methods_test extends \advanced_testcase {
    /**
     * Two competencies over two courses in different categories, plus a linked cohort.
     *
     * Competency 1 links course A (category A) and course B (category B); competency 2 links
     * only course B.
     *
     * @return array Keys: templateid, comp1id, comp2id, coursea, courseb, cataid, catbid,
     *               cohortid, studentroleid.
     */
    private function build_fixture(): array {
        global $DB;

        $dg = $this->getDataGenerator();
        $lpg = $dg->get_plugin_generator('core_competency');
        $framework = $lpg->create_framework();
        $comp1 = $lpg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $comp2 = $lpg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $template = $lpg->create_template();
        $lpg->create_template_competency([
            'templateid' => $template->get('id'),
            'competencyid' => $comp1->get('id'),
        ]);
        $lpg->create_template_competency([
            'templateid' => $template->get('id'),
            'competencyid' => $comp2->get('id'),
        ]);
        $cata = $dg->create_category(['name' => 'Category A']);
        $catb = $dg->create_category(['name' => 'Category B']);
        $coursea = $dg->create_course(['category' => $cata->id, 'shortname' => 'CA']);
        $courseb = $dg->create_course(['category' => $catb->id, 'shortname' => 'CB']);
        $lpg->create_course_competency(['competencyid' => $comp1->get('id'), 'courseid' => $coursea->id]);
        $lpg->create_course_competency(['competencyid' => $comp1->get('id'), 'courseid' => $courseb->id]);
        $lpg->create_course_competency(['competencyid' => $comp2->get('id'), 'courseid' => $courseb->id]);
        $cohort = $dg->create_cohort();
        competencyapi::create_template_cohort($template->get('id'), $cohort->id);
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);

        return [
            'templateid' => (int) $template->get('id'),
            'comp1id' => (int) $comp1->get('id'),
            'comp2id' => (int) $comp2->get('id'),
            'coursea' => $coursea,
            'courseb' => $courseb,
            'cataid' => (int) $cata->id,
            'catbid' => (int) $catb->id,
            'cohortid' => (int) $cohort->id,
            'studentroleid' => (int) $studentrole->id,
        ];
    }

    /**
     * A user with template management sitewide but enrolment capabilities only in some courses.
     *
     * @param array $capcourses Course records the user may configure.
     * @return int User id.
     */
    private function create_restricted_manager(array $capcourses): int {
        $dg = $this->getDataGenerator();
        $user = $dg->create_user();
        $syscontext = \context_system::instance();
        $sysroleid = $dg->create_role(['shortname' => 'tplmanager']);
        assign_capability('moodle/competency:templatemanage', CAP_ALLOW, $sysroleid, $syscontext->id);
        assign_capability('moodle/competency:templateview', CAP_ALLOW, $sysroleid, $syscontext->id);
        role_assign($sysroleid, $user->id, $syscontext->id);
        $courseroleid = $dg->create_role(['shortname' => 'enroladmin']);
        foreach (process_enrol_method::REQUIRED_CAPS as $cap) {
            assign_capability($cap, CAP_ALLOW, $courseroleid, $syscontext->id);
        }
        foreach ($capcourses as $course) {
            role_assign($courseroleid, $user->id, \context_course::instance($course->id)->id);
        }
        return (int) $user->id;
    }

    /**
     * Competency counts respect the per-course capability filter and omit empty competencies.
     *
     * @return void
     */
    public function test_list_competencies_filters_by_capability(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $fixture = $this->build_fixture();
        $userid = $this->create_restricted_manager([$fixture['coursea']]);
        $this->setUser($userid);

        $result = list_enrol_competencies::execute($fixture['templateid'], 0, false, true);
        $result = \core_external\external_api::clean_returnvalue(list_enrol_competencies::execute_returns(), $result);

        // Only competency 1 survives (course A); competency 2 has no configurable course.
        $this->assertSame(1, $result['total']);
        $this->assertCount(1, $result['items']);
        $this->assertSame($fixture['comp1id'], (int) $result['items'][0]['competencyid']);
        $this->assertSame(1, (int) $result['items'][0]['coursecount']);
        $this->assertSame(1, $result['totalcourses']);

        // Bootstrap: student is eligible and preselected; only course A's category is offered.
        $roleids = array_map('intval', array_column($result['bootstrap']['roles'], 'id'));
        $this->assertContains($fixture['studentroleid'], $roleids);
        $this->assertSame($fixture['studentroleid'], (int) $result['bootstrap']['defaultroleid']);
        $catids = array_map('intval', array_column($result['bootstrap']['categories'], 'id'));
        $this->assertSame([$fixture['cataid']], $catids);
        $this->assertTrue($result['bootstrap']['cohortenabled']);
        $this->assertTrue($result['bootstrap']['selfenabled']);
    }

    /**
     * Category and hidden filters plus pagination behave as declared.
     *
     * @return void
     */
    public function test_list_competencies_filters_and_pagination(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $fixture = $this->build_fixture();

        // As admin both competencies list; page size 1 still reports total 2.
        $result = list_enrol_competencies::execute($fixture['templateid'], 0, false, false, 0, 1);
        $this->assertSame(2, $result['total']);
        $this->assertCount(1, $result['items']);
        $this->assertSame(2, $result['totalcourses']);

        // Category filter: category A only reaches competency 1 via course A.
        $result = list_enrol_competencies::execute($fixture['templateid'], $fixture['cataid']);
        $this->assertSame(1, $result['total']);
        $this->assertSame($fixture['comp1id'], (int) $result['items'][0]['competencyid']);

        // Hidden courses are excluded by default and included on demand.
        $DB->set_field('course', 'visible', 0, ['id' => $fixture['coursea']->id]);
        $result = list_enrol_competencies::execute($fixture['templateid'], $fixture['cataid']);
        $this->assertSame(0, $result['total']);
        $result = list_enrol_competencies::execute($fixture['templateid'], $fixture['cataid'], true);
        $this->assertSame(1, $result['total']);
    }

    /**
     * Course rows carry both methods' status against the selected cohort.
     *
     * @return void
     */
    public function test_list_courses_statuses(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $fixture = $this->build_fixture();
        $courseaid = (int) $fixture['coursea']->id;

        // Pre-configure cohort sync on course A and queue a self-enrolment task for it.
        enrol_get_plugin('cohort')->add_instance($fixture['coursea'], [
            'customint1' => $fixture['cohortid'],
            'roleid' => $fixture['studentroleid'],
            'customint2' => 0,
        ]);
        process_enrol_method::queue(
            process_enrol_method::ACTION_APPLY,
            $courseaid,
            process_enrol_method::METHOD_SELF,
            $fixture['cohortid'],
            $fixture['studentroleid'],
            $fixture['templateid'],
            (int) get_admin()->id
        );

        $result = list_enrol_courses::execute($fixture['templateid'], $fixture['comp1id'], $fixture['cohortid']);
        $result = \core_external\external_api::clean_returnvalue(list_enrol_courses::execute_returns(), $result);
        $this->assertSame(2, $result['total']);
        $byid = array_column($result['items'], null, 'courseid');
        $rowa = $byid[$courseaid];
        $this->assertSame('configured', $rowa['cohortstatus']);
        $this->assertNotSame('', $rowa['cohortconfiguredsince']);
        $this->assertSame('processing', $rowa['selfstatus']);
        $this->assertSame('', $rowa['selfconfiguredsince']);
        $rowb = $byid[(int) $fixture['courseb']->id];
        $this->assertSame('notconfigured', $rowb['cohortstatus']);
        $this->assertSame('notconfigured', $rowb['selfstatus']);
        $this->assertTrue($rowa['visible']);
        $this->assertStringContainsString('/course/view.php', $rowa['courseurl']);
    }

    /**
     * A competency outside the template yields nothing; an unlinked cohort throws.
     *
     * @return void
     */
    public function test_list_courses_guards(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $fixture = $this->build_fixture();

        $lpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $otherframework = $lpg->create_framework();
        $foreign = $lpg->create_competency(['competencyframeworkid' => $otherframework->get('id')]);
        $result = list_enrol_courses::execute($fixture['templateid'], (int) $foreign->get('id'), $fixture['cohortid']);
        $this->assertSame(0, $result['total']);
        $this->assertCount(0, $result['items']);

        $unlinked = $this->getDataGenerator()->create_cohort();
        $this->expectException(\moodle_exception::class);
        list_enrol_courses::execute($fixture['templateid'], $fixture['comp1id'], (int) $unlinked->id);
    }

    /**
     * Queueing creates one task per course, reports re-queues as processing and skips
     * unauthorised courses; different combinations queue in parallel.
     *
     * @return void
     */
    public function test_queue_enrol_action(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $fixture = $this->build_fixture();
        $courseaid = (int) $fixture['coursea']->id;
        $coursebid = (int) $fixture['courseb']->id;

        $result = queue_enrol_action::execute(
            $fixture['templateid'],
            $fixture['cohortid'],
            process_enrol_method::METHOD_COHORT,
            $fixture['studentroleid'],
            process_enrol_method::ACTION_APPLY,
            [$courseaid, $coursebid]
        );
        $this->assertSame(2, $result['queued']);
        $statuses = array_column($result['results'], 'status', 'courseid');
        $this->assertSame('queued', $statuses[$courseaid]);
        $this->assertSame('queued', $statuses[$coursebid]);
        $this->assertCount(2, \core\task\manager::get_adhoc_tasks(process_enrol_method::class));

        // Re-queueing the same combinations is refused while they are pending.
        $result = queue_enrol_action::execute(
            $fixture['templateid'],
            $fixture['cohortid'],
            process_enrol_method::METHOD_COHORT,
            $fixture['studentroleid'],
            process_enrol_method::ACTION_APPLY,
            [$courseaid, $coursebid]
        );
        $this->assertSame(0, $result['queued']);
        $statuses = array_column($result['results'], 'status', 'courseid');
        $this->assertSame('processing', $statuses[$courseaid]);
        $this->assertCount(2, \core\task\manager::get_adhoc_tasks(process_enrol_method::class));

        // The same course accepts a different combination (other method) in parallel.
        $result = queue_enrol_action::execute(
            $fixture['templateid'],
            $fixture['cohortid'],
            process_enrol_method::METHOD_SELF,
            $fixture['studentroleid'],
            process_enrol_method::ACTION_APPLY,
            [$courseaid]
        );
        $this->assertSame(1, $result['queued']);
        $this->assertCount(3, \core\task\manager::get_adhoc_tasks(process_enrol_method::class));

        // A course the user cannot configure is skipped server-side; a foreign id too.
        $restricted = $this->create_restricted_manager([$fixture['coursea']]);
        $this->setUser($restricted);
        $foreigncourse = $this->getDataGenerator()->create_course();
        $result = queue_enrol_action::execute(
            $fixture['templateid'],
            $fixture['cohortid'],
            process_enrol_method::METHOD_SELF,
            $fixture['studentroleid'],
            process_enrol_method::ACTION_APPLY,
            [$coursebid, (int) $foreigncourse->id]
        );
        $this->assertSame(0, $result['queued']);
        $statuses = array_column($result['results'], 'status', 'courseid');
        $this->assertSame('skipped', $statuses[$coursebid]);
        $this->assertSame('skipped', $statuses[(int) $foreigncourse->id]);
    }

    /**
     * Queue validation: ineligible role and missing template capability are rejected.
     *
     * @return void
     */
    public function test_queue_enrol_action_validation(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $fixture = $this->build_fixture();
        $manager = $DB->get_record('role', ['shortname' => 'manager'], '*', MUST_EXIST);

        try {
            queue_enrol_action::execute(
                $fixture['templateid'],
                $fixture['cohortid'],
                process_enrol_method::METHOD_COHORT,
                (int) $manager->id,
                process_enrol_method::ACTION_APPLY,
                [(int) $fixture['coursea']->id]
            );
            $this->fail('An ineligible role must be rejected.');
        } catch (\invalid_parameter_exception $e) {
            $this->assertStringContainsString('not eligible', $e->debuginfo ?? $e->getMessage());
        }

        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\required_capability_exception::class);
        queue_enrol_action::execute(
            $fixture['templateid'],
            $fixture['cohortid'],
            process_enrol_method::METHOD_COHORT,
            $fixture['studentroleid'],
            process_enrol_method::ACTION_APPLY,
            [(int) $fixture['coursea']->id]
        );
    }

    /**
     * The poll reflects the queue and reports fresh configured state after the tasks run.
     *
     * @return void
     */
    public function test_get_enrol_queue_status(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $fixture = $this->build_fixture();
        $courseaid = (int) $fixture['coursea']->id;

        queue_enrol_action::execute(
            $fixture['templateid'],
            $fixture['cohortid'],
            process_enrol_method::METHOD_COHORT,
            $fixture['studentroleid'],
            process_enrol_method::ACTION_APPLY,
            [$courseaid]
        );
        $result = get_enrol_queue_status::execute(
            $fixture['templateid'],
            $fixture['cohortid'],
            process_enrol_method::METHOD_COHORT,
            [$courseaid]
        );
        $result = \core_external\external_api::clean_returnvalue(get_enrol_queue_status::execute_returns(), $result);
        $this->assertSame([$courseaid], array_map('intval', $result['pendingcourseids']));
        $this->assertFalse($result['items'][0]['configured']);

        // The other method's queue is empty for the same course.
        $other = get_enrol_queue_status::execute(
            $fixture['templateid'],
            $fixture['cohortid'],
            process_enrol_method::METHOD_SELF,
            [$courseaid]
        );
        $this->assertCount(0, $other['pendingcourseids']);

        $this->runAdhocTasks(process_enrol_method::class);
        $result = get_enrol_queue_status::execute(
            $fixture['templateid'],
            $fixture['cohortid'],
            process_enrol_method::METHOD_COHORT,
            [$courseaid]
        );
        $this->assertCount(0, $result['pendingcourseids']);
        $this->assertTrue((bool) $result['items'][0]['configured']);
    }
}
