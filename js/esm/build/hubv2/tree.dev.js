var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The lazily loaded tree the canvas draws: which competencies are known, which branches are loaded
 * and which are open.
 *
 * Pure and immutable: every function returns a new tree and leaves its argument alone, so React
 * state holds it as it is and Jest reads it without a DOM. A branch is the loaded part of a
 * parent's direct children, in the server's sort order, with the parent's total beside it: fewer
 * ids than the total means more pages to load.
 *
 * @module     local_dimensions/hubv2/tree
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const ROOT = 0;
const copy = /* @__PURE__ */ __name((tree) => ({
  nodes: { ...tree.nodes },
  branches: { ...tree.branches },
  expanded: [...tree.expanded]
}), "copy");
const pruneBelow = /* @__PURE__ */ __name((tree, id) => {
  const branch = tree.branches[id];
  if (!branch) {
    return;
  }
  branch.ids.forEach((child) => {
    pruneBelow(tree, child);
    delete tree.nodes[child];
    tree.expanded = tree.expanded.filter((open) => open !== child);
  });
  delete tree.branches[id];
}, "pruneBelow");
const createTree = /* @__PURE__ */ __name((roots) => insertChildren({ nodes: {}, branches: {}, expanded: [] }, ROOT, roots), "createTree");
const insertChildren = /* @__PURE__ */ __name((tree, parentid, page, append = false) => {
  const next = copy(tree);
  const incoming = page.items.map((item) => item.id);
  const previous = next.branches[parentid]?.ids ?? [];
  if (!append) {
    previous.filter((id) => !incoming.includes(id)).forEach((gone) => {
      pruneBelow(next, gone);
      delete next.nodes[gone];
      next.expanded = next.expanded.filter((open) => open !== gone);
    });
  }
  const kept = append ? previous.filter((id) => !incoming.includes(id)) : [];
  next.branches[parentid] = { ids: [...kept, ...incoming], total: page.total };
  page.items.forEach((item) => {
    next.nodes[item.id] = item;
  });
  return next;
}, "insertChildren");
const isExpanded = /* @__PURE__ */ __name((tree, id) => id === ROOT || tree.expanded.includes(id), "isExpanded");
const hasBranch = /* @__PURE__ */ __name((tree, id) => tree.branches[id] !== void 0, "hasBranch");
const setExpanded = /* @__PURE__ */ __name((tree, id, open) => {
  if (id === ROOT || isExpanded(tree, id) === open) {
    return tree;
  }
  const next = copy(tree);
  next.expanded = open ? [...next.expanded, id] : next.expanded.filter((other) => other !== id);
  return next;
}, "setExpanded");
const replaceNode = /* @__PURE__ */ __name((tree, node) => {
  const next = copy(tree);
  next.nodes[node.id] = node;
  if (!node.haschildren) {
    pruneBelow(next, node.id);
    next.expanded = next.expanded.filter((open) => open !== node.id);
  }
  return next;
}, "replaceNode");
const forgetBranch = /* @__PURE__ */ __name((tree, id) => {
  const next = copy(tree);
  pruneBelow(next, id);
  next.expanded = next.expanded.filter((open) => open !== id);
  return next;
}, "forgetBranch");
const removeNode = /* @__PURE__ */ __name((tree, id) => {
  const node = tree.nodes[id];
  if (!node) {
    return tree;
  }
  const next = copy(tree);
  pruneBelow(next, id);
  delete next.nodes[id];
  next.expanded = next.expanded.filter((open) => open !== id);
  const branch = next.branches[node.parentid];
  if (branch) {
    next.branches[node.parentid] = { ids: branch.ids.filter((other) => other !== id), total: Math.max(0, branch.total - 1) };
  }
  return next;
}, "removeNode");
const ancestors = /* @__PURE__ */ __name((tree, id) => {
  const chain = [];
  let parent = tree.nodes[id]?.parentid ?? ROOT;
  while (parent !== ROOT && tree.nodes[parent] && !chain.includes(parent)) {
    chain.unshift(parent);
    parent = tree.nodes[parent].parentid;
  }
  return chain;
}, "ancestors");
const isWithin = /* @__PURE__ */ __name((tree, id, ancestorid) => id === ancestorid || ancestors(tree, id).includes(ancestorid), "isWithin");
const missingBranches = /* @__PURE__ */ __name((tree, pathids) => [ROOT, ...pathids].filter((id) => !hasBranch(tree, id)), "missingBranches");
const expandPath = /* @__PURE__ */ __name((tree, pathids) => pathids.reduce((current, id) => setExpanded(current, id, true), tree), "expandPath");
const branchHas = /* @__PURE__ */ __name((tree, parentid, id) => tree.branches[parentid]?.ids.includes(id) ?? false, "branchHas");
const visibleItems = /* @__PURE__ */ __name((tree) => {
  const items = [];
  const walk = /* @__PURE__ */ __name((parent, depth) => {
    const branch = tree.branches[parent];
    if (!branch || !isExpanded(tree, parent)) {
      return;
    }
    branch.ids.forEach((id) => {
      if (!tree.nodes[id]) {
        return;
      }
      items.push({ kind: "competency", id, parent, depth });
      walk(id, depth + 1);
    });
    if (branch.ids.length < branch.total) {
      items.push({ kind: "more", parent, depth, remaining: branch.total - branch.ids.length });
    }
  }, "walk");
  walk(ROOT, 1);
  return items;
}, "visibleItems");
export {
  ROOT,
  ancestors,
  branchHas,
  createTree,
  expandPath,
  forgetBranch,
  hasBranch,
  insertChildren,
  isExpanded,
  isWithin,
  missingBranches,
  removeNode,
  replaceNode,
  setExpanded,
  visibleItems
};
//# sourceMappingURL=tree.dev.js.map
