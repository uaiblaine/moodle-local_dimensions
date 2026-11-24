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
 * Tests for the Competencies tab's views: which one shows, what the switch offers, and what the
 * workspace (Outline, Map, Both) receives.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\output\dynamictabs;

use advanced_testcase;
use context_coursecat;
use local_dimensions\constants;
use local_dimensions\external\browse_structure;
use local_dimensions\output\hub_map;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The classic list or the workspace in one of its three layouts: the URL's view, else the saved one,
 * else the list; and the workspace lists exactly what the list lists, because both come from the one
 * resolution the tab makes.
 */
#[CoversClass(structure::class)]
#[CoversClass(hub_map::class)]
final class structure_view_test extends advanced_testcase {
    /**
     * Export the tab as the current user.
     *
     * @param array $data The pane data.
     * @return array The template context, with the map's props decoded under 'decoded' (null without a map).
     */
    private function export(array $data): array {
        global $PAGE;
        $tab = new structure($data + ['contexttype' => 'system', 'categoryid' => 0, 'locked' => 0]);
        $context = $tab->export_for_template($PAGE->get_renderer('core'));
        $context['decoded'] = $context['mapprops'] === '' ? null : json_decode($context['mapprops'], true);
        return $context;
    }

    /**
     * Save a view in the current user's display preference.
     *
     * @param mixed $view The stored value.
     * @return void
     */
    private function save_view($view): void {
        set_user_preference(constants::PREF_CENTRAL_DISPLAY, json_encode(['structure' => ['view' => $view]]));
    }

    /**
     * Enable competencies and create one framework with a competency, as the administrator.
     *
     * @return \core_competency\competency_framework
     */
    private function one_framework(): \core_competency\competency_framework {
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework(['shortname' => 'Mapped']);
        $ccg->create_competency(['competencyframeworkid' => $framework->get('id'), 'shortname' => 'Only']);
        return $framework;
    }

    /**
     * In classic mode the pane's view beats the saved one, which beats the classic list; anything else counts as none.
     *
     * @return void
     */
    public function test_resolve_view_in_classic_mode_orders_the_url_the_saved_view_and_the_list(): void {
        $this->assertSame(['list', 'outline', 'map', 'both'], structure::VIEWS);
        foreach (structure::VIEWS as $view) {
            $this->assertSame($view, structure::resolve_view($view, 'list', true));
            $this->assertSame($view, structure::resolve_view('', $view, true));
            $this->assertSame($view, structure::resolve_view('garbage', $view, true));
        }
        $this->assertSame('list', structure::resolve_view('list', 'both', true));
        $this->assertSame('outline', structure::resolve_view('outline', 'map', true));
        $this->assertSame('list', structure::resolve_view('', '', true));
        // An unknown value is no value: the saved view, else the list.
        $this->assertSame('map', structure::resolve_view('canvas', 'map', true));
        $this->assertSame('list', structure::resolve_view('canvas', 'garbage', true));
        $this->assertSame('list', structure::resolve_view('MAP', '', true));
        $this->assertSame('list', structure::resolve_view('Both', 'OUTLINE', true));
        $this->assertSame('list', structure::resolve_view('classic', '', true));
    }

    /**
     * Outside classic mode the default is Both, and the classic list is no view: a link or a saved choice naming it
     * counts as none, as a mistyped one does.
     *
     * @return void
     */
    public function test_resolve_view_outside_classic_mode_never_opens_the_list(): void {
        $this->assertSame('both', structure::DEFAULT_VIEW);
        $this->assertSame('both', structure::resolve_view('', '', false));
        $this->assertSame('both', structure::resolve_view('', ''));
        foreach (structure::WORKSPACE_VIEWS as $view) {
            $this->assertSame($view, structure::resolve_view($view, '', false));
            $this->assertSame($view, structure::resolve_view('', $view, false));
        }
        $this->assertSame('both', structure::resolve_view('list', '', false));
        $this->assertSame('both', structure::resolve_view('', 'list', false));
        $this->assertSame('both', structure::resolve_view('list', 'list', false));
        // The saved choice still beats the default, as it does for a mistyped link.
        $this->assertSame('map', structure::resolve_view('list', 'map', false));
        $this->assertSame('outline', structure::resolve_view('canvas', 'outline', false));
    }

