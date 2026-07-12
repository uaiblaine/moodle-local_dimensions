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
import Modal from 'core/modal';
import ModalEvents from 'core/modal_events';
import ModalForm from 'core_form/modalform';
import Notification from 'core/notification';
import {notifyError} from 'local_dimensions/central/errors';
import Templates from 'core/templates';
import {getString} from 'core/str';
import {add as addToast, addToastRegion} from 'core/toast';
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
        if (event.target.name !== 'scaleid' || event.target.hasAttribute('readonly')) {
            // A frozen scale select (framework already graded) must not wipe the stored
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
 * Inject the "Open scales page" shortcut into the framework form modal header, just left of
 * the close button — same pattern and classes as the participants modal header links. Only
 * shown when the user can reach the scales admin page.
 *
 * @param {ModalForm} form The modal form (after its LOADED event).
 * @return {Promise<void>}
 */
const injectScalesLink = async(form) => {
    if (!form.modal) {
        return;
    }
    const root = form.modal.getRoot()[0];
    const dialog = root.querySelector('.modal-dialog');
    if (dialog) {
        // Applied even without the link: the shared class also standardises the close-button
        // chip, which otherwise only styles modals whose body carries plugin classes.
        dialog.classList.add('local-dimensions-headerlink-modal');
    }
    if (!activeRegion || activeRegion.dataset.canscalespage !== '1') {
        return;
    }
    const header = root.querySelector('.modal-header');
    if (!header || header.querySelector('.local-dimensions-headerlink')) {
        return;
    }
    const label = await getString('central_frameworks_openscales', 'local_dimensions');
    const link = document.createElement('a');
    link.href = M.cfg.wwwroot + '/grade/edit/scale/index.php';
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    link.className = 'btn btn-outline-secondary btn-sm local-dimensions-headerlink';
    const icon = document.createElement('i');
    icon.className = 'fa fa-arrow-up-right-from-square me-1';
    icon.setAttribute('aria-hidden', 'true');
    link.appendChild(icon);
    link.appendChild(document.createTextNode(label));
    header.insertBefore(link, header.querySelector('.btn-close'));
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
    form.addEventListener(form.events.FORM_SUBMITTED, () => reloadPane(pane).catch(notifyError));
    form.addEventListener(form.events.LOADED, () => injectScalesLink(form).catch(notifyError));
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
 * A small Bootstrap spinner element.
 *
 * @return {HTMLElement}
 */
const makeSpinner = () => {
    const spinner = document.createElement('span');
    spinner.className = 'spinner-border spinner-border-sm';
    spinner.setAttribute('role', 'status');
    spinner.setAttribute('aria-hidden', 'true');
    return spinner;
};

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
 * Open the import-framework modal (a dynamic form with a CSV file picker) for the tab's context.
 *
 * @param {HTMLElement} pane The tab pane.
 * @param {HTMLElement} region The frameworks region (carries data-contextid).
 * @return {Promise<void>}
 */
const openImportForm = async(pane, region) => {
    const form = new ModalForm({
        formClass: IMPORT_FORM_CLASS,
        args: {contextid: Number(region.dataset.contextid)},
        modalConfig: {title: await getString('central_frameworks_import_title', 'local_dimensions')},
    });
    form.addEventListener(form.events.SUBMIT_BUTTON_PRESSED, () => showImportLoading(form).catch(notifyError));
    form.addEventListener(form.events.CLIENT_VALIDATION_ERROR, () => hideImportLoading(form));
    form.addEventListener(form.events.SERVER_VALIDATION_ERROR, () => hideImportLoading(form));
    form.addEventListener(form.events.FORM_SUBMITTED, (event) => {
        const count = event.detail && event.detail.competencycount ? event.detail.competencycount : 0;
        reloadPane(pane).catch(notifyError);
        getString('central_frameworks_import_done', 'local_dimensions', {count: count})
            .then((message) => addToast(message, {type: 'success'}))
            .catch(notifyError);
    });
    form.show();
};

/**
 * Stream a CSV string to the browser as a downloaded file.
 *
 * @param {String} filename The suggested filename.
 * @param {String} content The CSV content.
 * @return {void}
 */
const triggerDownload = (filename, content) => {
    const blob = new Blob([content], {type: 'text/csv;charset=utf-8'});
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
};

/**
 * Fetch the selected framework's CSV from the web service and download it, with a loader.
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
    button.disabled = true;
    loader.hidden = false;
    loader.append(makeSpinner());
    try {
        const result = await Ajax.call([{
            methodname: 'local_dimensions_export_framework',
            args: {frameworkid: frameworkid},
        }])[0];
        triggerDownload(result.filename, result.content);
        addToast(await getString('central_frameworks_export_done', 'local_dimensions'), {type: 'success'});
    } catch (error) {
        notifyError(error);
    } finally {
        button.disabled = false;
        loader.hidden = true;
        loader.replaceChildren();
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
        editFramework(activePane, id).catch(notifyError);
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
            createFramework(pane, region).catch(notifyError);
            return;
        }
        if (event.target.closest('[data-action="import"]')) {
            openImportForm(pane, region).catch(notifyError);
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
