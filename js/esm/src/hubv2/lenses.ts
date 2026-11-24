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
 * The lenses: one question at a time over the same tree (Usage, Rules, Relations), each answered from the
 * framework's insights (insights.ts) in three forms that say the same thing.
 *
 * On the map a card takes a mark that differs in shape or pattern, never in hue alone (a dashed edge, an icon,
 * a line of words under the name); in the outline a row takes a quiet counter or a small mark at its end; and
 * the panel lists the legend with its counts and the competencies in words. Every mark's words are in the
 * card's and the row's accessible name too (I26: a lens never speaks by colour alone).
 *
 * Usage: a competency's own use by the measure chosen, never its children's sum, in four buckets (0; 1 to 2;
 * 3 to 5; 6 or more) under exact counts, or used and unused under presence counts. Rules: each rule on its
 * parent, the points it asks against those its children give, the children it counts with their points and
 * "required", and whether it can ever fire. Relations: every related pair of the framework; a pair whose two
 * ends are drawn is a dotted edge, one with a single end drawn a count on that end.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/lenses
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {usageOf} from './insights';
import type {InsightsIndex, Lens, Measure} from './insights';
import {fill, fillCount, fillObject} from './strings';
import type {Labels} from './strings';
import {visibleItems} from './tree';
import type {Tree} from './tree';
import type {InsightRule, UsageLevel} from './types';

/** A bucket of the usage lens: unused, the three steps of exact counts, or used under presence counts. */
export type Bucket = 'unused' | 'low' | 'mid' | 'high' | 'used';

/** The buckets the legend lists under exact counts, in order. */
export const EXACT_BUCKETS: Bucket[] = ['unused', 'low', 'mid', 'high'];

/** The buckets the legend lists under presence counts. */
export const PRESENCE_BUCKETS: Bucket[] = ['unused', 'used'];

/** The card's class of each bucket, written whole for the stylesheet's test. */
export const BUCKET_CLASSES: Record<Bucket, string> = {
    unused: 'local-dimensions-hubv2-lens-unused',
    low: 'local-dimensions-hubv2-lens-low',
    mid: 'local-dimensions-hubv2-lens-mid',
    high: 'local-dimensions-hubv2-lens-high',
    used: 'local-dimensions-hubv2-lens-used',
};

/** The legend's words for each bucket. */
export const BUCKET_LABELS: Record<Bucket, string> = {
    unused: 'hubv2_lens_bucket_unused',
    low: 'hubv2_lens_bucket_low',
    mid: 'hubv2_lens_bucket_mid',
    high: 'hubv2_lens_bucket_high',
    used: 'hubv2_lens_bucket_used',
};

/** The count's words for each measure, `<key>_one` for exactly 1 (strings.fillCount). */
export const COUNT_KEYS: Record<Measure, string> = {
    courses: 'hubv2_lens_count_courses',
    modules: 'hubv2_lens_count_modules',
    templates: 'hubv2_lens_count_templates',
    plans: 'hubv2_lens_count_plans',
};

/** Each measure's label in the measure switch. */
export const MEASURE_LABELS: Record<Measure, string> = {
    courses: 'hubv2_lens_measure_courses',
    modules: 'hubv2_lens_measure_modules',
    templates: 'hubv2_lens_measure_templates',
    plans: 'hubv2_lens_measure_plans',
};

/** Each lens's label in the switch. */
export const LENS_LABELS: Record<Lens, string> = {
    none: 'hubv2_lens_none',
    usage: 'hubv2_lens_usage',
    rules: 'hubv2_lens_rules',
    relations: 'hubv2_lens_relations',
};

/** The classes of the other lenses' marks on a card, written whole. */
export const MARK_CLASSES = {
    ruled: 'local-dimensions-hubv2-lens-ruled',
    cannotfire: 'local-dimensions-hubv2-lens-cannotfire',
    counted: 'local-dimensions-hubv2-lens-counted',
    related: 'local-dimensions-hubv2-lens-related',
    quiet: 'local-dimensions-hubv2-lens-quiet',
} as const;

/**
 * A count's bucket: unused at 0; under exact counts 1 to 2, 3 to 5, 6 or more; under presence counts, used.
 *
 * @param {number} count The count.
 * @param {string} level The usage level of the answer.
 * @returns {string} The bucket.
 */
