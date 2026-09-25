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

namespace local_dimensions\customfield;

use local_dimensions\constants;
use local_dimensions\event\competency_customfields_updated;
use local_dimensions\event\template_customfields_updated;
use local_dimensions\form\competency_dynamic_form;
use local_dimensions\form\template_dynamic_form;
use local_dimensions\helper;
use local_dimensions\picture_manager;

/**
 * Tests that a hub modal save logs whether it created the instance, in both of its events.
 *
 * One save through a hub modal can fire two customfields events: one for the field values and
 * one for the built-in images. Both must say whether the submission created the instance.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\customfield\lp_handler
 * @covers     \local_dimensions\customfield\competency_handler
 * @covers     \local_dimensions\form\template_dynamic_form
 */
final class customfields_updated_isnew_test extends \advanced_testcase {
    /**
     * A user draft area holding one image, to post as a filemanager's value.
     *
     * A real image: the filemanager reads the size of every image in the draft area.
     *
     * @return int Draft item id.
     */
    private function draft_with_image(): int {
        global $CFG, $USER;
        $draftid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_pathname([
            'contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftid,
            'filepath' => '/',
            'filename' => 'bg.png',
        ], $CFG->dirroot . '/lib/tests/fixtures/gd-logo.png');
        return $draftid;
    }

    /**
     * Submit a hub modal and return the isnew flag of every customfields event it fired.
     *
     * The submission changes a field value and uploads a background image, so a save fires both
     * events.
     *
     * @param string $formclass The dynamic form class.
     * @param string $eventclass The customfields event class to collect.
     * @param array $submission The posted values, without the image.
     * @param string $area Custom field area of the form ('lp' or 'competency').
     * @return bool[] One isnew flag per event, in firing order.
     */
    private function submit_and_collect_isnew(string $formclass, string $eventclass, array $submission, string $area): array {
        $submission[picture_manager::get_form_element_name($area, 'bgimage')] = $this->draft_with_image();
        $submission[picture_manager::get_form_element_name($area, 'cardimage')] = file_get_unused_draft_itemid();

        $ajaxdata = $formclass::mock_ajax_submit($submission);
        $form = new $formclass(null, null, 'post', '', null, true, $ajaxdata, true);
        $form->set_data_for_dynamic_submission();
        $this->assertTrue($form->is_validated(), 'The modal refused the submission');

        $sink = $this->redirectEvents();
        $form->process_dynamic_submission();
        $events = array_values(array_filter($sink->get_events(), static fn($event): bool => $event instanceof $eventclass));
        $sink->close();

        // Control: the save fired both events, so neither assertion below can pass on an empty list.
        $this->assertCount(2, $events, 'Expected a values event and an image event');
        return array_map(static fn($event) => $event->other['isnew'], $events);
    }

    /**
     * The template modal's posted values.
     *
     * @param int $id Template id, 0 to create one.
     * @param string $idnumber Template ID number custom field value.
     * @return array
     */
    private function template_submission(int $id, string $idnumber): array {
        return [
            'id' => $id,
            'contextid' => \context_system::instance()->id,
            'shortname' => 'Template ' . $idnumber,
            'description' => ['text' => '', 'format' => FORMAT_HTML],
            'visible' => 1,
            'customfield_' . constants::CFIELD_TEMPLATE_IDNUMBER => $idnumber,
        ];
    }

    /**
     * The competency modal's posted values.
     *
     * @param int $id Competency id, 0 to create one.
     * @param int $frameworkid Framework id.
     * @param string $colour Background colour custom field value.
     * @return array
     */
    private function competency_submission(int $id, int $frameworkid, string $colour): array {
        return [
            'id' => $id,
            'competencyframeworkid' => $frameworkid,
            'parentid' => 0,
            'shortname' => 'Competency ' . $colour,
            'idnumber' => 'C' . ltrim($colour, '#'),
            'description' => ['text' => '', 'format' => FORMAT_HTML],
            'scaleid' => '',
            'scaleconfiguration' => '',
            'customfield_' . constants::CFIELD_CUSTOMBGCOLOR => $colour,
        ];
    }

    /**
     * Creating a template through the modal logs isnew = true in both events.
     *
     * @return void
     */
    public function test_creating_a_template_logs_new_in_both_events(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('imagehandler', 'builtin', 'local_dimensions');
        helper::ensure_custom_fields_exist(helper::AREA_LP);

        $isnew = $this->submit_and_collect_isnew(
            template_dynamic_form::class,
            template_customfields_updated::class,
            $this->template_submission(0, 'NEW-1'),
            helper::AREA_LP
        );

        $this->assertSame([true, true], $isnew);
    }

    /**
     * Editing a template through the modal logs isnew = false in both events.
     *
     * @return void
     */
    public function test_editing_a_template_logs_not_new_in_both_events(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('imagehandler', 'builtin', 'local_dimensions');
        helper::ensure_custom_fields_exist(helper::AREA_LP);
        $templateid = (int) $this->getDataGenerator()->get_plugin_generator('core_competency')
            ->create_template(['shortname' => 'Existing'])->get('id');

        $isnew = $this->submit_and_collect_isnew(
            template_dynamic_form::class,
            template_customfields_updated::class,
            $this->template_submission($templateid, 'EDIT-1'),
            helper::AREA_LP
        );

        $this->assertSame([false, false], $isnew);
    }

    /**
     * Creating a competency through the modal logs isnew = true in both events.
     *
     * @return void
     */
    public function test_creating_a_competency_logs_new_in_both_events(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('imagehandler', 'builtin', 'local_dimensions');
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);
        $frameworkid = (int) $this->getDataGenerator()->get_plugin_generator('core_competency')
            ->create_framework()->get('id');

        $isnew = $this->submit_and_collect_isnew(
            competency_dynamic_form::class,
            competency_customfields_updated::class,
            $this->competency_submission(0, $frameworkid, '#112233'),
            helper::AREA_COMPETENCY
        );

        $this->assertSame([true, true], $isnew);
    }

    /**
     * Editing a competency through the modal logs isnew = false in both events.
     *
     * @return void
     */
    public function test_editing_a_competency_logs_not_new_in_both_events(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('imagehandler', 'builtin', 'local_dimensions');
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $frameworkid = (int) $generator->create_framework()->get('id');
        $competencyid = (int) $generator->create_competency(['competencyframeworkid' => $frameworkid])->get('id');

        $isnew = $this->submit_and_collect_isnew(
            competency_dynamic_form::class,
            competency_customfields_updated::class,
            $this->competency_submission($competencyid, $frameworkid, '#445566'),
            helper::AREA_COMPETENCY
        );

        $this->assertSame([false, false], $isnew);
    }
}
