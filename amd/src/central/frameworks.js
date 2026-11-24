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
 * Frameworks tab (labelled "Structures"): create/edit modal, CSV import and export, duplicate,
 * visibility toggle and delete. The framework list is server-rendered; every action that changes it
 * reloads the pane via reloadPane.
 *
 * The same actions are exported for the Competencies tab, whose workspace carries them on its root node
 * and whose Structures menu carries creating and importing (amd/src/central/structure.js, and the workspace
 * through js/esm/src/hubv2/amd.ts): one implementation, each export taking what the tab reads from its row
 * as arguments and telling its caller what changed through a callback, so the tab keeps reloading its pane
 * and the Competencies tab reloads its own.
 *
 * @module     local_dimensions/central/frameworks
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Modal from 'core/modal';
import ModalEvents from 'core/modal_events';
import ModalForm from 'core_form/modalform';
import Notification from 'core/notification';
import {notifyError} from 'local_dimensions/central/errors';
import Templates from 'core/templates';
import {escapeHtml} from 'local_dimensions/central/escape';
import {getString} from 'core/str';
import {add as addToast, addToastRegion} from 'local_dimensions/central/toast';
import {makeSpinner, triggerDownload} from 'local_dimensions/central/download';
import {reloadPane} from 'local_dimensions/central/tabs';
import {open as openScaleConfig} from 'local_dimensions/central/framework_scaleconfig';
import * as ActionFooter from 'local_dimensions/central/action_footer';
import * as Preferences from 'local_dimensions/central/preferences';

const FORM_CLASS = 'local_dimensions\\form\\framework_dynamic_form';
const IMPORT_FORM_CLASS = 'local_dimensions\\form\\import_framework_dynamic_form';

const SELECTORS = {
    region: '[data-region="frameworks"]',
    row: '[data-framework]',
};

/** @type {Boolean} Whether the document-level scale-config delegation is wired (once per page). */
let scaleconfigwired = false;

/** @type {HTMLElement|null} The selected framework row, whose actions the footer drives. */
let activeFrameworkRow = null;
/** @type {HTMLElement|null} The tab region, captured at init for the footer dispatch. */
let activeRegion = null;
/** @type {HTMLElement|null} The tab pane, captured at init for the footer dispatch. */
let activePane = null;

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
        .catch(notifyError);
};

/**
 * Set up document-level delegation for the framework form's scale-config button (once per page).
 * The dynamic form renders inside a modalform whose JS lifecycle does not run our init, so the button
 * is handled from the document, whenever the form body renders.
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
        if (event.target.name !== 'scaleid' || event.target.hasAttribute('readonly')) {
            // A frozen scale select (the framework has user competencies) must not wipe the stored
            // proficiency config: the server pins scaleid via a form constant anyway.
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
 * Inject the "Open scales page" shortcut into the framework form modal footer, left of the
 * Save/Cancel buttons — same footer-link pattern as the participants modal. Only shown when the
 * user can reach the scales admin page.
 *
 * @param {ModalForm} form The modal form (after its LOADED event).
 * @param {Boolean} canscalespage Whether the viewer may open the scales page (the server's gate).
 * @return {Promise<void>}
 */
const injectScalesLink = async(form, canscalespage) => {
    if (!form.modal || !canscalespage) {
        return;
    }
    const root = form.modal.getRoot()[0];
    const footer = root.querySelector('.modal-footer');
    if (!footer || footer.querySelector('.local-dimensions-modal-footer-links')) {
        return;
    }
    const label = await getString('central_frameworks_openscales', 'local_dimensions');
    const group = document.createElement('div');
    group.className = 'local-dimensions-modal-footer-links';
    const link = document.createElement('a');
    link.href = M.cfg.wwwroot + '/grade/edit/scale/index.php';
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    link.className = 'btn btn-link p-0';
    const icon = document.createElement('i');
    icon.className = 'fa fa-external-link me-1';
    icon.setAttribute('aria-hidden', 'true');
    link.appendChild(icon);
    link.appendChild(document.createTextNode(label));
    group.appendChild(link);
    // First child so margin-right:auto pushes the form's Save/Cancel to the right.
    footer.insertBefore(group, footer.firstChild);
};

/**
 * Open the framework create/edit modal, wiring the scale-config control.
 *
 * @param {Object} args The dynamic form args ({id} for edit, {id: 0, contextid} for create).
 * @param {String} titlekey The modal title lang key.
 * @param {Object} options What the caller adds.
 * @param {Function} options.onSaved Called with the saved framework's id once the form is saved.
 * @param {Boolean} options.canscalespage Whether the footer offers the scales page.
 * @param {Boolean} [options.scale] Whether the scale configuration opens as soon as the form has loaded.
 * @return {Promise<void>}
 */
