var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The panel beside the canvas: the selected competency's details and actions, or the framework's
 * when nothing is selected, and the controls of the move mode while it lasts.
 *
 * @module     local_dimensions/hubv2/Panel
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { isHexColour } from "./html";
import { fill, fillCount } from "./strings";
const Pair = /* @__PURE__ */ __name(({ label, children }) => /* @__PURE__ */ jsxDEV(Fragment, { children: [
  /* @__PURE__ */ jsxDEV("dt", { className: "local-dimensions-hubv2-kv-label", children: label }, void 0, false, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
    lineNumber: 70,
    columnNumber: 9
  }),
  /* @__PURE__ */ jsxDEV("dd", { className: "local-dimensions-hubv2-kv-value", children }, void 0, false, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
    lineNumber: 71,
    columnNumber: 9
  })
] }, void 0, true, {
  fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
  lineNumber: 69,
  columnNumber: 5
}), "Pair");
const UsageLists = /* @__PURE__ */ __name(({ labels, usage }) => {
  if (!usage) {
    return /* @__PURE__ */ jsxDEV("p", { className: "text-muted small mb-0", children: labels.hubv2_loading }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 83,
      columnNumber: 16
    });
  }
  const empty = usage.templates.length + usage.courses.length + usage.activities.length === 0;
  if (empty) {
    return /* @__PURE__ */ jsxDEV("p", { className: "text-muted small mb-0", children: labels.hubv2_usage_none }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 87,
      columnNumber: 16
    });
  }
  return /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-usagelists small", children: [
    usage.templates.length > 0 ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
      /* @__PURE__ */ jsxDEV("h4", { className: "h6 mt-2", children: labels.hubv2_usage_templates }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 93,
        columnNumber: 21
      }),
      /* @__PURE__ */ jsxDEV("ul", { className: "list-unstyled mb-0", children: usage.templates.map((t) => /* @__PURE__ */ jsxDEV("li", { children: t.name }, `t${t.id}`, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 94,
        columnNumber: 84
      })) }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 94,
        columnNumber: 21
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 92,
      columnNumber: 17
    }) : null,
    usage.courses.length > 0 ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
      /* @__PURE__ */ jsxDEV("h4", { className: "h6 mt-2", children: labels.hubv2_usage_courses }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 99,
        columnNumber: 21
      }),
      /* @__PURE__ */ jsxDEV("ul", { className: "list-unstyled mb-0", children: usage.courses.map((c) => /* @__PURE__ */ jsxDEV("li", { children: /* @__PURE__ */ jsxDEV("a", { href: c.url, children: c.name }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 101,
        columnNumber: 72
      }) }, `c${c.id}`, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 101,
        columnNumber: 51
      })) }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 100,
        columnNumber: 21
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 98,
      columnNumber: 17
    }) : null,
    usage.activities.length > 0 ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
      /* @__PURE__ */ jsxDEV("h4", { className: "h6 mt-2", children: labels.hubv2_usage_activities }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 107,
        columnNumber: 21
      }),
      /* @__PURE__ */ jsxDEV("ul", { className: "list-unstyled mb-0", children: usage.activities.map((a) => /* @__PURE__ */ jsxDEV("li", { children: [
        a.url ? /* @__PURE__ */ jsxDEV("a", { href: a.url, children: a.name }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 111,
          columnNumber: 42
        }) : a.name,
        /* @__PURE__ */ jsxDEV("span", { className: "text-muted", children: [
          " \xB7 ",
          a.courseshortname
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 112,
          columnNumber: 33
        })
      ] }, `a${a.cmid}`, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 110,
        columnNumber: 29
      })) }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 108,
        columnNumber: 21
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 106,
      columnNumber: 17
    }) : null
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
    lineNumber: 90,
    columnNumber: 9
  });
}, "UsageLists");
const MoveSection = /* @__PURE__ */ __name(({ labels, moving, actions }) => /* @__PURE__ */ jsxDEV("section", { className: "local-dimensions-hubv2-sec local-dimensions-hubv2-movesec", children: [
  /* @__PURE__ */ jsxDEV("p", { className: "mb-2", children: fill(labels.hubv2_movepick, moving.shortname) }, void 0, false, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
    lineNumber: 130,
    columnNumber: 9
  }),
  /* @__PURE__ */ jsxDEV("div", { className: "d-flex flex-wrap gap-2", children: [
    moving.parentid !== 0 ? /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-sm btn-primary", onClick: actions.moveRoot, children: labels.hubv2_moveroot }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 133,
      columnNumber: 17
    }) : null,
    /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-sm btn-secondary", onClick: actions.cancelMove, children: labels.hubv2_movecancel }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 135,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
    lineNumber: 131,
    columnNumber: 9
  })
] }, void 0, true, {
  fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
  lineNumber: 129,
  columnNumber: 5
}), "MoveSection");
const Panel = /* @__PURE__ */ __name((props) => {
  const { labels, framework, count, node, path, usage, related, onCanvas, moving, canUp, canDown, actions } = props;
  if (!node) {
    return /* @__PURE__ */ jsxDEV("aside", { className: "local-dimensions-hubv2-panel", "aria-label": framework.shortname, children: [
      moving ? /* @__PURE__ */ jsxDEV(MoveSection, { labels, moving, actions }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 154,
        columnNumber: 27
      }) : null,
      /* @__PURE__ */ jsxDEV("section", { className: "local-dimensions-hubv2-sec", children: [
        /* @__PURE__ */ jsxDEV("p", { className: "local-dimensions-hubv2-kicker", children: [
          labels.central_browseframeworks_framework,
          framework.idnumber ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
            " \xB7 ",
            framework.idnumber
          ] }, void 0, true, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 158,
            columnNumber: 47
          }) : null
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 156,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV("h3", { className: "h5 mb-1", children: framework.shortname }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 160,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV("p", { className: "text-muted mb-0", children: fillCount(labels, "hubv2_competencies", count) }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 161,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 155,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("section", { className: "local-dimensions-hubv2-sec", children: /* @__PURE__ */ jsxDEV("p", { className: "text-muted mb-0", children: labels.hubv2_noselection }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 164,
        columnNumber: 21
      }) }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 163,
        columnNumber: 17
      }),
      framework.canmanage && !moving ? /* @__PURE__ */ jsxDEV("section", { className: "local-dimensions-hubv2-sec", children: /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-sm btn-primary", onClick: () => actions.addChild(null), children: [
        /* @__PURE__ */ jsxDEV("i", { className: "fa fa-plus me-1", "aria-hidden": "true" }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 169,
          columnNumber: 29
        }),
        labels.managecompetencies_addcompetency
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 168,
        columnNumber: 25
      }) }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 167,
        columnNumber: 21
      }) : null
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 153,
      columnNumber: 13
    });
  }
  const offcanvas = related.filter((item) => !onCanvas(item.id));
  return /* @__PURE__ */ jsxDEV("aside", { className: "local-dimensions-hubv2-panel", "aria-label": node.shortname, children: [
    moving ? /* @__PURE__ */ jsxDEV(MoveSection, { labels, moving, actions }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 181,
      columnNumber: 23
    }) : null,
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
            lineNumber: 186,
            columnNumber: 30
          }),
          node.idnumber
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 186,
          columnNumber: 25
        }) : null
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 183,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("h3", { className: "h5 mb-1", children: node.shortname }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 189,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("p", { className: "text-muted small mb-0", children: [
        /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: [
          labels.hubv2_path,
          ": "
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 191,
          columnNumber: 21
        }),
        path.length > 0 ? path.join(" / ") : labels.hubv2_rootlevel
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 190,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 182,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("section", { className: "local-dimensions-hubv2-sec", children: [
      /* @__PURE__ */ jsxDEV("h4", { className: "local-dimensions-hubv2-sectitle", children: labels.hubv2_usage }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 197,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("ul", { className: "local-dimensions-hubv2-usage list-unstyled mb-2", children: [
        /* @__PURE__ */ jsxDEV("li", { children: [
          /* @__PURE__ */ jsxDEV("b", { children: node.templatecount }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 199,
            columnNumber: 25
          }),
          /* @__PURE__ */ jsxDEV("span", { children: labels.hubv2_usage_templates }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 199,
            columnNumber: 52
          })
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 199,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV("li", { children: [
          /* @__PURE__ */ jsxDEV("b", { children: node.coursecount }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 200,
            columnNumber: 25
          }),
          /* @__PURE__ */ jsxDEV("span", { children: labels.hubv2_usage_courses }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 200,
            columnNumber: 50
          })
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 200,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV("li", { children: [
          /* @__PURE__ */ jsxDEV("b", { children: node.activitycount }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 201,
            columnNumber: 25
          }),
          /* @__PURE__ */ jsxDEV("span", { children: labels.hubv2_usage_activities }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 201,
            columnNumber: 52
          })
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 201,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 198,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("details", { className: "local-dimensions-hubv2-details", children: [
        /* @__PURE__ */ jsxDEV("summary", { children: labels.hubv2_seeusage }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 204,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV(UsageLists, { labels, usage }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 205,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 203,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 196,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("section", { className: "local-dimensions-hubv2-sec", children: [
      /* @__PURE__ */ jsxDEV("h4", { className: "local-dimensions-hubv2-sectitle", children: labels.hubv2_assessment }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 210,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("dl", { className: "local-dimensions-hubv2-kv mb-0", children: [
        /* @__PURE__ */ jsxDEV(Pair, { label: labels.hubv2_scale, children: node.scale }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 212,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV(Pair, { label: labels.hubv2_rule, children: node.ruletype ? node.rulelabel : labels.managecompetencies_norule }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 213,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 211,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 209,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("section", { className: "local-dimensions-hubv2-sec", children: [
      /* @__PURE__ */ jsxDEV("h4", { className: "local-dimensions-hubv2-sectitle", children: labels.hubv2_appearance }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 218,
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
              lineNumber: 223,
              columnNumber: 33
            }
          ),
          node.bgcolor
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 222,
          columnNumber: 29
        }) : labels.hubv2_nocolour }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 220,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV(Pair, { label: labels.hubv2_tag1, children: node.tag1 || "-" }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 229,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV(Pair, { label: labels.hubv2_tag2, children: node.tag2 || "-" }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 230,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV(Pair, { label: labels.hubv2_type, children: node.type || "-" }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 231,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 219,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 217,
      columnNumber: 13
    }),
    node.description ? /* @__PURE__ */ jsxDEV("section", { className: "local-dimensions-hubv2-sec", children: [
      /* @__PURE__ */ jsxDEV("h4", { className: "local-dimensions-hubv2-sectitle", children: labels.hubv2_description }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 237,
        columnNumber: 21
      }),
      /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-description", dangerouslySetInnerHTML: { __html: node.description } }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 240,
        columnNumber: 21
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 236,
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
        lineNumber: 245,
        columnNumber: 17
      }),
      related.length === 0 ? /* @__PURE__ */ jsxDEV("p", { className: "text-muted small mb-0", children: labels.central_related_empty }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 246,
        columnNumber: 41
      }) : null,
      offcanvas.length > 0 ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
        /* @__PURE__ */ jsxDEV("p", { className: "small text-muted mb-1", children: labels.hubv2_related_offcanvas }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 249,
          columnNumber: 25
        }),
        /* @__PURE__ */ jsxDEV("ul", { className: "list-unstyled small mb-0", children: offcanvas.map((item) => /* @__PURE__ */ jsxDEV("li", { children: [
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
              lineNumber: 253,
              columnNumber: 37
            }
          ),
          item.path ? /* @__PURE__ */ jsxDEV("span", { className: "text-muted", children: [
            " \xB7 ",
            item.path
          ] }, void 0, true, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 257,
            columnNumber: 50
          }) : null
        ] }, item.id, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 252,
          columnNumber: 33
        })) }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 250,
          columnNumber: 25
        })
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 248,
        columnNumber: 21
      }) : null
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 244,
      columnNumber: 13
    }),
    node.canmanage && !moving ? /* @__PURE__ */ jsxDEV("section", { className: "local-dimensions-hubv2-sec", children: [
      /* @__PURE__ */ jsxDEV("h4", { className: "local-dimensions-hubv2-sectitle", children: labels.hubv2_actions }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 267,
        columnNumber: 21
      }),
      /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-actions d-flex flex-wrap gap-2", children: [
        /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-sm btn-primary", onClick: () => actions.edit(node), children: [
          /* @__PURE__ */ jsxDEV("i", { className: "fa fa-pen me-1", "aria-hidden": "true" }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 270,
            columnNumber: 29
          }),
          labels.hubv2_edit
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 269,
          columnNumber: 25
        }),
        /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-sm btn-outline-secondary", onClick: () => actions.addChild(node), children: [
          /* @__PURE__ */ jsxDEV("i", { className: "fa fa-plus me-1", "aria-hidden": "true" }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 273,
            columnNumber: 29
          }),
          labels.managecompetencies_addchild
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 272,
          columnNumber: 25
        }),
        /* @__PURE__ */ jsxDEV(
          "button",
          {
            type: "button",
            className: "btn btn-sm btn-outline-secondary",
            disabled: !node.haschildren,
            title: node.haschildren ? void 0 : labels.central_rule_nochildren,
            onClick: () => actions.rule(node),
            children: [
              /* @__PURE__ */ jsxDEV("i", { className: "fa fa-diagram-project me-1", "aria-hidden": "true" }, void 0, false, {
                fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
                lineNumber: 282,
                columnNumber: 29
              }),
              labels.hubv2_rule
            ]
          },
          void 0,
          true,
          {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 275,
            columnNumber: 25
          }
        ),
        /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-sm btn-outline-secondary", onClick: () => actions.links(node), children: [
          /* @__PURE__ */ jsxDEV("i", { className: "fa fa-link me-1", "aria-hidden": "true" }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 285,
            columnNumber: 29
          }),
          labels.central_links_button
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 284,
          columnNumber: 25
        }),
        /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-sm btn-outline-secondary", onClick: () => actions.relate(node), children: [
          /* @__PURE__ */ jsxDEV("i", { className: "fa fa-share-nodes me-1", "aria-hidden": "true" }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 288,
            columnNumber: 29
          }),
          labels.hubv2_relate
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 287,
          columnNumber: 25
        }),
        /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-sm btn-outline-secondary", onClick: () => actions.move(node), children: [
          /* @__PURE__ */ jsxDEV("i", { className: "fa fa-arrows-up-down-left-right me-1", "aria-hidden": "true" }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 291,
            columnNumber: 29
          }),
          labels.hubv2_move
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 290,
          columnNumber: 25
        }),
        /* @__PURE__ */ jsxDEV(
          "button",
          {
            type: "button",
            className: "btn btn-sm btn-outline-secondary",
            disabled: !canUp,
            onClick: () => actions.moveOne(node, true),
            children: [
              /* @__PURE__ */ jsxDEV("i", { className: "fa fa-arrow-up me-1", "aria-hidden": "true" }, void 0, false, {
                fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
                lineNumber: 295,
                columnNumber: 29
              }),
              labels.hubv2_moveup
            ]
          },
          void 0,
          true,
          {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 293,
            columnNumber: 25
          }
        ),
        /* @__PURE__ */ jsxDEV(
          "button",
          {
            type: "button",
            className: "btn btn-sm btn-outline-secondary",
            disabled: !canDown,
            onClick: () => actions.moveOne(node, false),
            children: [
              /* @__PURE__ */ jsxDEV("i", { className: "fa fa-arrow-down me-1", "aria-hidden": "true" }, void 0, false, {
                fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
                lineNumber: 299,
                columnNumber: 29
              }),
              labels.hubv2_movedown
            ]
          },
          void 0,
          true,
          {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 297,
            columnNumber: 25
          }
        ),
        /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-sm btn-outline-danger", onClick: () => actions.remove(node), children: [
          /* @__PURE__ */ jsxDEV("i", { className: "fa fa-trash me-1", "aria-hidden": "true" }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
            lineNumber: 302,
            columnNumber: 29
          }),
          labels.hubv2_delete
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
          lineNumber: 301,
          columnNumber: 25
        })
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
        lineNumber: 268,
        columnNumber: 21
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
      lineNumber: 266,
      columnNumber: 17
    }) : null
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Panel.tsx",
    lineNumber: 180,
    columnNumber: 9
  });
}, "Panel");
var Panel_default = Panel;
export {
  Panel_default as default
};
//# sourceMappingURL=Panel.dev.js.map
