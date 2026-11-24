var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The framework's summary, on request: what the client can say truthfully about the tree it holds.
 *
 * The total comes from the server (core's count of the framework's competencies). Everything else is
 * counted over the competencies the workspace has loaded, and is said to be so while that is not the
 * whole framework: the tree is lazy, and a count over a part read as the whole would be false. No
 * relation is counted: the tree's nodes do not carry them (only the selected competency's are read). Once the
 * framework's insights are read (insights.ts), the summary says the server's numbers instead.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/summary
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { ancestors } from "./tree";
const summaryFacts = /* @__PURE__ */ __name((tree, total) => {
  const ids = Object.keys(tree.nodes).map(Number);
  const levels = ids.reduce((deepest, id) => Math.max(deepest, ancestors(tree, id).length + 1), 0);
  const rules = ids.filter((id) => !!tree.nodes[id].ruletype).length;
  return {
    total,
    loaded: ids.length,
    complete: ids.length >= total,
    levels,
    rules,
    opened: tree.expanded.filter((id) => tree.nodes[id] !== void 0).length
  };
}, "summaryFacts");
const summaryLines = /* @__PURE__ */ __name((labels, facts, server = null) => {
  const lines = [{ label: labels.hubv2_summary_total, value: String(facts.total) }];
  if (server) {
    lines.push(
      { label: labels.hubv2_summary_levels, value: String(server.maxdepth) },
      { label: labels.hubv2_summary_rules, value: String(server.withrule) },
      { label: labels.hubv2_summary_relations, value: String(server.relations) },
      { label: labels.hubv2_summary_findings, value: String(server.findings) },
      { label: labels.hubv2_summary_opened, value: String(facts.opened) }
    );
    return lines;
  }
  if (!facts.complete) {
    lines.push({ label: labels.hubv2_summary_loaded, value: String(facts.loaded) });
  }
  lines.push(
    {
      label: facts.complete ? labels.hubv2_summary_levels : labels.hubv2_summary_levels_loaded,
      value: String(facts.levels)
    },
    {
      label: facts.complete ? labels.hubv2_summary_rules : labels.hubv2_summary_rules_loaded,
      value: String(facts.rules)
    },
    { label: labels.hubv2_summary_opened, value: String(facts.opened) }
  );
  return lines;
}, "summaryLines");
export {
  summaryFacts,
  summaryLines
};
//# sourceMappingURL=summary.dev.js.map