export const usageBucket = (count: number, level: UsageLevel): Bucket => {
    if (count <= 0) {
        return 'unused';
    }
    if (level !== 'exact') {
        return 'used';
    }
    if (count <= 2) {
        return 'low';
    }
    return count <= 5 ? 'mid' : 'high';
};

/** One line of a legend: its bucket and how many competencies are in it. */
export type LegendLine = {bucket: Bucket, count: number};

/**
 * The usage legend: how many of the framework's competencies fall in each bucket of a measure. A competency the
 * answer does not list has no use at all, so the unused count is the framework's total less the listed ones the
 * measure finds in use.
 *
 * @param {object} index The insights.
 * @param {string} measure The measure.
 * @returns {Array} The lines, in the legend's order; empty when usage is not shown to the viewer.
 */
export const usageLegend = (index: InsightsIndex, measure: Measure): LegendLine[] => {
    const level = index.answer.usagelevel;
    if (level === 'none') {
        return [];
    }
    const buckets = level === 'exact' ? EXACT_BUCKETS : PRESENCE_BUCKETS;
    const counts = new Map<Bucket, number>(buckets.map((bucket) => [bucket, 0]));
    let used = 0;
    index.answer.usage.forEach((row) => {
        const bucket = usageBucket(row[measure], level);
        if (bucket !== 'unused') {
            used++;
            counts.set(bucket, (counts.get(bucket) ?? 0) + 1);
        }
    });
    counts.set('unused', Math.max(0, index.answer.summary.competencies - used));
    return buckets.map((bucket) => ({bucket, count: counts.get(bucket) ?? 0}));
};

/**
 * The competencies a measure finds unused that the workspace can name: those the answer lists with a 0 for the
 * measure, those the checker found unused of every kind (exact counts only), and, since the answer lists no
 * competency without any use, those the tree has loaded and the answer does not list. Complete when they are as
 * many as the legend's unused count.
 *
 * @param {object} index The insights.
 * @param {string} measure The measure.
 * @param {Array} loaded The competencies the tree has loaded, in its order.
 * @returns {object} The ids, ascending, and whether they are all of them.
 */
export const unusedIds = (index: InsightsIndex, measure: Measure, loaded: number[]): {ids: number[], complete: boolean} => {
    const ids = new Set<number>();
    index.answer.usage.forEach((row) => {
        if (row[measure] <= 0) {
            ids.add(row.competencyid);
        }
    });
    index.answer.checks.forEach((check) => {
        if (check.code === 'unused' && check.competencyid > 0) {
            ids.add(check.competencyid);
        }
    });
    loaded.forEach((id) => {
        if (!index.usage.has(id)) {
            ids.add(id);
        }
    });
    const total = usageLegend(index, measure).find((line) => line.bucket === 'unused')?.count ?? 0;
    return {ids: [...ids].sort((a, b) => a - b), complete: ids.size >= total};
};

/**
 * A rule's card line: "6 of 8 points" for a points rule, its label otherwise.
 *
 * @param {object} labels The labels.
 * @param {object} rule The rule.
 * @returns {string} The line.
 */
export const ruleLine = (labels: Labels, rule: InsightRule): string => (rule.type === 'points'
    ? fillObject(labels.hubv2_lens_rule_points, {required: String(rule.required), available: String(rule.available)})
    : rule.label);

/**
 * What a child gives in its parent's points rule: "3 points", with "required" when the rule requires it.
 *
 * @param {object} labels The labels.
 * @param {object} entry The child's entry in the rule.
 * @returns {string} The words.
 */
export const childLine = (labels: Labels, entry: {points: number, required: boolean}): string => {
    const parts = [fillCount(labels, 'hubv2_lens_rule_childpoints', entry.points)];
    if (entry.required) {
        parts.push(labels.hubv2_lens_rule_required);
    }
    return parts.join(' · ');
};

/**
 * The children a rule names as required, in the rule's order.
 *
 * @param {object} rule The rule.
 * @returns {Array} Their ids.
 */
export const requiredChildren = (rule: InsightRule): number[] =>
    rule.children.filter((child) => child.required).map((child) => child.id);

