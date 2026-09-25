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
 * View-state store for the learner views: which sort, which completion filter and which
 * layout the learner last chose. Seeded from the server-rendered markup so the first paint
 * is already correct, then persisted (debounced) to a Moodle user preference through core's
 * own repository - the plugin owns no tables and needs no web service of its own for this.
 *
 * A failed write is logged, not raised: losing a chrome preference must not interrupt the
 * learner with a modal.
 *
 * @module     local_dimensions/learner_prefs
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Log from 'core/log';
import {setUserPreference} from 'core_user/repository';

/** @type {String} User preference name holding the learner view chrome. */
const PREF_VIEW = 'local_dimensions_learner_view';
/** @type {String} User preference name holding the per-plan favourites. */
const PREF_FAV = 'local_dimensions_learner_fav';
/** @type {Number} Debounce (ms) before a change is written to the server. */
const SAVE_DELAY = 400;
/**
 * @type {Number} Longest favourites value a write may carry. On Moodle 4.5 the preference value
 * column holds 1333 characters (5.0 made it text) and core refuses a longer value outright, so the
 * write would fail and every later star would be lost with it.
 */
const MAX_FAV_LENGTH = 1333;
/** @type {Object} Default view state, mirrored server-side. */
const DEFAULTS = {sort: 'planorder', filter: 'incomplete', view: 'list'};

/** @type {Object} Live view state (authoritative for the session). */
let state = {...DEFAULTS};
/** @type {Object} The whole stored favourites map, plan id => competency ids. */
let favourites = {};
/** @type {String} The plan this page is showing. */
let favplan = '0';
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
        setUserPreference(name, JSON.stringify(value)).catch(Log.error);
    }, SAVE_DELAY);
};

/**
 * Seed the store from the server-rendered state. Call once, before any save.
 *
 * @param {Object} seed Partial state read from the markup.
 */
export const init = (seed) => {
    state = {...DEFAULTS, ...(seed || {})};
};

/**
 * The current view state.
 *
 * @return {Object}
 */
export const get = () => state;

/**
 * Merge a partial change and persist the whole state (debounced).
 *
 * @param {Object} partial Keys to overwrite on the view state.
 */
export const save = (partial) => {
    state = {...state, ...partial};
    scheduleSave(PREF_VIEW, state);
};

/**
 * Seed the favourites store with the whole stored map, not just this plan's list.
 *
 * A write replaces the entire preference, so anything the page never saw would be lost.
 * Only the current plan's list is normalised; the other plans are passed back untouched.
 *
 * @param {Number|String} planid The plan this page is showing.
 * @param {Object} map The stored map, plan id => competency ids.
 */
export const initFavourites = (planid, map) => {
    favplan = String(planid);
    favourites = (map && typeof map === 'object') ? map : {};
    favourites[favplan] = Array.isArray(favourites[favplan]) ? favourites[favplan].map(Number) : [];
};

/**
 * The favourites map trimmed to fit one preference value.
 *
 * Other plans' empty lists are dropped outright. While the value is still too long, other plans'
 * lists go next, lowest plan id (the oldest plan) first, then this plan's oldest favourites; the
 * star just set is the last in its list and always stays.
 *
 * @param {Object} map Plan id => competency ids.
 * @param {String} planid The plan this page is showing.
 * @return {Object} A map whose JSON fits MAX_FAV_LENGTH.
 */
const fitFavourites = (map, planid) => {
    const fitted = {};
    Object.keys(map).forEach((key) => {
        if (key === planid || (Array.isArray(map[key]) && map[key].length > 0)) {
            fitted[key] = map[key];
        }
    });
    // Integer-like keys enumerate in ascending numeric order, whatever order they were added in.
    const others = Object.keys(fitted).filter((key) => key !== planid);
    while (JSON.stringify(fitted).length > MAX_FAV_LENGTH && others.length > 0) {
        delete fitted[others.shift()];
    }
    while (JSON.stringify(fitted).length > MAX_FAV_LENGTH && fitted[planid].length > 1) {
        fitted[planid].shift();
    }
    return fitted;
};

/**
 * Add or remove a competency from this plan's favourites, and persist (debounced).
 *
 * @param {Number|String} competencyid The competency to toggle.
 * @return {Boolean} Whether it is now a favourite.
 */
export const toggleFavourite = (competencyid) => {
    const list = favourites[favplan];
    const index = list.indexOf(Number(competencyid));
    if (index === -1) {
        list.push(Number(competencyid));
    } else {
        list.splice(index, 1);
    }
    favourites = fitFavourites(favourites, favplan);
    scheduleSave(PREF_FAV, favourites);
    return index === -1;
};
