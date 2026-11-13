var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The HTML of the two deletion dialogues: why a competency cannot be deleted, and what deleting it destroys.
 *
 * @module     local_dimensions/hubv2/deletion
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { escapeHtml } from "./html";
import { fill, fillCount, fillObject } from "./strings";
const blockedBody = /* @__PURE__ */ __name((labels, name, check) => {
  const safe = escapeHtml(name);
  let intro = fill(labels.hubv2_delete_blocked_intro, safe);
  if (check.descendants > 0) {
    const key = check.descendants === 1 ? "hubv2_delete_blocked_intro_tree_one" : "hubv2_delete_blocked_intro_tree";
    intro = fillObject(labels[key], { name: safe, count: String(check.descendants) });
  }
  const reason = /* @__PURE__ */ __name((kind, count) => check.exact ? fillCount(labels, `hubv2_delete_blocked_${kind}`, count) : labels[`hubv2_delete_blocked_${kind}_some`], "reason");
  const reasons = [
    check.templates > 0 ? reason("templates", check.templates) : "",
    check.plans > 0 ? reason("plans", check.plans) : "",
    check.courses > 0 ? reason("courses", check.courses) : "",
    check.users > 0 ? reason("users", check.users) : "",
    check.badges ? labels.hubv2_delete_blocked_badges : ""
  ].filter((reason2) => reason2 !== "");
  const list = reasons.length > 0 ? `<ul>${reasons.map((reason2) => `<li>${reason2}</li>`).join("")}</ul>` : "";
  const links = check.templates > 0 || check.plans > 0 || check.courses > 0 || check.badges;
  const advice = [
    links ? labels.hubv2_delete_blocked_closing_links : "",
    check.users > 0 ? labels.hubv2_delete_blocked_closing_users : ""
  ].filter((line) => line !== "").map((line) => `<p>${line}</p>`).join("");
  return `<p>${intro}</p>${list}${advice}`;
}, "blockedBody");
const confirmBody = /* @__PURE__ */ __name((labels, name, check) => {
  const safe = escapeHtml(name);
  if (check.descendants === 0) {
    return `<p>${fill(labels.hubv2_delete_confirm_leaf, safe)}</p>`;
  }
  const text = check.descendants === 1 ? fillObject(labels.hubv2_delete_confirm_tree_one, { name: safe }) : fillObject(labels.hubv2_delete_confirm_tree, {
    name: safe,
    count: String(check.descendants),
    total: String(check.descendants + 1)
  });
  const rule = check.parenthasrule ? `<p>${labels.hubv2_delete_parentrule}</p>` : "";
  return `<p>${text}</p>${rule}`;
}, "confirmBody");
export {
  blockedBody,
  confirmBody
};
//# sourceMappingURL=deletion.dev.js.map
