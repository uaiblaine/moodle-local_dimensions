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
 * Shared framework-competency browser: a debounced server search plus a hierarchical,
 * lazy-loading competency tree with checkbox rows, shift-range selection, optional
 * ancestor paths and infinite scrolling (local_dimensions_browse_competencies).
 *
 * Drives the filter + tree region (template partial central/competency_tree_browser) of the
 * "Browse frameworks" modal (central/competency_browser) and the "Related competencies"
 * modal (central/related_competencies). The host module owns the modal, the framework
 * choice and what adding the checked competencies means.
 *
 * The state contract (one object shared with the host) — seeded by the host: root, listEl,
 * frameworkid, excluded (Set of string ids), excludedsuffix (function id => label shown on
 * a disabled row), loadmorelabel, emptylabel; initBrowser() initialises the internal fields
 * (checked, togglelabel, mode, query, offset, total, loading, lastChecked, showpath,
 * debounce, sentinel, observer).
 *
 * Selection is persistent: checked ids live in state.checked and survive the re-renders
 * caused by filter/mode switches (rows are re-checked as they render), so getCheckedIds()
 * reflects everything the user picked, visible or not. Hosts clear the set when the
 * selection is consumed or the framework changes.
 *
 * @module     local_dimensions/central/competency_tree_browser
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import {notifyError} from 'local_dimensions/central/errors';
import {getString} from 'core/str';

const PAGE_SIZE = 25;
const SEARCH_MIN = 2;
const INDENT_STEP = 20;

const SELECTORS = {
    filter: '[data-region="filter"]',
    pathToggle: '[data-region="path-toggle"]',
};

/**
 * Call the browse_competencies web service.
 *
 * @param {Object} args Web service arguments.
 * @return {Promise<Object>} Resolves to {items, total}.
 */
const browse = (args) => Ajax.call([{
    methodname: 'local_dimensions_browse_competencies',
    args: args,
}])[0];

/**
 * Whether competency paths should currently be visible.
 *
 * @param {Object} state Browser state.
 * @return {Boolean}
 */
const pathsVisible = (state) => state.mode === 'search' || state.showpath;

/**
 * Build one competency node (a row plus, when it has children, an empty children container).
 *
 * @param {Object} state Browser state.
 * @param {Object} competency {id, shortname, idnumber, haschildren, path}.
 * @param {Number} depth Indentation depth (0 = root / search result).
 * @return {HTMLElement} The node element.
 */
const makeNode = (state, competency, depth) => {
    const id = String(competency.id);
    const already = state.excluded.has(id);

    const node = document.createElement('div');
    node.className = 'local-dimensions-cb-node';
    node.dataset.competency = id;
    node.dataset.depth = String(depth);
    node.dataset.expanded = '0';

    const rowel = document.createElement('div');
    rowel.className = 'local-dimensions-cb-row d-flex align-items-start py-1';
    rowel.style.marginLeft = `${depth * INDENT_STEP}px`;

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'btn btn-sm btn-link p-0 me-1';
    const icon = document.createElement('i');
    icon.className = 'fa fa-chevron-right';
    icon.setAttribute('aria-hidden', 'true');
    toggle.appendChild(icon);
    if (competency.haschildren) {
        toggle.dataset.action = 'toggle';
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', `${state.togglelabel}: ${competency.shortname}`);
    } else {
        toggle.classList.add('invisible');
    }

    const check = document.createElement('input');
    check.type = 'checkbox';
    check.className = 'form-check-input mt-1 me-2';
    check.id = `local-dimensions-cb-${id}`;
    check.value = id;
    check.setAttribute('aria-label', competency.shortname);
    if (already) {
        check.checked = true;
        check.disabled = true;
    } else if (state.checked.has(id)) {
        // Restore a selection made before a re-render (filter/mode switch).
        check.checked = true;
    }

    // No "for": the whole row is the click target (handled in onListClick), so clicking the name or
    // path selects with the same shift-range behaviour as clicking the checkbox.
    const textcol = document.createElement('div');
    textcol.className = 'flex-grow-1';
    const name = document.createElement('div');
    name.textContent = already ? `${competency.shortname} (${state.excludedsuffix(id)})` : competency.shortname;
    textcol.appendChild(name);
    if (competency.path) {
        const pathel = document.createElement('div');
        pathel.className = 'text-muted small local-dimensions-cb-path';
        pathel.textContent = competency.path;
        pathel.hidden = !pathsVisible(state);
        textcol.appendChild(pathel);
    }

    if (!already) {
        rowel.style.cursor = 'pointer';
    }
    rowel.appendChild(toggle);
    rowel.appendChild(check);
    rowel.appendChild(textcol);
    node.appendChild(rowel);

    if (competency.haschildren) {
        const children = document.createElement('div');
        children.dataset.region = 'children';
        children.dataset.offset = '0';
        children.hidden = true;
        node.appendChild(children);
    }
    return node;
};

