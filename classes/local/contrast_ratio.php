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
 * The WCAG contrast ratio of two colours.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\local;

/**
 * The contrast ratio of WCAG 2.2 (success criterion 1.4.3) between two sRGB hex colours.
 *
 * Pure arithmetic, no state: the relative luminance of each colour, then (lighter + 0.05) /
 * (darker + 0.05), from 1.0 (the same colour) to 21.0 (black on white). Body text needs 4.5.
 */
final class contrast_ratio {
    /** @var float The ratio WCAG asks of body text at level AA. */
    public const float MINIMUM_TEXT = 4.5;

    /**
     * The contrast ratio of two colours, whichever is the text.
     *
     * @param string $first A colour, `#rgb` or `#rrggbb`, the hash optional.
     * @param string $second The other colour, in the same forms.
     * @return float|null The ratio, or null when either is not such a colour.
     */
    public static function of(string $first, string $second): ?float {
        $a = self::luminance($first);
        $b = self::luminance($second);
        if ($a === null || $b === null) {
            return null;
        }
        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    /**
     * A ratio as shown to people: one decimal, cut rather than rounded, so a pair under the
     * minimum never reads as the minimum (4.48 shows as 4.4, never 4.5).
     *
     * @param float $ratio The ratio.
     * @return string
     */
    public static function format(float $ratio): string {
        // The epsilon keeps a ratio such as 4.3, stored as 4.29999..., at 4.3.
        return sprintf('%.1f', floor($ratio * 10 + 1e-9) / 10);
    }

    /**
     * The relative luminance of a colour, 0 for black to 1 for white.
     *
     * @param string $colour A colour, `#rgb` or `#rrggbb`, the hash optional.
     * @return float|null The luminance, or null when the value is not such a colour.
     */
    public static function luminance(string $colour): ?float {
        $hex = ltrim(trim($colour), '#');
        if (!preg_match('/^([0-9a-f]{3}|[0-9a-f]{6})$/i', $hex)) {
            return null;
        }
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $linear = [];
        foreach ([0, 2, 4] as $offset) {
            $channel = hexdec(substr($hex, $offset, 2)) / 255;
            $linear[] = $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
        }
        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }
}
