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
 * What the bulk dock's three actions decide before they ask the server: which competencies an action acts on, in
 * which order, what the appearance dialogue sends, what the delete confirmation says, and the words of each outcome.
 *
 * A move and a delete act on the picked competencies none of whose ancestors is picked too (topmost): core moves and
 * deletes a competency with its descendants, so a descendant would move twice or be deleted with its ancestor. The
 * order is the tree's (depth first, the server's sort), so the same set always runs in the same order. The server
 * decides every write again; these decisions only keep the dock from asking what it knows core refuses.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/bulk
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {normalise} from './contrast';
import {blockedReasons} from './deletion';
import {fill, fillCount, fillObject} from './strings';
import type {Labels} from './strings';
import {ROOT, ancestors, isWithin} from './tree';
import type {Tree} from './tree';
import type {DeleteCheck} from './types';

/** The most competencies one appearance call names (set_competencies_appearance::MAX_COMPETENCIES). */
export const BULK_MAX = 200;

/** How many names a dialogue lists before "and N more". */
export const PREVIEW_NAMES = 5;

/** The appearance fields, in the dialogue's order, named as the service and StructureNode name them. */
export const APPEARANCE_FIELDS = ['bgcolor', 'textcolor', 'tag1', 'tag2', 'type'] as const;

/** One appearance field. */
export type AppearanceName = typeof APPEARANCE_FIELDS[number];

/** The fields holding a hex colour; the others are selects. */
export const COLOUR_FIELDS: readonly AppearanceName[] = ['bgcolor', 'textcolor'];

/** What the dialogue holds for one field: leave it as it is (the default), a value, or no value. */
export type Choice = {mode: 'keep'} | {mode: 'set', value: string} | {mode: 'clear'};

/** One field entry of local_dimensions_set_competencies_appearance. */
export type FieldEntry = {name: AppearanceName, action: 'set' | 'clear', value: string};

/**
 * The fields the appearance call sends: one entry per field not left as it is, a colour as the server stores it,
 * a select's option position as typed. Null when a colour set is not a colour, so nothing is sent.
 *
 * @param {object} choices The dialogue's choice for each field.
 * @returns {Array|null} The entries, or null.
 */
export const appearancePayload = (choices: Record<AppearanceName, Choice>): FieldEntry[] | null => {
    const entries: FieldEntry[] = [];
    for (const name of APPEARANCE_FIELDS) {
        const choice = choices[name];
        if (choice.mode === 'clear') {
            entries.push({name, action: 'clear', value: ''});
        } else if (choice.mode === 'set') {
            const value = COLOUR_FIELDS.includes(name) ? normalise(choice.value) : choice.value;
            if (value === '') {
                return null;
            }
            entries.push({name, action: 'set', value});
        }
    }
    return entries;
};

/**
 * Every loaded competency in the tree's order, depth first, whether its parent is open or not.
 *
 * @param {object} tree The tree.
 * @returns {Array} The ids.
 */
export const treeOrder = (tree: Tree): number[] => {
    const ids: number[] = [];
    /**
     * Add a parent's loaded children, and theirs.
     *
     * @param {number} parent The parent.
     * @returns {void}
     */
    const walk = (parent: number): void => {
        (tree.branches[parent]?.ids ?? []).forEach((id) => {
            if (tree.nodes[id]) {
                ids.push(id);
                walk(id);
            }
        });
    };
    walk(ROOT);
    return ids;
};

/**
 * The picked competencies none of whose ancestors is picked, in the tree's order.
 *
 * @param {object} tree The tree.
 * @param {Array} ids The picked competencies.
 * @returns {Array} The topmost ones.
 */
export const topmost = (tree: Tree, ids: number[]): number[] => {
    const picked = new Set(ids);
    return treeOrder(tree).filter((id) => picked.has(id) && !ancestors(tree, id).some((above) => picked.has(above)));
};

/**
 * Whether a parent may take every subject of a move: not one of them, nor inside one of them.
 *
 * @param {object} tree The tree.
 * @param {Array} subjects The competencies to move.
 * @param {number} target The new parent, ROOT for the top level.
 * @returns {boolean} Whether it may.
 */
export const targetAllowed = (tree: Tree, subjects: number[], target: number): boolean =>
    target === ROOT || !subjects.some((id) => isWithin(tree, target, id));

/**
 * A move of several competencies under one parent: the topmost picked, in the tree's order, less those already
 * under it, which are skipped.
 *
 * @param {object} tree The tree.
 * @param {Array} ids The picked competencies.
 * @param {number} target The new parent, ROOT for the top level.
 * @returns {object} The ids to move and the ids skipped, each in order.
 */
