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
 * The workspace's keyboard, shared by the outline and the map: which item a key moves to, what a
 * map node is called, where a letter typed in the outline jumps, and when the map pans.
 *
 * Each pane is one Tab stop (a roving tabindex): the active item carries tabindex 0, every other
 * one -1, and the arrow keys walk the tree's structure with the keys of the W3C APG Tree View
 * pattern and of core's own core/tree (lib/amd/src/tree.js, handleKeyDown): Right opens a closed
 * parent or moves to its first child, Left closes an open parent or moves to its parent, Up and
 * Down move through the visible items in tree order, Home and End to the first and last, Enter
 * and Space select (a "load more" item loads its page instead). In a right-to-left language Left
 * and Right swap, as core/tree swaps them: the outline is mirrored by the page's direction and the
 * map by its layout (layout.ts mirrorLayout), so the key towards the children is the key that opens.
 *
 * Both panes walk the same order (navOrder), the framework first: a competency's level is its
 * depth plus one in both, so the outline's aria-level and the map's spoken level agree.
 *
 * Pure: the functions read the tree and return what to do; the workspace does it.
 *
 * @module     local_dimensions/hubv2/keynav
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {FRAMEWORK_ID, competencyKey, moreKey, parentKey} from './graph';
import {fill, fillCount, fillObject} from './strings';
import type {Labels} from './strings';
import {ROOT, ancestors, isExpanded, visibleItems} from './tree';
import type {Tree} from './tree';

/** One stop of the keyboard walk, in the order both panes draw it. */
export type NavItem = {
    key: string,
    parent: string | null,
    kind: 'framework' | 'competency' | 'more',
    // The competency, ROOT for the framework, the parent for a "load more" item.
    id: number,
    expandable: boolean,
    expanded: boolean,
};

/** What a key asks the pane to do. */
export type NavAction =
    {type: 'focus', key: string}
    | {type: 'expand', id: number}
    | {type: 'collapse', id: number}
    | {type: 'select', id: number}
    | {type: 'loadmore', parent: number}
    | {type: 'none'};

/** A key and its modifiers, as a KeyboardEvent carries them. */
export type KeyPress = {key: string, altKey?: boolean, ctrlKey?: boolean, metaKey?: boolean, shiftKey?: boolean};

/** The keys the walk consumes; every other key is left to the browser. */
const WALK_KEYS = ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Home', 'End', 'Enter', ' '];

/**
 * Every stop of the walk: the framework first, then what the panes draw, depth first.
 *
 * @param {object} tree The tree.
 * @returns {Array} The stops.
 */
export const navOrder = (tree: Tree): NavItem[] => [
    {key: FRAMEWORK_ID, parent: null, kind: 'framework', id: ROOT, expandable: true, expanded: true},
    ...visibleItems(tree).map((item): NavItem => (item.kind === 'more'
        ? {key: moreKey(item.parent), parent: parentKey(item.parent), kind: 'more', id: item.parent, expandable: false,
            expanded: false}
        : {key: competencyKey(item.id), parent: parentKey(item.parent), kind: 'competency', id: item.id,
            expandable: !!tree.nodes[item.id]?.haschildren, expanded: isExpanded(tree, item.id)})),
];

/**
 * Whether a key is held with Alt, Ctrl, Meta or Shift: such a key is always the browser's.
 *
 * @param {object} key The key.
 * @returns {boolean} Whether a modifier is held.
 */
const modified = (key: KeyPress): boolean => !!(key.altKey || key.ctrlKey || key.metaKey || key.shiftKey);

/**
 * What the key towards the children does: open a closed parent, else go to its first child.
 *
 * @param {Array} order The stops.
 * @param {number} index The focused item's place in them.
 * @returns {object} The action.
 */
const intoChildren = (order: NavItem[], index: number): NavAction => {
    const item = order[index];
    if (!item.expandable) {
        return {type: 'none'};
    }
    if (!item.expanded) {
        return {type: 'expand', id: item.id};
    }
    const next = order[index + 1];
    return next && next.parent === item.key ? {type: 'focus', key: next.key} : {type: 'none'};
};

