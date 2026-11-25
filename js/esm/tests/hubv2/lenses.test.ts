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
 * Tests for the lenses: the usage buckets and legend, the rules model, the relations on the map, each lens's mark
 * and the words it adds to a node's name and a row's description.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {buildGraph} from '../../src/hubv2/graph';
import {indexInsights, MEASURES} from '../../src/hubv2/insights';
import type {Measure} from '../../src/hubv2/insights';
import {nodeName} from '../../src/hubv2/keynav';
import {
    childLine, countsChild, drawnSet, itemMark, lensMarks, markWords, pairsOnMap, relationsLegend, ruleFacts, ruleLine,
    rulesLegend, unusedIds, usageBucket, usageLegend, usageNotice,
} from '../../src/hubv2/lenses';
import type {MarkContext} from '../../src/hubv2/lenses';
import type {Labels} from '../../src/hubv2/strings';
import {ROOT, createTree, insertChildren, setExpanded} from '../../src/hubv2/tree';
import type {Insights, InsightRule, StructureNode} from '../../src/hubv2/types';

const LABELS: Labels = {
    'hubv2_a11y_level': 'level {$a}',
    'hubv2_a11y_position': '{$a->position} of {$a->total}',
    'hubv2_a11y_rule': 'rule: {$a}',
    'hubv2_a11y_collapsed': 'collapsed',
    'hubv2_a11y_expanded': 'expanded',
    'hubv2_children': '{$a} children',
    'hubv2_children_one': '1 child',
    'hubv2_lens_bucket_unused': 'Not used',
    'hubv2_lens_bucket_used': 'Used',
    'hubv2_lens_count_courses': '{$a} courses',
    'hubv2_lens_count_courses_one': '1 course',
    'hubv2_lens_count_modules': '{$a} activities',
    'hubv2_lens_count_modules_one': '1 activity',
    'hubv2_lens_count_templates': '{$a} plan templates',
    'hubv2_lens_count_templates_one': '1 plan template',
    'hubv2_lens_count_plans': '{$a} learning plans',
    'hubv2_lens_count_plans_one': '1 learning plan',
    'hubv2_lens_unused_said': 'not used',
    'hubv2_lens_used_said': 'used',
    'hubv2_lens_rule_points': '{$a->required} of {$a->available} points',
    'hubv2_lens_rule_childpoints': '{$a} points',
    'hubv2_lens_rule_childpoints_one': '1 point',
    'hubv2_lens_rule_required': 'required',
    'hubv2_lens_rule_cannotfire': 'Can never fire',
    'hubv2_lens_rule_childsaid': '{$a->points} in the rule of {$a->name}',
    'hubv2_lens_rule_asks_all': 'Every child competency',
    'hubv2_lens_elsewhere_said': '{$a} related not on the map',
    'hubv2_lens_elsewhere_said_one': '1 related not on the map',
    'hubv2_outline_related': '{$a} related',
    'hubv2_outline_related_one': '1 related',
    'hubv2_lens_usage_none': 'NOT SHOWN',
    'hubv2_lens_usage_presence': 'PRESENCE ONLY',
    'tool_lp:competencyoutcome_none': 'None',
    'tool_lp:competencyoutcome_evidence': 'Attach an evidence',
    'tool_lp:competencyoutcome_complete': 'Mark as complete',
    'tool_lp:competencyoutcome_recommend': 'Recommend the competency',
};

/**
 * A points rule on competency 1 over 11 (3 points), 12 (2, required) and 13 (1): 6 asked of 6 available.
 *
 * @param {object} extra Fields to override.
 * @returns {object} The rule.
 */
const pointsRule = (extra: Partial<InsightRule> = {}): InsightRule => ({
    competencyid: 1, type: 'points', label: 'Points', outcome: 'complete', required: 6, available: 6, requiredchildren: 1,
    children: [{id: 11, points: 3, required: false}, {id: 12, points: 2, required: true}, {id: 13, points: 1, required: false}],
    canfire: true, ...extra,
});

/**
 * An answer for a framework of 8 competencies (1, 2, 11, 12, 13, 21, 22, 23).
 *
 * @param {object} extra Fields to override.
 * @returns {object} The answer.
 */
