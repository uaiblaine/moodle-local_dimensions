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
import {flashRow} from 'local_dimensions/central/flash';
import ModalForm from 'core_form/modalform';
import ModalDeleteCancel from 'core/modal_delete_cancel';
import ModalSaveCancel from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';
import Notification from 'core/notification';
import {notifyError} from 'local_dimensions/central/errors';
import Templates from 'core/templates';
import {enhance} from 'core/form-autocomplete';
import {getString} from 'core/str';
import {show as showCompetencyBrowser} from 'local_dimensions/central/competency_browser';
import {show as showParticipants} from 'local_dimensions/central/participants_manager';
import {initMasterResizer} from 'local_dimensions/central/pane_resizer';
import {reloadPane} from 'local_dimensions/central/tabs';
import CollapsibleDescription from 'local_dimensions/collapsible_description';
import * as ActionFooter from 'local_dimensions/central/action_footer';
import {openCompetencyDetailModal} from 'local_dimensions/central/competency_detail';
import * as Preferences from 'local_dimensions/central/preferences';

const FORM_CLASS = 'local_dimensions\\form\\template_dynamic_form';
const COMPETENCY_FORM_CLASS = 'local_dimensions\\form\\competency_dynamic_form';
const DATASOURCE = 'local_dimensions/central/competency_datasource';

/** @type {HTMLElement|null} The tab region, captured at init for the footer dispatch. */
let activeRegion = null;
/** @type {HTMLElement|null} The tab pane, captured at init for the footer dispatch. */
let activePane = null;

/** @type {Object} Map of display-toggle key to the CSS class it controls on the list. */
const DISPLAY_CLASSES = {tax: 'show-tax', path: 'show-path', id: 'show-id'};

/** @type {Object} Map of plan-list display-toggle key to the CSS class it controls on the rows. */
const LISTDISPLAY_CLASSES = {id: 'show-id', duedate: 'show-duedate'};

const SELECTORS = {
    region: '[data-region="plans"]',
    templateList: '[data-region="template-list"]',
    competencySearch: '[data-region="competency-search"]',
    filterPicker: '[data-region="competency-filter-picker"]',
    filterAddButton: '[data-action="add-filter-competency"]',
    planSearch: '[data-region="plan-search-input"]',
    templateRows: '[data-region="template-rows"]',
    templateRow: '[data-region="template-row"]',
    searchEmpty: '[data-region="plan-search-empty"]',
    plansBody: '[data-region="plans-body"]',
    plansResizer: '[data-region="plans-resizer"]',
    showDisabled: '[data-action="toggle-disabled"]',
    displayPanel: '[data-region="display-options-panel"]',
    displayGear: '[data-action="display-options"]',
    displayToggle: '[data-display-toggle]',
    listDisplayPanel: '[data-region="list-display-options-panel"]',
    listDisplayGear: '[data-action="list-display-options"]',
    listDisplayToggle: '[data-list-toggle]',
    competencyItems: '[data-region="competency-items"]',
    competencyList: '[data-region="competency-list"]',
    dragHandle: '[data-region="drag-handle"]',
    compName: '[data-region="comp-name"]',
};

/**
 * Reload the pane, restoring the scroll position of the given scroll regions so a
 * refresh does not jump long lists back to the top.
 *
 * @param {HTMLElement} pane
 * @param {String[]} [selectors] Scroll containers to preserve; defaults to both lists.
 * @return {Promise<void>}
 */
const reloadKeepingScroll = async(pane, selectors) => {
    const regions = selectors || [SELECTORS.templateRows, SELECTORS.competencyList];
    const positions = {};
    regions.forEach((selector) => {
        const el = pane.querySelector(selector);
        if (el) {
            positions[selector] = el.scrollTop;
        }
    });
    await reloadPane(pane);
    regions.forEach((selector) => {
        const el = pane.querySelector(selector);
        if (el && positions[selector]) {
            el.scrollTop = positions[selector];
        }
    });
};

/**
 * Recompute the first/last disabled state of every row's move up/down menu items
 * after an in-place reorder.
 *
 * @param {HTMLElement} list The competency items container.
 */
const refreshMoveState = (list) => {
    const rows = [...list.querySelectorAll('[data-competency]')];
    rows.forEach((li, index) => {
        const up = li.querySelector('[data-action="move-competency-up"]');
        const down = li.querySelector('[data-action="move-competency-down"]');
        if (up) {
            up.disabled = index === 0;
        }
        if (down) {
            down.disabled = index === rows.length - 1;
        }
    });
};

