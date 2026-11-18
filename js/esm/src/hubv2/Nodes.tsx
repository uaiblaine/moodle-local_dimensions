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
 * The canvas's node cards and its rule edge.
 *
 * React Flow wraps each card in a focusable element (role group, named by the competency, Enter or
 * Space selects it); the card itself holds the one control a node has of its own, the button that
 * opens or closes its children, which stops its key and click from also selecting the node.
 *
 * @module     local_dimensions/hubv2/Nodes
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {createContext, useContext} from 'react';
import type {KeyboardEvent, MouseEvent, ReactNode} from 'react';
import {BaseEdge, EdgeLabelRenderer, Handle, Position, getSmoothStepPath} from '../../vendor/xyflow/xyflow.js';
import type {EdgeProps, NodeProps} from '../../vendor/xyflow/xyflow.js';
import {cardCount} from './graph';
import type {CanvasEdge, CanvasNode, CompetencyData, FrameworkData, MoreData} from './graph';
import {isHexColour} from './html';
import {fill, fillCount} from './strings';
import type {Labels} from './strings';

/** What the cards need from the canvas. */
export type CanvasActions = {
    labels: Labels,
    pagesize: number,
    toggle: (id: number) => void,
    loadMore: (parent: number) => void,
};

/** The canvas's actions, provided by Hub. */
export const CanvasContext = createContext<CanvasActions | null>(null);

/**
 * The canvas's actions.
 *
 * @returns {object} The actions.
 */
const useCanvas = (): CanvasActions => {
    const actions = useContext(CanvasContext);
    if (!actions) {
        throw new Error('local_dimensions/hubv2: a node rendered outside the canvas');
    }
    return actions;
};

/**
 * Keep Enter and Space on a card's own button from also selecting the node around it.
 *
 * @param {object} event The key event.
 * @returns {void}
 */
const keepKeys = (event: KeyboardEvent): void => {
    if (event.key === 'Enter' || event.key === ' ') {
        event.stopPropagation();
    }
};

/** The class of a card's connection points, which the stylesheet hides. */
const HANDLE = 'local-dimensions-hubv2-handle';

/** The tile's colours by depth, when the competency has no colour of its own; deeper ones take the last. */
const DEPTH_TILES = [
    'local-dimensions-hubv2-tile-depth1',
    'local-dimensions-hubv2-tile-depth2',
    'local-dimensions-hubv2-tile-depth3',
    'local-dimensions-hubv2-tile-depth4',
];

/** The class a selected card adds, with its leading space. */
const SELECTED = ' local-dimensions-hubv2-selected';

/**
 * The two connection points every edge needs; nothing connects through them.
 *
 * @param {object} props The props.
 * @returns {ReactNode} The handles.
 */
const Handles = ({source = true, target = true}: {source?: boolean, target?: boolean}): ReactNode => (
    <>
        {target ? <Handle type="target" position={Position.Left} isConnectable={false} className={HANDLE} /> : null}
        {source ? <Handle type="source" position={Position.Right} isConnectable={false} className={HANDLE} /> : null}
    </>
);

/**
 * The framework's card, at the left of the tree.
 *
 * @param {object} props The node's props.
 * @returns {ReactNode} The card.
 */
export const FrameworkNode = ({data, selected}: NodeProps<CanvasNode>): ReactNode => {
    const {labels} = useCanvas();
    const {framework, count} = data as FrameworkData;
    return (
        <div className={`local-dimensions-hubv2-card local-dimensions-hubv2-card-framework${selected ? SELECTED : ''}`}>
            <span className="local-dimensions-hubv2-tile local-dimensions-hubv2-tile-framework" aria-hidden="true">
                <i className="fa fa-sitemap" />
            </span>
            <span className="local-dimensions-hubv2-card-text">
                <span className="local-dimensions-hubv2-card-title">{framework.shortname}</span>
                <span className="local-dimensions-hubv2-card-sub">{fillCount(labels, 'hubv2_competencies', count)}</span>
            </span>
            <Handles target={false} />
        </div>
    );
};

/**
 * A competency's card: its colour or depth tile, its name, its taxonomy or rule, a count, and
 * the button that opens its children.
 *
 * @param {object} props The node's props.
 * @returns {ReactNode} The card.
 */
