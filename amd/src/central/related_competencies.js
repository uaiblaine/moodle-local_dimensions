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
 * "Related competencies" modal: list a competency's related competencies, remove per row,
 * and add new ones through the same framework browser as the "Browse frameworks" modal —
 * debounced search plus the lazy competency tree with checkbox rows (shared
 * central/competency_tree_browser module) — minus the framework selector, because a
 * relation can only reference a competency of the same framework. The competency itself
 * and already-related competencies show as disabled rows. Rows are built in JS.
 * Relations are symmetric, so add/remove affects both directions.
 *
 * @module     local_dimensions/central/related_competencies
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import {flashRow} from 'local_dimensions/central/flash';
import ModalSaveCancel from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';
import Notification from 'core/notification';
import {notifyError} from 'local_dimensions/central/errors';
import Templates from 'core/templates';
import {getString} from 'core/str';
import {add as addToast, addToastRegion} from 'local_dimensions/central/toast';
import {applyMode, destroyBrowser, getCheckedIds, initBrowser} from 'local_dimensions/central/competency_tree_browser';

const SELECTORS = {
    region: '[data-region="related-competencies"]',
    pickerList: '[data-region="competency-list"]',
    relations: '[data-region="related-list"]',
    empty: '[data-region="related-empty"]',
};

/**
 * Build one related-competency row (name · idnumber · path + remove button).
 *
 * @param {Object} state Modal state.
 * @param {Object} item {id, shortname, idnumber, path}.
 * @return {HTMLElement}
 */
const makeRow = (state, item) => {
    const row = document.createElement('div');
    row.className = 'd-flex align-items-start border-bottom py-1';
    row.dataset.relatedid = String(item.id);

    const textcol = document.createElement('div');
    textcol.className = 'flex-grow-1';
    const name = document.createElement('span');
    name.className = 'fw-medium';
    name.textContent = item.shortname;
    textcol.appendChild(name);
    if (item.idnumber) {
        const idn = document.createElement('span');
        idn.className = 'font-monospace small text-muted ms-2';
        idn.textContent = item.idnumber;
        textcol.appendChild(idn);
    }
    if (item.path) {
        const path = document.createElement('div');
        path.className = 'small text-muted';
        path.textContent = item.path;
        textcol.appendChild(path);
    }

    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'btn btn-sm btn-link text-danger p-0 ms-2';
    remove.dataset.action = 'remove-related';
    remove.title = state.removelabel;
    const glyph = document.createElement('i');
    glyph.className = 'fa fa-trash';
    glyph.setAttribute('aria-hidden', 'true');
    const sr = document.createElement('span');
    sr.className = 'visually-hidden';
    sr.textContent = state.removelabel;
    remove.appendChild(glyph);
    remove.appendChild(sr);

    row.appendChild(textcol);
    row.appendChild(remove);
    return row;
};

/**
 * Fetch and render the current relations, rebuilding the exclude set.
 *
 * @param {Object} state Modal state.
 * @return {Promise<void>}
 */
const loadRelations = async(state) => {
    const response = await Ajax.call([{
        methodname: 'local_dimensions_list_related_competencies',
        args: {competencyid: state.competencyid},
    }])[0];
    state.relationsEl.textContent = '';
    state.excluded.clear();
    state.excluded.add(String(state.competencyid));
    response.items.forEach((item) => {
        state.relationsEl.appendChild(makeRow(state, item));
        state.excluded.add(String(item.id));
    });
    state.emptyEl.hidden = response.items.length > 0;
};

/**
 * Enable the footer "Add selected" button only while at least one pickable row is checked.
 *
 * @param {Object} state Modal state.
 * @return {void}
 */
const updateAddButton = (state) => {
    state.modal.setButtonDisabled('save', getCheckedIds(state).length === 0);
};

/**
 * Give keyboard focus a useful home when the previously focused control was disabled or
 * removed (focus falls to <body>, forcing keyboard users to re-traverse the dialog).
 *
 * @param {Object} state Modal state.
 * @param {HTMLElement|null} preferred Element to focus first, falling back to the filter.
 * @return {void}
 */
const restoreFocus = (state, preferred) => {
    if (document.activeElement !== document.body) {
        return;
    }
    const target = preferred || state.root.querySelector('[data-region="filter"]');
    if (target) {
        target.focus();
    }
};

/**
 * Remove a relation after a confirm.
 *
 * @param {Object} state Modal state.
 * @param {HTMLElement} rowEl The relation row.
 * @return {Promise<void>}
 */
const removeRelated = async(state, rowEl) => {
    const relatedid = Number(rowEl.dataset.relatedid);
    const name = rowEl.querySelector('.fw-medium').textContent;
    const [title, body] = await Promise.all([
        getString('central_related_remove', 'local_dimensions'),
        getString('central_related_remove_confirm', 'local_dimensions', name),
    ]);
    try {
        await Notification.deleteCancelPromise(title, body);
    } catch (e) {
        return;
    }
    await Ajax.call([{
        methodname: 'core_competency_remove_related_competency',
        args: {competencyid: state.competencyid, relatedcompetencyid: relatedid},
    }])[0];
    rowEl.remove();
    state.excluded.delete(String(relatedid));
    state.emptyEl.hidden = state.relationsEl.children.length > 0;
    // Re-render the tree so the removed competency becomes pickable again.
    await applyMode(state, state.mode, state.query);
    updateAddButton(state);
    // The confirm dialog restored focus to the now-detached row button.
    restoreFocus(state, state.relationsEl.querySelector('[data-action="remove-related"]'));
    addToast(state.removedlabel);
};

