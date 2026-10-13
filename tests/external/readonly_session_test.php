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
 * Tests that the read services declared read-only leave the session as they found it.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core_competency\api as competencyapi;
use core_competency\competency;
use core_competency\competency_rule_all;
use core_competency\plan;
use core_external\external_api;
use local_dimensions\helper;
use local_dimensions\local\template_csv_serializer;

/**
 * A service declared 'readonlysession' runs without the session lock, so nothing it writes to
 * $SESSION survives the request.
 *
 * Core's contract for a read-only session is the one write_close() checks
 * (lib/classes/session/manager.php, the array_session_diff() of $SESSION taken at session start
 * and at close): no $SESSION key added, removed or changed. That is what is asserted here, per
 * service, around a call made the way the browser makes it, with the plugin's caches cold so
 * every fill runs. $USER is not compared, as core does not compare it. No key is left out of the
 * comparison: the plugin's one session-mode cache, `returncontext`, is written only by the
 * learner pages and read by the Return FAB, never by a web service.
 *
 * The class-level covers tags stay in docblock form on purpose: moodle-cs on the 4.05 leg cannot
 * see PHP attributes, and the plugin still supports 4.5.
 *
 * @covers     \local_dimensions\external\get_course_progress
 * @covers     \local_dimensions\external\get_courses_completion_status
 * @covers     \local_dimensions\external\get_fontawesome_icons
 * @covers     \local_dimensions\external\get_competency_courses
 * @covers     \local_dimensions\external\get_user_competency_summary_in_plan
 * @covers     \local_dimensions\external\get_competency_rule_data
 * @covers     \local_dimensions\external\search_competencies
 * @covers     \local_dimensions\external\competency_usage
 * @covers     \local_dimensions\external\search_structure
 * @covers     \local_dimensions\external\browse_competencies
 * @covers     \local_dimensions\external\browse_structure
 * @covers     \local_dimensions\external\get_structure_node
 * @covers     \local_dimensions\external\list_related_competencies
 * @covers     \local_dimensions\external\list_template_cohorts
 * @covers     \local_dimensions\external\list_template_participants
 * @covers     \local_dimensions\external\search_assignable_users
 * @covers     \local_dimensions\external\get_competency_links
 * @covers     \local_dimensions\external\search_linkable_courses
 * @covers     \local_dimensions\external\get_competency_module_links
 * @covers     \local_dimensions\external\export_framework
 * @covers     \local_dimensions\external\export_templates
 * @covers     \local_dimensions\external\preview_import_templates
 * @covers     \local_dimensions\external\list_template_cohort_roles
 * @covers     \local_dimensions\external\list_enrol_competencies
 * @covers     \local_dimensions\external\list_enrol_courses
 * @covers     \local_dimensions\external\get_enrol_queue_status
 * @covers     \local_dimensions\external\search_categories
 */
final class readonly_session_test extends \advanced_testcase {
    /** @var string[] The plugin's application-mode cache definitions (db/caches.php), purged before every call. */
    private const CACHES = [
        'template_courses',
        'template_metadata',
        'competency_metadata',
        'template_scss',
        'competency_scss',
        'plan_trail',
        'course_customfields',
        'fontawesome_iconmap',
    ];

    /** @var string[] The learner-page services, called as the plan owner; every other one is called as the site admin. */
    private const LEARNER_SERVICES = [
        'local_dimensions_get_course_progress',
        'local_dimensions_get_courses_completion_status',
        'local_dimensions_get_competency_courses',
        'local_dimensions_get_user_competency_summary_in_plan',
        'local_dimensions_get_competency_rule_data',
    ];

    /** @var string[] The keys of the fixture create_fixture() returns. */
    private const FIXTURE_KEYS = [
        'frameworkid', 'parentid', 'childid', 'relatedid', 'templateid', 'planid', 'course1', 'course2',
        'cm1', 'cm2', 'cohortid', 'roleid', 'categoryid', 'learnerid', 'freeuserid', 'draftitemid',
    ];

    /**
     * resetAfterTest() does not restore superglobals: drop the sesskey the calls plant in $_POST.
     *
     * @return void
     */
    protected function tearDown(): void {
        unset($_POST['sesskey']);
        parent::tearDown();
    }

