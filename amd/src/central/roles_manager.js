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
 * "Assign roles" tab of the Manage participants modal: assign user-context roles to users over the
 * learning plan's linked cohorts, reusing core tool_cohortroles via local_dimensions web services.
 * Changes apply through a background sync; each assignment shows a Pending/Synced status.
 *
 * @module     local_dimensions/central/roles_manager
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import Templates from 'core/templates';
import {enhance} from 'core/form-autocomplete';
import {getString} from 'core/str';
import {add as addToast} from 'core/toast';

const USER_DATASOURCE = 'local_dimensions/central/user_datasource';

const SELECTORS = {
    noroles: '[data-region="role-noroles"]',
    nocohorts: '[data-region="role-nocohorts"]',
    form: '[data-region="role-form"]',
    user: '[data-region="role-user"]',
    role: '[data-region="role-role"]',
    cohort: '[data-region="role-cohort"]',
    rows: '[data-region="role-rows"]',
    add: '[data-action="role-add"]',
};

/**
 * Replace a select's options from a list of {value, label}.
 *
 * @param {HTMLSelectElement} select
 * @param {Array} options
 * @return {void}
 */
const fillSelect = (select, options) => {
    select.textContent = '';
    options.forEach((opt) => {
        const el = document.createElement('option');
        el.value = String(opt.value);
        el.textContent = opt.label;
        select.appendChild(el);
    });
};

/**
 * Build one assignment table row.
 *
 * @param {Object} state Tab state.
 * @param {Object} assignment Assignment record from the web service.
 * @return {HTMLElement}
 */
const makeRow = (state, assignment) => {
    const tr = document.createElement('tr');
    tr.dataset.assignmentid = String(assignment.id);

    const user = document.createElement('th');
    user.scope = 'row';
    user.textContent = assignment.userfullname;

    const role = document.createElement('td');
    role.textContent = assignment.rolename;
    role.dataset.rolename = assignment.rolename;

    const cohort = document.createElement('td');
    cohort.textContent = assignment.cohortname;

    const status = document.createElement('td');
    const badge = document.createElement('span');
    badge.className = 'badge ' + (assignment.status === 'synced' ? 'bg-success' : 'bg-secondary');
    badge.textContent = assignment.status === 'synced'
        ? `${state.syncedlabel} (${assignment.syncedcount}/${assignment.membercount})`
        : state.pendinglabel;
    status.appendChild(badge);

    const actions = document.createElement('td');
    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'btn btn-sm btn-link text-danger p-0';
    remove.dataset.action = 'role-remove';
    remove.textContent = state.removelabel;
    actions.appendChild(remove);

    tr.appendChild(user);
    tr.appendChild(role);
    tr.appendChild(cohort);
    tr.appendChild(status);
    tr.appendChild(actions);
    return tr;
};

/**
 * Fetch the tab data and (re)render selects, rows and empty/gate states.
 *
 * @param {Object} state Tab state.
 * @return {Promise<void>}
 */
const refresh = async(state) => {
    const data = await Ajax.call([{
        methodname: 'local_dimensions_list_template_cohort_roles',
        args: {templateid: state.templateid},
    }])[0];

    const noRoles = state.root.querySelector(SELECTORS.noroles);
    const noCohorts = state.root.querySelector(SELECTORS.nocohorts);
    const form = state.root.querySelector(SELECTORS.form);
    noRoles.hidden = data.roles.length > 0;
    noCohorts.hidden = data.cohorts.length > 0;
    form.hidden = !(data.roles.length > 0 && data.cohorts.length > 0);
    if (form.hidden) {
        return;
    }

    fillSelect(state.root.querySelector(SELECTORS.role), data.roles.map((r) => ({value: r.id, label: r.name})));
    fillSelect(state.root.querySelector(SELECTORS.cohort),
        data.cohorts.map((c) => ({value: c.cohortid, label: c.name})));

    const rows = state.root.querySelector(SELECTORS.rows);
    rows.textContent = '';
    if (!data.assignments.length) {
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 5;
        td.className = 'text-muted small';
        td.textContent = state.nonelabel;
        tr.appendChild(td);
        rows.appendChild(tr);
        return;
    }
    data.assignments.forEach((assignment) => rows.appendChild(makeRow(state, assignment)));
};

