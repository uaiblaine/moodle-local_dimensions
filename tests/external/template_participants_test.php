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

use core_competency\api;
use core_competency\plan;

/**
 * Tests for the template participant external functions.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\external\list_template_participants
 * @covers     \local_dimensions\external\add_template_user_plan
 * @covers     \local_dimensions\external\unlink_template_user_plan
 * @covers     \local_dimensions\external\delete_template_user_plan
 */
final class template_participants_test extends \advanced_testcase {
    /**
     * Make a visible template, a cohort with two members, and one extra non-cohort user.
     *
     * @return array [int templateid, int cohortid, array cohortuserids, int extrauserid]
     */
    private function fixture(): array {
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $template = $ccg->create_template(['visible' => 1]);
        $cohort = $this->getDataGenerator()->create_cohort();
        $u1 = $this->getDataGenerator()->create_user(['lastname' => 'Alpha']);
        $u2 = $this->getDataGenerator()->create_user(['lastname' => 'Bravo']);
        $u3 = $this->getDataGenerator()->create_user(['lastname' => 'Charlie']);
        cohort_add_member($cohort->id, $u1->id);
        cohort_add_member($cohort->id, $u2->id);
        return [(int) $template->get('id'), (int) $cohort->id, [(int) $u1->id, (int) $u2->id], (int) $u3->id];
    }

    /**
     * add creates a linked plan (idempotent); list shows linked-only by default with origin columns.
     *
     * @return void
     */
    public function test_add_and_list(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$templateid, $cohortid, $cohortusers, $extra] = $this->fixture();
        api::create_template_cohort($templateid, $cohortid);
        api::create_plans_from_template_cohort($templateid, $cohortid, false);

        $created = add_template_user_plan::execute($templateid, $extra);
        $this->assertTrue($created['created']);
        $this->assertFalse(add_template_user_plan::execute($templateid, $extra)['created']);

        $list = list_template_participants::execute($templateid, 0, '', false, 0, 50);
        $this->assertSame(3, $list['total']);
        $byuser = [];
        foreach ($list['items'] as $item) {
            $byuser[$item['userid']] = $item;
            $this->assertFalse($item['isindividual']);
        }
        $this->assertNotSame('', $byuser[$cohortusers[0]]['cohorts']);
        $this->assertSame('', $byuser[$extra]['cohorts']);
    }

    /**
     * The cohort filter restricts to that cohort's members.
     *
     * @return void
     */
    public function test_cohort_filter(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$templateid, $cohortid, , $extra] = $this->fixture();
        api::create_template_cohort($templateid, $cohortid);
        api::create_plans_from_template_cohort($templateid, $cohortid, false);
        add_template_user_plan::execute($templateid, $extra);

        $list = list_template_participants::execute($templateid, $cohortid, '', false, 0, 50);
        $this->assertSame(2, $list['total']);
    }

    /**
     * unlink hides the plan from the default list and shows it when individuals are included.
     *
     * @return void
     */
    public function test_unlink_makes_individual(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$templateid, , , $extra] = $this->fixture();
        add_template_user_plan::execute($templateid, $extra);
        $planid = (int) reset(api::list_plans_for_template($templateid))->get('id');

        unlink_template_user_plan::execute($planid);
        $this->assertSame(0, list_template_participants::execute($templateid, 0, '', false, 0, 50)['total']);
        $withindividual = list_template_participants::execute($templateid, 0, '', true, 0, 50);
        $this->assertSame(1, $withindividual['total']);
        $this->assertTrue($withindividual['items'][0]['isindividual']);
        $plan = plan::get_record(['id' => $planid]);
        $this->assertNull($plan->get('templateid'));
        $this->assertSame($templateid, (int) $plan->get('origtemplateid'));
    }

    /**
     * delete removes the plan from the list.
     *
     * @return void
     */
    public function test_delete(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$templateid, , , $extra] = $this->fixture();
        add_template_user_plan::execute($templateid, $extra);
        $planid = (int) reset(api::list_plans_for_template($templateid))->get('id');

        delete_template_user_plan::execute($planid);
        $this->assertSame(0, list_template_participants::execute($templateid, 0, '', true, 0, 50)['total']);
    }

    /**
     * Name search filters by user name.
     *
     * @return void
     */
    public function test_name_search(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$templateid, , , $extra] = $this->fixture();
        add_template_user_plan::execute($templateid, $extra);
        $this->assertSame(1, list_template_participants::execute($templateid, 0, 'Charlie', false, 0, 50)['total']);
        $this->assertSame(0, list_template_participants::execute($templateid, 0, 'Zzz', false, 0, 50)['total']);
    }
}