/**
 * Parse the competency-filter CSV from the pane dataset into ids.
 *
 * @param {HTMLElement} pane
 * @return {Number[]}
 */
const filterIds = (pane) => (pane.dataset.competencyids || '')
    .split(',')
    .map((id) => Number(id))
    .filter((id) => id > 0);

/**
 * Filter the template rows against the search box (name + idnumber haystack) and
 * toggle the no-results notice. Disabled templates only count as visible when the
 * show-disabled toggle has revealed them.
 *
 * @param {HTMLElement} region
 */
const applyPlanSearch = (region) => {
    const input = region.querySelector(SELECTORS.planSearch);
    const rows = region.querySelector(SELECTORS.templateRows);
    const query = input ? input.value.trim().toLowerCase() : '';
    const showdisabled = !!rows && rows.classList.contains('show-disabled');
    let visible = 0;
    region.querySelectorAll(SELECTORS.templateRow).forEach((row) => {
        const match = !query || (row.dataset.search || '').indexOf(query) !== -1;
        row.classList.toggle('local-dimensions-central-plan-filtered', !match);
        const disabled = row.classList.contains('local-dimensions-central-plan-hidden');
        if (match && (showdisabled || !disabled)) {
            visible++;
        }
    });
    const empty = region.querySelector(SELECTORS.searchEmpty);
    if (empty) {
        empty.hidden = visible !== 0;
    }
};

/**
 * Wire the "show disabled plans" toggle: the choice persists per session and is
 * applied as a class on the rows container (the disabled rows stay in the DOM).
 *
 * @param {HTMLElement} region
 */
const initShowDisabled = (region) => {
    const toggle = region.querySelector(SELECTORS.showDisabled);
    const rows = region.querySelector(SELECTORS.templateRows);
    if (!toggle || !rows) {
        return;
    }
    const show = Boolean(Preferences.getDisplay().plansshowdisabled);
    toggle.checked = show;
    rows.classList.toggle('show-disabled', show);
    toggle.addEventListener('change', () => {
        Preferences.saveDisplay({plansshowdisabled: toggle.checked});
        rows.classList.toggle('show-disabled', toggle.checked);
        applyPlanSearch(region);
    });
};

/**
 * Open a modal form (template or competency) and refresh the tab on success.
 *
 * @param {HTMLElement} pane
 * @param {String} formclass Fully-qualified dynamic-form class.
 * @param {Object} args
 * @param {String} titlekey
 * @param {String} titlecomponent
 */
const openForm = async(pane, formclass, args, titlekey, titlecomponent) => {
    const form = new ModalForm({
        formClass: formclass,
        args,
        modalConfig: {title: await getString(titlekey, titlecomponent)},
    });
    form.addEventListener(form.events.FORM_SUBMITTED, () => reloadKeepingScroll(pane).catch(notifyError));
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
    }])[0].then(() => reloadPane(pane)).catch(notifyError);

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
    reloadKeepingScroll(pane).catch(notifyError);
};

/**
 * Read the persisted display-toggle choice from the shared preferences store.
 *
 * @return {Object} Map of toggle key to boolean.
 */
const readDisplayPrefs = () => ({...Preferences.getDisplay().plansdetail});

/**
 * Persist the display-toggle choice via the shared preferences store.
 *
 * @param {Object} prefs Map of toggle key to boolean.
 */
const writeDisplayPrefs = (prefs) => {
    Preferences.saveDisplay({plansdetail: prefs});
};

/**
 * Apply the stored display prefs to the checkboxes and the competency list classes,
 * so the choice survives a pane reload (mirrors the Structure tab).
 *
 * @param {HTMLElement} region
 */
const applyDisplayPrefs = (region) => {
    const list = region.querySelector(SELECTORS.competencyItems);
    if (!list) {
        return;
    }
    const stored = readDisplayPrefs();
    region.querySelectorAll(SELECTORS.displayToggle).forEach((cb) => {
        const key = cb.dataset.displayToggle;
        const on = Object.prototype.hasOwnProperty.call(stored, key) ? Boolean(stored[key]) : cb.checked;
        cb.checked = on;
        list.classList.toggle(DISPLAY_CLASSES[key], on);
    });
};

