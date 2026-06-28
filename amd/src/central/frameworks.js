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
 * Frameworks tab: native management actions (edit modal, duplicate, visibility toggle, reason-gated
 * delete). The framework list is server-rendered; every action refreshes the pane via reloadPane.
 *
 * @module     local_dimensions/central/frameworks
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import ModalForm from 'core_form/modalform';
import Notification from 'core/notification';
import {getString} from 'core/str';
import {reloadPane} from 'local_dimensions/central/tabs';

const FORM_CLASS = 'local_dimensions\\form\\framework_dynamic_form';

const SELECTORS = {
    region: '[data-region="frameworks"]',
    row: '[data-framework]',
};

/**
 * Open the edit modal for a framework and refresh the pane on success.
 *
 * @param {HTMLElement} pane The tab pane.
 * @param {Number} id The framework id.
 * @return {Promise<void>}
 */
const editFramework = async(pane, id) => {
    const form = new ModalForm({
        formClass: FORM_CLASS,
        args: {id: id},
        modalConfig: {title: await getString('central_frameworks_edit', 'local_dimensions')},
    });
    form.addEventListener(form.events.FORM_SUBMITTED, () => reloadPane(pane).catch(Notification.exception));
    form.show();
};

/**
 * Toggle a framework's visibility, then refresh the pane.
 *
 * @param {HTMLElement} pane The tab pane.
 * @param {HTMLElement} row The framework row.
 * @return {Promise<void>}
 */
const toggleVisibility = (pane, row) => {
    const visible = row.dataset.visible === '1' ? 0 : 1;
    return Ajax.call([{
        methodname: 'local_dimensions_set_framework_visibility',
        args: {frameworkid: Number(row.dataset.framework), visible: visible},
    }])[0].then(() => reloadPane(pane));
};

/**
 * Duplicate a framework, then refresh the pane.
 *
 * @param {HTMLElement} pane The tab pane.
 * @param {Number} id The framework id.
 * @return {Promise<void>}
 */
const duplicateFramework = (pane, id) =>
    Ajax.call([{methodname: 'core_competency_duplicate_competency_framework', args: {id: id}}])[0]
        .then(() => reloadPane(pane));

/**
 * Delete a framework after a cascade confirm, or explain why it is blocked.
 *
 * @param {HTMLElement} pane The tab pane.
 * @param {HTMLElement} row The framework row.
 * @return {Promise<void>}
 */
const deleteFramework = async(pane, row) => {
    const id = Number(row.dataset.framework);
    if (row.dataset.deletable !== '1') {
        Notification.alert('', await getString('central_frameworks_delete_blocked', 'local_dimensions'));
        return;
    }
    const [title, body] = await Promise.all([
        getString('delete'),
        getString('central_frameworks_delete_confirm', 'local_dimensions',
            {name: row.dataset.name, count: row.dataset.count}),
    ]);
    try {
        await Notification.deleteCancelPromise(title, body);
    } catch (e) {
        return;
    }
    const success = await Ajax.call([{
        methodname: 'core_competency_delete_competency_framework',
        args: {id: id},
    }])[0];
    if (success === false) {
        Notification.alert('', await getString('central_frameworks_delete_blocked', 'local_dimensions'));
        return;
    }
    await reloadPane(pane);
};

/**
 * Initialise the Frameworks tab. Re-runs after each tab refresh.
 *
 * @return {void}
 */
export const init = () => {
    const region = document.querySelector(SELECTORS.region);
    if (!region) {
        return;
    }
    const pane = region.closest('[data-tab-content]');

    region.addEventListener('click', (event) => {
        const row = event.target.closest(SELECTORS.row);
        if (!row) {
            return;
        }
        const id = Number(row.dataset.framework);
        if (event.target.closest('[data-action="edit"]')) {
            editFramework(pane, id).catch(Notification.exception);
        } else if (event.target.closest('[data-action="visibility"]')) {
            toggleVisibility(pane, row).catch(Notification.exception);
        } else if (event.target.closest('[data-action="duplicate"]')) {
            duplicateFramework(pane, id).catch(Notification.exception);
        } else if (event.target.closest('[data-action="delete"]')) {
            deleteFramework(pane, row).catch(Notification.exception);
        }
    });
};