    /**
     * The services that must hold no session lock, each with the arguments its page sends against
     * the fixture below.
     *
     * @param array $f The fixture, as create_fixture() returns it.
     * @return array Service name => arguments.
     */
    private static function read_only_calls(array $f): array {
        $courses = [$f['course1']];
        $plancard = ['courseids' => $courses, 'planid' => $f['planid'], 'competencyid' => $f['childid']];

        return [
            'local_dimensions_get_course_progress' => $plancard,
            'local_dimensions_get_courses_completion_status' => $plancard,
            'local_dimensions_get_fontawesome_icons' => ['query' => 'check'],
            'local_dimensions_get_competency_courses' => ['competencyid' => $f['childid'], 'planid' => $f['planid']],
            'local_dimensions_get_user_competency_summary_in_plan' => [
                'competencyid' => $f['childid'],
                'planid' => $f['planid'],
            ],
            'local_dimensions_get_competency_rule_data' => ['competencyid' => $f['parentid'], 'planid' => $f['planid']],
            'local_dimensions_search_competencies' => ['query' => 'Probe child'],
            'local_dimensions_competency_usage' => ['competencyid' => $f['childid']],
            'local_dimensions_search_structure' => ['frameworkid' => $f['frameworkid'], 'query' => 'Probe child'],
            'local_dimensions_browse_competencies' => ['frameworkid' => $f['frameworkid'], 'query' => 'Probe child'],
            'local_dimensions_browse_structure' => ['frameworkid' => $f['frameworkid'], 'parentid' => $f['parentid']],
            'local_dimensions_get_structure_node' => ['competencyid' => $f['childid']],
            'local_dimensions_list_related_competencies' => ['competencyid' => $f['relatedid']],
            'local_dimensions_list_template_cohorts' => ['templateid' => $f['templateid']],
            'local_dimensions_list_template_participants' => ['templateid' => $f['templateid']],
            'local_dimensions_search_assignable_users' => ['templateid' => $f['templateid'], 'query' => 'Gamma'],
            'local_dimensions_get_competency_links' => ['competencyid' => $f['childid']],
            'local_dimensions_search_linkable_courses' => ['competencyid' => $f['childid'], 'query' => 'Probe course'],
            'local_dimensions_get_competency_module_links' => ['competencyid' => $f['childid'], 'courseid' => $f['course1']],
            'local_dimensions_export_framework' => ['frameworkid' => $f['frameworkid']],
            'local_dimensions_export_templates' => [
                'templateids' => (string) $f['templateid'],
                'contextid' => (int) \context_system::instance()->id,
            ],
            'local_dimensions_preview_import_templates' => [
                'draftitemid' => $f['draftitemid'],
                'contextid' => (int) \context_system::instance()->id,
            ],
            'local_dimensions_list_template_cohort_roles' => ['templateid' => $f['templateid']],
            'local_dimensions_list_enrol_competencies' => ['templateid' => $f['templateid'], 'includebootstrap' => true],
            'local_dimensions_list_enrol_courses' => [
                'templateid' => $f['templateid'],
                'competencyid' => $f['childid'],
                'cohortid' => $f['cohortid'],
            ],
            'local_dimensions_get_enrol_queue_status' => [
                'templateid' => $f['templateid'],
                'cohortid' => $f['cohortid'],
                'method' => 'cohort',
                'courseids' => $courses,
            ],
            'local_dimensions_search_categories' => ['query' => 'Probe category'],
        ];
    }

    /**
     * Read the plugin's db/services.php as core does.
     *
     * @return array The $functions array it declares.
     */
    private static function declared_functions(): array {
        $functions = null;
        include(\core_component::get_component_directory('local_dimensions') . '/db/services.php');

        return $functions;
    }

    /**
     * $SESSION as core compares it: one entry per key, the value serialised so a change inside a
     * nested object is seen too.
     *
     * @return array Key => serialised value.
     */
    private static function session_snapshot(): array {
        global $SESSION;

        return array_map('serialize', (array) $SESSION);
    }

    /**
     * The $SESSION keys added, removed or changed between two snapshots.
     *
     * @param array $before Snapshot taken before.
     * @param array $after Snapshot taken after.
     * @return array The keys, sorted.
     */
    private static function changed_keys(array $before, array $after): array {
        $keys = array_keys(array_diff_assoc($before, $after) + array_diff_assoc($after, $before));
        sort($keys);

        return $keys;
    }

    /**
     * Every read is declared read-only, no write is, the call map exercises exactly the declared
     * set, and core's own reader of db/services.php sees the flag.
     *
     * Reading through external_function_info() is the point: it is the value
     * external_api::call_external_function() decides the session lock from.
     *
     * @return void
     */
    public function test_every_read_and_no_write_is_declared_read_only(): void {
        $declared = [];
        foreach (self::declared_functions() as $name => $function) {
            $readonly = !empty($function['readonlysession']);
            $this->assertSame($function['type'] === 'read', $readonly, $name);
            $this->assertSame($readonly, (bool) external_api::external_function_info($name)->readonlysession, $name);
            if ($readonly) {
                $declared[] = $name;
            }
        }
        sort($declared);
        $exercised = array_keys(self::read_only_calls(array_fill_keys(self::FIXTURE_KEYS, 0)));
        sort($exercised);

        $this->assertCount(27, $declared);
        $this->assertSame($exercised, $declared);
    }