/**
 * What the key towards the root does: close an open competency, else go to the parent.
 *
 * @param {object} item The focused item.
 * @returns {object} The action.
 */
const towardsRoot = (item: NavItem): NavAction => {
    if (item.kind === 'competency' && item.expandable && item.expanded) {
        return {type: 'collapse', id: item.id};
    }
    return item.parent !== null ? {type: 'focus', key: item.parent} : {type: 'none'};
};

/**
 * Where Up, Down, Home and End go, or null for another key.
 *
 * @param {Array} order The stops.
 * @param {number} index The focused item's place in them.
 * @param {string} key The key.
 * @returns {object|null} The action.
 */
const alongOrder = (order: NavItem[], index: number, key: string): NavAction | null => {
    const targets: Record<string, number> = {ArrowDown: index + 1, ArrowUp: index - 1, Home: 0, End: order.length - 1};
    if (!(key in targets)) {
        return null;
    }
    const target = order[targets[key]];
    return target ? {type: 'focus', key: target.key} : {type: 'none'};
};

/**
 * What a key does on an item.
 *
 * A key the walk does not use, or one held with Alt, Ctrl, Meta or Shift, returns null and is left
 * to the browser, as core/tree leaves it. A used key that has nowhere to go returns 'none': the
 * caller still stops the page from scrolling.
 *
 * @param {Array} order The stops, from navOrder().
 * @param {string} current The focused item's key.
 * @param {object} key The key and its modifiers.
 * @param {boolean} rtl Whether Left and Right swap, as core/tree swaps them in a right-to-left language.
 * @returns {object|null} The action.
 */
export const keyAction = (order: NavItem[], current: string, key: KeyPress, rtl: boolean = false): NavAction | null => {
    if (modified(key) || order.length === 0) {
        return null;
    }
    const index = order.findIndex((item) => item.key === current);
    if (index < 0) {
        // The focused item left the walk under the key: start again from the top.
        return WALK_KEYS.includes(key.key) ? {type: 'focus', key: order[0].key} : null;
    }
    const item = order[index];
    if (key.key === (rtl ? 'ArrowLeft' : 'ArrowRight')) {
        return intoChildren(order, index);
    }
    if (key.key === (rtl ? 'ArrowRight' : 'ArrowLeft')) {
        return towardsRoot(item);
    }
    if (key.key === 'Enter' || key.key === ' ') {
        return item.kind === 'more' ? {type: 'loadmore', parent: item.id} : {type: 'select', id: item.id};
    }
    return alongOrder(order, index, key.key);
};

/**
 * Whether a key is a character the outline's type-ahead takes: one printable character, no
 * modifier but Shift (a capital letter), and not Space, which selects.
 *
 * @param {object} key The key.
 * @returns {boolean} Whether it types.
 */
export const isTypeAheadKey = (key: KeyPress): boolean =>
    key.key.length === 1 && key.key !== ' ' && !key.altKey && !key.ctrlKey && !key.metaKey;

/**
 * Where a type-ahead query moves the focus (APG Tree View: "focus moves to the next node with a
 * name that starts with the typed character"), or null when no visible name matches.
 *
 * One character, or the same character typed again and again, moves to the next match after the
 * focused item and wraps, so repeating a letter cycles through the names it starts. Several
 * different characters refine the match from the focused item itself, so typing on keeps the focus
 * where the longer prefix still matches. Case and accents of the page's language are folded by
 * toLocaleLowerCase(); "load more" items have no name and are skipped.
 *
 * @param {Array} names The visible items' keys and names, in the walk's order.
 * @param {string} current The focused item's key.
 * @param {string} query What was typed since the last pause.
 * @returns {string|null} The key to focus, or null.
 */
