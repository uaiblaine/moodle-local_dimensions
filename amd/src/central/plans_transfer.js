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
 * Learning plans tab: CSV transfer of learning plan templates.
 *
 * The export modal offers the templates the tab currently lists (its select is built from the
 * rendered rows, so the offering can never exceed what is on screen) and streams the CSV the
 * local_dimensions_export_templates web service returns.
 *
 * The import side is a two-step handoff: an upload-only dynamic form, then a preview modal that
 * shows what the file would do to this site. The preview modal opens on the upload modal's hidden
 * event rather than inside its submit handler, so it does not race Bootstrap's own body-class
 * cleanup, and the preview is re-fetched by a header refresh button — which is all the
 * re-validation the "import the missing structures in another tab, then re-check" flow needs.
 *
 * @module     local_dimensions/central/plans_transfer
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Modal from 'core/modal';
import ModalEvents from 'core/modal_events';
import ModalForm from 'core_form/modalform';
import ModalSaveCancel from 'core/modal_save_cancel';
import Templates from 'core/templates';
import {getString} from 'core/str';
import {flashRow} from 'local_dimensions/central/flash';
import {notifyError} from 'local_dimensions/central/errors';
import {reloadPane} from 'local_dimensions/central/tabs';
import {add as addToast, addToastRegion} from 'local_dimensions/central/toast';
import {makeSpinner, triggerDownload} from 'local_dimensions/central/download';
import * as ModalRefresh from 'local_dimensions/central/modal_refresh';

const IMPORT_FORM_CLASS = 'local_dimensions\\form\\import_templates_dynamic_form';

const SELECTORS = {
    templateRow: '[data-region="template-row"]',
    select: '[data-region="export-select"]',
    selectAll: '[data-action="export-selectall"]',
    download: '[data-action="download"]',
    loader: '[data-region="export-loader"]',
    frameworks: '[data-region="export-frameworks"]',
    frameworklist: '[data-region="export-frameworklist"]',
    downloadFramework: '[data-action="download-framework"]',
    groupToggle: '[data-action="toggle-group"]',
    preview: '[data-region="import-preview"]',
    row: '[data-region="import-row"]',
    itemCheck: '[data-region="item-check"]',
    linkCheck: '[data-region="link-check"]',
    link: '[data-region="import-link"]',
    remedy: '[data-region="remedy"]',
    remap: '[data-region="remap"]',
};

/**
 * Populate the export select from the tab's rendered template rows, pre-selecting the template
 * the tab currently shows in its detail pane.
 *
 * @param {HTMLElement} body The modal body.
 * @param {HTMLElement} region The plans region (holds the rows and the selected template id).
 * @return {void}
 */
const fillTemplateSelect = (body, region) => {
    const select = body.querySelector(SELECTORS.select);
    if (!select) {
        return;
    }
    const selectedid = region.dataset.templateid || '';
    region.querySelectorAll(SELECTORS.templateRow).forEach((row) => {
        const option = document.createElement('option');
        option.value = row.dataset.id;
        option.textContent = row.dataset.idnumber
            ? `${row.dataset.name} (${row.dataset.idnumber})`
            : row.dataset.name;
        option.selected = row.dataset.id === selectedid;
        select.append(option);
    });
};

/**
 * Select or deselect every template in the export select.
 *
 * @param {HTMLElement} body The modal body.
 * @param {Boolean} checked Whether every option should end up selected.
 * @return {void}
 */
const toggleSelectAll = (body, checked) => {
    const select = body.querySelector(SELECTORS.select);
    if (!select) {
        return;
    }
    [...select.options].forEach((option) => {
        option.selected = checked;
    });
};

/**
 * Offer every structure the exported plans reference as a companion download.
 *
 * A plan without its structures cannot be imported anywhere, and the import preview says so from
 * the other side; this is the same fact offered where the operator already is. The download goes
 * through the Structures tab's own existing web service.
 *
 * @param {HTMLElement} body The modal body.
 * @param {Array} frameworks The structures the export web service reported.
 * @return {void}
 */
