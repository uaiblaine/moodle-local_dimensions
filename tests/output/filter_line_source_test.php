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
 * Tests for the filter line's client, by its source.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\output;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Pins the rules of amd/src/filter_line.js, the filter line of both learner views.
 *
 * moodle-plugin-ci has no JavaScript test runner, so these are assertions about spelling, each scoped
 * to the function it is about, as learner_js_source_test's are; the Behat feature is what runs the
 * module. The rules are the fleet contract's (moodle-dev/docs/filter-line/CONTRACT.md, sections 2 to
 * 4 and 8): the line measured on its own box, the steps tried in order with every scope cell drawn and
 * no transition, the search growing over the line and closing back to its opener without opening
 * again, the suggestions matched accent- and case-insensitively and applied through the control they
 * name, every floating layer placed under the line's measured bottom, the bottom sheet, the rows that
 * scroll sideways, the scope's arrow keys and the slash key; and both views arming it.
 */
#[CoversNothing]
final class filter_line_source_test extends \basic_testcase {
    /** @var string The module under test. */
    private const MODULE = 'filter_line';

    /**
     * Absolute path to the plugin root.
     *
     * @return string
     */
    private function plugin_root(): string {
        return dirname(__DIR__, 2);
    }

    /**
     * One module's source.
     *
     * @param string $module The module's name.
     * @return string
     */
    private function source(string $module = self::MODULE): string {
        $path = $this->plugin_root() . '/amd/src/' . $module . '.js';
        $this->assertFileExists($path);
        return (string) file_get_contents($path);
    }

    /**
     * A module's source without its comments: what runs.
     *
     * @param string $module The module's name.
     * @return string
     */
    private function code(string $module = self::MODULE): string {
        $code = (string) preg_replace('~/\*.*?\*/~s', '', $this->source($module));
        return (string) preg_replace('~^\s*//.*$~m', '', $code);
    }

    /**
     * The body of one top-level arrow function of the module.
     *
     * @param string $name The function's name.
     * @return string The body, without the signature or the closing brace.
     */
    private function body(string $name): string {
        $pattern = '~\n(?:export )?const ' . preg_quote($name, '~') . ' = [^\n]*\{\n(.*?)\n\};~s';
        $found = preg_match($pattern, $this->source(), $matches);
        $this->assertSame(1, $found, "No top-level function named {$name}.");
        $this->assertNotSame('', trim($matches[1]), "The body of {$name} is empty.");
        return $matches[1];
    }

    /**
     * The body of a named function declaration of an AMD module, up to its closing brace at the same indent.
     *
     * @param string $module The module's name.
     * @param string $name The function's name.
     * @return string
     */
    private function declared(string $module, string $name): string {
        $pattern = '/^( *)function ' . preg_quote($name, '/') . '\([^)]*\) \{\n(.*?)\n\1\}$/ms';
        $this->assertSame(1, preg_match($pattern, $this->source($module), $match), "function {$name} not found in {$module}");
        return $match[2];
    }

    /**
     * The line is measured on its own box, never the viewport's, and refolds after the frame.
     *
     * A drawer opening changes the line's width and not the window's, so a viewport query or a
     * window resize listener would leave the line at a step that no longer fits it.
     *
     * Changes that must make it fail: the observer on the window, the document or the body; the
     * width read off innerWidth; a resize listener on the window.
     *
     * @return void
     */
    public function test_the_line_measures_its_own_width(): void {
        $arm = $this->body('arm');
        $this->assertMatchesRegularExpression(
            '~new ResizeObserver\(\(entries\) => \{.*?\}\)\.observe\(line\);~s',
            $arm,
            'The observer watches the line itself.'
        );
        $this->assertStringContainsString('const width = Math.round(entries[0].contentRect.width);', $arm);
        $this->assertStringContainsString('if (width !== state.width) {', $arm, 'A height change alone refolds nothing.');
        $code = $this->code();
        $this->assertStringNotContainsString('innerWidth', $code);
        $this->assertStringNotContainsString("addEventListener('resize'", $code);
        $this->assertStringNotContainsString('.observe(document', $code);
        $this->assertStringContainsString('window.requestAnimationFrame(', $this->body('refit'));
        $this->assertStringContainsString("new Pending('local_dimensions/filter_line:fit')", $this->body('refit'));
    }