/**
 * Append competencies as nodes into a container at the given depth.
 *
 * @param {Object} state Browser state.
 * @param {HTMLElement} container Target container.
 * @param {Array} items Competency records.
 * @param {Number} depth Depth for the nodes.
 * @return {void}
 */
const appendNodes = (state, container, items, depth) => {
    items.forEach((competency) => container.appendChild(makeNode(state, competency, depth)));
};

/**
 * Append a "load more" button that runs the given loader when clicked.
 *
 * @param {Object} state Browser state.
 * @param {HTMLElement} container Target container.
 * @param {Number} depth Depth for indentation.
 * @param {Function} loader Async loader to run.
 * @return {void}
 */
const appendLoadMore = (state, container, depth, loader) => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-sm btn-link';
    btn.dataset.role = 'load-more';
    btn.style.marginLeft = `${depth * INDENT_STEP}px`;
    btn.textContent = state.loadmorelabel;
    btn.addEventListener('click', () => {
        btn.remove();
        loader().catch(notifyError);
    });
    container.appendChild(btn);
};

/**
 * Load the next page of a node's children into its children container.
 *
 * @param {Object} state Browser state.
 * @param {HTMLElement} node The parent node element.
 * @return {Promise<void>}
 */
const loadChildren = async(state, node) => {
    const container = node.querySelector(':scope > [data-region="children"]');
    if (!container) {
        return;
    }
    const depth = Number(node.dataset.depth) + 1;
    const offset = Number(container.dataset.offset);
    const response = await browse({
        frameworkid: state.frameworkid,
        parentid: Number(node.dataset.competency),
        query: '',
        limitfrom: offset,
        limitnum: PAGE_SIZE,
    });
    appendNodes(state, container, response.items, depth);
    container.dataset.offset = String(offset + response.items.length);
    if (offset + response.items.length < response.total) {
        appendLoadMore(state, container, depth, () => loadChildren(state, node));
    }
};

/**
 * Expand or collapse a node, loading its children on first expand.
 *
 * @param {Object} state Browser state.
 * @param {HTMLElement} node The node element.
 * @return {Promise<void>}
 */
const toggleNode = async(state, node) => {
    const container = node.querySelector(':scope > [data-region="children"]');
    const toggler = node.querySelector(':scope > .local-dimensions-cb-row [data-action="toggle"]');
    const icon = node.querySelector(':scope > .local-dimensions-cb-row .fa');
    if (!container || !icon) {
        return;
    }
    if (node.dataset.expanded === '1') {
        container.hidden = true;
        node.dataset.expanded = '0';
        toggler.setAttribute('aria-expanded', 'false');
        icon.classList.replace('fa-chevron-down', 'fa-chevron-right');
        return;
    }
    node.dataset.expanded = '1';
    container.hidden = false;
    toggler.setAttribute('aria-expanded', 'true');
    icon.classList.replace('fa-chevron-right', 'fa-chevron-down');
    if (container.dataset.offset === '0' && !container.children.length) {
        await loadChildren(state, node);
    }
};

