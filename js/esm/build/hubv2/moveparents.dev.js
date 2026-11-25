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
 * a move there changes nothing. The bulk dock moves several competencies at once: each of them is closed and
 * cannot be chosen, and a parent is marked current, and refused, only when it is every one's parent already (a
 * competency already under the chosen parent is skipped, bulk.movePlan).
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/moveparents
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { outlineRows } from "./outlinerows";
import { ROOT, isWithin } from "./tree";
const subjectsOf = /* @__PURE__ */ __name((moving) => Array.isArray(moving) ? moving : [moving], "subjectsOf");
const pickerTree = /* @__PURE__ */ __name((tree, open, moving) => {
  const subjects = subjectsOf(moving);
  const nodes = { ...tree.nodes };
  subjects.forEach((id) => {
    if (nodes[id]) {
      nodes[id] = { ...nodes[id], haschildren: false };
    }
  });
  return {
    nodes,
    branches: tree.branches,
    expanded: open.filter((id) => !subjects.includes(id) && tree.branches[id] !== void 0 && !!tree.nodes[id]?.haschildren)
  };
}, "pickerTree");
const canMoveUnder = /* @__PURE__ */ __name((tree, target, moving, currentparent) => {
  if (target === currentparent) {
    return false;
  }
  return target === ROOT || !isWithin(tree, target, moving);
}, "canMoveUnder");
const sharedParent = /* @__PURE__ */ __name((tree, subjects) => {
  const parents = new Set(subjects.map((id) => tree.nodes[id]?.parentid ?? ROOT));
  return parents.size === 1 ? [...parents][0] : null;
}, "sharedParent");
const parentChoices = /* @__PURE__ */ __name((tree, open, moving, topname) => {
  const subjects = subjectsOf(moving);
  const currentparent = sharedParent(tree, subjects);
  return outlineRows(pickerTree(tree, open, subjects), topname).map((row) => {
    if (row.kind === "more") {
      return { ...row, disabled: false, current: false };
    }
    const current = row.id === currentparent;
    const inside = row.id !== ROOT && subjects.some((id) => isWithin(tree, row.id, id));
    return { ...row, current, disabled: current || inside };
  });
}, "parentChoices");
const hitSelectable = /* @__PURE__ */ __name((item, moving, currentparent) => subjectsOf(moving).every((id) => item.id !== id && !item.pathids.includes(id)) && item.id !== currentparent, "hitSelectable");
export {
  canMoveUnder,
  hitSelectable,
  parentChoices,
  pickerTree,
  sharedParent
};
//# sourceMappingURL=moveparents.dev.js.map
