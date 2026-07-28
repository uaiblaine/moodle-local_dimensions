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
 * @module     local_dimensions/central/plans_transfer
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Modal from 'core/modal';
import ModalEvents from 'core/modal_events';
import Templates from 'core/templates';
import {getString} from 'core/str';
import {notifyError} from 'local_dimensions/central/errors';
import {add as addToast, addToastRegion} from 'local_dimensions/central/toast';
import {makeSpinner, triggerDownload} from 'local_dimensions/central/download';

const SELECTORS = {
    templateRow: '[data-region="template-row"]',
    select: '[data-region="export-select"]',
    selectAll: '[data-action="export-selectall"]',
    download: '[data-action="download"]',
    loader: '[data-region="export-loader"]',
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
    modal.show();
};
