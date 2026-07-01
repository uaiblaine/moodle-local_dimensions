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
import {open as openRelatedModal} from 'local_dimensions/central/related_competencies';
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
 * Handle a detail-pane action for the active row.
 *
 * @param {HTMLElement} region
 * @param {HTMLElement} pane
 * @param {Event} event
 * @param {String} frameworkid
 * @return {void}
 */
const handleDetailAction = (region, pane, event, frameworkid) => {
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
 * Wire the draggable divider between the tree and detail panes. The detail width is
 * persisted in localStorage and reapplied on each init, so it survives pane reloads.
 * Mirrors the manage-templates resizer; supports pointer drag, dblclick reset and
 * ArrowLeft/ArrowRight keyboard resizing.
 *
 * @param {HTMLElement} region
 */
const initStructureResize = (region) => {
    const storagekey = 'local_dimensions_structure_detail_width';
    const resizer = region.querySelector(SELECTORS.structureResizer);
    const detail = region.querySelector(SELECTORS.detailPane);
    const body = region.querySelector(SELECTORS.structureBody);
    if (!resizer || !detail || !body) {
        return;
    }
    const minimum = 240;
    const maximum = 640;
    const applyWidth = (width) => {
        const bodywidth = body.getBoundingClientRect().width;
        const availablemax = Math.max(minimum, Math.min(maximum, bodywidth - 320));
        const next = Math.min(Math.max(width, minimum), availablemax);
        body.style.setProperty('--local-dimensions-structure-detail-width', next + 'px');
        resizer.setAttribute('aria-valuenow', String(Math.round(next)));
        return next;
    };
    const persist = (width) => {
        try {
            window.localStorage.setItem(storagekey, String(Math.round(width)));
        } catch (e) {
            // Local storage may be unavailable in restricted browser contexts.
        }
    };
    try {
        const stored = Number(window.localStorage.getItem(storagekey));
        if (stored) {
            applyWidth(stored);
        }
    } catch (e) {
        // Local storage may be unavailable in restricted browser contexts.
    }
    resizer.setAttribute('aria-valuemin', String(minimum));
    resizer.setAttribute('aria-valuemax', String(maximum));
    let startx = 0;
    let startwidth = 0;
    resizer.addEventListener('pointerdown', (event) => {
        event.preventDefault();
        startx = event.clientX;
        startwidth = detail.getBoundingClientRect().width;
        body.classList.add('resizing');
        resizer.setPointerCapture(event.pointerId);
    });
    resizer.addEventListener('pointermove', (event) => {
        if (!body.classList.contains('resizing')) {
            return;
        }
        applyWidth(startwidth + startx - event.clientX);
    });
    resizer.addEventListener('pointerup', (event) => {
        if (!body.classList.contains('resizing')) {
            return;
        }
        const width = applyWidth(detail.getBoundingClientRect().width);
        body.classList.remove('resizing');
        try {
            resizer.releasePointerCapture(event.pointerId);
        } catch (e) {
            // Pointer capture may already be released.
        }
        persist(width);
    });
    resizer.addEventListener('dblclick', () => {
        body.style.removeProperty('--local-dimensions-structure-detail-width');
        try {
            window.localStorage.removeItem(storagekey);
        } catch (e) {
            // Local storage may be unavailable in restricted browser contexts.
        }
    });
    resizer.addEventListener('keydown', (event) => {
        let delta = 0;
        if (event.key === 'ArrowLeft') {
            delta = 24;
        } else if (event.key === 'ArrowRight') {
            delta = -24;
        } else {
            return;
        }
        event.preventDefault();
        persist(applyWidth(detail.getBoundingClientRect().width + delta));
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
};
