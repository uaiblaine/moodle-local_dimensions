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
 * Shared owner of the page-level sticky footer for the Competency hub.
 *
 * The hub is one page with dynamic tabs, and core/sticky-footer drives a single
 * footer per page, so the Frameworks, Structure and Plans tabs all drive this one
 * surface through here. The active tab calls show() with its rendered button
 * markup and a dispatch callback; a single delegated click listener routes footer
 * clicks to whichever dispatch is current. Switching tabs clears the footer (the
 * entering tab's own init re-asserts it, since dynamic tabs re-run init on every
 * entry).
 *
 * Several hub modals open only from this footer, so a tab that drops its footer
 * loses those actions. See docs/design-kit/sticky-footer.html.
 *
 * The footer's inner HTML is replaced wholesale (the theme-agnostic approach core
 * bulkactions uses); callers supply the sticky-footer inner layout in their markup.
 *
 * @module     local_dimensions/central/action_footer
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {enableStickyFooter, disableStickyFooter} from 'core/sticky-footer';

/** @type {String} Id of the page-level sticky footer element. */
const FOOTER_ID = 'sticky-footer';

/** @type {String} Selector for the dynamic-tabs nav toggles. */
const TAB_TOGGLE = '.dynamictabs .nav-link';

/** @type {Function|null} Dispatch for the currently shown tab's footer. */
let currentDispatch = null;

/** @type {Boolean} Guard so init() binds its listeners only once. */
let initialised = false;

/**
 * The page-level sticky footer element, or null if the page rendered none.
 *
 * @return {HTMLElement|null} The footer element.
 */
const getFooter = () => document.getElementById(FOOTER_ID);

/**
 * Fill the footer with the given markup and reveal it.
 *
 * @param {String} html Rendered, trusted button markup (with the sticky-footer inner layout).
 * @param {Function} dispatch Called with (target, event) for a footer [data-action] click.
 * @return {void}
 */
export const show = (html, dispatch) => {
    const footer = getFooter();
    if (!footer) {
        return;
    }
    footer.innerHTML = html;
    currentDispatch = dispatch;
    enableStickyFooter();
    // A hide() that runs before the theme registers its sticky-footer manager (the Frameworks
    // tab's init on page load) takes core's fallback path, which adds `v-hidden`. The theme
    // manager's enable never removes it, so without this the bar slides up invisible.
    footer.classList.remove('v-hidden');
};

/**
 * Clear the footer and hide it.
 *
 * @return {void}
 */
export const hide = () => {
    const footer = getFooter();
    currentDispatch = null;
    disableStickyFooter();
    if (footer) {
        footer.innerHTML = '';
    }
};

/**
 * Bind the page-level listeners once. Safe to call repeatedly.
 *
 * @return {void}
 */
export const init = () => {
    if (initialised) {
        return;
    }
    initialised = true;
    const footer = getFooter();
    if (!footer) {
        return;
    }
    footer.addEventListener('click', (event) => {
        const target = event.target.closest('[data-action]');
        if (target && currentDispatch) {
            currentDispatch(target, event);
        }
    });
    // Switching to a different tab clears the footer; the entering tab's own init
    // re-asserts it. A native click is used rather than show.bs.tab because Moodle 4.5
    // runs Bootstrap 4, which dispatches tab events through jQuery, so native
    // 'show.bs.tab' listeners never fire there. This direct listener runs before
    // Bootstrap's delegated one, so the active-tab guard reads the pre-click state and
    // re-clicking the current tab keeps its footer.
    document.querySelectorAll(TAB_TOGGLE).forEach((toggle) => {
        toggle.addEventListener('click', () => {
            if (!toggle.classList.contains('active')) {
                hide();
            }
        });
    });
};