/**
 * Load the next top-level page (roots in tree mode, matches in search mode).
 *
 * @param {Object} state Browser state.
 * @return {Promise<void>}
 */
const loadTopPage = async(state) => {
    if (state.loading || (state.total && state.offset >= state.total)) {
        return;
    }
    state.loading = true;
    try {
        const response = await browse({
            frameworkid: state.frameworkid,
            parentid: 0,
            query: state.mode === 'search' ? state.query : '',
            limitfrom: state.offset,
            limitnum: PAGE_SIZE,
        });
        appendNodes(state, state.listEl, response.items, 0);
        state.offset += response.items.length;
        state.total = response.total;
    } finally {
        state.loading = false;
    }
};

/**
 * Reset the list for a mode switch.
 *
 * @param {Object} state Browser state.
 * @return {void}
 */
const resetList = (state) => {
    state.listEl.textContent = '';
    state.offset = 0;
    state.total = 0;
    state.loading = false;
    state.lastChecked = null;
};

/**
 * Switch the browser to a mode (tree or search) and load the first page.
 *
 * @param {Object} state Browser state.
 * @param {String} mode 'tree' or 'search'.
 * @param {String} query Search text (search mode only).
 * @return {Promise<void>}
 */
export const applyMode = async(state, mode, query) => {
    state.mode = mode;
    state.query = query;
    resetList(state);
    await loadTopPage(state);
    if (!state.listEl.querySelector('.local-dimensions-cb-node')) {
        const empty = document.createElement('div');
        empty.className = 'text-muted small';
        empty.textContent = state.emptylabel;
        state.listEl.appendChild(empty);
    }
    syncPathToggle(state);
};

/**
 * Reflect the current mode on the path toggle: forced on (and locked) while searching, otherwise
 * mirrors the user's chosen state.
 *
 * @param {Object} state Browser state.
 * @return {void}
 */
const syncPathToggle = (state) => {
    const toggle = state.root.querySelector(SELECTORS.pathToggle);
    if (!toggle) {
        return;
    }
    toggle.checked = state.mode === 'search' ? true : state.showpath;
    toggle.disabled = state.mode === 'search';
};

/**
 * Show or hide all rendered path lines according to the current mode/toggle.
 *
 * @param {Object} state Browser state.
 * @return {void}
 */
const applyPathVisibility = (state) => {
    const show = pathsVisible(state);
    state.listEl.querySelectorAll('.local-dimensions-cb-path').forEach((el) => {
        el.hidden = !show;
    });
};

/**
 * Apply shift-click range selection between the last toggled checkbox and the current one.
 *
 * @param {Object} state Browser state.
 * @param {HTMLInputElement} check The clicked checkbox.
 * @param {Boolean} shift Whether shift was held.
 * @return {void}
 */
const handleShiftSelect = (state, check, shift) => {
    if (shift && state.lastChecked) {
        const all = Array.from(state.listEl.querySelectorAll('input[type="checkbox"]:not(:disabled)'));
        const start = all.indexOf(state.lastChecked);
        const end = all.indexOf(check);
        if (start !== -1 && end !== -1) {
            const lo = Math.min(start, end);
            const hi = Math.max(start, end);
            for (let i = lo; i <= hi; i++) {
                all[i].checked = check.checked;
            }
        }
    }
    state.lastChecked = check;
};

/**
 * Debounced filter handler: switch to search mode (or back to tree) as the user types.
 *
 * @param {Object} state Browser state.
 * @param {String} value Current filter text.
 * @return {void}
 */
const onFilterInput = (state, value) => {
    if (state.debounce) {
        window.clearTimeout(state.debounce);
    }
    state.debounce = window.setTimeout(() => {
        const query = value.trim();
        if (query.length >= SEARCH_MIN) {
            applyMode(state, 'search', query).catch(notifyError);
        } else if (state.mode === 'search') {
            applyMode(state, 'tree', '').catch(notifyError);
        }
    }, 250);
};

