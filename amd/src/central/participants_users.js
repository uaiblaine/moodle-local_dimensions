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
 * Users grid for the Manage participants modal: an infinite-scroll paginated list of the template's
 * plans with unlink/delete actions, filters (cohort, name search, individual toggle) collapsed into
 * a filter-icon dropdown at the right of the list, and an assign-user autocomplete that only
 * suggests users without a plan created from the template.
 *
 * @module     local_dimensions/central/participants_users
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {notifyError} from 'local_dimensions/central/errors';
import {enhance} from 'core/form-autocomplete';
import {getString} from 'core/str';
import {add as addToast} from 'core/toast';

const PAGE_SIZE = 25;
const STATUS_COMPLETE = 2;
const DATASOURCE = 'local_dimensions/central/user_datasource';

const SELECTORS = {
    cohort: '[data-region="participant-cohort"]',
    search: '[data-region="participant-search"]',
    individual: '[data-region="participant-individual"]',
    filtersform: '[data-region="participant-filters-form"]',
    add: '[data-region="participant-add"]',
    rows: '[data-region="participant-rows"]',
    sentinel: '[data-region="participant-sentinel"]',
};

/**
 * Build one participant row.
 *
 * @param {Object} state Grid state.
 * @param {Object} item Participant record.
 * @return {HTMLElement}
 */
const makeRow = (state, item) => {
    const tr = document.createElement('tr');
    tr.dataset.planid = String(item.planid);
    tr.dataset.status = String(item.status);

    [item.fullname, item.statuslabel, item.modelo || '—', item.cohorts || '—'].forEach((text) => {
        const td = document.createElement('td');
        td.textContent = text;
        tr.appendChild(td);
    });

    const ind = document.createElement('td');
    if (item.isindividual) {
        const badge = document.createElement('span');
        badge.className = 'badge bg-secondary';
        badge.textContent = state.individuallabel;
        ind.appendChild(badge);
    } else {
        ind.textContent = '—';
    }
    tr.appendChild(ind);

    const actions = document.createElement('td');
    if (item.status !== STATUS_COMPLETE) {
        const unlink = document.createElement('button');
        unlink.type = 'button';
        unlink.className = 'btn btn-sm btn-link p-0 me-2';
        unlink.dataset.action = 'unlink-plan';
        unlink.textContent = state.unlinklabel;
        actions.appendChild(unlink);
    }
    const del = document.createElement('button');
    del.type = 'button';
    del.className = 'btn btn-sm btn-link text-danger p-0';
    del.dataset.action = 'delete-plan';
    del.textContent = state.deletelabel;
    actions.appendChild(del);
    tr.appendChild(actions);
    return tr;
};

/**
 * Load the next page of participants and append rows.
 *
 * @param {Object} state Grid state.
 * @return {Promise<void>}
 */
const loadPage = async(state) => {
    if (state.loading || (state.total && state.offset >= state.total)) {
        return;
    }
    state.loading = true;
    try {
        const response = await Ajax.call([{
            methodname: 'local_dimensions_list_template_participants',
            args: {
                templateid: state.templateid,
                cohortid: state.cohortid,
                query: state.query,
                includeindividual: state.includeindividual,
                limitfrom: state.offset,
                limitnum: PAGE_SIZE,
            },
        }])[0];
        response.items.forEach((item) => state.rowsEl.appendChild(makeRow(state, item)));
        state.offset += response.items.length;
        state.total = response.total;
    } finally {
        state.loading = false;
    }
    if (!state.rowsEl.querySelector('tr[data-planid]')) {
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 6;
        td.className = 'text-muted small';
        td.textContent = state.nonelabel;
        tr.appendChild(td);
        state.rowsEl.appendChild(tr);
    }
};

/**
 * Reset the grid and load the first page (after a filter/search/toggle change or a mutation).
 *
 * @param {Object} state Grid state.
 * @return {Promise<void>}
 */
const applyFilters = (state) => {
    state.rowsEl.textContent = '';
    state.offset = 0;
    state.total = 0;
    state.loading = false;
    return loadPage(state);
};

/**
 * Mutate one plan (unlink or delete) then reload the first page.
 *
 * @param {Object} state Grid state.
 * @param {HTMLElement} row The plan row.
 * @param {String} methodname Web service to call.
 * @return {Promise<void>}
 */
const mutate = async(state, row, methodname) => {
    await Ajax.call([{methodname: methodname, args: {planid: Number(row.dataset.planid)}}])[0];
    await applyFilters(state);
    // Keep keyboard focus in the grid once the mutated row's controls disappear.
    const nextaction = state.rowsEl.querySelector('[data-action="unlink-plan"], [data-action="delete-plan"]');
    if (nextaction) {
        nextaction.focus();
    }
};

/**
 * Handle clicks in the grid (unlink / delete per row).
 *
 * @param {Object} state Grid state.
 * @param {Event} event Click event.
 * @return {void}
 */
