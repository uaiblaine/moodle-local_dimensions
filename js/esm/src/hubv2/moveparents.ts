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
 * The "Move to" dialogue's choices: the parents a competency may move under, as a tree of its own over
 * the workspace's store, and the search hits it may take.
 *
 * The dialogue's tree is the workspace's tree with the dialogue's own opened branches: what it loads
 * goes into the shared store (so the panes reuse it), and what it opens leaves the panes alone. The
 * framework is the root, standing for the top level. The competency being moved is drawn and cannot be
 * chosen, and it never opens, so none of its descendants is ever offered (core would make the move a
 * cycle; the canvas refuses before asking). Its current parent is marked and cannot be chosen either:
 * a move there changes nothing. The bulk dock moves several competencies at once: each of them is closed and
 * cannot be chosen, and a parent is marked current, and refused, only when it is every one's parent already (a
 * competency already under the chosen parent is skipped, bulk.movePlan).
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/moveparents
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {outlineRows} from './outlinerows';
import type {OutlineRow} from './outlinerows';
import {ROOT, isWithin} from './tree';
import type {Tree} from './tree';
import type {PathItem} from './types';

/** One row of the dialogue's tree. */
export type ParentChoice = OutlineRow & {
    // Whether the row cannot be chosen: the competency itself, or its current parent.
    disabled: boolean,
    // Whether the row is the competency's current parent (the framework for a top-level one).
    current: boolean,
};

/**
 * The competencies being moved, as a list.
 *
 * @param {number|Array} moving The competency, or the competencies.
 * @returns {Array} The ids.
 */
const subjectsOf = (moving: number | number[]): number[] => (Array.isArray(moving) ? moving : [moving]);

/**
 * The workspace's tree as the dialogue walks it: its own opened branches, and the moved competencies
 * closed and childless, so the keys and the rows never go below them.
 *
 * @param {object} tree The workspace's tree.
 * @param {Array} open The branches the dialogue opened.
 * @param {number|Array} moving The competency being moved, or the competencies.
 * @returns {object} The tree.
 */
export const pickerTree = (tree: Tree, open: number[], moving: number | number[]): Tree => {
    const subjects = subjectsOf(moving);
    const nodes = {...tree.nodes};
    subjects.forEach((id) => {
        if (nodes[id]) {
            nodes[id] = {...nodes[id], haschildren: false};
        }
    });
    return {
        nodes,
        branches: tree.branches,
        expanded: open.filter((id) => !subjects.includes(id) && tree.branches[id] !== undefined && !!tree.nodes[id]?.haschildren),
    };
};

/**
 * Whether a competency may become the new parent; ROOT is the top level.
 *
 * @param {object} tree The workspace's tree.
 * @param {number} target The candidate parent.
 * @param {number} moving The competency being moved.
 * @param {number} currentparent Its parent now.
 * @returns {boolean} Whether it may.
 */
export const canMoveUnder = (tree: Tree, target: number, moving: number, currentparent: number): boolean => {
    if (target === currentparent) {
        return false;
    }
    return target === ROOT || !isWithin(tree, target, moving);
};

/**
 * The parent every competency being moved shares, or null when they do not share one.
 *
 * @param {object} tree The workspace's tree.
 * @param {Array} subjects The competencies being moved.
 * @returns {number|null} The shared parent, ROOT for the top level.
 */
export const sharedParent = (tree: Tree, subjects: number[]): number | null => {
    const parents = new Set(subjects.map((id) => tree.nodes[id]?.parentid ?? ROOT));
    return parents.size === 1 ? [...parents][0] : null;
};

/**
 * The dialogue's tree rows, the framework first.
 *
 * @param {object} tree The workspace's tree.
 * @param {Array} open The branches the dialogue opened.
 * @param {number|Array} moving The competency being moved, or the competencies.
 * @param {string} topname The framework row's name: the top level.
 * @returns {Array} The rows.
 */
export const parentChoices = (tree: Tree, open: number[], moving: number | number[], topname: string): ParentChoice[] => {
    const subjects = subjectsOf(moving);
    const currentparent = sharedParent(tree, subjects);
    return outlineRows(pickerTree(tree, open, subjects), topname).map((row) => {
        if (row.kind === 'more') {
            return {...row, disabled: false, current: false};
        }
        const current = row.id === currentparent;
        const inside = row.id !== ROOT && subjects.some((id) => isWithin(tree, row.id, id));
        return {...row, current, disabled: current || inside};
    });
};

/**
 * Whether a search hit may become the new parent: not a competency being moved, not one of their descendants
 * (the hit's own ancestors name it), not the parent they all have now.
 *
 * @param {object} item The hit, with its ancestors.
 * @param {number|Array} moving The competency being moved, or the competencies.
 * @param {number|null} currentparent The parent they all have now, or null when they have several.
 * @returns {boolean} Whether it may.
 */
export const hitSelectable = (item: PathItem, moving: number | number[], currentparent: number | null): boolean =>
    subjectsOf(moving).every((id) => item.id !== id && !item.pathids.includes(id)) && item.id !== currentparent;
