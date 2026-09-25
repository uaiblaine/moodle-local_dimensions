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

use local_dimensions\constants;
use local_dimensions\customfield\lp_handler;
use local_dimensions\helper;

/**
 * Tests which template settings the modal hides for each display mode.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\form\template_dynamic_form
 */
final class template_dynamic_form_display_mode_test extends \advanced_testcase {
    /**
     * Drop the handler's cached field list, which would outlive the rollback of the rows it holds.
     *
     * @return void
     */
    protected function tearDown(): void {
        lp_handler::create()->reset_configuration_cache();
        parent::tearDown();
    }

    /**
     * The locked-card settings stay editable in both display modes, since the plan overview applies them too.
     *
     * @return void
     */
    public function test_the_locked_card_settings_are_shown_in_both_display_modes(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_LP);

        $mform = $this->quickform();
        $redirect = 'customfield_' . constants::CFIELD_SINGLECOURSEREDIRECT;
        $showrelated = 'customfield_' . constants::CFIELD_SHOWRELATED;
        $lockedcard = [
            'customfield_' . constants::CFIELD_LOCKEDCARDMODE,
            'customfield_' . constants::CFIELD_SHOWLOCKEDDATE,
        ];

        // Precondition: every field named here is in the form, so "not hidden" means shown.
        foreach (array_merge([$redirect, $showrelated], $lockedcard) as $name) {
            $this->assertTrue($mform->elementExists($name), "$name is not in the form.");
        }

        $hiddeninplan = $this->hidden_when($mform, (string) constants::DISPLAYMODE_PLAN);
        $hiddenintracker = $this->hidden_when($mform, (string) constants::DISPLAYMODE_COMPETENCIES);

        // Controls: each mode still hides the setting it does not use, so the rules were read.
        $this->assertContains($redirect, $hiddeninplan);
        $this->assertContains($showrelated, $hiddenintracker);
        foreach ($lockedcard as $name) {
            $this->assertNotContains($name, $hiddeninplan);
            $this->assertNotContains($name, $hiddenintracker);
        }
    }

    /**
     * The quickform of the template modal, built the way its web service builds it for a new template.
     *
     * @return \MoodleQuickForm
     */
    private function quickform(): \MoodleQuickForm {
        $formdata = ['id' => 0, 'contextid' => \context_system::instance()->id];
        $form = new template_dynamic_form(null, null, 'post', '', [], true, $formdata, true);
        return (new \ReflectionProperty(\moodleform::class, '_form'))->getValue($form);
    }

    /**
     * The elements the form's hide rules hide while the display mode select holds a value.
     *
     * @param \MoodleQuickForm $mform The form.
     * @param string $value The display mode select's value.
     * @return string[] Element names.
     */
    private function hidden_when(\MoodleQuickForm $mform, string $value): array {
        [, $rules] = $mform->getLockOptionObject();
        $hidden = [];
        foreach ($rules['customfield_' . constants::CFIELD_DISPLAYMODE] ?? [] as $condition => $operands) {
            foreach ($operands as $operand => $actions) {
                $fires = match ($condition) {
                    'eq' => (string) $operand === $value,
                    'neq' => (string) $operand !== $value,
                    'in' => in_array($value, explode('|', (string) $operand), true),
                    default => $this->fail("Unexpected condition '$condition' on the display mode."),
                };
                if ($fires) {
                    $hidden = array_merge($hidden, $actions[\MoodleQuickForm::DEP_HIDE] ?? []);
                }
            }
        }
        return $hidden;
    }
}
