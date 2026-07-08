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
 * "Browse frameworks" modal for the Competency hub Plans tab: a framework selector on top of
 * the shared framework-competency browser (central/competency_tree_browser — debounced search,
 * lazy tree, checkbox rows, infinite scroll). Selected competencies are added with
 * core_competency_add_competency_to_template, then the plans pane is refreshed.
 *
 * @module     local_dimensions/central/competency_browser
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import ModalSaveCancel from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';
import {notifyError} from 'local_dimensions/central/errors';
import Templates from 'core/templates';
import {getString} from 'core/str';
import {applyMode, destroyBrowser, getCheckedIds, initBrowser} from 'local_dimensions/central/competency_tree_browser';
import {reloadPane} from 'local_dimensions/central/tabs';

const SELECTORS = {
    framework: '[data-region="framework"]',
    list: '[data-region="competency-list"]',
};

/**
 * Add the checked-and-enabled competencies to the template, then refresh the pane.
 *
 * @param {Object} state Browser state.
 * @return {void}
 */
const addSelected = (state) => {
    const calls = getCheckedIds(state).map((competencyid) => ({
        methodname: 'core_competency_add_competency_to_template',
        args: {templateid: Number(state.pane.dataset.templateid), competencyid: competencyid},
    }));
    if (!calls.length) {
        return;
    }
    Promise.all(Ajax.call(calls)).then(() => reloadPane(state.pane)).catch(notifyError);
};

/**
 * Open the browse-frameworks modal for the given plans pane.
 *
 * @param {HTMLElement} pane The tab pane.
 * @param {HTMLElement} region The plans region (contextid + the exclusion list of ids already on the plan).
 * @return {Promise<void>}
 */
export const show = async(pane, region) => {
    const raw = region.dataset.excludeids || '';
    const [addedlabel, loadmorelabel, emptylabel] = await Promise.all([
        getString('central_browseframeworks_alreadyadded', 'local_dimensions'),
        getString('central_browseframeworks_loadmore', 'local_dimensions'),
        getString('central_browseframeworks_empty', 'local_dimensions'),
    ]);

    const frameworks = await Ajax.call([{
        methodname: 'core_competency_list_competency_frameworks',
        args: {sort: 'shortname', context: {contextid: Number(region.dataset.contextid)}, includes: 'parents', onlyvisible: true},
    }])[0];

    const tplcontext = {
        hasframeworks: frameworks.length > 0,
        frameworks: frameworks.map((framework, index) => ({
            id: framework.id,
            shortname: framework.shortname,
            selected: index === 0,
        })),
    };
    const {html} = await Templates.renderForPromise('local_dimensions/central/competency_browser', tplcontext);

    const [title, addlabel] = await Promise.all([
        getString('central_browseframeworks', 'local_dimensions'),
        getString('central_browseframeworks_add', 'local_dimensions'),
    ]);
    const modal = await ModalSaveCancel.create({title, body: html});
    modal.setSaveButtonText(addlabel);
    modal.setRemoveOnClose(true);

    const root = modal.getRoot()[0];
    const state = {
        frameworkid: frameworks.length ? Number(frameworks[0].id) : 0,
        excluded: new Set(raw.split(',').filter((id) => id !== '')),
        excludedsuffix: () => addedlabel,
        loadmorelabel: loadmorelabel,
        emptylabel: emptylabel,
        pane: pane,
        root: root,
        listEl: root.querySelector(SELECTORS.list),
    };

    if (frameworks.length) {
        const fwselect = root.querySelector(SELECTORS.framework);
        if (fwselect) {
            fwselect.addEventListener('change', () => {
                state.frameworkid = Number(fwselect.value);
                // Selections are per framework: keeping them across a switch would silently
                // add competencies from a framework no longer on screen.
                state.checked.clear();
                applyMode(state, 'tree', '').catch(notifyError);
            });
        }
        await initBrowser(state);
    }

    modal.getRoot().on(ModalEvents.save, () => addSelected(state));
    modal.getRoot().on(ModalEvents.hidden, () => destroyBrowser(state));

    modal.show();
};