/**
 * Assign the selected role to the selected user over the selected cohort.
 *
 * @param {Object} state Tab state.
 * @return {void}
 */
const onAdd = (state) => {
    const userid = Number(state.root.querySelector(SELECTORS.user).value);
    const roleid = Number(state.root.querySelector(SELECTORS.role).value);
    const cohortid = Number(state.root.querySelector(SELECTORS.cohort).value);
    if (!userid || !roleid || !cohortid) {
        return;
    }
    Ajax.call([{
        methodname: 'local_dimensions_add_cohort_role',
        args: {templateid: state.templateid, userid: userid, roleid: roleid, cohortid: cohortid},
    }])[0]
        .then(() => {
            addToast(state.queuedlabel);
            return refresh(state);
        })
        .then(() => {
            const last = state.root.querySelector(`${SELECTORS.rows} tr:last-child`);
            if (last) {
                last.animate([{backgroundColor: '#fff3cd'}, {backgroundColor: 'transparent'}], {duration: 1500});
            }
            return null;
        })
        .catch(Notification.exception);
};

/**
 * Remove an assignment after a lightweight confirm.
 *
 * @param {Object} state Tab state.
 * @param {HTMLElement} row The assignment row.
 * @return {Promise<void>}
 */
const onRemove = async(state, row) => {
    const assignmentid = Number(row.dataset.assignmentid);
    const rolename = row.querySelector('[data-rolename]').dataset.rolename;
    const [title, body, removelabel] = await Promise.all([
        getString('central_roles_remove', 'local_dimensions'),
        getString('central_roles_remove_confirm', 'local_dimensions', rolename),
        getString('remove'),
    ]);
    try {
        await Notification.saveCancelPromise(title, body, removelabel);
    } catch (e) {
        return;
    }
    await Ajax.call([{
        methodname: 'local_dimensions_remove_cohort_role',
        args: {templateid: state.templateid, assignmentid: assignmentid},
    }])[0];
    addToast(state.queuedlabel);
    await refresh(state);
    // Keep keyboard focus in the list once the removed row disappears.
    const nextaction = state.root.querySelector('[data-action="role-remove"]');
    if (nextaction) {
        nextaction.focus();
    }
};

/**
 * Mount the roles manager into the Assign roles tab pane.
 *
 * @param {HTMLElement} container The pane element.
 * @param {Object} opts Options: templateid, contextid.
 * @return {Promise<void>}
 */
export const mount = async(container, opts) => {
    const [addlabel, removelabel, nonelabel, queuedlabel, pendinglabel, syncedlabel] = await Promise.all([
        getString('central_roles_selectuser', 'local_dimensions'),
        getString('central_roles_remove', 'local_dimensions'),
        getString('central_roles_none', 'local_dimensions'),
        getString('central_roles_queued', 'local_dimensions'),
        getString('central_roles_status_pending', 'local_dimensions'),
        getString('central_roles_status_synced', 'local_dimensions'),
    ]);
    const {html} = await Templates.renderForPromise('local_dimensions/central/roles_manager', {});
    await Templates.replaceNodeContents(container, html, '');

    const state = {
        templateid: Number(opts.templateid),
        root: container,
        removelabel: removelabel,
        nonelabel: nonelabel,
        queuedlabel: queuedlabel,
        pendinglabel: pendinglabel,
        syncedlabel: syncedlabel,
    };

    container.querySelector(SELECTORS.add).addEventListener('click', () => onAdd(state));
    container.querySelector(SELECTORS.rows).addEventListener('click', (event) => {
        const row = event.target.closest('tr[data-assignmentid]');
        if (row && event.target.closest('[data-action="role-remove"]')) {
            onRemove(state, row).catch(Notification.exception);
        }
    });
    await refresh(state);
    // Enhance the user picker (the pane is already attached + visible when mounted).
    enhance(SELECTORS.user, false, USER_DATASOURCE, addlabel).catch(Notification.exception);
};
