var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The only module of the canvas that talks to the server: the hub's own web services and core's
 * competency services, called with the arguments the hub's list view passes
 * (amd/src/central/structure.js).
 *
 * @module     local_dimensions/hubv2/repository
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { load } from "./amd";
const callMany = /* @__PURE__ */ __name((requests) => load("core/ajax").then((ajax) => Promise.all(ajax.call(requests))), "callMany");
const call = /* @__PURE__ */ __name((methodname, args) => callMany([{ methodname, args }]).then(([answer]) => answer), "call");
const browse = /* @__PURE__ */ __name((frameworkid, parentid, limitfrom, limitnum) => call("local_dimensions_browse_structure", { frameworkid, parentid, limitfrom, limitnum }), "browse");
const allChildren = /* @__PURE__ */ __name(async (frameworkid, parentid, limitnum) => {
  const children = [];
  for (; ; ) {
    const page = await browse(frameworkid, parentid, children.length, limitnum);
    page.items.forEach((item) => children.push({ id: item.id, shortname: item.shortname }));
    if (page.items.length === 0 || children.length >= page.total) {
      return children;
    }
  }
}, "allChildren");
const getNode = /* @__PURE__ */ __name((competencyid) => call("local_dimensions_get_structure_node", { competencyid }), "getNode");
const search = /* @__PURE__ */ __name((frameworkid, query) => call("local_dimensions_search_structure", { frameworkid, query, limitfrom: 0, limitnum: 25 }), "search");
const selectionData = /* @__PURE__ */ __name((competencyid) => callMany([
  { methodname: "local_dimensions_competency_usage", args: { competencyid } },
  { methodname: "local_dimensions_list_related_competencies", args: { competencyid } }
]).then(([usage, related]) => [usage, related.items]), "selectionData");
const deleteCheck = /* @__PURE__ */ __name((competencyid) => call("local_dimensions_competency_delete_check", { competencyid }), "deleteCheck");
const deleteCompetency = /* @__PURE__ */ __name((id) => call("core_competency_delete_competency", { id }), "deleteCompetency");
const moveOne = /* @__PURE__ */ __name((id, up) => call(up ? "core_competency_move_up_competency" : "core_competency_move_down_competency", { id }), "moveOne");
const setParent = /* @__PURE__ */ __name((competencyid, parentid) => call("core_competency_set_parent_competency", { competencyid, parentid }), "setParent");
const updateRule = /* @__PURE__ */ __name((id, config) => call("core_competency_update_competency", {
  competency: { id, ruletype: config.ruletype, ruleoutcome: config.ruleoutcome, ruleconfig: config.ruleconfig }
}), "updateRule");
const countCompetencies = /* @__PURE__ */ __name((frameworkid) => call("core_competency_count_competencies", {
  filters: [{ column: "competencyframeworkid", value: String(frameworkid) }]
}), "countCompetencies");
export {
  allChildren,
  browse,
  countCompetencies,
  deleteCheck,
  deleteCompetency,
  getNode,
  moveOne,
  search,
  selectionData,
  setParent,
  updateRule
};
//# sourceMappingURL=repository.dev.js.map