    /**
     * The comparison sees a write, so an empty result below is not an empty comparison.
     *
     * @return void
     */
    public function test_the_snapshot_sees_a_write(): void {
        global $SESSION;

        $this->resetAfterTest();
        $before = self::session_snapshot();
        $SESSION->local_dimensions_probe = (object) ['seen' => 1];
        $added = self::session_snapshot();
        $SESSION->local_dimensions_probe->seen = 2;
        $changed = self::session_snapshot();
        unset($SESSION->local_dimensions_probe);

        $this->assertSame(['local_dimensions_probe'], self::changed_keys($before, $added));
        $this->assertSame(['local_dimensions_probe'], self::changed_keys($added, $changed));
        $this->assertSame(['local_dimensions_probe'], self::changed_keys($changed, self::session_snapshot()));
        $this->assertSame([], self::changed_keys($before, self::session_snapshot()));
    }

    /**
     * Every read-only service leaves $SESSION as it found it, on a fixture that gives it work.
     *
     * The hub services run as the site admin, who passes every capability check; the learner
     * services run as the owner of an active plan from the fixture's template, enrolled in the
     * linked course. Before each call the plugin's caches are purged, so every fill runs inside
     * the call. The read-only branch of external_api::call_external_function() is taken, as on a
     * site with $CFG->enable_read_only_sessions on.
     *
     * One require_login() runs before each snapshot. Core's after_require_login callbacks run
     * inside every service of every component, and under PHPUnit tool_mfa's writes a marker
     * ($SESSION->mfa_login_hook_test, admin/tool/mfa/lib.php); paying it first keeps that core
     * write out of the comparison without exempting any key from it.
     *
     * @return void
     */
    public function test_read_only_services_leave_the_session_untouched(): void {
        global $CFG;

        $this->resetAfterTest();
        $f = $this->create_fixture();
        $learner = \core_user::get_user($f['learnerid'], '*', MUST_EXIST);
        $CFG->enable_read_only_sessions = true;

        foreach (self::read_only_calls($f) as $name => $args) {
            if (in_array($name, self::LEARNER_SERVICES, true)) {
                $this->setUser($learner);
            } else {
                $this->setAdminUser();
            }
            foreach (self::CACHES as $definition) {
                \cache::make('local_dimensions', $definition)->purge();
            }
            require_login();
            $_POST['sesskey'] = sesskey();

            $before = self::session_snapshot();
            $result = external_api::call_external_function($name, $args, true);
            $after = self::session_snapshot();

            $this->assertFalse($result['error'], "$name: " . json_encode($result['exception'] ?? null));
            $this->assert_found($name, $result['data'], $f);
            $this->assertSame([], self::changed_keys($before, $after), "$name changed \$SESSION");
        }
    }

