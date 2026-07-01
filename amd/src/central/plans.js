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
import ModalDeleteCancel from 'core/modal_delete_cancel';
import ModalEvents from 'core/modal_events';
import Notification from 'core/notification';
import Templates from 'core/templates';
import {enhance} from 'core/form-autocomplete';
import {getString} from 'core/str';
import {show as showCompetencyBrowser} from 'local_dimensions/central/competency_browser';
import {show as showParticipants} from 'local_dimensions/central/participants_manager';
import {reloadPane} from 'local_dimensions/central/tabs';

const FORM_CLASS = 'local_dimensions\\form\\template_dynamic_form';
const DATASOURCE = 'local_dimensions/central/competency_datasource';

const SELECTORS = {
    region: '[data-region="plans"]',
    competencySearch: '[data-region="competency-search"]',
    competencyAdd: '[data-region="competency-add"]',
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
 * With plans, a delete/cancel modal names the template, shows the real plan
 * count and spells out the consequence of each choice: unlink (default — the
 * plans keep existing without a template) or delete the learner plans.
 *
 * @param {HTMLElement} pane
 * @param {String|Number} id
 * @param {String} name
 * @param {String|Number} plancount Number of learner plans created from the template.
 * @return {Promise<void>}
 */
const deleteTemplate = async(pane, id, name, plancount) => {
    const templateid = Number(id);
    const hasplans = await Ajax.call([{
        methodname: 'core_competency_template_has_related_data',
        args: {id: templateid},
    }])[0];

    const remove = (deleteplans) => Ajax.call([{
        methodname: 'core_competency_delete_template',
        args: {id: templateid, deleteplans: deleteplans},
    }])[0].then(() => reloadPane(pane)).catch(Notification.exception);

    if (hasplans) {
        const {html} = await Templates.renderForPromise('local_dimensions/delete_template_modal', {
            name: name,
            plancount: Number(plancount) || 0,
        });
        const modal = await ModalDeleteCancel.create({
            title: getString('managetemplates_delete', 'local_dimensions'),
            body: html,
            show: true,
            removeOnClose: true,
        });
        modal.getRoot().on(ModalEvents.delete, () => {
            const checked = modal.getRoot()[0]
                .querySelector('input[name="local-dimensions-delete-template-choice"]:checked');
            remove(!!checked && checked.value === 'delete');
        });
        return;
    }

    const title = await getString('deletetemplate', 'tool_lp', name);
    try {
        await Notification.deleteCancelPromise(await getString('delete'), title);
    } catch (e) {
        return;
    }
    remove(false);
};

/**
 * Remove a competency from the template after a lightweight confirm, then refresh the tab.
 *
 * @param {HTMLElement} pane
 * @param {String|Number} id
 * @param {String} name
 * @return {Promise<void>}
 */
const removeCompetency = async(pane, id, name) => {
    const competencyid = Number(id);
    const [title, body, removelabel] = await Promise.all([
        getString('central_removecompetency', 'local_dimensions'),
        getString('central_removecompetency_confirm', 'local_dimensions', name),
        getString('remove'),
    ]);
    try {
        await Notification.saveCancelPromise(title, body, removelabel);
    } catch (e) {
        return;
    }
    await Ajax.call([{
        methodname: 'core_competency_remove_competency_from_template',
        args: {templateid: Number(pane.dataset.templateid), competencyid: competencyid},
    }])[0];
    reloadPane(pane).catch(Notification.exception);
};

/**
 * Move a competency one position up or down within the template, then refresh the tab.
 *
 * @param {HTMLElement} pane
 * @param {HTMLElement} button The clicked move button.
 * @param {String} direction 'up' or 'down'.
 * @return {Promise<void>}
 */
const moveCompetency = async(pane, button, direction) => {
    const li = button.closest('[data-competency]');
    if (!li) {
        return;
    }
    const sibling = direction === 'up' ? li.previousElementSibling : li.nextElementSibling;
    if (!sibling || !sibling.dataset.competency) {
        return;
    }
    await Ajax.call([{
        methodname: 'core_competency_reorder_template_competency',
        args: {
            templateid: Number(pane.dataset.templateid),
            competencyidfrom: Number(li.dataset.competency),
            competencyidto: Number(sibling.dataset.competency),
        },
    }])[0];
    reloadPane(pane).catch(Notification.exception);
};

/**
 * Click dispatch for the plans region, keyed by the clicked element's data-action.
 * Each handler receives (pane, region, target). Kept as a flat map so the click
 * listener stays trivial (one lookup) instead of a long if/else chain.
 *
 * @type {Object}
 */
const ACTION_HANDLERS = {
    'select-template': (pane, region, target) => {
        pane.dataset.templateid = target.dataset.id;
        reloadPane(pane).catch(Notification.exception);
    },
    'clear-competency': (pane) => {
        pane.dataset.competencyid = 0;
        reloadPane(pane).catch(Notification.exception);
    },
    'browse-frameworks': (pane, region) => showCompetencyBrowser(pane, region).catch(Notification.exception),
    'manage-participants': (pane, region) => showParticipants(pane, region).catch(Notification.exception),
    'new-template': (pane, region) => openForm(
        pane,
        {id: 0, contextid: region.dataset.contextid || 0},
        'managetemplates_addtemplate',
        'local_dimensions'
    ),
    'edit-template': (pane, region, target) => openForm(pane, {id: target.dataset.id}, 'edittemplate', 'tool_lp'),
    'delete-template': (pane, region, target) =>
        deleteTemplate(pane, target.dataset.id, target.dataset.name || '', target.dataset.plancount || 0)
            .catch(Notification.exception),
    'remove-competency': (pane, region, target) =>
        removeCompetency(pane, target.dataset.id, target.dataset.name || '').catch(Notification.exception),
    'move-competency-up': (pane, region, target) => moveCompetency(pane, target, 'up').catch(Notification.exception),
    'move-competency-down': (pane, region, target) => moveCompetency(pane, target, 'down').catch(Notification.exception),
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

    // The server auto-selects a template (selectedtemplateid); mirror it onto the pane dataset so
    // getContent args and the add/remove/reorder web services target the rendered template even
    // before the user clicks one (otherwise templateid is absent and the WS gets 0 -> invalid context).
    if (pane && region.dataset.templateid) {
        pane.dataset.templateid = region.dataset.templateid;
    }

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

    const addpicker = region.querySelector(SELECTORS.competencyAdd);
    if (addpicker && pane && !addpicker.dataset.enhanced) {
        addpicker.dataset.enhanced = '1';
        addpicker.addEventListener('change', () => {
            const competencyid = Number(addpicker.value);
            if (!competencyid) {
                return;
            }
            Ajax.call([{
                methodname: 'core_competency_add_competency_to_template',
                args: {templateid: Number(pane.dataset.templateid), competencyid: competencyid},
            }])[0].then(() => reloadPane(pane)).catch(Notification.exception);
        });
        getString('central_addcompetency', 'local_dimensions')
            .then((placeholder) => enhance(SELECTORS.competencyAdd, false, DATASOURCE, placeholder, false, true, '', true))
            .catch(Notification.exception);
    }

    region.addEventListener('click', (event) => {
        const target = event.target.closest('[data-action]');
        if (!target || !pane) {
            return;
        }
        const handler = ACTION_HANDLERS[target.dataset.action];
        if (handler) {
            handler(pane, region, target);
        }
    });
};