    /**
     * The steps are tried in order, measured with every cell drawn and no transition, and written as classes.
     *
     * Changes that must make it fail: the measuring class not set or not taken off; the absent cells
     * not drawn for the measure or not hidden again; a step tried out of order; the last step not
     * taken whatever it measures; the step written anywhere but as a class on the line.
     *
     * @return void
     */
    public function test_the_line_folds_at_the_first_step_that_fits(): void {
        $source = $this->source();
        $this->assertStringContainsString("const STEPS = ['s1', 's2', 's3', 's4'];", $source);
        $this->assertStringContainsString(
            "const TRIES = [['s1', false], ['s2', false], ['s3', true], ['s4', false], ['s4', true]];",
            $source
        );
        foreach (['s1', 's2', 's3', 's4'] as $step) {
            $this->assertStringContainsString("    {$step}: 'local-dimensions-line--{$step}',", $source);
        }
        $this->assertStringContainsString("measuring: 'local-dimensions-line--measuring',", $source);
        $this->assertStringContainsString("folded: 'local-dimensions-line--folded',", $source);
        $fit = $this->body('fit');
        $this->assertMatchesRegularExpression(
            '~line\.classList\.add\(CLASSES\.measuring\);.*?TRIES\.some\(.*?line\.classList\.remove\(CLASSES\.measuring\);~s',
            $fit,
            'Measured under the measuring class, taken off afterwards.'
        );
        $this->assertMatchesRegularExpression(
            "~querySelectorAll\(SELECTORS\.cell \+ '\[hidden\]'\).*?cell\.hidden = false;.*?TRIES\.some.*?cell\.hidden = true;~s",
            $fit,
            'Every cell of the scope is drawn for the measure and hidden again after it.'
        );
        $this->assertStringContainsString('return index === TRIES.length - 1 || !overflows();', $fit);
        $set = $this->body('setStep');
        $this->assertStringContainsString(
            'STEPS.forEach((name) => line.classList.toggle(STEP_CLASSES[name], name === step));',
            $set
        );
        $this->assertStringContainsString('placeScope(step === TWO_LINES);', $set);
        $scope = $this->body('placeScope');
        $this->assertStringContainsString('line.insertBefore(scope, parts.listbox);', $scope);
        $this->assertStringContainsString('quietly(focused);', $scope);
        $over = $this->body('overflows');
        $this->assertStringContainsString('rect.right > right || rect.left < left', $over);
        $this->assertStringContainsString('if (child === parts.listbox) {', $over, 'A layer never counts.');
    }

