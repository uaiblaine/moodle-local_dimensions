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
use core_external\external_api;
use local_dimensions\helper;

/**
 * What the tracker's card services refuse once they are given a plan.
 *
 * With a plan they describe its owner, so they answer only a caller who may read the plan and the
 * owner's user competencies, only for a competency the plan reaches, and only for courses the caller
 * may be told about that are linked to that competency. Every refusal sits beside a control: the same
 * call answered when only the refused condition changes.
 *
 * The covers tags stay in this docblock because moodle-cs for Moodle 4.5 cannot see PHPUnit
 * attributes.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\external\get_course_progress
 * @covers     \local_dimensions\external\get_courses_completion_status
 * @covers     \local_dimensions\local\plan_access
 */
final class tracker_cards_scope_test extends \advanced_testcase {
    /** @var string The progress web service. */
    private const PROGRESS = 'local_dimensions_get_course_progress';

    /** @var string The completion status web service. */
    private const STATUS = 'local_dimensions_get_courses_completion_status';

    /**
     * A caller who reads a draft plan with planviewdraft alone may not read its owner's progress.
     *
     * The control grants the user competency capability to the same role, and the same calls answer.
     *
     * @return void
     */
    public function test_a_draft_reader_without_rating_access_is_refused(): void {
        $f = $this->build_fixture(plan::STATUS_DRAFT);
        $viewer = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        $syscontextid = \context_system::instance()->id;
        assign_capability('moodle/competency:planviewdraft', CAP_ALLOW, $roleid, $syscontextid);
        role_assign($roleid, (int) $viewer->id, $syscontextid);
        $this->setUser($viewer);
        // The precondition: core lets this viewer read the draft plan.
        $this->assertSame($f['planid'], (int) api::read_plan($f['planid'])->get('id'));

        foreach ([self::PROGRESS, self::STATUS] as $wsname) {
            $response = $this->call($wsname, [$f['kilo']], $f['planid'], $f['competencyid']);
            $this->assert_refused($response, 'nopermissions', 'moodle/competency:usercompetencyview', $wsname);
        }

        assign_capability('moodle/competency:usercompetencyview', CAP_ALLOW, $roleid, $syscontextid);

        $progress = $this->rows(self::PROGRESS, [$f['kilo']], $f['planid'], $f['competencyid']);
        $this->assertSame(100, $progress[$f['kilo']]['sections'][1]['percentage']);
        $status = $this->rows(self::STATUS, [$f['kilo']], $f['planid'], $f['competencyid']);
        $this->assertTrue($status[$f['kilo']]['iscompleted']);
    }

    /**
     * A caller who may not read the plan at all is refused with core's own plan error.
     *
     * The control is the reviewer, who reads the same plan through the same calls.
     *
     * @return void
     */
    public function test_a_caller_who_cannot_read_the_plan_is_refused(): void {
        $f = $this->build_fixture(plan::STATUS_ACTIVE);
        $this->setUser($this->getDataGenerator()->create_user());

        foreach ([self::PROGRESS, self::STATUS] as $wsname) {
            $response = $this->call($wsname, [$f['kilo']], $f['planid'], $f['competencyid']);
            $this->assert_refused($response, 'nopermissions', 'moodle/competency:planview', $wsname);
            $response = $this->call($wsname, [$f['kilo']], $f['missingplanid'], $f['competencyid']);
            $this->assert_refused($response, 'invalidplan', null, $wsname);
        }

        $this->setUser($f['teacher']);
        $this->assertTrue($this->rows(self::STATUS, [$f['kilo']], $f['planid'], $f['competencyid'])[$f['kilo']]['iscompleted']);
    }

    /**
     * A competency the plan does not reach is refused, as are a missing id and 0.
     *
     * Without a plan the competency id is not read at all, so the same out-of-scope id is ignored there.
     *
     * @return void
     */
    public function test_a_competency_the_plan_does_not_reach_is_refused(): void {
        $f = $this->build_fixture(plan::STATUS_ACTIVE);
        $this->setUser($f['teacher']);
        // The control: the plan's own competency is answered.
        $this->assertSame(
            100,
            $this->rows(self::PROGRESS, [$f['kilo']], $f['planid'], $f['competencyid'])[$f['kilo']]['sections'][1]['percentage']
        );

        foreach ([self::PROGRESS, self::STATUS] as $wsname) {
            foreach ([$f['othercompetencyid'], $f['missingcompetencyid'], 0] as $competencyid) {
                $response = $this->call($wsname, [$f['kilo']], $f['planid'], $competencyid);
                $this->assert_refused($response, 'competency_id_missing', null, $wsname . ' ' . $competencyid);
            }
        }

        // Without a plan the caller's own cards are answered, whatever the competency id says.
        $own = $this->rows(self::PROGRESS, [$f['kilo']], 0, $f['othercompetencyid']);
        $this->assertTrue($own[$f['kilo']]['locked']);
        $this->assertSame('', $own[$f['kilo']]['error']);
    }

