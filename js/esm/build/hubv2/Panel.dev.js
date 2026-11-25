var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The panel beside the outline and the map: the selected competency's details and actions, or the
 * framework's when nothing is selected, and the controls of the move mode while it lasts.
 *
 * Every related competency is listed in words, each a button that reveals it in both panes; the map
 * draws, besides, a dotted edge to those it shows. A relation is never said by the drawing alone.
 *
 * Its buttons are the action registry's (commands.ts), the same list and the same decision the node's
 * menu and the command palette draw from: "Edit" and "Add child", then "More actions", which opens the
 * node's own menu under itself (the destructive action lives there, last). With nothing selected the root is
 * selected, and the panel shows the framework: its name, ID number, context, visibility (a status label), scale
 * with the values the framework counts as proficient, the taxonomy term of each level, the competencies' total
 * and the summary (summary.ts), which the palette's command brings into view; then the framework's own actions
 * ("Edit structure", then "More actions", the root's menu) and "Add competency". During a move it shows what the
 * move needs.
 *
 * A lens shown puts its section first (its sentence, legend and list, LensPanel.tsx), above whatever is selected;
 * the checker, while open, is the panel's whole content until its close button or a selection closes it.
 *
 * Under a selected competency's own content come the sections other plugins add (sections.ts), one block each
 * in the order received: a heading, a definition list of plain rows (a tone drawn as a status label around its
 * text, never colour alone), a muted note and one link to a page of this site in the same tab. No section draws
 * nothing at all; a failed read says so in one muted line and leaves the rest of the panel as it is.
 *
 * @module     local_dimensions/hubv2/Panel
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { isHexColour } from "./html";
import { headingId, toneClass } from "./sections";
import { fill, fillCount } from "./strings";
const PANEL_BUTTONS = ["edit", "addchild"];
const FRAMEWORK_BUTTONS = ["fwedit"];
const Pair = /* @__PURE__ */ __name(({ label, children }) => /* @__PURE__ */ jsxDEV(Fragment, { children: [
  /* @__PURE__ */ jsxDEV("dt", { className: "local-dimensions-hubv2-kv-label", children: label }, void 0, false, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
    lineNumber: 106,
    columnNumber: 9
  }),
  /* @__PURE__ */ jsxDEV("dd", { className: "local-dimensions-hubv2-kv-value", children }, void 0, false, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
    lineNumber: 107,
    columnNumber: 9
  })
] }, void 0, true, {
  fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
  lineNumber: 105,
  columnNumber: 5
}), "Pair");
const UsageLists = /* @__PURE__ */ __name(({ labels, usage }) => {
  if (!usage) {
    return /* @__PURE__ */ jsxDEV("p", { className: "text-muted small mb-0", children: labels.hubv2_loading }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 119,
      columnNumber: 16
    });
  }
  const empty = usage.templates.length + usage.courses.length + usage.activities.length === 0;
  if (empty) {
    return /* @__PURE__ */ jsxDEV("p", { className: "text-muted small mb-0", children: labels.hubv2_usage_none }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 123,
      columnNumber: 16
    });
  }
  return /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-usagelists small", children: [
    usage.templates.length > 0 ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
      /* @__PURE__ */ jsxDEV("h4", { className: "h6 mt-2", children: labels.hubv2_usage_templates }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 129,
        columnNumber: 21
      }),
      /* @__PURE__ */ jsxDEV("ul", { className: "list-unstyled mb-0", children: usage.templates.map((t) => /* @__PURE__ */ jsxDEV("li", { children: t.name }, `t${t.id}`, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 130,
        columnNumber: 84
      })) }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 130,
        columnNumber: 21
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 128,
      columnNumber: 17
    }) : null,
    usage.courses.length > 0 ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
      /* @__PURE__ */ jsxDEV("h4", { className: "h6 mt-2", children: labels.hubv2_usage_courses }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 135,
        columnNumber: 21
      }),
      /* @__PURE__ */ jsxDEV("ul", { className: "list-unstyled mb-0", children: usage.courses.map((c) => /* @__PURE__ */ jsxDEV("li", { children: /* @__PURE__ */ jsxDEV("a", { href: c.url, children: c.name }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 137,
        columnNumber: 72
      }) }, `c${c.id}`, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 137,
        columnNumber: 51
      })) }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 136,
        columnNumber: 21
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 134,
      columnNumber: 17
    }) : null,
    usage.activities.length > 0 ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
      /* @__PURE__ */ jsxDEV("h4", { className: "h6 mt-2", children: labels.hubv2_usage_activities }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 143,
        columnNumber: 21
      }),
      /* @__PURE__ */ jsxDEV("ul", { className: "list-unstyled mb-0", children: usage.activities.map((a) => /* @__PURE__ */ jsxDEV("li", { children: [
        a.url ? /* @__PURE__ */ jsxDEV("a", { href: a.url, children: a.name }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 147,
          columnNumber: 42
        }) : a.name,
        /* @__PURE__ */ jsxDEV("span", { className: "text-muted", children: [
          " \xB7 ",
          a.courseshortname
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 148,
          columnNumber: 33
        })
      ] }, `a${a.cmid}`, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 146,
        columnNumber: 29
      })) }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 144,
        columnNumber: 21
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 142,
      columnNumber: 17
    }) : null
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
    lineNumber: 126,
    columnNumber: 9
  });
}, "UsageLists");
const buttonClass = /* @__PURE__ */ __name((view, first) => {
  if (view.spec.danger) {
    return "btn btn-sm btn-outline-danger";
  }
  return first ? "btn btn-sm btn-primary" : "btn btn-sm btn-outline-secondary";
}, "buttonClass");
const MoreButton = /* @__PURE__ */ __name(({ labels, open, menu }) => /* @__PURE__ */ jsxDEV(
  "button",
  {
    type: "button",
    className: "btn btn-sm btn-outline-secondary",
    "aria-haspopup": "menu",
    "aria-expanded": open,
    onClick: (event) => menu(event.currentTarget),
    children: [
      labels.hubv2_moreactions,
      /* @__PURE__ */ jsxDEV("i", { className: "fa fa-chevron-down ms-1", "aria-hidden": "true" }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 187,
        columnNumber: 35
      })
    ]
  },
  void 0,
  true,
  {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
    lineNumber: 180,
    columnNumber: 5
  }
), "MoreButton");
const visibilityClass = /* @__PURE__ */ __name((visible) => visible ? "local-dimensions-hubv2-status local-dimensions-hubv2-status--success" : "local-dimensions-hubv2-status", "visibilityClass");
const FrameworkFacts = /* @__PURE__ */ __name(({ labels, framework }) => /* @__PURE__ */ jsxDEV(Fragment, { children: [
  /* @__PURE__ */ jsxDEV("section", { className: "local-dimensions-hubv2-sec", children: [
    /* @__PURE__ */ jsxDEV("dl", { className: "local-dimensions-hubv2-kv mb-0", children: [
      /* @__PURE__ */ jsxDEV(Pair, { label: labels.hubv2_fw_context, children: framework.contextname }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 212,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV(Pair, { label: labels.hubv2_fw_visibility, children: /* @__PURE__ */ jsxDEV("span", { "data-region": "framework-visibility", className: visibilityClass(framework.visible), children: [
        /* @__PURE__ */ jsxDEV("i", { className: `fa ${framework.visible ? "fa-eye" : "fa-eye-slash"}`, "aria-hidden": "true" }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 215,
          columnNumber: 25
        }),
        labels[framework.visible ? "tool_lp:visible" : "tool_lp:hidden"]
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 214,
        columnNumber: 21
      }) }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 213,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV(Pair, { label: labels.hubv2_scale, children: framework.scale.name || "-" }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 219,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 211,
      columnNumber: 13
    }),
    framework.scale.values.length > 0 ? /* @__PURE__ */ jsxDEV("ul", { className: "local-dimensions-hubv2-scalevalues list-unstyled mb-0", children: framework.scale.values.map((value) => /* @__PURE__ */ jsxDEV("li", { children: [
      /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-scalevalue", children: value.name }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 225,
        columnNumber: 29
      }),
      value.proficient ? /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-status local-dimensions-hubv2-status--success", children: labels.central_frameworks_scaleproficient }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 227,
        columnNumber: 33
      }) : null,
      value.isdefault ? /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-status", children: labels.central_frameworks_scaledefault }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 232,
        columnNumber: 33
      }) : null
    ] }, value.id, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 224,
      columnNumber: 25
    })) }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 222,
      columnNumber: 17
    }) : null
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
    lineNumber: 210,
    columnNumber: 9
  }),
  framework.taxonomies.length > 0 ? /* @__PURE__ */ jsxDEV("section", { className: "local-dimensions-hubv2-sec", children: [
    /* @__PURE__ */ jsxDEV("h4", { className: "local-dimensions-hubv2-sectitle", children: labels["tool_lp:taxonomies"] }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 241,
      columnNumber: 17
    }),
    /* @__PURE__ */ jsxDEV("dl", { className: "local-dimensions-hubv2-kv mb-0", children: framework.taxonomies.map((term, index) => /* @__PURE__ */ jsxDEV(Pair, { label: fill(labels.hubv2_fw_level, String(index + 1)), children: term }, index, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 244,
      columnNumber: 25
    })) }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 242,
      columnNumber: 17
    })
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
    lineNumber: 240,
    columnNumber: 13
  }) : null
] }, void 0, true, {
  fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
  lineNumber: 209,
  columnNumber: 5
}), "FrameworkFacts");
const ActionButtons = /* @__PURE__ */ __name(({ labels, views, run, secondary = false }) => /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-actions d-flex flex-wrap gap-2", children: views.map((view, index) => /* @__PURE__ */ jsxDEV(
  "button",
  {
    type: "button",
    className: buttonClass(view, index === 0 && !secondary),
    disabled: view.state === "disabled",
    title: view.state === "disabled" && view.spec.id === "rule" ? labels.central_rule_nochildren : void 0,
    onClick: () => run(view.spec.id),
    children: [
      /* @__PURE__ */ jsxDEV("i", { className: `fa ${view.spec.icon} me-1`, "aria-hidden": "true" }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 275,
        columnNumber: 17
      }),
      labels[view.spec.label]
    ]
  },
  view.spec.id,
  true,
  {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
    lineNumber: 267,
    columnNumber: 13
  }
)) }, void 0, false, {
  fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
  lineNumber: 265,
  columnNumber: 5
}), "ActionButtons");
const MoveSection = /* @__PURE__ */ __name(({ labels, moving, views, run }) => /* @__PURE__ */ jsxDEV("section", { className: "local-dimensions-hubv2-sec local-dimensions-hubv2-movesec", children: [
  /* @__PURE__ */ jsxDEV("p", { className: "mb-2", children: fill(labels.hubv2_movepick, moving.shortname) }, void 0, false, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
    lineNumber: 294,
    columnNumber: 9
  }),
  /* @__PURE__ */ jsxDEV(ActionButtons, { labels, views, run }, void 0, false, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
    lineNumber: 295,
    columnNumber: 9
  })
] }, void 0, true, {
  fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
  lineNumber: 293,
  columnNumber: 5
}), "MoveSection");
const CompanionSection = /* @__PURE__ */ __name(({ section }) => /* @__PURE__ */ jsxDEV(
  "section",
  {
    className: "local-dimensions-hubv2-sec",
    "data-region": "companion-section",
    "data-key": section.key,
    "aria-labelledby": headingId(section.key),
    children: [
      /* @__PURE__ */ jsxDEV("h4", { id: headingId(section.key), className: "local-dimensions-hubv2-sectitle", children: section.title }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 308,
        columnNumber: 9
      }),
      section.rows.length > 0 ? /* @__PURE__ */ jsxDEV("dl", { className: "local-dimensions-hubv2-kv mb-0", children: section.rows.map((row, index) => /* @__PURE__ */ jsxDEV(Pair, { label: row.label, children: row.tone ? /* @__PURE__ */ jsxDEV("span", { className: toneClass(row.tone), children: row.value }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 313,
        columnNumber: 37
      }) : row.value }, index, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 312,
        columnNumber: 21
      })) }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 310,
        columnNumber: 13
      }) : null,
      section.note ? /* @__PURE__ */ jsxDEV("p", { className: "text-muted small mb-0", children: section.note }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 318,
        columnNumber: 25
      }) : null,
      section.linkurl ? /* @__PURE__ */ jsxDEV("a", { className: "btn btn-link btn-sm p-0 text-start", href: section.linkurl, children: section.linklabel }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 320,
        columnNumber: 13
      }) : null
    ]
  },
  void 0,
  true,
  {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
    lineNumber: 306,
    columnNumber: 5
  }
), "CompanionSection");
const CompanionSections = /* @__PURE__ */ __name(({ labels, state }) => {
  if (!state) {
    return null;
  }
  if (state.status === "failed") {
    return /* @__PURE__ */ jsxDEV("p", { className: "local-dimensions-hubv2-sec text-muted small mb-0", "data-region": "companion-sections-failed", children: labels.hubv2_sections_failed }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 337,
      columnNumber: 13
    });
  }
  return /* @__PURE__ */ jsxDEV(Fragment, { children: state.sections.map((section) => /* @__PURE__ */ jsxDEV(CompanionSection, { section }, section.key, false, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
    lineNumber: 342,
    columnNumber: 47
  })) }, void 0, false, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
    lineNumber: 342,
    columnNumber: 12
  });
}, "CompanionSections");
const Panel = /* @__PURE__ */ __name((props) => {
  const {
    labels,
    framework,
    count,
    node,
    path,
    usage,
    related,
    moving,
    nodeactions,
    menuopen,
    moveactions,
    topactions,
    frameworkactions,
    summary,
    summaryRef,
    actions,
    lens,
    checker,
    sections
  } = props;
  const movesection = moving ? /* @__PURE__ */ jsxDEV(MoveSection, { labels, moving, views: moveactions, run: actions.run }, void 0, false, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
    lineNumber: 354,
    columnNumber: 34
  }) : null;
  if (checker) {
    return /* @__PURE__ */ jsxDEV("aside", { className: "local-dimensions-hubv2-panel", "aria-label": labels.hubv2_checker_title, children: [
      movesection,
      checker
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 358,
      columnNumber: 13
    });
  }
  if (!node) {
    return /* @__PURE__ */ jsxDEV("aside", { className: "local-dimensions-hubv2-panel", "aria-label": framework.shortname, children: [
      movesection,
      lens,
      /* @__PURE__ */ jsxDEV("section", { className: "local-dimensions-hubv2-sec", children: [
        /* @__PURE__ */ jsxDEV("p", { className: "local-dimensions-hubv2-kicker", children: [
          labels.central_browseframeworks_framework,
          framework.idnumber ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
            " \xB7 ",
            framework.idnumber
          ] }, void 0, true, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 373,
            columnNumber: 47
          }) : null
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 371,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV("h3", { className: "h5 mb-1", children: framework.shortname }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 375,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV("p", { className: "text-muted mb-0", children: fillCount(labels, "hubv2_competencies", count) }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 376,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 370,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV(FrameworkFacts, { labels, framework }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 378,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("section", { className: "local-dimensions-hubv2-sec", children: [
        /* @__PURE__ */ jsxDEV("h4", { ref: summaryRef, tabIndex: -1, className: "local-dimensions-hubv2-sectitle", children: labels.hubv2_summary_title }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 380,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV("dl", { className: "local-dimensions-hubv2-kv mb-0", children: summary.map((line) => /* @__PURE__ */ jsxDEV(Pair, { label: line.label, children: line.value }, line.label, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 382,
          columnNumber: 48
        })) }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 381,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV("p", { className: "text-muted small mb-0", children: labels.hubv2_noselection }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 384,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 379,
        columnNumber: 17
      }),
      frameworkactions.length > 0 ? /* @__PURE__ */ jsxDEV("section", { className: "local-dimensions-hubv2-sec", children: [
        /* @__PURE__ */ jsxDEV("h4", { className: "local-dimensions-hubv2-sectitle", children: labels.hubv2_actions }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 388,
          columnNumber: 25
        }),
        /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-actions d-flex flex-wrap gap-2", children: [
          /* @__PURE__ */ jsxDEV(
            ActionButtons,
            {
              labels,
              views: frameworkactions.filter((view) => FRAMEWORK_BUTTONS.includes(view.spec.id)),
              run: actions.run
            },
            void 0,
            false,
            {
              fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
              lineNumber: 390,
              columnNumber: 29
            }
          ),
          /* @__PURE__ */ jsxDEV(MoreButton, { labels, open: menuopen, menu: actions.menu }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 393,
            columnNumber: 29
          })
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 389,
          columnNumber: 25
        })
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 387,
        columnNumber: 21
      }) : null,
      topactions.length > 0 ? /* @__PURE__ */ jsxDEV("section", { className: "local-dimensions-hubv2-sec", children: /* @__PURE__ */ jsxDEV(
        ActionButtons,
        {
          labels,
          views: topactions,
          run: actions.run,
          secondary: frameworkactions.length > 0
        },
        void 0,
        false,
        {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 399,
          columnNumber: 25
        }
      ) }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 398,
        columnNumber: 21
      }) : null
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 367,
      columnNumber: 13
    });
  }
  return /* @__PURE__ */ jsxDEV("aside", { className: "local-dimensions-hubv2-panel", "aria-label": node.shortname, children: [
    movesection,
    lens,
    /* @__PURE__ */ jsxDEV("section", { className: "local-dimensions-hubv2-sec", children: [
      /* @__PURE__ */ jsxDEV("p", { className: "local-dimensions-hubv2-kicker", children: [
        node.taxonomy,
        node.idnumber ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
          " \xB7 ",
          /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: [
            labels["core:idnumber"],
            " "
          ] }, void 0, true, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 415,
            columnNumber: 30
          }),
          node.idnumber
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 415,
          columnNumber: 25
        }) : null
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 412,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("h3", { className: "h5 mb-1", children: node.shortname }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 418,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("p", { className: "text-muted small mb-0", children: [
        /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: [
          labels.hubv2_path,
          ": "
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 420,
          columnNumber: 21
        }),
        path.length > 0 ? path.join(" / ") : labels.hubv2_rootlevel
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 419,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 411,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("section", { className: "local-dimensions-hubv2-sec", children: [
      /* @__PURE__ */ jsxDEV("h4", { className: "local-dimensions-hubv2-sectitle", children: labels.hubv2_usage }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 426,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("ul", { className: "local-dimensions-hubv2-usage list-unstyled mb-2", children: [
        /* @__PURE__ */ jsxDEV("li", { children: [
          /* @__PURE__ */ jsxDEV("b", { children: node.templatecount }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 428,
            columnNumber: 25
          }),
          /* @__PURE__ */ jsxDEV("span", { children: labels.hubv2_usage_templates }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 428,
            columnNumber: 52
          })
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 428,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV("li", { children: [
          /* @__PURE__ */ jsxDEV("b", { children: node.coursecount }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 429,
            columnNumber: 25
          }),
          /* @__PURE__ */ jsxDEV("span", { children: labels.hubv2_usage_courses }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 429,
            columnNumber: 50
          })
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 429,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV("li", { children: [
          /* @__PURE__ */ jsxDEV("b", { children: node.activitycount }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 430,
            columnNumber: 25
          }),
          /* @__PURE__ */ jsxDEV("span", { children: labels.hubv2_usage_activities }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 430,
            columnNumber: 52
          })
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 430,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 427,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("details", { className: "local-dimensions-hubv2-details", children: [
        /* @__PURE__ */ jsxDEV("summary", { children: labels.hubv2_seeusage }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 433,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV(UsageLists, { labels, usage }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 434,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 432,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 425,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("section", { className: "local-dimensions-hubv2-sec", children: [
      /* @__PURE__ */ jsxDEV("h4", { className: "local-dimensions-hubv2-sectitle", children: labels.hubv2_assessment }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 439,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("dl", { className: "local-dimensions-hubv2-kv mb-0", children: [
        /* @__PURE__ */ jsxDEV(Pair, { label: labels.hubv2_scale, children: node.scale }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 441,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV(Pair, { label: labels.hubv2_rule, children: node.ruletype ? node.rulelabel : labels.managecompetencies_norule }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 442,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 440,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 438,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("section", { className: "local-dimensions-hubv2-sec", children: [
      /* @__PURE__ */ jsxDEV("h4", { className: "local-dimensions-hubv2-sectitle", children: labels.hubv2_appearance }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 447,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("dl", { className: "local-dimensions-hubv2-kv mb-0", children: [
        /* @__PURE__ */ jsxDEV(Pair, { label: labels.hubv2_colour, children: isHexColour(node.bgcolor) ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
          /* @__PURE__ */ jsxDEV(
            "span",
            {
              className: "local-dimensions-hubv2-swatch",
              "aria-hidden": "true",
              style: { backgroundColor: node.bgcolor }
            },
            void 0,
            false,
            {
              fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
              lineNumber: 452,
              columnNumber: 33
            }
          ),
          node.bgcolor
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 451,
          columnNumber: 29
        }) : labels.hubv2_nocolour }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 449,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV(Pair, { label: labels.hubv2_tag1, children: node.tag1 || "-" }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 458,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV(Pair, { label: labels.hubv2_tag2, children: node.tag2 || "-" }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 459,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV(Pair, { label: labels.hubv2_type, children: node.type || "-" }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 460,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 448,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 446,
      columnNumber: 13
    }),
    node.description ? /* @__PURE__ */ jsxDEV("section", { className: "local-dimensions-hubv2-sec", children: [
      /* @__PURE__ */ jsxDEV("h4", { className: "local-dimensions-hubv2-sectitle", children: labels.hubv2_description }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 466,
        columnNumber: 21
      }),
      /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-description", dangerouslySetInnerHTML: { __html: node.description } }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 469,
        columnNumber: 21
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 465,
      columnNumber: 17
    }) : null,
    /* @__PURE__ */ jsxDEV("section", { className: "local-dimensions-hubv2-sec", children: [
      /* @__PURE__ */ jsxDEV("h4", { className: "local-dimensions-hubv2-sectitle", children: [
        labels.central_related_button,
        " (",
        related.length,
        ")"
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 474,
        columnNumber: 17
      }),
      related.length === 0 ? /* @__PURE__ */ jsxDEV("p", { className: "text-muted small mb-0", children: labels.central_related_empty }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 475,
        columnNumber: 41
      }) : null,
      related.length > 0 ? /* @__PURE__ */ jsxDEV("ul", { className: "list-unstyled small mb-0", children: related.map((item) => /* @__PURE__ */ jsxDEV("li", { children: [
        /* @__PURE__ */ jsxDEV(
          "button",
          {
            type: "button",
            className: "btn btn-link btn-sm p-0 text-start",
            onClick: () => actions.reveal(item),
            children: item.shortname
          },
          void 0,
          false,
          {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 480,
            columnNumber: 33
          }
        ),
        item.path ? /* @__PURE__ */ jsxDEV("span", { className: "text-muted", children: [
          " \xB7 ",
          item.path
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 484,
          columnNumber: 46
        }) : null
      ] }, item.id, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 479,
        columnNumber: 29
      })) }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 477,
        columnNumber: 21
      }) : null
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 473,
      columnNumber: 13
    }),
    nodeactions.length > 0 ? /* @__PURE__ */ jsxDEV("section", { className: "local-dimensions-hubv2-sec", children: [
      /* @__PURE__ */ jsxDEV("h4", { className: "local-dimensions-hubv2-sectitle", children: labels.hubv2_actions }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 493,
        columnNumber: 21
      }),
      /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-actions d-flex flex-wrap gap-2", children: [
        /* @__PURE__ */ jsxDEV(
          ActionButtons,
          {
            labels,
            views: nodeactions.filter((view) => PANEL_BUTTONS.includes(view.spec.id)),
            run: actions.run
          },
          void 0,
          false,
          {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 495,
            columnNumber: 25
          }
        ),
        /* @__PURE__ */ jsxDEV(MoreButton, { labels, open: menuopen, menu: actions.menu }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 497,
          columnNumber: 25
        })
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 494,
        columnNumber: 21
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 492,
      columnNumber: 17
    }) : null,
    /* @__PURE__ */ jsxDEV(CompanionSections, { labels, state: sections }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 502,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
    lineNumber: 408,
    columnNumber: 9
  });
}, "Panel");
var Panel_default = Panel;
export {
  CompanionSections,
  Panel_default as default
};
//# sourceMappingURL=Panel.dev.js.map
