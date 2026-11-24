var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The canvas's motion: every animated move of the view is instant for a viewer who asked the system
 * for reduced motion (WCAG 2.3.3).
 *
 * @module     local_dimensions/hubv2/motion
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const REDUCED_MOTION = "(prefers-reduced-motion: reduce)";
const prefersReducedMotion = /* @__PURE__ */ __name(() => typeof window.matchMedia === "function" && window.matchMedia(REDUCED_MOTION).matches, "prefersReducedMotion");
const motionDuration = /* @__PURE__ */ __name((milliseconds) => prefersReducedMotion() ? 0 : milliseconds, "motionDuration");
export {
  REDUCED_MOTION,
  motionDuration,
  prefersReducedMotion
};
//# sourceMappingURL=motion.dev.js.map
