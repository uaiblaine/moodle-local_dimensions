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
 * Draggable divider between the two panes of a Competency hub master-detail layout.
 *
 * The layout is a CSS grid whose detail column reads its width from a custom
 * property; this module only maintains that property. The chosen width is
 * persisted in localStorage and reapplied on each init, so it survives pane
 * reloads. Supports pointer drag, dblclick reset and ArrowLeft/ArrowRight
 * keyboard resizing. Used by the Structure and Learning plans tabs.
 *
 * @module     local_dimensions/central/pane_resizer
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Wire a resizer between a master list and a detail pane.
 *
 * @param {Object} options
 * @param {HTMLElement} options.body Grid container of the two panes and the divider.
 * @param {HTMLElement} options.resizer The divider element (role="separator").
 * @param {HTMLElement} options.detail The detail pane whose width the CSS variable drives.
 * @param {String} options.cssvar CSS custom property the grid template reads for the detail column.
 * @param {String} options.storagekey localStorage key that persists the chosen width.
 * @param {Number} [options.minimum] Minimum detail-pane width in pixels.
 * @param {Number} [options.maximum] Maximum detail-pane width in pixels.
 * @param {Number} [options.reserve] Width in pixels always kept for the master pane.
 */
export const initPaneResizer = ({body, resizer, detail, cssvar, storagekey, minimum = 240, maximum = 640, reserve = 320}) => {
    if (!resizer || !detail || !body) {
        return;
    }
    const applyWidth = (width) => {
        const bodywidth = body.getBoundingClientRect().width;
        const availablemax = Math.max(minimum, Math.min(maximum, bodywidth - reserve));
        const next = Math.min(Math.max(width, minimum), availablemax);
        body.style.setProperty(cssvar, next + 'px');
        resizer.setAttribute('aria-valuenow', String(Math.round(next)));
        return next;
    };
    const persist = (width) => {
        try {
            window.localStorage.setItem(storagekey, String(Math.round(width)));
        } catch (e) {
            // Local storage may be unavailable in restricted browser contexts.
        }
    };
    try {
        const stored = Number(window.localStorage.getItem(storagekey));
        if (stored) {
            applyWidth(stored);
        }
    } catch (e) {
        // Local storage may be unavailable in restricted browser contexts.
    }
    resizer.setAttribute('aria-valuemin', String(minimum));
    resizer.setAttribute('aria-valuemax', String(maximum));
    let startx = 0;
    let startwidth = 0;
    resizer.addEventListener('pointerdown', (event) => {
        event.preventDefault();
        startx = event.clientX;
        startwidth = detail.getBoundingClientRect().width;
        body.classList.add('resizing');
        resizer.setPointerCapture(event.pointerId);
    });
    resizer.addEventListener('pointermove', (event) => {
        if (!body.classList.contains('resizing')) {
            return;
        }
        applyWidth(startwidth + startx - event.clientX);
    });
    resizer.addEventListener('pointerup', (event) => {
        if (!body.classList.contains('resizing')) {
            return;
        }
        const width = applyWidth(detail.getBoundingClientRect().width);
        body.classList.remove('resizing');
        try {
            resizer.releasePointerCapture(event.pointerId);
        } catch (e) {
            // Pointer capture may already be released.
        }
        persist(width);
    });
    resizer.addEventListener('dblclick', () => {
        body.style.removeProperty(cssvar);
        try {
            window.localStorage.removeItem(storagekey);
        } catch (e) {
            // Local storage may be unavailable in restricted browser contexts.
        }
    });
    resizer.addEventListener('keydown', (event) => {
        let delta = 0;
        if (event.key === 'ArrowLeft') {
            delta = 24;
        } else if (event.key === 'ArrowRight') {
            delta = -24;
        } else {
            return;
        }
        event.preventDefault();
        persist(applyWidth(detail.getBoundingClientRect().width + delta));
    });
};
