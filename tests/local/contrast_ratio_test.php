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
 * Tests for the WCAG contrast ratio.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\local;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The ratio is WCAG's, pinned on known pairs either side of the 4.5 threshold.
 */
#[CoversClass(\local_dimensions\local\contrast_ratio::class)]
final class contrast_ratio_test extends \basic_testcase {
    /**
     * Known pairs and their ratio, computed independently from WCAG 2.2's formula.
     *
     * @return array Case name => [first colour, second colour, ratio].
     */
    public static function pairs(): array {
        return [
            'black on white' => ['#000000', '#ffffff', 21.0],
            'shorthand, either order' => ['#fff', '#000', 21.0],
            '#777777 on white, just under 4.5' => ['#777777', '#ffffff', 4.4781],
            '#767676 on white, just over 4.5' => ['#767676', '#FFFFFF', 4.5422],
            'Moodle blue on white' => ['#0f6cbf', '#ffffff', 5.3604],
            'pure red on white' => ['#ff0000', '#ffffff', 3.9985],
            'no hash' => ['ffff00', 'ffffff', 1.0738],
            'a colour on itself' => ['#123456', '#123456', 1.0],
            'near black, in the linear branch' => ['#0a0a0a', '#ffffff', 19.7981],
            'near black, past the linear branch' => ['#0c0c0c', '#ffffff', 19.5616],
        ];
    }

    /**
     * Each known pair gives its ratio, whichever colour comes first.
     *
     * @param string $first First colour.
     * @param string $second Second colour.
     * @param float $expected The ratio.
     * @return void
     */
    #[DataProvider('pairs')]
    public function test_known_pairs(string $first, string $second, float $expected): void {
        $this->assertEqualsWithDelta($expected, contrast_ratio::of($first, $second), 0.0001);
        $this->assertEqualsWithDelta($expected, contrast_ratio::of($second, $first), 0.0001);
    }

    /**
     * What is not a 3- or 6-digit hex colour has no ratio; the control is a valid pair.
     *
     * @return void
     */
    public function test_an_invalid_colour_has_no_ratio(): void {
        $this->assertNotNull(contrast_ratio::of('#abc', '#ffffff'));
        foreach (['', '#12', '#1234', 'red', '#gggggg', '#ffffff80'] as $invalid) {
            $this->assertNull(contrast_ratio::of($invalid, '#ffffff'), $invalid);
            $this->assertNull(contrast_ratio::of('#ffffff', $invalid), $invalid);
        }
    }

    /**
     * The shown ratio is cut, never rounded up, to one decimal, so a failing pair never reads 4.5.
     *
     * @return void
     */
    public function test_the_shown_ratio_is_cut_to_one_decimal(): void {
        $this->assertSame('4.4', contrast_ratio::format(4.4781));
        $this->assertSame('3.9', contrast_ratio::format(3.9985));
        $this->assertSame('4.5', contrast_ratio::format(4.5422));
        $this->assertSame('21.0', contrast_ratio::format(21.0));
        $this->assertSame('1.0', contrast_ratio::format(1.0));
        // 59 hundredths times ten is 58.99999999999999: only the epsilon keeps the cut at 5.9.
        $this->assertSame('5.9', contrast_ratio::format(5.8999999999999995));
    }
}
