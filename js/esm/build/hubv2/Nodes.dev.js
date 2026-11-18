var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
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
import { createContext, useContext } from "react";
import { BaseEdge, EdgeLabelRenderer, Handle, Position, getSmoothStepPath } from "../../vendor/xyflow/xyflow.js";
import { cardCount } from "./graph";
import { isHexColour } from "./html";
import { fill, fillCount } from "./strings";
const CanvasContext = createContext(null);
const useCanvas = /* @__PURE__ */ __name(() => {
  const actions = useContext(CanvasContext);
  if (!actions) {
    throw new Error("local_dimensions/hubv2: a node rendered outside the canvas");
  }
  return actions;
}, "useCanvas");
const keepKeys = /* @__PURE__ */ __name((event) => {
  if (event.key === "Enter" || event.key === " ") {
    event.stopPropagation();
  }
}, "keepKeys");
const HANDLE = "local-dimensions-hubv2-handle";
const DEPTH_TILES = [
  "local-dimensions-hubv2-tile-depth1",
  "local-dimensions-hubv2-tile-depth2",
  "local-dimensions-hubv2-tile-depth3",
  "local-dimensions-hubv2-tile-depth4"
];
const SELECTED = " local-dimensions-hubv2-selected";
const Handles = /* @__PURE__ */ __name(({ source = true, target = true }) => /* @__PURE__ */ jsxDEV(Fragment, { children: [
  target ? /* @__PURE__ */ jsxDEV(Handle, { type: "target", position: Position.Left, isConnectable: false, className: HANDLE }, void 0, false, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Nodes.tsx",
    lineNumber: 96,
    columnNumber: 19
  }) : null,
  source ? /* @__PURE__ */ jsxDEV(Handle, { type: "source", position: Position.Right, isConnectable: false, className: HANDLE }, void 0, false, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Nodes.tsx",
    lineNumber: 97,
    columnNumber: 19
  }) : null
] }, void 0, true, {
  fileName: "public/local/dimensions/js/esm/src/hubv2/Nodes.tsx",
  lineNumber: 95,
  columnNumber: 5
}), "Handles");
const FrameworkNode = /* @__PURE__ */ __name(({ data, selected }) => {
  const { labels } = useCanvas();
  const { framework, count } = data;
  return /* @__PURE__ */ jsxDEV("div", { className: `local-dimensions-hubv2-card local-dimensions-hubv2-card-framework${selected ? SELECTED : ""}`, children: [
    /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-tile local-dimensions-hubv2-tile-framework", "aria-hidden": "true", children: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-sitemap" }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Nodes.tsx",
      lineNumber: 113,
      columnNumber: 17
    }) }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Nodes.tsx",
      lineNumber: 112,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-card-text", children: [
      /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-card-title", children: framework.shortname }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Nodes.tsx",
        lineNumber: 116,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-card-sub", children: fillCount(labels, "hubv2_competencies", count) }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Nodes.tsx",
        lineNumber: 117,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Nodes.tsx",
      lineNumber: 115,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV(Handles, { target: false }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Nodes.tsx",
      lineNumber: 119,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Nodes.tsx",
    lineNumber: 111,
    columnNumber: 9
  });
}, "FrameworkNode");
const CompetencyNode = /* @__PURE__ */ __name(({ data, selected }) => {
  const { labels, toggle } = useCanvas();
  const { node, expanded, childcount, depth, moving } = data;
  const tilestyle = isHexColour(node.bgcolor) ? { backgroundColor: node.bgcolor, color: isHexColour(node.textcolor) ? node.textcolor : void 0 } : void 0;
  const tileclass = tilestyle ? "local-dimensions-hubv2-tile-custom" : DEPTH_TILES[Math.min(depth, DEPTH_TILES.length) - 1];
  const glyph = !tilestyle || tilestyle.color ? /* @__PURE__ */ jsxDEV("i", { className: "fa fa-bullseye" }, void 0, false, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Nodes.tsx",
    lineNumber: 140,
    columnNumber: 51
  }) : null;
  const shown = cardCount(node, childcount);
  const countlabel = shown ? fillCount(labels, shown.kind === "children" ? "hubv2_children" : "hubv2_courses", shown.count) : "";
  const classes = ["local-dimensions-hubv2-card"];
  if (selected) {
    classes.push("local-dimensions-hubv2-selected");
  }
  if (moving) {
    classes.push("local-dimensions-hubv2-moving");
  }
  const onToggle = /* @__PURE__ */ __name((event) => {
    event.stopPropagation();
    toggle(node.id);
  }, "onToggle");
  return /* @__PURE__ */ jsxDEV("div", { className: classes.join(" "), children: [
    /* @__PURE__ */ jsxDEV(Handles, {}, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Nodes.tsx",
      lineNumber: 162,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("span", { className: `local-dimensions-hubv2-tile ${tileclass}`, style: tilestyle, "aria-hidden": "true", children: glyph }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Nodes.tsx",
      lineNumber: 163,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-card-text", children: [
      /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-card-title", children: node.shortname }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Nodes.tsx",
        lineNumber: 165,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-card-sub", children: node.ruletype ? node.rulelabel : node.taxonomy }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Nodes.tsx",
        lineNumber: 166,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Nodes.tsx",
      lineNumber: 164,
      columnNumber: 13
    }),
    shown ? /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-pill", title: countlabel, children: [
      /* @__PURE__ */ jsxDEV("span", { "aria-hidden": "true", children: shown.count }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Nodes.tsx",
        lineNumber: 170,
        columnNumber: 21
      }),
      /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: countlabel }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Nodes.tsx",
        lineNumber: 171,
        columnNumber: 21
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Nodes.tsx",
      lineNumber: 169,
      columnNumber: 17
    }) : null,
    node.haschildren ? /* @__PURE__ */ jsxDEV(
      "button",
      {
        type: "button",
        className: "local-dimensions-hubv2-toggle nodrag nopan",
        "aria-expanded": expanded,
        "aria-label": fill(labels[expanded ? "hubv2_collapse" : "hubv2_expand"], node.shortname),
        onClick: onToggle,
        onKeyDown: keepKeys,
        children: /* @__PURE__ */ jsxDEV("i", { className: `fa ${expanded ? "fa-chevron-left" : "fa-chevron-right"}`, "aria-hidden": "true" }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Nodes.tsx",
          lineNumber: 183,
          columnNumber: 21
        })
      },
      void 0,
      false,
      {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Nodes.tsx",
        lineNumber: 175,
        columnNumber: 17
      }
    ) : null
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Nodes.tsx",
    lineNumber: 161,
    columnNumber: 9
  });
}, "CompetencyNode");
const MoreNode = /* @__PURE__ */ __name(({ data }) => {
  const { labels, loadMore, pagesize } = useCanvas();
  const { parent, remaining } = data;
  return /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-card local-dimensions-hubv2-card-more", children: [
    /* @__PURE__ */ jsxDEV(Handles, { source: false }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Nodes.tsx",
      lineNumber: 201,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-sm btn-outline-secondary nodrag nopan", onClick: () => loadMore(parent), children: fill(labels.hubv2_loadmore, String(Math.min(remaining, pagesize))) }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Nodes.tsx",
      lineNumber: 202,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Nodes.tsx",
    lineNumber: 200,
    columnNumber: 9
  });
}, "MoreNode");
const RuleEdge = /* @__PURE__ */ __name((props) => {
  const { id, sourceX, sourceY, targetX, targetY, sourcePosition, targetPosition, data } = props;
  const [path] = getSmoothStepPath({ sourceX, sourceY, sourcePosition, targetX, targetY, targetPosition });
  return /* @__PURE__ */ jsxDEV(Fragment, { children: [
    /* @__PURE__ */ jsxDEV(BaseEdge, { id, path }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Nodes.tsx",
      lineNumber: 221,
      columnNumber: 13
    }),
    data && data.label ? /* @__PURE__ */ jsxDEV(EdgeLabelRenderer, { children: /* @__PURE__ */ jsxDEV(
      "div",
      {
        className: "local-dimensions-hubv2-edge-label",
        style: { transform: `translate(0, -50%) translate(${sourceX + 14}px, ${sourceY}px)` },
        children: data.label
      },
      void 0,
      false,
      {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Nodes.tsx",
        lineNumber: 224,
        columnNumber: 21
      }
    ) }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Nodes.tsx",
      lineNumber: 223,
      columnNumber: 17
    }) : null
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Nodes.tsx",
    lineNumber: 220,
    columnNumber: 9
  });
}, "RuleEdge");
export {
  CanvasContext,
  CompetencyNode,
  FrameworkNode,
  MoreNode,
  RuleEdge
};
//# sourceMappingURL=Nodes.dev.js.map