    /**
     * The switch offers the four views in order in classic mode, the one shown pressed, each with its label and icon.
     *
     * @return void
     */
    public function test_the_switch_offers_every_view_and_presses_the_one_shown(): void {
        $cells = structure::view_cells('both', true);
        $this->assertSame(structure::VIEWS, array_column($cells, 'value'));
        $this->assertSame([false, false, false, true], array_column($cells, 'pressed'));
        $this->assertSame(['Classic', 'Outline', 'Map', 'Both'], array_column($cells, 'label'));
        $this->assertSame(['fa-list', 'fa-folder-tree', 'fa-sitemap', 'fa-table-columns'], array_column($cells, 'icon'));
        $this->assertSame([true, false, false, false], array_column(structure::view_cells('list', true), 'pressed'));
        // A value that is no view presses none.
        $this->assertSame([false, false, false, false], array_column(structure::view_cells('bogus', true), 'pressed'));
    }

    /**
     * Outside classic mode the switch has no Classic cell.
     *
     * @return void
     */
    public function test_the_switch_has_no_classic_cell_outside_classic_mode(): void {
        $cells = structure::view_cells('both', false);
        $this->assertSame(['outline', 'map', 'both'], array_column($cells, 'value'));
        $this->assertSame(['Outline', 'Map', 'Both'], array_column($cells, 'label'));
        $this->assertSame([false, false, true], array_column($cells, 'pressed'));
    }

    /**
     * Through the tab in classic mode: the URL's view wins over the saved one, the saved one over the list.
     *
     * @return void
     */
    public function test_the_tab_shows_the_url_view_then_the_saved_view_then_the_list(): void {
        $this->resetAfterTest();
        $framework = $this->one_framework();
        $frameworkid = (int) $framework->get('id');
        $classic = ['frameworkid' => $frameworkid, 'classic' => 1];

        $default = $this->export($classic);
        $this->assertSame('list', $default['view']);
        $this->assertFalse($default['isworkspace']);
        $this->assertSame('', $default['mapprops']);
        $this->assertSame([true, false, false, false], array_column($default['views'], 'pressed'));

        $this->save_view('map');
        $saved = $this->export($classic);
        $this->assertSame('map', $saved['view']);
        $this->assertTrue($saved['isworkspace']);
        $this->assertSame('map', $saved['decoded']['layout']);
        $this->assertSame([false, false, true, false], array_column($saved['views'], 'pressed'));

        $this->assertSame('list', $this->export($classic + ['view' => 'list'])['view']);
        $this->assertSame('map', $this->export($classic + ['view' => 'bogus'])['view']);

        $this->save_view('<script>');
        $this->assertSame('list', $this->export($classic + ['view' => 'bogus'])['view']);
        $this->assertSame('map', $this->export($classic + ['view' => 'map'])['view']);
    }

    /**
     * Through the tab outside classic mode (the default): Both with nothing saved, the saved view beats it, and a
     * link or a saved choice naming the classic list opens the workspace; the switch has no Classic cell.
     *
     * @return void
     */
    public function test_by_default_the_tab_opens_the_workspace_in_both(): void {
        $this->resetAfterTest();
        $frameworkid = (int) $this->one_framework()->get('id');

        $default = $this->export(['frameworkid' => $frameworkid]);
        $this->assertFalse($default['classic']);
        $this->assertSame('both', $default['view']);
        $this->assertTrue($default['isworkspace']);
        $this->assertSame('both', $default['decoded']['layout']);
        $this->assertSame(['outline', 'map', 'both'], array_column($default['views'], 'value'));

        $this->assertSame('both', $this->export(['frameworkid' => $frameworkid, 'view' => 'list'])['view']);
        $this->save_view('list');
        $this->assertSame('both', $this->export(['frameworkid' => $frameworkid])['view']);
        $this->save_view('outline');
        $this->assertSame('outline', $this->export(['frameworkid' => $frameworkid, 'view' => 'list'])['view']);

        // The pane's mode beats the saved one, either way.
        set_user_preference(constants::PREF_CENTRAL_DISPLAY, json_encode(['classic' => true]));
        $this->assertTrue($this->export(['frameworkid' => $frameworkid])['classic']);
        $this->assertSame('list', $this->export(['frameworkid' => $frameworkid])['view']);
        $this->assertSame('both', $this->export(['frameworkid' => $frameworkid, 'classic' => '0'])['view']);
    }

