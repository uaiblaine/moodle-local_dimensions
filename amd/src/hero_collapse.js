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
 * The fold is per plan and per competency, so the preference holds the list of folded keys
 * rather than one flag. The page is seeded with the WHOLE list, not just its own key: a write
 * replaces the entire preference, so saving only this hero's key would unfold every other one.
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

/** @type {String} User preference name holding the list of folded heroes. */
const PREF_HERO = 'local_dimensions_learner_hero';
/** @type {String} Class that folds the hero to its slim state. */
const SLIM_CLASS = 'local-dimensions-hero-slim';
/**
 * @type {Number} How many folded heroes to keep. The preference value column holds 1333
 * characters, so an unbounded list would eventually fail to save; the least recently folded
 * hero is dropped instead, and simply opens the next time it is visited.
 */
const MAX_FOLDED = 100;

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
 * The stored list of folded heroes, seeded from the server-rendered markup.
 *
 * @param {HTMLElement} button The collapse handle.
 * @return {Array} The folded keys, most recently folded first.
 */
const readFolded = (button) => {
    try {
        const stored = JSON.parse(button.dataset.heroState || '[]');
        return Array.isArray(stored) ? stored.filter((key) => typeof key === 'string') : [];
    } catch (error) {
        // A corrupt value is a lost preference, not a broken page: start the list over.
        return [];
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

    const key = button.dataset.heroKey || '';
    let folded = readFolded(button);

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

        // Folding moves this hero to the front, so the cap drops the least recently folded.
        folded = folded.filter((stored) => stored !== key);
        if (slim) {
            folded.unshift(key);
            folded = folded.slice(0, MAX_FOLDED);
        }

        setUserPreference(PREF_HERO, JSON.stringify(folded)).catch(Log.error);
    });
};
