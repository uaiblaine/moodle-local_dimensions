var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
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
 * Beside the one competency the panel shows, a manager picks several for the bulk dock (multiselect.ts): Space on an
 * item, Shift+Up and Shift+Down from the anchor, Ctrl+A (Cmd+A) for the item's level, a box on each row and card,
 * Ctrl+click (Cmd+click) on a card; Escape releases them. The picks survive opening and closing branches and lose the
 * competencies that leave the tree. The dock (Dock.tsx) sits after the panes and acts on them through the registry's
 * `bulk` scope: "Set appearance…" (BulkDialogs.tsx, one all-or-nothing call of
 * local_dimensions_set_competencies_appearance), "Move under…" ("Move to" with every topmost pick, then core's
 * set_parent_competency once per competency, in the tree's order, stopping at the first refusal) and "Delete…" (core's
 * refusal reasons first, then core's delete for the competencies it allows). Each then reads the touched branches and
 * the insights again, says the outcome in the polite region, and keeps picked only what failed.
 *
 * @module     local_dimensions/hubv2/Hub
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useCallback, useEffect, useMemo, useReducer, useRef, useState } from "react";
import {
  LENS_ACTIONS,
  PALETTE_SHORTCUT,
  VIEW_ACTIONS,
  actionState,
  actionsFor,
  ariaShortcut,
  isMac,
  shortcutAction,
  shortcutCaps,
  specOf
} from "./commands";
import { AppearanceDialog, DeleteDialog } from "./BulkDialogs";
import { APPEARANCE_FIELDS, appearanceOutcome, deleteOutcome, deletionSummary, moveOutcome, movePlan, topmost } from "./bulk";
import Dock from "./Dock";
import Editor from "./Editor";
import { codeTitle, findingCounts, orderFindings, stepFinding } from "./findings";
import { NO_IMPACT, deleteImpact, moveImpact, sameImpact } from "./impact";
import { IDLE, LENSES, indexInsights, insightFacts, latest, lensOf } from "./insights";
import { countsChild, drawnSet, lensMarks, pairsOnMap } from "./lenses";
import { CheckButton, Checker, LensSection, LensSwitch } from "./LensPanel";
import { focusReturn } from "./focusreturn";
import { suggestIdnumber } from "./idnumber";
import Menu from "./Menu";
import MoveDialog from "./MoveDialog";
import Palette from "./Palette";
import { summaryFacts, summaryLines } from "./summary";
import { carryOf, hold, take } from "./carry";
import ColumnHeads from "./ColumnHeads";
import { columnHeads, deepestLevel } from "./columns";
import {
  Background,
  BackgroundVariant,
  Controls,
  MiniMap,
  ReactFlow,
  ReactFlowProvider,
  useReactFlow
} from "../../vendor/xyflow/xyflow.js";
import { load, notifyError } from "./amd";
import { CARD, FRAMEWORK_ID, buildGraph, competencyKey, competencyOf, moreKey, parentKey } from "./graph";
import { EXPAND_ALL_BOUND, collapseAll, expandStep } from "./expandall";
import { branchTarget, entryKey, isTypeAheadKey, keyAction, navOrder, nodeName, panTarget, typeAhead } from "./keynav";
import Outline, { OUTLINE_ID } from "./Outline";
import { editorSlot, outlineRows } from "./outlinerows";
import { useRoving } from "./roving";
import { NONE, extend, keepOnly, pickGesture, pickLevel, prune, toggle as togglePicked } from "./multiselect";
import { LAYOUT_EVENT, activation, isLayout, panesOf } from "./selection";
import Splitter from "./Splitter";
import { OUTLINE_WIDTH, clampWidth, storeWidth, storedWidth } from "./outlinewidth";
import { rememberLens, rememberSelection } from "./deeplink";
import { blockedBody, confirmBody } from "./deletion";
import { escapeHtml } from "./html";
import { motionDuration } from "./motion";
import { moveBody } from "./move";
import { CanvasContext, CompetencyNode, FrameworkNode, MoreNode, RuleEdge } from "./Nodes";
import Panel from "./Panel";
import { track } from "./pending";
import {
  allChildren,
  browse,
  countCompetencies,
  createCompetency,
  deleteCheck,
  deleteChecks,
  deleteCompetency,
  getNode,
  getNodes,
  insights as readInsights,
  moveOne,
  moveUpTimes,
  panelSections,
  renameCompetency,
  selectionData,
  setAppearance,
  setParent,
  updateRule
} from "./repository";
import { NO_SECTIONS, readSections, sectionsFor, sectionsReducer } from "./sections";
import Search from "./Search";
import { fill, fillCount, fillObject, loadLabels } from "./strings";
import {
  ROOT,
  ancestors,
  branchHas,
  createTree,
  expandPath,
  forgetBranch,
  hasBranch,
  insertChildren,
  isExpanded,
  isWithin,
  removeNode,
  replaceNode,
  setExpanded
} from "./tree";
const FORM_CLASS = "local_dimensions\\form\\competency_dynamic_form";
const MODAL_HIDDEN = "modal:hidden.localdimensionshubv2";
const NODE_TYPES = { framework: FrameworkNode, competency: CompetencyNode, more: MoreNode };
const ZOOM_KEYS = ["Control", "Meta"];
const MINIMAP = { width: 140, height: 96 };
const FIT = { padding: { top: "56px", right: "32px", bottom: "140px", left: "32px" }, maxZoom: 1 };
const menuTarget = /* @__PURE__ */ __name((selected, nodeactions, frameworkactions) => {
  if (selected !== null) {
    return nodeactions.length > 0 ? selected : null;
  }
  return frameworkactions.length > 0 ? ROOT : null;
}, "menuTarget");
const COLUMNS_ID = "local-dimensions-hubv2-columns";
const EDGE_TYPES = { rule: RuleEdge };
const NODE_SELECTOR = ".react-flow__node";
const ROW_SELECTOR = '[role="treeitem"]';
const OVERLAYS = ".react-flow__minimap, .react-flow__controls";
const TYPE_AHEAD_PAUSE = 500;
const EXPAND_ALL_PARALLEL = 4;
const messageOf = /* @__PURE__ */ __name((error) => {
  if (error && typeof error === "object" && "message" in error && typeof error.message === "string") {
    return error.message;
  }
  return String(error);
}, "messageOf");
const LAYOUT_CLASSES = {
  outline: "local-dimensions-hubv2-layout-outline",
  map: "local-dimensions-hubv2-layout-map",
  both: "local-dimensions-hubv2-layout-both"
};
const WIDTH_PROPERTY = "--local-dimensions-hubv2-outline-width";
const INSIGHTS_DELAY = 800;
const NAME_BATCH = 50;
const NO_MARKS = /* @__PURE__ */ new Map();
const NO_FINDINGS = /* @__PURE__ */ new Map();
const Workspace = /* @__PURE__ */ __name((props) => {
  const { labels, framework, pagesize, rtl } = props;
  const flow = useReactFlow();
  const [layout, setLayout] = useState(isLayout(props.layout) ? props.layout : "map");
  const panes = panesOf(layout);
  const panesRef = useRef(panes);
  panesRef.current = panes;
  const [tree, setTree] = useState(() => createTree(props.roots));
  const treeRef = useRef(tree);
  const [selected, setSelected] = useState(null);
  const selectedRef = useRef(null);
  const [usage, setUsage] = useState(null);
  const [related, setRelated] = useState([]);
  const [sectionsState, sendSections] = useReducer(sectionsReducer, NO_SECTIONS);
  const sectionsAsk = useRef(0);
  const listens = props.sections === true;
  const [moving, setMoving] = useState(null);
  const movingRef = useRef(null);
  const [count, setCount] = useState(framework.competencycount);
  const [announcement, setAnnouncement] = useState("");
  const [measured, setMeasured] = useState({});
  const centreOn = useRef(null);
  const selectionAsk = useRef(0);
  const removing = useRef(null);
  const [loadingBranches, setLoadingBranches] = useState([]);
  const opening = useRef(/* @__PURE__ */ new Set());
  const paging = useRef(/* @__PURE__ */ new Set());
  const [expanding, setExpanding] = useState(false);
  const expandRun = useRef(0);
  const [notice, setNotice] = useState("");
  const [outlineWidth, setOutlineWidth] = useState(() => clampWidth(storedWidth(), 0));
  const panesBox = useRef(null);
  const canvasRef = useRef(null);
  const listRef = useRef(null);
  const typed = useRef({ text: "", at: 0 });
  const layoutRef = useRef(layout);
  layoutRef.current = layout;
  const countRef = useRef(count);
  countRef.current = count;
  const expandingRef = useRef(expanding);
  expandingRef.current = expanding;
  const [menu, setMenu] = useState(null);
  const [palette, setPalette] = useState(false);
  const [moveDialog, setMoveDialog] = useState(null);
  const [editor, setEditor] = useState(null);
  const editorRef = useRef(null);
  editorRef.current = editor;
  const editorSerial = useRef(0);
  const [display, setDisplay] = useState({ idnumber: false, taxonomy: false });
  const rootRef = useRef(null);
  const summaryRef = useRef(null);
  const mac = useMemo(isMac, []);
  const lastPane = useRef(null);
  const revealing = useRef(null);
  const revealed = useRef(null);
  const settleReveal = /* @__PURE__ */ __name(() => {
    revealing.current = null;
    revealed.current?.();
    revealed.current = null;
  }, "settleReveal");
  const [lens, setLens] = useState(() => lensOf(props.lens));
  const lensRef = useRef(lens);
  lensRef.current = lens;
  const [measure, setMeasure] = useState("courses");
  const [insightsState, setInsights] = useState(IDLE);
  const insightsStatus = useRef("idle");
  const indexRef = useRef(null);
  const insightsAsk = useMemo(latest, []);
  const [names, setNames] = useState({});
  const naming = useRef(/* @__PURE__ */ new Set());
  const namedPaths = useRef({});
  const [checker, setChecker] = useState(false);
  const [findingAt, setFindingAt] = useState(null);
  const findingAtRef = useRef(null);
  const holdChecker = useRef(false);
  const checkButton = useRef(null);
  const checkerHeading = useRef(null);
  const relating = useRef(null);
  const [impact, setImpactState] = useState(NO_IMPACT);
  const moveDialogRef = useRef(null);
  moveDialogRef.current = moveDialog;
  const multiselect = framework.canmanage;
  const [picked, setPicked] = useState(NONE);
  const pickedRef = useRef(picked);
  pickedRef.current = picked;
  const [bulk, setBulk] = useState(null);
  const commit = useCallback((update) => {
    treeRef.current = update(treeRef.current);
    setTree(treeRef.current);
  }, []);
  const loadPage = useCallback((parent, append) => {
    const loaded = append ? treeRef.current.branches[parent]?.ids.length ?? 0 : 0;
    return browse(framework.id, parent, loaded, pagesize).then((page) => commit((current) => insertChildren(current, parent, page, append)));
  }, [commit, framework.id, pagesize]);
  const reloadBranch = useCallback(async (parent, atleast = 1) => {
    const wanted = Math.max(treeRef.current.branches[parent]?.ids.length ?? 0, atleast, 1);
    const items = [];
    let total = 0;
    do {
      const page = await browse(framework.id, parent, items.length, pagesize);
      items.push(...page.items);
      total = page.total;
      if (page.items.length === 0) {
        break;
      }
    } while (items.length < Math.min(wanted, total));
    commit((current) => insertChildren(current, parent, { items, total }));
  }, [commit, framework.id, pagesize]);
  const refreshParent = useCallback(async (parent) => {
    if (parent === ROOT) {
      return;
    }
    const answer = await getNode(parent);
    if (answer.found && answer.node) {
      const fresh = answer.node;
      commit((current) => replaceNode(current, fresh));
    }
  }, [commit]);
  const ensureVisible = useCallback(async (id, pathids) => {
    const chain = [ROOT, ...pathids];
    for (let index2 = 0; index2 < chain.length; index2++) {
      const parent = chain[index2];
      const child = index2 + 1 < chain.length ? chain[index2 + 1] : id;
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
  const loadSections = useCallback((id, keep) => {
    const ask = ++sectionsAsk.current;
    if (id === null || !listens) {
      sendSections({ type: "clear", ask });
      return;
    }
    sendSections({ type: "ask", ask, competencyid: id, keep });
    track("sections", panelSections(id)).then((answer) => {
      sendSections({ type: "answer", ask, sections: readSections(answer, window.location.origin) });
      return null;
    }).catch(() => {
      sendSections({ type: "fail", ask });
    });
  }, [listens]);
  const loadSelection = useCallback((id) => {
    const ask = ++selectionAsk.current;
    track("selection", selectionData(id)).then(([answer, relations]) => {
      if (ask === selectionAsk.current) {
        setUsage(answer);
        setRelated(relations);
      }
      return null;
    }).catch(notifyError);
  }, []);
  const choose = useCallback((id) => {
    selectedRef.current = id;
    setSelected(id);
    if (!holdChecker.current) {
      setChecker(false);
    }
    rememberSelection(id);
    setUsage(null);
    setRelated([]);
    if (id === null) {
      selectionAsk.current++;
      loadSections(null, false);
      return;
    }
    loadSelection(id);
    loadSections(id, false);
  }, [loadSelection, loadSections]);
  const reveal = useCallback((id, pathids) => ensureVisible(id, pathids).then(() => {
    centreOn.current = competencyKey(id);
    choose(id);
    return void 0;
  }), [choose, ensureVisible]);
  const relocate = useCallback(async (id, oldparent, answer) => {
    const fresh = answer.node;
    commit((current) => forgetBranch(current, id));
    await reloadBranch(oldparent);
    await refreshParent(oldparent);
    if (hasBranch(treeRef.current, fresh.parentid)) {
      await reloadBranch(fresh.parentid);
    }
    await refreshParent(fresh.parentid);
    await reveal(id, answer.pathids ?? []);
  }, [commit, refreshParent, reloadBranch, reveal]);
  const refreshNode = useCallback(async (id) => {
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
  const openBranch = useCallback((id, onmap = false) => {
    if (isExpanded(treeRef.current, id) || opening.current.has(id)) {
      return;
    }
    if (onmap && panesRef.current.map) {
      settleReveal();
      revealing.current = id;
      track("reveal", new Promise((resolve) => {
        revealed.current = resolve;
      })).catch(notifyError);
    }
    if (hasBranch(treeRef.current, id)) {
      commit((current) => setExpanded(current, id, true));
      return;
    }
    opening.current.add(id);
    setLoadingBranches((current) => [...current, id]);
    track("children", loadPage(id, false).then(() => commit((current) => setExpanded(current, id, true)))).finally(() => {
      opening.current.delete(id);
      setLoadingBranches((current) => current.filter((other) => other !== id));
      if (revealing.current === id && !isExpanded(treeRef.current, id)) {
        settleReveal();
      }
    }).catch(notifyError);
  }, [commit, loadPage]);
  const closeBranch = useCallback((id) => {
    commit((current) => setExpanded(current, id, false));
  }, [commit]);
  const toggle = useCallback((id, onmap = false) => {
    if (isExpanded(treeRef.current, id)) {
      closeBranch(id);
    } else {
      openBranch(id, onmap);
    }
  }, [closeBranch, openBranch]);
  const toggleOnMap = useCallback((id) => toggle(id, true), [toggle]);
  const nextPage = useCallback((parent) => {
    if (paging.current.has(parent)) {
      return Promise.resolve(null);
    }
    paging.current.add(parent);
    const before = treeRef.current.branches[parent]?.ids.length ?? 0;
    return track("children", loadPage(parent, true)).then(() => treeRef.current.branches[parent]?.ids[before] ?? null).finally(() => paging.current.delete(parent));
  }, [loadPage]);
  const loadMore = useCallback((parent) => {
    nextPage(parent).catch(notifyError);
  }, [nextPage]);
  const announce = /* @__PURE__ */ __name((message) => setAnnouncement(message), "announce");
  const endMove = useCallback((cancelled) => {
    movingRef.current = null;
    setMoving(null);
    if (cancelled) {
      setAnnouncement(labels.hubv2_movecancelled);
    }
  }, [labels]);
  const loadInsights = useCallback(() => {
    const ticket = insightsAsk.begin();
    insightsStatus.current = "loading";
    setInsights((current) => ({ status: "loading", index: current.index }));
    return track("insights", readInsights(framework.id)).then((answer) => {
      if (!insightsAsk.current(ticket)) {
        return void 0;
      }
      const index2 = indexInsights(answer);
      indexRef.current = index2;
      insightsStatus.current = "ready";
      setInsights({ status: "ready", index: index2 });
      findingAtRef.current = null;
      setFindingAt(null);
      return void 0;
    }, () => {
      if (insightsAsk.current(ticket)) {
        insightsStatus.current = "failed";
        setInsights((current) => ({ status: "failed", index: current.index }));
      }
      return void 0;
    });
  }, [framework.id, insightsAsk]);
  const ensureInsights = useCallback(() => {
    const status = insightsStatus.current;
    return status === "idle" || status === "failed" ? loadInsights() : Promise.resolve();
  }, [loadInsights]);
  const settled = useCallback((id) => {
    if (specOf(id).changes && insightsStatus.current !== "idle") {
      loadInsights().catch(notifyError);
    }
    if (specOf(id).changes && selectedRef.current !== null) {
      loadSections(selectedRef.current, true);
    }
  }, [loadInsights, loadSections]);
  const need = useCallback((ids) => {
    const missing = ids.filter((id) => id > 0 && !treeRef.current.nodes[id] && !naming.current.has(id) && namedPaths.current[id] === void 0);
    for (let start = 0; start < missing.length; start += NAME_BATCH) {
      const batch = missing.slice(start, start + NAME_BATCH);
      batch.forEach((id) => naming.current.add(id));
      track("names", getNodes(batch)).then((answers) => {
        const found = {};
        answers.forEach((answer, position) => {
          const id = batch[position];
          found[id] = answer.found && answer.node ? answer.node.shortname : "-";
          namedPaths.current[id] = answer.pathids ?? [];
        });
        setNames((current) => ({ ...current, ...found }));
        return null;
      }).finally(() => batch.forEach((id) => naming.current.delete(id))).catch(notifyError);
    }
  }, []);
  const setImpact = useCallback((next) => {
    setImpactState((current) => sameImpact(current, next) ? current : next);
  }, []);
  const onMoveTarget = useCallback((target) => {
    const source = moveDialogRef.current !== null ? treeRef.current.nodes[moveDialogRef.current] : void 0;
    setImpact(source && target !== null ? moveImpact(source.parentid, target) : NO_IMPACT);
  }, [setImpact]);
  const panTo = /* @__PURE__ */ __name((key) => {
    const position = graphRef.current?.positions[key];
    const canvas = measureCanvas();
    if (!position || !canvas) {
      return;
    }
    const target = panTarget({ ...position, ...CARD }, flow.getViewport(), canvas.view, canvas.obstacles);
    if (target) {
      flow.setCenter(target.x, target.y, { zoom: flow.getZoom(), duration: motionDuration(200) }).catch(() => null);
    }
  }, "panTo");
  const measureCanvas = /* @__PURE__ */ __name(() => {
    const element = canvasRef.current?.querySelector(".react-flow");
    if (!element) {
      return null;
    }
    const box = element.getBoundingClientRect();
    const obstacles = [...element.querySelectorAll(OVERLAYS)].map((overlay) => {
      const rect = overlay.getBoundingClientRect();
      return { x: rect.left - box.left, y: rect.top - box.top, width: rect.width, height: rect.height };
    });
    return { view: { width: box.width, height: box.height }, obstacles };
  }, "measureCanvas");
  const order = useMemo(() => navOrder(tree), [tree]);
  const rows = useMemo(() => outlineRows(tree, framework.shortname), [tree, framework.shortname]);
  const rowsRef = useRef(rows);
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
    keyOf: /* @__PURE__ */ __name((element) => element.dataset.id, "keyOf"),
    /**
     * A node's element on the map.
     *
     * @param {string} key The node.
     * @returns {HTMLElement|null} Its wrapper, when it is drawn.
     */
    find: /* @__PURE__ */ __name((key) => canvasRef.current?.querySelector(`${NODE_SELECTOR}[data-id="${key}"]`) ?? null, "find"),
    /**
     * Bring a node into view before it takes the focus.
     *
     * @param {string} key The node.
     * @param {boolean} pan Whether the viewport may move.
     * @returns {void}
     */
    beforeFocus: /* @__PURE__ */ __name((key, pan) => {
      if (pan) {
        panTo(key);
      }
    }, "beforeFocus")
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
    keyOf: /* @__PURE__ */ __name((element) => element.dataset.key, "keyOf"),
    /**
     * An item's row in the outline.
     *
     * @param {string} key The item.
     * @returns {HTMLElement|null} Its row, when it is drawn.
     */
    find: /* @__PURE__ */ __name((key) => listRef.current?.querySelector(`[data-key="${key}"]`) ?? null, "find")
  });
  const focusItem = /* @__PURE__ */ __name((key, pan = true) => {
    if (panesRef.current.outline) {
      outlineStop.focusKey(key);
    } else {
      mapStop.focusKey(key, pan);
    }
  }, "focusItem");
  const freshParent = useCallback(async (id) => {
    if (id === ROOT) {
      return null;
    }
    const answer = await getNode(id);
    return answer.found && answer.node ? answer.node : null;
  }, []);
  const moveUnder = /* @__PURE__ */ __name((source, target, confirm) => {
    if (target !== ROOT && isWithin(treeRef.current, target, source.id)) {
      announce(labels.hubv2_moveinvalid);
      return;
    }
    if (target === source.parentid) {
      return;
    }
    (async () => {
      if (confirm) {
        const parents = Promise.all([freshParent(source.parentid), freshParent(target)]);
        const [oldparent, newparent] = await track("move", parents);
        const known = target === ROOT ? null : { shortname: treeRef.current.nodes[target]?.shortname ?? "", ruletype: null };
        const question = moveBody(labels, source.shortname, oldparent, newparent ?? known);
        const notification = await load("core/notification");
        setImpact(moveImpact(source.parentid, target));
        const confirmed = await notification.saveCancelPromise(labels.hubv2_movetitle, question, labels.hubv2_move).then(() => true, () => false).finally(() => setImpact(NO_IMPACT));
        if (!confirmed) {
          return;
        }
      }
      endMove(false);
      await track("move", (async () => {
        await setParent(source.id, target);
        const answer = await getNode(source.id);
        if (answer.found) {
          await relocate(source.id, source.parentid, answer);
        }
        announce(fill(labels.hubv2_moved, source.shortname));
        settled("moveto");
      })());
    })().catch(notifyError);
  }, "moveUnder");
  const pickParent = /* @__PURE__ */ __name((target) => {
    const source = movingRef.current !== null ? treeRef.current.nodes[movingRef.current] : void 0;
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
  }, "pickParent");
  const activate = /* @__PURE__ */ __name((id, from) => {
    const action = activation(movingRef.current, id);
    if (action.type === "pick") {
      pickParent(action.parent);
      return;
    }
    choose(action.id);
    if (from === "outline" && panesRef.current.map) {
      panTo(action.id === null ? FRAMEWORK_ID : competencyKey(action.id));
    }
  }, "activate");
  const onNodesChange = /* @__PURE__ */ __name((changes) => {
    const sizes = {};
    changes.getByType("dimensions").forEach((change) => {
      if (change.dimensions) {
        sizes[change.id] = change.dimensions;
      }
    });
    if (Object.keys(sizes).length > 0) {
      setMeasured((current) => ({ ...current, ...sizes }));
    }
    const selections = changes.getByType("select");
    const picked2 = selections.find((change) => change.selected);
    if (picked2) {
      const id = picked2.id === FRAMEWORK_ID ? ROOT : competencyOf(picked2.id);
      if (id === null) {
        return;
      }
      activate(id, "map");
      return;
    }
    const unselected = selections.some((change) => !change.selected);
    if (unselected && movingRef.current === null) {
      choose(null);
    }
  }, "onNodesChange");
  const openForm = /* @__PURE__ */ __name((node2, parent) => {
    const args = node2 ? { competencyframeworkid: framework.id, id: node2.id, parentid: node2.parentid } : { competencyframeworkid: framework.id, id: 0, parentid: parent ? parent.id : ROOT };
    const title = labels[node2 ? "tool_lp:editcompetency" : "tool_lp:addcompetency"];
    (async () => {
      const ModalForm = await load("core_form/modalform");
      const form = new ModalForm({ formClass: FORM_CLASS, args, modalConfig: { title } });
      form.addEventListener(form.events.FORM_SUBMITTED, (event) => {
        if (node2) {
          track("refresh", refreshNode(node2.id)).then(() => settled("edit")).catch(notifyError);
          return;
        }
        const newid = Number(event.detail?.competencyid) || 0;
        const parentid = parent ? parent.id : ROOT;
        track("refresh", (async () => {
          await refreshParent(parentid);
          if (hasBranch(treeRef.current, parentid)) {
            await reloadBranch(parentid);
          }
          setCount(await countCompetencies(framework.id));
          const answer = newid ? await getNode(newid) : { found: false };
          if (answer.found) {
            await reveal(newid, answer.pathids ?? []);
          }
          settled("addchild");
        })()).catch(notifyError);
      });
      await form.show();
    })().catch(notifyError);
  }, "openForm");
  const storeRule = /* @__PURE__ */ __name((node2, config) => track("refresh", updateRule(node2.id, config).then(() => refreshNode(node2.id))).then(() => {
    announce(labels["core:changessaved"]);
    settled("rule");
    return void 0;
  }), "storeRule");
  const editRule = /* @__PURE__ */ __name((node2) => {
    (async () => {
      const children = await track("rule", allChildren(framework.id, node2.id, pagesize));
      const module = await load("local_dimensions/central/rule_config");
      const config = await module.show({
        id: node2.id,
        ruletype: node2.ruletype ?? "",
        ruleoutcome: node2.ruleoutcome,
        ruleconfig: node2.ruleconfig ?? ""
      }, children, props.rulesmodules);
      if (config) {
        await storeRule(node2, config);
      }
    })().catch(notifyError);
  }, "editRule");
  const openLinks = /* @__PURE__ */ __name((node2) => {
    const onClose = /* @__PURE__ */ __name(() => {
      track("refresh", refreshNode(node2.id)).then(() => settled("links")).catch(notifyError);
    }, "onClose");
    load("local_dimensions/central/competency_links").then((module) => module.open({
      competencyid: node2.id,
      competencyname: node2.shortname,
      courseoutcomes: props.courseoutcomes,
      moduleoutcomes: props.moduleoutcomes,
      onClose
    })).catch(notifyError);
  }, "openLinks");
  const openRelated = /* @__PURE__ */ __name((node2) => {
    relating.current = node2.id;
    load("local_dimensions/central/related_competencies").then((module) => module.open({ competencyid: node2.id, competencyname: node2.shortname, frameworkid: framework.id })).catch(notifyError);
  }, "openRelated");
  const startMove = /* @__PURE__ */ __name((node2) => {
    movingRef.current = node2.id;
    setMoving(node2.id);
    announce(fill(labels.hubv2_movepick, node2.shortname));
    focusItem(competencyKey(node2.id));
  }, "startMove");
  const stepMove = /* @__PURE__ */ __name((node2, up) => {
    track("refresh", (async () => {
      await moveOne(node2.id, up);
      const index2 = treeRef.current.branches[node2.parentid]?.ids.indexOf(node2.id) ?? -1;
      await reloadBranch(node2.parentid, index2 + (up ? 1 : 2));
      const branch = treeRef.current.branches[node2.parentid];
      const values = {
        name: node2.shortname,
        position: String((branch?.ids.indexOf(node2.id) ?? -1) + 1),
        total: String(branch?.total ?? 0)
      };
      announce(fillObject(up ? labels.hubv2_reordered_up : labels.hubv2_reordered_down, values));
    })()).catch(notifyError);
  }, "stepMove");
  const remove = /* @__PURE__ */ __name((node2) => {
    (async () => {
      const notification = await load("core/notification");
      removing.current = node2.id;
      const showBlocked = /* @__PURE__ */ __name(async (check2) => {
        removing.current = null;
        await notification.alert(labels.hubv2_delete_blocked_title, blockedBody(labels, node2.shortname, check2));
      }, "showBlocked");
      const check = await track("refresh", deleteCheck(node2.id)).catch((error) => {
        removing.current = null;
        throw error;
      });
      if (!check.candelete) {
        await showBlocked(check);
        return;
      }
      setImpact(deleteImpact(treeRef.current, node2.id));
      const confirmed = await notification.deleteCancelPromise(labels.hubv2_delete, confirmBody(labels, node2.shortname, check)).then(() => true, () => false).finally(() => setImpact(NO_IMPACT));
      if (!confirmed) {
        removing.current = null;
        return;
      }
      await track("refresh", (async () => {
        const done = await deleteCompetency(node2.id).catch((error) => {
          removing.current = null;
          throw error;
        });
        if (done === false) {
          const again = await deleteCheck(node2.id).catch(() => null);
          if (again && !again.candelete) {
            await showBlocked(again);
          } else {
            removing.current = null;
            await notification.alert(
              null,
              fill(labels["tool_lp:competencycannotbedeleted"], escapeHtml(node2.shortname))
            );
          }
          return;
        }
        commit((current) => removeNode(current, node2.id));
        choose(null);
        await reloadBranch(node2.parentid);
        await refreshParent(node2.parentid);
        setCount(await countCompetencies(framework.id));
        settled("delete");
      })());
    })().catch(notifyError);
  }, "remove");
  const updatePicked = /* @__PURE__ */ __name((update) => {
    pickedRef.current = update(pickedRef.current);
    setPicked(pickedRef.current);
  }, "updatePicked");
  const pick = /* @__PURE__ */ __name((id) => {
    if (!multiselect || movingRef.current !== null || !treeRef.current.nodes[id]) {
      return;
    }
    updatePicked((current) => togglePicked(current, id));
  }, "pick");
  const clearPicked = /* @__PURE__ */ __name(() => {
    updatePicked(() => NONE);
    announce(labels.hubv2_bulk_cleared);
  }, "clearPicked");
  const checkDeletion = /* @__PURE__ */ __name(() => {
    const ids = topmost(treeRef.current, pickedRef.current.ids);
    setBulk({ kind: "delete", ids, summary: null });
    track("bulk", deleteChecks(ids)).then((checks) => {
      const summary = deletionSummary(labels, ids.map((id, index2) => ({
        id,
        name: treeRef.current.nodes[id]?.shortname ?? "",
        check: checks[index2] ?? null
      })));
      setBulk((current) => current && current.kind === "delete" && current.ids === ids ? { ...current, summary } : current);
      setImpact(new Set(summary.allowed));
      return null;
    }).catch((error) => {
      setBulk(null);
      notifyError(error);
    });
  }, "checkDeletion");
  const closeBulk = /* @__PURE__ */ __name(() => {
    setBulk(null);
    setImpact(NO_IMPACT);
  }, "closeBulk");
  const rereadParent = /* @__PURE__ */ __name(async (parent, atleast = 1) => {
    if (hasBranch(treeRef.current, parent)) {
      await reloadBranch(parent, atleast);
    }
    await refreshParent(parent);
  }, "rereadParent");
  const commitAppearance = /* @__PURE__ */ __name(async (fields) => {
    const ids = pickedRef.current.ids;
    try {
      const answer = await track("bulk", setAppearance(framework.id, ids, fields));
      const names2 = APPEARANCE_FIELDS;
      commit((current) => answer.competencies.reduce((next, item) => {
        const known = next.nodes[item.id];
        if (!known) {
          return next;
        }
        const fresh = { ...known };
        item.fields.filter((field) => names2.includes(field.name)).forEach((field) => {
          fresh[field.name] = field.value;
        });
        return replaceNode(next, fresh);
      }, current));
      setBulk(null);
      updatePicked(() => NONE);
      announce(appearanceOutcome(labels, answer.changed.length));
      settled("bulkappearance");
      return null;
    } catch (error) {
      return messageOf(error);
    }
  }, "commitAppearance");
  const bulkMove = /* @__PURE__ */ __name((target) => {
    closeBulk();
    const plan = movePlan(treeRef.current, pickedRef.current.ids, target);
    track("bulk", (async () => {
      const result = { moved: 0, skipped: plan.skipped.length, refused: null, left: 0 };
      const moved = [];
      const parents = /* @__PURE__ */ new Set();
      let failed = null;
      for (let index2 = 0; index2 < plan.move.length; index2++) {
        const id = plan.move[index2];
        const node2 = treeRef.current.nodes[id];
        announce(fillObject(labels.hubv2_bulk_move_progress, {
          position: String(index2 + 1),
          total: String(plan.move.length)
        }));
        try {
          await setParent(id, target);
        } catch (error) {
          failed = id;
          result.refused = { name: node2?.shortname ?? "", reason: messageOf(error) };
          result.left = plan.move.length - index2 - 1;
          break;
        }
        moved.push(id);
        parents.add(node2?.parentid ?? ROOT);
      }
      result.moved = moved.length;
      moved.forEach((id) => commit((current) => forgetBranch(current, id)));
      for (const parent of parents) {
        await rereadParent(parent);
      }
      if (moved.length > 0) {
        await rereadParent(target, (treeRef.current.branches[target]?.ids.length ?? 0) + moved.length);
        if (hasBranch(treeRef.current, target)) {
          commit((current) => setExpanded(current, target, true));
        }
      }
      if (selectedRef.current !== null && !treeRef.current.nodes[selectedRef.current]) {
        choose(null);
      }
      const stopped = failed;
      updatePicked((current) => stopped === null ? NONE : keepOnly(current, plan.move.slice(plan.move.indexOf(stopped))));
      announce(moveOutcome(labels, result));
      if (moved.length > 0) {
        settled("bulkmove");
      }
    })()).catch(notifyError);
  }, "bulkMove");
  const bulkDelete = /* @__PURE__ */ __name((summary) => {
    closeBulk();
    track("bulk", (async () => {
      const deleted = [];
      const failed = [];
      const parents = /* @__PURE__ */ new Set();
      for (const id of summary.allowed) {
        const node2 = treeRef.current.nodes[id];
        const done = await deleteCompetency(id).catch(() => false);
        if (done === false) {
          failed.push(id);
          continue;
        }
        deleted.push(id);
        parents.add(node2?.parentid ?? ROOT);
        commit((current) => removeNode(current, id));
      }
      for (const parent of parents) {
        await rereadParent(parent);
      }
      setCount(await countCompetencies(framework.id));
      if (selectedRef.current !== null && !treeRef.current.nodes[selectedRef.current]) {
        choose(null);
      }
      const kept = [...summary.refused.map((item) => item.id), ...failed];
      const names2 = kept.map((id) => treeRef.current.nodes[id]?.shortname ?? summary.refused.find((item) => item.id === id)?.name ?? "");
      updatePicked((current) => kept.length === 0 ? NONE : keepOnly(current, kept));
      announce(deleteOutcome(labels, deleted.length, names2));
      if (deleted.length > 0) {
        settled("bulkdelete");
      }
    })()).catch(notifyError);
  }, "bulkDelete");
  const runBulkAction = /* @__PURE__ */ __name((id) => {
    setMenu(null);
    if (id === "bulkappearance" || id === "bulkmove") {
      setBulk({ kind: id === "bulkappearance" ? "appearance" : "move" });
    } else if (id === "bulkdelete") {
      checkDeletion();
    }
  }, "runBulkAction");
  const revealItem = /* @__PURE__ */ __name((item) => {
    track("reveal", reveal(item.id, item.pathids)).catch(notifyError);
  }, "revealItem");
  const revealId = /* @__PURE__ */ __name((id) => {
    if (id === ROOT) {
      choose(null);
      focusItem(FRAMEWORK_ID);
      return Promise.resolve();
    }
    let path;
    if (treeRef.current.nodes[id]) {
      path = Promise.resolve(ancestors(treeRef.current, id));
    } else if (namedPaths.current[id] !== void 0) {
      path = Promise.resolve(namedPaths.current[id]);
    } else {
      path = getNode(id).then((answer) => answer.found ? answer.pathids ?? [] : null);
    }
    return track("reveal", path.then((pathids) => pathids === null ? void 0 : reveal(id, pathids)));
  }, "revealId");
  const revealListed = /* @__PURE__ */ __name((id) => {
    revealId(id).catch(notifyError);
  }, "revealListed");
  const revealFinding = /* @__PURE__ */ __name(async (position, ordered) => {
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
    const name = check.competencyid === ROOT ? framework.shortname : treeRef.current.nodes[check.competencyid]?.shortname ?? "";
    announce(fillObject(labels.hubv2_checker_position, {
      position: String(position + 1),
      total: String(ordered.length),
      title: codeTitle(labels, check.code),
      name
    }));
  }, "revealFinding");
  const openChecker = /* @__PURE__ */ __name(() => {
    setMenu(null);
    setChecker(true);
    ensureInsights().catch(notifyError);
    window.requestAnimationFrame(() => checkerHeading.current?.focus());
  }, "openChecker");
  const closeChecker = /* @__PURE__ */ __name(() => {
    setChecker(false);
    checkButton.current?.focus();
  }, "closeChecker");
  const stepToFinding = /* @__PURE__ */ __name((forward) => {
    track("finding", (async () => {
      setMenu(null);
      setChecker(true);
      if (!indexRef.current) {
        await ensureInsights();
      }
      const index2 = indexRef.current;
      if (!index2) {
        return;
      }
      const ordered = orderFindings(index2.answer.checks);
      const next = stepFinding(ordered.length, findingAtRef.current, forward);
      if (next === null) {
        announce(labels.hubv2_checker_none);
        return;
      }
      await revealFinding(next, ordered);
    })()).catch(notifyError);
  }, "stepToFinding");
  const changeLens = /* @__PURE__ */ __name((next) => {
    setLens(next);
    lensRef.current = next;
    rememberLens(next);
    const pane = rootRef.current?.closest("[data-tab-content]");
    if (pane) {
      pane.dataset.lens = next;
    }
    load("local_dimensions/central/preferences").then((preferences) => {
      preferences.saveDisplay({ structure: { lens: next } });
      return null;
    }).catch(notifyError);
    if (next !== "none") {
      ensureInsights().catch(notifyError);
    }
  }, "changeLens");
  const runOn = /* @__PURE__ */ __name((action, id) => {
    const run = /* @__PURE__ */ __name(() => {
      runAction(action, id);
    }, "run");
    if (treeRef.current.nodes[id]) {
      run();
      return;
    }
    holdChecker.current = true;
    revealId(id).then(run).finally(() => {
      holdChecker.current = false;
    }).catch(notifyError);
  }, "runOn");
  const loadMoreAndFocus = /* @__PURE__ */ __name((parent, stop) => {
    nextPage(parent).then((first) => {
      if (first !== null) {
        stop.focusKey(competencyKey(first));
      }
      return null;
    }).catch(notifyError);
  }, "loadMoreAndFocus");
  const typeTo = /* @__PURE__ */ __name((character, time) => {
    const previous = typed.current;
    const text = time - previous.at > TYPE_AHEAD_PAUSE ? character : previous.text + character;
    typed.current = { text, at: time };
    const key = typeAhead(rowsRef.current, outlineStop.activeRef.current, text);
    if (key !== null) {
      outlineStop.focusKey(key);
    }
    return key !== null;
  }, "typeTo");
  const expandAll = /* @__PURE__ */ __name(() => {
    const run = ++expandRun.current;
    setExpanding(true);
    setNotice("");
    track("expandall", (async () => {
      let capped = false;
      for (let round = 0; round < 100 && run === expandRun.current; round++) {
        const step = expandStep(treeRef.current, EXPAND_ALL_BOUND, pagesize);
        capped = step.capped;
        for (let index2 = 0; index2 < step.load.length && run === expandRun.current; index2 += EXPAND_ALL_PARALLEL) {
          const batch = step.load.slice(index2, index2 + EXPAND_ALL_PARALLEL).filter((id) => !hasBranch(treeRef.current, id));
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
  }, "expandAll");
  const collapseEverything = /* @__PURE__ */ __name(() => {
    expandRun.current++;
    setExpanding(false);
    setNotice("");
    commit(collapseAll);
  }, "collapseEverything");
  const contextFor = /* @__PURE__ */ __name((id) => {
    const current = treeRef.current;
    const target = id !== null && id !== ROOT ? current.nodes[id] ?? null : null;
    const siblings = target ? current.branches[target.parentid] : void 0;
    const index2 = target && siblings ? siblings.ids.indexOf(target.id) : -1;
    return {
      node: target,
      frameworkcanmanage: framework.canmanage,
      moving: movingRef.current !== null ? current.nodes[movingRef.current] ?? null : null,
      first: index2 <= 0,
      last: !siblings || index2 < 0 || index2 === siblings.ids.length - 1 && siblings.ids.length >= siblings.total,
      layout: layoutRef.current,
      empty: countRef.current === 0,
      expanding: expandingRef.current,
      frameworkvisible: framework.visible,
      cancreate: props.structures.cancreate,
      lens: lensRef.current,
      usagelevel: indexRef.current ? indexRef.current.answer.usagelevel : null,
      findings: indexRef.current ? indexRef.current.answer.checks.length : null,
      picked: pickedRef.current.ids.length
    };
  }, "contextFor");
  const switchLayout = /* @__PURE__ */ __name((next) => {
    const region = rootRef.current?.closest('[data-region="structure"]');
    const button = region?.querySelector(`[data-action="switch-view"][data-view="${next}"]`);
    if (button) {
      button.click();
    } else {
      setLayout(next);
    }
  }, "switchLayout");
  const showSummary = /* @__PURE__ */ __name(() => {
    choose(null);
    window.requestAnimationFrame(() => summaryRef.current?.focus());
  }, "showSummary");
  const reach = /* @__PURE__ */ __name((id) => ensureVisible(id, ancestors(treeRef.current, id)), "reach");
  const startCreate = /* @__PURE__ */ __name((parent, after) => {
    track("editor", (async () => {
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
      setEditor({ mode: "create", parent, after, serial: editorSerial.current });
    })()).catch(notifyError);
  }, "startCreate");
  const startRename = /* @__PURE__ */ __name((id) => {
    track("editor", reach(id).then(() => setEditor({ mode: "rename", id }))).catch(notifyError);
  }, "startRename");
  const closeEditor = /* @__PURE__ */ __name(() => {
    const current = editorRef.current;
    setEditor(null);
    if (!current) {
      return;
    }
    if (current.mode === "rename") {
      outlineStop.focusKey(competencyKey(current.id));
    } else {
      outlineStop.focusKey(current.after !== null ? competencyKey(current.after) : parentKey(current.parent));
    }
  }, "closeEditor");
  const saveCreate = /* @__PURE__ */ __name(async (parent, after, name, idnumber) => {
    try {
      const created = await track("editor", (async () => {
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
        settled("quicksibling");
        return id;
      })());
      editorSerial.current++;
      setEditor({ mode: "create", parent, after: created, serial: editorSerial.current });
      return null;
    } catch (error) {
      return messageOf(error);
    }
  }, "saveCreate");
  const saveRename = /* @__PURE__ */ __name(async (id, name) => {
    try {
      await track("editor", renameCompetency(id, name).then(() => refreshNode(id)));
      closeEditor();
      announce(labels["core:changessaved"]);
      return null;
    } catch (error) {
      return messageOf(error);
    }
  }, "saveRename");
  const runNodeAction = /* @__PURE__ */ __name((id, target) => {
    switch (id) {
      case "edit":
        openForm(target, null);
        break;
      case "rename":
        startRename(target.id);
        break;
      case "addchild":
        openForm(null, target);
        break;
      case "quicksibling":
        startCreate(target.parentid, target.id);
        break;
      case "quickchild":
        startCreate(target.id, null);
        break;
      case "moveto":
        setMoveDialog(target.id);
        break;
      case "movepick":
        startMove(target);
        break;
      case "moveup":
      case "movedown":
        stepMove(target, id === "moveup");
        break;
      case "rule":
        editRule(target);
        break;
      case "links":
        openLinks(target);
        break;
      case "relate":
        openRelated(target);
        break;
      case "delete":
        remove(target);
        break;
      default:
        break;
    }
  }, "runNodeAction");
  const runFrameworkAction = /* @__PURE__ */ __name((id) => {
    const pane = rootRef.current?.closest("[data-tab-content]") ?? null;
    track("framework", (async () => {
      const [frameworks, tab] = await Promise.all([
        load("local_dimensions/central/frameworks"),
        load("local_dimensions/central/structure")
      ]);
      const show = /* @__PURE__ */ __name((frameworkid, keep) => {
        if (!pane) {
          return;
        }
        if (keep) {
          hold(carryOf(framework.id, treeRef.current, selectedRef.current));
        }
        tab.showFramework(pane, frameworkid).catch(notifyError);
      }, "show");
      const again = /* @__PURE__ */ __name(() => show(framework.id, true), "again");
      const canscalespage = props.structures.canscalespage;
      switch (id) {
        case "fwedit":
        case "fwscale":
          await frameworks.editFramework(framework.id, { onSaved: again, canscalespage, scale: id === "fwscale" });
          break;
        case "fwhide":
        case "fwshow":
          await frameworks.setVisibility(framework.id, id === "fwshow");
          again();
          break;
        case "fwduplicate": {
          await frameworks.duplicate(framework.id);
          const toast = await load("local_dimensions/central/toast");
          toast.add(labels.hubv2_fw_duplicated, { type: "success" }).catch(notifyError);
          again();
          break;
        }
        case "fwexport":
          await frameworks.exportFramework(framework.id);
          break;
        case "fwdelete": {
          const deleted = await frameworks.confirmDelete({
            id: framework.id,
            name: framework.shortname,
            count: countRef.current,
            deletable: framework.deletable
          });
          if (deleted) {
            show(tab.nextFramework(framework.id), false);
          }
          break;
        }
        case "fwnew":
          await frameworks.createFramework(props.structures.contextid, {
            /**
             * Shows the framework that was just created.
             *
             * @param {number} newid The new framework's id.
             * @returns {void}
             */
            onSaved: /* @__PURE__ */ __name((newid) => show(newid, false), "onSaved"),
            canscalespage
          });
          break;
        case "fwimport":
          await frameworks.importFramework(props.structures.contextid, (newid) => show(newid, false));
          break;
        default:
          break;
      }
    })()).catch(notifyError);
  }, "runFrameworkAction");
  const runWorkspaceAction = /* @__PURE__ */ __name((id) => {
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
      case "check":
        openChecker();
        break;
      case "nextfinding":
      case "prevfinding":
        stepToFinding(id === "nextfinding");
        break;
      case "moveroot":
        pickParent(ROOT);
        break;
      case "movecancel":
        endMove(true);
        break;
      case "expandall":
        expandAll();
        break;
      case "collapseall":
        collapseEverything();
        break;
      case "addtop":
        openForm(null, null);
        break;
      case "summary":
        showSummary();
        break;
      default:
        break;
    }
  }, "runWorkspaceAction");
  const runAction = /* @__PURE__ */ __name((id, targetid) => {
    const context = contextFor(targetid);
    if (actionState(id, context) !== "enabled") {
      return;
    }
    const scope = specOf(id).scope;
    if (scope === "node" && context.node) {
      runNodeAction(id, context.node);
    } else if (scope === "framework" || scope === "structures") {
      runFrameworkAction(id);
    } else if (scope === "bulk") {
      runBulkAction(id);
    } else {
      runWorkspaceAction(id);
    }
  }, "runAction");
  const menuActions = /* @__PURE__ */ __name((id) => id === ROOT ? actionsFor(["framework"], contextFor(null)) : actionsFor(["node"], contextFor(id)), "menuActions");
  const openMenu = /* @__PURE__ */ __name((id, anchor, origin) => {
    if (menuActions(id).length === 0) {
      return false;
    }
    setMenu((current) => current && current.id === id && current.anchor === anchor ? null : { id, anchor, origin });
    return true;
  }, "openMenu");
  const giveFocusBack = /* @__PURE__ */ __name((origin, control, key) => {
    const target = focusReturn(origin, lastPane.current, panesRef.current);
    if (target.kind === "control" && control) {
      control.focus();
    } else if (target.kind === "item" && target.pane === "outline") {
      outlineStop.focusKey(key);
    } else if (target.kind === "item") {
      mapStop.focusKey(key, false);
    }
  }, "giveFocusBack");
  const closeMenu = /* @__PURE__ */ __name((outside = false) => {
    const current = menu;
    if (!current) {
      return;
    }
    setMenu(null);
    const origin = current.origin === "panel" ? { kind: "control", connected: current.anchor.isConnected } : { kind: "item", pane: current.origin };
    const back = /* @__PURE__ */ __name(() => giveFocusBack(origin, current.anchor, current.id === ROOT ? FRAMEWORK_ID : competencyKey(current.id)), "back");
    if (!outside) {
      back();
      return;
    }
    document.addEventListener("pointerup", () => {
      window.setTimeout(() => {
        const active = document.activeElement;
        if (!active || active === document.body) {
          back();
        }
      }, 0);
    }, { once: true, capture: true });
  }, "closeMenu");
  const rootCommand = /* @__PURE__ */ __name((command, element, from) => {
    const allowed = framework.canmanage && movingRef.current === null && layoutRef.current !== "map";
    if (command === "quickchild" && from === "outline" && allowed) {
      startCreate(ROOT, null);
      return true;
    }
    if (command === "menu" && menuActions(ROOT).length > 0) {
      if (selectedRef.current !== null) {
        choose(null);
      }
      setMenu({ id: ROOT, anchor: element, origin: from });
      return true;
    }
    return false;
  }, "rootCommand");
  const keyCommand = /* @__PURE__ */ __name((command, key, element, from) => {
    if (key === FRAMEWORK_ID) {
      return rootCommand(command, element, from);
    }
    const id = competencyOf(key);
    if (id === null || command === "palette") {
      return false;
    }
    if (command === "menu") {
      if (actionsFor(["node"], contextFor(id)).length === 0) {
        return false;
      }
      if (selectedRef.current !== id) {
        choose(id);
      }
      setMenu({ id, anchor: element, origin: from });
      return true;
    }
    const context = contextFor(id);
    const state = actionState(command, context);
    if ((command === "moveup" || command === "movedown") && state === "disabled" && context.node) {
      announce(fill(command === "moveup" ? labels.hubv2_reorder_first : labels.hubv2_reorder_last, context.node.shortname));
      return true;
    }
    if (state !== "enabled") {
      return false;
    }
    runAction(command, id);
    return true;
  }, "keyCommand");
  const onRootKeyDown = /* @__PURE__ */ __name((event) => {
    if (event.key === "Escape" && pickedRef.current.ids.length > 0 && menu === null && !palette && moveDialog === null && bulk === null && editorRef.current === null && movingRef.current === null) {
      event.preventDefault();
      clearPicked();
      return;
    }
    const command = shortcutAction(event, "map");
    if (palette || moveDialog !== null || bulk !== null) {
      return;
    }
    if (command === "nextfinding" || command === "prevfinding") {
      if (menu === null) {
        event.preventDefault();
        stepToFinding(command === "nextfinding");
      }
      return;
    }
    if (command !== "palette") {
      return;
    }
    event.preventDefault();
    setMenu(null);
    setPalette(true);
  }, "onRootKeyDown");
  const restoreFocus = /* @__PURE__ */ __name(() => {
    giveFocusBack({ kind: "layer" }, null, entryKey(treeRef.current, navOrder(treeRef.current), selectedRef.current));
  }, "restoreFocus");
  const toggleDisplay = /* @__PURE__ */ __name((option) => {
    const next = { ...display, [option]: !display[option] };
    setDisplay(next);
    load("local_dimensions/central/preferences").then((preferences) => {
      preferences.saveDisplay({ structure: option === "idnumber" ? { id: next.idnumber } : { tax: next.taxonomy } });
      return null;
    }).catch(notifyError);
  }, "toggleDisplay");
  const pickKey = /* @__PURE__ */ __name((event, stop) => {
    if (!multiselect || movingRef.current !== null) {
      return false;
    }
    const key = stop.activeRef.current;
    const id = competencyOf(key);
    const gesture = pickGesture(event);
    if (gesture === "toggle" && id !== null) {
      pick(id);
      return true;
    }
    if (gesture === "up" || gesture === "down") {
      extendTo(stop, key, id, gesture === "down");
      return true;
    }
    if (gesture === "level") {
      updatePicked((current) => pickLevel(current, navOrder(treeRef.current), key));
      return true;
    }
    return false;
  }, "pickKey");
  const extendTo = /* @__PURE__ */ __name((stop, key, from, down) => {
    const order2 = navOrder(treeRef.current);
    const index2 = order2.findIndex((item) => item.key === key);
    const next = index2 < 0 ? void 0 : order2[index2 + (down ? 1 : -1)];
    if (!next) {
      return;
    }
    stop.focusKey(next.key);
    const to = competencyOf(next.key);
    if (to !== null) {
      updatePicked((current) => extend(current, order2, from, to));
    }
  }, "extendTo");
  const ownKey = /* @__PURE__ */ __name((event, element, stop) => {
    if (element !== event.target && (event.key === "Enter" || event.key === " ")) {
      return true;
    }
    if (pickKey(event, stop)) {
      event.preventDefault();
      return true;
    }
    return false;
  }, "ownKey");
  const walk = /* @__PURE__ */ __name((event, stop, selector, from) => {
    const target = event.target;
    const element = target.closest(selector);
    if (!element || !event.currentTarget.contains(element)) {
      return;
    }
    if (ownKey(event, element, stop)) {
      return;
    }
    const command = shortcutAction(event, from);
    if (command === "palette" || command === "nextfinding" || command === "prevfinding") {
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
      if (from === "outline" && isTypeAheadKey(event) && typeTo(event.key, event.timeStamp)) {
        event.preventDefault();
      }
      return;
    }
    event.preventDefault();
    switch (action.type) {
      case "focus":
        stop.focusKey(action.key);
        break;
      case "expand":
        openBranch(action.id, from === "map");
        break;
      case "collapse":
        closeBranch(action.id);
        break;
      case "select":
        activate(action.id, from);
        break;
      case "loadmore":
        loadMoreAndFocus(action.parent, stop);
        break;
      default:
        break;
    }
  }, "walk");
  const actions = {
    /**
     * Run one of the panel's actions on the selected competency.
     *
     * @param {string} id The action.
     * @returns {void}
     */
    run: /* @__PURE__ */ __name((id) => runAction(id, selectedRef.current), "run"),
    /**
     * Open the selected competency's menu, or the framework's on the root, under the panel's "More actions"
     * button.
     *
     * @param {HTMLElement} anchor The button.
     * @returns {void}
     */
    menu: /* @__PURE__ */ __name((anchor) => {
      openMenu(selectedRef.current ?? ROOT, anchor, "panel");
    }, "menu"),
    reveal: revealItem
  };
  useEffect(() => {
    load("local_dimensions/central/preferences").then((preferences) => {
      const saved = preferences.getDisplay().structure;
      setDisplay({ idnumber: !!saved.id, taxonomy: !!saved.tax });
      return null;
    }).catch(notifyError);
  }, []);
  useEffect(() => {
    if (!panes.outline) {
      setEditor(null);
    }
  }, [panes.outline]);
  useEffect(() => {
    if (moving === null) {
      return void 0;
    }
    const onKey = /* @__PURE__ */ __name((event) => {
      if (event.key === "Escape") {
        endMove(true);
      }
    }, "onKey");
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, [moving, endMove]);
  useEffect(() => {
    let off = /* @__PURE__ */ __name(() => void 0, "off");
    load("jquery").then(($) => {
      const onHidden = /* @__PURE__ */ __name(() => {
        if (selectedRef.current !== null && selectedRef.current !== removing.current) {
          loadSelection(selectedRef.current);
        }
        if (relating.current !== null) {
          relating.current = null;
          settled("relate");
        }
      }, "onHidden");
      $(document).on(MODAL_HIDDEN, onHidden);
      off = /* @__PURE__ */ __name(() => {
        $(document).off(MODAL_HIDDEN);
      }, "off");
      return null;
    }).catch(notifyError);
    return () => off();
  }, [loadSelection, settled]);
  const moreLabel = useCallback((remaining) => fill(labels.hubv2_loadmore, String(Math.min(remaining, pagesize))), [labels, pagesize]);
  const nameOf = useCallback((info) => nodeName(labels, info), [labels]);
  const frameworkName = useCallback((total) => `${framework.shortname}, ${fillCount(labels, "hubv2_competencies", total)}`, [framework.shortname, labels]);
  useEffect(() => {
    if (lensRef.current !== "none") {
      ensureInsights().catch(notifyError);
      return void 0;
    }
    const timer = window.setTimeout(() => {
      ensureInsights().catch(notifyError);
    }, INSIGHTS_DELAY);
    return () => window.clearTimeout(timer);
  }, [ensureInsights]);
  useEffect(() => () => insightsAsk.drop(), [insightsAsk]);
  const index = insightsState.index;
  const drawn = useMemo(() => drawnSet(tree), [tree]);
  const mappairs = useMemo(
    () => index && lens === "relations" ? pairsOnMap(index.answer.pairs, drawn) : null,
    [index, lens, drawn]
  );
  const nameById = /* @__PURE__ */ __name((id) => {
    if (id === ROOT) {
      return framework.shortname;
    }
    return tree.nodes[id]?.shortname ?? names[id] ?? null;
  }, "nameById");
  const marks = useMemo(() => index ? lensMarks(lens, [...drawn], {
    labels,
    index,
    measure,
    elsewhere: mappairs ? mappairs.elsewhere : /* @__PURE__ */ new Map(),
    /**
     * The name of the parent a child's points belong to.
     *
     * @param {number} id The parent.
     * @returns {string} Its name, empty while unknown.
     */
    nameOf: /* @__PURE__ */ __name((id) => tree.nodes[id]?.shortname ?? names[id] ?? "", "nameOf")
  }) : NO_MARKS, [index, lens, drawn, labels, measure, mappairs, tree, names]);
  const findingsmarked = useMemo(
    () => checker && index ? findingCounts(index.answer.checks) : NO_FINDINGS,
    [checker, index]
  );
  const findingwords = useCallback((total) => fillCount(labels, "hubv2_findings", total), [labels]);
  const decor = useMemo(() => ({
    marks,
    findings: findingsmarked,
    findingwords,
    impact,
    counts: lens === "rules" && index ? (parent, child) => countsChild(index, parent, child) : void 0,
    pairs: mappairs ? mappairs.edges : void 0
  }), [marks, findingsmarked, findingwords, impact, lens, index, mappairs]);
  const relatedids = useMemo(() => related.map((item) => item.id), [related]);
  const pickedset = useMemo(() => new Set(picked.ids), [picked]);
  const graph = useMemo(() => buildGraph({
    tree,
    framework: { ...framework, competencycount: count },
    selected,
    related: relatedids,
    moving,
    labels: {
      roledescription: labels.hubv2_noderole,
      more: moreLabel,
      name: nameOf,
      framework: frameworkName,
      picked: labels.hubv2_bulk_picked
    },
    rtl,
    decor,
    picked: pickedset
  }), [tree, framework, count, selected, relatedids, moving, labels, moreLabel, nameOf, frameworkName, rtl, decor, pickedset]);
  useEffect(() => {
    updatePicked((current) => prune(current, tree));
  }, [tree]);
  const graphRef = useRef(graph);
  graphRef.current = graph;
  const nodes = useMemo(() => graph.nodes.map((node2) => {
    const sized = measured[node2.id] ? { ...node2, measured: measured[node2.id] } : node2;
    return node2.id === mapStop.active ? { ...sized, domAttributes: { ...sized.domAttributes, tabIndex: 0 } } : sized;
  }), [graph, measured, mapStop.active]);
  const linked = useRef(false);
  useEffect(() => {
    const target = props.reveal;
    if (linked.current || !target) {
      return;
    }
    linked.current = true;
    track("reveal", ensureVisible(target.id, target.pathids).then(() => {
      if (treeRef.current.nodes[target.id]) {
        centreOn.current = competencyKey(target.id);
        choose(target.id);
      }
      return void 0;
    })).catch(notifyError);
  }, [props.reveal, ensureVisible, choose]);
  const carried = useRef(take(framework.id));
  useEffect(() => {
    const carry = carried.current;
    carried.current = null;
    if (!carry) {
      return;
    }
    track("restore", (async () => {
      const roots = treeRef.current.branches[ROOT]?.ids.length ?? 0;
      if ((carry.loaded[ROOT] ?? 0) > roots) {
        await reloadBranch(ROOT, carry.loaded[ROOT]);
      }
      for (const { id, pathids } of carry.expanded) {
        await ensureVisible(id, pathids);
        if (!treeRef.current.nodes[id]) {
          continue;
        }
        await reloadBranch(id, carry.loaded[id] ?? 1);
        commit((current) => setExpanded(current, id, true));
      }
      const picked2 = carry.selected;
      if (picked2) {
        await ensureVisible(picked2.id, picked2.pathids);
        if (treeRef.current.nodes[picked2.id]) {
          centreOn.current = competencyKey(picked2.id);
          choose(picked2.id);
        }
      }
    })()).catch(notifyError);
  }, [choose, commit, ensureVisible, reloadBranch]);
  useEffect(() => {
    const key = centreOn.current;
    const position = key ? graph.positions[key] : void 0;
    if (!key || !position) {
      return;
    }
    centreOn.current = null;
    if (panesRef.current.map) {
      const zoom = Math.max(flow.getZoom(), 0.8);
      flow.setCenter(position.x + CARD.width / 2, position.y + CARD.height / 2, { zoom, duration: motionDuration(300) }).catch(() => null);
    }
    focusItem(key, false);
  }, [graph, flow]);
  useEffect(() => {
    const onLayout = /* @__PURE__ */ __name((event) => {
      const next = event.detail?.layout;
      if (isLayout(next)) {
        setLayout(next);
      }
    }, "onLayout");
    document.addEventListener(LAYOUT_EVENT, onLayout);
    return () => document.removeEventListener(LAYOUT_EVENT, onLayout);
  }, []);
  const available = /* @__PURE__ */ __name(() => panesBox.current?.getBoundingClientRect().width ?? 0, "available");
  useEffect(() => {
    if (layout === "both") {
      setOutlineWidth((width) => clampWidth(width, available()));
    }
  }, [layout]);
  const onOutlineWidth = /* @__PURE__ */ __name((width, final) => {
    setOutlineWidth(width);
    if (final) {
      storeWidth(width);
    }
  }, "onOutlineWidth");
  const resetOutlineWidth = /* @__PURE__ */ __name(() => {
    setOutlineWidth(clampWidth(OUTLINE_WIDTH.initial, available()));
    storeWidth(null);
  }, "resetOutlineWidth");
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
    const children = keys.flatMap((key) => graph.positions[key] ? [{ ...graph.positions[key], ...CARD }] : []);
    const target = parent && canvas ? branchTarget({ ...parent, ...CARD }, children, flow.getViewport(), canvas.view, canvas.obstacles, rtl) : null;
    if (!target) {
      done?.();
      return;
    }
    flow.setCenter(target.x, target.y, { zoom: flow.getZoom(), duration: motionDuration(200) }).catch(() => null).finally(() => done?.());
  }, [graph, flow, rtl]);
  const selectedcontext = contextFor(selected);
  const nodeactions = actionsFor(["node"], selectedcontext);
  const frameworkactions = actionsFor(["framework"], selectedcontext);
  const menuFor = menuTarget(selected, nodeactions, frameworkactions);
  const menuOpen = menu ? menu.id : null;
  const openMenuRef = useRef(openMenu);
  openMenuRef.current = openMenu;
  const openMapMenu = useCallback((id, anchor) => {
    openMenuRef.current(id, anchor, "map");
  }, []);
  const viewportListener = useRef(null);
  const subscribeViewport = useCallback((listener) => {
    viewportListener.current = listener;
    return () => {
      if (viewportListener.current === listener) {
        viewportListener.current = null;
      }
    };
  }, []);
  const readViewport = useCallback(() => flow.getViewport(), [flow]);
  const levels = useMemo(() => deepestLevel(tree), [tree]);
  const heads = useMemo(
    () => columnHeads(levels, framework.taxonomies, framework.taxonomyfallback, rtl),
    [levels, framework.taxonomies, framework.taxonomyfallback, rtl]
  );
  const editLevels = /* @__PURE__ */ __name(() => runAction("fwedit", null), "editLevels");
  const pickRef = useRef(pick);
  pickRef.current = pick;
  const pickOnMap = useCallback((id) => pickRef.current(id), []);
  const canvasActions = useMemo(
    () => ({
      labels,
      pagesize,
      rtl,
      toggle: toggleOnMap,
      loadMore,
      menuFor,
      menuOpen,
      openMenu: openMapMenu,
      multiselect,
      pick: pickOnMap
    }),
    [labels, pagesize, rtl, toggleOnMap, loadMore, menuFor, menuOpen, openMapMenu, multiselect, pickOnMap]
  );
  const outlineActions = {
    /**
     * Select the competency of a row; a "load more" row selects nothing.
     *
     * @param {object} row The row.
     * @returns {void}
     */
    activate: /* @__PURE__ */ __name((row) => {
      if (row.kind !== "more") {
        activate(row.id, "outline");
      }
    }, "activate"),
    toggle,
    /**
     * Load a branch's next page and move the focus to its first new item.
     *
     * @param {number} parent The branch.
     * @returns {void}
     */
    loadMore: /* @__PURE__ */ __name((parent) => loadMoreAndFocus(parent, outlineStop), "loadMore"),
    expandAll,
    collapseAll: collapseEverything,
    /**
     * Add a top-level competency.
     *
     * @returns {void}
     */
    add: /* @__PURE__ */ __name(() => runAction("addtop", null), "add"),
    /**
     * Walk the outline with the tree's keys.
     *
     * @param {object} event The key event.
     * @returns {void}
     */
    onKeyDown: /* @__PURE__ */ __name((event) => walk(event, outlineStop, ROW_SELECTOR, "outline"), "onKeyDown"),
    /**
     * Note the outline as the pane that last had the focus, then follow its Tab stop.
     *
     * @param {object} event The focus event.
     * @returns {void}
     */
    onFocus: /* @__PURE__ */ __name((event) => {
      lastPane.current = "outline";
      outlineStop.onFocus(event);
    }, "onFocus"),
    onBlur: outlineStop.onBlur,
    /**
     * Open the selected row's menu under its "more" button.
     *
     * @param {number} id The competency.
     * @param {HTMLElement} anchor The button.
     * @returns {void}
     */
    openMenu: /* @__PURE__ */ __name((id, anchor) => {
      openMenu(id, anchor, "outline");
    }, "openMenu"),
    toggleDisplay,
    pick
  };
  const outlineHints = /* @__PURE__ */ __name(() => {
    const hints = [];
    if (framework.canmanage) {
      const keys = [
        [{ key: "F10", shift: true }, labels.hubv2_hint_actions],
        [{ key: "F2" }, labels.hubv2_cmd_rename],
        [{ key: "Enter", mod: true }, labels.hubv2_cmd_quicksibling],
        [{ key: "Enter", mod: true, shift: true }, labels.hubv2_cmd_quickchild],
        [{ key: "\u2191\u2193", alt: true }, labels.hubv2_hint_reorder]
      ];
      keys.forEach(([shortcut, label]) => hints.push({ shortcut, label }));
    }
    hints.push({ shortcut: PALETTE_SHORTCUT, label: labels.hubv2_commands });
    if (insightsState.index && insightsState.index.answer.checks.length > 0) {
      hints.push({ shortcut: { key: "F8" }, label: labels.hubv2_cmd_nextfinding });
    }
    return hints;
  }, "outlineHints");
  const outlineEditor = /* @__PURE__ */ __name(() => {
    if (editor && editor.mode === "rename") {
      const renamedid = editor.id;
      const renamed = tree.nodes[renamedid];
      const row = rows.find((item) => item.key === competencyKey(renamedid));
      if (renamed && row) {
        return { key: row.key, replace: true, node: /* @__PURE__ */ jsxDEV(
          Editor,
          {
            labels,
            mode: "rename",
            label: fill(labels.hubv2_editor_rename, renamed.shortname),
            level: row.level,
            initialname: renamed.shortname,
            initialidnumber: "",
            onSave: (name) => saveRename(renamedid, name),
            onCancel: closeEditor
          },
          `rename-${renamedid}`,
          false,
          {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
            lineNumber: 3074,
            columnNumber: 21
          }
        ) };
      }
    } else if (editor) {
      const parentkey = parentKey(editor.parent);
      const parentrow = rows.find((item) => item.key === parentkey);
      const slot = editorSlot(rows, parentkey, editor.after === null ? null : competencyKey(editor.after));
      if (parentrow && slot) {
        const siblings = (tree.branches[editor.parent]?.ids ?? []).map((id) => tree.nodes[id]?.idnumber ?? "");
        const taken = Object.values(tree.nodes).map((known) => known.idnumber);
        const { parent, after } = editor;
        return { key: slot, replace: false, node: /* @__PURE__ */ jsxDEV(
          Editor,
          {
            labels,
            mode: "create",
            label: fill(labels.hubv2_editor_new, parentrow.name),
            level: parentrow.level + 1,
            initialname: "",
            initialidnumber: suggestIdnumber(siblings, taken),
            onSave: (name, idnumber) => saveCreate(parent, after, name, idnumber),
            onCancel: closeEditor
          },
          `create-${editor.serial}`,
          false,
          {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
            lineNumber: 3089,
            columnNumber: 21
          }
        ) };
      }
    }
    return null;
  }, "outlineEditor");
  const entryOf = /* @__PURE__ */ __name((view) => ({ id: view.spec.id, label: labels[view.spec.label], icon: view.spec.icon, shortcut: view.spec.shortcut }), "entryOf");
  const menuItems = /* @__PURE__ */ __name((id) => menuActions(id).map((view, index2, all) => ({
    id: view.spec.id,
    label: labels[view.spec.label],
    icon: view.spec.icon,
    shortcut: view.spec.shortcut,
    disabled: view.state === "disabled",
    danger: view.spec.danger,
    separator: index2 > 0 && all[index2 - 1].spec.group !== view.spec.group
  })), "menuItems");
  const silent = /* @__PURE__ */ __name(() => "", "silent");
  const aria = useMemo(() => ({
    // Both, because the library shows keyboardDisabled's text when its own keyboard is off
    // (nodesFocusable false); every node's aria-describedby points at it.
    "node.a11yDescription.default": labels.hubv2_a11y_node,
    "node.a11yDescription.keyboardDisabled": labels.hubv2_a11y_node,
    // Spoken when a node is moved by the arrow keys, which a node that cannot be dragged never is.
    "node.a11yDescription.ariaLiveMessage": silent,
    "controls.ariaLabel": labels.hubv2_a11y_controls,
    "controls.zoomIn.ariaLabel": labels.hubv2_zoomin,
    "controls.zoomOut.ariaLabel": labels.hubv2_zoomout,
    "controls.fitView.ariaLabel": labels.hubv2_fitview,
    "controls.interactive.ariaLabel": labels.hubv2_a11y_controls,
    "minimap.ariaLabel": labels.hubv2_minimap,
    "handle.ariaLabel": labels.hubv2_a11y_handle
  }), [labels]);
  const node = selected !== null ? tree.nodes[selected] ?? null : null;
  const paneOf = /* @__PURE__ */ __name((origin) => {
    if (origin === "panel") {
      return null;
    }
    return origin === "outline" ? listRef.current : canvasRef.current;
  }, "paneOf");
  const nameOfItem = /* @__PURE__ */ __name((id) => id === ROOT ? framework.shortname : tree.nodes[id]?.shortname, "nameOfItem");
  const overlays = /* @__PURE__ */ __name(() => {
    const menuname = menu ? nameOfItem(menu.id) : void 0;
    const movingnode2 = moveto;
    const offered = palette ? actionsFor(["node", "framework", "move", "workspace", "structures"], selectedcontext) : [];
    const onthis = /* @__PURE__ */ __name((view) => view.spec.scope === "node" || view.spec.scope === "framework", "onthis");
    const enabled = offered.filter((view) => view.state === "enabled");
    return /* @__PURE__ */ jsxDEV(Fragment, { children: [
      menu && menuname !== void 0 ? /* @__PURE__ */ jsxDEV(
        Menu,
        {
          label: fill(labels.hubv2_actionsof, menuname),
          items: menuItems(menu.id),
          anchor: menu.anchor,
          pane: paneOf(menu.origin),
          rtl,
          mac,
          onRun: (id) => {
            closeMenu();
            runAction(id, menu.id === ROOT ? null : menu.id);
          },
          onClose: closeMenu
        },
        void 0,
        false,
        {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
          lineNumber: 3193,
          columnNumber: 21
        }
      ) : null,
      palette ? /* @__PURE__ */ jsxDEV(
        Palette,
        {
          labels,
          mac,
          frameworkid: framework.id,
          actionstitle: fill(labels.hubv2_palette_on, node ? node.shortname : framework.shortname),
          actions: enabled.filter(onthis).map(entryOf),
          commands: enabled.filter((view) => !onthis(view)).map(entryOf),
          onRun: (id, hit) => {
            setPalette(false);
            if (hit) {
              revealItem(hit);
            } else {
              runAction(id, selectedRef.current);
            }
          },
          onClose: () => setPalette(false),
          onLostFocus: restoreFocus
        },
        void 0,
        false,
        {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
          lineNumber: 3208,
          columnNumber: 21
        }
      ) : null,
      movingnode2 ? /* @__PURE__ */ jsxDEV(
        MoveDialog,
        {
          labels,
          tree,
          subjects: movesubjects,
          frameworkid: framework.id,
          frameworkname: framework.shortname,
          rtl,
          pagesize,
          canpick: panes.map,
          loadBranch: (parent, more) => track("children", loadPage(parent, more)),
          freshParent,
          onPick: () => {
            setMoveDialog(null);
            setImpact(NO_IMPACT);
            startMove(movingnode2);
          },
          onCommit: (target) => {
            setMoveDialog(null);
            setImpact(NO_IMPACT);
            moveUnder(movingnode2, target, false);
          },
          onClose: () => {
            setMoveDialog(null);
            setImpact(NO_IMPACT);
          },
          onLostFocus: restoreFocus,
          onTarget: onMoveTarget
        },
        void 0,
        false,
        {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
          lineNumber: 3228,
          columnNumber: 21
        }
      ) : null,
      bulksubjects.length > 0 ? /* @__PURE__ */ jsxDEV(
        MoveDialog,
        {
          labels,
          tree,
          subjects: bulksubjects,
          frameworkid: framework.id,
          frameworkname: framework.shortname,
          rtl,
          pagesize,
          canpick: false,
          loadBranch: (parent, more) => track("children", loadPage(parent, more)),
          freshParent,
          onPick: closeBulk,
          onCommit: bulkMove,
          onClose: closeBulk,
          onLostFocus: restoreFocus,
          returnTo: "opener"
        },
        void 0,
        false,
        {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
          lineNumber: 3258,
          columnNumber: 21
        }
      ) : null,
      bulk !== null && bulk.kind === "appearance" ? /* @__PURE__ */ jsxDEV(
        AppearanceDialog,
        {
          labels,
          names: picked.ids.map((id) => tree.nodes[id]?.shortname ?? ""),
          options: props.appearance ?? { tag1: [], tag2: [], type: [] },
          onCommit: commitAppearance,
          onClose: closeBulk,
          onLostFocus: restoreFocus
        },
        void 0,
        false,
        {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
          lineNumber: 3277,
          columnNumber: 21
        }
      ) : null,
      bulk !== null && bulk.kind === "delete" ? /* @__PURE__ */ jsxDEV(
        DeleteDialog,
        {
          labels,
          names: bulk.ids.map((id) => tree.nodes[id]?.shortname ?? ""),
          summary: bulk.summary,
          onCommit: () => {
            if (bulk.summary) {
              bulkDelete(bulk.summary);
            }
          },
          onClose: closeBulk,
          onLostFocus: restoreFocus
        },
        void 0,
        false,
        {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
          lineNumber: 3287,
          columnNumber: 21
        }
      ) : null
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
      lineNumber: 3191,
      columnNumber: 13
    });
  }, "overlays");
  const movingnode = moving !== null ? tree.nodes[moving] ?? null : null;
  const moveto = moveDialog !== null ? tree.nodes[moveDialog] : void 0;
  const movesubjects = useMemo(() => moveto ? [moveto] : [], [moveto]);
  const bulkmoving = bulk !== null && bulk.kind === "move";
  const bulksubjects = useMemo(
    () => bulkmoving ? topmost(tree, picked.ids).map((id) => tree.nodes[id]) : [],
    [bulkmoving, tree, picked]
  );
  const offeredlenses = LENSES.filter((value) => value !== "usage" || lens === "usage" || index?.answer.usagelevel !== "none");
  const facts = insightFacts(index);
  const orderedfindings = orderFindings(facts.checks);
  const loadedids = Object.keys(tree.nodes).map(Number);
  const fallbackState = /* @__PURE__ */ __name(() => framework.canmanage && moving === null ? "enabled" : "hidden", "fallbackState");
  const checkerPanel = /* @__PURE__ */ __name(() => checker ? /* @__PURE__ */ jsxDEV(
    Checker,
    {
      labels,
      state: insightsState,
      findings: orderedfindings,
      current: findingAt,
      frameworkname: framework.shortname,
      canmanage: framework.canmanage,
      nameOf: nameById,
      need,
      actionFor: (action, id) => tree.nodes[id] ? actionState(action, contextFor(id)) : fallbackState(),
      onReveal: (position) => {
        revealFinding(position, orderedfindings).catch(notifyError);
      },
      onFix: runOn,
      onAgain: () => {
        loadInsights().catch(notifyError);
      },
      onClose: closeChecker,
      headingRef: checkerHeading
    },
    void 0,
    false,
    {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
      lineNumber: 3333,
      columnNumber: 9
    }
  ) : null, "checkerPanel");
  const relatedcount = node && usage !== null ? related.length : null;
  const widthstyle = { [WIDTH_PROPERTY]: `${outlineWidth}px` };
  const rootclasses = ["local-dimensions-hubv2-layout", LAYOUT_CLASSES[layout]];
  if (moving !== null) {
    rootclasses.push("local-dimensions-hubv2-picking");
  }
  if (picked.ids.length > 0) {
    rootclasses.push("local-dimensions-hubv2-haspicks");
  }
  return /* @__PURE__ */ jsxDEV("div", { className: rootclasses.join(" "), style: widthstyle, ref: rootRef, onKeyDown: onRootKeyDown, children: [
    /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-main", children: [
      /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-bar", children: [
        /* @__PURE__ */ jsxDEV(LensSwitch, { labels, lens, offered: offeredlenses, onChange: changeLens }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
          lineNumber: 3369,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV(Search, { labels, frameworkid: framework.id, onChoose: actions.reveal }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
          lineNumber: 3370,
          columnNumber: 21
        }),
        panes.map ? /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-hint", children: [
          /* @__PURE__ */ jsxDEV("i", { className: "fa fa-search-plus me-1", "aria-hidden": "true" }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
            lineNumber: 3373,
            columnNumber: 29
          }),
          labels.hubv2_zoomhint
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
          lineNumber: 3372,
          columnNumber: 25
        }) : null,
        /* @__PURE__ */ jsxDEV(
          CheckButton,
          {
            labels,
            findings: facts.findings,
            open: checker,
            buttonRef: checkButton,
            onToggle: () => checker ? closeChecker() : openChecker()
          },
          void 0,
          false,
          {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
            lineNumber: 3376,
            columnNumber: 21
          }
        ),
        /* @__PURE__ */ jsxDEV(
          "button",
          {
            type: "button",
            className: "btn btn-sm btn-outline-secondary local-dimensions-hubv2-cmdbtn",
            "aria-haspopup": "dialog",
            "aria-keyshortcuts": ariaShortcut(PALETTE_SHORTCUT, mac),
            onClick: () => {
              setMenu(null);
              setPalette(true);
            },
            children: [
              labels.hubv2_commands,
              /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-keys", "aria-hidden": "true", children: shortcutCaps(PALETTE_SHORTCUT, mac).map((cap) => /* @__PURE__ */ jsxDEV("kbd", { children: cap }, cap, false, {
                fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
                lineNumber: 3390,
                columnNumber: 79
              })) }, void 0, false, {
                fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
                lineNumber: 3389,
                columnNumber: 25
              })
            ]
          },
          void 0,
          true,
          {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
            lineNumber: 3378,
            columnNumber: 21
          }
        )
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
        lineNumber: 3368,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-panes", ref: panesBox, children: [
        panes.outline ? /* @__PURE__ */ jsxDEV(
          Outline,
          {
            labels,
            framework,
            count,
            rows,
            selected,
            moving,
            relatedcount,
            active: outlineStop.active,
            loading: loadingBranches,
            expanding,
            notice,
            rtl,
            pagesize,
            listRef,
            actions: outlineActions,
            display,
            menuFor,
            menuOpen,
            editor: outlineEditor(),
            hints: outlineHints(),
            mac,
            marks,
            findings: findingsmarked,
            findingwords,
            impact,
            multiselect,
            picked: pickedset
          },
          void 0,
          false,
          {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
            lineNumber: 3396,
            columnNumber: 25
          }
        ) : null,
        layout === "both" ? /* @__PURE__ */ jsxDEV(
          Splitter,
          {
            label: labels.hubv2_resizeoutline,
            controls: OUTLINE_ID,
            width: outlineWidth,
            available,
            rtl,
            onWidth: onOutlineWidth,
            onReset: resetOutlineWidth
          },
          void 0,
          false,
          {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
            lineNumber: 3427,
            columnNumber: 25
          }
        ) : null,
        panes.map ? /* @__PURE__ */ jsxDEV(
          "div",
          {
            className: "local-dimensions-hubv2-canvas",
            role: "region",
            "aria-label": labels.hubv2_canvas,
            "aria-describedby": heads.length > 0 ? COLUMNS_ID : void 0,
            ref: canvasRef,
            onKeyDown: (event) => walk(event, mapStop, NODE_SELECTOR, "map"),
            onFocus: (event) => {
              lastPane.current = "map";
              mapStop.onFocus(event);
            },
            onBlur: mapStop.onBlur,
            onClickCapture: (event) => {
              const card = event.target.closest(NODE_SELECTOR);
              const id = card && (event.ctrlKey || event.metaKey) ? competencyOf(card.dataset.id ?? "") : null;
              if (multiselect && id !== null) {
                event.preventDefault();
                event.stopPropagation();
                pick(id);
              }
            },
            children: [
              /* @__PURE__ */ jsxDEV(
                ColumnHeads,
                {
                  labels,
                  heads,
                  cardwidth: CARD.width,
                  read: readViewport,
                  subscribe: subscribeViewport,
                  describedby: COLUMNS_ID,
                  canedit: actionState("fwedit", contextFor(null)) === "enabled",
                  onEdit: editLevels
                },
                void 0,
                false,
                {
                  fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
                  lineNumber: 3461,
                  columnNumber: 29
                }
              ),
              /* @__PURE__ */ jsxDEV(CanvasContext.Provider, { value: canvasActions, children: /* @__PURE__ */ jsxDEV(
                ReactFlow,
                {
                  nodes,
                  edges: graph.edges,
                  nodeTypes: NODE_TYPES,
                  edgeTypes: EDGE_TYPES,
                  onNodesChange,
                  onMove: (event, viewport) => viewportListener.current?.(viewport),
                  nodesFocusable: false,
                  nodesDraggable: false,
                  nodesConnectable: false,
                  edgesFocusable: false,
                  elevateEdgesOnSelect: false,
                  selectNodesOnDrag: false,
                  deleteKeyCode: null,
                  selectionKeyCode: null,
                  multiSelectionKeyCode: null,
                  zoomOnDoubleClick: false,
                  zoomOnScroll: false,
                  zoomOnPinch: true,
                  preventScrolling: false,
                  zoomActivationKeyCode: ZOOM_KEYS,
                  fitView: true,
                  fitViewOptions: FIT,
                  minZoom: 0.2,
                  maxZoom: 1.5,
                  ariaLabelConfig: aria,
                  "aria-label": framework.shortname,
                  proOptions: { hideAttribution: true },
                  children: [
                    /* @__PURE__ */ jsxDEV(
                      Background,
                      {
                        variant: BackgroundVariant.Dots,
                        gap: 18,
                        size: 1,
                        className: "local-dimensions-hubv2-dots"
                      },
                      void 0,
                      false,
                      {
                        fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
                        lineNumber: 3505,
                        columnNumber: 37
                      }
                    ),
                    /* @__PURE__ */ jsxDEV(Controls, { showInteractive: false, fitViewOptions: FIT }, void 0, false, {
                      fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
                      lineNumber: 3507,
                      columnNumber: 37
                    }),
                    /* @__PURE__ */ jsxDEV(MiniMap, { pannable: true, zoomable: true, ariaLabel: labels.hubv2_minimap, style: MINIMAP }, void 0, false, {
                      fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
                      lineNumber: 3508,
                      columnNumber: 37
                    })
                  ]
                },
                void 0,
                true,
                {
                  fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
                  lineNumber: 3472,
                  columnNumber: 33
                }
              ) }, void 0, false, {
                fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
                lineNumber: 3471,
                columnNumber: 29
              })
            ]
          },
          void 0,
          true,
          {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
            lineNumber: 3438,
            columnNumber: 25
          }
        ) : null
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
        lineNumber: 3394,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV(
        Dock,
        {
          labels,
          count: picked.ids.length,
          hidden: picked.ids.filter((id) => !drawn.has(id)).length,
          actions: actionsFor(["bulk"], selectedcontext),
          onRun: (id) => runAction(id, null),
          onClear: clearPicked
        },
        void 0,
        false,
        {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
          lineNumber: 3514,
          columnNumber: 17
        }
      )
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
      lineNumber: 3367,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV(
      Panel,
      {
        labels,
        framework,
        count,
        node,
        path: node ? ancestors(tree, node.id).map((id) => tree.nodes[id].shortname) : [],
        usage,
        related,
        moving: movingnode,
        nodeactions,
        menuopen: menu !== null && menu.origin === "panel",
        moveactions: actionsFor(["move"], selectedcontext),
        topactions: actionsFor(["workspace"], selectedcontext).filter((view) => view.spec.id === "addtop"),
        frameworkactions,
        summary: summaryLines(labels, summaryFacts(tree, count), facts.summary),
        summaryRef,
        actions,
        lens: /* @__PURE__ */ jsxDEV(
          LensSection,
          {
            labels,
            lens,
            state: insightsState,
            measure,
            loaded: loadedids,
            nameOf: nameById,
            need,
            reveal: revealListed,
            ruleState: (id) => tree.nodes[id] ? actionState("rule", contextFor(id)) : fallbackState(),
            runOn,
            onMeasure: setMeasure,
            onRetry: () => {
              loadInsights().catch(notifyError);
            }
          },
          void 0,
          false,
          {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
            lineNumber: 3541,
            columnNumber: 21
          }
        ),
        checker: checkerPanel(),
        sections: sectionsFor(sectionsState, selected)
      },
      void 0,
      false,
      {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
        lineNumber: 3523,
        columnNumber: 13
      }
    ),
    /* @__PURE__ */ jsxDEV("div", { className: "visually-hidden", "aria-live": "polite", children: announcement }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
      lineNumber: 3561,
      columnNumber: 13
    }),
    overlays()
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
    lineNumber: 3366,
    columnNumber: 9
  });
}, "Workspace");
const Hub = /* @__PURE__ */ __name((props) => {
  const [labels, setLabels] = useState(null);
  useEffect(() => {
    track("strings", loadLabels()).then(setLabels).catch(notifyError);
  }, []);
  if (!labels) {
    return /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-layout", "aria-busy": "true" }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
      lineNumber: 3579,
      columnNumber: 16
    });
  }
  return /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-root", children: /* @__PURE__ */ jsxDEV(ReactFlowProvider, { children: /* @__PURE__ */ jsxDEV(Workspace, { ...props, labels }, void 0, false, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
    lineNumber: 3584,
    columnNumber: 17
  }) }, void 0, false, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
    lineNumber: 3583,
    columnNumber: 13
  }) }, void 0, false, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
    lineNumber: 3582,
    columnNumber: 9
  });
}, "Hub");
var Hub_default = Hub;
export {
  Hub_default as default
};
//# sourceMappingURL=Hub.dev.js.map