    /**
     * Outline, Map and Both are one workspace with the view as its first layout; the list draws none.
     *
     * @return void
     */
    public function test_the_three_workspace_views_draw_one_workspace_with_their_layout(): void {
        $this->resetAfterTest();
        $frameworkid = (int) $this->one_framework()->get('id');

        foreach (structure::WORKSPACE_VIEWS as $view) {
            $data = $this->export(['frameworkid' => $frameworkid, 'view' => $view]);
            $this->assertSame($view, $data['view']);
            $this->assertTrue($data['isworkspace'], "{$view} draws the workspace");
            $this->assertSame($view, $data['decoded']['layout']);
            $this->assertSame($frameworkid, $data['decoded']['framework']['id']);
        }
        // The saved view opens the workspace in it, as the URL's does.
        $this->save_view('both');
        $this->assertSame('both', $this->export(['frameworkid' => $frameworkid])['decoded']['layout']);
        $this->save_view('outline');
        $this->assertSame('outline', $this->export(['frameworkid' => $frameworkid])['decoded']['layout']);
        // The control: the list (classic mode) draws no workspace, whatever was saved.
        $list = $this->export(['frameworkid' => $frameworkid, 'view' => 'list', 'classic' => 1]);
        $this->assertFalse($list['isworkspace']);
        $this->assertNull($list['decoded']);
    }

    /**
     * The map's props: the selected framework with its first page of roots, in the web service's node
     * shape, and nothing of the retired page (no list link).
     *
     * @return void
     */
    public function test_the_map_receives_the_framework_and_its_first_page_of_roots(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $ccg->create_framework(['shortname' => 'Alpha']);
        $beta = $ccg->create_framework(['shortname' => 'Beta', 'idnumber' => 'BETA']);
        $betaid = (int) $beta->get('id');
        $roots = [];
        for ($i = 0; $i < 27; $i++) {
            $roots[] = $ccg->create_competency(['competencyframeworkid' => $betaid, 'shortname' => sprintf('Root %02d', $i)]);
        }
        $ccg->create_competency(['competencyframeworkid' => $betaid, 'parentid' => $roots[0]->get('id')]);

        $data = $this->export(['frameworkid' => $betaid, 'view' => 'map']);

        $props = $data['decoded'];
        $this->assertSame(
            ['framework', 'roots', 'pagesize', 'rulesmodules', 'courseoutcomes', 'moduleoutcomes', 'rtl', 'reveal', 'layout',
                'structures', 'lens'],
            array_keys($props)
        );
        $this->assertSame('none', $props['lens']);
        $this->assertSame(
            ['id', 'shortname', 'idnumber', 'competencycount', 'canmanage', 'visible', 'contextname', 'scale', 'taxonomies',
                'taxonomyfallback', 'deletable'],
            array_keys($props['framework'])
        );
        $this->assertSame($betaid, $props['framework']['id']);
        $this->assertSame('Beta', $props['framework']['shortname']);
        $this->assertSame('BETA', $props['framework']['idnumber']);
        $this->assertSame(28, $props['framework']['competencycount']);
        $this->assertTrue($props['framework']['canmanage']);
        $this->assertSame(
            ['contextid' => \context_system::instance()->id, 'cancreate' => true, 'canscalespage' => true],
            $props['structures']
        );
        $this->assertSame(27, $props['roots']['total']);
        $this->assertCount(25, $props['roots']['items']);
        $this->assertSame(25, $props['pagesize']);
        $this->assertSame('Root 00', $props['roots']['items'][0]['shortname']);
        $this->assertTrue($props['roots']['items'][0]['haschildren']);
        $this->assertFalse($props['roots']['items'][1]['haschildren']);
        // A first-paint node and a lazily fetched one are the same shape.
        $this->assertSame(
            array_keys(browse_structure::node_structure()->keys),
            array_keys($props['roots']['items'][0])
        );
        $this->assertNotEmpty($props['rulesmodules']);
        $this->assertNotEmpty($props['courseoutcomes']);
        $this->assertNotEmpty($props['moduleoutcomes']);
        $this->assertFalse($props['rtl']);
        $this->assertNull($props['reveal']);
        // The framework select beside it lists the same frameworks the list view does.
        $this->assertSame(['Alpha', 'Beta'], array_column($data['frameworks'], 'name'));
        $this->assertSame([false, true], array_column($data['frameworks'], 'selected'));
    }

