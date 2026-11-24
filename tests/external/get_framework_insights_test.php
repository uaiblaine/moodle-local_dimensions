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
 * Tests for the get_framework_insights external function.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use context_coursecat;
use context_system;
use core_competency\competency;
use core_competency\competency_rule_all;
use core_competency\competency_rule_points;
use core_external\external_api;
use local_dimensions\constants;
use local_dimensions\customfield\competency_handler;
use local_dimensions\helper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The framework insights: who may ask, what each viewer is told about usage, and the rules,
 * relations, checks and summary measured against known fixtures, in a bounded number of reads.
 */
#[CoversClass(\local_dimensions\external\get_framework_insights::class)]
#[CoversClass(\local_dimensions\local\contrast_ratio::class)]
final class get_framework_insights_test extends \advanced_testcase {
    /** @var string Web service under test. */
    private const WSNAME = 'local_dimensions_get_framework_insights';

    /**
     * Forget local_unlistedcourses' per-request memos, which outlive a test's rollback.
     *
     * @return void
     */
    private function reset_unlisted_memos(): void {
        if (class_exists('\local_unlistedcourses\category_access')) {
            \local_unlistedcourses\category_discoverability::reset_caches();
            \local_unlistedcourses\category_access::reset_caches();
        }
    }

    /**
     * Start every test with local_unlistedcourses' memos empty.
     *
     * @return void
     */
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->reset_unlisted_memos();
    }

    /**
     * Drop the sesskey the calls plant in $_POST, which resetAfterTest() does not restore.
     *
     * @return void
     */
    #[\Override]
    protected function tearDown(): void {
        unset($_POST['sesskey']);
        $this->reset_unlisted_memos();
        parent::tearDown();
    }

    /**
     * Call the web service the way the browser does, as the current user.
     *
     * @param int $frameworkid Framework id.
     * @return array The call_external_function() response.
     */
    private function call(int $frameworkid): array {
        $_POST['sesskey'] = sesskey();
        return external_api::call_external_function(self::WSNAME, ['frameworkid' => $frameworkid]);
    }

    /**
     * Call the web service and return its cleaned data, failing on an error.
     *
     * @param int $frameworkid Framework id.
     * @return array The response data.
     */
    private function data(int $frameworkid): array {
        $response = $this->call($frameworkid);
        $this->assertFalse($response['error'], json_encode($response['exception'] ?? null));
        return $response['data'];
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

    /**
     * A list of the answer keyed by competency id.
     *
     * @param array $rows Rows carrying a competencyid.
     * @return array Competency id => row.
     */
    private static function by_competency(array $rows): array {
        return array_column($rows, null, 'competencyid');
    }

    /**
     * The competency ids carrying one check code, sorted.
     *
     * @param array $data The response data.
     * @param string $code The check code.
     * @return array
     */
    private static function flagged(array $data, string $code): array {
        $ids = [];
        foreach ($data['checks'] as $check) {
            if ($check['code'] === $code) {
                $ids[] = (int) $check['competencyid'];
            }
        }
        sort($ids);
        return $ids;
    }

    /**
     * The params of the one check of a code on a competency, as name => value.
     *
     * @param array $data The response data.
     * @param string $code The check code.
     * @param int $competencyid The competency id.
     * @return array
     */
    private function check_params(array $data, string $code, int $competencyid): array {
        $found = array_values(array_filter(
            $data['checks'],
            static fn(array $c): bool => $c['code'] === $code && (int) $c['competencyid'] === $competencyid
        ));
        $this->assertCount(1, $found, "$code on $competencyid");
        return array_column($found[0]['params'], 'value', 'name');
    }

    /**
     * A framework in a course category with one competency, competencies enabled, as the admin.
     *
     * @return array Keys: category (stdClass), context (context_coursecat), frameworkid (int), user (stdClass).
     */
    private function category_framework(): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $dg = $this->getDataGenerator();
        $ccg = $dg->get_plugin_generator('core_competency');
        $category = $dg->create_category();
        $context = context_coursecat::instance((int) $category->id);
        $framework = $ccg->create_framework(['contextid' => $context->id]);
        $ccg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        return [
            'category' => $category,
            'context' => $context,
            'frameworkid' => (int) $framework->get('id'),
            'user' => $dg->create_user(),
        ];
    }

    // Refusals first: each beside the control the same call passes.

    /**
     * Nobody logged in: the AJAX door refuses before the service, and the service's own
     * require_login() refuses when reached directly; a logged-in reader is the control.
     *
     * @return void
     */
    public function test_refuses_without_login(): void {
        $f = $this->category_framework();
        $this->setUser($f['user']);
        $this->assertSame($f['frameworkid'], $this->data($f['frameworkid'])['frameworkid'], 'Control: a reader');

        $this->setUser(null);
        $this->assert_refused('servicerequireslogin', $this->call($f['frameworkid']), 'The AJAX door');
        try {
            get_framework_insights::execute($f['frameworkid']);
            $this->fail('The service answered a visitor who is not logged in.');
        } catch (\require_login_exception $e) {
            $this->assertSame('requireloginerror', $e->errorcode);
        }
    }

    /**
     * The guest is refused even when the site grants it competencyview; a user is the control.
     *
     * @return void
     */
    public function test_refuses_the_guest(): void {
        global $CFG;
        $f = $this->category_framework();
        assign_capability('moodle/competency:competencyview', CAP_ALLOW, (int) $CFG->guestroleid, context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setGuestUser();
        $this->assertTrue(has_capability('moodle/competency:competencyview', $f['context']), 'Precondition: the guest reads');
        $this->assert_refused('noguest', $this->call($f['frameworkid']), 'The guest');

        $this->setUser($f['user']);
        $this->assertSame($f['frameworkid'], $this->data($f['frameworkid'])['frameworkid'], 'Control: a user');
    }

    /**
     * A user who may not read competencies in the framework's own context is refused; the same user
     * before the prohibition is the control.
     *
     * @return void
     */
    public function test_refuses_without_the_read_capability_in_the_framework_context(): void {
        global $CFG;
        $f = $this->category_framework();
        $this->setUser($f['user']);
        $this->assertSame($f['frameworkid'], $this->data($f['frameworkid'])['frameworkid'], 'Control: before the prohibition');

        assign_capability('moodle/competency:competencyview', CAP_PROHIBIT, (int) $CFG->defaultuserroleid, $f['context']->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assert_refused('nopermissions', $this->call($f['frameworkid']), 'Prohibited in the category');
    }

    /**
     * Competencies disabled on the site: refused; enabled is the control.
     *
     * @return void
     */
    public function test_refuses_when_competencies_are_disabled(): void {
        $f = $this->category_framework();
        $this->assertSame($f['frameworkid'], $this->data($f['frameworkid'])['frameworkid'], 'Control: enabled');
        set_config('enabled', 0, 'core_competency');
        $this->assert_refused('competenciesarenotenabled', $this->call($f['frameworkid']), 'Disabled');
    }

    /**
     * A framework that does not exist is refused, for the admin too.
     *
     * @return void
     */
    public function test_refuses_a_missing_framework(): void {
        global $DB;
        $f = $this->category_framework();
        $missing = (int) $DB->get_field_sql('SELECT MAX(id) FROM {competency_framework}') + 1;
        $response = $this->call($missing);
        $this->assertTrue($response['error']);
        // The table name reaches the error code only while debugging is on, as it is under PHPUnit.
        $this->assertContains($response['exception']->errorcode, ['invalidrecord', 'invalidrecordunknown']);
        $this->assertArrayNotHasKey('data', $response);
        $this->assertSame($f['frameworkid'], $this->data($f['frameworkid'])['frameworkid'], 'Control: an existing one');
    }

    /**
     * A framework in a category local_unlistedcourses withholds from the viewer gets the answer a
     * missing framework gets; the administrator and a role holder in the category are the controls.
     *
     * @return void
     */
    public function test_refuses_a_framework_in_a_withheld_category_as_if_missing(): void {
        global $DB;
        if (!class_exists('\local_unlistedcourses\category_discoverability')) {
            $this->markTestSkipped('local_unlistedcourses is not installed on this site.');
        }
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $dg = $this->getDataGenerator();
        $parent = $dg->create_category(['name' => 'Parent']);
        $child = $dg->create_category(['name' => 'Hidden child', 'parent' => $parent->id]);
        $childcontext = context_coursecat::instance((int) $child->id);
        $framework = $dg->get_plugin_generator('core_competency')->create_framework(['contextid' => $childcontext->id]);
        $frameworkid = (int) $framework->get('id');
        \local_unlistedcourses\category_discoverability::set_state(
            (int) $child->id,
            \local_unlistedcourses\category_discoverability::STATE_UNLISTED
        );
        $user = $dg->create_user();
        $member = $dg->create_user();
        role_assign($dg->create_role(), (int) $member->id, $childcontext->id);

        $this->assertSame($frameworkid, $this->data($frameworkid)['frameworkid'], 'Control: the administrator');

        $this->setUser($user);
        $this->assertTrue(has_capability('moodle/competency:competencyview', $childcontext), 'Precondition: the user reads');
        $withheld = $this->call($frameworkid);
        $missing = $this->call((int) $DB->get_field_sql('SELECT MAX(id) FROM {competency_framework}') + 1);
        $this->assert_refused($missing['exception']->errorcode, $withheld, 'Withheld as if missing');
        $this->assertSame($missing['exception']->message, $withheld['exception']->message);

        $this->reset_unlisted_memos();
        $this->setUser($member);
        $this->assertSame($frameworkid, $this->data($frameworkid)['frameworkid'], 'Control: a role holder in the category');
    }

    // Usage: who is told what.

    /**
     * A framework in a category whose competencies are used in every way core counts.
     *
     * A: two courses, one activity, one template, two plans (one of its own, one from the template).
     * B: the template, and the plan made from it. D: only an archived rating in a completed plan.
     * C: nothing at all.
     *
     * @return array Keys: context, frameworkid, a, b, c, d (ids as int).
     */
    private function usage_fixture(): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $dg = $this->getDataGenerator();
        $ccg = $dg->get_plugin_generator('core_competency');
        $category = $dg->create_category();
        $context = context_coursecat::instance((int) $category->id);
        $fwid = (int) $ccg->create_framework(['contextid' => $context->id])->get('id');
        $ids = [];
        foreach (['a', 'b', 'c', 'd'] as $key) {
            $ids[$key] = (int) $ccg->create_competency(['competencyframeworkid' => $fwid])->get('id');
        }

        $course1 = $dg->create_course(['category' => $category->id]);
        $course2 = $dg->create_course(['category' => $category->id]);
        foreach ([$course1, $course2] as $course) {
            $ccg->create_course_competency(['courseid' => $course->id, 'competencyid' => $ids['a']]);
        }
        $page = $dg->create_module('page', ['course' => $course1->id]);
        $ccg->create_course_module_competency(['cmid' => $page->cmid, 'competencyid' => $ids['a']]);

        $template = $ccg->create_template();
        $ccg->create_template_competency(['templateid' => $template->get('id'), 'competencyid' => $ids['a']]);
        $ccg->create_template_competency(['templateid' => $template->get('id'), 'competencyid' => $ids['b']]);

        $learner = $dg->create_user();
        $own = $ccg->create_plan(['userid' => $learner->id]);
        $ccg->create_plan_competency(['planid' => $own->get('id'), 'competencyid' => $ids['a']]);
        $ccg->create_plan(['userid' => $learner->id, 'templateid' => $template->get('id')]);
        // A completed plan whose template dropped the competency after completion (core allows
        // removing a template's competency then) holds it only through the rating archived at completion.
        $archived = $ccg->create_plan([
            'userid' => $learner->id,
            'templateid' => $ccg->create_template()->get('id'),
            'status' => \core_competency\plan::STATUS_COMPLETE,
        ]);
        $ccg->create_user_competency_plan([
            'userid' => $learner->id,
            'competencyid' => $ids['d'],
            'planid' => $archived->get('id'),
        ]);

        return ['context' => $context, 'frameworkid' => $fwid] + $ids;
    }

    /**
     * The administrator gets exact counts over every kind of use, and C is the one unused.
     *
     * @return void
     */
    public function test_usage_is_exact_for_the_administrator(): void {
        $f = $this->usage_fixture();
        $data = $this->data($f['frameworkid']);

        $this->assertTrue($data['exact']);
        $this->assertSame('exact', $data['usagelevel']);
        $usage = self::by_competency($data['usage']);
        $this->assertSame(
            ['competencyid' => $f['a'], 'courses' => 2, 'modules' => 1, 'templates' => 1, 'plans' => 2],
            $usage[$f['a']]
        );
        $this->assertSame(
            ['competencyid' => $f['b'], 'courses' => 0, 'modules' => 0, 'templates' => 1, 'plans' => 1],
            $usage[$f['b']]
        );
        $this->assertSame(
            ['competencyid' => $f['d'], 'courses' => 0, 'modules' => 0, 'templates' => 0, 'plans' => 1],
            $usage[$f['d']]
        );
        $this->assertArrayNotHasKey($f['c'], $usage, 'A competency with nothing to say is left out');
        $this->assertSame([$f['c']], self::flagged($data, 'unused'));
    }

    /**
     * An activity being deleted is not counted, as core's own lists leave it out; the live one is
     * the control.
     *
     * @return void
     */
    public function test_usage_leaves_out_an_activity_being_deleted(): void {
        global $DB;
        $f = $this->usage_fixture();
        $this->assertSame(1, self::by_competency($this->data($f['frameworkid'])['usage'])[$f['a']]['modules'], 'Control');

        $DB->set_field('course_modules', 'deletioninprogress', 1, []);
        $this->assertSame(0, self::by_competency($this->data($f['frameworkid'])['usage'])[$f['a']]['modules']);
    }

    /**
     * A manager of the framework's category learns which kinds of use apply (0 or 1), never how much,
     * and no competency is called unused; the administrator is the control.
     *
     * @return void
     */
    public function test_usage_is_presence_only_for_a_category_manager(): void {
        $f = $this->usage_fixture();
        $dg = $this->getDataGenerator();
        $manager = $dg->create_user();
        $roleid = $dg->create_role();
        assign_capability('moodle/competency:competencymanage', CAP_ALLOW, $roleid, $f['context']->id);
        role_assign($roleid, (int) $manager->id, $f['context']->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertSame([$f['c']], self::flagged($this->data($f['frameworkid']), 'unused'), 'Control: the administrator');

        $this->setUser($manager);
        $data = $this->data($f['frameworkid']);
        $this->assertFalse($data['exact']);
        $this->assertSame('presence', $data['usagelevel']);
        $usage = self::by_competency($data['usage']);
        $this->assertSame(
            ['competencyid' => $f['a'], 'courses' => 1, 'modules' => 1, 'templates' => 1, 'plans' => 1],
            $usage[$f['a']]
        );
        $this->assertSame(
            ['competencyid' => $f['b'], 'courses' => 0, 'modules' => 0, 'templates' => 1, 'plans' => 1],
            $usage[$f['b']]
        );
        $this->assertSame(
            ['competencyid' => $f['d'], 'courses' => 0, 'modules' => 0, 'templates' => 0, 'plans' => 1],
            $usage[$f['d']]
        );
        $this->assertArrayNotHasKey($f['c'], $usage);
        $this->assertSame([], self::flagged($data, 'unused'));
    }

    /**
     * A reader who manages nothing is told nothing about usage, and nothing is called unused;
     * the structure's other checks still reach them. The administrator is the control.
     *
     * @return void
     */
    public function test_usage_is_withheld_from_a_plain_reader(): void {
        global $DB;
        $f = $this->usage_fixture();
        $DB->set_field('competency', 'description', '', ['id' => $f['c']]);
        $admin = $this->data($f['frameworkid']);
        $this->assertNotEmpty($admin['usage'], 'Control: the administrator');

        $this->setUser($this->getDataGenerator()->create_user());
        $data = $this->data($f['frameworkid']);
        $this->assertFalse($data['exact']);
        $this->assertSame('none', $data['usagelevel']);
        $this->assertSame([], $data['usage']);
        $this->assertSame([], self::flagged($data, 'unused'));
        $this->assertSame([$f['c']], self::flagged($data, 'description_empty'));
    }

    /**
     * A site-level holder of every capability core's lists ask is exact without being an
     * administrator; without one of them (here the drafts of plans) the counts fall to presence.
     *
     * @return void
     */
    public function test_exact_needs_every_list_capability_at_the_site(): void {
        $f = $this->usage_fixture();
        $dg = $this->getDataGenerator();
        $holder = $dg->create_user();
        $roleid = $dg->create_role();
        $system = context_system::instance();
        foreach (get_framework_insights::EXACT_CAPABILITIES as $anyof) {
            assign_capability($anyof[0], CAP_ALLOW, $roleid, $system->id);
        }
        assign_capability('moodle/competency:competencymanage', CAP_ALLOW, $roleid, $system->id);
        role_assign($roleid, (int) $holder->id, $system->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($holder);
        $data = $this->data($f['frameworkid']);
        $this->assertTrue($data['exact']);
        $this->assertSame(2, self::by_competency($data['usage'])[$f['a']]['courses']);

        unassign_capability('moodle/competency:planviewdraft', $roleid, $system->id);
        accesslib_clear_all_caches_for_unit_testing();
        $data = $this->data($f['frameworkid']);
        $this->assertFalse($data['exact']);
        $this->assertSame('presence', $data['usagelevel']);
        $this->assertSame(1, self::by_competency($data['usage'])[$f['a']]['courses']);
    }

    /**
     * A site-level role holding the first capability of every group (plus competencymanage, so the
     * lower level is presence), assigned to a new user at the site.
     *
     * @return array [the user, the role id].
     */
    private function exact_holder(): array {
        $dg = $this->getDataGenerator();
        $holder = $dg->create_user();
        $roleid = $dg->create_role();
        $system = context_system::instance();
        foreach (get_framework_insights::EXACT_CAPABILITIES as $anyof) {
            assign_capability($anyof[0], CAP_ALLOW, $roleid, $system->id);
        }
        assign_capability('moodle/competency:competencymanage', CAP_ALLOW, $roleid, $system->id);
        role_assign($roleid, (int) $holder->id, $system->id);
        accesslib_clear_all_caches_for_unit_testing();
        return [$holder, $roleid];
    }

    /**
     * Control of the refusal tests below: a non-administrator holding every group at the site, with
     * no negative row anywhere, is exact (course count 2, not presence's 1).
     *
     * @return void
     */
    public function test_a_holder_without_a_refusal_is_exact(): void {
        $f = $this->usage_fixture();
        [$holder] = $this->exact_holder();
        $this->setUser($holder);
        $data = $this->data($f['frameworkid']);
        $this->assertTrue($data['exact']);
        $this->assertSame(2, self::by_competency($data['usage'])[$f['a']]['courses']);
    }

    /**
     * Assert the answer of a viewer demoted from exact to presence: the course count is 1, not 2.
     *
     * @param array $f The usage fixture.
     * @return void
     */
    private function assert_demoted(array $f): void {
        $data = $this->data($f['frameworkid']);
        $this->assertFalse($data['exact']);
        $this->assertSame('presence', $data['usagelevel']);
        $this->assertSame(1, self::by_competency($data['usage'])[$f['a']]['courses']);
    }

    /**
     * One course refuses the course-competency capabilities to the viewer's role: no longer exact.
     * Control: test_a_holder_without_a_refusal_is_exact (same viewer, same setup, no override).
     *
     * @return void
     */
    public function test_a_prohibit_in_one_course_demotes_exact(): void {
        global $DB;
        $f = $this->usage_fixture();
        [$holder, $roleid] = $this->exact_holder();
        $courseid = (int) $DB->get_field_sql('SELECT MIN(courseid) FROM {competency_coursecomp}');
        $coursecontext = \context_course::instance($courseid);
        assign_capability('moodle/competency:coursecompetencyview', CAP_PROHIBIT, $roleid, $coursecontext->id, true);
        assign_capability('moodle/competency:coursecompetencymanage', CAP_PROHIBIT, $roleid, $coursecontext->id, true);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($holder);
        $this->assert_demoted($f);
    }

    /**
     * A prevent override on the framework's category for a template capability (a group other than
     * the first): no longer exact, so a PREVENT counts as well as a PROHIBIT.
     * Control: test_a_holder_without_a_refusal_is_exact.
     *
     * @return void
     */
    public function test_a_prevent_in_a_category_demotes_exact(): void {
        $f = $this->usage_fixture();
        [$holder, $roleid] = $this->exact_holder();
        assign_capability('moodle/competency:templatemanage', CAP_PREVENT, $roleid, $f['context']->id, true);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($holder);
        $this->assert_demoted($f);
    }

    /**
     * A second role whose definition prohibits one capability (a row at the system context),
     * assigned to the viewer only in a course, demotes: a filter on rows outside the system context
     * would miss it, though the prohibit applies in that course. A capability of the seventh group.
     * Control: test_a_holder_without_a_refusal_is_exact.
     *
     * @return void
     */
    public function test_a_prohibiting_role_assigned_in_a_course_demotes_exact(): void {
        $f = $this->usage_fixture();
        [$holder] = $this->exact_holder();
        $dg = $this->getDataGenerator();
        $second = $dg->create_role();
        assign_capability('moodle/competency:planviewdraft', CAP_PROHIBIT, $second, context_system::instance()->id);
        $course = $dg->create_course();
        role_assign($second, (int) $holder->id, \context_course::instance((int) $course->id)->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($holder);
        $this->assertTrue(
            has_capability('moodle/competency:planviewdraft', context_system::instance()),
            'Precondition: the site-level check still passes'
        );
        $this->assert_demoted($f);
    }

    /**
     * The administrator keeps exact although a prohibit exists. Control: the same row demotes the
     * holder, asserted first.
     *
     * @return void
     */
    public function test_the_administrator_keeps_exact_despite_a_refusal(): void {
        $f = $this->usage_fixture();
        [$holder, $roleid] = $this->exact_holder();
        assign_capability('moodle/competency:templatemanage', CAP_PROHIBIT, $roleid, $f['context']->id, true);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($holder);
        $this->assert_demoted($f);

        $this->setAdminUser();
        $data = $this->data($f['frameworkid']);
        $this->assertTrue($data['exact']);
        $this->assertSame(2, self::by_competency($data['usage'])[$f['a']]['courses']);
    }

    /**
     * A negative row for capabilities the lists do not ask does not demote. Control: a negative row
     * for one they do ask demotes the same viewer afterwards.
     *
     * @return void
     */
    public function test_a_refusal_of_an_unrelated_capability_does_not_demote(): void {
        $f = $this->usage_fixture();
        [$holder, $roleid] = $this->exact_holder();
        assign_capability('moodle/course:view', CAP_PROHIBIT, $roleid, context_system::instance()->id, true);
        assign_capability('moodle/course:update', CAP_PREVENT, $roleid, $f['context']->id, true);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($holder);
        $data = $this->data($f['frameworkid']);
        $this->assertTrue($data['exact']);

        assign_capability('moodle/competency:planmanagedraft', CAP_PREVENT, $roleid, $f['context']->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assert_demoted($f);
    }

    /**
     * The capability groups core's usage lists ask at the site, spelled out here on purpose: a test
     * that read the service's own list would follow it when a group is dropped.
     *
     * @return array Group number => list of the capabilities of which one is enough.
     */
    private static function exact_groups(): array {
        return [
            1 => ['moodle/competency:coursecompetencyview', 'moodle/competency:coursecompetencymanage'],
            2 => ['moodle/course:viewhiddencourses'],
            3 => ['moodle/course:viewhiddenactivities'],
            4 => ['moodle/course:ignoreavailabilityrestrictions'],
            5 => ['moodle/competency:templatemanage'],
            6 => ['moodle/competency:planview', 'moodle/competency:planmanage'],
            7 => ['moodle/competency:planviewdraft', 'moodle/competency:planmanagedraft'],
        ];
    }

    /**
     * Every group of the exact level, one case each.
     *
     * @return array Case name => [group number].
     */
    public static function every_exact_group(): array {
        $cases = [];
        foreach (array_keys(self::exact_groups()) as $number) {
            $cases["group $number"] = [$number];
        }
        return $cases;
    }

    /**
     * Give a site-level role every group's first capability but those of one group, and take that
     * group's capabilities from every user: the holder of the rest is a plain user for the omitted one.
     *
     * @param int $omitted The group number left out.
     * @return \stdClass The holder.
     */
    private function holder_without_group(int $omitted): \stdClass {
        global $CFG;
        $dg = $this->getDataGenerator();
        $holder = $dg->create_user();
        $roleid = $dg->create_role();
        $system = context_system::instance();
        foreach (self::exact_groups() as $number => $anyof) {
            if ($number === $omitted) {
                foreach ($anyof as $capability) {
                    // Unassign, not prohibit: a negative row demotes on its own, which would hide what this
                    // case pins (the missing group). The authenticated role allows coursecompetencyview.
                    unassign_capability($capability, (int) $CFG->defaultuserroleid, $system->id);
                }
                continue;
            }
            assign_capability($anyof[0], CAP_ALLOW, $roleid, $system->id);
        }
        role_assign($roleid, (int) $holder->id, $system->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $holder;
    }

    /**
     * A holder of every group but one is not exact, whichever the one is: each capability the lists
     * ask is needed at the site.
     *
     * @param int $omitted The group number the holder lacks.
     * @return void
     */
    #[DataProvider('every_exact_group')]
    public function test_exact_needs_every_group_one_by_one(int $omitted): void {
        $f = $this->usage_fixture();
        $holder = $this->holder_without_group($omitted);

        $this->setUser($holder);
        $this->assertFalse(
            has_any_capability(self::exact_groups()[$omitted], context_system::instance()),
            'Precondition: the group is missing'
        );
        $data = $this->data($f['frameworkid']);
        $this->assertFalse($data['exact']);
        $this->assertNotSame('exact', $data['usagelevel']);
    }

    /**
     * The groups that name two capabilities take either: a holder of the second one alone, the first
     * taken from everybody, is exact.
     *
     * @return void
     */
    public function test_exact_takes_the_second_capability_of_a_group(): void {
        global $CFG;
        $f = $this->usage_fixture();
        $dg = $this->getDataGenerator();
        $holder = $dg->create_user();
        $roleid = $dg->create_role();
        $system = context_system::instance();
        foreach (self::exact_groups() as $anyof) {
            if (count($anyof) === 2) {
                // Unassign, as in holder_without_group(): a prohibit would demote the viewer by itself.
                unassign_capability($anyof[0], (int) $CFG->defaultuserroleid, $system->id);
            }
            assign_capability(end($anyof), CAP_ALLOW, $roleid, $system->id);
        }
        role_assign($roleid, (int) $holder->id, $system->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($holder);
        foreach (self::exact_groups() as $anyof) {
            if (count($anyof) === 2) {
                $this->assertFalse(has_capability($anyof[0], $system), 'Precondition: ' . $anyof[0]);
            }
        }
        $data = $this->data($f['frameworkid']);
        $this->assertTrue($data['exact']);
        $this->assertSame(2, self::by_competency($data['usage'])[$f['a']]['courses']);
    }

    /**
     * The use of a competency of another framework is not counted, in any of the seven ways it is
     * read: courses, activities, templates, plans of its own, archived ratings and plans made from a
     * template. The use of this framework's competencies is the control.
     *
     * @return void
     */
    public function test_usage_leaves_out_another_framework(): void {
        $f = $this->usage_fixture();
        $dg = $this->getDataGenerator();
        $ccg = $dg->get_plugin_generator('core_competency');
        $otherfw = (int) $ccg->create_framework()->get('id');
        $x = (int) $ccg->create_competency(['competencyframeworkid' => $otherfw])->get('id');
        $course = $dg->create_course();
        $ccg->create_course_competency(['courseid' => $course->id, 'competencyid' => $x]);
        $page = $dg->create_module('page', ['course' => $course->id]);
        $ccg->create_course_module_competency(['cmid' => $page->cmid, 'competencyid' => $x]);
        $template = $ccg->create_template();
        $ccg->create_template_competency(['templateid' => $template->get('id'), 'competencyid' => $x]);
        $learner = $dg->create_user();
        $own = $ccg->create_plan(['userid' => $learner->id]);
        $ccg->create_plan_competency(['planid' => $own->get('id'), 'competencyid' => $x]);
        // A completed plan whose template dropped the competency after completion (core allows
        // removing a template's competency then) holds it only through the rating archived at completion.
        $archived = $ccg->create_plan([
            'userid' => $learner->id,
            'templateid' => $ccg->create_template()->get('id'),
            'status' => \core_competency\plan::STATUS_COMPLETE,
        ]);
        $ccg->create_user_competency_plan([
            'userid' => $learner->id,
            'competencyid' => $x,
            'planid' => $archived->get('id'),
        ]);
        $ccg->create_plan(['userid' => $learner->id, 'templateid' => $template->get('id')]);

        $data = $this->data($f['frameworkid']);
        $listed = array_map('intval', array_column($data['usage'], 'competencyid'));
        sort($listed);
        $expected = [$f['a'], $f['b'], $f['d']];
        sort($expected);
        $this->assertSame($expected, $listed);
        $this->assertSame(2, self::by_competency($data['usage'])[$f['a']]['courses'], 'Control');
        $this->assertSame([$f['c']], self::flagged($data, 'unused'));
    }

    // Rules.

    /**
     * Give a competency a rule through core's persistent, which validates it as core's forms do.
     *
     * @param competency $competency The competency.
     * @param string $class The rule class.
     * @param int $outcome The rule outcome.
     * @param array|null $config The rule configuration, or null.
     * @return void
     */
    private function set_rule(competency $competency, string $class, int $outcome, ?array $config): void {
        $competency->read();
        $competency->set('ruletype', $class);
        $competency->set('ruleoutcome', $outcome);
        $competency->set('ruleconfig', $config === null ? null : json_encode($config));
        $competency->update();
    }

    /**
     * A points configuration.
     *
     * @param int $points Points required.
     * @param array $children List of [id, points, required].
     * @return array
     */
    private static function points(int $points, array $children): array {
        return [
            'base' => ['points' => $points],
            'competencies' => array_map(
                static fn(array $c): array => ['id' => $c[0], 'points' => $c[1], 'required' => $c[2] ? 1 : 0],
                $children
            ),
        ];
    }

    /**
     * One framework holding every rule shape.
     *
     * pp: points, can fire. ps: points asking more than its children hold. pg: points whose required
     * child is gone. pa: "all" over two children. p1: "all" over one child. leaf: "all" on a leaf,
     * which core saves (competency_rule_all::validate_config() accepts a null configuration).
     *
     * ps and pg are states no core path writes: competency_rule_points::validate_config() refuses
     * a threshold above the children's points on every save (the API, both CSV importers, framework
     * duplication), and api::delete_competency() and api::set_parent_competency() reset the parent's
     * rule. Only a direct write, a repaired database or an older site's data leaves them, so they are
     * written with set_field() and delete_records() past the persistent, which is the case the
     * rule_cannot_fire check exists for.
     *
     * @return array Keys: frameworkid and the competency ids.
     */
    private function rules_fixture(): array {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $fwid = (int) $ccg->create_framework()->get('id');
        $new = static fn(int $parentid = 0): competency => $ccg->create_competency([
            'competencyframeworkid' => $fwid,
            'parentid' => $parentid,
        ]);
        $ids = [];

        $pp = $new();
        $ids['pp1'] = (int) $new($pp->get('id'))->get('id');
        $ids['pp2'] = (int) $new($pp->get('id'))->get('id');
        $this->set_rule($pp, competency_rule_points::class, competency::OUTCOME_EVIDENCE, self::points(3, [
            [$ids['pp1'], 2, true],
            [$ids['pp2'], 1, false],
        ]));

        $ps = $new();
        $ids['ps1'] = (int) $new($ps->get('id'))->get('id');
        $ids['ps2'] = (int) $new($ps->get('id'))->get('id');
        $this->set_rule($ps, competency_rule_points::class, competency::OUTCOME_EVIDENCE, self::points(2, [
            [$ids['ps1'], 1, true],
            [$ids['ps2'], 1, false],
        ]));
        $DB->set_field('competency', 'ruleconfig', json_encode(self::points(5, [
            [$ids['ps1'], 1, true],
            [$ids['ps2'], 1, false],
        ])), ['id' => $ps->get('id')]);

        $pg = $new();
        $ids['pg1'] = (int) $new($pg->get('id'))->get('id');
        $ids['pg2'] = (int) $new($pg->get('id'))->get('id');
        // A third child outside the configuration keeps two children after the deletion below.
        $new($pg->get('id'));
        $this->set_rule($pg, competency_rule_points::class, competency::OUTCOME_COMPLETE, self::points(2, [
            [$ids['pg1'], 1, true],
            [$ids['pg2'], 2, false],
        ]));
        $DB->delete_records('competency', ['id' => $ids['pg1']]);

        $pa = $new();
        $new($pa->get('id'));
        $new($pa->get('id'));
        $this->set_rule($pa, competency_rule_all::class, competency::OUTCOME_COMPLETE, null);

        $p1 = $new();
        $new($p1->get('id'));
        $this->set_rule($p1, competency_rule_all::class, competency::OUTCOME_RECOMMEND, null);

        $leaf = $new();
        $this->set_rule($leaf, competency_rule_all::class, competency::OUTCOME_NONE, null);

        return $ids + [
            'frameworkid' => $fwid,
            'pp' => (int) $pp->get('id'),
            'ps' => (int) $ps->get('id'),
            'pg' => (int) $pg->get('id'),
            'pa' => (int) $pa->get('id'),
            'p1' => (int) $p1->get('id'),
            'leaf' => (int) $leaf->get('id'),
        ];
    }

    /**
     * A points rule whose children can reach its threshold can fire, and says how.
     *
     * @return void
     */
    public function test_a_points_rule_that_can_fire(): void {
        $f = $this->rules_fixture();
        $data = $this->data($f['frameworkid']);
        $this->assertSame([
            'competencyid' => $f['pp'],
            'type' => 'points',
            'label' => (string) competency_rule_points::get_name(),
            'outcome' => 'evidence',
            'required' => 3,
            'available' => 3,
            'requiredchildren' => 1,
            'children' => [
                ['id' => $f['pp1'], 'points' => 2, 'required' => true],
                ['id' => $f['pp2'], 'points' => 1, 'required' => false],
            ],
            'canfire' => true,
        ], self::by_competency($data['rules'])[$f['pp']]);
        $this->assertNotContains($f['pp'], self::flagged($data, 'rule_cannot_fire'));
        // A framework of competencies with no relation: no pairs, no counts.
        $this->assertSame([], $data['pairs']);
        $this->assertSame([], $data['relations']);
        $this->assertSame(0, $data['summary']['relations']);
    }

    /**
     * A points rule asking more than its children hold cannot fire, nor one whose required child is
     * gone; the finding carries the numbers. The rule that can fire is the control.
     *
     * @return void
     */
    public function test_points_rules_that_cannot_fire(): void {
        $f = $this->rules_fixture();
        $data = $this->data($f['frameworkid']);
        $rules = self::by_competency($data['rules']);

        $this->assertFalse($rules[$f['ps']]['canfire']);
        $this->assertSame(5, $rules[$f['ps']]['required']);
        $this->assertSame(2, $rules[$f['ps']]['available']);
        $this->assertSame(
            ['required' => '5', 'available' => '2', 'missing' => '0'],
            $this->check_params($data, 'rule_cannot_fire', $f['ps'])
        );

        $this->assertFalse($rules[$f['pg']]['canfire']);
        $this->assertSame(2, $rules[$f['pg']]['required']);
        $this->assertSame(2, $rules[$f['pg']]['available'], 'The deleted child adds nothing');
        $this->assertSame(1, $rules[$f['pg']]['requiredchildren']);
        $this->assertSame('complete', $rules[$f['pg']]['outcome']);
        $this->assertSame(
            ['required' => '2', 'available' => '2', 'missing' => '1'],
            $this->check_params($data, 'rule_cannot_fire', $f['pg'])
        );

        $this->assertTrue($rules[$f['pp']]['canfire'], 'Control');
        $expected = [$f['pg'], $f['ps']];
        sort($expected);
        $this->assertSame($expected, self::flagged($data, 'rule_cannot_fire'));
    }

    /**
     * Configurations a points rule can hold that core would not have saved.
     *
     * @return array Case name => [ruleconfig]. Each one, read loosely, would ask for 0 points.
     */
    public static function unreadable_point_configs(): array {
        return [
            'empty' => [''],
            'not json' => ['{not json'],
            'a list' => ['[0]'],
            'no base' => ['{"competencies":[]}'],
            'base without points' => ['{"base":{},"competencies":[]}'],
            'no competencies' => ['{"base":{"points":0}}'],
        ];
    }

    /**
     * A points rule whose configuration cannot be read cannot fire, however little it would ask, and
     * is flagged with zeros; a configuration that reads is the control.
     *
     * @param string $config The stored configuration.
     * @return void
     */
    #[DataProvider('unreadable_point_configs')]
    public function test_a_points_rule_with_an_unreadable_configuration(string $config): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $fwid = (int) $ccg->create_framework()->get('id');
        $parent = $ccg->create_competency(['competencyframeworkid' => $fwid]);
        $first = $ccg->create_competency(['competencyframeworkid' => $fwid, 'parentid' => $parent->get('id')]);
        $ccg->create_competency(['competencyframeworkid' => $fwid, 'parentid' => $parent->get('id')]);
        $DB->update_record('competency', (object) [
            'id' => $parent->get('id'),
            'ruletype' => competency_rule_points::class,
            'ruleoutcome' => competency::OUTCOME_EVIDENCE,
            'ruleconfig' => $config,
        ]);
        $parentid = (int) $parent->get('id');

        $data = $this->data($fwid);
        $rule = self::by_competency($data['rules'])[$parentid];
        $this->assertSame('points', $rule['type']);
        $this->assertFalse($rule['canfire']);
        $this->assertSame(0, $rule['required']);
        $this->assertSame(0, $rule['available']);
        $this->assertSame([], $rule['children']);
        $this->assertSame(
            ['required' => '0', 'available' => '0', 'missing' => '0'],
            $this->check_params($data, 'rule_cannot_fire', $parentid)
        );

        $DB->set_field('competency', 'ruleconfig', json_encode(self::points(0, [[(int) $first->get('id'), 0, false]])), [
            'id' => $parentid,
        ]);
        $control = self::by_competency($this->data($fwid)['rules'])[$parentid];
        $this->assertTrue($control['canfire'], 'Control: a configuration that reads');
    }

    /**
     * An "all" rule reports its type, label and outcome and no points.
     *
     * @return void
     */
    public function test_an_all_rule(): void {
        $f = $this->rules_fixture();
        $this->assertSame([
            'competencyid' => $f['pa'],
            'type' => 'all',
            'label' => (string) competency_rule_all::get_name(),
            'outcome' => 'complete',
            'required' => 0,
            'available' => 0,
            'requiredchildren' => 0,
            'children' => [],
            'canfire' => true,
        ], self::by_competency($this->data($f['frameworkid'])['rules'])[$f['pa']]);
    }

    /**
     * A rule over a single child is flagged; the same rule over two children is the control.
     *
     * @return void
     */
    public function test_a_rule_on_a_single_child(): void {
        $f = $this->rules_fixture();
        $data = $this->data($f['frameworkid']);
        $this->assertSame([$f['p1']], self::flagged($data, 'rule_single_child'));
        $this->assertSame('recommend', self::by_competency($data['rules'])[$f['p1']]['outcome']);
    }

    /**
     * Core saves an "all" rule on a leaf (its validate_config() accepts a null configuration), and
     * that rule can never fire; the rules on parents are the control.
     *
     * @return void
     */
    public function test_a_rule_without_children(): void {
        $f = $this->rules_fixture();
        $data = $this->data($f['frameworkid']);
        $this->assertSame([$f['leaf']], self::flagged($data, 'rule_without_children'));
        $leaf = self::by_competency($data['rules'])[$f['leaf']];
        $this->assertFalse($leaf['canfire']);
        $this->assertSame('none', $leaf['outcome']);
        $this->assertNotContains($f['leaf'], self::flagged($data, 'rule_cannot_fire'), 'One finding per cause');
        $this->assertSame(6, $data['summary']['withrule']);
    }

    // Relations, descriptions, ID numbers, colours.

    /**
     * A framework of six plain competencies, as the admin.
     *
     * @return array Keys: frameworkid, ids (list of six competency ids).
     */
    private function plain_fixture(): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $fwid = (int) $ccg->create_framework()->get('id');
        $ids = [];
        for ($i = 0; $i < 6; $i++) {
            $ids[] = (int) $ccg->create_competency(['competencyframeworkid' => $fwid])->get('id');
        }
        return ['frameworkid' => $fwid, 'ids' => $ids];
    }

    /**
     * Each relation counts once for each of its two ends, and the summary counts pairs; a competency
     * related to nothing in the framework is left out.
     *
     * Core's related_competency persistent stores the lower id first and refuses a pair from two
     * frameworks (validate_relatedcompetencyid()), so the in-framework pairs go through the
     * generator with the lower id first. A row reaching another framework is a state no core
     * path writes (api::add_related_competency(), tool_lpimportcsv and framework duplication all
     * go through that persistent; no backup carries relations): only a direct insert, a database
     * repair or an older site's data leaves one. The service still reads only pairs with both ends
     * in the framework, so such a row cannot count a competency the viewer may not read; the two
     * rows below, one per direction, are written with insert_record() to prove it.
     *
     * @return void
     */
    public function test_relations_count_both_directions_once(): void {
        global $DB;
        $f = $this->plain_fixture();
        [$x, $y, $z, $w] = $f['ids'];
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        // Stored x-z before x-y, so the answer's order is the service's sort, not the rows' order.
        $ccg->create_related_competency(['competencyid' => $x, 'relatedcompetencyid' => $z]);
        $ccg->create_related_competency(['competencyid' => $x, 'relatedcompetencyid' => $y]);
        $outside = (int) $ccg->create_competency([
            'competencyframeworkid' => $ccg->create_framework()->get('id'),
        ])->get('id');
        try {
            $ccg->create_related_competency(['competencyid' => $w, 'relatedcompetencyid' => $outside]);
            $this->fail('Precondition: core refuses a relation across frameworks.');
        } catch (\core\invalid_persistent_exception $e) {
            $this->assertStringContainsString('relatedcompetencyid', $e->getMessage());
        }
        $now = time();
        foreach ([[$w, $outside], [$outside, $w]] as [$from, $to]) {
            $DB->insert_record('competency_relatedcomp', (object) [
                'competencyid' => $from,
                'relatedcompetencyid' => $to,
                'timecreated' => $now,
                'timemodified' => $now,
                'usermodified' => 0,
            ]);
        }

        $data = $this->data($f['frameworkid']);
        $relations = array_column($data['relations'], 'count', 'competencyid');
        ksort($relations);
        $expected = [$x => 2, $y => 1, $z => 1];
        ksort($expected);
        $this->assertSame($expected, $relations);
        $this->assertArrayNotHasKey($w, $relations);

        // Each pair once, lower id first, sorted; the rows reaching another framework are not pairs.
        $this->assertSame([['a' => $x, 'b' => $y], ['a' => $x, 'b' => $z]], $data['pairs']);
        foreach ($data['pairs'] as $pair) {
            $this->assertNotContains($w, $pair);
            $this->assertNotContains($outside, $pair);
        }
        $this->assertSame(count($data['pairs']), $data['summary']['relations']);
        $this->assertSame(2, $data['summary']['relations']);
    }

    /**
     * A description empty once its tags, entities and white space (zero-width included) go is flagged; text and an image
     * alone are the controls.
     *
     * @return void
     */
    public function test_an_empty_description(): void {
        global $DB;
        $f = $this->plain_fixture();
        [$blank, $spaces, $text, $image, $zerowidth] = $f['ids'];
        $DB->set_field('competency', 'description', '', ['id' => $blank]);
        $DB->set_field('competency', 'description', "<p> &nbsp; </p>\n<br>", ['id' => $spaces]);
        $DB->set_field('competency', 'description', '<p>Text</p>', ['id' => $text]);
        $DB->set_field('competency', 'description', '<p><img src="https://example.com/a.png" alt=""></p>', ['id' => $image]);

        $DB->set_field('competency', 'description', '<p>&#8203;</p>', ['id' => $zerowidth]);

        $expected = [$blank, $spaces, $zerowidth];
        sort($expected);
        $this->assertSame($expected, self::flagged($this->data($f['frameworkid']), 'description_empty'));
    }

    /**
     * Core keeps ID numbers unique in a framework but its persistent stores an empty one (only
     * tool_lp's form requires it), so an empty ID number, or one of white space only, is flagged; the
     * generated ones are the control.
     *
     * @return void
     */
    public function test_an_empty_idnumber(): void {
        global $DB;
        $f = $this->plain_fixture();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $empty = (int) $ccg->create_competency(['competencyframeworkid' => $f['frameworkid'], 'idnumber' => ''])->get('id');
        $this->assertSame([$empty], self::flagged($this->data($f['frameworkid']), 'idnumber_empty'));
        // White space only goes in a framework of its own: ID numbers are unique per framework, and
        // MariaDB's padded comparison takes '' and '  ' for the same value.
        $g = $this->plain_fixture();
        $blank = $g['ids'][0];
        $DB->set_field('competency', 'idnumber', '  ', ['id' => $blank]);
        $this->assertSame([$blank], self::flagged($this->data($g['frameworkid']), 'idnumber_empty'));
    }

    /**
     * Custom colours under WCAG's 4.5 are flagged with the ratio cut to one decimal; a passing pair,
     * a single colour and the colours of another framework are the controls.
     *
     * @return void
     */
    public function test_colour_contrast(): void {
        $f = $this->plain_fixture();
        [$grey, $darker, $onlybg, $red] = $f['ids'];
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);
        $other = $this->getDataGenerator()->get_plugin_generator('core_competency')->create_framework();
        $elsewhere = (int) $this->getDataGenerator()->get_plugin_generator('core_competency')->create_competency([
            'competencyframeworkid' => $other->get('id'),
        ])->get('id');
        $colours = [
            $grey => ['#ffffff', '#777777'],
            $darker => ['#ffffff', '#767676'],
            $onlybg => ['#ffffff', null],
            $red => ['#ffffff', '#ff0000'],
            $elsewhere => ['#ffffff', '#ffff00'],
        ];
        foreach ($colours as $id => [$bg, $text]) {
            $formdata = (object) ['id' => $id, 'customfield_' . constants::CFIELD_CUSTOMBGCOLOR => $bg];
            if ($text !== null) {
                $formdata->{'customfield_' . constants::CFIELD_CUSTOMTEXTCOLOR} = $text;
            }
            competency_handler::create()->instance_form_save($formdata, true);
        }

        $data = $this->data($f['frameworkid']);
        $expected = [$grey, $red];
        sort($expected);
        $this->assertSame($expected, self::flagged($data, 'colour_contrast'));
        $this->assertSame(
            ['ratio' => '4.4', 'bgcolor' => '#ffffff', 'textcolor' => '#777777'],
            $this->check_params($data, 'colour_contrast', $grey)
        );
        $this->assertSame('3.9', $this->check_params($data, 'colour_contrast', $red)['ratio']);
    }

    // Summary and cost.

    /**
     * The summary of a known tree: four competencies over three levels, one rule, one relation, and
     * as many findings as checks.
     *
     * @return void
     */
    public function test_the_summary(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $fwid = (int) $ccg->create_framework()->get('id');
        $root = $ccg->create_competency(['competencyframeworkid' => $fwid]);
        $c1 = $ccg->create_competency(['competencyframeworkid' => $fwid, 'parentid' => $root->get('id')]);
        $c2 = $ccg->create_competency(['competencyframeworkid' => $fwid, 'parentid' => $root->get('id')]);
        $grandchild = $ccg->create_competency(['competencyframeworkid' => $fwid, 'parentid' => $c2->get('id')]);
        $this->set_rule($root, competency_rule_all::class, competency::OUTCOME_COMPLETE, null);
        $ccg->create_related_competency(['competencyid' => $c1->get('id'), 'relatedcompetencyid' => $grandchild->get('id')]);

        $data = $this->data($fwid);
        $this->assertSame([
            'competencies' => 4,
            'maxdepth' => (int) competency::get_framework_depth($fwid),
            'withrule' => 1,
            'relations' => 1,
            'findings' => 4,
        ], $data['summary']);
        $this->assertSame(3, $data['summary']['maxdepth']);
        $this->assertSame([['a' => (int) $c1->get('id'), 'b' => (int) $grandchild->get('id')]], $data['pairs']);
        $this->assertSame(count($data['pairs']), $data['summary']['relations']);
        $this->assertCount(4, $data['checks']);
        $this->assertSame(4, count(self::flagged($data, 'unused')));
        $this->assertSame($fwid, $data['frameworkid']);
    }

    /**
     * An empty framework answers with empty lists and zeros.
     *
     * @return void
     */
    public function test_an_empty_framework(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $fwid = (int) $this->getDataGenerator()->get_plugin_generator('core_competency')->create_framework()->get('id');
        $data = $this->data($fwid);
        $this->assertSame([], $data['usage']);
        $this->assertSame([], $data['rules']);
        $this->assertSame([], $data['relations']);
        $this->assertSame([], $data['pairs']);
        $this->assertSame([], $data['checks']);
        $this->assertSame(
            ['competencies' => 0, 'maxdepth' => 0, 'withrule' => 0, 'relations' => 0, 'findings' => 0],
            $data['summary']
        );
    }

    /**
     * A framework of a given size with every kind of data the service reads: a points rule over all
     * the children, a relation, a course, an activity, a template, a plan and custom colours.
     *
     * @param int $size Number of competencies.
     * @return int The framework id.
     */
    private function sized_framework(int $size): int {
        $dg = $this->getDataGenerator();
        $ccg = $dg->get_plugin_generator('core_competency');
        $fwid = (int) $ccg->create_framework()->get('id');
        $parent = $ccg->create_competency(['competencyframeworkid' => $fwid]);
        $children = [];
        for ($i = 1; $i < $size; $i++) {
            $children[] = (int) $ccg->create_competency([
                'competencyframeworkid' => $fwid,
                'parentid' => $parent->get('id'),
            ])->get('id');
        }
        $this->set_rule($parent, competency_rule_points::class, competency::OUTCOME_EVIDENCE, self::points(
            1,
            array_map(static fn(int $id): array => [$id, 1, false], $children)
        ));
        $ccg->create_related_competency(['competencyid' => $children[0], 'relatedcompetencyid' => $children[1]]);
        $course = $dg->create_course();
        $ccg->create_course_competency(['courseid' => $course->id, 'competencyid' => $children[0]]);
        $page = $dg->create_module('page', ['course' => $course->id]);
        $ccg->create_course_module_competency(['cmid' => $page->cmid, 'competencyid' => $children[0]]);
        $template = $ccg->create_template();
        $ccg->create_template_competency(['templateid' => $template->get('id'), 'competencyid' => $children[1]]);
        $ccg->create_plan(['userid' => $dg->create_user()->id, 'templateid' => $template->get('id')]);
        foreach ($children as $id) {
            competency_handler::create()->instance_form_save((object) [
                'id' => $id,
                'customfield_' . constants::CFIELD_CUSTOMBGCOLOR => '#ffffff',
                'customfield_' . constants::CFIELD_CUSTOMTEXTCOLOR => '#777777',
            ], true);
        }
        return $fwid;
    }

    /**
     * The number of reads does not grow with the framework: 5 and 60 competencies cost the same,
     * each measured after a first call has warmed the capability and string caches.
     *
     * @return void
     */
    public function test_the_reads_do_not_grow_with_the_framework(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);
        $small = $this->sized_framework(5);
        $large = $this->sized_framework(60);

        $reads = [];
        foreach (['small' => $small, 'large' => $large] as $label => $fwid) {
            get_framework_insights::execute($fwid);
            $before = $DB->perf_get_reads();
            $result = get_framework_insights::execute($fwid);
            $reads[$label] = $DB->perf_get_reads() - $before;
            $this->assertSame($label === 'small' ? 5 : 60, $result['summary']['competencies']);
            $this->assertCount($label === 'small' ? 4 : 59, self::flagged($result, 'colour_contrast'), $label);
            $this->assertNotEmpty($result['usage'], $label);
        }
        $this->assertSame($reads['small'], $reads['large']);
    }
}
