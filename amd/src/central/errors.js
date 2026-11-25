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
 * call, which goes through it. A transport failure rejects with fetch's own TypeError for a dropped connection,
 * an AbortError for a timeout or an aborted request, and Error('HTTP <status>: <text>') for a non-2xx answer
 * such as a balancer's 502 or 504. core/notification's exception() renders those as a raw technical dialogue, so
 * connectivity drops get a dismissible toast instead, while application errors keep the exception dialogue.
 *
 * A 4xx answer is the server refusing the request, not a lost connection: it keeps the dialogue, which names the
 * status. Core's REST helper (core/fetch) rejects a non-2xx answer with the bare status text, where Moodle writes
 * its own message for a refusal, and, over HTTP/2, with an empty string whatever the status, so its refusals
 * cannot always be told from a drop: the hub's own calls go through core/ajax (hub_javascript_guards_test holds
 * the preference write to it).
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
 * @type {String[]} The status texts of the answers that say the request was lost on the way: a timeout, a 5xx of
 * a balancer or a gateway, in the spellings Apache and nginx send. Core's REST helper rejects with the status
 * text alone, and Moodle writes its own message there when it refuses a request, so any other text is a refusal.
 */
const TRANSPORT_STATUS_TEXTS = [
    'Request Timeout', 'Request Time-out', 'Internal Server Error', 'Bad Gateway', 'Service Unavailable',
    'Service Temporarily Unavailable', 'Gateway Timeout', 'Gateway Time-out',
];

/**
 * Whether a non-2xx status says the request was refused rather than lost on the way.
 *
 * A 4xx is the server's (or a proxy's) answer to this request; 408, a request timeout, is the exception. A 5xx,
 * and a status that cannot be read, stay connectivity failures: a balancer's 502 or 504 is the common case.
 *
 * @param {Number} status The HTTP status.
 * @return {Boolean} True for a 4xx other than 408.
 */
const isRefusalStatus = (status) => {
    return status >= 400 && status < 500 && status !== 408;
};

/**
 * Decide whether a rejected call failed because of a connectivity problem rather
 * than a genuine application error.
 *
 * navigator.onLine === false is the strongest signal. Otherwise the connectivity shapes of core/ajax's fetch
 * layer are: fetch's TypeError for a dropped connection, an AbortError for a timeout, and Error('HTTP <status>:
 * <text>') for a non-2xx answer that is not a refusal ({@see isRefusalStatus}). A falsy rejection counts too, and
 * so does a bare string that is the status text of a timeout or a 5xx. Everything else is an error to show: a
 * refused request (a 4xx, or any other bare string, which is what the server said), a web-service exception (it
 * carries a Moodle errorcode), core/ajax's own Error('missing response') and a script fault (TypeError and
 * friends).
 *
 * @param {*} error The rejection value.
 * @return {Boolean} True when the failure looks like a connectivity drop.
 */
export const isNetworkError = (error) => {
    if (typeof navigator !== 'undefined' && navigator.onLine === false) {
        return true;
    }
    if (!error) {
        return true;
    }
    if (typeof error === 'string') {
        return TRANSPORT_STATUS_TEXTS.includes(error);
    }
    if (typeof error !== 'object' || error.errorcode) {
        return false;
    }
    if (error.name === 'AbortError') {
        return true;
    }
    if (typeof error.message === 'string' && error.message.startsWith('HTTP ')) {
        return !isRefusalStatus(parseInt(error.message.slice('HTTP '.length), 10));
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
        // The dialogue reads the message of an error object; what the server said arrives as a bare string.
        Notification.exception(typeof error === 'string' ? new Error(error) : error);
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
