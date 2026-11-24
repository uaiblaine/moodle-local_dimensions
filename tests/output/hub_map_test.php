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
 * Tests for the map view's strings, its deep link and the retired hubv2.php address.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\output;

use advanced_testcase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * What the map view hands the canvas besides its props, and where a kept link to the old page lands.
 */
#[CoversClass(hub_map::class)]
final class hub_map_test extends advanced_testcase {
    /**
     * A list of quoted strings in a TypeScript array literal.
     *
     * @param string $source The source.
     * @param string $name The constant's name.
     * @return string The array literal's body.
     */
    private function ts_array(string $source, string $name): string {
        $this->assertSame(
            1,
            preg_match('/export const ' . $name . '(?::[^=]+)? = \[(.*?)\n\];/s', $source, $m),
            "strings.ts declares no {$name} list"
        );
        return $m[1];
    }

    /**
     * The canvas reads exactly the strings the tab hands over, and every one of them exists.
     *
     * The map view passes hub_map::STRINGS and OTHER_STRINGS to strings_for_js(), and the canvas
     * reads KEYS and OTHER of js/esm/src/hubv2/strings.ts from that cache in one call; a key on one
     * side only is a string fetched by a request, or one handed over for nothing.
     *
     * @return void
     */
    public function test_the_canvas_reads_the_strings_the_tab_hands_over(): void {
        $source = (string) file_get_contents(__DIR__ . '/../../js/esm/src/hubv2/strings.ts');

        preg_match_all("/'([a-z0-9_]+)'/", $this->ts_array($source, 'KEYS'), $keys);
        $this->assertSame(hub_map::STRINGS, $keys[1]);

        preg_match_all("/\['([a-z_]+)', '([a-z0-9_]+)'\]/", $this->ts_array($source, 'OTHER'), $pairs, PREG_SET_ORDER);
        $other = [];
        foreach ($pairs as [, $component, $key]) {
            $other[$component][] = $key;
        }
        $this->assertSame(hub_map::OTHER_STRINGS, $other);

        foreach (['en', 'pt_br'] as $lang) {
            $string = [];
            include(__DIR__ . "/../../lang/{$lang}/local_dimensions.php");
            $missing = array_values(array_diff(hub_map::STRINGS, array_keys($string)));
            $this->assertSame([], $missing, "lang/{$lang} lacks strings the canvas reads");
            // The tab's own strings for the view switch and the mount's placeholder.
            $tabkeys = [
                'managecompetencies_view',
                'managecompetencies_view_list',
                'managecompetencies_view_outline',
                'managecompetencies_view_map',
                'managecompetencies_view_both',
                'hubv2_loading',
                // The Structures menu and the context bar's link to the other mode.
                'central_frameworks_tab',
                'hubv2_structures_import',
                'hubv2_structures_scales',
                'central_view_classic',
                'central_view_workspace',
            ];
            foreach ($tabkeys as $key) {
                $this->assertArrayHasKey($key, $string, "lang/{$lang} lacks {$key}");
            }
            // The retired page's own strings are gone with it, and the panel lists every relation (H10).
            foreach (['hubv2', 'hubv2_apply', 'hubv2_listview', 'hubv2_related_offcanvas'] as $key) {
                $this->assertArrayNotHasKey($key, $string, "lang/{$lang} still carries {$key}");
            }
        }
        $manager = get_string_manager();
        foreach (hub_map::OTHER_STRINGS as $component => $componentkeys) {
            foreach ($componentkeys as $key) {
                $this->assertTrue($manager->string_exists($key, $component), "{$component}:{$key} does not exist");
            }
        }
    }