/**
 * Restore a display-options panel open/collapsed state from the preferences store,
 * so the gear choice survives pane reloads and future visits.
 *
 * @param {HTMLElement} region
 * @param {String} panelselector Selector of the collapsible panel.
 * @param {String} gearselector Selector of the gear button controlling it.
 * @param {String} prefkey Key inside the display "panels" section.
 */
const applyPanelState = (region, panelselector, gearselector, prefkey) => {
    const panel = region.querySelector(panelselector);
    const gear = region.querySelector(gearselector);
    if (!panel || !gear) {
        return;
    }
    const open = Boolean(Preferences.getDisplay().panels[prefkey]);
    panel.hidden = !open;
    gear.setAttribute('aria-expanded', open ? 'true' : 'false');
};

/**
 * Wire the display-option switches: each change persists the choice and reapplies
 * the show-* classes on the competency list.
 *
 * @param {HTMLElement} region
 */
const initDisplayOptions = (region) => {
    region.querySelectorAll(SELECTORS.displayToggle).forEach((cb) => {
        cb.addEventListener('change', () => {
            const prefs = readDisplayPrefs();
            prefs[cb.dataset.displayToggle] = cb.checked;
            writeDisplayPrefs(prefs);
            applyDisplayPrefs(region);
        });
    });
    applyDisplayPrefs(region);
    applyPanelState(region, SELECTORS.displayPanel, SELECTORS.displayGear, 'plansdetail');
};

/**
 * Read the persisted plan-list display-toggle choice from the shared preferences store.
 *
 * @return {Object} Map of toggle key to boolean.
 */
const readListDisplayPrefs = () => ({...Preferences.getDisplay().planslist});

/**
 * Persist the plan-list display-toggle choice via the shared preferences store.
 *
 * @param {Object} prefs Map of toggle key to boolean.
 */
const writeListDisplayPrefs = (prefs) => {
    Preferences.saveDisplay({planslist: prefs});
};

/**
 * Apply the stored plan-list display prefs to the checkboxes and the rows container
 * classes (show identifiers / show due date), so the choice survives a pane reload.
 *
 * @param {HTMLElement} region
 */
const applyListDisplayPrefs = (region) => {
    const rows = region.querySelector(SELECTORS.templateRows);
    if (!rows) {
        return;
    }
    const stored = readListDisplayPrefs();
    region.querySelectorAll(SELECTORS.listDisplayToggle).forEach((cb) => {
        const key = cb.dataset.listToggle;
        const on = Object.prototype.hasOwnProperty.call(stored, key) ? Boolean(stored[key]) : cb.checked;
        cb.checked = on;
        rows.classList.toggle(LISTDISPLAY_CLASSES[key], on);
    });
};

/**
 * Wire the plan-list display-option switches: each change persists the choice and
 * reapplies the show-* classes on the rows container.
 *
 * @param {HTMLElement} region
 */
const initListDisplayOptions = (region) => {
    region.querySelectorAll(SELECTORS.listDisplayToggle).forEach((cb) => {
        cb.addEventListener('change', () => {
            const prefs = readListDisplayPrefs();
            prefs[cb.dataset.listToggle] = cb.checked;
            writeListDisplayPrefs(prefs);
            applyListDisplayPrefs(region);
        });
    });
    applyListDisplayPrefs(region);
    applyPanelState(region, SELECTORS.listDisplayPanel, SELECTORS.listDisplayGear, 'planslist');
};

/**
 * Drag-and-drop reordering of the plan's competencies. The drag starts from the grip
 * handle that appears on row hover; while dragging the row is live-repositioned at the
 * pointer's midpoint, and on release a single reorder web-service call persists the
 * final position (the kebab move up/down stays as the keyboard-accessible path).
 *
 * @param {HTMLElement} region
 * @param {HTMLElement} pane
 */
