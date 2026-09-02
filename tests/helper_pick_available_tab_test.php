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
 * Tests for the Competency hub's active-tab fallback.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions;

use basic_testcase;

/**
 * Pure logic: which tab the hub opens given what the viewer may see.
 *
 * @covers \local_dimensions\helper
 */
final class helper_pick_available_tab_test extends basic_testcase {
    /**
     * The requested tab wins whenever it is available, whatever its position in the strip.
     *
     * @return void
     */
    public function test_the_requested_tab_wins_when_available(): void {
        $available = ['frameworks' => true, 'structure' => true, 'plans' => true];
        $this->assertSame('plans', helper::pick_available_tab($available, 'plans'));
        $this->assertSame('structure', helper::pick_available_tab($available, 'structure'));
    }

    /**
     * An unavailable request falls back to the first available tab in strip order.
     *
     * @return void
     */
    public function test_an_unavailable_request_falls_back_in_strip_order(): void {
        $available = ['frameworks' => false, 'structure' => true, 'plans' => true];
        $this->assertSame('structure', helper::pick_available_tab($available, 'frameworks'));

        $onlyplans = ['frameworks' => false, 'structure' => false, 'plans' => true];
        $this->assertSame('plans', helper::pick_available_tab($onlyplans, 'frameworks'));
        $this->assertSame('plans', helper::pick_available_tab($onlyplans, 'structure'));
    }

    /**
     * A request the strip does not know, or an empty strip, cannot pick a tab by accident.
     *
     * @return void
     */
    public function test_unknown_requests_and_empty_strips(): void {
        $available = ['frameworks' => true, 'structure' => false, 'plans' => false];
        $this->assertSame('frameworks', helper::pick_available_tab($available, 'nosuchtab'));

        $none = ['frameworks' => false, 'structure' => false, 'plans' => false];
        $this->assertSame('', helper::pick_available_tab($none, 'plans'));
        $this->assertSame('', helper::pick_available_tab([], 'frameworks'));
    }
}
