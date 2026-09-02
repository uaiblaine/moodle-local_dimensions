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
 * Shared context selector for the Competency hub. Lives above the dynamic tabs and
 * governs both of them: switching System / Course category (or picking a category)
 * pushes the context onto every tab pane and refreshes the active one — no page reload.
 * The category picker searches on demand (category_datasource) rather than listing every
 * category of the site; the headline counter adapts to the active tab (frameworks in
 * Structure, learning plans in Plans) from the counts each hit and the selected option carry.
 *
 * @module     local_dimensions/central/context
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import {notifyError} from 'local_dimensions/central/errors';
import {getString} from 'core/str';
import {enhance} from 'core/form-autocomplete';
import {reloadPane} from 'local_dimensions/central/tabs';
import * as Preferences from 'local_dimensions/central/preferences';
import * as Categories from 'local_dimensions/central/category_datasource';

/**
 * Pristine clone of the category wrapper (label + raw select), taken before the autocomplete
 * enhancement mutates it. core/form-autocomplete has no reset API, so we restore this trusted
 * DOM subtree and re-enhance whenever a context switch invalidates the selection.
 *
 * @type {HTMLElement|null}
 */
let pristineCategoryNode = null;

const SELECTORS = {
    bar: '[data-region="contextbar"]',
    context: '[data-action="context"]',
    refresh: '[data-action="refresh"]',
    categoryWrapper: '[data-region="category-wrapper"]',
    categorySelect: '[data-region="category-select"]',
    count: '[data-region="context-count"]',
    countValue: '[data-region="count-value"]',
    countNoun: '[data-mode]',
    categoryOption: '[data-region="category-select"] option[data-name]',
    hiddenCatsBlock: '[data-region="hidden-cats"]',
    hiddenCatsToggle: '[data-action="toggle-hidden-cats"]',
    activePane: '.dynamictabs .tab-pane.active',
    pane: '.dynamictabs [data-tab-content]',
    // Core's dynamic_tabs template emits data-toggle on Moodle 4.5 (Bootstrap 4) and
    // data-bs-toggle on 5.x (Bootstrap 5), so match either attribute.
    tabToggle: '.dynamictabs a[data-toggle="tab"], .dynamictabs a[data-bs-toggle="tab"]',
};

/**
 * Mode of the currently active tab ('plans' or 'structure').
 *
 * @return {String}
 */
const activeMode = () => {
    const pane = document.querySelector(SELECTORS.activePane);
    return pane && pane.dataset.tabContent === 'plans' ? 'plans' : 'structure';
};

/**
 * Framework and plan counts of the currently selected context, or null when a course
 * category is required but not yet chosen.
 *
 * @param {HTMLElement} bar
 * @return {Object|null}
 */
const selectedCounts = (bar) => {
    if (bar.dataset.contexttype !== 'coursecat') {
        return {
            frameworks: Number(bar.dataset.systemframeworkcount || 0),
            plans: Number(bar.dataset.systemtemplatecount || 0),
        };
    }
    const select = bar.querySelector(SELECTORS.categorySelect);
    if (!select) {
        // Locked to the category the page was entered from: there is no picker, and the counts
        // of that one category ride on the counter region itself.
        const region = bar.querySelector(SELECTORS.count);
        if (!region) {
            return null;
        }
        return {
            frameworks: Number(region.dataset.frameworkcount || 0),
            plans: Number(region.dataset.templatecount || 0),
        };
    }
    const option = select.selectedOptions[0];
    if (!option || !Number(option.value)) {
        return null;
    }
    if (option.dataset.frameworkcount === undefined) {
        // An option the autocomplete created from a search hit carries no counts of its own;
        // the datasource remembers what the search returned for it.
        const hit = Categories.known(option.value);
        return hit ? {frameworks: hit.frameworks, plans: hit.plans} : null;
    }
    return {
        frameworks: Number(option.dataset.frameworkcount || 0),
        plans: Number(option.dataset.templatecount || 0),
    };
};

/**
 * Render the headline counter (value + frameworks/plans noun) for the active mode.
 *
 * @param {HTMLElement} bar
 */
const renderCounter = (bar) => {
    const region = bar.querySelector(SELECTORS.count);
    if (!region) {
        return;
    }
    const mode = activeMode();
    region.querySelectorAll(SELECTORS.countNoun).forEach((noun) => {
        noun.hidden = noun.dataset.mode !== mode;
    });
    const counts = selectedCounts(bar);
    if (!counts) {
        region.hidden = true;
        return;
    }
    region.hidden = false;
    region.querySelector(SELECTORS.countValue).textContent = mode === 'plans' ? counts.plans : counts.frameworks;
};

/**
 * Re-label the category options with the active mode's count.
 *
 * @param {HTMLElement} bar
 */
