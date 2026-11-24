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
 * Every edge is drawing only: what an edge says (a parent, a rule, a relation) the nodes and the panel
 * say in words, so each edge is left out of the accessibility tree ({@see EDGE_A11Y}). Every node is a
 * group named with its place in the tree (keynav.nodeName): React Flow's wrapper is an application,
 * which no tree role can cross, so the map says the structure in words and the outline beside it is
 * the workspace's real tree.
 *
 * Pure: the nodes carry plain data, and the components read the callbacks from context.
 *
 * @module     local_dimensions/hubv2/graph
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {Edge, Node} from '../../vendor/xyflow/xyflow.js';
import type {NameInfo} from './keynav';
import type {ItemMark} from './lenses';
import {layoutTree, mirrorLayout} from './layout';
import type {LayoutInput, Point} from './layout';
import {ROOT, isExpanded, visibleItems} from './tree';
import type {Tree} from './tree';
import type {Framework, StructureNode} from './types';

/** A card's size, in pixels; the stylesheet draws it at exactly this size. */
export const CARD = {width: 240, height: 76};

/** The framework node's id. */
export const FRAMEWORK_ID = 'f';

/**
 * What every edge carries for assistive technology: nothing. React Flow otherwise draws each edge as
 * an image named "Edge from <source> to <target>" with internal ids; hidden, with no role and no name,
 * an edge is not read out between the cards.
 */
export const EDGE_A11Y = {ariaRole: 'presentation', ariaLabel: null, domAttributes: {'aria-hidden': true}} as const;

/** Data of the framework node: with its findings while the checker is open, and whether a change touches it. */
export type FrameworkData = {kind: 'framework', framework: Framework, count: number, findings: number, impact: boolean};

/** Data of a competency node. */
export type CompetencyData = {
    kind: 'competency',
    node: StructureNode,
    expanded: boolean,
    childcount: number | null,
    depth: number,
    moving: boolean,
    // What the lens shown puts on it, or null.
    mark: ItemMark | null,
    // Its findings while the checker is open, 0 otherwise.
    findings: number,
    // Whether the change a dialogue is confirming touches it.
    impact: boolean,
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
export const parentKey = (id: number): string => (id === ROOT ? FRAMEWORK_ID : competencyKey(id));

/**
 * The id of the "load more" item after a parent's loaded children.
 *
 * @param {number} parent The parent.
 * @returns {string} The item's id.
 */
export const moreKey = (parent: number): string => `m${parent}`;

/**
 * What every node's wrapper carries beside its role and name: tabindex -1, so the map is one Tab stop
 * (the workspace gives the active node 0), and no aria-roledescription, which React Flow sets to the
 * English "node" (NodeWrapper writes it before it spreads domAttributes, so a key present here wins).
 */
const NODE_ATTRIBUTES = {tabIndex: -1, 'aria-roledescription': undefined};

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
    labels: {
        roledescription: string,
        more: (remaining: number) => string,
        // A competency node's accessible name (keynav.nodeName); its short name without one.
        name?: (info: NameInfo) => string,
        // The framework node's accessible name, from its competency count; its short name without one.
        framework?: (count: number) => string,
    },
    /** Whether the tree grows right to left (the page's language is written so). */
    rtl?: boolean,
    /** What the lenses, the checker and an impact preview put on the map; nothing when absent. */
    decor?: Decor,
};

/**
 * What the map shows on top of the tree: the lens's marks, the findings, the competencies a change touches,
 * the edges the rules lens draws in the rule's style, and the related pairs the relations lens draws.
 */
export type Decor = {
    marks?: Map<number, ItemMark>,
    // The words of a count of findings ("2 findings"), for the names.
    findingwords?: (count: number) => string,
    // Findings by competency, 0 for the framework.
    findings?: Map<number, number>,
    // The competencies a change touches, ROOT for the framework.
    impact?: Set<number>,
    // Under the rules lens: whether a parent's rule counts a child (lenses.countsChild).
    counts?: (parent: number, child: number) => boolean,
    // Under the relations lens: the related pairs with both ends drawn.
    pairs?: [number, number][],
};

/**
 * Add what the lens and the checker say of a competency to its name's facts, only when they say something.
 *
 * @param {object} info The name's facts, changed in place.
 * @param {string} lens The lens's words.
 * @param {string} findings The findings' words.
 * @returns {void}
 */
const addWords = (info: NameInfo, lens: string, findings: string): void => {
    if (lens) {
        info.lens = lens;
    }
    if (findings) {
        info.findings = findings;
    }
};

/**
 * The framework node's accessible name: its own, then its findings while the checker is open.
 *
 * @param {object} input The graph's input.
 * @param {string} findings The findings' words.
 * @returns {string} The name.
 */
const frameworkLabel = (input: GraphInput, findings: string): string => {
    const {framework, labels} = input;
    const name = labels.framework ? labels.framework(framework.competencycount) : framework.shortname;
    return findings ? `${name}, ${findings}` : name;
};

/**
 * What the lens, the checker and an impact preview put on one competency's card.
 *
 * @param {object} decor What the map shows on top of the tree.
 * @param {number} id The competency.
 * @returns {object} Its mark, its findings and whether a change touches it.
 */
const cardDecor = (decor: Decor, id: number): {mark: ItemMark | null, findings: number, impact: boolean} => ({
    mark: decor.marks?.get(id) ?? null,
    findings: decor.findings?.get(id) ?? 0,
    impact: !!decor.impact?.has(id),
});

/**
 * Whether the edge from a parent to a child takes the rule's style: the parent has a rule and, under the rules
 * lens, that rule counts the child.
 *
 * @param {object|null} parent The parent, null for the framework.
 * @param {object} decor What the map shows on top of the tree.
 * @param {number} parentid The parent's id.
 * @param {number} child The child.
 * @returns {boolean} Whether it does.
 */
