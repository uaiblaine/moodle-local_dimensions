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
 * Tests for what the workspace keeps across a reload of its pane: the opened branches, parents first, the
 * loaded counts and the selection, and only for a mount of the same framework, once.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {carryOf, hold, take} from '../../src/hubv2/carry';
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

/**
 * Roots 1 and 2 of 30; 1 opened with children 11 and 12 of 3; 12 opened with child 121.
 *
 * @returns {object} The tree.
 */
const opened = () => {
    let tree = createTree({items: [node(1, ROOT, true), node(2)], total: 30});
    tree = insertChildren(tree, 1, {items: [node(11, 1), node(12, 1, true)], total: 3});
    tree = insertChildren(tree, 12, {items: [node(121, 12)], total: 1});
    // Opened child first, so the carry has to put the parent before it.
    return setExpanded(setExpanded(tree, 12, true), 1, true);
};

describe('the carry', () => {
    it('keeps the opened branches parents first, the loaded counts and the selection', () => {
        const carry = carryOf(7, opened(), 121);
        expect(carry.frameworkid).toBe(7);
        expect(carry.expanded).toEqual([{id: 1, pathids: []}, {id: 12, pathids: [1]}]);
        expect(carry.loaded).toEqual({[ROOT]: 2, [1]: 2, [12]: 1});
        expect(carry.selected).toEqual({id: 121, pathids: [1, 12]});
    });

    it('keeps the root as no selection, and an unknown selection as none', () => {
        expect(carryOf(7, opened(), null).selected).toBeNull();
        expect(carryOf(7, opened(), 999).selected).toBeNull();
    });

    it('is taken back by a mount of the same framework, once', () => {
        hold(carryOf(7, opened(), null));
        expect(take(7)).not.toBeNull();
        expect(take(7)).toBeNull();
    });

    it('is dropped by a mount of another framework', () => {
        hold(carryOf(7, opened(), null));
        expect(take(8)).toBeNull();
        // Control: what was held is gone, not kept for a later mount of the first framework.
        expect(take(7)).toBeNull();
    });
});