/**
 * Add every checked competency as a relation, then refresh the rows and the tree.
 *
 * @param {Object} state Modal state.
 * @return {Promise<void>}
 */
const addSelected = async(state) => {
    const ids = getCheckedIds(state);
    if (!ids.length) {
        return;
    }
    state.modal.setButtonDisabled('save', true);
    try {
        await Promise.all(Ajax.call(ids.map((relatedid) => ({
            methodname: 'core_competency_add_related_competency',
            args: {competencyid: state.competencyid, relatedcompetencyid: relatedid},
        }))));
        state.checked.clear();
    } finally {
        // A failing call in the batch does not roll back the earlier ones, so re-sync the
        // rows and the tree with the server even on error; the still-pending checks are
        // kept (and restored on render) so the user can retry them.
        await loadRelations(state);
        await applyMode(state, state.mode, state.query);
        updateAddButton(state);
        // Disabling the focused button dropped focus to <body>.
        restoreFocus(state, null);
    }
    ids.forEach((relatedid) => flashRow(state.relationsEl.querySelector(`[data-relatedid="${relatedid}"]`)));
    addToast(state.addedlabel);
};

/**
 * Open the Related competencies modal.
 *
 * @param {Object} opts {competencyid, competencyname, frameworkid}.
 * @return {Promise<void>}
 */
export const open = async(opts) => {
    const competencyid = Number(opts.competencyid);
    const [title, addlabel, closelabel, removelabel, addedlabel, removedlabel, selflabel, relatedlabel,
        loadmorelabel, emptylabel] =
        await Promise.all([
            getString('central_related_title', 'local_dimensions', opts.competencyname),
            getString('central_browseframeworks_add', 'local_dimensions'),
            getString('closebuttontitle', 'core'),
            getString('central_related_remove', 'local_dimensions'),
            getString('central_related_added', 'local_dimensions'),
            getString('central_related_removed', 'local_dimensions'),
            getString('central_related_self', 'local_dimensions'),
            getString('central_related_alreadyrelated', 'local_dimensions'),
            getString('central_browseframeworks_loadmore', 'local_dimensions'),
            getString('central_browseframeworks_empty', 'local_dimensions'),
        ]);
    const {html} = await Templates.renderForPromise('local_dimensions/central/related_competencies', {});
    // The primary action ("Add selected") lives in the footer the core reveals as soon as it has
    // children — ModalSaveCancel fills it with Cancel (relabelled Close, this modal manages in place
    // and has nothing to cancel) + the save button. See the ModalEvents.save wiring below.
    const modal = await ModalSaveCancel.create({
        title,
        body: html,
        removeOnClose: true,
        buttons: {save: addlabel, cancel: closelabel},
    });
    // Nothing is checked yet, so the primary action starts disabled.
    modal.setButtonDisabled('save', true);

    const root = modal.getRoot()[0];
    const state = {
        competencyid: competencyid,
        frameworkid: Number(opts.frameworkid),
        modal: modal,
        root: root,
        listEl: null,
        relationsEl: null,
        emptyEl: null,
        excluded: new Set([String(competencyid)]),
        excludedsuffix: (id) => (id === String(competencyid) ? selflabel : relatedlabel),
        loadmorelabel: loadmorelabel,
        emptylabel: emptylabel,
        removelabel: removelabel,
        addedlabel: addedlabel,
        removedlabel: removedlabel,
    };

    modal.getRoot().on(ModalEvents.shown, () => {
        // Host a toast region inside the modal body so toasts render above the dialog, not behind
        // it (the page-level region sits below the modal). Core removes it on close.
        addToastRegion(modal.getBody()[0]).catch(notifyError);
        const region = root.querySelector(SELECTORS.region);
        state.listEl = region.querySelector(SELECTORS.pickerList);
        state.relationsEl = region.querySelector(SELECTORS.relations);
        state.emptyEl = region.querySelector(SELECTORS.empty);
        region.addEventListener('click', (event) => {
            if (event.target.closest('[data-action="remove-related"]')) {
                removeRelated(state, event.target.closest('[data-relatedid]')).catch(notifyError);
            }
        });
        loadRelations(state)
            .then(() => initBrowser(state))
            .then(() => {
                // Registered after the browser's own click handler so row-click toggles are
                // already applied when the button state is recomputed.
                state.listEl.addEventListener('click', () => updateAddButton(state));
                state.listEl.addEventListener('change', () => updateAddButton(state));
                return null;
            })
            .catch(notifyError);
    });
    modal.getRoot().on(ModalEvents.save, (event) => {
        // Adding never closes the dialog — the confirmation toast, the row flash and the refreshed
        // relation list all land in it, and the user returns to the tree — so prevent the core's
        // close-on-save unconditionally (unlike the browse modal, which closes on a real add).
        event.preventDefault();
        addSelected(state).catch(notifyError);
    });
    modal.getRoot().on(ModalEvents.hidden, () => destroyBrowser(state));
    modal.show();
};