    /**
     * The search grows over the line at the third and fourth steps, and closes back to its opener quietly.
     *
     * Changes that must make it fail: the morph at the first two steps; the close giving the focus to
     * nothing or opening the search again; the field's focus listener without its quiet guard or
     * opening on a focus the reader did not give; Escape left to the browser while the search is open.
     *
     * @return void
     */
    public function test_the_search_grows_over_the_line_and_closes_back_quietly(): void {
        $this->assertStringContainsString("const MORPH_STEPS = ['s3', 's4'];", $this->source());
        $this->assertStringContainsString("searching: 'local-dimensions-line--searching',", $this->source());
        $open = $this->body('openSearch');
        $this->assertMatchesRegularExpression(
            '~if \(morphs\(\) && !state\.searching\) \{\s*state\.searching = true;\s*state\.from = from \|\| field;'
                . '\s*line\.classList\.add\(CLASSES\.searching\);~',
            $open
        );
        $close = $this->body('closeSearch');
        $this->assertStringContainsString('line.classList.remove(CLASSES.searching);', $close);
        $this->assertStringContainsString(
            'quietly([from, field, parts.opener].find((control) => shown(control)) || null);',
            $close,
            'The focus goes back to the control that opened it, or the one drawn in its place.'
        );
        $quietly = $this->body('quietly');
        $this->assertMatchesRegularExpression(
            '~state\.quiet = true;\s*try \{\s*element\.focus\(\{preventScroll: true\}\);\s*\} finally \{\s*state\.quiet = false;~',
            $quietly
        );
        $listen = $this->body('listenSearch');
        $this->assertMatchesRegularExpression(
            "~field\.addEventListener\('focusin', \(\) => \{.*?if \(state\.quiet \|\| !intent\) \{\s*return;\s*\}\s*"
                . "openSearch\(field\);~s",
            $listen,
            'A quiet focus, or one the reader did not give, opens nothing.'
        );
        $this->assertStringContainsString('const intent = state.intent || Date.now() - state.tabat < TAB_INTENT;', $listen);
        $this->assertStringContainsString("parts.closer.addEventListener('click', () => closeSearch(true));", $listen);
        $keys = $this->body('onFieldKey');
        $this->assertMatchesRegularExpression(
            "~event\.key === 'Escape' && \(state\.open \|\| state\.searching\)\) \{.*?"
                . "event\.preventDefault\(\);\s*closeSearch\(true\);~s",
            $keys
        );
        $this->assertStringContainsString('closeSearch(true);', $this->body('onLineKey'), 'Escape from the close button too.');
    }

    /**
     * Matching is accent- and case-insensitive, word starts first, then by count; the empty field offers five.
     *
     * Changes that must make it fail: the accents kept; the case kept; the label matched alone and the
     * group never; the count ranked before the word start; the start list not ordered by count; the
     * counts taken with the typed text; a filter in force or one that would leave nothing offered.
     *
     * @return void
     */
    public function test_the_suggestions_match_by_the_normalise_rule_and_rank_word_starts_first(): void {
        $source = $this->source();
        $this->assertStringContainsString(
            "const fold = (text) => String(text).normalize('NFD').replace(/[\\u0300-\\u036f]/g, '').toLowerCase();",
            $source
        );
        $this->assertStringContainsString("export const normalise = (text) => fold(text || '').trim();", $source);
        $this->assertStringContainsString('const MIN_TERM = 1;', $source, 'The views search from the first character.');
        $this->assertStringContainsString('const START_COUNT = 5;', $source);
        $rank = $this->body('rankOf');
        $this->assertStringContainsString('const label = matchOf(normalise(item.label), term);', $rank);
        $this->assertStringContainsString('const group = matchOf(normalise(item.group), term);', $rank);
        $this->assertLessThan(strpos($rank, 'if (group === 0)'), strpos($rank, 'if (label === 0)'), 'The label first.');
        $this->assertStringContainsString("if (at === 0 || !/[a-z0-9]/.test(text.charAt(at - 1))) {", $this->body('matchOf'));
        $suggest = $this->body('suggest');
        $this->assertStringContainsString('const term = normalise(text);', $suggest);
        $this->assertStringContainsString(
            '.sort((a, b) => count(b) - count(a) || a.order - b.order).slice(0, START_COUNT);',
            $suggest,
            'With less than the floor, the highest counts.'
        );
        $this->assertStringContainsString(
            '.sort((a, b) => a.rank - b.rank || count(b.item) - count(a.item) || a.item.order - b.item.order)',
            $suggest,
            'Word starts first, then the count.'
        );
        $filters = $this->body('filters');
        $this->assertStringContainsString("if (label !== '' && count !== 0) {", $filters);
        $this->assertStringContainsString("cell.getAttribute('aria-checked') !== 'true'", $filters);
        $this->assertStringContainsString("star.getAttribute('aria-pressed') !== 'true'", $filters);
        $this->assertStringContainsString("chip.getAttribute('aria-pressed') !== 'true'", $filters);
        // The filters first, the items after them, under the server's headings.
        $render = $this->body('render');
        $this->assertStringContainsString('state.items = offered.concat(items);', $render);
        $this->assertStringContainsString(
            'const words = typed ? listbox.dataset.labelFilters : listbox.dataset.labelStart;',
            $render
        );
        $this->assertStringContainsString('listbox.dataset.labelItems', $render);
        $this->assertStringNotContainsString('get_string', $this->code());
        $this->assertStringNotContainsString('core/str', $this->code());
        // Both views count without the typed text for a suggestion and with it for a panel's chip.
        foreach (['accordion', 'competency_view'] as $module) {
            $count = $this->declared($module, 'countWith');
            $this->assertMatchesRegularExpression("~if \(!keepQuery\) \{\s*criteria\.query = '';~", $count, $module);
            $code = $this->code($module);
            $this->assertStringContainsString('return countWith(control, false);', $code, "{$module}'s suggestions.");
            $this->assertStringContainsString('return countWith(chip, true);', $code, "{$module}'s panel.");
        }
    }

