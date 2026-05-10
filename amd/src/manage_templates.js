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
 * Interactions for the manage learning plan templates page.
 *
 * @module     local_dimensions/manage_templates
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'core/ajax',
    'core/notification',
    'core/str',
    'core/form-autocomplete',
    'tool_lp/actionselector'
], function(Ajax, Notification, Str, Autocomplete, ActionSelector) {
    'use strict';

    /**
     * Read JSON embedded in a template node.
     *
     * @param {HTMLElement} root Root element.
     * @param {String} region Data region.
     * @param {*} fallback Fallback value.
     * @return {*} Parsed JSON or fallback.
     */
    function readJson(root, region, fallback) {
        var node = root.querySelector('[data-region="' + region + '"]');
        if (!node) {
            return fallback;
        }
        try {
            return JSON.parse(node.textContent || '');
        } catch (error) {
            return fallback;
        }
    }

    /**
     * Submit the toolbar form.
     *
     * @param {HTMLFormElement} form Toolbar form.
     */
    function submitForm(form) {
        if (!form) {
            return;
        }
        form.submit();
    }

    /**
     * Decorate the category autocomplete suggestions and selection chip with a
     * template-count badge for each category that has at least one template.
     *
     * @param {HTMLSelectElement} originalSelect The original select element.
     */
    function decorateCategoryAutocomplete(originalSelect) {
        var counts = {};
        Array.prototype.forEach.call(originalSelect.options, function(opt) {
            var c = parseInt(opt.dataset.templatecount || '0', 10);
            if (c > 0) {
                counts[opt.value] = c;
            }
        });
        if (!Object.keys(counts).length) {
            return;
        }

        var container = originalSelect.parentNode;
        if (!container) {
            return;
        }
        var BADGE_CLASS = 'local-dimensions-category-count-badge';
        var SELECTION_CLASS = BADGE_CLASS + '-selection';

        /**
         * Build a count badge element.
         *
         * @param {Number} count Count value.
         * @param {String} [extraClass] Optional extra CSS class.
         * @return {HTMLElement} Badge element.
         */
        function buildBadge(count, extraClass) {
            var badge = document.createElement('span');
            badge.className = extraClass ? BADGE_CLASS + ' ' + extraClass : BADGE_CLASS;
            badge.textContent = String(count);
            return badge;
        }

        /**
         * Apply count badges to suggestion list items (idempotent).
         */
        function applyToListbox() {
            var listbox = container.querySelector('ul.form-autocomplete-suggestions');
            if (!listbox) {
                return;
            }
            listbox.querySelectorAll('li[role="option"][data-value]').forEach(function(li) {
                var count = counts[li.dataset.value];
                var existing = li.querySelector('.' + BADGE_CLASS);
                if (count) {
                    if (!existing) {
                        li.appendChild(buildBadge(count));
                    } else if (existing.textContent !== String(count)) {
                        existing.textContent = String(count);
                    }
                } else if (existing) {
                    existing.remove();
                }
            });
        }

        /**
         * Apply count badge to the selected category chip (idempotent).
         */
        function applyToSelection() {
            var selection = container.querySelector('.form-autocomplete-selection');
            if (!selection) {
                return;
            }
            selection.querySelectorAll('[role="option"][data-value]').forEach(function(item) {
                var count = counts[item.dataset.value];
                var existing = item.querySelector('.' + BADGE_CLASS);
                if (count) {
                    if (!existing) {
                        item.appendChild(buildBadge(count, SELECTION_CLASS));
                    } else if (existing.textContent !== String(count)) {
                        existing.textContent = String(count);
                    }
                } else if (existing) {
                    existing.remove();
                }
            });
        }

        /**
         * Apply badges to both listbox and selection.
         */
        function applyAll() {
            applyToListbox();
            applyToSelection();
        }

        applyAll();
        new MutationObserver(applyAll).observe(container, {childList: true, subtree: true});
    }

    /**
     * Enhance the course category selector with Moodle autocomplete.
     *
     * @param {HTMLElement} root Root element.
     */
    function initCategorySelect(root) {
        var categorySelect = root.querySelector('[data-region="category-select"]');
        if (!categorySelect) {
            return;
        }

        Autocomplete.enhance(
            '#' + categorySelect.id,
            false,
            false,
            categorySelect.dataset.placeholder || '',
            false,
            true,
            categorySelect.dataset.noSelection || '',
            true
        );

        decorateCategoryAutocomplete(categorySelect);

        categorySelect.addEventListener('change', function() {
            submitForm(this.form);
        });
    }

    /**
     * Constrain the details pane width to a sensible band.
     *
     * @param {Number} value Requested width.
     * @param {Number} minimum Minimum width.
     * @param {Number} maximum Maximum width.
     * @return {Number} Constrained width.
     */
    function clampDetailsWidth(value, minimum, maximum) {
        return Math.min(Math.max(value, minimum), maximum);
    }

    /**
     * Wire the resizer between the templates pane and the details aside.
     *
     * @param {HTMLElement} root Root element.
     */
    function initDetailsResize(root) {
        var storageKey = 'local_dimensions_manage_templates_details_width';
        var resizer = root.querySelector('[data-region="details-resizer"]');
        var details = root.querySelector('[data-region="details-pane"]');
        var body = root.querySelector('.local-dimensions-manage-body');
        var minimum = 288;
        var maximum = 640;
        var startX = 0;
        var startWidth = 0;

        if (!resizer || !details || !body) {
            return;
        }

        var applyWidth = function(width) {
            var bodyWidth = body.getBoundingClientRect().width;
            var availableMaximum = Math.max(minimum, Math.min(maximum, bodyWidth - 360));
            var nextWidth = clampDetailsWidth(width, minimum, availableMaximum);
            root.style.setProperty('--local-dimensions-details-width', nextWidth + 'px');
            resizer.setAttribute('aria-valuenow', String(Math.round(nextWidth)));
            return nextWidth;
        };

        try {
            var storedWidth = Number(window.localStorage.getItem(storageKey));
            if (storedWidth) {
                applyWidth(storedWidth);
            }
        } catch (error) {
            // Local storage may be unavailable in restricted browser contexts.
        }

        resizer.setAttribute('aria-valuemin', String(minimum));
        resizer.setAttribute('aria-valuemax', String(maximum));

        resizer.addEventListener('pointerdown', function(event) {
            event.preventDefault();
            startX = event.clientX;
            startWidth = details.getBoundingClientRect().width;
            root.classList.add('resizing-details');
            resizer.setPointerCapture(event.pointerId);
        });

        resizer.addEventListener('pointermove', function(event) {
            if (!root.classList.contains('resizing-details')) {
                return;
            }
            applyWidth(startWidth + startX - event.clientX);
        });

        resizer.addEventListener('pointerup', function(event) {
            if (!root.classList.contains('resizing-details')) {
                return;
            }
            var width = applyWidth(details.getBoundingClientRect().width);
            root.classList.remove('resizing-details');
            resizer.releasePointerCapture(event.pointerId);
            try {
                window.localStorage.setItem(storageKey, String(Math.round(width)));
            } catch (error) {
                // Local storage may be unavailable in restricted browser contexts.
            }
        });

        resizer.addEventListener('dblclick', function() {
            root.style.removeProperty('--local-dimensions-details-width');
            try {
                window.localStorage.removeItem(storageKey);
            } catch (error) {
                // Local storage may be unavailable in restricted browser contexts.
            }
        });
    }

    /**
     * Set text content in a details field.
     *
     * @param {HTMLElement} root Root element.
     * @param {String} region Data region.
     * @param {String} value Value.
     */
    function setDetailsText(root, region, value) {
        var element = root.querySelector('[data-region="' + region + '"]');
        if (element) {
            element.textContent = value || '';
        }
    }

    /**
     * Set inner HTML in a details field. Caller is responsible for trusting the source.
     *
     * @param {HTMLElement} root Root element.
     * @param {String} region Data region.
     * @param {String} html HTML payload (already format_text-cleaned server-side).
     */
    function setDetailsHtml(root, region, html) {
        var element = root.querySelector('[data-region="' + region + '"]');
        if (element) {
            element.innerHTML = html || '';
        }
    }

    /**
     * Build a chip element with the given label and modifier class.
     *
     * @param {String} label Chip label.
     * @param {String} [modifier] Extra CSS class.
     * @return {HTMLElement} Chip element.
     */
    function buildChip(label, modifier) {
        var chip = document.createElement('span');
        chip.className = 'local-dimensions-manage-chip' + (modifier ? ' ' + modifier : '');
        chip.textContent = label;
        return chip;
    }

    /**
     * Populate the details aside with the selected template's data.
     *
     * @param {HTMLElement} root Root element.
     * @param {Object} template Template model from the embedded JSON.
     */
    function renderDetails(root, template) {
        var empty = root.querySelector('[data-region="details-empty"]');
        var content = root.querySelector('[data-region="details-content"]');
        if (!empty || !content) {
            return;
        }
        empty.hidden = true;
        content.hidden = false;

        setDetailsText(root, 'details-title', template.shortname);
        setDetailsText(root, 'details-idnumber', template.hasidnumber ? template.idnumber : '');
        setDetailsText(root, 'details-duedate', template.hasduedate ? template.duedateformatted : '—');
        setDetailsText(root, 'details-plans', String(template.plancount || 0));
        setDetailsText(root, 'details-cohorts', String(template.cohortcount || 0));
        setDetailsHtml(root, 'details-description', template.hasdescription ? template.description : '');

        var chips = root.querySelector('[data-region="details-chips"]');
        if (chips) {
            chips.innerHTML = '';
            if (template.hidden) {
                chips.appendChild(buildChip(
                    root.dataset.stringHidden || 'Hidden',
                    'local-dimensions-template-card-chip-hidden'
                ));
            }
            if (template.hastype) {
                chips.appendChild(buildChip(template.type, 'local-dimensions-template-card-chip-type'));
            }
            if (template.hastag1) {
                chips.appendChild(buildChip(template.tag1, 'local-dimensions-template-card-chip-tag'));
            }
            if (template.hastag2) {
                chips.appendChild(buildChip(template.tag2, 'local-dimensions-template-card-chip-tag'));
            }
        }

        var editLink = content.querySelector('[data-region="details-edit"]');
        if (editLink) {
            editLink.href = template.editurl;
        }
        var cohortsLink = content.querySelector('[data-region="details-cohorts-action"]');
        if (cohortsLink) {
            cohortsLink.href = template.cohortsurl;
        }
        var plansLink = content.querySelector('[data-region="details-plans-action"]');
        if (plansLink) {
            plansLink.href = template.plansurl;
        }
        var duplicateBtn = content.querySelector('[data-region="details-duplicate"]');
        if (duplicateBtn) {
            duplicateBtn.dataset.id = String(template.id);
        }
        var deleteBtn = content.querySelector('[data-region="details-delete"]');
        if (deleteBtn) {
            deleteBtn.dataset.id = String(template.id);
            deleteBtn.dataset.name = template.shortname;
        }
    }

    /**
     * Build the edit_template URL for a duplicated template, preserving page state.
     *
     * @param {HTMLElement} root Root element.
     * @param {Number} newTemplateId New template ID.
     * @return {String} URL to redirect to after duplication.
     */
    function buildEditTemplateUrl(root, newTemplateId) {
        var pageContextId = root.dataset.pageContextId || '0';
        return M.cfg.wwwroot + '/local/dimensions/edit_template.php' +
            '?id=' + encodeURIComponent(String(newTemplateId)) +
            '&pagecontextid=' + encodeURIComponent(String(pageContextId));
    }

    /**
     * Run `core_competency_delete_template` and reload the page on success.
     *
     * @param {Number} templateId Template ID.
     * @param {Boolean} deleteplans Whether linked plans should be deleted (true) or just unlinked (false).
     */
    function runDeleteTemplate(templateId, deleteplans) {
        Ajax.call([{
            methodname: 'core_competency_delete_template',
            args: {id: templateId, deleteplans: deleteplans}
        }])[0]
        .then(function() {
            window.location.reload();
            return null;
        })
        .catch(Notification.exception);
    }

    /**
     * Confirm and run the delete-template flow against the native AJAX service.
     *
     * Mirrors the native tool_lp UX: when the template has no related plans,
     * a single Confirm/Cancel dialog. When plans exist, an action-selector
     * modal with two radio choices ("Delete the learning plans" /
     * "Unlink the learning plans from their template") so the admin makes the
     * deleteplans decision the same way they would in core's templates UI.
     *
     * @param {Number} templateId Template ID.
     * @param {String} templateName Template short name (for the dialog body).
     */
    function confirmDelete(templateId, templateName) {
        Ajax.call([{
            methodname: 'core_competency_template_has_related_data',
            args: {id: templateId}
        }])[0]
        .then(function(hasrelated) {
            if (hasrelated) {
                return Str.get_strings([
                    {key: 'deletetemplate', component: 'tool_lp', param: templateName},
                    {key: 'deletetemplatewithplans', component: 'tool_lp'},
                    {key: 'deleteplans', component: 'tool_lp'},
                    {key: 'unlinkplanstemplate', component: 'tool_lp'},
                    {key: 'confirm', component: 'moodle'},
                    {key: 'cancel', component: 'moodle'}
                ])
                .then(function(strings) {
                    var actions = [
                        {text: strings[2], value: 'delete'},
                        {text: strings[3], value: 'unlink'}
                    ];
                    var selector = new ActionSelector(
                        strings[0], // Title.
                        strings[1], // Body message.
                        actions, // Radio options.
                        strings[4], // Confirm.
                        strings[5]  // Cancel.
                    );
                    selector.display();
                    selector.on('save', function(e, data) {
                        runDeleteTemplate(templateId, data.action === 'delete');
                    });
                    return null;
                });
            }
            return Str.get_strings([
                {key: 'confirm', component: 'moodle'},
                {key: 'deletetemplate', component: 'tool_lp', param: templateName},
                {key: 'delete', component: 'moodle'},
                {key: 'cancel', component: 'moodle'}
            ])
            .then(function(strings) {
                Notification.confirm(strings[0], strings[1], strings[2], strings[3], function() {
                    runDeleteTemplate(templateId, true);
                });
                return null;
            });
        })
        .catch(Notification.exception);
    }

    /**
     * Run the duplicate-template flow against the native AJAX service.
     * On success redirects the user to the edit page for the duplicated template.
     *
     * @param {HTMLElement} root Root element.
     * @param {Number} templateId Template ID.
     */
    function duplicateTemplate(root, templateId) {
        Ajax.call([{
            methodname: 'core_competency_duplicate_template',
            args: {id: templateId}
        }])[0]
        .then(function(newtemplate) {
            if (!newtemplate || !newtemplate.id) {
                return null;
            }
            window.location.href = buildEditTemplateUrl(root, newtemplate.id);
            return null;
        })
        .catch(Notification.exception);
    }

    /**
     * Apply the search filter across cards/rows.
     *
     * @param {HTMLElement} root Root element.
     */
    function applyFilters(root) {
        var queryInput = root.querySelector('[data-region="template-search"]');
        var query = queryInput ? queryInput.value.trim().toLowerCase() : '';
        var visibleCount = 0;
        var rows = root.querySelectorAll('[data-region="template-row"]');

        rows.forEach(function(row) {
            var haystack = (row.dataset.search || '').toLowerCase();
            var visible = !query || haystack.indexOf(query) !== -1;
            row.hidden = !visible;
            if (visible) {
                visibleCount++;
            }
        });

        var noResults = root.querySelector('[data-region="no-results"]');
        if (noResults) {
            noResults.hidden = visibleCount !== 0;
        }
    }

    /**
     * Mark a row/card as selected.
     *
     * @param {HTMLElement} root Root element.
     * @param {HTMLElement} row Newly selected row.
     */
    function markSelected(root, row) {
        root.querySelectorAll('[data-region="template-row"].selected').forEach(function(other) {
            if (other !== row) {
                other.classList.remove('selected');
                other.removeAttribute('aria-selected');
            }
        });
        row.classList.add('selected');
        row.setAttribute('aria-selected', 'true');
    }

    return {
        /**
         * Initialise the manage templates UI.
         */
        init: function() {
            var root = document.querySelector('[data-region="local-dimensions-manage-templates"]');
            if (!root) {
                return;
            }

            var templates = readJson(root, 'templates-model', []);
            var templatesById = {};
            templates.forEach(function(tpl) {
                tpl.id = Number(tpl.id);
                templatesById[tpl.id] = tpl;
            });

            initCategorySelect(root);
            initDetailsResize(root);

            var showHidden = root.querySelector('[data-region="show-hidden-templates"]');
            if (showHidden) {
                showHidden.addEventListener('change', function() {
                    submitForm(this.form);
                });
            }

            var search = root.querySelector('[data-region="template-search"]');
            if (search) {
                search.addEventListener('input', function() {
                    applyFilters(root);
                });
            }

            var showIds = root.querySelector('[data-region="show-ids"]');
            if (showIds) {
                showIds.addEventListener('change', function() {
                    root.classList.toggle('local-dimensions-manage-hide-ids', !this.checked);
                });
            }

            // Toolbar segmented buttons (context, view).
            root.addEventListener('click', function(event) {
                var actionTarget = event.target.closest('[data-action]');
                if (!actionTarget) {
                    return;
                }
                var action = actionTarget.dataset.action;

                if (action === 'view') {
                    event.preventDefault();
                    var viewInput = root.querySelector('[data-region="view-input"]');
                    if (viewInput) {
                        viewInput.value = actionTarget.dataset.view;
                    }
                    var viewForm = root.querySelector('[data-region="manage-form"]');
                    if (viewForm && root.dataset.view !== actionTarget.dataset.view) {
                        submitForm(viewForm);
                    }
                    return;
                }

                if (action === 'context') {
                    event.preventDefault();
                    var contextInput = root.querySelector('[data-region="context-type-input"]');
                    var contextType = actionTarget.dataset.contextType;
                    if (!contextInput || contextInput.value === contextType) {
                        return;
                    }
                    contextInput.value = contextType;
                    var categorySelect = root.querySelector('[data-region="category-select"]');
                    if (categorySelect) {
                        categorySelect.value = '0';
                    }
                    submitForm(root.querySelector('[data-region="manage-form"]'));
                    return;
                }

                if (action === 'duplicate') {
                    event.preventDefault();
                    var duplicateId = Number(actionTarget.dataset.id);
                    if (duplicateId > 0) {
                        duplicateTemplate(root, duplicateId);
                    }
                    return;
                }

                if (action === 'delete') {
                    event.preventDefault();
                    var deleteId = Number(actionTarget.dataset.id);
                    var deleteName = actionTarget.dataset.name || '';
                    if (deleteId > 0) {
                        confirmDelete(deleteId, deleteName);
                    }
                }
            });

            // Row / card selection populates the details aside. Ignore clicks on action buttons inside the row.
            root.addEventListener('click', function(event) {
                if (event.target.closest('a, button')) {
                    return;
                }
                var row = event.target.closest('[data-region="template-row"]');
                if (!row) {
                    return;
                }
                var id = Number(row.dataset.templateId);
                var template = templatesById[id];
                if (!template) {
                    return;
                }
                markSelected(root, row);
                renderDetails(root, template);
            });

            // Keyboard activation (Enter / Space) on focused cards.
            root.addEventListener('keydown', function(event) {
                if (event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }
                var row = event.target.closest('[data-region="template-row"]');
                if (!row || event.target.closest('a, button')) {
                    return;
                }
                event.preventDefault();
                var id = Number(row.dataset.templateId);
                var template = templatesById[id];
                if (!template) {
                    return;
                }
                markSelected(root, row);
                renderDetails(root, template);
            });
        }
    };
});
