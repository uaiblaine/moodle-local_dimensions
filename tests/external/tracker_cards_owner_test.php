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
 * Whose data the competency tracker carries when a teacher opens it from a learner's plan.
 *
 * With the plan, the tracker describes the plan owner, as the plan accordion does
 * (competency_courses_owner_test): the course list the enrolment filter keeps, the section progress,
 * the section restrictions, the completion and the card shape are the owner's. What the viewer can
 * do with a card is the viewer's: it opens when the viewer is actively enrolled, and a card the viewer
 * cannot open carries the viewer's lock date, enrolment start, enrol and pending state. Without a plan
 * the services describe the caller, and on the learner's own plan nothing changes.
 *
 * The covers tags stay in this docblock because moodle-cs for Moodle 4.5 cannot see PHPUnit
 * attributes.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\external\get_course_progress
 * @covers     \local_dimensions\external\get_courses_completion_status
 * @covers     \local_dimensions\calculator
 * @covers     \local_dimensions\output\view_competency_page
 */
final class tracker_cards_owner_test extends \advanced_testcase {
    /** @var string The progress web service. */
    private const PROGRESS = 'local_dimensions_get_course_progress';

    /** @var string The completion status web service. */
    private const STATUS = 'local_dimensions_get_courses_completion_status';

    /**
     * A teacher reviewing a learner's plan gets the learner's progress, restrictions and card shape,
     * with the teacher's own access.
     *
     * Each owner-side value the assertions read differs between the two people, which the
     * preconditions prove before the call, so reading the viewer instead of the owner fails the test.
     *
     * @return void
     */
    public function test_a_teacher_gets_the_plan_owners_progress(): void {
        $f = $this->build_fixture();
        $teacherid = (int) $f['teacher']->id;
        $learnerid = (int) $f['learner']->id;

        // Preconditions: the teacher's own answer to each owner-side question differs from the learner's.
        $this->assertTrue(calculator::is_locked(get_course($f['alpha']), $teacherid));
        $alphamodinfo = get_fast_modinfo($f['alpha'], $teacherid);
        $this->assertTrue($alphamodinfo->get_section_info(2)->uservisible);
        $this->assertFalse(get_fast_modinfo($f['alpha'], $learnerid)->get_section_info(2)->uservisible);
        $completion = new \completion_info(get_course($f['alpha']));
        $teacherdone = $completion->get_data($alphamodinfo->get_cm($f['done']), true, $teacherid);
        $this->assertEquals(COMPLETION_INCOMPLETE, $teacherdone->completionstate);
        $groupinfo = new \core_availability\info_module(get_fast_modinfo($f['alpha'], $learnerid)->get_cm($f['grouponly']));
        $this->assertCount(1, $groupinfo->filter_user_list([$teacherid => (object) ['id' => $teacherid]]));
        $this->assertCount(0, $groupinfo->filter_user_list([$learnerid => (object) ['id' => $learnerid]]));
        $this->assertFalse(is_enrolled(\context_course::instance($f['charlie']), $teacherid, '', true));
        $this->assertNull(calculator::get_enrolment_start_date(get_course($f['charlie']), $learnerid));
        $this->assertFalse(calculator::resolve_card_shape($f['delta'], $teacherid)['activity']['completed']);

        $this->setUser($f['teacher']);
        $rows = $this->rows(self::PROGRESS, $f['courseids'], $f['planid'], $f['competencyid']);

        // Alpha: the teacher can open it, and every number and restriction on it is the learner's.
        $alpha = $rows[$f['alpha']];
        $this->assertFalse($alpha['locked']);
        $this->assertSame(constants::CARDMODE_TIMELINE, $alpha['cardmode']);
        $this->assertSame(50, $alpha['sections'][1]['percentage']);
        $this->assertTrue($alpha['sections'][1]['is_started']);
        $this->assertStringContainsString('/course/section.php', $alpha['sections'][1]['url']);
        $this->assertTrue($alpha['sections'][2]['locked']);
        $this->assertFalse($alpha['sections'][2]['has_activities']);
        $this->assertStringContainsString('/course/view.php?id=' . $f['alpha'], $alpha['sections'][2]['url']);

        // Charlie: the teacher's enrolment starts later, so the card is locked on the teacher's date,
        // still showing the learner's progress behind the overlay, with no link to follow.
        $charlie = $rows[$f['charlie']];
        $this->assertTrue($charlie['locked']);
        $this->assertSame(constants::CARDMODE_TIMELINE, $charlie['cardmode']);
        $this->assertArrayNotHasKey('activity', $charlie);
        $this->assertSame(100, $charlie['sections'][1]['percentage']);
        $this->assertTrue($charlie['sections'][1]['is_completed']);
        foreach ($charlie['sections'] as $section) {
            $this->assertSame('', $section['url']);
            $this->assertFalse($section['locked']);
        }
        $this->assertSame(
            userdate($f['teacherstart'], get_string('strftimedatefullshort', 'langconfig')),
            $charlie['formatted_start_date']
        );
        $this->assertTrue($charlie['is_enrolment_start']);
        $this->assertTrue($charlie['is_future_date']);
        $this->assertFalse($charlie['can_self_enrol']);
        $this->assertFalse($charlie['is_pending']);

        // Delta: the learner's single activity, with the learner's completion.
        $this->assertFalse($rows[$f['delta']]['locked']);
        $this->assertSame(constants::CARDMODE_ACTIVITY, $rows[$f['delta']]['cardmode']);
        $this->assertSame('Only', $rows[$f['delta']]['activity']['name']);
        $this->assertTrue($rows[$f['delta']]['activity']['completed']);

        // Echo: the teacher is not enrolled but may enrol themselves, whatever the learner's enrolment.
        $this->assertTrue($rows[$f['echo']]['locked']);
        $this->assertTrue($rows[$f['echo']]['can_self_enrol']);
        $this->assertFalse($rows[$f['echo']]['is_pending']);
    }

