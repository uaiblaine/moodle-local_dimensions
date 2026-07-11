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
import {notifyError} from 'local_dimensions/central/errors';
import Templates from 'core/templates';
import {getString, getStrings} from 'core/str';
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

// Each tab can offer a header shortcut that opens the matching core admin page in a new tab.
// The capability flag names the data attribute the server sets on the plans region (a "1" when
// the user can reach that page); only allowed links are rendered.
const HEADER_PAGES = [
    {pane: 'pane-cohorts', path: '/cohort/index.php', flag: 'cancohortpage',
        strkey: 'central_participants_openpage_cohorts'},
    {pane: 'pane-users', path: '/admin/user.php', flag: 'canuserpage',
        strkey: 'central_participants_openpage_users'},
    {pane: 'pane-roles', path: '/admin/roles/manage.php', flag: 'canassignroles',
        strkey: 'central_participants_openpage_roles'},
];

/**
 * Inject the per-tab "open the matching core admin page" links into the modal header, just to
 * the left of the close button. Each link is only added when the user can reach its page. They
 * start hidden; the tab switcher reveals the one matching the active tab.
 *
 * @param {HTMLElement} root The modal root.
 * @param {HTMLElement} region The plans region (carries the capability flags).
 * @return {Promise<Object>} Map of pane region name -> the injected link element.
 */
const injectHeaderLinks = async(root, region) => {
    const header = root.querySelector('.modal-header');
    if (!header) {
        return {};
    }
    const allowed = HEADER_PAGES.filter((page) => region.dataset[page.flag] === '1');
    if (!allowed.length) {
        return {};
    }
    const closebtn = header.querySelector('.btn-close');
    const labels = await getStrings(allowed.map((page) => ({key: page.strkey, component: 'local_dimensions'})));
    const links = {};
    allowed.forEach((page, index) => {
        const link = document.createElement('a');
        link.href = M.cfg.wwwroot + page.path;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.className = 'btn btn-outline-secondary btn-sm local-dimensions-participants-headerlink d-none';
        link.title = labels[index];
        link.setAttribute('aria-label', labels[index]);
        const icon = document.createElement('i');
        icon.className = 'fa fa-arrow-up-right-from-square';
        icon.setAttribute('aria-hidden', 'true');
        link.appendChild(icon);
        header.insertBefore(link, closebtn);
        links[page.pane] = link;
    });
    return links;
};

/**
 * Reveal the header link matching the active tab's pane and hide the rest.
 *
 * @param {Object} links Map of pane region name -> link element (from injectHeaderLinks).
 * @param {String} paneregion The active tab's target pane (e.g. 'pane-users').
 * @return {void}
 */
const showHeaderLinkFor = (links, paneregion) => {
    Object.keys(links).forEach((pane) => {
        links[pane].classList.toggle('d-none', pane !== paneregion);
    });
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
    const headerlinks = await injectHeaderLinks(root, region);
    const opts = {
        templateid: Number(pane.dataset.templateid),
        contextid: Number(region.dataset.contextid),
    };

    let usersmounted = false;
    let rolesmounted = false;
    modal.getRoot().on(ModalEvents.shown, () => {
        // Host a toast region inside the modal body so the cohort/user managers' success toasts
        // render above the dialog, not behind it. Core removes it on close.
        addToastRegion(modal.getBody()[0]).catch(notifyError);
        mountCohorts(root.querySelector(SELECTORS.paneCohorts), opts).catch(notifyError);

        const tablist = root.querySelector(SELECTORS.tabs);
        const tabs = () => Array.from(tablist.querySelectorAll('.nav-link'));

        // Lazy-mount a tab's pane the first time it is shown.
        const ensureMounted = (button) => {
            if (button.dataset.region === 'tab-users' && !usersmounted) {
                usersmounted = true;
                mountUsers(root.querySelector(SELECTORS.paneUsers), opts).catch(notifyError);
            }
            if (button.dataset.region === 'tab-roles' && !rolesmounted) {
                rolesmounted = true;
                mountRoles(root.querySelector(SELECTORS.paneRoles), opts).catch(notifyError);
            }
        };

        // Activate a tab + its panel, keep the roving tabindex on the selected tab, lazy-mount it,
        // and optionally move focus to it (keyboard activation).
        const selectTab = (button, setfocus) => {
            activateTab(root, button);
            showHeaderLinkFor(headerlinks, button.dataset.targetPane);
            tabs().forEach((tab) => tab.setAttribute('tabindex', tab === button ? '0' : '-1'));
            ensureMounted(button);
            if (setfocus) {
                button.focus();
            }
        };

        // The active tab is the tablist's single tab-stop (WAI-ARIA tabs roving tabindex).
        tabs().forEach((tab) => tab.setAttribute('tabindex', tab.classList.contains('active') ? '0' : '-1'));
        // Reveal the header link that belongs to the initially-active (Cohorts) tab.
        const activetab = tablist.querySelector('.nav-link.active');
        if (activetab) {
            showHeaderLinkFor(headerlinks, activetab.dataset.targetPane);
        }

        tablist.addEventListener('click', (event) => {
            const button = event.target.closest('.nav-link');
            if (button) {
                event.preventDefault();
                selectTab(button, false);
            }
        });

        tablist.addEventListener('keydown', (event) => {
            const current = event.target.closest('.nav-link');
            if (!current) {
                return;
            }
            const all = tabs();
            const index = all.indexOf(current);
            let next = null;
            if (event.key === 'ArrowRight') {
                next = all[(index + 1) % all.length];
            } else if (event.key === 'ArrowLeft') {
                next = all[(index - 1 + all.length) % all.length];
            } else if (event.key === 'Home') {
                next = all[0];
            } else if (event.key === 'End') {
                next = all[all.length - 1];
            }
            if (next) {
                event.preventDefault();
                selectTab(next, true);
            }
        });
    });
    modal.show();
};
