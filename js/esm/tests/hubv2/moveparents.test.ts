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
 * Tests for the "Move to" dialogue's choices: never the competency, its descendants or its current parent;
 * the top level first; the dialogue's own opened branches; and the rule sentences it shares with the
 * pick-on-the-map flow.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {ruleNotes} from '../../src/hubv2/move';
import {canMoveUnder, hitSelectable, parentChoices, pickerTree} from '../../src/hubv2/moveparents';
import type {Labels} from '../../src/hubv2/strings';
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
    id, parentid, shortname: `C${id}`, idnumber: '', taxonomy: '', scale: '', description: '',
    coursecount: 0, activitycount: 0, templatecount: 0, depth: 0, indent: 0, haschildren: false, canmanage: true,
    ruletype: null, ruleoutcome: 0, ruleconfig: null, rulelabel: '', type: '', tag1: '', tag2: '', bgcolor: '', textcolor: '',
    ...extra,
});

/**
 * Roots 1 and 2; 1 holds 11 (the competency moved, itself a parent of 111) and 12; 2 holds 21. Every
 * branch is loaded; the workspace has 1 and 11 open.
 *
 * @returns {object} The tree.
 */
const sample = () => {
    let tree = createTree({items: [node(1, ROOT, {haschildren: true}), node(2, ROOT, {haschildren: true})], total: 2});
    tree = insertChildren(tree, 1, {items: [node(11, 1, {haschildren: true}), node(12, 1)], total: 2});
    tree = insertChildren(tree, 11, {items: [node(111, 11)], total: 1});
    tree = insertChildren(tree, 2, {items: [node(21, 2)], total: 1});
    return setExpanded(setExpanded(tree, 1, true), 11, true);
};

/**
 * The rows' keys with their flags, for a set of opened branches.
 *
 * @param {Array} open The dialogue's opened branches.
 * @returns {Array} [key, disabled, current] per row.
 */
const rows = (open: number[]) => parentChoices(sample(), open, 11, 'Top').map((row) => [row.key, row.disabled, row.current]);

describe('the dialogue\'s tree', () => {
    it('starts with the top level, and opens only what the dialogue opened', () => {
        expect(rows([])).toEqual([['f', false, false], ['c1', true, true], ['c2', false, false]]);
        expect(rows([2])).toEqual([['f', false, false], ['c1', true, true], ['c2', false, false], ['c21', false, false]]);
    });

    it('draws the competency moved as a closed row that cannot be chosen, and never its descendants', () => {
        // Branch 11 is open in the workspace and asked open here: its child 111 still never shows.
        expect(rows([1, 11])).toEqual([
            ['f', false, false], ['c1', true, true], ['c11', true, false], ['c12', false, false], ['c2', false, false],
        ]);
        const moved = parentChoices(sample(), [1, 11], 11, 'Top').find((row) => row.key === 'c11');
        expect(moved?.expandable).toBe(false);
        expect(pickerTree(sample(), [1, 11], 11).expanded).toEqual([1]);
    });

    it('marks the current parent and refuses it', () => {
        const current = parentChoices(sample(), [], 11, 'Top').filter((row) => row.current).map((row) => row.key);
        expect(current).toEqual(['c1']);
        // A top-level competency's current parent is the top level.
        const top = parentChoices(sample(), [], 2, 'Top').find((row) => row.key === 'f');
        expect([top?.current, top?.disabled]).toEqual([true, true]);
    });

    it('leaves the workspace\'s tree as it was', () => {
        const tree = sample();
        parentChoices(tree, [2], 11, 'Top');
        expect(tree.expanded).toEqual([1, 11]);
        expect(tree.nodes[11].haschildren).toBe(true);
    });
});

describe('canMoveUnder', () => {
    it('refuses the competency itself and every descendant', () => {
        expect(canMoveUnder(sample(), 11, 11, 1)).toBe(false);
        expect(canMoveUnder(sample(), 111, 11, 1)).toBe(false);
        // Control: a sibling and another branch may take it.
        expect(canMoveUnder(sample(), 12, 11, 1)).toBe(true);
        expect(canMoveUnder(sample(), 21, 11, 1)).toBe(true);
    });

    it('refuses the current parent and accepts the top level otherwise', () => {
        expect(canMoveUnder(sample(), 1, 11, 1)).toBe(false);
        expect(canMoveUnder(sample(), ROOT, 11, 1)).toBe(true);
        expect(canMoveUnder(sample(), ROOT, 1, ROOT)).toBe(false);
    });
});

describe('hitSelectable', () => {
    /**
     * A search hit.
     *
     * @param {number} id The competency.
     * @param {Array} pathids Its ancestors, root first.
     * @returns {object} The hit.
     */
    const hit = (id: number, pathids: number[]) => ({id, shortname: `C${id}`, idnumber: '', path: '', pathids});

    it('refuses a hit below the competency, even one the tree has not loaded', () => {
        expect(hitSelectable(hit(999, [1, 11, 555]), 11, 1)).toBe(false);
        expect(hitSelectable(hit(11, [1]), 11, 1)).toBe(false);
        expect(hitSelectable(hit(1, []), 11, 1)).toBe(false);
        expect(hitSelectable(hit(21, [2]), 11, 1)).toBe(true);
    });
});

describe('ruleNotes', () => {
    const labels: Labels = {
        'hubv2_moverule_newparent': 'NEW {$a} LOSES ITS RULE.',
        'hubv2_moverule_oldparent': 'OLD {$a} LOSES ITS RULE.',
    };

    it('names each parent with a rule, the current one first, as plain text', () => {
        /**
         * A parent with a rule.
         *
         * @param {string} shortname Its name.
         * @returns {object} The parent.
         */
        const ruled = (shortname: string) => ({shortname, ruletype: 'points'});
        expect(ruleNotes(labels, ruled('A&B'), ruled('New'))).toEqual(['OLD A&B LOSES ITS RULE.', 'NEW New LOSES ITS RULE.']);
        expect(ruleNotes(labels, {shortname: 'Old', ruletype: null}, null)).toEqual([]);
        expect(ruleNotes(labels, null, ruled('New'))).toEqual(['NEW New LOSES ITS RULE.']);
    });
});
