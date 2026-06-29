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
import {open as openScaleConfig} from 'local_dimensions/central/framework_scaleconfig';

const FORM_CLASS = 'local_dimensions\\form\\framework_dynamic_form';

const SELECTORS = {
    region: '[data-region="frameworks"]',
    row: '[data-framework]',
};

/** @type {Boolean} Whether the document-level scale-config delegation is wired (once per page). */
let scaleconfigwired = false;

/**
 * Open the scale-config modal for the framework form's current scale and write the result back.
 *
 * @return {void}
 */
const openScaleConfigForForm = () => {
    // Select by name, not id: core_form\dynamic_form appends a random suffix to element ids
    // (e.g. id_scaleid_c5fLCIS8ExDrcVf), so a fixed #id_scaleid selector never matches.
    const scale = document.querySelector('select[name="scaleid"]');
    const hidden = document.querySelector('[name="scaleconfiguration"]');
    const summary = document.querySelector('[data-region="scaleconfig-summary"]');
    if (!scale || !hidden) {
        return;
    }
    openScaleConfig(Number(scale.value), hidden.value)
        .then((json) => {
            if (!json) {
                return null;
            }
            hidden.value = json;
            return getString('central_frameworks_scaleconfigured', 'local_dimensions');
        })
        .then((label) => {
            if (label && summary) {
                summary.textContent = label;
            }
            return null;
        })
        .catch(Notification.exception);
};

/**
 * Set up document-level delegation for the framework form's scale-config button (once per page).
 * The dynamic form renders inside a modalform whose JS lifecycle does not run our init, so the button
 * is wired globally — the click bubbles to the document regardless of when the form body renders.
 *
 * @return {void}
 */
const setupScaleConfigDelegation = () => {
    if (scaleconfigwired) {
        return;
    }
    scaleconfigwired = true;
    // Capture phase: fires on the way down from the document, before anything inside the modalform
    // can stop the click from propagating — so the handler runs regardless of the modal's internals.
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-action="configure-scale"]')) {
            event.preventDefault();
            openScaleConfigForForm();
        }
    }, true);
    document.addEventListener('change', (event) => {
        if (event.target.name !== 'scaleid') {
            return;
        }
        const hidden = document.querySelector('[name="scaleconfiguration"]');
        const summary = document.querySelector('[data-region="scaleconfig-summary"]');
        if (hidden) {
            hidden.value = '';
        }
        if (summary) {
            summary.textContent = '';
        }
    });
};

/**
 * Open the framework create/edit modal, wiring the scale-config control and pane refresh.
 *
 * @param {HTMLElement} pane The tab pane.
 * @param {Object} args The dynamic form args ({id} for edit, {id: 0, contextid} for create).
 * @param {String} titlekey The modal title lang key.
 * @return {Promise<void>}
 */
const openFrameworkForm = async(pane, args, titlekey) => {
    const form = new ModalForm({
        formClass: FORM_CLASS,
        args: args,
        modalConfig: {title: await getString(titlekey, 'local_dimensions')},
    });
    form.addEventListener(form.events.FORM_SUBMITTED, () => reloadPane(pane).catch(Notification.exception));
    form.show();
};

/**
 * Open the edit modal for a framework.
 *
 * @param {HTMLElement} pane The tab pane.
 * @param {Number} id The framework id.
 * @return {Promise<void>}
 */
const editFramework = (pane, id) => openFrameworkForm(pane, {id: id}, 'central_frameworks_edit');

/**
 * Open the create modal for a new framework in the tab's context.
 *
 * @param {HTMLElement} pane The tab pane.
 * @param {HTMLElement} region The frameworks region (carries data-contextid).
 * @return {Promise<void>}
 */
const createFramework = (pane, region) =>
    openFrameworkForm(pane, {id: 0, contextid: Number(region.dataset.contextid)}, 'central_frameworks_new');

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
    setupScaleConfigDelegation();

    region.addEventListener('click', (event) => {
        if (event.target.closest('[data-action="new"]')) {
            createFramework(pane, region).catch(Notification.exception);
            return;
        }
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

    const toggleHidden = region.querySelector('[data-action="toggle-hidden"]');
    if (toggleHidden && pane) {
        toggleHidden.addEventListener('change', () => {
            pane.dataset.showhidden = toggleHidden.checked ? '1' : '0';
            reloadPane(pane).catch(Notification.exception);
        });
    }
};
