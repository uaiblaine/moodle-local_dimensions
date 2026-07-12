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
 * iframes such as H5P activities) and lets the user drag it when it overlaps
 * other UI. On release the button springs to the nearest screen edge (left or
 * right of the drop point relative to the viewport centre), keeping the
 * vertical position and a small gap from the edge. The chosen spot is
 * remembered in sessionStorage, so it persists for the current session only.
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
    // Gap (px) always kept between the FAB and the viewport edges.
    var EDGE_MARGIN = 16;
    // Class enabling the springy edge-snap transition (see styles.css).
    var SNAP_CLASS = 'local-dimensions-fab-snapping';
    // Keep in sync with the snap transition duration in styles.css.
    var SNAP_DURATION = 450;

    /**
     * Read the saved position from session storage.
     *
     * @return {{side: string, top: number}|null}
     */
    var readSavedPosition = function() {
        try {
            var raw = window.sessionStorage.getItem(STORAGE_KEY);
            if (!raw) {
                return null;
            }
            var pos = JSON.parse(raw);
            if (pos && (pos.side === 'left' || pos.side === 'right') && typeof pos.top === 'number') {
                return pos;
            }
        } catch (e) {
            // Storage unavailable or malformed value; fall back to default.
        }
        return null;
    };

    /**
     * Persist the docked position to session storage.
     *
     * @param {string} side Either 'left' or 'right'.
     * @param {number} top
     */
    var savePosition = function(side, top) {
        try {
            window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify({side: side, top: top}));
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
     * Clamp a top coordinate so the FAB stays fully inside the viewport.
     *
     * @param {HTMLElement} fab
     * @param {number} top
     * @return {number}
     */
    var clampTop = function(fab, top) {
        var maxTop = window.innerHeight - fab.offsetHeight - EDGE_MARGIN;
        return clamp(top, EDGE_MARGIN, Math.max(EDGE_MARGIN, maxTop));
    };

    /**
     * Apply a free (pointer-following) position to the FAB, clamped inside the
     * viewport, and clear the default bottom/right anchoring.
     *
     * @param {HTMLElement} fab
     * @param {number} left
     * @param {number} top
     */
    var applyPosition = function(fab, left, top) {
        var maxLeft = window.innerWidth - fab.offsetWidth - EDGE_MARGIN;
        left = clamp(left, EDGE_MARGIN, Math.max(EDGE_MARGIN, maxLeft));
        fab.style.left = left + 'px';
        fab.style.top = clampTop(fab, top) + 'px';
        fab.style.right = 'auto';
        fab.style.bottom = 'auto';
    };

    /**
     * Dock the FAB to one screen edge at the given vertical position and
     * remember the spot for the rest of the session.
     *
     * @param {HTMLElement} fab
     * @param {string} side Either 'left' or 'right'.
     * @param {number} top
     */
    var applySnap = function(fab, side, top) {
        var left = side === 'left'
            ? EDGE_MARGIN
            : window.innerWidth - fab.offsetWidth - EDGE_MARGIN;
        top = clampTop(fab, top);
        fab.style.left = left + 'px';
        fab.style.top = top + 'px';
        fab.style.right = 'auto';
        fab.style.bottom = 'auto';
        savePosition(side, top);
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
        var snapTimer = null;

        fab.addEventListener('pointerdown', function(e) {
            // Only react to the primary mouse button; allow touch/pen.
            if (e.pointerType === 'mouse' && e.button !== 0) {
                return;
            }
            // Catch the button even mid-snap: freeze it where it currently is.
            if (snapTimer !== null) {
                window.clearTimeout(snapTimer);
                snapTimer = null;
            }
            fab.classList.remove(SNAP_CLASS);
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
                var side = rect.left + rect.width / 2 < window.innerWidth / 2 ? 'left' : 'right';
                fab.classList.add(SNAP_CLASS);
                // Flush styles so the transition animates from the drop position.
                fab.getBoundingClientRect();
                applySnap(fab, side, rect.top);
                snapTimer = window.setTimeout(function() {
                    fab.classList.remove(SNAP_CLASS);
                    snapTimer = null;
                }, SNAP_DURATION);
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

        // Keep the FAB docked to its edge when the viewport is resized.
        window.addEventListener('resize', function() {
            var saved = readSavedPosition();
            if (saved) {
                applySnap(fab, saved.side, saved.top);
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
                applySnap(fab, saved.side, saved.top);
            }

            enableDragging(fab);
        }
    };
});