const renderOptionLabels = (bar) => {
    const mode = activeMode();
    bar.querySelectorAll(SELECTORS.categoryOption).forEach((option) => {
        const count = mode === 'plans' ? option.dataset.templatecount : option.dataset.frameworkcount;
        option.textContent = `${option.dataset.name} (${count})`;
    });
};

/**
 * Whether hidden course categories are currently shown (the toggle is on). False when the
 * toggle is absent (no hidden category is reachable).
 *
 * @param {HTMLElement} bar
 * @return {Boolean}
 */
const showHiddenCats = (bar) => {
    const toggle = bar.querySelector(SELECTORS.hiddenCatsToggle);
    return !!(toggle && toggle.checked);
};

/**
 * Push the shared context onto every tab pane, resetting the per-tab selection
 * (framework / template) that a context change invalidates.
 *
 * @param {String} contexttype
 * @param {Number} categoryid
 */
const applyContextToPanes = (contexttype, categoryid) => {
    document.querySelectorAll(SELECTORS.pane).forEach((pane) => {
        pane.dataset.contexttype = contexttype;
        pane.dataset.categoryid = categoryid;
        pane.dataset.frameworkid = 0;
        if ('templateid' in pane.dataset) {
            pane.dataset.templateid = 0;
        }
        if ('competencyids' in pane.dataset) {
            pane.dataset.competencyids = '';
        }
    });
};

/**
 * Refresh the active tab pane from the server.
 */
const refreshActive = () => {
    const pane = document.querySelector(SELECTORS.activePane);
    if (pane) {
        reloadPane(pane).catch(notifyError);
    }
};

/**
 * Reload the active tab pane on demand, showing the refresh control busy while it fetches.
 * Mirrors the enrol pane's discipline — disable and spin in a finally so a failed reload still
 * releases the control (never spins forever) and can be retried. reloadPane already covers the
 * pane itself with its busy overlay; this only signals the control the user pressed.
 *
 * @param {HTMLElement} button The refresh control.
 * @return {Promise<void>}
 */
const refresh = async(button) => {
    const pane = document.querySelector(SELECTORS.activePane);
    if (!pane) {
        return;
    }
    const icon = button.querySelector('.fa');
    button.disabled = true;
    if (icon) {
        icon.classList.add('fa-spin');
    }
    try {
        await reloadPane(pane);
    } finally {
        button.disabled = false;
        if (icon) {
            icon.classList.remove('fa-spin');
        }
        // Disabling the button blurred it to <body>; reloadPane only re-homes focus when it was
        // inside the pane, so a keyboard user who pressed the control would land nowhere. Return
        // focus to the now-enabled control unless focus has meanwhile moved elsewhere.
        if (document.activeElement === document.body) {
            button.focus();
        }
    }
};

/**
 * Switch the System / Course category context.
 *
 * @param {HTMLElement} bar
 * @param {String} contexttype
 */
const setContext = (bar, contexttype) => {
    bar.dataset.contexttype = contexttype;
    bar.querySelectorAll(SELECTORS.context).forEach((button) => {
        const isactive = button.dataset.context === contexttype;
        button.classList.toggle('btn-primary', isactive);
        button.classList.toggle('btn-outline-secondary', !isactive);
    });

    const wrapper = bar.querySelector(SELECTORS.categoryWrapper);
    if (wrapper) {
        wrapper.hidden = contexttype !== 'coursecat';
    }
    const hiddenblock = bar.querySelector(SELECTORS.hiddenCatsBlock);
    if (hiddenblock) {
        hiddenblock.hidden = contexttype !== 'coursecat';
    }
    bar.dataset.categoryid = 0;

    // Context switch starts the guided category flow afresh: entering coursecat resets and
    // re-enhances the picker (its synchronous DOM reset runs before the counter reads it);
    // leaving it just clears the now-hidden native value.
    if (contexttype === 'coursecat') {
        enhanceCategory(bar, true).catch(notifyError);
    } else {
        const select = bar.querySelector(SELECTORS.categorySelect);
        if (select) {
            select.value = '0';
        }
    }

    applyContextToPanes(contexttype, 0);
    renderCounter(bar);
    refreshActive();
    Preferences.saveNav({contexttype: contexttype, categoryid: 0, frameworkid: 0, templateid: 0});
};

/**
 * Apply a newly chosen course category.
 *
 * @param {HTMLElement} bar
 * @param {HTMLSelectElement} select
 */
const setCategory = (bar, select) => {
    const categoryid = Number(select.value) || 0;
    bar.dataset.categoryid = categoryid;
    const option = select.selectedOptions[0];
    const hit = option ? Categories.known(option.value) : null;
    if (option && hit && option.dataset.name === undefined) {
        option.dataset.name = hit.name;
        option.dataset.frameworkcount = hit.frameworks;
        option.dataset.templatecount = hit.plans;
    }
    applyContextToPanes('coursecat', categoryid);
    renderCounter(bar);
    refreshActive();
    Preferences.saveNav({contexttype: 'coursecat', categoryid: categoryid, frameworkid: 0, templateid: 0});
};