export const typeAhead = (names: {key: string, name: string}[], current: string, query: string): string | null => {
    if (query === '' || names.length === 0) {
        return null;
    }
    const lower = query.toLocaleLowerCase();
    const repeated = [...lower].every((character) => character === lower[0]);
    const prefix = repeated ? lower[0] : lower;
    const index = names.findIndex((item) => item.key === current);
    // A repeated character starts after the focused item, a longer query on it; an unknown item from the top.
    const start = index < 0 ? 0 : index + (repeated ? 1 : 0);
    for (let step = 0; step < names.length; step++) {
        const item = names[(start + step) % names.length];
        if (item.name !== '' && item.name.toLocaleLowerCase().startsWith(prefix)) {
            return item.key;
        }
    }
    return null;
};

/**
 * Where a pane is entered by Tab: the selected item, else its nearest ancestor that is drawn, else
 * the framework (APG Tree View: "If a node is selected before the tree receives focus, focus is set
 * on the selected node").
 *
 * @param {object} tree The tree.
 * @param {Array} order The stops, from navOrder().
 * @param {number|null} selected The selected competency, or null for the framework.
 * @returns {string} The item's key.
 */
export const entryKey = (tree: Tree, order: NavItem[], selected: number | null): string => {
    if (selected === null) {
        return FRAMEWORK_ID;
    }
    const keys = new Set(order.map((item) => item.key));
    const chain = [...ancestors(tree, selected), selected].reverse();
    return chain.map(competencyKey).find((key) => keys.has(key)) ?? FRAMEWORK_ID;
};

/**
 * Where the focus goes when its item leaves the pane (collapsed away, deleted, moved, a branch
 * reloaded without it): its nearest ancestor that is still drawn, else the framework.
 *
 * The ancestors are read from the walk before the change, because a deleted competency is no
 * longer in the tree.
 *
 * @param {Array} before The stops before the change.
 * @param {Array} after The stops after it.
 * @param {string} lost The item that is gone.
 * @returns {string} The key to focus.
 */
export const survivorKey = (before: NavItem[], after: NavItem[], lost: string): string => {
    const keys = new Set(after.map((item) => item.key));
    const parents = new Map(before.map((item) => [item.key, item.parent]));
    let key: string | null | undefined = lost;
    const seen = new Set<string>();
    while (key && !seen.has(key)) {
        if (keys.has(key)) {
            return key;
        }
        seen.add(key);
        key = parents.get(key);
    }
    return FRAMEWORK_ID;
};

/** What a competency node's name says on the map. */
export type NameInfo = {
    name: string,
    level: number,
    position: number,
    total: number,
    expandable: boolean,
    expanded: boolean,
    // The competency's own total of children when its branch is loaded, else null.
    children: number | null,
    rule: string,
    relatedto: string | null,
};

/**
 * A competency node's accessible name on the map: what the Tree View pattern's states would say, in
 * words, because a node of React Flow's application is a group, which carries none of them.
 *
 * "Domain 1, level 2, 1 of 8, collapsed, rule: All", "Leaf, level 3, 3 of 3, related to Domain 2".
 *
 * @param {object} labels The labels.
 * @param {object} info What to say.
 * @returns {string} The name.
 */
export const nodeName = (labels: Labels, info: NameInfo): string => {
    const parts = [
        info.name,
        fill(labels.hubv2_a11y_level, String(info.level)),
        fillObject(labels.hubv2_a11y_position, {position: String(info.position), total: String(info.total)}),
    ];
    if (info.expandable) {
        parts.push(labels[info.expanded ? 'hubv2_a11y_expanded' : 'hubv2_a11y_collapsed']);
        if (info.children !== null) {
            parts.push(fillCount(labels, 'hubv2_children', info.children));
        }
    }
    if (info.rule) {
        parts.push(fill(labels.hubv2_a11y_rule, info.rule));
    }
    if (info.relatedto) {
        parts.push(fill(labels.hubv2_a11y_related, info.relatedto));
    }
    return parts.filter((part) => part !== '').join(', ');
};

/** A rectangle, in pixels. */
export type Rect = {x: number, y: number, width: number, height: number};

/** React Flow's viewport: the pan, in pixels, and the zoom. */
export type Viewport = {x: number, y: number, zoom: number};

/**
 * Whether two rectangles overlap.
 *
 * @param {object} a One.
 * @param {object} b The other.
 * @returns {boolean} Whether they overlap.
 */
