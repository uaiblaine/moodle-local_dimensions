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
 * The only module of the canvas that talks to the server: the hub's own web services and core's
 * competency services, called with the arguments the hub's list view passes
 * (amd/src/central/structure.js).
 *
 * @module     local_dimensions/hubv2/repository
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {load} from './amd';
import type {AjaxRequest, RuleConfig} from './amd';
import type {DeleteCheck, NodePage, PathItem, StructureNode, Usage} from './types';

/** A child competency as the rule dialogue lists it. */
type Child = {id: number, shortname: string};

/** What get_structure_node answers. */
export type NodeAnswer = {found: boolean, node?: StructureNode, pathids?: number[]};

/**
 * Call several web services in one request.
 *
 * @param {Array} requests The calls.
 * @returns {Promise} Resolves with the answers in order.
 */
const callMany = (requests: AjaxRequest[]): Promise<unknown[]> =>
    load('core/ajax').then((ajax) => Promise.all(ajax.call(requests)));

/**
 * Call one web service.
 *
 * @param {string} methodname The function.
 * @param {object} args Its arguments.
 * @returns {Promise} Resolves with the answer.
 */
const call = <T>(methodname: string, args: Record<string, unknown>): Promise<T> =>
    callMany([{methodname, args}]).then(([answer]) => answer as T);

/**
 * One page of a parent's direct children.
 *
 * @param {number} frameworkid The framework.
 * @param {number} parentid The parent (0 for the roots).
 * @param {number} limitfrom How many are loaded already.
 * @param {number} limitnum The page size.
 * @returns {Promise} Resolves with the page.
 */
export const browse = (frameworkid: number, parentid: number, limitfrom: number, limitnum: number): Promise<NodePage> =>
    call<NodePage>('local_dimensions_browse_structure', {frameworkid, parentid, limitfrom, limitnum});

/**
 * Every direct child of a competency, all pages, for the rule dialogue.
 *
 * @param {number} frameworkid The framework.
 * @param {number} parentid The competency.
 * @param {number} limitnum The page size.
 * @returns {Promise} Resolves with {id, shortname} per child.
 */
export const allChildren = async(frameworkid: number, parentid: number, limitnum: number): Promise<Child[]> => {
    const children: Child[] = [];
    for (;;) {
        const page = await browse(frameworkid, parentid, children.length, limitnum);
        page.items.forEach((item) => children.push({id: item.id, shortname: item.shortname}));
        if (page.items.length === 0 || children.length >= page.total) {
            return children;
        }
    }
};

/**
 * One competency, fresh, with its ancestor chain.
 *
 * @param {number} competencyid The competency.
 * @returns {Promise} Resolves with the answer.
 */
export const getNode = (competencyid: number): Promise<NodeAnswer> =>
    call<NodeAnswer>('local_dimensions_get_structure_node', {competencyid});

/**
 * Competencies of the framework matching a text, by name or ID number.
 *
 * @param {number} frameworkid The framework.
 * @param {string} query The text.
 * @returns {Promise} Resolves with the hits and their total.
 */
export const search = (frameworkid: number, query: string): Promise<{items: PathItem[], total: number}> =>
    call('local_dimensions_search_structure', {frameworkid, query, limitfrom: 0, limitnum: 25});

/**
 * What the panel shows beyond the node: where the competency is used and what it is related to,
 * in one request.
 *
 * @param {number} competencyid The competency.
 * @returns {Promise} Resolves with [usage, related].
 */
export const selectionData = (competencyid: number): Promise<[Usage, PathItem[]]> =>
    callMany([
        {methodname: 'local_dimensions_competency_usage', args: {competencyid}},
        {methodname: 'local_dimensions_list_related_competencies', args: {competencyid}},
    ]).then(([usage, related]) => [usage as Usage, (related as {items: PathItem[]}).items]);

/**
 * What deleting a competency would destroy, and whether core would refuse it, before anything is attempted.
 *
 * @param {number} competencyid The competency.
 * @returns {Promise} Resolves with the check.
 */
export const deleteCheck = (competencyid: number): Promise<DeleteCheck> =>
    call<DeleteCheck>('local_dimensions_competency_delete_check', {competencyid});

/**
 * Delete a competency. Core answers false when it cannot be deleted (it is in use).
 *
 * @param {number} id The competency.
 * @returns {Promise} Resolves with core's answer.
 */
export const deleteCompetency = (id: number): Promise<boolean> =>
    call<boolean>('core_competency_delete_competency', {id});

/**
 * Move a competency one place among its siblings.
 *
 * @param {number} id The competency.
 * @param {boolean} up Up when true, down otherwise.
 * @returns {Promise} Resolves once moved.
 */
export const moveOne = (id: number, up: boolean): Promise<unknown> =>
    call(up ? 'core_competency_move_up_competency' : 'core_competency_move_down_competency', {id});

/**
 * Give a competency a new parent; core appends it after the new parent's children.
 *
 * @param {number} competencyid The competency.
 * @param {number} parentid The new parent, 0 for the top level.
 * @returns {Promise} Resolves once moved.
 */
export const setParent = (competencyid: number, parentid: number): Promise<unknown> =>
    call('core_competency_set_parent_competency', {competencyid, parentid});

/**
 * Store a competency's rule. Only the id and the rule fields are sent: core leaves every omitted
 * field alone, where echoing a read record back would store its display formatting.
 *
 * @param {number} id The competency.
 * @param {object} config The rule.
 * @returns {Promise} Resolves once stored.
 */
export const updateRule = (id: number, config: RuleConfig): Promise<unknown> =>
    call('core_competency_update_competency', {
        competency: {id, ruletype: config.ruletype, ruleoutcome: config.ruleoutcome, ruleconfig: config.ruleconfig},
    });

/**
 * How many competencies the framework holds, after an addition or a deletion (core deletes a
 * competency's whole subtree, which the canvas may not have loaded).
 *
 * @param {number} frameworkid The framework.
 * @returns {Promise} Resolves with the count.
 */
export const countCompetencies = (frameworkid: number): Promise<number> =>
    call<number>('core_competency_count_competencies', {
        filters: [{column: 'competencyframeworkid', value: String(frameworkid)}],
    });
