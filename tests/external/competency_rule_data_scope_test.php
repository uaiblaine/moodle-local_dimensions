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
 * Tests for whose ratings and which competencies the Rules tab's web service answers for.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core_competency\api;
use core_competency\competency;
use core_competency\competency_rule_all;
use core_competency\plan;
use core_external\external_api;

/**
 * The rule data answers only a viewer who may read the plan owner's ratings, and only for a competency the plan reaches.
 *
 * The covers tag stays in this docblock because moodle-cs for Moodle 4.5 cannot see PHPUnit
 * attributes.
 *
 * @covers \local_dimensions\external\get_competency_rule_data
 */
final class competency_rule_data_scope_test extends \advanced_testcase {
    /** @var string Web service under test. */
    private const WSNAME = 'local_dimensions_get_competency_rule_data';

    /** @var string A name whose plain and escaped spellings differ; a competency name cannot hold other tags. */
    private const PLAINNAME = 'R&D < Ops';

    /** @var string PLAINNAME wrapped in a tag, for a scale item, which nothing cleans on the way in. */
    private const TAGGEDNAME = '<b>R&D</b> < Ops';

    /** @var string A multilang competency name, which only format_string() resolves. */
    private const MULTILANGNAME = '<span lang="en" class="multilang">Alpha</span><span lang="pt_br" class="multilang">Alfa</span>';

    /**
     * The plan owner reads the rule and the rated children, their names formatted in the plain spelling.
     *
     * The accordion escapes each value once as it builds the HTML, so an escaped name here would
     * show its entities to the learner, and an unformatted one its markup.
     *
     * @return void
     */
    public function test_plan_owner_gets_the_ratings(): void {
        $this->resetAfterTest();
        filter_set_global_state('multilang', TEXTFILTER_ON);
        filter_set_applies_to_strings('multilang', true);
        $fixture = $this->build_fixture(plan::STATUS_ACTIVE);
        $this->setUser($fixture['ownerid']);

        $response = $this->call($fixture['inplan'], $fixture['planid']);

        $this->assertFalse($response['error'], json_encode($response));
        $data = json_decode($response['data'], true);
        $this->assertTrue($data['hasrule']);
        $this->assertSame(2, $data['childcount']);
        $children = array_column($data['children'], null, 'id');
        $rated = $children[$fixture['ratedchild']];
        $this->assertSame(self::PLAINNAME, $rated['shortname']);
        $this->assertSame(self::PLAINNAME, $rated['gradename']);
        $this->assertTrue($rated['hasgrade']);
        $this->assertTrue($rated['isproficient']);
        $this->assertSame('Alpha', $children[$fixture['unratedchild']]['shortname']);
        $this->assertFalse($children[$fixture['unratedchild']]['hasgrade']);
        $this->assertStringNotContainsString('&amp;', $response['data']);
        $this->assertStringNotContainsString('&lt;', $response['data']);
    }

    /**
     * A viewer holding only planviewdraft reads a draft plan, but not its owner's ratings.
     *
     * The control grants the rating capability to the same role and the same call succeeds, so the
     * refusal is the ratings check and nothing else.
     *
     * @return void
     */
    public function test_draft_reader_without_rating_access_is_refused(): void {
        $this->resetAfterTest();
        $fixture = $this->build_fixture(plan::STATUS_DRAFT);
        $viewer = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        $syscontextid = \context_system::instance()->id;
        assign_capability('moodle/competency:planviewdraft', CAP_ALLOW, $roleid, $syscontextid);
        role_assign($roleid, (int) $viewer->id, $syscontextid);
        $this->setUser($viewer);
        // The precondition: core lets this viewer read the draft plan.
        $this->assertSame($fixture['planid'], (int) api::read_plan($fixture['planid'])->get('id'));

        $response = $this->call($fixture['inplan'], $fixture['planid']);

        $this->assertTrue($response['error']);
        $this->assertSame('nopermissions', $response['exception']->errorcode);
        $this->assertSame(
            get_string('nopermissions', 'error', get_capability_string('moodle/competency:usercompetencyview')),
            $response['exception']->message
        );
        $this->assertArrayNotHasKey('data', $response);

        assign_capability('moodle/competency:usercompetencyview', CAP_ALLOW, $roleid, $syscontextid);

        $response = $this->call($fixture['inplan'], $fixture['planid']);
        $this->assertFalse($response['error'], json_encode($response));
        $this->assertSame(2, json_decode($response['data'], true)['childcount']);
    }

