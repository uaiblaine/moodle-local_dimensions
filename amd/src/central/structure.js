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
import Modal from 'core/modal';
import ModalEvents from 'core/modal_events';
import ModalForm from 'core_form/modalform';
import ModalSaveCancel from 'core/modal_save_cancel';
import Notification from 'core/notification';
import Templates from 'core/templates';
import {show as showRuleConfigModal} from 'local_dimensions/central/rule_config';
import {open as openLinksModal} from 'local_dimensions/central/competency_links';
import {open as openRelatedModal} from 'local_dimensions/central/related_competencies';
import {getString} from 'core/str';
import {add as addToast} from 'core/toast';
import {initPaneResizer} from 'local_dimensions/central/pane_resizer';
import {reloadPane} from 'local_dimensions/central/tabs';

const FORM_CLASS = 'local_dimensions\\form\\competency_dynamic_form';
const PAGE_SIZE = 25;

const SELECTORS = {
    region: '[data-region="structure"]',
    frameworkSelect: '[data-region="framework-select"]',
    tree: '[data-region="competency-tree"]',
    rootLoadMore: '[data-region="root-loadmore"]',
    toggle: '[data-action="toggle"]',
    node: '.local-dimensions-central-node',
    row: '[data-action="select"]',
    edit: '[data-action="edit"]',
    addChild: '[data-action="addchild"]',
    rules: '[data-action="rules"]',
    links: '[data-action="links"]',
    related: '[data-action="related"]',
    moveUp: '[data-action="moveup"]',
    moveDown: '[data-action="movedown"]',
    remove: '[data-action="delete"]',
    detailEmpty: '[data-region="detail-empty"]',
    detailContent: '[data-region="detail-content"]',
    detailTitle: '[data-region="detail-title"]',
    detailIdnumber: '[data-region="detail-idnumber"]',
    detailTaxonomy: '[data-region="detail-taxonomy"]',
    detailScale: '[data-region="detail-scale"]',
    detailScaleWrap: '[data-region="detail-scale-wrap"]',
    detailDescription: '[data-region="detail-description"]',
    detailDescriptionWrap: '[data-region="detail-description-wrap"]',
    detailCourses: '[data-region="detail-courses"]',
    detailActivities: '[data-region="detail-activities"]',
    detailPlans: '[data-region="detail-plans"]',
    detailRule: '[data-region="detail-rule"]',
    nodeDragHandle: '[data-region="node-drag-handle"]',
    showUsage: '[data-action="show-usage"]',
    addBtn: '[data-action="add"]',
    addHint: '[data-region="add-hint"]',
    expandAll: '[data-action="expand-all"]',
    collapseAll: '[data-action="collapse-all"]',
    displayOptions: '[data-action="display-options"]',
    displayPanel: '[data-region="display-options-panel"]',
    displayToggle: '[data-display-toggle]',
    searchInput: '[data-region="structure-search"]',
    searchResults: '[data-region="search-results"]',
    childLoadMore: '[data-region="child-loadmore"]',
    structureBody: '[data-region="structure-body"]',
    structureResizer: '[data-region="structure-resizer"]',
    detailPane: '[data-region="detail-pane"]',
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
/** @type {Number} setTimeout id for debouncing the structure search. */
let searchDebounce = 0;

/** @type {Number} Maximum branches a single "expand all" will open before stopping. */
const EXPAND_CAP = 200;
/** @type {String} sessionStorage key for the per-session display-toggle choice. */
const DISPLAY_KEY = 'local_dimensions_structure_display';
/** @type {String} sessionStorage key for the show-hidden-frameworks choice. */
const SHOWHIDDEN_KEY = 'local_dimensions_structure_showhidden';
/** @type {Array} Snapshot of all framework <option> descriptors for client-side filtering. */
let frameworkOptions = [];
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
 * Show or hide the hidden-framework options in the framework dropdown without reloading the
 * tab. The currently selected framework always stays visible.
 *
 * @param {HTMLElement} region
 * @param {Boolean} show Whether to include hidden frameworks in the dropdown.
 */
const applyShowHidden = (region, show) => {
    const select = region.querySelector(SELECTORS.frameworkSelect);
    if (!select || !frameworkOptions.length) {
        return;
    }
    const current = select.value;
    select.innerHTML = '';
    frameworkOptions.forEach((framework) => {
        if (!show && framework.hidden && framework.value !== current) {
            return;
        }
        const option = document.createElement('option');
        option.value = framework.value;
        option.textContent = framework.label;
        if (framework.value === current) {
            option.selected = true;
        }
        select.appendChild(option);
    });
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
 * Render search hits as clickable result buttons (or hide the list when empty).
 *
 * @param {HTMLElement} region
 * @param {Array} items Hits from search_structure: {id, shortname, idnumber, path, pathids}.
 */
const renderSearchResults = (region, items) => {
    const list = region.querySelector(SELECTORS.searchResults);
    if (!list) {
        return;
    }
    list.innerHTML = '';
    if (!items.length) {
        list.hidden = true;
        return;
    }
    items.forEach((item) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'list-group-item list-group-item-action text-start';
        button.dataset.id = String(item.id);
        button.dataset.pathids = JSON.stringify(item.pathids || []);
        const name = document.createElement('span');
        name.className = 'fw-medium';
        name.textContent = item.shortname;
        button.appendChild(name);
        if (item.idnumber) {
            const idn = document.createElement('span');
            idn.className = 'font-monospace small text-muted ms-2';
            idn.textContent = item.idnumber;
            button.appendChild(idn);
        }
        if (item.path) {
            const path = document.createElement('div');
            path.className = 'small text-muted';
            path.textContent = item.path;
            button.appendChild(path);
        }
        list.appendChild(button);
    });
    list.hidden = false;
};

/**
 * Run the framework-scoped search for the current query and render the results.
 *
 * @param {HTMLElement} region
 * @param {String} query
 * @return {Promise<void>}
 */
const runSearch = async(region, query) => {
    const response = await Ajax.call([{
        methodname: 'local_dimensions_search_structure',
        args: {frameworkid: activeFrameworkid, query, limitfrom: 0, limitnum: 25},
    }])[0];
    renderSearchResults(region, response.items);
};

/** @type {Number} Safety cap on load-more iterations during a reveal-walk. */
const REVEAL_CAP = 100;

/**
 * Page in nodes at one tree level until the node with the given id is present, or until
 * there are no more pages. Level is the roots when parentid is 0, else a node's children.
 *
 * @param {HTMLElement} region
 * @param {Number} id The competency id to surface.
 * @param {Number} parentid The parent whose level holds it (0 = roots).
 * @param {String} selector A SELECTORS entry to match the node element by (toggle or row).
 * @return {Promise<HTMLElement|null>}
 */
const ensureLoaded = async(region, id, parentid, selector) => {
    const find = () => region.querySelector(`${selector}[data-id="${id}"]`);
    let guard = 0;
    for (;;) {
        const found = find();
        if (found) {
            return found;
        }
        if (guard >= REVEAL_CAP) {
            return null;
        }
        guard += 1;
        if (parentid === 0) {
            if (!region.querySelector(SELECTORS.rootLoadMore)) {
                return null;
            }
            await loadMoreRoots(region);
        } else {
            const container = region.querySelector(`[data-children="${parentid}"]`);
            if (!container) {
                return null;
            }
            const more = container.querySelector(`:scope > ${SELECTORS.childLoadMore}`);
            if (container.dataset.loaded !== '1' || !more) {
                return null;
            }
            await loadChildPage(container, parentid);
        }
    }
};

/**
 * Reveal and select a competency by expanding its ancestor path, then scroll + flash it.
 *
 * @param {HTMLElement} region
 * @param {Number} targetid
 * @param {Array} pathids Ancestor id chain root->parent (empty for a root).
 * @return {Promise<void>}
 */
const revealNode = async(region, targetid, pathids) => {
    const list = region.querySelector(SELECTORS.searchResults);
    let parentid = 0;
    for (const ancestorid of pathids) {
        const toggle = await ensureLoaded(region, Number(ancestorid), parentid, SELECTORS.toggle);
        if (!toggle) {
            addToast(await getString('managecompetencies_searchnotintree', 'local_dimensions'));
            return;
        }
        if (toggle.getAttribute('aria-expanded') === 'false') {
            await toggleNode(region, toggle);
        }
        parentid = Number(ancestorid);
    }
    const row = await ensureLoaded(region, Number(targetid), parentid, SELECTORS.row);
    if (!row) {
        addToast(await getString('managecompetencies_searchnotintree', 'local_dimensions'));
        return;
    }
    if (list) {
        list.hidden = true;
    }
    selectRow(region, row);
    row.scrollIntoView({block: 'nearest', behavior: 'smooth'});
    row.animate([{backgroundColor: '#fff3cd'}, {backgroundColor: 'transparent'}], {duration: 1500});
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

    // Scale and description only appear when the node carries them (both optional).
    const scale = row.dataset.scale || '';
    const scalewrap = content.querySelector(SELECTORS.detailScaleWrap);
    content.querySelector(SELECTORS.detailScale).textContent = scale;
    scalewrap.hidden = scale === '';
    const description = row.dataset.description || '';
    const descwrap = content.querySelector(SELECTORS.detailDescriptionWrap);
    content.querySelector(SELECTORS.detailDescription).textContent = description;
    descwrap.hidden = description === '';

    content.querySelector(SELECTORS.detailCourses).textContent = row.dataset.courses || '0';
    content.querySelector(SELECTORS.detailActivities).textContent = row.dataset.activities || '0';
    content.querySelector(SELECTORS.detailPlans).textContent = row.dataset.templates || '0';

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
 * Update a node's linked-course count in place after the links modal closes, without reloading
 * the tab (so tree expansion and selection survive). A null count means "unknown" - leave as-is.
 *
 * @param {HTMLElement} region
 * @param {HTMLElement} row The node's [data-action="select"] element the modal was opened for.
 * @param {Number|null} count The reported linked-course count.
 */
const updateCourseCount = (region, row, count) => {
    if (count === null || count === undefined) {
        return;
    }
    row.dataset.courses = String(count);
    if (row === activeRow) {
        const detail = region.querySelector(SELECTORS.detailCourses);
        if (detail) {
            detail.textContent = String(count);
        }
    }
    row.animate([{backgroundColor: '#fff3cd'}, {backgroundColor: 'transparent'}], {duration: 1500});
};

/**
 * Reorder a node among its siblings in place after the move web service succeeds, preserving
 * tree expansion, selection and scroll. Swaps the node element with its adjacent sibling node;
 * falls back to a pane reload only when the adjacent sibling is not rendered (an unloaded page).
 *
 * @param {HTMLElement} pane
 * @param {HTMLElement} row The selected node's [data-action="select"] element.
 * @param {String} methodname The core move web service (up or down).
 * @param {String} direction 'up' or 'down'.
 */
const moveNode = (pane, row, methodname, direction) => {
    const node = row.closest(SELECTORS.node);
    if (!node) {
        return;
    }
    const sibling = direction === 'up' ? node.previousElementSibling : node.nextElementSibling;
    // No sibling at all = boundary (first/last overall); the move is a server-side no-op, skip it.
    if (!sibling) {
        return;
    }
    const target = sibling.matches(SELECTORS.node) ? sibling : null;
    Ajax.call([{methodname, args: {id: Number(row.dataset.id)}}])[0]
        .then(() => {
            if (!target) {
                // Sibling exists but is not a node (e.g. a load-more) - the real neighbour is on
                // an unfetched page; reload to stay correct.
                return reloadPane(pane);
            }
            if (direction === 'up') {
                node.parentNode.insertBefore(node, target);
            } else {
                node.parentNode.insertBefore(target, node);
            }
            row.animate([{backgroundColor: '#fff3cd'}, {backgroundColor: 'transparent'}], {duration: 1500});
            return null;
        })
        .catch(Notification.exception);
};

/**
 * The rendered same-parent siblings of a node (its subtree travels with it).
 *
 * @param {HTMLElement} node A [data-node] wrapper.
 * @return {HTMLElement[]}
 */
const nodeSiblings = (node) => [...node.parentElement.children].filter((el) => el.matches(SELECTORS.node));

/**
 * Persist an in-place sibling move as a batch of single-step core move calls
 * (core has no reorder-to-position service for framework competencies), then
 * flash the row. The DOM is expected to already show the final order; on
 * failure the pane reloads so the preview does not lie about the state.
 *
 * @param {HTMLElement} pane
 * @param {HTMLElement} node The moved [data-node] wrapper.
 * @param {Number} from Original sibling index.
 * @param {Number} to Final sibling index.
 */
const persistNodeMove = (pane, node, from, to) => {
    const delta = to - from;
    if (!delta) {
        return;
    }
    const row = node.querySelector(SELECTORS.row);
    const methodname = delta > 0 ? 'core_competency_move_down_competency' : 'core_competency_move_up_competency';
    const requests = Array.from({length: Math.abs(delta)}, () => ({
        methodname: methodname,
        args: {id: Number(row.dataset.id)},
    }));
    Promise.all(Ajax.call(requests)).then(() => {
        row.animate([{backgroundColor: '#fff3cd'}, {backgroundColor: 'transparent'}], {duration: 1500});
        return null;
    }).catch((error) => {
        Notification.exception(error);
        // Restoring the server's order from a failure handler is intentional.
        // eslint-disable-next-line promise/no-nesting
        reloadPane(pane).catch(() => null);
    });
};

/**
 * Open the "move to position" modal for a tree node: a numbered select of its
 * same-parent siblings. Saving batches the single-step moves and repositions the
 * node in place — the practical path for long branches, and the keyboard one.
 *
 * @param {HTMLElement} pane
 * @param {HTMLElement} node The [data-node] wrapper to move.
 * @return {Promise<void>}
 */
const openNodeMoveModal = async(pane, node) => {
    const siblings = nodeSiblings(node);
    if (siblings.length < 2) {
        return;
    }
    const current = siblings.indexOf(node);
    const options = siblings.map((sibling, index) => {
        const row = sibling.querySelector(SELECTORS.row);
        return {
            value: index,
            label: (index + 1) + '. ' + ((row && row.dataset.name) || ''),
            selected: index === current,
        };
    });
    const {html} = await Templates.renderForPromise('local_dimensions/central/move_competency_modal', {options: options});
    const modal = await ModalSaveCancel.create({
        title: getString('central_plans_moveto', 'local_dimensions'),
        body: html,
        show: true,
        removeOnClose: true,
    });
    modal.getRoot().on(ModalEvents.save, () => {
        const select = modal.getRoot()[0].querySelector('#local-dimensions-plans-move-position');
        const targetindex = select ? Number(select.value) : current;
        if (targetindex === current || !siblings[targetindex]) {
            return;
        }
        const reference = siblings[targetindex];
        if (targetindex > current) {
            reference.after(node);
        } else {
            reference.before(node);
        }
        persistNodeMove(pane, node, current, targetindex);
    });
};

/**
 * Reparent a node (indent under a new parent, or outdent to the framework root).
 * The level change cascades indentation and taxonomy through the whole subtree, so
 * a server re-render is the only honest refresh: the pane reloads and the moved
 * node is revealed (branch expanded, selected, flashed) at its new location.
 * Core appends the node at the end of the new parent's children.
 *
 * @param {HTMLElement} pane
 * @param {HTMLElement} node The dragged [data-node] wrapper.
 * @param {HTMLElement|null} parentnode The new parent node, or null for the root level.
 * @return {Promise<void>}
 */
const reparentNode = async(pane, node, parentnode) => {
    const row = node.querySelector(SELECTORS.row);
    const id = Number(row.dataset.id);
    // Ancestor chain of the destination (root -> new parent) for the post-reload reveal.
    const pathids = [];
    let ancestor = parentnode;
    while (ancestor) {
        pathids.unshift(Number(ancestor.dataset.node));
        ancestor = ancestor.parentElement.closest(SELECTORS.node);
    }
    await Ajax.call([{
        methodname: 'core_competency_set_parent_competency',
        args: {competencyid: id, parentid: parentnode ? Number(parentnode.dataset.node) : 0},
    }])[0];
    await reloadPane(pane);
    const fresh = document.querySelector(SELECTORS.region);
    if (fresh) {
        await revealNode(fresh, id, pathids);
    }
};

/**
 * Drag-and-drop of tree nodes, level-aware. The drag starts from the grip that
 * appears on row hover and the node travels with its subtree. Three drop gestures:
 * the top/bottom edges of a same-parent sibling reorder in place (persisted as one
 * batched request); the middle of any row indents the node as that row's child;
 * the tree's empty space outdents it back to the root level.
 *
 * @param {HTMLElement} region
 * @param {HTMLElement} pane
 */
const initTreeDrag = (region, pane) => {
    const tree = region.querySelector(SELECTORS.tree);
    if (!tree) {
        return;
    }
    let dragged = null;
    let startindex = -1;
    /** @type {HTMLElement|String|null} New parent node, 'root', or null for sibling reorder. */
    let dropinto = null;
    let highlighted = null;

    const clearDropHints = () => {
        if (highlighted) {
            highlighted.classList.remove('local-dimensions-central-drop-target');
            highlighted = null;
        }
        tree.classList.remove('local-dimensions-central-drop-root');
        dropinto = null;
    };

    // The node only becomes draggable while the pointer holds its grip.
    tree.addEventListener('mousedown', (event) => {
        const handle = event.target.closest(SELECTORS.nodeDragHandle);
        const node = handle && handle.closest(SELECTORS.node);
        if (node) {
            node.setAttribute('draggable', 'true');
        }
    });
    tree.addEventListener('mouseup', (event) => {
        const handle = event.target.closest(SELECTORS.nodeDragHandle);
        const node = handle && handle.closest(SELECTORS.node);
        if (node && !dragged) {
            node.removeAttribute('draggable');
        }
    });

    tree.addEventListener('dragstart', (event) => {
        dragged = event.target.closest(SELECTORS.node);
        if (!dragged) {
            return;
        }
        startindex = nodeSiblings(dragged).indexOf(dragged);
        dragged.classList.add('local-dimensions-central-plan-dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', dragged.dataset.node);
    });

    tree.addEventListener('dragover', (event) => {
        if (!dragged) {
            return;
        }
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        const over = event.target.closest(SELECTORS.node);
        const draggedrow = dragged.querySelector(SELECTORS.row);

        if (!over) {
            // Tree whitespace: outdent back to the root level (no-op for a root node).
            clearDropHints();
            if (draggedrow && draggedrow.dataset.parentid !== '0') {
                dropinto = 'root';
                tree.classList.add('local-dimensions-central-drop-root');
            }
            return;
        }
        if (over === dragged || dragged.contains(over)) {
            clearDropHints();
            return;
        }

        // Zones are measured on the row line only; hovering an expanded node's children
        // area resolves to the child nodes themselves.
        const line = over.querySelector(':scope > .d-flex');
        if (!line) {
            return;
        }
        const rect = line.getBoundingClientRect();
        if (event.clientY < rect.top || event.clientY > rect.bottom) {
            return;
        }
        const ratio = (event.clientY - rect.top) / rect.height;

        if (ratio < 0.3 || ratio > 0.7) {
            // Edge zones: live reorder, same-parent siblings only.
            clearDropHints();
            if (over.parentElement !== dragged.parentElement) {
                return;
            }
            if (ratio < 0.3) {
                over.before(dragged);
            } else {
                over.after(dragged);
            }
            return;
        }

        // Middle zone: indent as a child of the hovered node (no-op on the current parent).
        if (draggedrow && Number(draggedrow.dataset.parentid) === Number(over.dataset.node)) {
            clearDropHints();
            return;
        }
        if (highlighted !== line) {
            clearDropHints();
            highlighted = line;
            line.classList.add('local-dimensions-central-drop-target');
        }
        dropinto = over;
    });

    tree.addEventListener('dragleave', (event) => {
        if (dragged && !tree.contains(event.relatedTarget)) {
            clearDropHints();
        }
    });

    tree.addEventListener('drop', (event) => event.preventDefault());

    tree.addEventListener('dragend', () => {
        if (!dragged) {
            return;
        }
        const node = dragged;
        dragged = null;
        node.classList.remove('local-dimensions-central-plan-dragging');
        node.removeAttribute('draggable');
        const target = dropinto;
        clearDropHints();
        if (target) {
            reparentNode(pane, node, target === 'root' ? null : target).catch(Notification.exception);
            return;
        }
        const endindex = nodeSiblings(node).indexOf(node);
        if (startindex >= 0 && endindex >= 0) {
            persistNodeMove(pane, node, startindex, endindex);
        }
    });
};

/** @type {Object} Usage-modal section -> lang key of its title (also the counter label). */
const USAGE_SECTIONS = {
    courses: 'managecompetencies_linkedcourses',
    activities: 'managecompetencies_linkedactivities',
    templates: 'central_structure_linkedplans',
};

/**
 * Fetch and show one usage list for the active competency, matching the counter the
 * user clicked: linked courses, linked activities (each labelled with its course) or
 * the learning plan templates bundling it. The web service is shared; only the
 * clicked section is rendered.
 *
 * @param {HTMLElement} row The active tree row.
 * @param {String} section 'courses', 'activities' or 'templates'.
 * @return {Promise<void>}
 */
const openUsageModal = async(row, section) => {
    const labelkey = USAGE_SECTIONS[section] ? section : 'courses';
    const usage = await Ajax.call([{
        methodname: 'local_dimensions_competency_usage',
        args: {competencyid: Number(row.dataset.id)},
    }])[0];
    const {html} = await Templates.renderForPromise('local_dimensions/central/competency_usage_modal', {
        showcourses: labelkey === 'courses',
        hascourses: usage.courses.length > 0,
        courses: usage.courses,
        showactivities: labelkey === 'activities',
        hasactivities: usage.activities.length > 0,
        activities: usage.activities,
        showtemplates: labelkey === 'templates',
        hastemplates: usage.templates.length > 0,
        templates: usage.templates,
    });
    await Modal.create({
        title: getString(USAGE_SECTIONS[labelkey], 'local_dimensions')
            .then((label) => label + ' — ' + (row.dataset.name || '')),
        body: html,
        large: true,
        show: true,
        removeOnClose: true,
    });
};

/**
 * Handle a detail-pane action for the active row.
 *
 * @param {HTMLElement} region
 * @param {HTMLElement} pane
 * @param {Event} event
 * @param {String} frameworkid
 * @return {void}
 */
const handleDetailAction = (region, pane, event, frameworkid) => {
    const usagebutton = event.target.closest(SELECTORS.showUsage);
    if (usagebutton) {
        openUsageModal(activeRow, usagebutton.dataset.usage).catch(Notification.exception);
    } else if (event.target.closest(SELECTORS.edit)) {
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
        const row = activeRow;
        openLinksModal({
            competencyid: Number(row.dataset.id),
            competencyname: row.dataset.name || '',
            courseoutcomes: courseOutcomes,
            moduleoutcomes: moduleOutcomes,
            // Refresh the linked-course count in place (no pane reload -> selection/expansion kept).
            onClose: (count) => updateCourseCount(region, row, count),
        });
    } else if (event.target.closest(SELECTORS.related)) {
        openRelatedModal({
            competencyid: Number(activeRow.dataset.id),
            competencyname: activeRow.dataset.name || '',
            frameworkid: activeFrameworkid,
        });
    } else if (event.target.closest(SELECTORS.moveUp)) {
        moveNode(pane, activeRow, 'core_competency_move_up_competency', 'up');
    } else if (event.target.closest(SELECTORS.moveDown)) {
        moveNode(pane, activeRow, 'core_competency_move_down_competency', 'down');
    } else if (event.target.closest(SELECTORS.remove)) {
        confirmDelete(pane, activeRow);
    }
};

/**
 * Wire the draggable divider between the tree and detail panes via the shared
 * pane resizer (pointer drag, dblclick reset, arrow-key resize, localStorage).
 *
 * @param {HTMLElement} region
 */
const initStructureResize = (region) => {
    // Same reach as the Plans tab: the divider can shrink the tree down to ~200px
    // and let the detail grow well past the old 640px cap.
    initPaneResizer({
        body: region.querySelector(SELECTORS.structureBody),
        resizer: region.querySelector(SELECTORS.structureResizer),
        detail: region.querySelector(SELECTORS.detailPane),
        cssvar: '--local-dimensions-structure-detail-width',
        storagekey: 'local_dimensions_structure_detail_width',
        minimum: 280,
        maximum: 1600,
        reserve: 200,
    });
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
        const result = event.target.closest(`${SELECTORS.searchResults} [data-id]`);
        if (result) {
            event.preventDefault();
            let pathids = [];
            try {
                pathids = JSON.parse(result.dataset.pathids || '[]');
            } catch (e) {
                pathids = [];
            }
            revealNode(region, Number(result.dataset.id), pathids).catch(Notification.exception);
            return;
        }
        const griphandle = event.target.closest(SELECTORS.nodeDragHandle);
        if (griphandle) {
            event.preventDefault();
            const node = griphandle.closest(SELECTORS.node);
            if (node) {
                openNodeMoveModal(pane, node).catch(Notification.exception);
            }
            return;
        }
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
            // A node with children also expands/collapses on a whole-row click (not just the chevron).
            if (row.dataset.haschildren === '1') {
                const toggle = row.parentElement.querySelector(SELECTORS.toggle);
                if (toggle) {
                    toggleNode(region, toggle).catch(Notification.exception);
                }
            }
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
        handleDetailAction(region, pane, event, frameworkid);
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

    const searchInput = region.querySelector(SELECTORS.searchInput);
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const query = searchInput.value.trim();
            window.clearTimeout(searchDebounce);
            if (query.length < 2) {
                renderSearchResults(region, []);
                return;
            }
            searchDebounce = window.setTimeout(() => {
                runSearch(region, query).catch(Notification.exception);
            }, 250);
        });
    }

    const select = region.querySelector(SELECTORS.frameworkSelect);
    if (select && pane) {
        select.addEventListener('change', () => {
            window.clearTimeout(searchDebounce);
            // The pane dataset is the single source of truth for the tab's arguments.
            pane.dataset.frameworkid = select.value;
            reloadPane(pane).catch(Notification.exception);
        });
    }

    // Snapshot every framework option so "show hidden" filters the dropdown client-side
    // (no pane reload -> no re-apply of display prefs -> no toggle flash).
    const frameworkselect = region.querySelector(SELECTORS.frameworkSelect);
    if (frameworkselect) {
        frameworkOptions = Array.from(frameworkselect.options).map((option) => ({
            value: option.value,
            label: option.textContent,
            hidden: option.dataset.hidden === '1',
        }));
    }
    const toggleHidden = region.querySelector('[data-action="toggle-hidden"]');
    if (toggleHidden) {
        let showhidden = toggleHidden.checked;
        try {
            const stored = window.sessionStorage.getItem(SHOWHIDDEN_KEY);
            if (stored !== null) {
                showhidden = stored === '1';
            }
        } catch (e) {
            // Storage unavailable; fall back to the server-rendered checkbox state.
        }
        toggleHidden.checked = showhidden;
        applyShowHidden(region, showhidden);
        toggleHidden.addEventListener('change', () => {
            try {
                window.sessionStorage.setItem(SHOWHIDDEN_KEY, toggleHidden.checked ? '1' : '0');
            } catch (e) {
                // Storage unavailable; the choice simply does not persist.
            }
            applyShowHidden(region, toggleHidden.checked);
        });
    }

    applyDisplayPrefs(region);
    initStructureResize(region);
    initTreeDrag(region, pane);
};