    /**
     * A suggestion applies its filter by pressing the control it names, the text dropped first.
     *
     * Changes that must make it fail: the text dropped after the press; a filter of the line's own;
     * the search closed after a pick; a view not arming the line or not repainting it.
     *
     * @return void
     */
    public function test_a_suggestion_presses_its_control_after_the_text_is_dropped(): void {
        $choose = $this->body('choose');
        $this->assertMatchesRegularExpression(
            "~field\.value = '';\s*paintEraser\(\);\s*hooks\.dropTerm\(\);\s*item\.control\.click\(\);\s*render\(\);~",
            $choose
        );
        $this->assertMatchesRegularExpression(
            "~if \(item\.kind === 'item'\) \{\s*closeSearch\(false\);\s*hooks\.openItem\(item\.element\);~",
            $choose,
            'An item is the page module\'s to open.'
        );
        $code = $this->code();
        $this->assertStringNotContainsString('core/ajax', $code, 'The line makes no request.');
        $this->assertStringNotContainsString('methodname', $code);
        $drops = ['accordion' => 'dropTerm: applyFilter,', 'competency_view' => 'dropTerm: applyCardFilters,'];
        foreach ($drops as $module => $drop) {
            $source = $this->code($module);
            $this->assertStringContainsString("'local_dimensions/filter_line'", $source, "{$module} loads the line.");
            $this->assertStringContainsString('Line.arm(document, {', $source, "{$module} arms the line.");
            $this->assertStringContainsString($drop, $source);
            $this->assertStringContainsString('Line.repaint();', $source, "{$module} repaints the line.");
        }
        $listen = $this->body('listenSearch');
        $this->assertStringContainsString(
            "parts.listbox.addEventListener('mousedown', (event) => event.preventDefault());",
            $listen
        );
        $this->assertStringContainsString(
            "(event.key === 'Enter' || event.key === ' ') && state.active >= 0",
            $this->body('onFieldKey')
        );
        $arm = $this->body('arm');
        foreach (
            [
                "parts.listbox.setAttribute('role', 'listbox');",
                "field.setAttribute('role', 'combobox');",
                "field.setAttribute('aria-autocomplete', 'list');",
                "field.setAttribute('aria-controls', parts.listbox.id);",
            ] as $line
        ) {
            $this->assertStringContainsString($line, $arm);
        }
        $this->assertStringContainsString(
            "field.setAttribute('aria-activedescendant', options[state.active].id);",
            $this->body('activate')
        );
    }

