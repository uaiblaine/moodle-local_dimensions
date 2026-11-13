var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * Competency hub v2: a framework's tree as a canvas of nodes, with a detail panel beside it.
 *
 * The entry of the bundle (js/esm/bundle.json), mounted by templates/hubv2.mustache with the props
 * classes/output/hubv2_page.php builds. The tree is lazy: the first page of roots arrives as props,
 * each branch is loaded when it is first opened (local_dimensions_browse_structure), and a node is
 * refreshed from the server after every change. Nothing is persisted: the layout is recomputed from
 * the tree on every change (layout.ts), and nodes cannot be dragged. Every action reuses the hub's
 * own dialogues and core's competency services, called as the list view (amd/src/central/structure.js)
 * calls them.
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
import { CARD, FRAMEWORK_ID, buildGraph, competencyKey, competencyOf } from "./graph";
import { blockedBody, confirmBody } from "./deletion";
import { escapeHtml } from "./html";
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
import { fill, fillObject, loadLabels } from "./strings";
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
const Canvas = /* @__PURE__ */ __name((props) => {
  const { labels, framework, pagesize } = props;
  const flow = useReactFlow();
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
  const toggle = useCallback((id) => {
    if (isExpanded(treeRef.current, id)) {
      commit((current) => setExpanded(current, id, false));
      return;
    }
    const loading = hasBranch(treeRef.current, id) ? Promise.resolve() : loadPage(id, false);
    track("children", loading.then(() => commit((current) => setExpanded(current, id, true)))).catch(notifyError);
  }, [commit, loadPage]);
  const loadMore = useCallback((parent) => {
    track("children", loadPage(parent, true)).catch(notifyError);
  }, [loadPage]);
  const announce = /* @__PURE__ */ __name((message) => setAnnouncement(message), "announce");
  const endMove = useCallback((cancelled) => {
    movingRef.current = null;
    setMoving(null);
    if (cancelled) {
      setAnnouncement(labels.hubv2_movecancelled);
    }
  }, [labels]);
  const focusNode = /* @__PURE__ */ __name((key) => {
    window.requestAnimationFrame(() => window.requestAnimationFrame(() => {
      document.querySelector(`.local-dimensions-hubv2 .react-flow__node[data-id="${key}"]`)?.focus();
    }));
  }, "focusNode");
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
    const name = escapeHtml(source.shortname);
    const question = target === ROOT ? fill(labels.hubv2_moveconfirmroot, name) : fillObject(labels.hubv2_moveconfirm, { name, parent: escapeHtml(treeRef.current.nodes[target]?.shortname ?? "") });
    (async () => {
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
  const onNodesChange = /* @__PURE__ */ __name((changes) => {
    const sizes = {};
    changes.forEach((change) => {
      if (change.type === "dimensions" && change.dimensions) {
        sizes[change.id] = change.dimensions;
      }
    });
    if (Object.keys(sizes).length > 0) {
      setMeasured((current) => ({ ...current, ...sizes }));
    }
    const picked = changes.find((change) => change.type === "select" && change.selected);
    if (picked && "id" in picked) {
      const id = picked.id === FRAMEWORK_ID ? ROOT : competencyOf(picked.id);
      if (id === null) {
        return;
      }
      if (movingRef.current !== null) {
        pickParent(id);
      } else {
        choose(id === ROOT ? null : id);
      }
      return;
    }
    const unselected = changes.some((change) => change.type === "select" && !change.selected);
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
    focusNode(competencyKey(node2.id));
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
  const relatedids = useMemo(() => related.map((item) => item.id), [related]);
  const graph = useMemo(() => buildGraph({
    tree,
    framework: { ...framework, competencycount: count },
    selected,
    related: relatedids,
    moving,
    labels: { roledescription: labels.hubv2_noderole, more: moreLabel }
  }), [tree, framework, count, selected, relatedids, moving, labels, moreLabel]);
  const nodes = useMemo(() => graph.nodes.map((node2) => measured[node2.id] ? { ...node2, measured: measured[node2.id] } : node2), [graph, measured]);
  const oncanvas = useMemo(() => new Set(graph.nodes.map((node2) => node2.id)), [graph]);
  useEffect(() => {
    const key = centreOn.current;
    const position2 = key ? graph.positions[key] : void 0;
    if (!key || !position2) {
      return;
    }
    centreOn.current = null;
    const zoom = Math.max(flow.getZoom(), 0.8);
    flow.setCenter(position2.x + CARD.width / 2, position2.y + CARD.height / 2, { zoom, duration: 300 }).catch(() => null);
    focusNode(key);
  }, [graph, flow]);
  const canvasActions = useMemo(
    () => ({ labels, pagesize, toggle, loadMore }),
    [labels, pagesize, toggle, loadMore]
  );
  const silent = /* @__PURE__ */ __name(() => "", "silent");
  const aria = useMemo(() => ({
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
  return /* @__PURE__ */ jsxDEV("div", { className: `local-dimensions-hubv2-layout${moving !== null ? " local-dimensions-hubv2-picking" : ""}`, children: [
    /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-main", children: [
      /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-bar", children: [
        /* @__PURE__ */ jsxDEV(Search, { labels, frameworkid: framework.id, onChoose: actions.reveal }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
          lineNumber: 821,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-hint", children: [
          /* @__PURE__ */ jsxDEV("i", { className: "fa fa-search-plus me-1", "aria-hidden": "true" }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
            lineNumber: 823,
            columnNumber: 25
          }),
          labels.hubv2_zoomhint
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
          lineNumber: 822,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
        lineNumber: 820,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-canvas", role: "region", "aria-label": labels.hubv2_canvas, children: /* @__PURE__ */ jsxDEV(CanvasContext.Provider, { value: canvasActions, children: /* @__PURE__ */ jsxDEV(
        ReactFlow,
        {
          nodes,
          edges: graph.edges,
          nodeTypes: NODE_TYPES,
          edgeTypes: EDGE_TYPES,
          onNodesChange,
          nodesDraggable: false,
          nodesConnectable: false,
          edgesFocusable: false,
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
                lineNumber: 853,
                columnNumber: 29
              }
            ),
            /* @__PURE__ */ jsxDEV(Controls, { showInteractive: false, fitViewOptions: FIT }, void 0, false, {
              fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
              lineNumber: 855,
              columnNumber: 29
            }),
            /* @__PURE__ */ jsxDEV(MiniMap, { pannable: true, zoomable: true, ariaLabel: labels.hubv2_minimap, style: MINIMAP }, void 0, false, {
              fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
              lineNumber: 856,
              columnNumber: 29
            })
          ]
        },
        void 0,
        true,
        {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
          lineNumber: 828,
          columnNumber: 25
        }
      ) }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
        lineNumber: 827,
        columnNumber: 21
      }) }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
        lineNumber: 826,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
      lineNumber: 819,
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
        onCanvas: (id) => oncanvas.has(competencyKey(id)),
        moving: movingnode,
        canUp: position > 0,
        canDown: position >= 0 && !islast,
        actions
      },
      void 0,
      false,
      {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
        lineNumber: 861,
        columnNumber: 13
      }
    ),
    /* @__PURE__ */ jsxDEV("div", { className: "visually-hidden", "aria-live": "polite", children: announcement }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
      lineNumber: 875,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
    lineNumber: 818,
    columnNumber: 9
  });
}, "Canvas");
const Hub = /* @__PURE__ */ __name((props) => {
  const [labels, setLabels] = useState(null);
  useEffect(() => {
    track("strings", loadLabels()).then(setLabels).catch(notifyError);
  }, []);
  if (!labels) {
    return /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-layout", "aria-busy": "true" }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
      lineNumber: 892,
      columnNumber: 16
    });
  }
  return /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-root", children: /* @__PURE__ */ jsxDEV(ReactFlowProvider, { children: /* @__PURE__ */ jsxDEV(Canvas, { ...props, labels }, void 0, false, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
    lineNumber: 897,
    columnNumber: 17
  }) }, void 0, false, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
    lineNumber: 896,
    columnNumber: 13
  }) }, void 0, false, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Hub.tsx",
    lineNumber: 895,
    columnNumber: 9
  });
}, "Hub");
var Hub_default = Hub;
export {
  Hub_default as default
};
//# sourceMappingURL=Hub.dev.js.map
