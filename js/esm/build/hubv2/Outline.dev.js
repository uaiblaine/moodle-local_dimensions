var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The outline: the workspace's tree as an APG Tree View, beside the map, on the same store.
 *
 * A flat list of treeitems (outlinerows.ts says why) with one Tab stop; the workspace owns the keyboard
 * (keynav.ts, the same key map as the map's, plus type-ahead here), the selection and every action,
 * and this component only draws the rows and reports clicks. A row's click selects it, or picks the
 * new parent in the move mode; its chevron only opens or closes. Each row is named by the competency
 * alone and described by its meta in words (children, rule, related competencies), so the state a
 * screen reader already reads from aria-expanded and aria-level is not said twice.
 *
 * @module     local_dimensions/hubv2/Outline
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useEffect, useRef } from "react";
import { isHexColour } from "./html";
import { rowMeta, scrollToShow } from "./outlinerows";
import { fill, fillCount } from "./strings";
const DEPTH_MARKS = [
  "local-dimensions-hubv2-mark-depth1",
  "local-dimensions-hubv2-mark-depth2",
  "local-dimensions-hubv2-mark-depth3",
  "local-dimensions-hubv2-mark-depth4"
];
const ID = "local-dimensions-hubv2-outline";
const SKELETON_ROWS = 3;
const chevron = /* @__PURE__ */ __name((expanded, rtl) => {
  if (expanded) {
    return "fa-chevron-down";
  }
  return rtl ? "fa-chevron-left" : "fa-chevron-right";
}, "chevron");
const meta = /* @__PURE__ */ __name((labels, row, count, related) => {
  const shown = [];
  const said = [];
  rowMeta(row, count, related).forEach((part) => {
    if (part.kind === "count") {
      shown.push(/* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-ometa-count", children: part.value }, "count", false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 122,
        columnNumber: 24
      }));
      said.push(fillCount(labels, row.kind === "framework" ? "hubv2_competencies" : "hubv2_children", part.value));
    } else if (part.kind === "rule") {
      shown.push(/* @__PURE__ */ jsxDEV("i", { className: "fa fa-diagram-project local-dimensions-hubv2-ometa-rule" }, "rule", false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 125,
        columnNumber: 24
      }));
      said.push(fill(labels.hubv2_a11y_rule, part.label));
    } else {
      shown.push(
        /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-ometa-related", children: [
          /* @__PURE__ */ jsxDEV("i", { className: "fa fa-share-nodes" }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
            lineNumber: 130,
            columnNumber: 21
          }),
          part.value
        ] }, "related", true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 129,
          columnNumber: 17
        })
      );
      said.push(fillCount(labels, "hubv2_outline_related", part.value));
    }
  });
  return { shown, said: said.join(", ") };
}, "meta");
const Row = /* @__PURE__ */ __name((props) => {
  const { row, labels, count, selected, moving, related, active, rtl, pagesize, actions } = props;
  const indent = { paddingInlineStart: `${0.25 + (row.level - 1) * 1.25}rem` };
  const common = {
    "role": "treeitem",
    "data-key": row.key,
    "aria-level": row.level,
    "aria-setsize": row.setsize,
    "aria-posinset": row.posinset,
    "tabIndex": active ? 0 : -1,
    "style": indent
  };
  if (row.kind === "more") {
    const label = fill(labels.hubv2_loadmore, String(Math.min(row.remaining, pagesize)));
    const remaining = fillCount(labels, "hubv2_outline_remaining", row.remaining);
    return /* @__PURE__ */ jsxDEV(
      "li",
      {
        ...common,
        className: "local-dimensions-hubv2-orow local-dimensions-hubv2-orow-more",
        "aria-label": `${label}, ${remaining}`,
        onClick: () => actions.loadMore(row.id),
        children: [
          /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-otoggle", "aria-hidden": "true" }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
            lineNumber: 177,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV(
            "button",
            {
              type: "button",
              className: "local-dimensions-hubv2-omore",
              tabIndex: -1,
              "aria-hidden": "true",
              onClick: (event) => {
                event.stopPropagation();
                actions.loadMore(row.id);
              },
              children: label
            },
            void 0,
            false,
            {
              fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
              lineNumber: 178,
              columnNumber: 17
            }
          ),
          /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-ometa", "aria-hidden": "true", children: remaining }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
            lineNumber: 185,
            columnNumber: 17
          })
        ]
      },
      void 0,
      true,
      {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 175,
        columnNumber: 13
      }
    );
  }
  const { shown, said } = meta(labels, row, count, related);
  const node = row.node;
  let markclass = "local-dimensions-hubv2-mark local-dimensions-hubv2-mark-framework";
  let markstyle;
  if (node) {
    markstyle = isHexColour(node.bgcolor) ? { backgroundColor: node.bgcolor } : void 0;
    markclass = markstyle ? "local-dimensions-hubv2-mark" : `local-dimensions-hubv2-mark ${DEPTH_MARKS[Math.min(row.level - 1, DEPTH_MARKS.length) - 1]}`;
  }
  const classes = ["local-dimensions-hubv2-orow"];
  if (selected) {
    classes.push("local-dimensions-hubv2-orow-selected");
  }
  if (moving) {
    classes.push("local-dimensions-hubv2-orow-moving");
  }
  const onChevron = /* @__PURE__ */ __name((event) => {
    event.stopPropagation();
    actions.toggle(row.id);
  }, "onChevron");
  return /* @__PURE__ */ jsxDEV(
    "li",
    {
      ...common,
      className: classes.join(" "),
      "aria-expanded": row.expandable ? row.expanded : void 0,
      "aria-selected": selected ? true : void 0,
      "aria-labelledby": `${ID}-${row.key}-name`,
      "aria-describedby": said ? `${ID}-${row.key}-desc` : void 0,
      onClick: () => actions.activate(row),
      children: [
        row.expandable && row.kind === "competency" ? /* @__PURE__ */ jsxDEV(
          "span",
          {
            className: "local-dimensions-hubv2-otoggle local-dimensions-hubv2-otoggle-on",
            "aria-hidden": "true",
            onClick: onChevron,
            children: /* @__PURE__ */ jsxDEV("i", { className: `fa ${chevron(row.expanded, rtl)}` }, void 0, false, {
              fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
              lineNumber: 231,
              columnNumber: 21
            })
          },
          void 0,
          false,
          {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
            lineNumber: 229,
            columnNumber: 17
          }
        ) : /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-otoggle", "aria-hidden": "true" }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 233,
          columnNumber: 17
        }),
        /* @__PURE__ */ jsxDEV("span", { className: markclass, style: markstyle, "aria-hidden": "true" }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 234,
          columnNumber: 13
        }),
        /* @__PURE__ */ jsxDEV("span", { id: `${ID}-${row.key}-name`, className: "local-dimensions-hubv2-oname", title: row.name, children: row.name }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 237,
          columnNumber: 13
        }),
        shown.length > 0 ? /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-ometa", "aria-hidden": "true", children: shown }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 238,
          columnNumber: 33
        }) : null,
        said ? /* @__PURE__ */ jsxDEV("span", { id: `${ID}-${row.key}-desc`, className: "visually-hidden", children: said }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 239,
          columnNumber: 21
        }) : null
      ]
    },
    void 0,
    true,
    {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
      lineNumber: 218,
      columnNumber: 9
    }
  );
}, "Row");
const SkeletonRows = /* @__PURE__ */ __name(({ level, parent }) => /* @__PURE__ */ jsxDEV(Fragment, { children: Array.from({ length: SKELETON_ROWS }, (_, index) => /* @__PURE__ */ jsxDEV(
  "li",
  {
    role: "none",
    "aria-hidden": "true",
    className: "local-dimensions-hubv2-orow local-dimensions-hubv2-orow-sk local-dimensions-sk",
    style: { paddingInlineStart: `${0.25 + (level - 1) * 1.25}rem` },
    children: [
      /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-otoggle" }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 256,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-sk-block local-dimensions-hubv2-sk-mark" }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 257,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-sk-block local-dimensions-hubv2-sk-line" }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 258,
        columnNumber: 17
      })
    ]
  },
  `${parent}-sk${index}`,
  true,
  {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
    lineNumber: 253,
    columnNumber: 13
  }
)) }, void 0, false, {
  fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
  lineNumber: 251,
  columnNumber: 5
}), "SkeletonRows");
const Outline = /* @__PURE__ */ __name((props) => {
  const {
    labels,
    framework,
    count,
    rows,
    selected,
    moving,
    relatedcount,
    active,
    loading,
    expanding,
    notice,
    rtl,
    pagesize,
    listRef,
    actions
  } = props;
  const scrolled = useRef(null);
  useEffect(() => {
    const list = listRef.current;
    if (!list || scrolled.current === active) {
      return;
    }
    scrolled.current = active;
    const row = list.querySelector(`[data-key="${active}"]`);
    if (!row) {
      return;
    }
    const next = scrollToShow(row.offsetTop, row.offsetHeight, list.scrollTop, list.clientHeight);
    if (next !== null) {
      list.scrollTop = next;
    }
  }, [active, rows, listRef]);
  const empty = count === 0;
  const selectedkey = selected === null ? "f" : `c${selected}`;
  const movingkey = moving === null ? null : `c${moving}`;
  const items = [];
  rows.forEach((row) => {
    items.push(
      /* @__PURE__ */ jsxDEV(
        Row,
        {
          row,
          labels,
          count,
          selected: row.key === selectedkey,
          moving: row.key === movingkey,
          related: row.key === selectedkey ? relatedcount : null,
          active: row.key === active,
          rtl,
          pagesize,
          actions
        },
        row.key,
        false,
        {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 300,
          columnNumber: 13
        }
      )
    );
    if (row.kind === "competency" && !row.expanded && loading.includes(row.id)) {
      items.push(/* @__PURE__ */ jsxDEV(SkeletonRows, { level: row.level + 1, parent: row.key }, `${row.key}-loading`, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 315,
        columnNumber: 24
      }));
    }
  });
  return /* @__PURE__ */ jsxDEV("section", { id: ID, className: "local-dimensions-hubv2-outline", "aria-labelledby": `${ID}-title`, children: [
    /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-ohead", children: [
      /* @__PURE__ */ jsxDEV("h3", { id: `${ID}-title`, className: "local-dimensions-hubv2-otitle", children: labels.hubv2_outline }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 322,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV(
        "button",
        {
          type: "button",
          className: "local-dimensions-hubv2-iconbtn local-dimensions-tip local-dimensions-tip-bottom",
          "aria-label": labels.managecompetencies_expandall,
          "data-dim-tip": labels.managecompetencies_expandall,
          disabled: expanding || empty,
          onClick: actions.expandAll,
          children: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-angles-down", "aria-hidden": "true" }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
            lineNumber: 331,
            columnNumber: 21
          })
        },
        void 0,
        false,
        {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 323,
          columnNumber: 17
        }
      ),
      /* @__PURE__ */ jsxDEV(
        "button",
        {
          type: "button",
          className: "local-dimensions-hubv2-iconbtn local-dimensions-tip local-dimensions-tip-bottom",
          "aria-label": labels.managecompetencies_collapseall,
          "data-dim-tip": labels.managecompetencies_collapseall,
          disabled: empty,
          onClick: actions.collapseAll,
          children: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-angles-up", "aria-hidden": "true" }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
            lineNumber: 341,
            columnNumber: 21
          })
        },
        void 0,
        false,
        {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 333,
          columnNumber: 17
        }
      )
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
      lineNumber: 321,
      columnNumber: 13
    }),
    notice ? /* @__PURE__ */ jsxDEV("p", { className: "local-dimensions-hubv2-onotice", children: notice }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
      lineNumber: 344,
      columnNumber: 23
    }) : null,
    /* @__PURE__ */ jsxDEV(
      "ul",
      {
        ref: listRef,
        className: "local-dimensions-hubv2-tree",
        role: "tree",
        "aria-label": framework.shortname,
        "aria-busy": loading.length > 0 || expanding ? true : void 0,
        onKeyDown: actions.onKeyDown,
        onFocus: actions.onFocus,
        onBlur: actions.onBlur,
        children: items
      },
      void 0,
      false,
      {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 345,
        columnNumber: 13
      }
    ),
    loading.length > 0 ? /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", role: "status", children: labels.hubv2_loading }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
      lineNumber: 357,
      columnNumber: 35
    }) : null,
    empty ? /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-oempty", children: [
      /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-oempty-icon", "aria-hidden": "true", children: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-sitemap" }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 360,
        columnNumber: 93
      }) }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 360,
        columnNumber: 21
      }),
      /* @__PURE__ */ jsxDEV("p", { className: "local-dimensions-hubv2-oempty-title", children: labels.nocompetencies }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 361,
        columnNumber: 21
      }),
      framework.canmanage && moving === null ? /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-primary btn-sm", onClick: actions.add, children: [
        /* @__PURE__ */ jsxDEV("i", { className: "fa fa-plus me-1", "aria-hidden": "true" }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 364,
          columnNumber: 29
        }),
        labels.managecompetencies_addcompetency
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 363,
        columnNumber: 25
      }) : null
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
      lineNumber: 359,
      columnNumber: 17
    }) : null
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
    lineNumber: 320,
    columnNumber: 9
  });
}, "Outline");
var Outline_default = Outline;
export {
  ID as OUTLINE_ID,
  Outline_default as default
};
//# sourceMappingURL=Outline.dev.js.map
