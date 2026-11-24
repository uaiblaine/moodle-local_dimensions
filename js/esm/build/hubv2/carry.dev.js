var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * What the workspace keeps across a reload of its pane: the branches the viewer opened and the selection.
 *
 * An action on the framework (an edit of its name or taxonomies, its scale, its visibility, a duplicate) reloads
 * the Competencies tab's pane, so the framework select, the root node and every competency's taxonomy are the
 * server's again; the workspace is mounted afresh. Before the reload the workspace holds what the viewer had
 * opened, and the new mount takes it back when it shows the same framework, opening the same branches (as
 * many children loaded as before) and selecting the same item. Another framework takes nothing.
 *
 * Node-local by nature: it lives in this page's module, for one reload. The tree is pure (tree.ts), so Jest pins
 * what is held without a DOM.
 *
 * @module     local_dimensions/hubv2/carry
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { ROOT, ancestors } from "./tree";
let held = null;
const carryOf = /* @__PURE__ */ __name((frameworkid, tree, selected) => {
  const expanded = tree.expanded.filter((id) => tree.nodes[id] !== void 0).map((id) => ({ id, pathids: ancestors(tree, id) })).sort((one, other) => one.pathids.length - other.pathids.length);
  const loaded = {};
  Object.entries(tree.branches).forEach(([parent, branch]) => {
    loaded[Number(parent)] = branch.ids.length;
  });
  return {
    frameworkid,
    expanded,
    loaded,
    selected: selected !== null && selected !== ROOT && tree.nodes[selected] ? { id: selected, pathids: ancestors(tree, selected) } : null
  };
}, "carryOf");
const hold = /* @__PURE__ */ __name((carry) => {
  held = carry;
}, "hold");
const take = /* @__PURE__ */ __name((frameworkid) => {
  const carry = held;
  held = null;
  return carry && carry.frameworkid === frameworkid ? carry : null;
}, "take");
export {
  carryOf,
  hold,
  take
};
//# sourceMappingURL=carry.dev.js.map
