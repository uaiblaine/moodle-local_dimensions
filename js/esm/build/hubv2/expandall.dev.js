var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The outline's "Expand all" and "Collapse all": which closed branches to open next, and where to stop.
 *
 * The tree is lazy, so expanding all is a walk, level by level: each round opens every closed parent
 * at the shallowest depth that has one, loading the first page of those never opened, and the next
 * round looks again at what that revealed. It stops before the visible competencies would pass
 * EXPAND_ALL_BOUND, and says so; a parent's further pages stay behind its "load more" item, as they
 * do when it is opened by hand.
 *
 * Pure: the workspace loads and commits what a step names.
 *
 * @module     local_dimensions/hubv2/expandall
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { isExpanded, setExpanded, visibleItems } from "./tree";
const EXPAND_ALL_BOUND = 500;
const visibleCount = /* @__PURE__ */ __name((tree) => visibleItems(tree).filter((item) => item.kind === "competency").length, "visibleCount");
const expandStep = /* @__PURE__ */ __name((tree, bound, pagesize) => {
  const closed = visibleItems(tree).flatMap((item) => item.kind === "competency" && tree.nodes[item.id]?.haschildren && !isExpanded(tree, item.id) ? [{ id: item.id, depth: item.depth }] : []);
  if (closed.length === 0) {
    return { open: [], load: [], capped: false };
  }
  const depth = Math.min(...closed.map((item) => item.depth));
  const step = { open: [], load: [], capped: false };
  let shown = visibleCount(tree);
  let opened = tree;
  for (const item of closed.filter((candidate) => candidate.depth === depth)) {
    const loaded = tree.branches[item.id] !== void 0;
    const cost = loaded ? visibleCount(setExpanded(opened, item.id, true)) - visibleCount(opened) : pagesize;
    if (shown + cost > bound) {
      step.capped = true;
      break;
    }
    shown += cost;
    if (loaded) {
      opened = setExpanded(opened, item.id, true);
      step.open.push(item.id);
    } else {
      step.load.push(item.id);
    }
  }
  return step;
}, "expandStep");
const collapseAll = /* @__PURE__ */ __name((tree) => tree.expanded.length === 0 ? tree : { ...tree, expanded: [] }, "collapseAll");
export {
  EXPAND_ALL_BOUND,
  collapseAll,
  expandStep,
  visibleCount
};
//# sourceMappingURL=expandall.dev.js.map
