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
 * Lists attached cohorts with member/plan counts, attaches new cohorts (reusing the core
 * core/form-cohort-selector autocomplete), detaches them, and queues background plan generation.
 *
 * @module     local_dimensions/central/cohort_manager
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Modal from 'core/modal';
import ModalEvents from 'core/modal_events';
import Notification from 'core/notification';
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
 * Re-fetch the cohort list and rebuild the table body.
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
 * Detach a cohort after a lightweight confirm.
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
        removeCohort(state, row).catch(Notification.exception);
        return;
    }
    if (event.target.closest('[data-action="sync-cohort"]')) {
        Ajax.call([{
            methodname: 'local_dimensions_sync_template_cohort',
            args: {templateid: state.templateid, cohortid: Number(row.dataset.cohortid)},
        }])[0].then(() => addToast(state.queuedlabel)).catch(Notification.exception);
    }
};

/**
 * Open the manage-cohorts modal for the plans pane's selected template.
 *
 * @param {HTMLElement} pane The tab pane.
 * @param {HTMLElement} region The plans region (carries contextid).
 * @return {Promise<void>}
 */
export const show = async(pane, region) => {
    const [title, addlabel, synclabel, removelabel, nonelabel, queuedlabel] = await Promise.all([
        getString('central_cohorts_title', 'local_dimensions'),
        getString('central_cohorts_add', 'local_dimensions'),
        getString('central_cohorts_sync', 'local_dimensions'),
        getString('central_cohorts_remove', 'local_dimensions'),
        getString('central_cohorts_none', 'local_dimensions'),
        getString('central_cohorts_syncqueued', 'local_dimensions'),
    ]);
    const {html} = await Templates.renderForPromise('local_dimensions/central/cohort_manager', {
        contextid: Number(region.dataset.contextid),
    });
    const modal = await Modal.create({title, body: html});
    modal.setRemoveOnClose(true);

    const root = modal.getRoot()[0];
    const state = {
        templateid: Number(pane.dataset.templateid),
        rowsEl: root.querySelector(SELECTORS.rows),
        synclabel: synclabel,
        removelabel: removelabel,
        nonelabel: nonelabel,
        queuedlabel: queuedlabel,
    };

    const addsel = root.querySelector(SELECTORS.cohortAdd);
    state.addsel = addsel;
    if (addsel) {
        addsel.addEventListener('change', () => {
            const cohortid = Number(addsel.value);
            if (!cohortid) {
                return;
            }
            Ajax.call([{
                methodname: 'local_dimensions_add_template_cohort',
                args: {templateid: state.templateid, cohortid: cohortid},
            }])[0]
                .then(() => {
                    addToast(queuedlabel);
                    return refresh(state);
                })
                .catch(Notification.exception);
        });
    }

    state.rowsEl.addEventListener('click', (event) => onRowsClick(state, event));

    // Enhance the cohort autocomplete only once the modal is in the visible DOM: core/form-autocomplete's
    // enhance() resolves the element via document.querySelector, which finds nothing while the modal is
    // still detached (core/modal attaches it on show).
    modal.getRoot().on(ModalEvents.shown, () => {
        enhance(SELECTORS.cohortAdd, false, DATASOURCE, addlabel).catch(Notification.exception);
    });

    await refresh(state);
    modal.show();
};
