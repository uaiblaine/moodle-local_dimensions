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
 * core/form-autocomplete datasource for the Competency hub competency search.
 *
 * @module     local_dimensions/central/competency_datasource
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import {getString} from 'core/str';

/**
 * Fetch competencies matching the query from the server.
 *
 * @param {String} selector Unused (autocomplete contract).
 * @param {String} query The user's search text.
 * @param {Function} success Callback receiving the raw result items, or an overflow-notice string.
 * @param {Function} failure Callback receiving an error.
 */
export const transport = async(selector, query, success, failure) => {
    try {
        const response = await Ajax.call([{
            methodname: 'local_dimensions_search_competencies',
            args: {query: query, limitfrom: 0, limitnum: 25},
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
 * Map the raw items to autocomplete {value, label} pairs. The label follows the
 * Structure-tab search pattern: competency name, idnumber in monospace, and a muted
 * breadcrumb line with the origin framework tag plus the ancestor path.
 * Items whose id is listed in the originating select's data-exclude attribute are dropped
 * (used by the add picker to hide competencies already on the template).
 *
 * @param {String} selector The originating select's selector (autocomplete contract).
 * @param {Array|String} results Raw items from transport(), or an overflow-notice string.
 * @return {Array|String}
 */
export const processResults = (selector, results) => {
    if (!Array.isArray(results)) {
        // The transport passed the overflow-notice string straight through.
        return results;
    }
    const source = document.querySelector(selector);
    const raw = source && source.dataset.exclude ? source.dataset.exclude : '';
    const excluded = new Set(raw.split(',').filter((id) => id !== ''));
    return results
        .filter((competency) => !excluded.has(String(competency.id)))
        .map((competency) => {
            let label = `<span class="fw-medium">${escapeHtml(competency.shortname)}</span>`;
            if (competency.idnumber) {
                label += ` <span class="font-monospace small text-muted">${escapeHtml(competency.idnumber)}</span>`;
            }
            const trail = [competency.frameworktag, competency.path].filter(Boolean).join(' / ');
            if (trail) {
                label += `<div class="small text-muted">${escapeHtml(trail)}</div>`;
            }
            return {value: competency.id, label: label};
        });
};
