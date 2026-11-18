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

namespace local_dimensions\form;

use core_competency\template;
use local_dimensions\constants;
use local_dimensions\helper;

/**
 * What the hub's template, framework and competency modals trust from a submission.
 *
 * The context comes from the record being edited or the context access was checked in, never
 * from the posted hidden field; the custom fields' own rules apply; the custom SCSS is stored as
 * plain text.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\form\template_dynamic_form
 * @covers     \local_dimensions\form\framework_dynamic_form
 * @covers     \local_dimensions\form\competency_dynamic_form
 * @covers     \local_dimensions\helper
 */
final class hub_forms_submission_test extends \advanced_testcase {
    /**
     * An administrator, competencies on, the custom fields of both areas, and one category.
     *
     * @return \context_coursecat
     */
    private function category(): \context_coursecat {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        helper::ensure_custom_fields_exist(helper::AREA_LP);
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);
        return \context_coursecat::instance((int) $this->getDataGenerator()->create_category()->id);
    }

    /**
     * What the template modal posts.
     *
     * @param int $id Template id, 0 to create.
     * @param int $contextid The hidden contextid field.
     * @param string $shortname The short name typed in.
     * @return array
     */
    private function template_post(int $id, int $contextid, string $shortname): array {
        return [
            'id' => $id,
            'contextid' => $contextid,
            'shortname' => $shortname,
            'description' => ['text' => '', 'format' => FORMAT_HTML],
            'visible' => 1,
        ];
    }

    /**
     * Run the dynamic form web service the way the modal calls it.
     *
     * @param string $formclass The form class.
     * @param array $post The posted values.
     * @return array The web service result.
     */
    private function submit(string $formclass, array $post): array {
        // The form checks the sesskey of the request, which the modal's web service call carries.
        $_POST['sesskey'] = sesskey();
        $post = $formclass::mock_generate_submit_keys($post);
        return \core_form\external\dynamic_form::execute($formclass, http_build_query($post, '', '&'));
    }

    /**
     * Editing a template with another context posted saves it in its own context; core's update
     * refuses a context change, so the posted one used to end the save in an exception.
     *
     * @return void
     */
    public function test_an_edit_keeps_the_templates_own_context(): void {
        $category = $this->category();
        $own = $this->getDataGenerator()->get_plugin_generator('core_competency')
            ->create_template(['shortname' => 'Before', 'contextid' => $category->id]);

        $result = $this->submit(
            template_dynamic_form::class,
            $this->template_post((int) $own->get('id'), \context_system::instance()->id, 'After')
        );

        $this->assertTrue($result['submitted']);
        $saved = template::get_record(['id' => $own->get('id')]);
        $this->assertSame('After', $saved->get('shortname'));
        $this->assertSame((int) $category->id, (int) $saved->get('contextid'));
    }

    /**
     * The short name is unique in the template's or framework's own context, whatever context is
     * posted: the clash in the category is found though the site context is posted, and a name
     * free in the category (the control) passes.
     *
     * @return void
     */
    public function test_shortname_uniqueness_reads_the_templates_own_context(): void {
        $category = $this->category();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $ccg->create_template(['shortname' => 'Taken', 'contextid' => $category->id]);
        $own = $ccg->create_template(['shortname' => 'Mine', 'contextid' => $category->id]);
        $system = \context_system::instance()->id;

        $taken = $this->template_post((int) $own->get('id'), $system, 'Taken');
        $form = new template_dynamic_form(null, null, 'post', '', [], true, $taken);
        $this->assertArrayHasKey('shortname', $form->validation($taken, []));

        $free = $this->template_post((int) $own->get('id'), $system, 'Free');
        $form = new template_dynamic_form(null, null, 'post', '', [], true, $free);
        $this->assertArrayNotHasKey('shortname', $form->validation($free, []));

        // The framework modal reads the same way.
        $ccg->create_framework(['shortname' => 'Taken', 'contextid' => $category->id]);
        $mine = $ccg->create_framework(['shortname' => 'Mine', 'contextid' => $category->id]);
        foreach (['Taken' => true, 'Free' => false] as $shortname => $clashes) {
            $post = [
                'id' => (int) $mine->get('id'),
                'contextid' => $system,
                'shortname' => $shortname,
                'idnumber' => (string) $mine->get('idnumber'),
                'scaleid' => (int) $mine->get('scaleid'),
                'scaleconfiguration' => $mine->get('scaleconfiguration'),
            ];
            $form = new framework_dynamic_form(null, null, 'post', '', [], true, $post);
            $this->assertSame($clashes, array_key_exists('shortname', $form->validation($post, [])), "Framework $shortname");
        }
    }

    /**
     * The template and framework modals open only at the site or a course category, as core
     * keeps both there; the site (the control) opens.
     *
     * @return void
     */
    public function test_the_modals_refuse_a_context_core_keeps_no_template_in(): void {
        $this->category();
        $coursecontext = \context_course::instance((int) $this->getDataGenerator()->create_course()->id);
        foreach ([template_dynamic_form::class, framework_dynamic_form::class] as $formclass) {
            $result = \core_form\external\dynamic_form::execute(
                $formclass,
                http_build_query(['id' => 0, 'contextid' => \context_system::instance()->id], '', '&')
            );
            $this->assertFalse($result['submitted'], "$formclass at the site");
            try {
                \core_form\external\dynamic_form::execute(
                    $formclass,
                    http_build_query(['id' => 0, 'contextid' => $coursecontext->id], '', '&')
                );
                $this->fail("$formclass opened in a course context.");
            } catch (\moodle_exception $e) {
                $this->assertSame('invalidcontext', $e->errorcode, $formclass);
            }
        }
    }

    /**
     * The custom fields' own rules apply in both modals: the colour fields' 255-character limit
     * is a field error, and a value within it (the control) is not.
     *
     * @return void
     */
    public function test_the_custom_fields_rules_apply(): void {
        $category = $this->category();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $template = $ccg->create_template(['contextid' => $category->id]);
        $framework = $ccg->create_framework(['contextid' => $category->id]);
        $competency = $ccg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $key = 'customfield_' . constants::CFIELD_CUSTOMBGCOLOR;

        $post = $this->template_post((int) $template->get('id'), $category->id, 'Plan');
        $form = new template_dynamic_form(null, null, 'post', '', [], true, $post);
        $this->assertArrayHasKey($key, $form->validation([$key => str_repeat('a', 256)] + $post, []));
        $this->assertArrayNotHasKey($key, $form->validation([$key => '#123456'] + $post, []));

        $post = [
            'id' => (int) $competency->get('id'),
            'competencyframeworkid' => (int) $framework->get('id'),
            'shortname' => 'Competency',
            'idnumber' => 'C-RULES',
        ];
        $form = new competency_dynamic_form(null, null, 'post', '', [], true, $post);
        $this->assertArrayHasKey($key, $form->validation([$key => str_repeat('a', 256)] + $post, []));
        $this->assertArrayNotHasKey($key, $form->validation([$key => '#123456'] + $post, []));
    }

    /**
     * The competency modal stores the custom SCSS as plain text whatever format is posted, as the
     * template modal does.
     *
     * @return void
     */
    public function test_the_competency_modal_pins_the_scss_to_plain_text(): void {
        global $DB;
        $category = $this->category();
        $framework = $this->getDataGenerator()->get_plugin_generator('core_competency')
            ->create_framework(['contextid' => $category->id]);
        $field = helper::get_customscss_field(helper::AREA_COMPETENCY);
        $this->assertNotNull($field);

        $result = $this->submit(competency_dynamic_form::class, [
            'id' => 0,
            'competencyframeworkid' => (int) $framework->get('id'),
            'shortname' => 'Styled',
            'idnumber' => 'C-STYLED',
            'description' => ['text' => '', 'format' => FORMAT_HTML],
            'customfield_' . constants::CFIELD_CUSTOMSCSS . '_editor' => ['text' => '.x { color: red; }', 'format' => FORMAT_HTML],
        ]);

        $this->assertTrue($result['submitted']);
        $competencyid = (int) json_decode($result['data'], true)['competencyid'];
        $row = $DB->get_record('customfield_data', ['fieldid' => $field->get('id'), 'instanceid' => $competencyid]);
        $this->assertNotFalse($row, 'The SCSS was saved');
        $this->assertSame((int) FORMAT_PLAIN, (int) $row->valueformat);
    }
}
