var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The splitter between the outline and the map in the Both layout: the outline's width, its bounds,
 * and how a key or a drag changes it.
 *
 * It follows the hub's own pane divider (amd/src/central/pane_resizer.js): the width is kept in
 * localStorage under a local_dimensions_* key, the arrow keys move it 24 px a step, a double click
 * goes back to the stylesheet's default; and, as the APG Window Splitter pattern adds, Home and End
 * go to the limits. The outline is at the start side of the page, so in a right-to-left language it
 * is at the right and the keys and the drag mirror.
 *
 * Pure but for the two storage helpers, which swallow a browser that refuses storage.
 *
 * @module     local_dimensions/hubv2/outlinewidth
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const OUTLINE_WIDTH = { initial: 264, minimum: 200, maximum: 560, step: 24 };
const MAP_RESERVE = 320;
const STORAGE_KEY = "local_dimensions_hubv2_outline_width";
const upperBound = /* @__PURE__ */ __name((available) => {
  const room = available > 0 ? available - MAP_RESERVE : OUTLINE_WIDTH.maximum;
  return Math.max(OUTLINE_WIDTH.minimum, Math.min(OUTLINE_WIDTH.maximum, room));
}, "upperBound");
const clampWidth = /* @__PURE__ */ __name((width, available) => {
  const value = Number.isFinite(width) ? width : OUTLINE_WIDTH.initial;
  return Math.round(Math.min(Math.max(value, OUTLINE_WIDTH.minimum), upperBound(available)));
}, "clampWidth");
const keyWidth = /* @__PURE__ */ __name((key, width, available, rtl) => {
  const grow = rtl ? "ArrowLeft" : "ArrowRight";
  const shrink = rtl ? "ArrowRight" : "ArrowLeft";
  switch (key) {
    case grow:
      return clampWidth(width + OUTLINE_WIDTH.step, available);
    case shrink:
      return clampWidth(width - OUTLINE_WIDTH.step, available);
    case "Home":
      return OUTLINE_WIDTH.minimum;
    case "End":
      return upperBound(available);
    default:
      return null;
  }
}, "keyWidth");
const dragWidth = /* @__PURE__ */ __name((start, startx, x, available, rtl) => clampWidth(start + (rtl ? startx - x : x - startx), available), "dragWidth");
const storedWidth = /* @__PURE__ */ __name(() => {
  try {
    const stored = Number(window.localStorage.getItem(STORAGE_KEY));
    return stored > 0 ? stored : OUTLINE_WIDTH.initial;
  } catch {
    return OUTLINE_WIDTH.initial;
  }
}, "storedWidth");
const storeWidth = /* @__PURE__ */ __name((width) => {
  try {
    if (width === null) {
      window.localStorage.removeItem(STORAGE_KEY);
    } else {
      window.localStorage.setItem(STORAGE_KEY, String(Math.round(width)));
    }
  } catch {
  }
}, "storeWidth");
export {
  MAP_RESERVE,
  OUTLINE_WIDTH,
  STORAGE_KEY,
  clampWidth,
  dragWidth,
  keyWidth,
  storeWidth,
  storedWidth,
  upperBound
};
//# sourceMappingURL=outlinewidth.dev.js.map
