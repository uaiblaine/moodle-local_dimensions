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
 * Shared view-state store for the Competency hub. Holds the user's last-visited navigation
 * (tab / context / category / selected framework / template) and the display-toggle choices in
 * memory, seeded once from the server on page load, and persists changes to Moodle user
 * preferences (debounced) so the hub is restored on the next visit — across sessions and
 * devices. Replaces the previous per-session sessionStorage persistence.
 *
 * @module     local_dimensions/central/preferences
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {setUserPreference} from 'core_user/repository';
import {notifyError} from 'local_dimensions/central/errors';

/** @type {String} User preference name for the hub navigation state. */
const PREF_NAV = 'local_dimensions_central_nav';
/** @type {String} User preference name for the hub display-toggle state. */
const PREF_DISPLAY = 'local_dimensions_central_display';
/** @type {Number} Debounce (ms) before a change is written to the server. */
const SAVE_DELAY = 400;

/** @type {Object} Default navigation state. */
const NAV_DEFAULTS = {tab: 'frameworks', contexttype: 'system', categoryid: 0, frameworkid: 0, templateid: 0};
/** @type {Object} Default display state. */
const DISPLAY_DEFAULTS = {
    structure: {tax: false, id: false, rule: true, showhidden: false},
    planslist: {id: false, duedate: false},
    plansdetail: {tax: false, path: false, id: false},
    panels: {structure: true, planslist: true, plansdetail: true},
    plansshowdisabled: false,
    frameworksshowhidden: false,
    modalexpanded: false,
};

/**
 * Deep-clone a plain JSON-safe object.
 *
 * @param {Object} value
 * @return {Object}
 */
const clone = (value) => JSON.parse(JSON.stringify(value));

/** @type {Object} Live navigation state (authoritative for the session). */
let nav = clone(NAV_DEFAULTS);
/** @type {Object} Live display state (authoritative for the session). */
let display = clone(DISPLAY_DEFAULTS);
/** @type {Object} Pending debounce timer ids, keyed by preference name. */
const timers = {};

/**
 * Schedule a debounced write of a preference to the server.
 *
 * @param {String} name Preference name.
 * @param {Object} value Value to JSON-encode and store.
 */
const scheduleSave = (name, value) => {
    window.clearTimeout(timers[name]);
    timers[name] = window.setTimeout(() => {
        setUserPreference(name, JSON.stringify(value)).catch(notifyError);
    }, SAVE_DELAY);
};

/**
 * Seed the store from the server-rendered state. Called once on page load.
 *
 * @param {Object} state {nav: Object, display: Object} from the server.
 */
export const init = (state) => {
    const seed = state || {};
    nav = {...clone(NAV_DEFAULTS), ...(seed.nav || {})};
    const incoming = seed.display || {};
    display = {
        structure: {...DISPLAY_DEFAULTS.structure, ...(incoming.structure || {})},
        planslist: {...DISPLAY_DEFAULTS.planslist, ...(incoming.planslist || {})},
        plansdetail: {...DISPLAY_DEFAULTS.plansdetail, ...(incoming.plansdetail || {})},
        panels: {...DISPLAY_DEFAULTS.panels, ...(incoming.panels || {})},
        plansshowdisabled: Boolean(incoming.plansshowdisabled),
        frameworksshowhidden: Boolean(incoming.frameworksshowhidden),
        modalexpanded: Boolean(incoming.modalexpanded),
    };
};

/**
 * The current navigation state.
 *
 * @return {Object}
 */
export const getNav = () => nav;

/**
 * The current display state.
 *
 * @return {Object}
 */
export const getDisplay = () => display;

/**
 * Merge a partial navigation change and persist it (debounced).
 *
 * @param {Object} partial Keys to overwrite on the navigation state.
 */
export const saveNav = (partial) => {
    nav = {...nav, ...partial};
    scheduleSave(PREF_NAV, nav);
};

/**
 * Merge a partial display change (one level deep for the nested sections) and persist it.
 *
 * @param {Object} partial e.g. {structure: {tax: true}} or {plansshowdisabled: true}.
 */
export const saveDisplay = (partial) => {
    Object.keys(partial).forEach((key) => {
        const value = partial[key];
        if (value && typeof value === 'object' && display[key] && typeof display[key] === 'object') {
            display[key] = {...display[key], ...value};
        } else {
            display[key] = value;
        }
    });
    scheduleSave(PREF_DISPLAY, display);
};
