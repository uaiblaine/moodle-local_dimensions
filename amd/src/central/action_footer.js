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
 * The hub is one page with dynamic tabs, and Moodle allows a single sticky
 * footer per page, so all three tabs — Frameworks, Structure and Plans — drive
 * this one surface through here. The active tab calls show() with its rendered
 * button markup and a dispatch callback; a single delegated click listener routes
 * footer clicks to whichever dispatch is current. Switching tabs clears the footer
 * (the entering tab's own init re-asserts it, since dynamic tabs re-run init on
 * every entry).
 *
 * This surface is a launcher, not decoration: it reaches 10 of the hub's 17 modals
 * and is the only door to 7 of them (8 counting enrol_methods, which lives inside
 * participants). Dropping a tab's footer does not simplify a layout — it removes
 * the only way to act on the selected row. See docs/design-kit/sticky-footer.html.
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
    // The core/sticky-footer no-manager fallback adds `v-hidden` (visibility: hidden) when a
    // hide() runs before the theme registers its manager (e.g. the Frameworks tab's init on
    // page load). The manager-path enable does not clear it, so remove it here or the bar
    // stays invisible even though the theme slid it up.
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
    // Switching to a different tab clears the footer for a clean slate; the entering
    // tab's own init re-asserts it. This also covers the Frameworks tab (no footer) with
    // no Frameworks-side code. A native click is used rather than show.bs.tab because
    // Moodle 4.5 runs Bootstrap 4, which dispatches tab events via jQuery — native
    // 'show.bs.tab' listeners never fire there. The active-tab guard reads the pre-click
    // state (this direct listener runs before Bootstrap's delegated one), so re-clicking
    // the current tab does not wrongly clear its footer.
    document.querySelectorAll(TAB_TOGGLE).forEach((toggle) => {
        toggle.addEventListener('click', () => {
            if (!toggle.classList.contains('active')) {
                hide();
            }
        });
    });
};
