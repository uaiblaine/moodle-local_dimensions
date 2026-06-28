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
 * Structure tab: master-detail selection, no-reload framework switch, create/edit/move/
 * delete competency, and competency rule configuration (tool_lp/competencyruleconfig).
 * The System / Course category context is owned by the shared page-level selector
 * (local_dimensions/central/context); this module only switches frameworks within it.
 *
 * @module     local_dimensions/central/structure
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import ModalForm from 'core_form/modalform';
import Notification from 'core/notification';
import Templates from 'core/templates';
import {show as showRuleConfigModal} from 'local_dimensions/central/rule_config';
import {open as openLinksModal} from 'local_dimensions/central/competency_links';
import {getString} from 'core/str';
import {reloadPane} from 'local_dimensions/central/tabs';

const FORM_CLASS = 'local_dimensions\\form\\competency_dynamic_form';

const SELECTORS = {
    region: '[data-region="structure"]',
    frameworkSelect: '[data-region="framework-select"]',
    toggle: '[data-action="toggle"]',
    row: '[data-action="select"]',
    addRoot: '[data-action="addroot"]',
    edit: '[data-action="edit"]',
    addChild: '[data-action="addchild"]',
    rules: '[data-action="rules"]',
    links: '[data-action="links"]',
    moveUp: '[data-action="moveup"]',
    moveDown: '[data-action="movedown"]',
    remove: '[data-action="delete"]',
    detailEmpty: '[data-region="detail-empty"]',
    detailContent: '[data-region="detail-content"]',
    detailTitle: '[data-region="detail-title"]',
    detailIdnumber: '[data-region="detail-idnumber"]',
    detailTaxonomy: '[data-region="detail-taxonomy"]',
    detailCourses: '[data-region="detail-courses"]',
};

/** @type {HTMLElement|null} */
let activeRow = null;
/** @type {Object|null} */
let treeModel = null;
/** @type {Array} */
let rulesModules = [];
/** @type {Array} */
let courseOutcomes = [];
/** @type {Array} */
let moduleOutcomes = [];

/**
 * Read a JSON island embedded in the tab.
 *
 * @param {HTMLElement} region
 * @param {String} dataRegion
 * @param {*} fallback
 * @return {*}
 */
const readJson = (region, dataRegion, fallback) => {
    const node = region.querySelector(`[data-region="${dataRegion}"]`);
    if (!node) {
        return fallback;
    }
    try {
        return JSON.parse(node.textContent || 'null') || fallback;
    } catch (e) {
        return fallback;
    }
};

/**
 * Build the tree model consumed by tool_lp/competencyruleconfig.
 *
 * @param {HTMLElement} region
 * @return {Object}
 */
const createTreeModel = (region) => {
    const competencies = readJson(region, 'competency-model', []);
    const byId = {};
    competencies.forEach((competency) => {
        competency.id = Number(competency.id);
        competency.parentid = Number(competency.parentid);
        competency.competencyframeworkid = Number(competency.competencyframeworkid);
        competency.ruleoutcome = Number(competency.ruleoutcome || 0);
        byId[competency.id] = competency;
    });

    return {
        getCompetencyFrameworkId: () => (competencies.length ? competencies[0].competencyframeworkid : 0),
        getChildren: (id) => competencies.filter((competency) => competency.parentid === Number(id)),
        getCompetency: (id) => byId[Number(id)],
        getCompetencyLevel: function(id) {
            const competency = this.getCompetency(id);
            if (!competency || !competency.path) {
                return 0;
            }
            return competency.path.replace(/^\/|\/$/g, '').split('/').length;
        },
        hasChildren: function(id) {
            return this.getChildren(id).length > 0;
        },
        hasRule: function(id) {
            const competency = this.getCompetency(id);
            return !!competency && competency.ruleoutcome !== 0 && !!competency.ruletype;
        },
        updateRule: function(id, config) {
            const competency = this.getCompetency(id);
            if (competency) {
                competency.ruletype = config.ruletype;
                competency.ruleoutcome = Number(config.ruleoutcome || 0);
                competency.ruleconfig = config.ruleconfig;
            }
        },
    };
};

