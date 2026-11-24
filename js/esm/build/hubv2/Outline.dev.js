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
import { useEffect, useRef, useState } from "react";
import { shortcutCaps } from "./commands";
import { isHexColour } from "./html";
import Menu from "./Menu";
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
        lineNumber: 146,
        columnNumber: 24
      }));
      said.push(fillCount(labels, row.kind === "framework" ? "hubv2_competencies" : "hubv2_children", part.value));
    } else if (part.kind === "rule") {
      shown.push(/* @__PURE__ */ jsxDEV("i", { className: "fa fa-diagram-project local-dimensions-hubv2-ometa-rule" }, "rule", false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 149,
        columnNumber: 24
      }));
      said.push(fill(labels.hubv2_a11y_rule, part.label));
    } else {
      shown.push(
        /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-ometa-related", children: [
          /* @__PURE__ */ jsxDEV("i", { className: "fa fa-share-nodes" }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
            lineNumber: 154,
            columnNumber: 21
          }),
          part.value
        ] }, "related", true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 153,
          columnNumber: 17
        })
      );
      said.push(fillCount(labels, "hubv2_outline_related", part.value));
    }
  });
  return { shown, said: said.join(", ") };
}, "meta");
const displayed = /* @__PURE__ */ __name((labels, node, display) => {
  const parts = [];
  if (node && display.idnumber && node.idnumber) {
    parts.push({ shown: node.idnumber, said: `${labels["core:idnumber"]} ${node.idnumber}` });
  }
  if (node && display.taxonomy && node.taxonomy) {
    parts.push({ shown: node.taxonomy, said: node.taxonomy });
  }
  return parts;
}, "displayed");
const RowMenuButton = /* @__PURE__ */ __name((props) => {
  const { labels, row, open, actions } = props;
  return /* @__PURE__ */ jsxDEV(
    "button",
    {
      type: "button",
      className: "local-dimensions-hubv2-iconbtn local-dimensions-hubv2-orowmenu",
      tabIndex: -1,
      "aria-label": fill(labels.hubv2_actionsof, row.name),
      "aria-haspopup": "menu",
      "aria-expanded": open,
      onClick: (event) => {
        event.stopPropagation();
        actions.openMenu(row.id, event.currentTarget);
      },
      onKeyDown: (event) => {
        if (event.key === "Enter" || event.key === " ") {
          event.stopPropagation();
        }
      },
      children: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-ellipsis-vertical", "aria-hidden": "true" }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 210,
        columnNumber: 13
      })
    },
    void 0,
    false,
    {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
      lineNumber: 193,
      columnNumber: 9
    }
  );
}, "RowMenuButton");
const Row = /* @__PURE__ */ __name((props) => {
  const { row, labels, count, selected, moving, related, active, rtl, pagesize, actions, display, menu, menuopen } = props;
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
            lineNumber: 256,
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
              lineNumber: 257,
              columnNumber: 17
            }
          ),
          /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-ometa", "aria-hidden": "true", children: remaining }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
            lineNumber: 264,
            columnNumber: 17
          })
        ]
      },
      void 0,
      true,
      {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 254,
        columnNumber: 13
      }
    );
  }
  const { shown, said: metasaid } = meta(labels, row, count, related);
  const node = row.node;
  const extras = displayed(labels, node, display);
  const said = [...extras.map((extra) => extra.said), metasaid].filter((part) => part !== "").join(", ");
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
              lineNumber: 312,
              columnNumber: 21
            })
          },
          void 0,
          false,
          {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
            lineNumber: 310,
            columnNumber: 17
          }
        ) : /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-otoggle", "aria-hidden": "true" }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 314,
          columnNumber: 17
        }),
        /* @__PURE__ */ jsxDEV("span", { className: markclass, style: markstyle, "aria-hidden": "true" }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 315,
          columnNumber: 13
        }),
        /* @__PURE__ */ jsxDEV("span", { id: `${ID}-${row.key}-name`, className: "local-dimensions-hubv2-oname", title: row.name, children: row.name }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 318,
          columnNumber: 13
        }),
        extras.length > 0 ? /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-oextra", "aria-hidden": "true", children: extras.map((extra) => extra.shown).join(" \xB7 ") }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 320,
          columnNumber: 17
        }) : null,
        shown.length > 0 ? /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-ometa", "aria-hidden": "true", children: shown }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 324,
          columnNumber: 33
        }) : null,
        menu ? /* @__PURE__ */ jsxDEV(RowMenuButton, { labels, row, open: menuopen, actions }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 325,
          columnNumber: 21
        }) : null,
        said ? /* @__PURE__ */ jsxDEV("span", { id: `${ID}-${row.key}-desc`, className: "visually-hidden", children: said }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 326,
          columnNumber: 21
        }) : null
      ]
    },
    void 0,
    true,
    {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
      lineNumber: 299,
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
        lineNumber: 343,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-sk-block local-dimensions-hubv2-sk-mark" }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 344,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-sk-block local-dimensions-hubv2-sk-line" }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 345,
        columnNumber: 17
      })
    ]
  },
  `${parent}-sk${index}`,
  true,
  {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
    lineNumber: 340,
    columnNumber: 13
  }
)) }, void 0, false, {
  fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
  lineNumber: 338,
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
    actions,
    display,
    menuFor,
    menuOpen,
    editor,
    hints,
    mac
  } = props;
  const scrolled = useRef(null);
  const optionsButton = useRef(null);
  const [options, setOptions] = useState(false);
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
    if (editor && editor.replace && editor.key === row.key) {
      items.push(editor.node);
      return;
    }
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
          actions,
          display,
          menu: row.kind === "competency" && row.key === selectedkey && menuFor === row.id,
          menuopen: menuOpen === row.id
        },
        row.key,
        false,
        {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 393,
          columnNumber: 13
        }
      )
    );
    if (row.kind === "competency" && !row.expanded && loading.includes(row.id)) {
      items.push(/* @__PURE__ */ jsxDEV(SkeletonRows, { level: row.level + 1, parent: row.key }, `${row.key}-loading`, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 411,
        columnNumber: 24
      }));
    }
    if (editor && !editor.replace && editor.key === row.key) {
      items.push(editor.node);
    }
  });
  const closeOptions = /* @__PURE__ */ __name((outside) => {
    setOptions(false);
    if (!outside) {
      optionsButton.current?.focus();
    }
  }, "closeOptions");
  return /* @__PURE__ */ jsxDEV("section", { id: ID, className: "local-dimensions-hubv2-outline", "aria-labelledby": `${ID}-title`, children: [
    /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-ohead", children: [
      /* @__PURE__ */ jsxDEV("h3", { id: `${ID}-title`, className: "local-dimensions-hubv2-otitle", children: labels.hubv2_outline }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 435,
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
            lineNumber: 444,
            columnNumber: 21
          })
        },
        void 0,
        false,
        {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 436,
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
            lineNumber: 454,
            columnNumber: 21
          })
        },
        void 0,
        false,
        {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 446,
          columnNumber: 17
        }
      ),
      /* @__PURE__ */ jsxDEV(
        "button",
        {
          ref: optionsButton,
          type: "button",
          className: "local-dimensions-hubv2-iconbtn local-dimensions-tip local-dimensions-tip-bottom",
          "aria-label": labels.managecompetencies_displayoptions,
          "data-dim-tip": labels.managecompetencies_displayoptions,
          "aria-haspopup": "menu",
          "aria-expanded": options,
          onClick: () => setOptions((open) => !open),
          children: [
            /* @__PURE__ */ jsxDEV("i", { className: "fa fa-sliders", "aria-hidden": "true" }, void 0, false, {
              fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
              lineNumber: 466,
              columnNumber: 21
            }),
            /* @__PURE__ */ jsxDEV("i", { className: "fa fa-chevron-down local-dimensions-hubv2-iconbtn-chevron", "aria-hidden": "true" }, void 0, false, {
              fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
              lineNumber: 467,
              columnNumber: 21
            })
          ]
        },
        void 0,
        true,
        {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 456,
          columnNumber: 17
        }
      )
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
      lineNumber: 434,
      columnNumber: 13
    }),
    options && optionsButton.current ? /* @__PURE__ */ jsxDEV(
      Menu,
      {
        label: labels.managecompetencies_displayoptions,
        items: [
          { id: "idnumber", label: labels.managecompetencies_showids, checkbox: true, checked: display.idnumber },
          { id: "taxonomy", label: labels.managecompetencies_showtaxonomy, checkbox: true, checked: display.taxonomy }
        ],
        anchor: optionsButton.current,
        pane: null,
        rtl,
        mac,
        onRun: (option) => actions.toggleDisplay(option === "idnumber" ? "idnumber" : "taxonomy"),
        onClose: closeOptions
      },
      void 0,
      false,
      {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 471,
        columnNumber: 17
      }
    ) : null,
    notice ? /* @__PURE__ */ jsxDEV("p", { className: "local-dimensions-hubv2-onotice", children: notice }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
      lineNumber: 485,
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
        lineNumber: 486,
        columnNumber: 13
      }
    ),
    loading.length > 0 ? /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", role: "status", children: labels.hubv2_loading }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
      lineNumber: 498,
      columnNumber: 35
    }) : null,
    hints.length > 0 && !empty ? /* @__PURE__ */ jsxDEV("p", { className: "local-dimensions-hubv2-ohints", children: hints.map((hint) => /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-ohint", children: [
      /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-keys", children: shortcutCaps(hint.shortcut, mac).map((cap) => /* @__PURE__ */ jsxDEV("kbd", { children: cap }, cap, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 504,
        columnNumber: 80
      })) }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 503,
        columnNumber: 29
      }),
      hint.label
    ] }, hint.label, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
      lineNumber: 502,
      columnNumber: 25
    })) }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
      lineNumber: 500,
      columnNumber: 17
    }) : null,
    empty ? /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-oempty", children: [
      /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-oempty-icon", "aria-hidden": "true", children: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-sitemap" }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 513,
        columnNumber: 93
      }) }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 513,
        columnNumber: 21
      }),
      /* @__PURE__ */ jsxDEV("p", { className: "local-dimensions-hubv2-oempty-title", children: labels.nocompetencies }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 514,
        columnNumber: 21
      }),
      framework.canmanage && moving === null ? /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-primary btn-sm", onClick: actions.add, children: [
        /* @__PURE__ */ jsxDEV("i", { className: "fa fa-plus me-1", "aria-hidden": "true" }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 517,
          columnNumber: 29
        }),
        labels.managecompetencies_addcompetency
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 516,
        columnNumber: 25
      }) : null
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
      lineNumber: 512,
      columnNumber: 17
    }) : null
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
    lineNumber: 433,
    columnNumber: 9
  });
}, "Outline");
var Outline_default = Outline;
export {
  ID as OUTLINE_ID,
  Outline_default as default
};
//# sourceMappingURL=Outline.dev.js.map
