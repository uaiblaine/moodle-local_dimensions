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
 * The lazily loaded tree the canvas draws: which competencies are known, which branches are loaded
 * and which are open.
 *
 * Pure and immutable: every function returns a new tree and leaves its argument alone, so React
 * state holds it as it is and Jest reads it without a DOM. A branch is the loaded part of a
 * parent's direct children, in the server's sort order, with the parent's total beside it: fewer
 * ids than the total means more pages to load.
 *
 * @module     local_dimensions/hubv2/tree
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {NodePage, StructureNode} from './types';

/** The parent id of the root competencies: the framework itself. */
export const ROOT = 0;

/** The loaded part of one parent's children. */
export type Branch = {ids: number[], total: number};

/** The whole client-side tree. */
export type Tree = {
    nodes: Record<number, StructureNode>,
    branches: Record<number, Branch>,
    expanded: number[],
};

/** One entry of what the canvas draws, in depth-first order. */
export type VisibleItem =
    {kind: 'competency', id: number, parent: number, depth: number}
    | {kind: 'more', parent: number, depth: number, remaining: number};

/**
 * A copy of the tree whose three members can be changed without touching the original.
 *
 * @param {object} tree The tree.
 * @returns {object} The copy.
 */
const copy = (tree: Tree): Tree => ({
    nodes: {...tree.nodes},
    branches: {...tree.branches},
    expanded: [...tree.expanded],
});

/**
 * Forget everything below a competency, in place on a copy: its loaded children, theirs, and
 * whether any of them was open. The competency itself stays.
 *
 * @param {object} tree A copy made by copy().
 * @param {number} id The competency (or ROOT).
 * @returns {void}
 */
const pruneBelow = (tree: Tree, id: number): void => {
    const branch = tree.branches[id];
    if (!branch) {
        return;
    }
    branch.ids.forEach((child) => {
        pruneBelow(tree, child);
        delete tree.nodes[child];
        tree.expanded = tree.expanded.filter((open) => open !== child);
    });
    delete tree.branches[id];
};

/**
 * The tree of a framework's first page of root competencies.
 *
 * @param {object} roots The first page of roots, as the page embeds it.
 * @returns {object} The tree.
 */
export const createTree = (roots: NodePage): Tree =>
    insertChildren({nodes: {}, branches: {}, expanded: []}, ROOT, roots);

/**
 * Put a page of a parent's children into the tree.
 *
 * Appended after the loaded ones for a further page; otherwise the page replaces the branch, and a
 * child it no longer names is forgotten with everything below it (deleted or moved away).
 *
 * @param {object} tree The tree.
 * @param {number} parentid The parent (ROOT for the root competencies).
 * @param {object} page The page, with the parent's total.
 * @param {boolean} append Whether the page follows the loaded ones.
 * @returns {object} The new tree.
 */
export const insertChildren = (tree: Tree, parentid: number, page: NodePage, append: boolean = false): Tree => {
    const next = copy(tree);
    const incoming = page.items.map((item) => item.id);
    const previous = next.branches[parentid]?.ids ?? [];
    if (!append) {
        previous.filter((id) => !incoming.includes(id)).forEach((gone) => {
            pruneBelow(next, gone);
            delete next.nodes[gone];
            next.expanded = next.expanded.filter((open) => open !== gone);
        });
    }
    const kept = append ? previous.filter((id) => !incoming.includes(id)) : [];
    next.branches[parentid] = {ids: [...kept, ...incoming], total: page.total};
    page.items.forEach((item) => {
        next.nodes[item.id] = item;
    });
    return next;
};

/**
 * Whether a competency's children are drawn. The framework's always are.
 *
 * @param {object} tree The tree.
 * @param {number} id The competency.
 * @returns {boolean} Whether it is open.
 */
export const isExpanded = (tree: Tree, id: number): boolean => id === ROOT || tree.expanded.includes(id);

/**
 * Whether a parent's children are loaded at all.
 *
 * @param {object} tree The tree.
 * @param {number} id The parent.
 * @returns {boolean} Whether a branch is loaded.
 */
export const hasBranch = (tree: Tree, id: number): boolean => tree.branches[id] !== undefined;

/**
 * Open or close a competency. Closing keeps its loaded children, so opening again asks nothing.
 *
 * @param {object} tree The tree.
 * @param {number} id The competency.
 * @param {boolean} open Whether to open it.
 * @returns {object} The new tree.
 */
export const setExpanded = (tree: Tree, id: number, open: boolean): Tree => {
    if (id === ROOT || isExpanded(tree, id) === open) {
        return tree;
    }
    const next = copy(tree);
    next.expanded = open ? [...next.expanded, id] : next.expanded.filter((other) => other !== id);
    return next;
};

