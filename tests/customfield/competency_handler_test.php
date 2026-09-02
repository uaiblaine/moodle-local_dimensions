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
 * Tests for the competency custom-field handler's edit permissions.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\customfield;

use local_dimensions\constants;
use local_dimensions\helper;

/**
 * A manager holding competencymanage only in a course category edits that category's competencies.
 *
 * Core filters both the rendered and the saved fields through can_edit(), so the save test
 * asserts the value actually written: a wrong context fails silently, writing nothing.
 *
 * @covers \local_dimensions\customfield\competency_handler
 */
final class competency_handler_test extends \advanced_testcase {
    /**
     * A category with a framework and one competency, a system framework with one competency,
     * and a manager scoped to the category.
     *
     * @return array Keys: user, categorycontext, incategory (competency), atsystem (competency).
     */
    private function fixture(): array {
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);

        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $category = $this->getDataGenerator()->create_category();
        $categorycontext = \context_coursecat::instance((int) $category->id);
        $catframework = $ccg->create_framework(['shortname' => 'In category', 'contextid' => $categorycontext->id]);
        $incategory = $ccg->create_competency(['competencyframeworkid' => $catframework->get('id'), 'shortname' => 'Cat']);
        $sysframework = $ccg->create_framework(['shortname' => 'At system']);
        $atsystem = $ccg->create_competency(['competencyframeworkid' => $sysframework->get('id'), 'shortname' => 'Sys']);

        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/competency:competencymanage', CAP_ALLOW, $roleid, $categorycontext->id);
        role_assign($roleid, (int) $user->id, $categorycontext->id);

        return [
            'user' => $user,
            'categorycontext' => $categorycontext,
            'incategory' => $incategory,
            'atsystem' => $atsystem,
        ];
    }

    /**
     * The background-colour field of the competency area: a plain text field every site provisions.
     *
     * @return \core_customfield\field_controller
     */
    private function text_field(): \core_customfield\field_controller {
        $field = helper::find_field_by_shortname(constants::CFIELD_CUSTOMBGCOLOR, helper::AREA_COMPETENCY);
        $this->assertNotNull($field);
        return $field;
    }

    /**
     * The manager may edit a competency in their category and not one at the site.
     *
     * @return void
     */
    public function test_a_category_manager_can_edit_a_category_competency(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $this->setUser($f['user']);
        $handler = competency_handler::create();
        $field = $this->text_field();

        $this->assertTrue($handler->can_edit($field, (int) $f['incategory']->get('id')));
        $this->assertFalse($handler->can_edit($field, (int) $f['atsystem']->get('id')));
    }

    /**
     * On the create path there is no competency yet, so the form names the framework's context.
     *
     * @return void
     */
    public function test_the_create_path_resolves_the_hinted_context(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $this->setUser($f['user']);
        $handler = competency_handler::create();
        $field = $this->text_field();

        $handler->set_edit_context_hint(null);
        $this->assertFalse($handler->can_edit($field, 0), 'Without a hint the create path is site-scoped');
        $handler->set_edit_context_hint($f['categorycontext']);
        $this->assertTrue($handler->can_edit($field, 0), 'The hinted category admits the manager');
        $handler->set_edit_context_hint(null);
    }

    /**
     * A save by the category manager writes the value: core keeps only the fields can_edit() allows.
     *
     * @return void
     */
    public function test_a_category_manager_save_writes_the_value(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $this->setUser($f['user']);
        $handler = competency_handler::create();
        $competencyid = (int) $f['incategory']->get('id');
        $key = 'customfield_' . constants::CFIELD_CUSTOMBGCOLOR;

        $handler->instance_form_save((object) ['id' => $competencyid, $key => '#123456'], false);

        $written = '';
        foreach ($handler->get_instance_data($competencyid, true) as $data) {
            if ($data->get_field()->get('shortname') === constants::CFIELD_CUSTOMBGCOLOR) {
                $written = (string) $data->get_value();
            }
        }
        $this->assertSame('#123456', $written);
    }
}