    /**
     * Every floating layer is placed under the line's real bottom, measured; on the two-line step the panel is a sheet.
     *
     * Changes that must make it fail: a fixed offset; the top measured from the line's top; the
     * sheet given a top; the sheet's class not written while the panel is open at the two-line step.
     *
     * @return void
     */
    public function test_every_layer_is_placed_under_the_lines_measured_bottom(): void {
        $this->assertStringContainsString('bottom = Math.max(bottom, rect.bottom);', $this->body('lineBottom'));
        $place = $this->body('place');
        $this->assertStringContainsString("layer.style.top = Math.round(lineBottom() - top + GAP) + 'px';", $place);
        $this->assertStringContainsString('const top = parent.getBoundingClientRect().top + parent.clientTop;', $place);
        $this->assertMatchesRegularExpression(
            "~if \(layer === parts\.panel && state\.step === TWO_LINES\) \{\s*layer\.style\.top = '';~",
            $place,
            'The sheet is bottom-anchored and takes no top.'
        );
        $this->assertStringContainsString('place(listbox);', $this->body('placeList'));
        $fit = $this->body('fit');
        $this->assertStringContainsString('place(parts.panel);', $fit);
        $this->assertStringContainsString('place(parts.menu);', $fit);
        $this->assertStringContainsString(
            'toolbar.classList.toggle(CLASSES.sheet, state.step === TWO_LINES && panelOpen());',
            $this->body('sheet')
        );
        $this->assertStringContainsString("sheet: 'local-dimensions-toolbar--sheet',", $this->source());
        $layers = $this->body('listenLayers');
        $this->assertStringContainsString("observe(layer, {attributes: true, attributeFilter: ['hidden']});", $layers);
        $this->assertStringContainsString('watch(parts.panel, () => place(parts.panel));', $layers);
        $this->assertStringContainsString('watch(parts.menu, () => place(parts.menu));', $layers);
    }

    /**
     * The scope is a radio group: arrow keys walk its drawn cells and choose, one cell in the tab order.
     *
     * Changes that must make it fail: the keys moving the focus without choosing; a hidden cell
     * reached; every cell left in the tab order.
     *
     * @return void
     */
    public function test_the_scope_is_a_radio_group_with_a_roving_focus(): void {
        $keys = $this->body('onScopeKey');
        $this->assertStringContainsString('const steps = {ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1};', $keys);
        $this->assertMatchesRegularExpression('~drawn\[next\]\.focus\(\);\s*drawn\[next\]\.click\(\);~', $keys);
        $this->assertStringContainsString(
            "Array.from(parts.scope.querySelectorAll(SELECTORS.cell)).filter((cell) => !cell.hidden)",
            $this->source(),
            'Only the cells drawn are walked.'
        );
        $this->assertStringContainsString('cell.tabIndex = cell === chosen ? 0 : -1;', $this->body('rove'));
        $this->assertStringContainsString("parts.scope.addEventListener('keydown', onScopeKey);", $this->body('listenRows'));
    }

    /**
     * A half-hidden item of a row that scrolls sideways is revealed when pressed or focused, and a hidden side fades.
     *
     * Changes that must make it fail: the reveal on pointerdown; the reveal missing on focus; the
     * fades not following the scroll; the chips row left out.
     *
     * @return void
     */
    public function test_the_rows_that_scroll_reveal_and_fade(): void {
        $rows = $this->body('listenRows');
        $this->assertStringContainsString('[parts.scope, parts.chips].filter(Boolean).forEach((scroller) => {', $rows);
        $this->assertStringContainsString("scroller.addEventListener('scroll', () => fades(scroller), {passive: true});", $rows);
        foreach (['focusin', 'click'] as $event) {
            $this->assertStringContainsString(
                "scroller.addEventListener('{$event}', (event) => reveal(scroller, itemOf(event.target)));",
                $rows
            );
        }
        $this->assertStringNotContainsString('pointerdown', $rows);
        $this->assertStringContainsString(
            "scroller.scrollBy({left: dx, behavior: still() ? 'auto' : 'smooth'});",
            $this->body('reveal')
        );
        $fades = $this->body('fades');
        $this->assertStringContainsString('scroller.classList.toggle(CLASSES.fadeleft, max > 1 && left > 2);', $fades);
        $this->assertStringContainsString('scroller.classList.toggle(CLASSES.faderight, max > 1 && right > 2);', $fades);
    }

