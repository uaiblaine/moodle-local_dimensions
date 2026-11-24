var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The outline's rows: one per stop of the keyboard walk (keynav.navOrder), with what the APG Tree View
 * pattern needs on each when the whole set is not in the DOM.
 *
 * The outline is a flat list of treeitems, each carrying aria-level, aria-setsize and aria-posinset,
 * rather than nested groups: the tree is lazy, so a parent's set is never whole in the DOM and the
 * three attributes are needed on every item anyway, and a flat list keeps the rows in exactly the
 * walk's order, one to one, which is what the shared key map moves through. The framework is the
 * root item at level 1; a competency's level is its depth plus one; a parent's set size is its total
 * from the server, not what is loaded. A "load more" item stands at the level of the children it
 * loads, after them, at the position the next child will take.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/outlinerows
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { FRAMEWORK_ID, competencyKey, moreKey, parentKey } from "./graph";
import { ROOT, isExpanded, visibleItems } from "./tree";
const outlineRows = /* @__PURE__ */ __name((tree, frameworkname) => {
  const rows = [{
    key: FRAMEWORK_ID,
    kind: "framework",
    id: ROOT,
    parent: null,
    level: 1,
    setsize: 1,
    posinset: 1,
    expandable: true,
    expanded: true,
    name: frameworkname,
    childcount: tree.branches[ROOT]?.total ?? 0,
    remaining: 0,
    node: null
  }];
  visibleItems(tree).forEach((item) => {
    const siblings = tree.branches[item.parent];
    const total = siblings ? siblings.total : 1;
    if (item.kind === "more") {
      rows.push({
        key: moreKey(item.parent),
        kind: "more",
        id: item.parent,
        parent: parentKey(item.parent),
        level: item.depth + 1,
        setsize: total,
        posinset: (siblings ? siblings.ids.length : 0) + 1,
        expandable: false,
        expanded: false,
        name: "",
        childcount: null,
        remaining: item.remaining,
        node: null
      });
      return;
    }
    const node = tree.nodes[item.id];
    const branch = tree.branches[item.id];
    rows.push({
      key: competencyKey(item.id),
      kind: "competency",
      id: item.id,
      parent: parentKey(item.parent),
      level: item.depth + 1,
      setsize: total,
      posinset: (siblings ? siblings.ids.indexOf(item.id) : 0) + 1,
      expandable: node.haschildren,
      expanded: node.haschildren && isExpanded(tree, item.id),
      name: node.shortname,
      childcount: branch ? branch.total : null,
      remaining: 0,
      node
    });
  });
  return rows;
}, "outlineRows");
const rowMeta = /* @__PURE__ */ __name((row, competencies, related) => {
  if (row.kind === "more") {
    return [];
  }
  if (row.kind === "framework") {
    return [{ kind: "count", value: competencies }];
  }
  const parts = [];
  if (row.childcount !== null) {
    parts.push({ kind: "count", value: row.childcount });
  }
  if (row.node?.ruletype) {
    parts.push({ kind: "rule", label: row.node.rulelabel });
  }
  if (related !== null && related > 0) {
    parts.push({ kind: "related", value: related });
  }
  return parts;
}, "rowMeta");
const scrollToShow = /* @__PURE__ */ __name((top, height, scrolltop, viewheight) => {
  if (top < scrolltop) {
    return top;
  }
  if (top + height > scrolltop + viewheight) {
    return Math.max(0, top + height - viewheight);
  }
  return null;
}, "scrollToShow");
const editorSlot = /* @__PURE__ */ __name((rows, parentkey, afterkey) => {
  const anchor = afterkey ?? parentkey;
  const index = rows.findIndex((row) => row.key === anchor);
  if (index < 0) {
    return null;
  }
  if (afterkey === null) {
    return anchor;
  }
  let last = index;
  while (last + 1 < rows.length && rows[last + 1].level > rows[index].level) {
    last++;
  }
  return rows[last].key;
}, "editorSlot");
export {
  editorSlot,
  outlineRows,
  rowMeta,
  scrollToShow
};
//# sourceMappingURL=outlinerows.dev.js.map
