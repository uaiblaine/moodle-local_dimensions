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
 * Inject compiled custom CSS from a template source node.
 *
 * @module     local_dimensions/customcss_injector
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    'use strict';

    return {
        /**
         * Injects custom CSS found in a source element.
         *
         * @param {string} sourceid DOM id of an element containing compiled CSS.
         */
        init: function(sourceid) {
            if (!sourceid) {
                return;
            }

            var source = document.getElementById(sourceid);
            if (!source) {
                return;
            }

            var css = '';
            if (typeof source.value === 'string') {
                css = source.value.trim();
            } else if (source.textContent) {
                css = source.textContent.trim();
            }

            if (!css) {
                source.remove();
                return;
            }

            var styleid = sourceid + '-compiled';
            var style = document.getElementById(styleid);
            if (!style) {
                style = document.createElement('style');
                style.id = styleid;
                style.textContent = css;
                document.head.appendChild(style);
            }

            source.remove();
        }
    };
});
