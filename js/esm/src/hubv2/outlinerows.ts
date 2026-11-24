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
 * The outline's rows: one per stop of the keyboard walk (keynav.navOrder), with what the APG Tree View
 * pattern needs on each when the whole set is not in the DOM.
 *
 * The outline is a flat list of treeitems, each carrying aria-level, aria-setsize and aria-posinset,
 * rather than nested groups: the tree is lazy, so a parent's set is never whole in the DOM and the
 * three attributes are needed on every item anyway, and a flat list keeps the rows in exactly the
 * walk's order, one to one, which is what the shared key map moves through. The framework is the
 * root item at level 1; a competency's level is its depth plus one; a parent's set size is its total
 * from the server, not what is loaded. A "load more" item stands at the level of the children it
 * loads, after them, at the position the next child will take.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/outlinerows
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {FRAMEWORK_ID, competencyKey, moreKey, parentKey} from './graph';
import {ROOT, isExpanded, visibleItems} from './tree';
import type {Tree} from './tree';
import type {StructureNode} from './types';

/** One row of the outline. */
export type OutlineRow = {
    key: string,
    kind: 'framework' | 'competency' | 'more',
    // The competency, ROOT for the framework, the parent for a "load more" row.
    id: number,
    // The parent row's key; null for the framework.
    parent: string | null,
    level: number,
    setsize: number,
    posinset: number,
    expandable: boolean,
    expanded: boolean,
    // The name type-ahead matches; empty for a "load more" row.
    name: string,
    // A competency's own children total once its branch is loaded, else null.
    childcount: number | null,
    // How many children a "load more" row stands for; 0 on other rows.
    remaining: number,
    node: StructureNode | null,
};

/**
 * The outline's rows, in the walk's order.
 *
 * @param {object} tree The tree.
 * @param {string} frameworkname The framework's name, the root row's.
 * @returns {Array} The rows.
 */
export const outlineRows = (tree: Tree, frameworkname: string): OutlineRow[] => {
    const rows: OutlineRow[] = [{
        key: FRAMEWORK_ID, kind: 'framework', id: ROOT, parent: null, level: 1, setsize: 1, posinset: 1, expandable: true,
        expanded: true, name: frameworkname, childcount: tree.branches[ROOT]?.total ?? 0, remaining: 0, node: null,
    }];
    visibleItems(tree).forEach((item) => {
        const siblings = tree.branches[item.parent];
        const total = siblings ? siblings.total : 1;
        if (item.kind === 'more') {
            rows.push({
                key: moreKey(item.parent), kind: 'more', id: item.parent, parent: parentKey(item.parent), level: item.depth + 1,
                setsize: total, posinset: (siblings ? siblings.ids.length : 0) + 1, expandable: false, expanded: false, name: '',
                childcount: null, remaining: item.remaining, node: null,
            });
            return;
        }
        const node = tree.nodes[item.id];
        const branch = tree.branches[item.id];
        rows.push({
            key: competencyKey(item.id), kind: 'competency', id: item.id, parent: parentKey(item.parent), level: item.depth + 1,
            setsize: total, posinset: (siblings ? siblings.ids.indexOf(item.id) : 0) + 1, expandable: node.haschildren,
            expanded: node.haschildren && isExpanded(tree, item.id), name: node.shortname,
            childcount: branch ? branch.total : null, remaining: 0, node,
        });
    });
    return rows;
};

/** One part of a row's quiet meta, after its name. */
export type MetaPart = {kind: 'count', value: number} | {kind: 'rule', label: string} | {kind: 'related', value: number};

/**
 * What a row shows after its name, in that order: its count (the framework's competencies, a loaded
 * parent's children), a rule's mark, and its related competencies when they are known and any.
 *
 * Each part is short (a number, an icon, an icon and a number) and gives way before the name does;
 * the rule's label and the words of each part are in the row's description, never only on screen.
 * A "load more" row carries none: it says what is left in its own name.
 *
 * @param {object} row The row.
 * @param {number} competencies The framework's competency count.
 * @param {number|null} related The row's related competencies, when known.
 * @returns {Array} The parts.
 */
export const rowMeta = (row: OutlineRow, competencies: number, related: number | null): MetaPart[] => {
    if (row.kind === 'more') {
        return [];
    }
    if (row.kind === 'framework') {
        return [{kind: 'count', value: competencies}];
    }
    const parts: MetaPart[] = [];
    if (row.childcount !== null) {
        parts.push({kind: 'count', value: row.childcount});
    }
    if (row.node?.ruletype) {
        parts.push({kind: 'rule', label: row.node.rulelabel});
    }
    if (related !== null && related > 0) {
        parts.push({kind: 'related', value: related});
    }
    return parts;
};

/**
 * Where the outline's list must scroll to show a row whole, or null when it already does.
 *
 * Only the outline's own list scrolls, never the page: a selection made on the map must not move the
 * map out of view.
 *
 * @param {number} top The row's top, from the list's top, in pixels.
 * @param {number} height The row's height.
 * @param {number} scrolltop The list's scroll position.
 * @param {number} viewheight The list's visible height.
 * @returns {number|null} The new scroll position, or null.
 */
export const scrollToShow = (top: number, height: number, scrolltop: number, viewheight: number): number | null => {
    if (top < scrolltop) {
        return top;
    }
    if (top + height > scrolltop + viewheight) {
        return Math.max(0, top + height - viewheight);
    }
    return null;
};
