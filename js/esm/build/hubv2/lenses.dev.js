var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The lenses: one question at a time over the same tree (Usage, Rules, Relations), each answered from the
 * framework's insights (insights.ts) in three forms that say the same thing.
 *
 * On the map a card takes a mark that differs in shape or pattern, never in hue alone (a dashed edge, an icon,
 * a line of words under the name); in the outline a row takes a quiet counter or a small mark at its end; and
 * the panel lists the legend with its counts and the competencies in words. Every mark's words are in the
 * card's and the row's accessible name too (I26: a lens never speaks by colour alone).
 *
 * Usage: a competency's own use by the measure chosen, never its children's sum, in four buckets (0; 1 to 2;
 * 3 to 5; 6 or more) under exact counts, or used and unused under presence counts. Rules: each rule on its
 * parent, the points it asks against those its children give, the children it counts with their points and
 * "required", and whether it can ever fire. Relations: every related pair of the framework; a pair whose two
 * ends are drawn is a dotted edge, one with a single end drawn a count on that end.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/lenses
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { usageOf } from "./insights";
import { fill, fillCount, fillObject } from "./strings";
import { visibleItems } from "./tree";
const EXACT_BUCKETS = ["unused", "low", "mid", "high"];
const PRESENCE_BUCKETS = ["unused", "used"];
const BUCKET_CLASSES = {
  unused: "local-dimensions-hubv2-lens-unused",
  low: "local-dimensions-hubv2-lens-low",
  mid: "local-dimensions-hubv2-lens-mid",
  high: "local-dimensions-hubv2-lens-high",
  used: "local-dimensions-hubv2-lens-used"
};
const BUCKET_LABELS = {
  unused: "hubv2_lens_bucket_unused",
  low: "hubv2_lens_bucket_low",
  mid: "hubv2_lens_bucket_mid",
  high: "hubv2_lens_bucket_high",
  used: "hubv2_lens_bucket_used"
};
const COUNT_KEYS = {
  courses: "hubv2_lens_count_courses",
  modules: "hubv2_lens_count_modules",
  templates: "hubv2_lens_count_templates",
  plans: "hubv2_lens_count_plans"
};
const MEASURE_LABELS = {
  courses: "hubv2_lens_measure_courses",
  modules: "hubv2_lens_measure_modules",
  templates: "hubv2_lens_measure_templates",
  plans: "hubv2_lens_measure_plans"
};
const LENS_LABELS = {
  none: "hubv2_lens_none",
  usage: "hubv2_lens_usage",
  rules: "hubv2_lens_rules",
  relations: "hubv2_lens_relations"
};
const MARK_CLASSES = {
  ruled: "local-dimensions-hubv2-lens-ruled",
  cannotfire: "local-dimensions-hubv2-lens-cannotfire",
  counted: "local-dimensions-hubv2-lens-counted",
  related: "local-dimensions-hubv2-lens-related",
  quiet: "local-dimensions-hubv2-lens-quiet"
};
const usageBucket = /* @__PURE__ */ __name((count, level) => {
  if (count <= 0) {
    return "unused";
  }
  if (level !== "exact") {
    return "used";
  }
  if (count <= 2) {
    return "low";
  }
  return count <= 5 ? "mid" : "high";
}, "usageBucket");
const usageLegend = /* @__PURE__ */ __name((index, measure) => {
  const level = index.answer.usagelevel;
  if (level === "none") {
    return [];
  }
  const buckets = level === "exact" ? EXACT_BUCKETS : PRESENCE_BUCKETS;
  const counts = new Map(buckets.map((bucket) => [bucket, 0]));
  let used = 0;
  index.answer.usage.forEach((row) => {
    const bucket = usageBucket(row[measure], level);
    if (bucket !== "unused") {
      used++;
      counts.set(bucket, (counts.get(bucket) ?? 0) + 1);
    }
  });
  counts.set("unused", Math.max(0, index.answer.summary.competencies - used));
  return buckets.map((bucket) => ({ bucket, count: counts.get(bucket) ?? 0 }));
}, "usageLegend");
const unusedIds = /* @__PURE__ */ __name((index, measure, loaded) => {
  const ids = /* @__PURE__ */ new Set();
  index.answer.usage.forEach((row) => {
    if (row[measure] <= 0) {
      ids.add(row.competencyid);
    }
  });
  index.answer.checks.forEach((check) => {
    if (check.code === "unused" && check.competencyid > 0) {
      ids.add(check.competencyid);
    }
  });
  loaded.forEach((id) => {
    if (!index.usage.has(id)) {
      ids.add(id);
    }
  });
  const total = usageLegend(index, measure).find((line) => line.bucket === "unused")?.count ?? 0;
  return { ids: [...ids].sort((a, b) => a - b), complete: ids.size >= total };
}, "unusedIds");
const ruleLine = /* @__PURE__ */ __name((labels, rule) => rule.type === "points" ? fillObject(labels.hubv2_lens_rule_points, { required: String(rule.required), available: String(rule.available) }) : rule.label, "ruleLine");
const childLine = /* @__PURE__ */ __name((labels, entry) => {
  const parts = [fillCount(labels, "hubv2_lens_rule_childpoints", entry.points)];
  if (entry.required) {
    parts.push(labels.hubv2_lens_rule_required);
  }
  return parts.join(" \xB7 ");
}, "childLine");
const requiredChildren = /* @__PURE__ */ __name((rule) => rule.children.filter((child) => child.required).map((child) => child.id), "requiredChildren");
const countsChild = /* @__PURE__ */ __name((index, parent, child) => {
  const rule = index.rules.get(parent);
  if (!rule) {
    return false;
  }
  if (rule.type !== "points") {
    return true;
  }
  return rule.children.some((entry) => entry.id === child && (entry.points > 0 || entry.required));
}, "countsChild");
const rulesLegend = /* @__PURE__ */ __name((index) => {
  const cannotfire = index.answer.rules.filter((rule) => !rule.canfire).length;
  return {
    fire: index.answer.rules.length - cannotfire,
    cannotfire,
    without: Math.max(0, index.answer.summary.competencies - index.answer.rules.length)
  };
}, "rulesLegend");
const pairsOnMap = /* @__PURE__ */ __name((pairs, drawn) => {
  const edges = [];
  const elsewhere = /* @__PURE__ */ new Map();
  pairs.forEach(({ a, b }) => {
    const hasa = drawn.has(a);
    const hasb = drawn.has(b);
    if (hasa && hasb) {
      edges.push([a, b]);
    } else if (hasa || hasb) {
      const end = hasa ? a : b;
      elsewhere.set(end, (elsewhere.get(end) ?? 0) + 1);
    }
  });
  return { edges, elsewhere };
}, "pairsOnMap");
const relationsLegend = /* @__PURE__ */ __name((index) => {
  const related = [...index.relations.values()].filter((count) => count > 0).length;
  return { related, unrelated: Math.max(0, index.answer.summary.competencies - related) };
}, "relationsLegend");
const drawnSet = /* @__PURE__ */ __name((tree) => new Set(visibleItems(tree).flatMap((item) => item.kind === "competency" ? [item.id] : [])), "drawnSet");
const QUIET = { className: MARK_CLASSES.quiet, line: "", said: "", meta: null, icon: "", elsewhere: 0 };
const usageMark = /* @__PURE__ */ __name((id, context) => {
  const { labels, index, measure } = context;
  const level = index.answer.usagelevel;
  if (level === "none") {
    return null;
  }
  const count = usageOf(index, id, measure);
  const bucket = usageBucket(count, level);
  if (bucket === "unused") {
    return {
      className: BUCKET_CLASSES.unused,
      line: labels.hubv2_lens_bucket_unused,
      said: labels.hubv2_lens_unused_said,
      meta: { text: "", icon: "", dashed: true },
      icon: "",
      elsewhere: 0
    };
  }
  if (bucket === "used") {
    return {
      className: BUCKET_CLASSES.used,
      line: labels.hubv2_lens_bucket_used,
      said: labels.hubv2_lens_used_said,
      meta: null,
      icon: "",
      elsewhere: 0
    };
  }
  const words = fillCount(labels, COUNT_KEYS[measure], count);
  return {
    className: BUCKET_CLASSES[bucket],
    line: words,
    said: words,
    meta: { text: String(count), icon: "", dashed: false },
    icon: "",
    elsewhere: 0
  };
}, "usageMark");
const rulesMark = /* @__PURE__ */ __name((id, context) => {
  const { labels, index, nameOf } = context;
  const rule = index.rules.get(id);
  const entry = index.ruled.get(id);
  if (!rule && !entry) {
    return QUIET;
  }
  const lines = [];
  const said = [];
  let className = MARK_CLASSES.counted;
  let icon = "";
  let meta = null;
  if (rule) {
    const line = ruleLine(labels, rule);
    lines.push(line);
    if (rule.type === "points") {
      said.push(line);
    }
    className = MARK_CLASSES.ruled;
    icon = "fa-diagram-project";
    if (!rule.canfire) {
      lines.push(labels.hubv2_lens_rule_cannotfire);
      said.push(labels.hubv2_lens_rule_cannotfire);
      className = MARK_CLASSES.cannotfire;
      icon = "fa-triangle-exclamation";
    }
    meta = { text: rule.type === "points" ? line : "", icon, dashed: false };
  }
  if (entry) {
    const line = childLine(labels, entry);
    lines.push(line);
    said.push(fillObject(labels.hubv2_lens_rule_childsaid, { points: line, name: nameOf(entry.parent) }));
    if (!meta) {
      meta = { text: line, icon: "", dashed: false };
    }
  }
  return { className, line: lines.join(" \xB7 "), said: said.join(", "), meta, icon, elsewhere: 0 };
}, "rulesMark");
const relationsMark = /* @__PURE__ */ __name((id, context) => {
  const { labels, index, elsewhere } = context;
  const count = index.relations.get(id) ?? 0;
  if (count <= 0) {
    return QUIET;
  }
  const away = elsewhere.get(id) ?? 0;
  const words = fillCount(labels, "hubv2_outline_related", count);
  const said = [words];
  if (away > 0) {
    said.push(fillCount(labels, "hubv2_lens_elsewhere_said", away));
  }
  return {
    className: MARK_CLASSES.related,
    line: words,
    said: said.join(", "),
    meta: { text: String(count), icon: "fa-share-nodes", dashed: false },
    icon: "fa-share-nodes",
    elsewhere: away
  };
}, "relationsMark");
const itemMark = /* @__PURE__ */ __name((lens, id, context) => {
  switch (lens) {
    case "usage":
      return usageMark(id, context);
    case "rules":
      return rulesMark(id, context);
    case "relations":
      return relationsMark(id, context);
    default:
      return null;
  }
}, "itemMark");
const lensMarks = /* @__PURE__ */ __name((lens, ids, context) => {
  const marks = /* @__PURE__ */ new Map();
  if (lens === "none") {
    return marks;
  }
  ids.forEach((id) => {
    const mark = itemMark(lens, id, context);
    if (mark) {
      marks.set(id, mark);
    }
  });
  return marks;
}, "lensMarks");
const outcomeLabel = /* @__PURE__ */ __name((labels, outcome) => {
  switch (outcome) {
    case "evidence":
      return labels["tool_lp:competencyoutcome_evidence"];
    case "complete":
      return labels["tool_lp:competencyoutcome_complete"];
    case "recommend":
      return labels["tool_lp:competencyoutcome_recommend"];
    default:
      return labels["tool_lp:competencyoutcome_none"];
  }
}, "outcomeLabel");
const ruleFacts = /* @__PURE__ */ __name((labels, rule) => {
  const points = rule.type === "points";
  return {
    id: rule.competencyid,
    type: rule.label,
    asks: points ? fillCount(labels, "hubv2_lens_rule_childpoints", rule.required) : labels.hubv2_lens_rule_asks_all,
    available: points ? fillCount(labels, "hubv2_lens_rule_childpoints", rule.available) : "",
    required: requiredChildren(rule),
    outcome: outcomeLabel(labels, rule.outcome),
    canfire: rule.canfire
  };
}, "ruleFacts");
const usageNotice = /* @__PURE__ */ __name((labels, level) => {
  if (level === "none") {
    return labels.hubv2_lens_usage_none;
  }
  return level === "presence" ? labels.hubv2_lens_usage_presence : "";
}, "usageNotice");
const pairWords = /* @__PURE__ */ __name((labels, a, b) => fillObject(labels.hubv2_lens_pair, { a, b }), "pairWords");
const elsewhereText = /* @__PURE__ */ __name((labels, count) => fill(labels.hubv2_lens_elsewhere, String(count)), "elsewhereText");
const markWords = /* @__PURE__ */ __name((mark, findings, findingwords) => [mark ? mark.said : "", findings > 0 ? findingwords(findings) : ""].filter((part) => part !== ""), "markWords");
export {
  BUCKET_CLASSES,
  BUCKET_LABELS,
  COUNT_KEYS,
  EXACT_BUCKETS,
  LENS_LABELS,
  MARK_CLASSES,
  MEASURE_LABELS,
  PRESENCE_BUCKETS,
  childLine,
  countsChild,
  drawnSet,
  elsewhereText,
  itemMark,
  lensMarks,
  markWords,
  outcomeLabel,
  pairWords,
  pairsOnMap,
  relationsLegend,
  requiredChildren,
  ruleFacts,
  ruleLine,
  rulesLegend,
  unusedIds,
  usageBucket,
  usageLegend,
  usageNotice
};
//# sourceMappingURL=lenses.dev.js.map