    /**
     * The control of each call: what it returned shows it found the fixture, so its path ran and
     * an unchanged session is not the session of a call that stopped early.
     *
     * @param string $name The service.
     * @param mixed $data What it returned.
     * @param array $f The fixture.
     * @return void
     */
    private function assert_found(string $name, $data, array $f): void {
        $short = substr($name, strlen('local_dimensions_'));
        switch ($short) {
            case 'get_course_progress':
                $this->assertSame([$f['course1']], self::ids($data, 'courseid'), $name);
                break;
            case 'get_courses_completion_status':
                $this->assertSame([['courseid' => $f['course1'], 'iscompleted' => false, 'islocked' => false]], $data, $name);
                break;
            case 'get_fontawesome_icons':
                $this->assertNotEmpty($data['icons'], $name);
                break;
            case 'get_competency_courses':
                $this->assertSame([$f['course1']], self::ids($data, 'id'), $name);
                break;
            case 'get_user_competency_summary_in_plan':
                $summary = json_decode($data, true);
                $this->assertSame($f['planid'], (int) $summary['plan']['id'], $name);
                $this->assertSame($f['childid'], (int) $summary['usercompetencysummary']['competency']['competency']['id'], $name);
                break;
            case 'get_competency_rule_data':
                $rule = json_decode($data, true);
                $this->assertTrue($rule['hasrule'], $name);
                $this->assertSame([$f['childid']], self::ids($rule['children'], 'id'), $name);
                break;
            case 'search_competencies':
            case 'search_structure':
            case 'browse_competencies':
            case 'list_related_competencies':
                $this->assertSame([$f['childid']], self::ids($data['items'], 'id'), $name);
                break;
            case 'browse_structure':
                $this->assertContains($f['childid'], self::ids($data['items'], 'id'), $name);
                break;
            case 'competency_usage':
                $this->assertSame([$f['course1']], self::ids($data['courses'], 'id'), $name);
                $this->assertSame([$f['cm1']], self::ids($data['activities'], 'cmid'), $name);
                $this->assertSame([$f['templateid']], self::ids($data['templates'], 'id'), $name);
                break;
            case 'get_structure_node':
                $this->assertSame($f['childid'], (int) $data['node']['id'], $name);
                break;
            case 'list_template_cohorts':
                $this->assertSame([$f['cohortid']], self::ids($data['cohorts'], 'cohortid'), $name);
                break;
            case 'list_template_participants':
                $this->assertSame([$f['learnerid']], self::ids($data['items'], 'userid'), $name);
                break;
            case 'search_assignable_users':
                $this->assertSame([$f['freeuserid']], self::ids($data['items'], 'id'), $name);
                break;
            case 'get_competency_links':
                $this->assertSame([$f['course1']], self::ids($data['items'], 'courseid'), $name);
                break;
            case 'search_linkable_courses':
                $this->assertSame([$f['course2']], self::ids($data['items'], 'id'), $name);
                break;
            case 'get_competency_module_links':
                $this->assertSame([$f['cm1']], self::ids($data['linked'], 'cmid'), $name);
                $this->assertSame([$f['cm2']], self::ids($data['available'], 'cmid'), $name);
                break;
            case 'export_framework':
                $this->assertStringContainsString('RO-CHILD', $data['content'], $name);
                break;
            case 'export_templates':
                $this->assertSame([$f['frameworkid']], self::ids($data['frameworks'], 'id'), $name);
                $this->assertStringContainsString('Probe template', $data['content'], $name);
                break;
            case 'preview_import_templates':
                $this->assertSame(1, (int) $data['counts']['total'], $name);
                $this->assertSame(1, (int) $data['counts']['linksmatched'], $name);
                break;
            case 'list_template_cohort_roles':
                $this->assertSame([$f['roleid']], self::ids($data['assignments'], 'roleid'), $name);
                break;
            case 'list_enrol_competencies':
                $this->assertContains($f['childid'], self::ids($data['items'], 'competencyid'), $name);
                $this->assertContains($f['roleid'], self::ids($data['bootstrap']['roles'], 'id'), $name);
                break;
            case 'list_enrol_courses':
                $this->assertSame([$f['course1']], self::ids($data['items'], 'courseid'), $name);
                break;
            case 'get_enrol_queue_status':
                $this->assertSame([['courseid' => $f['course1'], 'configured' => true]], $data['items'], $name);
                break;
            case 'search_categories':
                $this->assertSame([$f['categoryid']], self::ids($data['items'], 'id'), $name);
                break;
            default:
                $this->fail("$name has no control");
        }
    }

    /**
     * The ids of a list, read from one field and cast.
     *
     * @param array $items The list.
     * @param string $key The id field.
     * @return array The ids, as ints, in list order.
     */
    private static function ids(array $items, string $key): array {
        return array_map('intval', array_column($items, $key));
    }

    /**
     * One fixture every service finds something in.
     *
     * A framework holds a root carrying a rule over its one child, and a second root related to
     * the child. Both the root and the child sit on a template whose cohort holds the learner, who
     * owns an active plan from it and is enrolled in the first course. The child is linked to that
     * course and to one of its two activities; the second course is not linked. The cohort carries
     * a role rule with a custom role and a cohort-sync instance in the first course. A user named
     * Gamma has no plan, and the admin's draft area holds a template CSV that links the child.
     *
     * @return array Ids, keyed as self::FIXTURE_KEYS.
     */
    private function create_fixture(): array {
        global $DB;

        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        set_config('enablecompletion', 1);
        helper::ensure_custom_fields_exist(helper::AREA_LP);
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);
        $generator = $this->getDataGenerator();
        $ccg = $generator->get_plugin_generator('core_competency');