/**
 * Whether a parent's rule counts a child, so their edge is drawn in the rule's style: a points rule counts the
 * children it lists with points or as required; any other rule counts every child.
 *
 * @param {object} index The insights.
 * @param {number} parent The parent.
 * @param {number} child The child.
 * @returns {boolean} Whether it does.
 */
export const countsChild = (index: InsightsIndex, parent: number, child: number): boolean => {
    const rule = index.rules.get(parent);
    if (!rule) {
        return false;
    }
    if (rule.type !== 'points') {
        return true;
    }
    return rule.children.some((entry) => entry.id === child && (entry.points > 0 || entry.required));
};

/** The rules legend: rules that can fire, rules that cannot, and competencies with none. */
export type RulesLegend = {fire: number, cannotfire: number, without: number};

/**
 * The rules legend's counts.
 *
 * @param {object} index The insights.
 * @returns {object} The counts.
 */
export const rulesLegend = (index: InsightsIndex): RulesLegend => {
    const cannotfire = index.answer.rules.filter((rule) => !rule.canfire).length;
    return {
        fire: index.answer.rules.length - cannotfire,
        cannotfire,
        without: Math.max(0, index.answer.summary.competencies - index.answer.rules.length),
    };
};

/** The relations a map draws: the pairs with both ends drawn, and per drawn end the pairs whose other end is not. */
export type MapPairs = {edges: [number, number][], elsewhere: Map<number, number>};

/**
 * Split the framework's related pairs by what the map draws.
 *
 * @param {Array} pairs The pairs, each once.
 * @param {Set} drawn The competencies drawn.
 * @returns {object} The edges and the counts elsewhere.
 */
export const pairsOnMap = (pairs: {a: number, b: number}[], drawn: Set<number>): MapPairs => {
    const edges: [number, number][] = [];
    const elsewhere = new Map<number, number>();
    pairs.forEach(({a, b}) => {
        const hasa = drawn.has(a);
        const hasb = drawn.has(b);
        if (hasa && hasb) {
            edges.push([a, b]);
        } else if (hasa || hasb) {
            const end = hasa ? a : b;
            elsewhere.set(end, (elsewhere.get(end) ?? 0) + 1);
        }
    });
    return {edges, elsewhere};
};

/** The relations legend: competencies with a relation and without. */
export type RelationsLegend = {related: number, unrelated: number};

/**
 * The relations legend's counts.
 *
 * @param {object} index The insights.
 * @returns {object} The counts.
 */
export const relationsLegend = (index: InsightsIndex): RelationsLegend => {
    const related = [...index.relations.values()].filter((count) => count > 0).length;
    return {related, unrelated: Math.max(0, index.answer.summary.competencies - related)};
};

/**
 * The competencies the tree draws, as a set.
 *
 * @param {object} tree The tree.
 * @returns {Set} Their ids.
 */
export const drawnSet = (tree: Tree): Set<number> =>
    new Set(visibleItems(tree).flatMap((item) => (item.kind === 'competency' ? [item.id] : [])));

/** What a lens puts on one competency's card and row. */
export type ItemMark = {
    // The card's class, written whole; '' for none.
    className: string,
    // The card's line under the name, in place of its taxonomy; '' keeps the taxonomy.
    line: string,
    // The words in the card's accessible name and the row's description.
    said: string,
    // The row's quiet meta: short text, an icon, or the dashed mark of an unused competency.
    meta: {text: string, icon: string, dashed: boolean} | null,
    // The icon before the card's line, the mark's shape; '' for none.
    icon: string,
    // Related competencies not drawn, the count mark on the card.
    elsewhere: number,
};

/** What the marks are computed from besides the lens and the competency. */
export type MarkContext = {
    labels: Labels,
    index: InsightsIndex,
    measure: Measure,
    // Related competencies not drawn, by drawn competency (pairsOnMap).
    elsewhere: Map<number, number>,
    // A competency's name: the parent a child's points belong to is named.
    nameOf: (id: number) => string,
};

/** A quiet mark: the card drawn quieter, no line, no words. */
const QUIET: ItemMark = {className: MARK_CLASSES.quiet, line: '', said: '', meta: null, icon: '', elsewhere: 0};

