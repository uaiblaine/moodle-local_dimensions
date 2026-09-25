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

use core_competency\competency_framework;

/**
 * Tests for the framework modal's idnumber check.
 *
 * Core refuses a framework idnumber already used anywhere on the site, from the persistent's own
 * validation, which the modal only meets as an exception once it saves.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\form\framework_dynamic_form
 */
final class framework_dynamic_form_idnumber_test extends \advanced_testcase {
    /** @var competency_framework A framework in a course category, holding the idnumber 'taken'. */
    protected $existing;

    /**
     * One framework already holds the idnumber, in a category rather than at the site.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $category = $this->getDataGenerator()->create_category();
        $this->existing = $this->getDataGenerator()->get_plugin_generator('core_competency')->create_framework([
            'idnumber' => 'taken',
            'contextid' => \context_coursecat::instance((int) $category->id)->id,
        ]);
    }

    /**
     * What the modal posts to create (id 0) or edit a framework at the site context.
     *
     * @param int $id Framework id, 0 to create.
     * @param string $idnumber The idnumber typed in.
     * @return array
     */
    protected function formdata(int $id, string $idnumber): array {
        return [
            'id' => $id,
            'contextid' => \context_system::instance()->id,
            'shortname' => 'Framework ' . $idnumber,
            'idnumber' => $idnumber,
            'description' => ['text' => '', 'format' => FORMAT_HTML],
            'scaleid' => (int) $this->existing->get('scaleid'),
            'scaleconfiguration' => $this->existing->get('scaleconfiguration'),
            'visible' => 1,
        ];
    }

    /**
     * Validation errors for a submission.
     *
     * @param array $data The submitted data.
     * @return array
     */
    protected function errors(array $data): array {
        $form = new framework_dynamic_form(null, null, 'post', '', [], true, $data);
        return $form->validation($data, []);
    }

    /**
     * An idnumber held by a framework in another context is a field error.
     *
     * @return void
     */
    public function test_an_idnumber_taken_anywhere_is_a_field_error(): void {
        $errors = $this->errors($this->formdata(0, 'taken'));

        $this->assertSame(get_string('idnumbertaken', 'error'), $errors['idnumber'] ?? null);
    }

    /**
     * A free idnumber, and a framework keeping its own, raise no idnumber error.
     *
     * @return void
     */
    public function test_a_free_idnumber_and_the_frameworks_own_are_accepted(): void {
        $this->assertArrayNotHasKey('idnumber', $this->errors($this->formdata(0, 'free')));
        $this->assertArrayNotHasKey('idnumber', $this->errors($this->formdata((int) $this->existing->get('id'), 'taken')));
    }

    /**
     * The modal's submission comes back unvalidated instead of reaching the save and throwing.
     *
     * @return void
     */
    public function test_the_modal_refuses_a_taken_idnumber_before_saving(): void {
        // The form checks the sesskey of the request, which the modal's web service call carries.
        $_POST['sesskey'] = sesskey();
        $free = framework_dynamic_form::mock_generate_submit_keys($this->formdata(0, 'free'));
        $form = new framework_dynamic_form(null, null, 'post', '', [], true, $free, true);
        $form->set_data_for_dynamic_submission();
        $this->assertTrue($form->is_validated());

        $taken = framework_dynamic_form::mock_generate_submit_keys($this->formdata(0, 'taken'));
        $form = new framework_dynamic_form(null, null, 'post', '', [], true, $taken, true);
        $form->set_data_for_dynamic_submission();
        $this->assertFalse($form->is_validated());
    }
}
