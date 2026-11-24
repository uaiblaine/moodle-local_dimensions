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
 * A left-to-right tidy tree: the root at the left, one column per depth.
 *
 * Every leaf takes the next row, in the order given, and every parent sits level with the middle
 * of its first and last child, so subtrees never overlap and siblings keep the server's order. A
 * gap after each top-level subtree that has children keeps the framework's areas apart. In a language
 * written right to left the same tree is mirrored, root at the right ({@see mirrorLayout()}). Pure: no
 * library and no DOM, so Jest pins it; positions are the top-left corners React Flow places nodes by.
 *
 * @module     local_dimensions/hubv2/layout
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** One node to place: its key, and its parent's key (null for the root). */
export type LayoutInput = {key: string, parent: string | null};

/** The grid the tree is laid on, in pixels. */
export type LayoutOptions = {columnWidth: number, rowHeight: number, groupGap: number};

/** A node's top-left corner. */
export type Point = {x: number, y: number};

/** The canvas's grid: a card is 240 by 76, so columns and rows leave room for the edges. */
export const GRID: LayoutOptions = {columnWidth: 320, rowHeight: 100, groupGap: 40};

/**
 * Place a tree.
 *
 * A node whose parent is not in the input is placed as a root, below the roots before it, so a
 * stale entry cannot drop a subtree off the canvas.
 *
 * @param {Array} items The nodes, parents before their children, siblings in display order.
 * @param {object} options The grid.
 * @returns {object} Map of key to position.
 */
export const layoutTree = (items: LayoutInput[], options: LayoutOptions = GRID): Record<string, Point> => {
    const keys = new Set(items.map((item) => item.key));
    const children: Record<string, string[]> = {};
    const roots: string[] = [];
    items.forEach((item) => {
        if (item.parent === null || !keys.has(item.parent)) {
            roots.push(item.key);
            return;
        }
        (children[item.parent] ??= []).push(item.key);
    });

    const positions: Record<string, Point> = {};
    let cursor = 0;
    /**
     * Place a node's subtree, then the node level with the middle of its children.
     *
     * @param {string} key The node.
     * @param {number} depth Its column.
     * @returns {void}
     */
    const place = (key: string, depth: number): void => {
        const kids = children[key] ?? [];
        if (kids.length === 0) {
            positions[key] = {x: depth * options.columnWidth, y: cursor};
            cursor += options.rowHeight;
            return;
        }
        kids.forEach((kid) => {
            place(kid, depth + 1);
            if (depth === 0 && (children[kid] ?? []).length > 0) {
                cursor += options.groupGap;
            }
        });
        const first = positions[kids[0]].y;
        const last = positions[kids[kids.length - 1]].y;
        positions[key] = {x: depth * options.columnWidth, y: (first + last) / 2};
    };
    roots.forEach((root) => place(root, 0));
    return positions;
};

/**
 * Mirror a placed tree for a language written right to left: the root at the right, each depth one
 * column further left, every row where it was.
 *
 * Positions are top-left corners and every card has the same width, so negating x mirrors the whole
 * tree about the root's column and keeps every gap between columns; the root stays at the origin.
 *
 * @param {object} positions Map of key to position, from layoutTree().
 * @returns {object} The mirrored map.
 */
export const mirrorLayout = (positions: Record<string, Point>): Record<string, Point> => {
    const mirrored: Record<string, Point> = {};
    Object.entries(positions).forEach(([key, point]) => {
        // 0 - x, not -x: the root column stays at +0, never -0.
        mirrored[key] = {x: 0 - point.x, y: point.y};
    });
    return mirrored;
};
