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
 * From the tree to what React Flow draws: one node per visible competency, the framework at the
 * left, a "load more" node after a partly loaded branch, the parent-child edges and, for the
 * selected competency, a dotted edge to each related competency on the canvas.
 *
 * Pure: the nodes carry plain data, and the components read the callbacks from context.
 *
 * @module     local_dimensions/hubv2/graph
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {Edge, Node} from '../../vendor/xyflow/xyflow.js';
import {layoutTree} from './layout';
import type {LayoutInput, Point} from './layout';
import {ROOT, isExpanded, visibleItems} from './tree';
import type {Tree} from './tree';
import type {Framework, StructureNode} from './types';

/** A card's size, in pixels; the stylesheet draws it at exactly this size. */
export const CARD = {width: 240, height: 76};

/** The framework node's id. */
export const FRAMEWORK_ID = 'f';

/** Data of the framework node. */
export type FrameworkData = {kind: 'framework', framework: Framework, count: number};

/** Data of a competency node. */
export type CompetencyData = {
    kind: 'competency',
    node: StructureNode,
    expanded: boolean,
    childcount: number | null,
    depth: number,
    moving: boolean,
};

/** Data of a "load more" node. */
export type MoreData = {kind: 'more', parent: number, remaining: number};

/** A node of the canvas. */
export type CanvasNode = Node<FrameworkData | CompetencyData | MoreData>;

/** Data of an edge: the rule label it shows near its parent, if any. */
export type EdgeData = {label: string};

/** An edge of the canvas. */
export type CanvasEdge = Edge<EdgeData>;

/** The number a competency card shows: how many children or how many linked courses. */
export type CardCount = {kind: 'children' | 'courses', count: number};

/**
 * The number a competency card shows, if any.
 *
 * A competency with children whose branch is not loaded yet shows none (a zero would read as "no
 * children"; the expand button already says there are some). Once loaded it shows the children
 * count. A leaf shows its linked courses, and only when there are any.
 *
 * @param {object} node The competency.
 * @param {number|null} childcount The loaded children's total, or null when the branch is not loaded.
 * @returns {object|null} What to show, or null for no number.
 */
export const cardCount = (node: StructureNode, childcount: number | null): CardCount | null => {
    if (childcount !== null) {
        return {kind: 'children', count: childcount};
    }
    if (node.haschildren) {
        return null;
    }
    return node.coursecount > 0 ? {kind: 'courses', count: node.coursecount} : null;
};

/**
 * A competency node's id.
 *
 * @param {number} id The competency.
 * @returns {string} The node id.
 */
export const competencyKey = (id: number): string => `c${id}`;

/**
 * The node id of a parent: the framework for ROOT.
 *
 * @param {number} id The parent.
 * @returns {string} The node id.
 */
const parentKey = (id: number): string => (id === ROOT ? FRAMEWORK_ID : competencyKey(id));

/**
 * The competency id a node id names, or null for the framework and "load more" nodes.
 *
 * @param {string} key The node id.
 * @returns {number|null} The competency id.
 */
export const competencyOf = (key: string): number | null => (/^c\d+$/.test(key) ? Number(key.slice(1)) : null);

/** What the graph is built from. */
export type GraphInput = {
    tree: Tree,
    framework: Framework,
    selected: number | null,
    related: number[],
    moving: number | null,
    labels: {roledescription: string, more: (remaining: number) => string},
};

/** The graph: nodes, edges, and every position by node id. */
export type Graph = {nodes: CanvasNode[], edges: CanvasEdge[], positions: Record<string, Point>};

/**
 * Build the graph.
 *
 * @param {object} input The tree and what the canvas marks on it.
 * @returns {object} The graph.
 */
export const buildGraph = (input: GraphInput): Graph => {
    const {tree, framework, selected, related, moving, labels} = input;
    const items = visibleItems(tree);
    const layoutinput: LayoutInput[] = [{key: FRAMEWORK_ID, parent: null}];
    items.forEach((item) => {
        layoutinput.push({
            key: item.kind === 'competency' ? competencyKey(item.id) : `m${item.parent}`,
            parent: parentKey(item.parent),
        });
    });
    const positions = layoutTree(layoutinput);

    const nodes: CanvasNode[] = [{
        id: FRAMEWORK_ID,
        type: 'framework',
        position: positions[FRAMEWORK_ID],
        data: {kind: 'framework', framework, count: framework.competencycount},
        ...CARD,
        selected: selected === null,
        ariaLabel: framework.shortname,
        ariaRole: 'group',
    }];
    const edges: CanvasEdge[] = [];
    const ruled = new Set<number>();
    const onCanvas = new Set<number>();

    items.forEach((item) => {
        if (item.kind === 'more') {
            const key = `m${item.parent}`;
            nodes.push({
                id: key,
                type: 'more',
                position: positions[key],
                data: {kind: 'more', parent: item.parent, remaining: item.remaining},
                ...CARD,
                selectable: false,
                focusable: false,
                ariaLabel: labels.more(item.remaining),
            });
            edges.push({id: `e-${key}`, source: parentKey(item.parent), target: key, type: 'smoothstep',
                className: 'local-dimensions-hubv2-edge local-dimensions-hubv2-edge-more'});
            return;
        }
        const node = tree.nodes[item.id];
        const key = competencyKey(item.id);
        const branch = tree.branches[item.id];
        onCanvas.add(item.id);
        nodes.push({
            id: key,
            type: 'competency',
            position: positions[key],
            data: {
                kind: 'competency',
                node,
                expanded: isExpanded(tree, item.id),
                childcount: branch ? branch.total : null,
                depth: item.depth,
                moving: moving === item.id,
            },
            ...CARD,
            selected: selected === item.id,
            ariaLabel: node.shortname,
            ariaRole: 'group',
            domAttributes: {
                'aria-roledescription': labels.roledescription,
                'aria-current': selected === item.id ? 'true' : undefined,
            },
        });
        const parent = item.parent === ROOT ? null : tree.nodes[item.parent];
        const hasrule = !!(parent && parent.ruletype);
        // The rule's label is drawn once, on the first child's edge, near the parent.
        const first = hasrule && !ruled.has(item.parent);
        if (first) {
            ruled.add(item.parent);
        }
        edges.push({
            id: `e-${key}`,
            source: parentKey(item.parent),
            target: key,
            type: hasrule ? 'rule' : 'smoothstep',
            className: hasrule ? 'local-dimensions-hubv2-edge local-dimensions-hubv2-edge-rule' : 'local-dimensions-hubv2-edge',
            data: {label: first && parent ? parent.rulelabel : ''},
        });
    });

    if (selected !== null && onCanvas.has(selected)) {
        related.filter((id) => id !== selected && onCanvas.has(id)).forEach((id) => {
            edges.push({
                id: `r-${selected}-${id}`,
                source: competencyKey(selected),
                target: competencyKey(id),
                type: 'default',
                className: 'local-dimensions-hubv2-edge-related',
                zIndex: 1,
            });
        });
    }

    return {nodes, edges, positions};
};
