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
 * Icon picker autocomplete widget for admin settings.
 *
 * Provides search, selection and preview of FontAwesome icons
 * via AJAX calls to the local_dimensions_get_fontawesome_icons service.
 *
 * @module     local_dimensions/setting_iconpicker
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax'], function(Ajax) {
    'use strict';

    /**
     * Escape HTML entities in a string.
     *
     * @param {String} str
     * @return {String}
     */
    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    /**
     * Resolve an icon object to its CSS class string.
     *
     * @param {Object} icon
     * @return {String}
     */
    function resolveClass(icon) {
        if (icon.source === 'core') {
            return 'fa ' + icon.class;
        }
        if (icon.source === 'fasolid') {
            return 'fas ' + icon.class;
        }
        if (icon.source === 'fabrand') {
            return 'fab ' + icon.class;
        }
        return 'fa ' + icon.class;
    }

    return {
        /**
         * Initialise the icon picker widget.
         *
         * @param {Object} config
         * @param {String} config.elementId  Base element ID for the widget
         * @param {String} config.hiddenId   ID of the hidden input storing the value
         */
        init: function(config) {
            var elementId = config.elementId;
            var hiddenId = config.hiddenId;

            var searchInput = document.getElementById(elementId + '-search');
            var hiddenInput = document.getElementById(hiddenId);
            var dropdown = document.getElementById(elementId + '-dropdown');
            var preview = document.getElementById(elementId + '-preview');
            var clearBtn = document.getElementById(elementId + '-clear');
            var downArrow = document.getElementById(elementId + '-downarrow');
            var debounceTimer = null;
            var cache = {};

            if (!searchInput || !hiddenInput || !dropdown) {
                return;
            }

            searchInput.addEventListener('input', function() {
                var query = this.value.trim();
                clearTimeout(debounceTimer);
                if (query.length < 1) {
                    dropdown.style.display = 'none';
                    return;
                }
                debounceTimer = setTimeout(function() {
                    searchIcons(query);
                }, 300);
            });

            searchInput.addEventListener('focus', function() {
                var query = this.value.trim();
                if (query.length >= 1 && dropdown.innerHTML) {
                    dropdown.style.display = 'block';
                }
            });

            if (downArrow) {
                downArrow.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (dropdown.style.display === 'block') {
                        dropdown.style.display = 'none';
                    } else {
                        searchIcons('');
                    }
                });
            }

            document.addEventListener('click', function(e) {
                if (!e.target.closest('#' + elementId + '-container')) {
                    dropdown.style.display = 'none';
                }
            });

            clearBtn.addEventListener('click', function() {
                searchInput.value = '';
                hiddenInput.value = '';
                preview.innerHTML = '';
                dropdown.style.display = 'none';
                dropdown.innerHTML = '';
            });

            /**
             * Search icons via AJAX with client-side caching.
             *
             * @param {String} query
             */
            function searchIcons(query) {
                if (cache[query]) {
                    renderResults(cache[query]);
                    return;
                }

                Ajax.call([{
                    methodname: 'local_dimensions_get_fontawesome_icons',
                    args: {query: query}
                }])[0].done(function(response) {
                    cache[query] = response;
                    renderResults(response);
                }).fail(function(err) {
                    window.console.error('Icon search failed:', err);
                    dropdown.innerHTML = '<div class="p-2 text-danger">Error loading icons</div>';
                    dropdown.style.display = 'block';
                });
            }

            /**
             * Render search results into the dropdown.
             *
             * @param {Object} response
             */
            function renderResults(response) {
                dropdown.innerHTML = '';

                if (response.overflow) {
                    dropdown.innerHTML = '<div class="p-2 text-muted">Too many results. Please refine your search.</div>';
                    dropdown.style.display = 'block';
                    return;
                }

                if (!response.icons || response.icons.length === 0) {
                    dropdown.innerHTML = '<div class="p-2 text-muted">No icons found.</div>';
                    dropdown.style.display = 'block';
                    return;
                }

                response.icons.forEach(function(icon) {
                    var item = document.createElement('div');
                    item.className = 'local-dimensions-icon-dropdown-item';

                    var iconClass = resolveClass(icon);
                    var sourceLabel = icon.source === 'core' ? 'Core'
                        : (icon.source === 'fasolid' ? 'FA'
                            : (icon.source === 'fabrand' ? 'Brand' : 'FA'));
                    var sourceColor = icon.source === 'core' ? 'bg-warning text-dark' : 'bg-success';

                    item.innerHTML = '<i class="' + iconClass + ' fa-fw" aria-hidden="true"></i>' +
                        '<small style="flex:1; word-break:break-all;">' + escapeHtml(icon.name) + '</small>' +
                        '<span class="badge ' + sourceColor + '">' + sourceLabel + '</span>';

                    item.addEventListener('click', function() {
                        var storedValue = (icon.source === 'core') ? icon.class : icon.name;
                        hiddenInput.value = storedValue;
                        searchInput.value = storedValue;
                        preview.innerHTML = '<i class="' + iconClass + ' fa-lg" aria-hidden="true"></i>';
                        dropdown.style.display = 'none';
                    });

                    dropdown.appendChild(item);
                });

                dropdown.style.display = 'block';
            }
        }
    };
});
