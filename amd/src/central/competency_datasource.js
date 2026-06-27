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

/**
 * Fetch competencies matching the query from the server.
 *
 * @param {String} selector Unused (autocomplete contract).
 * @param {String} query The user's search text.
 * @param {Function} success Callback receiving the raw result items.
 * @param {Function} failure Callback receiving an error.
 */
export const transport = (selector, query, success, failure) => {
    Ajax.call([{
        methodname: 'local_dimensions_search_competencies',
        args: {query: query, limitfrom: 0, limitnum: 25},
    }])[0].then((response) => success(response.items)).catch(failure);
};

/**
 * Map the raw items to autocomplete {value, label} pairs (label carries the framework tag).
 *
 * @param {String} selector Unused (autocomplete contract).
 * @param {Array} results Raw items from transport().
 * @return {Array}
 */
export const processResults = (selector, results) => results.map((competency) => ({
    value: competency.id,
    label: competency.frameworktag ? `${competency.shortname} · ${competency.frameworktag}` : competency.shortname,
}));
