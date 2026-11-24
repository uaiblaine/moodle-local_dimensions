var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The ID number the outline's inline editor offers for a new competency.
 *
 * Core refuses a competency without an ID number or with one its framework already has, so typing a
 * list in the outline needs one per line. When the siblings' ID numbers share a stem and end in a
 * number ("DCE-1.1", "DCE-1.2"; "C01", "C02"; "7"), the next number after the highest is offered, at
 * the same width when the numbers are zero-padded. When the siblings disagree on the stem, or one does
 * not end in a number, nothing is offered: a guess there would be wrong more often than right. The
 * offer is only a value in the field: core decides whether it is unique.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/idnumber
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const split = /* @__PURE__ */ __name((idnumber) => {
  const match = /^(.*?)(\d+)$/.exec(idnumber.trim());
  return match ? { stem: match[1], digits: match[2] } : null;
}, "split");
const suggestIdnumber = /* @__PURE__ */ __name((siblings, taken = []) => {
  const parts = siblings.filter((idnumber) => idnumber.trim() !== "").map(split);
  if (parts.length === 0 || parts.length !== siblings.length || parts.some((part) => part === null)) {
    return "";
  }
  const splits = parts;
  const stem = splits[0].stem;
  if (splits.some((part) => part.stem !== stem)) {
    return "";
  }
  const highest = splits.reduce((best, part) => Number(part.digits) > Number(best.digits) ? part : best, splits[0]);
  const width = highest.digits.startsWith("0") ? highest.digits.length : 0;
  const known = new Set([...siblings, ...taken].map((idnumber) => idnumber.trim()));
  let next = Number(highest.digits) + 1;
  let offer = stem + String(next).padStart(width, "0");
  for (let guard = 0; known.has(offer) && guard <= known.size; guard++) {
    next++;
    offer = stem + String(next).padStart(width, "0");
  }
  return offer;
}, "suggestIdnumber");
export {
  suggestIdnumber
};
//# sourceMappingURL=idnumber.dev.js.map