    /**
     * Without a plan the progress service still describes its caller, on the same fixture.
     *
     * The teacher holds no student role, so is_locked() locks every course of theirs: no progress and
     * the timeline shape, where the call with the plan above opens Alpha and Delta on the learner's data.
     *
     * @return void
     */
    public function test_without_a_plan_the_progress_is_the_callers(): void {
        $f = $this->build_fixture();

        $this->setUser($f['teacher']);
        $rows = $this->rows(self::PROGRESS, $f['courseids']);

        $this->assertTrue($rows[$f['alpha']]['locked']);
        $this->assertSame(0, $rows[$f['alpha']]['sections'][1]['percentage']);
        $this->assertFalse($rows[$f['alpha']]['sections'][1]['has_activities']);
        $this->assertSame('', $rows[$f['alpha']]['sections'][1]['url']);
        $this->assertTrue($rows[$f['delta']]['locked']);
        $this->assertSame(constants::CARDMODE_TIMELINE, $rows[$f['delta']]['cardmode']);
        $this->assertArrayNotHasKey('activity', $rows[$f['delta']]);

        // The control: the same caller, with the plan, gets the learner's cards.
        $withplan = $this->rows(self::PROGRESS, $f['courseids'], $f['planid'], $f['competencyid']);
        $this->assertFalse($withplan[$f['alpha']]['locked']);
        $this->assertSame(50, $withplan[$f['alpha']]['sections'][1]['percentage']);
    }

    /**
     * The completed-courses count is the learner's, and the lock the teacher's, with the plan; the
     * caller's own without it.
     *
     * @return void
     */
    public function test_a_teacher_gets_the_plan_owners_completion_status(): void {
        $f = $this->build_fixture();
        // Preconditions: the learner completed Delta, the teacher did not.
        $completion = new \completion_info(get_course($f['delta']));
        $this->assertTrue($completion->is_course_complete((int) $f['learner']->id));
        $this->assertFalse($completion->is_course_complete((int) $f['teacher']->id));

        $this->setUser($f['teacher']);
        $rows = $this->rows(self::STATUS, $f['courseids'], $f['planid'], $f['competencyid']);

        $this->assertTrue($rows[$f['delta']]['iscompleted']);
        $this->assertFalse($rows[$f['delta']]['islocked']);
        $this->assertFalse($rows[$f['alpha']]['iscompleted']);
        $this->assertFalse($rows[$f['alpha']]['islocked']);
        $this->assertTrue($rows[$f['charlie']]['islocked']);
        $this->assertTrue($rows[$f['echo']]['islocked']);

        // The control: without the plan both flags are the teacher's own, and is_locked() locks them out.
        $own = $this->rows(self::STATUS, $f['courseids']);
        $this->assertFalse($own[$f['delta']]['iscompleted']);
        $this->assertTrue($own[$f['delta']]['islocked']);
        $this->assertTrue($own[$f['alpha']]['islocked']);
    }