    /**
     * A course linked to another competency only is answered like an unreadable one, beside a linked one.
     *
     * Lima carries a competency link, so the gate without a plan answers it; with the plan it is not
     * linked to the plan's competency. The learner completed it, so any answer about it would show.
     *
     * @return void
     */
    public function test_a_course_not_linked_to_the_competency_is_unavailable(): void {
        $f = $this->build_fixture(plan::STATUS_ACTIVE);
        $this->setUser($f['teacher']);

        $progress = $this->rows(self::PROGRESS, [$f['kilo'], $f['lima']], $f['planid'], $f['competencyid']);
        $status = $this->rows(self::STATUS, [$f['kilo'], $f['lima']], $f['planid'], $f['competencyid']);

        $this->assert_unavailable($progress[$f['lima']], $status[$f['lima']]);
        // The control: Kilo, linked to the plan's competency, carries the learner's data.
        $this->assertTrue($progress[$f['kilo']]['enabled']);
        $this->assertSame(100, $progress[$f['kilo']]['sections'][1]['percentage']);
        $this->assertNotSame('', $progress[$f['kilo']]['formatted_start_date']);
        $this->assertTrue($status[$f['kilo']]['iscompleted']);

        // And the learner, asking without a plan, is answered about Lima: only the link was missing.
        $this->setUser($f['learner']);
        $own = $this->rows(self::PROGRESS, [$f['lima']]);
        $this->assertTrue($own[$f['lima']]['enabled']);
        $this->assertSame(100, $own[$f['lima']]['sections'][1]['percentage']);
    }

    /**
     * A linked course the caller may not be told about is answered like an unreadable one.
     *
     * Mike is hidden, linked to the plan's competency and completed by the learner. The control grants
     * the reviewer the capability to see hidden courses, and the same call answers it.
     *
     * @return void
     */
    public function test_a_course_the_caller_may_not_see_is_unavailable(): void {
        $f = $this->build_fixture(plan::STATUS_ACTIVE);
        $this->setUser($f['teacher']);

        $progress = $this->rows(self::PROGRESS, [$f['kilo'], $f['mike']], $f['planid'], $f['competencyid']);
        $status = $this->rows(self::STATUS, [$f['kilo'], $f['mike']], $f['planid'], $f['competencyid']);

        $this->assert_unavailable($progress[$f['mike']], $status[$f['mike']]);
        $this->assertTrue($status[$f['kilo']]['iscompleted']);

        assign_capability('moodle/course:viewhiddencourses', CAP_ALLOW, $f['reviewerroleid'], \context_system::instance()->id);

        $progress = $this->rows(self::PROGRESS, [$f['mike']], $f['planid'], $f['competencyid']);
        $this->assertTrue($progress[$f['mike']]['enabled']);
        $this->assertSame(100, $progress[$f['mike']]['sections'][1]['percentage']);
        $this->assertTrue($this->rows(self::STATUS, [$f['mike']], $f['planid'], $f['competencyid'])[$f['mike']]['iscompleted']);
    }