/**
 * Sync the persistent selection set with the checkboxes currently rendered.
 *
 * Ids not currently rendered keep their state, so checks survive re-renders
 * (mode switches, filter changes) until the host clears the set.
 *
 * @param {Object} state Browser state.
 * @return {void}
 */
const syncChecked = (state) => {
    state.listEl.querySelectorAll('input[type="checkbox"]:not(:disabled)').forEach((input) => {
        if (input.checked) {
            state.checked.add(input.value);
        } else {
            state.checked.delete(input.value);
        }
    });
};

/**
 * Handle clicks inside the list: expand/collapse toggles and checkbox range selection.
 *
 * @param {Object} state Browser state.
 * @param {Event} event The click event.
 * @return {void}
 */
const onListClick = (state, event) => {
    const toggler = event.target.closest('[data-action="toggle"]');
    if (toggler) {
        const node = toggler.closest('.local-dimensions-cb-node');
        if (node) {
            toggleNode(state, node).catch(notifyError);
        }
        return;
    }
    if (event.target.closest('[data-role="load-more"]')) {
        return;
    }
    const node = event.target.closest('.local-dimensions-cb-node');
    if (!node) {
        return;
    }
    const check = node.querySelector(':scope > .local-dimensions-cb-row input[type="checkbox"]');
    if (!check || check.disabled) {
        return;
    }
    // A direct checkbox click has already toggled natively; a name/path/row click has not.
    if (event.target !== check) {
        check.checked = !check.checked;
    }
    handleShiftSelect(state, check, event.shiftKey);
    syncChecked(state);
};

/**
 * Collect the ids of the checked (and still pickable) competencies — including checks made
 * before a re-render on rows the current filter/mode does not show.
 *
 * @param {Object} state Browser state.
 * @return {Array} Competency ids, as numbers.
 */
export const getCheckedIds = (state) => Array.from(state.checked)
    .filter((id) => !state.excluded.has(id))
    .map((id) => Number(id));

/**
 * Wire the filter, path toggle and list interactions, load the tree and start the
 * infinite scroll.
 *
 * @param {Object} state Browser state (see the module docblock for the contract).
 * @return {Promise<void>}
 */
export const initBrowser = async(state) => {
    state.checked = new Set();
    state.togglelabel = await getString('show_more', 'local_dimensions');
    state.mode = 'tree';
    state.query = '';
    state.offset = 0;
    state.total = 0;
    state.loading = false;
    state.lastChecked = null;
    state.showpath = false;
    state.debounce = null;

    const filter = state.root.querySelector(SELECTORS.filter);
    if (filter) {
        filter.addEventListener('input', () => onFilterInput(state, filter.value));
    }
    const toggle = state.root.querySelector(SELECTORS.pathToggle);
    if (toggle) {
        toggle.addEventListener('change', () => {
            state.showpath = toggle.checked;
            applyPathVisibility(state);
        });
    }
    state.listEl.addEventListener('click', (event) => onListClick(state, event));

    // The sentinel sits right after the list so it shares the list's scroll container: a host
    // that caps the region's height keeps gating the infinite scroll (a sentinel outside the
    // scrollable box would always intersect and load every page at once).
    state.sentinel = document.createElement('div');
    state.listEl.insertAdjacentElement('afterend', state.sentinel);
    await applyMode(state, 'tree', '');
    state.observer = new IntersectionObserver((entries) => {
        if (entries.some((entry) => entry.isIntersecting)) {
            loadTopPage(state).catch(notifyError);
        }
    });
    state.observer.observe(state.sentinel);
};

/**
 * Disconnect the infinite-scroll observer (call when the host modal closes).
 *
 * @param {Object} state Browser state.
 * @return {void}
 */
export const destroyBrowser = (state) => {
    if (state.observer) {
        state.observer.disconnect();
    }
};
