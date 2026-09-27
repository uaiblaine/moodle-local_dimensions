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
 * Tests for which plans and competencies the accordion's course list answers for.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core_competency\competency;
use core_competency\competency_rule_all;
use core_competency\plan;
use core_external\external_api;
use local_dimensions\constants;
use local_dimensions\customfield\lp_handler;
use local_dimensions\helper;

/**
 * The course list answers only through a plan the caller reads, and only for a competency that plan reaches.
 *
 * Every competency in the fixture is linked to the same two courses, so a refusal is never an empty
 * list and an answer is never empty. The site filter shows only courses the learner is actively
 * enrolled in and the plan's template shows every course, so the rows also tell which layer of the
 * enrolment-filter cascade applied.
 *
 * The covers tag stays in this docblock because moodle-cs for Moodle 4.5 cannot see PHPUnit
 * attributes.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\external\get_competency_courses
 */
final class competency_courses_scope_test extends \advanced_testcase {
    /** @var string Web service under test. */
    private const WSNAME = 'local_dimensions_get_competency_courses';

    /**
     * A plan's own competency answers, filtered by the plan's template.
     *
     * @return void
     */
    public function test_plan_competency_answers_through_the_plan_template(): void {
        $this->resetAfterTest();
        $fixture = $this->build_fixture();

        $this->assertSame($fixture['allcourses'], $this->course_ids($this->call($fixture['inplan'], $fixture['planid'])));
    }

    /**
     * A competency the plan does not reach is refused, exactly like a missing one.
     *
     * @return void
     */
    public function test_competency_outside_the_scope_is_refused(): void {
        global $DB;

        $this->resetAfterTest();
        $fixture = $this->build_fixture();
        // The control: the same caller and plan are answered for the plan's own competency.
        $this->assertSame($fixture['allcourses'], $this->course_ids($this->call($fixture['inplan'], $fixture['planid'])));
        $missingid = (int) $DB->get_field_sql('SELECT MAX(id) FROM {competency}') + 1000;

        foreach ([$fixture['unrelated'], $fixture['grandchild'], $missingid] as $competencyid) {
            $response = $this->call($competencyid, $fixture['planid']);
            $this->assert_refused('competency_id_missing', $response, 'Competency ' . $competencyid);
        }
    }

    /**
     * A plan id of zero, which once skipped the plan read altogether, and a missing plan are refused.
     *
     * @return void
     */
    public function test_plan_id_zero_and_missing_plan_are_refused(): void {
        global $DB;

        $this->resetAfterTest();
        $fixture = $this->build_fixture();
        // The control: the real plan answers for the same competency.
        $this->assertSame($fixture['allcourses'], $this->course_ids($this->call($fixture['inplan'], $fixture['planid'])));
        $missingid = (int) $DB->get_field_sql('SELECT MAX(id) FROM {competency_plan}') + 1000;

        foreach ([0, $missingid] as $planid) {
            $response = $this->call($fixture['inplan'], $planid);
            $this->assert_refused('invalidplan', $response, 'Plan ' . $planid);
            // The plugin's string, not core's own invalidplan (whose text differs).
            $this->assertSame(get_string('invalidplan', 'local_dimensions'), $response['exception']->message, 'Plan ' . $planid);
        }
    }

    /**
     * Another learner's plan is refused with core's own error, not reported as missing.
     *
     * @return void
     */
    public function test_plan_the_caller_may_not_read_is_refused_with_cores_error(): void {
        $this->resetAfterTest();
        $fixture = $this->build_fixture();
        // The control: the owner is answered.
        $this->assertSame($fixture['allcourses'], $this->course_ids($this->call($fixture['inplan'], $fixture['planid'])));

        $this->setUser($this->getDataGenerator()->create_user());
        $response = $this->call($fixture['inplan'], $fixture['planid']);

        $this->assert_refused('nopermissions', $response, 'Another learner');
        $this->assertSame(
            get_string('nopermissions', 'error', get_capability_string('moodle/competency:planview')),
            $response['exception']->message
        );
    }