    /**
     * The chips row: a chip per pressed chip of the panel, the text's chip only while the field is folded away,
     * "Clear all" from two.
     *
     * Changes that must make it fail: the text's chip drawn beside a visible field; "Clear all" from one.
     *
     * @return void
     */
    public function test_the_chips_row_follows_the_filters(): void {
        $paint = $this->body('paintChips');
        $this->assertStringContainsString("if (term !== '' && state.folded && !state.searching) {", $paint);
        $this->assertStringContainsString("parts.panel.querySelectorAll(SELECTORS.chip + '[aria-pressed=\"true\"]')", $paint);
        $this->assertStringContainsString('parts.clearall.hidden = chips.length < 2;', $paint);
        $this->assertStringContainsString('parts.chips.hidden = chips.length === 0;', $paint);
        $this->assertStringContainsString("cross.className = 'fa fa-times';", $this->body('chipOf'), 'A chip has a cross.');
    }

    /**
     * The slash key opens the search while the line is on screen and the focus is in no field.
     *
     * Changes that must make it fail: the key taken inside a field; taken while the line is off screen;
     * the field opened directly where it is folded into its icon.
     *
     * @return void
     */
    public function test_the_slash_key_opens_the_search(): void {
        $this->assertStringContainsString(
            "event.key === '/' && !event.ctrlKey && !event.metaKey && !event.altKey && !event.defaultPrevented",
            $this->body('listenLayers')
        );
        $slash = $this->body('onSlash');
        $this->assertStringContainsString("active.closest('input, textarea, select')", $slash);
        $this->assertStringContainsString('if (rect.bottom <= 0 || rect.top >= window.innerHeight || !rect.width) {', $slash);
        $this->assertStringContainsString('openSearch(state.folded && !state.searching ? parts.opener : field);', $slash);
    }

    /**
     * The tracker remembers its scope under its own key, seeded first, and opens nothing it cannot count.
     *
     * Changes that must make it fail: the store not seeded before a save (the plan's keys reset);
     * the tracker writing the plan's key; a zero cell drawn while it is not chosen; the "no results"
     * state never shown; a card whose completion is not known yet hidden by "To do".
     *
     * @return void
     */
    public function test_the_tracker_remembers_its_scope_and_says_when_nothing_matches(): void {
        $tracker = $this->code('competency_view');
        $this->assertMatchesRegularExpression(
            '~LearnerPrefs\.init\(viewstate\);.*?Line\.arm\(document, \{~s',
            $tracker,
            'Seeded before the line can press anything.'
        );
        $tabs = $this->declared('competency_view', 'initCourseTabs');
        $this->assertStringContainsString('LearnerPrefs.save({trackerfilter: cell.dataset.filter});', $tabs);
        $this->assertStringContainsString(
            "cell.hidden = count === 0 && cell.getAttribute('aria-checked') !== 'true';",
            $tabs,
            'A zero cell is not drawn unless it is the chosen one.'
        );
        $this->assertStringContainsString('scope.hidden = false;', $tabs, 'The scope is drawn once it can be counted.');
        $apply = $this->declared('competency_view', 'applyCardFilters');
        $this->assertStringContainsString('noresults.hidden = visible > 0;', $apply);
        $this->assertStringContainsString(
            "if (criteria.filter === 'incomplete' && wrapper.getAttribute('data-completed') === '1') {",
            $this->declared('competency_view', 'passes'),
            'Only a card known to be complete leaves "To do".'
        );
        $this->assertStringContainsString(
            'LearnerPrefs.save({filter: cell.dataset.filter});',
            $this->declared('accordion', 'initFilterTabs'),
            'The plan keeps its own key.'
        );
    }

