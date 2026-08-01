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
 * Tests for the CSV formula escaper.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\local\csv_formula
 */
final class csv_formula_test extends \basic_testcase {
    /**
     * Cells a spreadsheet would evaluate, and the harmless ones beside them.
     *
     * Kept as a plain array rather than a data provider: no other test in the plugin uses one,
     * and PHPUnit 11 reports the doc-comment metadata as deprecated.
     *
     * @return array Raw cell value => expected escaped value.
     */
    private function cases(): array {
        return [
            '=HYPERLINK("http://x.example")' => '\'=HYPERLINK("http://x.example")',
            '+1234' => '\'+1234',
            '-5 points' => '\'-5 points',
            '@SUM(A1)' => '\'@SUM(A1)',
            "\t=cmd" => "'\t=cmd",
            ' =cmd' => '\' =cmd',
            'Digital literacy' => 'Digital literacy',
            'Ratio a=b' => 'Ratio a=b',
            // Moodle's null placeholder means nothing to a spreadsheet and is left alone.
            '-' => '-',
            '' => '',
            '   ' => '   ',
            '\'quoted\'' => '\'quoted\'',
        ];
    }

    /**
     * A cell that would be read as a formula gains a guarding apostrophe; nothing else does.
     *
     * @return void
     */
    public function test_escape(): void {
        foreach ($this->cases() as $value => $expected) {
            $this->assertSame($expected, csv_formula::escape((string) $value), 'Escaping ' . $value);
        }
    }

    /**
     * Escaping then unescaping returns the original value, which is what makes the plugin's
     * CSV formats round-trippable through an export and a re-import.
     *
     * @return void
     */
    public function test_escape_round_trips(): void {
        foreach (array_keys($this->cases()) as $value) {
            $value = (string) $value;
            $this->assertSame($value, csv_formula::unescape(csv_formula::escape($value)), 'Round trip of ' . $value);
        }
    }

    /**
     * An apostrophe that guards nothing is part of the value and survives an import.
     *
     * @return void
     */
    public function test_unescape_keeps_a_meaningful_apostrophe(): void {
        $this->assertSame('\'quoted\'', csv_formula::unescape('\'quoted\''));
        $this->assertSame('\'', csv_formula::unescape('\''));
        $this->assertSame('O\'Brien', csv_formula::unescape('O\'Brien'));
    }

    /**
     * A file written by core's csv_export_writer reads back clean.
     *
     * Core escapes on export and offers no counterpart on import, so a tool_lp framework CSV
     * arrives here with the apostrophe still on it.
     *
     * @return void
     */
    public function test_unescape_reads_a_core_written_cell(): void {
        $this->assertSame('=1+1', csv_formula::unescape('\'=1+1'));
        $this->assertSame('@handle', csv_formula::unescape('\'@handle'));
    }
}
