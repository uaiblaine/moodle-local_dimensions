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
 * The canvas's deep link: the selected competency and the lens kept in the hub's address.
 *
 * The address is rewritten in place (history.replaceState: selecting never adds a history entry), and
 * only its competencyid (or lens) parameter changes; the path, the other parameters and the fragment that names
 * the hub's open tab stay. What the link reveals on the next visit is decided by the server, which
 * reveals it only when the competency belongs to the framework the map shows. The list view's AMD
 * twin is amd/src/central/deeplink.js; keep the two in step.
 *
 * @module     local_dimensions/hubv2/deeplink
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** The address parameter that names the competency. */
export const PARAM = 'competencyid';

/**
 * An address naming a competency, or naming none.
 *
 * @param {string} href The address.
 * @param {number|null} id The competency, or null to name none.
 * @returns {string} The address with only its competencyid parameter changed.
 */
export const withCompetency = (href: string, id: number | null): string => {
    const url = new URL(href);
    if (id !== null && Number.isInteger(id) && id > 0) {
        url.searchParams.set(PARAM, String(id));
    } else {
        url.searchParams.delete(PARAM);
    }
    return url.href;
};

/**
 * Keep a selection in the current address, without a new history entry.
 *
 * @param {number|null} id The competency selected, or null when the framework is.
 * @returns {void}
 */
export const rememberSelection = (id: number | null): void => {
    const next = withCompetency(window.location.href, id);
    if (next !== window.location.href) {
        window.history.replaceState(window.history.state, '', next);
    }
};

/** The address parameter that names the lens. */
export const LENS_PARAM = 'lens';

/**
 * An address naming a lens, or none: "No lens" is the default, so it is named by no parameter.
 *
 * @param {string} href The address.
 * @param {string} lens The lens.
 * @returns {string} The address with only its lens parameter changed.
 */
export const withLens = (href: string, lens: string): string => {
    const url = new URL(href);
    if (lens && lens !== 'none') {
        url.searchParams.set(LENS_PARAM, lens);
    } else {
        url.searchParams.delete(LENS_PARAM);
    }
    return url.href;
};

/**
 * Keep the lens in the current address, without a new history entry, as the selection is kept.
 *
 * @param {string} lens The lens.
 * @returns {void}
 */
export const rememberLens = (lens: string): void => {
    const next = withLens(window.location.href, lens);
    if (next !== window.location.href) {
        window.history.replaceState(window.history.state, '', next);
    }
};
