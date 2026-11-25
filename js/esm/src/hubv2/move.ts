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
 * The HTML of the move confirmation: where the competency goes, and which rules core resets on the way.
 *
 * core_competency\api::set_parent_competency() resets the rule of the competency's current parent
 * and of its new parent, whatever the rule was. The confirmation names each one that has a rule
 * today, as the delete confirmation names the parent's; with neither, it says nothing about rules.
 * (Core also resets the moved competency's own rule when it moves under one of its descendants,
 * which the canvas refuses before asking.)
 *
 * @module     local_dimensions/hubv2/move
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {escapeHtml} from './html';
import {fill, fillObject} from './strings';
import type {Labels} from './strings';

/** What the confirmation reads of a parent: its name, and whether it has a rule. */
export type MoveParent = {shortname: string, ruletype: string | null};

/**
 * Whether a parent has a rule core would reset.
 *
 * @param {object|null} parent The parent, or null for the top level.
 * @returns {boolean} True when it has a rule.
 */
const hasRule = (parent: MoveParent | null): parent is MoveParent => !!parent && !!parent.ruletype;

/**
 * The sentences naming each rule core resets on the way: the current parent's, then the new one's.
 *
 * Both confirmations read them from here: the pick-on-the-map flow's dialogue as HTML, the "Move to"
 * dialogue as text, so the two can never say different things about the same move.
 *
 * @param {object} labels The labels.
 * @param {object|null} oldparent The current parent, or null when the competency is at the top level.
 * @param {object|null} newparent The new parent, or null for the top level.
 * @param {Function} escape How a name enters the sentence: escapeHtml for HTML, the identity for text.
 * @returns {Array} The sentences, none when neither parent has a rule.
 */
export const ruleNotes = (
    labels: Labels,
    oldparent: MoveParent | null,
    newparent: MoveParent | null,
    escape: (text: string) => string = (text) => text,
): string[] => [
    hasRule(oldparent) ? fill(labels.hubv2_moverule_oldparent, escape(oldparent.shortname)) : '',
    hasRule(newparent) ? fill(labels.hubv2_moverule_newparent, escape(newparent.shortname)) : '',
].filter((note) => note !== '');

/**
 * The body of the move confirmation.
 *
 * @param {object} labels The labels.
 * @param {string} name The moved competency's plain name.
 * @param {object|null} oldparent Its current parent, or null when it is at the top level.
 * @param {object|null} newparent Its new parent, or null for the top level.
 * @returns {string} HTML: the question, then one line per rule that will be reset.
 */
export const moveBody = (labels: Labels, name: string, oldparent: MoveParent | null, newparent: MoveParent | null): string => {
    const safe = escapeHtml(name);
    const question = newparent
        ? fillObject(labels.hubv2_moveconfirm, {name: safe, parent: escapeHtml(newparent.shortname)})
        : fill(labels.hubv2_moveconfirmroot, safe);
    const notes = ruleNotes(labels, oldparent, newparent, escapeHtml).map((note) => `<p>${note}</p>`).join('');
    return `<p>${question}</p>${notes}`;
};
