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
 * Return button (FAB) visibility and drag-to-reposition behaviour.
 *
 * Shows the floating action button only in the main window (hidden inside
 * iframes such as H5P activities) and lets the user drag it to a different
 * spot when it overlaps other UI. The chosen position is remembered in
 * sessionStorage, so it persists for the current browser session only.
 *
 * @module     local_dimensions/return_button
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    'use strict';

    var FAB_ID = 'local-dimensions-return-fab';
    var STORAGE_KEY = 'local_dimensions_fab_pos';
    // Pointer travel (px) before a press is treated as a drag instead of a click.
    var DRAG_THRESHOLD = 5;
    // Keep at least this many px between the FAB and the viewport edge.
    var EDGE_MARGIN = 8;

    /**
     * Read the saved position from session storage.
     *
     * @return {{left: number, top: number}|null}
     */
    var readSavedPosition = function() {
        try {
            var raw = window.sessionStorage.getItem(STORAGE_KEY);
            if (!raw) {
                return null;
            }
            var pos = JSON.parse(raw);
            if (pos && typeof pos.left === 'number' && typeof pos.top === 'number') {
                return pos;
            }
        } catch (e) {
            // Storage unavailable or malformed value; fall back to default.
        }
        return null;
    };

    /**
     * Persist the position to session storage.
     *
     * @param {number} left
     * @param {number} top
     */
    var savePosition = function(left, top) {
        try {
            window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify({left: left, top: top}));
        } catch (e) {
            // Storage unavailable; the position simply won't persist.
        }
    };

    /**
     * Remove any persisted position.
     */
    var clearPosition = function() {
        try {
            window.sessionStorage.removeItem(STORAGE_KEY);
        } catch (e) {
            // Ignore.
        }
    };

    /**
     * Clamp a value into the [min, max] range.
     *
     * @param {number} value
     * @param {number} min
     * @param {number} max
     * @return {number}
     */
    var clamp = function(value, min, max) {
        return Math.max(min, Math.min(value, max));
    };

    /**
     * Apply an absolute position to the FAB, clamped inside the viewport, and
     * clear the default bottom/right anchoring.
     *
     * @param {HTMLElement} fab
     * @param {number} left
     * @param {number} top
     */
    var applyPosition = function(fab, left, top) {
        var maxLeft = window.innerWidth - fab.offsetWidth - EDGE_MARGIN;
        var maxTop = window.innerHeight - fab.offsetHeight - EDGE_MARGIN;
        left = clamp(left, EDGE_MARGIN, Math.max(EDGE_MARGIN, maxLeft));
        top = clamp(top, EDGE_MARGIN, Math.max(EDGE_MARGIN, maxTop));
        fab.style.left = left + 'px';
        fab.style.top = top + 'px';
        fab.style.right = 'auto';
        fab.style.bottom = 'auto';
    };

    /**
     * Reset the FAB to its default (CSS) corner and forget the saved position.
     *
     * @param {HTMLElement} fab
     */
    var resetPosition = function(fab) {
        clearPosition();
        fab.style.left = '';
        fab.style.top = '';
        fab.style.right = '';
        fab.style.bottom = '';
    };

    /**
     * Wire up pointer-based dragging on the FAB.
     *
     * @param {HTMLElement} fab
     */
    var enableDragging = function(fab) {
        var dragging = false;
        var moved = false;
        var startX = 0;
        var startY = 0;
        var originLeft = 0;
        var originTop = 0;

        fab.addEventListener('pointerdown', function(e) {
            // Only react to the primary mouse button; allow touch/pen.
            if (e.pointerType === 'mouse' && e.button !== 0) {
                return;
            }
            dragging = true;
            moved = false;
            startX = e.clientX;
            startY = e.clientY;
            var rect = fab.getBoundingClientRect();
            originLeft = rect.left;
            originTop = rect.top;
            fab.setPointerCapture(e.pointerId);
        });

        fab.addEventListener('pointermove', function(e) {
            if (!dragging) {
                return;
            }
            var dx = e.clientX - startX;
            var dy = e.clientY - startY;
            if (!moved && Math.abs(dx) < DRAG_THRESHOLD && Math.abs(dy) < DRAG_THRESHOLD) {
                return;
            }
            moved = true;
            fab.classList.add('local-dimensions-fab-dragging');
            applyPosition(fab, originLeft + dx, originTop + dy);
            e.preventDefault();
        });

        var endDrag = function(e) {
            if (!dragging) {
                return;
            }
            dragging = false;
            fab.classList.remove('local-dimensions-fab-dragging');
            if (fab.hasPointerCapture && fab.hasPointerCapture(e.pointerId)) {
                fab.releasePointerCapture(e.pointerId);
            }
            if (moved) {
                var rect = fab.getBoundingClientRect();
                savePosition(rect.left, rect.top);
            }
        };

        fab.addEventListener('pointerup', endDrag);
        fab.addEventListener('pointercancel', endDrag);

        // Prevent the browser's native link-drag (ghost image).
        fab.addEventListener('dragstart', function(e) {
            e.preventDefault();
        });

        // Suppress the click that follows a drag so it never navigates.
        fab.addEventListener('click', function(e) {
            if (moved) {
                e.preventDefault();
                e.stopPropagation();
                moved = false;
            }
        });

        // Double-click returns the button to its default corner.
        fab.addEventListener('dblclick', function(e) {
            e.preventDefault();
            resetPosition(fab);
        });

        // Keep the FAB on-screen when the viewport is resized.
        window.addEventListener('resize', function() {
            if (fab.style.left) {
                var rect = fab.getBoundingClientRect();
                applyPosition(fab, rect.left, rect.top);
            }
        });
    };

    return {
        /**
         * Initialise the return button visibility and dragging.
         */
        init: function() {
            var fab = document.getElementById(FAB_ID);
            if (!fab || window !== window.parent) {
                return;
            }

            // Show the button (it ships hidden to avoid a flash inside iframes).
            fab.style.display = 'flex';

            // Restore any position the user set earlier this session.
            var saved = readSavedPosition();
            if (saved) {
                applyPosition(fab, saved.left, saved.top);
            }

            enableDragging(fab);
        }
    };
});
