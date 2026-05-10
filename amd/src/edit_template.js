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
 * Interactions for the local_dimensions learning plan template edit page.
 *
 * Mirrors the relevant pieces of `local_dimensions/edit_competency` (section
 * navigation, action-bar submit, colour swatch preview) but skips the rule /
 * tree-model machinery — templates have no rules.
 *
 * @module     local_dimensions/edit_template
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {
    'use strict';
    // jQuery is required only to keep this module on the same dependency surface
    // as `local_dimensions/edit_competency`. Direct usage is intentionally avoided
    // here so the bundle stays small.
    void $;

    /**
     * Submit the Moodle form from the shell action button.
     *
     * @param {HTMLElement} root Root element.
     */
    function submitForm(root) {
        var form = root.querySelector('form.mform');
        if (!form) {
            return;
        }

        var submit = form.querySelector('#id_submitbutton, [type="submit"][name="submitbutton"]');
        if (form.requestSubmit) {
            form.requestSubmit(submit || undefined);
        } else if (submit) {
            submit.click();
        } else {
            form.submit();
        }
    }

    /**
     * Activate the nav link for a section.
     *
     * @param {HTMLElement} root Root element.
     * @param {String} id Section id.
     */
    function activateSection(root, id) {
        root.querySelectorAll('[data-section-link]').forEach(function(link) {
            var active = link.dataset.sectionLink === id;
            link.classList.toggle('active', active);
            if (active) {
                link.setAttribute('aria-current', 'page');
            } else {
                link.removeAttribute('aria-current');
            }
        });
    }

    /**
     * Initialise side navigation state.
     *
     * @param {HTMLElement} root Root element.
     */
    function initSectionNavigation(root) {
        var sections = Array.prototype.slice.call(root.querySelectorAll('[data-region="edit-section"]'));

        root.querySelectorAll('[data-section-link]').forEach(function(link) {
            link.addEventListener('click', function() {
                activateSection(root, link.dataset.sectionLink);
            });
        });

        if (!sections.length || typeof IntersectionObserver === 'undefined') {
            return;
        }

        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    activateSection(root, entry.target.id);
                }
            });
        }, {rootMargin: '-20% 0px -65% 0px', threshold: 0.01});

        sections.forEach(function(section) {
            observer.observe(section);
        });
    }

    /**
     * Return a normalized hex colour, or an empty string.
     *
     * @param {String} value Raw colour value.
     * @return {String}
     */
    function normalizeColour(value) {
        value = (value || '').trim();
        if (/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(value)) {
            return value;
        }
        return '';
    }

    /**
     * Add a live swatch next to a custom colour field.
     *
     * @param {String} fieldname Custom field shortname.
     */
    function initColourSwatch(fieldname) {
        if (!fieldname) {
            return;
        }

        var input = document.querySelector('[name="customfield_' + fieldname + '"]');
        if (!input || !input.parentNode || input.dataset.localDimensionsSwatch === '1') {
            return;
        }

        var row = document.createElement('span');
        var swatch = document.createElement('span');
        input.dataset.localDimensionsSwatch = '1';
        row.className = 'local-dimensions-edit-colour-row';
        swatch.className = 'local-dimensions-edit-colour-swatch';

        input.parentNode.insertBefore(row, input);
        row.appendChild(swatch);
        row.appendChild(input);

        var update = function() {
            var colour = normalizeColour(input.value);
            swatch.style.backgroundColor = colour || 'transparent';
            swatch.classList.toggle('empty', !colour);
        };

        input.addEventListener('input', update);
        update();
    }

    return {
        /**
         * Initialise the edit template UI.
         *
         * @param {Object} settings Page settings (templateId, backgroundColourField, textColourField).
         */
        init: function(settings) {
            var root = document.querySelector('[data-region="local-dimensions-edit-template"]');
            if (!root) {
                return;
            }

            initSectionNavigation(root);
            initColourSwatch(settings.backgroundColourField);
            initColourSwatch(settings.textColourField);

            root.addEventListener('click', function(event) {
                var actionTarget = event.target.closest('[data-action]');
                if (!actionTarget) {
                    return;
                }
                if (actionTarget.dataset.action === 'submit-edit-form') {
                    event.preventDefault();
                    submitForm(root);
                }
            });
        }
    };
});
