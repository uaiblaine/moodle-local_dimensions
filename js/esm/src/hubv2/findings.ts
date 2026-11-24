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
 * The checker's findings (idea I27): the order the panel lists them in and F8 walks, the codes present with
 * their counts, the codes checked and found nowhere, the words of each finding, and the one action of the
 * registry that fixes each kind.
 *
 * The findings come from the framework's insights (insights.ts); their words are the server's strings,
 * hubv2_check_<code> (the title) and hubv2_check_<code>_desc (a sentence whose {$a->name} placeholders the
 * finding's params fill). The codes are listed here by literal key: a code the client does not know yet is
 * listed last, titled by its code, with no sentence and no fix.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/findings
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {ActionId} from './commands';
import {fillObject} from './strings';
import type {Labels} from './strings';
import type {InsightCheck} from './types';

/** Every code the checker knows, in the order the panel lists their findings: rules, use, identity, words, look. */
export const CHECK_CODES = [
    'rule_cannot_fire',
    'rule_without_children',
    'rule_single_child',
    'unused',
    'idnumber_empty',
    'description_empty',
    'colour_contrast',
] as const;

/** One code the checker knows. */
export type CheckCode = typeof CHECK_CODES[number];

/** Each code's title string. */
const TITLES: Record<CheckCode, string> = {
    'rule_cannot_fire': 'hubv2_check_rule_cannot_fire',
    'rule_without_children': 'hubv2_check_rule_without_children',
    'rule_single_child': 'hubv2_check_rule_single_child',
    'unused': 'hubv2_check_unused',
    'idnumber_empty': 'hubv2_check_idnumber_empty',
    'description_empty': 'hubv2_check_description_empty',
    'colour_contrast': 'hubv2_check_colour_contrast',
};

/** Each code's sentence string. */
const SENTENCES: Record<CheckCode, string> = {
    'rule_cannot_fire': 'hubv2_check_rule_cannot_fire_desc',
    'rule_without_children': 'hubv2_check_rule_without_children_desc',
    'rule_single_child': 'hubv2_check_rule_single_child_desc',
    'unused': 'hubv2_check_unused_desc',
    'idnumber_empty': 'hubv2_check_idnumber_empty_desc',
    'description_empty': 'hubv2_check_description_empty_desc',
    'colour_contrast': 'hubv2_check_colour_contrast_desc',
};

/**
 * The action of the registry that fixes each kind: the rule dialogue for a rule, the competency's form for
 * what the form edits (the description, the ID number, the colours), the courses and activities dialogue for
 * an unused competency.
 */
const FIXES: Record<CheckCode, ActionId> = {
    'rule_cannot_fire': 'rule',
    'rule_without_children': 'rule',
    'rule_single_child': 'rule',
    'unused': 'links',
    'idnumber_empty': 'edit',
    'description_empty': 'edit',
    'colour_contrast': 'edit',
};

/**
 * Whether a code is one the client knows.
 *
 * @param {string} code The code.
 * @returns {boolean} Whether it is.
 */
export const isKnownCode = (code: string): code is CheckCode => (CHECK_CODES as readonly string[]).includes(code);

/**
 * A code's place in the list; an unknown code comes after every known one.
 *
 * @param {string} code The code.
 * @returns {number} Its rank.
 */
const rank = (code: string): number => (isKnownCode(code) ? CHECK_CODES.indexOf(code) : CHECK_CODES.length);

/**
 * The findings in the list's order: by code (CHECK_CODES), and within a code in the answer's order, which is
 * the competencies' id order (the framework's own, id 0, first).
 *
 * @param {Array} checks The answer's findings.
 * @returns {Array} The findings, ordered; a new array.
 */
export const orderFindings = (checks: InsightCheck[]): InsightCheck[] =>
    checks.map((check, index) => ({check, index}))
        .sort((x, y) => rank(x.check.code) - rank(y.check.code) || x.index - y.index)
        .map(({check}) => check);

/** One code present among the findings, and how many findings carry it. */
export type FindingGroup = {code: string, count: number};

/**
 * The codes present, each with its count, in the list's order.
 *
 * @param {Array} checks The findings.
 * @returns {Array} The groups.
 */
export const findingGroups = (checks: InsightCheck[]): FindingGroup[] => {
    const counts = new Map<string, number>();
    orderFindings(checks).forEach((check) => counts.set(check.code, (counts.get(check.code) ?? 0) + 1));
    return [...counts.entries()].map(([code, count]) => ({code, count}));
};

/**
 * The codes the checker looked for and found nowhere, in the list's order.
 *
 * @param {Array} checks The findings.
 * @param {boolean} usagechecked Whether unused competencies were looked for (exact counts only).
 * @returns {Array} The codes.
 */
export const absentCodes = (checks: InsightCheck[], usagechecked: boolean): CheckCode[] => {
    const present = new Set(checks.map((check) => check.code));
    return CHECK_CODES.filter((code) => !present.has(code) && (code !== 'unused' || usagechecked));
};

/**
 * The finding F8 (forward) or Shift+F8 moves to, wrapping at both ends: from none, the first or the last.
 *
 * @param {number} count How many findings there are.
 * @param {number|null} current The finding chosen, or null.
 * @param {boolean} forward Forward when true.
 * @returns {number|null} The finding's index, or null when there is none.
 */
export const stepFinding = (count: number, current: number | null, forward: boolean): number | null => {
    if (count <= 0) {
        return null;
    }
    if (current === null || current < 0 || current >= count) {
        return forward ? 0 : count - 1;
    }
    return (current + (forward ? 1 : -1) + count) % count;
};

/**
 * The action that fixes a finding, for a viewer who manages the framework's competencies; none for a reader,
 * for a finding on the framework itself and for a code the client does not know.
 *
 * @param {object} check The finding.
 * @param {boolean} canmanage Whether the viewer manages competencies in the framework's context.
 * @returns {string|null} The action.
 */
export const fixFor = (check: InsightCheck, canmanage: boolean): ActionId | null => {
    if (!canmanage || check.competencyid <= 0 || !isKnownCode(check.code)) {
        return null;
    }
    return FIXES[check.code];
};

/**
 * A code's title.
 *
 * @param {object} labels The labels.
 * @param {string} code The code.
 * @returns {string} The title, or the code itself when the client does not know it.
 */
export const codeTitle = (labels: Labels, code: string): string => (isKnownCode(code) ? labels[TITLES[code]] || code : code);

/**
 * A finding's sentence, its params in the placeholders.
 *
 * @param {object} labels The labels.
 * @param {object} check The finding.
 * @returns {string} The sentence; '' for a code the client does not know.
 */
export const findingSentence = (labels: Labels, check: InsightCheck): string => {
    if (!isKnownCode(check.code)) {
        return '';
    }
    const values: Record<string, string> = {};
    check.params.forEach((param) => {
        values[param.name] = param.value;
    });
    return fillObject(labels[SENTENCES[check.code]], values);
};

/**
 * How many findings each competency carries, 0 for the framework.
 *
 * @param {Array} checks The findings.
 * @returns {Map} Counts by competency.
 */
export const findingCounts = (checks: InsightCheck[]): Map<number, number> => {
    const counts = new Map<number, number>();
    checks.forEach((check) => counts.set(check.competencyid, (counts.get(check.competencyid) ?? 0) + 1));
    return counts;
};
