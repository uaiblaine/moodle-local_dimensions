var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The workspace's keyboard, shared by the outline and the map: which item a key moves to, what a
 * map node is called, where a letter typed in the outline jumps, and when the map pans.
 *
 * Each pane is one Tab stop (a roving tabindex): the active item carries tabindex 0, every other
 * one -1, and the arrow keys walk the tree's structure with the keys of the W3C APG Tree View
 * pattern and of core's own core/tree (lib/amd/src/tree.js, handleKeyDown): Right opens a closed
 * parent or moves to its first child, Left closes an open parent or moves to its parent, Up and
 * Down move through the visible items in tree order, Home and End to the first and last, Enter
 * and Space select (a "load more" item loads its page instead). In a right-to-left language Left
 * and Right swap, as core/tree swaps them: the outline is mirrored by the page's direction and the
 * map by its layout (layout.ts mirrorLayout), so the key towards the children is the key that opens.
 *
 * Both panes walk the same order (navOrder), the framework first: a competency's level is its
 * depth plus one in both, so the outline's aria-level and the map's spoken level agree.
 *
 * Pure: the functions read the tree and return what to do; the workspace does it.
 *
 * @module     local_dimensions/hubv2/keynav
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { FRAMEWORK_ID, competencyKey, moreKey, parentKey } from "./graph";
import { fill, fillCount, fillObject } from "./strings";
import { ROOT, ancestors, isExpanded, visibleItems } from "./tree";
const WALK_KEYS = ["ArrowUp", "ArrowDown", "ArrowLeft", "ArrowRight", "Home", "End", "Enter", " "];
const navOrder = /* @__PURE__ */ __name((tree) => [
  { key: FRAMEWORK_ID, parent: null, kind: "framework", id: ROOT, expandable: true, expanded: true },
  ...visibleItems(tree).map((item) => item.kind === "more" ? {
    key: moreKey(item.parent),
    parent: parentKey(item.parent),
    kind: "more",
    id: item.parent,
    expandable: false,
    expanded: false
  } : {
    key: competencyKey(item.id),
    parent: parentKey(item.parent),
    kind: "competency",
    id: item.id,
    expandable: !!tree.nodes[item.id]?.haschildren,
    expanded: isExpanded(tree, item.id)
  })
], "navOrder");
const modified = /* @__PURE__ */ __name((key) => !!(key.altKey || key.ctrlKey || key.metaKey || key.shiftKey), "modified");
const intoChildren = /* @__PURE__ */ __name((order, index) => {
  const item = order[index];
  if (!item.expandable) {
    return { type: "none" };
  }
  if (!item.expanded) {
    return { type: "expand", id: item.id };
  }
  const next = order[index + 1];
  return next && next.parent === item.key ? { type: "focus", key: next.key } : { type: "none" };
}, "intoChildren");
const towardsRoot = /* @__PURE__ */ __name((item) => {
  if (item.kind === "competency" && item.expandable && item.expanded) {
    return { type: "collapse", id: item.id };
  }
  return item.parent !== null ? { type: "focus", key: item.parent } : { type: "none" };
}, "towardsRoot");
const alongOrder = /* @__PURE__ */ __name((order, index, key) => {
  const targets = { ArrowDown: index + 1, ArrowUp: index - 1, Home: 0, End: order.length - 1 };
  if (!(key in targets)) {
    return null;
  }
  const target = order[targets[key]];
  return target ? { type: "focus", key: target.key } : { type: "none" };
}, "alongOrder");
const keyAction = /* @__PURE__ */ __name((order, current, key, rtl = false) => {
  if (modified(key) || order.length === 0) {
    return null;
  }
  const index = order.findIndex((item2) => item2.key === current);
  if (index < 0) {
    return WALK_KEYS.includes(key.key) ? { type: "focus", key: order[0].key } : null;
  }
  const item = order[index];
  if (key.key === (rtl ? "ArrowLeft" : "ArrowRight")) {
    return intoChildren(order, index);
  }
  if (key.key === (rtl ? "ArrowRight" : "ArrowLeft")) {
    return towardsRoot(item);
  }
  if (key.key === "Enter" || key.key === " ") {
    return item.kind === "more" ? { type: "loadmore", parent: item.id } : { type: "select", id: item.id };
  }
  return alongOrder(order, index, key.key);
}, "keyAction");
const isTypeAheadKey = /* @__PURE__ */ __name((key) => key.key.length === 1 && key.key !== " " && !key.altKey && !key.ctrlKey && !key.metaKey, "isTypeAheadKey");
const typeAhead = /* @__PURE__ */ __name((names, current, query) => {
  if (query === "" || names.length === 0) {
    return null;
  }
  const lower = query.toLocaleLowerCase();
  const repeated = [...lower].every((character) => character === lower[0]);
  const prefix = repeated ? lower[0] : lower;
  const index = names.findIndex((item) => item.key === current);
  const start = index < 0 ? 0 : index + (repeated ? 1 : 0);
  for (let step = 0; step < names.length; step++) {
    const item = names[(start + step) % names.length];
    if (item.name !== "" && item.name.toLocaleLowerCase().startsWith(prefix)) {
      return item.key;
    }
  }
  return null;
}, "typeAhead");
const entryKey = /* @__PURE__ */ __name((tree, order, selected) => {
  if (selected === null) {
    return FRAMEWORK_ID;
  }
  const keys = new Set(order.map((item) => item.key));
  const chain = [...ancestors(tree, selected), selected].reverse();
  return chain.map(competencyKey).find((key) => keys.has(key)) ?? FRAMEWORK_ID;
}, "entryKey");
const survivorKey = /* @__PURE__ */ __name((before, after, lost) => {
  const keys = new Set(after.map((item) => item.key));
  const parents = new Map(before.map((item) => [item.key, item.parent]));
  let key = lost;
  const seen = /* @__PURE__ */ new Set();
  while (key && !seen.has(key)) {
    if (keys.has(key)) {
      return key;
    }
    seen.add(key);
    key = parents.get(key);
  }
  return FRAMEWORK_ID;
}, "survivorKey");
const nodeName = /* @__PURE__ */ __name((labels, info) => {
  const parts = [
    info.name,
    fill(labels.hubv2_a11y_level, String(info.level)),
    fillObject(labels.hubv2_a11y_position, { position: String(info.position), total: String(info.total) })
  ];
  if (info.expandable) {
    parts.push(labels[info.expanded ? "hubv2_a11y_expanded" : "hubv2_a11y_collapsed"]);
    if (info.children !== null) {
      parts.push(fillCount(labels, "hubv2_children", info.children));
    }
  }
  if (info.rule) {
    parts.push(fill(labels.hubv2_a11y_rule, info.rule));
  }
  if (info.relatedto) {
    parts.push(fill(labels.hubv2_a11y_related, info.relatedto));
  }
  parts.push(info.picked ?? "", info.lens ?? "", info.findings ?? "");
  return parts.filter((part) => part !== "").join(", ");
}, "nodeName");
const overlaps = /* @__PURE__ */ __name((a, b) => a.x < b.x + b.width && b.x < a.x + a.width && a.y < b.y + b.height && b.y < a.y + a.height, "overlaps");
const panTarget = /* @__PURE__ */ __name((node, viewport, view, obstacles, margin = 16) => {
  const { zoom } = viewport;
  const width = node.width * zoom;
  const height = node.height * zoom;
  const clear = /* @__PURE__ */ __name((left2, top2) => {
    const box = { x: left2, y: top2, width, height };
    return left2 >= margin && top2 >= margin && left2 + width <= view.width - margin && top2 + height <= view.height - margin && !obstacles.some((obstacle) => overlaps(box, obstacle));
  }, "clear");
  if (clear(node.x * zoom + viewport.x, node.y * zoom + viewport.y)) {
    return null;
  }
  const middlex = (view.width - width) / 2;
  const middley = (view.height - height) / 2;
  const spots = [[middlex, middley], [middlex, margin], [margin, middley], [margin, margin]];
  const [left, top] = spots.find(([x, y]) => clear(x, y)) ?? [margin, margin];
  return { x: node.x + (view.width / 2 - left) / zoom, y: node.y + (view.height / 2 - top) / zoom };
}, "panTarget");
const shifted = /* @__PURE__ */ __name((rect, dx, dy) => ({ ...rect, x: rect.x + dx, y: rect.y + dy }), "shifted");
const union = /* @__PURE__ */ __name((a, b) => {
  const x = Math.min(a.x, b.x);
  const y = Math.min(a.y, b.y);
  return { x, y, width: Math.max(a.x + a.width, b.x + b.width) - x, height: Math.max(a.y + a.height, b.y + b.height) - y };
}, "union");
const fitAxis = /* @__PURE__ */ __name((start, size, low, high, keepend) => {
  if (size > high - low) {
    return keepend ? high - (start + size) : low - start;
  }
  if (start < low) {
    return low - start;
  }
  return start + size > high ? high - (start + size) : 0;
}, "fitAxis");
const branchTarget = /* @__PURE__ */ __name((parent, children, viewport, view, obstacles, rtl = false, margin = 16) => {
  const { zoom } = viewport;
  const screen = /* @__PURE__ */ __name((rect) => ({
    x: rect.x * zoom + viewport.x,
    y: rect.y * zoom + viewport.y,
    width: rect.width * zoom,
    height: rect.height * zoom
  }), "screen");
  const middle = /* @__PURE__ */ __name((rect) => rect.y + rect.height / 2, "middle");
  const top = screen(parent);
  const nearest = children.map(screen).sort((a, b) => Math.abs(middle(a) - middle(top)) - Math.abs(middle(b) - middle(top)));
  let box = top;
  nearest.forEach((child) => {
    const grown = union(box, child);
    if (grown.height <= view.height - 2 * margin) {
      box = grown;
    }
  });
  if (nearest.length > 0) {
    box = union(box, { ...nearest[0], y: box.y, height: box.height });
  }
  const clear = /* @__PURE__ */ __name((rect) => rect.x >= margin && rect.y >= margin && rect.x + rect.width <= view.width - margin && rect.y + rect.height <= view.height - margin && !obstacles.some((obstacle) => overlaps(rect, obstacle)), "clear");
  if (clear(box)) {
    return null;
  }
  let dx = fitAxis(box.x, box.width, margin, view.width - margin, rtl);
  let dy = fitAxis(box.y, box.height, margin, view.height - margin, false);
  obstacles.forEach((obstacle) => {
    const moved = shifted(box, dx, dy);
    if (!overlaps(moved, obstacle)) {
      return;
    }
    const up = obstacle.y - (moved.y + moved.height);
    if (moved.y + up >= margin) {
      dy += up;
      return;
    }
    const left = obstacle.x - (moved.x + moved.width);
    const right = obstacle.x + obstacle.width - moved.x;
    if (moved.x + left >= margin) {
      dx += left;
    } else if (moved.x + moved.width + right <= view.width - margin) {
      dx += right;
    }
  });
  if (dx === 0 && dy === 0) {
    return null;
  }
  return { x: (view.width / 2 - viewport.x - dx) / zoom, y: (view.height / 2 - viewport.y - dy) / zoom };
}, "branchTarget");
export {
  branchTarget,
  entryKey,
  isTypeAheadKey,
  keyAction,
  navOrder,
  nodeName,
  panTarget,
  survivorKey,
  typeAhead
};
//# sourceMappingURL=keynav.dev.js.map
