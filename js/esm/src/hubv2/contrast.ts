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
 * The hex colours the appearance dialogue takes, and the WCAG 2 contrast ratio between two of them.
 *
 * A colour is what the server's helper::normalise_hex_color() accepts: #rgb or #rrggbb, the # optional, any case;
 * normalise() spells it as the server stores it. The ratio is WCAG 2's: (L1 + 0.05) / (L2 + 0.05) over the relative
 * luminances, each channel linearised with the sRGB curve (threshold 0.04045, the IEC 61966-2-1 value).
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/contrast
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** The ratio under which text is hard to read (WCAG 2.2 success criterion 1.4.3, normal text). */
export const MIN_CONTRAST = 4.5;

/** A colour as the server accepts it. */
const HEX = /^#?([0-9a-f]{3}|[0-9a-f]{6})$/i;

/**
 * A colour as the server stores it (helper::normalise_hex_color()), or '' when it is not one.
 *
 * @param {string} value What was typed.
 * @returns {string} The colour with its leading #, or ''.
 */
export const normalise = (value: string): string => {
    const text = value.trim();
    if (!HEX.test(text)) {
        return '';
    }
    return text.startsWith('#') ? text : `#${text}`;
};

/**
 * A colour's three channels, 0 to 255.
 *
 * @param {string} colour A colour normalise() accepts.
 * @returns {Array|null} The red, green and blue channels, or null for no colour.
 */
export const channels = (colour: string): [number, number, number] | null => {
    const match = HEX.exec(colour.trim());
    if (!match) {
        return null;
    }
    const digits = match[1].length === 3 ? [...match[1]].map((digit) => digit + digit) : match[1].match(/../g) as string[];
    return [parseInt(digits[0], 16), parseInt(digits[1], 16), parseInt(digits[2], 16)];
};

/**
 * One channel, linearised.
 *
 * @param {number} value The channel, 0 to 255.
 * @returns {number} Its linear value, 0 to 1.
 */
const linear = (value: number): number => {
    const ratio = value / 255;
    return ratio <= 0.04045 ? ratio / 12.92 : ((ratio + 0.055) / 1.055) ** 2.4;
};

/**
 * A colour's relative luminance (WCAG 2).
 *
 * @param {string} colour A colour normalise() accepts.
 * @returns {number|null} The luminance, 0 to 1, or null for no colour.
 */
export const luminance = (colour: string): number | null => {
    const rgb = channels(colour);
    if (!rgb) {
        return null;
    }
    return 0.2126 * linear(rgb[0]) + 0.7152 * linear(rgb[1]) + 0.0722 * linear(rgb[2]);
};

/**
 * The contrast ratio between two colours, 1 to 21, whichever is lighter.
 *
 * @param {string} first One colour.
 * @param {string} second The other.
 * @returns {number|null} The ratio, or null when either is not a colour.
 */
export const contrastRatio = (first: string, second: string): number | null => {
    const a = luminance(first);
    const b = luminance(second);
    if (a === null || b === null) {
        return null;
    }
    return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
};

/**
 * A ratio as the dialogue writes it: rounded down to one decimal, so 4.49 never reads as 4.5.
 *
 * @param {number} ratio The ratio.
 * @returns {string} The number, without the ":1".
 */
export const ratioText = (ratio: number): string => (Math.floor(ratio * 10) / 10).toFixed(1);