    /**
     * The workspace opens with the pane's lens (the address's), else the viewer's saved one, else none; any value that
     * is not a lens counts as none named, in the pane as in the preference.
     *
     * @return void
     */
    public function test_the_workspace_opens_with_the_pane_lens_then_the_saved_one_then_none(): void {
        $this->resetAfterTest();
        $frameworkid = (int) $this->one_framework()->get('id');
        $lens = fn(array $pane): string =>
            $this->export(['frameworkid' => $frameworkid, 'view' => 'both'] + $pane)['decoded']['lens'];

        $this->assertSame('none', $lens([]));
        foreach (hub_map::LENSES as $value) {
            $this->assertSame($value, $lens(['lens' => $value]));
        }
        $this->assertSame('none', $lens(['lens' => 'heatmap']));

        set_user_preference(constants::PREF_CENTRAL_DISPLAY, json_encode(['structure' => ['lens' => 'rules']]));
        $this->assertSame('rules', $lens([]));
        $this->assertSame('rules', $lens(['lens' => 'RULES']));
        $this->assertSame('usage', $lens(['lens' => 'usage']));
        $this->assertSame('none', $lens(['lens' => 'none']));

        set_user_preference(constants::PREF_CENTRAL_DISPLAY, json_encode(['structure' => ['lens' => '<b>usage</b>']]));
        $this->assertSame('none', $lens([]));
        $this->assertSame('relations', $lens(['lens' => 'relations']));
    }

    /**
     * Admin text with markup reaches the props JSON with its angle brackets encoded, and decodes whole.
     *
     * @return void
     */
    public function test_the_props_keep_markup_inert_and_decode_whole(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        // A bare "<" followed by a space is not a tag, so format_string() keeps it.
        $framework = $ccg->create_framework(['shortname' => "A < B & 'C' > D"]);

        $data = $this->export(['frameworkid' => (int) $framework->get('id'), 'view' => 'map']);

        foreach (['<', '>', '&', "'"] as $character) {
            $this->assertStringNotContainsString($character, $data['mapprops']);
        }
        $this->assertSame("A < B & 'C' > D", $data['decoded']['framework']['shortname']);
    }

    /**
     * An unknown framework falls back to the first visible one, in the map as in the list.
     *
     * @return void
     */
    public function test_an_unknown_framework_falls_back_to_the_first_visible_one(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $ccg->create_framework(['shortname' => 'Aaa hidden', 'visible' => 0]);
        $visible = $ccg->create_framework(['shortname' => 'Bbb visible']);

        $data = $this->export(['frameworkid' => 999999, 'view' => 'map']);

        $this->assertSame((int) $visible->get('id'), $data['decoded']['framework']['id']);
        // The map draws a framework without competencies: its panel adds the first one.
        $this->assertTrue($data['isworkspace']);
        $this->assertSame(0, $data['decoded']['roots']['total']);
    }

    /**
     * A reader who may not manage gets the map without the rule modules, canmanage false, and nodes
     * that are not manageable; the administrator (the control) manages the same framework.
     *
     * @return void
     */
    public function test_a_reader_gets_no_management(): void {
        $this->resetAfterTest();
        $framework = $this->one_framework();
        $frameworkid = (int) $framework->get('id');
        $this->assertTrue($this->export(['frameworkid' => $frameworkid, 'view' => 'map'])['decoded']['framework']['canmanage']);

        // The authenticated user's role holds competencyview at the site, and not competencymanage.
        $this->setUser($this->getDataGenerator()->create_user());
        $data = $this->export(['frameworkid' => $frameworkid, 'view' => 'map']);

        $this->assertFalse($data['decoded']['framework']['canmanage']);
        $this->assertSame([], $data['decoded']['rulesmodules']);
        $this->assertFalse($data['decoded']['roots']['items'][0]['canmanage']);
        $this->assertSame(0, $data['canmanage']);
        // Nothing to create, import or delete, and no Structures menu: the reader sees no hidden structure either.
        $this->assertFalse($data['decoded']['framework']['deletable']);
        $this->assertSame(
            ['contextid' => \context_system::instance()->id, 'cancreate' => false, 'canscalespage' => false],
            $data['decoded']['structures']
        );
        $this->assertNull($data['structuresmenu']);
    }

