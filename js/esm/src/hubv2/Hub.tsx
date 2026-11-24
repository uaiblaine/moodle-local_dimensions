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
 * The workspace of the hub's Competencies tab: a framework's tree as an outline (Outline.tsx, an ARIA
 * tree) and as a map of nodes (React Flow), side by side or one at a time, with a detail panel.
 *
 * The entry of the bundle (js/esm/bundle.json), mounted by templates/central/structure.mustache in the
 * Outline, Map and Both views with the props classes/output/hub_map.php builds; the tab around it owns
 * the context, the framework choice and the view switch, and tells the workspace when the viewer moves
 * between its three layouts (selection.LAYOUT_EVENT), so the store survives the switch.
 *
 * One store, two projections: the Workspace below holds the tree (nodes, loaded pages, opened
 * branches), the selection and the move mode; the outline and the map draw it and send every pick,
 * key and command back here, so a selection, a move, a search or an "Expand all" made in one shows in
 * the other. Each pane keeps only its own Tab stop (roving.ts), and both walk the tree with the same
 * keys (keynav.ts).
 *
 * The tree is lazy: the first page of roots arrives as props, each branch is loaded when it is first
 * opened (local_dimensions_browse_structure), and a node is refreshed from the server after every
 * change. Nothing is persisted: the map's layout is recomputed from the tree on every change
 * (layout.ts), and nodes cannot be dragged. Every action reuses the hub's own dialogues and core's
 * competency services, called as the list view (amd/src/central/structure.js) calls them. The selected
 * competency is kept in the address (deeplink.ts), and a competency the link named is revealed at
 * mount when the server found it in this framework (props.reveal).
 *
 * Commands, not gestures: every action is in the action registry (commands.ts) and runs through one
 * function here (runAction), whether the panel's button, a node's menu (Menu.tsx, the "more" button or
 * Shift+F10), the command palette (Palette.tsx, Ctrl+K while the focus is in the workspace) or a key on
 * an item (Alt+Up and Alt+Down reorder; F2, Ctrl+Enter and Ctrl+Shift+Enter open the outline's inline
 * editor, Editor.tsx) asked for it. A competency moves under a new parent by picking it in a pane or
 * through "Move to" (MoveDialog.tsx); both end in moveUnder.
 *
 * The framework is the tree's root: selected (nothing else is), the panel shows it and its own actions, the
 * Structures tab's, run by that tab's AMD functions (amd/src/central/frameworks.js, through amd.ts) from the
 * root's panel, its menu and the palette. An action that changes the framework reloads the tab's pane, so
 * everything the server says of it is fresh; the opened branches and the selection survive the reload
 * (carry.ts). Above the map's columns, the taxonomy term of each level (ColumnHeads.tsx).
 *
 * @module     local_dimensions/hubv2/Hub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useCallback, useEffect, useMemo, useRef, useState} from 'react';
import type {CSSProperties, KeyboardEvent as ReactKeyboardEvent, ReactNode} from 'react';
import {
    LENS_ACTIONS, PALETTE_SHORTCUT, VIEW_ACTIONS, actionState, actionsFor, ariaShortcut, isMac, shortcutAction, shortcutCaps,
    specOf,
} from './commands';
import type {ActionContext, ActionId, ActionState, ActionView, KeyCommand, Shortcut} from './commands';
import Editor from './Editor';
import {codeTitle, findingCounts, orderFindings, stepFinding} from './findings';
import {NO_IMPACT, deleteImpact, moveImpact, sameImpact} from './impact';
import {IDLE, LENSES, indexInsights, insightFacts, latest, lensOf} from './insights';
import type {InsightsIndex, InsightsState, Lens, Measure} from './insights';
import {countsChild, drawnSet, lensMarks, pairsOnMap} from './lenses';
import type {ItemMark} from './lenses';
import {CheckButton, Checker, LensSection, LensSwitch} from './LensPanel';
import {focusReturn} from './focusreturn';
import type {LayerOrigin, Pane} from './focusreturn';
import {suggestIdnumber} from './idnumber';
import Menu from './Menu';
import type {MenuItem} from './Menu';
import MoveDialog from './MoveDialog';
import Palette from './Palette';
import type {PaletteEntry} from './paletteitems';
import {summaryFacts, summaryLines} from './summary';
import {carryOf, hold, take} from './carry';
import ColumnHeads from './ColumnHeads';
import type {Viewport} from './ColumnHeads';
import {columnHeads, deepestLevel} from './columns';
import {
    Background, BackgroundVariant, Controls, MiniMap, ReactFlow, ReactFlowProvider, useReactFlow,
} from '../../vendor/xyflow/xyflow.js';
import type {AriaLabelConfig, FitViewOptions, NodeChangeset} from '../../vendor/xyflow/xyflow.js';
import {load, notifyError} from './amd';
import type {RuleConfig} from './amd';
import {CARD, FRAMEWORK_ID, buildGraph, competencyKey, competencyOf, moreKey, parentKey} from './graph';
import {EXPAND_ALL_BOUND, collapseAll, expandStep} from './expandall';
import {branchTarget, entryKey, isTypeAheadKey, keyAction, navOrder, nodeName, panTarget, typeAhead} from './keynav';
import type {Rect} from './keynav';
import type {NameInfo} from './keynav';
import Outline, {OUTLINE_ID} from './Outline';
import type {OutlineActions, OutlineDisplay, OutlineEditor, OutlineHint} from './Outline';
import {editorSlot, outlineRows} from './outlinerows';
import type {OutlineRow} from './outlinerows';
import {useRoving} from './roving';
import type {Roving} from './roving';
import {LAYOUT_EVENT, activation, isLayout, panesOf} from './selection';
import type {Layout} from './selection';
import Splitter from './Splitter';
import {OUTLINE_WIDTH, clampWidth, storeWidth, storedWidth} from './outlinewidth';
import type {CanvasEdge, CanvasNode} from './graph';
import {rememberLens, rememberSelection} from './deeplink';
import {blockedBody, confirmBody} from './deletion';
import {escapeHtml} from './html';
import {motionDuration} from './motion';
import {moveBody} from './move';
import type {MoveParent} from './move';
import {CanvasContext, CompetencyNode, FrameworkNode, MoreNode, RuleEdge} from './Nodes';
import type {CanvasActions} from './Nodes';
import Panel from './Panel';
import type {PanelActions} from './Panel';
import {track} from './pending';
import {
    allChildren, browse, countCompetencies, createCompetency, deleteCheck, deleteCompetency, getNode, getNodes,
    insights as readInsights, moveOne, moveUpTimes, renameCompetency, selectionData, setParent, updateRule,
} from './repository';
import type {NodeAnswer} from './repository';
import Search from './Search';
import {fill, fillCount, fillObject, loadLabels} from './strings';
import type {Labels} from './strings';
import {
    ROOT, ancestors, branchHas, createTree, expandPath, forgetBranch, hasBranch, insertChildren, isExpanded, isWithin,
    removeNode, replaceNode, setExpanded,
} from './tree';
import type {Tree} from './tree';
import type {DeleteCheck, HubProps, PathItem, StructureNode, Usage} from './types';

/** The competency form the list view edits and adds with. */
const FORM_CLASS = 'local_dimensions\\form\\competency_dynamic_form';

/** The jQuery event, namespaced, through which the canvas hears a dialogue close. */
const MODAL_HIDDEN = 'modal:hidden.localdimensionshubv2';

/** Card components by node type; module-level so React Flow sees the same object on every render. */
const NODE_TYPES = {framework: FrameworkNode, competency: CompetencyNode, more: MoreNode};

/** The keys that turn the mouse wheel into zoom: the wheel alone scrolls the page. */
const ZOOM_KEYS = ['Control', 'Meta'];

/** The overview map's size, in pixels; FIT reserves the corner it covers. */
const MINIMAP = {width: 140, height: 96};

/**
 * How the tree is framed: a margin all round, above it room for the column headings (ColumnHeads.tsx) and
 * below it room for the overview map and the controls, so that no fitted node sits under any of them.
 */
const FIT: FitViewOptions = {padding: {top: '56px', right: '32px', bottom: '140px', left: '32px'}, maxZoom: 1};

/**
 * The item whose row and card carry the "more" button: the selected competency when it has actions, the root
 * (ROOT) when nothing is selected and the framework has actions, else none.
 *
 * @param {number|null} selected The selected competency, or null for the root.
 * @param {Array} nodeactions The actions offered on the selected competency.
 * @param {Array} frameworkactions The actions offered on the framework.
 * @returns {number|null} The competency, ROOT, or null.
 */
const menuTarget = (selected: number | null, nodeactions: ActionView[], frameworkactions: ActionView[]): number | null => {
    if (selected !== null) {
        return nodeactions.length > 0 ? selected : null;
    }
    return frameworkactions.length > 0 ? ROOT : null;
};

/** The id of the canvas's description: the levels its columns are, in words. */
const COLUMNS_ID = 'local-dimensions-hubv2-columns';

/** Edge components by edge type. */
const EDGE_TYPES = {rule: RuleEdge};

/** A map node's wrapper, as React Flow draws it; its data-id is the node's key. */
const NODE_SELECTOR = '.react-flow__node';

/** An outline row; its data-key is the item's key. */
const ROW_SELECTOR = '[role="treeitem"]';

/** The map's own overlays a focused node must not sit under (WCAG 2.4.11). */
const OVERLAYS = '.react-flow__minimap, .react-flow__controls';

/** How long a pause ends a type-ahead query, in milliseconds (the APG examples' 500). */
const TYPE_AHEAD_PAUSE = 500;

/** How many first pages "Expand all" asks for at once. */
const EXPAND_ALL_PARALLEL = 4;

/** The inline editor of the outline: a rename in place, or a new competency after a sibling (null: first child). */
type EditorState = {mode: 'rename', id: number} | {mode: 'create', parent: number, after: number | null, serial: number};

/**
 * A node's menu while it is open: the competency, the element it hangs from, and where it was opened (an
 * item of a pane, by a key or its "more" button, or the panel's "More actions" button).
 */
type MenuState = {id: number, anchor: HTMLElement, origin: Pane | 'panel'};

/**
 * The words of a refusal from the server, as core/ajax rejects with it.
 *
 * @param {*} error The rejection value.
 * @returns {string} Its message.
 */
const messageOf = (error: unknown): string => {
    if (error && typeof error === 'object' && 'message' in error && typeof error.message === 'string') {
        return error.message;
    }
    return String(error);
};

/** The class of each layout's root, written whole so the stylesheet test finds it. */
const LAYOUT_CLASSES: Record<Layout, string> = {
    outline: 'local-dimensions-hubv2-layout-outline',
    map: 'local-dimensions-hubv2-layout-map',
    both: 'local-dimensions-hubv2-layout-both',
};

/** The custom property through which the outline's width reaches the stylesheet in the Both layout. */
const WIDTH_PROPERTY = '--local-dimensions-hubv2-outline-width';

/** How long after the workspace mounts the insights are read, so the checker's count can show, in milliseconds. */
const INSIGHTS_DELAY = 800;

/** How many competencies' names one request asks for. */
const NAME_BATCH = 50;

/** No lens marks. */
const NO_MARKS: Map<number, ItemMark> = new Map();

/** No findings marked. */
const NO_FINDINGS: Map<number, number> = new Map();

/**
 * The workspace: the store, the search, the outline, the map and the panel, inside the provider
 * useReactFlow() needs.
 *
 * @param {object} props The page's props and the loaded labels.
 * @returns {ReactNode} The workspace.
 */
