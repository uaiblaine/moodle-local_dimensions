var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The "Move to" dialogue's choices: the parents a competency may move under, as a tree of its own over
 * the workspace's store, and the search hits it may take.
 *
 * The dialogue's tree is the workspace's tree with the dialogue's own opened branches: what it loads
 * goes into the shared store (so the panes reuse it), and what it opens leaves the panes alone. The
 * framework is the root, standing for the top level. The competency being moved is drawn and cannot be
 * chosen, and it never opens, so none of its descendants is ever offered (core would make the move a
 * cycle; the canvas refuses before asking). Its current parent is marked and cannot be chosen either:
 * a move there changes nothing.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/moveparents
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { outlineRows } from "./outlinerows";
import { ROOT, isWithin } from "./tree";
const pickerTree = /* @__PURE__ */ __name((tree, open, moving) => {
  const node = tree.nodes[moving];
  return {
    nodes: node ? { ...tree.nodes, [moving]: { ...node, haschildren: false } } : tree.nodes,
    branches: tree.branches,
    expanded: open.filter((id) => id !== moving && tree.branches[id] !== void 0 && !!tree.nodes[id]?.haschildren)
  };
}, "pickerTree");
const canMoveUnder = /* @__PURE__ */ __name((tree, target, moving, currentparent) => {
  if (target === currentparent) {
    return false;
  }
  return target === ROOT || !isWithin(tree, target, moving);
}, "canMoveUnder");
const parentChoices = /* @__PURE__ */ __name((tree, open, moving, topname) => {
  const currentparent = tree.nodes[moving]?.parentid ?? ROOT;
  return outlineRows(pickerTree(tree, open, moving), topname).map((row) => {
    if (row.kind === "more") {
      return { ...row, disabled: false, current: false };
    }
    const current = row.id === currentparent;
    return { ...row, current, disabled: !canMoveUnder(tree, row.id, moving, currentparent) };
  });
}, "parentChoices");
const hitSelectable = /* @__PURE__ */ __name((item, moving, currentparent) => item.id !== moving && !item.pathids.includes(moving) && item.id !== currentparent, "hitSelectable");
export {
  canMoveUnder,
  hitSelectable,
  parentChoices,
  pickerTree
};
//# sourceMappingURL=moveparents.dev.js.map
