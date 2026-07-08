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
 * "Manage cohorts" modal for a learning plan template (Competency hub Plans tab).
 *
 * Lists attached cohorts with member/plan counts, attaches new cohorts (cohort autocomplete that
 * excludes the already-attached ones), detaches them, and queues background plan generation. After an
 * attach the modal body is re-rendered so the autocomplete resets to an empty, ready state.
 *
 * @module     local_dimensions/central/cohort_manager
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {notifyError} from 'local_dimensions/central/errors';
import Templates from 'core/templates';
import {enhance} from 'core/form-autocomplete';
import {getString} from 'core/str';
import {add as addToast} from 'core/toast';

const DATASOURCE = 'local_dimensions/central/cohort_datasource';

const SELECTORS = {
    cohortAdd: '[data-region="cohort-add"]',
    rows: '[data-region="cohort-rows"]',
};

/**
 * Build one cohort table row.
 *
 * @param {Object} state Modal state.
 * @param {Object} cohort {cohortid, name, members, plans}.
 * @return {HTMLElement}
 */
const makeRow = (state, cohort) => {
    const tr = document.createElement('tr');
    tr.dataset.cohortid = String(cohort.cohortid);

    const name = document.createElement('th');
    name.scope = 'row';
    name.textContent = cohort.name;

    const members = document.createElement('td');
    members.textContent = String(cohort.members);

    const plans = document.createElement('td');
    plans.textContent = String(cohort.plans);

    const actions = document.createElement('td');
    const sync = document.createElement('button');
    sync.type = 'button';
    sync.className = 'btn btn-sm btn-link p-0 me-2';
    sync.dataset.action = 'sync-cohort';
    sync.textContent = state.synclabel;
    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'btn btn-sm btn-link text-danger p-0';
    remove.dataset.action = 'remove-cohort';
    remove.textContent = state.removelabel;
    actions.appendChild(sync);
    actions.appendChild(remove);

    tr.appendChild(name);
    tr.appendChild(members);
    tr.appendChild(plans);
    tr.appendChild(actions);
    return tr;
};

/**
 * Re-fetch the cohort list, update the picker's exclude list, and rebuild the table body.
 *
 * @param {Object} state Modal state.
 * @return {Promise<void>}
 */
const refresh = async(state) => {
    const response = await Ajax.call([{
        methodname: 'local_dimensions_list_template_cohorts',
        args: {templateid: state.templateid},
    }])[0];
    if (state.addsel) {
        state.addsel.dataset.exclude = response.cohorts.map((cohort) => String(cohort.cohortid)).join(',');
    }
    state.rowsEl.textContent = '';
    if (!response.cohorts.length) {
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 4;
        td.className = 'text-muted small';
        td.textContent = state.nonelabel;
        tr.appendChild(td);
        state.rowsEl.appendChild(tr);
        return;
    }
    response.cohorts.forEach((cohort) => state.rowsEl.appendChild(makeRow(state, cohort)));
};

/**
 * Detach a cohort after a lightweight confirm, then refresh the table.
 *
 * @param {Object} state Modal state.
 * @param {HTMLElement} row The cohort row.
 * @return {Promise<void>}
 */
const removeCohort = async(state, row) => {
    const cohortid = Number(row.dataset.cohortid);
    const name = row.querySelector('th').textContent;
    const [title, body, removelabel] = await Promise.all([
        getString('central_cohorts_remove', 'local_dimensions'),
        getString('central_cohorts_remove_confirm', 'local_dimensions', name),
        getString('remove'),
    ]);
    try {
        await Notification.saveCancelPromise(title, body, removelabel);
    } catch (e) {
        return;
    }
    await Ajax.call([{
        methodname: 'local_dimensions_remove_template_cohort',
        args: {templateid: state.templateid, cohortid: cohortid},
    }])[0];
    await refresh(state);
    // Keep keyboard focus in the list once the removed row disappears.
    const nextaction = state.rowsEl.querySelector('[data-action="remove-cohort"]');
    if (nextaction) {
        nextaction.focus();
    }
};

/**
 * Handle clicks in the cohort table (sync / remove per row).
 *
 * @param {Object} state Modal state.
 * @param {Event} event Click event.
 * @return {void}
 */
const onRowsClick = (state, event) => {
    const row = event.target.closest('tr[data-cohortid]');
    if (!row) {
        return;
    }
    if (event.target.closest('[data-action="remove-cohort"]')) {
        removeCohort(state, row).catch(notifyError);
        return;
    }
    if (event.target.closest('[data-action="sync-cohort"]')) {
        Ajax.call([{
            methodname: 'local_dimensions_sync_template_cohort',
            args: {templateid: state.templateid, cohortid: Number(row.dataset.cohortid)},
        }])[0].then(() => addToast(state.queuedlabel)).catch(notifyError);
    }
};

/**
 * Attach the selected cohort, then re-render the body so the autocomplete resets.
 *
 * @param {Object} state Modal state.
 * @return {void}
 */
const onAdd = (state) => {
    const cohortid = Number(state.addsel.value);
    if (!cohortid) {
        return;
    }
    Ajax.call([{
        methodname: 'local_dimensions_add_template_cohort',
        args: {templateid: state.templateid, cohortid: cohortid},
    }])[0]
        .then(() => {
            addToast(state.queuedlabel);
            return Templates.replaceNodeContents(state.bodyEl, state.bodyhtml, '');
        })
        .then(() => setup(state))
        .catch(notifyError);
};

/**
 * Bind the (re-rendered) body: grab regions, wire events, enhance the picker, fill the table.
 *
 * @param {Object} state Modal state.
 * @return {Promise<void>}
 */
const setup = (state) => {
    state.rowsEl = state.bodyEl.querySelector(SELECTORS.rows);
    state.addsel = state.bodyEl.querySelector(SELECTORS.cohortAdd);
    if (state.addsel) {
        state.addsel.addEventListener('change', () => onAdd(state));
    }
    state.rowsEl.addEventListener('click', (event) => onRowsClick(state, event));
    enhance(SELECTORS.cohortAdd, false, DATASOURCE, state.addlabel).catch(notifyError);
    return refresh(state);
};

/**
 * Mount the cohort manager into a container (the Cohorts tab pane of the participants modal).
 * Call after the container is attached + visible so the autocomplete enhances correctly.
 *
 * @param {HTMLElement} container The element to render the cohort UI into.
 * @param {Object} opts Options: templateid, contextid.
 * @return {Promise<void>}
 */
export const mount = async(container, opts) => {
    const [addlabel, synclabel, removelabel, nonelabel, queuedlabel] = await Promise.all([
        getString('central_cohorts_add', 'local_dimensions'),
        getString('central_cohorts_sync', 'local_dimensions'),
        getString('central_cohorts_remove', 'local_dimensions'),
        getString('central_cohorts_none', 'local_dimensions'),
        getString('central_cohorts_syncqueued', 'local_dimensions'),
    ]);
    const {html} = await Templates.renderForPromise('local_dimensions/central/cohort_manager', {
        contextid: Number(opts.contextid),
    });
    const state = {
        templateid: Number(opts.templateid),
        bodyEl: container,
        bodyhtml: html,
        addlabel: addlabel,
        synclabel: synclabel,
        removelabel: removelabel,
        nonelabel: nonelabel,
        queuedlabel: queuedlabel,
        rowsEl: null,
        addsel: null,
    };
    await Templates.replaceNodeContents(container, html, '');
    await setup(state);
};
