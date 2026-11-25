var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The workspace's modal dialogue: the command palette's and "Move to"'s frame.
 *
 * Labelled by its title, modal, the focus kept inside (Tab and Shift+Tab wrap), Escape or a press on
 * the veil around it closes it, and the focus goes back to the element that had it when the dialogue
 * opened (the fleet Design System's dialog variant, panel-popover-sheet.md). When that element has left
 * the page meanwhile (a view switched from the palette), or when the dialogue was opened by another
 * layer that has closed (returnTo 'item'), onLostFocus decides where the focus goes (focusreturn.ts).
 *
 * Drawn on the page's body, over everything, as core's dialogues are; React still delivers its events
 * through the workspace.
 *
 * @module     local_dimensions/hubv2/Dialog
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useEffect, useRef } from "react";
import { createPortal } from "react-dom";
const FOCUSABLE = 'button:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex="0"], a[href]';
const Dialog = /* @__PURE__ */ __name(({ labelledby, className, initialFocus, returnTo, onClose, onLostFocus, children }) => {
  const box = useRef(null);
  const latest = useRef({ onLostFocus, returnTo });
  latest.current = { onLostFocus, returnTo };
  useEffect(() => {
    const opener = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    initialFocus.current?.focus();
    return () => {
      if (latest.current.returnTo === "opener" && opener && opener !== document.body && opener.isConnected) {
        opener.focus({ preventScroll: true });
      } else {
        latest.current.onLostFocus();
      }
    };
  }, [initialFocus]);
  const onKeyDown = /* @__PURE__ */ __name((event) => {
    if (event.key === "Escape") {
      event.preventDefault();
      event.stopPropagation();
      onClose();
      return;
    }
    if (event.key !== "Tab" || !box.current) {
      return;
    }
    const focusable = [...box.current.querySelectorAll(FOCUSABLE)].filter((element) => !element.hidden);
    if (focusable.length === 0) {
      event.preventDefault();
      return;
    }
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }, "onKeyDown");
  const onVeil = /* @__PURE__ */ __name((event) => {
    if (event.target === event.currentTarget) {
      onClose();
    }
  }, "onVeil");
  return createPortal(
    /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-scrim", onMouseDown: onVeil, children: /* @__PURE__ */ jsxDEV(
      "div",
      {
        ref: box,
        className,
        role: "dialog",
        "aria-modal": "true",
        "aria-labelledby": labelledby,
        onKeyDown,
        children
      },
      void 0,
      false,
      {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Dialog.tsx",
        lineNumber: 125,
        columnNumber: 13
      }
    ) }, void 0, false, {
      fileName: "public/local/dimensions/js/esm/src/hubv2/Dialog.tsx",
      lineNumber: 124,
      columnNumber: 9
    }),
    document.body
  );
}, "Dialog");
var Dialog_default = Dialog;
export {
  Dialog_default as default
};
//# sourceMappingURL=Dialog.dev.js.map
