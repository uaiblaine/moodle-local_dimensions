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

use core_competency\template_cohort;
use local_dimensions\task\sync_template_cohort as sync_task;

/**
 * Tests for the template-cohort external functions and the sync adhoc task.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\external\add_template_cohort
 * @covers     \local_dimensions\external\list_template_cohorts
 * @covers     \local_dimensions\external\remove_template_cohort
 * @covers     \local_dimensions\external\sync_template_cohort
 * @covers     \local_dimensions\task\sync_template_cohort
 */
final class template_cohorts_test extends \advanced_testcase {
    /**
     * Set up a visible template and a cohort with two members.
     *
     * @return array [int $templateid, int $cohortid, array $userids]
     */
    private function setup_fixture(): array {
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $template = $ccg->create_template(['visible' => 1]);
        $cohort = $this->getDataGenerator()->create_cohort();
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        cohort_add_member($cohort->id, $u1->id);
        cohort_add_member($cohort->id, $u2->id);
        return [(int) $template->get('id'), (int) $cohort->id, [(int) $u1->id, (int) $u2->id]];
    }

    /**
     * Attaching a cohort creates the relation and queues a sync task (no plans yet).
     *
     * @return void
     */
    public function test_add_queues_sync(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$templateid, $cohortid] = $this->setup_fixture();

        $result = add_template_cohort::execute($templateid, $cohortid);
        $this->assertTrue($result['success']);
        $this->assertTrue(template_cohort::get_relation($templateid, $cohortid)->get('id') > 0);

        $tasks = \core\task\manager::get_adhoc_tasks(sync_task::class);
        $this->assertCount(1, $tasks);
        $data = reset($tasks)->get_custom_data();
        $this->assertSame($templateid, (int) $data->templateid);
        $this->assertSame($cohortid, (int) $data->cohortid);

        $this->assertSame(0, $DB->count_records('competency_plan', ['templateid' => $templateid]));
    }

    /**
     * The list service reports member and plan counts.
     *
     * @return void
     */
    public function test_list_counts(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$templateid, $cohortid] = $this->setup_fixture();
        add_template_cohort::execute($templateid, $cohortid);

        $list = list_template_cohorts::execute($templateid);
        $this->assertCount(1, $list['cohorts']);
        $this->assertSame($cohortid, $list['cohorts'][0]['cohortid']);
        $this->assertSame(2, $list['cohorts'][0]['members']);
        $this->assertSame(0, $list['cohorts'][0]['plans']);
    }

    /**
     * Running the queued task creates the missing plans.
     *
     * @return void
     */
    public function test_task_creates_plans(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$templateid, $cohortid, $userids] = $this->setup_fixture();
        add_template_cohort::execute($templateid, $cohortid);

        foreach (\core\task\manager::get_adhoc_tasks(sync_task::class) as $task) {
            $task->execute();
        }

        $this->assertSame(2, $DB->count_records('competency_plan', ['templateid' => $templateid]));
        foreach ($userids as $userid) {
            $this->assertTrue($DB->record_exists('competency_plan', ['templateid' => $templateid, 'userid' => $userid]));
        }

        $list = list_template_cohorts::execute($templateid);
        $this->assertSame(2, $list['cohorts'][0]['plans']);
    }

    /**
     * Detaching a cohort removes the relation but keeps the plans.
     *
     * @return void
     */
    public function test_remove_keeps_plans(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$templateid, $cohortid] = $this->setup_fixture();
        add_template_cohort::execute($templateid, $cohortid);
        foreach (\core\task\manager::get_adhoc_tasks(sync_task::class) as $task) {
            $task->execute();
        }

        $result = remove_template_cohort::execute($templateid, $cohortid);
        $this->assertTrue($result['success']);
        $this->assertSame(0, (int) template_cohort::get_relation($templateid, $cohortid)->get('id'));
        $this->assertSame(2, $DB->count_records('competency_plan', ['templateid' => $templateid]));
    }

    /**
     * The sync service queues the adhoc task without creating a relation.
     *
     * @return void
     */
    public function test_sync_queues_task(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$templateid, $cohortid] = $this->setup_fixture();

        $result = sync_template_cohort::execute($templateid, $cohortid);
        $this->assertTrue($result['success']);
        $this->assertCount(1, \core\task\manager::get_adhoc_tasks(sync_task::class));
    }
}