    /**
     * On their own plan the learner gets exactly the cards they get without the plan: is_locked() decides.
     *
     * India enrols the learner under a non-student role, which is_locked() locks and an enrolment
     * check alone would open.
     *
     * @return void
     */
    public function test_the_owner_on_their_own_plan_gets_the_cards_they_always_got(): void {
        $f = $this->build_fixture();
        $this->assertTrue(is_enrolled(\context_course::instance($f['india']), (int) $f['learner']->id, '', true));

        $this->setUser($f['learner']);
        $courseids = array_merge($f['courseids'], [$f['india']]);
        $withplan = $this->rows(self::PROGRESS, $courseids, $f['planid'], $f['competencyid']);
        $without = $this->rows(self::PROGRESS, $courseids);

        $this->assertSame($without, $withplan);
        $this->assertTrue($withplan[$f['india']]['locked']);
        $this->assertFalse($withplan[$f['alpha']]['locked']);
        $this->assertSame(50, $withplan[$f['alpha']]['sections'][1]['percentage']);
        $this->assertFalse($withplan[$f['charlie']]['locked']);
        $this->assertSame(constants::CARDMODE_ACTIVITY, $withplan[$f['charlie']]['cardmode']);
        $this->assertFalse($withplan[$f['echo']]['locked']);

        $statuswithplan = $this->rows(self::STATUS, $courseids, $f['planid'], $f['competencyid']);
        $this->assertSame($this->rows(self::STATUS, $courseids), $statuswithplan);
        $this->assertTrue($statuswithplan[$f['india']]['islocked']);
        $this->assertFalse($statuswithplan[$f['alpha']]['islocked']);
        $this->assertTrue($statuswithplan[$f['delta']]['iscompleted']);
    }

    /**
     * A pending application on a learner's tracker is the viewer's own, never the learner's.
     *
     * Skipped where enrol_apply is not installed, which includes every Moodle 4.5 site: enrol_apply
     * supports Moodle 5.1 and later only.
     *
     * @return void
     */
    public function test_a_teacher_sees_their_own_pending_application(): void {
        global $DB;

        $f = $this->build_fixture();
        $plugin = enrol_get_plugin('apply');
        if (!$plugin || !is_callable([$plugin, 'allow_apply'])) {
            $this->markTestSkipped('enrol_apply is not installed on this site.');
        }
        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        $this->setAdminUser();
        $foxtrot = $this->getDataGenerator()->create_course(['fullname' => 'Foxtrot', 'enablecompletion' => 1]);
        \core_competency\api::add_competency_to_course((int) $foxtrot->id, $f['competencyid']);
        $instanceid = $plugin->add_instance($foxtrot, [
            'status' => ENROL_INSTANCE_ENABLED,
            'customint3' => 0,
            'customint5' => 0,
            'customint6' => 1,
        ]);
        $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
        $this->getDataGenerator()->enrol_user((int) $f['learner']->id, (int) $foxtrot->id, 'student');
        $plugin->enrol_user($instance, (int) $f['teacher']->id, null, 0, 0, ENROL_USER_SUSPENDED);
        // The precondition: the application is the teacher's; the learner has none.
        $this->assertFalse(calculator::has_pending_application((int) $foxtrot->id, (int) $f['learner']->id));

        $this->setUser($f['teacher']);
        $row = $this->rows(self::PROGRESS, [(int) $foxtrot->id], $f['planid'], $f['competencyid'])[(int) $foxtrot->id];

        $this->assertTrue($row['locked']);
        $this->assertFalse($row['can_self_enrol']);
        $this->assertTrue($row['is_pending']);
    }

    /**
     * The tracker page a teacher opens from a learner's plan lists the learner's courses, locks only what
     * the teacher cannot open, and hands the plan and competency to the card script.
     *
     * The learner's own page is the control: the same course list, locked by is_locked().
     *
     * @return void
     */
    public function test_the_tracker_page_lists_the_plan_owners_courses(): void {
        $f = $this->build_fixture();
        $learnercourses = [$f['alpha'], $f['charlie'], $f['delta'], $f['echo'], $f['india']];
        sort($learnercourses);

        $this->setUser($f['teacher']);
        $html = $this->render_tracker_page($f['planid'], $f['competencyid']);

        $cards = $this->cards($html);
        $this->assertSame($learnercourses, array_keys($cards));
        $this->assertFalse($cards[$f['alpha']]);
        $this->assertTrue($cards[$f['charlie']]);
        $this->assertFalse($cards[$f['delta']]);
        $this->assertTrue($cards[$f['echo']]);
        $this->assertTrue($cards[$f['india']]);
        $settings = $this->card_script_settings();
        $this->assertSame($f['planid'], $settings['planid']);
        $this->assertSame($f['competencyid'], $settings['competencyid']);

        $this->setUser($f['learner']);
        $own = $this->cards($this->render_tracker_page($f['planid'], $f['competencyid']));

        $this->assertSame($learnercourses, array_keys($own));
        $this->assertFalse($own[$f['alpha']]);
        $this->assertFalse($own[$f['charlie']]);
        $this->assertFalse($own[$f['echo']]);
        $this->assertTrue($own[$f['india']]);
    }

