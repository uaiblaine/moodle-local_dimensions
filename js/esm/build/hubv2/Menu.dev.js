var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * A menu of the workspace: a node's actions, or the outline's display options.
 *
 * The fleet Design System's menu (menu.md): an icon before every action, a check box before every
 * on/off choice, separators between groups, the destructive item last, shortcuts at the line's end.
 * The ARIA Menu pattern: role menu, one item holding the tab stop, the keys of menukeys.ts, Escape, Tab or
 * a press outside closes it and gives the focus back to what opened it (the caller's onClose).
 *
 * It is drawn in a layer of its own on the page's body, fixed to the viewport under its anchor's real
 * bottom (menukeys.placeMenu), so neither the map nor the outline, both of which clip what overflows them,
 * can cut it. React still delivers its events through the workspace.
 *
 * @module     local_dimensions/hubv2/Menu
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { Fragment, useCallback, useEffect, useLayoutEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { ariaShortcut, shortcutCaps } from "./commands";
import { firstActive, menuKey, placeMenu } from "./menukeys";
const TYPE_AHEAD_PAUSE = 500;
const boxOf = /* @__PURE__ */ __name((element) => {
  const rect = element.getBoundingClientRect();
  return { left: rect.left, top: rect.top, right: rect.right, bottom: rect.bottom };
}, "boxOf");
const itemClass = /* @__PURE__ */ __name((item) => item.danger ? "local-dimensions-hubv2-menu-item local-dimensions-hubv2-menu-item--danger" : "local-dimensions-hubv2-menu-item", "itemClass");
const Menu = /* @__PURE__ */ __name(({ label, items, anchor, pane, rtl, mac, onRun, onClose }) => {
  const [active, setActive] = useState(() => firstActive(items.map((item) => ({ label: item.label, disabled: !!item.disabled }))));
  const [place, setPlace] = useState({ left: 0, top: 0 });
  const box = useRef(null);
  const buttons = useRef([]);
  const typed = useRef({ text: "", at: 0 });
  const latest = useRef({ onClose, anchor, pane, rtl });
  latest.current = { onClose, anchor, pane, rtl };
  const position = useCallback(() => {
    const { anchor: from, pane: around, rtl: backwards } = latest.current;
    const element = box.current;
    if (!element) {
      return;
    }
    if (!from.isConnected) {
      latest.current.onClose(false);
      return;
    }
    const placement = placeMenu(
      boxOf(from),
      { width: element.offsetWidth, height: element.offsetHeight },
      { width: window.innerWidth, height: window.innerHeight },
      around ? boxOf(around) : null,
      backwards
    );
    setPlace({ left: placement.left, top: placement.top });
  }, []);
  useLayoutEffect(() => {
    position();
  }, [position, items.length]);
  useEffect(() => {
    window.addEventListener("resize", position);
    window.addEventListener("scroll", position, true);
    return () => {
      window.removeEventListener("resize", position);
      window.removeEventListener("scroll", position, true);
    };
  }, [position]);
  useEffect(() => {
    const onPointer = /* @__PURE__ */ __name((event) => {
      const target = event.target;
      if (target && (box.current?.contains(target) || latest.current.anchor.contains(target))) {
        return;
      }
      latest.current.onClose(true);
    }, "onPointer");
    document.addEventListener("pointerdown", onPointer, true);
    return () => document.removeEventListener("pointerdown", onPointer, true);
  }, []);
  useEffect(() => {
    if (active >= 0) {
      buttons.current[active]?.focus({ preventScroll: true });
    }
  }, [active]);
  const onKeyDown = /* @__PURE__ */ __name((event) => {
    let query = "";
    if (event.key.length === 1 && event.key !== " ") {
      const previous = typed.current;
      query = event.timeStamp - previous.at > TYPE_AHEAD_PAUSE ? event.key : previous.text + event.key;
      typed.current = { text: query, at: event.timeStamp };
    }
    const step = menuKey(items.map((item) => ({ label: item.label, disabled: !!item.disabled })), active, event, query);
    if (!step.handled) {
      return;
    }
    event.preventDefault();
    event.stopPropagation();
    if (step.close) {
      onClose(false);
      return;
    }
    if (step.run !== null) {
      onRun(items[step.run].id);
      return;
    }
    setActive(step.active);
  }, "onKeyDown");
  return createPortal(
    /* @__PURE__ */ jsxDEV(
      "div",
      {
        ref: box,
        className: "local-dimensions-hubv2-menu",
        role: "menu",
        "aria-label": label,
        style: place,
        onKeyDown,
        children: items.map((item, index) => /* @__PURE__ */ jsxDEV(Fragment, { children: [
          item.separator ? /* @__PURE__ */ jsxDEV("div", { className: "local-dimensions-hubv2-menu-sep", role: "separator" }, void 0, false, {
            fileName: "public/local/dimensions/js/esm/src/hubv2/Menu.tsx",
            lineNumber: 220,
            columnNumber: 39
          }) : null,
          /* @__PURE__ */ jsxDEV(
            "button",
            {
              ref: (element) => {
                buttons.current[index] = element;
              },
              type: "button",
              className: itemClass(item),
              role: item.checkbox ? "menuitemcheckbox" : "menuitem",
              tabIndex: index === active ? 0 : -1,
              "aria-checked": item.checkbox ? !!item.checked : void 0,
              "aria-disabled": item.disabled ? true : void 0,
              "aria-keyshortcuts": item.shortcut ? ariaShortcut(item.shortcut, mac) : void 0,
              onClick: () => {
                setActive(index);
                if (!item.disabled) {
                  onRun(item.id);
                }
              },
              children: [
                item.checkbox ? /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-menu-box", "aria-hidden": "true", children: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-check" }, void 0, false, {
                  fileName: "public/local/dimensions/js/esm/src/hubv2/Menu.tsx",
                  lineNumber: 241,
                  columnNumber: 33
                }) }, void 0, false, {
                  fileName: "public/local/dimensions/js/esm/src/hubv2/Menu.tsx",
                  lineNumber: 240,
                  columnNumber: 29
                }) : /* @__PURE__ */ jsxDEV("i", { className: `fa ${item.icon ?? "fa-circle"} local-dimensions-hubv2-menu-icon`, "aria-hidden": "true" }, void 0, false, {
                  fileName: "public/local/dimensions/js/esm/src/hubv2/Menu.tsx",
                  lineNumber: 243,
                  columnNumber: 29
                }),
                /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-menu-label", children: item.label }, void 0, false, {
                  fileName: "public/local/dimensions/js/esm/src/hubv2/Menu.tsx",
                  lineNumber: 244,
                  columnNumber: 25
                }),
                item.shortcut ? /* @__PURE__ */ jsxDEV("span", { className: "local-dimensions-hubv2-keys", "aria-hidden": "true", children: shortcutCaps(item.shortcut, mac).map((cap) => /* @__PURE__ */ jsxDEV("kbd", { children: cap }, cap, false, {
                  fileName: "public/local/dimensions/js/esm/src/hubv2/Menu.tsx",
                  lineNumber: 247,
                  columnNumber: 80
                })) }, void 0, false, {
                  fileName: "public/local/dimensions/js/esm/src/hubv2/Menu.tsx",
                  lineNumber: 246,
                  columnNumber: 29
                }) : null
              ]
            },
            void 0,
            true,
            {
              fileName: "public/local/dimensions/js/esm/src/hubv2/Menu.tsx",
              lineNumber: 221,
              columnNumber: 21
            }
          )
        ] }, item.id, true, {
          fileName: "public/local/dimensions/js/esm/src/hubv2/Menu.tsx",
          lineNumber: 219,
          columnNumber: 17
        }))
      },
      void 0,
      false,
      {
        fileName: "public/local/dimensions/js/esm/src/hubv2/Menu.tsx",
        lineNumber: 210,
        columnNumber: 9
      }
    ),
    document.body
  );
}, "Menu");
var Menu_default = Menu;
export {
  Menu_default as default
};
//# sourceMappingURL=Menu.dev.js.map