const openFrameworkForm = async(args, titlekey, {onSaved, canscalespage, scale = false}) => {
    setupScaleConfigDelegation();
    const form = new ModalForm({
        formClass: FORM_CLASS,
        args: args,
        modalConfig: {title: await getString(titlekey, 'local_dimensions')},
    });
    let scaleopened = false;
    form.addEventListener(form.events.FORM_SUBMITTED, (event) => {
        getString('central_frameworks_saved', 'local_dimensions').then(addToast).catch(notifyError);
        const saved = event.detail && event.detail.frameworkid ? Number(event.detail.frameworkid) : Number(args.id || 0);
        onSaved(saved);
    });
    form.addEventListener(form.events.LOADED, () => {
        injectScalesLink(form, canscalespage).catch(notifyError);
        // "Configure scale" from the Competencies tab: the form's own button, pressed once for the viewer.
        if (scale && !scaleopened) {
            scaleopened = true;
            openScaleConfigForForm();
        }
    });
    form.show();
};

/**
 * Open the edit modal for a framework (it carries the taxonomies and the scale).
 *
 * @param {Number} id The framework id.
 * @param {Object} options What the caller adds.
 * @param {Function} options.onSaved Called with the framework's id once the form is saved.
 * @param {Boolean} options.canscalespage Whether the footer offers the scales page.
 * @param {Boolean} [options.scale] Whether the scale configuration opens as soon as the form has loaded.
 * @return {Promise<void>}
 */
export const editFramework = (id, options) => openFrameworkForm({id: id}, 'central_frameworks_edit', options);

/**
 * Open the create modal for a new framework in a context.
 *
 * @param {Number} contextid The context the framework is created in (the tab's).
 * @param {Object} options What the caller adds.
 * @param {Function} options.onSaved Called with the new framework's id once it is created.
 * @param {Boolean} options.canscalespage Whether the footer offers the scales page.
 * @return {Promise<void>}
 */
export const createFramework = (contextid, options) =>
    openFrameworkForm({id: 0, contextid: Number(contextid)}, 'central_frameworks_new', options);

/**
 * Show a processing banner inside the import modal body while the CSV is imported in-request.
 *
 * @param {ModalForm} form The import modal form.
 * @return {Promise<void>}
 */
const showImportLoading = async(form) => {
    const body = form.modal && form.modal.getBody ? form.modal.getBody()[0] : null;
    if (!body || body.querySelector('[data-region="import-loading"]')) {
        return;
    }
    const label = await getString('central_frameworks_importing', 'local_dimensions');
    const banner = document.createElement('div');
    banner.dataset.region = 'import-loading';
    banner.className = 'alert alert-info d-flex align-items-center gap-2 mb-3';
    banner.setAttribute('role', 'status');
    const text = document.createElement('span');
    text.textContent = label;
    banner.append(makeSpinner(), text);
    body.prepend(banner);
};

/**
 * Remove the import processing banner (e.g. when server validation sends the form back).
 *
 * @param {ModalForm} form The import modal form.
 * @return {void}
 */
const hideImportLoading = (form) => {
    const body = form.modal && form.modal.getBody ? form.modal.getBody()[0] : null;
    const banner = body ? body.querySelector('[data-region="import-loading"]') : null;
    if (banner) {
        banner.remove();
    }
};

/**
 * Open the import-framework modal (a dynamic form with a CSV file picker) for a context.
 *
 * @param {Number} contextid The context the framework is imported into (the tab's).
 * @param {Function} onImported Called with the imported framework's id (0 when the answer names none).
 * @return {Promise<void>}
 */
export const importFramework = async(contextid, onImported) => {
    const form = new ModalForm({
        formClass: IMPORT_FORM_CLASS,
        args: {contextid: Number(contextid)},
        modalConfig: {title: await getString('central_frameworks_import_title', 'local_dimensions')},
    });
    form.addEventListener(form.events.SUBMIT_BUTTON_PRESSED, () => showImportLoading(form).catch(notifyError));
    form.addEventListener(form.events.CLIENT_VALIDATION_ERROR, () => hideImportLoading(form));
    form.addEventListener(form.events.SERVER_VALIDATION_ERROR, () => hideImportLoading(form));
    form.addEventListener(form.events.FORM_SUBMITTED, (event) => {
        const count = event.detail && event.detail.competencycount ? event.detail.competencycount : 0;
        onImported(event.detail && event.detail.frameworkid ? Number(event.detail.frameworkid) : 0);
        getString('central_frameworks_import_done', 'local_dimensions', {count: count})
            .then((message) => addToast(message, {type: 'success'}))
            .catch(notifyError);
    });
    form.show();
};

/**
 * Fetch a framework's CSV from the web service and download it, then say so.
 *
 * @param {Number} frameworkid The framework.
 * @return {Promise<void>}
 */
