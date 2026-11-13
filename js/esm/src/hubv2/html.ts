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
 * Escaping for the few strings the canvas hands to an HTML sink of core's (a dialogue's title or
 * question), and the check an admin colour passes before it reaches a style.
 *
 * @module     local_dimensions/hubv2/html
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Escape plain text for HTML, as local_dimensions/central/escape does for the list view.
 *
 * @param {string} text Plain text.
 * @returns {string} The escaped text.
 */
export const escapeHtml = (text: string): string => text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

/**
 * Whether a custom-field colour is a hex colour, the only value written into a style.
 *
 * @param {string} value The stored value.
 * @returns {boolean} Whether it is #rgb, #rgba, #rrggbb or #rrggbbaa.
 */
export const isHexColour = (value: string): boolean => /^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i.test(value);
