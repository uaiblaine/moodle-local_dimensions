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
use core_external\external_api;
use local_dimensions\constants;
use local_dimensions\customfield\competency_handler;
use local_dimensions\customfield\lp_handler;
use local_dimensions\helper;
use local_dimensions\output\dynamictabs\frameworks;
use local_dimensions\output\dynamictabs\plans;
use local_dimensions\output\dynamictabs\structure;
use local_dimensions\template_metadata_cache;

/**
 * Names in the Competency hub reach the reader escaped exactly once.
 *
 * Every fixture name is "R&D < Ops": a bare ampersand and a "<" followed by a space are the two
 * characters format_string() spells differently in its two modes, while a "<b>" tag would be
 * stripped the same way by both and prove nothing. A value the hub sends as data (a web-service
 * field, a template context value) must be exactly that plain spelling, and a rendered tab must
 * hold "R&amp;D &lt; Ops" and never "&amp;amp;".
 *
 * The hub's JavaScript cannot run here, so the sinks that parse HTML (modal titles and bodies,
 * confirm dialogues, toasts, autocomplete labels) are pinned by reading its source, the way
 * bootstrap_compat_test pins class names.
 *
 * The class-level covers tags stay in docblock form on purpose: moodle-cs on the 4.05 leg cannot
 * see PHP attributes, and the plugin still supports 4.5.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\helper
 * @covers     \local_dimensions\output\dynamictabs\frameworks
 * @covers     \local_dimensions\output\dynamictabs\structure
 * @covers     \local_dimensions\output\dynamictabs\plans
 * @covers     \local_dimensions\external\browse_competencies
 * @covers     \local_dimensions\external\browse_structure
 * @covers     \local_dimensions\external\get_competency_links
 * @covers     \local_dimensions\external\get_competency_module_links
 * @covers     \local_dimensions\external\get_structure_node
 * @covers     \local_dimensions\external\link_competency_course
 * @covers     \local_dimensions\external\link_competency_module
 * @covers     \local_dimensions\external\list_enrol_competencies
 * @covers     \local_dimensions\external\list_enrol_courses
 * @covers     \local_dimensions\external\list_related_competencies
 * @covers     \local_dimensions\external\list_template_cohort_roles
 * @covers     \local_dimensions\external\list_template_cohorts
 * @covers     \local_dimensions\external\list_template_participants
 * @covers     \local_dimensions\external\search_competencies
 * @covers     \local_dimensions\external\search_linkable_courses
 * @covers     \local_dimensions\external\search_structure
 */
final class hub_plain_names_test extends \advanced_testcase {
    /** @var string Every fixture name, in the plain spelling. */
    private const NAME = 'R&D < Ops';

    /** @var string The fixture name as a rendered page must hold it. */
    private const NAME_HTML = 'R&amp;D &lt; Ops';

    /** @var string Short name of the second course (course short names are unique). */
    private const SECOND = 'R&D < Ops 2';

