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
 * a move there changes nothing.
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
 * The workspace's tree as the dialogue walks it: its own opened branches, and the moved competency
 * closed and childless, so the keys and the rows never go below it.
 *
 * @param {object} tree The workspace's tree.
 * @param {Array} open The branches the dialogue opened.
 * @param {number} moving The competency being moved.
 * @returns {object} The tree.
 */
export const pickerTree = (tree: Tree, open: number[], moving: number): Tree => {
    const node = tree.nodes[moving];
    return {
        nodes: node ? {...tree.nodes, [moving]: {...node, haschildren: false}} : tree.nodes,
        branches: tree.branches,
        expanded: open.filter((id) => id !== moving && tree.branches[id] !== undefined && !!tree.nodes[id]?.haschildren),
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
 * The dialogue's tree rows, the framework first.
 *
 * @param {object} tree The workspace's tree.
 * @param {Array} open The branches the dialogue opened.
 * @param {number} moving The competency being moved.
 * @param {string} topname The framework row's name: the top level.
 * @returns {Array} The rows.
 */
export const parentChoices = (tree: Tree, open: number[], moving: number, topname: string): ParentChoice[] => {
    const currentparent = tree.nodes[moving]?.parentid ?? ROOT;
    return outlineRows(pickerTree(tree, open, moving), topname).map((row) => {
        if (row.kind === 'more') {
            return {...row, disabled: false, current: false};
        }
        const current = row.id === currentparent;
        return {...row, current, disabled: !canMoveUnder(tree, row.id, moving, currentparent)};
    });
};

/**
 * Whether a search hit may become the new parent: not the competency, not one of its descendants
 * (the hit's own ancestors name it), not its current parent.
 *
 * @param {object} item The hit, with its ancestors.
 * @param {number} moving The competency being moved.
 * @param {number} currentparent Its parent now.
 * @returns {boolean} Whether it may.
 */
export const hitSelectable = (item: PathItem, moving: number, currentparent: number): boolean =>
    item.id !== moving && !item.pathids.includes(moving) && item.id !== currentparent;
