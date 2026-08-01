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
 * Neutralising and restoring spreadsheet formula triggers in CSV cells.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\local;

/**
 * Neutralising and restoring spreadsheet formula triggers in CSV cells.
 *
 * A cell whose first non-blank character is one of = + - @ is read as a formula by Excel,
 * LibreOffice and Sheets, so an author who names a competency "=HYPERLINK(...)" turns every
 * export of it into a live formula on the next person's screen. escape() prefixes such a cell
 * with an apostrophe, which those applications consume as "this is text".
 *
 * The semantics deliberately match core's \core\dataformat::escape_spreadsheet_formula() (which
 * csv_export_writer applies to every core CSV, this plugin's formats included when they are
 * produced by tool_lp). Core is not called directly because it only arrived in a 4.5 point
 * release while the plugin still supports 4.5.0 - and because core provides no counterpart to
 * unescape(), which this plugin does need: its template and framework CSVs are round-trippable,
 * so what an export writes an import has to read back unchanged.
 */
class csv_formula {
    /** @var string[] The characters a spreadsheet reads as the start of a formula. */
    private const TRIGGERS = ['=', '+', '-', '@'];

    /**
     * Neutralise a cell that a spreadsheet would otherwise evaluate as a formula.
     *
     * @param string $value The raw cell value.
     * @return string The value, prefixed with an apostrophe when it needs one.
     */
    public static function escape(string $value): string {
        // Moodle's null placeholder is exactly one dash and means nothing to a spreadsheet.
        if ($value === '-') {
            return $value;
        }

        /* Leading blanks (spaces, tabs, carriage returns) do not stop the evaluation, so the
           first non-blank character is what decides - while the value itself keeps them. */
        $trimmed = ltrim($value);
        if ($trimmed === '' || !in_array($trimmed[0], self::TRIGGERS, true)) {
            return $value;
        }

        return "'" . $value;
    }

    /**
     * Restore a cell that escape() - or core's csv_export_writer - neutralised.
     *
     * Only an apostrophe that actually guards a formula trigger is dropped, so a value that
     * genuinely starts with one ("'quoted'") survives an import untouched.
     *
     * @param string $value The cell value as read from the file.
     * @return string The value without its guarding apostrophe.
     */
    public static function unescape(string $value): string {
        if (!isset($value[0]) || $value[0] !== "'") {
            return $value;
        }

        $rest = substr($value, 1);
        $trimmed = ltrim($rest);
        if ($trimmed === '' || !in_array($trimmed[0], self::TRIGGERS, true)) {
            return $value;
        }

        return $rest;
    }
}