    /**
     * A learner's active plan over one competency, linked courses and a teacher allowed to read it.
     *
     * The site filter shows only courses the plan owner is actively enrolled in. The learner is a
     * student wherever enrolled, except in India; the teacher an editing teacher.
     * - Alpha: both enrolled. Section 1 holds Done (completed by the learner), Todo, and GroupOnly,
     *   restricted to a group the learner is not in; all three tracked. Section 2 is restricted by a
     *   future date, shown greyed, and holds one tracked activity. The teacher ignores both restrictions.
     * - Bravo: only the teacher is enrolled.
     * - Charlie: the learner is enrolled and completed its one tracked activity; the teacher's
     *   enrolment starts in two weeks.
     * - Delta: both enrolled, one tracked activity the learner completed, and the learner completed the
     *   course.
     * - Echo: self enrolment is open; only the learner is enrolled.
     * - India: only the learner is enrolled, as a non-editing teacher.
     *
     * @return array Keys competencyid, planid, learner, teacher, the course ids alpha, bravo, charlie,
     *     delta, echo and india, the course module ids done and grouponly, teacherstart (the teacher's
     *     Charlie start date) and courseids (alpha, charlie, delta and echo).
     */
    private function build_fixture(): array {
        global $CFG, $DB;
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

        $names = ['alpha', 'bravo', 'charlie', 'delta', 'echo', 'india'];
        $courses = [];
        foreach ($names as $key) {
            $courses[$key] = (int) $dg->create_course([
                'fullname' => ucfirst($key),
                'enablecompletion' => 1,
                'numsections' => 3,
            ])->id;
            \core_competency\api::add_competency_to_course($courses[$key], $competencyid);
        }

        $tracked = ['completion' => COMPLETION_TRACKING_MANUAL, 'section' => 1];
        $done = $this->page($courses['alpha'], ['name' => 'Done'] + $tracked);
        $this->page($courses['alpha'], ['name' => 'Todo'] + $tracked);
        $group = $dg->create_group(['courseid' => $courses['alpha']]);
        $grouponly = $this->page($courses['alpha'], [
            'name' => 'GroupOnly',
            'availability' => '{"op":"&","c":[{"type":"group","id":' . (int) $group->id . '}],"showc":[true]}',
        ] + $tracked);
        $this->page($courses['alpha'], ['name' => 'Later', 'section' => 2] + $tracked);
        $DB->set_field(
            'course_sections',
            'availability',
            '{"op":"&","c":[{"type":"date","d":">=","t":' . (time() + WEEKSECS) . '}],"showc":[true]}',
            ['course' => $courses['alpha'], 'section' => 2]
        );
        rebuild_course_cache($courses['alpha'], true);
        $task = $this->page($courses['charlie'], ['name' => 'Task'] + $tracked);
        $only = $this->page($courses['delta'], ['name' => 'Only'] + $tracked);

        $self = $DB->get_record('enrol', ['courseid' => $courses['echo'], 'enrol' => 'self'], '*', MUST_EXIST);
        enrol_get_plugin('self')->update_status($self, ENROL_INSTANCE_ENABLED);

        foreach (['alpha', 'charlie', 'delta', 'echo'] as $key) {
            $dg->enrol_user((int) $learner->id, $courses[$key], 'student');
        }
        $dg->enrol_user((int) $learner->id, $courses['india'], 'teacher');
        foreach (['alpha', 'bravo', 'delta'] as $key) {
            $dg->enrol_user((int) $teacher->id, $courses[$key], 'editingteacher');
        }
        $teacherstart = time() + 2 * WEEKSECS;
        $dg->enrol_user((int) $teacher->id, $courses['charlie'], 'editingteacher', 'manual', $teacherstart);

        foreach ([$courses['alpha'] => $done, $courses['charlie'] => $task, $courses['delta'] => $only] as $courseid => $cmid) {
            $completion = new \completion_info(get_course($courseid));
            $cm = get_coursemodule_from_id('page', $cmid, 0, false, MUST_EXIST);
            $completion->update_state($cm, COMPLETION_COMPLETE, (int) $learner->id);
        }
        (new \completion_completion(['course' => $courses['delta'], 'userid' => (int) $learner->id]))->mark_complete();

        return $courses + [
            'competencyid' => $competencyid,
            'planid' => $planid,
            'learner' => $learner,
            'teacher' => $teacher,
            'done' => $done,
            'grouponly' => $grouponly,
            'teacherstart' => $teacherstart,
            'courseids' => [$courses['alpha'], $courses['charlie'], $courses['delta'], $courses['echo']],
        ];
    }