/**
 * Enhance the category select into a searchable single-select autocomplete, and wire its
 * change handler. When `reset` is set the wrapper is first restored to its pristine markup
 * so a stale selection from a previous coursecat visit is dropped (form-autocomplete keeps
 * no reset API, so re-rendering the region is the supported way to clear it).
 *
 * @param {HTMLElement} bar
 * @param {Boolean} reset Whether to drop the current selection before enhancing.
 * @param {String|null} keepvalue On reset, the category value to restore (null resets to none).
 * @return {Promise<void>}
 */
const enhanceCategory = async(bar, reset, keepvalue = null) => {
    const wrapper = bar.querySelector(SELECTORS.categoryWrapper);
    if (!wrapper || pristineCategoryNode === null) {
        return;
    }
    if (reset) {
        // Restore the pristine label + select (cloned trusted DOM, so no markup parsing).
        wrapper.replaceChildren(...pristineCategoryNode.cloneNode(true).childNodes);
    }
    const select = wrapper.querySelector(SELECTORS.categorySelect);
    if (!select) {
        return;
    }
    if (reset) {
        select.value = keepvalue !== null ? keepvalue : '0';
    }
    // Match the option labels to the active tab's count before the autocomplete reads them, then
    // enhance with the search datasource: the select holds at most the selected category, and
    // every other one is fetched as the viewer types (the datasource reads the hidden toggle).
    renderOptionLabels(bar);
    const placeholder = await getString('managecompetencies_category_placeholder', 'local_dimensions');
    await enhance(
        SELECTORS.categorySelect,
        false,
        'local_dimensions/central/category_datasource',
        placeholder,
        false,
        true,
        placeholder,
        true
    );
    wrapper.querySelector(SELECTORS.categorySelect)
        .addEventListener('change', (event) => setCategory(bar, event.target));
};

/**
 * Persist the "show hidden categories" toggle. Nothing is rebuilt: the search datasource reads
 * the toggle on every request, so the next suggestions already honour it.
 *
 * @param {HTMLElement} bar
 */
const applyHiddenCats = (bar) => {
    Preferences.saveNav({showhiddencats: showHiddenCats(bar)});
};

/**
 * Initialise the shared context selector. Runs once on page load (the bar lives outside
 * the tab panes, so it is not re-rendered on tab refresh).
 */
export const init = () => {
    const bar = document.querySelector(SELECTORS.bar);
    if (!bar || bar.dataset.initialised === '1') {
        return;
    }
    bar.dataset.initialised = '1';

    bar.addEventListener('click', (event) => {
        const contextbtn = event.target.closest(SELECTORS.context);
        if (contextbtn && contextbtn.dataset.context !== bar.dataset.contexttype) {
            setContext(bar, contextbtn.dataset.context);
            return;
        }
        const refreshbtn = event.target.closest(SELECTORS.refresh);
        if (refreshbtn) {
            refresh(refreshbtn).catch(notifyError);
        }
    });

    const wrapper = bar.querySelector(SELECTORS.categoryWrapper);
    const select = bar.querySelector(SELECTORS.categorySelect);
    if (wrapper && select) {
        // Snapshot the pristine wrapper before enhancing so a later context switch can reset it.
        pristineCategoryNode = wrapper.cloneNode(true);
        if (bar.dataset.contexttype === 'coursecat') {
            enhanceCategory(bar, false).catch(notifyError);
        } else {
            select.addEventListener('change', () => setCategory(bar, select));
        }
    }

    // The "show hidden categories" toggle rebuilds the picker client-side; it lives outside the
    // category wrapper, so it survives the wrapper reset that a context switch performs.
    const hiddencb = bar.querySelector(SELECTORS.hiddenCatsToggle);
    if (hiddencb) {
        hiddencb.addEventListener('change', () => applyHiddenCats(bar));
    }

    // Tab switches keep the counter and option labels in step with the active mode. Bound via
    // jQuery because Bootstrap 4 (Moodle 4.5) only fires its tab events as jQuery events, which
    // never reach a native listener; Bootstrap 5 fires both, so one jQuery listener covers both.
    $(SELECTORS.tabToggle).on('shown.bs.tab', () => {
        bar.dataset.activemode = activeMode();
        renderCounter(bar);
        renderOptionLabels(bar);
        const active = document.querySelector(SELECTORS.activePane);
        if (active) {
            Preferences.saveNav({tab: active.dataset.tabContent});
        }
    });

    /*
     * Restoring the saved tab is NOT done here any more. This module initialises after core's
     * dynamic_tabs has already opened and fetched a tab, so clicking the saved one from here
     * fetched a second tab concurrently and threw the first away. The saved tab now reaches core
     * through the URL fragment, written synchronously by the local_dimensions/central/tab_hash
     * template before core initialises, and the server pre-renders that same tab.
     */
};