export const movePlan = (tree: Tree, ids: number[], target: number): {move: number[], skipped: number[]} => {
    const subjects = topmost(tree, ids);
    return {
        move: subjects.filter((id) => tree.nodes[id].parentid !== target),
        skipped: subjects.filter((id) => tree.nodes[id].parentid === target),
    };
};

/**
 * The names a dialogue lists: the first five, and how many more.
 *
 * @param {Array} names Every name, in order.
 * @returns {object} The names shown and the number left out.
 */
export const preview = (names: string[]): {shown: string[], more: number} =>
    ({shown: names.slice(0, PREVIEW_NAMES), more: Math.max(0, names.length - PREVIEW_NAMES)});

/** One competency's delete check, or the failure to read it. */
export type CheckedDeletion = {id: number, name: string, check: DeleteCheck | null};

/** What the delete confirmation says. */
export type DeletionSummary = {
    // The competencies core will delete, in order.
    allowed: number[],
    // How many competencies below them go with them.
    descendants: number,
    // The competencies core will refuse, each with why.
    refused: {id: number, name: string, reasons: string[]}[],
    // Whether a parent of an allowed one has a rule, which core resets.
    parentrule: boolean,
};

/**
 * Sort the delete checks into what core will delete and what it will refuse, with why. A check that could not be
 * read counts as a refusal with no reason: the competency is never attempted.
 *
 * @param {object} labels The labels.
 * @param {Array} checks The checks, in order.
 * @returns {object} The summary.
 */
export const deletionSummary = (labels: Labels, checks: CheckedDeletion[]): DeletionSummary => {
    const summary: DeletionSummary = {allowed: [], descendants: 0, refused: [], parentrule: false};
    checks.forEach(({id, name, check}) => {
        if (check && check.candelete) {
            summary.allowed.push(id);
            summary.descendants += check.descendants;
            summary.parentrule = summary.parentrule || check.parenthasrule;
        } else {
            summary.refused.push({id, name, reasons: check ? blockedReasons(labels, check) : []});
        }
    });
    return summary;
};

/**
 * The dock's count: how many are picked and, when some of them sit in closed branches, how many are not shown. The
 * picks outlive a closed branch, so the count says it rather than name only what the reader sees (the Design System
 * keeps a selection to what is on screen, D131; the workspace keeps it across opening and closing, and says so).
 *
 * @param {object} labels The labels.
 * @param {number} count How many are picked.
 * @param {number} hidden How many of them are not drawn.
 * @returns {string} The words.
 */
export const dockCount = (labels: Labels, count: number, hidden: number): string => {
    const picked = fillCount(labels, 'hubv2_bulk_selected', count);
    return hidden > 0 ? `${picked} ${fillCount(labels, 'hubv2_bulk_notshown', hidden)}` : picked;
};

/**
 * What the polite region says after an appearance change.
 *
 * @param {object} labels The labels.
 * @param {number} changed How many competencies' values changed.
 * @returns {string} The words.
 */
export const appearanceOutcome = (labels: Labels, changed: number): string =>
    (changed === 0 ? labels.hubv2_bulk_appearance_none : fillCount(labels, 'hubv2_bulk_appearance_done', changed));

/** How a bulk move ended. */
export type MoveResult = {
    moved: number,
    skipped: number,
    // The competency core refused, and its words; null when none was.
    refused: {name: string, reason: string} | null,
    // How many were not tried after the refusal.
    left: number,
};

/**
 * What the polite region says after a bulk move: how many moved, how many were skipped as already there, and, when
 * core refused one, which and why, and how many were not tried.
 *
 * @param {object} labels The labels.
 * @param {object} result How it ended.
 * @returns {string} The words.
 */
export const moveOutcome = (labels: Labels, result: MoveResult): string => {
    const parts = [fillCount(labels, 'hubv2_bulk_move_done', result.moved)];
    if (result.skipped > 0) {
        parts.push(fillCount(labels, 'hubv2_bulk_move_skipped', result.skipped));
    }
    if (result.refused) {
        parts.push(fillObject(labels.hubv2_bulk_move_refused, {name: result.refused.name, reason: result.refused.reason}));
        if (result.left > 0) {
            parts.push(fillCount(labels, 'hubv2_bulk_move_left', result.left));
        }
    }
    return parts.join(' ');
};

/**
 * What the polite region says after a bulk delete: how many were deleted, and which were not.
 *
 * @param {object} labels The labels.
 * @param {number} deleted How many core deleted.
 * @param {Array} kept The names of those it did not delete (refused by the check, or by core at the commit).
 * @returns {string} The words.
 */
export const deleteOutcome = (labels: Labels, deleted: number, kept: string[]): string => {
    const done = fillCount(labels, 'hubv2_bulk_delete_done', deleted);
    return kept.length > 0 ? `${done} ${fill(labels.hubv2_bulk_delete_kept, kept.join(', '))}` : done;
};
