var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The workspace's one selection and its layouts: what picking an item does, where each pane's Tab
 * stop sits after a change, and which panes a layout draws.
 *
 * The outline and the map are two projections of one store (Hub.tsx's Workspace): one tree, one
 * selected competency, one move in progress. Each pane keeps only its own Tab stop, and both stops
 * follow the selection while the focus is elsewhere, so Tab enters either pane at the competency the
 * other one selected (APG Tree View).
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/selection
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { entryKey, survivorKey } from "./keynav";
import { ROOT } from "./tree";
const LAYOUTS = ["outline", "map", "both"];
const LAYOUT_EVENT = "local_dimensions:hubv2-layout";
const isLayout = /* @__PURE__ */ __name((value) => LAYOUTS.includes(value), "isLayout");
const panesOf = /* @__PURE__ */ __name((layout) => ({ outline: layout !== "map", map: layout !== "outline" }), "panesOf");
const activation = /* @__PURE__ */ __name((moving, id) => {
  if (moving !== null) {
    return { type: "pick", parent: id };
  }
  return { type: "select", id: id === ROOT ? null : id };
}, "activation");
const nextStop = /* @__PURE__ */ __name((previous, order, tree, selected, active, inside) => {
  if (!inside) {
    return { key: entryKey(tree, order, selected), refocus: false };
  }
  if (order.some((item) => item.key === active)) {
    return { key: active, refocus: false };
  }
  return { key: survivorKey(previous, order, active), refocus: true };
}, "nextStop");
export {
  LAYOUTS,
  LAYOUT_EVENT,
  activation,
  isLayout,
  nextStop,
  panesOf
};
//# sourceMappingURL=selection.dev.js.map
