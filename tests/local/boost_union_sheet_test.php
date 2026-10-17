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
 * Guards styles_boost_union.css, the sheet core loads only under Boost Union and its children.
 *
 * Nothing in the pipeline computes a layout, so the hero's offset under a smart menu is held by
 * reading the rule: both learner pages, only while no menu bar is drawn, back to the navbar height.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class boost_union_sheet_test extends \basic_testcase {
    /**
     * A smart menu without a menu bar puts #page back at the navbar height on both learner pages.
     *
     * Boost Union's ".theme-boost-union-smartmenu .navbar.fixed-top ~ #page.drawers" adds the menu
     * bar's 40px whether or not a menu bar is drawn, which left a strip between the navbar and the hero.
     *
     * @return void
     */
    public function test_the_hero_meets_the_navbar_under_a_smart_menu_without_a_menu_bar(): void {
        global $CFG;
        $css = file_get_contents($CFG->dirroot . '/local/dimensions/styles_boost_union.css');
        $this->assertNotFalse($css);
        $css = preg_replace('~/\*.*?\*/~s', '', $css);

        foreach (['viewplan', 'viewcompetency'] as $page) {
            $selector = 'body.local-dimensions-' . $page
                . '.theme-boost-union-smartmenu:not(:has(.boost-union-menubar)) #page.drawers';
            $this->assertStringContainsString($selector, $css, "The {$page} page must be covered.");
        }
        $this->assertMatchesRegularExpression(
            '~#page\.drawers\s*\{\s*margin-top:\s*60px;\s*\}~',
            $css,
            'Without a menu bar #page starts at the navbar height.'
        );
    }
}