    /**
     * A learner's plan over one competency, the courses around it, and a teacher allowed to read it.
     *
     * Each course holds one tracked activity in section 1 that the learner completed, and the learner
     * completed the course; the learner is enrolled as a student in all three, the teacher in none.
     * - Kilo is linked to the plan's competency.
     * - Lima is linked to another competency only, outside the plan.
     * - Mike is linked to the plan's competency and hidden.
     *
     * @param int $status The plan status.
     * @return array Keys competencyid, othercompetencyid, missingcompetencyid, planid, missingplanid,
     *     learner, teacher, reviewerroleid and the course ids kilo, lima and mike.
     */
    private function build_fixture(int $status): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/completionlib.php');

        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enablecompletion', 1);
        helper::ensure_custom_fields_exist(helper::AREA_LP);
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);

        $dg = $this->getDataGenerator();
        $ccg = $dg->get_plugin_generator('core_competency');
        $frameworkid = (int) $ccg->create_framework()->get('id');
        $competencyid = (int) $ccg->create_competency(['competencyframeworkid' => $frameworkid])->get('id');
        $othercompetencyid = (int) $ccg->create_competency(['competencyframeworkid' => $frameworkid])->get('id');

        $learner = $dg->create_user();
        $teacher = $dg->create_user();
        $planid = (int) $ccg->create_plan(['userid' => $learner->id, 'status' => $status])->get('id');
        $ccg->create_plan_competency(['planid' => $planid, 'competencyid' => $competencyid]);

        $systemcontext = \context_system::instance();
        $reviewerroleid = $dg->create_role();
        assign_capability('moodle/competency:planview', CAP_ALLOW, $reviewerroleid, $systemcontext->id);
        role_assign($reviewerroleid, (int) $teacher->id, $systemcontext->id);

        $courses = [];
        foreach (['kilo' => $competencyid, 'lima' => $othercompetencyid, 'mike' => $competencyid] as $key => $linkid) {
            $courses[$key] = (int) $dg->create_course([
                'fullname' => ucfirst($key),
                'enablecompletion' => 1,
                'numsections' => 2,
                'visible' => $key === 'mike' ? 0 : 1,
            ])->id;
            api::add_competency_to_course($courses[$key], $linkid);
            $dg->enrol_user((int) $learner->id, $courses[$key], 'student');
            $cmid = (int) $dg->create_module('page', [
                'course' => $courses[$key],
                'section' => 1,
                'completion' => COMPLETION_TRACKING_MANUAL,
            ])->cmid;
            $completion = new \completion_info(get_course($courses[$key]));
            $completion->update_state(get_coursemodule_from_id('page', $cmid), COMPLETION_COMPLETE, (int) $learner->id);
            (new \completion_completion(['course' => $courses[$key], 'userid' => (int) $learner->id]))->mark_complete();
        }

        return $courses + [
            'competencyid' => $competencyid,
            'othercompetencyid' => $othercompetencyid,
            'missingcompetencyid' => (int) $DB->get_field_sql('SELECT MAX(id) FROM {competency}') + 1000,
            'planid' => $planid,
            'missingplanid' => (int) $DB->get_field_sql('SELECT MAX(id) FROM {competency_plan}') + 1000,
            'learner' => $learner,
            'teacher' => $teacher,
            'reviewerroleid' => $reviewerroleid,
        ];
    }

    /**
     * Assert that a course got exactly the rows an unreadable course gets from both services.
     *
     * @param array $progress The course's progress row.
     * @param array $status The course's completion status row.
     * @return void
     */
    private function assert_unavailable(array $progress, array $status): void {
        $this->assertSame([
            'courseid' => $progress['courseid'],
            'enabled' => false,
            'cardmode' => \local_dimensions\constants::CARDMODE_TIMELINE,
            'locked' => true,
            'formatted_start_date' => '',
            'is_enrolment_start' => false,
            'can_self_enrol' => false,
            'is_pending' => false,
            'is_future_date' => false,
            'course_url' => '',
            'error' => '',
            'sections' => [],
        ], $progress);
        $this->assertSame(['courseid' => $status['courseid'], 'iscompleted' => false, 'islocked' => true], $status);
    }

    /**
     * Assert that a call was refused with the given error, and returned nothing.
     *
     * @param array $response The call_external_function() response.
     * @param string $errorcode The expected error code.
     * @param string|null $capability The capability the refusal names, if it names one.
     * @param string $label What the assertion is about.
     * @return void
     */
    private function assert_refused(array $response, string $errorcode, ?string $capability, string $label): void {
        $this->assertTrue($response['error'], $label);
        $this->assertSame($errorcode, $response['exception']->errorcode, $label);
        if ($capability !== null) {
            $this->assertSame(
                get_string('nopermissions', 'error', get_capability_string($capability)),
                $response['exception']->message,
                $label
            );
        }
        $this->assertArrayNotHasKey('data', $response, $label);
    }

    /**
     * Call a card web service as the current user.
     *
     * @param string $wsname The web service name.
     * @param array $courseids The course ids.
     * @param int $planid The plan id.
     * @param int $competencyid The competency id.
     * @return array The call_external_function() response.
     */
    private function call(string $wsname, array $courseids, int $planid, int $competencyid): array {
        $_POST['sesskey'] = sesskey();

        return external_api::call_external_function($wsname, [
            'courseids' => $courseids,
            'planid' => $planid,
            'competencyid' => $competencyid,
        ]);
    }

    /**
     * Call a card web service as the current user and return its rows.
     *
     * @param string $wsname The web service name.
     * @param array $courseids The course ids.
     * @param int $planid The plan id, 0 for none.
     * @param int $competencyid The competency id.
     * @return array The cleaned rows, keyed by course id.
     */
    private function rows(string $wsname, array $courseids, int $planid = 0, int $competencyid = 0): array {
        $response = $this->call($wsname, $courseids, $planid, $competencyid);
        $this->assertFalse($response['error'], json_encode($response['exception'] ?? null));

        return array_column($response['data'], null, 'courseid');
    }
}
