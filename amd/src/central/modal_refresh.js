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
 * Refresh control for the Competency hub's data-dense modals (participants, links). Injects one
 * header button that reloads the active content through a caller-supplied callback, and owns the
 * busy state (disable + spinning icon while the reload runs, cleared in a finally so a failed
 * reload never leaves the button stuck). Shares the close-button chip's look with the size toggle.
 *
 * @module     local_dimensions/central/modal_refresh
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getString} from 'core/str';
import {notifyError} from 'local_dimensions/central/errors';

/**
 * Build the header refresh button.
 *
 * @param {String} label The accessible name, also used as the tooltip.
 * @return {HTMLElement}
 */
const makeButton = (label) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'local-dimensions-modal-refresh';
    button.dataset.action = 'modal-refresh';
    button.setAttribute('aria-label', label);
    button.title = label;
    const icon = document.createElement('i');
    icon.className = 'fa fa-rotate';
    icon.setAttribute('aria-hidden', 'true');
    button.appendChild(icon);
    return button;
};

/**
 * Add the refresh control to a modal header and wire it to a reload callback. Call after the
 * expander so the button slots to the left of the size toggles (order: refresh, expand, close).
 *
 * @param {HTMLElement} dialog The modal's .modal-dialog element.
 * @param {Function} onrefresh Returns a Promise; the button stays busy until it settles.
 * @return {Promise<void>}
 */
export const attach = async(dialog, onrefresh) => {
    const header = dialog && dialog.querySelector('.modal-header');
    if (!header) {
        return;
    }
    const label = await getString('refresh');
    const button = makeButton(label);
    const icon = button.querySelector('i');
    // Sit to the left of the size toggles when they are present, else just left of the close chip.
    const anchor = header.querySelector('.local-dimensions-modal-sizetoggle') || header.querySelector('.btn-close');
    header.insertBefore(button, anchor);

    button.addEventListener('click', async() => {
        if (button.disabled) {
            return;
        }
        button.disabled = true;
        icon.classList.add('fa-spin');
        try {
            await onrefresh();
        } catch (error) {
            notifyError(error);
        } finally {
            button.disabled = false;
            icon.classList.remove('fa-spin');
        }
    });
};
