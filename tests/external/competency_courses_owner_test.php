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

use core_competency\plan;
use core_external\external_api;
use local_dimensions\calculator;
use local_dimensions\constants;
use local_dimensions\helper;

/**
 * Whose data the accordion's course cards carry when a teacher opens a learner's plan.
 *
 * The cards describe the plan owner: which courses the enrolment filter keeps, the progress, the
 * activities' completion and restrictions, and the card shape. Only the access state and its lock
 * date are the viewer's, since they decide what clicking the card does.
 *
 * The covers tag stays in this docblock because moodle-cs for Moodle 4.5 cannot see PHPUnit
 * attributes.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\external\get_competency_courses
 */
final class competency_courses_owner_test extends \advanced_testcase {
    /** @var string Web service under test. */
    private const WSNAME = 'local_dimensions_get_competency_courses';

    /**
     * A teacher reviewing a learner's plan sees the learner's courses, progress and completion.
     *
     * Each owner-side field the assertions read differs between the two people, which the
     * preconditions prove before the call, so reading the viewer instead of the owner fails the test.
     *
     * @return void
     */
    public function test_a_teacher_sees_the_plan_owners_cards(): void {
        $f = $this->build_fixture();

        // Preconditions: the teacher's own answer to each question differs from the learner's.
        $teacherid = (int) $f['teacher']->id;
        $this->assertTrue(is_enrolled(\context_course::instance($f['bravo']), $teacherid, '', true));
        $this->assertFalse(is_enrolled(\context_course::instance($f['bravo']), (int) $f['learner']->id));
        $this->assertSame(0, calculator::course_completion_percentage($f['alpha'], $teacherid));
        $alphamodinfo = get_fast_modinfo($f['alpha'], $teacherid);
        $this->assertTrue($alphamodinfo->get_cm($f['later'])->uservisible);
        $completion = new \completion_info(get_course($f['alpha']));
        $teacherdone = $completion->get_data($alphamodinfo->get_cm($f['done']), true, $teacherid);
        $this->assertEquals(COMPLETION_INCOMPLETE, $teacherdone->completionstate);
        $this->assertFalse(calculator::resolve_card_shape($f['delta'], $teacherid)['activity']['completed']);

        $this->setUser($f['teacher']);
        $rows = $this->rows($f['competencyid'], $f['planid']);

        $this->assertSame($f['learnercourses'], $this->sorted_ids($rows));

        $this->assertSame(50, $rows[$f['alpha']]['progress']);
        $activities = array_column($rows[$f['alpha']]['activities'], null, 'cmid');
        $this->assertTrue($activities[$f['done']]['is_completed']);
        $this->assertFalse($activities[$f['todo']]['is_completed']);
        $this->assertTrue($activities[$f['later']]['locked']);
        $this->assertStringContainsString('/course/view.php?id=' . $f['alpha'], $activities[$f['later']]['url']);

        $this->assertSame(constants::CARDMODE_ACTIVITY, $rows[$f['delta']]['cardmode']);
        $this->assertTrue($rows[$f['delta']]['activity']['completed']);

        // Access is the viewer's: the teacher's enrolment in Charlie starts later, the learner's is active.
        $this->assertSame('open', $rows[$f['alpha']]['access']);
        $this->assertSame('locked', $rows[$f['charlie']]['access']);
        $this->assertSame($f['teacherstart'], $rows[$f['charlie']]['lockdate']);
        $this->assertTrue($rows[$f['charlie']]['isenrolstart']);
    }

    /**
     * The learner's own view of the plan: the same cards, with the learner's own access.
     *
     * The control for the test above: it shows the fixture's values are the learner's, and that the
     * two viewers get identical cards wherever both can open the course.
     *
     * @return void
     */
    public function test_the_owner_sees_the_same_cards_with_their_own_access(): void {
        $f = $this->build_fixture();

        $this->setUser($f['teacher']);
        $teacherrows = $this->rows($f['competencyid'], $f['planid']);
        $this->setUser($f['learner']);
        $rows = $this->rows($f['competencyid'], $f['planid']);

        $this->assertSame($f['learnercourses'], $this->sorted_ids($rows));
        $this->assertSame(50, $rows[$f['alpha']]['progress']);
        $this->assertTrue($rows[$f['delta']]['activity']['completed']);
        $this->assertSame($rows[$f['alpha']], $teacherrows[$f['alpha']]);
        $this->assertSame($rows[$f['delta']], $teacherrows[$f['delta']]);

        $this->assertSame('open', $rows[$f['charlie']]['access']);
        $this->assertSame(0, $rows[$f['charlie']]['lockdate']);
        $this->assertFalse($rows[$f['charlie']]['isenrolstart']);
    }

