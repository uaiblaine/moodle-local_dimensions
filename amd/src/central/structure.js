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
 * delete competency, and competency rule configuration. The tree is lazy — children and
 * overflow roots are fetched on demand via local_dimensions_browse_structure, so the page
 * never ships the whole framework model. The System / Course category context is owned by
 * the shared page-level selector (local_dimensions/central/context).
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
import {add as addToast} from 'core/toast';
import {reloadPane} from 'local_dimensions/central/tabs';

const FORM_CLASS = 'local_dimensions\\form\\competency_dynamic_form';
const PAGE_SIZE = 25;

const SELECTORS = {
    region: '[data-region="structure"]',
    frameworkSelect: '[data-region="framework-select"]',
    tree: '[data-region="competency-tree"]',
    rootLoadMore: '[data-region="root-loadmore"]',
    toggle: '[data-action="toggle"]',
    row: '[data-action="select"]',
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
    detailActivities: '[data-region="detail-activities"]',
    detailRule: '[data-region="detail-rule"]',
    addBtn: '[data-action="add"]',
    addHint: '[data-region="add-hint"]',
    expandAll: '[data-action="expand-all"]',
    collapseAll: '[data-action="collapse-all"]',
    displayOptions: '[data-action="display-options"]',
    displayPanel: '[data-region="display-options-panel"]',
    displayToggle: '[data-display-toggle]',
};

/** @type {HTMLElement|null} */
let activeRow = null;
/** @type {Array} */
let rulesModules = [];
/** @type {Array} */
let courseOutcomes = [];
/** @type {Array} */
let moduleOutcomes = [];
/** @type {Number} */
let activeFrameworkid = 0;
/** @type {Promise<String>|null} */
let loadMoreLabelPromise = null;

/** @type {Number} Maximum branches a single "expand all" will open before stopping. */
const EXPAND_CAP = 200;
/** @type {String} sessionStorage key for the per-session display-toggle choice. */
const DISPLAY_KEY = 'local_dimensions_structure_display';
/** @type {Object} Map of toggle key to the CSS class it controls on the tree container. */
const DISPLAY_CLASSES = {tax: 'show-tax', id: 'show-id', rule: 'show-rule'};

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
 * Fetch one page of a parent's direct children (0 = roots) from browse_structure.
 *
 * @param {Number} parentid
 * @param {Number} limitfrom
 * @return {Promise<Object>} Resolves to {items, total}.
 */
const browse = (parentid, limitfrom) => Ajax.call([{
    methodname: 'local_dimensions_browse_structure',
    args: {
        frameworkid: activeFrameworkid,
        parentid: Number(parentid),
        limitfrom: Number(limitfrom),
        limitnum: PAGE_SIZE,
    },
}])[0];

/**
 * Render competency nodes (from browse_structure) into a container.
 *
 * @param {HTMLElement} container
 * @param {Array} items
 * @return {Promise<void>}
 */
const renderNodes = async(container, items) => {
    for (const item of items) {
        const {html, js} = await Templates.renderForPromise('local_dimensions/central/structure_node', item);
        await Templates.appendNodeContents(container, html, js);
    }
};

/**
 * Build a "load more" link that runs the given loader on click.
 *
 * @param {Function} loader Async loader to run.
 * @return {HTMLElement}
 */
const makeLoadMore = (loader) => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-sm btn-link';
    btn.dataset.region = 'child-loadmore';
    if (loadMoreLabelPromise) {
        loadMoreLabelPromise.then((label) => {
            btn.textContent = label;
            return label;
        }).catch(Notification.exception);
    }
    btn.addEventListener('click', () => loader().catch(Notification.exception));
    return btn;
};

/**
 * Load and render the next page of a node's children into its container, managing the
 * per-container "load more" link.
 *
 * @param {HTMLElement} container The [data-children] container.
 * @param {Number} parentid
 * @return {Promise<void>}
 */