const initDragReorder = (region, pane) => {
    const list = region.querySelector(SELECTORS.competencyItems);
    if (!list || !pane) {
        return;
    }
    let dragged = null;
    let startorder = [];

    // The row is only draggable while the pointer holds its grip, so text selection
    // and accidental row drags stay untouched.
    list.querySelectorAll(SELECTORS.dragHandle).forEach((handle) => {
        const row = handle.closest('[data-competency]');
        if (!row) {
            return;
        }
        handle.addEventListener('mousedown', () => row.setAttribute('draggable', 'true'));
        handle.addEventListener('mouseup', () => row.removeAttribute('draggable'));
        handle.addEventListener('mouseleave', () => {
            if (!dragged) {
                row.removeAttribute('draggable');
            }
        });
    });

    list.addEventListener('dragstart', (event) => {
        dragged = event.target.closest('[data-competency]');
        if (!dragged) {
            return;
        }
        startorder = [...list.querySelectorAll('[data-competency]')].map((li) => li.dataset.competency);
        dragged.classList.add('local-dimensions-central-plan-dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', dragged.dataset.competency);
    });

    list.addEventListener('dragover', (event) => {
        if (!dragged) {
            return;
        }
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        const over = event.target.closest('[data-competency]');
        if (!over || over === dragged) {
            return;
        }
        const rect = over.getBoundingClientRect();
        if (event.clientY - rect.top > rect.height / 2) {
            over.after(dragged);
        } else {
            over.before(dragged);
        }
    });

    list.addEventListener('drop', (event) => event.preventDefault());

    list.addEventListener('dragend', () => {
        if (!dragged) {
            return;
        }
        const row = dragged;
        dragged = null;
        row.classList.remove('local-dimensions-central-plan-dragging');
        row.removeAttribute('draggable');
        const neworder = [...list.querySelectorAll('[data-competency]')].map((li) => li.dataset.competency);
        const id = row.dataset.competency;
        const from = startorder.indexOf(id);
        const to = neworder.indexOf(id);
        if (from === to || from === -1 || to === -1) {
            return;
        }
        // Core's reorder puts "from" right AFTER "to" when moving down and right BEFORE
        // it when moving up — so the reference row is the new previous/next sibling.
        const reference = to > from ? row.previousElementSibling : row.nextElementSibling;
        if (!reference || !reference.dataset.competency) {
            return;
        }
        Ajax.call([{
            methodname: 'core_competency_reorder_template_competency',
            args: {
                templateid: Number(pane.dataset.templateid),
                competencyidfrom: Number(id),
                competencyidto: Number(reference.dataset.competency),
            },
        }])[0].then(() => {
            // The DOM already sits in the final order; confirm in place, keep the scroll.
            refreshMoveState(list);
            flashRow(row);
            return null;
        }).catch((error) => {
            notifyError(error);
            // Restoring the server's order from a failure handler is intentional.
            // eslint-disable-next-line promise/no-nesting
            reloadKeepingScroll(pane).catch(() => null);
        });
    });
};

/**
 * Open the "move to position" modal for a competency row: a numbered select of every
 * position (annotated with the competency currently there). Saving issues one reorder
 * web-service call and repositions the row in place — the practical path for long
 * lists, and the keyboard-accessible one (drag-and-drop is pointer-only).
 *
 * @param {HTMLElement} pane
 * @param {HTMLElement} region
 * @param {HTMLElement} target The clicked handle or menu item, inside the row.
 * @return {Promise<void>}
 */
const moveCompetencyTo = async(pane, region, target) => {
    const list = region.querySelector(SELECTORS.competencyItems);
    const row = target.closest('[data-competency]');
    if (!list || !row) {
        return;
    }
    const rows = [...list.querySelectorAll('[data-competency]')];
    if (rows.length < 2) {
        return;
    }
    const current = rows.indexOf(row);
    const options = rows.map((li, index) => {
        const name = li.querySelector(SELECTORS.compName);
        return {
            value: index,
            label: (index + 1) + '. ' + (name ? name.textContent.trim() : ''),
            selected: index === current,
        };
    });
    const {html} = await Templates.renderForPromise('local_dimensions/central/move_competency_modal', {options: options});
    const modal = await ModalSaveCancel.create({
        title: getString('central_plans_moveto', 'local_dimensions'),
        body: html,
        show: true,
        removeOnClose: true,
    });
    modal.getRoot().on(ModalEvents.save, () => {
        const select = modal.getRoot()[0].querySelector('#local-dimensions-plans-move-position');
        const targetindex = select ? Number(select.value) : current;
        if (targetindex === current || !rows[targetindex]) {
            return;
        }
        const reference = rows[targetindex];
        Ajax.call([{
            methodname: 'core_competency_reorder_template_competency',
            args: {
                templateid: Number(pane.dataset.templateid),
                competencyidfrom: Number(row.dataset.competency),
                competencyidto: Number(reference.dataset.competency),
            },
        }])[0].then(() => {
            // Core lands the row after the occupant when moving down, before it when
            // moving up — mirror that in place so the list matches without a reload.
            if (targetindex > current) {
                reference.after(row);
            } else {
                reference.before(row);
            }
            refreshMoveState(list);
            flashRow(row);
            return null;
        }).catch((error) => {
            notifyError(error);
            // Restoring the server's order from a failure handler is intentional.
            // eslint-disable-next-line promise/no-nesting
            reloadKeepingScroll(pane).catch(() => null);
        });
    });
};

