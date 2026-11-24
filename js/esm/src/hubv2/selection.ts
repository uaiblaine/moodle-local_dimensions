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
 * The workspace's one selection and its layouts: what picking an item does, where each pane's Tab
 * stop sits after a change, and which panes a layout draws.
 *
 * The outline and the map are two projections of one store (Hub.tsx's Workspace): one tree, one
 * selected competency, one move in progress. Each pane keeps only its own Tab stop, and both stops
 * follow the selection while the focus is elsewhere, so Tab enters either pane at the competency the
 * other one selected (APG Tree View).
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/selection
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {entryKey, survivorKey} from './keynav';
import type {NavItem} from './keynav';
import {ROOT} from './tree';
import type {Tree} from './tree';

/** The workspace's layouts: the Competencies tab's views other than the classic list (structure::VIEWS). */
export const LAYOUTS = ['outline', 'map', 'both'] as const;

/** One of the workspace's layouts. */
export type Layout = typeof LAYOUTS[number];

/**
 * The event the tab's view switch (amd/src/central/structure.js) sends the workspace when the viewer
 * moves between two of its layouts, which the pane is not reloaded for. Its detail is {layout}.
 * hub_javascript_guards_test holds the two files to the same name.
 */
export const LAYOUT_EVENT = 'local_dimensions:hubv2-layout';

/**
 * Whether a value is one of the workspace's layouts.
 *
 * @param {unknown} value The value.
 * @returns {boolean} Whether it is.
 */
export const isLayout = (value: unknown): value is Layout => (LAYOUTS as readonly unknown[]).includes(value);

/**
 * Which panes a layout draws beside the panel.
 *
 * @param {string} layout The layout.
 * @returns {object} Whether the outline and the map are drawn.
 */
export const panesOf = (layout: Layout): {outline: boolean, map: boolean} =>
    ({outline: layout !== 'map', map: layout !== 'outline'});

/** What picking an item does: select it, or in the move mode make it the new parent. */
export type Activation = {type: 'select', id: number | null} | {type: 'pick', parent: number};

/**
 * What picking an item does, by click, Enter or Space, in either pane.
 *
 * @param {number|null} moving The competency being moved, or null outside the move mode.
 * @param {number} id The item picked: a competency, or ROOT for the framework.
 * @returns {object} The activation.
 */
export const activation = (moving: number | null, id: number): Activation => {
    if (moving !== null) {
        return {type: 'pick', parent: id};
    }
    return {type: 'select', id: id === ROOT ? null : id};
};

/** Where a pane's Tab stop goes after the tree, the order or the selection changed. */
export type Stop = {key: string, refocus: boolean};

/**
 * Where a pane's Tab stop goes after a change.
 *
 * While the focus is outside the pane its stop follows the selection: the selected item, else its
 * nearest drawn ancestor, else the framework. While the focus is inside, the stop stays on the
 * focused item; when that item has left the pane the stop moves to its nearest surviving ancestor
 * and the focus must follow it there (refocus), or it would fall to the page.
 *
 * @param {Array} previous The walk before the change.
 * @param {Array} order The walk after it.
 * @param {object} tree The tree after it.
 * @param {number|null} selected The selected competency, or null for the framework.
 * @param {string} active The pane's current stop.
 * @param {boolean} inside Whether the focus is on one of the pane's items.
 * @returns {object} The new stop.
 */
export const nextStop = (
    previous: NavItem[],
    order: NavItem[],
    tree: Tree,
    selected: number | null,
    active: string,
    inside: boolean,
): Stop => {
    if (!inside) {
        return {key: entryKey(tree, order, selected), refocus: false};
    }
    if (order.some((item) => item.key === active)) {
        return {key: active, refocus: false};
    }
    return {key: survivorKey(previous, order, active), refocus: true};
};
