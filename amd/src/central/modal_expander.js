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
 * Expand / restore control for the Competency hub's data-dense modals (participants, links). Two
 * buttons are always present in the modal header; the CSS shows whichever matches the dialog's
 * expanded state, so nothing swaps an icon in JS. The chosen size lives in the shared display
 * preference, so it follows the user across the two modals and across sessions and devices.
 *
 * @module     local_dimensions/central/modal_expander
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getDisplay, saveDisplay} from 'local_dimensions/central/preferences';
import {getString} from 'core/str';

/**
 * Where the expanded state is read and written when a caller does not say.
 *
 * The hub's own store, so the hub's call sites need no argument. A caller from outside the
 * hub MUST pass its own: a learner expanding a modal would otherwise write the admin hub's
 * display preference.
 *
 * @type {Object}
 */
const HUB_STORE = {
    get: () => Boolean(getDisplay().modalexpanded),
    set: (expanded) => saveDisplay({modalexpanded: expanded}),
};

/**
 * Class the dialog carries while expanded; the CSS keys the widened width and the button swap off it.
 *
 * @type {String}
 */
const EXPANDED_CLASS = 'local-dimensions-modal-expanded';

/**
 * Build one header size-toggle button.
 *
 * @param {String} action The data-action value ('modal-expand' or 'modal-restore').
 * @param {String} iconclass The FontAwesome glyph class.
 * @param {String} label The accessible name, also used as the tooltip.
 * @param {String} statclass The class the CSS uses to show or hide this button per state.
 * @return {HTMLElement}
 */
const makeButton = (action, iconclass, label, statclass) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'local-dimensions-modal-sizetoggle ' + statclass;
    button.dataset.action = action;
    button.setAttribute('aria-label', label);
    button.title = label;
    const icon = document.createElement('i');
    icon.className = 'fa ' + iconclass;
    icon.setAttribute('aria-hidden', 'true');
    button.appendChild(icon);
    return button;
};

/**
 * Add the expand/restore control to a modal and bind it to the stored size preference. Call once,
 * after Modal.create and before show, with the dialog element. The expanded state is seeded
 * synchronously so the modal opens at the right size before the buttons finish loading.
 *
 * @param {HTMLElement} dialog The modal's .modal-dialog element.
 * @param {Object} store Optional {get, set} pair for the expanded state; defaults to the hub's.
 * @return {Promise<void>}
 */
export const attach = async(dialog, store) => {
    const state = store || HUB_STORE;
    const header = dialog && dialog.querySelector('.modal-header');
    if (!header) {
        return;
    }
    // Open at the size the user last chose.
    dialog.classList.toggle(EXPANDED_CLASS, Boolean(state.get()));

    const [expandlabel, restorelabel] = await Promise.all([
        getString('central_modal_expand', 'local_dimensions'),
        getString('central_modal_restore', 'local_dimensions'),
    ]);
    // Both buttons ship; the CSS reveals the one matching the state, so nothing swaps an icon here.
    const closebtn = header.querySelector('.btn-close');
    header.insertBefore(makeButton('modal-expand', 'fa-expand', expandlabel, 'local-dimensions-modal-expand'), closebtn);
    header.insertBefore(makeButton('modal-restore', 'fa-compress', restorelabel, 'local-dimensions-modal-restore'), closebtn);

    header.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action="modal-expand"], [data-action="modal-restore"]');
        if (!button) {
            return;
        }
        const wasfocused = document.activeElement === button;
        const expanded = button.dataset.action === 'modal-expand';
        dialog.classList.toggle(EXPANDED_CLASS, expanded);
        state.set(expanded);
        if (wasfocused) {
            // The activated button hides itself in the CSS swap, which would drop keyboard focus to
            // the body; move focus to the now-visible counterpart so the control keeps its place.
            const shown = expanded ? 'local-dimensions-modal-restore' : 'local-dimensions-modal-expand';
            const counterpart = header.querySelector('.' + shown);
            if (counterpart) {
                counterpart.focus();
            }
        }
    });
};
