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
 * Tests for the filter line's markup on both learner views.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\output;

use core_competency\plan;
use local_dimensions\constants;
use local_dimensions\customfield\competency_handler;
use local_dimensions\helper;

/**
 * Renders both learner views and reads the hooks local_dimensions/filter_line depends on.
 *
 * The module finds every part of the line by a data hook the template writes, so a hook renamed in
 * one place and not the other leaves a line that renders and does nothing. These tests render the
 * real pages, not the partial's example, so the page's own context is what is checked: the slot
 * order of the contract (scope, search, star, Filters, Organise, view), the eraser that is never a
 * cross, the scope as a radio group with one chosen cell, the floating layers named, the chips row,
 * and the old mobile "adjust" button gone.
 *
 * @covers \local_dimensions\output\view_plan_summary_page
 * @covers \local_dimensions\output\view_competency_page
 */
final class filter_line_markup_test extends \advanced_testcase {
    /**
     * A plan of two competencies, one proficient, one of them tagged so the Filters panel draws.
     *
     * @return array Keys: plan, user.
     */
    private function create_plan(): array {
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);
        $generator = $this->getDataGenerator();
        $ccg = $generator->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework();
        $template = $ccg->create_template();
        $ids = [];
        foreach (['Alpha', 'Bravo'] as $shortname) {
            $ids[$shortname] = (int) $ccg->create_competency([
                'competencyframeworkid' => $framework->get('id'),
                'shortname' => $shortname,
            ])->get('id');
            $ccg->create_template_competency(['templateid' => $template->get('id'), 'competencyid' => $ids[$shortname]]);
        }
        $field = helper::find_field_by_shortname(constants::CFIELD_TAG1, helper::AREA_COMPETENCY);
        $config = $field->get('configdata');
        $config['options'] = "Core\nElective";
        $field->set('configdata', json_encode($config));
        $field->save();
        competency_handler::create()->instance_form_save((object) [
            'id' => $ids['Alpha'],
            'customfield_' . constants::CFIELD_TAG1 => 1,
        ], true);
        set_config('viewplan_filter_fields', constants::CFIELD_TAG1, 'local_dimensions');

        $user = $generator->create_user();
        $plan = $ccg->create_plan(['userid' => $user->id, 'templateid' => $template->get('id'), 'status' => plan::STATUS_ACTIVE]);
        $ccg->create_user_competency(['userid' => $user->id, 'competencyid' => $ids['Bravo'], 'grade' => 4, 'proficiency' => 1]);

