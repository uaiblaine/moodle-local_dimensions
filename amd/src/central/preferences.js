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
 * preferences (debounced) so the hub is restored on the next visit, across sessions and
 * devices.
 *
 * @module     local_dimensions/central/preferences
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Pending from 'core/pending';
import {notifyError} from 'local_dimensions/central/errors';

/** @type {String} User preference name for the hub navigation state. */
const PREF_NAV = 'local_dimensions_central_nav';
/** @type {String} User preference name for the hub display-toggle state. */
const PREF_DISPLAY = 'local_dimensions_central_display';
/**
 * @type {String[]} The Competencies tab's views (structure::VIEWS). The default depends on the hub's mode and is
 * the server's to apply (structure::resolve_view()): an unknown saved view is kept as none ('').
 */
const STRUCTURE_VIEWS = ['list', 'outline', 'map', 'both'];
/**
 * @type {String[]} The workspace's lenses (hub_map::LENSES). As with the view, the server resolves the lens: an
 * unknown saved lens is kept as none saved ('').
 */
const STRUCTURE_LENSES = ['none', 'usage', 'rules', 'relations'];
/** @type {Number} Debounce (ms) before a change is written to the server. */
const SAVE_DELAY = 400;

/** @type {Object} Default navigation state. */
const NAV_DEFAULTS = {
    tab: 'frameworks', contexttype: 'system', categoryid: 0, frameworkid: 0, templateid: 0, showhiddencats: false,
};
/** @type {Object} Default display state. */
const DISPLAY_DEFAULTS = {
    structure: {tax: false, id: false, rule: true, showhidden: false, view: '', lens: ''},
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

/** @type {Object} Live navigation state (authoritative for this page load). */
let nav = clone(NAV_DEFAULTS);
/** @type {Object} Live display state (authoritative for this page load). */
let display = clone(DISPLAY_DEFAULTS);
/** @type {Object} Pending debounce timer ids, keyed by preference name. */
const timers = {};
/**
 * The context the viewer had saved before this visit, when the page is locked to the course
 * category it was entered from; saveNav() writes it in place of the locked context, so the entry
 * context wins for the visit but is never remembered. Null when the page is not locked.
 *
 * @type {Object|null}
 */
let pinnedcontext = null;

/** @type {Object} The page's pending marker of each scheduled write, keyed by preference name. */
const pendings = {};

/**
 * Write one of the hub's preferences.
 *
 * Through core's preference web service, never core_user/repository's setUserPreference(): that helper posts to
 * core's REST route, which refuses (HTTP 400) a value the preference's cleaning callback changes, and the display
 * preference's callback changes every write once a mode or a lens is stored, by putting the stored one back
 * (hub_mode::clean_display(), hub_map::clean_display()). The web service stores the cleaned value.
 *
 * @param {String} name Preference name.
 * @param {Object} payload Value to JSON-encode and store.
 * @return {Promise} Resolved once the server has answered.
 */
const writePreference = (name, payload) => {
    return Ajax.call([{
        methodname: 'core_user_set_user_preferences',
        args: {preferences: [{name, value: JSON.stringify(payload), userid: 0}]},
    }])[0];
};

/**
 * Schedule a debounced write of a preference to the server.
 *
 * The page stays pending from the choice to the server's answer: the delay is not a request yet, and a test that
 * leaves the page inside it would never see the write.
 *
 * @param {String} name Preference name.
 * @param {Object} value Value to JSON-encode and store.
 */
const scheduleSave = (name, value) => {
    window.clearTimeout(timers[name]);
    const pending = pendings[name] || new Pending(`local_dimensions/central/preferences:${name}`);
    pendings[name] = pending;
    timers[name] = window.setTimeout(async() => {
        delete pendings[name];
        const payload = typeof value === 'function' ? value() : value;
        try {
            await writePreference(name, payload);
        } catch (error) {
            await notifyError(error);
        } finally {
            pending.resolve();
        }
    }, SAVE_DELAY);
};

/** @type {Boolean} Whether the lens was chosen since the last write: only then does a write carry it. */
let lenschosen = false;

/**
 * The display state as one write sends it: the lens only when it was chosen since the last write, so a page left
 * open never writes the lens it loaded with over one chosen since in another (hub_map::clean_display() keeps the
 * stored lens of a write without one).
 *
 * @return {Object}
 */
const displayPayload = () => {
    const payload = clone(display);
    if (!lenschosen) {
        delete payload.structure.lens;
    }
    lenschosen = false;
    return payload;
};

/**
 * Seed the store from the server-rendered state. Called once on page load.
 *
 * @param {Object} state {nav, display, lockedcontext, storedcontext} from the server (central.php).
 */
export const init = (state) => {
    const seed = state || {};
    nav = {...clone(NAV_DEFAULTS), ...(seed.nav || {})};
    pinnedcontext = null;
    if (seed.lockedcontext && seed.storedcontext) {
        pinnedcontext = {
            contexttype: seed.storedcontext.contexttype === 'coursecat' ? 'coursecat' : 'system',
            categoryid: Number(seed.storedcontext.categoryid) || 0,
        };
    }
    const incoming = seed.display || {};
    const structure = {...DISPLAY_DEFAULTS.structure, ...(incoming.structure || {})};
    // As helper::get_central_prefs() reads them: anything but a known view or lens is none saved.
    if (!STRUCTURE_VIEWS.includes(structure.view)) {
        structure.view = '';
    }
    if (!STRUCTURE_LENSES.includes(structure.lens)) {
        structure.lens = '';
    }
    display = {
        structure,
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
    scheduleSave(PREF_NAV, pinnedcontext ? {...nav, ...pinnedcontext} : nav);
};

/**
 * Merge a partial display change (one level deep for the nested sections) and persist it.
 *
 * The store never holds the hub's mode (`classic`), so it never writes it: the server keeps the stored one on
 * every client write (hub_mode::clean_display()), and only the context bar's link changes it. The lens is written
 * only by the write that follows its choice (displayPayload()); every other write leaves the stored one.
 *
 * @param {Object} partial e.g. {structure: {tax: true}} or {plansshowdisabled: true}.
 */
export const saveDisplay = (partial) => {
    if (partial.structure && typeof partial.structure === 'object' && 'lens' in partial.structure) {
        lenschosen = true;
    }
    Object.keys(partial).forEach((key) => {
        const value = partial[key];
        if (value && typeof value === 'object' && display[key] && typeof display[key] === 'object') {
            display[key] = {...display[key], ...value};
        } else {
            display[key] = value;
        }
    });
    scheduleSave(PREF_DISPLAY, displayPayload);
};
