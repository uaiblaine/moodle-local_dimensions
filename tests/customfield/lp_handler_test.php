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
use local_dimensions\helper;

/**
 * Tests for the learning plan template custom-field handler's editing rights.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\customfield\lp_handler
 */
final class lp_handler_test extends \advanced_testcase {
    /**
     * A manager who holds templatemanage only in a course category can edit the custom fields of
     * a template in that category — and still cannot edit the SCSS field, which is site-wide.
     *
     * @return void
     */
    public function test_a_category_manager_can_edit_a_category_template(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        helper::ensure_custom_fields_exist(helper::AREA_LP);

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $category = $this->getDataGenerator()->create_category();
        $categorycontext = \context_coursecat::instance((int) $category->id);
        $incategory = $generator->create_template(['shortname' => 'In category', 'contextid' => $categorycontext->id]);
        $atsystem = $generator->create_template(['shortname' => 'At system']);

        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/competency:templatemanage', CAP_ALLOW, $roleid, $categorycontext->id);
        role_assign($roleid, (int) $user->id, $categorycontext->id);
        $this->setUser($user);

        $handler = lp_handler::create();
        $idnumberfield = helper::find_field_by_shortname(constants::CFIELD_TEMPLATE_IDNUMBER, helper::AREA_LP);
        $scssfield = helper::find_field_by_shortname(constants::CFIELD_CUSTOMSCSS, helper::AREA_LP);

        $this->assertNotNull($idnumberfield);
        $this->assertTrue($handler->can_edit($idnumberfield, (int) $incategory->get('id')));
        // The same field on a system-context template is still out of reach.
        $this->assertFalse($handler->can_edit($idnumberfield, (int) $atsystem->get('id')));
        if ($scssfield) {
            $this->assertFalse($handler->can_edit($scssfield, (int) $incategory->get('id')));
        }
    }

    /**
     * With no instance the check falls back to the system context: the field-configuration
     * screens edit the definitions themselves, which are site-wide.
     *
     * @return void
     */
    public function test_the_field_configuration_screens_stay_system_scoped(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        helper::ensure_custom_fields_exist(helper::AREA_LP);

        $field = helper::find_field_by_shortname(constants::CFIELD_TEMPLATE_IDNUMBER, helper::AREA_LP);
        $this->assertNotNull($field);
        $this->assertTrue(lp_handler::create()->can_edit($field, 0));

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertFalse(lp_handler::create()->can_edit($field, 0));
    }

    /**
     * A category manager's values really land, which is what the capability fix is for: the
     * handler saves only the fields can_edit() allows, so before the fix this wrote nothing.
     *
     * @return void
     */
    public function test_a_category_managers_values_are_written(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        helper::ensure_custom_fields_exist(helper::AREA_LP);

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $category = $this->getDataGenerator()->create_category();
        $categorycontext = \context_coursecat::instance((int) $category->id);
        $template = $generator->create_template(['shortname' => 'Owned', 'contextid' => $categorycontext->id]);
        $templateid = (int) $template->get('id');

        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/competency:templatemanage', CAP_ALLOW, $roleid, $categorycontext->id);
        role_assign($roleid, (int) $user->id, $categorycontext->id);
        $this->setUser($user);

        $formdata = (object) (['id' => $templateid] + helper::template_customfields_to_formdata([
            'template_idnumber' => 'CAT-1',
        ]));
        lp_handler::create()->instance_form_save($formdata, true);

        $this->assertSame('CAT-1', helper::export_template_customfields($templateid)['template_idnumber']);
    }
}