const offerFrameworks = (body, frameworks) => {
    const holder = body.querySelector(SELECTORS.frameworks);
    const list = body.querySelector(SELECTORS.frameworklist);
    if (!holder || !list || !frameworks.length) {
        return;
    }
    list.replaceChildren();
    frameworks.forEach((framework) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'local-dimensions-central-btn-outline';
        button.dataset.action = 'download-framework';
        button.dataset.id = framework.id;
        const icon = document.createElement('i');
        icon.className = 'fa fa-download';
        icon.setAttribute('aria-hidden', 'true');
        button.append(icon, document.createTextNode(framework.shortname || framework.idnumber));
        list.append(button);
    });
    holder.hidden = false;
};

/**
 * Download one referenced structure through the Structures tab's export service.
 *
 * @param {HTMLElement} button The structure's download button.
 * @return {Promise<void>}
 */
const downloadFramework = async(button) => {
    button.disabled = true;
    try {
        const result = await Ajax.call([{
            methodname: 'local_dimensions_export_framework',
            args: {frameworkid: Number(button.dataset.id)},
        }])[0];
        triggerDownload(result.filename, result.content);
        addToast(await getString('central_frameworks_export_done', 'local_dimensions'), {type: 'success'});
    } finally {
        button.disabled = false;
    }
};

/**
 * Fetch the selected templates as CSV and hand the file to the browser, with a loader.
 *
 * @param {Modal} modal The export modal.
 * @param {HTMLElement} region The plans region (carries the hub context id).
 * @return {Promise<void>}
 */
const downloadTemplates = async(modal, region) => {
    const body = modal.getBody()[0];
    const select = body.querySelector(SELECTORS.select);
    const button = body.querySelector(SELECTORS.download);
    const loader = body.querySelector(SELECTORS.loader);
    const ids = [...select.selectedOptions].map((option) => option.value);
    if (!ids.length) {
        addToast(await getString('central_plans_export_none', 'local_dimensions'), {type: 'warning'});
        return;
    }
    const spinner = makeSpinner();
    button.disabled = true;
    loader.hidden = false;
    loader.append(spinner);
    try {
        const result = await Ajax.call([{
            methodname: 'local_dimensions_export_templates',
            args: {templateids: ids.join(','), contextid: Number(region.dataset.contextid)},
        }])[0];
        triggerDownload(result.filename, result.content);
        offerFrameworks(body, result.frameworks || []);
        addToast(await getString('central_plans_export_done', 'local_dimensions'), {type: 'success'});
    } catch (error) {
        notifyError(error);
    } finally {
        button.disabled = false;
        loader.hidden = true;
        spinner.remove();
    }
};

/**
 * Open the export-templates modal for the Learning plans tab.
 *
 * @param {HTMLElement} region The plans region.
 * @return {Promise<void>}
 */
export const openExportModal = async(region) => {
    const [title, body] = await Promise.all([
        getString('central_plans_export_title', 'local_dimensions'),
        Templates.render('local_dimensions/central/plans_export', {}),
    ]);
    const modal = await Modal.create({title: title, body: body});
    modal.setRemoveOnClose(true);
    modal.getRoot().on(ModalEvents.shown, () => {
        // The toast region is hosted in the modal body: the page-level wrapper sits below the
        // modal's z-index, so a toast fired from here would otherwise land behind the dialog.
        addToastRegion(modal.getBody()[0]).catch(notifyError);
        fillTemplateSelect(modal.getBody()[0], region);
    });
    modal.getRoot().on('change', SELECTORS.selectAll, (event) => {
        toggleSelectAll(modal.getBody()[0], event.target.checked);
    });
    modal.getRoot().on('click', SELECTORS.download, (event) => {
        event.preventDefault();
        downloadTemplates(modal, region).catch(notifyError);
    });
    modal.getRoot().on('click', SELECTORS.downloadFramework, (event) => {
        event.preventDefault();
        downloadFramework(event.target.closest(SELECTORS.downloadFramework)).catch(notifyError);
    });
    modal.show();
};

