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
 * The ID number the outline's inline editor offers for a new competency.
 *
 * Core refuses a competency without an ID number or with one its framework already has, so typing a
 * list in the outline needs one per line. When the siblings' ID numbers share a stem and end in a
 * number ("DCE-1.1", "DCE-1.2"; "C01", "C02"; "7"), the next number after the highest is offered, at
 * the same width when the numbers are zero-padded. When the siblings disagree on the stem, or one does
 * not end in a number, nothing is offered: a guess there would be wrong more often than right. The
 * offer is only a value in the field: core decides whether it is unique.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/idnumber
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** An ID number split into its stem and its final number. */
type Split = {stem: string, digits: string};

/**
 * Split an ID number into its stem and its final run of digits, or null when it ends otherwise.
 *
 * @param {string} idnumber The ID number.
 * @returns {object|null} Its parts.
 */
const split = (idnumber: string): Split | null => {
    const match = /^(.*?)(\d+)$/.exec(idnumber.trim());
    return match ? {stem: match[1], digits: match[2]} : null;
};

/**
 * The ID number to offer for a new sibling, or the empty string when no pattern is clear.
 *
 * @param {Array} siblings The ID numbers of the new competency's loaded siblings.
 * @param {Array} taken Every ID number the workspace knows in the framework, which the offer avoids.
 * @returns {string} The offer.
 */
export const suggestIdnumber = (siblings: string[], taken: string[] = []): string => {
    const parts = siblings.filter((idnumber) => idnumber.trim() !== '').map(split);
    if (parts.length === 0 || parts.length !== siblings.length || parts.some((part) => part === null)) {
        return '';
    }
    const splits = parts as Split[];
    const stem = splits[0].stem;
    if (splits.some((part) => part.stem !== stem)) {
        return '';
    }
    const highest = splits.reduce((best, part) => (Number(part.digits) > Number(best.digits) ? part : best), splits[0]);
    // Zero-padded numbers ("C01") keep their width; others grow as they need ("C9", "C10").
    const width = highest.digits.startsWith('0') ? highest.digits.length : 0;
    const known = new Set([...siblings, ...taken].map((idnumber) => idnumber.trim()));
    let next = Number(highest.digits) + 1;
    let offer = stem + String(next).padStart(width, '0');
    // Bounded: a framework cannot hold more ID numbers than the workspace knows.
    for (let guard = 0; known.has(offer) && guard <= known.size; guard++) {
        next++;
        offer = stem + String(next).padStart(width, '0');
    }
    return offer;
};
