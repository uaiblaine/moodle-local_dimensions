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
 * node's own menu under itself (the destructive action lives there, last). With nothing selected it shows
 * the framework's summary (summary.ts), which the palette's command brings into view, and "Add competency".
 * During a move it shows what the move needs.
 *
 * @module     local_dimensions/hubv2/Panel
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { isHexColour } from "./html";
import { fill, fillCount } from "./strings";
const PANEL_BUTTONS = ["edit", "addchild"];
const Pair = /* @__PURE__ */ __name(({ label, children }) => /* @__PURE__ */ jsxDEV(Fragment, { children: [
  /* @__PURE__ */ jsxDEV("dt", { className: "local-dimensions-hubv2-kv-label", children: label }, void 0, false, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
    lineNumber: 82,
    columnNumber: 9
  }),
  /* @__PURE__ */ jsxDEV("dd", { className: "local-dimensions-hubv2-kv-value", children }, void 0, false, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
    lineNumber: 83,
    columnNumber: 9
  })
] }, void 0, true, {
  fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
  lineNumber: 81,
  columnNumber: 5
}), "Pair");
const UsageLists = /* @__PURE__ */ __name(({ labels, usage }) => {
  if (!usage) {
    return /* @__PURE__ */ jsxDEV("p", { className: "text-muted small mb-0", children: labels.hubv2_loading }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 95,
      columnNumber: 16
    });
  }
  const empty = usage.templates.length + usage.courses.length + usage.activities.length === 0;
  if (empty) {
    return /* @__PURE__ */ jsxDEV("p", { className: "text-muted small mb-0", children: labels.hubv2_usage_none }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 99,
      columnNumber: 16
    });
  }
  return /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-usagelists small", children: [
    usage.templates.length > 0 ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
      /* @__PURE__ */ jsxDEV("h4", { className: "h6 mt-2", children: labels.hubv2_usage_templates }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 105,
        columnNumber: 21
      }),
      /* @__PURE__ */ jsxDEV("ul", { className: "list-unstyled mb-0", children: usage.templates.map((t) => /* @__PURE__ */ jsxDEV("li", { children: t.name }, `t${t.id}`, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 106,
        columnNumber: 84
      })) }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 106,
        columnNumber: 21
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 104,
      columnNumber: 17
    }) : null,
    usage.courses.length > 0 ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
      /* @__PURE__ */ jsxDEV("h4", { className: "h6 mt-2", children: labels.hubv2_usage_courses }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 111,
        columnNumber: 21
      }),
      /* @__PURE__ */ jsxDEV("ul", { className: "list-unstyled mb-0", children: usage.courses.map((c) => /* @__PURE__ */ jsxDEV("li", { children: /* @__PURE__ */ jsxDEV("a", { href: c.url, children: c.name }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 113,
        columnNumber: 72
      }) }, `c${c.id}`, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 113,
        columnNumber: 51
      })) }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 112,
        columnNumber: 21
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 110,
      columnNumber: 17
    }) : null,
    usage.activities.length > 0 ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
      /* @__PURE__ */ jsxDEV("h4", { className: "h6 mt-2", children: labels.hubv2_usage_activities }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 119,
        columnNumber: 21
      }),
      /* @__PURE__ */ jsxDEV("ul", { className: "list-unstyled mb-0", children: usage.activities.map((a) => /* @__PURE__ */ jsxDEV("li", { children: [
        a.url ? /* @__PURE__ */ jsxDEV("a", { href: a.url, children: a.name }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 123,
          columnNumber: 42
        }) : a.name,
        /* @__PURE__ */ jsxDEV("span", { className: "text-muted", children: [
          " \xB7 ",
          a.courseshortname
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 124,
          columnNumber: 33
        })
      ] }, `a${a.cmid}`, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 122,
        columnNumber: 29
      })) }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 120,
        columnNumber: 21
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 118,
      columnNumber: 17
    }) : null
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
    lineNumber: 102,
    columnNumber: 9
  });
}, "UsageLists");
const buttonClass = /* @__PURE__ */ __name((view, first) => {
  if (view.spec.danger) {
    return "btn btn-sm btn-outline-danger";
  }
  return first ? "btn btn-sm btn-primary" : "btn btn-sm btn-outline-secondary";
}, "buttonClass");
const ActionButtons = /* @__PURE__ */ __name(({ labels, views, run }) => /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-actions d-flex flex-wrap gap-2", children: views.map((view, index) => /* @__PURE__ */ jsxDEV(
  "button",
  {
    type: "button",
    className: buttonClass(view, index === 0),
    disabled: view.state === "disabled",
    title: view.state === "disabled" && view.spec.id === "rule" ? labels.central_rule_nochildren : void 0,
    onClick: () => run(view.spec.id),
    children: [
      /* @__PURE__ */ jsxDEV("i", { className: `fa ${view.spec.icon} me-1`, "aria-hidden": "true" }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 166,
        columnNumber: 17
      }),
      labels[view.spec.label]
    ]
  },
  view.spec.id,
  true,
  {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
    lineNumber: 158,
    columnNumber: 13
  }
)) }, void 0, false, {
  fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
  lineNumber: 156,
  columnNumber: 5
}), "ActionButtons");
const MoveSection = /* @__PURE__ */ __name(({ labels, moving, views, run }) => /* @__PURE__ */ jsxDEV("section", { className: "local-dimensions-hubv2-sec local-dimensions-hubv2-movesec", children: [
  /* @__PURE__ */ jsxDEV("p", { className: "mb-2", children: fill(labels.hubv2_movepick, moving.shortname) }, void 0, false, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
    lineNumber: 185,
    columnNumber: 9
  }),
  /* @__PURE__ */ jsxDEV(ActionButtons, { labels, views, run }, void 0, false, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
    lineNumber: 186,
    columnNumber: 9
  })
] }, void 0, true, {
  fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
  lineNumber: 184,
  columnNumber: 5
}), "MoveSection");
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
    summary,
    summaryRef,
    actions
  } = props;
  const movesection = moving ? /* @__PURE__ */ jsxDEV(MoveSection, { labels, moving, views: moveactions, run: actions.run }, void 0, false, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
    lineNumber: 199,
    columnNumber: 34
  }) : null;
  if (!node) {
    return /* @__PURE__ */ jsxDEV("aside", { className: "local-dimensions-hubv2-panel", "aria-label": framework.shortname, children: [
      movesection,
      /* @__PURE__ */ jsxDEV("section", { className: "local-dimensions-hubv2-sec", children: [
        /* @__PURE__ */ jsxDEV("p", { className: "local-dimensions-hubv2-kicker", children: [
          labels.central_browseframeworks_framework,
          framework.idnumber ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
            " \xB7 ",
            framework.idnumber
          ] }, void 0, true, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 208,
            columnNumber: 47
          }) : null
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 206,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV("h3", { className: "h5 mb-1", children: framework.shortname }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 210,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV("p", { className: "text-muted mb-0", children: fillCount(labels, "hubv2_competencies", count) }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 211,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 205,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("section", { className: "local-dimensions-hubv2-sec", children: [
        /* @__PURE__ */ jsxDEV("h4", { ref: summaryRef, tabIndex: -1, className: "local-dimensions-hubv2-sectitle", children: labels.hubv2_summary_title }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 214,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV("dl", { className: "local-dimensions-hubv2-kv mb-0", children: summary.map((line) => /* @__PURE__ */ jsxDEV(Pair, { label: line.label, children: line.value }, line.label, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 216,
          columnNumber: 48
        })) }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 215,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV("p", { className: "text-muted small mb-0", children: labels.hubv2_noselection }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 218,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 213,
        columnNumber: 17
      }),
      topactions.length > 0 ? /* @__PURE__ */ jsxDEV("section", { className: "local-dimensions-hubv2-sec", children: /* @__PURE__ */ jsxDEV(ActionButtons, { labels, views: topactions, run: actions.run }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 222,
        columnNumber: 25
      }) }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 221,
        columnNumber: 21
      }) : null
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 203,
      columnNumber: 13
    });
  }
  return /* @__PURE__ */ jsxDEV("aside", { className: "local-dimensions-hubv2-panel", "aria-label": node.shortname, children: [
    movesection,
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
            lineNumber: 236,
            columnNumber: 30
          }),
          node.idnumber
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 236,
          columnNumber: 25
        }) : null
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 233,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("h3", { className: "h5 mb-1", children: node.shortname }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 239,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("p", { className: "text-muted small mb-0", children: [
        /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: [
          labels.hubv2_path,
          ": "
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 241,
          columnNumber: 21
        }),
        path.length > 0 ? path.join(" / ") : labels.hubv2_rootlevel
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 240,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 232,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("section", { className: "local-dimensions-hubv2-sec", children: [
      /* @__PURE__ */ jsxDEV("h4", { className: "local-dimensions-hubv2-sectitle", children: labels.hubv2_usage }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 247,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("ul", { className: "local-dimensions-hubv2-usage list-unstyled mb-2", children: [
        /* @__PURE__ */ jsxDEV("li", { children: [
          /* @__PURE__ */ jsxDEV("b", { children: node.templatecount }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 249,
            columnNumber: 25
          }),
          /* @__PURE__ */ jsxDEV("span", { children: labels.hubv2_usage_templates }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 249,
            columnNumber: 52
          })
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 249,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV("li", { children: [
          /* @__PURE__ */ jsxDEV("b", { children: node.coursecount }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 250,
            columnNumber: 25
          }),
          /* @__PURE__ */ jsxDEV("span", { children: labels.hubv2_usage_courses }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 250,
            columnNumber: 50
          })
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 250,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV("li", { children: [
          /* @__PURE__ */ jsxDEV("b", { children: node.activitycount }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 251,
            columnNumber: 25
          }),
          /* @__PURE__ */ jsxDEV("span", { children: labels.hubv2_usage_activities }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 251,
            columnNumber: 52
          })
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 251,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 248,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("details", { className: "local-dimensions-hubv2-details", children: [
        /* @__PURE__ */ jsxDEV("summary", { children: labels.hubv2_seeusage }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 254,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV(UsageLists, { labels, usage }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 255,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 253,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 246,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("section", { className: "local-dimensions-hubv2-sec", children: [
      /* @__PURE__ */ jsxDEV("h4", { className: "local-dimensions-hubv2-sectitle", children: labels.hubv2_assessment }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 260,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("dl", { className: "local-dimensions-hubv2-kv mb-0", children: [
        /* @__PURE__ */ jsxDEV(Pair, { label: labels.hubv2_scale, children: node.scale }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 262,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV(Pair, { label: labels.hubv2_rule, children: node.ruletype ? node.rulelabel : labels.managecompetencies_norule }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 263,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 261,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 259,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("section", { className: "local-dimensions-hubv2-sec", children: [
      /* @__PURE__ */ jsxDEV("h4", { className: "local-dimensions-hubv2-sectitle", children: labels.hubv2_appearance }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 268,
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
              lineNumber: 273,
              columnNumber: 33
            }
          ),
          node.bgcolor
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 272,
          columnNumber: 29
        }) : labels.hubv2_nocolour }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 270,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV(Pair, { label: labels.hubv2_tag1, children: node.tag1 || "-" }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 279,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV(Pair, { label: labels.hubv2_tag2, children: node.tag2 || "-" }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 280,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV(Pair, { label: labels.hubv2_type, children: node.type || "-" }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 281,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 269,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 267,
      columnNumber: 13
    }),
    node.description ? /* @__PURE__ */ jsxDEV("section", { className: "local-dimensions-hubv2-sec", children: [
      /* @__PURE__ */ jsxDEV("h4", { className: "local-dimensions-hubv2-sectitle", children: labels.hubv2_description }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 287,
        columnNumber: 21
      }),
      /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-description", dangerouslySetInnerHTML: { __html: node.description } }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 290,
        columnNumber: 21
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 286,
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
        lineNumber: 295,
        columnNumber: 17
      }),
      related.length === 0 ? /* @__PURE__ */ jsxDEV("p", { className: "text-muted small mb-0", children: labels.central_related_empty }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 296,
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
            lineNumber: 301,
            columnNumber: 33
          }
        ),
        item.path ? /* @__PURE__ */ jsxDEV("span", { className: "text-muted", children: [
          " \xB7 ",
          item.path
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 305,
          columnNumber: 46
        }) : null
      ] }, item.id, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 300,
        columnNumber: 29
      })) }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 298,
        columnNumber: 21
      }) : null
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 294,
      columnNumber: 13
    }),
    nodeactions.length > 0 ? /* @__PURE__ */ jsxDEV("section", { className: "local-dimensions-hubv2-sec", children: [
      /* @__PURE__ */ jsxDEV("h4", { className: "local-dimensions-hubv2-sectitle", children: labels.hubv2_actions }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 314,
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
            lineNumber: 316,
            columnNumber: 25
          }
        ),
        /* @__PURE__ */ jsxDEV(
          "button",
          {
            type: "button",
            className: "btn btn-sm btn-outline-secondary",
            "aria-haspopup": "menu",
            "aria-expanded": menuopen,
            onClick: (event) => actions.menu(event.currentTarget),
            children: [
              labels.hubv2_moreactions,
              /* @__PURE__ */ jsxDEV("i", { className: "fa fa-chevron-down ms-1", "aria-hidden": "true" }, void 0, false, {
                fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
                lineNumber: 325,
                columnNumber: 55
              })
            ]
          },
          void 0,
          true,
          {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 318,
            columnNumber: 25
          }
        )
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 315,
        columnNumber: 21
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 313,
      columnNumber: 17
    }) : null
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
    lineNumber: 230,
    columnNumber: 9
  });
}, "Panel");
var Panel_default = Panel;
export {
  Panel_default as default
};
//# sourceMappingURL=Panel.dev.js.map
