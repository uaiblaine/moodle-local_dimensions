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
 * Learning plans tab: select a template, filter by competency, and create/edit/delete templates
 * in a modal (no page reload). Context arrives via the pane dataset (set by central/context).
 *
 * @module     local_dimensions/central/plans
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import ModalForm from 'core_form/modalform';
import ModalSaveCancel from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';
import Notification from 'core/notification';
import Templates from 'core/templates';
import {enhance} from 'core/form-autocomplete';
import {getString} from 'core/str';
import {reloadPane} from 'local_dimensions/central/tabs';

const FORM_CLASS = 'local_dimensions\\form\\template_dynamic_form';
const DATASOURCE = 'local_dimensions/central/competency_datasource';

const SELECTORS = {
    region: '[data-region="plans"]',
    selectTemplate: '[data-action="select-template"]',
    competencySearch: '[data-region="competency-search"]',
    clearCompetency: '[data-action="clear-competency"]',
    newTemplate: '[data-action="new-template"]',
    editTemplate: '[data-action="edit-template"]',
    deleteTemplate: '[data-action="delete-template"]',
};

/**
 * Open the template modal form and refresh the tab on success.
 *
 * @param {HTMLElement} pane
 * @param {Object} args
 * @param {String} titlekey
 * @param {String} titlecomponent
 */
const openForm = async(pane, args, titlekey, titlecomponent) => {
    const form = new ModalForm({
        formClass: FORM_CLASS,
        args,
        modalConfig: {title: await getString(titlekey, titlecomponent)},
    });
    form.addEventListener(form.events.FORM_SUBMITTED, () => reloadPane(pane).catch(Notification.exception));
    form.show();
};

/**
 * Delete a template, asking how to handle its learning plans when it has any.
 *
 * @param {HTMLElement} pane
 * @param {String|Number} id
 * @param {String} name
 * @return {Promise<void>}
 */
const deleteTemplate = async(pane, id, name) => {
    const templateid = Number(id);
    const hasplans = await Ajax.call([{
        methodname: 'core_competency_template_has_related_data',
        args: {id: templateid},
    }])[0];

    const remove = (deleteplans) => Ajax.call([{
        methodname: 'core_competency_delete_template',
        args: {id: templateid, deleteplans: deleteplans},
    }])[0].then(() => reloadPane(pane)).catch(Notification.exception);

    const title = await getString('deletetemplate', 'tool_lp', name);

    if (hasplans) {
        const {html} = await Templates.renderForPromise('local_dimensions/central/delete_template_plans', {});
        const modal = await ModalSaveCancel.create({title, body: html});
        modal.setSaveButtonText(await getString('delete'));
        modal.getRoot().on(ModalEvents.save, () => {
            const checked = modal.getRoot()[0].querySelector('input[name="deleteplans"]:checked');
            remove(!!checked && checked.value === '1');
        });
        modal.show();
        return;
    }

    try {
        await Notification.deleteCancelPromise(await getString('delete'), title);
    } catch (e) {
        return;
    }
    remove(false);
};

/**
 * Initialise the Learning plans tab. Re-runs after each tab refresh.
 */
export const init = () => {
    const region = document.querySelector(SELECTORS.region);
    if (!region) {
        return;
    }
    const pane = region.closest('[data-tab-content]');

    const search = region.querySelector(SELECTORS.competencySearch);
    if (search && pane && !search.dataset.enhanced) {
        search.dataset.enhanced = '1';
        search.addEventListener('change', () => {
            pane.dataset.competencyid = search.value || 0;
            reloadPane(pane).catch(Notification.exception);
        });
        getString('central_searchcompetency', 'local_dimensions')
            .then((placeholder) => enhance(SELECTORS.competencySearch, false, DATASOURCE, placeholder, false, true, '', true))
            .catch(Notification.exception);
    }

    region.addEventListener('click', (event) => {
        const item = event.target.closest(SELECTORS.selectTemplate);
        if (item && pane) {
            pane.dataset.templateid = item.dataset.id;
            reloadPane(pane).catch(Notification.exception);
            return;
        }
        if (event.target.closest(SELECTORS.clearCompetency) && pane) {
            pane.dataset.competencyid = 0;
            reloadPane(pane).catch(Notification.exception);
            return;
        }
        if (event.target.closest(SELECTORS.newTemplate) && pane) {
            openForm(pane, {id: 0, contextid: region.dataset.contextid || 0}, 'managetemplates_addtemplate', 'local_dimensions');
            return;
        }
        const edit = event.target.closest(SELECTORS.editTemplate);
        if (edit && pane) {
            openForm(pane, {id: edit.dataset.id}, 'edittemplate', 'tool_lp');
            return;
        }
        const del = event.target.closest(SELECTORS.deleteTemplate);
        if (del && pane) {
            deleteTemplate(pane, del.dataset.id, del.dataset.name || '').catch(Notification.exception);
        }
    });
};
