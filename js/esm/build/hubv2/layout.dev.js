var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * A left-to-right tidy tree: the root at the left, one column per depth.
 *
 * Every leaf takes the next row, in the order given, and every parent sits level with the middle
 * of its first and last child, so subtrees never overlap and siblings keep the server's order. A
 * gap after each top-level subtree that has children keeps the framework's areas apart. Pure: no
 * library and no DOM, so Jest pins it; positions are the top-left corners React Flow places nodes by.
 *
 * @module     local_dimensions/hubv2/layout
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const GRID = { columnWidth: 320, rowHeight: 100, groupGap: 40 };
const layoutTree = /* @__PURE__ */ __name((items, options = GRID) => {
  const keys = new Set(items.map((item) => item.key));
  const children = {};
  const roots = [];
  items.forEach((item) => {
    if (item.parent === null || !keys.has(item.parent)) {
      roots.push(item.key);
      return;
    }
    (children[item.parent] ??= []).push(item.key);
  });
  const positions = {};
  let cursor = 0;
  const place = /* @__PURE__ */ __name((key, depth) => {
    const kids = children[key] ?? [];
    if (kids.length === 0) {
      positions[key] = { x: depth * options.columnWidth, y: cursor };
      cursor += options.rowHeight;
      return;
    }
    kids.forEach((kid) => {
      place(kid, depth + 1);
      if (depth === 0 && (children[kid] ?? []).length > 0) {
        cursor += options.groupGap;
      }
    });
    const first = positions[kids[0]].y;
    const last = positions[kids[kids.length - 1]].y;
    positions[key] = { x: depth * options.columnWidth, y: (first + last) / 2 };
  }, "place");
  roots.forEach((root) => place(root, 0));
  return positions;
}, "layoutTree");
export {
  GRID,
  layoutTree
};
//# sourceMappingURL=layout.dev.js.map
