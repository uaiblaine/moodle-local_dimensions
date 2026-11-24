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
 * Tests for the outline's "Expand all" and "Collapse all": level by level, loaded branches opened and
 * unloaded ones named for loading, and the bound that stops the walk.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {EXPAND_ALL_BOUND, collapseAll, expandStep, visibleCount} from '../../src/hubv2/expandall';
import {ROOT, createTree, insertChildren, setExpanded} from '../../src/hubv2/tree';
import type {StructureNode} from '../../src/hubv2/types';

/**
 * A node with the fields the tests read.
 *
 * @param {number} id The id.
 * @param {number} parentid The parent.
 * @param {object} extra Fields to override.
 * @returns {object} The node.
 */
const node = (id: number, parentid: number = ROOT, extra: Partial<StructureNode> = {}): StructureNode => ({
    id, parentid, shortname: `C${id}`, idnumber: '', taxonomy: 'Competency', scale: '', description: '',
    coursecount: 0, activitycount: 0, templatecount: 0, depth: 0, indent: 0, haschildren: false, canmanage: true,
    ruletype: null, ruleoutcome: 0, ruleconfig: null, rulelabel: '', type: '', tag1: '', tag2: '', bgcolor: '', textcolor: '',
    ...extra,
});

/**
 * Roots 1 (children 11, 12 loaded, closed), 2 (never opened) and 3 (a leaf); 11 is itself a parent
 * with child 111 loaded and open, hidden while 1 is closed.
 *
 * @returns {object} The tree.
 */
const sample = () => {
    let tree = createTree({items: [node(1, ROOT, {haschildren: true}), node(2, ROOT, {haschildren: true}), node(3)], total: 3});
    tree = insertChildren(tree, 1, {items: [node(11, 1, {haschildren: true}), node(12, 1)], total: 2});
    tree = insertChildren(tree, 11, {items: [node(111, 11)], total: 1});
    return setExpanded(tree, 11, true);
};

describe('"Expand all"', () => {
    it('opens the shallowest closed parents first: the loaded one, and names the unloaded one for loading', () => {
        expect(expandStep(sample(), 500, 25)).toEqual({open: [1], load: [2], capped: false});
    });

    it('goes one level down on the next round, and is done when every visible parent is open', () => {
        let tree = setExpanded(sample(), 1, true);
        tree = insertChildren(tree, 2, {items: [node(21, 2, {haschildren: true})], total: 1});
        tree = setExpanded(tree, 2, true);
        expect(expandStep(tree, 500, 25)).toEqual({open: [], load: [21], capped: false});
        tree = setExpanded(insertChildren(tree, 21, {items: [node(211, 21)], total: 1}), 21, true);
        expect(expandStep(tree, 500, 25)).toEqual({open: [], load: [], capped: false});
    });

    it('counts what opening a loaded branch shows, its open sub-branches included', () => {
        const tree = sample();
        expect(visibleCount(tree)).toBe(3);
        // Opening 1 shows 11, 12 and, under the open 11, 111: 3 more, to 6.
        expect(visibleCount(setExpanded(tree, 1, true))).toBe(6);
        expect(expandStep(tree, 6, 25)).toEqual({open: [1], load: [], capped: true});
        expect(expandStep(tree, 5, 25)).toEqual({open: [], load: [], capped: true});
    });

    it('counts an unloaded branch as a full first page', () => {
        const tree = setExpanded(sample(), 1, true);
        expect(expandStep(tree, 6 + 25, 25)).toEqual({open: [], load: [2], capped: false});
        expect(expandStep(tree, 6 + 24, 25)).toEqual({open: [], load: [], capped: true});
    });

    it('stops at 500 competencies', () => {
        expect(EXPAND_ALL_BOUND).toBe(500);
        const roots = Array.from({length: 25}, (_, index) => node(index + 1, ROOT, {haschildren: true}));
        let tree = createTree({items: roots, total: 25});
        for (let index = 1; index <= 25; index++) {
            const children = Array.from({length: 25}, (_, child) => node(index * 100 + child, index));
            tree = insertChildren(tree, index, {items: children, total: 25});
        }
        // 25 roots, then 25 children each: 19 branches reach exactly 500 (25 + 19 * 25), the 20th would pass it.
        const step = expandStep(tree, EXPAND_ALL_BOUND, 25);
        expect(step.open).toHaveLength(19);
        expect(step.capped).toBe(true);
    });
});

describe('"Collapse all"', () => {
    it('closes every branch and keeps what is loaded', () => {
        const tree = setExpanded(sample(), 1, true);
        const collapsed = collapseAll(tree);
        expect(collapsed.expanded).toEqual([]);
        expect(collapsed.branches).toBe(tree.branches);
        expect(visibleCount(collapsed)).toBe(3);
    });

    it('returns the same tree when nothing is open', () => {
        const tree = createTree({items: [node(1)], total: 1});
        expect(collapseAll(tree)).toBe(tree);
    });
});