/**
 * Populate the detail pane from the selected tree row.
 *
 * @param {HTMLElement} region
 * @param {HTMLElement} row
 */
const selectRow = (region, row) => {
    activeRow = row;
    region.querySelectorAll(SELECTORS.row).forEach((node) => node.classList.remove('active'));
    row.classList.add('active');

    region.querySelector(SELECTORS.detailEmpty).hidden = true;
    const content = region.querySelector(SELECTORS.detailContent);
    content.hidden = false;
    content.querySelector(SELECTORS.detailTitle).textContent = row.dataset.name || '';
    content.querySelector(SELECTORS.detailIdnumber).textContent = row.dataset.idnumber || '';
    content.querySelector(SELECTORS.detailTaxonomy).textContent = row.dataset.taxonomy || '';
    content.querySelector(SELECTORS.detailCourses).textContent = row.dataset.courses || '0';
};

/**
 * Expand or collapse a competency node, rendering its children from the in-memory
 * model the first time it is opened (lazy rendering — the DOM stays small).
 *
 * @param {HTMLElement} region
 * @param {HTMLElement} button The toggle button.
 * @return {Promise<void>}
 */
const toggleNode = async(region, button) => {
    if (!treeModel) {
        return;
    }
    const id = Number(button.dataset.id);
    const container = region.querySelector(`[data-children="${id}"]`);
    if (!container) {
        return;
    }
    const icon = button.querySelector('i');

    if (container.dataset.open === '1') {
        container.hidden = true;
        container.dataset.open = '0';
        button.setAttribute('aria-expanded', 'false');
        if (icon) {
            icon.className = 'fa fa-chevron-right';
        }
        return;
    }

    if (container.dataset.loaded !== '1') {
        for (const child of treeModel.getChildren(id)) {
            const {html, js} = await Templates.renderForPromise('local_dimensions/central/structure_node', child);
            await Templates.appendNodeContents(container, html, js);
        }
        container.dataset.loaded = '1';
    }

    container.hidden = false;
    container.dataset.open = '1';
    button.setAttribute('aria-expanded', 'true');
    if (icon) {
        icon.className = 'fa fa-chevron-down';
    }
};

/**
 * Call a single-id core competency web service, then refresh the tab.
 *
 * @param {HTMLElement} pane
 * @param {String} methodname
 * @param {String|Number} id
 */
const callAndReload = (pane, methodname, id) => {
    Ajax.call([{methodname, args: {id: Number(id)}}])[0]
        .then(() => reloadPane(pane))
        .catch(Notification.exception);
};

/**
 * Open the competency modal form and refresh the tab on success.
 *
 * @param {HTMLElement} pane
 * @param {Object} args
 * @param {String} titlekey
 */
const openForm = async(pane, args, titlekey) => {
    const form = new ModalForm({
        formClass: FORM_CLASS,
        args,
        modalConfig: {title: await getString(titlekey, 'tool_lp')},
    });
    form.addEventListener(form.events.FORM_SUBMITTED, () => reloadPane(pane).catch(Notification.exception));
    form.show();
};

/**
 * Confirm and delete the active competency.
 *
 * @param {HTMLElement} pane
 * @param {HTMLElement} row
 */
const confirmDelete = async(pane, row) => {
    const [title, question] = await Promise.all([
        getString('delete'),
        getString('deletecompetency', 'tool_lp', row.dataset.name || ''),
    ]);
    try {
        await Notification.deleteCancelPromise(title, question);
    } catch (e) {
        return;
    }
    Ajax.call([{methodname: 'core_competency_delete_competency', args: {id: Number(row.dataset.id)}}])[0]
        .then(async(success) => {
            if (success === false) {
                Notification.alert(null, await getString('competencycannotbedeleted', 'tool_lp', row.dataset.name || ''));
                return null;
            }
            return reloadPane(pane);
        })
        .catch(Notification.exception);
};

/**
 * Persist a rule config via core_competency_update_competency, then refresh the pane.
 *
 * @param {HTMLElement} pane
 * @param {Object} competency The target competency from the tree model.
 * @param {Object} config {ruletype, ruleoutcome, ruleconfig}.
 * @return {Promise<void>}
 */
