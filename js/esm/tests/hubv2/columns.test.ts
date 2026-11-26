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
 * Tests for the map's column headings: the term of each level, the levels the map draws, and their order on
 * the screen in both directions.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {columnHeads, deepestLevel, headWords, levelTerm} from '../../src/hubv2/columns';
import {GRID} from '../../src/hubv2/layout';
import {ROOT, createTree, insertChildren, setExpanded} from '../../src/hubv2/tree';
import type {StructureNode} from '../../src/hubv2/types';

/**
 * A node with the fields the tree reads.
 *
 * @param {number} id The id.
 * @param {number} parentid The parent.
 * @param {boolean} haschildren Whether it has children.
 * @returns {object} The node.
 */
const node = (id: number, parentid: number = ROOT, haschildren: boolean = false): StructureNode => ({
    id, parentid, shortname: `C${id}`, idnumber: '', taxonomy: '', scale: '', description: '',
    coursecount: 0, activitycount: 0, templatecount: 0, depth: 0, indent: 0, haschildren, canmanage: true,
    ruletype: null, ruleoutcome: 0, ruleconfig: null, rulelabel: '', type: '', tag1: '', tag2: '', bgcolor: '', textcolor: '',
});

const TERMS = ['Domain', 'Competency', 'Indicator'];

describe('a level\'s term', () => {
    it('is the framework\'s own for the levels it names', () => {
        expect(levelTerm(TERMS, 'Competency', 1)).toBe('Domain');
        expect(levelTerm(TERMS, 'Competency', 3)).toBe('Indicator');
    });

    it('is core\'s fallback past the levels the framework names, and for an empty term', () => {
        expect(levelTerm(TERMS, 'Competency', 4)).toBe('Competency');
        expect(levelTerm(TERMS, 'Competency', 9)).toBe('Competency');
        expect(levelTerm([], 'Competency', 1)).toBe('Competency');
        expect(levelTerm(['', 'Skill'], 'Competency', 1)).toBe('Competency');
        expect(levelTerm(TERMS, 'Competency', 0)).toBe('Competency');
    });
});

describe('a heading\'s words', () => {
    it('say the level before its term, so two levels of one term are two headings', () => {
        const heads = columnHeads(2, [], 'Competency', false);
        expect(heads.map((head) => headWords('Level {$a}', head))).toEqual(['Level 1: Competency', 'Level 2: Competency']);
    });

    it('keep the framework\'s own term', () => {
        expect(headWords('Level {$a}', {level: 3, x: 0, term: 'Indicator'})).toBe('Level 3: Indicator');
    });
});

describe('the levels the map draws', () => {
    it('follow the opened branches, not the loaded ones', () => {
        const roots = createTree({items: [node(1, ROOT, true), node(2)], total: 2});
        expect(deepestLevel(roots)).toBe(1);
        const loaded = insertChildren(roots, 1, {items: [node(11, 1)], total: 1});
        expect(deepestLevel(loaded)).toBe(1);
        expect(deepestLevel(setExpanded(loaded, 1, true))).toBe(2);
        expect(deepestLevel(createTree({items: [], total: 0}))).toBe(0);
    });
});

describe('the headings', () => {
    it('stand above each level\'s column, left to right, past the framework\'s', () => {
        const heads = columnHeads(4, TERMS, 'Competency', false);
        expect(heads).toEqual([
            {level: 1, x: GRID.columnWidth, term: 'Domain'},
            {level: 2, x: 2 * GRID.columnWidth, term: 'Competency'},
            {level: 3, x: 3 * GRID.columnWidth, term: 'Indicator'},
            {level: 4, x: 4 * GRID.columnWidth, term: 'Competency'},
        ]);
    });

    it('mirror right to left: the deepest level leftmost, each level where mirrorLayout() puts it', () => {
        const heads = columnHeads(3, TERMS, 'Competency', true);
        expect(heads.map((head) => head.level)).toEqual([3, 2, 1]);
        expect(heads.map((head) => head.x)).toEqual([-3 * GRID.columnWidth, -2 * GRID.columnWidth, -GRID.columnWidth]);
        expect(heads.map((head) => head.term)).toEqual(['Indicator', 'Competency', 'Domain']);
    });

    it('are none when no competency is drawn', () => {
        expect(columnHeads(0, TERMS, 'Competency', false)).toEqual([]);
    });
});
