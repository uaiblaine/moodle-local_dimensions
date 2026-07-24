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
 * Open/slim toggle for the learner hero, shared by the plan overview and the competency
 * tracker. The handle folds the header down to its title (plus the plan's due date) and
 * back, and stores the choice as a Moodle user preference through core's own repository -
 * the plugin owns no tables and needs no web service of its own for this.
 *
 * The state is rendered server-side, so this module never applies it on load: it only
 * flips it. A failed write is logged, not raised: losing a chrome preference must not
 * interrupt the learner with a modal.
 *
 * @module     local_dimensions/hero_collapse
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Log from 'core/log';
import {setUserPreference} from 'core_user/repository';
import {remeasure} from 'local_dimensions/collapsible_description';

/** @type {String} User preference name holding the hero's open/slim choice. */
const PREF_HERO = 'local_dimensions_learner_hero';
/** @type {String} Class that folds the hero to its slim state. */
const SLIM_CLASS = 'local-dimensions-hero-slim';

/**
 * Bring the handle's label, chevron and aria state in line with the hero.
 *
 * @param {HTMLElement} button The collapse handle.
 * @param {Boolean} slim Whether the hero is now slim.
 */
const syncHandle = (button, slim) => {
    button.setAttribute('aria-expanded', slim ? 'false' : 'true');

    const label = button.querySelector('[data-hero-collapse-label]');
    if (label) {
        label.textContent = slim ? button.dataset.labelExpand : button.dataset.labelCollapse;
    }

    const icon = button.querySelector('i');
    if (icon) {
        icon.classList.toggle('fa-chevron-up', !slim);
        icon.classList.toggle('fa-chevron-down', slim);
    }
};

/**
 * Wire the hero's open/slim handle, persisting each choice for the learner.
 */
export const init = () => {
    const button = document.querySelector('[data-hero-collapse]');
    const hero = button ? button.closest('.local-dimensions-hero-wrapper') : null;
    if (!hero) {
        return;
    }

    button.addEventListener('click', () => {
        const slim = !hero.classList.contains(SLIM_CLASS);
        hero.classList.toggle(SLIM_CLASS, slim);
        syncHandle(button, slim);

        /* The description is display:none while the hero is slim, so it measured as fitting
           and its "See more" toggle stayed hidden. Re-measure now that it is back on screen,
           or a clipped description would offer no way to open it. */
        if (!slim) {
            remeasure();
        }

        setUserPreference(PREF_HERO, slim ? '1' : '0').catch(Log.error);
    });
};