    /**
     * The locked category entry maps a framework of a descendant category, as its list lists it; the
     * unlocked category mode does not, and falls back to the category's own framework.
     *
     * @return void
     */
    public function test_the_locked_entry_maps_a_descendant_framework_as_its_list_lists_it(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $generator = $this->getDataGenerator();
        $ccg = $generator->get_plugin_generator('core_competency');
        $parent = $generator->create_category(['name' => 'Parent']);
        $child = $generator->create_category(['name' => 'Child', 'parent' => $parent->id]);
        $inparent = $ccg->create_framework([
            'shortname' => 'Framework in parent',
            'contextid' => context_coursecat::instance((int) $parent->id)->id,
        ]);
        $inchild = $ccg->create_framework([
            'shortname' => 'Framework in child',
            'contextid' => context_coursecat::instance((int) $child->id)->id,
        ]);
        $pane = [
            'contexttype' => 'coursecat',
            'categoryid' => (int) $parent->id,
            'frameworkid' => (int) $inchild->get('id'),
            'view' => 'map',
        ];

        $locked = $this->export($pane + ['locked' => 1]);
        $this->assertSame((int) $inchild->get('id'), $locked['decoded']['framework']['id']);
        $this->assertSame(['Framework in child', 'Framework in parent'], array_column($locked['frameworks'], 'name'));

        $unlocked = $this->export($pane + ['locked' => 0]);
        $this->assertSame((int) $inparent->get('id'), $unlocked['decoded']['framework']['id']);
        $this->assertSame(['Framework in parent'], array_column($unlocked['frameworks'], 'name'));
    }