    /**
     * A competency the plan does not reach is refused, exactly like a missing one.
     *
     * @return void
     */
    public function test_competency_outside_the_scope_is_refused(): void {
        global $DB;

        $this->resetAfterTest();
        $fixture = $this->build_fixture(plan::STATUS_ACTIVE);
        $this->setUser($fixture['ownerid']);
        // The control: the owner is answered for the plan's own competency.
        $this->assertFalse($this->call($fixture['inplan'], $fixture['planid'])['error']);
        $missingid = (int) $DB->get_field_sql('SELECT MAX(id) FROM {competency}') + 1000;

        foreach ([$fixture['unrelated'], $missingid] as $competencyid) {
            $response = $this->call($competencyid, $fixture['planid']);
            $this->assertTrue($response['error'], 'Competency id ' . $competencyid);
            $this->assertSame('competency_id_missing', $response['exception']->errorcode, 'Competency id ' . $competencyid);
            $this->assertArrayNotHasKey('data', $response);
        }
    }

    /**
     * A related competency the accordion links to is answered while the link is on, and refused once it is off.
     *
     * @return void
     */
    public function test_related_competency_in_scope_is_accepted(): void {
        $this->resetAfterTest();
        $fixture = $this->build_fixture(plan::STATUS_ACTIVE);
        $this->setUser($fixture['ownerid']);

        $response = $this->call($fixture['related'], $fixture['planid']);
        $this->assertFalse($response['error'], json_encode($response));
        $data = json_decode($response['data'], true);
        $this->assertTrue($data['hasrule']);
        $this->assertSame(1, $data['childcount']);

        set_config('showrelatedlink', 0, 'local_dimensions');

        $response = $this->call($fixture['related'], $fixture['planid']);
        $this->assertTrue($response['error']);
        $this->assertSame('competency_id_missing', $response['exception']->errorcode);
    }

    /**
     * A learner's plan over one competency whose rule counts two children, one of them rated.
     *
     * The scale's first item is TAGGEDNAME, the rated child is named PLAINNAME and the other child
     * MULTILANGNAME. A related competency and an unrelated one each carry a rule of their own, so a
     * refusal is never an empty rule. Both related-competency switches are on at the site.
     *
     * @param int $status The plan status.
     * @return array Keys: ownerid, planid, inplan, ratedchild, unratedchild, related, unrelated.
     */
    private function build_fixture(int $status): array {
        $dg = $this->getDataGenerator();
        $ccg = $dg->get_plugin_generator('core_competency');
        $scale = $dg->create_scale(['scale' => self::TAGGEDNAME . ',Proficient']);
        $frameworkid = (int) $ccg->create_framework(['scaleid' => $scale->id])->get('id');
        $rule = [
            'competencyframeworkid' => $frameworkid,
            'ruletype' => competency_rule_all::class,
            'ruleoutcome' => competency::OUTCOME_EVIDENCE,
        ];
        $inplan = (int) $ccg->create_competency($rule)->get('id');
        $ratedchild = (int) $ccg->create_competency([
            'competencyframeworkid' => $frameworkid,
            'parentid' => $inplan,
            'shortname' => self::PLAINNAME,
        ])->get('id');
        $unratedchild = (int) $ccg->create_competency([
            'competencyframeworkid' => $frameworkid,
            'parentid' => $inplan,
            'shortname' => self::MULTILANGNAME,
        ])->get('id');
        $related = (int) $ccg->create_competency($rule)->get('id');
        $ccg->create_competency(['competencyframeworkid' => $frameworkid, 'parentid' => $related]);
        $ccg->create_related_competency(['competencyid' => $inplan, 'relatedcompetencyid' => $related]);
        $unrelated = (int) $ccg->create_competency($rule)->get('id');
        $ccg->create_competency(['competencyframeworkid' => $frameworkid, 'parentid' => $unrelated]);

        $owner = $dg->create_user();
        $planid = (int) $ccg->create_plan(['userid' => $owner->id, 'status' => $status])->get('id');
        $ccg->create_plan_competency(['planid' => $planid, 'competencyid' => $inplan]);
        $ccg->create_user_competency([
            'userid' => $owner->id,
            'competencyid' => $ratedchild,
            'grade' => 1,
            'proficiency' => 1,
        ]);
        set_config('showrelated', 1, 'local_dimensions');
        set_config('showrelatedlink', 1, 'local_dimensions');

        return [
            'ownerid' => (int) $owner->id,
            'planid' => $planid,
            'inplan' => $inplan,
            'ratedchild' => $ratedchild,
            'unratedchild' => $unratedchild,
            'related' => $related,
            'unrelated' => $unrelated,
        ];
    }

    /**
     * Call the web service as the current user.
     *
     * @param int $competencyid The competency id.
     * @param int $planid The plan id.
     * @return array The call_external_function() response.
     */
    private function call(int $competencyid, int $planid): array {
        $_POST['sesskey'] = sesskey();
        return external_api::call_external_function(self::WSNAME, [
            'competencyid' => $competencyid,
            'planid' => $planid,
        ]);
    }
}
