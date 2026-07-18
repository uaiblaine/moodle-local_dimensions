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
 * A transport failure (offline, dropped connection, server unreachable, timeout)
 * rejects a web-service or template-dependency call (e.g. core/ajax or
 * core_output_load_template_with_dependencies) with jQuery's bare errorThrown —
 * a value with no Moodle `errorcode`. Passing that to core/notification's
 * exception() renders the YUI exception modal with an "undefined" title and
 * body, which is both ugly and useless. This module tells connectivity drops
 * apart from genuine application errors: the former get a friendly, dismissible
 * toast; the latter keep the technical exception path (which carries a real
 * message).
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
 * than a genuine application error returned by Moodle.
 *
 * navigator.onLine === false is the strongest signal. Otherwise: a real
 * web-service exception always carries a Moodle `errorcode`; core/ajax rejects
 * transport failures with jQuery's bare errorThrown (an empty string or a plain
 * Error), which has none. Treating "no errorcode" as a network failure keeps
 * capability/param/coding errors on the technical path while connectivity drops
 * get the friendly notice.
 *
 * @param {*} error The rejection value.
 * @return {Boolean} True when the failure looks like a connectivity drop.
 */
export const isNetworkError = (error) => {
    if (typeof navigator !== 'undefined' && navigator.onLine === false) {
        return true;
    }
    return !(error && typeof error === 'object' && error.errorcode);
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
        // Keep the hard-coded fallback if the localised string cannot be fetched.
    }
    await addToast(message, {type: 'warning'});
};
