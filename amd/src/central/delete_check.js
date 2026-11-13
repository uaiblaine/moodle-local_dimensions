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
 * Deleting a competency from the Structure tab: what the server says core will do, and the two
 * dialogue bodies built from it (why it cannot be deleted; what deleting it destroys).
 *
 * The canvas builds the same two bodies from the same strings in js/esm/src/hubv2/deletion.ts;
 * keep the two in step.
 *
 * @module     local_dimensions/central/delete_check
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import {getString} from 'core/str';

const COMPONENT = 'local_dimensions';

/**
 * Ask the server what deleting the competency and its descendants involves.
 *
 * @param {number} competencyid The competency.
 * @return {Promise<Object>} The answer of local_dimensions_competency_delete_check.
 */
export const check = (competencyid) =>
    Ajax.call([{methodname: 'local_dimensions_competency_delete_check', args: {competencyid}}])[0];

/**
 * The string that fits a count: `<key>_one` for exactly 1.
 *
 * @param {string} key The plural string's key.
 * @param {number} count The number.
 * @return {Promise<string>} The string.
 */
const counted = (key, count) => (count === 1 ? getString(`${key}_one`, COMPONENT) : getString(key, COMPONENT, count));

/**
 * The body of the dialogue saying why core will refuse the deletion.
 *
 * @param {string} name The competency's name, already escaped for HTML.
 * @param {Object} answer The server's answer.
 * @return {Promise<string>} HTML: an introduction, one list item per reason, and what to do about each kind.
 */
export const blockedBody = async(name, answer) => {
    let intro = getString('hubv2_delete_blocked_intro', COMPONENT, name);
    if (answer.descendants === 1) {
        intro = getString('hubv2_delete_blocked_intro_tree_one', COMPONENT, {name});
    } else if (answer.descendants > 1) {
        intro = getString('hubv2_delete_blocked_intro_tree', COMPONENT, {name, count: answer.descendants});
    }
    // Counts are exact only for a site-level manager; otherwise say which reasons apply, without a number.
    const reason = (kind, count) => (answer.exact
        ? counted(`hubv2_delete_blocked_${kind}`, count)
        : getString(`hubv2_delete_blocked_${kind}_some`, COMPONENT));
    const links = answer.templates > 0 || answer.plans > 0 || answer.courses > 0 || answer.badges;
    const [first, reasons, advice] = await Promise.all([
        intro,
        Promise.all([
            answer.templates > 0 ? reason('templates', answer.templates) : '',
            answer.plans > 0 ? reason('plans', answer.plans) : '',
            answer.courses > 0 ? reason('courses', answer.courses) : '',
            answer.users > 0 ? reason('users', answer.users) : '',
            answer.badges ? getString('hubv2_delete_blocked_badges', COMPONENT) : '',
        ]),
        // What to do: links can be removed, a user's record cannot.
        Promise.all([
            links ? getString('hubv2_delete_blocked_closing_links', COMPONENT) : '',
            answer.users > 0 ? getString('hubv2_delete_blocked_closing_users', COMPONENT) : '',
        ]),
    ]);
    const items = reasons.filter((reason) => reason !== '').map((reason) => `<li>${reason}</li>`).join('');
    const closing = advice.filter((line) => line !== '').map((line) => `<p>${line}</p>`).join('');
    return `<p>${first}</p>${items ? `<ul>${items}</ul>` : ''}${closing}`;
};

/**
 * The body of the confirmation, severe when children go with the competency.
 *
 * @param {string} name The competency's name, already escaped for HTML.
 * @param {Object} answer The server's answer.
 * @return {Promise<string>} HTML.
 */
export const confirmBody = async(name, answer) => {
    if (answer.descendants === 0) {
        return `<p>${await getString('hubv2_delete_confirm_leaf', COMPONENT, name)}</p>`;
    }
    const [text, rule] = await Promise.all([
        answer.descendants === 1
            ? getString('hubv2_delete_confirm_tree_one', COMPONENT, {name})
            : getString('hubv2_delete_confirm_tree', COMPONENT, {
                name,
                count: answer.descendants,
                total: answer.descendants + 1,
            }),
        answer.parenthasrule ? getString('hubv2_delete_parentrule', COMPONENT) : '',
    ]);
    return `<p>${text}</p>${rule ? `<p>${rule}</p>` : ''}`;
};