    /**
     * A related competency the accordion links to answers through the competency and site filters only.
     *
     * The plan's template shows every course and the site only the learner's, so the one-course answer
     * is the site filter; the plan's own competency, asked the same way, gets the template's. Once the
     * link is off the same competency is refused.
     *
     * @return void
     */
    public function test_related_competency_answers_through_the_site_filter(): void {
        $this->resetAfterTest();
        $fixture = $this->build_fixture();
        // The precondition for any reach outside the plan: competencyview in the framework's context.
        $this->assertTrue(has_capability('moodle/competency:competencyview', \context_system::instance()));

        $this->assertSame([$fixture['enrolledcourse']], $this->course_ids($this->call($fixture['related'], $fixture['planid'])));
        $this->assertSame($fixture['allcourses'], $this->course_ids($this->call($fixture['inplan'], $fixture['planid'])));

        set_config('showrelatedlink', 0, 'local_dimensions');

        $this->assert_refused('competency_id_missing', $this->call($fixture['related'], $fixture['planid']), 'Link off');
    }

    /**
     * A child counted by the rule of a plan competency answers through the site filter; its own child does not.
     *
     * Both related-competency switches are off, so the child is reached through the rule alone.
     *
     * @return void
     */
    public function test_rule_child_answers_and_its_child_is_refused(): void {
        $this->resetAfterTest();
        $fixture = $this->build_fixture();
        set_config('showrelated', 0, 'local_dimensions');
        set_config('showrelatedlink', 0, 'local_dimensions');

        $this->assertSame([$fixture['enrolledcourse']], $this->course_ids($this->call($fixture['child'], $fixture['planid'])));

        $this->assert_refused('competency_id_missing', $this->call($fixture['grandchild'], $fixture['planid']), 'Grandchild');
    }

    /**
     * A reader of a draft plan who may not read its owner's user competencies is refused.
     *
     * read_plan() accepts planviewdraft alone on a draft plan, which grants nothing about the learner, while
     * the course list describes the learner's enrolment, progress and activity completion. The accordion
     * never asks for this viewer (its rows have no detail region), but the service is callable directly.
     * The control grants the same role usercompetencyview and gets the learner's courses.
     *
     * @return void
     */
    public function test_a_draft_reader_without_rating_access_is_refused(): void {
        $this->resetAfterTest();
        $fixture = $this->build_fixture(plan::STATUS_DRAFT);
        $viewer = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        $syscontextid = \context_system::instance()->id;
        assign_capability('moodle/competency:planviewdraft', CAP_ALLOW, $roleid, $syscontextid);
        role_assign($roleid, (int) $viewer->id, $syscontextid);
        $this->setUser($viewer);
        // The precondition: core lets this viewer read the draft plan.
        $this->assertSame($fixture['planid'], (int) \core_competency\api::read_plan($fixture['planid'])->get('id'));

        $response = $this->call($fixture['inplan'], $fixture['planid']);
        $this->assert_refused('nopermissions', $response, 'Draft reader');
        $this->assertSame(
            get_string('nopermissions', 'error', get_capability_string('moodle/competency:usercompetencyview')),
            $response['exception']->message
        );

        assign_capability('moodle/competency:usercompetencyview', CAP_ALLOW, $roleid, $syscontextid);

        $this->assertSame($fixture['allcourses'], $this->course_ids($this->call($fixture['inplan'], $fixture['planid'])));
    }

