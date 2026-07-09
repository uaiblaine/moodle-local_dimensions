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

/**
 * Wire a resizer that adjusts the MASTER (left) pane's width from a divider on its right
 * edge. Where initPaneResizer drives the detail (right) column, this writes the master width
 * to a CSS custom property so the detail flexes to fill the rest — the shape the redesigned
 * Learning plans tab uses. The divider tracks the pointer as a relative delta from the grab
 * point (so it never jumps on drag start), clamped so the detail keeps its reserve. The
 * chosen width persists in localStorage and is reapplied on each init. Supports pointer drag,
 * dblclick reset and ArrowLeft/ArrowRight keyboard resizing.
 *
 * @param {Object} options
 * @param {HTMLElement} options.body Flex container of the two panes and the divider.
 * @param {HTMLElement} options.resizer The divider element (role="separator").
 * @param {HTMLElement} options.master The master pane whose width the CSS variable drives.
 * @param {String} options.cssvar CSS custom property the master pane reads for its width.
 * @param {String} options.storagekey localStorage key that persists the chosen width.
 * @param {Number} [options.minimum] Minimum master-pane width in pixels.
 * @param {Number} [options.maximum] Maximum master-pane width in pixels.
 * @param {Number} [options.reserve] Width in pixels always kept for the detail pane + divider.
 */
export const initMasterResizer = ({body, resizer, master, cssvar, storagekey, minimum = 300, maximum = 1200, reserve = 382}) => {
    if (!resizer || !body) {
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
        startwidth = master ? master.getBoundingClientRect().width : body.getBoundingClientRect().width;
        body.classList.add('resizing');
        resizer.setPointerCapture(event.pointerId);
    });
    resizer.addEventListener('pointermove', (event) => {
        if (!body.classList.contains('resizing')) {
            return;
        }
        // Relative delta from the grab point keeps the divider under the cursor with no jump.
        applyWidth(startwidth + event.clientX - startx);
    });
    resizer.addEventListener('pointerup', (event) => {
        if (!body.classList.contains('resizing')) {
            return;
        }
        const width = applyWidth(startwidth + event.clientX - startx);
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
        if (event.key === 'ArrowRight') {
            delta = 24;
        } else if (event.key === 'ArrowLeft') {
            delta = -24;
        } else {
            return;
        }
        event.preventDefault();
        const base = master ? master.getBoundingClientRect().width : minimum;
        persist(applyWidth(base + delta));
    });
};

/**
 * Wire a vertical resizer that adjusts a container's height from a handle rendered
 * directly below it. The height is written to a CSS custom property (so it survives
 * pane reloads) and persisted in localStorage. Supports pointer drag, dblclick reset
 * and ArrowUp/ArrowDown keyboard resizing.
 *
 * @param {Object} options
 * @param {HTMLElement} options.body The container whose height the CSS variable drives.
 * @param {HTMLElement} options.resizer The handle element (role="separator").
 * @param {String} options.cssvar CSS custom property the container height reads.
 * @param {String} options.storagekey localStorage key that persists the chosen height.
 * @param {Number} [options.minimum] Minimum height in pixels.
 * @param {Number} [options.maximum] Maximum height in pixels; defaults to 85% of the viewport.
 */
export const initVerticalResizer = ({body, resizer, cssvar, storagekey, minimum = 320, maximum = 0}) => {
    if (!resizer || !body) {
        return;
    }
    const cap = () => maximum || Math.round(window.innerHeight * 0.85);
    const applyHeight = (height) => {
        const next = Math.min(Math.max(height, minimum), cap());
        body.style.setProperty(cssvar, next + 'px');
        resizer.setAttribute('aria-valuenow', String(Math.round(next)));
        return next;
    };
    const persist = (height) => {
        try {
            window.localStorage.setItem(storagekey, String(Math.round(height)));
        } catch (e) {
            // Local storage may be unavailable in restricted browser contexts.
        }
    };
    try {
        const stored = Number(window.localStorage.getItem(storagekey));
        if (stored) {
            applyHeight(stored);
        }
    } catch (e) {
        // Local storage may be unavailable in restricted browser contexts.
    }
    resizer.setAttribute('aria-valuemin', String(minimum));
    let starty = 0;
    let startheight = 0;
    resizer.addEventListener('pointerdown', (event) => {
        event.preventDefault();
        starty = event.clientY;
        startheight = body.getBoundingClientRect().height;
        body.classList.add('local-dimensions-resizing-vert');
        resizer.setPointerCapture(event.pointerId);
    });
    resizer.addEventListener('pointermove', (event) => {
        if (!body.classList.contains('local-dimensions-resizing-vert')) {
            return;
        }
        applyHeight(startheight + event.clientY - starty);
    });
    resizer.addEventListener('pointerup', (event) => {
        if (!body.classList.contains('local-dimensions-resizing-vert')) {
            return;
        }
        const height = applyHeight(body.getBoundingClientRect().height);
        body.classList.remove('local-dimensions-resizing-vert');
        try {
            resizer.releasePointerCapture(event.pointerId);
        } catch (e) {
            // Pointer capture may already be released.
        }
        persist(height);
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
        if (event.key === 'ArrowUp') {
            delta = -24;
        } else if (event.key === 'ArrowDown') {
            delta = 24;
        } else {
            return;
        }
        event.preventDefault();
        persist(applyHeight(body.getBoundingClientRect().height + delta));
    });
};