export const exportFramework = async(frameworkid) => {
    const result = await Ajax.call([{
        methodname: 'local_dimensions_export_framework',
        args: {frameworkid: Number(frameworkid)},
    }])[0];
    triggerDownload(result.filename, result.content);
    addToast(await getString('central_frameworks_export_done', 'local_dimensions'), {type: 'success'});
};

/**
 * Fetch the selected framework's CSV and download it, with a loader.
 *
 * @param {Modal} modal The export modal.
 * @return {Promise<void>}
 */
const downloadFramework = async(modal) => {
    const body = modal.getBody()[0];
    const select = body.querySelector('[data-region="export-select"]');
    const button = body.querySelector('[data-action="download"]');
    const loader = body.querySelector('[data-region="export-loader"]');
    const frameworkid = Number(select.value);
    if (!frameworkid) {
        return;
    }
    // Only this spinner is removed afterwards: the loader's visually hidden label is what it announces.
    const spinner = makeSpinner();
    button.disabled = true;
    loader.hidden = false;
    loader.append(spinner);
    try {
        await exportFramework(frameworkid);
    } catch (error) {
        notifyError(error);
    } finally {
        button.disabled = false;
        loader.hidden = true;
        spinner.remove();
    }
};

/**
 * Open the export modal: pick a framework from the current context and download its CSV.
 *
 * @param {HTMLElement} pane The tab pane (unused; kept for a consistent handler signature).
 * @param {HTMLElement} region The frameworks region (its rows list the frameworks to export).
 * @return {Promise<void>}
 */
const openExportModal = async(pane, region) => {
    const [title, body] = await Promise.all([
        getString('central_frameworks_export_title', 'local_dimensions'),
        Templates.render('local_dimensions/central/frameworks_export', {}),
    ]);
    const modal = await Modal.create({title: title, body: body});
    modal.getRoot().addClass('local-dimensions-modal');
    modal.setRemoveOnClose(true);
    modal.getRoot().on(ModalEvents.shown, () => {
        addToastRegion(modal.getBody()[0]).catch(notifyError);
        const select = modal.getBody()[0].querySelector('[data-region="export-select"]');
        region.querySelectorAll(SELECTORS.row).forEach((row) => {
            const option = document.createElement('option');
            option.value = row.dataset.frameworkid;
            option.textContent = row.dataset.name;
            select.append(option);
        });
    });
    modal.getRoot().on('click', '[data-action="download"]', (event) => {
        event.preventDefault();
        downloadFramework(modal).catch(notifyError);
    });
    modal.show();
};

/**
 * Set a framework's visibility through the plugin's service (core checks competencymanage in its context).
 *
 * @param {Number} id The framework id.
 * @param {Boolean} visible Whether it becomes visible.
 * @return {Promise<Object>} The service's answer.
 */
export const setVisibility = (id, visible) => Ajax.call([{
    methodname: 'local_dimensions_set_framework_visibility',
    args: {frameworkid: Number(id), visible: visible ? 1 : 0},
}])[0];

/**
 * Toggle a framework's visibility, then refresh the pane.
 *
 * @param {HTMLElement} pane The tab pane.
 * @param {HTMLElement} row The framework row.
 * @return {Promise<void>}
 */
const toggleVisibility = (pane, row) =>
    setVisibility(Number(row.dataset.framework), row.dataset.visible !== '1').then(() => reloadPane(pane));

/**
 * Duplicate a framework through core's service.
 *
 * @param {Number} id The framework id.
 * @return {Promise<Object>} The copy, as core's service describes it.
 */
export const duplicate = (id) =>
    Ajax.call([{methodname: 'core_competency_duplicate_competency_framework', args: {id: Number(id)}}])[0];

/**
 * Duplicate a framework, then refresh the pane.
 *
 * @param {HTMLElement} pane The tab pane.
 * @param {Number} id The framework id.
 * @return {Promise<void>}
 */
const duplicateFramework = (pane, id) => duplicate(id).then(() => reloadPane(pane));

/**
 * Delete a framework after a cascade confirm, or explain why it is blocked.
 *
 * @param {Object} framework What the confirmation names.
 * @param {Number} framework.id The framework id.
 * @param {String} framework.name Its name, the plain spelling.
 * @param {Number} framework.count How many competencies it holds.
 * @param {Boolean} framework.deletable Whether the server found every competency deletable.
 * @return {Promise<Boolean>} Whether core deleted it.
 */
