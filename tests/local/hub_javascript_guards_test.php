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

namespace local_dimensions\local;

/**
 * Pins the Competency hub's JavaScript guards that no PHP code path can exercise.
 *
 * The plugin has no JavaScript test runner, so each guard is read out of its amd/src/central module,
 * the way hub_plain_names_test pins the HTML sinks: the function is extracted, comments are dropped,
 * and the guard must sit where it takes effect (after the await it protects, before the write it
 * gates). Most of them answer one question: when a response arrives after the state that asked for
 * it was replaced, is the answer dropped rather than written into the new state?
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class hub_javascript_guards_test extends \basic_testcase {
    /**
     * Closing the Courses and activities modal reports the server's linked-course count.
     *
     * The cards on screen are only the pages Load more fetched, so counting them understates any
     * competency linked to more courses than one page holds.
     *
     * @return void
     */
    public function test_links_modal_reports_the_server_count_on_close(): void {
        $source = $this->source('competency_links');
        $this->assertSame(
            1,
            preg_match('/on\(ModalEvents\.hidden, \(\) => \{\n(.*?)\n    \}\);/s', $source, $match),
            'The hidden handler of the links modal was not found.'
        );
        $handler = $match[1];
        $this->assertMatchesRegularExpression(
            '/opts\.onClose\(\s*state\.totalknown\s*\?\s*state\.total\s*:\s*null\s*\)/',
            $handler
        );
        $this->assertStringNotContainsString('children.length', $handler);

        // The count is known only once a page has answered, and a reload forgets it again.
        $load = $this->body($source, 'loadCourses');
        $this->assert_in_order($load, ['state.total = response.total;', 'state.totalknown = true;']);
        $this->assertStringContainsString('state.totalknown = false;', $this->body($source, 'reloadCourses'));
        $this->assertMatchesRegularExpression('/\n        totalknown: false,\n/', $this->body($source, 'open'));
    }

    /**
     * A tree browser page that answers after a mode, query or framework switch is dropped.
     *
     * @return void
     */
    public function test_tree_browser_drops_a_page_for_a_reset_list(): void {
        $source = $this->source('competency_tree_browser');
        $guard = '/if \(generation !== state\.generation\) \{\s*return;\s*\}/';

        $this->assertMatchesRegularExpression('/state\.generation \+= 1;/', $this->body($source, 'resetList'));
        $this->assertStringContainsString('state.generation = 0;', $this->body($source, 'initBrowser'));

        $top = $this->body($source, 'loadTopPage');
        $this->assert_in_order($top, ['const generation = state.generation;', 'await browse(']);
        $this->assertMatchesRegularExpression($guard, $this->between($top, 'await browse(', 'appendNodes('));
        // A stale page must not clear the loading flag of the new list's first page.
        $this->assertMatchesRegularExpression(
            '/if \(generation === state\.generation\) \{\s*state\.loading = false;/',
            substr($top, (int) strpos($top, 'finally {'))
        );

        $children = $this->body($source, 'loadChildren');
        $this->assert_in_order($children, ['const generation = state.generation;', 'await browse(']);
        $this->assertMatchesRegularExpression($guard, $this->between($children, 'await browse(', 'appendNodes('));

        // Without it the stale call writes the empty message into the list the later switch is filling.
        $apply = $this->body($source, 'applyMode');
        $this->assert_in_order($apply, ['resetList(state);', 'const generation = state.generation;', 'await loadTopPage(state);']);
        $this->assertMatchesRegularExpression($guard, $this->between($apply, 'await loadTopPage(state);', 'state.emptylabel'));
    }

    /**
     * Every write of an enrolment status names the method its answer was fetched for.
     *
     * The method segment stays live while a call is in flight, so reading state.method after the
     * await writes a cohort answer onto the self enrolment status, or the reverse.
     *
     * @return void
     */
    public function test_enrol_status_writes_name_their_method(): void {
        $source = $this->source('enrol_methods');
        $this->assertMatchesRegularExpression('/\nconst setRowStatus = \(row, method, status\) => \{/', $source);
        $this->assertStringNotContainsString('state.', $this->body($source, 'setRowStatus'));

        $mark = $this->body($source, 'markProcessing');
        $this->assertStringContainsString("setRowStatus(row, method, 'processing');", $mark);
        $this->assertMatchesRegularExpression('/if \(method === state\.method\) \{\s*state\.pending\.add\(courseid\);/', $mark);

        $toggle = $this->body($source, 'onToggleStatus');
        $this->assert_in_order($toggle, ['const method = state.method;', 'await Ajax.call(']);
        $this->assertStringNotContainsString('state.method', $this->between($toggle, 'await Ajax.call(', 'addToast('));
    }

    /**
     * A queue poll answered after a method switch or a reload is dropped.
     *
     * @return void
     */
    public function test_enrol_poll_drops_a_stale_answer(): void {
        $poll = $this->body($this->source('enrol_methods'), 'poll');
        $this->assert_in_order($poll, ['const token = state.loadtoken;', 'const method = state.method;', 'await Ajax.call(']);
        $this->assertStringContainsString('method: method,', $this->between($poll, 'await Ajax.call(', '}])[0];'));
        $this->assertMatchesRegularExpression(
            '/if \(token !== state\.loadtoken \|\| method !== state\.method\) \{\s*return;\s*\}/',
            $this->between($poll, '}])[0];', 'state.pending.delete(')
        );
        $this->assertStringContainsString('setRowStatus(row, method, status);', $poll);
    }

    /**
     * A competency or course page, or an action answer, that arrives after a reload leaves the new tree alone.
     *
     * @return void
     */
    public function test_enrol_answers_after_a_reload_leave_the_new_tree_alone(): void {
        $source = $this->source('enrol_methods');
        $stale = '/if \(token !== state\.loadtoken\) \{\s*return;\s*\}/';

        $this->assert_in_order($this->body($source, 'reload'), ['state.loadtoken += 1;', 'textContent = \'\';']);
        $init = $this->body($source, 'init');
        $this->assert_in_order($init, ['state.loadtoken += 1;', 'const token = state.loadtoken;', 'await Promise.all(']);
        $this->assertMatchesRegularExpression($stale, $this->between($init, '} catch (e) {', 'error.hidden = false;'));
        $this->assertMatchesRegularExpression($stale, $this->between($init, 'throw e;', 'error.hidden = true;'));
        $this->assertMatchesRegularExpression('/\n        loadtoken: 0,\n/', $this->body($source, 'mount'));

        $competencies = $this->body($source, 'loadCompetencies');
        $this->assert_in_order($competencies, ['const token = state.loadtoken;', 'await Ajax.call(']);
        $this->assertMatchesRegularExpression($stale, $this->between($competencies, '}])[0];', 'const tree ='));
        $this->assertMatchesRegularExpression($stale, $this->between($competencies, 'renderGroupHtml(', 'appendNodeContents('));
        $this->assertMatchesRegularExpression(
            '/if \(token === state\.loadtoken\) \{\s*state\.root\.querySelector\(SELECTORS\.viscount\)/',
            $competencies
        );

        $courses = $this->body($source, 'loadCourses');
        $this->assert_in_order($courses, ['const token = state.loadtoken;', 'await Ajax.call(']);
        $this->assertMatchesRegularExpression($stale, $this->between($courses, '}])[0];', '[data-group='));
        $this->assertMatchesRegularExpression($stale, $this->between($courses, 'makeRow(', 'tbody.appendChild('));

        $queue = $this->body($source, 'queueAction');
        $this->assert_in_order($queue, ['const token = state.loadtoken;', 'const method = state.method;', 'await Ajax.call(']);
        $this->assertMatchesRegularExpression(
            '/if \(token === state\.loadtoken\) \{\s*data\.results\.forEach/',
            $this->between($queue, '}])[0];', 'markProcessing(')
        );
        $this->assertStringContainsString('markProcessing(state, Number(result.courseid), method);', $queue);

        $this->assertMatchesRegularExpression(
            '/if \(token === state\.loadtoken\) \{/',
            $this->between($this->body($source, 'onToggleStatus'), '}])[0];', 'paintRow(')
        );
    }

    /**
     * Only a transport failure is routed to the connection-lost toast.
     *
     * core/ajax rejects a dropped connection with jQuery's bare errorThrown, a string. Everything
     * else is an object that must reach the exception dialogue, including a script's own TypeError
     * and core/ajax's Error('missing response'), neither of which carries an errorcode.
     *
     * @return void
     */
    public function test_only_a_transport_failure_counts_as_a_network_error(): void {
        $check = $this->body($this->source('errors'), 'isNetworkError');
        $this->assertMatchesRegularExpression('/return typeof error === \'string\' \|\| !error;\s*$/', $check);
        $this->assertStringNotContainsString('errorcode', $check);
        $this->assertStringNotContainsString("typeof error === 'object'", $check);
    }

    /**
     * A pane drag the browser cancels ends the drag, as a pointerup would.
     *
     * A touch the browser turns into a pan fires pointercancel and never pointerup; a drag left open
     * keeps resizing on any later hover over the divider.
     *
     * @return void
     */
    public function test_a_cancelled_pane_drag_ends(): void {
        $source = $this->source('pane_resizer');
        foreach (['pointercancel', 'lostpointercapture'] as $event) {
            $this->assertSame(
                1,
                preg_match("/resizer\.addEventListener\('{$event}', (\w+)\);/", $source, $match),
                "No {$event} listener on the divider."
            );
            $handler = $this->body($source, $match[1], '    ');
            $this->assertStringContainsString("body.classList.remove('resizing');", $handler, $event);
            // The width the divider last showed survives a reload; a cancelled event's coordinates are not a drop point.
            $this->assertStringContainsString('persist(lastwidth);', $handler, $event);
            $this->assertStringNotContainsString('clientX', $handler, $event);
        }
        $this->assertStringContainsString('lastwidth = applyWidth(startwidth + event.clientX - startx);', $source);
    }

    /**
     * The points rule refuses what core_competency's own validation refuses, before the save.
     *
     * competency_rule_points::validate_config() rejects a fractional or negative value anywhere, so
     * a clamped sum alone lets such a config through to a server exception.
     *
     * @return void
     */
    public function test_points_rule_refuses_fractional_and_negative_points(): void {
        $read = $this->body($this->source('rule_config'), 'readPointsConfig');
        $this->assertStringContainsString('Number.isInteger(requiredpoints)', $read);
        $this->assertMatchesRegularExpression(
            '/competencies\.every\(\(comp\) => Number\.isInteger\(comp\.points\) && comp\.points >= 0\)/',
            $read
        );
        $this->assertMatchesRegularExpression('/if \(!wholepoints \|\| requiredpoints < 1 \|\| total < requiredpoints\)/', $read);
        $this->assertStringNotContainsString('Math.max(', $read);
    }

    /**
     * The hub's flash reads its colour from the plugin's warning tint, not a literal.
     *
     * A literal is the light theme's yellow on every page, dark ones included; the token follows the
     * theme's --bs-* palette like every other colour the plugin paints.
     *
     * @return void
     */
    public function test_flash_colour_comes_from_the_warning_tint_token(): void {
        $flash = $this->body($this->source('flash'), 'flashRow');
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-f]{3,8}\b|\b(rgba?|hsla?)\(/i', $flash);
        $this->assertSame(
            1,
            preg_match("/const (\w+) = style\.getPropertyValue\('(--local-dimensions-[a-z-]+)'\)\.trim\(\);/", $flash, $match),
            'The flash colour is not read from a plugin token.'
        );
        $this->assertSame('--local-dimensions-warning-tint', $match[2]);
        $this->assertMatchesRegularExpression(
            '/\[\{backgroundColor: ' . $match[1] . '\}, \{backgroundColor: \'transparent\'\}\]/',
            $flash
        );

        // The token read must be one the stylesheet declares, or the flash silently never runs.
        $styles = (string) file_get_contents(__DIR__ . '/../../styles.css');
        $this->assertMatchesRegularExpression('/\n    ' . preg_quote($match[2], '/') . ': /', $styles);
    }

    /**
     * The code of a module under amd/src/central, without its comments.
     *
     * @param string $module The module name, without the extension.
     * @return string
     */
    private function source(string $module): string {
        $path = __DIR__ . '/../../amd/src/central/' . $module . '.js';
        $this->assertFileExists($path);
        return $this->code((string) file_get_contents($path));
    }

    /**
     * The body of a const-declared arrow function, from the line after its opening brace to its closing one.
     *
     * The modules close a function with a brace at the declaration's own indent followed by a
     * semicolon, so the first such line after the declaration ends it.
     *
     * @param string $source The module source.
     * @param string $name The function name.
     * @param string $indent The declaration's indent (empty for a top-level function).
     * @return string
     */
    private function body(string $source, string $name, string $indent = ''): string {
        $pattern = '/\n' . $indent . '(?:export )?const ' . preg_quote($name, '/')
            . ' = (?:async)?\(?[^\n]*?\)? => \{\n(.*?)\n' . $indent . '\};/s';
        $this->assertSame(1, preg_match($pattern, $source, $match), "Function {$name} was not found.");
        return $match[1];
    }

    /**
     * The code without its comments, so an assertion reads what runs rather than what is said about it.
     *
     * @param string $js JavaScript source.
     * @return string
     */
    private function code(string $js): string {
        $js = (string) preg_replace('#/\*.*?\*/#s', '', $js);
        return (string) preg_replace('#(^|[^:])//[^\n]*#m', '$1', $js);
    }

    /**
     * The text between the first occurrence of one marker and the next occurrence of another after it.
     *
     * @param string $text The text to search.
     * @param string $from The opening marker.
     * @param string $to The closing marker.
     * @return string
     */
    private function between(string $text, string $from, string $to): string {
        $start = strpos($text, $from);
        $this->assertNotFalse($start, "Marker '{$from}' was not found.");
        $end = strpos($text, $to, $start + strlen($from));
        $this->assertNotFalse($end, "Marker '{$to}' was not found after '{$from}'.");
        return substr($text, $start + strlen($from), $end - $start - strlen($from));
    }

    /**
     * Assert that the fragments occur in the text, each after the previous one.
     *
     * @param string $text The text to search.
     * @param array $fragments Literal fragments, in the order they must appear.
     * @return void
     */
    private function assert_in_order(string $text, array $fragments): void {
        $offset = 0;
        foreach ($fragments as $fragment) {
            $position = strpos($text, $fragment, $offset);
            $this->assertNotFalse($position, "'{$fragment}' was not found in order.");
            $offset = $position + strlen($fragment);
        }
    }
}