    /**
     * The stylesheet draws the steps, the morph and the sheet the module writes.
     *
     * Changes that must make it fail: the measured line allowed to wrap; the folded field still drawn;
     * a control slid out left visible (and so in the focus order); the slide not gated on the reader's
     * motion preference; the sheet not fixed; the view toggle left on the two-line step; the toolbar's
     * filter kept while the sheet is open.
     *
     * @return void
     */
    public function test_the_stylesheet_draws_the_steps_morph_and_sheet(): void {
        $css = (string) preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents($this->plugin_root() . '/styles.css'));
        $rule = function (string $selector) use ($css): string {
            $found = preg_match('~(?:^|\})\s*' . preg_quote($selector, '~') . '\s*\{([^}]*)\}~', $css, $matches);
            $this->assertSame(1, $found, "No rule for {$selector}.");
            return $matches[1];
        };
        $this->assertStringContainsString('flex-wrap: nowrap;', $rule('.local-dimensions-line.local-dimensions-line--measuring'));
        $this->assertStringContainsString(
            'display: none;',
            $rule('.local-dimensions-line--folded > .local-dimensions-line-search')
        );
        $this->assertStringContainsString(
            'display: inline-flex;',
            $rule('.local-dimensions-line.local-dimensions-line--folded > .local-dimensions-line-searchopen')
        );
        $slid = $rule(".local-dimensions-line--searching.local-dimensions-line--s3 > .local-dimensions-line-slides,\n"
            . ".local-dimensions-line--searching.local-dimensions-line--s3 > .local-dimensions-line-scope,\n"
            . '.local-dimensions-line--searching.local-dimensions-line--s4 > .local-dimensions-line-slides');
        $this->assertStringContainsString('visibility: hidden;', $slid);
        $this->assertStringContainsString('max-width: 0;', $slid);
        $this->assertMatchesRegularExpression(
            '~@media \(prefers-reduced-motion: no-preference\) \{\s*\.local-dimensions-line\.local-dimensions-line--s3,~',
            $css,
            'The slide is for readers who welcome motion.'
        );
        $this->assertStringContainsString('transition: none;', $css);
        $this->assertStringContainsString('position: fixed;', $rule('.local-dimensions-line--s4 .local-dimensions-filter-panel'));
        $this->assertStringContainsString('display: none;', $rule('.local-dimensions-line--s4 > .local-dimensions-line-view'));
        $this->assertStringContainsString(
            'display: flex;',
            $rule('.local-dimensions-line.local-dimensions-line--s4 .local-dimensions-line-showas')
        );
        $this->assertStringContainsString(
            'backdrop-filter: none;',
            $rule('.local-dimensions-toolbar.local-dimensions-toolbar--sheet')
        );
    }

    /**
     * The committed build of every module the line touches was made from the committed source.
     *
     * Changes that must make it fail: an edit of amd/src left unbuilt.
     *
     * @return void
     */
    public function test_the_builds_are_current(): void {
        foreach (['filter_line', 'accordion', 'competency_view', 'chip_filters', 'learner_prefs'] as $module) {
            $map = $this->plugin_root() . '/amd/build/' . $module . '.min.js.map';
            $this->assertFileExists($map);
            $decoded = json_decode((string) file_get_contents($map), true);
            $this->assertSame(
                $this->source($module),
                (string) ($decoded['sourcesContent'][0] ?? ''),
                "amd/build/{$module} is not this source: rebuild it with grunt amd and commit the build."
            );
        }
        preg_match_all("~^import [^\n]* from '([^']+)';$~m", $this->source(), $imports);
        $this->assertSame(['core/pending'], $imports[1], 'It imports core\'s pending tracker and nothing else.');
    }
}
