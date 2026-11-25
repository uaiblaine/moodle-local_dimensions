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
 * Pins the Journey view's JavaScript, which no PHP test runs.
 *
 * Read the way plan_actions_js_source_test reads amd/src: the latest request wins after every await, a
 * pending token spans each request, every record is written as text, an address is http(s) only, and the
 * accordion offers the view only once the module is armed.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class plan_journey_js_source_test extends \basic_testcase {
    /**
     * Read one AMD source of the plugin.
     *
     * @param string $module The module name under amd/src.
     * @return string The source.
     */
    private function js_source(string $module): string {
        $path = dirname(__DIR__, 2) . '/amd/src/' . $module . '.js';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /**
     * The code without its comments, the same reduction as plan_actions_js_source_test::code().
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
        // Inside define() only a declaration at the module's own indentation counts: a same-named local elsewhere
        // (the filter tabs' applyFilter) is deeper and must not be matched first.
        $head = '\n' . $indent . 'function ' . $name . '\(';
        if ($indent === '') {
            $head = '(?:function ' . $name . '\(|const ' . $name . ' = )';
        }
        $found = preg_match(
            '/' . $head . '(.*?)(?=\n' . $indent . '(?:function |const |export |let )|\z)/s',
            $source,
            $match
        );
        $this->assertSame(1, $found, "No {$name} in the source.");
        return $match[1];
    }

    /**
     * A request takes a ticket, and its answer is dropped after each await unless it is still the latest.
     *
     * @return void
     */
    public function test_the_latest_request_wins(): void {
        $code = $this->code($this->js_source('plan_journey'));
        $load = $this->body($code, 'load');

        $this->assertStringContainsString('const ticket = ++seq;', $load);
        $this->assertStringContainsString("methodname: 'local_dimensions_get_plan_journey'", $load);
        $awaits = substr_count($load, 'await ');
        $this->assertSame(2, $awaits);
        $this->assertSame($awaits, substr_count($load, 'if (ticket !== seq) {'), 'A check follows every await.');
        $this->assertLessThan(
            strpos($load, 'await paint('),
            strpos($load, 'if (ticket !== seq) {'),
            'The answer is checked before it is painted.'
        );
        $paint = $this->body($code, 'paint');
        $this->assertSame(1, substr_count($paint, 'await '));
        $this->assertLessThan(
            strpos($paint, 'page.entries.forEach(drawEntry);'),
            strpos($paint, 'if (ticket !== seq) {'),
            'Nothing is drawn before the words arrive and the request is checked.'
        );
    }

    /**
     * A pending token spans every request, released in a finally.
     *
     * @return void
     */
    public function test_every_request_is_pending(): void {
        $load = $this->body($this->code($this->js_source('plan_journey')), 'load');

        $this->assertStringContainsString("new Pending('local_dimensions/plan_journey:load')", $load);
        $finally = strpos($load, 'finally {');
        $this->assertNotFalse($finally);
        $this->assertStringContainsString('pending.resolve();', substr($load, $finally));
    }

    /**
     * Records are written as text, and an address is linked only when it parses as http(s) without a base.
     *
     * @return void
     */
    public function test_records_are_text_and_addresses_web_only(): void {
        $code = $this->code($this->js_source('plan_journey'));

        $this->assertDoesNotMatchRegularExpression('/innerHTML|outerHTML|insertAdjacentHTML|\.html\(/', $code);
        $this->assertStringContainsString('node.textContent = text;', $this->body($code, 'element'));
        $href = $this->body($code, 'webHref');
        $this->assertStringContainsString('new URL(value)', $href);
        $this->assertStringContainsString("url.protocol === 'http:' || url.protocol === 'https:'", $href);
        $this->assertStringContainsString('webHref(entry.url)', $this->body($code, 'drawEntry'));
        $this->assertStringContainsString("link.rel = 'noopener noreferrer';", $this->body($code, 'drawEntry'));
    }

    /**
     * The accordion shows the Journey's buttons only once the module is armed, after their handler is attached.
     *
     * @return void
     */
    public function test_the_view_is_offered_only_once_armed(): void {
        $code = $this->code($this->js_source('accordion'));
        $toggle = $this->body($code, 'initViewToggle', '        ');

        $this->assertStringContainsString("'local_dimensions/plan_journey'", $code);
        $this->assertStringContainsString('initViewToggle(planId, armJourney(summary, planId));', $code);
        $shown = strpos($toggle, 'button.hidden = false;');
        $this->assertNotFalse($shown);
        // A missing line is false, which compares as less than any position: each must be found first.
        $listener = strpos($toggle, "addEventListener('click'");
        $guard = strpos($toggle, 'if (journeyArmed) {');
        $this->assertNotFalse($listener, 'The press is handled before the button is shown.');
        $this->assertNotFalse($guard, 'The button is shown only under the armed guard.');
        $this->assertLessThan($shown, $listener);
        $this->assertLessThan($shown, $guard);
        $this->assertStringContainsString('Journey.refilter();', $this->body($code, 'applyFilter', '        '));
        $this->assertStringContainsString('openDetailModal(item, planId);', $this->body($code, 'armJourney', '        '));
    }

    /**
     * The list is hidden only under a class written after the Journey armed and showed its frame.
     *
     * A stored Journey the module could not arm (a script error after jsenabled) must leave the list on the page.
     *
     * @return void
     */
    public function test_the_list_is_hidden_only_after_the_journey_armed(): void {
        $apply = $this->body($this->code($this->js_source('accordion')), 'applyViewMode', '        ');

        $shown = strpos($apply, 'journeyOn = Journey.show();');
        $this->assertNotFalse($shown, 'The class follows what the module answered when it was shown.');
        $toggle = strpos($apply, "summary.classList.toggle('local-dimensions-journey-on', journeyOn);");
        $this->assertNotFalse($toggle);
        $this->assertLessThan($toggle, $shown, 'The class is written after the Journey is shown.');

        $show = $this->body($this->code($this->js_source('plan_journey')), 'show');
        $unhidden = strpos($show, 'frame.region.hidden = false;');
        $answered = strpos($show, 'return true;');
        $this->assertNotFalse($unhidden, 'The frame is shown.');
        $this->assertNotFalse($answered, 'An armed module answers true.');
        $this->assertLessThan($answered, $unhidden);
        $this->assertStringContainsString('return false;', $show, 'A module without a frame answers false.');

        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/styles.css');
        $this->assertStringContainsString(
            'body.jsenabled .local-dimensions-plan-summary.local-dimensions-journey-on > .local-dimensions-competency-accordion',
            $css
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\[data-viewmode="journey"\][^{]*competency-accordion\s*\{/',
            $css,
            'The stored layout alone never hides the list.'
        );
    }

    /**
     * The committed build is this source.
     *
     * @return void
     */
    public function test_the_build_is_current(): void {
        $map = dirname(__DIR__, 2) . '/amd/build/plan_journey.min.js.map';
        $this->assertFileExists($map);
        $decoded = json_decode((string) file_get_contents($map), true);
        $this->assertSame(
            $this->js_source('plan_journey'),
            (string) ($decoded['sourcesContent'][0] ?? ''),
            'amd/build/plan_journey is not this source: rebuild it with grunt amd and commit the build.'
        );
    }
}
