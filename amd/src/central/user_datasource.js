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
 * @module     local_dimensions/central/user_datasource
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

/**
 * Search users by name/identity.
 *
 * @param {String} selector Unused (autocomplete contract).
 * @param {String} query The user's search text.
 * @param {Function} success Callback receiving the raw user list.
 * @param {Function} failure Callback receiving an error.
 */
export const transport = (selector, query, success, failure) => {
    Ajax.call([{
        methodname: 'core_user_search_identity',
        args: {query: query},
    }])[0].then((response) => success(response.list)).catch(failure);
};

/**
 * Map users to autocomplete {value, label} pairs (label = full name + any identity fields).
 *
 * @param {String} selector Unused (autocomplete contract).
 * @param {Array} results Raw users from transport().
 * @return {Array}
 */
export const processResults = (selector, results) => results.map((user) => {
    const extra = (user.extrafields || []).map((field) => field.value).filter((value) => value !== '');
    return {value: user.id, label: extra.length ? `${user.fullname} (${extra.join(', ')})` : user.fullname};
});
