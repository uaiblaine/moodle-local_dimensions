var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Escaping for the few strings the canvas hands to an HTML sink of core's (a dialogue's title or
 * question), and the check an admin colour passes before it reaches a style.
 *
 * @module     local_dimensions/hubv2/html
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const escapeHtml = /* @__PURE__ */ __name((text) => text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#39;"), "escapeHtml");
const isHexColour = /* @__PURE__ */ __name((value) => /^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i.test(value), "isHexColour");
export {
  escapeHtml,
  isHexColour
};
//# sourceMappingURL=html.dev.js.map
