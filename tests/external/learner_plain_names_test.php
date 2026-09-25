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

use core_external\external_api;
use local_dimensions\chip_filters;
use local_dimensions\constants;
use local_dimensions\helper;
use local_dimensions\output\view_competency_page;
use local_dimensions\output\view_plan_summary_page;

/**
 * Names on the learner pages reach the reader escaped exactly once.
 *
 * Every fixture name holds a bare ampersand and a "<" followed by a space, the two characters
 * format_string() spells differently in its two modes (a "<b>" tag would be stripped the same
 * way by both and prove nothing). A value sent as data must come back in the plain spelling,
 * and a rendered page must hold the escaped spelling once and never "&amp;amp;".
 *
 * The accordion's JavaScript cannot run here, so its sinks are pinned by reading its source,
 * the way bootstrap_compat_test pins class names.
 *
 * The class-level covers tags stay in docblock form on purpose: moodle-cs on the 4.05 leg cannot
 * see PHP attributes, and the plugin still supports 4.5.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\external\get_competency_courses
 * @covers     \local_dimensions\external\get_course_progress
 * @covers     \local_dimensions\calculator
 * @covers     \local_dimensions\chip_filters
 * @covers     \local_dimensions\output\view_plan_summary_page
 * @covers     \local_dimensions\output\view_competency_page
 */
final class learner_plain_names_test extends \advanced_testcase {
    /** @var string Course full name. */
    private const COURSE = 'R&D < Ops';

    /** @var string Course full name as a rendered page must hold it. */
    private const COURSE_HTML = 'R&amp;D &lt; Ops';

    /** @var string Name of the one activity of the single-activity course. */
    private const ACTIVITY = 'Ask & answer < 5';

    /** @var string Name of the only section of the single-section course. */
    private const SECTION = 'Plan & do < now';

    /** @var string Section name as a rendered page must hold it. */
    private const SECTION_HTML = 'Plan &amp; do &lt; now';

    /**
     * A competency linked to two courses with awkward names, and a learner enrolled in both.
     *
     * The first course boils down to one tracked activity, which is also linked to the
     * competency, so its name travels both as the card's activity and in the activities list.
     * The second has a single authored section holding two tracked activities, so it takes
     * the section shape and lists that section in its timeline.
     *
     * @return array Keys competencyid (int), activitycourse and sectioncourse (stdClass), user (stdClass).
     */
    private function create_fixture(): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/completionlib.php');

