var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The canvas's deep link: the selected competency kept in the hub's address.
 *
 * The address is rewritten in place (history.replaceState: selecting never adds a history entry), and
 * only its competencyid parameter changes; the path, the other parameters and the fragment that names
 * the hub's open tab stay. What the link reveals on the next visit is decided by the server, which
 * reveals it only when the competency belongs to the framework the map shows. The list view's AMD
 * twin is amd/src/central/deeplink.js; keep the two in step.
 *
 * @module     local_dimensions/hubv2/deeplink
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const PARAM = "competencyid";
const withCompetency = /* @__PURE__ */ __name((href, id) => {
  const url = new URL(href);
  if (id !== null && Number.isInteger(id) && id > 0) {
    url.searchParams.set(PARAM, String(id));
  } else {
    url.searchParams.delete(PARAM);
  }
  return url.href;
}, "withCompetency");
const rememberSelection = /* @__PURE__ */ __name((id) => {
  const next = withCompetency(window.location.href, id);
  if (next !== window.location.href) {
    window.history.replaceState(window.history.state, "", next);
  }
}, "rememberSelection");
export {
  PARAM,
  rememberSelection,
  withCompetency
};
//# sourceMappingURL=deeplink.dev.js.map
