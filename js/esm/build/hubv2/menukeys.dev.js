var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * A menu's keyboard and its place on the screen, for Menu.tsx.
 *
 * The keys are the APG Menu pattern's and the fleet Design System's (menu.md): Up and Down move through
 * every item and wrap, Home and End go to the ends, a printable character moves to the next label it
 * starts (keynav.typeAhead), Enter and Space run the item, Escape and Tab close the menu. A disabled
 * item is reached like the others and runs nothing.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/menukeys
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { typeAhead } from "./keynav";
const firstActive = /* @__PURE__ */ __name((items) => {
  if (items.length === 0) {
    return -1;
  }
  const index = items.findIndex((item) => !item.disabled);
  return index < 0 ? 0 : index;
}, "firstActive");
const menuKey = /* @__PURE__ */ __name((items, active, key, query = "") => {
  const stay = { active, run: null, close: false, handled: true };
  const count = items.length;
  if (key.key === "Escape" || key.key === "Tab") {
    return { ...stay, close: true };
  }
  if (count === 0 || key.altKey || key.ctrlKey || key.metaKey) {
    return { ...stay, handled: false };
  }
  switch (key.key) {
    case "ArrowDown":
      return { ...stay, active: (active + 1) % count };
    case "ArrowUp":
      return { ...stay, active: (active - 1 + count) % count };
    case "Home":
      return { ...stay, active: 0 };
    case "End":
      return { ...stay, active: count - 1 };
    case "Enter":
    case " ":
      return items[active] && !items[active].disabled ? { ...stay, run: active } : stay;
    default:
      break;
  }
  if (key.key.length === 1 && query !== "") {
    const names = items.map((item, index) => ({ key: String(index), name: item.label }));
    const found = typeAhead(names, String(active), query);
    return found === null ? stay : { ...stay, active: Number(found) };
  }
  return { ...stay, handled: false };
}, "menuKey");
const placeMenu = /* @__PURE__ */ __name((anchor, size, view, pane, rtl, gap = 6, margin = 8) => {
  const wanted = rtl ? anchor.left : anchor.right - size.width;
  const left = Math.max(margin, Math.min(wanted, view.width - margin - size.width));
  const floor = Math.min(view.height, pane ? pane.bottom : view.height) - margin;
  const ceiling = Math.max(0, pane ? pane.top : 0) + margin;
  const below = anchor.bottom + gap;
  const above = anchor.top - gap - size.height;
  if (below + size.height <= floor) {
    return { left, top: below, above: false };
  }
  if (above >= ceiling) {
    return { left, top: above, above: true };
  }
  const roomBelow = view.height - below;
  const roomAbove = anchor.top - gap;
  const top = roomBelow >= roomAbove ? below : above;
  return { left, top: Math.max(margin, Math.min(top, view.height - margin - size.height)), above: roomBelow < roomAbove };
}, "placeMenu");
export {
  firstActive,
  menuKey,
  placeMenu
};
//# sourceMappingURL=menukeys.dev.js.map
