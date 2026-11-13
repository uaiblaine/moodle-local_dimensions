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
 * Tests for what the canvas hands React Flow: nodes, the rule's label once, the related edges.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {FRAMEWORK_ID, buildGraph, cardCount, competencyOf} from '../../src/hubv2/graph';
import {ROOT, createTree, insertChildren, setExpanded} from '../../src/hubv2/tree';
import type {StructureNode} from '../../src/hubv2/types';

/**
 * A node with the fields the graph reads.
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

const FRAMEWORK = {id: 23, shortname: 'DigCompEdu', idnumber: 'DCE', competencycount: 5, canmanage: true};
const LABELS = {roledescription: 'competency', more: (remaining: number) => `Load ${remaining} more`};

/**
 * A root with a points rule and three children, and a second root.
 *
 * @returns {object} The tree.
 */
const ruled = () => {
    const root = node(1, ROOT, {haschildren: true, ruletype: 'points', rulelabel: 'Points'});
    let tree = createTree({items: [root, node(2)], total: 2});
    tree = insertChildren(tree, 1, {items: [node(11, 1), node(12, 1), node(13, 1)], total: 3});
    return setExpanded(tree, 1, true);
};

describe('the graph', () => {
    it('draws the framework, every visible competency, and an edge to each from its parent', () => {
        const graph = buildGraph({tree: ruled(), framework: FRAMEWORK, selected: null, related: [], moving: null, labels: LABELS});
        expect(graph.nodes.map((n) => n.id)).toEqual([FRAMEWORK_ID, 'c1', 'c11', 'c12', 'c13', 'c2']);
        expect(graph.nodes[0].selected).toBe(true);
        expect(graph.edges.map((e) => `${e.source}>${e.target}`)).toEqual(['f>c1', 'c1>c11', 'c1>c12', 'c1>c13', 'f>c2']);
        expect(competencyOf('c12')).toBe(12);
        expect(competencyOf(FRAMEWORK_ID)).toBeNull();
        expect(competencyOf('m1')).toBeNull();
    });

    it('draws a rule parent\'s edges in the rule type, with its label on the first one only', () => {
        const graph = buildGraph({tree: ruled(), framework: FRAMEWORK, selected: null, related: [], moving: null, labels: LABELS});
        const fromrule = graph.edges.filter((e) => e.source === 'c1');
        expect(fromrule.map((e) => e.type)).toEqual(['rule', 'rule', 'rule']);
        expect(fromrule.map((e) => e.data?.label)).toEqual(['Points', '', '']);
        expect(graph.edges.find((e) => e.target === 'c2')?.type).toBe('smoothstep');
    });

    it('adds a dotted edge to each related competency on the canvas, for the selected one only', () => {
        const input = {tree: ruled(), framework: FRAMEWORK, selected: 11, related: [13, 2, 99], moving: null, labels: LABELS};
        const related = buildGraph(input).edges.filter((e) => e.className === 'local-dimensions-hubv2-edge-related');
        expect(related.map((e) => `${e.source}>${e.target}`)).toEqual(['c11>c13', 'c11>c2']);
        expect(buildGraph({...input, selected: null}).edges.some((e) => e.id.startsWith('r-'))).toBe(false);
    });

    it('adds a load-more node after a partly loaded branch', () => {
        const tree = createTree({items: [node(1)], total: 30});
        const graph = buildGraph({tree, framework: FRAMEWORK, selected: null, related: [], moving: null, labels: LABELS});
        const more = graph.nodes.find((n) => n.id === 'm0');
        expect(more?.data).toEqual({kind: 'more', parent: ROOT, remaining: 29});
        expect(more?.selectable).toBe(false);
        expect(more?.ariaLabel).toBe('Load 29 more');
    });

    it('marks the selected and the moving competency', () => {
        const graph = buildGraph({tree: ruled(), framework: FRAMEWORK, selected: 12, related: [], moving: 12, labels: LABELS});
        const card = graph.nodes.find((n) => n.id === 'c12');
        expect(card?.selected).toBe(true);
        expect(card?.data).toMatchObject({kind: 'competency', moving: true, depth: 2, childcount: null});
        expect(graph.nodes.find((n) => n.id === 'c1')?.data).toMatchObject({expanded: true, childcount: 3});
        expect(graph.nodes[0].selected).toBe(false);
    });
});

describe('the number on a card', () => {
    it('shows none for a competency whose children are not loaded yet', () => {
        expect(cardCount(node(1, ROOT, {haschildren: true, coursecount: 0}), null)).toBeNull();
        expect(cardCount(node(1, ROOT, {haschildren: true, coursecount: 4}), null)).toBeNull();
    });

    it('shows the children count once they are loaded, even when it is the only number', () => {
        expect(cardCount(node(1, ROOT, {haschildren: true, coursecount: 4}), 3)).toEqual({kind: 'children', count: 3});
    });

    it('shows a leaf\'s linked courses only above zero', () => {
        expect(cardCount(node(2), null)).toBeNull();
        expect(cardCount(node(2, ROOT, {coursecount: 2}), null)).toEqual({kind: 'courses', count: 2});
    });
});
