var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The framework's insights as the workspace keeps them: one answer of local_dimensions_get_framework_insights
 * per framework, indexed by competency for the lenses (lenses.ts) and the checker (findings.ts).
 *
 * The workspace asks once shortly after it mounts (so the checker's count can show), whenever a lens or the
 * checker is first asked for, after every action of the registry that changes what the answer says
 * (commands.ts `changes`), and on "Check again". Only the newest request's answer is kept (latest()): an
 * answer that arrives after a later request was made is dropped, whatever order the server answers in.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/insights
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const LENSES = ["none", "usage", "rules", "relations"];
const isLens = /* @__PURE__ */ __name((value) => LENSES.includes(value), "isLens");
const MEASURES = ["courses", "modules", "templates", "plans"];
const indexInsights = /* @__PURE__ */ __name((answer) => {
  const usage = /* @__PURE__ */ new Map();
  answer.usage.forEach((row) => usage.set(row.competencyid, row));
  const rules = /* @__PURE__ */ new Map();
  const ruled = /* @__PURE__ */ new Map();
  answer.rules.forEach((rule) => {
    rules.set(rule.competencyid, rule);
    rule.children.forEach((child) => ruled.set(child.id, {
      parent: rule.competencyid,
      points: child.points,
      required: child.required
    }));
  });
  const relations = /* @__PURE__ */ new Map();
  answer.relations.forEach((row) => relations.set(row.competencyid, row.count));
  const checks = /* @__PURE__ */ new Map();
  answer.checks.forEach((check) => {
    checks.set(check.competencyid, [...checks.get(check.competencyid) ?? [], check]);
  });
  return { answer, usage, rules, ruled, relations, checks };
}, "indexInsights");
const usageOf = /* @__PURE__ */ __name((index, id, measure) => index.usage.get(id)?.[measure] ?? 0, "usageOf");
const latest = /* @__PURE__ */ __name(() => {
  let last = 0;
  return {
    /**
     * Start a request.
     *
     * @returns {number} Its ticket.
     */
    begin: /* @__PURE__ */ __name(() => ++last, "begin"),
    /**
     * Whether a ticket is the newest request's.
     *
     * @param {number} ticket The ticket.
     * @returns {boolean} Whether it is.
     */
    current: /* @__PURE__ */ __name((ticket) => ticket === last, "current"),
    /**
     * Drop every request made so far.
     *
     * @returns {void}
     */
    drop: /* @__PURE__ */ __name(() => {
      last++;
    }, "drop")
  };
}, "latest");
const IDLE = { status: "idle", index: null };
const lensOf = /* @__PURE__ */ __name((value) => isLens(value) ? value : "none", "lensOf");
const insightFacts = /* @__PURE__ */ __name((index) => index ? { findings: index.answer.checks.length, summary: index.answer.summary, checks: index.answer.checks } : { findings: null, summary: null, checks: [] }, "insightFacts");
export {
  IDLE,
  LENSES,
  MEASURES,
  indexInsights,
  insightFacts,
  isLens,
  latest,
  lensOf,
  usageOf
};
//# sourceMappingURL=insights.dev.js.map
