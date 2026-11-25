var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The impact preview (idea I28): which items a change marks on the map and in the outline while its dialogue
 * asks for the confirmation. The dialogue itself says the change and its count in words; the marks only show
 * where it lands.
 *
 * A delete touches the competency and every descendant the tree has loaded (core deletes the whole subtree;
 * the confirmation counts all of it, loaded or not). A move touches the old parent and the new one, ROOT for
 * the framework.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/impact
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { isWithin } from "./tree";
const NO_IMPACT = /* @__PURE__ */ new Set();
const deleteImpact = /* @__PURE__ */ __name((tree, id) => new Set(Object.keys(tree.nodes).map(Number).filter((other) => isWithin(tree, other, id))), "deleteImpact");
const moveImpact = /* @__PURE__ */ __name((oldparent, newparent) => /* @__PURE__ */ new Set([oldparent, newparent]), "moveImpact");
const sameImpact = /* @__PURE__ */ __name((a, b) => a.size === b.size && [...a].every((id) => b.has(id)), "sameImpact");
export {
  NO_IMPACT,
  deleteImpact,
  moveImpact,
  sameImpact
};
//# sourceMappingURL=impact.dev.js.map
