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

namespace local_dimensions;

/**
 * Tests for helper::scaleconfig_is_complete.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\helper::scaleconfig_is_complete
 */
final class helper_scaleconfig_test extends \advanced_testcase {
    /**
     * A config with at least one default and one proficient value is complete.
     *
     * @return void
     */
    public function test_complete(): void {
        $json = json_encode([
            ['scaleid' => 2],
            ['id' => 1, 'scaledefault' => 1, 'proficient' => 0],
            ['id' => 2, 'scaledefault' => 0, 'proficient' => 1],
        ]);
        $this->assertTrue(helper::scaleconfig_is_complete($json));
    }

    /**
     * Missing default, missing proficient, or malformed JSON is incomplete.
     *
     * @return void
     */
    public function test_incomplete(): void {
        $nodefault = json_encode([
            ['scaleid' => 2],
            ['id' => 1, 'scaledefault' => 0, 'proficient' => 1],
        ]);
        $noproficient = json_encode([
            ['scaleid' => 2],
            ['id' => 1, 'scaledefault' => 1, 'proficient' => 0],
        ]);
        $this->assertFalse(helper::scaleconfig_is_complete($nodefault));
        $this->assertFalse(helper::scaleconfig_is_complete($noproficient));
        $this->assertFalse(helper::scaleconfig_is_complete(''));
        $this->assertFalse(helper::scaleconfig_is_complete('not json'));
    }
}
