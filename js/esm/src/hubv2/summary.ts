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
 * The framework's summary, on request: what the client can say truthfully about the tree it holds.
 *
 * The total comes from the server (core's count of the framework's competencies). Everything else is
 * counted over the competencies the workspace has loaded, and is said to be so while that is not the
 * whole framework: the tree is lazy, and a count over a part read as the whole would be false. No
 * relation is counted: the tree's nodes do not carry them (only the selected competency's are read). Once the
 * framework's insights are read (insights.ts), the summary says the server's numbers instead.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/summary
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {Labels} from './strings';
import {ancestors} from './tree';
import type {Tree} from './tree';

/** What the summary counts. */
export type SummaryFacts = {
    // The framework's competencies, from the server.
    total: number,
    // The competencies the workspace has loaded.
    loaded: number,
    // Whether the loaded ones are all of them.
    complete: boolean,
    // The deepest level among the loaded competencies, 1 for a top-level one; 0 with none loaded.
    levels: number,
    // How many loaded competencies have a rule.
    rules: number,
    // How many branches are open in the panes.
    opened: number,
};

/**
 * Count what the loaded tree says.
 *
 * @param {object} tree The tree.
 * @param {number} total The framework's competency count, from the server.
 * @returns {object} The facts.
 */
export const summaryFacts = (tree: Tree, total: number): SummaryFacts => {
    const ids = Object.keys(tree.nodes).map(Number);
    const levels = ids.reduce((deepest, id) => Math.max(deepest, ancestors(tree, id).length + 1), 0);
    const rules = ids.filter((id) => !!tree.nodes[id].ruletype).length;
    return {
        total,
        loaded: ids.length,
        complete: ids.length >= total,
        levels,
        rules,
        opened: tree.expanded.filter((id) => tree.nodes[id] !== undefined).length,
    };
};

/** One line of the summary: its label and its value. */
export type SummaryLine = {label: string, value: string};

/** The framework's own numbers, from its insights (get_framework_insights' summary). */
export type ServerSummary = {competencies: number, maxdepth: number, withrule: number, relations: number, findings: number};

/**
 * The summary's lines: the total; then, over a partly loaded tree, how many are loaded and each count
 * labelled as the loaded part's. Once the framework's insights are read, their numbers replace the loaded
 * part's: the levels, the competencies with a rule, the related pairs and the findings, all of the framework.
 *
 * @param {object} labels The labels.
 * @param {object} facts The facts.
 * @param {object|null} server The insights' numbers, or null before they are read.
 * @returns {Array} The lines.
 */
export const summaryLines = (labels: Labels, facts: SummaryFacts, server: ServerSummary | null = null): SummaryLine[] => {
    const lines: SummaryLine[] = [{label: labels.hubv2_summary_total, value: String(facts.total)}];
    if (server) {
        lines.push(
            {label: labels.hubv2_summary_levels, value: String(server.maxdepth)},
            {label: labels.hubv2_summary_rules, value: String(server.withrule)},
            {label: labels.hubv2_summary_relations, value: String(server.relations)},
            {label: labels.hubv2_summary_findings, value: String(server.findings)},
            {label: labels.hubv2_summary_opened, value: String(facts.opened)},
        );
        return lines;
    }
    if (!facts.complete) {
        lines.push({label: labels.hubv2_summary_loaded, value: String(facts.loaded)});
    }
    lines.push(
        {
            label: facts.complete ? labels.hubv2_summary_levels : labels.hubv2_summary_levels_loaded,
            value: String(facts.levels),
        },
        {
            label: facts.complete ? labels.hubv2_summary_rules : labels.hubv2_summary_rules_loaded,
            value: String(facts.rules),
        },
        {label: labels.hubv2_summary_opened, value: String(facts.opened)},
    );
    return lines;
};
