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
 * "Manage participants" modal: a tabbed (Cohorts / Users) surface for a learning plan template.
 *
 * @module     local_dimensions/central/participants_manager
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal';
import ModalEvents from 'core/modal_events';
import Notification from 'core/notification';
import Templates from 'core/templates';
import {getString} from 'core/str';
import {addToastRegion} from 'core/toast';
import {mount as mountCohorts} from 'local_dimensions/central/cohort_manager';
import {mount as mountUsers} from 'local_dimensions/central/participants_users';
import {mount as mountRoles} from 'local_dimensions/central/roles_manager';

const SELECTORS = {
    tabs: '[data-region="participant-tabs"]',
    paneCohorts: '[data-region="pane-cohorts"]',
    paneUsers: '[data-region="pane-users"]',
    paneRoles: '[data-region="pane-roles"]',
};

/**
 * Activate the clicked tab + its pane (self-contained, no Bootstrap tab JS dependency in the modal).
 *
 * @param {HTMLElement} root The modal root.
 * @param {HTMLElement} button The clicked nav button.
 * @return {void}
 */
const activateTab = (root, button) => {
    const target = button.dataset.targetPane;
    root.querySelectorAll(`${SELECTORS.tabs} .nav-link`).forEach((other) => {
        other.classList.toggle('active', other === button);
        other.setAttribute('aria-selected', other === button ? 'true' : 'false');
    });
    root.querySelectorAll('.tab-pane').forEach((pane) => {
        const on = pane.dataset.region === target;
        pane.classList.toggle('show', on);
        pane.classList.toggle('active', on);
    });
};

/**
 * Open the Manage participants modal for the plans pane's selected template.
 *
 * @param {HTMLElement} pane The tab pane.
 * @param {HTMLElement} region The plans region (carries contextid + selected template name).
 * @return {Promise<void>}
 */
export const show = async(pane, region) => {
    const title = await getString('central_participants_title', 'local_dimensions');
    const {html} = await Templates.renderForPromise('local_dimensions/central/participants_manager', {
        templatename: region.dataset.templatename || '',
        contextid: Number(region.dataset.contextid),
        canassignroles: region.dataset.canassignroles === '1',
    });
    const modal = await Modal.create({title, body: html});
    modal.setRemoveOnClose(true);

    const root = modal.getRoot()[0];
    // Widen this data-dense modal (tabs + grids) responsively, scoped to our dialog only.
    const dialog = root.querySelector('.modal-dialog');
    if (dialog) {
        dialog.classList.add('local-dimensions-participants-modal');
    }
    const opts = {
        templateid: Number(pane.dataset.templateid),
        contextid: Number(region.dataset.contextid),
    };

    let usersmounted = false;
    let rolesmounted = false;
    modal.getRoot().on(ModalEvents.shown, () => {
        // Host a toast region inside the modal body so the cohort/user managers' success toasts
        // render above the dialog, not behind it. Core removes it on close.
        addToastRegion(modal.getBody()[0]).catch(Notification.exception);
        mountCohorts(root.querySelector(SELECTORS.paneCohorts), opts).catch(Notification.exception);
        root.querySelector(SELECTORS.tabs).addEventListener('click', (event) => {
            const button = event.target.closest('.nav-link');
            if (!button) {
                return;
            }
            event.preventDefault();
            activateTab(root, button);
            if (button.dataset.region === 'tab-users' && !usersmounted) {
                usersmounted = true;
                mountUsers(root.querySelector(SELECTORS.paneUsers), opts).catch(Notification.exception);
            }
            if (button.dataset.region === 'tab-roles' && !rolesmounted) {
                rolesmounted = true;
                mountRoles(root.querySelector(SELECTORS.paneRoles), opts).catch(Notification.exception);
            }
        });
    });
    modal.show();
};
