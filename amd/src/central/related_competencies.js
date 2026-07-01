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
 * "Related competencies" modal: list a competency's related competencies (same framework),
 * remove per row, and add new ones via a framework-scoped search autocomplete. Rows are built
 * in JS. Relations are symmetric, so add/remove affects both directions.
 *
 * @module     local_dimensions/central/related_competencies
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Modal from 'core/modal';
import ModalEvents from 'core/modal_events';
import Notification from 'core/notification';
import Templates from 'core/templates';
import {enhance} from 'core/form-autocomplete';
import {getString} from 'core/str';
import {add as addToast, addToastRegion} from 'core/toast';

const DATASOURCE = 'local_dimensions/central/related_datasource';

const SELECTORS = {
    region: '[data-region="related-competencies"]',
    addSelect: '[data-region="related-add"]',
    list: '[data-region="related-list"]',
    empty: '[data-region="related-empty"]',
};

/**
 * Briefly highlight an element so an in-place change is visible without leaving the modal.
 *
 * @param {HTMLElement} el The element to flash.
 */
const flash = (el) => {
    if (!el || typeof el.animate !== 'function') {
        return;
    }
    el.animate(
        [{backgroundColor: '#fff3cd'}, {backgroundColor: 'transparent'}],
        {duration: 1500, easing: 'ease-out'}
    );
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
    state.listEl.textContent = '';
    state.excluded = new Set([String(state.competencyid)]);
    response.items.forEach((item) => {
        state.listEl.appendChild(makeRow(state, item));
        state.excluded.add(String(item.id));
    });
    state.emptyEl.hidden = response.items.length > 0;
    if (state.addsel) {
        state.addsel.dataset.exclude = Array.from(state.excluded).join(',');
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
    if (state.addsel) {
        state.addsel.dataset.exclude = Array.from(state.excluded).join(',');
    }
    state.emptyEl.hidden = state.listEl.children.length > 0;
    addToast(state.removedlabel);
};

/**
 * Add the picked competency, then re-fetch the list and reset the picker.
 *
 * @param {Object} state Modal state.
 * @return {void}
 */
const onAdd = (state) => {
    const relatedid = Number(state.addsel.value);
    if (!relatedid) {
        return;
    }
    state.excluded.add(String(relatedid));
    Ajax.call([{
        methodname: 'core_competency_add_related_competency',
        args: {competencyid: state.competencyid, relatedcompetencyid: relatedid},
    }])[0]
        .then(() => Templates.replaceNodeContents(state.addsel.parentElement, state.addshtml, ''))
        .then(() => {
            bindPicker(state);
            return loadRelations(state);
        })
        .then(() => {
            flash(state.listEl.querySelector(`[data-relatedid="${relatedid}"]`));
            addToast(state.addedlabel);
            return null;
        })
        .catch(Notification.exception);
};

/**
 * (Re-)bind the add picker: refresh the cached select and enhance the autocomplete.
 *
 * @param {Object} state Modal state.
 * @return {void}
 */
const bindPicker = (state) => {
    state.addsel = state.root.querySelector(SELECTORS.addSelect);
    state.addsel.dataset.frameworkid = String(state.frameworkid);
    state.addsel.dataset.exclude = Array.from(state.excluded).join(',');
    state.addsel.addEventListener('change', () => onAdd(state));
    enhance(SELECTORS.addSelect, false, DATASOURCE, state.addlabel).catch(Notification.exception);
};

/**
 * Open the Related competencies modal.
 *
 * @param {Object} opts {competencyid, competencyname, frameworkid}.
 * @return {Promise<void>}
 */
export const open = async(opts) => {
    const [title, addlabel, removelabel, addedlabel, removedlabel] = await Promise.all([
        getString('central_related_title', 'local_dimensions', opts.competencyname),
        getString('central_related_add', 'local_dimensions'),
        getString('central_related_remove', 'local_dimensions'),
        getString('central_related_added', 'local_dimensions'),
        getString('central_related_removed', 'local_dimensions'),
    ]);
    const {html} = await Templates.renderForPromise('local_dimensions/central/related_competencies', {
        competencyid: Number(opts.competencyid),
        frameworkid: Number(opts.frameworkid),
    });
    const modal = await Modal.create({title, body: html});
    modal.setRemoveOnClose(true);

    const root = modal.getRoot()[0];
    const state = {
        competencyid: Number(opts.competencyid),
        frameworkid: Number(opts.frameworkid),
        root: root,
        listEl: null,
        emptyEl: null,
        addsel: null,
        addshtml: '',
        excluded: new Set([String(Number(opts.competencyid))]),
        addlabel: addlabel,
        removelabel: removelabel,
        addedlabel: addedlabel,
        removedlabel: removedlabel,
    };

    modal.getRoot().on(ModalEvents.shown, () => {
        // Host a toast region inside the modal body so toasts render above the dialog, not behind
        // it (the page-level region sits below the modal). Core removes it on close.
        addToastRegion(modal.getBody()[0]).catch(Notification.exception);
        const region = root.querySelector(SELECTORS.region);
        state.listEl = region.querySelector(SELECTORS.list);
        state.emptyEl = region.querySelector(SELECTORS.empty);
        state.addshtml = region.querySelector(SELECTORS.addSelect).parentElement.innerHTML;
        region.addEventListener('click', (event) => {
            if (event.target.closest('[data-action="remove-related"]')) {
                removeRelated(state, event.target.closest('[data-relatedid]')).catch(Notification.exception);
            }
        });
        bindPicker(state);
        loadRelations(state).catch(Notification.exception);
    });
    modal.show();
};
