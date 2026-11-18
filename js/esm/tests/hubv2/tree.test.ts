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
 * Tests for the canvas's lazily loaded tree: branches, opening, refreshing, removing and paths.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
    ROOT, ancestors, branchHas, createTree, expandPath, forgetBranch, hasBranch, insertChildren, isExpanded, isWithin,
    missingBranches, removeNode, replaceNode, setExpanded, visibleItems,
} from '../../src/hubv2/tree';
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
    id, parentid, shortname: `C${id}`, idnumber: `ID${id}`, taxonomy: 'Competency', scale: '', description: '',
    coursecount: 0, activitycount: 0, templatecount: 0, depth: 0, indent: 0, haschildren, canmanage: true,
    ruletype: null, ruleoutcome: 0, ruleconfig: null, rulelabel: '', type: '', tag1: '', tag2: '', bgcolor: '', textcolor: '',
});

/**
 * A tree of two roots, the first with two loaded children of three.
 *
 * @returns {object} The tree.
 */
const sample = () => {
    const tree = createTree({items: [node(1, ROOT, true), node(2)], total: 2});
    return insertChildren(tree, 1, {items: [node(11, 1), node(12, 1, true)], total: 3});
};

describe('the tree', () => {
    it('starts from the first page of roots, with the roots open', () => {
        const tree = createTree({items: [node(1), node(2)], total: 30});
        expect(tree.branches[ROOT]).toEqual({ids: [1, 2], total: 30});
        expect(isExpanded(tree, ROOT)).toBe(true);
        expect(visibleItems(tree)).toEqual([
            {kind: 'competency', id: 1, parent: ROOT, depth: 1},
            {kind: 'competency', id: 2, parent: ROOT, depth: 1},
            {kind: 'more', parent: ROOT, depth: 1, remaining: 28},
        ]);
    });

    it('draws loaded children only under an open parent, and keeps them when closed', () => {
        const tree = sample();
        expect(hasBranch(tree, 1)).toBe(true);
        expect(visibleItems(tree).map((item) => item.kind === 'competency' ? item.id : 'more')).toEqual([1, 2]);

        const open = setExpanded(tree, 1, true);
        expect(visibleItems(open)).toEqual([
            {kind: 'competency', id: 1, parent: ROOT, depth: 1},
            {kind: 'competency', id: 11, parent: 1, depth: 2},
            {kind: 'competency', id: 12, parent: 1, depth: 2},
            {kind: 'more', parent: 1, depth: 2, remaining: 1},
            {kind: 'competency', id: 2, parent: ROOT, depth: 1},
        ]);
        const closed = setExpanded(open, 1, false);
        expect(isExpanded(closed, 1)).toBe(false);
        expect(closed.branches[1].ids).toEqual([11, 12]);
        // Immutable: the original is untouched.
        expect(isExpanded(tree, 1)).toBe(false);
        expect(setExpanded(tree, ROOT, false)).toBe(tree);
    });

    it('appends a further page after the loaded children, without duplicates', () => {
        const tree = insertChildren(sample(), 1, {items: [node(12, 1, true), node(13, 1)], total: 3}, true);
        expect(tree.branches[1]).toEqual({ids: [11, 12, 13], total: 3});
        expect(visibleItems(setExpanded(tree, 1, true)).some((item) => item.kind === 'more')).toBe(false);
    });

    it('forgets a child a reloaded page no longer names, with everything below it', () => {
        let tree = insertChildren(sample(), 12, {items: [node(121, 12)], total: 1});
        tree = expandPath(tree, [1, 12]);
        const reloaded = insertChildren(tree, 1, {items: [node(11, 1)], total: 1});
        expect(reloaded.branches[1].ids).toEqual([11]);
        expect(reloaded.nodes[12]).toBeUndefined();
        expect(reloaded.nodes[121]).toBeUndefined();
        expect(reloaded.branches[12]).toBeUndefined();
        expect(reloaded.expanded).toEqual([1]);
    });

    it('refreshes a node in place, and drops the branch of one that has no children any more', () => {
        const tree = setExpanded(insertChildren(sample(), 12, {items: [node(121, 12)], total: 1}), 12, true);
        const renamed = replaceNode(tree, {...node(12, 1, true), shortname: 'Renamed'});
        expect(renamed.nodes[12].shortname).toBe('Renamed');
        expect(renamed.branches[12].ids).toEqual([121]);

        const childless = replaceNode(tree, node(12, 1, false));
        expect(childless.branches[12]).toBeUndefined();
        expect(childless.nodes[121]).toBeUndefined();
        expect(isExpanded(childless, 12)).toBe(false);
    });

    it('collapses and forgets a branch whose depth changed', () => {
        const tree = setExpanded(insertChildren(sample(), 12, {items: [node(121, 12)], total: 1}), 12, true);
        const forgotten = forgetBranch(tree, 12);
        expect(hasBranch(forgotten, 12)).toBe(false);
        expect(isExpanded(forgotten, 12)).toBe(false);
        expect(forgotten.nodes[12]).toBeDefined();
        expect(forgotten.nodes[121]).toBeUndefined();
    });

    it('removes a node and its subtree, and counts it out of its parent', () => {
        const tree = insertChildren(sample(), 12, {items: [node(121, 12)], total: 1});
        const removed = removeNode(tree, 12);
        expect(removed.branches[1]).toEqual({ids: [11], total: 2});
        expect(removed.nodes[12]).toBeUndefined();
        expect(removed.nodes[121]).toBeUndefined();
        expect(removeNode(tree, 999)).toBe(tree);
    });

    it('walks ancestors and refuses a parent inside the moved subtree', () => {
        const tree = insertChildren(sample(), 12, {items: [node(121, 12)], total: 1});
        expect(ancestors(tree, 121)).toEqual([1, 12]);
        expect(ancestors(tree, 1)).toEqual([]);
        expect(isWithin(tree, 121, 1)).toBe(true);
        expect(isWithin(tree, 1, 1)).toBe(true);
        expect(isWithin(tree, 2, 1)).toBe(false);
        expect(isWithin(tree, 11, 12)).toBe(false);
    });

    it('names the branches a path still needs and opens the path', () => {
        const tree = sample();
        expect(missingBranches(tree, [1, 12])).toEqual([12]);
        expect(missingBranches(tree, [])).toEqual([]);
        expect(missingBranches({nodes: {}, branches: {}, expanded: []}, [5])).toEqual([ROOT, 5]);
        const open = expandPath(tree, [1, 12]);
        expect(isExpanded(open, 1) && isExpanded(open, 12)).toBe(true);
        expect(branchHas(open, 1, 12)).toBe(true);
        expect(branchHas(open, 1, 13)).toBe(false);
        expect(branchHas(open, 99, 1)).toBe(false);
    });
});