    /**
     * Create a page in a course.
     *
     * @param int $courseid The course id.
     * @param array $record The module record, merged over the course.
     * @return int The course module id.
     */
    private function page(int $courseid, array $record): int {
        return (int) $this->getDataGenerator()->create_module('page', ['course' => $courseid] + $record)->cmid;
    }

    /**
     * Call a card web service as the current user.
     *
     * @param string $wsname The web service name.
     * @param array $courseids The course ids.
     * @param int|null $planid The plan id, or null to leave both optional parameters out.
     * @param int $competencyid The competency id, sent with a plan id.
     * @return array The cleaned rows, keyed by course id.
     */
    private function rows(string $wsname, array $courseids, ?int $planid = null, int $competencyid = 0): array {
        $args = ['courseids' => $courseids];
        if ($planid !== null) {
            $args += ['planid' => $planid, 'competencyid' => $competencyid];
        }
        $_POST['sesskey'] = sesskey();
        $response = external_api::call_external_function($wsname, $args);
        $this->assertFalse($response['error'], json_encode($response['exception'] ?? null));

        return array_column($response['data'], null, 'courseid');
    }

    /**
     * The course cards of a rendered tracker page, and whether each is locked.
     *
     * @param string $html The page's HTML.
     * @return array Course id => whether the card is locked, sorted by course id.
     */
    private function cards(string $html): array {
        preg_match_all(
            '/data-courseid="(\d+)"\s+data-filtervalues="[^"]*">\s*<div class="card h-100 ([^"]*)">/',
            $html,
            $matches,
            PREG_SET_ORDER
        );
        $cards = [];
        foreach ($matches as $match) {
            $cards[(int) $match[1]] = str_contains($match[2], 'local-dimensions-card-locked');
        }
        ksort($cards);

        return $cards;
    }

    /**
     * The settings the last rendered page handed to the card script's init().
     *
     * The page's footer JavaScript is not part of what a test render prints, so the call is read from
     * the page's requirements, where js_call_amd() left it.
     *
     * @return array The decoded settings.
     */
    private function card_script_settings(): array {
        global $PAGE;

        $calls = implode("\n", (new \ReflectionProperty($PAGE->requires, 'amdjscode'))->getValue($PAGE->requires));
        $pattern = '/require\(\[\'local_dimensions\/competency_view\'\], function\(amd\) \{amd\.init\((\{.*?\})\);/';
        $this->assertSame(1, preg_match($pattern, $calls, $match), 'The card script is not initialised.');

        return json_decode($match[1], true);
    }

    /**
     * Run view-competency.php as a request by the current user would, and return what it printed.
     *
     * See plan_access_test::render_tracker_page(), which this copies.
     *
     * @param int $planid The plan id parameter.
     * @param int $competencyid The competency id parameter.
     * @return string The page's HTML.
     */
    private function render_tracker_page(int $planid, int $competencyid): string {
        // The page runs in this method's scope and reads these as its globals.
        global $CFG, $DB, $OUTPUT, $PAGE, $USER;

        $PAGE = new \moodle_page();
        $OUTPUT = new \bootstrap_renderer();
        $_GET = ['id' => $planid, 'competencyid' => $competencyid];
        $cwd = getcwd();
        chdir($CFG->dirroot . '/local/dimensions');
        ob_start();
        try {
            require($CFG->dirroot . '/local/dimensions/view-competency.php');
        } finally {
            $html = ob_get_clean();
            chdir($cwd);
            $_GET = [];
        }

        return $html;
    }
}
