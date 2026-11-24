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
 * Where the focus goes when one of the workspace's layers closes (a node's menu, the palette, "Move to"),
 * so it never falls to the page's body.
 *
 * A layer opened on an item (a row or a node, by a key or by its "more" button) gives the focus back to
 * that item, in its pane, which owns the Tab stop: the "more" button itself is out of the Tab order. A
 * layer opened by a control outside the panes (the panel's "More actions", the "Commands" button) gives
 * it back to that control while it is still on the page. A layer opened by another layer that has since
 * closed (the palette or a menu running "Move to"), or whose control is gone, gives it to the selected
 * item of the pane that last had the focus, or of the pane still drawn.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/focusreturn
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** One of the two panes. */
export type Pane = 'outline' | 'map';

/** What opened a layer. */
export type LayerOrigin =
    {kind: 'item', pane: Pane}
    | {kind: 'control', connected: boolean}
    | {kind: 'layer'};

/** Where the focus goes: the control that opened the layer, an item of a pane, or nowhere to go. */
export type FocusReturn = {kind: 'control'} | {kind: 'item', pane: Pane} | {kind: 'none'};

/**
 * The pane to give the focus to: the one asked for when it is drawn, else the outline, else the map.
 *
 * @param {string|null} wanted The pane asked for, or null.
 * @param {object} panes Which panes are drawn.
 * @returns {string|null} The pane, or null when neither is drawn.
 */
export const drawnPane = (wanted: Pane | null, panes: {outline: boolean, map: boolean}): Pane | null => {
    if (wanted && panes[wanted]) {
        return wanted;
    }
    if (panes.outline) {
        return 'outline';
    }
    return panes.map ? 'map' : null;
};

/**
 * Where the focus goes when a layer closes.
 *
 * @param {object} origin What opened the layer.
 * @param {string|null} lastpane The pane that last had the focus, or null.
 * @param {object} panes Which panes are drawn now.
 * @returns {object} Where the focus goes.
 */
export const focusReturn = (origin: LayerOrigin, lastpane: Pane | null, panes: {outline: boolean, map: boolean}): FocusReturn => {
    if (origin.kind === 'control' && origin.connected) {
        return {kind: 'control'};
    }
    const pane = drawnPane(origin.kind === 'item' ? origin.pane : lastpane, panes);
    return pane ? {kind: 'item', pane} : {kind: 'none'};
};
