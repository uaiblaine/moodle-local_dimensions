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

/**
 * Tests for the cohort-roles web services + sync task.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\external\list_template_cohort_roles
 * @covers     \local_dimensions\external\add_cohort_role
 * @covers     \local_dimensions\external\remove_cohort_role
 * @covers     \local_dimensions\task\sync_cohort_roles
 */
final class cohort_roles_test extends \advanced_testcase {
    /**
     * Create a template, a cohort linked to it with members, and a user-context-assignable role.
     *
     * @return array [templateid, cohortid, roleid, holderid, memberids]
     */
    private function setup_fixture(): array {
        $dg = $this->getDataGenerator();
        $ccg = $dg->get_plugin_generator('core_competency');

        $template = $ccg->create_template();
        $cohort = $dg->create_cohort();
        $member1 = $dg->create_user();
        $member2 = $dg->create_user();
        cohort_add_member($cohort->id, $member1->id);
        cohort_add_member($cohort->id, $member2->id);
        competencyapi::create_template_cohort($template->get('id'), $cohort->id);

        // A role assignable at user context (mirrors "Learning plan supervisor").
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'lpsupervisor']);
        set_role_contextlevels($roleid, [CONTEXT_USER]);

        $holder = $dg->create_user();

        return [
            (int) $template->get('id'),
            (int) $cohort->id,
            (int) $roleid,
            (int) $holder->id,
            [(int) $member1->id, (int) $member2->id],
        ];
    }

    /**
     * add_cohort_role creates a mapping, queues the sync, and list shows it as pending; after the
     * task runs the members get the role and the status becomes synced.
     *
     * @return void
     */
    public function test_add_list_and_sync(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$templateid, $cohortid, $roleid, $holderid, $memberids] = $this->setup_fixture();

        $added = add_cohort_role::execute($templateid, $holderid, $roleid, $cohortid);
        $this->assertTrue($added['success']);
        $crparams = ['userid' => $holderid, 'roleid' => $roleid, 'cohortid' => $cohortid];
        $this->assertEquals(1, $DB->count_records('tool_cohortroles', $crparams));

        $queued = \core\task\manager::get_adhoc_tasks(\local_dimensions\task\sync_cohort_roles::class);
        $this->assertNotEmpty($queued);

        $list = list_template_cohort_roles::execute($templateid);
        $this->assertNotEmpty($list['roles']);
        $this->assertCount(1, $list['cohorts']);
        $this->assertCount(1, $list['assignments']);
        $this->assertSame('pending', $list['assignments'][0]['status']);
        $this->assertSame(2, $list['assignments'][0]['membercount']);
        $this->assertSame(0, $list['assignments'][0]['syncedcount']);

        // Run the background sync the task would run.
        \tool_cohortroles\api::sync_all_cohort_roles();

        foreach ($memberids as $memberid) {
            $usercontext = \core\context\user::instance($memberid);
            $raparams = ['roleid' => $roleid, 'userid' => $holderid, 'contextid' => $usercontext->id,
                'component' => 'tool_cohortroles'];
            $this->assertTrue($DB->record_exists('role_assignments', $raparams));
        }

        $synced = list_template_cohort_roles::execute($templateid);
        $this->assertSame('synced', $synced['assignments'][0]['status']);
        $this->assertSame(2, $synced['assignments'][0]['syncedcount']);
    }

    /**
     * add_cohort_role is idempotent and rejects a cohort not linked to the template.
     *
     * @return void
     */
    public function test_add_validation(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$templateid, $cohortid, $roleid, $holderid] = $this->setup_fixture();
        $crparams = ['userid' => $holderid, 'roleid' => $roleid, 'cohortid' => $cohortid];

        $this->assertTrue(add_cohort_role::execute($templateid, $holderid, $roleid, $cohortid)['success']);
        $this->assertSame(1, $DB->count_records('tool_cohortroles', $crparams));
        // Idempotent: a duplicate does not error and does not create a second row.
        $this->assertTrue(add_cohort_role::execute($templateid, $holderid, $roleid, $cohortid)['success']);
        $this->assertSame(1, $DB->count_records('tool_cohortroles', $crparams));

        $unlinked = $this->getDataGenerator()->create_cohort();
        try {
            add_cohort_role::execute($templateid, $holderid, $roleid, (int) $unlinked->id);
            $this->fail('A cohort the template does not hold was accepted.');
        } catch (\moodle_exception $e) {
            $this->assertSame('central_roles_cohortnotlinked', $e->errorcode);
        }
        $this->assertSame(0, $DB->count_records('tool_cohortroles', ['cohortid' => $unlinked->id]));
    }

    /**
     * remove_cohort_role deletes the mapping.
     *
     * @return void
     */
    public function test_remove(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$templateid, $cohortid, $roleid, $holderid] = $this->setup_fixture();

        add_cohort_role::execute($templateid, $holderid, $roleid, $cohortid);
        $gfparams = ['userid' => $holderid, 'roleid' => $roleid, 'cohortid' => $cohortid];
        $assignmentid = (int) $DB->get_field('tool_cohortroles', 'id', $gfparams);

        $this->assertTrue(remove_cohort_role::execute($templateid, $assignmentid)['success']);
        $this->assertEquals(0, $DB->count_records('tool_cohortroles', ['id' => $assignmentid]));
    }

    /**
     * remove_cohort_role rejects an assignment whose cohort is not linked to the template.
     *
     * @return void
     */
    public function test_remove_rejects_out_of_scope(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$templateid, , $roleid, $holderid] = $this->setup_fixture();

        $othercohort = $this->getDataGenerator()->create_cohort();
        \tool_cohortroles\api::create_cohort_role_assignment((object) [
            'userid' => $holderid,
            'roleid' => $roleid,
            'cohortid' => (int) $othercohort->id,
        ]);
        $otherid = (int) $DB->get_field('tool_cohortroles', 'id', ['cohortid' => $othercohort->id]);

        try {
            remove_cohort_role::execute($templateid, $otherid);
            $this->fail('An assignment over a cohort the template does not hold was removed.');
        } catch (\moodle_exception $e) {
            $this->assertSame('central_roles_cohortnotlinked', $e->errorcode);
        }
        $this->assertTrue($DB->record_exists('tool_cohortroles', ['id' => $otherid]));
    }

    /**
     * The listing reads each cohort, holder and synced count in a fixed number of queries, however
     * many cohorts and assignments the template carries, and still reports each one correctly.
     *
     * @return void
     */
    public function test_list_reads_do_not_grow_with_the_rows(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$templateid, $cohortid, $roleid, $holderid, $memberids] = $this->setup_fixture();
        add_cohort_role::execute($templateid, $holderid, $roleid, $cohortid);
        \tool_cohortroles\api::sync_all_cohort_roles();
        list_template_cohort_roles::execute($templateid);

        $before = $DB->perf_get_reads();
        $one = list_template_cohort_roles::execute($templateid);
        $readsforone = $DB->perf_get_reads() - $before;

        $dg = $this->getDataGenerator();
        $morecohortids = [];
        for ($i = 0; $i < 3; $i++) {
            $cohort = $dg->create_cohort(['name' => 'Extra ' . $i]);
            cohort_add_member($cohort->id, $memberids[0]);
            competencyapi::create_template_cohort($templateid, $cohort->id);
            add_cohort_role::execute($templateid, (int) $dg->create_user()->id, $roleid, (int) $cohort->id);
            $morecohortids[] = (int) $cohort->id;
        }
        list_template_cohort_roles::execute($templateid);

        $before = $DB->perf_get_reads();
        $four = list_template_cohort_roles::execute($templateid);
        $readsforfour = $DB->perf_get_reads() - $before;

        $this->assertCount(1, $one['assignments']);
        $this->assertCount(4, $four['cohorts']);
        $this->assertCount(4, $four['assignments']);
        $this->assertSame($readsforone, $readsforfour);
        // Each row still reports its own counts and holder.
        $bycohort = array_column($four['assignments'], null, 'cohortid');
        $this->assertSame('synced', $bycohort[$cohortid]['status']);
        $this->assertSame(2, $bycohort[$cohortid]['syncedcount']);
        $this->assertSame(fullname(\core_user::get_user($holderid)), $bycohort[$cohortid]['userfullname']);
        foreach ($morecohortids as $morecohortid) {
            $this->assertSame('pending', $bycohort[$morecohortid]['status']);
            $this->assertSame(0, $bycohort[$morecohortid]['syncedcount']);
            $this->assertSame(1, $bycohort[$morecohortid]['membercount']);
        }
        $members = array_column($four['cohorts'], 'members', 'cohortid');
        $this->assertSame(2, $members[$cohortid]);
    }

    /**
     * A user without moodle/role:manage cannot list assignments.
     *
     * @return void
     */
    public function test_requires_role_manage(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$templateid] = $this->setup_fixture();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->expectException(\required_capability_exception::class);
        list_template_cohort_roles::execute($templateid);
    }
}