const loadChildPage = async(container, parentid) => {
    const existing = container.querySelector(':scope > [data-region="child-loadmore"]');
    if (existing) {
        existing.remove();
    }
    const offset = Number(container.dataset.offset || '0');
    const response = await browse(parentid, offset);
    await renderNodes(container, response.items);
    const newoffset = offset + response.items.length;
    container.dataset.offset = String(newoffset);
    if (newoffset < response.total && response.items.length > 0) {
        container.appendChild(makeLoadMore(() => loadChildPage(container, parentid)));
    }
};

/**
 * Read the persisted display-toggle choice from sessionStorage.
 *
 * @return {Object} Map of toggle key to boolean; empty when nothing is stored.
 */
const readDisplayPrefs = () => {
    try {
        return JSON.parse(window.sessionStorage.getItem(DISPLAY_KEY) || 'null') || {};
    } catch (e) {
        return {};
    }
};

/**
 * Persist the display-toggle choice to sessionStorage.
 *
 * @param {Object} prefs Map of toggle key to boolean.
 */
const writeDisplayPrefs = (prefs) => {
    try {
        window.sessionStorage.setItem(DISPLAY_KEY, JSON.stringify(prefs));
    } catch (e) {
        // Storage unavailable (e.g. private mode) — the toggles simply do not persist.
    }
};

/**
 * Reconcile the toggle checkboxes and tree classes with the persisted choice (or the
 * server-rendered defaults when nothing is stored). Runs on every init so the choice
 * survives a pane reload.
 *
 * @param {HTMLElement} region
 */
const applyDisplayPrefs = (region) => {
    const tree = region.querySelector(SELECTORS.tree);
    if (!tree) {
        return;
    }
    const stored = readDisplayPrefs();
    region.querySelectorAll(SELECTORS.displayToggle).forEach((cb) => {
        const key = cb.dataset.displayToggle;
        const on = Object.prototype.hasOwnProperty.call(stored, key) ? Boolean(stored[key]) : cb.checked;
        cb.checked = on;
        tree.classList.toggle(DISPLAY_CLASSES[key], on);
    });
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
    content.querySelector(SELECTORS.detailActivities).textContent = row.dataset.activities || '0';

    // A leaf cannot carry a rule (a rule aggregates children); show the not-applicable label.
    const haschildren = row.dataset.haschildren === '1';
    const narule = region.dataset.narule || '';
    content.querySelector(SELECTORS.detailRule).textContent =
        haschildren ? (row.dataset.rulelabel || '') : narule;

    // The header "Add competency" button is context-sensitive; reflect the selected parent.
    const hint = region.querySelector(SELECTORS.addHint);
    if (hint) {
        getString('managecompetencies_addhint_child', 'local_dimensions', row.dataset.name || '')
            .then((label) => {
                hint.textContent = label;
                return label;
            })
            .catch(Notification.exception);
    }
};

/**
 * Expand or collapse a competency node, fetching its children the first time it is opened.
 *
 * @param {HTMLElement} region
 * @param {HTMLElement} button The toggle button.
 * @return {Promise<void>}
 */
const toggleNode = async(region, button) => {
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
        // Mark loaded before awaiting so a rapid second click can't double-fetch;
        // reset on failure so a transient error can still be retried.
        container.dataset.loaded = '1';
        try {
            await loadChildPage(container, id);
        } catch (e) {
            container.dataset.loaded = '0';
            throw e;
        }
    }

    container.hidden = false;
    container.dataset.open = '1';
    button.setAttribute('aria-expanded', 'true');
    if (icon) {
        icon.className = 'fa fa-chevron-down';
    }
};

/**
 * Progressively expand every branch, fetching children on demand. Stops after EXPAND_CAP
 * branches and toasts, so a very large framework cannot hang the browser.
 *
 * @param {HTMLElement} region
 * @return {Promise<void>}
 */
