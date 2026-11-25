var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The lenses' and the checker's controls and text: the lens switch and the "Check" button of the workspace's
 * bar, the lens's section at the top of the panel (its sentence, the legend with its counts, the list), and the
 * checker's panel mode (its heading and count, the codes present, "Check again", the findings, the codes found
 * nowhere).
 *
 * The switch is a view switch in the Design System's segmented form: buttons with aria-pressed in a labelled
 * group, as the tab's own view switch. Every list names competencies the tree may not have loaded: a name the
 * workspace does not know yet is a grey block until it does (the component asks for the names it shows, a page
 * at a time, through `need`). Every competency is a button that reveals it in both panes.
 *
 * @module     local_dimensions/hubv2/LensPanel
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useEffect, useId, useState } from "react";
import { absentCodes, codeTitle, findingGroups, findingSentence, fixFor } from "./findings";
import { MEASURES } from "./insights";
import {
  BUCKET_CLASSES,
  BUCKET_LABELS,
  LENS_LABELS,
  MEASURE_LABELS,
  pairWords,
  relationsLegend,
  ruleFacts,
  rulesLegend,
  unusedIds,
  usageLegend,
  usageNotice
} from "./lenses";
import { fill, fillCount } from "./strings";
const LIST_PAGE = 50;
const Name = /* @__PURE__ */ __name(({ name }) => name !== null ? /* @__PURE__ */ jsxDEV(Fragment, { children: name }, void 0, false, {
  fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
  lineNumber: 58,
  columnNumber: 77
}) : /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-sk-block local-dimensions-hubv2-sk-name", "aria-hidden": "true" }, void 0, false, {
  fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
  lineNumber: 59,
  columnNumber: 5
}), "Name");
const useNames = /* @__PURE__ */ __name((ids, need) => {
  const key = ids.join(",");
  useEffect(() => {
    if (key !== "") {
      need(key.split(",").map(Number));
    }
  }, [key, need]);
}, "useNames");
const ShowMore = /* @__PURE__ */ __name(({ labels, left, onMore }) => left > 0 ? /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-link btn-sm p-0", onClick: onMore, children: fill(labels.hubv2_showmore, String(Math.min(left, LIST_PAGE))) }, void 0, false, {
  fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
  lineNumber: 85,
  columnNumber: 5
}) : null, "ShowMore");
const LensSwitch = /* @__PURE__ */ __name(({ labels, lens, offered, onChange }) => {
  const id = useId();
  return /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-lensswitch", children: [
    /* @__PURE__ */ jsxDEV("span", { id: `${id}-label`, className: "local-dimensions-hubv2-lenslabel", children: labels.hubv2_lens }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 109,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-seg", role: "group", "aria-labelledby": `${id}-label`, children: offered.map((value) => /* @__PURE__ */ jsxDEV(
      "button",
      {
        type: "button",
        className: "local-dimensions-hubv2-seg-cell",
        "data-lens": value,
        "aria-pressed": value === lens,
        onClick: () => onChange(value),
        children: labels[LENS_LABELS[value]]
      },
      value,
      false,
      {
        fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
        lineNumber: 112,
        columnNumber: 21
      }
    )) }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 110,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
    lineNumber: 108,
    columnNumber: 9
  });
}, "LensSwitch");
const CheckButton = /* @__PURE__ */ __name(({ labels, findings, open, buttonRef, onToggle }) => /* @__PURE__ */ jsxDEV(
  "button",
  {
    ref: buttonRef,
    type: "button",
    className: "btn btn-sm btn-outline-secondary local-dimensions-hubv2-checkbtn",
    "aria-pressed": open,
    onClick: onToggle,
    children: [
      /* @__PURE__ */ jsxDEV("i", { className: "fa fa-list-check me-1", "aria-hidden": "true" }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
        lineNumber: 153,
        columnNumber: 9
      }),
      labels.hubv2_checker,
      findings !== null && findings > 0 ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
        /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-badge", "aria-hidden": "true", children: findings }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
          lineNumber: 156,
          columnNumber: 17
        }),
        /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: [
          ", ",
          fillCount(labels, "hubv2_findings", findings)
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
          lineNumber: 157,
          columnNumber: 17
        })
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
        lineNumber: 155,
        columnNumber: 13
      }) : null
    ]
  },
  void 0,
  true,
  {
    fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
    lineNumber: 146,
    columnNumber: 5
  }
), "CheckButton");
const InsightsStatus = /* @__PURE__ */ __name(({ labels, state, onRetry }) => {
  if (state.status === "failed") {
    return /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-insights-failed", role: "alert", children: [
      /* @__PURE__ */ jsxDEV("p", { className: "mb-2", children: labels.hubv2_insights_failed }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
        lineNumber: 173,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-sm btn-outline-secondary", onClick: onRetry, children: labels.hubv2_insights_retry }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
        lineNumber: 174,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 172,
      columnNumber: 13
    });
  }
  if (state.index === null) {
    return /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-insights-loading", "aria-busy": "true", children: [
      /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-sk", "aria-hidden": "true", children: [
        /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-sk-block local-dimensions-hubv2-sk-line" }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
          lineNumber: 184,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-sk-block local-dimensions-hubv2-sk-line" }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
          lineNumber: 185,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-sk-block local-dimensions-hubv2-sk-line" }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
          lineNumber: 186,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
        lineNumber: 183,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", role: "status", children: labels.hubv2_insights_loading }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
        lineNumber: 188,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 182,
      columnNumber: 13
    });
  }
  return null;
}, "InsightsStatus");
const Legend = /* @__PURE__ */ __name(({ labels, items }) => /* @__PURE__ */ jsxDEV(Fragment, { children: [
  /* @__PURE__ */ jsxDEV("h5", { className: "local-dimensions-hubv2-subtitle", children: labels.hubv2_lens_legend }, void 0, false, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
    lineNumber: 206,
    columnNumber: 9
  }),
  /* @__PURE__ */ jsxDEV("ul", { className: "local-dimensions-hubv2-legend list-unstyled mb-0", children: items.map((item) => /* @__PURE__ */ jsxDEV("li", { children: [
    /* @__PURE__ */ jsxDEV("span", { className: `local-dimensions-hubv2-swatchbox ${item.swatch}`, "aria-hidden": "true" }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 210,
      columnNumber: 21
    }),
    /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-legend-label", children: item.label }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 211,
      columnNumber: 21
    }),
    /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-legend-count", children: item.count }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 212,
      columnNumber: 21
    })
  ] }, item.key, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
    lineNumber: 209,
    columnNumber: 17
  })) }, void 0, false, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
    lineNumber: 207,
    columnNumber: 9
  })
] }, void 0, true, {
  fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
  lineNumber: 205,
  columnNumber: 5
}), "Legend");
const UsageBody = /* @__PURE__ */ __name((props) => {
  const { labels, state, measure, loaded, nameOf, need, reveal, onMeasure } = props;
  const [shown, setShown] = useState(LIST_PAGE);
  const index = state.index;
  const unused = index ? unusedIds(index, measure, loaded) : { ids: [], complete: true };
  const visible = unused.ids.slice(0, shown);
  useNames(visible, need);
  const measureid = useId();
  if (!index) {
    return null;
  }
  const level = index.answer.usagelevel;
  const notice = usageNotice(labels, level);
  if (level === "none") {
    return /* @__PURE__ */ jsxDEV("p", { className: "local-dimensions-hubv2-lens-notice mb-0", children: notice }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 257,
      columnNumber: 16
    });
  }
  const legend = usageLegend(index, measure).map((line) => ({
    key: line.bucket,
    swatch: BUCKET_CLASSES[line.bucket],
    label: labels[BUCKET_LABELS[line.bucket]],
    count: line.count
  }));
  return /* @__PURE__ */ jsxDEV(Fragment, { children: [
    /* @__PURE__ */ jsxDEV("p", { className: "text-muted small mb-2", children: labels.hubv2_lens_usage_desc }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 267,
      columnNumber: 13
    }),
    notice ? /* @__PURE__ */ jsxDEV("p", { className: "local-dimensions-hubv2-lens-notice small mb-2", children: notice }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 268,
      columnNumber: 23
    }) : null,
    /* @__PURE__ */ jsxDEV("span", { id: measureid, className: "visually-hidden", children: labels.hubv2_lens_measure }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 269,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV(
      "div",
      {
        className: "local-dimensions-hubv2-seg local-dimensions-hubv2-seg-wrap mb-2",
        role: "group",
        "aria-labelledby": measureid,
        children: MEASURES.map((value) => /* @__PURE__ */ jsxDEV(
          "button",
          {
            type: "button",
            className: "local-dimensions-hubv2-seg-cell",
            "aria-pressed": value === measure,
            onClick: () => onMeasure(value),
            children: labels[MEASURE_LABELS[value]]
          },
          value,
          false,
          {
            fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
            lineNumber: 273,
            columnNumber: 21
          }
        ))
      },
      void 0,
      false,
      {
        fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
        lineNumber: 270,
        columnNumber: 13
      }
    ),
    /* @__PURE__ */ jsxDEV(Legend, { labels, items: legend }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 279,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("h5", { className: "local-dimensions-hubv2-subtitle", children: fill(labels.hubv2_lens_unusedlist, String(unused.ids.length)) }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 280,
      columnNumber: 13
    }),
    unused.ids.length === 0 ? /* @__PURE__ */ jsxDEV("p", { className: "text-muted small mb-0", children: labels.hubv2_lens_unused_empty }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 281,
      columnNumber: 40
    }) : null,
    !unused.complete ? /* @__PURE__ */ jsxDEV("p", { className: "text-muted small mb-1", children: labels.hubv2_lens_unused_partial }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 282,
      columnNumber: 33
    }) : null,
    /* @__PURE__ */ jsxDEV("ul", { className: "local-dimensions-hubv2-lenslist list-unstyled mb-0", children: visible.map((id) => /* @__PURE__ */ jsxDEV("li", { children: /* @__PURE__ */ jsxDEV(
      "button",
      {
        type: "button",
        className: "btn btn-link btn-sm p-0 text-start",
        onClick: () => reveal(id),
        "aria-busy": nameOf(id) === null ? true : void 0,
        children: /* @__PURE__ */ jsxDEV(Name, { name: nameOf(id) }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
          lineNumber: 288,
          columnNumber: 29
        })
      },
      void 0,
      false,
      {
        fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
        lineNumber: 286,
        columnNumber: 25
      }
    ) }, id, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 285,
      columnNumber: 21
    })) }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 283,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV(ShowMore, { labels, left: unused.ids.length - visible.length, onMore: () => setShown(shown + LIST_PAGE) }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 293,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
    lineNumber: 266,
    columnNumber: 9
  });
}, "UsageBody");
const RulesBody = /* @__PURE__ */ __name((props) => {
  const { labels, state, nameOf, need, reveal, ruleState, runOn } = props;
  const [shown, setShown] = useState(LIST_PAGE);
  const rules = state.index ? state.index.answer.rules : [];
  const visible = rules.slice(0, shown);
  useNames(visible.flatMap((rule) => [rule.competencyid, ...rule.children.filter((c) => c.required).map((c) => c.id)]), need);
  if (!state.index) {
    return null;
  }
  const counts = rulesLegend(state.index);
  const legend = [
    { key: "fire", swatch: "local-dimensions-hubv2-lens-ruled", label: labels.hubv2_lens_rule_with, count: counts.fire },
    {
      key: "cannotfire",
      swatch: "local-dimensions-hubv2-lens-cannotfire",
      label: labels.hubv2_lens_rule_cannotfire,
      count: counts.cannotfire
    },
    { key: "without", swatch: "local-dimensions-hubv2-lens-quiet", label: labels.hubv2_lens_rule_without, count: counts.without }
  ];
  return /* @__PURE__ */ jsxDEV(Fragment, { children: [
    /* @__PURE__ */ jsxDEV("p", { className: "text-muted small mb-2", children: labels.hubv2_lens_rules_desc }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 322,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV(Legend, { labels, items: legend }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 323,
      columnNumber: 13
    }),
    rules.length === 0 ? /* @__PURE__ */ jsxDEV("p", { className: "text-muted small mb-0", children: labels.hubv2_lens_rules_empty }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 324,
      columnNumber: 35
    }) : null,
    visible.map((rule) => {
      const facts = ruleFacts(labels, rule);
      const state2 = ruleState(rule.competencyid);
      return /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-ruleblock", children: [
        /* @__PURE__ */ jsxDEV("h5", { className: "local-dimensions-hubv2-subtitle", children: /* @__PURE__ */ jsxDEV(
          "button",
          {
            type: "button",
            className: "btn btn-link btn-sm p-0 text-start",
            onClick: () => reveal(rule.competencyid),
            children: /* @__PURE__ */ jsxDEV(Name, { name: nameOf(rule.competencyid) }, void 0, false, {
              fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
              lineNumber: 333,
              columnNumber: 33
            })
          },
          void 0,
          false,
          {
            fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
            lineNumber: 331,
            columnNumber: 29
          }
        ) }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
          lineNumber: 330,
          columnNumber: 25
        }),
        !facts.canfire ? /* @__PURE__ */ jsxDEV("p", { className: "local-dimensions-hubv2-status local-dimensions-hubv2-status--warning mb-1", children: [
          /* @__PURE__ */ jsxDEV("i", { className: "fa fa-triangle-exclamation", "aria-hidden": "true" }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
            lineNumber: 338,
            columnNumber: 33
          }),
          labels.hubv2_lens_rule_cannotfire
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
          lineNumber: 337,
          columnNumber: 29
        }) : null,
        /* @__PURE__ */ jsxDEV("dl", { className: "local-dimensions-hubv2-kv mb-1", children: [
          /* @__PURE__ */ jsxDEV("dt", { className: "local-dimensions-hubv2-kv-label", children: labels.hubv2_lens_rule_type }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
            lineNumber: 342,
            columnNumber: 29
          }),
          /* @__PURE__ */ jsxDEV("dd", { className: "local-dimensions-hubv2-kv-value", children: facts.type }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
            lineNumber: 343,
            columnNumber: 29
          }),
          /* @__PURE__ */ jsxDEV("dt", { className: "local-dimensions-hubv2-kv-label", children: labels.hubv2_lens_rule_asks }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
            lineNumber: 344,
            columnNumber: 29
          }),
          /* @__PURE__ */ jsxDEV("dd", { className: "local-dimensions-hubv2-kv-value", children: facts.asks }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
            lineNumber: 345,
            columnNumber: 29
          }),
          facts.available ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
            /* @__PURE__ */ jsxDEV("dt", { className: "local-dimensions-hubv2-kv-label", children: labels.hubv2_lens_rule_available }, void 0, false, {
              fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
              lineNumber: 348,
              columnNumber: 37
            }),
            /* @__PURE__ */ jsxDEV("dd", { className: "local-dimensions-hubv2-kv-value", children: facts.available }, void 0, false, {
              fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
              lineNumber: 349,
              columnNumber: 37
            })
          ] }, void 0, true, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
            lineNumber: 347,
            columnNumber: 33
          }) : null,
          facts.required.length > 0 ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
            /* @__PURE__ */ jsxDEV("dt", { className: "local-dimensions-hubv2-kv-label", children: labels.hubv2_lens_rule_requiredchildren }, void 0, false, {
              fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
              lineNumber: 354,
              columnNumber: 37
            }),
            /* @__PURE__ */ jsxDEV("dd", { className: "local-dimensions-hubv2-kv-value", children: facts.required.map((id, index) => /* @__PURE__ */ jsxDEV("span", { children: [
              index > 0 ? ", " : null,
              /* @__PURE__ */ jsxDEV(Name, { name: nameOf(id) }, void 0, false, {
                fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
                lineNumber: 357,
                columnNumber: 85
              })
            ] }, id, true, {
              fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
              lineNumber: 357,
              columnNumber: 45
            })) }, void 0, false, {
              fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
              lineNumber: 355,
              columnNumber: 37
            })
          ] }, void 0, true, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
            lineNumber: 353,
            columnNumber: 33
          }) : null,
          /* @__PURE__ */ jsxDEV("dt", { className: "local-dimensions-hubv2-kv-label", children: labels["tool_lp:outcome"] }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
            lineNumber: 362,
            columnNumber: 29
          }),
          /* @__PURE__ */ jsxDEV("dd", { className: "local-dimensions-hubv2-kv-value", children: facts.outcome }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
            lineNumber: 363,
            columnNumber: 29
          })
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
          lineNumber: 341,
          columnNumber: 25
        }),
        state2 !== "hidden" ? /* @__PURE__ */ jsxDEV(
          "button",
          {
            type: "button",
            className: "btn btn-sm btn-outline-secondary",
            disabled: state2 === "disabled",
            title: state2 === "disabled" ? labels.central_rule_nochildren : void 0,
            onClick: () => runOn("rule", rule.competencyid),
            children: [
              /* @__PURE__ */ jsxDEV("i", { className: "fa fa-diagram-project me-1", "aria-hidden": "true" }, void 0, false, {
                fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
                lineNumber: 369,
                columnNumber: 33
              }),
              labels.hubv2_cmd_rule
            ]
          },
          void 0,
          true,
          {
            fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
            lineNumber: 366,
            columnNumber: 29
          }
        ) : null
      ] }, rule.competencyid, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
        lineNumber: 329,
        columnNumber: 21
      });
    }),
    /* @__PURE__ */ jsxDEV(ShowMore, { labels, left: rules.length - visible.length, onMore: () => setShown(shown + LIST_PAGE) }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 375,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
    lineNumber: 321,
    columnNumber: 9
  });
}, "RulesBody");
const RelationsBody = /* @__PURE__ */ __name((props) => {
  const { labels, state, nameOf, need, reveal } = props;
  const [shown, setShown] = useState(LIST_PAGE);
  const pairs = state.index ? state.index.answer.pairs : [];
  const visible = pairs.slice(0, shown);
  useNames(visible.flatMap((pair) => [pair.a, pair.b]), need);
  if (!state.index) {
    return null;
  }
  const counts = relationsLegend(state.index);
  const legend = [
    { key: "related", swatch: "local-dimensions-hubv2-lens-related", label: labels.hubv2_lens_related, count: counts.related },
    {
      key: "unrelated",
      swatch: "local-dimensions-hubv2-lens-quiet",
      label: labels.hubv2_lens_unrelated,
      count: counts.unrelated
    }
  ];
  return /* @__PURE__ */ jsxDEV(Fragment, { children: [
    /* @__PURE__ */ jsxDEV("p", { className: "text-muted small mb-2", children: labels.hubv2_lens_relations_desc }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 403,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV(Legend, { labels, items: legend }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 404,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("h5", { className: "local-dimensions-hubv2-subtitle", children: fill(labels.hubv2_lens_pairs, String(pairs.length)) }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 405,
      columnNumber: 13
    }),
    pairs.length === 0 ? /* @__PURE__ */ jsxDEV("p", { className: "text-muted small mb-0", children: labels.hubv2_lens_pairs_empty }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 406,
      columnNumber: 35
    }) : null,
    /* @__PURE__ */ jsxDEV("ul", { className: "local-dimensions-hubv2-lenslist list-unstyled mb-0", children: visible.map((pair) => {
      const a = nameOf(pair.a);
      const b = nameOf(pair.b);
      return /* @__PURE__ */ jsxDEV("li", { "aria-label": a !== null && b !== null ? pairWords(labels, a, b) : void 0, children: [
        /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-link btn-sm p-0 text-start", onClick: () => reveal(pair.a), children: /* @__PURE__ */ jsxDEV(Name, { name: a }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
          lineNumber: 414,
          columnNumber: 33
        }) }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
          lineNumber: 413,
          columnNumber: 29
        }),
        /* @__PURE__ */ jsxDEV("i", { className: "fa fa-arrows-left-right mx-2 local-dimensions-hubv2-pairsep", "aria-hidden": "true" }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
          lineNumber: 416,
          columnNumber: 29
        }),
        /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-link btn-sm p-0 text-start", onClick: () => reveal(pair.b), children: /* @__PURE__ */ jsxDEV(Name, { name: b }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
          lineNumber: 418,
          columnNumber: 33
        }) }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
          lineNumber: 417,
          columnNumber: 29
        })
      ] }, `${pair.a}-${pair.b}`, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
        lineNumber: 412,
        columnNumber: 25
      });
    }) }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 407,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV(ShowMore, { labels, left: pairs.length - visible.length, onMore: () => setShown(shown + LIST_PAGE) }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 424,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
    lineNumber: 402,
    columnNumber: 9
  });
}, "RelationsBody");
const LensSection = /* @__PURE__ */ __name((props) => {
  const { labels, lens, state, onRetry } = props;
  if (lens === "none") {
    return null;
  }
  let body = null;
  if (lens === "usage") {
    body = /* @__PURE__ */ jsxDEV(UsageBody, { ...props }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 442,
      columnNumber: 16
    });
  } else if (lens === "rules") {
    body = /* @__PURE__ */ jsxDEV(RulesBody, { ...props }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 444,
      columnNumber: 16
    });
  } else {
    body = /* @__PURE__ */ jsxDEV(RelationsBody, { ...props }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 446,
      columnNumber: 16
    });
  }
  return /* @__PURE__ */ jsxDEV("section", { className: "local-dimensions-hubv2-sec local-dimensions-hubv2-lenssec", "data-region": "lens", children: [
    /* @__PURE__ */ jsxDEV("p", { className: "local-dimensions-hubv2-kicker", children: labels.hubv2_lens }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 450,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("h4", { className: "local-dimensions-hubv2-sectitle", children: labels[LENS_LABELS[lens]] }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 451,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV(InsightsStatus, { labels, state, onRetry }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 452,
      columnNumber: 13
    }),
    body
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
    lineNumber: 449,
    columnNumber: 9
  });
}, "LensSection");
const Checker = /* @__PURE__ */ __name((props) => {
  const {
    labels,
    state,
    findings,
    current,
    frameworkname,
    canmanage,
    nameOf,
    need,
    actionFor,
    onReveal,
    onFix,
    onAgain,
    onClose,
    headingRef
  } = props;
  const [shown, setShown] = useState(LIST_PAGE);
  const limit = Math.max(shown, current !== null ? current + 1 : 0);
  const visible = findings.slice(0, limit);
  useNames(visible.map((check) => check.competencyid).filter((id) => id > 0), need);
  const index = state.index;
  const absent = index ? absentCodes(index.answer.checks, index.answer.usagelevel === "exact") : [];
  return /* @__PURE__ */ jsxDEV("section", { className: "local-dimensions-hubv2-sec local-dimensions-hubv2-checker", "data-region": "checker", children: [
    /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-checker-head", children: [
      /* @__PURE__ */ jsxDEV("p", { className: "local-dimensions-hubv2-kicker mb-0", children: labels.hubv2_checker_title }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
        lineNumber: 498,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV(
        "button",
        {
          type: "button",
          className: "local-dimensions-hubv2-iconbtn local-dimensions-tip local-dimensions-tip-bottom",
          "aria-label": labels.hubv2_checker_close,
          "data-dim-tip": labels.hubv2_checker_close,
          onClick: onClose,
          children: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-xmark", "aria-hidden": "true" }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
            lineNumber: 501,
            columnNumber: 21
          })
        },
        void 0,
        false,
        {
          fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
          lineNumber: 499,
          columnNumber: 17
        }
      )
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 497,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("h4", { ref: headingRef, tabIndex: -1, className: "h5 mb-1", children: index ? fillCount(labels, "hubv2_findings", index.answer.checks.length) : labels.hubv2_checker_title }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 504,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV(InsightsStatus, { labels, state, onRetry: onAgain }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 507,
      columnNumber: 13
    }),
    index ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
      /* @__PURE__ */ jsxDEV("p", { className: "text-muted small mb-2", children: labels.hubv2_checker_intro }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
        lineNumber: 510,
        columnNumber: 21
      }),
      findings.length > 0 ? /* @__PURE__ */ jsxDEV("ul", { className: "local-dimensions-hubv2-codes list-unstyled mb-2", children: findingGroups(findings).map((group) => /* @__PURE__ */ jsxDEV("li", { className: "local-dimensions-hubv2-status", children: [
        codeTitle(labels, group.code),
        /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-count", children: group.count }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
          lineNumber: 516,
          columnNumber: 37
        })
      ] }, group.code, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
        lineNumber: 514,
        columnNumber: 33
      })) }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
        lineNumber: 512,
        columnNumber: 25
      }) : null,
      /* @__PURE__ */ jsxDEV(
        "button",
        {
          type: "button",
          className: "btn btn-sm btn-outline-secondary mb-2",
          onClick: onAgain,
          disabled: state.status === "loading",
          children: [
            /* @__PURE__ */ jsxDEV("i", { className: "fa fa-rotate me-1", "aria-hidden": "true" }, void 0, false, {
              fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
              lineNumber: 523,
              columnNumber: 25
            }),
            labels.hubv2_checker_again
          ]
        },
        void 0,
        true,
        {
          fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
          lineNumber: 521,
          columnNumber: 21
        }
      ),
      findings.length === 0 ? /* @__PURE__ */ jsxDEV("p", { className: "local-dimensions-hubv2-empty--compact mb-0", children: labels.hubv2_checker_none }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
        lineNumber: 526,
        columnNumber: 25
      }) : null,
      /* @__PURE__ */ jsxDEV("ol", { className: "local-dimensions-hubv2-findings list-unstyled mb-0", children: visible.map((check, position) => {
        const fix = fixFor(check, canmanage);
        const fixstate = fix ? actionFor(fix, check.competencyid) : "hidden";
        const chosen = position === current;
        const name = check.competencyid > 0 ? nameOf(check.competencyid) : frameworkname;
        const sentence = findingSentence(labels, check);
        return /* @__PURE__ */ jsxDEV(
          "li",
          {
            className: chosen ? "local-dimensions-hubv2-finding local-dimensions-hubv2-finding--chosen" : "local-dimensions-hubv2-finding",
            "aria-current": chosen ? "true" : void 0,
            children: [
              /* @__PURE__ */ jsxDEV("p", { className: "local-dimensions-hubv2-finding-title mb-0", children: [
                /* @__PURE__ */ jsxDEV("i", { className: "fa fa-triangle-exclamation me-1", "aria-hidden": "true" }, void 0, false, {
                  fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
                  lineNumber: 541,
                  columnNumber: 41
                }),
                codeTitle(labels, check.code)
              ] }, void 0, true, {
                fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
                lineNumber: 540,
                columnNumber: 37
              }),
              /* @__PURE__ */ jsxDEV("p", { className: "local-dimensions-hubv2-finding-name mb-1", children: /* @__PURE__ */ jsxDEV(Name, { name }, void 0, false, {
                fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
                lineNumber: 544,
                columnNumber: 93
              }) }, void 0, false, {
                fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
                lineNumber: 544,
                columnNumber: 37
              }),
              sentence ? /* @__PURE__ */ jsxDEV("p", { className: "small mb-1", children: sentence }, void 0, false, {
                fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
                lineNumber: 545,
                columnNumber: 49
              }) : null,
              /* @__PURE__ */ jsxDEV("div", { className: "d-flex flex-wrap gap-2", children: [
                fix && fixstate !== "hidden" ? /* @__PURE__ */ jsxDEV(
                  "button",
                  {
                    type: "button",
                    className: "btn btn-sm btn-outline-secondary",
                    disabled: fixstate === "disabled",
                    title: fixstate === "disabled" ? labels.central_rule_nochildren : void 0,
                    onClick: () => onFix(fix, check.competencyid),
                    children: [
                      fix === "rule" ? labels.hubv2_cmd_rule : null,
                      fix === "edit" ? labels.hubv2_edit : null,
                      fix === "links" ? labels.hubv2_cmd_links : null
                    ]
                  },
                  void 0,
                  true,
                  {
                    fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
                    lineNumber: 548,
                    columnNumber: 45
                  }
                ) : null,
                /* @__PURE__ */ jsxDEV(
                  "button",
                  {
                    type: "button",
                    className: "btn btn-sm btn-outline-secondary",
                    "aria-pressed": chosen,
                    "aria-label": name !== null ? fill(labels.hubv2_checker_revealname, name) : void 0,
                    onClick: () => onReveal(position),
                    children: labels.hubv2_checker_reveal
                  },
                  void 0,
                  false,
                  {
                    fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
                    lineNumber: 557,
                    columnNumber: 41
                  }
                )
              ] }, void 0, true, {
                fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
                lineNumber: 546,
                columnNumber: 37
              })
            ]
          },
          `${check.code}-${check.competencyid}`,
          true,
          {
            fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
            lineNumber: 536,
            columnNumber: 33
          }
        );
      }) }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
        lineNumber: 528,
        columnNumber: 21
      }),
      /* @__PURE__ */ jsxDEV(ShowMore, { labels, left: findings.length - visible.length, onMore: () => setShown(limit + LIST_PAGE) }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
        lineNumber: 567,
        columnNumber: 21
      }),
      absent.length > 0 ? /* @__PURE__ */ jsxDEV("p", { className: "text-muted small mt-2 mb-0", children: fill(labels.hubv2_checker_clean, absent.map((code) => codeTitle(labels, code)).join(", ")) }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
        lineNumber: 569,
        columnNumber: 25
      }) : null
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
      lineNumber: 509,
      columnNumber: 17
    }) : null
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/LensPanel.tsx",
    lineNumber: 496,
    columnNumber: 9
  });
}, "Checker");
export {
  CheckButton,
  Checker,
  LIST_PAGE,
  LensSection,
  LensSwitch
};
//# sourceMappingURL=LensPanel.dev.js.map