    /**
     * A learner's active plan over one competency, four linked courses and a teacher allowed to read it.
     *
     * The site filter shows only courses the plan owner is actively enrolled in.
     * - Alpha: both enrolled. Three linked activities: Done (tracked, completed by the learner), Todo
     *   (tracked) and Later (untracked, restricted by a future date and shown greyed). The teacher
     *   ignores the restriction.
     * - Bravo: only the teacher is enrolled.
     * - Charlie: the learner is enrolled; the teacher's enrolment starts in two weeks.
     * - Delta: both enrolled, one tracked activity the learner completed.
     *
     * @return array Keys competencyid, planid, learner, teacher, the course ids alpha, bravo,
     *     charlie and delta, the course module ids done, todo and later, teacherstart (the teacher's
     *     Charlie start date) and learnercourses (the sorted ids of the courses the learner's filter keeps).
     */
    private function build_fixture(): array {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enablecompletion', 1);
        set_config('enableavailability', 1);
        helper::ensure_custom_fields_exist(helper::AREA_LP);
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);
        set_config('enrollmentfilter', constants::ENROLLMENTFILTER_ACTIVE, 'local_dimensions');

        $dg = $this->getDataGenerator();
        $ccg = $dg->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework();
        $competencyid = (int) $ccg->create_competency(['competencyframeworkid' => $framework->get('id')])->get('id');

        $learner = $dg->create_user();
        $teacher = $dg->create_user();
        $planid = (int) $ccg->create_plan(['userid' => $learner->id, 'status' => plan::STATUS_ACTIVE])->get('id');
        $ccg->create_plan_competency(['planid' => $planid, 'competencyid' => $competencyid]);

        // A reviewer reading learners' plans, as a site-level role grants it.
        $systemcontext = \context_system::instance();
        $reviewerroleid = $dg->create_role();
        assign_capability('moodle/competency:planview', CAP_ALLOW, $reviewerroleid, $systemcontext->id);
        role_assign($reviewerroleid, (int) $teacher->id, $systemcontext->id);

        $courses = [];
        foreach (['alpha' => 'Alpha', 'bravo' => 'Bravo', 'charlie' => 'Charlie', 'delta' => 'Delta'] as $key => $name) {
            $courses[$key] = (int) $dg->create_course(['fullname' => $name, 'enablecompletion' => 1])->id;
            \core_competency\api::add_competency_to_course($courses[$key], $competencyid);
        }

        $tracked = ['completion' => COMPLETION_TRACKING_MANUAL];
        $done = $this->linked_page($courses['alpha'], $competencyid, ['name' => 'Done'] + $tracked);
        $todo = $this->linked_page($courses['alpha'], $competencyid, ['name' => 'Todo'] + $tracked);
        $later = $this->linked_page($courses['alpha'], $competencyid, [
            'name' => 'Later',
            'availability' => '{"op":"&","c":[{"type":"date","d":">=","t":' . (time() + WEEKSECS) . '}],"showc":[true]}',
        ]);
        $only = $this->linked_page($courses['delta'], $competencyid, ['name' => 'Only'] + $tracked);

        foreach (['alpha', 'charlie', 'delta'] as $key) {
            $dg->enrol_user((int) $learner->id, $courses[$key], 'student');
        }
        foreach (['alpha', 'bravo', 'delta'] as $key) {
            $dg->enrol_user((int) $teacher->id, $courses[$key], 'editingteacher');
        }
        $teacherstart = time() + 2 * WEEKSECS;
        $dg->enrol_user((int) $teacher->id, $courses['charlie'], 'editingteacher', 'manual', $teacherstart);

        foreach ([$courses['alpha'] => $done, $courses['delta'] => $only] as $courseid => $cmid) {
            $completion = new \completion_info(get_course($courseid));
            $cm = get_coursemodule_from_id('page', $cmid, 0, false, MUST_EXIST);
            $completion->update_state($cm, COMPLETION_COMPLETE, (int) $learner->id);
        }

        $learnercourses = [$courses['alpha'], $courses['charlie'], $courses['delta']];
        sort($learnercourses);

        return $courses + [
            'competencyid' => $competencyid,
            'planid' => $planid,
            'learner' => $learner,
            'teacher' => $teacher,
            'done' => $done,
            'todo' => $todo,
            'later' => $later,
            'teacherstart' => $teacherstart,
            'learnercourses' => $learnercourses,
        ];
    }

    /**
     * Create a page in a course and link it to the competency.
     *
     * @param int $courseid The course id.
     * @param int $competencyid The competency id.
     * @param array $record The module record, merged over the course.
     * @return int The course module id.
     */
    private function linked_page(int $courseid, int $competencyid, array $record): int {
        $cmid = (int) $this->getDataGenerator()->create_module('page', ['course' => $courseid] + $record)->cmid;
        \core_competency\api::add_competency_to_course_module($cmid, $competencyid);

        return $cmid;
    }

    /**
     * Call the web service as the current user.
     *
     * @param int $competencyid The competency id.
     * @param int $planid The plan id.
     * @return array The cleaned rows, keyed by course id.
     */
    private function rows(int $competencyid, int $planid): array {
        $_POST['sesskey'] = sesskey();
        $response = external_api::call_external_function(self::WSNAME, [
            'competencyid' => $competencyid,
            'planid' => $planid,
        ]);
        $this->assertFalse($response['error'], json_encode($response['exception'] ?? null));

        return array_column($response['data'], null, 'id');
    }

    /**
     * The course ids of a response, sorted.
     *
     * @param array $rows The rows, keyed by course id.
     * @return array
     */
    private function sorted_ids(array $rows): array {
        $ids = array_map('intval', array_keys($rows));
        sort($ids);

        return $ids;
    }
}
