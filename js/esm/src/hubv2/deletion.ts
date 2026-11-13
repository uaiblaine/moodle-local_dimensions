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
 * The HTML of the two deletion dialogues: why a competency cannot be deleted, and what deleting it destroys.
 *
 * @module     local_dimensions/hubv2/deletion
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {escapeHtml} from './html';
import {fill, fillCount, fillObject} from './strings';
import type {Labels} from './strings';
import type {DeleteCheck} from './types';

/**
 * The body of the dialogue saying why core will refuse the deletion.
 *
 * @param {object} labels The labels.
 * @param {string} name The competency's plain name.
 * @param {object} check The server's answer.
 * @returns {string} HTML: an introduction, one list item per reason, and what to do about each kind.
 */
export const blockedBody = (labels: Labels, name: string, check: DeleteCheck): string => {
    const safe = escapeHtml(name);
    let intro = fill(labels.hubv2_delete_blocked_intro, safe);
    if (check.descendants > 0) {
        const key = check.descendants === 1 ? 'hubv2_delete_blocked_intro_tree_one' : 'hubv2_delete_blocked_intro_tree';
        intro = fillObject(labels[key], {name: safe, count: String(check.descendants)});
    }
    const reasons = [
        check.templates > 0 ? fillCount(labels, 'hubv2_delete_blocked_templates', check.templates) : '',
        check.plans > 0 ? fillCount(labels, 'hubv2_delete_blocked_plans', check.plans) : '',
        check.courses > 0 ? fillCount(labels, 'hubv2_delete_blocked_courses', check.courses) : '',
        check.users > 0 ? fillCount(labels, 'hubv2_delete_blocked_users', check.users) : '',
        check.badges ? labels.hubv2_delete_blocked_badges : '',
    ].filter((reason) => reason !== '');
    const list = reasons.length > 0 ? `<ul>${reasons.map((reason) => `<li>${reason}</li>`).join('')}</ul>` : '';
    // What to do: links can be removed, a user's record cannot.
    const links = check.templates > 0 || check.plans > 0 || check.courses > 0 || check.badges;
    const advice = [
        links ? labels.hubv2_delete_blocked_closing_links : '',
        check.users > 0 ? labels.hubv2_delete_blocked_closing_users : '',
    ].filter((line) => line !== '').map((line) => `<p>${line}</p>`).join('');
    return `<p>${intro}</p>${list}${advice}`;
};

/**
 * The body of the confirmation, severe when children go with the competency.
 *
 * @param {object} labels The labels.
 * @param {string} name The competency's plain name.
 * @param {object} check The server's answer.
 * @returns {string} HTML.
 */
export const confirmBody = (labels: Labels, name: string, check: DeleteCheck): string => {
    const safe = escapeHtml(name);
    if (check.descendants === 0) {
        return `<p>${fill(labels.hubv2_delete_confirm_leaf, safe)}</p>`;
    }
    const text = check.descendants === 1
        ? fillObject(labels.hubv2_delete_confirm_tree_one, {name: safe})
        : fillObject(labels.hubv2_delete_confirm_tree, {
            name: safe,
            count: String(check.descendants),
            total: String(check.descendants + 1),
        });
    const rule = check.parenthasrule ? `<p>${labels.hubv2_delete_parentrule}</p>` : '';
    return `<p>${text}</p>${rule}`;
};
