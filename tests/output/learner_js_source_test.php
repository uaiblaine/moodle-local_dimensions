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

/**
 * Pins behaviour of the learner pages' JavaScript that no PHP test can run.
 *
 * The plugin has no JavaScript test runner, so these read amd/src the way bootstrap_compat_test
 * reads class names: each assertion names the shape a fix gave the code and fails when the shape
 * it replaced comes back.
 *
 * The class-level covers tag stays in docblock form on purpose: moodle-cs on the 4.05 leg cannot
 * see PHP attributes, and the plugin still supports 4.5.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class learner_js_source_test extends \basic_testcase {
    /**
     * Read one of the learner pages' AMD sources.
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
     * The same reduction as hub_javascript_guards_test::code().
     *
     * @param string $js JavaScript source.
     * @return string
     */
    private function code(string $js): string {
        $js = (string) preg_replace('#/\*.*?\*/#s', '', $js);
        return (string) preg_replace('#(^|[^:])//[^\n]*#m', '$1', $js);
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

    /**
     * The body of a named function declaration, up to its closing brace at the same indent.
     *
     * @param string $source The module source.
     * @param string $name The function name.
     * @return string The function's body.
     */
    private function function_body(string $source, string $name): string {
        $pattern = '/^( *)function ' . preg_quote($name, '/') . '\([^)]*\) \{\n(.*?)\n\1\}$/ms';
        $this->assertSame(1, preg_match($pattern, $source, $match), "function {$name} not found");

        return $match[2];
    }

    /**
     * The body of an arrow function assigned to a top-level const.
     *
     * @param string $source The module source.
     * @param string $name The const name.
     * @return string The function's body.
     */
    private function arrow_body(string $source, string $name): string {
        $pattern = '/^(?:export )?const ' . preg_quote($name, '/') . ' = \([^)]*\) => \{\n(.*?)\n\};$/ms';
        $this->assertSame(1, preg_match($pattern, $source, $match), "const {$name} not found");

        return $match[1];
    }

    /**
     * The decisive rule completion is the newest one, which core lists first.
     *
     * api::list_evidence() orders by timecreated DESC, so keeping the last match of a forward walk
     * lifted the oldest completion into the result strip.
     *
     * @return void
     */
    public function test_the_decisive_rule_completion_is_the_newest(): void {
        $body = $this->function_body($this->js_source('accordion'), 'renderEvidenceList');

        $this->assertStringContainsString('const decisiveIndex = evidence.findIndex(isRuleCompletion);', $body);
        $this->assertStringNotContainsString('decisiveIndex = index', $body);
    }

    /**
     * The evidence icon never guesses "file" from the name the learner typed.
     *
     * desca is the learner's own name for a piece of prior-learning evidence, so any substring
     * test on it labels evidence by what someone chose to call it.
     *
     * @return void
     */
    public function test_evidence_type_ignores_the_learner_typed_name(): void {
        $source = $this->js_source('accordion');
        $this->assertDoesNotMatchRegularExpression('/\.desca\b/', $source);

        $body = $this->function_body($source, 'getEvidenceTypeInfo');
        $this->assertMatchesRegularExpression(
            "/descidentifier === 'evidence_evidenceofpriorlearninglinked'\) \{\s*return \{\s*icon: 'fa-trophy',"
                . "\s*label: strMap\.evidenceTypePrior,/",
            $body
        );
    }

    /**
     * The paperclip marks a prior-learning record with files only when the server said so, and is named.
     *
     * The server sets hasfiles on a prior-learning row only, and only for a viewer allowed to open the
     * owner's records; prior_learning_files_test holds that side. The client must draw the marker on
     * a strict true alone, so a row without the key (every other type, every other viewer) never shows
     * it. The icon is decoration: the name is the loaded evidence_hasfiles string, hidden text in the
     * marker and part of the row's aria-label, since a role="button" is named by that label alone.
     * The type itself stays prior learning, with its trophy.
     *
     * @return void
     */
    public function test_the_files_marker_follows_the_server_flag(): void {
        $source = $this->code($this->js_source('accordion'));

        $row = $this->function_body($source, 'renderEvidenceRow');
        $this->assertStringContainsString('const hasFiles = ev.hasfiles === true;', $row);
        $this->assertSame(1, preg_match_all('/\bhasfiles\b/', $row), 'the row reads the flag once');
        $this->assertMatchesRegularExpression(
            '/if \(hasFiles\) \{\s*'
                . preg_quote("html += '<span class=\"local-dimensions-ev-row-files\">';", '/') . '\s*'
                . preg_quote("html += '<i class=\"fa fa-paperclip\" aria-hidden=\"true\"></i>';", '/') . '\s*'
                . preg_quote(
                    "html += '<span class=\"visually-hidden\">' + escapeHtml(strMap.evidenceHasFiles) + '</span>';",
                    '/'
                ) . '\s*'
                . preg_quote("html += '</span>';", '/') . '\s*\}/',
            $row
        );
        $this->assertStringContainsString(
            "(hasFiles ? ', ' + escapeHtml(strMap.evidenceHasFiles) : '') + '\"'",
            $row
        );
        // The marker is drawn in that one place: never unconditionally, never by the type.
        $this->assertSame(1, substr_count($source, 'fa-paperclip'));
        $this->assertSame(1, substr_count($source, 'local-dimensions-ev-row-files'));

        $modal = $this->function_body($source, 'buildEvidenceModalContext');
        $this->assertStringContainsString('hasfiles: ev.hasfiles === true,', $modal);
        $this->assertStringContainsString('strhasfiles: strMap.evidenceHasFiles,', $modal);

        $this->assertSame('evidence_hasfiles', $this->string_slots($source)['evidenceHasFiles']);

        $type = $this->function_body($source, 'getEvidenceTypeInfo');
        $this->assertStringNotContainsString('hasfiles', $type);
        $this->assertMatchesRegularExpression(
            "/descidentifier === 'evidence_evidenceofpriorlearninglinked'\) \{\s*return \{\s*icon: 'fa-trophy',"
                . "\s*label: strMap\.evidenceTypePrior,\s*colorClass: 'local-dimensions-evidence-prior'\s*\};\s*\}/",
            $type
        );
    }

    /**
     * The evidence modal draws the same marker inside its hasfiles section only, with the label beside it.
     *
     * @return void
     */
    public function test_the_evidence_modal_marks_files_only_under_the_flag(): void {
        global $CFG;

        $template = (string) file_get_contents($CFG->dirroot . '/local/dimensions/templates/evidence_detail_modal.mustache');
        $markup = (string) preg_replace('/\{\{!.*?\}\}/s', '', $template);

        $this->assertMatchesRegularExpression(
            '/\{\{#hasfiles\}\}\s*<span class="local-dimensions-ev-modal-files">\s*'
                . '<i class="fa fa-paperclip" aria-hidden="true"><\/i>\s*\{\{strhasfiles\}\}\s*<\/span>\s*\{\{\/hasfiles\}\}/',
            $markup
        );
        $this->assertSame(1, substr_count($markup, 'fa-paperclip'));
        $this->assertSame(1, substr_count($markup, '{{#hasfiles}}'));
        $this->assertSame(1, substr_count($markup, '{{strhasfiles}}'));
    }

    /**
     * The rule progress line and bar speak the loaded strings, in the unit the rule counts.
     *
     * An item-count rule sends completed children in the same earnedpoints/totalrequired fields,
     * so announcing its bar in points misreports it.
     *
     * @return void
     */
    public function test_rule_progress_uses_the_loaded_unit(): void {
        $source = $this->js_source('accordion');
        $this->assertStringNotContainsString("' pts'", $source);

        $body = $this->function_body($source, 'renderRulesSection');
        $this->assertStringContainsString("(isPoints ? ' ' + escapeHtml(strMap.rulesPts) : '')", $body);
        $this->assertStringContainsString(
            'const srProgressText = (isPoints ? strMap.rulesSrProgress : strMap.rulesSrProgressItems)',
            $body
        );

        $slots = $this->string_slots($source);
        $this->assertSame('rules_pts', $slots['rulesPts']);
        $this->assertSame('rules_sr_progress', $slots['rulesSrProgress']);
        $this->assertSame('rules_sr_progress_items', $slots['rulesSrProgressItems']);

        foreach (['en', 'pt_br'] as $lang) {
            $string = [];
            include(__DIR__ . '/../../lang/' . $lang . '/local_dimensions.php');
            $this->assertArrayHasKey('rules_sr_progress_items', $string, $lang);
            $this->assertStringContainsString('{$a->earned}', $string['rules_sr_progress_items'], $lang);
            $this->assertStringContainsString('{$a->total}', $string['rules_sr_progress_items'], $lang);
        }
    }

    /**
     * Map each strMap name of the competency summary to the string key its index loads.
     *
     * The map is positional, so a key added or removed without renumbering shifts every later
     * name onto its neighbour's text. The key count and the highest index must agree too.
     *
     * @param string $source The accordion source.
     * @return array strMap name => lang string key.
     */
    private function string_slots(string $source): array {
        $body = $this->function_body($source, 'renderCompetencySummary');
        $this->assertSame(1, preg_match('/Str\.get_strings\(\[(.*?)\]\)\.then/s', $body, $list));
        preg_match_all("/\{key: '([a-z0-9_]+)', component: '[a-z_]+'\}/", $list[1], $keys);
        $keys = $keys[1];

        preg_match_all('/^\s+([A-Za-z]+): strings\[(\d+)\],?$/m', $body, $names, PREG_SET_ORDER);
        $slots = [];
        $highest = -1;
        foreach ($names as $name) {
            $index = (int) $name[2];
            $highest = max($highest, $index);
            $this->assertArrayHasKey($index, $keys, "strings[{$index}] loads nothing");
            $slots[$name[1]] = $keys[$index];
        }
        $this->assertSame(count($keys) - 1, $highest, 'a loaded string is never read');

        return $slots;
    }

    /**
     * The competency path footnote reads no key the summary payload never carries.
     *
     * Core's competency summary exports the path as comppath; nothing produces a compparents key.
     *
     * @return void
     */
    public function test_the_path_footnote_reads_no_phantom_key(): void {
        $this->assertStringNotContainsString('compparents', $this->js_source('accordion'));
    }

    /**
     * A favourites write is trimmed to what a Moodle 4.5 preference value can hold.
     *
     * The 4.5 column holds 1333 characters and set_user_preference() refuses a longer value, so an
     * unbounded map would make every later star fail to save.
     *
     * @return void
     */
    public function test_the_favourites_map_is_capped_before_it_is_saved(): void {
        $source = $this->js_source('learner_prefs');
        $this->assertStringContainsString('const MAX_FAV_LENGTH = 1333;', $source);

        $toggle = $this->arrow_body($source, 'toggleFavourite');
        $this->assertMatchesRegularExpression(
            '/favourites = fitFavourites\(favourites, favplan\);\s*scheduleSave\(PREF_FAV, favourites\);/',
            $toggle
        );
        $this->assertSame(1, substr_count($source, 'scheduleSave(PREF_FAV'), 'an unfitted favourites write');

        $fit = $this->arrow_body($source, 'fitFavourites');
        $this->assertSame(2, substr_count($fit, 'JSON.stringify(fitted).length > MAX_FAV_LENGTH'));
        $this->assertStringContainsString('delete fitted[others.shift()];', $fit);
        $this->assertStringContainsString('fitted[planid].length > 1', $fit);
    }

    /**
     * The tracker's error and retry text is never drawn before its strings arrive.
     *
     * The cards used to start loading at once with English placeholders, so a fast failure on a
     * non-English site showed English.
     *
     * @return void
     */
    public function test_tracker_cards_load_only_after_their_strings(): void {
        $source = $this->js_source('competency_view');
        foreach (["'Could not load progress.'", "'Retry'", "'Loading…'"] as $literal) {
            $this->assertStringNotContainsString($literal, $source);
        }

        $this->assertSame(
            1,
            preg_match(
                "/Str\.get_strings\(\[\s*\{key: 'course_load_error'.*?\]\)\.then\(function\(s\) \{(.*?)\n {12}\}\)"
                    . "\.catch\(Notification\.exception\);/s",
                $source,
                $chain
            )
        );
        $this->assertStringContainsString('loadAllCourses();', $chain[1]);
        // The declaration and that one call: nothing starts the cards outside the chain.
        $this->assertSame(2, preg_match_all('/\bloadAllCourses\(\)/', $source));
        $this->assertStringContainsString(
            'local_dimensions_get_courses_completion_status',
            $this->function_body($source, 'loadAllCourses')
        );
    }

    /**
     * Both tracker card services are sent the page's plan and competency, read from the init settings.
     *
     * Without them the services describe the caller, so a teacher opening a learner's tracker would see
     * their own progress and locks over the learner's course list. view-competency.php hands both to
     * init(); tracker_cards_owner_test reads them back from a rendered page.
     *
     * @return void
     */
    public function test_tracker_card_services_are_sent_the_plan(): void {
        $source = $this->code($this->js_source('competency_view'));

        $this->assert_in_order($source, [
            'init: function(settings) {',
            'settings = settings || {};',
            'var planid = settings.planid || 0;',
            'var competencyid = settings.competencyid || 0;',
            'function loadCourseWithSoftTimeout(courseid) {',
            'function loadAllCourses() {',
        ]);
        // Declared once, and never reassigned.
        $this->assertSame(1, preg_match_all('/\bplanid =/', $source));
        $this->assertSame(1, preg_match_all('/\bcompetencyid =/', $source));

        $this->assertMatchesRegularExpression(
            "/methodname: 'local_dimensions_get_course_progress',\s*"
                . "args: \{courseids: \[courseid\], planid: planid, competencyid: competencyid\}/",
            $this->function_body($source, 'loadCourseWithSoftTimeout')
        );
        $this->assertMatchesRegularExpression(
            "/methodname: 'local_dimensions_get_courses_completion_status',\s*"
                . "args: \{courseids: courseIds, planid: planid, competencyid: competencyid\}/",
            $this->function_body($source, 'loadAllCourses')
        );
        // Each service is called from that one place only, so no call goes out without the plan.
        $this->assertSame(1, substr_count($source, "'local_dimensions_get_course_progress'"));
        $this->assertSame(1, substr_count($source, "'local_dimensions_get_courses_completion_status'"));
    }

    /**
     * The tracker's locked-card settings dress only a card that offers nothing.
     *
     * Learn-more mode, the admin's icon and the start date belong to the none state: a card with a
     * state of its own (an application, a later enrolment, an open route) says that state, which the
     * server composed, and a Learn more button or a lock icon over it would hide it.
     *
     * @return void
     */
    public function test_tracker_locked_card_settings_dress_only_the_none_state(): void {
        $source = $this->code($this->js_source('competency_view'));

        $this->assert_in_order($source, [
            "var statekey = data.state ? data.state.key : '';",
            "data.isenrolled = !data.locked && statekey === 'enrolled';",
            "var isnone = statekey === 'none';",
            "data.islearnmore = isnone && lockedcardmode === 'learnmore';",
            'data.showlockeddate = isnone && showlockeddate && (!data.islearnmore || !!data.is_future_date);',
            "data.customicon = isnone ? cardiconclass : '';",
        ]);
        // Each flag is written once, so nothing widens it afterwards.
        foreach (['data.islearnmore =', 'data.showlockeddate =', 'data.customicon =', 'data.isenrolled ='] as $assignment) {
            $this->assertSame(1, substr_count($source, $assignment), $assignment);
        }
    }

    /**
     * Only a plan card the viewer can open is a link; every other state carries its own way in.
     *
     * The card's state comes from the course list service as a whole state area; the script reads its
     * key and nothing else of the old access field.
     *
     * @return void
     */
    public function test_plan_cards_link_only_an_enrolled_course(): void {
        $source = $this->code($this->js_source('accordion'));
        $cards = $this->function_body($source, 'renderCourseCardsScrollable');

        $this->assertStringContainsString("const isReachable = !!course.state && course.state.key === 'enrolled';", $cards);
        $this->assertMatchesRegularExpression(
            "/html \+= isReachable\s*\? '<a href=\"' \+ escapeHtml\(courseUrl\) \+ '\" class=\"local-dimensions-course-link\">'"
                . "\s*: '<div class=\"local-dimensions-course-link\">';/",
            $cards
        );
        $this->assert_in_order($cards, [
            'if (isReachable) {',
            'renderStatePill(course.state)',
            '} else {',
            'html += renderLockedState(course, strMap);',
        ]);
        $this->assertStringNotContainsString('course.access', $source);

        // The locked state area: learn-more mode lends the course page only to a card that offers nothing.
        $locked = $this->function_body($source, 'renderLockedState');
        $this->assertStringContainsString(
            "if (!actionurl && isnone && displaySettings.lockedcardmode === 'learnmore') {",
            $locked
        );
        $this->assertStringContainsString(
            'if (isnone && displaySettings.showlockeddate && lockdate * 1000 > Date.now()) {',
            $locked
        );
    }

    /**
     * The filter tabs offer no teardown, since a re-initialisation after one would wrap twice.
     *
     * @return void
     */
    public function test_filter_tabs_offer_no_half_teardown(): void {
        $source = $this->js_source('filter_tabs_nav');

        $this->assertStringNotContainsString('destroy', $source);
        $this->assertMatchesRegularExpression('/return \{\s*FilterTabsNav: FilterTabsNav,\s*initAll: initAll,'
            . '\s*updateAll: updateAll\s*\};/', $source);
    }

    /**
     * Collapsible descriptions measure only what is still on the page, and wait on no video load.
     *
     * The accordion rebuilds its panes on every layout switch; a list that only grows keeps each
     * torn-down pane alive and measures it on every resize. A video element fires no load event.
     *
     * @return void
     */
    public function test_collapsible_descriptions_forget_detached_panes(): void {
        $source = $this->js_source('collapsible_description');

        $prune = $this->function_body($source, 'pruneDisconnected');
        $this->assertStringContainsString('if (container.isConnected) {', $prune);
        $this->assertStringContainsString('resizeObserver.unobserve(content);', $prune);

        $this->assertSame(2, substr_count($source, 'pruneDisconnected().forEach(measure);'));
        $this->assertStringNotContainsString('trackedContainers.forEach(measure)', $source);

        preg_match_all("/querySelectorAll\('([^']*)'\)/", $source, $selectors);
        $this->assertContains('img, iframe', $selectors[1]);
        foreach ($selectors[1] as $selector) {
            $this->assertStringNotContainsString('video', $selector);
        }
    }
}
