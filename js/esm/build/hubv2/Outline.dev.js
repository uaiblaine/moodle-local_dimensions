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
 * screen reader already reads from aria-expanded and aria-level is not said twice. A lens adds its short meta
 * (a count, a mark) and its words to the description; while the checker is open a row with findings carries a
 * warning mark and their count, said in the description too.
 *
 * @module     local_dimensions/hubv2/Outline
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useEffect, useRef, useState } from "react";
import { levelTerm } from "./columns";
import { shortcutCaps } from "./commands";
import { isHexColour } from "./html";
import { markWords } from "./lenses";
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
        lineNumber: 158,
        columnNumber: 24
      }));
      said.push(fillCount(labels, row.kind === "framework" ? "hubv2_competencies" : "hubv2_children", part.value));
    } else if (part.kind === "rule") {
      shown.push(/* @__PURE__ */ jsxDEV("i", { className: "fa fa-diagram-project local-dimensions-hubv2-ometa-rule" }, "rule", false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 161,
        columnNumber: 24
      }));
      said.push(fill(labels.hubv2_a11y_rule, part.label));
    } else {
      shown.push(
        /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-ometa-related", children: [
          /* @__PURE__ */ jsxDEV("i", { className: "fa fa-share-nodes" }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
            lineNumber: 166,
            columnNumber: 21
          }),
          part.value
        ] }, "related", true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 165,
          columnNumber: 17
        })
      );
      said.push(fillCount(labels, "hubv2_outline_related", part.value));
    }
  });
  return { shown, said: said.join(", ") };
}, "meta");
const displayed = /* @__PURE__ */ __name((labels, node, display, term) => {
  const parts = [];
  if (node && display.idnumber && node.idnumber) {
    parts.push({ shown: node.idnumber, said: `${labels["core:idnumber"]} ${node.idnumber}` });
  }
  if (node && display.taxonomy && term) {
    parts.push({ shown: term, said: term });
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
        lineNumber: 229,
        columnNumber: 13
      })
    },
    void 0,
    false,
    {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
      lineNumber: 212,
      columnNumber: 9
    }
  );
}, "RowMenuButton");
const rowClasses = /* @__PURE__ */ __name((selected, moving, impact) => {
  const classes = ["local-dimensions-hubv2-orow"];
  if (selected) {
    classes.push("local-dimensions-hubv2-orow-selected");
  }
  if (moving) {
    classes.push("local-dimensions-hubv2-orow-moving");
  }
  if (impact) {
    classes.push("local-dimensions-hubv2-impact");
  }
  return classes;
}, "rowClasses");
const LensMeta = /* @__PURE__ */ __name(({ mark, findings }) => {
  const meta2 = mark ? mark.meta : null;
  return /* @__PURE__ */ jsxDEV(Fragment, { children: [
    meta2 ? /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-ometa local-dimensions-hubv2-olens", "aria-hidden": "true", children: [
      meta2.dashed ? /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-ometa-unused" }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 269,
        columnNumber: 36
      }) : null,
      meta2.icon ? /* @__PURE__ */ jsxDEV("i", { className: `fa ${meta2.icon}` }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 270,
        columnNumber: 34
      }) : null,
      meta2.text
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
      lineNumber: 268,
      columnNumber: 17
    }) : null,
    findings > 0 ? /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-findmark", "aria-hidden": "true", children: [
      /* @__PURE__ */ jsxDEV("i", { className: "fa fa-triangle-exclamation" }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 276,
        columnNumber: 21
      }),
      findings
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
      lineNumber: 275,
      columnNumber: 17
    }) : null
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
    lineNumber: 266,
    columnNumber: 9
  });
}, "LensMeta");
const Row = /* @__PURE__ */ __name((props) => {
  const {
    row,
    labels,
    count,
    selected,
    moving,
    related,
    active,
    rtl,
    pagesize,
    actions,
    display,
    menu,
    menuopen,
    term,
    mark,
    findings,
    findingwords,
    impact
  } = props;
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
            lineNumber: 331,
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
              lineNumber: 332,
              columnNumber: 17
            }
          ),
          /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-ometa", "aria-hidden": "true", children: remaining }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
            lineNumber: 339,
            columnNumber: 17
          })
        ]
      },
      void 0,
      true,
      {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 329,
        columnNumber: 13
      }
    );
  }
  const { shown, said: metasaid } = meta(labels, row, count, related);
  const node = row.node;
  const extras = displayed(labels, node, display, term);
  const said = [...extras.map((extra) => extra.said), metasaid, ...markWords(mark, findings, findingwords)].filter((part) => part !== "").join(", ");
  let markclass = "local-dimensions-hubv2-mark local-dimensions-hubv2-mark-framework";
  let markstyle;
  if (node) {
    markstyle = isHexColour(node.bgcolor) ? { backgroundColor: node.bgcolor } : void 0;
    markclass = markstyle ? "local-dimensions-hubv2-mark" : `local-dimensions-hubv2-mark ${DEPTH_MARKS[Math.min(row.level - 1, DEPTH_MARKS.length) - 1]}`;
  }
  const classes = rowClasses(selected, moving, impact);
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
              lineNumber: 382,
              columnNumber: 21
            })
          },
          void 0,
          false,
          {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
            lineNumber: 380,
            columnNumber: 17
          }
        ) : /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-otoggle", "aria-hidden": "true" }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 384,
          columnNumber: 17
        }),
        /* @__PURE__ */ jsxDEV("span", { className: markclass, style: markstyle, "aria-hidden": "true" }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 385,
          columnNumber: 13
        }),
        /* @__PURE__ */ jsxDEV("span", { id: `${ID}-${row.key}-name`, className: "local-dimensions-hubv2-oname", title: row.name, children: row.name }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 388,
          columnNumber: 13
        }),
        extras.length > 0 ? /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-oextra", "aria-hidden": "true", children: extras.map((extra) => extra.shown).join(" \xB7 ") }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 390,
          columnNumber: 17
        }) : null,
        shown.length > 0 ? /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-ometa", "aria-hidden": "true", children: shown }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 394,
          columnNumber: 33
        }) : null,
        /* @__PURE__ */ jsxDEV(LensMeta, { mark, findings }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 395,
          columnNumber: 13
        }),
        menu ? /* @__PURE__ */ jsxDEV(RowMenuButton, { labels, row, open: menuopen, actions }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 396,
          columnNumber: 21
        }) : null,
        said ? /* @__PURE__ */ jsxDEV("span", { id: `${ID}-${row.key}-desc`, className: "visually-hidden", children: said }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 397,
          columnNumber: 21
        }) : null
      ]
    },
    void 0,
    true,
    {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
      lineNumber: 369,
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
        lineNumber: 414,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-sk-block local-dimensions-hubv2-sk-mark" }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 415,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-sk-block local-dimensions-hubv2-sk-line" }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 416,
        columnNumber: 17
      })
    ]
  },
  `${parent}-sk${index}`,
  true,
  {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
    lineNumber: 411,
    columnNumber: 13
  }
)) }, void 0, false, {
  fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
  lineNumber: 409,
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
    mac,
    marks,
    findings,
    findingwords,
    impact
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
          menu: row.kind !== "more" && row.key === selectedkey && menuFor === row.id,
          menuopen: menuOpen === row.id,
          term: row.kind === "competency" ? levelTerm(framework.taxonomies, framework.taxonomyfallback, row.level - 1) : "",
          mark: row.kind === "competency" ? marks.get(row.id) ?? null : null,
          findings: row.kind === "more" ? 0 : findings.get(row.id) ?? 0,
          findingwords,
          impact: row.kind !== "more" && impact.has(row.id)
        },
        row.key,
        false,
        {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 464,
          columnNumber: 13
        }
      )
    );
    if (row.kind === "competency" && !row.expanded && loading.includes(row.id)) {
      items.push(/* @__PURE__ */ jsxDEV(SkeletonRows, { level: row.level + 1, parent: row.key }, `${row.key}-loading`, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 487,
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
        lineNumber: 511,
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
            lineNumber: 520,
            columnNumber: 21
          })
        },
        void 0,
        false,
        {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 512,
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
            lineNumber: 530,
            columnNumber: 21
          })
        },
        void 0,
        false,
        {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 522,
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
              lineNumber: 542,
              columnNumber: 21
            }),
            /* @__PURE__ */ jsxDEV("i", { className: "fa fa-chevron-down local-dimensions-hubv2-iconbtn-chevron", "aria-hidden": "true" }, void 0, false, {
              fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
              lineNumber: 543,
              columnNumber: 21
            })
          ]
        },
        void 0,
        true,
        {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 532,
          columnNumber: 17
        }
      )
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
      lineNumber: 510,
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
        lineNumber: 547,
        columnNumber: 17
      }
    ) : null,
    notice ? /* @__PURE__ */ jsxDEV("p", { className: "local-dimensions-hubv2-onotice", children: notice }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
      lineNumber: 561,
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
        lineNumber: 562,
        columnNumber: 13
      }
    ),
    loading.length > 0 ? /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", role: "status", children: labels.hubv2_loading }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
      lineNumber: 574,
      columnNumber: 35
    }) : null,
    hints.length > 0 && !empty ? /* @__PURE__ */ jsxDEV("p", { className: "local-dimensions-hubv2-ohints", children: hints.map((hint) => /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-ohint", children: [
      /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-keys", children: shortcutCaps(hint.shortcut, mac).map((cap) => /* @__PURE__ */ jsxDEV("kbd", { children: cap }, cap, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 580,
        columnNumber: 80
      })) }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 579,
        columnNumber: 29
      }),
      hint.label
    ] }, hint.label, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
      lineNumber: 578,
      columnNumber: 25
    })) }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
      lineNumber: 576,
      columnNumber: 17
    }) : null,
    empty ? /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-oempty", children: [
      /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-oempty-icon", "aria-hidden": "true", children: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-sitemap" }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 589,
        columnNumber: 93
      }) }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 589,
        columnNumber: 21
      }),
      /* @__PURE__ */ jsxDEV("p", { className: "local-dimensions-hubv2-oempty-title", children: labels.nocompetencies }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 590,
        columnNumber: 21
      }),
      framework.canmanage && moving === null ? /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-primary btn-sm", onClick: actions.add, children: [
        /* @__PURE__ */ jsxDEV("i", { className: "fa fa-plus me-1", "aria-hidden": "true" }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
          lineNumber: 593,
          columnNumber: 29
        }),
        labels.managecompetencies_addcompetency
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
        lineNumber: 592,
        columnNumber: 25
      }) : null
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
      lineNumber: 588,
      columnNumber: 17
    }) : null
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Outline.tsx",
    lineNumber: 509,
    columnNumber: 9
  });
}, "Outline");
var Outline_default = Outline;
export {
  ID as OUTLINE_ID,
  Outline_default as default
};
//# sourceMappingURL=Outline.dev.js.map
