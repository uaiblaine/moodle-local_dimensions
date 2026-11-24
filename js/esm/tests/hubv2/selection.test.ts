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
 * Tests for the workspace's one selection: what a pick does in either pane, where each pane's Tab
 * stop goes after a change, and which panes each layout draws.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {navOrder} from '../../src/hubv2/keynav';
import {LAYOUTS, LAYOUT_EVENT, activation, isLayout, nextStop, panesOf} from '../../src/hubv2/selection';
import {ROOT, createTree, insertChildren, removeNode, setExpanded} from '../../src/hubv2/tree';
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
 * Root 1, open, with children 11 and 12; root 2.
 *
 * @returns {object} The tree.
 */
const sample = () => {
    let tree = createTree({items: [node(1, ROOT, {haschildren: true}), node(2)], total: 2});
    tree = insertChildren(tree, 1, {items: [node(11, 1), node(12, 1)], total: 2});
    return setExpanded(tree, 1, true);
};

describe('a pick in either pane', () => {
    it('selects the competency, or the framework with null', () => {
        expect(activation(null, 12)).toEqual({type: 'select', id: 12});
        expect(activation(null, ROOT)).toEqual({type: 'select', id: null});
    });

    it('picks the new parent instead while a competency is being moved, the framework as the top level', () => {
        expect(activation(11, 2)).toEqual({type: 'pick', parent: 2});
        expect(activation(11, ROOT)).toEqual({type: 'pick', parent: ROOT});
    });
});

describe('a pane\'s Tab stop after a change', () => {
    const tree = sample();
    const order = navOrder(tree);

    it('follows the selection while the focus is elsewhere: a selection made in the other pane', () => {
        expect(nextStop(order, order, tree, 12, 'c1', false)).toEqual({key: 'c12', refocus: false});
        expect(nextStop(order, order, tree, null, 'c12', false)).toEqual({key: 'f', refocus: false});
    });

    it('stops at the selection\'s nearest drawn ancestor when its branch is closed', () => {
        const closed = setExpanded(tree, 1, false);
        expect(nextStop(order, navOrder(closed), closed, 12, 'f', false)).toEqual({key: 'c1', refocus: false});
    });

    it('stays on the focused item while the focus is inside, whatever the selection', () => {
        expect(nextStop(order, order, tree, 2, 'c11', true)).toEqual({key: 'c11', refocus: false});
    });

    it('moves the focus to the nearest survivor when the focused item leaves the pane', () => {
        const closed = setExpanded(tree, 1, false);
        expect(nextStop(order, navOrder(closed), closed, null, 'c12', true)).toEqual({key: 'c1', refocus: true});
        const removed = removeNode(tree, 1);
        expect(nextStop(order, navOrder(removed), removed, null, 'c11', true)).toEqual({key: 'f', refocus: true});
        // The control: the same change with the focus elsewhere moves the stop without taking the focus.
        expect(nextStop(order, navOrder(closed), closed, null, 'c12', false)).toEqual({key: 'f', refocus: false});
    });
});

describe('the layouts', () => {
    it('are the three workspace views, each drawing its panes', () => {
        expect(LAYOUTS).toEqual(['outline', 'map', 'both']);
        expect(panesOf('outline')).toEqual({outline: true, map: false});
        expect(panesOf('map')).toEqual({outline: false, map: true});
        expect(panesOf('both')).toEqual({outline: true, map: true});
    });

    it('accept only those three values, the classic list included in none', () => {
        expect(LAYOUTS.every((layout) => isLayout(layout))).toBe(true);
        ['list', 'MAP', '', null, undefined, 1, ['map']].forEach((value) => expect(isLayout(value)).toBe(false));
    });

    it('are announced by the event the tab\'s view switch sends', () => {
        expect(LAYOUT_EVENT).toBe('local_dimensions:hubv2-layout');
    });
});
