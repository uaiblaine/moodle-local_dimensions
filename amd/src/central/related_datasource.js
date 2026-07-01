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
 * core/form-autocomplete datasource for the Related competencies picker (framework-scoped).
 *
 * @module     local_dimensions/central/related_datasource
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

/**
 * Search competencies within the picker's framework (read from the select's data-frameworkid).
 *
 * @param {String} selector The originating select's selector (autocomplete contract).
 * @param {String} query The user's search text.
 * @param {Function} success Callback receiving the raw result items.
 * @param {Function} failure Callback receiving an error.
 */
export const transport = (selector, query, success, failure) => {
    const source = document.querySelector(selector);
    const frameworkid = source ? Number(source.dataset.frameworkid || 0) : 0;
    Ajax.call([{
        methodname: 'local_dimensions_search_structure',
        args: {frameworkid: frameworkid, query: query, limitfrom: 0, limitnum: 25},
    }])[0].then((response) => success(response.items)).catch(failure);
};

/**
 * Map raw hits to autocomplete {value, label} pairs (label carries the ancestor path), dropping
 * ids listed in the select's data-exclude attribute (self + already-related competencies).
 *
 * @param {String} selector The originating select's selector (autocomplete contract).
 * @param {Array} results Raw items from transport().
 * @return {Array}
 */
export const processResults = (selector, results) => {
    const source = document.querySelector(selector);
    const raw = source && source.dataset.exclude ? source.dataset.exclude : '';
    const excluded = new Set(raw.split(',').filter((id) => id !== ''));
    return results
        .filter((competency) => !excluded.has(String(competency.id)))
        .map((competency) => ({
            value: competency.id,
            label: competency.path ? `${competency.shortname} · ${competency.path}` : competency.shortname,
        }));
};