/**
 * A centred spinner with a label, used as the preview modal's body while the projection loads.
 *
 * @param {String} labelkey The lang key of the waiting message.
 * @return {Promise<HTMLElement>}
 */
const makeLoadingBody = async(labelkey) => {
    const holder = document.createElement('div');
    holder.className = 'text-center p-4 d-flex justify-content-center align-items-center gap-2';
    holder.setAttribute('role', 'status');
    const text = document.createElement('span');
    text.textContent = await getString(labelkey, 'local_dimensions');
    holder.append(makeSpinner(), text);
    return holder;
};

/**
 * Fetch the projection for the uploaded file and paint it into the modal body.
 *
 * @param {Modal} modal The preview modal.
 * @param {Object} settings The draft handle and parse settings the upload form returned.
 * @return {Promise<void>}
 */
const refreshPreview = async(modal, settings) => {
    const loading = await makeLoadingBody('central_plans_import_loading');
    modal.setBody(loading.outerHTML);
    const result = await Ajax.call([{
        methodname: 'local_dimensions_preview_import_templates',
        args: settings,
    }])[0];
    modal.setBody(result.html);
    // The toast region is re-added after every paint: setBody replaces the whole body, and the
    // page-level wrapper sits below the modal's z-index.
    await addToastRegion(modal.getBody()[0]);
};

/**
 * Expand or collapse one verdict group.
 *
 * @param {HTMLElement} button The group header button.
 * @return {void}
 */
const toggleGroup = (button) => {
    const rows = document.getElementById(button.getAttribute('aria-controls'));
    if (!rows) {
        return;
    }
    rows.hidden = !rows.hidden;
    button.setAttribute('aria-expanded', rows.hidden ? 'false' : 'true');
};

/**
 * Read the operator's choices off the rendered rows.
 *
 * Only choices travel: an item key, the verdict and fingerprint the row was drawn with, the
 * chosen remedy and the ticked competency keys. No ids, no field values — the server re-derives
 * everything else from the file and the database.
 *
 * @param {HTMLElement} body The modal body.
 * @return {Array} One selection per ticked row.
 */
const collectSelections = (body) => {
    const selections = [];
    body.querySelectorAll(SELECTORS.row).forEach((row) => {
        const check = row.querySelector(SELECTORS.itemCheck);
        if (!check || !check.checked) {
            return;
        }
        const remedy = row.querySelector(`${SELECTORS.remedy}:checked`);
        const links = [];
        row.querySelectorAll(SELECTORS.link).forEach((link) => {
            const linkcheck = link.querySelector(SELECTORS.linkCheck);
            if (linkcheck && linkcheck.checked) {
                links.push(link.dataset.itemkey);
            }
        });
        const remaps = [];
        row.querySelectorAll(SELECTORS.remap).forEach((select) => {
            remaps.push({token: select.dataset.token, value: select.value});
        });
        selections.push({
            itemkey: row.dataset.itemkey,
            verdict: row.dataset.verdict,
            fingerprint: row.dataset.fingerprint,
            remedy: remedy ? remedy.value : 'none',
            links: links,
            remaps: remaps,
        });
    });
    return selections;
};

/**
 * Repaint the rows the apply touched, flashing each so the change is visible where the operator
 * is looking, and report the run.
 *
 * @param {HTMLElement} body The modal body.
 * @param {Object} response The apply web service response.
 * @return {Promise<void>}
 */
const paintResults = async(body, response) => {
    await Promise.all(response.results.map(async(result) => {
        const selector = `${SELECTORS.row}[data-itemkey="${result.itemkey}"]`;
        const row = body.querySelector(selector);
        if (!row || !result.html) {
            return;
        }
        // Through core's own DOM replacement rather than innerHTML: the markup is server-rendered
        // Mustache, and this is the path that also runs any JS a template ships with.
        await Templates.replaceNode(row, result.html, '');
        flashRow(body.querySelector(selector));
    }));
    const written = response.counts.created + response.counts.updated;
    addToast(
        await getString('central_plans_import_applied', 'local_dimensions', {
            written: written,
            skipped: response.counts.skipped + response.counts.changed + response.counts.gone,
            failed: response.counts.failed,
        }),
        {type: response.counts.failed > 0 ? 'warning' : 'success'}
    );
};

