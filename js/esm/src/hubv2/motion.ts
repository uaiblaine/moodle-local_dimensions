// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * The canvas's motion: every animated move of the view is instant for a viewer who asked the system
 * for reduced motion (WCAG 2.3.3).
 *
 * @module     local_dimensions/hubv2/motion
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** The media query a viewer who wants less motion matches. */
export const REDUCED_MOTION = '(prefers-reduced-motion: reduce)';

/**
 * Whether the viewer asked for reduced motion, read when the move happens so a change applies at once.
 *
 * @returns {boolean} True when the system asks for reduced motion.
 */
export const prefersReducedMotion = (): boolean =>
    typeof window.matchMedia === 'function' && window.matchMedia(REDUCED_MOTION).matches;

/**
 * The duration of a move of the view: as asked, or none under reduced motion.
 *
 * @param {number} milliseconds The duration the move would take.
 * @returns {number} That duration, or 0.
 */
export const motionDuration = (milliseconds: number): number => (prefersReducedMotion() ? 0 : milliseconds);