        return ['plan' => $plan, 'user' => $user];
    }

    /**
     * Render the plan overview as its own learner.
     *
     * @param plan $plan The plan.
     * @return string The page's markup.
     */
    private function render_plan(plan $plan): string {
        global $PAGE;
        $renderer = $PAGE->get_renderer('core');
        $data = (new view_plan_summary_page($plan))->export_for_template($renderer);
        return $renderer->render_from_template('local_dimensions/view_plan_summary', $data);
    }

    /**
     * Render the tracker for one competency and one course, as a fresh learner.
     *
     * @return string The page's markup.
     */
    private function render_tracker(): string {
        global $DB, $PAGE;
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework();
        $competencyid = (int) $ccg->create_competency(['competencyframeworkid' => $framework->get('id')])->get('id');
        $competency = $DB->get_record('competency', ['id' => $competencyid], '*', MUST_EXIST);
        $course = get_course((int) $this->getDataGenerator()->create_course(['fullname' => 'Gamma course'])->id);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $renderer = $PAGE->get_renderer('core');
        $data = (new view_competency_page($competency, [(int) $course->id => $course], (int) $user->id))
            ->export_for_template($renderer);
        return $renderer->render_from_template('local_dimensions/view_competency', $data);
    }

    /**
     * The opening tag of the one element carrying a hook, failing when there is none or several.
     *
     * @param string $html The markup.
     * @param string $hook An attribute and its value, as written: data-region="x".
     * @return string The opening tag.
     */
    private function tag(string $html, string $hook): string {
        $found = preg_match_all('~<[a-z]+\b[^>]*' . preg_quote($hook, '~') . '[^>]*>~', $html, $matches);
        $this->assertSame(1, $found, "Exactly one element carries {$hook}.");
        return $matches[0][0];
    }

    /**
     * The line: the contract's slots in their order, each with the hook the module reads.
     *
     * Changes that must make it fail: a slot moved (the star after Filters, the view before
     * Organise); a hook renamed in the template; the eraser drawn as a cross; the listbox drawn
     * without its headings or visible; the adjust button put back.
     *
     * @return void
     */
    public function test_the_plan_line_draws_the_contracts_slots_in_order(): void {
        $this->resetAfterTest();
        $fixture = $this->create_plan();
        $this->setUser($fixture['user']);

        $html = $this->render_plan($fixture['plan']);

        $order = [
            'data-region="local-dimensions-line-scope"',
            'data-region="local-dimensions-line-search"',
            'data-region="local-dimensions-line-star"',
            'data-filter-toggle',
            'data-sort-toggle',
            'data-region="local-dimensions-line-view"',
            'data-action="local-dimensions-search-close"',
            'data-region="local-dimensions-line-suggest"',
        ];
        $at = -1;
        foreach ($order as $hook) {
            $position = strpos($html, $hook);
            $this->assertNotFalse($position, "{$hook} is drawn.");
            $this->assertGreaterThan($at, $position, "{$hook} comes after the slot before it.");
            $at = $position;
        }
        $line = $this->tag($html, 'data-region="local-dimensions-line"');
        $this->assertStringContainsString('class="local-dimensions-line"', $line);
        $this->assertStringContainsString('data-region="local-dimensions-line-toolbar"', $html);

        // The search: a search field with its label, the eraser hidden until there is text, never a cross.
        $pattern = '~<input type="search" id="([^"]+)" class="local-dimensions-search-input"~';
        $this->assertSame(1, preg_match($pattern, $html, $field));
        $this->assertStringContainsString('<label class="local-dimensions-line-sronly" for="' . $field[1] . '">', $html);
        $eraser = $this->tag($html, 'data-action="local-dimensions-search-clear"');
        $this->assertStringContainsString(' hidden', $eraser);
        $this->assertSame(1, preg_match('~data-action="local-dimensions-search-clear"[^>]*>\s*<i class="fa fa-eraser"~', $html));
        $opener = $this->tag($html, 'data-action="local-dimensions-search-open"');
        $this->assertStringContainsString('aria-controls="' . $field[1] . '"', $opener);
        $this->assertStringContainsString('aria-expanded="false"', $opener);

        // The suggestions: hidden until the module fills them, with the three headings it reads.
        $listbox = $this->tag($html, 'data-region="local-dimensions-line-suggest"');
        $this->assertStringContainsString(' hidden', $listbox);
        foreach (['line_suggest_filters', 'line_suggest_start', 'line_suggest_competencies'] as $key) {
            $this->assertStringContainsString('="' . get_string($key, 'local_dimensions') . '"', $listbox);
        }

        // The layers are named and controlled: the panel by Filters, the menu by Organise.
        $this->assertSame(1, preg_match('~data-filter-toggle aria-expanded="false" aria-controls="([^"]+)"~', $html, $panel));
        $this->assertStringContainsString('id="' . $panel[1] . '"', $this->tag($html, 'data-region="local-dimensions-line-panel"'));
        $this->assertSame(1, preg_match('~data-sort-toggle[^>]*aria-controls="([^"]+)"~', $html, $menu));
        $this->assertStringContainsString('id="' . $menu[1] . '"', $this->tag($html, 'data-region="local-dimensions-line-menu"'));
        $this->assertStringContainsString('data-region="local-dimensions-line-showas"', $html);
        $this->assertStringContainsString('data-action="local-dimensions-panel-show"', $html);

        // The chips row under the line, hidden until a filter is in force.
        $chips = $this->tag($html, 'data-region="local-dimensions-line-chips"');
        $this->assertStringContainsString(' hidden', $chips);
        $remove = get_string('line_remove_filter', 'local_dimensions');
        $this->assertStringContainsString('data-label-remove="' . $remove . '"', $chips);
        $this->assertStringContainsString('data-action="local-dimensions-clear-all"', $html);

        // The fold replaces the old narrow-screen button and its breaks.
        $this->assertStringNotContainsString('data-toolbar-adjust', $html);
        $this->assertStringNotContainsString('data-fav-filter', $html);
    }

    /**
     * The scope is a radio group with one chosen cell, "To do" first, and the stored choice decides which.
     *
     * Changes that must make it fail: the cells drawn as tabs; the chosen cell not the stored one; both
     * cells in the tab order; a zero "To do" drawn while "All" is chosen.
     *
     * @return void
     */
    public function test_the_plan_scope_is_a_radio_group_with_one_chosen_cell(): void {
        $this->resetAfterTest();
        $fixture = $this->create_plan();
        $this->setUser($fixture['user']);

        $html = $this->render_plan($fixture['plan']);
        $this->assertStringContainsString('role="radiogroup"', $this->tag($html, 'data-region="local-dimensions-line-scope"'));
        preg_match_all('~<button type="button" class="local-dimensions-line-cell" role="radio" data-filter="([a-z]+)"'
            . ' aria-checked="(true|false)" tabindex="(0|-1)"( hidden)?>~', $html, $cells, PREG_SET_ORDER);
        $this->assertCount(2, $cells);
        $this->assertSame(['incomplete', 'true', '0'], array_slice($cells[0], 1, 3), 'To do, first and chosen.');
        $this->assertSame(['all', 'false', '-1'], array_slice($cells[1], 1, 3));
        $this->assertStringContainsString('>' . get_string('todo', 'local_dimensions') . ' <span', $html);

        set_user_preference(constants::PREF_LEARNER_VIEW, json_encode(['filter' => 'all']));
        $html = $this->render_plan($fixture['plan']);
        preg_match_all('~role="radio" data-filter="([a-z]+)" aria-checked="(true|false)" tabindex="(0|-1)"~', $html, $cells);
        $this->assertSame(['false', 'true'], $cells[2], 'The remembered "All" is the chosen cell.');
        $this->assertSame(['-1', '0'], $cells[3]);
    }

    /**
     * A "To do" with nothing left to do is not drawn, unless it is the chosen cell.
     *
     * Changes that must make it fail: the zero cell drawn beside a chosen "All"; the chosen zero cell
     * hidden.
     *
     * @return void
     */
    public function test_a_zero_to_do_is_drawn_only_when_chosen(): void {
        $this->resetAfterTest();
        $fixture = $this->create_plan();
        $rows = \core_competency\api::list_plan_competencies($fixture['plan']);
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        foreach ($rows as $row) {
            if (!$row->usercompetency->get('id')) {
                $ccg->create_user_competency([
                    'userid' => $fixture['user']->id,
                    'competencyid' => $row->competency->get('id'),
                    'grade' => 4,
                    'proficiency' => 1,
                ]);
            }
        }
        $this->setUser($fixture['user']);

        set_user_preference(constants::PREF_LEARNER_VIEW, json_encode(['filter' => 'all']));
        $html = $this->render_plan($fixture['plan']);
        $this->assertSame(1, preg_match('~data-filter="incomplete" aria-checked="false" tabindex="-1" hidden>~', $html));

        set_user_preference(constants::PREF_LEARNER_VIEW, json_encode(['filter' => 'incomplete']));
        $html = $this->render_plan($fixture['plan']);
        $this->assertSame(1, preg_match('~data-filter="incomplete" aria-checked="true" tabindex="0">~', $html));
    }

    /**
     * The star replaces the favourites pills: one aria-pressed toggle, hidden until a favourite exists.
     *
     * Changes that must make it fail: the star drawn without aria-pressed; drawn while there is no
     * favourite; its glyph not following the stored favourites-only choice.
     *
     * @return void
     */
    public function test_the_star_is_one_pressed_toggle_kept_on_favonly(): void {
        $this->resetAfterTest();
        $fixture = $this->create_plan();
        $this->setUser($fixture['user']);

        $star = $this->tag($this->render_plan($fixture['plan']), 'data-region="local-dimensions-line-star"');
        $this->assertStringContainsString('aria-pressed="false"', $star);
        $this->assertStringContainsString(' hidden', $star, 'No favourite yet: nothing to narrow to.');

        $plan = $fixture['plan'];
        $rows = \core_competency\api::list_plan_competencies($plan);
        $first = (int) reset($rows)->competency->get('id');
        set_user_preference(constants::PREF_LEARNER_FAV, json_encode([(string) $plan->get('id') => [$first]]));
        set_user_preference(constants::PREF_LEARNER_VIEW, json_encode(['favonly' => true]));
        $html = $this->render_plan($plan);
        $star = $this->tag($html, 'data-region="local-dimensions-line-star"');
        $this->assertStringContainsString('aria-pressed="true"', $star);
        $this->assertStringNotContainsString(' hidden', $star);
        $this->assertSame(1, preg_match('~data-region="local-dimensions-line-star"[^>]*><i class="fa fa-star"~', $html));
    }

    /**
     * The tracker's line: its scope waiting for its counts, "To do" chosen, its search, no star, no menu.
     *
     * Changes that must make it fail: the scope drawn before the batch answers; "All" chosen by
     * default; a star, an Organise menu or a view toggle drawn; the "no results" state missing.
     *
     * @return void
     */
    public function test_the_tracker_line_draws_scope_search_and_no_results(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $html = $this->render_tracker();

        $scope = $this->tag($html, 'data-region="local-dimensions-line-scope"');
        $this->assertStringContainsString(' hidden', $scope);
        $this->assertSame(1, preg_match('~data-filter="incomplete" aria-checked="true"~', $html));
        $this->assertSame(1, preg_match('~data-filter="all" aria-checked="false"~', $html));
        $this->assertStringContainsString('placeholder="' . get_string('line_search_courses', 'local_dimensions') . '"', $html);
        $this->assertStringContainsString(
            'data-label-items="' . get_string('line_suggest_courses', 'local_dimensions') . '"',
            $this->tag($html, 'data-region="local-dimensions-line-suggest"')
        );
        $this->assertStringNotContainsString('data-region="local-dimensions-line-star"', $html);
        $this->assertStringNotContainsString('data-sort-toggle', $html);
        $this->assertStringNotContainsString('data-region="local-dimensions-line-view"', $html);
        $this->assertStringContainsString('data-region="local-dimensions-line-chips"', $html);

        $noresults = $this->tag($html, 'id="local-dimensions-viewcompetency-noresults"');
        $this->assertStringContainsString(' hidden', $noresults);
        $this->assertStringContainsString(get_string('line_noresults_courses', 'local_dimensions'), $html);
        $this->assertStringContainsString('data-noresults-clear', $html);
        $this->assertStringContainsString('data-region="local-dimensions-tracker"', $html);
        $this->assertStringContainsString('data-viewstate="', $html);
    }

    /**
     * Each chip of the panel carries the count hook chip_filters paints.
     *
     * @return void
     */
    public function test_every_chip_carries_its_count_hook(): void {
        $this->resetAfterTest();
        $fixture = $this->create_plan();
        $this->setUser($fixture['user']);

        $html = $this->render_plan($fixture['plan']);
        $chips = preg_match_all('~<button type="button"\s+class="local-dimensions-filter-tab"\s+data-chip-value="[^"]*"~', $html);
        $this->assertGreaterThan(0, $chips);
        $this->assertSame($chips, substr_count($html, 'data-region="local-dimensions-chip-count"'));
    }

    /**
     * The contract's words (section 7), in both packs, in lockstep.
     *
     * Changes that must make it fail: a key added to one pack and not the other; a word of the
     * contract changed in either language.
     *
     * @return void
     */
    public function test_the_contracts_words_are_in_both_packs(): void {
        $words = [
            'line_search_courses' => ['Search courses…', 'Buscar cursos…'],
            'line_search_competencies' => ['Search competencies…', 'Buscar competências…'],
            'line_clear_text' => ['Clear text', 'Limpar texto'],
            'line_close_search' => ['Close search', 'Fechar busca'],
            'line_filters' => ['Filters', 'Filtros'],
            'filter_chip_clear' => ['Clear filters', 'Limpar filtros'],
            'line_clear_all' => ['Clear all', 'Limpar tudo'],
            'line_organise' => ['Organise', 'Organizar'],
            'line_view_list' => ['List', 'Lista'],
            'line_view_cards' => ['Cards', 'Cartões'],
            'line_favourites' => ['Favourites', 'Favoritos'],
            'todo' => ['To do', 'A fazer'],
            'line_suggest_filters' => ['Filters', 'Filtros'],
            'line_suggest_courses' => ['Courses', 'Cursos'],
            'line_suggest_start' => ['Start with a filter', 'Comece por um filtro'],
        ];
        $en = $this->strings('en');
        $pt = $this->strings('pt_br');
        foreach ($words as $key => [$english, $portuguese]) {
            $this->assertSame($english, $en[$key] ?? null, "en {$key}");
            $this->assertSame($portuguese, $pt[$key] ?? null, "pt_br {$key}");
        }
        $this->assertSame(array_keys($en), array_keys($pt), 'Both packs hold the same keys in the same order.');
        $sorted = array_keys($en);
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, array_keys($en), 'The packs are sorted.');
    }

    /**
     * One language pack, read as a file.
     *
     * @param string $lang The pack.
     * @return array Key => string.
     */
    private function strings(string $lang): array {
        $string = [];
        include(dirname(__DIR__, 2) . '/lang/' . $lang . '/local_dimensions.php');
        return $string;
    }
}
