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
 * Interactions for the local_dimensions competency edit page.
 *
 * @module     local_dimensions/edit_competency
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'jquery',
    'core/ajax',
    'core/notification',
    'tool_lp/competencyruleconfig',
    'tool_lp/competency_outcomes'
], function($, Ajax, Notification, RuleConfig, Outcomes) {
    'use strict';

    var ruleConfigInstance = null;
    var relatedTarget = null;

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
     * Add local styling hooks to native tool_lp rule dialogues.
     */
    function enhanceNativeDialogues() {
        $('[data-region="competencyruleconfig"]').closest('.moodle-dialogue').addClass(
            'local-dimensions-dialogue local-dimensions-dialogue-rule'
        );

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
     * Schedule local hooks after the native dialogue has rendered.
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
            link.classList.toggle('active', link.dataset.sectionLink === id);
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
        if (!input || input.dataset.localDimensionsSwatch === '1') {
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
         * Initialise the edit competency UI.
         *
         * @param {Object} settings Page settings.
         */
        init: function(settings) {
            var root = document.querySelector('[data-region="local-dimensions-edit"]');
            if (!root) {
                return;
            }

            initSectionNavigation(root);
            initColourSwatch(settings.backgroundColourField);
            initColourSwatch(settings.textColourField);

            var treeModel = createTreeModel(root);
            var rulesModules = readJson(root, 'rules-modules', []);
            if (rulesModules.length) {
                ruleConfigInstance = new RuleConfig(treeModel, rulesModules);
                ruleConfigInstance.on('save', saveRuleConfig.bind(null, treeModel));
            }

            root.addEventListener('click', function(event) {
                var actionTarget = event.target.closest('[data-action]');
                if (!actionTarget) {
                    return;
                }

                if (actionTarget.dataset.action === 'submit-edit-form') {
                    event.preventDefault();
                    submitForm(root);
                } else if (actionTarget.dataset.action === 'open-rule-config') {
                    event.preventDefault();
                    releasePageFocus();
                    scheduleNativeDialogueEnhancement();
                    showRuleConfig(Number(actionTarget.dataset.competencyId || settings.competencyId), treeModel);
                }
            });
        }
    };
});
