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
 * The splitter between the outline and the map in the Both layout: the outline's width, its bounds,
 * and how a key or a drag changes it.
 *
 * It follows the hub's own pane divider (amd/src/central/pane_resizer.js): the width is kept in
 * localStorage under a local_dimensions_* key, the arrow keys move it 24 px a step, a double click
 * goes back to the stylesheet's default; and, as the APG Window Splitter pattern adds, Home and End
 * go to the limits. The outline is at the start side of the page, so in a right-to-left language it
 * is at the right and the keys and the drag mirror.
 *
 * Pure but for the two storage helpers, which swallow a browser that refuses storage.
 *
 * @module     local_dimensions/hubv2/outlinewidth
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** The outline's width: the default, the limits and one key step, in pixels. */
export const OUTLINE_WIDTH = {initial: 264, minimum: 200, maximum: 560, step: 24};

/** How much of the panes' width the map always keeps, in pixels. */
export const MAP_RESERVE = 320;

/** The localStorage key of the outline's width, named as the hub's other pane widths are. */
export const STORAGE_KEY = 'local_dimensions_hubv2_outline_width';

/**
 * The widest the outline may be: its maximum, or less when the panes leave the map too little room;
 * never under its minimum.
 *
 * @param {number} available The width of the outline, the splitter and the map together, in pixels; 0 when unknown.
 * @returns {number} The upper bound.
 */
export const upperBound = (available: number): number => {
    const room = available > 0 ? available - MAP_RESERVE : OUTLINE_WIDTH.maximum;
    return Math.max(OUTLINE_WIDTH.minimum, Math.min(OUTLINE_WIDTH.maximum, room));
};

/**
 * A width held to the outline's bounds, in whole pixels.
 *
 * @param {number} width The width asked for.
 * @param {number} available The panes' width, 0 when unknown.
 * @returns {number} The width used.
 */
export const clampWidth = (width: number, available: number): number => {
    const value = Number.isFinite(width) ? width : OUTLINE_WIDTH.initial;
    return Math.round(Math.min(Math.max(value, OUTLINE_WIDTH.minimum), upperBound(available)));
};

/**
 * The width a key on the splitter asks for, or null for a key it does not use.
 *
 * Right widens the outline and Left narrows it, mirrored in a right-to-left language, where the
 * outline is at the right; Home and End go to the limits.
 *
 * @param {string} key The key.
 * @param {number} width The current width.
 * @param {number} available The panes' width, 0 when unknown.
 * @param {boolean} rtl Whether the page is written right to left.
 * @returns {number|null} The new width, or null.
 */
export const keyWidth = (key: string, width: number, available: number, rtl: boolean): number | null => {
    const grow = rtl ? 'ArrowLeft' : 'ArrowRight';
    const shrink = rtl ? 'ArrowRight' : 'ArrowLeft';
    switch (key) {
        case grow:
            return clampWidth(width + OUTLINE_WIDTH.step, available);
        case shrink:
            return clampWidth(width - OUTLINE_WIDTH.step, available);
        case 'Home':
            return OUTLINE_WIDTH.minimum;
        case 'End':
            return upperBound(available);
        default:
            return null;
    }
};

/**
 * The width a drag asks for: the width at the grab plus the pointer's travel since, towards the map.
 *
 * @param {number} start The width when the drag began.
 * @param {number} startx The pointer's x then.
 * @param {number} x The pointer's x now.
 * @param {number} available The panes' width, 0 when unknown.
 * @param {boolean} rtl Whether the page is written right to left.
 * @returns {number} The width.
 */
export const dragWidth = (start: number, startx: number, x: number, available: number, rtl: boolean): number =>
    clampWidth(start + (rtl ? startx - x : x - startx), available);

/**
 * The width kept from an earlier visit, or the default.
 *
 * @returns {number} The width, before the panes' own bound.
 */
export const storedWidth = (): number => {
    try {
        const stored = Number(window.localStorage.getItem(STORAGE_KEY));
        return stored > 0 ? stored : OUTLINE_WIDTH.initial;
    } catch {
        // A browser may refuse storage; the default then stands.
        return OUTLINE_WIDTH.initial;
    }
};

/**
 * Keep the width for the next visit, or forget it with null.
 *
 * @param {number|null} width The width, or null to go back to the default.
 * @returns {void}
 */
export const storeWidth = (width: number | null): void => {
    try {
        if (width === null) {
            window.localStorage.removeItem(STORAGE_KEY);
        } else {
            window.localStorage.setItem(STORAGE_KEY, String(Math.round(width)));
        }
    } catch {
        // A browser may refuse storage; the width then lasts as long as the page.
    }
};
