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
 * Collapsible description manager.
 *
 * Measures every [data-collapsible-description] element on the page (or inside
 * a given root) and shows a toggle button when content exceeds the configured
 * max-height (default 30vh, overridable via the --collapsible-max-vh CSS
 * variable). Reacts to viewport resize and to dynamic content changes via
 * ResizeObserver, so embedded media (videos, H5P, iframes) recalculate the
 * overflow state once they finish loading.
 *
 * @module     local_dimensions/collapsible_description
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function () {
    'use strict';

    var CONTAINER_SELECTOR = '[data-collapsible-description]';
    var CONTENT_SELECTOR = '.local-dimensions-collapsible-content';
    var TOGGLE_SELECTOR = '.local-dimensions-collapsible-toggle';
    var EXPANDED_CLASS = 'is-expanded';
    var OVERFLOW_CLASS = 'has-overflow';

    var initialised = new WeakSet();
    var resizeObserver = null;
    var windowListenerAttached = false;
    var trackedContainers = [];

    /**
     * Re-measure a container and toggle the has-overflow class accordingly.
     *
     * @param {HTMLElement} container
     */
    function measure(container) {
        var content = container.querySelector(CONTENT_SELECTOR);
        var toggle = container.querySelector(TOGGLE_SELECTOR);
        if (!content || !toggle) {
            return;
        }

        // When already expanded, do not collapse based on measurement.
        if (container.classList.contains(EXPANDED_CLASS)) {
            toggle.hidden = false;
            return;
        }

        // Temporarily ensure the content is collapsed so scrollHeight reflects
        // overflow against the CSS-imposed max-height.
        var overflows = content.scrollHeight - content.clientHeight > 1;

        if (overflows) {
            container.classList.add(OVERFLOW_CLASS);
            toggle.hidden = false;
        } else {
            container.classList.remove(OVERFLOW_CLASS);
            toggle.hidden = true;
        }
    }

    /**
     * Update toggle button label/aria after expand/collapse.
     *
     * @param {HTMLElement} container
     * @param {HTMLButtonElement} toggle
     * @param {boolean} expanded
     */
    function syncToggle(container, toggle, expanded) {
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        var label = toggle.querySelector('.local-dimensions-collapsible-toggle-label');
        var icon = toggle.querySelector('i');
        if (label) {
            label.textContent = expanded
                ? (toggle.dataset.labelHide || label.textContent)
                : (toggle.dataset.labelShow || label.textContent);
        }
        if (icon) {
            icon.classList.toggle('fa-chevron-down', !expanded);
            icon.classList.toggle('fa-chevron-up', expanded);
        }
    }

    /**
     * Wire up a single container.
     *
     * @param {HTMLElement} container
     */
    function setupContainer(container) {
        if (initialised.has(container)) {
            return;
        }
        initialised.add(container);

        var content = container.querySelector(CONTENT_SELECTOR);
        var toggle = container.querySelector(TOGGLE_SELECTOR);
        if (!content || !toggle) {
            return;
        }

        toggle.addEventListener('click', function () {
            var willExpand = !container.classList.contains(EXPANDED_CLASS);
            container.classList.toggle(EXPANDED_CLASS, willExpand);
            syncToggle(container, toggle, willExpand);
        });

        // Track for global resize / observer.
        trackedContainers.push(container);

        if (typeof ResizeObserver !== 'undefined') {
            if (!resizeObserver) {
                resizeObserver = new ResizeObserver(function (entries) {
                    entries.forEach(function (entry) {
                        var owner = entry.target.closest(CONTAINER_SELECTOR);
                        if (owner) {
                            measure(owner);
                        }
                    });
                });
            }
            resizeObserver.observe(content);
        }

        if (!windowListenerAttached) {
            windowListenerAttached = true;
            window.addEventListener('resize', function () {
                trackedContainers.forEach(measure);
            }, { passive: true });
        }

        // Re-measure once embedded media (images/iframes) finish loading.
        var media = content.querySelectorAll('img, iframe, video');
        media.forEach(function (el) {
            el.addEventListener('load', function () { measure(container); }, { once: true });
        });

        measure(container);
    }

    /**
     * Find and initialise every container under the given root.
     *
     * @param {ParentNode} root
     */
    function setupAllUnder(root) {
        if (!root || !root.querySelectorAll) {
            return;
        }
        var containers = root.querySelectorAll(CONTAINER_SELECTOR);
        containers.forEach(setupContainer);
    }

    return {
        /**
         * Initialise every collapsible description currently in the document.
         */
        init: function () {
            setupAllUnder(document);
        },

        /**
         * Initialise collapsibles inside a specific root (used after AJAX
         * inserts new markup, e.g. accordion description tabs).
         *
         * @param {HTMLElement|string} rootOrSelector
         */
        refresh: function (rootOrSelector) {
            var root = typeof rootOrSelector === 'string'
                ? document.querySelector(rootOrSelector)
                : rootOrSelector;
            setupAllUnder(root || document);
        }
    };
});
