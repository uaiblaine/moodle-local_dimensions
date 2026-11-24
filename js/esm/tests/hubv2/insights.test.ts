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
 * Tests for the insights' store: the index by competency, and the newest-wins guard that drops a stale answer.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {LENSES, indexInsights, isLens, latest, usageOf} from '../../src/hubv2/insights';
import type {Insights} from '../../src/hubv2/types';

const ANSWER: Insights = {
    frameworkid: 4,
    exact: true,
    usagelevel: 'exact',
    usage: [{competencyid: 7, courses: 2, modules: 1, templates: 0, plans: 3}],
    rules: [{competencyid: 1, type: 'points', label: 'Points', outcome: 'none', required: 3, available: 3, requiredchildren: 1,
        children: [{id: 7, points: 2, required: true}, {id: 8, points: 1, required: false}], canfire: true}],
    relations: [{competencyid: 7, count: 1}, {competencyid: 8, count: 1}],
    pairs: [{a: 7, b: 8}],
    checks: [{code: 'idnumber_empty', competencyid: 7, params: []}, {code: 'unused', competencyid: 7, params: []},
        {code: 'idnumber_empty', competencyid: 0, params: []}],
    summary: {competencies: 3, maxdepth: 2, withrule: 1, relations: 1, findings: 3},
};

describe('the index', () => {
    it('finds each competency\'s use, rule, place in a parent\'s rule, relations and findings', () => {
        const index = indexInsights(ANSWER);
        expect(usageOf(index, 7, 'plans')).toBe(3);
        expect(usageOf(index, 8, 'courses')).toBe(0);
        expect(index.rules.get(1)?.required).toBe(3);
        expect(index.ruled.get(7)).toEqual({parent: 1, points: 2, required: true});
        expect(index.ruled.get(1)).toBeUndefined();
        expect(index.relations.get(8)).toBe(1);
        expect(index.checks.get(7)?.map((check) => check.code)).toEqual(['idnumber_empty', 'unused']);
        expect(index.checks.get(0)?.length).toBe(1);
    });
});

describe('the lenses', () => {
    it('are the four the server allows, and nothing else is one', () => {
        expect([...LENSES]).toEqual(['none', 'usage', 'rules', 'relations']);
        expect(['none', 'usage', 'rules', 'relations'].every(isLens)).toBe(true);
        ['', 'Usage', 'checker', null, 1].forEach((value) => expect(isLens(value)).toBe(false));
    });
});

describe('the newest-wins guard', () => {
    it('keeps only the newest request\'s answer, whatever order the answers come in', () => {
        const guard = latest();
        const first = guard.begin();
        const second = guard.begin();
        // The second answer arrives first and is kept; the first, arriving later, is stale and dropped.
        expect(guard.current(second)).toBe(true);
        expect(guard.current(first)).toBe(false);
    });

    it('drops every request made so far once dropped', () => {
        const guard = latest();
        const ticket = guard.begin();
        expect(guard.current(ticket)).toBe(true);
        guard.drop();
        expect(guard.current(ticket)).toBe(false);
        expect(guard.current(guard.begin())).toBe(true);
    });

    it('serves a store that ignores a stale answer: the shape the workspace follows', async() => {
        const guard = latest();
        const kept: number[] = [];
        const resolvers: ((value: number) => void)[] = [];
        const ask = (): Promise<void> => {
            const ticket = guard.begin();
            return new Promise<number>((resolve) => resolvers.push(resolve)).then((value) => {
                if (guard.current(ticket)) {
                    kept.push(value);
                }
                return undefined;
            });
        };
        const slow = ask();
        const fast = ask();
        resolvers[1](2);
        resolvers[0](1);
        await Promise.all([slow, fast]);
        expect(kept).toEqual([2]);
    });
});
