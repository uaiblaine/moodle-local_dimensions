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
 * Shared client-side download helpers for the Competency hub's CSV transfer modals.
 *
 * Both export modals (structures and learning plan templates) receive their CSV as a string
 * from a web service and hand it to the browser as a file, with a small spinner while the
 * call runs. The two helpers live here so the Frameworks and Plans modules share one
 * implementation instead of each carrying a copy.
 *
 * @module     local_dimensions/central/download
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Stream a CSV string to the browser as a downloaded file.
 *
 * @param {String} filename The suggested filename.
 * @param {String} content The CSV content.
 * @return {void}
 */
export const triggerDownload = (filename, content) => {
    const blob = new Blob([content], {type: 'text/csv;charset=utf-8'});
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
};

/**
 * A small Bootstrap spinner element.
 *
 * @return {HTMLElement}
 */
export const makeSpinner = () => {
    const spinner = document.createElement('span');
    spinner.className = 'spinner-border spinner-border-sm';
    spinner.setAttribute('aria-hidden', 'true');
    return spinner;
};