/**
 * The usage lens's mark on a competency.
 *
 * @param {number} id The competency.
 * @param {object} context What the marks read.
 * @returns {object} The mark.
 */
const usageMark = (id: number, context: MarkContext): ItemMark | null => {
    const {labels, index, measure} = context;
    const level = index.answer.usagelevel;
    if (level === 'none') {
        return null;
    }
    const count = usageOf(index, id, measure);
    const bucket = usageBucket(count, level);
    if (bucket === 'unused') {
        return {className: BUCKET_CLASSES.unused, line: labels.hubv2_lens_bucket_unused, said: labels.hubv2_lens_unused_said,
            meta: {text: '', icon: '', dashed: true}, icon: '', elsewhere: 0};
    }
    if (bucket === 'used') {
        return {className: BUCKET_CLASSES.used, line: labels.hubv2_lens_bucket_used, said: labels.hubv2_lens_used_said,
            meta: null, icon: '', elsewhere: 0};
    }
    const words = fillCount(labels, COUNT_KEYS[measure], count);
    return {className: BUCKET_CLASSES[bucket], line: words, said: words, meta: {text: String(count), icon: '', dashed: false},
        icon: '', elsewhere: 0};
};

/**
 * The rules lens's mark on a competency: its own rule, what it gives in its parent's points rule, both, or quiet.
 *
 * @param {number} id The competency.
 * @param {object} context What the marks read.
 * @returns {object} The mark.
 */
const rulesMark = (id: number, context: MarkContext): ItemMark => {
    const {labels, index, nameOf} = context;
    const rule = index.rules.get(id);
    const entry = index.ruled.get(id);
    if (!rule && !entry) {
        return QUIET;
    }
    const lines: string[] = [];
    const said: string[] = [];
    let className: string = MARK_CLASSES.counted;
    let icon = '';
    let meta: ItemMark['meta'] = null;
    if (rule) {
        const line = ruleLine(labels, rule);
        lines.push(line);
        // The rule's label is already in the name (rule: ...); the points are not.
        if (rule.type === 'points') {
            said.push(line);
        }
        className = MARK_CLASSES.ruled;
        icon = 'fa-diagram-project';
        if (!rule.canfire) {
            lines.push(labels.hubv2_lens_rule_cannotfire);
            said.push(labels.hubv2_lens_rule_cannotfire);
            className = MARK_CLASSES.cannotfire;
            icon = 'fa-triangle-exclamation';
        }
        meta = {text: rule.type === 'points' ? line : '', icon, dashed: false};
    }
    if (entry) {
        const line = childLine(labels, entry);
        lines.push(line);
        said.push(fillObject(labels.hubv2_lens_rule_childsaid, {points: line, name: nameOf(entry.parent)}));
        if (!meta) {
            meta = {text: line, icon: '', dashed: false};
        }
    }
    return {className, line: lines.join(' · '), said: said.join(', '), meta, icon, elsewhere: 0};
};

/**
 * The relations lens's mark on a competency: how many it is related to, and how many of those are not drawn.
 *
 * @param {number} id The competency.
 * @param {object} context What the marks read.
 * @returns {object} The mark.
 */
const relationsMark = (id: number, context: MarkContext): ItemMark => {
    const {labels, index, elsewhere} = context;
    const count = index.relations.get(id) ?? 0;
    if (count <= 0) {
        return QUIET;
    }
    const away = elsewhere.get(id) ?? 0;
    const words = fillCount(labels, 'hubv2_outline_related', count);
    const said = [words];
    if (away > 0) {
        said.push(fillCount(labels, 'hubv2_lens_elsewhere_said', away));
    }
    return {className: MARK_CLASSES.related, line: words, said: said.join(', '),
        meta: {text: String(count), icon: 'fa-share-nodes', dashed: false}, icon: 'fa-share-nodes', elsewhere: away};
};

/**
 * What a lens puts on one competency, or null for none (no lens, or usage the viewer is not told).
 *
 * @param {string} lens The lens.
 * @param {number} id The competency.
 * @param {object} context What the marks read.
 * @returns {object|null} The mark.
 */