    /**
     * A framework, template, courses, cohort and role that all carry the awkward name.
     *
     * The framework (idnumber and scale named too) holds a root and its child, plus a second root
     * related to the child. The child carries type and tag labels, sits on the template and is
     * linked to the first course and to one of its two activities; the second course is not linked
     * yet. The template has a cohort with one member who holds a plan from it, a cohort role rule
     * and a cohort-sync instance in the first course, both with a custom role that is also a
     * gradebook role.
     *
     * @return array Keys: frameworkid, parentid, childid, relatedid, templateid, course1, course2,
     *               cm1, cm2, cohortid, roleid (ints, course1/course2 records).
     */
    private function create_fixture(): array {
        global $DB;

        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $generator = $this->getDataGenerator();
        $ccg = $generator->get_plugin_generator('core_competency');

        $category = $generator->create_category(['name' => self::NAME]);
        $scale = $generator->create_scale(['name' => self::NAME, 'scale' => 'Low,High']);
        $framework = $ccg->create_framework([
            'shortname' => self::NAME,
            'idnumber' => self::NAME,
            'scaleid' => $scale->id,
        ]);
        $frameworkid = (int) $framework->get('id');
        $parent = $ccg->create_competency(['competencyframeworkid' => $frameworkid, 'shortname' => self::NAME]);
        $child = $ccg->create_competency([
            'competencyframeworkid' => $frameworkid,
            'parentid' => $parent->get('id'),
            'shortname' => self::NAME,
        ]);
        $related = $ccg->create_competency(['competencyframeworkid' => $frameworkid, 'shortname' => self::NAME]);
        competencyapi::add_related_competency($child->get('id'), $related->get('id'));
        $childid = (int) $child->get('id');

        $this->set_select_labels(helper::AREA_COMPETENCY, $childid);

        $course1 = $generator->create_course([
            'category' => $category->id,
            'fullname' => self::NAME,
            'shortname' => self::NAME,
        ]);
        $course2 = $generator->create_course([
            'category' => $category->id,
            'fullname' => self::NAME,
            'shortname' => self::SECOND,
        ]);
        $page1 = $generator->create_module('page', ['course' => $course1->id, 'name' => self::NAME]);
        $page2 = $generator->create_module('page', ['course' => $course1->id, 'name' => self::NAME]);
        competencyapi::add_competency_to_course($course1->id, $childid);
        competencyapi::add_competency_to_course_module($page1->cmid, $childid);

        $template = $ccg->create_template(['shortname' => self::NAME]);
        $templateid = (int) $template->get('id');
        $ccg->create_template_competency(['templateid' => $templateid, 'competencyid' => $childid]);
        $this->set_select_labels(helper::AREA_LP, $templateid);

        $cohort = $generator->create_cohort(['name' => self::NAME]);
        competencyapi::create_template_cohort($templateid, $cohort->id);
        $member = $generator->create_user();
        cohort_add_member($cohort->id, $member->id);
        $ccg->create_plan(['userid' => $member->id, 'templateid' => $templateid]);

        // No archetype: the role is assignable at every level, the user and course ones included.
        $roleid = (int) $generator->create_role(['name' => self::NAME, 'shortname' => 'rdops']);
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
            'parentid' => (int) $parent->get('id'),
            'childid' => $childid,
            'relatedid' => (int) $related->get('id'),
            'templateid' => $templateid,
            'course1' => $course1,
            'course2' => $course2,
            'cm1' => (int) $page1->cmid,
            'cm2' => (int) $page2->cmid,
            'cohortid' => (int) $cohort->id,
            'roleid' => $roleid,
        ];
    }

    /**
     * Give an instance the fixture name as its type, tag 1 and tag 2 labels (and, for a template,
     * its ID number), through the plugin's own handler.
     *
     * The option lists are rewritten to hold the label first, so each select stores index 1.
     *
     * @param string $area helper::AREA_COMPETENCY or helper::AREA_LP.
     * @param int $instanceid The competency or template id.
     * @param string $label The option label to store.
     * @return void
     */
    private function set_select_labels(string $area, int $instanceid, string $label = self::NAME): void {
        global $DB;

        helper::ensure_custom_fields_exist($area);
        $formdata = ['id' => $instanceid];
        foreach ([constants::CFIELD_TYPE, constants::CFIELD_TAG1, constants::CFIELD_TAG2] as $shortname) {
            $fieldid = (int) helper::find_field_by_shortname($shortname, $area)->get('id');
            $config = json_decode((string) $DB->get_field('customfield_field', 'configdata', ['id' => $fieldid]), true);
            $config['options'] = $label . "\nOther";
            $DB->set_field('customfield_field', 'configdata', json_encode($config), ['id' => $fieldid]);
            $formdata['customfield_' . $shortname] = 1;
        }
        if ($area === helper::AREA_LP) {
            $formdata['customfield_' . constants::CFIELD_TEMPLATE_IDNUMBER] = self::NAME;
            lp_handler::create()->instance_form_save((object) $formdata, true);
            template_metadata_cache::purge_all();
        } else {
            competency_handler::create()->instance_form_save((object) $formdata, true);
        }
    }

    /**
     * Call a web service the way the page does, through its returns allowlist.
     *
     * @param string $methodname The registered function name.
     * @param array $args Its arguments.
     * @return mixed The cleaned payload.
     */
    private function call_service(string $methodname, array $args): mixed {
        $_POST['sesskey'] = sesskey();
        $response = external_api::call_external_function($methodname, $args);
        $this->assertFalse($response['error'], json_encode($response['exception'] ?? null));

        return $response['data'];
    }

    /**
     * The one item of a list whose id field matches.
     *
     * @param array $items The list.
     * @param string $key The id field.
     * @param int $id The id wanted.
     * @return array The item.
     */
    private function item(array $items, string $key, int $id): array {
        $found = array_values(array_filter($items, static fn(array $item): bool => (int) $item[$key] === $id));
        $this->assertCount(1, $found, "No single item with $key $id");

        return $found[0];
    }

    /**
     * Assert that rendered HTML holds the name escaped once, and nothing escaped twice.
     *
     * @param string $html The rendered HTML.
     * @return void
     */
    private function assert_escaped_once(string $html): void {
        $this->assertStringContainsString(self::NAME_HTML, $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertStringNotContainsString('&amp;lt;', $html);
    }

    /**
     * The Frameworks tab sends each framework's name and ID number plain and renders them once.
     *
     * @return void
     */
    public function test_frameworks_tab_is_plain_and_renders_once(): void {
        global $OUTPUT, $PAGE;
        $this->resetAfterTest();
        $f = $this->create_fixture();

        $data = (new frameworks(['contexttype' => 'system']))->export_for_template($PAGE->get_renderer('core'));
        $row = $this->item($data['frameworks'], 'id', $f['frameworkid']);
        $this->assertSame(self::NAME, $row['shortname']);
        $this->assertSame(self::NAME, $row['idnumber']);

        $html = $OUTPUT->render_from_template('local_dimensions/central/frameworks', $data);
        $this->assert_escaped_once($html);
        $this->assertStringContainsString('data-name="' . self::NAME_HTML . '"', $html);
    }

    /**
     * The Structure tab sends framework options and root nodes plain and renders them once.
     *
     * @return void
     */
    public function test_structure_tab_is_plain_and_renders_once(): void {
        global $OUTPUT, $PAGE;
        $this->resetAfterTest();
        $f = $this->create_fixture();

        $tab = new structure(['contexttype' => 'system', 'frameworkid' => $f['frameworkid']]);
        $data = $tab->export_for_template($PAGE->get_renderer('core'));
        $option = $this->item($data['frameworks'], 'id', $f['frameworkid']);
        $this->assertSame(self::NAME, $option['name']);
        $this->assertSame(self::NAME, $option['idnumber']);
        $this->assertSame(self::NAME, $data['selectedframeworkname']);
        $this->assertSame(self::NAME, $data['selectedframeworkidnumber']);
        $root = $this->item($data['competencies'], 'id', $f['parentid']);
        $this->assertSame(self::NAME, $root['shortname']);
        $this->assertSame(self::NAME, $root['scale']);

        $html = $OUTPUT->render_from_template('local_dimensions/central/structure', $data);
        $this->assert_escaped_once($html);
        $this->assertStringContainsString('data-name="' . self::NAME_HTML . '"', $html);
        $this->assertStringContainsString('data-scale="' . self::NAME_HTML . '"', $html);
    }

    /**
     * The Plans tab sends template, competency, path, label and filter names plain and renders them once.
     *
     * @return void
     */
    public function test_plans_tab_is_plain_and_renders_once(): void {
        global $OUTPUT, $PAGE;
        $this->resetAfterTest();
        $f = $this->create_fixture();

        $tab = new plans([
            'contexttype' => 'system',
            'templateid' => $f['templateid'],
            'competencyids' => (string) $f['childid'],
        ]);
        $data = $tab->export_for_template($PAGE->get_renderer('core'));

        $this->assertSame(self::NAME, $this->item($data['competencyfilters'], 'id', $f['childid'])['label']);
        $template = $this->item($data['templates'], 'id', $f['templateid']);
        $this->assertSame(self::NAME, $template['name']);
        $this->assertSame(self::NAME, $template['idnumber']);
        // The search haystack must match what the user types, not an escaped form of it.
        $this->assertSame(\core_text::strtolower(self::NAME . ' ' . self::NAME), $template['search']);

        $competency = $this->item($data['competencies'], 'id', $f['childid']);
        $this->assertSame(self::NAME, $competency['shortname']);
        $this->assertSame(self::NAME, $competency['path']);
        $this->assertSame(self::NAME, $competency['frameworktag']);

        $this->assertSame(self::NAME, $data['selectedtemplatename']);
        $this->assertSame(self::NAME, $data['selectedtemplateidnumber']);
        $this->assertSame(self::NAME, $data['selectedtemplatetype']);
        $this->assertSame(self::NAME, $data['selectedtemplatetag1']);
        $this->assertSame(self::NAME, $data['selectedtemplatetag2']);

        $html = $OUTPUT->render_from_template('local_dimensions/central/plans', $data);
        $this->assert_escaped_once($html);
        $this->assertStringContainsString('data-templatename="' . self::NAME_HTML . '"', $html);
    }

    /**
     * The Structure tree services send node names, scale and labels plain, and their node renders once.
     *
     * @return void
     */
    public function test_structure_services_send_plain_nodes(): void {
        global $OUTPUT;
        $this->resetAfterTest();
        $f = $this->create_fixture();

        $browse = $this->call_service('local_dimensions_browse_structure', [
            'frameworkid' => $f['frameworkid'],
            'parentid' => $f['parentid'],
        ]);
        $node = $this->item($browse['items'], 'id', $f['childid']);
        foreach (['shortname', 'scale', 'type', 'tag1', 'tag2'] as $key) {
            $this->assertSame(self::NAME, $node[$key], $key);
        }

        $single = $this->call_service('local_dimensions_get_structure_node', ['competencyid' => $f['childid']]);
        $this->assertSame(self::NAME, $single['node']['shortname']);
        $this->assertSame(self::NAME, $single['node']['type']);

        $html = $OUTPUT->render_from_template('local_dimensions/central/structure_node', $node);
        $this->assert_escaped_once($html);
        $this->assertStringContainsString('data-tag1="' . self::NAME_HTML . '"', $html);
    }

    /**
     * A type or tag label typed with markup reaches the tree as text, and never breaks the service.
     *
     * The labels are admin text read raw from the option list. Unformatted, a tag would reach the
     * PARAM_TEXT return fields, whose cleaning strips it and then rejects the whole response.
     *
     * @return void
     */
    public function test_structure_labels_are_formatted_before_they_travel(): void {
        $this->resetAfterTest();
        $f = $this->create_fixture();
        $this->set_select_labels(helper::AREA_COMPETENCY, $f['childid'], 'Core <b>skills</b>');

        $browse = $this->call_service('local_dimensions_browse_structure', [
            'frameworkid' => $f['frameworkid'],
            'parentid' => $f['parentid'],
        ]);
        $node = $this->item($browse['items'], 'id', $f['childid']);
        foreach (['type', 'tag1', 'tag2'] as $key) {
            $this->assertSame('Core skills', $node[$key], $key);
        }
    }

    /**
     * The competency searches and browsers send names, framework tags and ancestor paths plain.
     *
     * @return void
     */
    public function test_competency_search_services_send_plain_names_and_paths(): void {
        $this->resetAfterTest();
        $f = $this->create_fixture();

        $search = $this->call_service('local_dimensions_search_competencies', ['query' => 'R&D']);
        $hit = $this->item($search['items'], 'id', $f['childid']);
        $this->assertSame(self::NAME, $hit['shortname']);
        $this->assertSame(self::NAME, $hit['frameworktag']);
        $this->assertSame(self::NAME, $hit['path']);

        $structure = $this->call_service('local_dimensions_search_structure', [
            'frameworkid' => $f['frameworkid'],
            'query' => 'R&D',
        ]);
        $hit = $this->item($structure['items'], 'id', $f['childid']);
        $this->assertSame(self::NAME, $hit['shortname']);
        $this->assertSame(self::NAME, $hit['path']);

        $browse = $this->call_service('local_dimensions_browse_competencies', [
            'frameworkid' => $f['frameworkid'],
            'query' => 'R&D',
        ]);
        $hit = $this->item($browse['items'], 'id', $f['childid']);
        $this->assertSame(self::NAME, $hit['shortname']);
        $this->assertSame(self::NAME, $hit['path']);

        // Relations are symmetric: the related root lists the child, whose path names the parent.
        $related = $this->call_service('local_dimensions_list_related_competencies', ['competencyid' => $f['relatedid']]);
        $hit = $this->item($related['items'], 'id', $f['childid']);
        $this->assertSame(self::NAME, $hit['shortname']);
        $this->assertSame(self::NAME, $hit['path']);
    }

    /**
     * The Courses & activities services send course and activity names plain.
     *
     * @return void
     */
    public function test_link_services_send_plain_course_and_activity_names(): void {
        $this->resetAfterTest();
        $f = $this->create_fixture();
        $course1id = (int) $f['course1']->id;
        $course2id = (int) $f['course2']->id;

        $links = $this->call_service('local_dimensions_get_competency_links', ['competencyid' => $f['childid']]);
        $row = $this->item($links['items'], 'courseid', $course1id);
        $this->assertSame(self::NAME, $row['fullname']);
        $this->assertSame(self::NAME, $row['shortname']);

        $linkable = $this->call_service('local_dimensions_search_linkable_courses', [
            'competencyid' => $f['childid'],
            'query' => 'R&D',
        ]);
        $hit = $this->item($linkable['items'], 'id', $course2id);
        $this->assertSame(self::NAME, $hit['fullname']);
        $this->assertSame(self::SECOND, $hit['shortname']);

        $linked = $this->call_service('local_dimensions_link_competency_course', [
            'competencyid' => $f['childid'],
            'courseid' => $course2id,
        ]);
        $this->assertSame(self::NAME, $linked['fullname']);
        $this->assertSame(self::SECOND, $linked['shortname']);

        $modules = $this->call_service('local_dimensions_get_competency_module_links', [
            'competencyid' => $f['childid'],
            'courseid' => $course1id,
        ]);
        $this->assertSame(self::NAME, $this->item($modules['linked'], 'cmid', $f['cm1'])['name']);
        $this->assertSame(self::NAME, $this->item($modules['available'], 'cmid', $f['cm2'])['name']);

        $module = $this->call_service('local_dimensions_link_competency_module', [
            'competencyid' => $f['childid'],
            'cmid' => $f['cm2'],
        ]);
        $this->assertSame(self::NAME, $module['name']);
    }

    /**
     * The enrolment-methods services send competency, course, category and role names plain.
     *
     * @return void
     */
    public function test_enrol_services_send_plain_names(): void {
        $this->resetAfterTest();
        $f = $this->create_fixture();

        $competencies = $this->call_service('local_dimensions_list_enrol_competencies', [
            'templateid' => $f['templateid'],
            'includebootstrap' => true,
        ]);
        $this->assertSame(self::NAME, $this->item($competencies['items'], 'competencyid', $f['childid'])['shortname']);
        $this->assertContains(self::NAME, array_column($competencies['bootstrap']['categories'], 'name'));
        $this->assertSame(self::NAME, $this->item($competencies['bootstrap']['roles'], 'id', $f['roleid'])['name']);

        // The name filter matches the text the user sees, ampersand and all.
        $filtered = $this->call_service('local_dimensions_list_enrol_competencies', [
            'templateid' => $f['templateid'],
            'query' => 'R&D',
        ]);
        $this->assertSame(1, (int) $filtered['total']);

        $courses = $this->call_service('local_dimensions_list_enrol_courses', [
            'templateid' => $f['templateid'],
            'competencyid' => $f['childid'],
            'cohortid' => $f['cohortid'],
        ]);
        $row = $this->item($courses['items'], 'courseid', (int) $f['course1']->id);
        foreach (['shortname', 'fullname', 'categoryname', 'cohortrolename'] as $key) {
            $this->assertSame(self::NAME, $row[$key], $key);
        }
    }

    /**
     * The participants services send cohort, role and template names plain.
     *
     * @return void
     */
    public function test_participant_services_send_plain_names(): void {
        $this->resetAfterTest();
        $f = $this->create_fixture();

        $cohorts = $this->call_service('local_dimensions_list_template_cohorts', ['templateid' => $f['templateid']]);
        $this->assertSame(self::NAME, $this->item($cohorts['cohorts'], 'cohortid', $f['cohortid'])['name']);

        $roles = $this->call_service('local_dimensions_list_template_cohort_roles', ['templateid' => $f['templateid']]);
        $this->assertSame(self::NAME, $this->item($roles['roles'], 'id', $f['roleid'])['name']);
        $this->assertSame(self::NAME, $this->item($roles['cohorts'], 'cohortid', $f['cohortid'])['name']);
        $assignment = $this->item($roles['assignments'], 'roleid', $f['roleid']);
        $this->assertSame(self::NAME, $assignment['rolename']);
        $this->assertSame(self::NAME, $assignment['cohortname']);

        $participants = $this->call_service('local_dimensions_list_template_participants', [
            'templateid' => $f['templateid'],
        ]);
        $this->assertCount(1, $participants['items']);
        $this->assertSame(self::NAME, $participants['items'][0]['modelo']);
        $this->assertSame(self::NAME, $participants['items'][0]['cohorts']);
    }

    /**
     * A role holder's name reaches the roles pane exactly as stored, and a markup-shaped one cannot
     * fail the listing.
     *
     * Core's user APIs strip tags from names on write, but a row written around them keeps what it
     * holds. Under PARAM_TEXT a "<" before a letter failed the returns check, taking every assignment
     * of the plan with it; the field is raw, like the participants grid's names, and the pane writes
     * it as text.
     *
     * @return void
     */
    public function test_role_holder_name_travels_raw(): void {
        global $DB;
        $this->resetAfterTest();
        $f = $this->create_fixture();

        $holder = $this->getDataGenerator()->create_user(['firstname' => self::NAME, 'lastname' => 'Holder']);
        // Written around user_update_user(), which would strip the tag-shaped part.
        $DB->set_field('user', 'lastname', 'A<B', ['id' => $holder->id]);
        \tool_cohortroles\api::create_cohort_role_assignment((object) [
            'userid' => (int) $holder->id,
            'roleid' => $f['roleid'],
            'cohortid' => $f['cohortid'],
        ]);
        $expected = fullname($DB->get_record('user', ['id' => $holder->id]));
        $this->assertStringContainsString(self::NAME . ' A<B', $expected);

        $roles = $this->call_service('local_dimensions_list_template_cohort_roles', ['templateid' => $f['templateid']]);
        $this->assertSame($expected, $this->item($roles['assignments'], 'userid', (int) $holder->id)['userfullname']);

        // A raw value is safe only in a text sink.
        $this->assertStringContainsString('user.textContent = assignment.userfullname;', $this->js_source('roles_manager'));
    }

    /**
     * Plain role names: a custom name loses no ampersand, a standard role keeps core's default.
     *
     * @return void
     */
    public function test_plain_role_names(): void {
        global $DB;
        $this->resetAfterTest();
        $roleid = (int) $this->getDataGenerator()->create_role(['name' => self::NAME, 'shortname' => 'rdops']);
        $studentid = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);

        $names = helper::plain_role_names([$roleid, $studentid, 999999]);

        $this->assertEqualsCanonicalizing([$roleid, $studentid], array_keys($names));
        $this->assertSame(self::NAME, $names[$roleid]);
        $this->assertSame(get_string('defaultcoursestudent'), $names[$studentid]);
    }

    /**
     * Where the hub's JavaScript puts a plain name into HTML, it escapes it; where core hands it an
     * escaped one for a double stash, it decodes it.
     *
     * The web services above now send names plain, so a sink that parses HTML (a modal title, a
     * confirm or alert body, a toast, an autocomplete label) would read a bare "<" as markup
     * unless the module escapes the value there. Each assertion names one such call site.
     *
     * @return void
     */
    public function test_hub_javascript_escapes_plain_names_at_html_sinks(): void {
        $sites = [
            'category_datasource' => ['/label: \x60\$\{escapeHtml\(item\.name\)\} \(/'],
            // The linkable-course and competency searches now send plain names, and the user search
            // raw ones: these escapes are the only ones before core/form-autocomplete's HTML label.
            'course_datasource' => [
                '/let label = escapeHtml\(course\.fullname\);/',
                '/\+ \x60\$\{escapeHtml\(course\.shortname\)\}<\/span>\x60;/',
            ],
            'competency_datasource' => [
                '/<span class="fw-medium">\$\{escapeHtml\(competency\.shortname\)\}<\/span>/',
                '/<div class="small text-muted">\$\{escapeHtml\(trail\)\}<\/div>/',
            ],
            'user_datasource' => [
                '/let label = escapeHtml\(user\.fullname\);/',
                '/\+ \x60\$\{escapeHtml\(user\.identity\)\}<\/span>\x60;/',
            ],
            'competency_links' => [
                '/const name = escapeHtml\(courseEl\.dataset\.fullname \|\| \'\'\);/',
                '/const name = escapeHtml\(moduleEl\.dataset\.name \|\| \'\'\);/',
                '/getString\(\'central_links_title\', \'local_dimensions\', escapeHtml\(opts\.competencyname\)\)/',
            ],
            'related_competencies' => [
                '/const name = escapeHtml\(rowEl\.querySelector\(\'\.fw-medium\'\)\.textContent\);/',
                '/getString\(\'central_related_title\', \'local_dimensions\', escapeHtml\(opts\.competencyname\)\)/',
            ],
            'structure' => [
                '/const name = escapeHtml\(row\.dataset\.name \|\| \'\'\);\s*const \[title, question\]/',
                '/getString\(\'competencycannotbedeleted\', \'tool_lp\', name\)/',
                '/label \+ \' — \' \+ escapeHtml\(row\.dataset\.name \|\| \'\'\)/',
            ],
            'competency_detail' => ['/title: escapeHtml\(data\.name\),/'],
            'plans' => [
                '/getString\(\'deletetemplate\', \'tool_lp\', escapeHtml\(name\)\)/',
                '/getString\(\'central_removecompetency_confirm\', \'local_dimensions\', escapeHtml\(name\)\)/',
            ],
            'frameworks' => ['/\{name: escapeHtml\(row\.dataset\.name\), count: row\.dataset\.count\}/'],
            'cohort_manager' => ['/const name = escapeHtml\(row\.querySelector\(\'th\'\)\.textContent\);/'],
            'roles_manager' => ['/getString\(\'central_roles_remove_confirm\', \'local_dimensions\', escapeHtml\(rolename\)\)/'],
            'participants_users' => [
                '/getString\(\'central_participants_delete_confirm\', \'local_dimensions\', '
                    . 'escapeHtml\(row\.querySelector\(\'td\'\)\.textContent\)\)/',
            ],
            'enrol_methods' => [
                '/getString\(toastkey, \'local_dimensions\', escapeHtml\(row\.dataset\.shortname\)\)/',
                '/Modal\.create\(\{title: escapeHtml\(data\.fullname\), body: html, large: true\}\)/',
            ],
            'framework_scaleconfig' => ['/name: decodeEntities\(value\.name\),/'],
            'competency_browser' => ['/shortname: decodeEntities\(framework\.shortname\),/'],
        ];
        foreach ($sites as $module => $patterns) {
            $source = $this->js_source($module);
            foreach ($patterns as $pattern) {
                $this->assertMatchesRegularExpression($pattern, $source, "$module: $pattern");
            }
        }

        // The shared helper escapes all five characters that change meaning in HTML.
        $escape = $this->js_source('escape');
        $this->assertStringContainsString(
            "const ENTITIES = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '\"': '&quot;', \"'\": '&#39;'};",
            $escape
        );
        $this->assertMatchesRegularExpression('/return value\.replace\(\/\[&<>"\'\]\/g, \(char\) => ENTITIES\[char\]\);/', $escape);
    }

    /**
     * Core returns the names these two modules decode already escaped: the decode is what keeps
     * their double stashes from escaping twice.
     *
     * @return void
     */
    public function test_core_services_the_hub_decodes_return_escaped_names(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $scale = $this->getDataGenerator()->create_scale(['name' => 'Scale', 'scale' => self::NAME . ',High']);
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $ccg->create_framework(['shortname' => self::NAME, 'scaleid' => $scale->id]);

        $values = $this->call_service('core_competency_get_scale_values', ['scaleid' => (int) $scale->id]);
        $this->assert_escaped_spelling($values[0]['name']);

        $frameworks = $this->call_service('core_competency_list_competency_frameworks', [
            'sort' => 'shortname',
            'context' => ['contextid' => \context_system::instance()->id],
            'includes' => 'parents',
        ]);
        $this->assertCount(1, $frameworks);
        $this->assert_escaped_spelling($frameworks[0]['shortname']);
    }

    /**
     * Assert that a value is the fixture name escaped, whichever entities core chose for it.
     *
     * Filters may re-spell an entity (numeric instead of named), so the check is that the value is
     * not the plain name and that decoding it once gives the plain name back.
     *
     * @param string $value The value core returned.
     * @return void
     */
    private function assert_escaped_spelling(string $value): void {
        $this->assertNotSame(self::NAME, $value);
        $this->assertSame(self::NAME, html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * A rule save sends the rule fields alone, so the competency's name is never rewritten.
     *
     * core_competency_read_competency returns the name escaped (and the description formatted);
     * the Structure tab used to echo that record back, escaping the stored name once more on every
     * rule save. The update service leaves an omitted field untouched, which is what the tab now
     * relies on.
     *
     * @return void
     */
    public function test_rule_save_leaves_the_stored_name_alone(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework();
        $parent = $ccg->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'shortname' => self::NAME,
            'description' => '<p>' . self::NAME_HTML . '</p>',
            'descriptionformat' => FORMAT_HTML,
        ]);
        $parentid = (int) $parent->get('id');

        $read = $this->call_service('core_competency_read_competency', ['id' => $parentid]);
        $this->assert_escaped_spelling($read['shortname']);

        $updated = $this->call_service('core_competency_update_competency', ['competency' => [
            'id' => $parentid,
            'ruletype' => 'core_competency\\competency_rule_all',
            'ruleoutcome' => \core_competency\competency::OUTCOME_EVIDENCE,
            'ruleconfig' => null,
        ]]);
        $this->assertTrue($updated);
        $stored = new \core_competency\competency($parentid);
        $this->assertSame(self::NAME, $stored->get('shortname'));
        $this->assertSame('<p>' . self::NAME_HTML . '</p>', $stored->get('description'));
        $this->assertSame('core_competency\\competency_rule_all', $stored->get('ruletype'));

        $source = $this->js_source('structure');
        $this->assertSame(1, preg_match('/const persistRule = \(row, config\) => \{(.*?)\n\};/s', $source, $body));
        $this->assertStringNotContainsString('core_competency_read_competency', $body[1]);
        $this->assertMatchesRegularExpression(
            '/competency: \{\s*id: id,\s*ruletype: config\.ruletype,\s*ruleoutcome: config\.ruleoutcome,'
                . '\s*ruleconfig: config\.ruleconfig,\s*\}/',
            $body[1]
        );
    }

    /**
     * Read one of the hub's AMD sources.
     *
     * @param string $module The module name under amd/src/central.
     * @return string The source.
     */
    private function js_source(string $module): string {
        global $CFG;
        $path = $CFG->dirroot . '/local/dimensions/amd/src/central/' . $module . '.js';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