const persistRule = (pane, competency, config) => {
    return Ajax.call([{methodname: 'core_competency_read_competency', args: {id: competency.id}}])[0]
        .then((full) => Ajax.call([{
            methodname: 'core_competency_update_competency',
            args: {
                competency: {
                    id: full.id,
                    shortname: full.shortname,
                    idnumber: full.idnumber,
                    description: full.description,
                    descriptionformat: full.descriptionformat,
                    parentid: full.parentid,
                    competencyframeworkid: full.competencyframeworkid,
                    scaleid: full.scaleid,
                    scaleconfiguration: full.scaleconfiguration,
                    ruletype: config.ruletype,
                    ruleoutcome: config.ruleoutcome,
                    ruleconfig: config.ruleconfig,
                },
            },
        }])[0])
        .then(() => {
            treeModel.updateRule(competency.id, config);
            return reloadPane(pane);
        });
};

/**
 * Open the native rule-config modal for a competency and persist the result.
 *
 * @param {HTMLElement} pane
 * @param {Number} id
 * @return {void}
 */
const showRuleConfig = (pane, id) => {
    if (!treeModel) {
        return;
    }
    const competency = treeModel.getCompetency(id);
    if (!competency) {
        return;
    }
    const children = treeModel.getChildren(id);
    showRuleConfigModal(competency, children, rulesModules)
        .then((config) => (config ? persistRule(pane, competency, config) : null))
        .catch(Notification.exception);
};

/**
 * Initialise the Structure tab. Re-runs after each tab refresh.
 */
export const init = () => {
    const region = document.querySelector(SELECTORS.region);
    if (!region) {
        return;
    }
    const pane = region.closest('[data-tab-content]');
    const frameworkid = region.dataset.frameworkid || '';

    treeModel = createTreeModel(region);
    rulesModules = readJson(region, 'rules-modules', []);
    courseOutcomes = readJson(region, 'course-outcomes', []);
    moduleOutcomes = readJson(region, 'module-outcomes', []);

    region.addEventListener('click', (event) => {
        const toggle = event.target.closest(SELECTORS.toggle);
        if (toggle) {
            event.preventDefault();
            toggleNode(region, toggle).catch(Notification.exception);
            return;
        }
        const row = event.target.closest(SELECTORS.row);
        if (row) {
            event.preventDefault();
            selectRow(region, row);
            return;
        }
        if (event.target.closest(SELECTORS.addRoot)) {
            openForm(pane, {competencyframeworkid: frameworkid, parentid: 0, id: 0}, 'addcompetency');
            return;
        }
        if (!activeRow) {
            return;
        }
        if (event.target.closest(SELECTORS.edit)) {
            openForm(pane, {
                competencyframeworkid: frameworkid,
                id: activeRow.dataset.id,
                parentid: activeRow.dataset.parentid,
            }, 'editcompetency');
        } else if (event.target.closest(SELECTORS.addChild)) {
            openForm(pane, {competencyframeworkid: frameworkid, parentid: activeRow.dataset.id, id: 0}, 'addcompetency');
        } else if (event.target.closest(SELECTORS.rules)) {
            showRuleConfig(pane, Number(activeRow.dataset.id));
        } else if (event.target.closest(SELECTORS.links)) {
            openLinksModal({
                competencyid: Number(activeRow.dataset.id),
                competencyname: activeRow.dataset.name || '',
                courseoutcomes: courseOutcomes,
                moduleoutcomes: moduleOutcomes,
                onClose: () => reloadPane(pane).catch(Notification.exception),
            });
        } else if (event.target.closest(SELECTORS.moveUp)) {
            callAndReload(pane, 'core_competency_move_up_competency', activeRow.dataset.id);
        } else if (event.target.closest(SELECTORS.moveDown)) {
            callAndReload(pane, 'core_competency_move_down_competency', activeRow.dataset.id);
        } else if (event.target.closest(SELECTORS.remove)) {
            confirmDelete(pane, activeRow);
        }
    });

    const select = region.querySelector(SELECTORS.frameworkSelect);
    if (select && pane) {
        select.addEventListener('change', () => {
            // The pane dataset is the single source of truth for the tab's arguments.
            pane.dataset.frameworkid = select.value;
            reloadPane(pane).catch(Notification.exception);
        });
    }
};