const answer = (extra: Partial<Insights> = {}): Insights => ({
    frameworkid: 9,
    exact: true,
    usagelevel: 'exact',
    usage: [
        {competencyid: 1, courses: 0, modules: 3, templates: 1, plans: 0},
        {competencyid: 11, courses: 1, modules: 0, templates: 0, plans: 0},
        {competencyid: 12, courses: 2, modules: 0, templates: 0, plans: 0},
        {competencyid: 13, courses: 3, modules: 0, templates: 0, plans: 0},
        {competencyid: 21, courses: 5, modules: 0, templates: 0, plans: 0},
        {competencyid: 22, courses: 6, modules: 0, templates: 0, plans: 0},
    ],
    rules: [pointsRule(), {competencyid: 2, type: 'all', label: 'All children', outcome: 'evidence', required: 0, available: 0,
        requiredchildren: 0, children: [], canfire: true}],
    relations: [{competencyid: 11, count: 2}, {competencyid: 21, count: 1}, {competencyid: 23, count: 1}],
    pairs: [{a: 11, b: 21}, {a: 11, b: 23}],
    checks: [{code: 'unused', competencyid: 2, params: []}, {code: 'unused', competencyid: 23, params: []}],
    summary: {competencies: 8, maxdepth: 2, withrule: 2, relations: 2, findings: 2},
    ...extra,
});

/**
 * A node with the fields the graph reads.
 *
 * @param {number} id The id.
 * @param {number} parentid The parent.
 * @param {object} extra Fields to override.
 * @returns {object} The node.
 */
const node = (id: number, parentid: number = ROOT, extra: Partial<StructureNode> = {}): StructureNode => ({
    id, parentid, shortname: `C${id}`, idnumber: '', taxonomy: 'Competency', scale: '', description: '',
    coursecount: 0, activitycount: 0, templatecount: 0, depth: 0, indent: 0, haschildren: false, canmanage: true,
    ruletype: null, ruleoutcome: 0, ruleconfig: null, rulelabel: '', type: '', tag1: '', tag2: '', bgcolor: '', textcolor: '',
    ...extra,
});

/**
 * Roots 1 (open, children 11, 12, 13) and 2 (closed, its children 21 to 23 not loaded).
 *
 * @returns {object} The tree.
 */
const tree = () => {
    let built = createTree({items: [node(1, ROOT, {haschildren: true, ruletype: 'points', rulelabel: 'Points'}),
        node(2, ROOT, {haschildren: true})], total: 2});
    built = insertChildren(built, 1, {items: [node(11, 1), node(12, 1), node(13, 1)], total: 3});
    return setExpanded(built, 1, true);
};

/**
 * What the marks read, for an answer and a measure.
 *
 * @param {object} given The answer.
 * @param {string} measure The measure.
 * @returns {object} The context.
 */
const context = (given: Insights = answer(), measure: Measure = 'courses'): MarkContext => {
    const index = indexInsights(given);
    return {
        labels: LABELS, index, measure,
        elsewhere: pairsOnMap(given.pairs, drawnSet(tree())).elsewhere,
        nameOf: (id) => `C${id}`,
    };
};

describe('the usage buckets', () => {
    it('step at 0, 1 to 2, 3 to 5 and 6 or more under exact counts', () => {
        expect([0, 1, 2, 3, 5, 6, 40].map((count) => usageBucket(count, 'exact')))
            .toEqual(['unused', 'low', 'low', 'mid', 'mid', 'high', 'high']);
    });

    it('say only used or unused under presence counts, whatever the number', () => {
        expect([0, 1, 2, 6].map((count) => usageBucket(count, 'presence'))).toEqual(['unused', 'used', 'used', 'used']);
    });

    it('count every measure from its own column', () => {
        const index = indexInsights(answer());
        const expected: Record<Measure, number[]> = {
            courses: [3, 2, 2, 1],
            modules: [7, 0, 1, 0],
            templates: [7, 1, 0, 0],
            plans: [8, 0, 0, 0],
        };
        MEASURES.forEach((measure) => {
            expect(usageLegend(index, measure).map((line) => line.count)).toEqual(expected[measure]);
            expect(usageLegend(index, measure).map((line) => line.bucket)).toEqual(['unused', 'low', 'mid', 'high']);
        });
    });

    it('make the legend two lines under presence counts, and none when usage is not shown', () => {
        const presence = answer({usagelevel: 'presence', exact: false, usage: [
            {competencyid: 11, courses: 1, modules: 0, templates: 0, plans: 0},
            {competencyid: 12, courses: 1, modules: 1, templates: 0, plans: 0},
        ]});
        expect(usageLegend(indexInsights(presence), 'courses')).toEqual([{bucket: 'unused', count: 6}, {bucket: 'used', count: 2}]);
        expect(usageLegend(indexInsights(answer({usagelevel: 'none', usage: []})), 'courses')).toEqual([]);
    });

    it('name every unused competency under exact counts, from the zeros and the checker', () => {
        // Courses: 1 has none (its activities do not count), 2 and 23 have no use at all; 3 unused in the legend.
        expect(unusedIds(indexInsights(answer()), 'courses', [1, 2, 11, 12, 13])).toEqual({ids: [1, 2, 23], complete: true});
        // The control: without the checker's findings, 23 is nowhere to be named and the list says it is partial.
        expect(unusedIds(indexInsights(answer({checks: []})), 'courses', [1, 2, 11, 12, 13]))
            .toEqual({ids: [1, 2], complete: false});
    });

    it('name the loaded unused ones under presence counts, and say when that is not all of them', () => {
        const presence = answer({usagelevel: 'presence', exact: false, checks: [], usage: [
            {competencyid: 11, courses: 1, modules: 0, templates: 0, plans: 0},
        ]});
        expect(unusedIds(indexInsights(presence), 'courses', [1, 11, 12])).toEqual({ids: [1, 12], complete: false});
    });

    it('say why usage is missing or reduced in one sentence, and nothing under exact counts', () => {
        expect(usageNotice(LABELS, 'none')).toBe('NOT SHOWN');
        expect(usageNotice(LABELS, 'presence')).toBe('PRESENCE ONLY');
        expect(usageNotice(LABELS, 'exact')).toBe('');
    });
});

