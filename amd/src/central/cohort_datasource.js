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
 * core/form-autocomplete datasource for the Competency hub cohort picker.
 *
 * Searches cohorts via core_cohort_search_cohorts (context read from the select's data-contextid) and
 * drops any cohort whose id is in the select's data-exclude attribute (the cohorts already attached to
 * the template). data-exclude is read fresh from the DOM on each search, so it stays correct after the
 * attached list changes — unlike core/form-cohort-selector, which caches it via jQuery .data().
 *
 * @module     local_dimensions/central/cohort_datasource
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

/**
 * Fetch cohorts matching the query for the select's context.
 *
 * @param {String} selector The originating select's selector.
 * @param {String} query The user's search text.
 * @param {Function} success Callback receiving the raw cohort list.
 * @param {Function} failure Callback receiving an error.
 */
export const transport = (selector, query, success, failure) => {
    const source = document.querySelector(selector);
    const contextid = source && source.dataset.contextid ? Number(source.dataset.contextid) : 0;
    Ajax.call([{
        methodname: 'core_cohort_search_cohorts',
        args: {query: query, context: {contextid: contextid}, limitfrom: 0, limitnum: 100},
    }])[0].then((response) => success(response.cohorts)).catch(failure);
};

/**
 * Map cohorts to autocomplete {value, label} pairs, excluding those already attached
 * (ids listed in the originating select's data-exclude attribute).
 *
 * @param {String} selector The originating select's selector.
 * @param {Array} results Raw cohorts from transport().
 * @return {Array}
 */
export const processResults = (selector, results) => {
    const source = document.querySelector(selector);
    const raw = source && source.dataset.exclude ? source.dataset.exclude : '';
    const excluded = new Set(raw.split(',').filter((id) => id !== ''));
    return results
        .filter((cohort) => !excluded.has(String(cohort.id)))
        .map((cohort) => ({value: cohort.id, label: cohort.name}));
};
