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
 * The category field of the competency hub v2 toolbar follows its context: hidden and left out
 * of the submission while the context is System, shown when it is Course category.
 *
 * Without this module the field stays visible and the form still works; the server ignores a
 * category under the System context.
 *
 * @module     local_dimensions/hubv2_toolbar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Show or hide the category field for the context now selected.
 *
 * @param {HTMLFormElement} form The toolbar form.
 * @returns {void}
 */
const apply = (form) => {
    const context = form.querySelector('[name="contexttype"]');
    const wrapper = form.querySelector('[data-region="hubv2-category"]');
    const select = form.querySelector('[name="categoryid"]');
    if (!context || !wrapper || !select) {
        return;
    }
    const iscategory = context.value === 'coursecat';
    wrapper.hidden = !iscategory;
    select.disabled = !iscategory;
};

/**
 * Wire the toolbar form.
 *
 * @param {String} selector The form's selector.
 * @returns {void}
 */
export const init = (selector) => {
    const form = document.querySelector(selector);
    const context = form ? form.querySelector('[name="contexttype"]') : null;
    if (!form || !context) {
        return;
    }
    apply(form);
    context.addEventListener('change', () => apply(form));
};