const ruleStyled = (parent: StructureNode | null, decor: Decor, parentid: number, child: number): boolean =>
    !!(parent && parent.ruletype) && (!decor.counts || decor.counts(parentid, child));

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
    const decor: Decor = input.decor ?? {};
    /**
     * How many findings an item carries while the checker is open.
     *
     * @param {number} id The competency, ROOT for the framework.
     * @returns {number} The count.
     */
    const findingsOf = (id: number): number => decor.findings?.get(id) ?? 0;
    /**
     * A count of findings in words, for a name; nothing for none.
     *
     * @param {number} count The count.
     * @returns {string} The words.
     */
    const said = (count: number): string => (count > 0 && decor.findingwords ? decor.findingwords(count) : '');
    const items = visibleItems(tree);
    const layoutinput: LayoutInput[] = [{key: FRAMEWORK_ID, parent: null}];
    items.forEach((item) => {
        layoutinput.push({
            key: item.kind === 'competency' ? competencyKey(item.id) : moreKey(item.parent),
            parent: parentKey(item.parent),
        });
    });
    const placed = layoutTree(layoutinput);
    const positions = input.rtl ? mirrorLayout(placed) : placed;
    const drawn = new Set(items.flatMap((item) => (item.kind === 'competency' ? [item.id] : [])));
    // The relations the dotted edges draw are said in the related nodes' names instead.
    const relatedto = selected !== null && drawn.has(selected) ? tree.nodes[selected].shortname : null;
    const relatedset = new Set(relatedto !== null ? related.filter((id) => id !== selected) : []);

    const nodes: CanvasNode[] = [{
        id: FRAMEWORK_ID,
        type: 'framework',
        position: positions[FRAMEWORK_ID],
        data: {kind: 'framework', framework, count: framework.competencycount, findings: findingsOf(ROOT),
            impact: !!decor.impact?.has(ROOT)},
        ...CARD,
        selected: selected === null,
        ariaLabel: frameworkLabel(input, said(findingsOf(ROOT))),
        ariaRole: 'group',
        domAttributes: {...NODE_ATTRIBUTES, 'aria-current': selected === null ? 'true' : undefined},
    }];
    const edges: CanvasEdge[] = [];
    const ruled = new Set<number>();

    items.forEach((item) => {
        if (item.kind === 'more') {
            const key = moreKey(item.parent);
            nodes.push({
                id: key,
                type: 'more',
                position: positions[key],
                data: {kind: 'more', parent: item.parent, remaining: item.remaining},
                ...CARD,
                selectable: false,
                ariaLabel: labels.more(item.remaining),
                ariaRole: 'group',
                domAttributes: NODE_ATTRIBUTES,
            });
            edges.push({id: `e-${key}`, source: parentKey(item.parent), target: key, type: 'smoothstep',
                className: 'local-dimensions-hubv2-edge local-dimensions-hubv2-edge-more', ...EDGE_A11Y});
            return;
        }
        const node = tree.nodes[item.id];
        const key = competencyKey(item.id);
        const branch = tree.branches[item.id];
        const siblings = tree.branches[item.parent];
        const info: NameInfo = {
            name: node.shortname,
            // The framework is level 1 in both panes, so a competency is its depth plus one.
            level: item.depth + 1,
            position: (siblings ? siblings.ids.indexOf(item.id) : 0) + 1,
            total: siblings ? siblings.total : 1,
            expandable: node.haschildren,
            expanded: isExpanded(tree, item.id),
            children: branch ? branch.total : null,
            rule: node.ruletype ? node.rulelabel : '',
            relatedto: relatedset.has(item.id) ? relatedto : null,
        };
        const decorated = cardDecor(decor, item.id);
        addWords(info, decorated.mark ? decorated.mark.said : '', said(decorated.findings));
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
                ...decorated,
            },
            ...CARD,
            selected: selected === item.id,
            ariaLabel: labels.name ? labels.name(info) : node.shortname,
            ariaRole: 'group',
            domAttributes: {
                ...NODE_ATTRIBUTES,
                'aria-roledescription': labels.roledescription,
                'aria-current': selected === item.id ? 'true' : undefined,
            },
        });
        const parent = item.parent === ROOT ? null : tree.nodes[item.parent];
        const hasrule = ruleStyled(parent, decor, item.parent, item.id);
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
            ...EDGE_A11Y,
        });
    });

    // The relations lens draws every pair whose two ends are drawn, as the selection's own relations are drawn.
    const paired = new Set<string>();
    (decor.pairs ?? []).forEach(([a, b]) => {
        paired.add(`${a}-${b}`);
        edges.push({
            id: `p-${a}-${b}`,
            source: competencyKey(a),
            target: competencyKey(b),
            type: 'default',
            className: 'local-dimensions-hubv2-edge-related',
            zIndex: 1,
            ...EDGE_A11Y,
        });
    });

    if (selected !== null && drawn.has(selected)) {
        /**
         * The key of the pair a relation of the selection makes, lower id first, as the lens's pairs are keyed.
         *
         * @param {number} id The related competency.
         * @returns {string} The key.
         */
        const pairKey = (id: number): string => `${Math.min(id, selected)}-${Math.max(id, selected)}`;
        related.filter((id) => id !== selected && drawn.has(id) && !paired.has(pairKey(id))).forEach((id) => {
            edges.push({
                id: `r-${selected}-${id}`,
                source: competencyKey(selected),
                target: competencyKey(id),
                type: 'default',
                className: 'local-dimensions-hubv2-edge-related',
                zIndex: 1,
                ...EDGE_A11Y,
            });
        });
    }

    return {nodes, edges, positions};
};
