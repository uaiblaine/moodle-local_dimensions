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
 * Tests for the two percentage rules a progress ring or bar is allowed to show.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\calculator::progress_percentage
 * @covers     \local_dimensions\calculator::clamp_percentage
 */
final class calculator_percentage_test extends \advanced_testcase {
    /**
     * Counts and the percentage the ring may honestly show for them.
     *
     * @return array The cases, each completed, total and expected percentage.
     */
    public static function progress_provider(): array {
        return [
            'nothing to measure' => [0, 0, null],
            'none done' => [0, 4, 0],
            'half done' => [2, 4, 50],
            'all done' => [4, 4, 100],
            'one short of a round hundred' => [199, 200, 99],
            'one short, rounding further up' => [499, 500, 99],
            'one done, rounding down to zero' => [1, 201, 1],
            'one done of many' => [1, 500, 1],
            'ordinary rounding is untouched' => [1, 3, 33],
            'ordinary rounding up is untouched' => [2, 3, 67],
        ];
    }

    /**
     * The ring reaches 100 only when every activity is done, and 0 only when none is.
     *
     * round() alone lies at both ends: 199 of 200 rounds to 100, which the external
     * function then reads as "completed", and 1 of 201 rounds to 0, which reads as
     * "not started".
     *
     * @dataProvider progress_provider
     * @param int $completed How many activities are complete.
     * @param int $total How many activities the section counts.
     * @param int|null $expected The percentage the ring may show.
     * @return void
     */
    public function test_progress_percentage(int $completed, int $total, ?int $expected): void {
        $this->assertSame($expected, calculator::progress_percentage($completed, $total));
    }

    /**
     * Raw values core can return, and the value a bar may display for them.
     *
     * @return array The cases, each raw value and expected percentage.
     */
    public static function clamp_provider(): array {
        return [
            'nothing to measure' => [null, 0],
            'zero' => [0.0, 0],
            'ordinary value rounds' => [66.4, 66],
            'ordinary value rounds up' => [66.6, 67],
            'full' => [100.0, 100],
            'above full is capped' => [120.0, 100],
            'far above full is capped' => [1000.0, 100],
            'below zero is floored' => [-5.0, 0],
        ];
    }

    /**
     * A course percentage from core is clamped to 0-100 before it is displayed.
     *
     * On Moodle releases without the MDL-60912 fix the core numerator is not a subset of
     * its denominator, so a stale completion row can push the value above 100.
     *
     * @dataProvider clamp_provider
     * @param float|null $raw The value core returned.
     * @param int $expected The percentage a bar may display.
     * @return void
     */
    public function test_clamp_percentage(?float $raw, int $expected): void {
        $this->assertSame($expected, calculator::clamp_percentage($raw));
    }
}