export const confirmDelete = async({id, name, count, deletable}) => {
    if (!deletable) {
        Notification.alert('', await getString('central_frameworks_delete_blocked', 'local_dimensions'));
        return false;
    }
    // The name is the plain spelling and the confirm body is HTML.
    const [title, body] = await Promise.all([
        getString('delete'),
        getString('central_frameworks_delete_confirm', 'local_dimensions', {name: escapeHtml(name), count: count}),
    ]);
    try {
        await Notification.deleteCancelPromise(title, body);
    } catch (e) {
        return false;
    }
    const success = await Ajax.call([{
        methodname: 'core_competency_delete_competency_framework',
        args: {id: Number(id)},
    }])[0];
    if (success === false) {
        Notification.alert('', await getString('central_frameworks_delete_blocked', 'local_dimensions'));
        return false;
    }
    return true;
};

/**
 * Delete a framework after a cascade confirm, or explain why it is blocked, then refresh the pane.
 *
 * @param {HTMLElement} pane The tab pane.
 * @param {HTMLElement} row The framework row.
 * @return {Promise<void>}
 */
const deleteFramework = async(pane, row) => {
    const deleted = await confirmDelete({
        id: Number(row.dataset.framework),
        name: row.dataset.name,
        count: row.dataset.count,
        deletable: row.dataset.deletable === '1',
    });
    if (deleted) {
        await reloadPane(pane);
    }
};

/**
 * Route a sticky-footer [data-action] click to the selected framework's handler. Operates on
 * the module-level selected row, so it works from the page footer (outside the tab region).
 *
 * @param {HTMLElement} target The clicked [data-action] element.
 * @return {void}
 */
const dispatchFrameworksAction = (target) => {
    // Ignore footer clicks once this tab is no longer active (guards a lingering footer during
    // a slow tab switch).
    if (!activeFrameworkRow || !activeRegion || !activeRegion.closest('.tab-pane.active')) {
        return;
    }
    const row = activeFrameworkRow;
    const id = Number(row.dataset.framework);
    const action = target.dataset.action;
    if (action === 'edit') {
        const pane = activePane;
        editFramework(id, {
            onSaved: () => reloadPane(pane).catch(notifyError),
            canscalespage: activeRegion.dataset.canscalespage === '1',
        }).catch(notifyError);
    } else if (action === 'visibility') {
        toggleVisibility(activePane, row).catch(notifyError);
    } else if (action === 'duplicate') {
        duplicateFramework(activePane, id).catch(notifyError);
    } else if (action === 'delete') {
        deleteFramework(activePane, row).catch(notifyError);
    }
};

/**
 * Select a framework row and mirror its management actions into the shared sticky footer.
 * Managers only; the async render is guarded so a rapid re-select cannot leave the footer
 * bound to a stale row.
 *
 * @param {HTMLElement} region The tab region.
 * @param {HTMLElement} row The clicked [data-framework] row.
 * @return {void}
 */
const selectFramework = (region, row) => {
    activeFrameworkRow = row;
    region.querySelectorAll(SELECTORS.row).forEach((node) => node.classList.remove('active'));
    row.classList.add('active');
    if (region.dataset.canmanage !== '1') {
        ActionFooter.hide();
        return;
    }
    Templates.renderForPromise('local_dimensions/central/frameworks_footer_actions', {
        canmanage: true,
        visible: row.dataset.visible === '1',
    }).then(({html}) => {
        if (row.classList.contains('active') && region.closest('.tab-pane.active')) {
            ActionFooter.show(html, dispatchFrameworksAction);
        }
        return null;
    }).catch(Notification.exception);
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
    activeRegion = region;
    activePane = pane;
    // Reset the shared sticky footer on entry, but only when this tab is active: init re-runs
    // from an async tab load, so a late/out-of-order load for a tab the user left must not wipe
    // its footer. selectFramework re-shows it when a row is chosen.
    if (region.closest('.tab-pane.active')) {
        ActionFooter.hide();
    }
    setupScaleConfigDelegation();

    region.addEventListener('click', (event) => {
        if (event.target.closest('[data-action="new"]')) {
            createFramework(region.dataset.contextid, {
                onSaved: () => reloadPane(pane).catch(notifyError),
                canscalespage: region.dataset.canscalespage === '1',
            }).catch(notifyError);
            return;
        }
        if (event.target.closest('[data-action="import"]')) {
            importFramework(region.dataset.contextid, () => reloadPane(pane).catch(notifyError)).catch(notifyError);
            return;
        }
        if (event.target.closest('[data-action="export"]')) {
            openExportModal(pane, region).catch(notifyError);
            return;
        }
        const row = event.target.closest(SELECTORS.row);
        if (row) {
            selectFramework(region, row);
        }
    });

    const toggleHidden = region.querySelector('[data-action="toggle-hidden"]');
    if (toggleHidden && pane) {
        toggleHidden.addEventListener('change', () => {
            pane.dataset.showhidden = toggleHidden.checked ? '1' : '0';
            Preferences.saveDisplay({frameworksshowhidden: toggleHidden.checked});
            reloadPane(pane).catch(notifyError);
        });
    }
};
