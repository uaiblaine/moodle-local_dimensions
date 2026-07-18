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
import {mount as mountEnrol} from 'local_dimensions/central/enrol_methods';
import {attach as attachExpander} from 'local_dimensions/central/modal_expander';

const SELECTORS = {
    tabs: '[data-region="participant-tabs"]',
    paneCohorts: '[data-region="pane-cohorts"]',
    paneUsers: '[data-region="pane-users"]',
    paneRoles: '[data-region="pane-roles"]',
    paneEnrol: '[data-region="pane-enrol"]',
};

// Each allowed admin page becomes a footer escape link that opens the matching core admin page in
// a new tab. The capability flag names the data attribute the server sets on the plans region (a
// "1" when the user can reach that page); only allowed links are rendered.
const ADMIN_PAGES = [
    {path: '/cohort/index.php', flag: 'cancohortpage',
        strkey: 'central_participants_openpage_cohorts'},
    {path: '/admin/user.php', flag: 'canuserpage',
        strkey: 'central_participants_openpage_users'},
    {path: '/admin/roles/manage.php', flag: 'canassignroles',
        strkey: 'central_participants_openpage_roles'},
    {path: '/admin/settings.php?section=manageenrols', flag: 'canenrolpage',
        strkey: 'central_participants_openpage_enrol'},
];

/**
 * Inject the "open the matching core admin page" links into the modal footer, left-aligned. Each
 * link is only added when the user can reach its page; giving the otherwise-empty footer a child
 * makes core reveal it (this is a management modal, so there is no primary action on the right).
 *
 * @param {HTMLElement} root The modal root.
 * @param {HTMLElement} region The plans region (carries the capability flags).
 * @return {Promise<void>}
 */
const injectFooterLinks = async(root, region) => {
    const footer = root.querySelector('.modal-footer');
    if (!footer) {
        return;
    }
    const allowed = ADMIN_PAGES.filter((page) => region.dataset[page.flag] === '1');
    if (!allowed.length) {
        return;
    }
    const labels = await getStrings(allowed.map((page) => ({key: page.strkey, component: 'local_dimensions'})));
    const group = document.createElement('div');
    group.className = 'local-dimensions-modal-footer-links';
    allowed.forEach((page, index) => {
        const link = document.createElement('a');
        link.href = M.cfg.wwwroot + page.path;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.className = 'btn btn-link p-0';
        const icon = document.createElement('i');
        icon.className = 'fa fa-external-link me-1';
        icon.setAttribute('aria-hidden', 'true');
        link.appendChild(icon);
        // The visible label is the accessible name; no separate title/aria-label needed.
        link.appendChild(document.createTextNode(labels[index]));
        group.appendChild(link);
    });
    footer.appendChild(group);
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
        canmanageenrol: region.dataset.canmanageenrol === '1',
    });
    const modal = await Modal.create({title, body: html});
    modal.setRemoveOnClose(true);

    const root = modal.getRoot()[0];
    // Widen this data-dense modal (tabs + grids) responsively. Bootstrap's own modal-xl carries
    // the sizing (identical on 4 and 5); core's modal API only exposes setLarge(), so add the
    // class directly. The local-dimensions-participants-modal class only hooks the height rule.
    const dialog = root.querySelector('.modal-dialog');
    if (dialog) {
        // The close-button chip comes via the :has(.modal-body .local-dimensions-*) rule (the body
        // carries .local-dimensions-participants), so no header-link class is needed here.
        dialog.classList.add('modal-xl', 'local-dimensions-participants-modal');
    }
    // Admin escape links live in the footer (D2); giving it a child makes core reveal the footer.
    await injectFooterLinks(root, region);
    // A header expand/restore control lets the user widen this dense modal; the choice persists.
    attachExpander(dialog).catch(notifyError);
    const opts = {
        templateid: Number(pane.dataset.templateid),
        contextid: Number(region.dataset.contextid),
    };

    const mounted = {cohorts: false, users: false, roles: false, enrol: false};
    // Claim the latch synchronously so a concurrent double-click cannot fire a second mount, and
    // release it if the mount rejects so the next tab activation retries. A released latch always
    // means an unwired pane: cohorts and roles clear and rewire fresh children on remount, and
    // users and enrol reject only before they wire (their sole post-wire failure resolves instead).
    const startMount = (key, mountfn, selector) => {
        if (mounted[key]) {
            return;
        }
        mounted[key] = true;
        mountfn(root.querySelector(selector), opts).catch((error) => {
            mounted[key] = false;
            notifyError(error);
        });
    };
    modal.getRoot().on(ModalEvents.shown, () => {
        // Host a toast region inside the modal body so the cohort/user managers' success toasts
        // render above the dialog, not behind it. Core removes it on close.
        addToastRegion(modal.getBody()[0]).catch(notifyError);
        startMount('cohorts', mountCohorts, SELECTORS.paneCohorts);

        const tablist = root.querySelector(SELECTORS.tabs);
        const tabs = () => Array.from(tablist.querySelectorAll('.nav-link'));

        // Lazy-mount a tab's pane the first time it is shown; a re-click retries a released latch.
        const ensureMounted = (button) => {
            const region = button.dataset.region;
            if (region === 'tab-cohorts') {
                startMount('cohorts', mountCohorts, SELECTORS.paneCohorts);
            } else if (region === 'tab-users') {
                startMount('users', mountUsers, SELECTORS.paneUsers);
            } else if (region === 'tab-roles') {
                startMount('roles', mountRoles, SELECTORS.paneRoles);
            } else if (region === 'tab-enrol') {
                startMount('enrol', mountEnrol, SELECTORS.paneEnrol);
            }
        };

        // Activate a tab + its panel, keep the roving tabindex on the selected tab, lazy-mount it,
        // and optionally move focus to it (keyboard activation).
        const selectTab = (button, setfocus) => {
            activateTab(root, button);
            tabs().forEach((tab) => tab.setAttribute('tabindex', tab === button ? '0' : '-1'));
            ensureMounted(button);
            if (setfocus) {
                button.focus();
            }
        };

        // The active tab is the tablist's single tab-stop (WAI-ARIA tabs roving tabindex).
        tabs().forEach((tab) => tab.setAttribute('tabindex', tab.classList.contains('active') ? '0' : '-1'));

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
