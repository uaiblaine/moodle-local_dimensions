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
 * Live colour swatch for the Competency hub's colour custom-field inputs.
 *
 * The bg/text colour custom fields are plain text inputs holding a hex value.
 * This module prepends a small swatch that tracks what the user types, so the
 * hex is not entered blind. Wired from the competency and template dynamic
 * forms via js_call_amd in definition_after_data(), which runs inside the
 * modal's JS-collection window (same timing as tool_lp/scaleconfig).
 *
 * @module     local_dimensions/central/colour_swatch
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Return a normalised 3/6-digit hex colour, or an empty string.
 *
 * @param {String} value Raw input value.
 * @return {String}
 */
const normaliseColour = (value) => {
    const trimmed = (value || '').trim();
    return (/^#([0-9a-f]{3}|[0-9a-f]{6})$/i).test(trimmed) ? trimmed : '';
};

/**
 * Prepend a live swatch to one colour custom-field input.
 *
 * @param {String} fieldname Custom-field shortname (without the customfield_ prefix).
 */
const decorate = (fieldname) => {
    if (!fieldname) {
        return;
    }
    const input = document.querySelector('[name="customfield_' + fieldname + '"]');
    if (!input || !input.parentNode || input.dataset.localDimensionsSwatch === '1') {
        return;
    }
    input.dataset.localDimensionsSwatch = '1';

    const row = document.createElement('span');
    row.className = 'local-dimensions-central-colour-row';
    const swatch = document.createElement('span');
    swatch.className = 'local-dimensions-central-colour-swatch';

    input.parentNode.insertBefore(row, input);
    row.appendChild(swatch);
    row.appendChild(input);

    const update = () => {
        const colour = normaliseColour(input.value);
        swatch.style.backgroundColor = colour || 'transparent';
        swatch.classList.toggle('local-dimensions-central-colour-swatch-empty', !colour);
    };
    input.addEventListener('input', update);
    update();
};

/**
 * Decorate the background and text colour inputs, if present in the form.
 *
 * @param {String} bgfield Background-colour custom-field shortname.
 * @param {String} textfield Text-colour custom-field shortname.
 */
export const init = (bgfield, textfield) => {
    decorate(bgfield);
    decorate(textfield);
};
