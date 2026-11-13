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
 * Hand-written, narrow declarations of the vendored React Flow module (xyflow.js beside this file).
 *
 * Only what the canvas uses, in the shape @xyflow/react 12.12.1 declares it
 * (node_modules/@xyflow/react/dist/esm/types and @xyflow/system/dist/esm/types of that release).
 * The real declarations are not vendored: they span two packages and some sixty files, and core's
 * build compiles every .ts under js/esm/src, which is why this file and the module live outside it.
 * A name the canvas starts using is declared here first, from those sources, or tsc fails.
 *
 * @module     local_dimensions/vendor/xyflow
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {ComponentType, CSSProperties, HTMLAttributes, MouseEvent as ReactMouseEvent, ReactNode} from 'react';

/** Which side of a node a handle sits on (an enum of strings in the library). */
export declare const Position: {readonly Left: 'left', readonly Top: 'top', readonly Right: 'right', readonly Bottom: 'bottom'};
export type Position = 'left' | 'top' | 'right' | 'bottom';

/** The background's pattern (an enum of strings in the library). */
export declare const BackgroundVariant: {readonly Lines: 'lines', readonly Dots: 'dots', readonly Cross: 'cross'};
export type BackgroundVariant = 'lines' | 'dots' | 'cross';

/** A point on the canvas. */
export type XYPosition = {x: number, y: number};

/** A node as the canvas hands it to the library. */
export type Node<NodeData extends Record<string, unknown> = Record<string, unknown>, NodeType extends string = string> = {
    id: string,
    position: XYPosition,
    data: NodeData,
    type?: NodeType,
    width?: number,
    height?: number,
    selected?: boolean,
    selectable?: boolean,
    draggable?: boolean,
    connectable?: boolean,
    deletable?: boolean,
    focusable?: boolean,
    className?: string,
    ariaLabel?: string,
    ariaRole?: string,
    domAttributes?: Omit<HTMLAttributes<HTMLDivElement>, 'id' | 'style' | 'className' | 'draggable' | 'role' | 'aria-label'>,
    measured?: {width?: number, height?: number},
};

/** An edge as the canvas hands it to the library. */
export type Edge<EdgeData extends Record<string, unknown> = Record<string, unknown>, EdgeType extends string = string> = {
    id: string,
    source: string,
    target: string,
    type?: EdgeType,
    data?: EdgeData,
    className?: string,
    selectable?: boolean,
    focusable?: boolean,
    deletable?: boolean,
    zIndex?: number,
};

/** What a custom node component receives. */
export type NodeProps<NodeType extends Node = Node> = {
    id: string,
    data: NodeType['data'],
    type: string,
    selected: boolean,
    width?: number,
    height?: number,
};

/** What a custom edge component receives. */
export type EdgeProps<EdgeType extends Edge = Edge> = {
    id: string,
    source: string,
    target: string,
    data?: EdgeType['data'],
    sourceX: number,
    sourceY: number,
    targetX: number,
    targetY: number,
    sourcePosition: Position,
    targetPosition: Position,
    markerEnd?: string,
    style?: CSSProperties,
};

/** A change the library reports for a node: only selection and measurement reach the canvas. */
export type NodeChange =
    {id: string, type: 'select', selected: boolean}
    | {id: string, type: 'dimensions', dimensions?: {width: number, height: number}, resizing?: boolean}
    | {id: string, type: 'position', position?: XYPosition, dragging?: boolean}
    | {id: string, type: 'remove'}
    | {type: 'add', item: Node, index?: number}
    | {id: string, type: 'replace', item: Node};

/** The library's labels for its own controls and descriptions. */
export type AriaLabelConfig = {
    'node.a11yDescription.default': string,
    'node.a11yDescription.keyboardDisabled': string,
    'node.a11yDescription.ariaLiveMessage': (params: {direction: string, x: number, y: number}) => string,
    'edge.a11yDescription.default': string,
    'controls.ariaLabel': string,
    'controls.zoomIn.ariaLabel': string,
    'controls.zoomOut.ariaLabel': string,
    'controls.fitView.ariaLabel': string,
    'controls.interactive.ariaLabel': string,
    'minimap.ariaLabel': string,
    'handle.ariaLabel': string,
};

/** A fitView padding: a fraction of the view, a length such as '24px', or one of those per side. */
export type FitPadding = number | string
    | {top?: number | string, right?: number | string, bottom?: number | string, left?: number | string};

