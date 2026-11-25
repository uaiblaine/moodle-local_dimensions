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
 * Tests for the checker's findings: their order, their codes and counts, the codes found nowhere, the walk of F8,
 * the action that fixes each kind, and their words.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
    CHECK_CODES, absentCodes, codeTitle, findingCounts, findingGroups, findingSentence, fixFor, orderFindings, stepFinding,
} from '../../src/hubv2/findings';
import type {Labels} from '../../src/hubv2/strings';
import type {InsightCheck} from '../../src/hubv2/types';

/**
 * A finding.
 *
 * @param {string} code The code.
 * @param {number} competencyid The competency, 0 for the framework.
 * @param {object} params Its placeholders.
 * @returns {object} The finding.
 */
const check = (code: string, competencyid: number, params: Record<string, string> = {}): InsightCheck =>
    ({code, competencyid, params: Object.entries(params).map(([name, value]) => ({name, value}))});

/** The answer's order: by competency id, codes in the server's loop order. */
const ANSWER: InsightCheck[] = [
    check('description_empty', 3),
    check('unused', 3),
    check('rule_cannot_fire', 5, {required: '6', available: '4', missing: '0'}),
    check('idnumber_empty', 5),
    check('colour_contrast', 8, {ratio: '3.9', bgcolor: '#ffcc00', textcolor: '#ffffff'}),
    check('rule_single_child', 9),
    check('future_code', 10),
];

const LABELS: Labels = {
    'hubv2_check_rule_cannot_fire': 'Rule that can never fire',
    'hubv2_check_rule_cannot_fire_desc': 'Asks {$a->required}, gets {$a->available}, {$a->missing} gone.',
    'hubv2_check_colour_contrast': 'Colours hard to read',
    'hubv2_check_colour_contrast_desc': '{$a->textcolor} on {$a->bgcolor}: {$a->ratio}:1.',
    'hubv2_check_unused': 'Not used',
    'hubv2_check_unused_desc': 'Nothing uses it.',
};

describe('the findings\' order', () => {
    it('groups by code in the list\'s order, keeps the answer\'s order within a code, and puts unknown codes last', () => {
        expect(orderFindings(ANSWER).map((item) => `${item.code}:${item.competencyid}`)).toEqual([
            'rule_cannot_fire:5', 'rule_single_child:9', 'unused:3', 'idnumber_empty:5', 'description_empty:3',
            'colour_contrast:8', 'future_code:10',
        ]);
        // The answer itself is left as it came.
        expect(ANSWER[0].code).toBe('description_empty');
    });

    it('lists the codes present with their counts, in the same order', () => {
        const findings = [...ANSWER, check('unused', 11), check('unused', 12)];
        expect(findingGroups(findings)).toEqual([
            {code: 'rule_cannot_fire', count: 1}, {code: 'rule_single_child', count: 1}, {code: 'unused', count: 3},
            {code: 'idnumber_empty', count: 1}, {code: 'description_empty', count: 1}, {code: 'colour_contrast', count: 1},
            {code: 'future_code', count: 1},
        ]);
    });

    it('names the codes looked for and found nowhere; unused only when usage was counted exactly', () => {
        expect(absentCodes(ANSWER, true)).toEqual(['rule_without_children']);
        expect(absentCodes([], true)).toEqual([...CHECK_CODES]);
        expect(absentCodes([], false)).not.toContain('unused');
    });

    it('counts the findings of each item, the framework\'s under 0', () => {
        expect([...findingCounts([...ANSWER, check('idnumber_empty', 0)]).entries()])
            .toEqual([[3, 2], [5, 2], [8, 1], [9, 1], [10, 1], [0, 1]]);
    });
});

describe('the walk of F8', () => {
    it('goes forward and back, wrapping at both ends', () => {
        expect(stepFinding(3, 0, true)).toBe(1);
        expect(stepFinding(3, 2, true)).toBe(0);
        expect(stepFinding(3, 0, false)).toBe(2);
        expect(stepFinding(3, 1, false)).toBe(0);
    });

    it('starts at the first, or the last going back, and goes nowhere without findings', () => {
        expect(stepFinding(4, null, true)).toBe(0);
        expect(stepFinding(4, null, false)).toBe(3);
        expect(stepFinding(2, 7, true)).toBe(0);
        expect(stepFinding(0, null, true)).toBeNull();
        expect(stepFinding(0, 0, false)).toBeNull();
        expect(stepFinding(1, 0, true)).toBe(0);
    });
});

describe('the fix of each kind', () => {
    it('is the rule dialogue, the form or the links dialogue, for a manager', () => {
        const fixes = Object.fromEntries(orderFindings(ANSWER).map((item) => [item.code, fixFor(item, true)]));
        expect(fixes).toEqual({
            'rule_cannot_fire': 'rule', 'rule_single_child': 'rule', 'unused': 'links', 'idnumber_empty': 'edit',
            'description_empty': 'edit', 'colour_contrast': 'edit', 'future_code': null,
        });
        expect(fixFor(check('rule_without_children', 4), true)).toBe('rule');
    });

    it('is none for a reader, whatever the kind', () => {
        ANSWER.forEach((item) => expect(fixFor(item, false)).toBeNull());
    });

    it('is none for a finding on the framework itself', () => {
        expect(fixFor(check('idnumber_empty', 0), true)).toBeNull();
    });
});

describe('the words of a finding', () => {
    it('are the code\'s title and its sentence with the params in place', () => {
        expect(codeTitle(LABELS, 'rule_cannot_fire')).toBe('Rule that can never fire');
        expect(findingSentence(LABELS, ANSWER[2])).toBe('Asks 6, gets 4, 0 gone.');
        expect(findingSentence(LABELS, ANSWER[4])).toBe('#ffffff on #ffcc00: 3.9:1.');
        expect(findingSentence(LABELS, ANSWER[1])).toBe('Nothing uses it.');
    });

    it('are the code itself, without a sentence, for a code the client does not know', () => {
        expect(codeTitle(LABELS, 'future_code')).toBe('future_code');
        expect(findingSentence(LABELS, ANSWER[6])).toBe('');
        // A known code whose string has not reached the page yet falls back to its code too.
        expect(codeTitle({}, 'idnumber_empty')).toBe('idnumber_empty');
    });
});
