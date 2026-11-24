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
 * @module     local_dimensions/hubv2/Hub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useCallback, useEffect, useMemo, useRef, useState} from 'react';
import type {CSSProperties, KeyboardEvent as ReactKeyboardEvent, ReactNode} from 'react';
import {
    Background, BackgroundVariant, Controls, MiniMap, ReactFlow, ReactFlowProvider, useReactFlow,
} from '../../vendor/xyflow/xyflow.js';
import type {AriaLabelConfig, FitViewOptions, NodeChangeset} from '../../vendor/xyflow/xyflow.js';
import {load, notifyError} from './amd';
import type {RuleConfig} from './amd';
import {CARD, FRAMEWORK_ID, buildGraph, competencyKey, competencyOf, moreKey} from './graph';
import {EXPAND_ALL_BOUND, collapseAll, expandStep} from './expandall';
import {branchTarget, isTypeAheadKey, keyAction, navOrder, nodeName, panTarget, typeAhead} from './keynav';
import type {Rect} from './keynav';
import type {NameInfo} from './keynav';
import Outline, {OUTLINE_ID} from './Outline';
import type {OutlineActions} from './Outline';
import {outlineRows} from './outlinerows';
import type {OutlineRow} from './outlinerows';
import {useRoving} from './roving';
import type {Roving} from './roving';
import {LAYOUT_EVENT, activation, isLayout, panesOf} from './selection';
import type {Layout} from './selection';
import Splitter from './Splitter';
import {OUTLINE_WIDTH, clampWidth, storeWidth, storedWidth} from './outlinewidth';
import type {CanvasEdge, CanvasNode} from './graph';
import {rememberSelection} from './deeplink';
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
    allChildren, browse, countCompetencies, deleteCheck, deleteCompetency, getNode, moveOne, selectionData, setParent,
    updateRule,
} from './repository';
import type {NodeAnswer} from './repository';
import Search from './Search';
import {fill, fillCount, loadLabels} from './strings';
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
 * How the tree is framed: a margin all round, and below it room for the overview map and the
 * controls so that no fitted node sits under them.
 */
const FIT: FitViewOptions = {padding: {top: '32px', right: '32px', bottom: '140px', left: '32px'}, maxZoom: 1};

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

/** The class of each layout's root, written whole so the stylesheet test finds it. */
const LAYOUT_CLASSES: Record<Layout, string> = {
    outline: 'local-dimensions-hubv2-layout-outline',
    map: 'local-dimensions-hubv2-layout-map',
    both: 'local-dimensions-hubv2-layout-both',
};

