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
 * The impact preview (idea I28): which items a change marks on the map and in the outline while its dialogue
 * asks for the confirmation. The dialogue itself says the change and its count in words; the marks only show
 * where it lands.
 *
 * A delete touches the competency and every descendant the tree has loaded (core deletes the whole subtree;
 * the confirmation counts all of it, loaded or not). A move touches the old parent and the new one, ROOT for
 * the framework.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/impact
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {isWithin} from './tree';
import type {Tree} from './tree';

/** No item marked. */
export const NO_IMPACT: Set<number> = new Set();

/**
 * What a delete touches: the competency and its loaded descendants.
 *
 * @param {object} tree The tree.
 * @param {number} id The competency.
 * @returns {Set} The ids.
 */
export const deleteImpact = (tree: Tree, id: number): Set<number> =>
    new Set(Object.keys(tree.nodes).map(Number).filter((other) => isWithin(tree, other, id)));

/**
 * What a move touches: the parent it leaves and the parent it joins.
 *
 * @param {number} oldparent The parent it leaves, ROOT for the top level.
 * @param {number} newparent The parent it joins, ROOT for the top level.
 * @returns {Set} The ids.
 */
export const moveImpact = (oldparent: number, newparent: number): Set<number> => new Set([oldparent, newparent]);

/**
 * Whether two marked sets are the same, so a store does not change for an equal one.
 *
 * @param {Set} a One.
 * @param {Set} b The other.
 * @returns {boolean} Whether they hold the same ids.
 */
export const sameImpact = (a: Set<number>, b: Set<number>): boolean => a.size === b.size && [...a].every((id) => b.has(id));
