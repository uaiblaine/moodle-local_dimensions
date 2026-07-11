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
 * Build a compact icon + text action button for the hub's table rows.
 *
 * Shared by the cohort / participants / roles managers so every row action looks the same:
 * an outlined secondary button with a Font Awesome icon followed by the visible label. The
 * decorative icon is aria-hidden; the visible text is the accessible name (the "button"
 * Behat/ARIA selector matches it), so no separate title/aria-label is needed.
 *
 * @module     local_dimensions/central/action_button
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Create an icon + text action button.
 *
 * @param {String} action Value for the button's data-action attribute (delegated click handler).
 * @param {String} iconname Font Awesome class, e.g. 'fa-trash'.
 * @param {String} label Visible button text (and accessible name).
 * @param {String} [extraclass] Optional extra class(es), e.g. 'me-1' for inter-button spacing.
 * @return {HTMLButtonElement}
 */
export const iconButton = (action, iconname, label, extraclass = '') => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = ('btn btn-outline-secondary btn-sm ' + extraclass).trim();
    button.dataset.action = action;
    const icon = document.createElement('i');
    icon.className = 'fa ' + iconname + ' me-1';
    icon.setAttribute('aria-hidden', 'true');
    button.appendChild(icon);
    button.appendChild(document.createTextNode(label));
    return button;
};
