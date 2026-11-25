var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * What the bulk dock's three actions decide before they ask the server: which competencies an action acts on, in
 * which order, what the appearance dialogue sends, what the delete confirmation says, and the words of each outcome.
 *
 * A move and a delete act on the picked competencies none of whose ancestors is picked too (topmost): core moves and
 * deletes a competency with its descendants, so a descendant would move twice or be deleted with its ancestor. The
 * order is the tree's (depth first, the server's sort), so the same set always runs in the same order. The server
 * decides every write again; these decisions only keep the dock from asking what it knows core refuses.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/bulk
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { normalise } from "./contrast";
import { blockedReasons } from "./deletion";
import { fill, fillCount, fillObject } from "./strings";
import { ROOT, ancestors, isWithin } from "./tree";
const BULK_MAX = 200;
const PREVIEW_NAMES = 5;
const APPEARANCE_FIELDS = ["bgcolor", "textcolor", "tag1", "tag2", "type"];
const COLOUR_FIELDS = ["bgcolor", "textcolor"];
const appearancePayload = /* @__PURE__ */ __name((choices) => {
  const entries = [];
  for (const name of APPEARANCE_FIELDS) {
    const choice = choices[name];
    if (choice.mode === "clear") {
      entries.push({ name, action: "clear", value: "" });
    } else if (choice.mode === "set") {
      const value = COLOUR_FIELDS.includes(name) ? normalise(choice.value) : choice.value;
      if (value === "") {
        return null;
      }
      entries.push({ name, action: "set", value });
    }
  }
  return entries;
}, "appearancePayload");
const treeOrder = /* @__PURE__ */ __name((tree) => {
  const ids = [];
  const walk = /* @__PURE__ */ __name((parent) => {
    (tree.branches[parent]?.ids ?? []).forEach((id) => {
      if (tree.nodes[id]) {
        ids.push(id);
        walk(id);
      }
    });
  }, "walk");
  walk(ROOT);
  return ids;
}, "treeOrder");
const topmost = /* @__PURE__ */ __name((tree, ids) => {
  const picked = new Set(ids);
  return treeOrder(tree).filter((id) => picked.has(id) && !ancestors(tree, id).some((above) => picked.has(above)));
}, "topmost");
const targetAllowed = /* @__PURE__ */ __name((tree, subjects, target) => target === ROOT || !subjects.some((id) => isWithin(tree, target, id)), "targetAllowed");
const movePlan = /* @__PURE__ */ __name((tree, ids, target) => {
  const subjects = topmost(tree, ids);
  return {
    move: subjects.filter((id) => tree.nodes[id].parentid !== target),
    skipped: subjects.filter((id) => tree.nodes[id].parentid === target)
  };
}, "movePlan");
const preview = /* @__PURE__ */ __name((names) => ({ shown: names.slice(0, PREVIEW_NAMES), more: Math.max(0, names.length - PREVIEW_NAMES) }), "preview");
const deletionSummary = /* @__PURE__ */ __name((labels, checks) => {
  const summary = { allowed: [], descendants: 0, refused: [], parentrule: false };
  checks.forEach(({ id, name, check }) => {
    if (check && check.candelete) {
      summary.allowed.push(id);
      summary.descendants += check.descendants;
      summary.parentrule = summary.parentrule || check.parenthasrule;
    } else {
      summary.refused.push({ id, name, reasons: check ? blockedReasons(labels, check) : [] });
    }
  });
  return summary;
}, "deletionSummary");
const dockCount = /* @__PURE__ */ __name((labels, count, hidden) => {
  const picked = fillCount(labels, "hubv2_bulk_selected", count);
  return hidden > 0 ? `${picked} ${fillCount(labels, "hubv2_bulk_notshown", hidden)}` : picked;
}, "dockCount");
const appearanceOutcome = /* @__PURE__ */ __name((labels, changed) => changed === 0 ? labels.hubv2_bulk_appearance_none : fillCount(labels, "hubv2_bulk_appearance_done", changed), "appearanceOutcome");
const moveOutcome = /* @__PURE__ */ __name((labels, result) => {
  const parts = [fillCount(labels, "hubv2_bulk_move_done", result.moved)];
  if (result.skipped > 0) {
    parts.push(fillCount(labels, "hubv2_bulk_move_skipped", result.skipped));
  }
  if (result.refused) {
    parts.push(fillObject(labels.hubv2_bulk_move_refused, { name: result.refused.name, reason: result.refused.reason }));
    if (result.left > 0) {
      parts.push(fillCount(labels, "hubv2_bulk_move_left", result.left));
    }
  }
  return parts.join(" ");
}, "moveOutcome");
const deleteOutcome = /* @__PURE__ */ __name((labels, deleted, kept) => {
  const done = fillCount(labels, "hubv2_bulk_delete_done", deleted);
  return kept.length > 0 ? `${done} ${fill(labels.hubv2_bulk_delete_kept, kept.join(", "))}` : done;
}, "deleteOutcome");
export {
  APPEARANCE_FIELDS,
  BULK_MAX,
  COLOUR_FIELDS,
  PREVIEW_NAMES,
  appearanceOutcome,
  appearancePayload,
  deleteOutcome,
  deletionSummary,
  dockCount,
  moveOutcome,
  movePlan,
  preview,
  targetAllowed,
  topmost,
  treeOrder
};
//# sourceMappingURL=bulk.dev.js.map