const onRowsClick = (state, event) => {
    const row = event.target.closest('tr[data-planid]');
    if (!row) {
        return;
    }
    if (event.target.closest('[data-action="unlink-plan"]')) {
        mutate(state, row, 'local_dimensions_unlink_template_user_plan').catch(notifyError);
        return;
    }
    if (event.target.closest('[data-action="delete-plan"]')) {
        getString('central_participants_delete_confirm', 'local_dimensions', row.querySelector('td').textContent)
            .then((body) => Notification.saveCancelPromise(state.deletelabel, body, state.deletelabel))
            .then(() => mutate(state, row, 'local_dimensions_delete_template_user_plan'))
            .catch(() => null);
    }
};

/**
 * Populate the cohort filter select with the template's attached cohorts.
 *
 * @param {Object} state Grid state.
 * @return {Promise<void>}
 */
const fillCohortFilter = async(state) => {
    const response = await Ajax.call([{
        methodname: 'local_dimensions_list_template_cohorts',
        args: {templateid: state.templateid},
    }])[0];
    const all = document.createElement('option');
    all.value = '0';
    all.textContent = state.allcohortslabel;
    state.cohortsel.appendChild(all);
    response.cohorts.forEach((cohort) => {
        const opt = document.createElement('option');
        opt.value = String(cohort.cohortid);
        opt.textContent = cohort.name;
        state.cohortsel.appendChild(opt);
    });
};

/**
 * Wire the filter bar, the add picker and the row actions.
 *
 * @param {Object} state Grid state.
 * @param {HTMLElement} pane The users pane.
 * @return {void}
 */
const wire = (state, pane) => {
    // The filter controls live in a <form> inside the filters dropdown (Bootstrap 4 keeps a
    // dropdown open for clicks inside a form); stop Enter in the search box from submitting it.
    pane.querySelector(SELECTORS.filtersform).addEventListener('submit', (event) => event.preventDefault());
    state.cohortsel.addEventListener('change', () => {
        state.cohortid = Number(state.cohortsel.value);
        applyFilters(state).catch(notifyError);
    });
    const searchel = pane.querySelector(SELECTORS.search);
    searchel.addEventListener('input', () => {
        if (state.debounce) {
            window.clearTimeout(state.debounce);
        }
        state.debounce = window.setTimeout(() => {
            state.query = searchel.value.trim();
            applyFilters(state).catch(notifyError);
        }, 250);
    });
    const toggle = pane.querySelector(SELECTORS.individual);
    toggle.addEventListener('change', () => {
        state.includeindividual = toggle.checked;
        applyFilters(state).catch(notifyError);
    });
    state.rowsEl.addEventListener('click', (event) => onRowsClick(state, event));

    const addsel = pane.querySelector(SELECTORS.add);
    addsel.addEventListener('change', () => {
        const userid = Number(addsel.value);
        if (!userid) {
            return;
        }
        Ajax.call([{
            methodname: 'local_dimensions_add_template_user_plan',
            args: {templateid: state.templateid, userid: userid},
        }])[0].then(() => {
            addToast(state.addedlabel);
            return applyFilters(state);
        }).catch(notifyError);
    });
    enhance(SELECTORS.add, false, DATASOURCE, state.addlabel).catch(notifyError);
};

/**
 * Mount the users grid into the (visible) users pane.
 *
 * @param {HTMLElement} pane The users tab pane.
 * @param {Object} opts Options: templateid, contextid.
 * @return {Promise<void>}
 */
export const mount = async(pane, opts) => {
    const labels = await Promise.all([
        getString('central_participants_individual', 'local_dimensions'),
        getString('central_participants_unlink', 'local_dimensions'),
        getString('central_participants_delete', 'local_dimensions'),
        getString('central_participants_none', 'local_dimensions'),
        getString('central_participants_allcohorts', 'local_dimensions'),
        getString('central_participants_add', 'local_dimensions'),
        getString('central_participants_added', 'local_dimensions'),
    ]);
    const addsel = pane.querySelector(SELECTORS.add);
    addsel.dataset.contextid = String(opts.contextid);
    addsel.dataset.templateid = String(opts.templateid);

    const state = {
        templateid: Number(opts.templateid),
        cohortid: 0,
        query: '',
        includeindividual: false,
        offset: 0,
        total: 0,
        loading: false,
        debounce: null,
        observer: null,
        rowsEl: pane.querySelector(SELECTORS.rows),
        cohortsel: pane.querySelector(SELECTORS.cohort),
        individuallabel: labels[0],
        unlinklabel: labels[1],
        deletelabel: labels[2],
        nonelabel: labels[3],
        allcohortslabel: labels[4],
        addlabel: labels[5],
        addedlabel: labels[6],
    };

    await fillCohortFilter(state);
    wire(state, pane);

    state.observer = new IntersectionObserver((entries) => {
        if (entries.some((entry) => entry.isIntersecting)) {
            loadPage(state).catch(notifyError);
        }
    });
    state.observer.observe(pane.querySelector(SELECTORS.sentinel));

    await applyFilters(state);
};