const overlaps = (a: Rect, b: Rect): boolean =>
    a.x < b.x + b.width && b.x < a.x + a.width && a.y < b.y + b.height && b.y < a.y + a.height;

/**
 * Where to centre the view so that a focused node is wholly visible and under none of the map's
 * own overlays (the overview map, the controls: WCAG 2.4.11), or null when it already is.
 *
 * The node is placed at the view's centre when that spot is clear. On a short or narrow canvas the
 * centre can fall under an overlay (they sit in the bottom corners), so the node is then tried at
 * the top centre, the middle of the start edge and the top start corner, in that order; when no
 * spot is clear the top start corner is used, which keeps the node whole and as far from the
 * bottom overlays as the canvas allows.
 *
 * @param {object} node The node's box, in flow coordinates.
 * @param {object} viewport The current viewport.
 * @param {object} view The canvas's size, in pixels.
 * @param {Array} obstacles The overlays' boxes, in pixels from the canvas's top left corner.
 * @param {number} margin How far from the canvas's edges the node must stay, in pixels.
 * @returns {object|null} The point to centre on, in flow coordinates, or null.
 */
export const panTarget = (
    node: Rect,
    viewport: Viewport,
    view: {width: number, height: number},
    obstacles: Rect[],
    margin: number = 16,
): {x: number, y: number} | null => {
    const {zoom} = viewport;
    const width = node.width * zoom;
    const height = node.height * zoom;
    /**
     * Whether the node, drawn with its top left corner at a point of the canvas, is whole and clear.
     *
     * @param {number} left The corner's x, in pixels.
     * @param {number} top The corner's y, in pixels.
     * @returns {boolean} Whether it is.
     */
    const clear = (left: number, top: number): boolean => {
        const box = {x: left, y: top, width, height};
        return left >= margin && top >= margin && left + width <= view.width - margin && top + height <= view.height - margin
            && !obstacles.some((obstacle) => overlaps(box, obstacle));
    };
    if (clear(node.x * zoom + viewport.x, node.y * zoom + viewport.y)) {
        return null;
    }
    const middlex = (view.width - width) / 2;
    const middley = (view.height - height) / 2;
    const spots = [[middlex, middley], [middlex, margin], [margin, middley], [margin, margin]];
    const [left, top] = spots.find(([x, y]) => clear(x, y)) ?? [margin, margin];
    // The flow point that sits at the view's centre when the node's corner is at (left, top).
    return {x: node.x + (view.width / 2 - left) / zoom, y: node.y + (view.height / 2 - top) / zoom};
};

/**
 * A rectangle moved by a pan.
 *
 * @param {object} rect The rectangle.
 * @param {number} dx The move across.
 * @param {number} dy The move down.
 * @returns {object} The moved rectangle.
 */
const shifted = (rect: Rect, dx: number, dy: number): Rect => ({...rect, x: rect.x + dx, y: rect.y + dy});

/**
 * The smallest rectangle holding two others.
 *
 * @param {object} a One.
 * @param {object} b The other.
 * @returns {object} The union.
 */
const union = (a: Rect, b: Rect): Rect => {
    const x = Math.min(a.x, b.x);
    const y = Math.min(a.y, b.y);
    return {x, y, width: Math.max(a.x + a.width, b.x + b.width) - x, height: Math.max(a.y + a.height, b.y + b.height) - y};
};

/**
 * The move, along one axis, that brings a span inside [low, high]: none when it is already, the
 * preferred end aligned when it is longer than the room.
 *
 * @param {number} start The span's start.
 * @param {number} size Its length.
 * @param {number} low The room's start.
 * @param {number} high The room's end.
 * @param {boolean} keepend Whether, when the span is too long, its end rather than its start stays in.
 * @returns {number} The move.
 */
const fitAxis = (start: number, size: number, low: number, high: number, keepend: boolean): number => {
    if (size > high - low) {
        return keepend ? high - (start + size) : low - start;
    }
    if (start < low) {
        return low - start;
    }
    return start + size > high ? high - (start + size) : 0;
};