        $category = $generator->create_category(['name' => 'Probe category']);
        $framework = $ccg->create_framework(['shortname' => 'Probe framework', 'idnumber' => 'RO-FW']);
        $frameworkid = (int) $framework->get('id');
        $parent = $ccg->create_competency([
            'competencyframeworkid' => $frameworkid,
            'shortname' => 'Probe root',
            'ruletype' => competency_rule_all::class,
            'ruleoutcome' => competency::OUTCOME_EVIDENCE,
        ]);
        $parentid = (int) $parent->get('id');
        $childid = (int) $ccg->create_competency([
            'competencyframeworkid' => $frameworkid,
            'parentid' => $parentid,
            'shortname' => 'Probe child',
            'idnumber' => 'RO-CHILD',
        ])->get('id');
        $relatedid = (int) $ccg->create_competency([
            'competencyframeworkid' => $frameworkid,
            'shortname' => 'Probe related',
        ])->get('id');
        competencyapi::add_related_competency($childid, $relatedid);

        $course1 = $generator->create_course([
            'category' => $category->id,
            'fullname' => 'Probe course 1',
            'enablecompletion' => 1,
        ]);
        $course2 = $generator->create_course(['category' => $category->id, 'fullname' => 'Probe course 2']);
        $page1 = $generator->create_module('page', ['course' => $course1->id]);
        $page2 = $generator->create_module('page', ['course' => $course1->id]);
        competencyapi::add_competency_to_course($course1->id, $childid);
        competencyapi::add_competency_to_course_module($page1->cmid, $childid);

        $templateid = (int) $ccg->create_template(['shortname' => 'Probe template'])->get('id');
        foreach ([$parentid, $childid] as $competencyid) {
            $ccg->create_template_competency(['templateid' => $templateid, 'competencyid' => $competencyid]);
        }
        $cohort = $generator->create_cohort();
        competencyapi::create_template_cohort($templateid, $cohort->id);
        $learner = $generator->create_user();
        cohort_add_member($cohort->id, $learner->id);
        $generator->enrol_user((int) $learner->id, (int) $course1->id, 'student');
        $planid = (int) $ccg->create_plan([
            'userid' => $learner->id,
            'templateid' => $templateid,
            'status' => plan::STATUS_ACTIVE,
        ])->get('id');
        $freeuserid = (int) $generator->create_user(['firstname' => 'Gamma', 'lastname' => 'Free'])->id;

        // No archetype: the role is assignable at every level, the user and course ones included.
        $roleid = (int) $generator->create_role(['shortname' => 'probecohortrole']);
        $studentid = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);
        set_config('gradebookroles', $studentid . ',' . $roleid);
        \tool_cohortroles\api::create_cohort_role_assignment((object) [
            'userid' => (int) $generator->create_user()->id,
            'roleid' => $roleid,
            'cohortid' => (int) $cohort->id,
        ]);
        $DB->insert_record('enrol', (object) [
            'enrol' => 'cohort',
            'courseid' => $course1->id,
            'customint1' => $cohort->id,
            'roleid' => $roleid,
            'status' => ENROL_INSTANCE_ENABLED,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        return [
            'frameworkid' => $frameworkid,
            'parentid' => $parentid,
            'childid' => $childid,
            'relatedid' => $relatedid,
            'templateid' => $templateid,
            'planid' => $planid,
            'course1' => (int) $course1->id,
            'course2' => (int) $course2->id,
            'cm1' => (int) $page1->cmid,
            'cm2' => (int) $page2->cmid,
            'cohortid' => (int) $cohort->id,
            'roleid' => $roleid,
            'categoryid' => (int) $category->id,
            'learnerid' => (int) $learner->id,
            'freeuserid' => $freeuserid,
            'draftitemid' => $this->upload_template_csv(),
        ];
    }

    /**
     * Put a CSV with one new template linking the child competency into the admin's draft area, as
     * the upload form would.
     *
     * @return int The draft item id.
     */
    private function upload_template_csv(): int {
        global $USER;

        $rows = [
            ['rowtype' => 'template', 'template_idnumber' => 'RO-TPL', 'shortname' => 'Imported probe'],
            [
                'rowtype' => 'link',
                'template_idnumber' => 'RO-TPL',
                'framework_idnumber' => 'RO-FW',
                'competency_idnumber' => 'RO-CHILD',
                'sortorder' => '0',
            ],
        ];
        $headers = template_csv_serializer::headers(false);
        $lines = [self::csv_line($headers)];
        foreach ($rows as $row) {
            $lines[] = self::csv_line(array_map(static fn(string $header): string => $row[$header] ?? '', $headers));
        }
        $draftitemid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => 'templates.csv',
        ], implode("\n", $lines) . "\n");

        return $draftitemid;
    }

    /**
     * Encode one CSV row, quoting every cell.
     *
     * @param array $cells The cell values.
     * @return string The row.
     */
    private static function csv_line(array $cells): string {
        return implode(',', array_map(static fn($cell): string => '"' . str_replace('"', '""', (string) $cell) . '"', $cells));
    }
}
