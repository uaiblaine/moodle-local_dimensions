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
 * core/form-autocomplete datasource for the Competency hub participant (user) picker.
 *
 * Searches via local_dimensions_search_assignable_users (template read from the select's
 * data-templateid), so users who already have a plan created from the template — individually
 * or by cohort sync — are excluded server-side: core refuses a second plan from the same
 * template, so suggesting them only misleads. Identity fields render in monospace next to
 * the name when the server exposes them.
 *
 * @module     local_dimensions/central/user_datasource
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import {getString} from 'core/str';

/**
 * Search users assignable to the select's template.
 *
 * @param {String} selector The originating select's selector.
 * @param {String} query The user's search text.
 * @param {Function} success Callback receiving the raw user list, or an overflow-notice string.
 * @param {Function} failure Callback receiving an error.
 */
export const transport = async(selector, query, success, failure) => {
    const source = document.querySelector(selector);
    const templateid = source && source.dataset.templateid ? Number(source.dataset.templateid) : 0;
    try {
        const response = await Ajax.call([{
            methodname: 'local_dimensions_search_assignable_users',
            args: {templateid: templateid, query: query, limitfrom: 0, limitnum: 25},
        }])[0];
        if (response.total > response.items.length) {
            // More matches than one page holds: form-autocomplete shows this notice instead of options.
            success(await getString('search_toomany', 'local_dimensions'));
            return;
        }
        success(response.items);
    } catch (error) {
        failure(error);
    }
};

/**
 * HTML-escape a plain-text fragment for use inside a suggestion label.
 *
 * @param {String} text
 * @return {String}
 */
const escapeHtml = (text) => {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
};

/**
 * Map users to autocomplete {value, label} pairs (full name plus identity in monospace).
 *
 * @param {String} selector Unused (autocomplete contract).
 * @param {Array|String} results Raw users from transport(), or an overflow-notice string.
 * @return {Array|String}
 */
export const processResults = (selector, results) => {
    if (!Array.isArray(results)) {
        // The transport passed the overflow-notice string straight through.
        return results;
    }
    return results.map((user) => {
        let label = escapeHtml(user.fullname);
        if (user.identity) {
            label += ` <span class="font-monospace small local-dimensions-central-links-code">`
                + `${escapeHtml(user.identity)}</span>`;
        }
        return {value: user.id, label: label};
    });
};
