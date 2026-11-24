var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The bulk dock: the count of the competencies picked and the actions over them (the fleet Design System's data
 * report, "Bulk dock", D133 to D135).
 *
 * Drawn by the workspace after its panes while something is picked, and only for a manager (the registry's `bulk`
 * scope); sticky 12 px above the viewport's bottom while the panes scroll, never fixed. Its count comes first, in a
 * polite live region, saying how many picks sit in closed branches when some do; then the actions as core's buttons,
 * the main one the only solid fill, "Clear selection", and the destructive one last, outlined. It takes no focus
 * when it appears and follows the panes in the Tab order. An action needing more input opens a dialogue
 * (BulkDialogs.tsx); the dock never grows fields.
 *
 * @module     local_dimensions/hubv2/Dock
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { dockCount } from "./bulk";
const buttonClass = /* @__PURE__ */ __name((view, index) => {
  if (view.spec.danger) {
    return "btn btn-outline-danger";
  }
  return index === 0 ? "btn btn-primary" : "btn btn-outline-secondary";
}, "buttonClass");
const Dock = /* @__PURE__ */ __name((props) => {
  const { labels, count, hidden, actions, onRun, onClear } = props;
  if (actions.length === 0) {
    return null;
  }
  const plain = actions.filter((view) => !view.spec.danger);
  const danger = actions.filter((view) => view.spec.danger);
  const button = /* @__PURE__ */ __name((view, index) => /* @__PURE__ */ jsxDEV(
    "button",
    {
      type: "button",
      className: buttonClass(view, index),
      "aria-haspopup": "dialog",
      disabled: view.state === "disabled",
      onClick: (event) => onRun(view.spec.id, event.currentTarget),
      children: [
        /* @__PURE__ */ jsxDEV("i", { className: `fa ${view.spec.icon} me-1`, "aria-hidden": "true" }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Dock.tsx",
          lineNumber: 93,
          columnNumber: 13
        }),
        labels[view.spec.label]
      ]
    },
    view.spec.id,
    true,
    {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Dock.tsx",
      lineNumber: 85,
      columnNumber: 9
    }
  ), "button");
  return /* @__PURE__ */ jsxDEV(
    "div",
    {
      className: "local-dimensions-hubv2-dock",
      role: "region",
      "aria-label": labels.hubv2_bulk_dock,
      "data-region": "bulk-dock",
      children: [
        /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-dockcount", "aria-live": "polite", children: dockCount(labels, count, hidden) }, void 0, false, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Dock.tsx",
          lineNumber: 99,
          columnNumber: 13
        }),
        /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-dockactions", children: [
          plain.map(button),
          /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-outline-secondary", onClick: onClear, children: labels.hubv2_bulk_clear }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Dock.tsx",
            lineNumber: 104,
            columnNumber: 17
          }),
          danger.map((view) => button(view, plain.length))
        ] }, void 0, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Dock.tsx",
          lineNumber: 102,
          columnNumber: 13
        })
      ]
    },
    void 0,
    true,
    {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Dock.tsx",
      lineNumber: 97,
      columnNumber: 9
    }
  );
}, "Dock");
var Dock_default = Dock;
export {
  Dock_default as default
};
//# sourceMappingURL=Dock.dev.js.map
