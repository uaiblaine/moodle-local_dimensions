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
 * Tests for the framework's summary: the server's total, and every other count labelled as the loaded
 * part's until the whole framework is loaded.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {Labels} from '../../src/hubv2/strings';
import {summaryFacts, summaryLines} from '../../src/hubv2/summary';
import {ROOT, createTree, insertChildren, setExpanded} from '../../src/hubv2/tree';
import type {StructureNode} from '../../src/hubv2/types';

/**
 * A node with the fields the tests read.
 *
 * @param {number} id The id.
 * @param {number} parentid The parent.
 * @param {object} extra Fields to override.
 * @returns {object} The node.
 */
const node = (id: number, parentid: number = ROOT, extra: Partial<StructureNode> = {}): StructureNode => ({
    id, parentid, shortname: `C${id}`, idnumber: '', taxonomy: '', scale: '', description: '',
    coursecount: 0, activitycount: 0, templatecount: 0, depth: 0, indent: 0, haschildren: false, canmanage: true,
    ruletype: null, ruleoutcome: 0, ruleconfig: null, rulelabel: '', type: '', tag1: '', tag2: '', bgcolor: '', textcolor: '',
    ...extra,
});

/**
 * Roots 1 (with a rule, open, two children) and 2; child 11 has a child 111 loaded.
 *
 * @returns {object} The tree.
 */
const sample = () => {
    let tree = createTree({items: [node(1, ROOT, {haschildren: true, ruletype: 'all'}), node(2)], total: 2});
    tree = insertChildren(tree, 1, {items: [node(11, 1, {haschildren: true}), node(12, 1)], total: 2});
    tree = insertChildren(tree, 11, {items: [node(111, 11, {ruletype: 'points'})], total: 1});
    return setExpanded(tree, 1, true);
};

const labels: Labels = {
    'hubv2_summary_levels': 'LEVELS',
    'hubv2_summary_levels_loaded': 'LEVELS LOADED',
    'hubv2_summary_loaded': 'LOADED',
    'hubv2_summary_opened': 'OPEN',
    'hubv2_summary_rules': 'RULES',
    'hubv2_summary_rules_loaded': 'RULES LOADED',
    'hubv2_summary_total': 'TOTAL',
};

describe('summaryFacts', () => {
    it('counts the loaded tree: competencies, the deepest level, rules and open branches', () => {
        expect(summaryFacts(sample(), 5)).toEqual({total: 5, loaded: 5, complete: true, levels: 3, rules: 2, opened: 1});
    });

    it('knows when the loaded part is not the whole framework', () => {
        expect(summaryFacts(sample(), 40).complete).toBe(false);
        expect(summaryFacts(createTree({items: [], total: 0}), 0)).toEqual(
            {total: 0, loaded: 0, complete: true, levels: 0, rules: 0, opened: 0}
        );
    });
});

describe('summaryLines', () => {
    it('labels the counts as the whole framework\'s when all of it is loaded', () => {
        expect(summaryLines(labels, summaryFacts(sample(), 5))).toEqual([
            {label: 'TOTAL', value: '5'},
            {label: 'LEVELS', value: '3'},
            {label: 'RULES', value: '2'},
            {label: 'OPEN', value: '1'},
        ]);
    });

    it('says how many are loaded and labels every count as the loaded part\'s otherwise', () => {
        expect(summaryLines(labels, summaryFacts(sample(), 40))).toEqual([
            {label: 'TOTAL', value: '40'},
            {label: 'LOADED', value: '5'},
            {label: 'LEVELS LOADED', value: '3'},
            {label: 'RULES LOADED', value: '2'},
            {label: 'OPEN', value: '1'},
        ]);
    });
});