/**
 * Where to centre the map after a branch opened on it, so that the parent and the start of its
 * children are in view and clear of the map's overlays, or null when they already are.
 *
 * The children shown with the parent are those nearest to it, as many as the view's height holds
 * (the tidy layout centres a parent on its children, so a long branch's first children are far
 * above it); the children's column is always reached across. The move is the smallest that brings
 * that box inside the view's margins: a box wider than the view keeps its parent's side (the left,
 * or the right in a right-to-left tree, whose children grow leftwards), and a box under an overlay
 * moves up off it, else sideways, when the view leaves room. The zoom never changes.
 *
 * @param {object} parent The parent node's box, in flow coordinates.
 * @param {Array} children Its children's boxes (and its "load more" node's), in flow coordinates.
 * @param {object} viewport The current viewport.
 * @param {object} view The canvas's size, in pixels.
 * @param {Array} obstacles The overlays' boxes, in pixels from the canvas's top left corner.
 * @param {boolean} rtl Whether the tree grows right to left.
 * @param {number} margin How far from the canvas's edges the boxes must stay, in pixels.
 * @returns {object|null} The point to centre on, in flow coordinates, or null.
 */
export const branchTarget = (
    parent: Rect,
    children: Rect[],
    viewport: Viewport,
    view: {width: number, height: number},
    obstacles: Rect[],
    rtl: boolean = false,
    margin: number = 16,
): {x: number, y: number} | null => {
    const {zoom} = viewport;
    /**
     * A flow box on the canvas, in pixels.
     *
     * @param {object} rect The box in flow coordinates.
     * @returns {object} The box in pixels.
     */
    const screen = (rect: Rect): Rect => ({
        x: rect.x * zoom + viewport.x, y: rect.y * zoom + viewport.y, width: rect.width * zoom, height: rect.height * zoom,
    });
    /**
     * A box's vertical middle.
     *
     * @param {object} rect The box.
     * @returns {number} Its middle, in pixels.
     */
    const middle = (rect: Rect): number => rect.y + rect.height / 2;
    const top = screen(parent);
    const nearest = children.map(screen).sort((a, b) => Math.abs(middle(a) - middle(top)) - Math.abs(middle(b) - middle(top)));
    let box = top;
    nearest.forEach((child) => {
        const grown = union(box, child);
        if (grown.height <= view.height - 2 * margin) {
            box = grown;
        }
    });
    if (nearest.length > 0) {
        // The children's column is reached across even when none of them fits the height beside the parent.
        box = union(box, {...nearest[0], y: box.y, height: box.height});
    }
    /**
     * Whether a box on the canvas is whole and clear of the overlays.
     *
     * @param {object} rect The box, in pixels.
     * @returns {boolean} Whether it is.
     */
    const clear = (rect: Rect): boolean => rect.x >= margin && rect.y >= margin && rect.x + rect.width <= view.width - margin
        && rect.y + rect.height <= view.height - margin && !obstacles.some((obstacle) => overlaps(rect, obstacle));
    if (clear(box)) {
        return null;
    }
    let dx = fitAxis(box.x, box.width, margin, view.width - margin, rtl);
    let dy = fitAxis(box.y, box.height, margin, view.height - margin, false);
    obstacles.forEach((obstacle) => {
        const moved = shifted(box, dx, dy);
        if (!overlaps(moved, obstacle)) {
            return;
        }
        const up = obstacle.y - (moved.y + moved.height);
        if (moved.y + up >= margin) {
            dy += up;
            return;
        }
        const left = obstacle.x - (moved.x + moved.width);
        const right = obstacle.x + obstacle.width - moved.x;
        if (moved.x + left >= margin) {
            dx += left;
        } else if (moved.x + moved.width + right <= view.width - margin) {
            dx += right;
        }
    });
    if (dx === 0 && dy === 0) {
        return null;
    }
    // The flow point at the view's centre once the viewport has moved by (dx, dy).
    return {x: (view.width / 2 - viewport.x - dx) / zoom, y: (view.height / 2 - viewport.y - dy) / zoom};
};
