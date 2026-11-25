var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The outline's inline editor: a row with a name and an ID number for a new competency, or the name
 * alone to rename one in place (owner's decision H5: fast authoring in the outline only).
 *
 * A form in all but the element: Tab and Shift+Tab move through its fields and its two buttons, Enter
 * in a field saves, Escape cancels and the workspace gives the focus back to the row. The row sits in the
 * tree's list as a presentational item holding a labelled group, so the tree's keys (which act on
 * treeitems only) leave its fields alone. What the server refuses (an ID number already taken, a
 * missing right) is said under the fields, tied to them by aria-describedby and announced, and the
 * editor stays open with what was typed.
 *
 * @module     local_dimensions/hubv2/Editor
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useEffect, useId, useRef, useState } from "react";
const Editor = /* @__PURE__ */ __name(({ labels, mode, label, level, initialname, initialidnumber, onSave, onCancel }) => {
  const [name, setName] = useState(initialname);
  const [idnumber, setIdnumber] = useState(initialidnumber);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const first = useRef(null);
  const alive = useRef(true);
  const id = useId();
  const errorid = `${id}-error`;
  useEffect(() => {
    first.current?.focus();
    first.current?.select();
    return () => {
      alive.current = false;
    };
  }, []);
  const save = /* @__PURE__ */ __name(() => {
    if (busy) {
      return;
    }
    if (name.trim() === "" || mode === "create" && idnumber.trim() === "") {
      setError(labels["core:required"]);
      return;
    }
    setBusy(true);
    onSave(name.trim(), idnumber.trim()).then((refusal) => {
      if (alive.current) {
        setBusy(false);
        setError(refusal ?? "");
      }
      return null;
    }).catch(() => {
      if (alive.current) {
        setBusy(false);
      }
    });
  }, "save");
  const onKeyDown = /* @__PURE__ */ __name((event) => {
    if (event.key === "Escape") {
      event.preventDefault();
      event.stopPropagation();
      onCancel();
      return;
    }
    if (event.key === "Enter" && event.target instanceof HTMLInputElement) {
      event.preventDefault();
      event.stopPropagation();
      save();
    }
  }, "onKeyDown");
  const described = error ? errorid : void 0;
  return /* @__PURE__ */ jsxDEV("li", { role: "none", className: "local-dimensions-hubv2-oeditor", style: { paddingInlineStart: `${0.25 + (level - 1) * 1.25}rem` }, children: [
    /* @__PURE__ */ jsxDEV("div", { role: "group", "aria-label": label, className: "local-dimensions-hubv2-oeditor-group", onKeyDown, children: [
      /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-otoggle", "aria-hidden": "true" }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Editor.tsx",
        lineNumber: 128,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV(
        "input",
        {
          ref: first,
          type: "text",
          className: "form-control form-control-sm local-dimensions-hubv2-oeditor-name",
          "aria-label": labels.hubv2_editor_name,
          placeholder: labels.hubv2_editor_name,
          "aria-invalid": error ? true : void 0,
          "aria-describedby": described,
          maxLength: 100,
          value: name,
          onChange: (event) => setName(event.target.value)
        },
        void 0,
        false,
        {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Editor.tsx",
          lineNumber: 129,
          columnNumber: 17
        }
      ),
      mode === "create" ? /* @__PURE__ */ jsxDEV(
        "input",
        {
          type: "text",
          className: "form-control form-control-sm local-dimensions-hubv2-oeditor-id",
          "aria-label": labels["core:idnumber"],
          placeholder: labels["core:idnumber"],
          "aria-invalid": error ? true : void 0,
          "aria-describedby": described,
          maxLength: 100,
          value: idnumber,
          onChange: (event) => setIdnumber(event.target.value)
        },
        void 0,
        false,
        {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Editor.tsx",
          lineNumber: 142,
          columnNumber: 21
        }
      ) : null,
      /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-sm btn-primary", disabled: busy, onClick: save, children: labels["core:save"] }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Editor.tsx",
        lineNumber: 154,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-sm btn-secondary", onClick: onCancel, children: labels["core:cancel"] }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Editor.tsx",
        lineNumber: 157,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Editor.tsx",
      lineNumber: 127,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("p", { id: errorid, className: "local-dimensions-hubv2-oeditor-error", role: "alert", children: error }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Editor.tsx",
      lineNumber: 159,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/local/dimensions/js/esm/src/hubv2/Editor.tsx",
    lineNumber: 126,
    columnNumber: 9
  });
}, "Editor");
var Editor_default = Editor;
export {
  Editor_default as default
};
//# sourceMappingURL=Editor.dev.js.map
