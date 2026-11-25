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
 * The workspace's multi-selection: the competencies a manager picked for the bulk dock, beside the one competency
 * the panel shows (selection.ts).
 *
 * A set of competency ids, never the framework and never a "load more" item, with an anchor: the last competency
 * toggled, from which Shift+Up and Shift+Down extend. `base` is the set as it stood when the anchor was set, so a
 * range drawn from the anchor replaces the previous range instead of piling ranges up (the APG multi-select
 * listbox's Shift+arrow). The set survives opening and closing branches (it names ids, not rows) and loses the ids
 * that leave the tree (prune): a deleted competency, or one forgotten after a move.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/multiselect
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {KeyPress, NavItem} from './keynav';
import type {Tree} from './tree';

/** The picked competencies, the anchor of a range and the set the range is added to. */
export type Picked = {ids: number[], anchor: number | null, base: number[]};

/** Nothing picked. */
export const NONE: Picked = {ids: [], anchor: null, base: []};

/**
 * The competencies of the walk, in its order: the rows a range runs over.
 *
 * @param {Array} order The walk (keynav.navOrder).
 * @returns {Array} The competency ids drawn, in order.
 */
export const drawnCompetencies = (order: NavItem[]): number[] =>
    order.filter((item) => item.kind === 'competency').map((item) => item.id);

/**
 * Pick a competency, or release it; it becomes the anchor.
 *
 * @param {object} picked The picked set.
 * @param {number} id The competency.
 * @returns {object} The new set.
 */
export const toggle = (picked: Picked, id: number): Picked => {
    const ids = picked.ids.includes(id) ? picked.ids.filter((other) => other !== id) : [...picked.ids, id];
    return {ids, anchor: id, base: ids};
};

/**
 * Extend the set from the anchor to a competency, over the drawn competencies between them: the range replaces the
 * previous one drawn from the same anchor. Without an anchor still drawn, the range starts at `from` (the item the
 * focus leaves).
 *
 * @param {object} picked The picked set.
 * @param {Array} order The walk (keynav.navOrder).
 * @param {number|null} from The competency the focus leaves, or null when it leaves another item.
 * @param {number} to The competency the focus reaches.
 * @returns {object} The new set.
 */
export const extend = (picked: Picked, order: NavItem[], from: number | null, to: number): Picked => {
    const drawn = drawnCompetencies(order);
    const anchored = picked.anchor !== null && drawn.includes(picked.anchor);
    const anchor = anchored ? picked.anchor as number : from ?? to;
    const base = anchored ? picked.base : picked.ids;
    const start = drawn.indexOf(anchor);
    const end = drawn.indexOf(to);
    if (start < 0 || end < 0) {
        return picked;
    }
    const range = drawn.slice(Math.min(start, end), Math.max(start, end) + 1);
    const ids = [...base, ...range.filter((id) => !base.includes(id))];
    return {ids, anchor, base};
};

/**
 * Pick every drawn competency of an item's level under the same parent (Ctrl+A, Cmd+A), adding them to the set.
 *
 * @param {object} picked The picked set.
 * @param {Array} order The walk (keynav.navOrder).
 * @param {string} key The focused item's key; a "load more" item stands for its level too.
 * @returns {object} The new set; the same one when the focused item is the framework.
 */
export const pickLevel = (picked: Picked, order: NavItem[], key: string): Picked => {
    const item = order.find((candidate) => candidate.key === key);
    if (!item || item.parent === null) {
        return picked;
    }
    const level = order.filter((candidate) => candidate.kind === 'competency' && candidate.parent === item.parent)
        .map((candidate) => candidate.id);
    const ids = [...picked.ids, ...level.filter((id) => !picked.ids.includes(id))];
    return {ids, anchor: picked.anchor, base: ids};
};

/**
 * Drop the ids that left the tree: deleted, or forgotten with a branch after a move. The same set when none left.
 *
 * @param {object} picked The picked set.
 * @param {object} tree The tree.
 * @returns {object} The set.
 */
export const prune = (picked: Picked, tree: Tree): Picked => {
    const kept = picked.ids.filter((id) => tree.nodes[id] !== undefined);
    if (kept.length === picked.ids.length) {
        return picked;
    }
    const anchor = picked.anchor !== null && tree.nodes[picked.anchor] ? picked.anchor : null;
    return {ids: kept, anchor, base: picked.base.filter((id) => tree.nodes[id] !== undefined)};
};

/**
 * Keep only some ids, as after a bulk action that failed on part of the set.
 *
 * @param {object} picked The picked set.
 * @param {Array} ids The ids to keep.
 * @returns {object} The set.
 */
export const keepOnly = (picked: Picked, ids: number[]): Picked => {
    const kept = picked.ids.filter((id) => ids.includes(id));
    return {ids: kept, anchor: null, base: kept};
};

/** What a key asks of the picks: toggle the focused one, extend up or down, pick the level, or nothing. */
export type Gesture = 'toggle' | 'up' | 'down' | 'level' | null;

/**
 * The multi-selection's key map (the APG multi-select tree's): Space toggles, Shift+Up and Shift+Down extend,
 * Ctrl+A (Cmd+A) picks the level. Any other key, or one of these with another modifier, is not the picks'.
 *
 * @param {object} key The key and its modifiers.
 * @returns {string|null} The gesture.
 */
export const pickGesture = (key: KeyPress): Gesture => {
    const command = !!(key.ctrlKey || key.metaKey);
    if (key.altKey) {
        return null;
    }
    if (key.key === ' ' && !command && !key.shiftKey) {
        return 'toggle';
    }
    if (key.shiftKey && !command && (key.key === 'ArrowUp' || key.key === 'ArrowDown')) {
        return key.key === 'ArrowUp' ? 'up' : 'down';
    }
    return command && !key.shiftKey && key.key.toLowerCase() === 'a' ? 'level' : null;
};
