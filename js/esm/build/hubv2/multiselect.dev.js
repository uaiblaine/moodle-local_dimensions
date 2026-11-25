var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The workspace's multi-selection: the competencies a manager picked for the bulk dock, beside the one competency
 * the panel shows (selection.ts).
 *
 * A set of competency ids, never the framework and never a "load more" item, with an anchor: the last competency
 * toggled, from which Shift+Up and Shift+Down extend. `base` is the set as it stood when the anchor was set, so a
 * range drawn from the anchor replaces the previous range instead of piling ranges up (the APG multi-select
 * listbox's Shift+arrow). The set survives opening and closing branches (it names ids, not rows) and loses the ids
 * that leave the tree (prune): a deleted competency, or one forgotten after a move.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/multiselect
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const NONE = { ids: [], anchor: null, base: [] };
const drawnCompetencies = /* @__PURE__ */ __name((order) => order.filter((item) => item.kind === "competency").map((item) => item.id), "drawnCompetencies");
const toggle = /* @__PURE__ */ __name((picked, id) => {
  const ids = picked.ids.includes(id) ? picked.ids.filter((other) => other !== id) : [...picked.ids, id];
  return { ids, anchor: id, base: ids };
}, "toggle");
const extend = /* @__PURE__ */ __name((picked, order, from, to) => {
  const drawn = drawnCompetencies(order);
  const anchored = picked.anchor !== null && drawn.includes(picked.anchor);
  const anchor = anchored ? picked.anchor : from ?? to;
  const base = anchored ? picked.base : picked.ids;
  const start = drawn.indexOf(anchor);
  const end = drawn.indexOf(to);
  if (start < 0 || end < 0) {
    return picked;
  }
  const range = drawn.slice(Math.min(start, end), Math.max(start, end) + 1);
  const ids = [...base, ...range.filter((id) => !base.includes(id))];
  return { ids, anchor, base };
}, "extend");
const pickLevel = /* @__PURE__ */ __name((picked, order, key) => {
  const item = order.find((candidate) => candidate.key === key);
  if (!item || item.parent === null) {
    return picked;
  }
  const level = order.filter((candidate) => candidate.kind === "competency" && candidate.parent === item.parent).map((candidate) => candidate.id);
  const ids = [...picked.ids, ...level.filter((id) => !picked.ids.includes(id))];
  return { ids, anchor: picked.anchor, base: ids };
}, "pickLevel");
const prune = /* @__PURE__ */ __name((picked, tree) => {
  const kept = picked.ids.filter((id) => tree.nodes[id] !== void 0);
  if (kept.length === picked.ids.length) {
    return picked;
  }
  const anchor = picked.anchor !== null && tree.nodes[picked.anchor] ? picked.anchor : null;
  return { ids: kept, anchor, base: picked.base.filter((id) => tree.nodes[id] !== void 0) };
}, "prune");
const keepOnly = /* @__PURE__ */ __name((picked, ids) => {
  const kept = picked.ids.filter((id) => ids.includes(id));
  return { ids: kept, anchor: null, base: kept };
}, "keepOnly");
const pickGesture = /* @__PURE__ */ __name((key) => {
  const command = !!(key.ctrlKey || key.metaKey);
  if (key.altKey) {
    return null;
  }
  if (key.key === " " && !command && !key.shiftKey) {
    return "toggle";
  }
  if (key.shiftKey && !command && (key.key === "ArrowUp" || key.key === "ArrowDown")) {
    return key.key === "ArrowUp" ? "up" : "down";
  }
  return command && !key.shiftKey && key.key.toLowerCase() === "a" ? "level" : null;
}, "pickGesture");
export {
  NONE,
  drawnCompetencies,
  extend,
  keepOnly,
  pickGesture,
  pickLevel,
  prune,
  toggle
};
//# sourceMappingURL=multiselect.dev.js.map
