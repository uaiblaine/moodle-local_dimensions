var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The map's column headings: the framework's taxonomy term of each level, above its column.
 *
 * The map lays one column per depth (layout.ts), the framework in column 0 and a competency of level N (its depth,
 * 1 for a top-level one) in column N; a language written right to left mirrors the columns. Each level is named
 * as core names it (competency_framework::get_taxonomy()): the term the framework gives that level, else core's
 * fallback. The outline's "Show taxonomy" meta reads the same function, so both panes say the same word.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/columns
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { GRID } from "./layout";
import { fill } from "./strings";
import { visibleItems } from "./tree";
const levelTerm = /* @__PURE__ */ __name((terms, fallback, level) => level >= 1 && terms[level - 1] ? terms[level - 1] : fallback, "levelTerm");
const headWords = /* @__PURE__ */ __name((levellabel, head) => `${fill(levellabel, String(head.level))}: ${head.term}`, "headWords");
const deepestLevel = /* @__PURE__ */ __name((tree) => visibleItems(tree).reduce((deepest, item) => item.kind === "competency" ? Math.max(deepest, item.depth) : deepest, 0), "deepestLevel");
const columnHeads = /* @__PURE__ */ __name((levels, terms, fallback, rtl, columnwidth = GRID.columnWidth) => {
  const heads = [];
  for (let level = 1; level <= levels; level++) {
    const x = rtl ? 0 - level * columnwidth : level * columnwidth;
    heads.push({ level, x, term: levelTerm(terms, fallback, level) });
  }
  return heads.sort((one, other) => one.x - other.x);
}, "columnHeads");
export {
  columnHeads,
  deepestLevel,
  headWords,
  levelTerm
};
//# sourceMappingURL=columns.dev.js.map
