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
 * The framework's insights as the workspace keeps them: one answer of local_dimensions_get_framework_insights
 * per framework, indexed by competency for the lenses (lenses.ts) and the checker (findings.ts).
 *
 * The workspace asks once shortly after it mounts (so the checker's count can show), whenever a lens or the
 * checker is first asked for, after every action of the registry that changes what the answer says
 * (commands.ts `changes`), and on "Check again". Only the newest request's answer is kept (latest()): an
 * answer that arrives after a later request was made is dropped, whatever order the server answers in.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/insights
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {Insights, InsightCheck, InsightRule, InsightUsage} from './types';

/** The lenses, in the switch's order (hub_map::LENSES). */
export const LENSES = ['none', 'usage', 'rules', 'relations'] as const;

/** One lens; 'none' draws the tree as it is. */
export type Lens = typeof LENSES[number];

/**
 * Whether a value is a lens.
 *
 * @param {*} value The value.
 * @returns {boolean} Whether it is one.
 */
export const isLens = (value: unknown): value is Lens => (LENSES as readonly unknown[]).includes(value);

/** What the usage lens counts, in the measure switch's order. */
export const MEASURES = ['courses', 'modules', 'templates', 'plans'] as const;

/** One measure of the usage lens. */
export type Measure = typeof MEASURES[number];

/** The insights, indexed by competency. */
export type InsightsIndex = {
    answer: Insights,
    usage: Map<number, InsightUsage>,
    rules: Map<number, InsightRule>,
    // Each child a points rule lists, with its parent and what it gives.
    ruled: Map<number, {parent: number, points: number, required: boolean}>,
    relations: Map<number, number>,
    // The findings of each competency, 0 for the framework, in the answer's order.
    checks: Map<number, InsightCheck[]>,
};

/**
 * Index an answer by competency.
 *
 * @param {object} answer The service's answer.
 * @returns {object} The index.
 */
export const indexInsights = (answer: Insights): InsightsIndex => {
    const usage = new Map<number, InsightUsage>();
    answer.usage.forEach((row) => usage.set(row.competencyid, row));
    const rules = new Map<number, InsightRule>();
    const ruled = new Map<number, {parent: number, points: number, required: boolean}>();
    answer.rules.forEach((rule) => {
        rules.set(rule.competencyid, rule);
        rule.children.forEach((child) => ruled.set(child.id, {parent: rule.competencyid, points: child.points,
            required: child.required}));
    });
    const relations = new Map<number, number>();
    answer.relations.forEach((row) => relations.set(row.competencyid, row.count));
    const checks = new Map<number, InsightCheck[]>();
    answer.checks.forEach((check) => {
        checks.set(check.competencyid, [...(checks.get(check.competencyid) ?? []), check]);
    });
    return {answer, usage, rules, ruled, relations, checks};
};

/**
 * A competency's use by one measure: 0 when it is not listed (the answer lists only competencies with some use).
 *
 * @param {object} index The insights.
 * @param {number} id The competency.
 * @param {string} measure The measure.
 * @returns {number} The count (0 or 1 under presence).
 */
export const usageOf = (index: InsightsIndex, id: number, measure: Measure): number => index.usage.get(id)?.[measure] ?? 0;

/** The newest-wins guard of one kind of request. */
export type Latest = {
    // Start a request: its ticket.
    begin: () => number,
    // Whether a ticket is still the newest request's.
    current: (ticket: number) => boolean,
    // Drop every request made so far (a framework left behind).
    drop: () => void,
};

/**
 * A guard that keeps only the newest request's answer: each request takes a ticket, and an answer is used only
 * while its ticket is the last one given.
 *
 * @returns {object} The guard.
 */
export const latest = (): Latest => {
    let last = 0;
    return {
        /**
         * Start a request.
         *
         * @returns {number} Its ticket.
         */
        begin: () => ++last,
        /**
         * Whether a ticket is the newest request's.
         *
         * @param {number} ticket The ticket.
         * @returns {boolean} Whether it is.
         */
        current: (ticket) => ticket === last,
        /**
         * Drop every request made so far.
         *
         * @returns {void}
         */
        drop: () => {
            last++;
        },
    };
};

/** The insights as the workspace's store holds them. */
export type InsightsState = {
    // Idle until first asked; loading while a request runs; failed after one did.
    status: 'idle' | 'loading' | 'ready' | 'failed',
    // The last answer received, kept while a newer one loads.
    index: InsightsIndex | null,
};

/** The store before the first request. */
export const IDLE: InsightsState = {status: 'idle', index: null};

/**
 * A lens from a value the page handed over, none for anything else.
 *
 * @param {*} value The value.
 * @returns {string} The lens.
 */
export const lensOf = (value: unknown): Lens => (isLens(value) ? value : 'none');

/** What the bar and the summary read of the insights, whether they are read or not. */
export type InsightFacts = {
    // The findings' count, null before the insights are read.
    findings: number | null,
    // The framework's numbers, null before.
    summary: Insights['summary'] | null,
    // The findings, none before.
    checks: Insights['checks'],
};

/**
 * What the bar and the summary read of the insights.
 *
 * @param {object|null} index The insights, null before they are read.
 * @returns {object} The facts.
 */
export const insightFacts = (index: InsightsIndex | null): InsightFacts => (index
    ? {findings: index.answer.checks.length, summary: index.answer.summary, checks: index.answer.checks}
    : {findings: null, summary: null, checks: []});