    /**
     * The deep link's target is decided by the competency's own framework, never by the link.
     *
     * @return void
     */
    public function test_reveal_target_reads_the_competency_s_own_framework(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $shown = $ccg->create_framework();
        $other = $ccg->create_framework();
        $root = $ccg->create_competency(['competencyframeworkid' => $shown->get('id')]);
        $child = $ccg->create_competency(['competencyframeworkid' => $shown->get('id'), 'parentid' => $root->get('id')]);
        $grandchild = $ccg->create_competency(['competencyframeworkid' => $shown->get('id'), 'parentid' => $child->get('id')]);
        $foreign = $ccg->create_competency(['competencyframeworkid' => $other->get('id')]);

        $this->assertSame(
            ['id' => (int) $grandchild->get('id'), 'pathids' => [(int) $root->get('id'), (int) $child->get('id')]],
            hub_map::reveal_target($shown, (int) $grandchild->get('id'))
        );
        $this->assertSame(
            ['id' => (int) $root->get('id'), 'pathids' => []],
            hub_map::reveal_target($shown, (int) $root->get('id'))
        );
        $this->assertNull(hub_map::reveal_target($shown, (int) $foreign->get('id')));
        $this->assertNull(hub_map::reveal_target($shown, 0));
        $this->assertNull(hub_map::reveal_target($shown, -1));
        $this->assertNull(hub_map::reveal_target($shown, (int) $foreign->get('id') + 1000));
        // Control: in its own framework the foreign competency is revealed.
        $this->assertSame((int) $foreign->get('id'), hub_map::reveal_target($other, (int) $foreign->get('id'))['id']);
    }

    /**
     * A kept link to hubv2.php lands on the hub's map view, carrying only what it gave.
     *
     * @return void
     */
    public function test_the_retired_address_lands_on_the_map_with_what_it_carried(): void {
        $this->resetAfterTest();
        $bare = hub_map::legacy_url('', 0, 0);
        $this->assertStringEndsWith('/local/dimensions/central.php', $bare->out_omit_querystring());
        $this->assertSame(['tab' => 'structure', 'view' => 'map'], $bare->params());

        $this->assertSame(
            ['tab' => 'structure', 'view' => 'map', 'contexttype' => 'coursecat', 'categoryid' => '7', 'frameworkid' => '23'],
            hub_map::legacy_url('coursecat', 7, 23)->params()
        );
        $this->assertSame(
            ['tab' => 'structure', 'view' => 'map', 'contexttype' => 'system'],
            hub_map::legacy_url('system', 0, -4)->params()
        );
        // An unknown context type is not carried: the hub would read it as the site anyway.
        $this->assertArrayNotHasKey('contexttype', hub_map::legacy_url('module', 0, 0)->params());
    }

    /**
     * hubv2.php passes the hub's own gate before it redirects, so whoever the old page refused is
     * refused the same way and is never told where the redirect would go.
     *
     * A page that redirects cannot be run under PHPUnit (redirect() throws there), so this reads the
     * script: the gate and the competencies check come first, and nothing is written before them.
     * settings_registration_test holds who passes the gate.
     *
     * @return void
     */
    public function test_the_retired_page_passes_the_hub_gate_before_it_redirects(): void {
        $source = (string) file_get_contents(__DIR__ . '/../../hubv2.php');

        $gate = strpos($source, "admin_externalpage_setup('local_dimensions_central');");
        $enabled = strpos($source, 'api::require_enabled();');
        $redirect = strpos($source, 'redirect(');
        $this->assertNotFalse($gate, 'hubv2.php does not set up the hub\'s Site administration page');
        $this->assertNotFalse($enabled, 'hubv2.php does not require competencies to be enabled');
        $this->assertNotFalse($redirect, 'hubv2.php does not redirect');
        $this->assertLessThan($enabled, $gate);
        $this->assertLessThan($redirect, $enabled);
        // Nothing before the gate reads a parameter or writes output.
        $before = substr($source, 0, $gate);
        foreach (['optional_param', 'required_param', 'echo', '$OUTPUT', 'print'] as $call) {
            $this->assertStringNotContainsString($call, $before, "hubv2.php calls {$call} before its gate");
        }
        $this->assertStringNotContainsString('local_dimensions_centralv2', $source);
    }
}
