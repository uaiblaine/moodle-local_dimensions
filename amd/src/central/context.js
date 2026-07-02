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
 * The headline counter and the per-category counts adapt to the active tab (frameworks
 * in Structure, learning plans in Plans) from data embedded at render time.
 *
 * @module     local_dimensions/central/context
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';
import {reloadPane} from 'local_dimensions/central/tabs';

const SELECTORS = {
    bar: '[data-region="contextbar"]',
    context: '[data-action="context"]',
    categoryWrapper: '[data-region="category-wrapper"]',
    categorySelect: '[data-region="category-select"]',
    count: '[data-region="context-count"]',
    countValue: '[data-region="count-value"]',
    countNoun: '[data-mode]',
    categoryOption: '[data-region="category-select"] option[data-name]',
    activePane: '.dynamictabs .tab-pane.active',
    pane: '.dynamictabs [data-tab-content]',
    tabToggle: '.dynamictabs a[data-bs-toggle="tab"]',
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
    const option = select && select.selectedOptions[0];
    if (!option || !Number(option.value)) {
        return null;
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
        reloadPane(pane).catch(Notification.exception);
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
    // Context switch starts the guided category flow afresh.
    const select = bar.querySelector(SELECTORS.categorySelect);
    if (select) {
        select.value = '0';
    }
    bar.dataset.categoryid = 0;

    applyContextToPanes(contexttype, 0);
    renderCounter(bar);
    refreshActive();
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
    applyContextToPanes('coursecat', categoryid);
    renderCounter(bar);
    refreshActive();
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
        const button = event.target.closest(SELECTORS.context);
        if (button && button.dataset.context !== bar.dataset.contexttype) {
            setContext(bar, button.dataset.context);
        }
    });

    const select = bar.querySelector(SELECTORS.categorySelect);
    if (select) {
        select.addEventListener('change', () => setCategory(bar, select));
    }

    // Tab switches keep the counter and option labels in step with the active mode.
    document.querySelectorAll(SELECTORS.tabToggle).forEach((toggle) => {
        toggle.addEventListener('shown.bs.tab', () => {
            bar.dataset.activemode = activeMode();
            renderCounter(bar);
            renderOptionLabels(bar);
        });
    });
};