/**
 * Duplicate a template and select the copy once the tab refreshes.
 *
 * @param {HTMLElement} pane
 * @param {String|Number} id
 * @return {Promise<void>}
 */
const duplicateTemplate = async(pane, id) => {
    // Plugin wrapper around core_competency_duplicate_template: also copies
    // the lp-area custom fields, their embedded files and the card images.
    const newtemplate = await Ajax.call([{
        methodname: 'local_dimensions_duplicate_template',
        args: {templateid: Number(id)},
    }])[0];
    if (newtemplate && newtemplate.id) {
        pane.dataset.templateid = newtemplate.id;
    }
    await reloadPane(pane);
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
    // In-place move: no pane reload, so long lists keep their scroll position.
    if (direction === 'up') {
        sibling.before(li);
    } else {
        sibling.after(li);
    }
    refreshMoveState(li.closest(SELECTORS.competencyItems));
    flashRow(li);
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
        Preferences.saveNav({templateid: Number(target.dataset.id) || 0});
        // Keep the plan-list scroll; the detail shows new content so its scroll resets.
        reloadKeepingScroll(pane, [SELECTORS.templateRows]).catch(notifyError);
    },
    'clear-competency': (pane) => {
        pane.dataset.competencyids = '';
        reloadPane(pane).catch(notifyError);
    },
    'remove-filter-competency': (pane, region, target) => {
        const removed = Number(target.dataset.id);
        pane.dataset.competencyids = filterIds(pane).filter((id) => id !== removed).join(',');
        reloadPane(pane).catch(notifyError);
    },
    'add-filter-competency': (pane, region) => {
        const picker = region.querySelector(SELECTORS.filterPicker);
        const button = region.querySelector(SELECTORS.filterAddButton);
        if (!picker) {
            return;
        }
        picker.hidden = !picker.hidden;
        if (button) {
            button.setAttribute('aria-expanded', picker.hidden ? 'false' : 'true');
        }
        if (!picker.hidden) {
            const input = picker.querySelector('input');
            if (input) {
                input.focus();
            }
        }
    },
    'duplicate-template': (pane, region, target) =>
        duplicateTemplate(pane, target.dataset.id).catch(notifyError),
    'display-options': (pane, region, target) => {
        const panel = region.querySelector(SELECTORS.displayPanel);
        if (!panel) {
            return;
        }
        panel.hidden = !panel.hidden;
        target.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
        Preferences.saveDisplay({panels: {plansdetail: !panel.hidden}});
    },
    'list-display-options': (pane, region, target) => {
        const panel = region.querySelector(SELECTORS.listDisplayPanel);
        if (!panel) {
            return;
        }
        panel.hidden = !panel.hidden;
        target.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
        Preferences.saveDisplay({panels: {planslist: !panel.hidden}});
    },
    'browse-frameworks': (pane, region) => showCompetencyBrowser(pane, region).catch(notifyError),
    'manage-participants': (pane, region) => showParticipants(pane, region).catch(notifyError),
    'new-template': (pane, region) => openForm(
        pane,
        FORM_CLASS,
        {id: 0, contextid: region.dataset.contextid || 0},
        'managetemplates_addtemplate',
        'local_dimensions'
    ),
    'edit-template': (pane, region, target) => openForm(
        pane,
        FORM_CLASS,
        {id: target.dataset.id},
        'edittemplate',
        'tool_lp'
    ),
    'edit-competency': (pane, region, target) => openForm(
        pane,
        COMPETENCY_FORM_CLASS,
        {id: target.dataset.id, competencyframeworkid: target.dataset.frameworkid},
        'editcompetency',
        'tool_lp'
    ),
    'delete-template': (pane, region, target) =>
        deleteTemplate(pane, target.dataset.id, target.dataset.name || '', target.dataset.plancount || 0)
            .catch(notifyError),
    'remove-competency': (pane, region, target) =>
        removeCompetency(pane, target.dataset.id, target.dataset.name || '').catch(notifyError),
    'move-competency-up': (pane, region, target) => moveCompetency(pane, target, 'up').catch(notifyError),
    'move-competency-down': (pane, region, target) => moveCompetency(pane, target, 'down').catch(notifyError),
    'move-competency-to': (pane, region, target) => moveCompetencyTo(pane, region, target).catch(notifyError),
    'open-competency-detail': (pane, region, target) =>
        openCompetencyDetailModal(Number(target.dataset.id)).catch(notifyError),
};

