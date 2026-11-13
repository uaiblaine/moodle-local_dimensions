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
 * Holds on Moodle's pending-JavaScript counter across the canvas's asynchronous gaps.
 *
 * core/ajax holds the counter only while a request is in flight; a chain of requests separated by
 * a React render (children loaded, state changed, the layout recomputed, the node centred) leaves
 * a gap Behat would read as a settled page. Each gap is spanned by one `local_dimensions/hubv2:<what>`
 * token from start to the last state change.
 *
 * @module     local_dimensions/hubv2/pending
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Pending from '@moodle/lms/core/pending';

/**
 * Hold the counter until a promise settles, whichever way.
 *
 * @param {string} what What is pending, the token's suffix.
 * @param {Promise} work The work.
 * @returns {Promise} The same outcome as the work.
 */
export const track = <T>(what: string, work: Promise<T>): Promise<T> => {
    const pending = new Pending(`local_dimensions/hubv2:${what}`);
    return work.finally(() => pending.resolve());
};