export const itemMark = (lens: Lens, id: number, context: MarkContext): ItemMark | null => {
    switch (lens) {
        case 'usage':
            return usageMark(id, context);
        case 'rules':
            return rulesMark(id, context);
        case 'relations':
            return relationsMark(id, context);
        default:
            return null;
    }
};

/**
 * Every drawn competency's mark under a lens.
 *
 * @param {string} lens The lens.
 * @param {Array} ids The competencies drawn.
 * @param {object} context What the marks read.
 * @returns {Map} The marks by competency; empty without a lens.
 */
export const lensMarks = (lens: Lens, ids: number[], context: MarkContext): Map<number, ItemMark> => {
    const marks = new Map<number, ItemMark>();
    if (lens === 'none') {
        return marks;
    }
    ids.forEach((id) => {
        const mark = itemMark(lens, id, context);
        if (mark) {
            marks.set(id, mark);
        }
    });
    return marks;
};

/**
 * The label of a rule's outcome, core's own wording (tool_lp).
 *
 * @param {object} labels The labels.
 * @param {string} outcome The outcome: none, evidence, complete or recommend.
 * @returns {string} The label.
 */
export const outcomeLabel = (labels: Labels, outcome: string): string => {
    switch (outcome) {
        case 'evidence':
            return labels['tool_lp:competencyoutcome_evidence'];
        case 'complete':
            return labels['tool_lp:competencyoutcome_complete'];
        case 'recommend':
            return labels['tool_lp:competencyoutcome_recommend'];
        default:
            return labels['tool_lp:competencyoutcome_none'];
    }
};

/** One rule as the panel's block says it. */
export type RuleFacts = {
    id: number,
    type: string,
    asks: string,
    available: string,
    required: number[],
    outcome: string,
    canfire: boolean,
};

/**
 * What the panel says of one rule: its type, what it asks, what its children make available, the children it
 * requires, its outcome, and whether it can ever fire.
 *
 * @param {object} labels The labels.
 * @param {object} rule The rule.
 * @returns {object} The facts.
 */
export const ruleFacts = (labels: Labels, rule: InsightRule): RuleFacts => {
    const points = rule.type === 'points';
    return {
        id: rule.competencyid,
        type: rule.label,
        asks: points ? fillCount(labels, 'hubv2_lens_rule_childpoints', rule.required) : labels.hubv2_lens_rule_asks_all,
        available: points ? fillCount(labels, 'hubv2_lens_rule_childpoints', rule.available) : '',
        required: requiredChildren(rule),
        outcome: outcomeLabel(labels, rule.outcome),
        canfire: rule.canfire,
    };
};

/**
 * The usage lens's sentence on what the viewer is told: why there is no usage, or that only presence is shown.
 *
 * @param {object} labels The labels.
 * @param {string} level The usage level.
 * @returns {string} The sentence; '' under exact counts.
 */
export const usageNotice = (labels: Labels, level: UsageLevel): string => {
    if (level === 'none') {
        return labels.hubv2_lens_usage_none;
    }
    return level === 'presence' ? labels.hubv2_lens_usage_presence : '';
};

/**
 * A pair's words, for a list item's name: "A and B".
 *
 * @param {object} labels The labels.
 * @param {string} a One end's name.
 * @param {string} b The other's.
 * @returns {string} The words.
 */
export const pairWords = (labels: Labels, a: string, b: string): string => fillObject(labels.hubv2_lens_pair, {a, b});

/**
 * The "+2 elsewhere" mark's text.
 *
 * @param {object} labels The labels.
 * @param {number} count How many related competencies are not drawn.
 * @returns {string} The text.
 */
export const elsewhereText = (labels: Labels, count: number): string => fill(labels.hubv2_lens_elsewhere, String(count));

/**
 * The words a lens's mark and the checker's findings add to an outline row's description, in that order.
 *
 * @param {object|null} mark The lens's mark on the row's competency.
 * @param {number} findings Its findings while the checker is open, 0 otherwise.
 * @param {Function} findingwords A count of findings in words.
 * @returns {Array} The words, none empty.
 */
export const markWords = (mark: ItemMark | null, findings: number, findingwords: (count: number) => string): string[] =>
    [mark ? mark.said : '', findings > 0 ? findingwords(findings) : ''].filter((part) => part !== '');
