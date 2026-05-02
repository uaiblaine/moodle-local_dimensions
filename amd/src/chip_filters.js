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
 * Chip filter manager.
 *
 * Wires up [data-chip-filters] containers rendered by the
 * local_dimensions/chip_filters Mustache partial. Tracks the active
 * selection per shortname (multi-select inside a group is OR; selections
 * across groups are AND) and invokes the host-page callback whenever the
 * selection changes.
 *
 * Each filterable item must carry a data-filtervalues attribute holding a
 * JSON object mapping shortname => value. Items are matched against the
 * active chip selection inside the host callback (or via the helper
 * `matchesSelection`).
 *
 * @module     local_dimensions/chip_filters
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['local_dimensions/filter_tabs_nav'], function (FilterTabsNav) {
    'use strict';

    /**
     * Container state keyed by container DOM id.
     *
     * @type {Object<string, {container: HTMLElement, callback: Function}>}
     */
    var registry = {};

    /**
     * Convert a container's current chip state into a plain selection map.
     *
     * @param {HTMLElement} container
     * @return {Object<string, string[]>}
     */
    function readSelection(container) {
        var selection = {};
        container.querySelectorAll('.local-dimensions-filter-tab[aria-pressed="true"]').forEach(function (chip) {
            var group = chip.closest('.local-dimensions-filter-tabs-wrapper');
            if (!group) {
                return;
            }
            var field = group.dataset.chipField;
            var value = chip.dataset.chipValue;
            if (!field) {
                return;
            }
            if (!selection[field]) {
                selection[field] = [];
            }
            selection[field].push(value);
        });
        return selection;
    }

    /**
     * Toggle the visibility of the "clear" button.
     *
     * @param {HTMLElement} container
     * @param {Object<string, string[]>} selection
     */
    function refreshClear(container, selection) {
        var clearBtn = container.querySelector('[data-chip-clear]');
        if (!clearBtn) {
            return;
        }
        var hasAny = Object.keys(selection).some(function (k) {
            return selection[k] && selection[k].length > 0;
        });
        clearBtn.hidden = !hasAny;
    }

    /**
     * Reset every chip in the container.
     *
     * @param {HTMLElement} container
     */
    function clearAll(container) {
        container.querySelectorAll('.local-dimensions-filter-tab[aria-pressed="true"]').forEach(function (chip) {
            chip.setAttribute('aria-pressed', 'false');
        });
    }

    /**
     * Wire up a single container.
     *
     * @param {HTMLElement} container
     * @param {Function} callback Receives the updated selection map.
     */
    function setupContainer(container, callback) {
        container.querySelectorAll('.local-dimensions-filter-tab').forEach(function (chip) {
            chip.addEventListener('click', function () {
                var pressed = chip.getAttribute('aria-pressed') === 'true';
                chip.setAttribute('aria-pressed', pressed ? 'false' : 'true');
                var selection = readSelection(container);
                refreshClear(container, selection);
                FilterTabsNav.updateAll(container);
                if (typeof callback === 'function') {
                    callback(selection);
                }
            });
        });

        var clearBtn = container.querySelector('[data-chip-clear]');
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                clearAll(container);
                var selection = readSelection(container);
                refreshClear(container, selection);
                FilterTabsNav.updateAll(container);
                if (typeof callback === 'function') {
                    callback(selection);
                }
            });
        }
    }

    return {
        /**
         * Initialise a chip filter container by id.
         *
         * @param {string} containerId DOM id of the [data-chip-filters] node.
         * @param {Function} callback Invoked with the updated selection map.
         */
        init: function (containerId, callback) {
            var container = document.getElementById(containerId);
            if (!container) {
                return;
            }
            registry[containerId] = { container: container, callback: callback };
            setupContainer(container, callback);
            // Activate the scrollable pill UI on every chip group.
            FilterTabsNav.initAll(container);
        },

        /**
         * Helper used by host pages to check whether an item passes the
         * current selection. Selection is AND across fields, OR within a
         * field. Empty selection = pass.
         *
         * @param {Object<string, string[]>} selection
         * @param {Object<string, string>} itemValues item shortname=>value map.
         * @return {boolean}
         */
        matchesSelection: function (selection, itemValues) {
            var keys = Object.keys(selection || {});
            for (var i = 0; i < keys.length; i++) {
                var field = keys[i];
                var allowed = selection[field];
                if (!allowed || allowed.length === 0) {
                    continue;
                }
                var actual = (itemValues && itemValues[field]) || '';
                if (allowed.indexOf(actual) === -1) {
                    return false;
                }
            }
            return true;
        }
    };
});
