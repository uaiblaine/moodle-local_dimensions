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
 * core/form-autocomplete datasource for the Competency hub add-course picker.
 *
 * Searches courses the user may link via local_dimensions_search_linkable_courses (competency read from
 * the select's data-competencyid; the server matches name, short name and ID number and excludes hidden
 * courses) and drops any course id in the select's data-exclude attribute (the courses already linked).
 * data-exclude is read fresh from the DOM on each search, so it stays correct after the linked list
 * changes. Suggestions show the course name plus the short name in monospace.
 *
 * @module     local_dimensions/central/course_datasource
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

/**
 * Fetch courses matching the query for the select's competency.
 *
 * @param {String} selector The originating select's selector.
 * @param {String} query The user's search text.
 * @param {Function} success Callback receiving the items list.
 * @param {Function} failure Callback receiving an error.
 */
export const transport = (selector, query, success, failure) => {
    const source = document.querySelector(selector);
    const competencyid = source && source.dataset.competencyid ? Number(source.dataset.competencyid) : 0;
    Ajax.call([{
        methodname: 'local_dimensions_search_linkable_courses',
        args: {competencyid: competencyid, query: query, limitfrom: 0, limitnum: 25},
    }])[0].then((response) => success(response.items)).catch(failure);
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
 * Map courses to autocomplete {value, label} pairs, excluding already-linked ids.
 * The label shows the course full name plus the short name in monospace.
 *
 * @param {String} selector The originating select's selector.
 * @param {Array} results Raw items from transport().
 * @return {Array}
 */
export const processResults = (selector, results) => {
    const source = document.querySelector(selector);
    const raw = source && source.dataset.exclude ? source.dataset.exclude : '';
    const excluded = new Set(raw.split(',').filter((id) => id !== ''));
    return results
        .filter((course) => !excluded.has(String(course.id)))
        .map((course) => {
            let label = escapeHtml(course.fullname);
            if (course.shortname) {
                label += ` <span class="font-monospace small local-dimensions-central-links-code">`
                    + `${escapeHtml(course.shortname)}</span>`;
            }
            return {value: course.id, label: label};
        });
};
