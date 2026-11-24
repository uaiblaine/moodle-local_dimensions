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
 * Tests for the Competencies tab's two views: which one shows, and what the map receives.
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
 * The list or the map: the URL's view, else the saved one, else the list; and the map lists exactly
 * what the list lists, because both come from the one resolution the tab makes.
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
     * The pane's view beats the saved one, which beats the list; anything else counts as none.
     *
     * @return void
     */
    public function test_resolve_view_orders_the_url_the_saved_view_and_the_default(): void {
        $this->assertSame('map', structure::resolve_view('map', 'list'));
        $this->assertSame('list', structure::resolve_view('list', 'map'));
        $this->assertSame('map', structure::resolve_view('', 'map'));
        $this->assertSame('list', structure::resolve_view('', 'list'));
        $this->assertSame('list', structure::resolve_view('', ''));
        // An unknown value is no value: the saved view, else the list.
        $this->assertSame('map', structure::resolve_view('canvas', 'map'));
        $this->assertSame('list', structure::resolve_view('canvas', 'garbage'));
        $this->assertSame('list', structure::resolve_view('MAP', ''));
    }

    /**
     * Through the tab: the URL's view wins over the saved one, the saved one over the list.
     *
     * @return void
     */
    public function test_the_tab_shows_the_url_view_then_the_saved_view_then_the_list(): void {
        $this->resetAfterTest();
        $framework = $this->one_framework();
        $frameworkid = (int) $framework->get('id');

        $default = $this->export(['frameworkid' => $frameworkid]);
        $this->assertSame('list', $default['view']);
        $this->assertFalse($default['ismap']);
        $this->assertSame('', $default['mapprops']);

        $this->save_view('map');
        $saved = $this->export(['frameworkid' => $frameworkid]);
        $this->assertSame('map', $saved['view']);
        $this->assertTrue($saved['ismap']);
        $this->assertTrue($saved['ismapview']);
        $this->assertFalse($saved['islistview']);

        $this->assertSame('list', $this->export(['frameworkid' => $frameworkid, 'view' => 'list'])['view']);
        $this->assertSame('map', $this->export(['frameworkid' => $frameworkid, 'view' => 'bogus'])['view']);

        $this->save_view('<script>');
        $this->assertSame('list', $this->export(['frameworkid' => $frameworkid, 'view' => 'bogus'])['view']);
        $this->assertSame('map', $this->export(['frameworkid' => $frameworkid, 'view' => 'map'])['view']);
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
            ['framework', 'roots', 'pagesize', 'rulesmodules', 'courseoutcomes', 'moduleoutcomes', 'rtl', 'reveal'],
            array_keys($props)
        );
        $this->assertSame([
            'id' => $betaid,
            'shortname' => 'Beta',
            'idnumber' => 'BETA',
            'competencycount' => 28,
            'canmanage' => true,
        ], $props['framework']);
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
        $this->assertTrue($data['ismap']);
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
        $this->assertFalse($data['ismap']);
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

        $this->export(['frameworkid' => (int) $framework->get('id'), 'view' => 'list']);
        $this->assertArrayNotHasKey('hubv2_moverule_oldparent', array_flip($handed()['local_dimensions'] ?? []));

        $this->export(['frameworkid' => (int) $framework->get('id'), 'view' => 'map']);
        $strings = $handed();
        $this->assertSame([], array_values(array_diff(hub_map::STRINGS, $strings['local_dimensions'] ?? [])));
        $this->assertSame([], array_values(array_diff(hub_map::OTHER_STRINGS['tool_lp'], $strings['tool_lp'] ?? [])));
        $this->assertSame([], array_values(array_diff(hub_map::OTHER_STRINGS['core'], $strings['core'] ?? [])));
    }
}
