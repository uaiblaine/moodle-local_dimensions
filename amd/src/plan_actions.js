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
 * The plan overview's review actions: request a review of the plan, or cancel the request.
 *
 * Both are core's own web services, as on tool_lp's plan page (admin/tool/lp/amd/src/planactions.js),
 * which asks no confirmation for either; the server drew only the action core allows this viewer
 * (local_dimensions\local\plan_access::review_actions()), and core checks it again. On success the
 * page reloads, since the status it shows is drawn by the server; on failure core's exception shows
 * and the button can be pressed again.
 *
 * @module     local_dimensions/plan_actions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import Pending from 'core/pending';

/** The row's actions, each the core service it calls. */
const ACTIONS = {
    'local-dimensions-plan-request-review': 'core_competency_plan_request_review',
    'local-dimensions-plan-cancel-review-request': 'core_competency_plan_cancel_review_request',
};

/** Whether a request is on its way: one at a time, whichever button sent it. */
let busy = false;

/**
 * Send one review action for the plan.
 *
 * The button keeps its place and shows a spinner while busy (fleet Design System, action-button.md).
 * The pending token is left open on success: the reload replaces the page, and a test waiting on it
 * must not read the old page in between.
 *
 * @param {HTMLElement} button The pressed button.
 * @param {string} methodname The core web service.
 * @param {number} planid The plan id.
 * @return {void}
 */
const press = (button, methodname, planid) => {
    if (busy) {
        return;
    }
    busy = true;
    const pending = new Pending('local_dimensions/plan_actions:' + methodname);
    button.setAttribute('aria-disabled', 'true');
    const spinner = document.createElement('span');
    spinner.className = 'spinner-border spinner-border-sm ms-2';
    spinner.setAttribute('aria-hidden', 'true');
    button.appendChild(spinner);

    Ajax.call([{methodname: methodname, args: {id: planid}}])[0].then(() => {
        window.location.reload();
        return null;
    }).catch((error) => {
        busy = false;
        spinner.remove();
        button.removeAttribute('aria-disabled');
        Notification.exception(error);
        pending.resolve();
    });
};

/**
 * Arm the actions row: each review button gets its handler, then is shown.
 *
 * @return {void}
 */
export const init = () => {
    const row = document.querySelector('[data-region="local-dimensions-plan-actions"]');
    const planid = row ? Number.parseInt(row.dataset.planid, 10) : NaN;
    if (!row || !(planid > 0)) {
        return;
    }
    Object.keys(ACTIONS).forEach((action) => {
        const button = row.querySelector('[data-action="' + action + '"]');
        if (!button) {
            return;
        }
        button.addEventListener('click', () => press(button, ACTIONS[action], planid));
        button.hidden = false;
    });
};
