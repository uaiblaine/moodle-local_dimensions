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

use local_dimensions\output\dynamictabs\structure;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Pins what the Competencies tab's three sides must agree on for the workspace views, read out of
 * their files: the server (structure::VIEWS), the tab's AMD modules and the React workspace.
 *
 * Nothing runs either script under PHPUnit, so a name on one side only would pass every other gate
 * and break the page: a view the switch offers but the server refuses, a layout event the workspace
 * never hears, a width the stylesheet never reads.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class hub_workspace_contract_test extends \basic_testcase {
    /**
     * A file of the plugin.
     *
     * @param string $path The path from the plugin's root.
     * @return string The source.
     */
    private function source(string $path): string {
        $source = file_get_contents(__DIR__ . '/../../' . $path);
        $this->assertIsString($source, "{$path} was not read");
        return $source;
    }

    /**
     * The quoted strings of a constant's array literal.
     *
     * @param string $source The source.
     * @param string $name The constant's name.
     * @return string[] The values, in order.
     */
    private function list_of(string $source, string $name): array {
        $this->assertSame(
            1,
            preg_match('/const ' . $name . '(?::[^=]+)? = \[([^\]]*)\]/', $source, $match),
            "No {$name} list was found."
        );
        preg_match_all("/'([a-z]+)'/", $match[1], $values);
        return $values[1];
    }

    /**
     * The view switch, the saved preference and the workspace know the same views as the server.
     *
     * Change that must make it fail: drop 'both' from VIEWS in amd/src/central/structure.js.
     *
     * @return void
     */
    public function test_every_side_knows_the_same_views(): void {
        $this->assertSame(['list', 'outline', 'map', 'both'], structure::VIEWS);
        $this->assertSame(['outline', 'map', 'both'], structure::WORKSPACE_VIEWS);
        $this->assertSame([], array_values(array_diff(structure::WORKSPACE_VIEWS, structure::VIEWS)));

        $switch = $this->source('amd/src/central/structure.js');
        $this->assertSame(structure::VIEWS, $this->list_of($switch, 'VIEWS'));
        $this->assertSame(structure::WORKSPACE_VIEWS, $this->list_of($switch, 'WORKSPACE_VIEWS'));
        $this->assertSame(structure::VIEWS, $this->list_of($this->source('amd/src/central/preferences.js'), 'STRUCTURE_VIEWS'));
        $this->assertSame(structure::WORKSPACE_VIEWS, $this->list_of($this->source('js/esm/src/hubv2/selection.ts'), 'LAYOUTS'));
    }

    /**
     * The switch tells the workspace its layout by the event the workspace listens for.
     *
     * Change that must make it fail: rename LAYOUT_EVENT in js/esm/src/hubv2/selection.ts.
     *
     * @return void
     */
    public function test_the_switch_and_the_workspace_name_the_same_layout_event(): void {
        $pattern = "/const LAYOUT_EVENT = '([^']+)';/";
        $this->assertSame(1, preg_match($pattern, $this->source('amd/src/central/structure.js'), $switch));
        $this->assertSame(1, preg_match($pattern, $this->source('js/esm/src/hubv2/selection.ts'), $workspace));
        $this->assertSame($switch[1], $workspace[1]);
        // The switch sends it and the workspace listens for it, each by its constant.
        $this->assertStringContainsString('new CustomEvent(LAYOUT_EVENT', $this->source('amd/src/central/structure.js'));
        $this->assertStringContainsString('addEventListener(LAYOUT_EVENT', $this->source('js/esm/src/hubv2/Hub.tsx'));
    }

    /**
     * An outline row never overflows its pane, and its name is the last thing to give way.
     *
     * The list scrolls vertically only; each row clips what is left after its indent; the meta shrinks
     * a hundred times faster than the name, which truncates with an ellipsis. With the meta at
     * `flex: none`, a rule's label pushed the name out of a 264 px outline.
     *
     * Change that must make it fail: set .local-dimensions-hubv2-ometa back to `flex: none`.
     *
     * @return void
     */
    public function test_an_outline_row_keeps_its_name_and_stays_in_its_pane(): void {
        $css = $this->source('styles.css');
        $rule = function (string $selector) use ($css): string {
            $this->assertSame(
                1,
                preg_match('/\n' . preg_quote($selector, '/') . ' \{([^}]*)\}/', $css, $m),
                "No {$selector} rule was found."
            );
            return $m[1];
        };
        $this->assertStringContainsString('overflow-x: hidden;', $rule('.local-dimensions-hubv2-tree'));
        $row = $rule('.local-dimensions-hubv2-orow');
        $this->assertStringContainsString('overflow: hidden;', $row);
        $this->assertStringContainsString('min-width: 0;', $row);
        $name = $rule('.local-dimensions-hubv2-oname');
        foreach (['flex: 1 1 auto;', 'min-width: 0;', 'text-overflow: ellipsis;', 'white-space: nowrap;'] as $declaration) {
            $this->assertStringContainsString($declaration, $name);
        }
        $meta = $rule('.local-dimensions-hubv2-ometa');
        $this->assertStringContainsString('flex: 0 100 auto;', $meta);
        $this->assertStringContainsString('min-width: 0;', $meta);
        // The rule is a mark in the row: its label is never drawn there, only said in the description.
        $outline = $this->source('js/esm/src/hubv2/Outline.tsx');
        $this->assertDoesNotMatchRegularExpression('/ometa-rule[^\n]*\n?[^\n]*\{part\.label\}/', $outline);
    }

    /**
     * The splitter's width reaches the stylesheet through the one custom property both name.
     *
     * The transport has three ends: the stylesheet declares the property's default on the workspace's
     * root, `.local-dimensions-hubv2-layout`, at the splitter's own default (outlinewidth.ts); the
     * workspace writes the chosen width inline on that same root, which wins over the sheet; and the
     * Both layout's outline reads it. A name or a default changed at one end only would leave the
     * outline at a width nobody chose, and no other gate reads the chain.
     *
     * Changes that must make it fail: rename the property in the Both layout's outline rule of
     * styles.css; move the inline style off the root's element in Hub.tsx; change the default at one end.
     *
     * @return void
     */
    public function test_the_outline_width_reaches_the_stylesheet(): void {
        $hub = $this->source('js/esm/src/hubv2/Hub.tsx');
        $this->assertSame(1, preg_match("/const WIDTH_PROPERTY = '(--local-dimensions-hubv2-[a-z-]+)';/", $hub, $m));
        $property = preg_quote($m[1], '/');
        // The workspace writes it inline on the root it renders, the element the sheet declares it on
        // (\x60 is the template literal's quote, which the coding standard keeps out of PHP strings).
        $this->assertMatchesRegularExpression('/const widthstyle = \{\[WIDTH_PROPERTY\]: \x60\$\{outlineWidth\}px\x60\}/', $hub);
        $this->assertMatchesRegularExpression(
            "/const rootclasses = \['local-dimensions-hubv2-layout',[^\n]*\n(?:[^\n]*\n){0,8}"
                . "\s*<div className=\{rootclasses\.join\(' '\)\} style=\{widthstyle\}>/",
            $hub
        );

        $css = $this->source('styles.css');
        $this->assertSame(1, preg_match('/\n\.local-dimensions-hubv2-layout \{([^}]*)\}/', $css, $root));
        $this->assertSame(1, preg_match('/' . $property . ': (\d+)px;/', $root[1], $default), 'The root declares no default.');
        $this->assertSame(
            1,
            preg_match('/initial: (\d+)/', $this->source('js/esm/src/hubv2/outlinewidth.ts'), $initial)
        );
        $this->assertSame($initial[1], $default[1], 'The sheet\'s default and the splitter\'s differ.');
        $this->assertMatchesRegularExpression(
            '/\.local-dimensions-hubv2-layout-both \.local-dimensions-hubv2-outline \{[^}]*width: var\(' . $property . '\);/',
            $css
        );
    }
}