/**
 * Apply the ticked rows.
 *
 * @param {Modal} modal The preview modal.
 * @param {Object} settings The draft handle and parse settings.
 * @param {HTMLElement} pane The tab pane, reloaded once something was written.
 * @return {Promise<void>}
 */
const applySelections = async(modal, settings, pane) => {
    const body = modal.getBody()[0];
    const selections = collectSelections(body);
    if (!selections.length) {
        addToast(await getString('central_plans_import_nothingticked', 'local_dimensions'), {type: 'warning'});
        return;
    }
    modal.setButtonDisabled('save', true);
    try {
        const response = await Ajax.call([{
            methodname: 'local_dimensions_apply_import_templates',
            args: {...settings, selections: selections},
        }])[0];
        await paintResults(body, response);
        if (response.counts.created + response.counts.updated > 0 && pane) {
            await reloadPane(pane, undefined, {quiet: true});
        }
    } finally {
        modal.setButtonDisabled('save', false);
    }
};

/**
 * Open the preview modal for an uploaded file.
 *
 * @param {HTMLElement} pane The tab pane, reloaded after a successful apply.
 * @param {Object} settings The draft handle and parse settings the upload form returned.
 * @return {Promise<void>}
 */
const openPreviewModal = async(pane, settings) => {
    const [title, savelabel, loading] = await Promise.all([
        getString('central_plans_import_preview_title', 'local_dimensions'),
        getString('central_plans_import_apply', 'local_dimensions'),
        makeLoadingBody('central_plans_import_loading'),
    ]);
    const modal = await ModalSaveCancel.create({title: title, body: loading.outerHTML, large: true});
    modal.setSaveButtonText(savelabel);
    modal.setRemoveOnClose(true);
    modal.getRoot().on(ModalEvents.shown, () => {
        const dialog = modal.getRoot()[0].querySelector('.modal-dialog');
        ModalRefresh.attach(dialog, () => refreshPreview(modal, settings)).catch(notifyError);
        refreshPreview(modal, settings).catch(notifyError);
    });
    modal.getRoot().on('click', SELECTORS.groupToggle, (event) => {
        event.preventDefault();
        toggleGroup(event.target.closest(SELECTORS.groupToggle));
    });
    modal.getRoot().on(ModalEvents.save, (event) => {
        // The modal stays open: the outcomes are painted onto the rows the operator was reading.
        event.preventDefault();
        applySelections(modal, settings, pane).catch(notifyError);
    });
    modal.show();
};

/**
 * Open the upload modal, then hand the uploaded file to the preview.
 *
 * The preview opens on the upload modal's hidden event, not inside the submit handler: opening a
 * second modal while Bootstrap is still tearing down the first races its body-class cleanup and
 * can leave the page unscrollable.
 *
 * @param {HTMLElement} pane The tab pane, reloaded after a successful apply.
 * @param {HTMLElement} region The plans region (carries the hub context id).
 * @return {Promise<void>}
 */
export const openImportModal = async(pane, region) => {
    let uploaded = null;
    const form = new ModalForm({
        formClass: IMPORT_FORM_CLASS,
        args: {contextid: Number(region.dataset.contextid)},
        modalConfig: {title: await getString('central_plans_import_title', 'local_dimensions')},
    });
    form.addEventListener(form.events.FORM_SUBMITTED, (event) => {
        uploaded = event.detail;
    });
    form.addEventListener(form.events.LOADED, () => {
        form.modal.getRoot().on(ModalEvents.hidden, () => {
            if (!uploaded) {
                return;
            }
            const settings = uploaded;
            uploaded = null;
            openPreviewModal(pane, settings).catch(notifyError);
        });
    });
    form.show();
};