describe('the rules model', () => {
    it('says a points rule as the points asked of those available, any other by its label', () => {
        expect(ruleLine(LABELS, pointsRule({required: 6, available: 8}))).toBe('6 of 8 points');
        expect(ruleLine(LABELS, answer().rules[1])).toBe('All children');
    });

    it('says what a child gives, singular for one point, with "required" when it is', () => {
        expect(childLine(LABELS, {points: 3, required: false})).toBe('3 points');
        expect(childLine(LABELS, {points: 1, required: true})).toBe('1 point · required');
    });

    it('lists the required children, the outcome in core\'s words, and whether it can fire', () => {
        const facts = ruleFacts(LABELS, pointsRule({canfire: false, required: 9}));
        expect(facts).toEqual({id: 1, type: 'Points', asks: '9 points', available: '6 points', required: [12],
            outcome: 'Mark as complete', canfire: false});
        expect(ruleFacts(LABELS, answer().rules[1])).toMatchObject({asks: 'Every child competency', available: '',
            required: [], outcome: 'Attach an evidence'});
    });

    it('draws in the rule\'s style only the children a points rule counts, every child of any other rule', () => {
        const index = indexInsights(answer({rules: [pointsRule({children: [{id: 11, points: 3, required: false},
            {id: 12, points: 0, required: true}, {id: 13, points: 0, required: false}]}), answer().rules[1]]}));
        expect([11, 12, 13].map((child) => countsChild(index, 1, child))).toEqual([true, true, false]);
        expect(countsChild(index, 2, 21)).toBe(true);
        expect(countsChild(index, 11, 99)).toBe(false);
    });

    it('counts the legend: rules that fire, rules that cannot, competencies without one', () => {
        const index = indexInsights(answer({rules: [pointsRule({canfire: false}), answer().rules[1]]}));
        expect(rulesLegend(index)).toEqual({fire: 1, cannotfire: 1, without: 6});
    });
});

describe('the relations on the map', () => {
    it('draws a pair with both ends drawn, counts one end drawn, and leaves out a pair with none', () => {
        const drawn = new Set([11, 12, 21]);
        const result = pairsOnMap([{a: 11, b: 21}, {a: 11, b: 23}, {a: 12, b: 99}, {a: 30, b: 31}], drawn);
        expect(result.edges).toEqual([[11, 21]]);
        expect([...result.elsewhere.entries()]).toEqual([[11, 1], [12, 1]]);
    });

    it('counts the legend from the relation counts', () => {
        expect(relationsLegend(indexInsights(answer()))).toEqual({related: 3, unrelated: 5});
    });

    it('draws the lens\'s pairs as the selection\'s dotted edges, once per pair, out of the accessibility tree', () => {
        const built = tree();
        const graph = buildGraph({tree: built, framework: {id: 9, shortname: 'F', competencycount: 8} as never,
            selected: 11, related: [12], moving: null, labels: {roledescription: 'competency', more: () => ''},
            decor: {pairs: [[11, 12]]}});
        const related = graph.edges.filter((edge) => edge.className === 'local-dimensions-hubv2-edge-related');
        expect(related.map((edge) => edge.id)).toEqual(['p-11-12']);
        expect(related[0].domAttributes).toEqual({'aria-hidden': true});
    });
});

