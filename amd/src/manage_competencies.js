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
 * Interactions for the manage competencies page.
 *
 * @module     local_dimensions/manage_competencies
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'jquery',
    'core/ajax',
    'core/notification',
    'core/str',
    'core/templates',
    'tool_lp/dialogue',
    'tool_lp/tree',
    'tool_lp/competencypicker',
    'tool_lp/competencyruleconfig',
    'tool_lp/competency_outcomes',
    'core/pending'
], function($, Ajax, Notification, Str, Templates, Dialogue, Ariatree, Picker, RuleConfig, Outcomes, Pending) {
    'use strict';

    var moveSource = null;
    var moveTarget = 0;
    var relatedTarget = null;
    var pickerInstance = null;
    var ruleConfigInstance = null;
    var relatedCompetenciesCache = {};

    /**
     * Get the competency row containing a target.
     *
     * @param {HTMLElement} target Event target.
     * @return {HTMLElement|null} Competency row.
     */
    function getCompetencyRow(target) {
        return target.closest('[data-region="competency-row"]');
    }

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
     * Create a tree model compatible with native tool_lp rule widgets.
     *
     * @param {HTMLElement} root Root element.
     * @return {Object} Tree model.
     */
    function createTreeModel(root) {
        var competencies = readJson(root, 'competency-model', []);
        var byId = {};

        competencies.forEach(function(competency) {
            competency.id = Number(competency.id);
            competency.parentid = Number(competency.parentid);
            competency.competencyframeworkid = Number(competency.competencyframeworkid);
            competency.ruleoutcome = Number(competency.ruleoutcome || 0);
            byId[competency.id] = competency;
        });

        return {
            getCompetencyFrameworkId: function() {
                return competencies.length ? competencies[0].competencyframeworkid : 0;
            },

            getChildren: function(id) {
                id = Number(id);
                return competencies.filter(function(competency) {
                    return competency.parentid === id;
                });
            },

            getCompetency: function(id) {
                return byId[Number(id)];
            },

            getCompetencyLevel: function(id) {
                var competency = this.getCompetency(id);
                if (!competency || !competency.path) {
                    return 0;
                }
                return competency.path.replace(/^\/|\/$/g, '').split('/').length;
            },

            hasChildren: function(id) {
                return this.getChildren(id).length > 0;
            },

            hasRule: function(id) {
                var competency = this.getCompetency(id);
                return !!competency && competency.ruleoutcome !== Outcomes.NONE && !!competency.ruletype;
            },

            updateRule: function(id, config) {
                var competency = this.getCompetency(id);
                if (competency) {
                    competency.ruletype = config.ruletype;
                    competency.ruleoutcome = Number(config.ruleoutcome || 0);
                    competency.ruleconfig = config.ruleconfig;
                }
            }
        };
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
     * Add local styling hooks to native tool_lp dialogues rendered outside the page root.
     */
    function markNativeDialogues() {
        $('[data-region="competencymovetree"]').closest('.moodle-dialogue').addClass(
            'local-dimensions-dialogue local-dimensions-dialogue-move'
        );
        $('[data-region="competencylinktree"]').closest('.moodle-dialogue').addClass(
            'local-dimensions-dialogue local-dimensions-dialogue-related'
        );
        $('[data-region="competencyruleconfig"]').closest('.moodle-dialogue').addClass(
            'local-dimensions-dialogue local-dimensions-dialogue-rule'
        );
    }

    /**
     * Initialise local behaviour for native dialogues that may be rendered asynchronously.
     */
    function enhanceNativeDialogues() {
        markNativeDialogues();
        initRelatedPickerPopup();
        initRuleConfigPopup();
    }

    /**
     * Schedule local hooks after native dialogues have finished rendering.
     */
    function scheduleNativeDialogueEnhancement() {
        enhanceNativeDialogues();

        if (typeof MutationObserver === 'undefined') {
            return;
        }

        var observer = new MutationObserver(function(mutations) {
            var shouldEnhance = mutations.some(function(mutation) {
                return Array.prototype.slice.call(mutation.addedNodes).some(function(node) {
                    if (!node.querySelector) {
                        return false;
                    }
                    return (node.classList && node.classList.contains('moodle-dialogue'))
                        || node.querySelector('[data-region="competencymovetree"]')
                        || node.querySelector('[data-region="competencylinktree"]')
                        || node.querySelector('[data-region="competencyruleconfig"]');
                });
            });

            if (shouldEnhance) {
                enhanceNativeDialogues();
                observer.disconnect();
            }
        });

        observer.observe(document.body, {childList: true, subtree: true});
        setTimeout(function() {
            observer.disconnect();
        }, 5000);
    }

    /**
     * Move focus away from page controls before Moodle hides the page behind a modal dialogue.
     */
    function releasePageFocus() {
        if (document.activeElement && document.activeElement !== document.body) {
            document.activeElement.blur();
        }
    }

    /**
     * Expand ancestors for a visible tree row so search results are not hidden by collapsed branches.
     *
     * @param {HTMLElement} row Visible tree row.
     */
    function expandVisibleTreeAncestors(row) {
        var node = row.closest('[data-region="tree-node"]');
        while (node) {
            node.classList.remove('collapsed');
            var parentChildren = node.parentElement ? node.parentElement.closest('[data-region="tree-children"]') : null;
            node = parentChildren ? parentChildren.closest('[data-region="tree-node"]') : null;
        }
    }

    /**
     * Return a number constrained to the sidebar width limits.
     *
     * @param {Number} value Width in pixels.
     * @param {Number} minimum Minimum width.
     * @param {Number} maximum Maximum width.
     * @return {Number} Constrained width.
     */
    function clampDetailsWidth(value, minimum, maximum) {
        return Math.max(minimum, Math.min(maximum, value));
    }

    /**
     * Initialise client-side resizing for the details sidebar.
     *
     * @param {HTMLElement} root Root element.
     */
    function initDetailsResize(root) {
        var storageKey = 'local_dimensions_manage_details_width';
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

        resizer.addEventListener('keydown', function(event) {
            var increment = event.shiftKey ? 48 : 24;
            var currentWidth = details.getBoundingClientRect().width;
            var nextWidth = currentWidth;

            if (event.key === 'ArrowLeft') {
                nextWidth = currentWidth + increment;
            } else if (event.key === 'ArrowRight') {
                nextWidth = currentWidth - increment;
            } else {
                return;
            }

            event.preventDefault();
            try {
                window.localStorage.setItem(storageKey, String(Math.round(applyWidth(nextWidth))));
            } catch (error) {
                // Local storage may be unavailable in restricted browser contexts.
            }
        });
    }

    /**
     * Replace the related competencies section if the same competency remains selected.
     *
     * @param {HTMLElement} root Root element.
     * @param {Number} id Competency ID.
     * @param {String} html Rendered HTML.
     * @param {String} js Rendered JS.
     * @return {Promise|Boolean}
     */
    function replaceRelatedCompetencies(root, id, html, js) {
        var container = root.querySelector('[data-region="details-related"]');
        if (!container || Number(root.dataset.selectedCompetencyId || 0) !== Number(id)) {
            return $.Deferred().resolve().promise();
        }

        return Templates.replaceNodeContents($(container), html, js);
    }

    /**
     * Render native related competencies in the details panel.
     *
     * @param {HTMLElement} root Root element.
     * @param {Number} id Competency ID.
     */
    function renderRelatedCompetencies(root, id) {
        var container = root.querySelector('[data-region="details-related"]');
        if (!container) {
            return;
        }

        if (relatedCompetenciesCache[id]) {
            replaceRelatedCompetencies(
                root,
                id,
                relatedCompetenciesCache[id].html,
                relatedCompetenciesCache[id].js
            ).catch(Notification.exception);
            return;
        }

        Templates.render('tool_lp/loading', {}).then(function(html, js) {
            return Templates.replaceNodeContents($(container), html, js);
        }).then(function() {
            return Ajax.call([{
                methodname: 'tool_lp_data_for_related_competencies_section',
                args: {competencyid: id}
            }])[0];
        }).then(function(context) {
            return Templates.render('tool_lp/related_competencies', context);
        }).then(function(html, js) {
            relatedCompetenciesCache[id] = {html: html, js: js};
            return replaceRelatedCompetencies(root, id, html, js);
        }).catch(Notification.exception);
    }

    /**
     * Select a competency row and populate contextual details.
     *
     * @param {HTMLElement} root Root element.
     * @param {HTMLElement} row Competency row.
     * @param {Object} treeModel Tree model.
     * @return {Object|undefined} Selected competency model.
     */
    function selectCompetency(root, row, treeModel) {
        var selectedRows = root.querySelectorAll('[data-region="competency-row"].selected');
        selectedRows.forEach(function(selectedRow) {
            selectedRow.classList.remove('selected');
        });
        row.classList.add('selected');
        root.dataset.selectedCompetencyId = row.dataset.competencyId;

        var empty = root.querySelector('[data-region="details-empty"]');
        var content = root.querySelector('[data-region="details-content"]');
        if (empty) {
            empty.hidden = true;
        }
        if (content) {
            content.hidden = false;
        }

        setDetailsText(root, 'details-taxonomy', row.dataset.taxonomy);
        setDetailsText(root, 'details-title', row.dataset.shortname);
        setDetailsText(root, 'details-idnumber', row.dataset.idnumber);
        setDetailsText(root, 'details-path', row.dataset.path);
        setDetailsText(root, 'details-scale', row.dataset.scale);
        setDetailsText(root, 'details-rule', row.dataset.rule);
        setDetailsText(root, 'details-courses', row.dataset.coursecount);
        setDetailsText(root, 'details-description', row.dataset.description);

        var editLink = root.querySelector('[data-region="details-edit"]');
        if (editLink) {
            editLink.href = row.dataset.editUrl;
        }
        var addLink = root.querySelector('[data-region="details-add"]');
        if (addLink) {
            addLink.href = row.dataset.addUrl;
        }
        var primaryAdd = root.querySelector('[data-region="primary-add"]');
        if (primaryAdd) {
            primaryAdd.href = row.dataset.addUrl;
            var primaryAddLabel = root.querySelector('[data-region="primary-add-label"]');
            if (primaryAddLabel) {
                primaryAddLabel.textContent = primaryAdd.dataset.childLabelTemplate.replace('__NAME__', row.dataset.shortname);
            }
        }

        [
            '[data-region="details-move"]',
            '[data-region="details-related-action"]',
            '[data-region="details-rules"]',
            '[data-action="linkedcourses"]'
        ].forEach(function(selector) {
            var button = content ? content.querySelector(selector) : null;
            if (button) {
                button.dataset.id = row.dataset.competencyId;
            }
        });

        renderRelatedCompetencies(root, Number(row.dataset.competencyId));

        Ajax.call([{
            methodname: 'core_competency_competency_viewed',
            args: {id: Number(row.dataset.competencyId)}
        }])[0].fail(function() {
            // Viewing logs are non-critical for the management interaction.
        });

        return treeModel.getCompetency(row.dataset.competencyId);
    }

    /**
     * Apply search filtering to tree and table rows.
     *
     * @param {HTMLElement} root Root element.
     */
    function applyFilters(root) {
        var queryInput = root.querySelector('[data-region="competency-search"]');
        var query = queryInput ? queryInput.value.trim().toLowerCase() : '';
        var visibleCount = 0;
        var rows = root.querySelectorAll('[data-region="competency-row"]');

        rows.forEach(function(row) {
            var haystack = (row.dataset.search || '').toLowerCase();
            var visible = !query || haystack.indexOf(query) !== -1;
            row.hidden = !visible;
            if (visible) {
                visibleCount++;
            }
        });

        root.querySelectorAll('[data-region="tree-node"]').forEach(function(node) {
            var directRow = node.querySelector(':scope > [data-region="competency-row"]');
            var visibleDescendant = node.querySelector('[data-region="competency-row"]:not([hidden])');
            node.hidden = !(directRow && !directRow.hidden) && !visibleDescendant;
        });

        if (query) {
            root.querySelectorAll('.local-dimensions-manage-tree-row[data-region="competency-row"]:not([hidden])')
                .forEach(expandVisibleTreeAncestors);
        }

        var noResults = root.querySelector('[data-region="no-results"]');
        if (noResults) {
            noResults.hidden = visibleCount !== 0;
        }
    }

    /**
     * Reorder a competency using a core service.
     *
     * @param {String} methodName Core service method.
     * @param {Number} id Competency ID.
     */
    function reorderCompetency(methodName, id) {
        Ajax.call([{
            methodname: methodName,
            args: {id: id}
        }])[0].done(function() {
            window.location.reload();
        }).fail(Notification.exception);
    }

    /**
     * Confirm and delete a competency.
     *
     * @param {Number} id Competency ID.
     * @param {Object} treeModel Tree model.
     */
    function confirmDelete(id, treeModel) {
        var competency = treeModel.getCompetency(id) || {};
        var confirmMessage = treeModel.hasRule(competency.parentid) ? 'deletecompetencyparenthasrule' : 'deletecompetency';

        Str.get_strings([
            {key: 'confirm', component: 'moodle'},
            {key: confirmMessage, component: 'tool_lp', param: competency.shortname || ''},
            {key: 'delete', component: 'moodle'},
            {key: 'cancel', component: 'moodle'}
        ]).done(function(strings) {
            Notification.confirm(strings[0], strings[1], strings[2], strings[3], function() {
                Ajax.call([{
                    methodname: 'core_competency_delete_competency',
                    args: {id: id}
                }])[0].done(function(success) {
                    if (success === false) {
                        Str.get_string('competencycannotbedeleted', 'tool_lp', competency.shortname || '')
                            .done(function(message) {
                                Notification.alert(null, message);
                            }).fail(Notification.exception);
                        return;
                    }
                    window.location.reload();
                }).fail(Notification.exception);
            });
        }).fail(Notification.exception);
    }

    /**
     * Show courses linked to a competency.
     *
     * @param {Number} id Competency ID.
     */
    function showLinkedCourses(id) {
        Ajax.call([{
            methodname: 'tool_lp_list_courses_using_competency',
            args: {id: id}
        }])[0].done(function(courses) {
            Templates.render('tool_lp/linked_courses_summary', {courses: courses}).done(function(html) {
                Str.get_string('linkedcourses', 'tool_lp').done(function(title) {
                    new Dialogue(title, html);
                }).fail(Notification.exception);
            }).fail(Notification.exception);
        }).fail(Notification.exception);
    }

    /**
     * Add child competencies to a native move tree node.
     *
     * @param {Object} parent Parent competency.
     * @param {Object[]} competencies Flat competencies.
     */
    function addCompetencyChildren(parent, competencies) {
        competencies.forEach(function(competency) {
            if (String(competency.parentid) === String(parent.id)) {
                parent.haschildren = true;
                competency.children = [];
                competency.haschildren = false;
                parent.children.push(competency);
                addCompetencyChildren(competency, competencies);
            }
        });
    }

    /**
     * Move the selected competency to the chosen parent.
     */
    function doMove() {
        Ajax.call([{
            methodname: 'core_competency_set_parent_competency',
            args: {competencyid: moveSource, parentid: moveTarget}
        }])[0].done(function() {
            window.location.reload();
        }).fail(Notification.exception);
    }

    /**
     * Confirm native rule reset side effects before moving.
     *
     * @param {Object} treeModel Tree model.
     */
    function confirmMove(treeModel) {
        moveTarget = typeof moveTarget === 'undefined' ? 0 : Number(moveTarget);
        if (Number(moveTarget) === Number(moveSource)) {
            return;
        }

        var targetComp = treeModel.getCompetency(moveTarget) || {};
        var sourceComp = treeModel.getCompetency(moveSource) || {};
        var confirmMessage = 'movecompetencywillresetrules';
        var showConfirm = false;

        if (Number(sourceComp.parentid) === Number(moveTarget)) {
            return;
        }

        if (targetComp.path && targetComp.path.indexOf('/' + sourceComp.id + '/') >= 0) {
            confirmMessage = 'movecompetencytochildofselfwillresetrules';
            showConfirm = showConfirm || treeModel.hasRule(sourceComp.id);
        }

        showConfirm = showConfirm || treeModel.hasRule(targetComp.id) || treeModel.hasRule(sourceComp.parentid);

        if (showConfirm) {
            Str.get_strings([
                {key: 'confirm', component: 'moodle'},
                {key: confirmMessage, component: 'tool_lp'},
                {key: 'yes', component: 'moodle'},
                {key: 'no', component: 'moodle'}
            ]).done(function(strings) {
                Notification.confirm(strings[0], strings[1], strings[2], strings[3], doMove);
            }).fail(Notification.exception);
        } else {
            doMove();
        }
    }

    /**
     * Store the selected target from a native move tree node.
     *
     * @param {HTMLElement|jQuery} node Tree node or child element.
     */
    function setMoveTarget(node) {
        var item = $(node).is('li') ? $(node) : $(node).closest('li');
        var targetId = item.data('id');
        moveTarget = typeof targetId === 'undefined' ? 0 : Number(targetId);
    }

    /**
     * Initialise the native move tree dialogue.
     *
     * @param {Object} popup Dialogue instance.
     * @param {Object} treeModel Tree model.
     */
    function initMovePopup(popup, treeModel) {
        var body = $(popup.getContent());
        var treeRoot = body.find('[data-enhance=movetree]');
        var tree = new Ariatree(treeRoot, false);
        tree.on('selectionchanged', function(evt, params) {
            setMoveTarget(params.selected);
        });
        treeRoot.find('li > span').on('click', function() {
            setMoveTarget(this);
        });
        treeRoot.show();
        enhanceNativeDialogues();

        body.on('click', '[data-action="move"]', function() {
            popup.close();
            confirmMove(treeModel);
        });
        body.on('click', '[data-action="cancel"]', function() {
            popup.close();
        });
    }

    /**
     * Trigger the native picker selection handler from the current tree state.
     *
     * @param {jQuery} treeRoot Native related competencies tree.
     */
    function syncRelatedPickerSelection(treeRoot) {
        treeRoot.trigger('selectionchanged', {selected: treeRoot.find('li[aria-selected="true"]')});
    }

    /**
     * Add a click fallback to the native related competencies picker.
     */
    function initRelatedPickerPopup() {
        var region = $('[data-region="competencylinktree"]');
        var treeRoot = region.find('[data-enhance=linktree]');

        if (!treeRoot.length || treeRoot.data('local-dimensions-selection-fallback')) {
            return;
        }

        treeRoot.data('local-dimensions-selection-fallback', true);
        treeRoot.find('li > span').on('click', function() {
            var item = $(this).closest('li');

            setTimeout(function() {
                var selectedWithId = treeRoot.find('li[aria-selected="true"]').filter(function() {
                    return typeof $(this).data('id') !== 'undefined';
                });
                if (!selectedWithId.length && typeof item.data('id') !== 'undefined') {
                    item.attr('aria-selected', 'true');
                }
                syncRelatedPickerSelection(treeRoot);
            }, 0);
        });

        region.on(
            'click.localDimensionsPicker change.localDimensionsPicker',
            '[data-region="filtercompetencies"] button, [data-action="chooseframework"]',
            function() {
                setTimeout(initRelatedPickerPopup, 250);
            }
        );
    }

    /**
     * Open the native move-to-parent dialogue.
     *
     * @param {Number} id Competency ID.
     * @param {Object} treeModel Tree model.
     */
    function showMoveDialog(id, treeModel) {
        var competency = treeModel.getCompetency(id);
        if (!competency) {
            return;
        }

        moveSource = competency.id;
        moveTarget = 0;

        var requests = Ajax.call([{
            methodname: 'core_competency_search_competencies',
            args: {
                competencyframeworkid: competency.competencyframeworkid,
                searchtext: ''
            }
        }, {
            methodname: 'core_competency_read_competency_framework',
            args: {id: competency.competencyframeworkid}
        }]);

        $.when.apply(null, requests).done(function(competencies, framework) {
            var competenciestree = [];
            competencies.forEach(function(onecompetency) {
                if (String(onecompetency.parentid) === '0') {
                    onecompetency.children = [];
                    onecompetency.haschildren = false;
                    competenciestree.push(onecompetency);
                    addCompetencyChildren(onecompetency, competencies);
                }
            });

            Str.get_strings([
                {key: 'movecompetency', component: 'tool_lp', param: competency.shortname},
                {key: 'move', component: 'tool_lp'},
                {key: 'cancel', component: 'moodle'}
            ]).done(function(strings) {
                Templates.render('tool_lp/competencies_move_tree', {
                    framework: framework,
                    competencies: competenciestree
                }).done(function(tree) {
                    new Dialogue(strings[0], tree, function(popup) {
                        initMovePopup(popup, treeModel);
                    });
                }).fail(Notification.exception);
            }).fail(Notification.exception);
        }).fail(Notification.exception);
    }

    /**
     * Open the native competency picker for related competencies.
     *
     * @param {HTMLElement} root Root element.
     * @param {Number} id Competency ID.
     * @param {Object} treeModel Tree model.
     */
    function showRelatedPicker(root, id, treeModel) {
        relatedTarget = treeModel.getCompetency(id);
        if (!relatedTarget) {
            return;
        }

        if (!pickerInstance) {
            pickerInstance = new Picker(Number(root.dataset.pageContextId), relatedTarget.competencyframeworkid);
            pickerInstance.on('save', function(e, data) {
                var pendingPromise = new Pending();
                var calls = [];
                var targetId = relatedTarget.id;

                data.competencyIds.forEach(function(competencyid) {
                    delete relatedCompetenciesCache[competencyid];
                    calls.push({
                        methodname: 'core_competency_add_related_competency',
                        args: {competencyid: competencyid, relatedcompetencyid: targetId}
                    });
                });

                calls.push({
                    methodname: 'tool_lp_data_for_related_competencies_section',
                    args: {competencyid: targetId}
                });

                var promises = Ajax.call(calls);
                promises[calls.length - 1].then(function(context) {
                    return Templates.render('tool_lp/related_competencies', context);
                }).then(function(html, js) {
                    relatedCompetenciesCache[targetId] = {html: html, js: js};
                    return replaceRelatedCompetencies(root, targetId, html, js);
                }).then(pendingPromise.resolve).catch(Notification.exception);
            });
        }

        pickerInstance.setDisallowedCompetencyIDs([relatedTarget.id]);
        pickerInstance.display().then(function() {
            if (typeof window.requestAnimationFrame === 'function') {
                window.requestAnimationFrame(enhanceNativeDialogues);
            } else {
                enhanceNativeDialogues();
            }
            return;
        }).catch(Notification.exception);
    }

    /**
     * Delete a relation from the selected competency.
     *
     * @param {HTMLElement} root Root element.
     * @param {Number} relatedid Related competency ID.
     */
    function deleteRelatedCompetency(root, relatedid) {
        var competencyid = Number(root.dataset.selectedCompetencyId || 0);
        if (!competencyid) {
            return;
        }

        delete relatedCompetenciesCache[relatedid];

        var calls = Ajax.call([{
            methodname: 'core_competency_remove_related_competency',
            args: {relatedcompetencyid: relatedid, competencyid: competencyid}
        }, {
            methodname: 'tool_lp_data_for_related_competencies_section',
            args: {competencyid: competencyid}
        }]);

        calls[1].then(function(context) {
            return Templates.render('tool_lp/related_competencies', context);
        }).then(function(html, js) {
            relatedCompetenciesCache[competencyid] = {html: html, js: js};
            return replaceRelatedCompetencies(root, competencyid, html, js);
        }).catch(Notification.exception);
    }

    /**
     * Open the native competency rule configuration dialogue.
     *
     * @param {Number} id Competency ID.
     * @param {Object} treeModel Tree model.
     */
    function showRuleConfig(id, treeModel) {
        if (!ruleConfigInstance) {
            return;
        }
        relatedTarget = treeModel.getCompetency(id);
        if (!relatedTarget) {
            return;
        }
        ruleConfigInstance.setTargetCompetencyId(id);
        ruleConfigInstance.display().then(function() {
            enhanceNativeDialogues();
            return;
        }).catch(Notification.exception);
    }

    /**
     * Keep native points rule validation in sync while numeric fields are being typed.
     */
    function initRuleConfigPopup() {
        var region = $('[data-region="competencyruleconfig"]');

        if (!region.length || region.data('local-dimensions-points-validation')) {
            return;
        }

        region.data('local-dimensions-points-validation', true);
        region.on('input.localDimensionsRule', '[name="points"], [name="requiredpoints"]', function() {
            $(this).trigger('change');
        });
    }

    /**
     * Save native competency rule configuration.
     *
     * @param {Object} treeModel Tree model.
     * @param {Event} e Save event.
     * @param {Object} config Rule configuration.
     */
    function saveRuleConfig(treeModel, e, config) {
        if (!relatedTarget) {
            return;
        }

        Ajax.call([{
            methodname: 'core_competency_read_competency',
            args: {id: relatedTarget.id}
        }])[0].then(function(competency) {
            var update = {
                id: competency.id,
                shortname: competency.shortname,
                idnumber: competency.idnumber,
                description: competency.description,
                descriptionformat: competency.descriptionformat,
                parentid: competency.parentid,
                competencyframeworkid: competency.competencyframeworkid,
                scaleid: competency.scaleid,
                scaleconfiguration: competency.scaleconfiguration,
                ruletype: config.ruletype,
                ruleoutcome: config.ruleoutcome,
                ruleconfig: config.ruleconfig
            };

            return Ajax.call([{
                methodname: 'core_competency_update_competency',
                args: {competency: update}
            }])[0];
        }).then(function(result) {
            if (result) {
                treeModel.updateRule(relatedTarget.id, config);
                window.location.reload();
            }
            return result;
        }).catch(Notification.exception);
    }

    return {
        /**
         * Initialise the manage competencies UI.
         */
        init: function() {
            var root = document.querySelector('[data-region="local-dimensions-manage"]');
            if (!root) {
                return;
            }

            var treeModel = createTreeModel(root);
            var rulesModules = readJson(root, 'rules-modules', []);
            if (rulesModules.length) {
                ruleConfigInstance = new RuleConfig(treeModel, rulesModules);
                ruleConfigInstance.on('save', saveRuleConfig.bind(null, treeModel));
            }

            var select = root.querySelector('[data-region="framework-select"]');
            if (select) {
                select.addEventListener('change', function() {
                    this.form.submit();
                });
            }

            var showHidden = root.querySelector('[data-region="show-hidden-frameworks"]');
            if (showHidden) {
                showHidden.addEventListener('change', function() {
                    this.form.submit();
                });
            }

            var search = root.querySelector('[data-region="competency-search"]');
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

            initDetailsResize(root);

            root.addEventListener('click', function(event) {
                var actionTarget = event.target.closest('[data-action]');
                if (actionTarget) {
                    var action = actionTarget.dataset.action;
                    if (action === 'toggle') {
                        event.preventDefault();
                        event.stopPropagation();
                        actionTarget.closest('[data-region="tree-node"]').classList.toggle('collapsed');
                        return;
                    }

                    if (action === 'view') {
                        event.preventDefault();
                        var viewInput = root.querySelector('[data-region="view-input"]');
                        if (viewInput) {
                            viewInput.value = actionTarget.dataset.view;
                        }
                        var form = root.querySelector('[data-region="manage-form"]');
                        if (form && root.dataset.view !== actionTarget.dataset.view) {
                            form.submit();
                        }
                        return;
                    }

                    if (action === 'expand-all' || action === 'collapse-all') {
                        event.preventDefault();
                        root.querySelectorAll('[data-region="tree-node"]').forEach(function(node) {
                            node.classList.toggle('collapsed', action === 'collapse-all');
                        });
                        return;
                    }

                    if (action === 'moveup' || action === 'movedown' || action === 'delete') {
                        event.preventDefault();
                        event.stopPropagation();
                        var id = Number(actionTarget.dataset.id);
                        if (action === 'moveup') {
                            reorderCompetency('core_competency_move_up_competency', id);
                        } else if (action === 'movedown') {
                            reorderCompetency('core_competency_move_down_competency', id);
                        } else {
                            confirmDelete(id, treeModel);
                        }
                        return;
                    }

                    if (action === 'moveparent') {
                        event.preventDefault();
                        releasePageFocus();
                        scheduleNativeDialogueEnhancement();
                        showMoveDialog(Number(actionTarget.dataset.id), treeModel);
                        return;
                    }

                    if (action === 'relatedcompetencies') {
                        event.preventDefault();
                        releasePageFocus();
                        scheduleNativeDialogueEnhancement();
                        showRelatedPicker(root, Number(actionTarget.dataset.id), treeModel);
                        return;
                    }

                    if (action === 'competencyrules') {
                        event.preventDefault();
                        releasePageFocus();
                        scheduleNativeDialogueEnhancement();
                        showRuleConfig(Number(actionTarget.dataset.id), treeModel);
                        return;
                    }

                    if (action === 'linkedcourses') {
                        event.preventDefault();
                        showLinkedCourses(Number(actionTarget.dataset.id));
                        return;
                    }

                    if (action === 'deleterelation') {
                        event.preventDefault();
                        deleteRelatedCompetency(root, Number(actionTarget.id.substr(11)));
                        return;
                    }
                }

                if (event.target.closest('.local-dimensions-manage-row-actions, .local-dimensions-manage-tree-actions')) {
                    return;
                }

                var row = getCompetencyRow(event.target);
                if (row) {
                    selectCompetency(root, row, treeModel);
                }
            });

            applyFilters(root);
        }
    };
});
