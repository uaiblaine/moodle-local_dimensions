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
 * Shared "flash" confirmation cue for the Competency hub: briefly highlight an element's
 * background so an in-place change (a row added, edited or moved without a full reload) is
 * visible where the user is looking. The single source for what were ten inline copies across
 * six hub modules.
 *
 * @module     local_dimensions/central/flash
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Briefly flash an element's background. A no-op when the element cannot animate, and skipped
 * entirely when the user has requested reduced motion (the flash is a redundant confirmation cue).
 *
 * @param {HTMLElement} el The element to flash.
 * @return {void}
 */
export const flashRow = (el) => {
    if (!el || typeof el.animate !== 'function') {
        return;
    }
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }
    // Read the duration from the --mds-motion-flash token (styles.css :root, inherited here) so the
    // stylesheet stays the single source; fall back to 1500ms if the token is not set.
    const duration = parseInt(getComputedStyle(el).getPropertyValue('--mds-motion-flash'), 10) || 1500;
    el.animate(
        [{backgroundColor: '#fff3cd'}, {backgroundColor: 'transparent'}],
        {duration, easing: 'ease-out'}
    );
};
