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
 * The one door from the canvas's ES modules to Moodle's AMD modules.
 *
 * A component cannot import an AMD module: the import map Moodle serves resolves `@moodle/lms/`,
 * react and react-dom, and nothing of RequireJS, so `import Ajax from 'core/ajax'` in a .tsx fails
 * to resolve in the browser and the canvas never mounts. AMD is reached through core's loader
 * (`@moodle/lms/core/amd`) instead, and only from this file. Every module the canvas may load is a
 * key of `Modules`, so any other name is a type error under the tsc gate; the hub's dialogues are
 * reached here and never copied. Strings and the pending counter are core's ES modules
 * (`@moodle/lms/core/stringUtils`, `@moodle/lms/core/pending`) and are imported, not bridged.
 *
 * @module     local_dimensions/hubv2/amd
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {requireAsync} from '@moodle/lms/core/amd';

/** One web-service call for core/ajax. */
export type AjaxRequest = {methodname: string, args: Record<string, unknown>};

/** The part of core/ajax the canvas uses. */
export type AjaxModule = {call: (requests: AjaxRequest[]) => Promise<unknown>[]};

/** The part of core/notification the canvas uses. */
export type NotificationModule = {
    deleteCancelPromise: (title: string, question: string, deleteLabel?: string) => Promise<unknown>,
    saveCancelPromise: (title: string, question: string, saveLabel: string) => Promise<unknown>,
    alert: (title: string | null, message: string) => Promise<unknown>,
};

/** One modal form of core_form/modalform. */
export type ModalFormInstance = {
    events: {FORM_SUBMITTED: string},
    addEventListener: (type: string, listener: (event: Event) => void) => void,
    show: () => Promise<unknown>,
};

/** Core_form/modalform: its module is the class. */
export type ModalFormModule = new (config: {
    formClass: string,
    args: Record<string, unknown>,
    modalConfig: {title: string},
}) => ModalFormInstance;

/** A competency's rule as the rule dialogue reads and returns it. */
export type RuleConfig = {ruletype: string, ruleoutcome: number, ruleconfig: string};

/** Local_dimensions/central/rule_config. */
export type RuleConfigModule = {
    show: (
        competency: {id: number, ruletype: string, ruleoutcome: number, ruleconfig: string},
        children: {id: number, shortname: string}[],
        rulesModules: {type: string, name: string}[],
    ) => Promise<RuleConfig | null>,
};

/** Local_dimensions/central/competency_links. */
export type LinksModule = {
    open: (opts: {
        competencyid: number,
        competencyname: string,
        courseoutcomes: {value: number, label: string}[],
        moduleoutcomes: {value: number, label: string}[],
        onClose: (count: number | null) => void,
    }) => Promise<void>,
};

/** Local_dimensions/central/related_competencies. */
export type RelatedModule = {
    open: (opts: {competencyid: number, competencyname: string, frameworkid: number}) => Promise<void>,
};

/** Local_dimensions/central/errors. */
export type ErrorsModule = {notifyError: (error: unknown) => Promise<void>};

/** The part of jQuery the canvas uses: core/modal reports a hidden dialogue as a jQuery event. */
export type JQueryModule = (target: Document) => {
    on: (events: string, handler: () => void) => unknown,
    off: (events: string) => unknown,
};

/** Every AMD module the canvas may load, by its RequireJS name. */
export interface Modules {
    'core/ajax': AjaxModule,
    'core/notification': NotificationModule,
    'jquery': JQueryModule,
    'core_form/modalform': ModalFormModule,
    'local_dimensions/central/rule_config': RuleConfigModule,
    'local_dimensions/central/competency_links': LinksModule,
    'local_dimensions/central/related_competencies': RelatedModule,
    'local_dimensions/central/errors': ErrorsModule,
}

/** The name of an AMD module the canvas may load. */
export type ModuleName = keyof Modules;

/**
 * Load one AMD module, once core/first has loaded.
 *
 * With the JavaScript cache on, every AMD module the page loads arrives in one bundle, reached as
 * core/first; asked for by name before RequireJS knows the module is inside it, a module would be
 * fetched a second time on its own. The footer has already asked for core/first, so waiting for it
 * costs no request.
 *
 * @param {string} name The module's RequireJS name, one of `Modules`.
 * @returns {Promise} Resolves with the module; rejects with RequireJS's error when it cannot load.
 */
export const load = async<K extends ModuleName>(name: K): Promise<Modules[K]> => {
    await requireAsync('core/first');
    return requireAsync<Modules[K]>(name);
};

/**
 * Report a failure through the hub's own error router (a toast when offline, core's dialogue
 * otherwise), falling back to the console when even that cannot load.
 *
 * @param {*} error The rejection value.
 * @returns {Promise} Resolves once reported.
 */
export const notifyError = async(error: unknown): Promise<void> => {
    try {
        const errors = await load('local_dimensions/central/errors');
        await errors.notifyError(error);
    } catch {
        window.console.error(error);
    }
};
