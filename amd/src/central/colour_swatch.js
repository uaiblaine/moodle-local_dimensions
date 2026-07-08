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
 * Live, clickable colour swatch for the Competency hub's colour custom-field inputs.
 *
 * The bg/text colour custom fields are plain text inputs holding a hex value.
 * This module prepends a swatch that both previews and edits the colour: it
 * tracks what the user types, and clicking it opens a native colour picker that
 * writes the chosen hex back into the text input (dispatching input + change so
 * the real-time contrast panel and any other listeners update). Wired from the
 * competency and template dynamic forms via js_call_amd in definition_after_data().
 *
 * @module     local_dimensions/central/colour_swatch
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getString} from 'core/str';
import Notification from 'core/notification';

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
 * Expand a normalised hex to the #rrggbb form a native colour input accepts.
 *
 * @param {String} colour A normalised (valid) hex colour, or an empty string.
 * @return {String} A #rrggbb colour, or an empty string when the input is empty.
 */
const toSixDigit = (colour) => {
    if (!colour) {
        return '';
    }
    if (colour.length === 4) {
        return '#' + colour[1] + colour[1] + colour[2] + colour[2] + colour[3] + colour[3];
    }
    return colour;
};

/**
 * Prepend a live, clickable swatch to one colour custom-field input.
 *
 * @param {String} fieldname Custom-field shortname (without the customfield_ prefix).
 * @param {String} pickerlabel Accessible label for the native colour input.
 */
const decorate = (fieldname, pickerlabel) => {
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

    const picker = document.createElement('label');
    picker.className = 'local-dimensions-central-colour-swatch';
    const preview = document.createElement('span');
    preview.className = 'local-dimensions-central-colour-preview';
    const colourinput = document.createElement('input');
    colourinput.type = 'color';
    colourinput.className = 'local-dimensions-central-colour-input';
    colourinput.setAttribute('aria-label', pickerlabel);
    picker.appendChild(preview);
    picker.appendChild(colourinput);

    input.parentNode.insertBefore(row, input);
    row.appendChild(picker);
    row.appendChild(input);

    const sync = () => {
        const colour = normaliseColour(input.value);
        preview.style.backgroundColor = colour || 'transparent';
        preview.classList.toggle('local-dimensions-central-colour-swatch-empty', !colour);
        const full = toSixDigit(colour);
        if (full) {
            colourinput.value = full;
        }
    };

    // Typing in the hex field updates the swatch and the native picker.
    input.addEventListener('input', sync);
    // Picking a colour writes it back to the hex field and notifies listeners.
    colourinput.addEventListener('input', () => {
        input.value = colourinput.value;
        input.dispatchEvent(new Event('input', {bubbles: true}));
        input.dispatchEvent(new Event('change', {bubbles: true}));
    });
    sync();
};

/**
 * Decorate the background and text colour inputs, if present in the form.
 *
 * @param {String} bgfield Background-colour custom-field shortname.
 * @param {String} textfield Text-colour custom-field shortname.
 */
export const init = (bgfield, textfield) => {
    getString('colourpicker', 'local_dimensions')
        .then((pickerlabel) => {
            decorate(bgfield, pickerlabel);
            decorate(textfield, pickerlabel);
            return pickerlabel;
        })
        .catch(Notification.exception);
};
