/**
 * The canvas's motion: every animated move of the view is instant for a viewer who asked the system
 * for reduced motion (WCAG 2.3.3).
 *
 * @module     local_dimensions/hubv2/motion
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const o="(prefers-reduced-motion: reduce)",t=()=>typeof window.matchMedia=="function"&&window.matchMedia(o).matches,n=e=>t()?0:e;export{o as REDUCED_MOTION,n as motionDuration,t as prefersReducedMotion};
