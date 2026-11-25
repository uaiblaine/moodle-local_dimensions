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

namespace local_dimensions\output;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Pins the plan overview's actions and address-opened competency in the JavaScript no PHP test runs.
 *
 * Read the way learner_js_source_test reads amd/src: each assertion names the shape the code must
 * keep and fails when it is lost. The review actions call core's own web services and add none of
 * the plugin's; the open-on-load runs once, under a pending token, the way a press would.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class plan_actions_js_source_test extends \basic_testcase {
    /**
     * Read one AMD source of the plugin.
     *
     * @param string $module The module name under amd/src.
     * @return string The source.
     */
    private function js_source(string $module): string {
        global $CFG;
        $path = $CFG->dirroot . '/local/dimensions/amd/src/' . $module . '.js';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /**
     * The code without its comments, so an assertion reads what runs rather than what is said about it.
     *
     * The same reduction as learner_js_source_test::code().
     *
     * @param string $js JavaScript source.
     * @return string
     */
    private function code(string $js): string {
        $js = (string) preg_replace('#/\*.*?\*/#s', '', $js);
        return (string) preg_replace('#(^|[^:])//[^\n]*#m', '$1', $js);
    }

    /**
     * The body of a named function declaration or arrow constant, to the next declaration at its level.
     *
     * @param string $source The code.
     * @param string $name The function's name.
     * @param string $indent The declarations' indentation: none in an ES module, eight spaces inside define().
     * @return string
     */
    private function body(string $source, string $name, string $indent = ''): string {
        $found = preg_match(
            '/(?:function ' . $name . '\(|const ' . $name . ' = )(.*?)(?=\n' . $indent . '(?:function |const |export |let )|\z)/s',
            $source,
            $match
        );
        $this->assertSame(1, $found, "No {$name} in the source.");
        return $match[1];
    }

    /**
     * The two review actions call core's services with the plan id, and the plugin declares none of its own.
     *
     * @return void
     */
    public function test_the_actions_call_cores_review_services(): void {
        global $CFG;

        $code = $this->code($this->js_source('plan_actions'));
        $this->assertStringContainsString("'core_competency_plan_request_review'", $code);
        $this->assertStringContainsString("'core_competency_plan_cancel_review_request'", $code);
        $this->assertMatchesRegularExpression('/args:\s*\{id:\s*planid\}/', $code);

        $functions = [];
        include($CFG->dirroot . '/local/dimensions/db/services.php');
        foreach (array_keys($functions) as $name) {
            // The word between underscores: "preview_import_templates" is not a review service.
            $this->assertDoesNotMatchRegularExpression(
                '/(^|_)review(_|$)/',
                $name,
                'The plugin adds no review service of its own.'
            );
        }
    }

    /**
     * A press is guarded against a second one and held by a pending token until the answer.
     *
     * @return void
     */
    public function test_a_press_is_guarded_and_pending(): void {
        $code = $this->code($this->js_source('plan_actions'));
        $press = $this->body($code, 'press');

        $this->assertMatchesRegularExpression('/^\s*\(?[^)]*\)?\s*=>\s*\{\s*if \(busy\)\s*\{\s*return;/s', $press);
        $this->assertStringContainsString('busy = true;', $press);
        $this->assertStringContainsString("import Pending from 'core/pending';", $code);
        $this->assertStringContainsString('new Pending(', $press);
        $this->assertLessThan(
            strpos($press, 'Ajax.call('),
            strpos($press, 'busy = true;'),
            'The guard is set before the request leaves.'
        );
    }

    /**
     * Success reloads the page; a failure frees the button and shows core's exception.
     *
     * @return void
     */
    public function test_success_reloads_and_failure_frees_the_button(): void {
        $press = $this->body($this->code($this->js_source('plan_actions')), 'press');

        $then = strpos($press, '.then(');
        $catch = strpos($press, '.catch(');
        $this->assertNotFalse($then);
        $this->assertNotFalse($catch);
        $this->assertStringContainsString('window.location.reload()', substr($press, $then, $catch - $then));
        $failure = substr($press, $catch);
        $this->assertStringContainsString('busy = false;', $failure);
        $this->assertStringContainsString('Notification.exception(', $failure);
        $this->assertStringContainsString('pending.resolve()', $failure);
    }

    /**
     * The actions are shown only once armed: the module removes the hidden attribute it is drawn with.
     *
     * @return void
     */
    public function test_the_actions_show_only_once_armed(): void {
        $init = $this->body($this->code($this->js_source('plan_actions')), 'init');

        $this->assertStringContainsString('button.hidden = false;', $init);
        $this->assertLessThan(
            strpos($init, 'button.hidden = false;'),
            strpos($init, "addEventListener('click'"),
            'A button is shown only after its handler is attached.'
        );
    }

    /**
     * The accordion opens the address's competency once, after the rows are armed, under a pending token.
     *
     * @return void
     */
    public function test_the_named_competency_opens_once_after_arming(): void {
        $code = $this->code($this->js_source('accordion'));
        $init = $this->body($code, 'init', '        ');
        $open = $this->body($code, 'openRequested', '        ');

        $this->assertLessThan(
            strpos($init, 'openRequested(planId);'),
            strpos($init, "classList.add('local-dimensions-filter-initialized')"),
            'The competency opens after the rows are armed and filtered.'
        );
        $this->assertSame(1, substr_count($code, 'openRequested(planId);'));
        $this->assertStringContainsString('new Pending(', $open);
        $this->assertStringContainsString('revealItem(item);', $open);
        $this->assertStringContainsString('openDetailModal(item, planId)', $open);
        $this->assertStringContainsString('toggle.click();', $open);
        $this->assertStringContainsString('openItem(item);', $open);
    }

    /**
     * A row the filters hide is shown for this view only: the address path saves no view preference.
     *
     * A link must not change the reader's stored view, so the reveal sets the controls itself instead of
     * pressing the line's, whose handlers save. A press by the reader still saves.
     *
     * @return void
     */
    public function test_a_hidden_row_is_shown_without_saving_the_view(): void {
        $code = $this->code($this->js_source('accordion'));
        $reveal = $this->body($code, 'revealItem', '        ');

        $this->assertStringContainsString('setScope(allCell);', $reveal);
        $this->assertStringContainsString('setFavouriteFilter(false);', $reveal);
        $this->assertStringNotContainsString('allCell.click()', $reveal);
        $this->assertStringNotContainsString('star.click()', $reveal);
        $this->assertStringNotContainsString('LearnerPrefs', $reveal);
        $this->assertStringNotContainsString('.save(', $reveal);

        $tabs = $this->body($code, 'initFilterTabs', '        ');
        $this->assertStringContainsString('LearnerPrefs.save({filter: cell.dataset.filter});', $tabs);
        $this->assertStringContainsString('setScope(cell);', $tabs);
    }

    /**
     * The committed builds are these sources.
     *
     * @return void
     */
    public function test_the_builds_are_current(): void {
        global $CFG;

        foreach (['plan_actions', 'accordion'] as $module) {
            $map = $CFG->dirroot . '/local/dimensions/amd/build/' . $module . '.min.js.map';
            $this->assertFileExists($map);
            $decoded = json_decode((string) file_get_contents($map), true);
            $this->assertSame(
                $this->js_source($module),
                (string) ($decoded['sourcesContent'][0] ?? ''),
                "amd/build/{$module} is not this source: rebuild it with grunt amd and commit the build."
            );
        }
    }
}