        $this->setAdminUser();
        set_config('enablecompletion', 1);
        helper::ensure_custom_fields_exist(helper::AREA_LP);
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);
        set_config('enrollmentfilter', constants::ENROLLMENTFILTER_ALL, 'local_dimensions');

        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework(['visible' => 1]);
        $competency = $ccg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $competencyid = (int) $competency->get('id');

        $activitycourse = $this->getDataGenerator()->create_course([
            'fullname' => self::COURSE,
            'shortname' => self::COURSE . ' short',
            'enablecompletion' => 1,
        ]);
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $activitycourse->id,
            'name' => self::ACTIVITY,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        \core_competency\api::add_competency_to_course((int) $activitycourse->id, $competencyid);
        \core_competency\api::add_competency_to_course_module((int) $page->cmid, $competencyid);

        $sectioncourse = $this->getDataGenerator()->create_course([
            'fullname' => 'Section course',
            'numsections' => 0,
            'enablecompletion' => 1,
        ]);
        foreach (['First', 'Second'] as $name) {
            $this->getDataGenerator()->create_module('page', [
                'course' => $sectioncourse->id,
                'name' => $name,
                'completion' => COMPLETION_TRACKING_MANUAL,
            ]);
        }
        $DB->set_field('course_sections', 'name', self::SECTION, ['course' => $sectioncourse->id, 'section' => 0]);
        rebuild_course_cache((int) $sectioncourse->id, true);
        \core_competency\api::add_competency_to_course((int) $sectioncourse->id, $competencyid);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int) $user->id, (int) $activitycourse->id, 'student');
        $this->getDataGenerator()->enrol_user((int) $user->id, (int) $sectioncourse->id, 'student');

        return [
            'competencyid' => $competencyid,
            'activitycourse' => $activitycourse,
            'sectioncourse' => $sectioncourse,
            'user' => $user,
        ];
    }

    /**
     * Call a web service the way the page does, through its returns allowlist.
     *
     * @param string $methodname The registered function name.
     * @param array $args Its arguments.
     * @return array The cleaned payload.
     */
    private function call_service(string $methodname, array $args): array {
        $_POST['sesskey'] = sesskey();
        $response = external_api::call_external_function($methodname, $args);
        $this->assertFalse($response['error'], json_encode($response['exception'] ?? null));

        return $response['data'];
    }

    /**
     * Assert that rendered HTML holds a fragment escaped once, and nothing escaped twice.
     *
     * @param string $expected The fragment in its once-escaped spelling.
     * @param string $html The rendered HTML (or one element of it).
     * @return void
     */
    private function assert_escaped_once(string $expected, string $html): void {
        $this->assertStringContainsString($expected, $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertStringNotContainsString('&amp;lt;', $html);
    }

    /**
     * The accordion's course service sends every name plain: the JavaScript escapes it once.
     *
     * @return void
     */
    public function test_competency_courses_send_plain_names(): void {
        $this->resetAfterTest();
        $fixture = $this->create_fixture();
        // The service answers only for a competency of a plan the caller reads; no template, so no cascade.
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $planid = (int) $ccg->create_plan([
            'userid' => $fixture['user']->id,
            'status' => \core_competency\plan::STATUS_ACTIVE,
        ])->get('id');
        $ccg->create_plan_competency(['planid' => $planid, 'competencyid' => $fixture['competencyid']]);
        $this->setUser($fixture['user']);

        $rows = array_column(
            $this->call_service('local_dimensions_get_competency_courses', [
                'competencyid' => $fixture['competencyid'],
                'planid' => $planid,
            ]),
            null,
            'id'
        );
        $activityrow = $rows[(int) $fixture['activitycourse']->id];
        $sectionrow = $rows[(int) $fixture['sectioncourse']->id];

        $this->assertSame(self::COURSE, $activityrow['fullname']);
        $this->assertSame(self::COURSE . ' short', $activityrow['shortname']);
        $this->assertSame(constants::CARDMODE_ACTIVITY, $activityrow['cardmode']);
        $this->assertSame(self::ACTIVITY, $activityrow['activity']['name']);
        $this->assertCount(1, $activityrow['activities']);
        $this->assertSame(self::ACTIVITY, $activityrow['activities'][0]['name']);

        $this->assertSame(constants::CARDMODE_SECTION, $sectionrow['cardmode']);
        $this->assertTrue($sectionrow['section']['hasownname']);
        $this->assertSame(self::SECTION, $sectionrow['section']['name']);
    }

    /**
     * The tracker's progress service sends plain names, and its template escapes them once.
     *
     * The card body is rendered by competency_view.js in the browser; rendering it here with the
     * same payload shows what the double stashes do with each spelling.
     *
     * @return void
     */
    public function test_course_progress_sends_plain_names_and_its_card_escapes_them_once(): void {
        global $OUTPUT;
        $this->resetAfterTest();
        $fixture = $this->create_fixture();
        $this->setUser($fixture['user']);

        $rows = array_column(
            $this->call_service('local_dimensions_get_course_progress', [
                'courseids' => [(int) $fixture['activitycourse']->id, (int) $fixture['sectioncourse']->id],
            ]),
            null,
            'courseid'
        );
        $activityrow = $rows[(int) $fixture['activitycourse']->id];
        $sectionrow = $rows[(int) $fixture['sectioncourse']->id];

        $this->assertSame(self::ACTIVITY, $activityrow['activity']['name']);
        $this->assertSame(self::SECTION, $sectionrow['section']['name']);
        $this->assertCount(1, $sectionrow['sections']);
        $this->assertSame(self::SECTION, $sectionrow['sections'][0]['name']);

        // The timeline flag is what competency_view.js derives from cardmode before rendering.
        $html = $OUTPUT->render_from_template(
            'local_dimensions/progress_card_body',
            $sectionrow + ['istimeline' => true]
        );
        $this->assertSame(1, preg_match('~local-dimensions-single-section-name">([^<]*)</span>~', $html, $name));
        $this->assertSame(self::SECTION_HTML, $name[1]);
        $this->assertSame(1, preg_match('~local-dimensions-timeline-content">\s*<a [^>]*>([^<]*)</a>~', $html, $row));
        $this->assertSame(self::SECTION_HTML, $row[1]);
        $this->assert_escaped_once(self::SECTION_HTML, $html);

        $html = $OUTPUT->render_from_template('local_dimensions/progress_card_body', $activityrow);
        $this->assertSame(1, preg_match('~local-dimensions-single-name">([^<]*)</span>~', $html, $name));
        $this->assertSame('Ask &amp; answer &lt; 5', $name[1]);
        $this->assert_escaped_once('Ask &amp; answer &lt; 5', $html);
    }

    /**
     * The plan overview prints each competency name once escaped, and keeps the hero's.
     *
     * The competency name lands in a double stash, so the renderable exports it plain; the
     * hero title lands in a triple stash, so it stays escaped.
     *
     * @return void
     */
    public function test_plan_overview_prints_names_escaped_once(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework(['visible' => 1]);
        $template = $ccg->create_template(['shortname' => 'Induction & onboarding < 1']);
        $competency = $ccg->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'shortname' => self::COURSE,
        ]);
        $ccg->create_template_competency([
            'templateid' => $template->get('id'),
            'competencyid' => $competency->get('id'),
        ]);
        $user = $this->getDataGenerator()->create_user();
        $plan = $ccg->create_plan([
            'userid' => $user->id,
            'templateid' => $template->get('id'),
            'status' => \core_competency\plan::STATUS_ACTIVE,
        ]);
        $this->setUser($user);

        $renderer = $PAGE->get_renderer('core');
        $data = (new view_plan_summary_page($plan))->export_for_template($renderer);
        $this->assertSame(self::COURSE, $data['competencies'][0]['shortname']);

        $html = $renderer->render_from_template('local_dimensions/view_plan_summary', $data);
        $this->assertSame(1, preg_match('~local-dimensions-accordion-title">([^<]*)</span>~', $html, $title));
        $this->assertSame(self::COURSE_HTML, $title[1]);
        $this->assertSame(1, preg_match('~local-dimensions-hero-title">([^<]*)</h1>~', $html, $hero));
        $this->assertSame('Induction &amp; onboarding &lt; 1', $hero[1]);
        $this->assert_escaped_once(self::COURSE_HTML, $html);
    }

    /**
     * The tracker's course card names the course once escaped, in its text and its label.
     *
     * @return void
     */
    public function test_tracker_course_card_names_the_course_escaped_once(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $fixture = $this->create_fixture();
        $course = get_course((int) $fixture['activitycourse']->id);
        $competency = $DB->get_record('competency', ['id' => $fixture['competencyid']], '*', MUST_EXIST);
        $this->setUser($fixture['user']);

        $page = new view_competency_page($competency, [(int) $course->id => $course], (int) $fixture['user']->id);
        $renderer = $PAGE->get_renderer('core');
        $data = $page->export_for_template($renderer);
        $this->assertSame(self::COURSE_HTML, $data['courses'][0]['fullname']);
        $this->assertSame(
            get_string('view_course', 'local_dimensions', self::COURSE),
            $data['courses'][0]['viewcoursestr']
        );

        $html = $renderer->render_from_template('local_dimensions/view_competency', $data);
        $this->assertSame(1, preg_match('~<a href="[^"]*" aria-label="([^"]*)">([^<]*)</a>~', $html, $link));
        $this->assertSame(s(get_string('view_course', 'local_dimensions', self::COURSE)), $link[1]);
        $this->assertSame(self::COURSE_HTML, $link[2]);
        $this->assert_escaped_once(self::COURSE_HTML, $html);
    }

    /**
     * Chip group labels are read plain, and the chip template escapes label and value once.
     *
     * @return void
     */
    public function test_chip_labels_are_plain_and_render_escaped_once(): void {
        global $OUTPUT;
        $this->resetAfterTest();
        $cfg = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $category = $cfg->create_category(['component' => 'core_course', 'area' => 'course', 'itemid' => 0]);
        $cfg->create_field([
            'categoryid' => $category->get('id'),
            'type' => 'text',
            'shortname' => 'rdops',
            'name' => self::COURSE,
        ]);

        $labels = chip_filters::get_field_labels('course', ['rdops']);
        $this->assertSame(['rdops' => self::COURSE], $labels);

        $groups = chip_filters::build_filterfields_payload(['rdops'], [1 => ['rdops' => self::ACTIVITY]], $labels);
        $html = $OUTPUT->render_from_template('local_dimensions/chip_filters', [
            'id' => 'chips',
            'hasgroups' => true,
            'groups' => $groups,
            'clearlabel' => 'Clear',
        ]);
        $this->assertSame(1, preg_match('~local-dimensions-chip-group-label"[^>]*>([^<]*)</span>~', $html, $label));
        $this->assertSame(self::COURSE_HTML, $label[1]);
        $this->assertSame(1, preg_match('~data-chip-value="([^"]*)"[^>]*>\s*([^<]*?)\s*</button>~', $html, $chip));
        $this->assertSame('Ask &amp; answer &lt; 5', $chip[1]);
        $this->assertSame('Ask &amp; answer &lt; 5', $chip[2]);
        $this->assert_escaped_once(self::COURSE_HTML, $html);
    }

    /**
     * The accordion decodes core's exporter text once and escapes every name once.
     *
     * Core's exporters hand PARAM_TEXT properties over already escaped, so each such field is
     * decoded by fromExporter() and then escaped by escapeHtml() where it enters HTML; the one
     * that feeds a Mustache double stash is decoded only. Titles read back from the page are
     * plain text, and core/modal writes a title as HTML, so they are escaped.
     *
     * @return void
     */
    public function test_accordion_escapes_exporter_and_page_text_once(): void {
        global $CFG;
        $source = file_get_contents($CFG->dirroot . '/local/dimensions/amd/src/accordion.js');

        // No exporter field goes straight into escapeHtml(): it would come out escaped twice.
        $this->assertSame(0, preg_match_all(
            '/escapeHtml\(\s*(?:ev|uc|related|parent|decisive|data\.framework)\??\.(?:gradename|shortname)\b/',
            $source
        ));

        // The decoder parses into an inert document; an element in the page would load markup.
        $this->assertSame(1, preg_match('/function fromExporter\([^)]*\)\s*\{(.*?)\n        \}/s', $source, $body));
        $this->assertStringContainsString('new DOMParser()', $body[1]);
        $this->assertStringNotContainsString('innerHTML', $body[1]);

        /* Every decoded value is escaped again where it enters HTML, except the evidence modal's
           grade, which a double stash escapes. Dropping the escape would be an injection. */
        preg_match_all(
            '/(.{0,40})\bfromExporter\((?:ev|uc|related|parent|data\.framework)\.(?:gradename|shortname)\)/',
            $source,
            $calls,
            PREG_SET_ORDER
        );
        $this->assertCount(6, $calls);
        foreach ($calls as $call) {
            if (preg_match('/gradename:\s*hasGrade\s*\?\s*$/', $call[1])) {
                continue;
            }
            $this->assertMatchesRegularExpression('/escapeHtml\($/', $call[1], $call[0]);
        }
        $this->assertSame(1, preg_match('/gradename:\s*hasGrade\s*\?\s*fromExporter\(ev\.gradename\)/', $source));

        /* The Rules tab's names come plain from the plugin's own get_competency_rule_data, so each
           use is escaped once and none is decoded. */
        foreach (['child.shortname', 'child.gradename', 'ruleText', 'data.requiredwarningtext'] as $field) {
            $this->assertSame(1, substr_count($source, 'escapeHtml(' . $field . ')'), $field);
            $this->assertSame(0, preg_match('/\+=?\s*' . preg_quote($field, '/') . '\b/', $source), $field);
        }
        $this->assertStringNotContainsString('fromExporter(child.', $source);

        // Both grid modal titles and the taxonomy modal title are escaped plain text.
        $this->assertSame(0, preg_match_all('/(?:title:|setTitle\()\s*title\s*\?\s*title\.textContent/', $source));
        $this->assertSame(2, preg_match_all('/title\s*\?\s*escapeHtml\(title\.textContent\.trim\(\)\)/', $source));
        $this->assertSame(1, preg_match('/taxonomyWhatIs\.replace\(\'\{\$a\}\',\s*escapeHtml\(term\)\)/', $source));
    }
}
