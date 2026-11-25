var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The checker's findings (idea I27): the order the panel lists them in and F8 walks, the codes present with
 * their counts, the codes checked and found nowhere, the words of each finding, and the one action of the
 * registry that fixes each kind.
 *
 * The findings come from the framework's insights (insights.ts); their words are the server's strings,
 * hubv2_check_<code> (the title) and hubv2_check_<code>_desc (a sentence whose {$a->name} placeholders the
 * finding's params fill). The codes are listed here by literal key: a code the client does not know yet is
 * listed last, titled by its code, with no sentence and no fix.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/findings
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { fillObject } from "./strings";
const CHECK_CODES = [
  "rule_cannot_fire",
  "rule_without_children",
  "rule_single_child",
  "unused",
  "idnumber_empty",
  "description_empty",
  "colour_contrast"
];
const TITLES = {
  "rule_cannot_fire": "hubv2_check_rule_cannot_fire",
  "rule_without_children": "hubv2_check_rule_without_children",
  "rule_single_child": "hubv2_check_rule_single_child",
  "unused": "hubv2_check_unused",
  "idnumber_empty": "hubv2_check_idnumber_empty",
  "description_empty": "hubv2_check_description_empty",
  "colour_contrast": "hubv2_check_colour_contrast"
};
const SENTENCES = {
  "rule_cannot_fire": "hubv2_check_rule_cannot_fire_desc",
  "rule_without_children": "hubv2_check_rule_without_children_desc",
  "rule_single_child": "hubv2_check_rule_single_child_desc",
  "unused": "hubv2_check_unused_desc",
  "idnumber_empty": "hubv2_check_idnumber_empty_desc",
  "description_empty": "hubv2_check_description_empty_desc",
  "colour_contrast": "hubv2_check_colour_contrast_desc"
};
const FIXES = {
  "rule_cannot_fire": "rule",
  "rule_without_children": "rule",
  "rule_single_child": "rule",
  "unused": "links",
  "idnumber_empty": "edit",
  "description_empty": "edit",
  "colour_contrast": "edit"
};
const isKnownCode = /* @__PURE__ */ __name((code) => CHECK_CODES.includes(code), "isKnownCode");
const rank = /* @__PURE__ */ __name((code) => isKnownCode(code) ? CHECK_CODES.indexOf(code) : CHECK_CODES.length, "rank");
const orderFindings = /* @__PURE__ */ __name((checks) => checks.map((check, index) => ({ check, index })).sort((x, y) => rank(x.check.code) - rank(y.check.code) || x.index - y.index).map(({ check }) => check), "orderFindings");
const findingGroups = /* @__PURE__ */ __name((checks) => {
  const counts = /* @__PURE__ */ new Map();
  orderFindings(checks).forEach((check) => counts.set(check.code, (counts.get(check.code) ?? 0) + 1));
  return [...counts.entries()].map(([code, count]) => ({ code, count }));
}, "findingGroups");
const absentCodes = /* @__PURE__ */ __name((checks, usagechecked) => {
  const present = new Set(checks.map((check) => check.code));
  return CHECK_CODES.filter((code) => !present.has(code) && (code !== "unused" || usagechecked));
}, "absentCodes");
const stepFinding = /* @__PURE__ */ __name((count, current, forward) => {
  if (count <= 0) {
    return null;
  }
  if (current === null || current < 0 || current >= count) {
    return forward ? 0 : count - 1;
  }
  return (current + (forward ? 1 : -1) + count) % count;
}, "stepFinding");
const fixFor = /* @__PURE__ */ __name((check, canmanage) => {
  if (!canmanage || check.competencyid <= 0 || !isKnownCode(check.code)) {
    return null;
  }
  return FIXES[check.code];
}, "fixFor");
const codeTitle = /* @__PURE__ */ __name((labels, code) => isKnownCode(code) ? labels[TITLES[code]] || code : code, "codeTitle");
const findingSentence = /* @__PURE__ */ __name((labels, check) => {
  if (!isKnownCode(check.code)) {
    return "";
  }
  const values = {};
  check.params.forEach((param) => {
    values[param.name] = param.value;
  });
  return fillObject(labels[SENTENCES[check.code]], values);
}, "findingSentence");
const findingCounts = /* @__PURE__ */ __name((checks) => {
  const counts = /* @__PURE__ */ new Map();
  checks.forEach((check) => counts.set(check.competencyid, (counts.get(check.competencyid) ?? 0) + 1));
  return counts;
}, "findingCounts");
export {
  CHECK_CODES,
  absentCodes,
  codeTitle,
  findingCounts,
  findingGroups,
  findingSentence,
  fixFor,
  isKnownCode,
  orderFindings,
  stepFinding
};
//# sourceMappingURL=findings.dev.js.map
