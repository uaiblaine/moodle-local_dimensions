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
 * Network-aware error routing for the Competency hub.
 *
 * A transport failure (offline, dropped connection, timeout) rejects a core/ajax call, including
 * core/templates' core_output_load_template_with_dependencies, with a bare string rather than an
 * error object. core/notification's exception() renders that as a YUI exception dialogue with an
 * "undefined" title and body. Connectivity drops therefore get a dismissible toast instead, while
 * application errors keep the exception dialogue.
 *
 * @module     local_dimensions/central/errors
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';
import {add as addToast} from 'local_dimensions/central/toast';
import {getString} from 'core/str';

/**
 * Decide whether a rejected call failed because of a connectivity problem rather
 * than a genuine application error.
 *
 * navigator.onLine === false is the strongest signal. Otherwise only core/ajax's
 * requestFail() rejects with jQuery's bare errorThrown, a string ('' for a dropped
 * connection, 'timeout', 'abort', or an HTTP error's status text, which this cannot
 * tell apart), or with nothing at all. Every other rejection
 * is an object and an error to show: a web-service exception (with a Moodle
 * errorcode), a script fault (TypeError and friends), core/ajax's own
 * Error('missing response') and jQuery's parse error on a broken response.
 *
 * @param {*} error The rejection value.
 * @return {Boolean} True when the failure looks like a connectivity drop.
 */
export const isNetworkError = (error) => {
    if (typeof navigator !== 'undefined' && navigator.onLine === false) {
        return true;
    }
    return typeof error === 'string' || !error;
};

/**
 * Route a rejected promise or caught error. Connectivity failures show a
 * friendly dismissible toast; genuine application errors keep routing through
 * core/notification's exception modal. Safe as a drop-in for
 * Notification.exception in any .catch() or catch block.
 *
 * @param {*} error The rejection value.
 * @return {Promise<void>}
 */
export const notifyError = async(error) => {
    if (!isNetworkError(error)) {
        Notification.exception(error);
        return;
    }
    let message = 'Connection lost. Please check your network and try again.';
    try {
        message = await getString('errornetwork', 'local_dimensions');
    } catch (e) {
        // Offline, fetching an uncached string fails too; keep the English fallback.
    }
    await addToast(message, {type: 'warning'});
};
