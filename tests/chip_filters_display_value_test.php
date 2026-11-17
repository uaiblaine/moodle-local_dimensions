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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A select chip's label comes from the same option split as the metadata caches.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_dimensions\chip_filters::class)]
final class chip_filters_display_value_test extends \advanced_testcase {
    /**
     * Option texts as an admin could save them, and the labels each stored index must read.
     *
     * @return array<string, array{0: string, 1: array<int, string>}> Option text, then index => label.
     */
    public static function option_text_provider(): array {
        return [
            'plain lines' => ["Alpha\nBravo\nCharlie", [1 => 'Alpha', 2 => 'Bravo', 3 => 'Charlie', 4 => '']],
            'trailing newline' => ["Alpha\nBravo\n", [1 => 'Alpha', 2 => 'Bravo', 3 => '']],
            'CRLF' => ["Alpha\r\nBravo\r\n", [1 => 'Alpha', 2 => 'Bravo', 3 => '']],
            'blank lines do not count' => ["Alpha\n\n  \nBravo", [1 => 'Alpha', 2 => 'Bravo', 3 => '']],
            'padded lines' => ["  Alpha  \n  Bravo  ", [1 => 'Alpha', 2 => 'Bravo']],
            'empty text' => ['', [1 => '']],
            'only blanks' => ["\n \r\n", [1 => '']],
        ];
    }

    /**
     * A stored index reads the label that {@see \local_dimensions\helper::split_select_options()} puts there.
     *
     * @param string $options The option text.
     * @param array $expected Stored index => label.
     * @return void
     */
    #[DataProvider('option_text_provider')]
    public function test_a_select_index_reads_the_helper_split(string $options, array $expected): void {
        $method = new \ReflectionMethod(chip_filters::class, 'display_value');
        foreach ($expected as $index => $label) {
            $this->assertSame(
                $label,
                $method->invoke(null, 'select', ['options' => $options], (string) $index),
                "Index $index"
            );
            if ($label !== '') {
                $this->assertSame($label, helper::split_select_options($options)[$index - 1]);
            }
        }
    }

    /**
     * An index that is not positive reads as no label.
     *
     * @return void
     */
    public function test_a_non_positive_index_reads_empty(): void {
        $method = new \ReflectionMethod(chip_filters::class, 'display_value');
        $this->assertSame('', $method->invoke(null, 'select', ['options' => "A\nB"], '0'));
        $this->assertSame('', $method->invoke(null, 'select', ['options' => "A\nB"], '-1'));
    }
}
