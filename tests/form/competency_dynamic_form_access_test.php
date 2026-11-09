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

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The competency modal resolves its context from the competency being edited, not only from the posted framework.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_dimensions\form\competency_dynamic_form::class)]
final class competency_dynamic_form_access_test extends \advanced_testcase {
    /**
     * Two categories, each with a framework and a competency; the current user manages competencies in A only.
     *
     * @return array Keys: fwa, fwb, compa, compb (ids).
     */
    private function build_fixture(): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        $dg = $this->getDataGenerator();
        $lpg = $dg->get_plugin_generator('core_competency');
        $catctx = [];
        $out = [];
        foreach (['a', 'b'] as $key) {
            $category = $dg->create_category();
            $catctx[$key] = \context_coursecat::instance((int) $category->id);
            $framework = $lpg->create_framework(['contextid' => $catctx[$key]->id]);
            $competency = $lpg->create_competency([
                'competencyframeworkid' => $framework->get('id'),
                'shortname' => 'Competency ' . strtoupper($key),
            ]);
            $out['fw' . $key] = (int) $framework->get('id');
            $out['comp' . $key] = (int) $competency->get('id');
        }

        $user = $dg->create_user();
        $roleid = $dg->create_role();
        assign_capability('moodle/competency:competencymanage', CAP_ALLOW, $roleid, $catctx['a']->id);
        role_assign($roleid, (int) $user->id, $catctx['a']->id);
        $this->setUser($user);
        return $out;
    }

    /**
     * Build the form the way core's dynamic form web service does.
     *
     * @param array $data The posted values.
     * @return competency_dynamic_form
     */
    private function open_form(array $data): competency_dynamic_form {
        return new competency_dynamic_form(null, null, 'post', '', [], true, $data, true);
    }

    /**
     * A competency of another category cannot be opened by naming one's own framework beside it.
     *
     * @return void
     */
    public function test_foreign_competency_with_own_framework_is_refused(): void {
        $f = $this->build_fixture();

        $this->expectException(\invalid_parameter_exception::class);
        $this->open_form(['id' => $f['compb'], 'competencyframeworkid' => $f['fwa']]);
    }

    /**
     * A competency of another category, with its true framework, is refused for want of the capability.
     *
     * @return void
     */
    public function test_foreign_competency_with_its_own_framework_lacks_the_capability(): void {
        $f = $this->build_fixture();

        $this->expectException(\required_capability_exception::class);
        $this->open_form(['id' => $f['compb'], 'competencyframeworkid' => $f['fwb']]);
    }

    /**
     * A competency id that does not exist is refused.
     *
     * @return void
     */
    public function test_missing_competency_is_refused(): void {
        $f = $this->build_fixture();

        $this->expectException(\dml_missing_record_exception::class);
        $this->open_form(['id' => $f['compb'] + 1000, 'competencyframeworkid' => $f['fwa']]);
    }

    /**
     * The control: the manager of A opens A's competency and the form loads its fields.
     *
     * @return void
     */
    public function test_own_competency_loads(): void {
        $f = $this->build_fixture();

        $form = $this->open_form(['id' => $f['compa'], 'competencyframeworkid' => $f['fwa']]);
        $form->set_data_for_dynamic_submission();
        $this->assertStringContainsString('value="Competency A"', $form->render());
    }
}
