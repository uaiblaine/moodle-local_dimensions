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
 * Standalone scale-proficiency config modal for a framework. Opened from the create/edit form's
 * "Configure scale" button: fetches the scale's values (core get_scale_values WS), renders a default
 * radio + proficient checkbox per value in a native core/modal_save_cancel (zero YUI), and resolves the
 * core scaleconfiguration JSON ([{scaleid}, {id, scaledefault, proficient}, ...]) on save.
 *
 * @module     local_dimensions/central/framework_scaleconfig
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import ModalSaveCancel from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';
import Notification from 'core/notification';
import Templates from 'core/templates';
import {getString} from 'core/str';

/**
 * Parse an existing scaleconfiguration JSON into its per-value configs (dropping the leading {scaleid}).
 *
 * @param {String} json The scaleconfiguration JSON, or empty.
 * @return {Array}
 */
const parseExisting = (json) => {
    try {
        const parsed = JSON.parse(json || 'null');
        return Array.isArray(parsed) ? parsed.slice(1) : [];
    } catch (e) {
        return [];
    }
};

/**
 * Serialize the modal's rows into the core scaleconfiguration JSON.
 *
 * @param {HTMLElement} root The modal root.
 * @param {Number} scaleid The scale id.
 * @return {String}
 */
const serialize = (root, scaleid) => {
    const config = [{scaleid: Number(scaleid)}];
    root.querySelectorAll('[data-value]').forEach((row) => {
        const def = row.querySelector('[data-role="default"]');
        const prof = row.querySelector('[data-role="proficient"]');
        config.push({
            id: Number(row.dataset.value),
            scaledefault: def && def.checked ? 1 : 0,
            proficient: prof && prof.checked ? 1 : 0,
        });
    });
    return JSON.stringify(config);
};

/**
 * Whether the modal's rows have at least one default and one proficient value selected.
 *
 * @param {HTMLElement} root The modal root.
 * @return {Boolean}
 */
const isComplete = (root) => {
    let hasdefault = false;
    let hasproficient = false;
    root.querySelectorAll('[data-value]').forEach((row) => {
        const def = row.querySelector('[data-role="default"]');
        const prof = row.querySelector('[data-role="proficient"]');
        if (def && def.checked) {
            hasdefault = true;
        }
        if (prof && prof.checked) {
            hasproficient = true;
        }
    });
    return hasdefault && hasproficient;
};

/**
 * Build the template rows for a scale's values, applying any existing selections.
 *
 * @param {Array} values The scale values ({id, name}) from get_scale_values.
 * @param {Array} existing The existing per-value configs ({id, scaledefault, proficient}).
 * @return {Array}
 */
const buildRows = (values, existing) => {
    const defaults = {};
    const proficients = {};
    existing.forEach((value) => {
        if (value.scaledefault) {
            defaults[value.id] = true;
        }
        if (value.proficient) {
            proficients[value.id] = true;
        }
    });
    return values.map((value) => ({
        id: value.id,
        name: value.name,
        isdefault: Boolean(defaults[value.id]),
        isproficient: Boolean(proficients[value.id]),
    }));
};

/**
 * Open the scale-config modal for a scale, pre-selecting from an existing config.
 *
 * @param {Number} scaleid The scale id.
 * @param {String} existingJson The current scaleconfiguration JSON, or empty.
 * @return {Promise<String|null>} The new scaleconfiguration JSON, or null if cancelled.
 */
export const open = async(scaleid, existingJson) => {
    if (!scaleid) {
        return null;
    }
    const values = await Ajax.call([{
        methodname: 'core_competency_get_scale_values',
        args: {scaleid: scaleid},
    }])[0];
    const rows = buildRows(values, parseExisting(existingJson));
    const [title, incomplete] = await Promise.all([
        getString('central_frameworks_configurescale', 'local_dimensions'),
        getString('central_frameworks_scaleincomplete', 'local_dimensions'),
    ]);
    const {html} = await Templates.renderForPromise('local_dimensions/central/framework_scaleconfig', {rows: rows});
    const modal = await ModalSaveCancel.create({title, body: html});
    modal.setRemoveOnClose(true);
    const root = modal.getRoot()[0];

    return new Promise((resolve) => {
        modal.getRoot().on(ModalEvents.save, (event) => {
            if (!isComplete(root)) {
                event.preventDefault();
                Notification.alert('', incomplete);
                return;
            }
            resolve(serialize(root, scaleid));
        });
        modal.getRoot().on(ModalEvents.hidden, () => resolve(null));
        modal.show();
    });
};
