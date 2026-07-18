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
 * Thin wrapper over core/toast for the Competency hub: every hub toast gets a close button by
 * default, so a user firing a repeated action (which stacks several auto-hiding confirmations)
 * can dismiss them immediately instead of waiting out each 4s delay. The button is core's own
 * right-aligned btn-close (supported since Moodle 4.5; ignored gracefully on older cores).
 * The single source for the plugin's toast behaviour — hub modules import add/addToastRegion
 * from here, not straight from core/toast.
 *
 * @module     local_dimensions/central/toast
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {add as coreAdd, addToastRegion} from 'core/toast';

/**
 * Show a toast that carries a close button unless the caller opts out. Every other core/toast
 * option still applies and overrides the default (e.g. type, autohide, delay).
 *
 * @param {String|Promise<string>} message The toast text (or a promise resolving to it).
 * @param {Object} [configuration] core/toast options; closeButton defaults to true here.
 * @return {Promise<void>}
 */
export const add = (message, configuration) => coreAdd(message, {closeButton: true, ...configuration});

export {addToastRegion};
