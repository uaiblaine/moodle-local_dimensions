var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
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
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
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
import { CARD, FRAMEWORK_ID, buildGraph, competencyKey, competencyOf, moreKey } from "./graph";
import { EXPAND_ALL_BOUND, collapseAll, expandStep } from "./expandall";
import { branchTarget, isTypeAheadKey, keyAction, navOrder, nodeName, panTarget, typeAhead } from "./keynav";
import Outline, { OUTLINE_ID } from "./Outline";
import { outlineRows } from "./outlinerows";
import { useRoving } from "./roving";
import { LAYOUT_EVENT, activation, isLayout, panesOf } from "./selection";
import Splitter from "./Splitter";
import { OUTLINE_WIDTH, clampWidth, storeWidth, storedWidth } from "./outlinewidth";
import { rememberSelection } from "./deeplink";
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
  deleteCheck,
  deleteCompetency,
  getNode,
  moveOne,
  selectionData,
  setParent,
  updateRule
} from "./repository";
import Search from "./Search";
import { fill, fillCount, loadLabels } from "./strings";
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
const FIT = { padding: { top: "32px", right: "32px", bottom: "140px", left: "32px" }, maxZoom: 1 };
const EDGE_TYPES = { rule: RuleEdge };
const NODE_SELECTOR = ".react-flow__node";
const ROW_SELECTOR = '[role="treeitem"]';
const OVERLAYS = ".react-flow__minimap, .react-flow__controls";
const TYPE_AHEAD_PAUSE = 500;
const EXPAND_ALL_PARALLEL = 4;
const LAYOUT_CLASSES = {
  outline: "local-dimensions-hubv2-layout-outline",
  map: "local-dimensions-hubv2-layout-map",
  both: "local-dimensions-hubv2-layout-both"
};
const WIDTH_PROPERTY = "--local-dimensions-hubv2-outline-width";
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
  const revealing = useRef(null);
  const revealed = useRef(null);
  const settleReveal = /* @__PURE__ */ __name(() => {
    revealing.current = null;
    revealed.current?.();
    revealed.current = null;
  }, "settleReveal");
  const commit = useCallback((update) => {
    treeRef.current = update(treeRef.current);
    setTree(treeRef.current);
  }, []);
  const loadPage = useCallback((parent, append) => {
    const loaded = append ? treeRef.current.branches[parent]?.ids.length ?? 0 : 0;
    return browse(framework.id, parent, loaded, pagesize).then((page) => commit((current) => insertChildren(current, parent, page, append)));
  }, [commit, framework.id, pagesize]);
  const reloadBranch = useCallback(async (parent) => {
    const wanted = Math.max(treeRef.current.branches[parent]?.ids.length ?? 0, 1);
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
    rememberSelection(id);
    setUsage(null);
    setRelated([]);
    if (id === null) {
      selectionAsk.current++;
      return;
    }
    loadSelection(id);
  }, [loadSelection]);
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
  const panTo = /* @__PURE__ */ __name((key) => {
    const position2 = graphRef.current?.positions[key];
    const canvas = measureCanvas();
    if (!position2 || !canvas) {
      return;
    }
    const target = panTarget({ ...position2, ...CARD }, flow.getViewport(), canvas.view, canvas.obstacles);
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
    const freshParent = /* @__PURE__ */ __name(async (id) => {
      if (id === ROOT) {
        return null;
      }
      const answer = await getNode(id);
      return answer.found && answer.node ? answer.node : null;
    }, "freshParent");
    (async () => {
      const [oldparent, newparent] = await track("move", Promise.all([freshParent(source.parentid), freshParent(target)]));
      const known = target === ROOT ? null : { shortname: treeRef.current.nodes[target]?.shortname ?? "", ruletype: null };
      const question = moveBody(labels, source.shortname, oldparent, newparent ?? known);
      const notification = await load("core/notification");
      const confirmed = await notification.saveCancelPromise(labels.hubv2_movetitle, question, labels.hubv2_move).then(() => true, () => false);
      if (!confirmed) {
        return;
      }
      endMove(false);
      await track("move", (async () => {
        await setParent(source.id, target);
        const answer = await getNode(source.id);
        if (answer.found) {
          await relocate(source.id, source.parentid, answer);
        }
        announce(fill(labels.hubv2_moved, source.shortname));
      })());
    })().catch(notifyError);
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
    const picked = selections.find((change) => change.selected);
    if (picked) {
      const id = picked.id === FRAMEWORK_ID ? ROOT : competencyOf(picked.id);
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
          track("refresh", refreshNode(node2.id)).catch(notifyError);
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
        })()).catch(notifyError);
      });
      await form.show();
    })().catch(notifyError);
  }, "openForm");
  const storeRule = /* @__PURE__ */ __name((node2, config) => track("refresh", updateRule(node2.id, config).then(() => refreshNode(node2.id))).then(() => announce(labels["core:changessaved"])), "storeRule");
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
      track("refresh", refreshNode(node2.id)).catch(notifyError);
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
    load("local_dimensions/central/related_competencies").then((module) => module.open({ competencyid: node2.id, competencyname: node2.shortname, frameworkid: framework.id })).catch(notifyError);
  }, "openRelated");
  const startMove = /* @__PURE__ */ __name((node2) => {
    movingRef.current = node2.id;
    setMoving(node2.id);
    announce(fill(labels.hubv2_movepick, node2.shortname));
    focusItem(competencyKey(node2.id));
  }, "startMove");
  const stepMove = /* @__PURE__ */ __name((node2, up) => {
    track("refresh", moveOne(node2.id, up).then(() => reloadBranch(node2.parentid))).catch(notifyError);
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
      const confirmed = await notification.deleteCancelPromise(labels.hubv2_delete, confirmBody(labels, node2.shortname, check)).then(() => true, () => false);
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
      })());
    })().catch(notifyError);
  }, "remove");
  const revealItem = /* @__PURE__ */ __name((item) => {
    track("reveal", reveal(item.id, item.pathids)).catch(notifyError);
  }, "revealItem");
  const edit = /* @__PURE__ */ __name((node2) => openForm(node2, null), "edit");
  const addChild = /* @__PURE__ */ __name((node2) => openForm(null, node2), "addChild");
  const moveRoot = /* @__PURE__ */ __name(() => pickParent(ROOT), "moveRoot");
  const cancelMove = /* @__PURE__ */ __name(() => endMove(true), "cancelMove");
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
  const walk = /* @__PURE__ */ __name((event, stop, selector, from) => {
    const target = event.target;
    const element = target.closest(selector);
    if (!element || !event.currentTarget.contains(element)) {
      return;
    }
    if (element !== target && (event.key === "Enter" || event.key === " ")) {
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
  const expandAll = /* @__PURE__ */ __name(() => {
    const run = ++expandRun.current;
    setExpanding(true);
    setNotice("");
    track("expandall", (async () => {
      let capped = false;
      for (let round = 0; round < 100 && run === expandRun.current; round++) {
        const step = expandStep(treeRef.current, EXPAND_ALL_BOUND, pagesize);
        capped = step.capped;
        for (let index = 0; index < step.load.length && run === expandRun.current; index += EXPAND_ALL_PARALLEL) {
          const batch = step.load.slice(index, index + EXPAND_ALL_PARALLEL).filter((id) => !hasBranch(treeRef.current, id));
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
  const actions = {
    edit,
    addChild,
    rule: editRule,
    links: openLinks,
    relate: openRelated,
    move: startMove,
    moveRoot,
    cancelMove,
    moveOne: stepMove,
    remove,
    reveal: revealItem
  };
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
      }, "onHidden");
      $(document).on(MODAL_HIDDEN, onHidden);
      off = /* @__PURE__ */ __name(() => {
        $(document).off(MODAL_HIDDEN);
      }, "off");
      return null;
    }).catch(notifyError);
    return () => off();
  }, [loadSelection]);
  const moreLabel = useCallback((remaining) => fill(labels.hubv2_loadmore, String(Math.min(remaining, pagesize))), [labels, pagesize]);
  const nameOf = useCallback((info) => nodeName(labels, info), [labels]);
  const frameworkName = useCallback((total) => `${framework.shortname}, ${fillCount(labels, "hubv2_competencies", total)}`, [framework.shortname, labels]);
  const relatedids = useMemo(() => related.map((item) => item.id), [related]);
  const graph = useMemo(() => buildGraph({
    tree,
    framework: { ...framework, competencycount: count },
    selected,
    related: relatedids,
    moving,
    labels: { roledescription: labels.hubv2_noderole, more: moreLabel, name: nameOf, framework: frameworkName },
    rtl
  }), [tree, framework, count, selected, relatedids, moving, labels, moreLabel, nameOf, frameworkName, rtl]);
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
  useEffect(() => {
    const key = centreOn.current;
    const position2 = key ? graph.positions[key] : void 0;
    if (!key || !position2) {
      return;
    }
    centreOn.current = null;
    if (panesRef.current.map) {
      const zoom = Math.max(flow.getZoom(), 0.8);
      flow.setCenter(position2.x + CARD.width / 2, position2.y + CARD.height / 2, { zoom, duration: motionDuration(300) }).catch(() => null);
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
  const canvasActions = useMemo(
    () => ({ labels, pagesize, rtl, toggle: toggleOnMap, loadMore }),
    [labels, pagesize, rtl, toggleOnMap, loadMore]
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
    add: /* @__PURE__ */ __name(() => addChild(null), "add"),
    /**
     * Walk the outline with the tree's keys.
     *
     * @param {object} event The key event.
     * @returns {void}
     */
    onKeyDown: /* @__PURE__ */ __name((event) => walk(event, outlineStop, ROW_SELECTOR, "outline"), "onKeyDown"),
    onFocus: outlineStop.onFocus,
    onBlur: outlineStop.onBlur
  };
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
  const siblings = node ? tree.branches[node.parentid] : void 0;
  const position = node && siblings ? siblings.ids.indexOf(node.id) : -1;
  const islast = !!siblings && position === siblings.ids.length - 1 && siblings.ids.length >= siblings.total;
  const movingnode = moving !== null ? tree.nodes[moving] ?? null : null;
  const relatedcount = node && usage !== null ? related.length : null;
  const widthstyle = { [WIDTH_PROPERTY]: `${outlineWidth}px` };
  const rootclasses = ["local-dimensions-hubv2-layout", LAYOUT_CLASSES[layout]];
  if (moving !== null) {
    rootclasses.push("local-dimensions-hubv2-picking");
  }
  return /* @__PURE__ */ jsxDEV("div", { className: rootclasses.join(" "), style: widthstyle, children: [
    /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-main", children: [
      /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-bar", children: [
        /* @__PURE__ */ jsxDEV(Search, { labels, frameworkid: framework.id, onChoose: actions.reveal }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
          lineNumber: 1419,
          columnNumber: 21
        }),
        panes.map ? /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-hint", children: [
          /* @__PURE__ */ jsxDEV("i", { className: "fa fa-search-plus me-1", "aria-hidden": "true" }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
            lineNumber: 1422,
            columnNumber: 29
          }),
          labels.hubv2_zoomhint
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
          lineNumber: 1421,
          columnNumber: 25
        }) : null
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
        lineNumber: 1418,
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
            actions: outlineActions
          },
          void 0,
          false,
          {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
            lineNumber: 1428,
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
            lineNumber: 1447,
            columnNumber: 25
          }
        ) : null,
        panes.map ? /* @__PURE__ */ jsxDEV(
          "div",
          {
            className: "local-dimensions-hubv2-canvas",
            role: "region",
            "aria-label": labels.hubv2_canvas,
            ref: canvasRef,
            onKeyDown: (event) => walk(event, mapStop, NODE_SELECTOR, "map"),
            onFocus: mapStop.onFocus,
            onBlur: mapStop.onBlur,
            children: /* @__PURE__ */ jsxDEV(CanvasContext.Provider, { value: canvasActions, children: /* @__PURE__ */ jsxDEV(
              ReactFlow,
              {
                nodes,
                edges: graph.edges,
                nodeTypes: NODE_TYPES,
                edgeTypes: EDGE_TYPES,
                onNodesChange,
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
                      lineNumber: 1500,
                      columnNumber: 37
                    }
                  ),
                  /* @__PURE__ */ jsxDEV(Controls, { showInteractive: false, fitViewOptions: FIT }, void 0, false, {
                    fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
                    lineNumber: 1502,
                    columnNumber: 37
                  }),
                  /* @__PURE__ */ jsxDEV(MiniMap, { pannable: true, zoomable: true, ariaLabel: labels.hubv2_minimap, style: MINIMAP }, void 0, false, {
                    fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
                    lineNumber: 1503,
                    columnNumber: 37
                  })
                ]
              },
              void 0,
              true,
              {
                fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
                lineNumber: 1468,
                columnNumber: 33
              }
            ) }, void 0, false, {
              fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
              lineNumber: 1467,
              columnNumber: 29
            })
          },
          void 0,
          false,
          {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
            lineNumber: 1458,
            columnNumber: 25
          }
        ) : null
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
        lineNumber: 1426,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
      lineNumber: 1417,
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
        canUp: position > 0,
        canDown: position >= 0 && !islast,
        actions
      },
      void 0,
      false,
      {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
        lineNumber: 1510,
        columnNumber: 13
      }
    ),
    /* @__PURE__ */ jsxDEV("div", { className: "visually-hidden", "aria-live": "polite", children: announcement }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
      lineNumber: 1523,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
    lineNumber: 1416,
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
      lineNumber: 1540,
      columnNumber: 16
    });
  }
  return /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-root", children: /* @__PURE__ */ jsxDEV(ReactFlowProvider, { children: /* @__PURE__ */ jsxDEV(Workspace, { ...props, labels }, void 0, false, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
    lineNumber: 1545,
    columnNumber: 17
  }) }, void 0, false, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
    lineNumber: 1544,
    columnNumber: 13
  }) }, void 0, false, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
    lineNumber: 1543,
    columnNumber: 9
  });
}, "Hub");
var Hub_default = Hub;
export {
  Hub_default as default
};
//# sourceMappingURL=Hub.dev.js.map
