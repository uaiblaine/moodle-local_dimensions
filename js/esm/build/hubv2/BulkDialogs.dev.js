var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The bulk dock's two dialogues of its own: "Set appearance…" and "Delete…" ("Move under…" is MoveDialog.tsx with
 * several subjects).
 *
 * Each is the workspace's Dialog (labelled by its title, modal, Tab kept inside, Escape and the veil close it), its
 * title naming the action and the count and taking the focus, its body naming the first five competencies and "and N
 * more", its foot Cancel and the commit, which is the confirmation, names the count and acts once; the focus goes
 * back to the dock's button on close (the Design System's data report, "Bulk dock", D135).
 *
 * Set appearance: each field is left as it is until a value or "Clear" is chosen, and only those are sent
 * (bulk.appearancePayload). A colour is typed as a hex value beside a swatch, refused in place when it is not one
 * (contrast.normalise, the server's rule); the competency form's colour swatch and contrast panel are AMD modules
 * bound to that form's own fields, so they are not reused here. A chip previews both colours, and with both set the
 * dialogue says their contrast ratio, warning under 4.5:1.
 *
 * Delete: the workspace asks core's refusal reasons first (local_dimensions_competency_delete_check, one per topmost
 * competency); the dialogue then says how many core will delete, what goes with them, which it will refuse and why.
 * Its commit deletes only those the check allowed.
 *
 * @module     local_dimensions/hubv2/BulkDialogs
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useId, useRef, useState } from "react";
import { APPEARANCE_FIELDS, BULK_MAX, COLOUR_FIELDS, appearancePayload, preview } from "./bulk";
import { MIN_CONTRAST, contrastRatio, normalise, ratioText } from "./contrast";
import Dialog from "./Dialog";
import { fill, fillCount } from "./strings";
const FIELD_LABELS = {
  bgcolor: "custombgcolor",
  textcolor: "customtextcolor",
  tag1: "tag1",
  tag2: "tag2",
  type: "type"
};
const KEEP_ALL = Object.fromEntries(APPEARANCE_FIELDS.map((name) => [name, { mode: "keep" }]));
const Names = /* @__PURE__ */ __name((props) => {
  const { labels, names } = props;
  const { shown, more } = preview(names);
  return /* @__PURE__ */ jsxDEV("ul", { className: "local-dimensions-hubv2-bulknames", children: [
    shown.map((name, index) => /* @__PURE__ */ jsxDEV("li", { children: name }, `${index}-${name}`, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
      lineNumber: 73,
      columnNumber: 41
    })),
    more > 0 ? /* @__PURE__ */ jsxDEV("li", { children: fill(labels.hubv2_bulk_more, String(more)) }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
      lineNumber: 74,
      columnNumber: 25
    }) : null
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
    lineNumber: 72,
    columnNumber: 9
  });
}, "Names");
const Frame = /* @__PURE__ */ __name((props) => {
  const { title, onClose, onLostFocus, body, foot, labels } = props;
  const heading = useRef(null);
  const id = useId();
  return /* @__PURE__ */ jsxDEV(
    Dialog,
    {
      labelledby: `${id}-title`,
      className: "local-dimensions-hubv2-dialog local-dimensions-hubv2-bulkdialog",
      initialFocus: heading,
      returnTo: "opener",
      onClose,
      onLostFocus,
      children: [
        /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-dialog-head", children: [
          /* @__PURE__ */ jsxDEV("h2", { id: `${id}-title`, ref: heading, tabIndex: -1, className: "local-dimensions-hubv2-dialog-title", children: title }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
            lineNumber: 103,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV(
            "button",
            {
              type: "button",
              className: "local-dimensions-hubv2-iconbtn",
              "aria-label": labels["core:closebuttontitle"],
              onClick: onClose,
              children: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-xmark", "aria-hidden": "true" }, void 0, false, {
                fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
                lineNumber: 106,
                columnNumber: 21
              })
            },
            void 0,
            false,
            {
              fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
              lineNumber: 104,
              columnNumber: 17
            }
          )
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
          lineNumber: 102,
          columnNumber: 13
        }),
        /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-dialog-body", children: body }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
          lineNumber: 109,
          columnNumber: 13
        }),
        /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-dialog-foot", children: foot }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
          lineNumber: 110,
          columnNumber: 13
        })
      ]
    },
    void 0,
    true,
    {
      fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
      lineNumber: 100,
      columnNumber: 9
    }
  );
}, "Frame");
const ColourField = /* @__PURE__ */ __name((props) => {
  const { labels, name, choice, onChange } = props;
  const id = useId();
  const typed = choice.mode === "set" ? choice.value : "";
  const colour = normalise(typed);
  const invalid = choice.mode === "set" && typed.trim() !== "" && colour === "";
  return /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-bulkfield", children: [
    /* @__PURE__ */ jsxDEV("label", { htmlFor: `${id}-mode`, className: "local-dimensions-hubv2-bulkfield-label", children: labels[FIELD_LABELS[name]] }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
      lineNumber: 137,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV(
      "select",
      {
        id: `${id}-mode`,
        className: "form-select form-select-sm",
        value: choice.mode,
        onChange: (event) => {
          const mode = event.target.value;
          onChange(mode === "set" ? { mode: "set", value: typed } : { mode: mode === "clear" ? "clear" : "keep" });
        },
        children: [
          /* @__PURE__ */ jsxDEV("option", { value: "keep", children: labels.hubv2_bulk_keep }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
            lineNumber: 143,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("option", { value: "set", children: labels.hubv2_bulk_setcolour }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
            lineNumber: 144,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("option", { value: "clear", children: labels.hubv2_bulk_clearvalue }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
            lineNumber: 145,
            columnNumber: 17
          })
        ]
      },
      void 0,
      true,
      {
        fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
        lineNumber: 138,
        columnNumber: 13
      }
    ),
    choice.mode === "set" ? /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-bulkcolour", children: [
      /* @__PURE__ */ jsxDEV("label", { htmlFor: `${id}-value`, className: "visually-hidden", children: labels[FIELD_LABELS[name]] }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
        lineNumber: 149,
        columnNumber: 21
      }),
      /* @__PURE__ */ jsxDEV(
        "input",
        {
          id: `${id}-value`,
          type: "text",
          className: "form-control form-control-sm",
          value: typed,
          placeholder: "#0f6cbf",
          autoComplete: "off",
          spellCheck: false,
          maxLength: 7,
          "aria-invalid": invalid ? true : void 0,
          "aria-describedby": `${id}-help`,
          onChange: (event) => onChange({ mode: "set", value: event.target.value })
        },
        void 0,
        false,
        {
          fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
          lineNumber: 150,
          columnNumber: 21
        }
      ),
      /* @__PURE__ */ jsxDEV(
        "span",
        {
          className: "local-dimensions-hubv2-swatch",
          "aria-hidden": "true",
          style: colour ? { backgroundColor: colour } : void 0
        },
        void 0,
        false,
        {
          fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
          lineNumber: 155,
          columnNumber: 21
        }
      ),
      /* @__PURE__ */ jsxDEV("p", { id: `${id}-help`, className: invalid ? "local-dimensions-hubv2-bulkfield-help local-dimensions-hubv2-bulkfield-error" : "local-dimensions-hubv2-bulkfield-help", children: invalid ? labels.hubv2_bulk_colour_invalid : labels.hubv2_bulk_colour_help }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
        lineNumber: 157,
        columnNumber: 21
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
      lineNumber: 148,
      columnNumber: 17
    }) : null
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
    lineNumber: 136,
    columnNumber: 9
  });
}, "ColourField");
const OptionField = /* @__PURE__ */ __name((props) => {
  const { labels, name, options, choice, onChange } = props;
  const id = useId();
  const value = choice.mode === "set" ? `o${choice.value}` : choice.mode;
  return /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-bulkfield", children: [
    /* @__PURE__ */ jsxDEV("label", { htmlFor: id, className: "local-dimensions-hubv2-bulkfield-label", children: labels[FIELD_LABELS[name]] }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
      lineNumber: 181,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV(
      "select",
      {
        id,
        className: "form-select form-select-sm",
        value,
        onChange: (event) => {
          const picked = event.target.value;
          if (picked.startsWith("o")) {
            onChange({ mode: "set", value: picked.slice(1) });
          } else {
            onChange({ mode: picked === "clear" ? "clear" : "keep" });
          }
        },
        children: [
          /* @__PURE__ */ jsxDEV("option", { value: "keep", children: labels.hubv2_bulk_keep }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
            lineNumber: 191,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("option", { value: "clear", children: labels.hubv2_bulk_clearvalue }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
            lineNumber: 192,
            columnNumber: 17
          }),
          options.map((option, index) => /* @__PURE__ */ jsxDEV("option", { value: `o${index + 1}`, children: option }, `${index}-${option}`, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
            lineNumber: 193,
            columnNumber: 49
          }))
        ]
      },
      void 0,
      true,
      {
        fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
        lineNumber: 182,
        columnNumber: 13
      }
    )
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
    lineNumber: 180,
    columnNumber: 9
  });
}, "OptionField");
const Preview = /* @__PURE__ */ __name((props) => {
  const { labels, background, text } = props;
  const ratio = background && text ? contrastRatio(background, text) : null;
  const style = { backgroundColor: background || void 0, color: text || void 0 };
  return /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-bulkpreview", children: [
    /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-bulkchip", style, children: labels.hubv2_bulk_preview }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
      lineNumber: 211,
      columnNumber: 13
    }),
    ratio !== null ? /* @__PURE__ */ jsxDEV("p", { className: ratio < MIN_CONTRAST ? "local-dimensions-hubv2-bulkcontrast local-dimensions-hubv2-bulkcontrast--low" : "local-dimensions-hubv2-bulkcontrast", children: [
      fill(labels.hubv2_bulk_contrast, ratioText(ratio)),
      ratio < MIN_CONTRAST ? ` ${labels.hubv2_bulk_contrast_low}` : ""
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
      lineNumber: 213,
      columnNumber: 17
    }) : null
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
    lineNumber: 210,
    columnNumber: 9
  });
}, "Preview");
const AppearanceDialog = /* @__PURE__ */ __name((props) => {
  const { labels, names, options, onCommit, onClose, onLostFocus } = props;
  const [choices, setChoices] = useState(KEEP_ALL);
  const [busy, setBusy] = useState(false);
  const [refusal, setRefusal] = useState("");
  const payload = appearancePayload(choices);
  const toomany = names.length > BULK_MAX;
  const ready = payload !== null && payload.length > 0 && !toomany && !busy;
  const choose = /* @__PURE__ */ __name((name, choice) => setChoices((current) => ({ ...current, [name]: choice })), "choose");
  const commit = /* @__PURE__ */ __name(() => {
    if (!ready || payload === null) {
      return;
    }
    setBusy(true);
    setRefusal("");
    onCommit(payload).then((answer) => {
      if (answer !== null) {
        setRefusal(answer);
        setBusy(false);
      }
      return null;
    }).catch(() => setBusy(false));
  }, "commit");
  const colourOf = /* @__PURE__ */ __name((name) => {
    const choice = choices[name];
    return choice.mode === "set" ? normalise(choice.value) : "";
  }, "colourOf");
  const body = /* @__PURE__ */ jsxDEV(Fragment, { children: [
    /* @__PURE__ */ jsxDEV(Names, { labels, names }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
      lineNumber: 277,
      columnNumber: 13
    }),
    toomany ? /* @__PURE__ */ jsxDEV("div", { className: "alert alert-warning mb-0", children: fill(labels.hubv2_bulk_toomany, String(BULK_MAX)) }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
      lineNumber: 278,
      columnNumber: 24
    }) : null,
    /* @__PURE__ */ jsxDEV("p", { className: "local-dimensions-hubv2-dialog-label", children: labels.hubv2_bulk_appearance_intro }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
      lineNumber: 279,
      columnNumber: 13
    }),
    APPEARANCE_FIELDS.map((name) => COLOUR_FIELDS.includes(name) ? /* @__PURE__ */ jsxDEV(
      ColourField,
      {
        labels,
        name,
        choice: choices[name],
        onChange: (choice) => choose(name, choice)
      },
      name,
      false,
      {
        fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
        lineNumber: 281,
        columnNumber: 19
      }
    ) : /* @__PURE__ */ jsxDEV(
      OptionField,
      {
        labels,
        name,
        options: options[name],
        choice: choices[name],
        onChange: (choice) => choose(name, choice)
      },
      name,
      false,
      {
        fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
        lineNumber: 283,
        columnNumber: 19
      }
    )),
    /* @__PURE__ */ jsxDEV(Preview, { labels, background: colourOf("bgcolor"), text: colourOf("textcolor") }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
      lineNumber: 285,
      columnNumber: 13
    }),
    refusal ? /* @__PURE__ */ jsxDEV("div", { className: "alert alert-danger mb-0", role: "alert", children: refusal }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
      lineNumber: 286,
      columnNumber: 24
    }) : null
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
    lineNumber: 276,
    columnNumber: 9
  });
  const foot = /* @__PURE__ */ jsxDEV(Fragment, { children: [
    /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-sm btn-secondary", onClick: onClose, children: labels["core:cancel"] }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
      lineNumber: 291,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-sm btn-primary", disabled: !ready, onClick: commit, children: fillCount(labels, "hubv2_bulk_appearance_commit", names.length) }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
      lineNumber: 292,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
    lineNumber: 290,
    columnNumber: 9
  });
  return /* @__PURE__ */ jsxDEV(
    Frame,
    {
      labels,
      title: fillCount(labels, "hubv2_bulk_appearance_title", names.length),
      onClose,
      onLostFocus,
      body,
      foot
    },
    void 0,
    false,
    {
      fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
      lineNumber: 297,
      columnNumber: 12
    }
  );
}, "AppearanceDialog");
const DeleteDialog = /* @__PURE__ */ __name((props) => {
  const { labels, names, summary, onCommit, onClose, onLostFocus } = props;
  const [busy, setBusy] = useState(false);
  const allowed = summary ? summary.allowed.length : 0;
  let verdict = /* @__PURE__ */ jsxDEV("p", { className: "mb-0", role: "status", children: labels.hubv2_bulk_delete_checking }, void 0, false, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
    lineNumber: 318,
    columnNumber: 30
  });
  if (summary) {
    verdict = /* @__PURE__ */ jsxDEV(Fragment, { children: [
      allowed > 0 ? /* @__PURE__ */ jsxDEV("div", { className: "alert alert-danger mb-0", children: [
        /* @__PURE__ */ jsxDEV("p", { className: "mb-0", children: fillCount(labels, "hubv2_bulk_delete_can", allowed) }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
          lineNumber: 324,
          columnNumber: 25
        }),
        summary.descendants > 0 ? /* @__PURE__ */ jsxDEV("p", { className: "mb-0", children: fillCount(labels, "hubv2_bulk_delete_with", summary.descendants) }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
          lineNumber: 326,
          columnNumber: 31
        }) : null,
        summary.parentrule ? /* @__PURE__ */ jsxDEV("p", { className: "mb-0", children: labels.hubv2_delete_parentrule }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
          lineNumber: 328,
          columnNumber: 47
        }) : null
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
        lineNumber: 323,
        columnNumber: 21
      }) : /* @__PURE__ */ jsxDEV("p", { className: "mb-0", children: labels.hubv2_bulk_delete_none }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
        lineNumber: 330,
        columnNumber: 21
      }),
      summary.refused.length > 0 ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
        /* @__PURE__ */ jsxDEV("p", { className: "local-dimensions-hubv2-dialog-label", children: fillCount(labels, "hubv2_bulk_delete_refused", summary.refused.length) }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
          lineNumber: 333,
          columnNumber: 25
        }),
        /* @__PURE__ */ jsxDEV("ul", { className: "local-dimensions-hubv2-bulkrefused", children: summary.refused.map((item) => /* @__PURE__ */ jsxDEV("li", { children: [
          /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-bulkrefused-name", children: item.name }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
            lineNumber: 339,
            columnNumber: 37
          }),
          item.reasons.length > 0 ? `: ${item.reasons.join("; ")}` : ""
        ] }, item.id, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
          lineNumber: 338,
          columnNumber: 33
        })) }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
          lineNumber: 336,
          columnNumber: 25
        })
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
        lineNumber: 332,
        columnNumber: 21
      }) : null
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
      lineNumber: 321,
      columnNumber: 13
    });
  }
  const foot = /* @__PURE__ */ jsxDEV(Fragment, { children: [
    /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-sm btn-secondary", onClick: onClose, children: labels["core:cancel"] }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
      lineNumber: 351,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV(
      "button",
      {
        type: "button",
        className: "btn btn-sm btn-danger",
        disabled: allowed === 0 || busy,
        onClick: () => {
          if (!busy) {
            setBusy(true);
            onCommit();
          }
        },
        children: fillCount(labels, "hubv2_bulk_delete_commit", allowed)
      },
      void 0,
      false,
      {
        fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
        lineNumber: 352,
        columnNumber: 13
      }
    )
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
    lineNumber: 350,
    columnNumber: 9
  });
  return /* @__PURE__ */ jsxDEV(
    Frame,
    {
      labels,
      title: fillCount(labels, "hubv2_bulk_delete_title", names.length),
      onClose,
      onLostFocus,
      body: /* @__PURE__ */ jsxDEV(Fragment, { children: [
        /* @__PURE__ */ jsxDEV(Names, { labels, names }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
          lineNumber: 364,
          columnNumber: 61
        }),
        verdict
      ] }, void 0, true, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
        lineNumber: 364,
        columnNumber: 59
      }),
      foot
    },
    void 0,
    false,
    {
      fileName: "public/local/dimensions/js/esm/src/hubv2/BulkDialogs.tsx",
      lineNumber: 363,
      columnNumber: 12
    }
  );
}, "DeleteDialog");
export {
  AppearanceDialog,
  DeleteDialog
};
//# sourceMappingURL=BulkDialogs.dev.js.map