    /**
     * A viewer who may not read competencies gets neither view: the tab is unavailable, the map asked
     * for by name included, as core's dynamic-tabs service and central.php both ask it first.
     *
     * @return void
     */
    public function test_a_viewer_without_competencyview_gets_no_view(): void {
        $this->resetAfterTest();
        $framework = $this->one_framework();
        $user = $this->getDataGenerator()->create_user();
        $pane = ['contexttype' => 'system', 'categoryid' => 0, 'frameworkid' => (int) $framework->get('id')];
        $this->setUser($user);
        // Control: the authenticated user reads competencies at the site, in both views.
        $this->assertTrue((new structure($pane + ['view' => 'map']))->is_available());
        $this->assertTrue((new structure($pane + ['view' => 'list']))->is_available());

        $roleid = $this->getDataGenerator()->create_role();
        $systemid = \context_system::instance()->id;
        assign_capability('moodle/competency:competencyview', CAP_PROHIBIT, $roleid, $systemid);
        role_assign($roleid, (int) $user->id, $systemid);
        accesslib_clear_all_caches_for_unit_testing();

        $this->assertFalse((new structure($pane + ['view' => 'map']))->is_available());
        $this->assertFalse((new structure($pane + ['view' => 'list']))->is_available());
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('nopermissiontoaccesspage', 'error'));
        (new structure($pane + ['view' => 'map']))->require_access();
    }

    /**
     * Course-category mode without a category draws no map, whatever the view.
     *
     * @return void
     */
    public function test_category_mode_without_a_category_draws_no_map(): void {
        $this->resetAfterTest();
        $this->one_framework();

        $data = $this->export(['contexttype' => 'coursecat', 'categoryid' => 0, 'view' => 'map']);

        $this->assertTrue($data['needscategoryselection']);
        $this->assertFalse($data['isworkspace']);
        $this->assertSame('', $data['mapprops']);
    }

    /**
     * The deep link reveals a competency of the framework shown, and only that: another framework's
     * competency, a missing one and none at all reveal nothing.
     *
     * @return void
     */
    public function test_the_deep_link_reveals_only_a_competency_of_the_framework_shown(): void {
        $this->resetAfterTest();
        $framework = $this->one_framework();
        $frameworkid = (int) $framework->get('id');
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $parent = $ccg->create_competency(['competencyframeworkid' => $frameworkid, 'shortname' => 'Parent']);
        $leaf = $ccg->create_competency(['competencyframeworkid' => $frameworkid, 'parentid' => $parent->get('id')]);
        $other = $ccg->create_framework(['shortname' => 'Other']);
        $elsewhere = $ccg->create_competency(['competencyframeworkid' => $other->get('id')]);
        $pane = ['frameworkid' => $frameworkid, 'view' => 'map'];

        $this->assertSame(
            ['id' => (int) $leaf->get('id'), 'pathids' => [(int) $parent->get('id')]],
            $this->export($pane + ['competencyid' => (int) $leaf->get('id')])['decoded']['reveal']
        );
        $this->assertSame(
            ['id' => (int) $parent->get('id'), 'pathids' => []],
            $this->export($pane + ['competencyid' => (string) $parent->get('id')])['decoded']['reveal']
        );

        $this->assertNull($this->export($pane + ['competencyid' => (int) $elsewhere->get('id')])['decoded']['reveal']);
        $this->assertNull($this->export($pane + ['competencyid' => 999999])['decoded']['reveal']);
        $this->assertNull($this->export($pane + ['competencyid' => -5])['decoded']['reveal']);
        $this->assertNull($this->export($pane)['decoded']['reveal']);
        // Control: the other framework's map reveals its own competency.
        $this->assertSame(
            (int) $elsewhere->get('id'),
            $this->export(['frameworkid' => (int) $other->get('id'), 'view' => 'map',
                'competencyid' => (int) $elsewhere->get('id')])['decoded']['reveal']['id']
        );
    }

    /**
     * The map hands the page the canvas's strings; the list, which does not draw it, hands none.
     *
     * @return void
     */
    public function test_only_the_map_hands_the_canvas_strings_to_the_page(): void {
        global $PAGE;
        $this->resetAfterTest();
        $framework = $this->one_framework();
        // The strings handed so far, by component, as the requirements manager holds them for the footer.
        $handed = static function () use (&$PAGE): array {
            $property = new \ReflectionProperty($PAGE->requires, 'stringsforjs');
            return array_map('array_keys', $property->getValue($PAGE->requires));
        };

        $this->export(['frameworkid' => (int) $framework->get('id'), 'view' => 'list', 'classic' => 1]);
        $this->assertArrayNotHasKey('hubv2_moverule_oldparent', array_flip($handed()['local_dimensions'] ?? []));

        $this->export(['frameworkid' => (int) $framework->get('id'), 'view' => 'outline']);
        $strings = $handed();
        $this->assertSame([], array_values(array_diff(hub_map::STRINGS, $strings['local_dimensions'] ?? [])));
        $this->assertSame([], array_values(array_diff(hub_map::OTHER_STRINGS['tool_lp'], $strings['tool_lp'] ?? [])));
        $this->assertSame([], array_values(array_diff(hub_map::OTHER_STRINGS['core'], $strings['core'] ?? [])));
    }

    /**
     * The root's facts: the framework's name, ID number, context, visibility, scale with the framework's default and
     * proficient values, the taxonomy term of each level it names with core's fallback past them, and whether core
     * would delete it.
     *
     * @return void
     */
    public function test_the_root_says_what_the_framework_is(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $generator = $this->getDataGenerator();
        $ccg = $generator->get_plugin_generator('core_competency');
        $scale = $generator->create_scale(['name' => 'Three steps', 'scale' => 'Not yet,Nearly,Done']);
        $category = $generator->create_category(['name' => 'Engineering']);
        $framework = $ccg->create_framework([
            'shortname' => 'Rooted',
            'idnumber' => 'ROOT1',
            'visible' => 0,
            'contextid' => context_coursecat::instance((int) $category->id)->id,
            'scaleid' => $scale->id,
            'scaleconfiguration' => json_encode([
                ['scaleid' => (int) $scale->id],
                ['id' => 2, 'scaledefault' => 1, 'proficient' => 0],
                ['id' => 3, 'scaledefault' => 0, 'proficient' => 1],
            ]),
            'taxonomies' => 'domain,skill',
        ]);
        $ccg->create_competency(['competencyframeworkid' => $framework->get('id')]);

        $facts = $this->export([
            'contexttype' => 'coursecat',
            'categoryid' => (int) $category->id,
            'frameworkid' => (int) $framework->get('id'),
            'view' => 'map',
        ])['decoded']['framework'];

        $this->assertSame('Rooted', $facts['shortname']);
        $this->assertSame('ROOT1', $facts['idnumber']);
        $this->assertFalse($facts['visible']);
        $this->assertSame('Engineering', $facts['contextname']);
        $this->assertSame('Three steps', $facts['scale']['name']);
        $this->assertSame(['Not yet', 'Nearly', 'Done'], array_column($facts['scale']['values'], 'name'));
        $this->assertSame([1, 2, 3], array_column($facts['scale']['values'], 'id'));
        $this->assertSame([false, true, false], array_column($facts['scale']['values'], 'isdefault'));
        $this->assertSame([false, false, true], array_column($facts['scale']['values'], 'proficient'));
        $this->assertSame(
            [get_string('taxonomy_domain', 'core_competency'), get_string('taxonomy_skill', 'core_competency')],
            $facts['taxonomies']
        );
        $this->assertSame(get_string('taxonomy_competency', 'core_competency'), $facts['taxonomyfallback']);
        $this->assertTrue($facts['canmanage']);
        $this->assertTrue($facts['deletable']);

        // Control: a competency in use makes it not deletable, as the Structures tab says.
        $course = $generator->create_course();
        $competency = \core_competency\competency::get_record(['competencyframeworkid' => $framework->get('id')]);
        $ccg->create_course_competency(['courseid' => (int) $course->id, 'competencyid' => (int) $competency->get('id')]);
        $again = $this->export([
            'contexttype' => 'coursecat',
            'categoryid' => (int) $category->id,
            'frameworkid' => (int) $framework->get('id'),
            'view' => 'map',
        ])['decoded']['framework'];
        $this->assertFalse($again['deletable']);
    }

    /**
     * The Structures menu: outside classic mode a manager gets it with no framework at all, and it carries the "show
     * hidden" toggle in place of the standalone one; in classic mode the list keeps the standalone toggle.
     *
     * @return void
     */
    public function test_the_structures_menu_replaces_the_tab_outside_classic_mode(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');

        $empty = $this->export(['frameworkid' => 0]);
        $this->assertFalse($empty['hasframeworks']);
        $this->assertTrue($empty['structuresmenu']['cancreate']);
        $this->assertFalse($empty['structuresmenu']['hashidden']);
        $this->assertSame(\context_system::instance()->id, $empty['contextid']);
        // Control: the classic hub's empty tab has no menu; its Structures tab creates.
        $this->assertNull($this->export(['frameworkid' => 0, 'classic' => 1])['structuresmenu']);

        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $ccg->create_framework(['shortname' => 'Shown']);
        $ccg->create_framework(['shortname' => 'Tucked', 'visible' => 0]);
        $workspace = $this->export([]);
        $this->assertTrue($workspace['structuresmenu']['hashidden']);
        $this->assertNull($workspace['showhiddentoggle']);
        $list = $this->export(['classic' => 1, 'view' => 'list']);
        $this->assertNull($list['structuresmenu']);
        $this->assertNotNull($list['showhiddentoggle']);
    }

    /**
     * framework_facts() on the framework shapes the generators make: none, two and four named levels. Its taxonomy
     * list is read through core's getter, which returns an array; a PHP warning here fails the test (it took the
     * hub's page down under Behat while the one-level answer was right by accident).
     *
     * @return void
     */
    public function test_framework_facts_read_every_named_level_without_a_warning(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $term = static fn(string $constant): string => get_string('taxonomy_' . $constant, 'core_competency');
        $cases = [
            // The generators' default, which every Behat background of the hub creates.
            [[], [$term('competency')]],
            [['taxonomies' => 'domain,skill'], [$term('domain'), $term('skill')]],
            [
                ['taxonomies' => 'domain,competency,indicator,behaviour'],
                [$term('domain'), $term('competency'), $term('indicator'), $term('behaviour')],
            ],
        ];
        set_error_handler(static function (int $errno, string $message, string $file, int $line): bool {
            throw new \ErrorException($message, 0, $errno, $file, $line);
        });
        try {
            foreach ($cases as $index => [$record, $expected]) {
                $framework = $ccg->create_framework($record + ['shortname' => "Shape {$index}"]);
                $facts = hub_map::framework_facts($framework, 0, true);
                $this->assertSame($expected, $facts['taxonomies'], "Shape {$index}");
                $this->assertSame($term('competency'), $facts['taxonomyfallback']);
            }
        } finally {
            restore_error_handler();
        }
    }
}