export const CompetencyNode = ({data, selected}: NodeProps<CanvasNode>): ReactNode => {
    const {labels, toggle} = useCanvas();
    const {node, expanded, childcount, depth, moving} = data as CompetencyData;
    // The admin's colours go straight into the style, never through the stylesheet: only a hex value passes.
    const tilestyle = isHexColour(node.bgcolor)
        ? {backgroundColor: node.bgcolor, color: isHexColour(node.textcolor) ? node.textcolor : undefined}
        : undefined;
    const tileclass = tilestyle ? 'local-dimensions-hubv2-tile-custom' : DEPTH_TILES[Math.min(depth, DEPTH_TILES.length) - 1];
    // Without the admin's text colour the custom tile is a swatch: no glyph whose contrast is unknown.
    const glyph = !tilestyle || tilestyle.color ? <i className="fa fa-bullseye" /> : null;
    const shown = cardCount(node, childcount);
    const countlabel = shown ? fillCount(labels, shown.kind === 'children' ? 'hubv2_children' : 'hubv2_courses', shown.count) : '';
    const classes = ['local-dimensions-hubv2-card'];
    if (selected) {
        classes.push('local-dimensions-hubv2-selected');
    }
    if (moving) {
        classes.push('local-dimensions-hubv2-moving');
    }
    /**
     * Open or close the children, without selecting the node.
     *
     * @param {object} event The click.
     * @returns {void}
     */
    const onToggle = (event: MouseEvent): void => {
        event.stopPropagation();
        toggle(node.id);
    };
    return (
        <div className={classes.join(' ')}>
            <Handles />
            <span className={`local-dimensions-hubv2-tile ${tileclass}`} style={tilestyle} aria-hidden="true">{glyph}</span>
            <span className="local-dimensions-hubv2-card-text">
                <span className="local-dimensions-hubv2-card-title">{node.shortname}</span>
                <span className="local-dimensions-hubv2-card-sub">{node.ruletype ? node.rulelabel : node.taxonomy}</span>
            </span>
            {shown ? (
                <span className="local-dimensions-hubv2-pill" title={countlabel}>
                    <span aria-hidden="true">{shown.count}</span>
                    <span className="visually-hidden">{countlabel}</span>
                </span>
            ) : null}
            {node.haschildren ? (
                <button
                    type="button"
                    className="local-dimensions-hubv2-toggle nodrag nopan"
                    aria-expanded={expanded}
                    aria-label={fill(labels[expanded ? 'hubv2_collapse' : 'hubv2_expand'], node.shortname)}
                    onClick={onToggle}
                    onKeyDown={keepKeys}
                >
                    <i className={`fa ${expanded ? 'fa-chevron-left' : 'fa-chevron-right'}`} aria-hidden="true" />
                </button>
            ) : null}
        </div>
    );
};

/**
 * The pseudo-node after a partly loaded branch: loads the parent's next page.
 *
 * @param {object} props The node's props.
 * @returns {ReactNode} The card.
 */
export const MoreNode = ({data}: NodeProps<CanvasNode>): ReactNode => {
    const {labels, loadMore, pagesize} = useCanvas();
    const {parent, remaining} = data as MoreData;
    return (
        <div className="local-dimensions-hubv2-card local-dimensions-hubv2-card-more">
            <Handles source={false} />
            <button type="button" className="btn btn-sm btn-outline-secondary nodrag nopan" onClick={() => loadMore(parent)}>
                {fill(labels.hubv2_loadmore, String(Math.min(remaining, pagesize)))}
            </button>
        </div>
    );
};

/**
 * The edge from a competency with a rule to one of its children: the rule's colour, and the
 * rule's label once, near the parent, on the first child's edge.
 *
 * @param {object} props The edge's props.
 * @returns {ReactNode} The edge.
 */
export const RuleEdge = (props: EdgeProps<CanvasEdge>): ReactNode => {
    const {id, sourceX, sourceY, targetX, targetY, sourcePosition, targetPosition, data} = props;
    const [path] = getSmoothStepPath({sourceX, sourceY, sourcePosition, targetX, targetY, targetPosition});
    return (
        <>
            <BaseEdge id={id} path={path} />
            {data && data.label ? (
                <EdgeLabelRenderer>
                    <div
                        className="local-dimensions-hubv2-edge-label"
                        style={{transform: `translate(0, -50%) translate(${sourceX + 14}px, ${sourceY}px)`}}
                    >
                        {data.label}
                    </div>
                </EdgeLabelRenderer>
            ) : null}
        </>
    );
};