/**
 * Replace a competency's fields with fresher ones, keeping its place and its loaded children;
 * a competency that has no children any more loses its branch and closes.
 *
 * @param {object} tree The tree.
 * @param {object} node The fresh node.
 * @returns {object} The new tree.
 */
export const replaceNode = (tree: Tree, node: StructureNode): Tree => {
    const next = copy(tree);
    next.nodes[node.id] = node;
    if (!node.haschildren) {
        pruneBelow(next, node.id);
        next.expanded = next.expanded.filter((open) => open !== node.id);
    }
    return next;
};

/**
 * Forget a competency's children and close it, so the next opening loads them afresh (after a
 * move their depth, and so their taxonomy, changed).
 *
 * @param {object} tree The tree.
 * @param {number} id The competency.
 * @returns {object} The new tree.
 */
export const forgetBranch = (tree: Tree, id: number): Tree => {
    const next = copy(tree);
    pruneBelow(next, id);
    next.expanded = next.expanded.filter((open) => open !== id);
    return next;
};

/**
 * Remove a competency and everything below it, as after its deletion.
 *
 * @param {object} tree The tree.
 * @param {number} id The competency.
 * @returns {object} The new tree.
 */
export const removeNode = (tree: Tree, id: number): Tree => {
    const node = tree.nodes[id];
    if (!node) {
        return tree;
    }
    const next = copy(tree);
    pruneBelow(next, id);
    delete next.nodes[id];
    next.expanded = next.expanded.filter((open) => open !== id);
    const branch = next.branches[node.parentid];
    if (branch) {
        next.branches[node.parentid] = {ids: branch.ids.filter((other) => other !== id), total: Math.max(0, branch.total - 1)};
    }
    return next;
};

/**
 * A competency's known ancestors, the root first, itself excluded.
 *
 * @param {object} tree The tree.
 * @param {number} id The competency.
 * @returns {Array} The ancestor ids.
 */
export const ancestors = (tree: Tree, id: number): number[] => {
    const chain: number[] = [];
    let parent = tree.nodes[id]?.parentid ?? ROOT;
    while (parent !== ROOT && tree.nodes[parent] && !chain.includes(parent)) {
        chain.unshift(parent);
        parent = tree.nodes[parent].parentid;
    }
    return chain;
};

/**
 * Whether a competency is another one or sits below it: a parent it cannot be moved under.
 *
 * @param {object} tree The tree.
 * @param {number} id The candidate parent.
 * @param {number} ancestorid The competency being moved.
 * @returns {boolean} Whether id is ancestorid or one of its descendants.
 */
export const isWithin = (tree: Tree, id: number, ancestorid: number): boolean =>
    id === ancestorid || ancestors(tree, id).includes(ancestorid);

/**
 * The parents along a path whose children are still to load before its last competency can show.
 *
 * @param {object} tree The tree.
 * @param {Array} pathids The ancestor ids of a competency, root first.
 * @returns {Array} The parents to load, in order (ROOT first when even the roots are missing).
 */
export const missingBranches = (tree: Tree, pathids: number[]): number[] =>
    [ROOT, ...pathids].filter((id) => !hasBranch(tree, id));

/**
 * Open every competency along a path.
 *
 * @param {object} tree The tree.
 * @param {Array} pathids The ids to open.
 * @returns {object} The new tree.
 */
export const expandPath = (tree: Tree, pathids: number[]): Tree =>
    pathids.reduce((current, id) => setExpanded(current, id, true), tree);

/**
 * Whether a parent's loaded children include a competency.
 *
 * @param {object} tree The tree.
 * @param {number} parentid The parent.
 * @param {number} id The competency.
 * @returns {boolean} Whether it is loaded under that parent.
 */
export const branchHas = (tree: Tree, parentid: number, id: number): boolean =>
    tree.branches[parentid]?.ids.includes(id) ?? false;

/**
 * What the canvas draws: every competency under an open parent, depth first in the server's order,
 * and a "load more" entry after a branch that has more children than are loaded.
 *
 * @param {object} tree The tree.
 * @returns {Array} The visible items.
 */
export const visibleItems = (tree: Tree): VisibleItem[] => {
    const items: VisibleItem[] = [];
    /**
     * Add an open parent's loaded children, and theirs, at a depth.
     *
     * @param {number} parent The parent.
     * @param {number} depth The children's depth.
     * @returns {void}
     */
    const walk = (parent: number, depth: number): void => {
        const branch = tree.branches[parent];
        if (!branch || !isExpanded(tree, parent)) {
            return;
        }
        branch.ids.forEach((id) => {
            if (!tree.nodes[id]) {
                return;
            }
            items.push({kind: 'competency', id, parent, depth});
            walk(id, depth + 1);
        });
        if (branch.ids.length < branch.total) {
            items.push({kind: 'more', parent, depth, remaining: branch.total - branch.ids.length});
        }
    };
    walk(ROOT, 1);
    return items;
};
