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
 * The map's column headings: the framework's taxonomy term of each level, above its column.
 *
 * The map lays one column per depth (layout.ts), the framework in column 0 and a competency of level N (its depth,
 * 1 for a top-level one) in column N; a language written right to left mirrors the columns. Each level is named
 * as core names it (competency_framework::get_taxonomy()): the term the framework gives that level, else core's
 * fallback. The outline's "Show taxonomy" meta reads the same function, so both panes say the same word.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/columns
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {GRID} from './layout';
import {fill} from './strings';
import {visibleItems} from './tree';
import type {Tree} from './tree';

/** One heading: the level, the column's left edge in the map's coordinates, and its term. */
export type ColumnHead = {level: number, x: number, term: string};

/**
 * A level's taxonomy term.
 *
 * @param {Array} terms The terms the framework names, level 1 first.
 * @param {string} fallback Core's term for a level the framework does not name.
 * @param {number} level The level, 1 for a top-level competency.
 * @returns {string} The term.
 */
export const levelTerm = (terms: string[], fallback: string, level: number): string =>
    (level >= 1 && terms[level - 1]) ? terms[level - 1] : fallback;

/**
 * A heading's words: the level, then its term ("Level 2: Competency").
 *
 * The level is said because a framework often gives every level the same term, and the term alone would then
 * head every column with one word. The canvas's description says the levels with the same words.
 *
 * @param {string} levellabel The "Level {$a}" label.
 * @param {object} head The heading.
 * @returns {string} The words.
 */
export const headWords = (levellabel: string, head: ColumnHead): string => `${fill(levellabel, String(head.level))}: ${head.term}`;

/**
 * The deepest level the map draws: the level of its deepest visible competency, 0 with none.
 *
 * @param {object} tree The tree.
 * @returns {number} The level.
 */
export const deepestLevel = (tree: Tree): number =>
    visibleItems(tree).reduce((deepest, item) => (item.kind === 'competency' ? Math.max(deepest, item.depth) : deepest), 0);

/**
 * The headings of the levels the map draws, left to right on the screen.
 *
 * @param {number} levels How many levels are drawn.
 * @param {Array} terms The terms the framework names, level 1 first.
 * @param {string} fallback Core's term for a level the framework does not name.
 * @param {boolean} rtl Whether the tree grows right to left.
 * @param {number} columnwidth The layout's column width.
 * @returns {Array} The headings.
 */
export const columnHeads = (
    levels: number,
    terms: string[],
    fallback: string,
    rtl: boolean,
    columnwidth: number = GRID.columnWidth,
): ColumnHead[] => {
    const heads: ColumnHead[] = [];
    for (let level = 1; level <= levels; level++) {
        // As mirrorLayout() places them: 0 - x keeps the root's column at +0.
        const x = rtl ? 0 - level * columnwidth : level * columnwidth;
        heads.push({level, x, term: levelTerm(terms, fallback, level)});
    }
    return heads.sort((one, other) => one.x - other.x);
};