const expandAll = async(region) => {
    let opened = 0;
    for (;;) {
        const next = region.querySelector(`${SELECTORS.toggle}[aria-expanded="false"]`);
        if (!next) {
            return;
        }
        await toggleNode(region, next);
        opened += 1;
        if (opened >= EXPAND_CAP) {
            addToast(await getString('managecompetencies_expandcapped', 'local_dimensions', EXPAND_CAP));
            return;
        }
    }
};

/**
 * Collapse every open branch (no fetching; children stay cached for re-expansion).
 *
 * @param {HTMLElement} region
 */
const collapseAll = (region) => {
    region.querySelectorAll(`${SELECTORS.toggle}[aria-expanded="true"]`).forEach((toggle) => {
        const container = region.querySelector(`[data-children="${Number(toggle.dataset.id)}"]`);
        if (container) {
            container.hidden = true;
            container.dataset.open = '0';
        }
        toggle.setAttribute('aria-expanded', 'false');
        const icon = toggle.querySelector('i');
        if (icon) {
            icon.className = 'fa fa-chevron-right';
        }
    });
};

/**
 * Load the next page of roots, inserting them before the root "load more" button.
 *
 * @param {HTMLElement} region
 * @return {Promise<void>}
 */
const loadMoreRoots = async(region) => {
    const tree = region.querySelector(SELECTORS.tree);
    const button = region.querySelector(SELECTORS.rootLoadMore);
    if (!tree || !button) {
        return;
    }
    const offset = Number(button.dataset.offset || '0');
    const response = await browse(0, offset);
    const wrapper = document.createElement('div');
    await renderNodes(wrapper, response.items);
    while (wrapper.firstChild) {
        tree.insertBefore(wrapper.firstChild, button);
    }
    const newoffset = offset + response.items.length;
    button.dataset.offset = String(newoffset);
    if (newoffset >= response.total || response.items.length === 0) {
        button.remove();
    }
};

/**
 * Fetch every direct child of a competency (all pages) as {id, shortname} for the rule editor.
 *
 * @param {Number} parentid
 * @return {Promise<Array>}
 */
