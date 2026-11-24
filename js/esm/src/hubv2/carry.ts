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
 * What the workspace keeps across a reload of its pane: the branches the viewer opened and the selection.
 *
 * An action on the framework (an edit of its name or taxonomies, its scale, its visibility, a duplicate) reloads
 * the Competencies tab's pane, so the framework select, the root node and every competency's taxonomy are the
 * server's again; the workspace is mounted afresh. Before the reload the workspace holds what the viewer had
 * opened, and the new mount takes it back when it shows the same framework, opening the same branches (as
 * many children loaded as before) and selecting the same item. Another framework takes nothing.
 *
 * Node-local by nature: it lives in this page's module, for one reload. The tree is pure (tree.ts), so Jest pins
 * what is held without a DOM.
 *
 * @module     local_dimensions/hubv2/carry
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {ROOT, ancestors} from './tree';
import type {Tree} from './tree';

/** A competency and its ancestors, root first. */
export type Placed = {id: number, pathids: number[]};

/** What a reload keeps. */
export type Carry = {
    frameworkid: number,
    // The opened competencies, a parent before its children.
    expanded: Placed[],
    // How many children of each parent were loaded (ROOT for the top level).
    loaded: Record<number, number>,
    // The selected competency, or null for the framework.
    selected: Placed | null,
};

/** The one held, until the next mount takes it. */
let held: Carry | null = null;

/**
 * What a tree keeps across a reload.
 *
 * @param {number} frameworkid The framework shown.
 * @param {object} tree The tree.
 * @param {number|null} selected The selected competency, or null for the framework.
 * @returns {object} The carry.
 */
export const carryOf = (frameworkid: number, tree: Tree, selected: number | null): Carry => {
    const expanded = tree.expanded
        .filter((id) => tree.nodes[id] !== undefined)
        .map((id) => ({id, pathids: ancestors(tree, id)}))
        .sort((one, other) => one.pathids.length - other.pathids.length);
    const loaded: Record<number, number> = {};
    Object.entries(tree.branches).forEach(([parent, branch]) => {
        loaded[Number(parent)] = branch.ids.length;
    });
    return {
        frameworkid,
        expanded,
        loaded,
        selected: selected !== null && selected !== ROOT && tree.nodes[selected]
            ? {id: selected, pathids: ancestors(tree, selected)}
            : null,
    };
};

/**
 * Hold what the next mount takes back.
 *
 * @param {object} carry The carry.
 * @returns {void}
 */
export const hold = (carry: Carry): void => {
    held = carry;
};

/**
 * Take what was held, once: only a mount of the same framework gets it, and nothing is held after.
 *
 * @param {number} frameworkid The framework the mount shows.
 * @returns {object|null} The carry, or null.
 */
export const take = (frameworkid: number): Carry | null => {
    const carry = held;
    held = null;
    return carry && carry.frameworkid === frameworkid ? carry : null;
};
