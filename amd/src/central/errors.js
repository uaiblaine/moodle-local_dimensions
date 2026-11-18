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
 * Moodle 5.3's core/ajax is fetch-based, and so is core/templates' core_output_load_template_with_dependencies
 * call, which goes through it. A transport failure rejects with an error object, never a bare string:
 * fetch's own TypeError for a dropped connection, an AbortError for a timeout or an aborted request, and
 * Error('HTTP <status>: <text>') for a non-2xx answer such as a balancer's 502 or 504. core/notification's
 * exception() renders those as a raw technical dialogue, so connectivity drops get a dismissible toast
 * instead, while application errors keep the exception dialogue.
 *
 * @module     local_dimensions/central/errors
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';
import {add as addToast} from 'local_dimensions/central/toast';
import {getString} from 'core/str';

/**
 * Whether a TypeError is fetch's own transport failure rather than a script fault.
 *
 * Fetch rejects with a TypeError whose message differs per engine: 'Failed to fetch' (Chromium),
 * 'NetworkError when attempting to fetch resource.' (Firefox) and 'Load failed' (Safari). A bare TypeError
 * from a script fault is an application error and keeps the exception dialogue.
 *
 * @param {Object} error The rejection value.
 * @return {Boolean} True when the error is a fetch transport failure.
 */
const isFetchFailure = (error) => {
    if (!(error instanceof TypeError) || typeof error.message !== 'string') {
        return false;
    }
    return ['Failed to fetch', 'NetworkError when attempting to fetch resource.', 'Load failed'].includes(error.message);
};

/**
 * Decide whether a rejected call failed because of a connectivity problem rather
 * than a genuine application error.
 *
 * navigator.onLine === false is the strongest signal. Otherwise the connectivity shapes of core/ajax's fetch
 * layer are: fetch's TypeError for a dropped connection, an AbortError for a timeout, and Error('HTTP <status>:
 * <text>') for a non-2xx answer. A falsy rejection or a bare string (older callers) counts too. Everything else
 * is an error to show: a web-service exception (it carries a Moodle errorcode), core/ajax's own
 * Error('missing response') and a script fault (TypeError and friends).
 *
 * @param {*} error The rejection value.
 * @return {Boolean} True when the failure looks like a connectivity drop.
 */
export const isNetworkError = (error) => {
    if (typeof navigator !== 'undefined' && navigator.onLine === false) {
        return true;
    }
    if (!error || typeof error === 'string') {
        return true;
    }
    if (typeof error !== 'object' || error.errorcode) {
        return false;
    }
    if (error.name === 'AbortError') {
        return true;
    }
    if (typeof error.message === 'string' && error.message.startsWith('HTTP ')) {
        return true;
    }
    return isFetchFailure(error);
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
