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
 * Tests for the outline's rows: the level, the set size, the position and the open state of each
 * treeitem from a tree whose pages are loaded only in part, and the list's own scrolling.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {navOrder} from '../../src/hubv2/keynav';
import {editorSlot, outlineRows, rowMeta, scrollToShow} from '../../src/hubv2/outlinerows';
import type {OutlineRow} from '../../src/hubv2/outlinerows';
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
 * Roots 1 and 2 of 40 (one page loaded); 1 open with children 11 and 12 of 30 (one page loaded), 12
 * closed with its only child loaded; 2 a parent never opened.
 *
 * @returns {object} The tree.
 */
const sample = () => {
    let tree = createTree({items: [node(1, ROOT, {haschildren: true}), node(2, ROOT, {haschildren: true})], total: 40});
    tree = insertChildren(tree, 1, {items: [node(11, 1), node(12, 1, {haschildren: true, ruletype: 'all', rulelabel: 'All'})],
        total: 30});
    tree = insertChildren(tree, 12, {items: [node(121, 12)], total: 1});
    return setExpanded(tree, 1, true);
};

describe('the outline\'s rows', () => {
    it('are the keyboard walk, one to one, in its order', () => {
        const tree = sample();
        expect(outlineRows(tree, 'Framework').map((row) => row.key)).toEqual(navOrder(tree).map((item) => item.key));
    });

    it('carry the level, the set size from the server\'s total and the position among the loaded siblings', () => {
        const rows = outlineRows(sample(), 'Framework');
        expect(rows.map((row) => [row.key, row.level, row.setsize, row.posinset])).toEqual([
            ['f', 1, 1, 1],
            ['c1', 2, 40, 1],
            ['c11', 3, 30, 1],
            ['c12', 3, 30, 2],
            ['m1', 3, 30, 3],
            ['c2', 2, 40, 2],
            ['m0', 2, 40, 3],
        ]);
    });

    it('say open and closed on parents only, and a parent\'s own total once its branch is loaded', () => {
        const rows = outlineRows(sample(), 'Framework');
        const byKey = Object.fromEntries(rows.map((row) => [row.key, row]));
        expect(byKey.f).toMatchObject({kind: 'framework', expandable: true, expanded: true, name: 'Framework', childcount: 40});
        expect(byKey.c1).toMatchObject({expandable: true, expanded: true, childcount: 30});
        expect(byKey.c12).toMatchObject({expandable: true, expanded: false, childcount: 1});
        expect(byKey.c2).toMatchObject({expandable: true, expanded: false, childcount: null});
        expect(byKey.c11).toMatchObject({expandable: false, expanded: false, childcount: null});
        expect(byKey.c12.node?.rulelabel).toBe('All');
    });

    it('end a partly loaded branch with a "load more" row that counts what is left and has no name', () => {
        const rows = outlineRows(sample(), 'Framework');
        expect(rows.find((row) => row.key === 'm1')).toMatchObject({kind: 'more', id: 1, parent: 'c1', remaining: 28, name: ''});
        expect(rows.find((row) => row.key === 'm0')).toMatchObject({kind: 'more', id: ROOT, parent: 'f', remaining: 38});
    });

    it('follow a further page: positions go on from the loaded ones, and the last page drops the "load more" row', () => {
        let tree = insertChildren(sample(), 1, {items: [node(13, 1)], total: 3}, true);
        let rows = outlineRows(tree, 'Framework');
        expect(rows.filter((row) => row.parent === 'c1').map((row) => [row.key, row.posinset, row.setsize])).toEqual([
            ['c11', 1, 3], ['c12', 2, 3], ['c13', 3, 3],
        ]);
        tree = setExpanded(tree, 12, true);
        rows = outlineRows(tree, 'Framework');
        expect(rows.find((row) => row.key === 'c121')).toMatchObject({level: 4, setsize: 1, posinset: 1});
    });
});

describe('what a row shows after its name', () => {
    const rows = outlineRows(sample(), 'Framework');
    const row = (key: string) => rows.find((candidate) => candidate.key === key) as OutlineRow;

    it('is the framework\'s competency count on the root', () => {
        expect(rowMeta(row('f'), 120, null)).toEqual([{kind: 'count', value: 120}]);
    });

    it('is a loaded parent\'s child count, then its rule\'s mark, then its related competencies when any', () => {
        expect(rowMeta(row('c12'), 120, 2)).toEqual([
            {kind: 'count', value: 1},
            {kind: 'rule', label: 'All'},
            {kind: 'related', value: 2},
        ]);
        expect(rowMeta(row('c1'), 120, null)).toEqual([{kind: 'count', value: 30}]);
    });

    it('is nothing for a leaf without relations, a parent not loaded yet, or a "load more" row', () => {
        expect(rowMeta(row('c11'), 120, null)).toEqual([]);
        expect(rowMeta(row('c11'), 120, 0)).toEqual([]);
        expect(rowMeta(row('c2'), 120, null)).toEqual([]);
        expect(rowMeta(row('m1'), 120, 5)).toEqual([]);
    });
});

describe('the outline\'s own scrolling', () => {
    it('does not scroll for a row already whole in view', () => {
        expect(scrollToShow(64, 32, 0, 200)).toBeNull();
        expect(scrollToShow(168, 32, 0, 200)).toBeNull();
    });

    it('scrolls up to a row above the view and down to one below it', () => {
        expect(scrollToShow(32, 32, 100, 200)).toBe(32);
        expect(scrollToShow(400, 32, 0, 200)).toBe(232);
        expect(scrollToShow(190, 32, 0, 200)).toBe(22);
    });
});

describe('the inline editor\'s place', () => {
    it('follows the anchor\'s drawn subtree, or the parent\'s row for a first child', () => {
        const rows = outlineRows(sample(), 'Framework');
        // 1 is open: a sibling after 1 goes after its whole drawn subtree, "load more" row included.
        expect(editorSlot(rows, 'f', 'c1')).toBe(rows[rows.findIndex((row) => row.key === 'c2') - 1].key);
        expect(editorSlot(rows, 'c1', 'c11')).toBe('c11');
        expect(editorSlot(rows, 'c1', null)).toBe('c1');
        expect(editorSlot(rows, 'f', null)).toBe('f');
    });

    it('is nowhere when the row it needs is not drawn', () => {
        const rows = outlineRows(sample(), 'Framework');
        expect(editorSlot(rows, 'c2', 'c21')).toBeNull();
        expect(editorSlot(rows, 'c9', null)).toBeNull();
    });
});