/** The custom property through which the outline's width reaches the stylesheet in the Both layout. */
const WIDTH_PROPERTY = '--local-dimensions-hubv2-outline-width';

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
     * Reload a parent's children, as many as were loaded (one page at least), after a change among them.
     *
     * @param {number} parent The parent.
     * @returns {Promise} Resolves once in the tree.
     */
    const reloadBranch = useCallback(async(parent: number): Promise<void> => {
        const wanted = Math.max(treeRef.current.branches[parent]?.ids.length ?? 0, 1);
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
     * Select a competency, or the framework with null, and keep it in the address.
     *
     * @param {number|null} id The competency.
     * @returns {void}
     */
    const choose = useCallback((id: number | null): void => {
        selectedRef.current = id;
        setSelected(id);
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
        /**
         * A parent as it is now: read again, since whether it has a rule is what the dialogue says.
         *
         * @param {number} id The parent, ROOT for the top level.
         * @returns {Promise} Resolves with the parent, or null for the top level or one gone.
         */
        const freshParent = async(id: number): Promise<MoveParent | null> => {
            if (id === ROOT) {
                return null;
            }
            const answer = await getNode(id);
            return answer.found && answer.node ? answer.node : null;
        };
        (async() => {
            const [oldparent, newparent] = await track('move', Promise.all([freshParent(source.parentid), freshParent(target)]));
            // A new parent gone meanwhile is named as the canvas knew it; core refuses the move then.
            const known = target === ROOT ? null : {shortname: treeRef.current.nodes[target]?.shortname ?? '', ruletype: null};
            const question = moveBody(labels, source.shortname, oldparent, newparent ?? known);
            const notification = await load('core/notification');
            const confirmed = await notification
                .saveCancelPromise(labels.hubv2_movetitle, question, labels.hubv2_move)
                .then(() => true, () => false);
            if (!confirmed) {
                return;
            }
            endMove(false);
            await track('move', (async() => {
                await setParent(source.id, target);
                const answer = await getNode(source.id);
                if (answer.found) {
                    await relocate(source.id, source.parentid, answer);
                }
                announce(fill(labels.hubv2_moved, source.shortname));
            })());
        })().catch(notifyError);
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
                    track('refresh', refreshNode(node.id)).catch(notifyError);
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
            .then(() => announce(labels['core:changessaved']));

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
            track('refresh', refreshNode(node.id)).catch(notifyError);
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
     * Move a competency one place among its siblings, then reload them.
     *
     * @param {object} node The competency.
     * @param {boolean} up Up when true.
     * @returns {void}
     */
    const stepMove = (node: StructureNode, up: boolean): void => {
        track('refresh', moveOne(node.id, up).then(() => reloadBranch(node.parentid))).catch(notifyError);
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
            const confirmed = await notification
                .deleteCancelPromise(labels.hubv2_delete, confirmBody(labels, node.shortname, check))
                .then(() => true, () => false);
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
     * Edit a competency.
     *
     * @param {object} node The competency.
     * @returns {void}
     */
    const edit = (node: StructureNode): void => openForm(node, null);

    /**
     * Add a competency under another, or at the top level with null.
     *
     * @param {object|null} node The parent.
     * @returns {void}
     */
    const addChild = (node: StructureNode | null): void => openForm(null, node);

    /**
     * Make the competency being moved a top-level one.
     *
     * @returns {void}
     */
    const moveRoot = (): void => pickParent(ROOT);

    /**
     * Leave the move mode without moving.
     *
     * @returns {void}
     */
    const cancelMove = (): void => endMove(true);

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
     * Walk the tree with the keyboard in either pane (keynav.keyAction).
     *
     * The item the key acts on is the pane's Tab stop, which moves at once, and the walk is read from
     * the latest tree, so a key repeated before React renders acts on where the previous one went.
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

    const actions: PanelActions = {
        edit, addChild, rule: editRule, links: openLinks, relate: openRelated, move: startMove, moveRoot, cancelMove,
        moveOne: stepMove, remove, reveal: revealItem,
    };

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
    }, [loadSelection]);

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

    const relatedids = useMemo(() => related.map((item) => item.id), [related]);
    const graph = useMemo(() => buildGraph({
        tree,
        framework: {...framework, competencycount: count},
        selected,
        related: relatedids,
        moving,
        labels: {roledescription: labels.hubv2_noderole, more: moreLabel, name: nameOf, framework: frameworkName},
        rtl,
    }), [tree, framework, count, selected, relatedids, moving, labels, moreLabel, nameOf, frameworkName, rtl]);
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

    const canvasActions: CanvasActions = useMemo(
        () => ({labels, pagesize, rtl, toggle: toggleOnMap, loadMore}),
        [labels, pagesize, rtl, toggleOnMap, loadMore]
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
        add: () => addChild(null),
        /**
         * Walk the outline with the tree's keys.
         *
         * @param {object} event The key event.
         * @returns {void}
         */
        onKeyDown: (event) => walk(event, outlineStop, ROW_SELECTOR, 'outline'),
        onFocus: outlineStop.onFocus,
        onBlur: outlineStop.onBlur,
    };

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
    const siblings = node ? tree.branches[node.parentid] : undefined;
    const position = node && siblings ? siblings.ids.indexOf(node.id) : -1;
    const islast = !!siblings && position === siblings.ids.length - 1 && siblings.ids.length >= siblings.total;
    const movingnode = moving !== null ? tree.nodes[moving] ?? null : null;

    const relatedcount = node && usage !== null ? related.length : null;
    const widthstyle = {[WIDTH_PROPERTY]: `${outlineWidth}px`} as CSSProperties;
    const rootclasses = ['local-dimensions-hubv2-layout', LAYOUT_CLASSES[layout]];
    if (moving !== null) {
        rootclasses.push('local-dimensions-hubv2-picking');
    }

    return (
        <div className={rootclasses.join(' ')} style={widthstyle}>
            <div className="local-dimensions-hubv2-main">
                <div className="local-dimensions-hubv2-bar">
                    <Search labels={labels} frameworkid={framework.id} onChoose={actions.reveal} />
                    {panes.map ? (
                        <span className="local-dimensions-hubv2-hint">
                            <i className="fa fa-search-plus me-1" aria-hidden="true" />{labels.hubv2_zoomhint}
                        </span>
                    ) : null}
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
                            ref={canvasRef}
                            onKeyDown={(event) => walk(event, mapStop, NODE_SELECTOR, 'map')}
                            onFocus={mapStop.onFocus}
                            onBlur={mapStop.onBlur}
                        >
                            <CanvasContext.Provider value={canvasActions}>
                                <ReactFlow<CanvasNode, CanvasEdge>
                                    nodes={nodes}
                                    edges={graph.edges}
                                    nodeTypes={NODE_TYPES}
                                    edgeTypes={EDGE_TYPES}
                                    onNodesChange={onNodesChange}
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
                canUp={position > 0}
                canDown={position >= 0 && !islast}
                actions={actions}
            />
            <div className="visually-hidden" aria-live="polite">{announcement}</div>
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
