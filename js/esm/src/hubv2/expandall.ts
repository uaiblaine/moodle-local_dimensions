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
 * The outline's "Expand all" and "Collapse all": which closed branches to open next, and where to stop.
 *
 * The tree is lazy, so expanding all is a walk, level by level: each round opens every closed parent
 * at the shallowest depth that has one, loading the first page of those never opened, and the next
 * round looks again at what that revealed. It stops before the visible competencies would pass
 * EXPAND_ALL_BOUND, and says so; a parent's further pages stay behind its "load more" item, as they
 * do when it is opened by hand.
 *
 * Pure: the workspace loads and commits what a step names.
 *
 * @module     local_dimensions/hubv2/expandall
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {isExpanded, setExpanded, visibleItems} from './tree';
import type {Tree} from './tree';

/**
 * The most competencies "Expand all" shows. Both panes keep every drawn item in the DOM: the map about
 * 14 elements a node, and the map stopped being smooth from about 1,500 nodes (S0.md, measure (c)).
 * At 500 the Both layout draws each one twice and stays well under that, and the walk makes at most
 * one request per parent it opens.
 */
export const EXPAND_ALL_BOUND = 500;

/** What one round of "Expand all" does. */
export type ExpandStep = {
    // Closed parents whose children are loaded: open them.
    open: number[],
    // Closed parents never opened: load their first page, then open them.
    load: number[],
    // Whether the walk stops at the bound with closed parents left.
    capped: boolean,
};

/**
 * How many competencies the panes draw.
 *
 * @param {object} tree The tree.
 * @returns {number} The count.
 */
export const visibleCount = (tree: Tree): number => visibleItems(tree).filter((item) => item.kind === 'competency').length;

/**
 * The next round of "Expand all".
 *
 * Every closed parent at the shallowest depth that has one is taken in the order the panes draw
 * them, as long as the competencies it would add fit under the bound: a loaded branch adds what
 * opening it shows (its children and any open branches below them), an unloaded one is counted as a
 * full first page. The first parent that does not fit ends the walk.
 *
 * @param {object} tree The tree.
 * @param {number} bound The most competencies to show.
 * @param {number} pagesize The size of a first page.
 * @returns {object} The step: nothing to open and not capped when every visible branch is open.
 */
export const expandStep = (tree: Tree, bound: number, pagesize: number): ExpandStep => {
    const closed = visibleItems(tree).flatMap((item) => (
        item.kind === 'competency' && tree.nodes[item.id]?.haschildren && !isExpanded(tree, item.id)
            ? [{id: item.id, depth: item.depth}] : []
    ));
    if (closed.length === 0) {
        return {open: [], load: [], capped: false};
    }
    const depth = Math.min(...closed.map((item) => item.depth));
    const step: ExpandStep = {open: [], load: [], capped: false};
    let shown = visibleCount(tree);
    let opened = tree;
    for (const item of closed.filter((candidate) => candidate.depth === depth)) {
        const loaded = tree.branches[item.id] !== undefined;
        const cost = loaded ? visibleCount(setExpanded(opened, item.id, true)) - visibleCount(opened) : pagesize;
        if (shown + cost > bound) {
            step.capped = true;
            break;
        }
        shown += cost;
        if (loaded) {
            opened = setExpanded(opened, item.id, true);
            step.open.push(item.id);
        } else {
            step.load.push(item.id);
        }
    }
    return step;
};

/**
 * Close every branch. The loaded children stay, so opening one again asks the server nothing.
 *
 * @param {object} tree The tree.
 * @returns {object} The new tree.
 */
export const collapseAll = (tree: Tree): Tree => (tree.expanded.length === 0 ? tree : {...tree, expanded: []});
