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

/*
 * The strings of each blocking reason, spelled out so tests/lang_string_references_test.php can see
 * every key: `plural` takes the count, `one` is for exactly 1, `some` is the form without a number.
 */
const REASONS = {
    templates: {
        plural: {key: 'hubv2_delete_blocked_templates', component: 'local_dimensions'},
        one: {key: 'hubv2_delete_blocked_templates_one', component: 'local_dimensions'},
        some: {key: 'hubv2_delete_blocked_templates_some', component: 'local_dimensions'},
    },
    plans: {
        plural: {key: 'hubv2_delete_blocked_plans', component: 'local_dimensions'},
        one: {key: 'hubv2_delete_blocked_plans_one', component: 'local_dimensions'},
        some: {key: 'hubv2_delete_blocked_plans_some', component: 'local_dimensions'},
    },
    courses: {
        plural: {key: 'hubv2_delete_blocked_courses', component: 'local_dimensions'},
        one: {key: 'hubv2_delete_blocked_courses_one', component: 'local_dimensions'},
        some: {key: 'hubv2_delete_blocked_courses_some', component: 'local_dimensions'},
    },
    users: {
        plural: {key: 'hubv2_delete_blocked_users', component: 'local_dimensions'},
        one: {key: 'hubv2_delete_blocked_users_one', component: 'local_dimensions'},
        some: {key: 'hubv2_delete_blocked_users_some', component: 'local_dimensions'},
    },
};

/**
 * Ask the server what deleting the competency and its descendants involves.
 *
 * @param {number} competencyid The competency.
 * @return {Promise<Object>} The answer of local_dimensions_competency_delete_check.
 */
export const check = (competencyid) =>
    Ajax.call([{methodname: 'local_dimensions_competency_delete_check', args: {competencyid}}])[0];

/**
 * The body of the dialogue saying why core will refuse the deletion.
 *
 * @param {string} name The competency's name, already escaped for HTML.
 * @param {Object} answer The server's answer.
 * @return {Promise<string>} HTML: an introduction, one list item per reason, and what to do about each kind.
 */
export const blockedBody = async(name, answer) => {
    let intro = getString('hubv2_delete_blocked_intro', 'local_dimensions', name);
    if (answer.descendants === 1) {
        intro = getString('hubv2_delete_blocked_intro_tree_one', 'local_dimensions', {name});
    } else if (answer.descendants > 1) {
        intro = getString('hubv2_delete_blocked_intro_tree', 'local_dimensions', {name, count: answer.descendants});
    }
    // Counts are exact only for a site-level manager; otherwise say which reasons apply, without a number.
    const reason = (kind, count) => {
        const strings = REASONS[kind];
        if (!answer.exact) {
            return getString(strings.some.key, strings.some.component);
        }
        const {key, component} = count === 1 ? strings.one : strings.plural;
        return getString(key, component, count);
    };
    const links = answer.templates > 0 || answer.plans > 0 || answer.courses > 0 || answer.badges;
    const [first, reasons, advice] = await Promise.all([
        intro,
        Promise.all([
            answer.templates > 0 ? reason('templates', answer.templates) : '',
            answer.plans > 0 ? reason('plans', answer.plans) : '',
            answer.courses > 0 ? reason('courses', answer.courses) : '',
            answer.users > 0 ? reason('users', answer.users) : '',
            answer.badges ? getString('hubv2_delete_blocked_badges', 'local_dimensions') : '',
        ]),
        // What to do: links can be removed, a user's record cannot.
        Promise.all([
            links ? getString('hubv2_delete_blocked_closing_links', 'local_dimensions') : '',
            answer.users > 0 ? getString('hubv2_delete_blocked_closing_users', 'local_dimensions') : '',
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
    let question = getString('hubv2_delete_confirm_leaf', 'local_dimensions', name);
    if (answer.descendants === 1) {
        question = getString('hubv2_delete_confirm_tree_one', 'local_dimensions', {name});
    } else if (answer.descendants > 1) {
        question = getString('hubv2_delete_confirm_tree', 'local_dimensions', {
            name,
            count: answer.descendants,
            total: answer.descendants + 1,
        });
    }
    // Core resets the parent's rule whatever is deleted under it, a leaf included.
    const [text, rule] = await Promise.all([
        question,
        answer.parenthasrule ? getString('hubv2_delete_parentrule', 'local_dimensions') : '',
    ]);
    return `<p>${text}</p>${rule ? `<p>${rule}</p>` : ''}`;
};
