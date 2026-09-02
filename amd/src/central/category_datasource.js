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
 * core/form-autocomplete datasource for the Competency hub's course category picker.
 *
 * Searches categories through local_dimensions_search_categories as the viewer types, so the
 * page never renders every category of the site. The "show hidden categories" toggle of the
 * bar is read fresh on each search, and each hit's counts are remembered so the headline
 * counter can follow a selection without another round-trip.
 *
 * @module     local_dimensions/central/category_datasource
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

const SELECTORS = {
    bar: '[data-region="contextbar"]',
    hiddenCatsToggle: '[data-action="toggle-hidden-cats"]',
};

/** @type {Map<String, Object>} Hits seen so far, keyed by category id: {name, frameworks, plans, hidden}. */
const seen = new Map();

/**
 * The context bar the originating select belongs to.
 *
 * @param {String} selector The originating select's selector.
 * @return {HTMLElement|null}
 */
const barOf = (selector) => {
    const source = document.querySelector(selector);
    return source ? source.closest(SELECTORS.bar) : null;
};

/**
 * Fetch the categories matching the query, honouring the bar's hidden-categories toggle.
 *
 * @param {String} selector The originating select's selector.
 * @param {String} query The user's search text.
 * @param {Function} success Callback receiving the raw item list.
 * @param {Function} failure Callback receiving an error.
 */
export const transport = (selector, query, success, failure) => {
    const bar = barOf(selector);
    const toggle = bar ? bar.querySelector(SELECTORS.hiddenCatsToggle) : null;
    Ajax.call([{
        methodname: 'local_dimensions_search_categories',
        args: {query: query, includehidden: !!(toggle && toggle.checked), limitnum: 25},
    }])[0].then((response) => success(response.items)).catch(failure);
};

/**
 * Map hits to autocomplete {value, label} pairs, labelled with the active mode's count.
 *
 * @param {String} selector The originating select's selector.
 * @param {Array} results Raw items from transport().
 * @return {Array}
 */
export const processResults = (selector, results) => {
    const bar = barOf(selector);
    const mode = bar && bar.dataset.activemode === 'plans' ? 'plans' : 'structure';
    return results.map((item) => {
        seen.set(String(item.id), {
            name: item.name,
            frameworks: Number(item.frameworkcount) || 0,
            plans: Number(item.templatecount) || 0,
            hidden: Boolean(item.hidden),
        });
        const count = mode === 'plans' ? item.templatecount : item.frameworkcount;
        return {value: item.id, label: `${item.name} (${count})`};
    });
};

/**
 * What a previous search learnt about a category, or null when it was never a hit.
 *
 * @param {String|Number} id The category id.
 * @return {Object|null} {name, frameworks, plans, hidden}
 */
export const known = (id) => seen.get(String(id)) || null;
