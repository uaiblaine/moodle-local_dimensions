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
 * Horizontal-scrolling pill tab navigation for local_dimensions filters.
 *
 * Adapted from the block_dimensions/filter_tabs_nav module so the chip
 * filters in view-plan and view-competency pages share the same UX.
 *
 * @module     local_dimensions/filter_tabs_nav
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/* eslint-disable jsdoc/require-jsdoc */

define([], function() {
    'use strict';

    var EASE_OUT_CUBIC = function(t) {
        return 1 - Math.pow(1 - t, 3);
    };

    function clamp(value, min, max) {
        return Math.min(max, Math.max(min, value));
    }

    function parseDurationMs(rawValue, fallbackMs) {
        fallbackMs = fallbackMs || 320;
        var value = String(rawValue || '').trim();
        if (!value) {
            return fallbackMs;
        }
        if (value.indexOf('ms') === value.length - 2) {
            return parseFloat(value);
        }
        if (value.charAt(value.length - 1) === 's') {
            return parseFloat(value) * 1000;
        }
        var numeric = parseFloat(value);
        return isFinite(numeric) ? numeric : fallbackMs;
    }

    var PADDLE_LEFT_SVG = '<svg width="8" height="14" viewBox="0 0 8 14" fill="none" xmlns="http://www.w3.org/2000/svg">' +
        '<path d="M7 1L1 7L7 13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    var PADDLE_RIGHT_SVG = '<svg width="8" height="14" viewBox="0 0 8 14" fill="none" xmlns="http://www.w3.org/2000/svg">' +
        '<path d="M1 1L7 7L1 13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    /**
     * Wrap the inner content of a .local-dimensions-filter-tabs element with
     * mask/indicator/paddles wrapping siblings.
     *
     * @param {HTMLElement} tabsEl The .local-dimensions-filter-tabs element.
     */
    function wrapTabsContent(tabsEl) {
        var tabs = [];
        var child = tabsEl.firstChild;
        while (child) {
            var next = child.nextSibling;
            tabs.push(child);
            child = next;
        }

        var itemsEl = document.createElement('div');
        itemsEl.className = 'local-dimensions-filter-tabs-items';
        tabs.forEach(function(tab) {
            itemsEl.appendChild(tab);
        });

        var maskEl = document.createElement('div');
        maskEl.className = 'local-dimensions-filter-tabs-mask';
        maskEl.appendChild(itemsEl);

        var indicatorEl = document.createElement('div');
        indicatorEl.className = 'local-dimensions-filter-tabs-indicator';

        var paddlesEl = document.createElement('div');
        paddlesEl.className = 'local-dimensions-filter-tabs-paddles';

        var paddleLeft = document.createElement('button');
        paddleLeft.type = 'button';
        paddleLeft.className = 'local-dimensions-filter-tabs-paddle local-dimensions-filter-tabs-paddle-left ' +
            'local-dimensions-filter-tabs-paddle-hidden';
        paddleLeft.setAttribute('aria-hidden', 'true');
        paddleLeft.tabIndex = -1;
        paddleLeft.innerHTML = PADDLE_LEFT_SVG;

        var paddleRight = document.createElement('button');
        paddleRight.type = 'button';
        paddleRight.className = 'local-dimensions-filter-tabs-paddle local-dimensions-filter-tabs-paddle-right ' +
            'local-dimensions-filter-tabs-paddle-hidden';
        paddleRight.setAttribute('aria-hidden', 'true');
        paddleRight.tabIndex = -1;
        paddleRight.innerHTML = PADDLE_RIGHT_SVG;

        paddlesEl.appendChild(paddleLeft);
        paddlesEl.appendChild(paddleRight);

        tabsEl.innerHTML = '';
        tabsEl.appendChild(maskEl);
        tabsEl.appendChild(indicatorEl);
        tabsEl.appendChild(paddlesEl);
    }

    /**
     * Controller for one .local-dimensions-filter-tabs-wrapper element.
     *
     * @param {HTMLElement} wrapperEl The wrapper element containing one tabs platter.
     * @constructor
     */
    function FilterTabsNav(wrapperEl) {
        this.wrapperEl = wrapperEl;
        this.platterEl = wrapperEl.querySelector('.local-dimensions-filter-tabs');
        if (!this.platterEl) {
            return;
        }

        wrapTabsContent(this.platterEl);

        this.maskEl = this.platterEl.querySelector('.local-dimensions-filter-tabs-mask');
        this.itemsEl = this.platterEl.querySelector('.local-dimensions-filter-tabs-items');
        this.indicatorEl = this.platterEl.querySelector('.local-dimensions-filter-tabs-indicator');
        this.paddleLeftEl = this.platterEl.querySelector('.local-dimensions-filter-tabs-paddle-left');
        this.paddleRightEl = this.platterEl.querySelector('.local-dimensions-filter-tabs-paddle-right');

        this.scrollAnimationFrame = 0;
        this.resizeDebounceTimer = 0;
        this.reducedMotion = false;
        this._destroyed = false;

        this._onPaddleLeftClick = this._onPaddleLeftClick.bind(this);
        this._onPaddleRightClick = this._onPaddleRightClick.bind(this);
        this._onResize = this._onResize.bind(this);
        this._onReducedMotionChange = this._onReducedMotionChange.bind(this);
        this._onKeyDown = this._onKeyDown.bind(this);

        this._reducedMotionMql = window.matchMedia('(prefers-reduced-motion: reduce)');
        this.reducedMotion = this._reducedMotionMql.matches;
        if (this._reducedMotionMql.addEventListener) {
            this._reducedMotionMql.addEventListener('change', this._onReducedMotionChange);
        }

        this._resizeObserver = new ResizeObserver(this._onResize);
        this._resizeObserver.observe(this.wrapperEl);

        this.paddleLeftEl.addEventListener('click', this._onPaddleLeftClick);
        this.paddleRightEl.addEventListener('click', this._onPaddleRightClick);

        this.platterEl.addEventListener('keydown', this._onKeyDown);

        this.platterEl.classList.add('local-dimensions-filter-tabs-no-transition');
        var self = this;
        requestAnimationFrame(function() {
            if (self._destroyed) {
                return;
            }
            self.update();
            requestAnimationFrame(function() {
                if (!self._destroyed) {
                    self.platterEl.classList.remove('local-dimensions-filter-tabs-no-transition');
                }
            });
        });

        wrapperEl._localDimsFilterTabsNav = this;
    }

    FilterTabsNav.prototype._getActiveTab = function() {
        // For chip filters, "active" means at least one chip pressed.
        // The indicator follows the first pressed chip; if none, it follows
        // the focused tab (if any) or the first tab as visual hint.
        return this.itemsEl.querySelector('.local-dimensions-filter-tab[aria-pressed="true"]') ||
            this.itemsEl.querySelector('.local-dimensions-filter-tab:focus') ||
            null;
    };

    FilterTabsNav.prototype._getAllTabs = function() {
        return Array.prototype.slice.call(
            this.itemsEl.querySelectorAll('.local-dimensions-filter-tab')
        );
    };

    FilterTabsNav.prototype._parseCssPx = function(name, fallback) {
        var raw = getComputedStyle(this.platterEl).getPropertyValue(name);
        var parsed = parseFloat(raw);
        return isFinite(parsed) ? parsed : (fallback || 0);
    };

    FilterTabsNav.prototype._getScrollDurationMs = function() {
        var raw = getComputedStyle(this.platterEl).getPropertyValue('--local-dimensions-tabs-scroll-duration');
        return parseDurationMs(raw, 320);
    };

    FilterTabsNav.prototype._computeProps = function(targetEl) {
        var activeTab = this._getActiveTab();
        var referenceEl = targetEl || activeTab;

        var platterWidth = this.platterEl.offsetWidth;
        var platterPadding = this._parseCssPx('--local-dimensions-tabs-platter-padding', 4);
        var itemsWidth = this.itemsEl.scrollWidth;
        var scrollable = itemsWidth + platterPadding * 2 - 1 > platterWidth;

        if (!referenceEl) {
            // No active or reference tab — derive paddle state from current scroll.
            var atLeft = this.maskEl.scrollLeft <= 1;
            var atRight = this.maskEl.scrollLeft + this.maskEl.clientWidth >= itemsWidth - 1;
            return {
                scrollable: scrollable,
                indicatorStart: '4px',
                indicatorWidth: '0px',
                hideIndicator: true,
                scrollLeft: this.maskEl.scrollLeft,
                disableLeftPaddle: atLeft,
                disableRightPaddle: atRight,
                contentPosition: 'inBetweenEdges'
            };
        }

        var activeLeft = referenceEl.offsetLeft;
        var activeWidth = referenceEl.offsetWidth;
        var itemLeft = referenceEl.offsetLeft;
        var itemWidth = referenceEl.offsetWidth;
        var itemCenter = itemWidth / 2;
        var platterCenter = platterWidth / 2;

        var contentPosition = 'inBetweenEdges';
        if (itemLeft + itemCenter > itemsWidth - platterCenter) {
            contentPosition = 'rightEdge';
        } else if (itemLeft + itemCenter < platterCenter) {
            contentPosition = 'leftEdge';
        }

        var indicatorStart;
        var scrollLeft;

        if (contentPosition === 'rightEdge') {
            indicatorStart = activeLeft + (platterWidth - itemsWidth) - platterPadding;
            scrollLeft = itemsWidth - platterWidth + platterPadding * 2;
        } else if (contentPosition === 'leftEdge') {
            indicatorStart = activeLeft + platterPadding;
            scrollLeft = 0;
        } else {
            indicatorStart = platterCenter - itemLeft - itemCenter + activeLeft;
            scrollLeft = itemLeft - (platterCenter - itemCenter) + platterPadding;
        }

        return {
            contentPosition: contentPosition,
            indicatorStart: indicatorStart + 'px',
            indicatorWidth: activeWidth + 'px',
            hideIndicator: false,
            scrollLeft: scrollLeft,
            scrollable: scrollable,
            disableLeftPaddle: contentPosition === 'leftEdge',
            disableRightPaddle: contentPosition === 'rightEdge'
        };
    };

    FilterTabsNav.prototype._animateMaskScroll = function(targetLeft) {
        cancelAnimationFrame(this.scrollAnimationFrame);

        if (this.reducedMotion) {
            this.maskEl.scrollLeft = targetLeft;
            return;
        }

        var startLeft = this.maskEl.scrollLeft;
        var delta = targetLeft - startLeft;
        var durationMs = this._getScrollDurationMs();
        var startTime = performance.now();
        var maskEl = this.maskEl;
        var self = this;

        var tick = function(now) {
            var progress = clamp((now - startTime) / durationMs, 0, 1);
            var eased = EASE_OUT_CUBIC(progress);
            maskEl.scrollLeft = Math.round(startLeft + delta * eased);
            if (progress < 1) {
                self.scrollAnimationFrame = requestAnimationFrame(tick);
            }
        };

        this.scrollAnimationFrame = requestAnimationFrame(tick);
    };

    FilterTabsNav.prototype._updatePaddles = function(props) {
        if (!props.scrollable) {
            this.paddleLeftEl.classList.add('local-dimensions-filter-tabs-paddle-hidden');
            this.paddleLeftEl.disabled = true;
            this.paddleRightEl.classList.add('local-dimensions-filter-tabs-paddle-hidden');
            this.paddleRightEl.disabled = true;
            this.maskEl.classList.add('local-dimensions-filter-tabs-mask-noscroll');
            return;
        }

        this.maskEl.classList.remove('local-dimensions-filter-tabs-mask-noscroll');

        this.paddleLeftEl.classList.toggle(
            'local-dimensions-filter-tabs-paddle-hidden',
            props.disableLeftPaddle
        );
        this.paddleLeftEl.disabled = props.disableLeftPaddle;

        this.paddleRightEl.classList.toggle(
            'local-dimensions-filter-tabs-paddle-hidden',
            props.disableRightPaddle
        );
        this.paddleRightEl.disabled = props.disableRightPaddle;
    };

    FilterTabsNav.prototype._centerItem = function(targetEl) {
        var props = this._computeProps(targetEl);

        this.platterEl.style.setProperty('--local-dimensions-tabs-indicator-start', props.indicatorStart);
        this.platterEl.style.setProperty('--local-dimensions-tabs-indicator-width', props.indicatorWidth);
        this.indicatorEl.style.opacity = props.hideIndicator ? '0' : '1';

        if (props.scrollable && targetEl) {
            this._animateMaskScroll(props.scrollLeft);
        }

        this._updatePaddles(props);
    };

    /**
     * Public: re-compute indicator and paddle visibility.
     */
    FilterTabsNav.prototype.update = function() {
        this._centerItem();
    };

    FilterTabsNav.prototype._scrollByDirection = function(direction) {
        var tabs = this._getAllTabs();
        var maskRect = this.maskEl.getBoundingClientRect();
        var i;
        if (direction > 0) {
            for (i = 0; i < tabs.length; i++) {
                if (tabs[i].getBoundingClientRect().right > maskRect.right + 2) {
                    this._centerItem(tabs[i]);
                    return;
                }
            }
        } else {
            for (i = tabs.length - 1; i >= 0; i--) {
                if (tabs[i].getBoundingClientRect().left < maskRect.left - 2) {
                    this._centerItem(tabs[i]);
                    return;
                }
            }
        }
    };

    FilterTabsNav.prototype._onPaddleLeftClick = function(e) {
        e.preventDefault();
        e.stopPropagation();
        this._scrollByDirection(-1);
    };

    FilterTabsNav.prototype._onPaddleRightClick = function(e) {
        e.preventDefault();
        e.stopPropagation();
        this._scrollByDirection(1);
    };

    FilterTabsNav.prototype._onResize = function() {
        if (this._destroyed) {
            return;
        }
        this.platterEl.classList.add('local-dimensions-filter-tabs-no-transition');
        this._centerItem();
        var self = this;
        clearTimeout(this.resizeDebounceTimer);
        this.resizeDebounceTimer = window.setTimeout(function() {
            if (!self._destroyed) {
                self.platterEl.classList.remove('local-dimensions-filter-tabs-no-transition');
            }
        }, 250);
    };

    FilterTabsNav.prototype._onReducedMotionChange = function(e) {
        this.reducedMotion = !!e.matches;
    };

    FilterTabsNav.prototype._onKeyDown = function(e) {
        if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') {
            return;
        }
        var tabs = this._getAllTabs();
        var focusedIndex = tabs.indexOf(document.activeElement);
        if (focusedIndex === -1) {
            return;
        }
        e.preventDefault();
        var nextIndex = e.key === 'ArrowRight'
            ? (focusedIndex + 1) % tabs.length
            : (focusedIndex - 1 + tabs.length) % tabs.length;
        tabs[nextIndex].focus({preventScroll: true});
    };

    FilterTabsNav.prototype.destroy = function() {
        this._destroyed = true;
        cancelAnimationFrame(this.scrollAnimationFrame);
        clearTimeout(this.resizeDebounceTimer);
        if (this._resizeObserver) {
            this._resizeObserver.disconnect();
        }
        if (this._reducedMotionMql && this._reducedMotionMql.removeEventListener) {
            this._reducedMotionMql.removeEventListener('change', this._onReducedMotionChange);
        }
        if (this.paddleLeftEl) {
            this.paddleLeftEl.removeEventListener('click', this._onPaddleLeftClick);
        }
        if (this.paddleRightEl) {
            this.paddleRightEl.removeEventListener('click', this._onPaddleRightClick);
        }
        if (this.platterEl) {
            this.platterEl.removeEventListener('keydown', this._onKeyDown);
        }
        if (this.wrapperEl) {
            delete this.wrapperEl._localDimsFilterTabsNav;
        }
    };

    /**
     * Initialise FilterTabsNav on every wrapper inside a container.
     *
     * @param {HTMLElement} container
     * @return {FilterTabsNav[]}
     */
    function initAll(container) {
        if (!container) {
            return [];
        }
        var wrappers = container.querySelectorAll('.local-dimensions-filter-tabs-wrapper');
        var instances = [];
        for (var i = 0; i < wrappers.length; i++) {
            if (wrappers[i]._localDimsFilterTabsNav) {
                instances.push(wrappers[i]._localDimsFilterTabsNav);
                continue;
            }
            if (!wrappers[i].querySelector('.local-dimensions-filter-tabs')) {
                continue;
            }
            instances.push(new FilterTabsNav(wrappers[i]));
        }
        return instances;
    }

    function destroyAll(container) {
        if (!container) {
            return;
        }
        var wrappers = container.querySelectorAll('.local-dimensions-filter-tabs-wrapper');
        for (var i = 0; i < wrappers.length; i++) {
            if (wrappers[i]._localDimsFilterTabsNav) {
                wrappers[i]._localDimsFilterTabsNav.destroy();
            }
        }
    }

    function updateAll(container) {
        if (!container) {
            return;
        }
        var wrappers = container.querySelectorAll('.local-dimensions-filter-tabs-wrapper');
        for (var i = 0; i < wrappers.length; i++) {
            if (wrappers[i]._localDimsFilterTabsNav) {
                wrappers[i]._localDimsFilterTabsNav.update();
            }
        }
    }

    return {
        FilterTabsNav: FilterTabsNav,
        initAll: initAll,
        destroyAll: destroyAll,
        updateAll: updateAll
    };
});
