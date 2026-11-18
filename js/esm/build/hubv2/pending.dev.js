var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Holds on Moodle's pending-JavaScript counter across the canvas's asynchronous gaps.
 *
 * core/ajax holds the counter only while a request is in flight; a chain of requests separated by
 * a React render (children loaded, state changed, the layout recomputed, the node centred) leaves
 * a gap Behat would read as a settled page. Each gap is spanned by one `local_dimensions/hubv2:<what>`
 * token from start to the last state change.
 *
 * @module     local_dimensions/hubv2/pending
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Pending from "@moodle/lms/core/pending";
const track = /* @__PURE__ */ __name((what, work) => {
  const pending = new Pending(`local_dimensions/hubv2:${what}`);
  return work.finally(() => pending.resolve());
}, "track");
export {
  track
};
//# sourceMappingURL=pending.dev.js.map
