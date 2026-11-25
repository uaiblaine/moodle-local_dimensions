var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The splitter between the outline and the map in the Both layout: an APG Window Splitter, moved by
 * the arrow keys, Home and End, or a drag; a double click goes back to the default width.
 *
 * The widths and the keys are outlinewidth.ts's; the width lives in the workspace, which hands it to the
 * stylesheet. Below the stacked breakpoint the stylesheet does not draw it.
 *
 * @module     local_dimensions/hubv2/Splitter
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useRef } from "react";
import { OUTLINE_WIDTH, dragWidth, keyWidth, upperBound } from "./outlinewidth";
const Splitter = /* @__PURE__ */ __name((props) => {
  const { label, controls, width, available, rtl, onWidth, onReset } = props;
  const drag = useRef(null);
  const onKeyDown = /* @__PURE__ */ __name((event) => {
    if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) {
      return;
    }
    const next = keyWidth(event.key, width, available(), rtl);
    if (next === null) {
      return;
    }
    event.preventDefault();
    onWidth(next, true);
  }, "onKeyDown");
  const onPointerDown = /* @__PURE__ */ __name((event) => {
    event.preventDefault();
    drag.current = { startx: event.clientX, start: width, last: width };
    event.currentTarget.setPointerCapture(event.pointerId);
  }, "onPointerDown");
  const onPointerMove = /* @__PURE__ */ __name((event) => {
    const state = drag.current;
    if (!state) {
      return;
    }
    state.last = dragWidth(state.start, state.startx, event.clientX, available(), rtl);
    onWidth(state.last, false);
  }, "onPointerMove");
  const onPointerEnd = /* @__PURE__ */ __name(() => {
    const state = drag.current;
    if (!state) {
      return;
    }
    drag.current = null;
    onWidth(state.last, true);
  }, "onPointerEnd");
  return /* @__PURE__ */ jsxDEV(
    "div",
    {
      className: "local-dimensions-hubv2-splitter",
      role: "separator",
      "aria-orientation": "vertical",
      "aria-label": label,
      "aria-controls": controls,
      "aria-valuenow": width,
      "aria-valuemin": OUTLINE_WIDTH.minimum,
      "aria-valuemax": upperBound(available()),
      tabIndex: 0,
      onKeyDown,
      onPointerDown,
      onPointerMove,
      onPointerUp: onPointerEnd,
      onPointerCancel: onPointerEnd,
      onLostPointerCapture: onPointerEnd,
      onDoubleClick: onReset,
      children: /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-splitter-grip", "aria-hidden": "true" }, void 0, false, {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Splitter.tsx",
        lineNumber: 134,
        columnNumber: 13
      })
    },
    void 0,
    false,
    {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Splitter.tsx",
      lineNumber: 116,
      columnNumber: 9
    }
  );
}, "Splitter");
var Splitter_default = Splitter;
export {
  Splitter_default as default
};
//# sourceMappingURL=Splitter.dev.js.map