    /**
     * A learner's plan from a template over one competency with a rule, and competencies around it.
     *
     * Every competency sits in one framework at the system context and is linked to two courses: one the
     * learner is enrolled in and one they are not. The site's enrolment filter is "active" and the
     * template's is "all". Both related-competency switches are on at the site. The learner is the current
     * user on return.
     *
     * @param int $status The plan's status, active unless a test needs another.
     * @return array Keys: planid, inplan (in the plan, with a rule), child, grandchild, related, unrelated,
     *     enrolledcourse and allcourses (the two course ids, sorted).
     */
    private function build_fixture(int $status = plan::STATUS_ACTIVE): array {
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_LP);
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);
        set_config('enrollmentfilter', constants::ENROLLMENTFILTER_ACTIVE, 'local_dimensions');
        set_config('showrelated', 1, 'local_dimensions');
        set_config('showrelatedlink', 1, 'local_dimensions');

        $dg = $this->getDataGenerator();
        $ccg = $dg->get_plugin_generator('core_competency');
        $frameworkid = (int) $ccg->create_framework()->get('id');
        $create = static function (array $record = []) use ($ccg, $frameworkid): int {
            return (int) $ccg->create_competency(['competencyframeworkid' => $frameworkid] + $record)->get('id');
        };
        $inplan = $create([
            'ruletype' => competency_rule_all::class,
            'ruleoutcome' => competency::OUTCOME_EVIDENCE,
        ]);
        $child = $create(['parentid' => $inplan]);
        $grandchild = $create(['parentid' => $child]);
        $related = $create();
        $unrelated = $create();
        $ccg->create_related_competency(['competencyid' => $inplan, 'relatedcompetencyid' => $related]);

        $templateid = (int) $ccg->create_template()->get('id');
        $ccg->create_template_competency(['templateid' => $templateid, 'competencyid' => $inplan]);
        // Saved as an administrator: instance_form_save() drops fields the current user cannot edit.
        $showall = array_search(constants::ENROLLMENTFILTER_ALL, array_keys(constants::enrollmentfilter_options()), true) + 1;
        lp_handler::create()->instance_form_save((object) [
            'id' => $templateid,
            'customfield_' . constants::CFIELD_ENROLLMENTFILTER => $showall,
        ], true);

        $owner = $dg->create_user();
        $planid = (int) $ccg->create_plan([
            'userid' => $owner->id,
            'templateid' => $templateid,
            'status' => $status,
        ])->get('id');

        $enrolledcourse = (int) $dg->create_course()->id;
        $othercourse = (int) $dg->create_course()->id;
        foreach ([$inplan, $child, $grandchild, $related, $unrelated] as $competencyid) {
            \core_competency\api::add_competency_to_course($enrolledcourse, $competencyid);
            \core_competency\api::add_competency_to_course($othercourse, $competencyid);
        }
        $dg->enrol_user((int) $owner->id, $enrolledcourse, 'student');
        $allcourses = [$enrolledcourse, $othercourse];
        sort($allcourses);

        $this->setUser($owner);

        return [
            'planid' => $planid,
            'inplan' => $inplan,
            'child' => $child,
            'grandchild' => $grandchild,
            'related' => $related,
            'unrelated' => $unrelated,
            'enrolledcourse' => $enrolledcourse,
            'allcourses' => $allcourses,
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

    /**
     * The course ids of a successful response, sorted.
     *
     * @param array $response The call_external_function() response.
     * @return array The course ids.
     */
    private function course_ids(array $response): array {
        $this->assertFalse($response['error'], json_encode($response['exception'] ?? null));
        $ids = array_map('intval', array_column($response['data'], 'id'));
        sort($ids);

        return $ids;
    }

    /**
     * Assert that a response is a refusal with the given error code and carries no data.
     *
     * @param string $errorcode The expected error code.
     * @param array $response The call_external_function() response.
     * @param string $message Names the case in a failure.
     * @return void
     */
    private function assert_refused(string $errorcode, array $response, string $message): void {
        $this->assertTrue($response['error'], $message);
        $this->assertSame($errorcode, $response['exception']->errorcode, $message);
        $this->assertArrayNotHasKey('data', $response, $message);
    }
}
