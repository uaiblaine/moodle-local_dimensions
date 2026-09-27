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
 * Tests for the Structure tab's sticky-footer actions.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\output;

/**
 * The competency rule action is disabled for a competency without child competencies.
 *
 * A competency rule is computed from the child competencies, so a competency without any takes none.
 * The footer renders the rule button disabled for it, with a title saying why, and leaves every other
 * action alone. structure.js hands the template the selected row's flag
 * (hub_javascript_guards_test::test_rule_action_needs_child_competencies).
 *
 * Coverage stays in a docblock annotation: moodle-cs for Moodle 4.5 cannot see PHP attributes.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class structure_footer_actions_test extends \advanced_testcase {
    /**
     * Without child competencies only the rule button is disabled, and it says why.
     *
     * @return void
     */
    public function test_rule_button_is_disabled_without_children(): void {
        $buttons = $this->buttons(false);

        $this->assertTrue($buttons['rules']->hasAttribute('disabled'));
        $this->assertSame(
            get_string('central_rule_nochildren', 'local_dimensions'),
            $buttons['rules']->getAttribute('title')
        );
        foreach ($buttons as $action => $button) {
            if ($action !== 'rules') {
                $this->assertFalse($button->hasAttribute('disabled'), $action);
            }
        }
    }

    /**
     * With child competencies every button is enabled: the control for the test above.
     *
     * @return void
     */
    public function test_rule_button_is_enabled_with_children(): void {
        $buttons = $this->buttons(true);

        $this->assertArrayHasKey('rules', $buttons);
        foreach ($buttons as $action => $button) {
            $this->assertFalse($button->hasAttribute('disabled'), $action);
            $this->assertFalse($button->hasAttribute('title'), $action);
        }
    }

    /**
     * Render the footer for a manager and return its buttons by action.
     *
     * @param bool $canrule Whether the selected competency has child competencies.
     * @return array Action name => the button element.
     */
    private function buttons(bool $canrule): array {
        global $OUTPUT;

        $html = $OUTPUT->render_from_template('local_dimensions/central/structure_footer_actions', [
            'canmanage' => true,
            'canrule' => $canrule,
        ]);
        $doc = new \DOMDocument();
        $doc->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $buttons = [];
        foreach ((new \DOMXPath($doc))->query('//button[@data-action]') as $button) {
            $buttons[$button->getAttribute('data-action')] = $button;
        }
        $this->assertCount(7, $buttons);

        return $buttons;
    }
}