describe('each lens\'s mark', () => {
    it('is nothing without a lens', () => {
        expect(lensMarks('none', [1, 11], context()).size).toBe(0);
        expect(itemMark('none', 1, context())).toBeNull();
    });

    it('under usage: the count in words and as the row\'s counter, unused as a dashed mark', () => {
        expect(itemMark('usage', 22, context())).toEqual({className: 'local-dimensions-hubv2-lens-high', line: '6 courses',
            said: '6 courses', meta: {text: '6', icon: '', dashed: false}, icon: '', elsewhere: 0});
        expect(itemMark('usage', 11, context())?.said).toBe('1 course');
        expect(itemMark('usage', 1, context())).toMatchObject({className: 'local-dimensions-hubv2-lens-unused',
            line: 'Not used', said: 'not used', meta: {dashed: true}});
        // The same competency by another measure: its own activities, not its children's sum.
        expect(itemMark('usage', 1, context(answer(), 'modules'))?.said).toBe('3 activities');
    });

    it('under usage with presence counts: used or not used, no number', () => {
        const presence = answer({usagelevel: 'presence', exact: false, usage: [
            {competencyid: 11, courses: 1, modules: 0, templates: 0, plans: 0},
        ]});
        expect(itemMark('usage', 11, context(presence))).toMatchObject({className: 'local-dimensions-hubv2-lens-used',
            said: 'used', meta: null});
        expect(itemMark('usage', 12, context(presence))?.said).toBe('not used');
        expect(itemMark('usage', 11, context(answer({usagelevel: 'none', usage: []})))).toBeNull();
    });

    it('under rules: the points on the parent, what each child gives and in whose rule, a rule that cannot fire', () => {
        expect(itemMark('rules', 1, context())).toMatchObject({className: 'local-dimensions-hubv2-lens-ruled',
            line: '6 of 6 points', said: '6 of 6 points', icon: 'fa-diagram-project'});
        expect(itemMark('rules', 12, context())).toMatchObject({className: 'local-dimensions-hubv2-lens-counted',
            line: '2 points · required', said: '2 points · required in the rule of C1'});
        expect(itemMark('rules', 21, context())?.className).toBe('local-dimensions-hubv2-lens-quiet');
        const broken = answer({rules: [pointsRule({canfire: false, required: 9})]});
        expect(itemMark('rules', 1, context(broken))).toMatchObject({className: 'local-dimensions-hubv2-lens-cannotfire',
            line: '9 of 6 points · Can never fire', said: '9 of 6 points, Can never fire', icon: 'fa-triangle-exclamation'});
        // An "all" rule's label is already in the name ("rule: All children"): the lens adds no words.
        expect(itemMark('rules', 2, context())?.said).toBe('');
    });

    it('under relations: the count, the ones off the map, and quiet without any', () => {
        expect(itemMark('relations', 11, context())).toMatchObject({className: 'local-dimensions-hubv2-lens-related',
            line: '2 related', said: '2 related, 2 related not on the map', elsewhere: 2});
        expect(itemMark('relations', 12, context())?.className).toBe('local-dimensions-hubv2-lens-quiet');
    });
});

describe('the words of a lens in a name', () => {
    const findingwords = (count: number): string => (count === 1 ? '1 finding' : `${count} findings`);

    it('end a node\'s accessible name under each lens, and its findings after them', () => {
        const base = {name: 'C11', level: 3, position: 1, total: 3, expandable: false, expanded: false, children: null,
            rule: '', relatedto: null};
        expect(nodeName(LABELS, base)).toBe('C11, level 3, 1 of 3');
        expect(nodeName(LABELS, {...base, lens: itemMark('usage', 11, context())?.said})).toBe('C11, level 3, 1 of 3, 1 course');
        expect(nodeName(LABELS, {...base, lens: itemMark('rules', 11, context())?.said, findings: findingwords(2)}))
            .toBe('C11, level 3, 1 of 3, 3 points in the rule of C1, 2 findings');
        expect(nodeName(LABELS, {...base, lens: itemMark('relations', 11, context())?.said}))
            .toBe('C11, level 3, 1 of 3, 2 related, 2 related not on the map');
    });

    it('are the graph\'s names when the lens is drawn', () => {
        const built = tree();
        const marks = lensMarks('usage', [...drawnSet(built)], context());
        const graph = buildGraph({tree: built, framework: {id: 9, shortname: 'F', competencycount: 8} as never,
            selected: null, related: [], moving: null,
            labels: {roledescription: 'competency', more: () => '', name: (info) => nodeName(LABELS, info)},
            decor: {marks, findings: new Map([[12, 1]]), findingwords}});
        const named = (key: string): string | undefined => graph.nodes.find((item) => item.id === key)?.ariaLabel;
        expect(named('c13')).toBe('C13, level 3, 3 of 3, 3 courses');
        expect(named('c12')).toBe('C12, level 3, 2 of 3, 2 courses, 1 finding');
        expect(named('c1')).toBe('C1, level 2, 1 of 2, expanded, 3 children, rule: Points, not used');
    });

    it('end a row\'s description: the lens\'s words, then the findings', () => {
        expect(markWords(itemMark('usage', 1, context()), 0, findingwords)).toEqual(['not used']);
        expect(markWords(itemMark('relations', 21, context()), 3, findingwords)).toEqual(['1 related', '3 findings']);
        expect(markWords(itemMark('rules', 21, context()), 0, findingwords)).toEqual([]);
        expect(markWords(null, 1, findingwords)).toEqual(['1 finding']);
    });
});
