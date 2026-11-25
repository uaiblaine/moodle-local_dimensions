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
 * The hub's address as a deep link: the Competencies tab's view and the map's competency.
 *
 * Only the current address is ever rewritten, in place (history.replaceState, no new history entry),
 * and only the named query parameters change: the path, the other parameters and the fragment that
 * names the open tab stay as they are. The canvas keeps its own copy of the competency rule in
 * js/esm/src/hubv2/deeplink.ts, since an ES module cannot import an AMD one; keep the two in step.
 *
 * @module     local_dimensions/central/deeplink
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Set or remove query parameters of the current address, without a new history entry.
 *
 * @param {Object} params Name to value; null or an empty string removes the parameter.
 */
export const setUrlParams = (params) => {
    const url = new URL(window.location.href);
    Object.keys(params).forEach((name) => {
        const value = params[name];
        if (value === null || value === undefined || value === '') {
            url.searchParams.delete(name);
        } else {
            url.searchParams.set(name, String(value));
        }
    });
    if (url.href !== window.location.href) {
        window.history.replaceState(window.history.state, '', url.href);
    }
};

/**
 * The competency the current address names, as a positive integer, or 0.
 *
 * @return {Number}
 */
export const urlCompetencyId = () => {
    const value = new URL(window.location.href).searchParams.get('competencyid') || '';
    return /^[1-9]\d{0,9}$/.test(value) ? Number(value) : 0;
};