const fetchAllChildren = async(parentid) => {
    const children = [];
    let offset = 0;
    for (;;) {
        const response = await browse(parentid, offset);
        response.items.forEach((item) => children.push({id: item.id, shortname: item.shortname}));
        offset += response.items.length;
        if (offset >= response.total || response.items.length === 0) {
            break;
        }
    }
    return children;
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
 * Persist a rule config via core_competency_update_competency, then update the node in place.
 *
 * @param {HTMLElement} row The selected [data-action="select"] element to update + flash.
 * @param {Object} config {ruletype, ruleoutcome, ruleconfig}.
 * @return {Promise<void>}
 */
const persistRule = (row, config) => {
    const id = Number(row.dataset.id);
    return Ajax.call([{methodname: 'core_competency_read_competency', args: {id: id}}])[0]
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
        .then(() => getString('changessaved'))
        .then((message) => {
            // Update the node's rule data in place + flash, instead of reloading the whole pane.
            row.dataset.ruletype = config.ruletype || '';
            row.dataset.ruleoutcome = String(config.ruleoutcome || 0);
            row.dataset.ruleconfig = config.ruleconfig || '';
            row.animate([{backgroundColor: '#fff3cd'}, {backgroundColor: 'transparent'}], {duration: 1500});
            addToast(message);
            return null;
        });
};

/**
 * Open the native rule-config modal for the selected row and persist the result.
 *
 * @param {HTMLElement} row The selected [data-action="select"] element.
 * @return {void}
 */
const showRuleConfig = (row) => {
    const competency = {
        id: Number(row.dataset.id),
        ruletype: row.dataset.ruletype || '',
        ruleoutcome: Number(row.dataset.ruleoutcome || 0),
        ruleconfig: row.dataset.ruleconfig || '',
    };
    fetchAllChildren(competency.id)
        .then((children) => showRuleConfigModal(competency, children, rulesModules))
        .then((config) => (config ? persistRule(row, config) : null))
        .catch(Notification.exception);
};

/**
 * Handle a detail-pane action for the active row.
 *
 * @param {HTMLElement} pane
 * @param {Event} event
 * @param {String} frameworkid
 * @return {void}
 */
const handleDetailAction = (pane, event, frameworkid) => {
    if (event.target.closest(SELECTORS.edit)) {
        openForm(pane, {
            competencyframeworkid: frameworkid,
            id: activeRow.dataset.id,
            parentid: activeRow.dataset.parentid,
        }, 'editcompetency');
    } else if (event.target.closest(SELECTORS.addChild)) {
        openForm(pane, {competencyframeworkid: frameworkid, parentid: activeRow.dataset.id, id: 0}, 'addcompetency');
    } else if (event.target.closest(SELECTORS.rules)) {
        showRuleConfig(activeRow);
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
    activeFrameworkid = Number(frameworkid || 0);

    if (pane) {
        // Keep the pane dataset in sync with the framework the server actually resolved
        // (it may differ from a prior selection after a visibility-toggle fallback).
        pane.dataset.frameworkid = frameworkid;
    }

    rulesModules = readJson(region, 'rules-modules', []);
    courseOutcomes = readJson(region, 'course-outcomes', []);
    moduleOutcomes = readJson(region, 'module-outcomes', []);

    loadMoreLabelPromise = getString('managecompetencies_loadmore', 'local_dimensions');
    loadMoreLabelPromise.catch(Notification.exception);

    region.addEventListener('click', (event) => {
        const toggle = event.target.closest(SELECTORS.toggle);
        if (toggle) {
            event.preventDefault();
            toggleNode(region, toggle).catch(Notification.exception);
            return;
        }
        if (event.target.closest(SELECTORS.rootLoadMore)) {
            event.preventDefault();
            loadMoreRoots(region).catch(Notification.exception);
            return;
        }
        const row = event.target.closest(SELECTORS.row);
        if (row) {
            event.preventDefault();
            selectRow(region, row);
            return;
        }
        if (event.target.closest(SELECTORS.expandAll)) {
            expandAll(region).catch(Notification.exception);
            return;
        }
        if (event.target.closest(SELECTORS.collapseAll)) {
            collapseAll(region);
            return;
        }
        const gear = event.target.closest(SELECTORS.displayOptions);
        if (gear) {
            const panel = region.querySelector(SELECTORS.displayPanel);
            if (panel) {
                panel.hidden = !panel.hidden;
                gear.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
            }
            return;
        }
        if (event.target.closest(SELECTORS.addBtn)) {
            const parentid = activeRow ? activeRow.dataset.id : 0;
            openForm(pane, {competencyframeworkid: frameworkid, parentid, id: 0}, 'addcompetency');
            return;
        }
        if (!activeRow) {
            return;
        }
        handleDetailAction(pane, event, frameworkid);
    });

    region.addEventListener('change', (event) => {
        const toggle = event.target.closest(SELECTORS.displayToggle);
        if (!toggle) {
            return;
        }
        const tree = region.querySelector(SELECTORS.tree);
        if (tree) {
            tree.classList.toggle(DISPLAY_CLASSES[toggle.dataset.displayToggle], toggle.checked);
        }
        const prefs = readDisplayPrefs();
        prefs[toggle.dataset.displayToggle] = toggle.checked;
        writeDisplayPrefs(prefs);
    });

    const select = region.querySelector(SELECTORS.frameworkSelect);
    if (select && pane) {
        select.addEventListener('change', () => {
            // The pane dataset is the single source of truth for the tab's arguments.
            pane.dataset.frameworkid = select.value;
            reloadPane(pane).catch(Notification.exception);
        });
    }

    const toggleHidden = region.querySelector('[data-action="toggle-hidden"]');
    if (toggleHidden && pane) {
        toggleHidden.addEventListener('change', () => {
            pane.dataset.showhidden = toggleHidden.checked ? '1' : '0';
            reloadPane(pane).catch(Notification.exception);
        });
    }

    applyDisplayPrefs(region);
};
