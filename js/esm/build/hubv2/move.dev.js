var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The HTML of the move confirmation: where the competency goes, and which rules core resets on the way.
 *
 * core_competency\api::set_parent_competency() resets the rule of the competency's current parent
 * and of its new parent, whatever the rule was. The confirmation names each one that has a rule
 * today, as the delete confirmation names the parent's; with neither, it says nothing about rules.
 * (Core also resets the moved competency's own rule when it moves under one of its descendants,
 * which the canvas refuses before asking.)
 *
 * @module     local_dimensions/hubv2/move
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { escapeHtml } from "./html";
import { fill, fillObject } from "./strings";
const hasRule = /* @__PURE__ */ __name((parent) => !!parent && !!parent.ruletype, "hasRule");
const moveBody = /* @__PURE__ */ __name((labels, name, oldparent, newparent) => {
  const safe = escapeHtml(name);
  const question = newparent ? fillObject(labels.hubv2_moveconfirm, { name: safe, parent: escapeHtml(newparent.shortname) }) : fill(labels.hubv2_moveconfirmroot, safe);
  const notes = [
    hasRule(oldparent) ? fill(labels.hubv2_moverule_oldparent, escapeHtml(oldparent.shortname)) : "",
    hasRule(newparent) ? fill(labels.hubv2_moverule_newparent, escapeHtml(newparent.shortname)) : ""
  ].filter((note) => note !== "").map((note) => `<p>${note}</p>`).join("");
  return `<p>${question}</p>${notes}`;
}, "moveBody");
export {
  moveBody
};
//# sourceMappingURL=move.dev.js.map