/**
 * Route a [data-action] click (from the tab region or the shared sticky footer) to its
 * ACTION_HANDLERS entry. The footer sits outside the tab region, so it dispatches through
 * the pane/region captured at init rather than relying on the click's DOM position.
 *
 * @param {HTMLElement} target The clicked [data-action] element.
 * @return {void}
 */
const dispatchPlansAction = (target) => {
    // Ignore footer clicks once this tab is no longer active (guards a footer lingering
    // during a slow tab switch); in-region clicks always satisfy this.
    if (!activePane || !activeRegion || !activeRegion.closest('.tab-pane.active')) {
        return;
    }
    const handler = ACTION_HANDLERS[target.dataset.action];
    if (handler) {
        handler(activePane, activeRegion, target);
    }
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
    activeRegion = region;
    activePane = pane;

    // Feed the selected template's actions into the shared page-level sticky footer, but
    // only when this tab is actually active — dynamic tabs re-run init from an async load,
    // so a late/out-of-order load for a tab the user already left must not drive the
    // footer. The holder is removed after copying so its buttons are not duplicated in the
    // DOM (a hidden duplicate earlier in document order would shadow name-based clicks).
    // Re-runs on every tab entry and reloadPane, so it tracks the selected template.
    if (region.closest('.tab-pane.active')) {
        const footerholder = region.querySelector('[data-region="plans-footer-actions"]');
        if (footerholder) {
            ActionFooter.show(footerholder.innerHTML, dispatchPlansAction);
            footerholder.remove();
        } else {
            ActionFooter.hide();
        }
    }

    // Activate the collapsible container around the selected template's description
    // (re-runs after each tab refresh, so a freshly rendered description is measured).
    CollapsibleDescription.refresh(pane);

    // The server auto-selects a template (selectedtemplateid); mirror it onto the pane dataset so
    // getContent args and the add/remove/reorder web services target the rendered template even
    // before the user clicks one (otherwise templateid is absent and the WS gets 0 -> invalid context).
    if (pane && region.dataset.templateid) {
        pane.dataset.templateid = region.dataset.templateid;
    }

    // Mirror the server-validated filter back too, so unreadable/deleted competencies the
    // server dropped do not linger in the pane dataset.
    if (pane && 'competencyids' in region.dataset) {
        pane.dataset.competencyids = region.dataset.competencyids;
    }

    const search = region.querySelector(SELECTORS.competencySearch);
    if (search && pane && !search.dataset.enhanced) {
        search.dataset.enhanced = '1';
        search.addEventListener('change', () => {
            const added = Number(search.value);
            if (!added) {
                return;
            }
            const ids = filterIds(pane);
            if (!ids.includes(added)) {
                ids.push(added);
            }
            pane.dataset.competencyids = ids.join(',');
            reloadPane(pane).catch(notifyError);
        });
        getString('central_searchcompetency', 'local_dimensions')
            .then((placeholder) => enhance(SELECTORS.competencySearch, false, DATASOURCE, placeholder, false, true, '', true))
            .catch(notifyError);
    }

    const searchinput = region.querySelector(SELECTORS.planSearch);
    if (searchinput) {
        searchinput.addEventListener('input', () => applyPlanSearch(region));
    }

    initShowDisabled(region);
    initDisplayOptions(region);
    initListDisplayOptions(region);
    initDragReorder(region, pane);
    // The redesign gives the master (templates) an explicit, adjustable width (default 400px)
    // and lets the detail flex to fill the rest; the divider drives that master width.
    initMasterResizer({
        body: region.querySelector(SELECTORS.plansBody),
        resizer: region.querySelector(SELECTORS.plansResizer),
        master: region.querySelector(SELECTORS.templateList),
        cssvar: '--local-dimensions-plans-master-width',
        storagekey: 'local_dimensions_plans_master_width',
        minimum: 300,
        maximum: 1200,
        reserve: 382,
    });

    region.addEventListener('click', (event) => {
        const target = event.target.closest('[data-action]');
        if (target) {
            dispatchPlansAction(target);
        }
    });
};
