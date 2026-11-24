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
 * Tests for the impact preview: what a delete and a move mark while their dialogue is open.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {NO_IMPACT, deleteImpact, moveImpact, sameImpact} from '../../src/hubv2/impact';
import {ROOT, createTree, insertChildren} from '../../src/hubv2/tree';
import type {StructureNode} from '../../src/hubv2/types';

/**
 * A node with the fields the tree reads.
 *
 * @param {number} id The id.
 * @param {number} parentid The parent.
 * @returns {object} The node.
 */
const node = (id: number, parentid: number = ROOT): StructureNode => ({
    id, parentid, shortname: `C${id}`, idnumber: '', taxonomy: '', scale: '', description: '',
    coursecount: 0, activitycount: 0, templatecount: 0, depth: 0, indent: 0, haschildren: false, canmanage: true,
    ruletype: null, ruleoutcome: 0, ruleconfig: null, rulelabel: '', type: '', tag1: '', tag2: '', bgcolor: '', textcolor: '',
});

describe('the impact preview', () => {
    it('marks a deleted competency and every loaded descendant, and nothing beside them', () => {
        let tree = createTree({items: [node(1), node(2)], total: 2});
        tree = insertChildren(tree, 1, {items: [node(11, 1), node(12, 1)], total: 2});
        tree = insertChildren(tree, 11, {items: [node(111, 11)], total: 1});
        expect([...deleteImpact(tree, 1)].sort((a, b) => a - b)).toEqual([1, 11, 12, 111]);
        expect([...deleteImpact(tree, 12)]).toEqual([12]);
    });

    it('marks the parent a move leaves and the one it joins, the framework as ROOT', () => {
        expect([...moveImpact(4, ROOT)]).toEqual([4, ROOT]);
    });

    it('knows an equal set, so the store does not change for it', () => {
        expect(sameImpact(new Set([1, 2]), new Set([2, 1]))).toBe(true);
        expect(sameImpact(new Set([1]), new Set([1, 2]))).toBe(false);
        expect(sameImpact(NO_IMPACT, new Set())).toBe(true);
    });
});