/** How fitView frames the nodes. */
export type FitViewOptions = {padding?: FitPadding, minZoom?: number, maxZoom?: number, duration?: number, nodes?: {id: string}[]};

/** The props of <ReactFlow> the canvas sets. */
export type ReactFlowProps<NodeType extends Node = Node, EdgeType extends Edge = Edge> = {
    nodes: NodeType[],
    edges: EdgeType[],
    nodeTypes?: Record<string, ComponentType<NodeProps<NodeType>>>,
    edgeTypes?: Record<string, ComponentType<EdgeProps<EdgeType>>>,
    onNodesChange?: (changes: NodeChange[]) => void,
    onPaneClick?: (event: ReactMouseEvent) => void,
    nodesDraggable?: boolean,
    nodesConnectable?: boolean,
    nodesFocusable?: boolean,
    edgesFocusable?: boolean,
    elementsSelectable?: boolean,
    selectNodesOnDrag?: boolean,
    deleteKeyCode?: string | string[] | null,
    selectionKeyCode?: string | string[] | null,
    multiSelectionKeyCode?: string | string[] | null,
    zoomOnDoubleClick?: boolean,
    zoomOnScroll?: boolean,
    zoomOnPinch?: boolean,
    preventScrolling?: boolean,
    zoomActivationKeyCode?: string | string[] | null,
    autoPanOnNodeFocus?: boolean,
    fitView?: boolean,
    fitViewOptions?: FitViewOptions,
    minZoom?: number,
    maxZoom?: number,
    ariaLabelConfig?: Partial<AriaLabelConfig>,
    proOptions?: {hideAttribution?: boolean},
    className?: string,
    children?: ReactNode,
    'aria-label'?: string,
};

/** The canvas itself. */
export declare function ReactFlow<NodeType extends Node = Node, EdgeType extends Edge = Edge>(
    props: ReactFlowProps<NodeType, EdgeType>,
): ReactNode;

/** Provides the store useReactFlow() reads, above <ReactFlow>. */
export declare function ReactFlowProvider(props: {children?: ReactNode}): ReactNode;

/** The patterned background. */
export declare function Background(props: {variant?: BackgroundVariant, gap?: number, size?: number, className?: string}): ReactNode;

/** Zoom and fit buttons. */
export declare function Controls(props: {showInteractive?: boolean, showZoom?: boolean, showFitView?: boolean, className?: string,
    fitViewOptions?: FitViewOptions, position?: string}): ReactNode;

/** The overview map. */
export declare function MiniMap(props: {pannable?: boolean, zoomable?: boolean, className?: string,
    nodeClassName?: string | ((node: Node) => string), ariaLabel?: string | null, position?: string,
    style?: CSSProperties}): ReactNode;

/** A connection point on a node; every edge needs one at each end. */
export declare function Handle(props: {type: 'source' | 'target', position: Position, isConnectable?: boolean,
    className?: string}): ReactNode;

/** The path of an edge, drawn by the library. */
export declare function BaseEdge(props: {id?: string, path: string, className?: string, style?: CSSProperties,
    markerEnd?: string, interactionWidth?: number}): ReactNode;

/** Renders HTML labels of edges in a layer above the canvas. */
export declare function EdgeLabelRenderer(props: {children?: ReactNode}): ReactNode;

/** The path of a smooth-step edge: [path, labelX, labelY, offsetX, offsetY]. */
export declare function getSmoothStepPath(params: {sourceX: number, sourceY: number, sourcePosition?: Position,
    targetX: number, targetY: number, targetPosition?: Position, borderRadius?: number, offset?: number}):
    [string, number, number, number, number];

/** The canvas's imperative helpers. */
export type ReactFlowInstance = {
    setCenter: (x: number, y: number, options?: {zoom?: number, duration?: number}) => Promise<boolean>,
    fitView: (options?: FitViewOptions) => Promise<boolean>,
    getZoom: () => number,
};

/** The canvas's imperative helpers, inside a ReactFlowProvider. */
export declare function useReactFlow(): ReactFlowInstance;

/** Apply the changes the library reports to a list of nodes. */
export declare function applyNodeChanges<NodeType extends Node = Node>(changes: NodeChange[], nodes: NodeType[]): NodeType[];