const Workspace = (props: HubProps & {labels: Labels}): ReactNode => {
    const {labels, framework, pagesize, rtl} = props;
    const flow = useReactFlow();
    const [layout, setLayout] = useState<Layout>(isLayout(props.layout) ? props.layout : 'map');
    const panes = panesOf(layout);
    const panesRef = useRef(panes);
    panesRef.current = panes;

    const [tree, setTree] = useState<Tree>(() => createTree(props.roots));
    const treeRef = useRef<Tree>(tree);
    const [selected, setSelected] = useState<number | null>(null);
    const selectedRef = useRef<number | null>(null);
    const [usage, setUsage] = useState<Usage | null>(null);
    const [related, setRelated] = useState<PathItem[]>([]);
    const [moving, setMoving] = useState<number | null>(null);
    const movingRef = useRef<number | null>(null);
    const [count, setCount] = useState(framework.competencycount);
    const [announcement, setAnnouncement] = useState('');
    const [measured, setMeasured] = useState<Record<string, {width: number, height: number}>>({});
    const centreOn = useRef<string | null>(null);
    const selectionAsk = useRef(0);
    // The competency a confirmed deletion is removing: its panel lists are not asked for again.
    const removing = useRef<number | null>(null);
    // The parents whose first page is on its way, and those whose next page is: a repeated key asks once.
    const [loadingBranches, setLoadingBranches] = useState<number[]>([]);
    const opening = useRef(new Set<number>());
    const paging = useRef(new Set<number>());
    // "Expand all": whether it runs, which run is current (Collapse all ends one), and its notice.
    const [expanding, setExpanding] = useState(false);
    const expandRun = useRef(0);
    const [notice, setNotice] = useState('');
    const [outlineWidth, setOutlineWidth] = useState(() => clampWidth(storedWidth(), 0));
    const panesBox = useRef<HTMLDivElement>(null);
    const canvasRef = useRef<HTMLDivElement>(null);
    const listRef = useRef<HTMLUListElement>(null);
    const typed = useRef({text: '', at: 0});
    const layoutRef = useRef<Layout>(layout);
    layoutRef.current = layout;
    const countRef = useRef(count);
    countRef.current = count;
    const expandingRef = useRef(expanding);
    expandingRef.current = expanding;
    // The commands' layers: a node's menu, the palette, "Move to", and the outline's inline editor.
    const [menu, setMenu] = useState<MenuState | null>(null);
    const [palette, setPalette] = useState(false);
    const [moveDialog, setMoveDialog] = useState<number | null>(null);
    const [editor, setEditor] = useState<EditorState | null>(null);
    const editorRef = useRef<EditorState | null>(null);
    editorRef.current = editor;
    const editorSerial = useRef(0);
    const [display, setDisplay] = useState<OutlineDisplay>({idnumber: false, taxonomy: false});
    const rootRef = useRef<HTMLDivElement>(null);
    const summaryRef = useRef<HTMLHeadingElement>(null);
    const mac = useMemo(isMac, []);
    // The pane that last had the focus: where a layer opened from another layer gives it back.
    const lastPane = useRef<Pane | null>(null);
    // A branch just opened on the map, whose children the viewport is to bring into view once drawn, and
    // the end of the pending token that spans the gap from the click or key to that move (pending.ts).
    const revealing = useRef<number | null>(null);
    const revealed = useRef<(() => void) | null>(null);

    /**
     * End the wait for a branch's children to be brought into view.
     *
     * @returns {void}
     */
    const settleReveal = (): void => {
        revealing.current = null;
        revealed.current?.();
        revealed.current = null;
    };
    // The lens, its measure, the framework's insights (insights.ts) and the names the tree has not loaded.
    const [lens, setLens] = useState<Lens>(() => lensOf(props.lens));
    const lensRef = useRef<Lens>(lens);
    lensRef.current = lens;
    const [measure, setMeasure] = useState<Measure>('courses');
    const [insightsState, setInsights] = useState<InsightsState>(IDLE);
    const insightsStatus = useRef<InsightsState['status']>('idle');
    const indexRef = useRef<InsightsIndex | null>(null);
    const insightsAsk = useMemo(latest, []);
    const [names, setNames] = useState<Record<number, string>>({});
    const naming = useRef(new Set<number>());
    const namedPaths = useRef<Record<number, number[]>>({});
    // The checker's panel mode, the finding chosen last, and whether a reveal from it keeps it open.
    const [checker, setChecker] = useState(false);
    const [findingAt, setFindingAt] = useState<number | null>(null);
    const findingAtRef = useRef<number | null>(null);
    const holdChecker = useRef(false);
    const checkButton = useRef<HTMLButtonElement>(null);
    const checkerHeading = useRef<HTMLHeadingElement>(null);
    // The competency whose related competencies' dialogue is open: its closing is the relation's change.
    const relating = useRef<number | null>(null);
    // The items a change being confirmed touches (impact.ts), and the competency "Move to" moves.
    const [impact, setImpactState] = useState<Set<number>>(NO_IMPACT);
    const moveDialogRef = useRef<number | null>(null);
    moveDialogRef.current = moveDialog;

    /**
     * Change the tree; the ref is what asynchronous work reads between renders.
     *
     * @param {Function} update From the current tree to the next.
     * @returns {void}
     */
    const commit = useCallback((update: (current: Tree) => Tree): void => {
        treeRef.current = update(treeRef.current);
        setTree(treeRef.current);
    }, []);

    /**
     * Load a parent's first page, or the page after the loaded ones.
     *
     * @param {number} parent The parent.
     * @param {boolean} append Whether to load the next page.
     * @returns {Promise} Resolves once in the tree.
     */
    const loadPage = useCallback((parent: number, append: boolean): Promise<void> => {
        const loaded = append ? (treeRef.current.branches[parent]?.ids.length ?? 0) : 0;
        return browse(framework.id, parent, loaded, pagesize)
            .then((page) => commit((current) => insertChildren(current, parent, page, append)));
    }, [commit, framework.id, pagesize]);

    /**
     * Reload a parent's children, as many as were loaded (one page at least, and at least `atleast`, so a
     * competency moved or added past the loaded ones stays drawn), after a change among them.
     *
     * @param {number} parent The parent.
     * @param {number} atleast How many children must be loaded at least.
     * @returns {Promise} Resolves once in the tree.
     */
    const reloadBranch = useCallback(async(parent: number, atleast: number = 1): Promise<void> => {
        const wanted = Math.max(treeRef.current.branches[parent]?.ids.length ?? 0, atleast, 1);
        const items: StructureNode[] = [];
        let total = 0;
        do {
            const page = await browse(framework.id, parent, items.length, pagesize);
            items.push(...page.items);
            total = page.total;
            if (page.items.length === 0) {
                break;
            }
        } while (items.length < Math.min(wanted, total));
        commit((current) => insertChildren(current, parent, {items, total}));
    }, [commit, framework.id, pagesize]);

    /**
     * Refresh a parent's own fields (whether it still has children), unless it is the framework.
     *
     * @param {number} parent The parent.
     * @returns {Promise} Resolves once in the tree.
     */
    const refreshParent = useCallback(async(parent: number): Promise<void> => {
        if (parent === ROOT) {
            return;
        }
        const answer = await getNode(parent);
        if (answer.found && answer.node) {
            const fresh = answer.node;
            commit((current) => replaceNode(current, fresh));
        }
    }, [commit]);

    /**
     * Make a competency drawable: load every branch along its path (further pages too, until it
     * is among them) and open its ancestors.
     *
     * @param {number} id The competency.
     * @param {Array} pathids Its ancestors, root first.
     * @returns {Promise} Resolves once it is on the canvas (or its branch is exhausted).
     */
    const ensureVisible = useCallback(async(id: number, pathids: number[]): Promise<void> => {
        const chain = [ROOT, ...pathids];
        for (let index = 0; index < chain.length; index++) {
            const parent = chain[index];
            const child = index + 1 < chain.length ? chain[index + 1] : id;
            if (!hasBranch(treeRef.current, parent)) {
                await loadPage(parent, false);
            }
            while (!branchHas(treeRef.current, parent, child)) {
                const branch = treeRef.current.branches[parent];
                if (!branch || branch.ids.length >= branch.total) {
                    break;
                }
                await loadPage(parent, true);
            }
        }
        commit((current) => expandPath(current, pathids));
    }, [commit, loadPage]);

    /**
     * Load what the panel shows beyond the node; a later selection discards an earlier answer.
     *
     * @param {number} id The competency.
     * @returns {void}
     */
    const loadSelection = useCallback((id: number): void => {
        const ask = ++selectionAsk.current;
        track('selection', selectionData(id))
            .then(([answer, relations]) => {
                if (ask === selectionAsk.current) {
                    setUsage(answer);
                    setRelated(relations);
                }
                return null;
            })
            .catch(notifyError);
    }, []);

    /**
     * Select a competency, or the framework with null, and keep it in the address. A selection closes the
     * checker, unless the checker made it (holdChecker).
     *
     * @param {number|null} id The competency.
     * @returns {void}
     */
    const choose = useCallback((id: number | null): void => {
        selectedRef.current = id;
        setSelected(id);
        // Selecting something closes the checker, unless the checker is what revealed it.
        if (!holdChecker.current) {
            setChecker(false);
        }
        rememberSelection(id);
        setUsage(null);
        setRelated([]);
        if (id === null) {
            selectionAsk.current++;
            return;
        }
        loadSelection(id);
    }, [loadSelection]);

    /**
     * Reveal a competency, select it and centre the canvas on it.
     *
     * @param {number} id The competency.
     * @param {Array} pathids Its ancestors, root first.
     * @returns {Promise} Resolves once revealed.
     */
    const reveal = useCallback((id: number, pathids: number[]): Promise<void> =>
        ensureVisible(id, pathids).then(() => {
            centreOn.current = competencyKey(id);
            choose(id);
            return undefined;
        }), [choose, ensureVisible]);

    /**
     * Place a competency again after it changed parent: reload both branches, refresh both parents
     * and reveal it where it now is. Its own children are forgotten: their depth, and so their
     * taxonomy, changed.
     *
     * @param {number} id The competency.
     * @param {number} oldparent Its parent before.
     * @param {object} answer Its fresh node and path.
     * @returns {Promise} Resolves once placed.
     */
    const relocate = useCallback(async(id: number, oldparent: number, answer: NodeAnswer): Promise<void> => {
        const fresh = answer.node as StructureNode;
        commit((current) => forgetBranch(current, id));
        await reloadBranch(oldparent);
        await refreshParent(oldparent);
        if (hasBranch(treeRef.current, fresh.parentid)) {
            await reloadBranch(fresh.parentid);
        }
        await refreshParent(fresh.parentid);
        await reveal(id, answer.pathids ?? []);
    }, [commit, refreshParent, reloadBranch, reveal]);

    /**
     * Refresh one competency after a dialogue changed it; one the form moved is placed again, one
     * that is gone is removed.
     *
     * @param {number} id The competency.
     * @returns {Promise} Resolves once refreshed.
     */
    const refreshNode = useCallback(async(id: number): Promise<void> => {
        const answer = await getNode(id);
        const known = treeRef.current.nodes[id];
        if (!answer.found || !answer.node) {
            commit((current) => removeNode(current, id));
            if (selectedRef.current === id) {
                choose(null);
            }
            return;
        }
        if (known && known.parentid !== answer.node.parentid) {
            await relocate(id, known.parentid, answer);
            return;
        }
        const fresh = answer.node;
        commit((current) => replaceNode(current, fresh));
        if (selectedRef.current === id) {
            loadSelection(id);
        }
    }, [choose, commit, loadSelection, relocate]);

    /**
     * Open a competency's children, loading them the first time. Opening one already open, or one
     * whose first page is on its way, does nothing, so a key repeated while the page loads asks once.
     *
     * @param {number} id The competency.
     * @param {boolean} onmap Whether it was opened on the map, which then brings the children into view.
     * @returns {void}
     */
    const openBranch = useCallback((id: number, onmap: boolean = false): void => {
        if (isExpanded(treeRef.current, id) || opening.current.has(id)) {
            return;
        }
        if (onmap && panesRef.current.map) {
            settleReveal();
            revealing.current = id;
            track('reveal', new Promise<void>((resolve) => {
                revealed.current = resolve;
            })).catch(notifyError);
        }
        if (hasBranch(treeRef.current, id)) {
            commit((current) => setExpanded(current, id, true));
            return;
        }
        opening.current.add(id);
        setLoadingBranches((current) => [...current, id]);
        track('children', loadPage(id, false).then(() => commit((current) => setExpanded(current, id, true))))
            .finally(() => {
                opening.current.delete(id);
                setLoadingBranches((current) => current.filter((other) => other !== id));
                // A page that failed opens nothing, so nothing is drawn to bring into view.
                if (revealing.current === id && !isExpanded(treeRef.current, id)) {
                    settleReveal();
                }
            })
            .catch(notifyError);
    }, [commit, loadPage]);

    /**
     * Close a competency's children; they stay loaded.
     *
     * @param {number} id The competency.
     * @returns {void}
     */
    const closeBranch = useCallback((id: number): void => {
        commit((current) => setExpanded(current, id, false));
    }, [commit]);

    /**
     * Open or close a competency's children, as the outline's chevron or a card's button asks.
     *
     * @param {number} id The competency.
     * @param {boolean} onmap Whether it was asked on the map.
     * @returns {void}
     */
    const toggle = useCallback((id: number, onmap: boolean = false): void => {
        if (isExpanded(treeRef.current, id)) {
            closeBranch(id);
        } else {
            openBranch(id, onmap);
        }
    }, [closeBranch, openBranch]);

    /**
     * Open or close a competency's children from its card on the map.
     *
     * @param {number} id The competency.
     * @returns {void}
     */
    const toggleOnMap = useCallback((id: number): void => toggle(id, true), [toggle]);

    /**
     * Load a parent's next page of children, once at a time.
     *
     * @param {number} parent The parent.
     * @returns {Promise} Resolves with the first competency the page brought, or null.
     */
    const nextPage = useCallback((parent: number): Promise<number | null> => {
        if (paging.current.has(parent)) {
            return Promise.resolve(null);
        }
        paging.current.add(parent);
        const before = treeRef.current.branches[parent]?.ids.length ?? 0;
        return track('children', loadPage(parent, true))
            .then(() => treeRef.current.branches[parent]?.ids[before] ?? null)
            .finally(() => paging.current.delete(parent));
    }, [loadPage]);

    /**
     * Load a parent's next page of children.
     *
     * @param {number} parent The parent.
     * @returns {void}
     */
    const loadMore = useCallback((parent: number): void => {
        nextPage(parent).catch(notifyError);
    }, [nextPage]);

    /**
     * Say something to assistive technology through the canvas's live region.
     *
     * @param {string} message The message.
     * @returns {void}
     */
    const announce = (message: string): void => setAnnouncement(message);

    /**
     * Leave the move mode.
     *
     * @param {boolean} cancelled Whether to say it was cancelled.
     * @returns {void}
     */
    const endMove = useCallback((cancelled: boolean): void => {
        movingRef.current = null;
        setMoving(null);
        if (cancelled) {
            setAnnouncement(labels.hubv2_movecancelled);
        }
    }, [labels]);

    /**
     * Read the framework's insights; only the newest request's answer is kept (insights.latest). The last answer
     * stays shown while a newer one loads, and a failure keeps it too, beside the message and its retry.
     *
     * @returns {Promise} Resolves once answered or failed, never rejects.
     */
    const loadInsights = useCallback((): Promise<void> => {
        const ticket = insightsAsk.begin();
        insightsStatus.current = 'loading';
        setInsights((current) => ({status: 'loading', index: current.index}));
        return track('insights', readInsights(framework.id)).then((answer) => {
            if (!insightsAsk.current(ticket)) {
                return undefined;
            }
            const index = indexInsights(answer);
            indexRef.current = index;
            insightsStatus.current = 'ready';
            setInsights({status: 'ready', index});
            // The positions of the findings changed with the answer.
            findingAtRef.current = null;
            setFindingAt(null);
            return undefined;
        }, () => {
            if (insightsAsk.current(ticket)) {
                insightsStatus.current = 'failed';
                setInsights((current) => ({status: 'failed', index: current.index}));
            }
            return undefined;
        });
    }, [framework.id, insightsAsk]);

    /**
     * Read the insights unless they are read or on their way: a lens or the checker asked for them.
     *
     * @returns {Promise} Resolves once there is an answer (or a failure).
     */
    const ensureInsights = useCallback((): Promise<void> => {
        const status = insightsStatus.current;
        return status === 'idle' || status === 'failed' ? loadInsights() : Promise.resolve();
    }, [loadInsights]);

    /**
     * An action of the registry is done: read the insights again when the registry says it can change them
     * (commands.ts `changes`) and they have been read at all.
     *
     * @param {string} id The action.
     * @returns {void}
     */
    const settled = useCallback((id: ActionId): void => {
        if (specOf(id).changes && insightsStatus.current !== 'idle') {
            loadInsights().catch(notifyError);
        }
    }, [loadInsights]);

    /**
     * Ask for the names of competencies the panel's lists show and the tree has not loaded, a batch per request.
     *
     * @param {Array} ids The competencies shown.
     * @returns {void}
     */
    const need = useCallback((ids: number[]): void => {
        const missing = ids.filter((id) => id > 0 && !treeRef.current.nodes[id] && !naming.current.has(id)
            && namedPaths.current[id] === undefined);
        for (let start = 0; start < missing.length; start += NAME_BATCH) {
            const batch = missing.slice(start, start + NAME_BATCH);
            batch.forEach((id) => naming.current.add(id));
            track('names', getNodes(batch)).then((answers) => {
                const found: Record<number, string> = {};
                answers.forEach((answer, position) => {
                    const id = batch[position];
                    // A competency gone meanwhile is named by a dash until the insights are read again.
                    found[id] = answer.found && answer.node ? answer.node.shortname : '-';
                    namedPaths.current[id] = answer.pathids ?? [];
                });
                setNames((current) => ({...current, ...found}));
                return null;
            }).finally(() => batch.forEach((id) => naming.current.delete(id))).catch(notifyError);
        }
    }, []);

    /**
     * Mark the items a change being confirmed touches, unless the same are marked already.
     *
     * @param {Set} next The items, NO_IMPACT for none.
     * @returns {void}
     */
    const setImpact = useCallback((next: Set<number>): void => {
        setImpactState((current) => (sameImpact(current, next) ? current : next));
    }, []);

    /**
     * Mark the parents "Move to" would change while one is chosen in it; stable, as the dialogue's effect needs.
     *
     * @param {number|null} target The parent chosen, ROOT for the top level, null for none yet.
     * @returns {void}
     */
    const onMoveTarget = useCallback((target: number | null): void => {
        const source = moveDialogRef.current !== null ? treeRef.current.nodes[moveDialogRef.current] : undefined;
        setImpact(source && target !== null ? moveImpact(source.parentid, target) : NO_IMPACT);
    }, [setImpact]);

    /**
     * Pan the map, unless a node is already wholly visible and clear of the overview map and the
     * controls (keynav.panTarget); without animation when the viewer asks for reduced motion.
     *
     * @param {string} key The node's key.
     * @returns {void}
     */
    const panTo = (key: string): void => {
        const position = graphRef.current?.positions[key];
        const canvas = measureCanvas();
        if (!position || !canvas) {
            return;
        }
        const target = panTarget({...position, ...CARD}, flow.getViewport(), canvas.view, canvas.obstacles);
        if (target) {
            flow.setCenter(target.x, target.y, {zoom: flow.getZoom(), duration: motionDuration(200)}).catch(() => null);
        }
    };

    /**
     * The map's size and its overlays (the overview map, the controls), in pixels from its top left corner.
     *
     * @returns {object|null} The view's size and the overlays' boxes, or null while the map is not drawn.
     */
    const measureCanvas = (): {view: {width: number, height: number}, obstacles: Rect[]} | null => {
        const element = canvasRef.current?.querySelector<HTMLElement>('.react-flow');
        if (!element) {
            return null;
        }
        const box = element.getBoundingClientRect();
        const obstacles = [...element.querySelectorAll<HTMLElement>(OVERLAYS)].map((overlay) => {
            const rect = overlay.getBoundingClientRect();
            return {x: rect.left - box.left, y: rect.top - box.top, width: rect.width, height: rect.height};
        });
        return {view: {width: box.width, height: box.height}, obstacles};
    };

    const order = useMemo(() => navOrder(tree), [tree]);
    const rows = useMemo(() => outlineRows(tree, framework.shortname), [tree, framework.shortname]);
    const rowsRef = useRef<OutlineRow[]>(rows);
    rowsRef.current = rows;

    const mapStop = useRoving({
        order,
        tree,
        selected,
        container: canvasRef,
        item: NODE_SELECTOR,
        /**
         * The node an element draws.
         *
         * @param {HTMLElement} element The node wrapper.
         * @returns {string|undefined} Its key.
         */
        keyOf: (element) => element.dataset.id,
        /**
         * A node's element on the map.
         *
         * @param {string} key The node.
         * @returns {HTMLElement|null} Its wrapper, when it is drawn.
         */
        find: (key) => canvasRef.current?.querySelector<HTMLElement>(`${NODE_SELECTOR}[data-id="${key}"]`) ?? null,
        /**
         * Bring a node into view before it takes the focus.
         *
         * @param {string} key The node.
         * @param {boolean} pan Whether the viewport may move.
         * @returns {void}
         */
        beforeFocus: (key, pan) => {
            if (pan) {
                panTo(key);
            }
        },
    });

    const outlineStop = useRoving({
        order,
        tree,
        selected,
        container: listRef,
        item: ROW_SELECTOR,
        /**
         * The item a row draws.
         *
         * @param {HTMLElement} element The row.
         * @returns {string|undefined} Its key.
         */
        keyOf: (element) => element.dataset.key,
        /**
         * An item's row in the outline.
         *
         * @param {string} key The item.
         * @returns {HTMLElement|null} Its row, when it is drawn.
         */
        find: (key) => listRef.current?.querySelector<HTMLElement>(`[data-key="${key}"]`) ?? null,
    });

    /**
     * Focus an item in the pane that takes the focus after a command: the outline when it is drawn,
     * the real tree, else the map.
     *
     * @param {string} key The item's key.
     * @param {boolean} pan Whether the map pans to it (not when the caller has just centred on it).
     * @returns {void}
     */
    const focusItem = (key: string, pan: boolean = true): void => {
        if (panesRef.current.outline) {
            outlineStop.focusKey(key);
        } else {
            mapStop.focusKey(key, pan);
        }
    };

    /**
     * A parent as it is now: read again, since whether it has a rule is what a move's confirmation says.
     *
     * @param {number} id The parent, ROOT for the top level.
     * @returns {Promise} Resolves with the parent, or null for the top level or one gone.
     */
    const freshParent = useCallback(async(id: number): Promise<MoveParent | null> => {
        if (id === ROOT) {
            return null;
        }
        const answer = await getNode(id);
        return answer.found && answer.node ? answer.node : null;
    }, []);

    /**
     * Give a competency a new parent: the one move both ways of choosing the parent end in.
     *
     * Refused before any request when the target is the competency or one of its descendants, or its
     * parent already. Picking on the map asks for the confirmation, which names the rules core resets,
     * read afresh; "Move to" is its own confirmation and says the same sentences (move.ruleNotes) before
     * its commit button.
     *
     * @param {object} source The competency.
     * @param {number} target The new parent, ROOT for the top level.
     * @param {boolean} confirm Whether to ask first.
     * @returns {void}
     */
    const moveUnder = (source: StructureNode, target: number, confirm: boolean): void => {
        if (target !== ROOT && isWithin(treeRef.current, target, source.id)) {
            announce(labels.hubv2_moveinvalid);
            return;
        }
        if (target === source.parentid) {
            return;
        }
        (async() => {
            if (confirm) {
                const parents = Promise.all([freshParent(source.parentid), freshParent(target)]);
                const [oldparent, newparent] = await track('move', parents);
                // A new parent gone meanwhile is named as the canvas knew it; core refuses the move then.
                const known = target === ROOT ? null : {shortname: treeRef.current.nodes[target]?.shortname ?? '', ruletype: null};
                const question = moveBody(labels, source.shortname, oldparent, newparent ?? known);
                const notification = await load('core/notification');
                setImpact(moveImpact(source.parentid, target));
                const confirmed = await notification
                    .saveCancelPromise(labels.hubv2_movetitle, question, labels.hubv2_move)
                    .then(() => true, () => false)
                    .finally(() => setImpact(NO_IMPACT));
                if (!confirmed) {
                    return;
                }
            }
            endMove(false);
            await track('move', (async() => {
                await setParent(source.id, target);
                const answer = await getNode(source.id);
                if (answer.found) {
                    await relocate(source.id, source.parentid, answer);
                }
                announce(fill(labels.hubv2_moved, source.shortname));
                settled('moveto');
            })());
        })().catch(notifyError);
    };

    /**
     * Give the competency being moved its new parent, once confirmed.
     *
     * @param {number} target The new parent, ROOT for the top level.
     * @returns {void}
     */
    const pickParent = (target: number): void => {
        const source = movingRef.current !== null ? treeRef.current.nodes[movingRef.current] : undefined;
        if (!source) {
            endMove(false);
            return;
        }
        if (target !== ROOT && isWithin(treeRef.current, target, source.id)) {
            announce(labels.hubv2_moveinvalid);
            return;
        }
        if (target === source.parentid) {
            endMove(true);
            return;
        }
        moveUnder(source, target, true);
    };

    /**
     * Act on an item picked by click, Enter or Space in either pane: select it, or in the move mode make
     * it the new parent. A selection made in the outline brings its node into the map's view.
     *
     * @param {number} id The competency, ROOT for the framework.
     * @param {string} from The pane it was picked in.
     * @returns {void}
     */
    const activate = (id: number, from: 'outline' | 'map'): void => {
        const action = activation(movingRef.current, id);
        if (action.type === 'pick') {
            pickParent(action.parent);
            return;
        }
        choose(action.id);
        if (from === 'outline' && panesRef.current.map) {
            panTo(action.id === null ? FRAMEWORK_ID : competencyKey(action.id));
        }
    };

    /**
     * React to what React Flow reports: a node's size, and a selection by click, which in the move
     * mode picks the new parent instead.
     *
     * @param {object} changes The changes, a NodeChangeset.
     * @returns {void}
     */
    const onNodesChange = (changes: NodeChangeset<CanvasNode>): void => {
        const sizes: Record<string, {width: number, height: number}> = {};
        changes.getByType('dimensions').forEach((change) => {
            if (change.dimensions) {
                sizes[change.id] = change.dimensions;
            }
        });
        if (Object.keys(sizes).length > 0) {
            setMeasured((current) => ({...current, ...sizes}));
        }
        const selections = changes.getByType('select');
        const picked = selections.find((change) => change.selected);
        if (picked) {
            const id = picked.id === FRAMEWORK_ID ? ROOT : competencyOf(picked.id);
            if (id === null) {
                return;
            }
            activate(id, 'map');
            return;
        }
        const unselected = selections.some((change) => !change.selected);
        if (unselected && movingRef.current === null) {
            choose(null);
        }
    };

    /**
     * Open the competency form: an edit of a competency, or a new child (of the framework with null).
     *
     * @param {object|null} node The competency to edit, or null.
     * @param {object|null} parent The new competency's parent, or null for the top level.
     * @returns {void}
     */
    const openForm = (node: StructureNode | null, parent: StructureNode | null): void => {
        const args = node
            ? {competencyframeworkid: framework.id, id: node.id, parentid: node.parentid}
            : {competencyframeworkid: framework.id, id: 0, parentid: parent ? parent.id : ROOT};
        const title = labels[node ? 'tool_lp:editcompetency' : 'tool_lp:addcompetency'];
        (async() => {
            const ModalForm = await load('core_form/modalform');
            const form = new ModalForm({formClass: FORM_CLASS, args, modalConfig: {title}});
            form.addEventListener(form.events.FORM_SUBMITTED, (event) => {
                if (node) {
                    track('refresh', refreshNode(node.id)).then(() => settled('edit')).catch(notifyError);
                    return;
                }
                const newid = Number((event as CustomEvent).detail?.competencyid) || 0;
                const parentid = parent ? parent.id : ROOT;
                track('refresh', (async() => {
                    await refreshParent(parentid);
                    if (hasBranch(treeRef.current, parentid)) {
                        await reloadBranch(parentid);
                    }
                    setCount(await countCompetencies(framework.id));
                    const answer = newid ? await getNode(newid) : {found: false};
                    if (answer.found) {
                        await reveal(newid, answer.pathids ?? []);
                    }
                    settled('addchild');
                })()).catch(notifyError);
            });
            await form.show();
        })().catch(notifyError);
    };

    /**
     * Store a rule chosen in the hub's rule dialogue, then refresh the competency.
     *
     * @param {object} node The competency.
     * @param {object} config The rule.
     * @returns {Promise} Resolves once stored and refreshed.
     */
    const storeRule = (node: StructureNode, config: RuleConfig): Promise<void> =>
        track('refresh', updateRule(node.id, config).then(() => refreshNode(node.id)))
            .then(() => {
                announce(labels['core:changessaved']);
                settled('rule');
                return undefined;
            });

    /**
     * Open the hub's rule dialogue over the competency's children, then store what it returns.
     *
     * @param {object} node The competency.
     * @returns {void}
     */
    const editRule = (node: StructureNode): void => {
        // Only the children's fetch is tracked: the dialogue then waits on the viewer.
        (async() => {
            const children = await track('rule', allChildren(framework.id, node.id, pagesize));
            const module = await load('local_dimensions/central/rule_config');
            const config = await module.show({
                id: node.id,
                ruletype: node.ruletype ?? '',
                ruleoutcome: node.ruleoutcome,
                ruleconfig: node.ruleconfig ?? '',
            }, children, props.rulesmodules);
            if (config) {
                await storeRule(node, config);
            }
        })().catch(notifyError);
    };

    /**
     * Open the hub's courses and activities dialogue; its counts are refreshed when it closes.
     *
     * @param {object} node The competency.
     * @returns {void}
     */
    const openLinks = (node: StructureNode): void => {
        /**
         * Refresh the competency's counts.
         *
         * @returns {void}
         */
        const onClose = (): void => {
            track('refresh', refreshNode(node.id)).then(() => settled('links')).catch(notifyError);
        };
        load('local_dimensions/central/competency_links')
            .then((module) => module.open({
                competencyid: node.id,
                competencyname: node.shortname,
                courseoutcomes: props.courseoutcomes,
                moduleoutcomes: props.moduleoutcomes,
                onClose,
            }))
            .catch(notifyError);
    };

    /**
     * Open the hub's related competencies dialogue. The relations are read again when it closes,
     * through the modal-hidden listener below.
     *
     * @param {object} node The competency.
     * @returns {void}
     */
    const openRelated = (node: StructureNode): void => {
        relating.current = node.id;
        load('local_dimensions/central/related_competencies')
            .then((module) => module.open({competencyid: node.id, competencyname: node.shortname, frameworkid: framework.id}))
            .catch(notifyError);
    };

    /**
     * Enter the move mode: the next node picked becomes the new parent.
     *
     * @param {object} node The competency to move.
     * @returns {void}
     */
    const startMove = (node: StructureNode): void => {
        movingRef.current = node.id;
        setMoving(node.id);
        announce(fill(labels.hubv2_movepick, node.shortname));
        focusItem(competencyKey(node.id));
    };

    /**
     * Move a competency one place among its siblings, reload them and say where it now stands.
     *
     * The branch is reloaded as far as the competency's new place: moved down past the loaded siblings,
     * it would otherwise drop out of the tree, and the focus with it. Its row and node keep their keys,
     * so a competency moved from the keyboard keeps the focus.
     *
     * @param {object} node The competency.
     * @param {boolean} up Up when true.
     * @returns {void}
     */
    const stepMove = (node: StructureNode, up: boolean): void => {
        track('refresh', (async() => {
            await moveOne(node.id, up);
            const index = treeRef.current.branches[node.parentid]?.ids.indexOf(node.id) ?? -1;
            await reloadBranch(node.parentid, index + (up ? 1 : 2));
            const branch = treeRef.current.branches[node.parentid];
            const values = {
                name: node.shortname,
                position: String((branch?.ids.indexOf(node.id) ?? -1) + 1),
                total: String(branch?.total ?? 0),
            };
            announce(fillObject(up ? labels.hubv2_reordered_up : labels.hubv2_reordered_down, values));
        })()).catch(notifyError);
    };

    /**
     * Delete a competency: ask the server first, say why when core would refuse it, and otherwise name
     * what will be destroyed before asking for the confirmation.
     *
     * @param {object} node The competency.
     * @returns {void}
     */
    const remove = (node: StructureNode): void => {
        (async() => {
            const notification = await load('core/notification');
            // Set before any dialogue opens: the dialogue reports its closing before its promise settles, and
            // that closing would otherwise ask for the lists of a competency that is going away.
            removing.current = node.id;
            /**
             * Tell why core would refuse the deletion.
             *
             * @param {object} check The server's answer.
             * @returns {Promise} Resolves when the dialogue closes.
             */
            const showBlocked = async(check: DeleteCheck): Promise<void> => {
                removing.current = null;
                await notification.alert(labels.hubv2_delete_blocked_title, blockedBody(labels, node.shortname, check));
            };
            const check = await track('refresh', deleteCheck(node.id)).catch((error: unknown) => {
                removing.current = null;
                throw error;
            });
            if (!check.candelete) {
                await showBlocked(check);
                return;
            }
            setImpact(deleteImpact(treeRef.current, node.id));
            const confirmed = await notification
                .deleteCancelPromise(labels.hubv2_delete, confirmBody(labels, node.shortname, check))
                .then(() => true, () => false)
                .finally(() => setImpact(NO_IMPACT));
            if (!confirmed) {
                removing.current = null;
                return;
            }
            await track('refresh', (async() => {
                const done = await deleteCompetency(node.id).catch((error: unknown) => {
                    removing.current = null;
                    throw error;
                });
                if (done === false) {
                    // Something changed since the check: ask again for the reasons.
                    const again = await deleteCheck(node.id).catch(() => null);
                    if (again && !again.candelete) {
                        await showBlocked(again);
                    } else {
                        removing.current = null;
                        await notification.alert(
                            null,
                            fill(labels['tool_lp:competencycannotbedeleted'], escapeHtml(node.shortname))
                        );
                    }
                    return;
                }
                commit((current) => removeNode(current, node.id));
                choose(null);
                await reloadBranch(node.parentid);
                await refreshParent(node.parentid);
                setCount(await countCompetencies(framework.id));
                settled('delete');
            })());
        })().catch(notifyError);
    };

    /**
     * Reveal, select and centre a competency named by a search hit or a relation.
     *
     * @param {object} item The competency and its path.
     * @returns {void}
     */
    const revealItem = (item: PathItem): void => {
        track('reveal', reveal(item.id, item.pathids)).catch(notifyError);
    };

    /**
     * Reveal, select and centre a competency named by id only (a lens's list, a finding): its path is the tree's
     * when it is loaded, the one its name came with, or asked of the server; the framework is selected for ROOT.
     *
     * @param {number} id The competency, ROOT for the framework.
     * @returns {Promise} Resolves once revealed (or found gone).
     */
    const revealId = (id: number): Promise<void> => {
        if (id === ROOT) {
            choose(null);
            focusItem(FRAMEWORK_ID);
            return Promise.resolve();
        }
        let path: Promise<number[] | null>;
        if (treeRef.current.nodes[id]) {
            path = Promise.resolve(ancestors(treeRef.current, id));
        } else if (namedPaths.current[id] !== undefined) {
            path = Promise.resolve(namedPaths.current[id]);
        } else {
            path = getNode(id).then((answer) => (answer.found ? answer.pathids ?? [] : null));
        }
        return track('reveal', path.then((pathids) => (pathids === null ? undefined : reveal(id, pathids))));
    };

    /**
     * Reveal a competency from a lens's list.
     *
     * @param {number} id The competency.
     * @returns {void}
     */
    const revealListed = (id: number): void => {
        revealId(id).catch(notifyError);
    };

    /**
     * Choose a finding: mark it in the list, reveal and select its item in both panes with the checker kept open,
     * and say which it is ("Finding 2 of 6: No description, Domain A").
     *
     * @param {number} position The finding's place in the list.
     * @param {Array} ordered The findings, in the list's order.
     * @returns {Promise} Resolves once revealed.
     */
    const revealFinding = async(position: number, ordered: ReturnType<typeof orderFindings>): Promise<void> => {
        const check = ordered[position];
        if (!check) {
            return;
        }
        findingAtRef.current = position;
        setFindingAt(position);
        holdChecker.current = true;
        try {
            await revealId(check.competencyid);
        } finally {
            holdChecker.current = false;
        }
        const name = check.competencyid === ROOT
            ? framework.shortname
            : treeRef.current.nodes[check.competencyid]?.shortname ?? '';
        announce(fillObject(labels.hubv2_checker_position, {
            position: String(position + 1),
            total: String(ordered.length),
            title: codeTitle(labels, check.code),
            name,
        }));
    };

    /**
     * Open the checker in the panel, reading the insights when they are not read yet, its heading focused.
     *
     * @returns {void}
     */
    const openChecker = (): void => {
        setMenu(null);
        setChecker(true);
        ensureInsights().catch(notifyError);
        window.requestAnimationFrame(() => checkerHeading.current?.focus());
    };

    /**
     * Close the checker, the focus back on its button.
     *
     * @returns {void}
     */
    const closeChecker = (): void => {
        setChecker(false);
        checkButton.current?.focus();
    };

    /**
     * Go to the next finding, or the previous one, wrapping at both ends (F8, Shift+F8, the palette): the
     * checker opens, the insights are read first when they are not.
     *
     * @param {boolean} forward Forward when true.
     * @returns {void}
     */
    const stepToFinding = (forward: boolean): void => {
        track('finding', (async() => {
            setMenu(null);
            setChecker(true);
            if (!indexRef.current) {
                await ensureInsights();
            }
            const index = indexRef.current;
            if (!index) {
                return;
            }
            const ordered = orderFindings(index.answer.checks);
            const next = stepFinding(ordered.length, findingAtRef.current, forward);
            if (next === null) {
                announce(labels.hubv2_checker_none);
                return;
            }
            await revealFinding(next, ordered);
        })()).catch(notifyError);
    };

    /**
     * Show a lens, and keep it where the next visit reads it: the address (lens=), the pane's data (a reload of
     * the pane, after a framework action, opens with it) and the saved preference.
     *
     * @param {string} next The lens.
     * @returns {void}
     */
    const changeLens = (next: Lens): void => {
        setLens(next);
        lensRef.current = next;
        rememberLens(next);
        const pane = rootRef.current?.closest<HTMLElement>('[data-tab-content]');
        if (pane) {
            pane.dataset.lens = next;
        }
        load('local_dimensions/central/preferences')
            .then((preferences) => {
                preferences.saveDisplay({structure: {lens: next}});
                return null;
            })
            .catch(notifyError);
        if (next !== 'none') {
            ensureInsights().catch(notifyError);
        }
    };

    /**
     * Run an action of the registry on a competency named by a list (a rule block, a finding), revealing it first
     * when the tree has not loaded it: the registry decides on the loaded competency.
     *
     * @param {string} action The action.
     * @param {number} id The competency.
     * @returns {void}
     */
    const runOn = (action: ActionId, id: number): void => {
        /**
         * Run the action, once the competency is in the tree.
         *
         * @returns {void}
         */
        const run = (): void => {
            runAction(action, id);
        };
        if (treeRef.current.nodes[id]) {
            run();
            return;
        }
        holdChecker.current = true;
        revealId(id).then(run).finally(() => {
            holdChecker.current = false;
        }).catch(notifyError);
    };

    /**
     * Load the next page of a parent from the keyboard, then focus the first competency it brought.
     *
     * @param {number} parent The parent.
     * @param {object} stop The pane's Tab stop.
     * @returns {void}
     */
    const loadMoreAndFocus = (parent: number, stop: Roving): void => {
        nextPage(parent).then((first) => {
            if (first !== null) {
                stop.focusKey(competencyKey(first));
            }
            return null;
        }).catch(notifyError);
    };

    /**
     * Move the outline's focus to the next visible name that starts with what was typed.
     *
     * @param {string} character The character typed.
     * @param {number} time The key's time stamp, in milliseconds.
     * @returns {boolean} Whether a name matched.
     */
    const typeTo = (character: string, time: number): boolean => {
        const previous = typed.current;
        const text = time - previous.at > TYPE_AHEAD_PAUSE ? character : previous.text + character;
        typed.current = {text, at: time};
        const key = typeAhead(rowsRef.current, outlineStop.activeRef.current, text);
        if (key !== null) {
            outlineStop.focusKey(key);
        }
        return key !== null;
    };

    /**
     * Open every branch of the visible tree, level by level, up to EXPAND_ALL_BOUND competencies.
     *
     * @returns {void}
     */
    const expandAll = (): void => {
        const run = ++expandRun.current;
        setExpanding(true);
        setNotice('');
        track('expandall', (async() => {
            let capped = false;
            // Each round opens at least one parent, so the walk ends; the bound on rounds is a guard.
            for (let round = 0; round < 100 && run === expandRun.current; round++) {
                const step = expandStep(treeRef.current, EXPAND_ALL_BOUND, pagesize);
                capped = step.capped;
                for (let index = 0; index < step.load.length && run === expandRun.current; index += EXPAND_ALL_PARALLEL) {
                    const batch = step.load.slice(index, index + EXPAND_ALL_PARALLEL)
                        .filter((id) => !hasBranch(treeRef.current, id));
                    await Promise.all(batch.map((id) => loadPage(id, false)));
                }
                if (run !== expandRun.current) {
                    return;
                }
                const ids = [...step.open, ...step.load];
                commit((current) => ids.reduce((next, id) => setExpanded(next, id, true), current));
                if (capped || ids.length === 0) {
                    break;
                }
            }
            if (capped && run === expandRun.current) {
                const message = fill(labels.hubv2_expandall_capped, String(EXPAND_ALL_BOUND));
                setNotice(message);
                announce(message);
            }
        })()).finally(() => {
            if (run === expandRun.current) {
                setExpanding(false);
            }
        }).catch(notifyError);
    };

    /**
     * Close every branch, ending an "Expand all" still running.
     *
     * @returns {void}
     */
    const collapseEverything = (): void => {
        expandRun.current++;
        setExpanding(false);
        setNotice('');
        commit(collapseAll);
    };

    /**
     * What the registry's decision reads for one competency, from the latest store (commands.actionState).
     *
     * @param {number|null} id The competency, or null for the framework.
     * @returns {object} The context.
     */
    const contextFor = (id: number | null): ActionContext => {
        const current = treeRef.current;
        const target = id !== null && id !== ROOT ? current.nodes[id] ?? null : null;
        const siblings = target ? current.branches[target.parentid] : undefined;
        const index = target && siblings ? siblings.ids.indexOf(target.id) : -1;
        return {
            node: target,
            frameworkcanmanage: framework.canmanage,
            moving: movingRef.current !== null ? current.nodes[movingRef.current] ?? null : null,
            first: index <= 0,
            last: !siblings || index < 0 || (index === siblings.ids.length - 1 && siblings.ids.length >= siblings.total),
            layout: layoutRef.current,
            empty: countRef.current === 0,
            expanding: expandingRef.current,
            frameworkvisible: framework.visible,
            cancreate: props.structures.cancreate,
            lens: lensRef.current,
            usagelevel: indexRef.current ? indexRef.current.answer.usagelevel : null,
            findings: indexRef.current ? indexRef.current.answer.checks.length : null,
        };
    };

    /**
     * Switch the workspace's view through the tab's own switch, so the tab, the address and the saved
     * preference follow (amd/src/central/structure.js); without the switch, the workspace alone.
     *
     * @param {string} next The layout.
     * @returns {void}
     */
    const switchLayout = (next: Layout): void => {
        const region = rootRef.current?.closest('[data-region="structure"]');
        const button = region?.querySelector<HTMLButtonElement>(`[data-action="switch-view"][data-view="${next}"]`);
        if (button) {
            button.click();
        } else {
            setLayout(next);
        }
    };

    /**
     * Show the framework's summary: the panel's content with nothing selected, its heading focused.
     *
     * @returns {void}
     */
    const showSummary = (): void => {
        choose(null);
        window.requestAnimationFrame(() => summaryRef.current?.focus());
    };

    /**
     * Make a competency's row drawn (its ancestors loaded and opened), for the inline editor.
     *
     * @param {number} id The competency.
     * @returns {Promise} Resolves once drawn.
     */
    const reach = (id: number): Promise<void> => ensureVisible(id, ancestors(treeRef.current, id));

    /**
     * Open the outline's inline editor for a new competency: a sibling right after another, or the
     * first child of a parent, whose branch is opened first.
     *
     * @param {number} parent The new competency's parent, ROOT for the top level.
     * @param {number|null} after The sibling it follows, or null for a first child.
     * @returns {void}
     */
    const startCreate = (parent: number, after: number | null): void => {
        track('editor', (async() => {
            if (after !== null) {
                await reach(after);
            } else if (parent !== ROOT) {
                await reach(parent);
                if (treeRef.current.nodes[parent]?.haschildren && !hasBranch(treeRef.current, parent)) {
                    await loadPage(parent, false);
                }
                commit((current) => setExpanded(current, parent, true));
            }
            editorSerial.current++;
            setEditor({mode: 'create', parent, after, serial: editorSerial.current});
        })()).catch(notifyError);
    };

    /**
     * Open the outline's inline editor on a competency's name.
     *
     * @param {number} id The competency.
     * @returns {void}
     */
    const startRename = (id: number): void => {
        track('editor', reach(id).then(() => setEditor({mode: 'rename', id}))).catch(notifyError);
    };

    /**
     * Close the inline editor and give the focus back to its row: the competency renamed, the last one
     * created, or the row the editor was opened from.
     *
     * @returns {void}
     */
    const closeEditor = (): void => {
        const current = editorRef.current;
        setEditor(null);
        if (!current) {
            return;
        }
        if (current.mode === 'rename') {
            outlineStop.focusKey(competencyKey(current.id));
        } else {
            outlineStop.focusKey(current.after !== null ? competencyKey(current.after) : parentKey(current.parent));
        }
    };

    /**
     * Create the competency the inline editor holds, place it right after its anchor, and open the
     * editor again for the next sibling. Core appends a new competency after its siblings; the moves up
     * that bring it under its anchor go in one request.
     *
     * @param {number} parent The parent, ROOT for the top level.
     * @param {number|null} after The sibling it follows, or null for a first child.
     * @param {string} name The name.
     * @param {string} idnumber The ID number.
     * @returns {Promise} Resolves with the server's refusal in words, or null once created.
     */
    const saveCreate = async(parent: number, after: number | null, name: string, idnumber: string): Promise<string | null> => {
        try {
            const created = await track('editor', (async() => {
                const id = await createCompetency(framework.id, parent, name, idnumber);
                const total = (await browse(framework.id, parent, 0, 1)).total;
                const anchor = after === null ? -1 : treeRef.current.branches[parent]?.ids.indexOf(after) ?? -1;
                const place = after === null ? 0 : anchor + 1;
                if (after === null || anchor >= 0) {
                    await moveUpTimes(id, total - 1 - place);
                }
                await reloadBranch(parent, place + 1);
                await refreshParent(parent);
                commit((current) => setExpanded(current, parent, true));
                setCount(await countCompetencies(framework.id));
                announce(fill(labels.hubv2_editor_created, name));
                settled('quicksibling');
                return id;
            })());
            editorSerial.current++;
            setEditor({mode: 'create', parent, after: created, serial: editorSerial.current});
            return null;
        } catch (error) {
            return messageOf(error);
        }
    };

    /**
     * Rename the competency the inline editor holds, then close the editor.
     *
     * @param {number} id The competency.
     * @param {string} name The new name.
     * @returns {Promise} Resolves with the server's refusal in words, or null once stored.
     */
    const saveRename = async(id: number, name: string): Promise<string | null> => {
        try {
            await track('editor', renameCompetency(id, name).then(() => refreshNode(id)));
            closeEditor();
            announce(labels['core:changessaved']);
            return null;
        } catch (error) {
            return messageOf(error);
        }
    };

    /**
     * Run an action on one competency.
     *
     * @param {string} id The action.
     * @param {object} target The competency.
     * @returns {void}
     */
    const runNodeAction = (id: ActionId, target: StructureNode): void => {
        switch (id) {
            case 'edit':
                openForm(target, null);
                break;
            case 'rename':
                startRename(target.id);
                break;
            case 'addchild':
                openForm(null, target);
                break;
            case 'quicksibling':
                startCreate(target.parentid, target.id);
                break;
            case 'quickchild':
                startCreate(target.id, null);
                break;
            case 'moveto':
                setMoveDialog(target.id);
                break;
            case 'movepick':
                startMove(target);
                break;
            case 'moveup':
            case 'movedown':
                stepMove(target, id === 'moveup');
                break;
            case 'rule':
                editRule(target);
                break;
            case 'links':
                openLinks(target);
                break;
            case 'relate':
                openRelated(target);
                break;
            case 'delete':
                remove(target);
                break;
            default:
                break;
        }
    };

    /**
     * Run an action on the framework (the root's) or on the tab's structures, through the Structures tab's own
     * functions (amd/src/central/frameworks.js), then show the result through the tab (amd/src/central/structure.js
     * showFramework(), which reloads its pane).
     *
     * An edit, the scale, the visibility and a duplicate show the same framework again, keeping the opened
     * branches and the selection (carry.ts); a duplicate says where the copy is, in the framework select, as the
     * Structures tab lists it. A delete shows the next framework the select lists (or the empty state), a new or
     * imported framework itself. Every write is checked again by its form or service.
     *
     * @param {string} id The action.
     * @returns {void}
     */
    const runFrameworkAction = (id: ActionId): void => {
        const pane = rootRef.current?.closest<HTMLElement>('[data-tab-content]') ?? null;
        track('framework', (async() => {
            const [frameworks, tab] = await Promise.all([
                load('local_dimensions/central/frameworks'),
                load('local_dimensions/central/structure'),
            ]);
            /**
             * Show a framework in the tab, keeping what the viewer opened when it is this one.
             *
             * @param {number} frameworkid The framework; 0 lets the server choose.
             * @param {boolean} keep Whether this framework's branches and selection are kept.
             * @returns {void}
             */
            const show = (frameworkid: number, keep: boolean): void => {
                if (!pane) {
                    return;
                }
                if (keep) {
                    hold(carryOf(framework.id, treeRef.current, selectedRef.current));
                }
                tab.showFramework(pane, frameworkid).catch(notifyError);
            };
            /**
             * Show this framework again, as the server now has it.
             *
             * @returns {void}
             */
            const again = (): void => show(framework.id, true);
            const canscalespage = props.structures.canscalespage;
            switch (id) {
                case 'fwedit':
                case 'fwscale':
                    await frameworks.editFramework(framework.id, {onSaved: again, canscalespage, scale: id === 'fwscale'});
                    break;
                case 'fwhide':
                case 'fwshow':
                    await frameworks.setVisibility(framework.id, id === 'fwshow');
                    again();
                    break;
                case 'fwduplicate': {
                    await frameworks.duplicate(framework.id);
                    const toast = await load('local_dimensions/central/toast');
                    toast.add(labels.hubv2_fw_duplicated, {type: 'success'}).catch(notifyError);
                    again();
                    break;
                }
                case 'fwexport':
                    await frameworks.exportFramework(framework.id);
                    break;
                case 'fwdelete': {
                    const deleted = await frameworks.confirmDelete({
                        id: framework.id,
                        name: framework.shortname,
                        count: countRef.current,
                        deletable: framework.deletable,
                    });
                    if (deleted) {
                        show(tab.nextFramework(framework.id), false);
                    }
                    break;
                }
                case 'fwnew':
                    await frameworks.createFramework(props.structures.contextid, {
                        /**
                         * Shows the framework that was just created.
                         *
                         * @param {number} newid The new framework's id.
                         * @returns {void}
                         */
                        onSaved: (newid: number) => show(newid, false),
                        canscalespage,
                    });
                    break;
                case 'fwimport':
                    await frameworks.importFramework(props.structures.contextid, (newid: number) => show(newid, false));
                    break;
                default:
                    break;
            }
        })()).catch(notifyError);
    };

    /**
     * Run an action on the move in progress or on the workspace.
     *
     * @param {string} id The action.
     * @returns {void}
     */
    const runWorkspaceAction = (id: ActionId): void => {
        const view = VIEW_ACTIONS[id];
        if (view) {
            switchLayout(view);
            return;
        }
        const chosen = LENS_ACTIONS[id];
        if (chosen) {
            changeLens(chosen);
            return;
        }
        switch (id) {
            case 'check':
                openChecker();
                break;
            case 'nextfinding':
            case 'prevfinding':
                stepToFinding(id === 'nextfinding');
                break;
            case 'moveroot':
                pickParent(ROOT);
                break;
            case 'movecancel':
                endMove(true);
                break;
            case 'expandall':
                expandAll();
                break;
            case 'collapseall':
                collapseEverything();
                break;
            case 'addtop':
                openForm(null, null);
                break;
            case 'summary':
                showSummary();
                break;
            default:
                break;
        }
    };

    /**
     * Run an action of the registry on a competency (or on the workspace): the one implementation the
     * panel, the menus, the palette and the keys reach. The registry's decision is read again here, so
     * no place can run what the registry refuses.
     *
     * @param {string} id The action.
     * @param {number|null} targetid The competency, or null for the framework.
     * @returns {void}
     */
    const runAction = (id: ActionId, targetid: number | null): void => {
        const context = contextFor(targetid);
        if (actionState(id, context) !== 'enabled') {
            return;
        }
        const scope = specOf(id).scope;
        if (scope === 'node' && context.node) {
            runNodeAction(id, context.node);
        } else if (scope === 'framework' || scope === 'structures') {
            runFrameworkAction(id);
        } else {
            runWorkspaceAction(id);
        }
    };

    /**
     * The actions a menu offers: the competency's, or the framework's on the root (ROOT).
     *
     * @param {number} id The competency, or ROOT.
     * @returns {Array} The offered actions.
     */
    const menuActions = (id: number): ActionView[] =>
        (id === ROOT ? actionsFor(['framework'], contextFor(null)) : actionsFor(['node'], contextFor(id)));

    /**
     * Open a competency's menu under an element of a pane, when it has an action to offer.
     *
     * @param {number} id The competency.
     * @param {HTMLElement} anchor The element the menu hangs from.
     * @param {string} origin Where it was opened from: a pane, or the panel's button.
     * @returns {boolean} Whether it opened.
     */
    const openMenu = (id: number, anchor: HTMLElement, origin: Pane | 'panel'): boolean => {
        if (menuActions(id).length === 0) {
            return false;
        }
        setMenu((current) => (current && current.id === id && current.anchor === anchor ? null : {id, anchor, origin}));
        return true;
    };

    /**
     * Give the focus back after a layer closed (focusreturn.focusReturn): to the control that opened it,
     * or to an item, in the pane the decision names.
     *
     * @param {object} origin What opened the layer.
     * @param {HTMLElement|null} control The control that opened it, if one did.
     * @param {string} key The item's key.
     * @returns {void}
     */
    const giveFocusBack = (origin: LayerOrigin, control: HTMLElement | null, key: string): void => {
        const target = focusReturn(origin, lastPane.current, panesRef.current);
        if (target.kind === 'control' && control) {
            control.focus();
        } else if (target.kind === 'item' && target.pane === 'outline') {
            outlineStop.focusKey(key);
        } else if (target.kind === 'item') {
            mapStop.focusKey(key, false);
        }
    };

    /**
     * Close the open menu and give the focus back to what opened it: the item it belongs to (the row or
     * node that owns the pane's Tab stop, also when its "more" button opened it), or the panel's button.
     *
     * @param {boolean} outside Whether a press outside the menu closed it.
     * @returns {void}
     */
    const closeMenu = (outside: boolean = false): void => {
        const current = menu;
        if (!current) {
            return;
        }
        setMenu(null);
        const origin: LayerOrigin = current.origin === 'panel'
            ? {kind: 'control', connected: current.anchor.isConnected}
            : {kind: 'item', pane: current.origin};
        /**
         * Give the focus back to the item, or to the panel's button.
         *
         * @returns {void}
         */
        const back = (): void =>
            giveFocusBack(origin, current.anchor, current.id === ROOT ? FRAMEWORK_ID : competencyKey(current.id));
        if (!outside) {
            back();
            return;
        }
        // A press outside may land on a control that takes the focus; only a press on nothing (which leaves
        // the focus on the body) gives it back, once the press is over.
        document.addEventListener('pointerup', () => {
            window.setTimeout(() => {
                const active = document.activeElement;
                if (!active || active === document.body) {
                    back();
                }
            }, 0);
        }, {once: true, capture: true});
    };

    /**
     * Act on a command key pressed on the root (the framework's item) of a pane: Ctrl+Shift+Enter in the outline
     * opens the editor for a new first top-level competency; the menu key opens the framework's menu on it,
     * selecting the root first.
     *
     * @param {string} command The command.
     * @param {HTMLElement} element The focused item.
     * @param {string} from The pane.
     * @returns {boolean} Whether the key was used.
     */
    const rootCommand = (command: KeyCommand, element: HTMLElement, from: 'outline' | 'map'): boolean => {
        const allowed = framework.canmanage && movingRef.current === null && layoutRef.current !== 'map';
        if (command === 'quickchild' && from === 'outline' && allowed) {
            startCreate(ROOT, null);
            return true;
        }
        if (command === 'menu' && menuActions(ROOT).length > 0) {
            if (selectedRef.current !== null) {
                choose(null);
            }
            setMenu({id: ROOT, anchor: element, origin: from});
            return true;
        }
        return false;
    };

    /**
     * Act on a command key pressed on an item of a pane (commands.shortcutAction).
     *
     * The menu key selects the item and opens its menu on it; a reorder key at an end moves nothing and
     * says so; any other command runs when the registry offers it for the item. On the framework's row,
     * Ctrl+Shift+Enter opens the editor for a new first top-level competency.
     *
     * @param {string} command The command.
     * @param {string} key The focused item's key.
     * @param {HTMLElement} element The focused item.
     * @param {string} from The pane.
     * @returns {boolean} Whether the key was used.
     */
    const keyCommand = (command: KeyCommand, key: string, element: HTMLElement, from: 'outline' | 'map'): boolean => {
        if (key === FRAMEWORK_ID) {
            return rootCommand(command, element, from);
        }
        const id = competencyOf(key);
        if (id === null || command === 'palette') {
            return false;
        }
        if (command === 'menu') {
            if (actionsFor(['node'], contextFor(id)).length === 0) {
                return false;
            }
            if (selectedRef.current !== id) {
                choose(id);
            }
            setMenu({id, anchor: element, origin: from});
            return true;
        }
        const context = contextFor(id);
        const state = actionState(command, context);
        if ((command === 'moveup' || command === 'movedown') && state === 'disabled' && context.node) {
            announce(fill(command === 'moveup' ? labels.hubv2_reorder_first : labels.hubv2_reorder_last, context.node.shortname));
            return true;
        }
        if (state !== 'enabled') {
            return false;
        }
        runAction(command, id);
        return true;
    };

    /**
     * Ctrl+K (Cmd+K on a Mac) opens the palette while the focus is anywhere inside the workspace; outside
     * it the browser keeps its own shortcut. F8 and Shift+F8 go to the next and the previous finding, from
     * anywhere inside it too, unless a layer (a menu, the palette, a dialogue) has the keys.
     *
     * @param {object} event The key event.
     * @returns {void}
     */
    const onRootKeyDown = (event: ReactKeyboardEvent<HTMLDivElement>): void => {
        const command = shortcutAction(event, 'map');
        if (palette || moveDialog !== null) {
            return;
        }
        if (command === 'nextfinding' || command === 'prevfinding') {
            if (menu === null) {
                event.preventDefault();
                stepToFinding(command === 'nextfinding');
            }
            return;
        }
        if (command !== 'palette') {
            return;
        }
        event.preventDefault();
        setMenu(null);
        setPalette(true);
    };

    /**
     * Where the focus goes when a dialogue closes and the element that opened it is gone, or was another
     * layer: the selected item of the pane that last had the focus.
     *
     * @returns {void}
     */
    const restoreFocus = (): void => {
        giveFocusBack({kind: 'layer'}, null, entryKey(treeRef.current, navOrder(treeRef.current), selectedRef.current));
    };

    /**
     * Turn one of the outline's display options on or off, kept where the classic list keeps it.
     *
     * @param {string} option The option.
     * @returns {void}
     */
    const toggleDisplay = (option: 'idnumber' | 'taxonomy'): void => {
        const next = {...display, [option]: !display[option]};
        setDisplay(next);
        load('local_dimensions/central/preferences')
            .then((preferences) => {
                preferences.saveDisplay({structure: option === 'idnumber' ? {id: next.idnumber} : {tax: next.taxonomy}});
                return null;
            })
            .catch(notifyError);
    };

    /**
     * Walk the tree with the keyboard in either pane (keynav.keyAction).
     *
     * The item the key acts on is the pane's Tab stop, which moves at once, and the walk is read from
     * the latest tree, so a key repeated before React renders acts on where the previous one went. A
     * command key (commands.shortcutAction) acts on the same item before the walk sees it.
     *
     * @param {object} event The key event.
     * @param {object} stop The pane's Tab stop.
     * @param {string} selector The CSS selector of the pane's items.
     * @param {string} from The pane.
     * @returns {void}
     */
    const walk = (event: ReactKeyboardEvent<HTMLElement>, stop: Roving, selector: string, from: 'outline' | 'map'): void => {
        const target = event.target as HTMLElement;
        const element = target.closest<HTMLElement>(selector);
        if (!element || !event.currentTarget.contains(element)) {
            return;
        }
        // Enter and Space on an item's own button (focused by a click) are the button's.
        if (element !== target && (event.key === 'Enter' || event.key === ' ')) {
            return;
        }
        const command = shortcutAction(event, from);
        // The palette's key and the checker's walk are the workspace root's (onRootKeyDown).
        if (command === 'palette' || command === 'nextfinding' || command === 'prevfinding') {
            return;
        }
        if (command !== null) {
            if (keyCommand(command, stop.activeRef.current, element, from)) {
                event.preventDefault();
            }
            return;
        }
        const action = keyAction(navOrder(treeRef.current), stop.activeRef.current, event, rtl);
        if (!action) {
            if (from === 'outline' && isTypeAheadKey(event) && typeTo(event.key, event.timeStamp)) {
                event.preventDefault();
            }
            return;
        }
        event.preventDefault();
        switch (action.type) {
            case 'focus':
                stop.focusKey(action.key);
                break;
            case 'expand':
                openBranch(action.id, from === 'map');
                break;
            case 'collapse':
                closeBranch(action.id);
                break;
            case 'select':
                activate(action.id, from);
                break;
            case 'loadmore':
                loadMoreAndFocus(action.parent, stop);
                break;
            default:
                break;
        }
    };

    const actions: PanelActions = {
        /**
         * Run one of the panel's actions on the selected competency.
         *
         * @param {string} id The action.
         * @returns {void}
         */
        run: (id) => runAction(id, selectedRef.current),
        /**
         * Open the selected competency's menu, or the framework's on the root, under the panel's "More actions"
         * button.
         *
         * @param {HTMLElement} anchor The button.
         * @returns {void}
         */
        menu: (anchor) => {
            openMenu(selectedRef.current ?? ROOT, anchor, 'panel');
        },
        reveal: revealItem,
    };

    // The outline's display options are the classic list's, read where it keeps them.
    useEffect(() => {
        load('local_dimensions/central/preferences')
            .then((preferences) => {
                const saved = preferences.getDisplay().structure;
                setDisplay({idnumber: !!saved.id, taxonomy: !!saved.tax});
                return null;
            })
            .catch(notifyError);
    }, []);

    // The inline editor lives in the outline: a view without it closes the editor.
    useEffect(() => {
        if (!panes.outline) {
            setEditor(null);
        }
    }, [panes.outline]);

    // Escape leaves the move mode, wherever the focus is.
    useEffect(() => {
        if (moving === null) {
            return undefined;
        }
        /**
         * Leave the move mode on Escape.
         *
         * @param {object} event The key event.
         * @returns {void}
         */
        const onKey = (event: KeyboardEvent): void => {
            if (event.key === 'Escape') {
                endMove(true);
            }
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [moving, endMove]);

    // Any dialogue closing (the related competencies', the links', a form) refreshes the panel's lists.
    useEffect(() => {
        /**
         * Unbind the listener; replaced once jQuery has loaded.
         *
         * @returns {void}
         */
        let off = (): void => undefined;
        load('jquery')
            .then(($) => {
                /**
                 * Read the selected competency's lists again, unless it is being deleted.
                 *
                 * @returns {void}
                 */
                const onHidden = (): void => {
                    if (selectedRef.current !== null && selectedRef.current !== removing.current) {
                        loadSelection(selectedRef.current);
                    }
                    // The related competencies' dialogue writes as it goes: its closing ends the change.
                    if (relating.current !== null) {
                        relating.current = null;
                        settled('relate');
                    }
                };
                $(document).on(MODAL_HIDDEN, onHidden);
                /**
                 * Unbind the listener.
                 *
                 * @returns {void}
                 */
                off = (): void => {
                    $(document).off(MODAL_HIDDEN);
                };
                return null;
            })
            .catch(notifyError);
        return () => off();
    }, [loadSelection, settled]);

    /**
     * The name of a "load more" node.
     *
     * @param {number} remaining How many children are still to load.
     * @returns {string} The name.
     */
    const moreLabel = useCallback((remaining: number): string =>
        fill(labels.hubv2_loadmore, String(Math.min(remaining, pagesize))), [labels, pagesize]);

    /**
     * A competency node's accessible name.
     *
     * @param {object} info What to say.
     * @returns {string} The name.
     */
    const nameOf = useCallback((info: NameInfo): string => nodeName(labels, info), [labels]);

    /**
     * The framework node's accessible name.
     *
     * @param {number} total The framework's competency count.
     * @returns {string} The name.
     */
    const frameworkName = useCallback((total: number): string =>
        `${framework.shortname}, ${fillCount(labels, 'hubv2_competencies', total)}`, [framework.shortname, labels]);

    // Read the insights once shortly after mounting, so the checker's count shows; at once when a lens is shown.
    useEffect(() => {
        if (lensRef.current !== 'none') {
            ensureInsights().catch(notifyError);
            return undefined;
        }
        const timer = window.setTimeout(() => {
            ensureInsights().catch(notifyError);
        }, INSIGHTS_DELAY);
        return () => window.clearTimeout(timer);
    }, [ensureInsights]);

    // A later framework never takes an answer asked for this one.
    useEffect(() => () => insightsAsk.drop(), [insightsAsk]);

    const index = insightsState.index;
    const drawn = useMemo(() => drawnSet(tree), [tree]);
    const mappairs = useMemo(
        () => (index && lens === 'relations' ? pairsOnMap(index.answer.pairs, drawn) : null),
        [index, lens, drawn]
    );
    /**
     * A competency's name, from the tree or from the names read for the panel's lists.
     *
     * @param {number} id The competency, ROOT for the framework.
     * @returns {string|null} The name, or null while it is not known.
     */
    const nameById = (id: number): string | null => {
        if (id === ROOT) {
            return framework.shortname;
        }
        return tree.nodes[id]?.shortname ?? names[id] ?? null;
    };
    const marks = useMemo(() => (index ? lensMarks(lens, [...drawn], {
        labels,
        index,
        measure,
        elsewhere: mappairs ? mappairs.elsewhere : new Map(),
        /**
         * The name of the parent a child's points belong to.
         *
         * @param {number} id The parent.
         * @returns {string} Its name, empty while unknown.
         */
        nameOf: (id) => tree.nodes[id]?.shortname ?? names[id] ?? '',
    }) : NO_MARKS), [index, lens, drawn, labels, measure, mappairs, tree, names]);
    const findingsmarked = useMemo(
        () => (checker && index ? findingCounts(index.answer.checks) : NO_FINDINGS),
        [checker, index]
    );
    const findingwords = useCallback((total: number): string => fillCount(labels, 'hubv2_findings', total), [labels]);
    const decor = useMemo(() => ({
        marks,
        findings: findingsmarked,
        findingwords,
        impact,
        counts: lens === 'rules' && index
            /**
             * Whether the parent's rule counts the child.
             *
             * @param {number} parent The parent.
             * @param {number} child The child.
             * @returns {boolean} Whether it does.
             */
            ? (parent: number, child: number): boolean => countsChild(index, parent, child)
            : undefined,
        pairs: mappairs ? mappairs.edges : undefined,
    }), [marks, findingsmarked, findingwords, impact, lens, index, mappairs]);

    const relatedids = useMemo(() => related.map((item) => item.id), [related]);
    const graph = useMemo(() => buildGraph({
        tree,
        framework: {...framework, competencycount: count},
        selected,
        related: relatedids,
        moving,
        labels: {roledescription: labels.hubv2_noderole, more: moreLabel, name: nameOf, framework: frameworkName},
        rtl,
        decor,
    }), [tree, framework, count, selected, relatedids, moving, labels, moreLabel, nameOf, frameworkName, rtl, decor]);
    const graphRef = useRef(graph);
    graphRef.current = graph;

    // Only the Tab stop's node object changes when the stop moves: React Flow redraws that wrapper alone.
    const nodes = useMemo((): CanvasNode[] => graph.nodes.map((node) => {
        const sized = measured[node.id] ? {...node, measured: measured[node.id]} : node;
        return node.id === mapStop.active ? {...sized, domAttributes: {...sized.domAttributes, tabIndex: 0}} : sized;
    }), [graph, measured, mapStop.active]);

    // Reveal the competency the address named, once, when the server found it in this framework.
    const linked = useRef(false);
    useEffect(() => {
        const target = props.reveal;
        if (linked.current || !target) {
            return;
        }
        linked.current = true;
        track('reveal', ensureVisible(target.id, target.pathids).then(() => {
            if (treeRef.current.nodes[target.id]) {
                centreOn.current = competencyKey(target.id);
                choose(target.id);
            }
            return undefined;
        })).catch(notifyError);
    }, [props.reveal, ensureVisible, choose]);

    // After a reload of the pane for this framework (an action on it), open again what the viewer had opened,
    // as many children loaded as before, and select what was selected (carry.ts). Once, at mount.
    const carried = useRef(take(framework.id));
    useEffect(() => {
        const carry = carried.current;
        carried.current = null;
        if (!carry) {
            return;
        }
        track('restore', (async() => {
            const roots = treeRef.current.branches[ROOT]?.ids.length ?? 0;
            if ((carry.loaded[ROOT] ?? 0) > roots) {
                await reloadBranch(ROOT, carry.loaded[ROOT]);
            }
            for (const {id, pathids} of carry.expanded) {
                await ensureVisible(id, pathids);
                if (!treeRef.current.nodes[id]) {
                    continue;
                }
                await reloadBranch(id, carry.loaded[id] ?? 1);
                commit((current) => setExpanded(current, id, true));
            }
            const picked = carry.selected;
            if (picked) {
                await ensureVisible(picked.id, picked.pathids);
                if (treeRef.current.nodes[picked.id]) {
                    centreOn.current = competencyKey(picked.id);
                    choose(picked.id);
                }
            }
        })()).catch(notifyError);
    }, [choose, commit, ensureVisible, reloadBranch]);

    // Centre on a competency just revealed, once the layout has placed it.
    useEffect(() => {
        const key = centreOn.current;
        const position = key ? graph.positions[key] : undefined;
        if (!key || !position) {
            return;
        }
        centreOn.current = null;
        if (panesRef.current.map) {
            const zoom = Math.max(flow.getZoom(), 0.8);
            flow.setCenter(position.x + CARD.width / 2, position.y + CARD.height / 2, {zoom, duration: motionDuration(300)})
                .catch(() => null);
        }
        focusItem(key, false);
    }, [graph, flow]);

    // The tab's view switch moves among the three layouts without reloading the pane (selection.LAYOUT_EVENT).
    useEffect(() => {
        /**
         * Take the layout the switch chose.
         *
         * @param {object} event The event, its detail {layout}.
         * @returns {void}
         */
        const onLayout = (event: Event): void => {
            const next = (event as CustomEvent<{layout?: unknown}>).detail?.layout;
            if (isLayout(next)) {
                setLayout(next);
            }
        };
        document.addEventListener(LAYOUT_EVENT, onLayout);
        return () => document.removeEventListener(LAYOUT_EVENT, onLayout);
    }, []);

    /**
     * The width of the outline, the splitter and the map together.
     *
     * @returns {number} The width in pixels, 0 before the panes are drawn.
     */
    const available = (): number => panesBox.current?.getBoundingClientRect().width ?? 0;

    // In the Both layout the kept width is held to the room the panes have now.
    useEffect(() => {
        if (layout === 'both') {
            setOutlineWidth((width) => clampWidth(width, available()));
        }
    }, [layout]);

    /**
     * Take a width from the splitter, and keep it when the key or the drop that made it ends.
     *
     * @param {number} width The width.
     * @param {boolean} final Whether to keep it.
     * @returns {void}
     */
    const onOutlineWidth = (width: number, final: boolean): void => {
        setOutlineWidth(width);
        if (final) {
            storeWidth(width);
        }
    };

    /**
     * Go back to the default width and forget the kept one.
     *
     * @returns {void}
     */
    const resetOutlineWidth = (): void => {
        setOutlineWidth(clampWidth(OUTLINE_WIDTH.initial, available()));
        storeWidth(null);
    };

    // Once a branch opened on the map is drawn, move the viewport just enough to show the parent and the
    // start of its children (keynav.branchTarget); instant under reduced motion, no move when they fit.
    useEffect(() => {
        const id = revealing.current;
        if (id === null || !isExpanded(treeRef.current, id)) {
            return;
        }
        const parent = graph.positions[competencyKey(id)];
        const canvas = measureCanvas();
        const done = revealed.current;
        revealing.current = null;
        revealed.current = null;
        const keys = [...(treeRef.current.branches[id]?.ids ?? []).map(competencyKey), moreKey(id)];
        const children = keys.flatMap((key) => (graph.positions[key] ? [{...graph.positions[key], ...CARD}] : []));
        const target = parent && canvas
            ? branchTarget({...parent, ...CARD}, children, flow.getViewport(), canvas.view, canvas.obstacles, rtl)
            : null;
        if (!target) {
            done?.();
            return;
        }
        flow.setCenter(target.x, target.y, {zoom: flow.getZoom(), duration: motionDuration(200)})
            .catch(() => null)
            .finally(() => done?.());
    }, [graph, flow, rtl]);

    // The registry's decision for the selected competency: the panel's buttons, and whether its row and
    // its card carry the "more" button.
    const selectedcontext = contextFor(selected);
    const nodeactions = actionsFor(['node'], selectedcontext);
    const frameworkactions = actionsFor(['framework'], selectedcontext);
    const menuFor = menuTarget(selected, nodeactions, frameworkactions);
    const menuOpen = menu ? menu.id : null;
    const openMenuRef = useRef(openMenu);
    openMenuRef.current = openMenu;
    /**
     * Open a map card's menu under its "more" button; stable, so the cards are not drawn again for it.
     *
     * @param {number} id The competency.
     * @param {HTMLElement} anchor The button.
     * @returns {void}
     */
    const openMapMenu = useCallback((id: number, anchor: HTMLElement): void => {
        openMenuRef.current(id, anchor, 'map');
    }, []);

    // The map's view, for the column headings: React Flow reports every move, and the headings listen.
    const viewportListener = useRef<((viewport: Viewport) => void) | null>(null);
    const subscribeViewport = useCallback((listener: (viewport: Viewport) => void): (() => void) => {
        viewportListener.current = listener;
        return () => {
            if (viewportListener.current === listener) {
                viewportListener.current = null;
            }
        };
    }, []);
    const readViewport = useCallback((): Viewport => flow.getViewport(), [flow]);
    const levels = useMemo(() => deepestLevel(tree), [tree]);
    const heads = useMemo(
        () => columnHeads(levels, framework.taxonomies, framework.taxonomyfallback, rtl),
        [levels, framework.taxonomies, framework.taxonomyfallback, rtl]
    );
    /**
     * Open the framework's form from a column heading, where the level names are edited.
     *
     * @returns {void}
     */
    const editLevels = (): void => runAction('fwedit', null);

    const canvasActions: CanvasActions = useMemo(
        () => ({labels, pagesize, rtl, toggle: toggleOnMap, loadMore, menuFor, menuOpen, openMenu: openMapMenu}),
        [labels, pagesize, rtl, toggleOnMap, loadMore, menuFor, menuOpen, openMapMenu]
    );

    const outlineActions: OutlineActions = {
        /**
         * Select the competency of a row; a "load more" row selects nothing.
         *
         * @param {object} row The row.
         * @returns {void}
         */
        activate: (row) => {
            if (row.kind !== 'more') {
                activate(row.id, 'outline');
            }
        },
        toggle,
        /**
         * Load a branch's next page and move the focus to its first new item.
         *
         * @param {number} parent The branch.
         * @returns {void}
         */
        loadMore: (parent) => loadMoreAndFocus(parent, outlineStop),
        expandAll,
        collapseAll: collapseEverything,
        /**
         * Add a top-level competency.
         *
         * @returns {void}
         */
        add: () => runAction('addtop', null),
        /**
         * Walk the outline with the tree's keys.
         *
         * @param {object} event The key event.
         * @returns {void}
         */
        onKeyDown: (event) => walk(event, outlineStop, ROW_SELECTOR, 'outline'),
        /**
         * Note the outline as the pane that last had the focus, then follow its Tab stop.
         *
         * @param {object} event The focus event.
         * @returns {void}
         */
        onFocus: (event) => {
            lastPane.current = 'outline';
            outlineStop.onFocus(event);
        },
        onBlur: outlineStop.onBlur,
        /**
         * Open the selected row's menu under its "more" button.
         *
         * @param {number} id The competency.
         * @param {HTMLElement} anchor The button.
         * @returns {void}
         */
        openMenu: (id, anchor) => {
            openMenu(id, anchor, 'outline');
        },
        toggleDisplay,
    };

    /**
     * The keys the outline's foot names: the authoring keys for a manager, the palette for everyone, F8 once the
     * checker has findings.
     *
     * @returns {Array} The hints.
     */
    const outlineHints = (): OutlineHint[] => {
        const hints: OutlineHint[] = [];
        if (framework.canmanage) {
            const keys: [Shortcut, string][] = [
                [{key: 'F10', shift: true}, labels.hubv2_hint_actions],
                [{key: 'F2'}, labels.hubv2_cmd_rename],
                [{key: 'Enter', mod: true}, labels.hubv2_cmd_quicksibling],
                [{key: 'Enter', mod: true, shift: true}, labels.hubv2_cmd_quickchild],
                [{key: '↑↓', alt: true}, labels.hubv2_hint_reorder],
            ];
            keys.forEach(([shortcut, label]) => hints.push({shortcut, label}));
        }
        hints.push({shortcut: PALETTE_SHORTCUT, label: labels.hubv2_commands});
        // The checker's walk, once there is something to walk.
        if (insightsState.index && insightsState.index.answer.checks.length > 0) {
            hints.push({shortcut: {key: 'F8'}, label: labels.hubv2_cmd_nextfinding});
        }
        return hints;
    };

    /**
     * The inline editor, drawn by the outline after its anchor's row or in place of the renamed one.
     *
     * @returns {object|null} The editor and its place, or null when none is open or its row is not drawn.
     */
    const outlineEditor = (): OutlineEditor | null => {
        if (editor && editor.mode === 'rename') {
            const renamedid = editor.id;
            const renamed = tree.nodes[renamedid];
            const row = rows.find((item) => item.key === competencyKey(renamedid));
            if (renamed && row) {
                return {key: row.key, replace: true, node: (
                    <Editor key={`rename-${renamedid}`} labels={labels} mode="rename"
                        label={fill(labels.hubv2_editor_rename, renamed.shortname)} level={row.level}
                        initialname={renamed.shortname} initialidnumber=""
                        onSave={(name) => saveRename(renamedid, name)} onCancel={closeEditor} />
                )};
            }
        } else if (editor) {
            const parentkey = parentKey(editor.parent);
            const parentrow = rows.find((item) => item.key === parentkey);
            const slot = editorSlot(rows, parentkey, editor.after === null ? null : competencyKey(editor.after));
            if (parentrow && slot) {
                const siblings = (tree.branches[editor.parent]?.ids ?? []).map((id) => tree.nodes[id]?.idnumber ?? '');
                const taken = Object.values(tree.nodes).map((known) => known.idnumber);
                const {parent, after} = editor;
                return {key: slot, replace: false, node: (
                    <Editor key={`create-${editor.serial}`} labels={labels} mode="create"
                        label={fill(labels.hubv2_editor_new, parentrow.name)} level={parentrow.level + 1}
                        initialname="" initialidnumber={suggestIdnumber(siblings, taken)}
                        onSave={(name, idnumber) => saveCreate(parent, after, name, idnumber)} onCancel={closeEditor} />
                )};
            }
        }
        return null;
    };

    /**
     * An action as the palette lists it.
     *
     * @param {object} view The action and its state.
     * @returns {object} The entry.
     */
    const entryOf = (view: ActionView): PaletteEntry =>
        ({id: view.spec.id, label: labels[view.spec.label], icon: view.spec.icon, shortcut: view.spec.shortcut});

    /**
     * The open menu's items: the registry's actions on its competency (or on the framework, for ROOT), a
     * separator between groups.
     *
     * @param {number} id The competency, or ROOT.
     * @returns {Array} The items.
     */
    const menuItems = (id: number): MenuItem[] => menuActions(id).map((view, index, all) => ({
        id: view.spec.id,
        label: labels[view.spec.label],
        icon: view.spec.icon,
        shortcut: view.spec.shortcut,
        disabled: view.state === 'disabled',
        danger: view.spec.danger,
        separator: index > 0 && all[index - 1].spec.group !== view.spec.group,
    }));

    /**
     * Nothing to say.
     *
     * @returns {string} The empty string.
     */
    const silent = (): string => '';

    const aria: Partial<AriaLabelConfig> = useMemo(() => ({
        // Both, because the library shows keyboardDisabled's text when its own keyboard is off
        // (nodesFocusable false); every node's aria-describedby points at it.
        'node.a11yDescription.default': labels.hubv2_a11y_node,
        'node.a11yDescription.keyboardDisabled': labels.hubv2_a11y_node,
        // Spoken when a node is moved by the arrow keys, which a node that cannot be dragged never is.
        'node.a11yDescription.ariaLiveMessage': silent,
        'controls.ariaLabel': labels.hubv2_a11y_controls,
        'controls.zoomIn.ariaLabel': labels.hubv2_zoomin,
        'controls.zoomOut.ariaLabel': labels.hubv2_zoomout,
        'controls.fitView.ariaLabel': labels.hubv2_fitview,
        'controls.interactive.ariaLabel': labels.hubv2_a11y_controls,
        'minimap.ariaLabel': labels.hubv2_minimap,
        'handle.ariaLabel': labels.hubv2_a11y_handle,
    }), [labels]);

    const node = selected !== null ? tree.nodes[selected] ?? null : null;

    /**
     * The pane whose bottom decides whether a menu goes above its anchor; none for the panel's button.
     *
     * @param {string} origin Where the menu was opened.
     * @returns {HTMLElement|null} The pane's element.
     */
    const paneOf = (origin: Pane | 'panel'): HTMLElement | null => {
        if (origin === 'panel') {
            return null;
        }
        return origin === 'outline' ? listRef.current : canvasRef.current;
    };

    /**
     * The name of an item a menu belongs to: the framework's for the root, else the competency's.
     *
     * @param {number} id The competency, or ROOT.
     * @returns {string|undefined} The name, undefined for a competency no longer in the tree.
     */
    const nameOfItem = (id: number): string | undefined =>
        (id === ROOT ? framework.shortname : tree.nodes[id]?.shortname);

    /**
     * The layers over the workspace: a node's menu, the command palette, the "Move to" dialogue.
     *
     * @returns {object} The layers that are open.
     */
    const overlays = (): ReactNode => {
        const menuname = menu ? nameOfItem(menu.id) : undefined;
        const movingnode = moveDialog !== null ? tree.nodes[moveDialog] : undefined;
        const offered = palette ? actionsFor(['node', 'framework', 'move', 'workspace', 'structures'], selectedcontext) : [];
        /**
         * Whether an action belongs to the selected item (a competency or the framework).
         *
         * @param {ActionView} view The action as offered.
         * @returns {boolean} True for an action on the selection.
         */
        const onthis = (view: ActionView): boolean => view.spec.scope === 'node' || view.spec.scope === 'framework';
        const enabled = offered.filter((view) => view.state === 'enabled');
        return (
            <>
                {menu && menuname !== undefined ? (
                    <Menu
                        label={fill(labels.hubv2_actionsof, menuname)}
                        items={menuItems(menu.id)}
                        anchor={menu.anchor}
                        pane={paneOf(menu.origin)}
                        rtl={rtl}
                        mac={mac}
                        onRun={(id) => {
                            closeMenu();
                            runAction(id as ActionId, menu.id === ROOT ? null : menu.id);
                        }}
                        onClose={closeMenu}
                    />
                ) : null}
                {palette ? (
                    <Palette
                        labels={labels}
                        mac={mac}
                        frameworkid={framework.id}
                        actionstitle={fill(labels.hubv2_palette_on, node ? node.shortname : framework.shortname)}
                        actions={enabled.filter(onthis).map(entryOf)}
                        commands={enabled.filter((view) => !onthis(view)).map(entryOf)}
                        onRun={(id, hit) => {
                            setPalette(false);
                            if (hit) {
                                revealItem(hit);
                            } else {
                                runAction(id as ActionId, selectedRef.current);
                            }
                        }}
                        onClose={() => setPalette(false)}
                        onLostFocus={restoreFocus}
                    />
                ) : null}
                {movingnode ? (
                    <MoveDialog
                        labels={labels}
                        tree={tree}
                        moving={movingnode}
                        frameworkid={framework.id}
                        frameworkname={framework.shortname}
                        rtl={rtl}
                        pagesize={pagesize}
                        canpick={panes.map}
                        loadBranch={(parent, more) => track('children', loadPage(parent, more))}
                        freshParent={freshParent}
                        onPick={() => {
                            setMoveDialog(null);
                            setImpact(NO_IMPACT);
                            startMove(movingnode);
                        }}
                        onCommit={(target) => {
                            setMoveDialog(null);
                            setImpact(NO_IMPACT);
                            moveUnder(movingnode, target, false);
                        }}
                        onClose={() => {
                            setMoveDialog(null);
                            setImpact(NO_IMPACT);
                        }}
                        onLostFocus={restoreFocus}
                        onTarget={onMoveTarget}
                    />
                ) : null}
            </>
        );
    };

    const movingnode = moving !== null ? tree.nodes[moving] ?? null : null;

    // Usage is offered unless the viewer is told none; while it is the lens shown, its cell stays, pressed.
    const offeredlenses = LENSES.filter((value) => value !== 'usage' || lens === 'usage' || index?.answer.usagelevel !== 'none');
    const facts = insightFacts(index);
    const orderedfindings = orderFindings(facts.checks);
    const loadedids = Object.keys(tree.nodes).map(Number);
    /**
     * The registry's decision for a competency the tree has not loaded: a manager of the framework is offered the
     * action, which runs once the competency is revealed, the registry deciding then (runOn).
     *
     * @returns {string} The state.
     */
    const fallbackState = (): ActionState => (framework.canmanage && moving === null ? 'enabled' : 'hidden');

    /**
     * The checker's panel mode while it is open.
     *
     * @returns {object|null} The checker, or null while it is closed.
     */
    const checkerPanel = (): ReactNode => (checker ? (
        <Checker
            labels={labels}
            state={insightsState}
            findings={orderedfindings}
            current={findingAt}
            frameworkname={framework.shortname}
            canmanage={framework.canmanage}
            nameOf={nameById}
            need={need}
            actionFor={(action, id) => (tree.nodes[id] ? actionState(action, contextFor(id)) : fallbackState())}
            onReveal={(position) => {
                revealFinding(position, orderedfindings).catch(notifyError);
            }}
            onFix={runOn}
            onAgain={() => {
                loadInsights().catch(notifyError);
            }}
            onClose={closeChecker}
            headingRef={checkerHeading}
        />
    ) : null);

    const relatedcount = node && usage !== null ? related.length : null;
    const widthstyle = {[WIDTH_PROPERTY]: `${outlineWidth}px`} as CSSProperties;
    const rootclasses = ['local-dimensions-hubv2-layout', LAYOUT_CLASSES[layout]];
    if (moving !== null) {
        rootclasses.push('local-dimensions-hubv2-picking');
    }

    return (
        <div className={rootclasses.join(' ')} style={widthstyle} ref={rootRef} onKeyDown={onRootKeyDown}>
            <div className="local-dimensions-hubv2-main">
                <div className="local-dimensions-hubv2-bar">
                    <LensSwitch labels={labels} lens={lens} offered={offeredlenses} onChange={changeLens} />
                    <Search labels={labels} frameworkid={framework.id} onChoose={actions.reveal} />
                    {panes.map ? (
                        <span className="local-dimensions-hubv2-hint">
                            <i className="fa fa-search-plus me-1" aria-hidden="true" />{labels.hubv2_zoomhint}
                        </span>
                    ) : null}
                    <CheckButton labels={labels} findings={facts.findings} open={checker}
                        buttonRef={checkButton} onToggle={() => (checker ? closeChecker() : openChecker())} />
                    <button
                        type="button"
                        className="btn btn-sm btn-outline-secondary local-dimensions-hubv2-cmdbtn"
                        aria-haspopup="dialog"
                        aria-keyshortcuts={ariaShortcut(PALETTE_SHORTCUT, mac)}
                        onClick={() => {
                            setMenu(null);
                            setPalette(true);
                        }}
                    >
                        {labels.hubv2_commands}
                        <span className="local-dimensions-hubv2-keys" aria-hidden="true">
                            {shortcutCaps(PALETTE_SHORTCUT, mac).map((cap) => <kbd key={cap}>{cap}</kbd>)}
                        </span>
                    </button>
                </div>
                <div className="local-dimensions-hubv2-panes" ref={panesBox}>
                    {panes.outline ? (
                        <Outline
                            labels={labels}
                            framework={framework}
                            count={count}
                            rows={rows}
                            selected={selected}
                            moving={moving}
                            relatedcount={relatedcount}
                            active={outlineStop.active}
                            loading={loadingBranches}
                            expanding={expanding}
                            notice={notice}
                            rtl={rtl}
                            pagesize={pagesize}
                            listRef={listRef}
                            actions={outlineActions}
                            display={display}
                            menuFor={menuFor}
                            menuOpen={menuOpen}
                            editor={outlineEditor()}
                            hints={outlineHints()}
                            mac={mac}
                            marks={marks}
                            findings={findingsmarked}
                            findingwords={findingwords}
                            impact={impact}
                        />
                    ) : null}
                    {layout === 'both' ? (
                        <Splitter
                            label={labels.hubv2_resizeoutline}
                            controls={OUTLINE_ID}
                            width={outlineWidth}
                            available={available}
                            rtl={rtl}
                            onWidth={onOutlineWidth}
                            onReset={resetOutlineWidth}
                        />
                    ) : null}
                    {panes.map ? (
                        <div
                            className="local-dimensions-hubv2-canvas"
                            role="region"
                            aria-label={labels.hubv2_canvas}
                            aria-describedby={heads.length > 0 ? COLUMNS_ID : undefined}
                            ref={canvasRef}
                            onKeyDown={(event) => walk(event, mapStop, NODE_SELECTOR, 'map')}
                            onFocus={(event) => {
                                lastPane.current = 'map';
                                mapStop.onFocus(event);
                            }}
                            onBlur={mapStop.onBlur}
                        >
                            <ColumnHeads
                                labels={labels}
                                heads={heads}
                                cardwidth={CARD.width}
                                read={readViewport}
                                subscribe={subscribeViewport}
                                describedby={COLUMNS_ID}
                                canedit={actionState('fwedit', contextFor(null)) === 'enabled'}
                                onEdit={editLevels}
                            />
                            <CanvasContext.Provider value={canvasActions}>
                                <ReactFlow<CanvasNode, CanvasEdge>
                                    nodes={nodes}
                                    edges={graph.edges}
                                    nodeTypes={NODE_TYPES}
                                    edgeTypes={EDGE_TYPES}
                                    onNodesChange={onNodesChange}
                                    onMove={(event, viewport) => viewportListener.current?.(viewport)}
                                    // The workspace's own keyboard (walk) replaces the library's: no tabindex 0 on
                                    // every node, no Enter, Space or Escape of its own, no pan on focus.
                                    nodesFocusable={false}
                                    nodesDraggable={false}
                                    nodesConnectable={false}
                                    edgesFocusable={false}
                                    // The selected card's edges, its related ones too, stay under the other cards.
                                    elevateEdgesOnSelect={false}
                                    selectNodesOnDrag={false}
                                    deleteKeyCode={null}
                                    selectionKeyCode={null}
                                    multiSelectionKeyCode={null}
                                    zoomOnDoubleClick={false}
                                    zoomOnScroll={false}
                                    zoomOnPinch
                                    preventScrolling={false}
                                    zoomActivationKeyCode={ZOOM_KEYS}
                                    fitView
                                    fitViewOptions={FIT}
                                    minZoom={0.2}
                                    maxZoom={1.5}
                                    ariaLabelConfig={aria}
                                    // The library's wrapper is an application; it is named after the framework it draws.
                                    aria-label={framework.shortname}
                                    proOptions={{hideAttribution: true}}
                                >
                                    <Background variant={BackgroundVariant.Dots} gap={18} size={1}
                                        className="local-dimensions-hubv2-dots" />
                                    <Controls showInteractive={false} fitViewOptions={FIT} />
                                    <MiniMap pannable zoomable ariaLabel={labels.hubv2_minimap} style={MINIMAP} />
                                </ReactFlow>
                            </CanvasContext.Provider>
                        </div>
                    ) : null}
                </div>
            </div>
            <Panel
                labels={labels}
                framework={framework}
                count={count}
                node={node}
                path={node ? ancestors(tree, node.id).map((id) => tree.nodes[id].shortname) : []}
                usage={usage}
                related={related}
                moving={movingnode}
                nodeactions={nodeactions}
                menuopen={menu !== null && menu.origin === 'panel'}
                moveactions={actionsFor(['move'], selectedcontext)}
                topactions={actionsFor(['workspace'], selectedcontext).filter((view) => view.spec.id === 'addtop')}
                frameworkactions={frameworkactions}
                summary={summaryLines(labels, summaryFacts(tree, count), facts.summary)}
                summaryRef={summaryRef}
                actions={actions}
                lens={(
                    <LensSection
                        labels={labels}
                        lens={lens}
                        state={insightsState}
                        measure={measure}
                        loaded={loadedids}
                        nameOf={nameById}
                        need={need}
                        reveal={revealListed}
                        ruleState={(id) => (tree.nodes[id] ? actionState('rule', contextFor(id)) : fallbackState())}
                        runOn={runOn}
                        onMeasure={setMeasure}
                        onRetry={() => {
                            loadInsights().catch(notifyError);
                        }}
                    />
                )}
                checker={checkerPanel()}
            />
            <div className="visually-hidden" aria-live="polite">{announcement}</div>
            {overlays()}
        </div>
    );
};

/**
 * The workspace: the strings first, read in one call from the page's own cache.
 *
 * @param {object} props The page's props.
 * @returns {ReactNode} The component.
 */
const Hub = (props: HubProps): ReactNode => {
    const [labels, setLabels] = useState<Labels | null>(null);
    useEffect(() => {
        track('strings', loadLabels()).then(setLabels).catch(notifyError);
    }, []);
    if (!labels) {
        return <div className="local-dimensions-hubv2-layout" aria-busy="true" />;
    }
    return (
        <div className="local-dimensions-hubv2-root">
            <ReactFlowProvider>
                <Workspace {...props} labels={labels} />
            </ReactFlowProvider>
        </div>
    );
};

export default Hub;
