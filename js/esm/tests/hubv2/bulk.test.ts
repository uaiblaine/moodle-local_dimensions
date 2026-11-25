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
 * Tests for the bulk dock: the multi-selection's reducer, the dock's availability, the topmost picks a move and a
 * delete act on, the appearance dialogue's payload, the contrast arithmetic and the outcome words.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
    APPEARANCE_FIELDS, appearanceOutcome, appearancePayload, deleteOutcome, dockCount, deletionSummary, moveOutcome, movePlan,
    preview, targetAllowed, topmost, treeOrder,
} from '../../src/hubv2/bulk';
import type {AppearanceName, Choice} from '../../src/hubv2/bulk';
import {actionState, actionsFor} from '../../src/hubv2/commands';
import type {ActionContext} from '../../src/hubv2/commands';
import {contrastRatio, normalise, ratioText} from '../../src/hubv2/contrast';
import {navOrder} from '../../src/hubv2/keynav';
import {hitSelectable, parentChoices} from '../../src/hubv2/moveparents';
import {NONE, extend, keepOnly, pickLevel, prune, toggle} from '../../src/hubv2/multiselect';
import type {Labels} from '../../src/hubv2/strings';
import {ROOT, createTree, insertChildren, removeNode, setExpanded} from '../../src/hubv2/tree';
import type {DeleteCheck, StructureNode} from '../../src/hubv2/types';

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
 * Roots 1, 2 and 3; 1 holds 11 (a parent of 111) and 12; 2 holds 21. Every branch is loaded; 1 and 11 are open, 2
 * is closed.
 *
 * @returns {object} The tree.
 */
const sample = () => {
    let tree = createTree({items: [node(1, ROOT, {haschildren: true}), node(2, ROOT, {haschildren: true}), node(3)], total: 3});
    tree = insertChildren(tree, 1, {items: [node(11, 1, {haschildren: true}), node(12, 1)], total: 2});
    tree = insertChildren(tree, 11, {items: [node(111, 11)], total: 1});
    tree = insertChildren(tree, 2, {items: [node(21, 2)], total: 1});
    return setExpanded(setExpanded(tree, 1, true), 11, true);
};

/** Labels that show which string and value each outcome used. */
const labels: Labels = {
    'hubv2_bulk_appearance_done': 'changed {$a}',
    'hubv2_bulk_appearance_done_one': 'changed one',
    'hubv2_bulk_appearance_none': 'nothing changed',
    'hubv2_bulk_move_done': 'moved {$a}',
    'hubv2_bulk_move_done_one': 'moved one',
    'hubv2_bulk_move_skipped': 'skipped {$a}',
    'hubv2_bulk_move_skipped_one': 'skipped one',
    'hubv2_bulk_move_refused': '{$a->name} refused: {$a->reason}',
    'hubv2_bulk_move_left': 'left {$a}',
    'hubv2_bulk_move_left_one': 'left one',
    'hubv2_bulk_delete_done': 'deleted {$a}',
    'hubv2_bulk_delete_done_one': 'deleted one',
    'hubv2_bulk_delete_kept': 'kept {$a}',
    'hubv2_delete_blocked_courses': '{$a} courses',
    'hubv2_delete_blocked_courses_one': '1 course',
    'hubv2_delete_blocked_courses_some': 'some courses',
    'hubv2_delete_blocked_badges': 'badges',
    'hubv2_bulk_selected': '{$a} selected',
    'hubv2_bulk_selected_one': '1 selected',
    'hubv2_bulk_notshown': '({$a} not shown)',
    'hubv2_bulk_notshown_one': '(1 not shown)',
};

describe('the multi-selection', () => {
    it('picks and releases a competency, which becomes the anchor', () => {
        const once = toggle(NONE, 11);
        expect(once).toEqual({ids: [11], anchor: 11, base: [11]});
        expect(toggle(once, 12).ids).toEqual([11, 12]);
        expect(toggle(toggle(once, 12), 11)).toEqual({ids: [12], anchor: 11, base: [12]});
    });

    it('extends from the anchor over the drawn competencies, the new range replacing the last one', () => {
        const order = navOrder(sample());
        // Drawn: 1, 11, 111, 12, 2, 3 (2 is closed, so 21 is not).
        const anchored = toggle(NONE, 11);
        const down = extend(anchored, order, 11, 12);
        expect(down.ids).toEqual([11, 111, 12]);
        // Back up to 111: the range is now 11..111, so 12 leaves; the anchor stays.
        expect(extend(down, order, 12, 111)).toEqual({ids: [11, 111], anchor: 11, base: [11]});
        // Past the anchor upwards.
        expect(extend(down, order, 12, 1).ids).toEqual([11, 1]);
        // A closed branch's children are not drawn, so a range never reaches them.
        expect(extend(down, order, 12, 3).ids).not.toContain(21);
    });

    it('starts a range at the item the focus leaves when no anchor is drawn, keeping the picks made before', () => {
        const order = navOrder(sample());
        const hidden = toggle(NONE, 21);
        expect(extend(hidden, order, 12, 2)).toEqual({ids: [21, 12, 2], anchor: 12, base: [21]});
    });

    it('picks the drawn competencies of the focused item\'s level under its parent, not the whole framework', () => {
        const order = navOrder(sample());
        expect(pickLevel(NONE, order, 'c12').ids).toEqual([11, 12]);
        expect(pickLevel(NONE, order, 'c1').ids).toEqual([1, 2, 3]);
        // The framework's own item picks nothing: it is never picked.
        expect(pickLevel(NONE, order, 'f')).toBe(NONE);
        // What was picked stays.
        expect(pickLevel(toggle(NONE, 111), order, 'c12').ids).toEqual([111, 11, 12]);
    });

    it('drops the ids that left the tree and keeps the same set when none did', () => {
        const tree = sample();
        const picked = toggle(toggle(toggle(NONE, 11), 111), 3);
        expect(prune(picked, tree)).toBe(picked);
        const pruned = prune(picked, removeNode(tree, 11));
        expect(pruned.ids).toEqual([3]);
        expect(pruned.anchor).toBe(3);
        expect(prune(toggle(NONE, 11), removeNode(tree, 11)).anchor).toBeNull();
    });

    it('survives closing a branch: closed children stay in the tree', () => {
        const picked = toggle(NONE, 111);
        expect(prune(picked, setExpanded(sample(), 11, false))).toBe(picked);
    });

    it('keeps only what failed, and clears to nothing', () => {
        expect(keepOnly(toggle(toggle(NONE, 11), 12), [12, 99])).toEqual({ids: [12], anchor: null, base: [12]});
        expect(NONE.ids).toEqual([]);
    });
});

/**
 * A registry context for the dock's actions.
 *
 * @param {object} extra Fields to override.
 * @returns {object} The context.
 */
const context = (extra: Partial<ActionContext> = {}): ActionContext => ({
    node: null, frameworkcanmanage: true, moving: null, first: false, last: false, layout: 'both', empty: false,
    expanding: false, picked: 2, ...extra,
});

describe('the dock\'s availability', () => {
    it('offers the three actions to a manager with picks, the destructive one last', () => {
        expect(actionsFor(['bulk'], context()).map((view) => view.spec.id)).toEqual(['bulkappearance', 'bulkmove', 'bulkdelete']);
        expect(actionsFor(['bulk'], context()).map((view) => !!view.spec.danger)).toEqual([false, false, true]);
    });

    it('offers nothing to a reader, with picks or without: the variant is absent, not disabled', () => {
        expect(actionsFor(['bulk'], context({frameworkcanmanage: false}))).toEqual([]);
        expect(actionState('bulkdelete', context({frameworkcanmanage: false, picked: 5}))).toBe('hidden');
    });

    it('offers nothing with no pick, or while a competency is being moved by picking', () => {
        expect(actionsFor(['bulk'], context({picked: 0}))).toEqual([]);
        expect(actionsFor(['bulk'], context({moving: node(11, 1)}))).toEqual([]);
    });
});

describe('the topmost picks', () => {
    it('drops a pick whose ancestor is picked, in the tree\'s order, closed branches included', () => {
        expect(treeOrder(sample())).toEqual([1, 11, 111, 12, 2, 21, 3]);
        expect(topmost(sample(), [111, 3, 11, 21])).toEqual([11, 21, 3]);
        expect(topmost(sample(), [111, 1])).toEqual([1]);
    });

    it('plans a move in the tree\'s order, skipping those already under the target', () => {
        expect(movePlan(sample(), [3, 12, 111], 1)).toEqual({move: [111, 3], skipped: [12]});
        expect(movePlan(sample(), [3, 12, 11, 111], ROOT)).toEqual({move: [11, 12], skipped: [3]});
    });

    it('refuses a target inside any subject\'s subtree, and accepts the top level', () => {
        expect(targetAllowed(sample(), [11, 3], 111)).toBe(false);
        expect(targetAllowed(sample(), [11, 3], 11)).toBe(false);
        expect(targetAllowed(sample(), [11, 3], 12)).toBe(true);
        expect(targetAllowed(sample(), [11, 3], ROOT)).toBe(true);
    });

    it('marks the move picker\'s rows for several subjects: none of theirs selectable, a shared parent refused', () => {
        /**
         * The picker's rows, with 1 and 2 opened in it.
         *
         * @param {Array} subjects The competencies moved.
         * @returns {Array} [key, disabled, current] per row.
         */
        const flags = (subjects: number[]) => parentChoices(sample(), [1, 2], subjects, 'Top')
            .map((row) => [row.key, row.disabled, row.current]);
        // 11 and 12 share parent 1: it is refused as their current parent; 11 itself too.
        expect(flags([11, 12])).toEqual([
            ['f', false, false], ['c1', true, true], ['c11', true, false], ['c12', true, false], ['c2', false, false],
            ['c21', false, false], ['c3', false, false],
        ]);
        // 11 and 3 have different parents: both parents may be chosen (the one already there is skipped).
        expect(flags([11, 3]).filter(([key]) => key === 'f' || key === 'c1')).toEqual([['f', false, false], ['c1', false, false]]);
        const hit = {id: 111, shortname: 'C111', idnumber: '', path: '', pathids: [1, 11]};
        expect(hitSelectable(hit, [11, 3], null)).toBe(false);
        expect(hitSelectable({...hit, id: 21, pathids: [2]}, [11, 3], null)).toBe(true);
        expect(hitSelectable({...hit, id: 1, pathids: []}, [11, 12], 1)).toBe(false);
    });
});

/**
 * Every field left as it is, with some chosen.
 *
 * @param {object} chosen The fields chosen.
 * @returns {object} The choices.
 */
const choices = (chosen: Partial<Record<AppearanceName, Choice>>): Record<AppearanceName, Choice> => ({
    ...Object.fromEntries(APPEARANCE_FIELDS.map((name) => [name, {mode: 'keep'}])) as Record<AppearanceName, Choice>,
    ...chosen,
});

describe('the appearance payload', () => {
    it('sends nothing for a field left as it is', () => {
        expect(appearancePayload(choices({}))).toEqual([]);
        expect(appearancePayload(choices({tag1: {mode: 'set', value: '2'}}))).toEqual([{name: 'tag1', action: 'set', value: '2'}]);
    });

    it('spells a colour as the server stores it, and sends a clear without a value', () => {
        expect(appearancePayload(choices({bgcolor: {mode: 'set', value: ' abc '}, type: {mode: 'clear'}}))).toEqual([
            {name: 'bgcolor', action: 'set', value: '#abc'},
            {name: 'type', action: 'clear', value: ''},
        ]);
    });

    it('sends nothing at all while a colour set is not a colour', () => {
        expect(appearancePayload(choices({bgcolor: {mode: 'set', value: 'red'}, tag2: {mode: 'clear'}}))).toBeNull();
        expect(appearancePayload(choices({textcolor: {mode: 'set', value: ''}}))).toBeNull();
    });
});

describe('the contrast', () => {
    it('accepts what the server accepts', () => {
        expect(normalise('#0F6CBF')).toBe('#0F6CBF');
        expect(normalise('fff')).toBe('#fff');
        ['#12345', 'red', '#ggg', 'url(x)', '#1234'].forEach((value) => expect(normalise(value)).toBe(''));
    });

    it('measures black on white as 21 and a colour on itself as 1', () => {
        expect(contrastRatio('#000', '#fff')).toBeCloseTo(21, 5);
        expect(contrastRatio('#ffffff', '#000000')).toBeCloseTo(21, 5);
        expect(contrastRatio('#0f6cbf', '#0f6cbf')).toBeCloseTo(1, 5);
    });

    it('measures two known pairs as WCAG does', () => {
        // #767676 on white is the lightest grey to pass 4.5:1 (4.54); #777777 fails it (4.48).
        expect(contrastRatio('#767676', '#ffffff')).toBeCloseTo(4.54, 2);
        expect(contrastRatio('#777777', '#ffffff')).toBeCloseTo(4.48, 2);
        // Pure red on white is 4.0.
        expect(contrastRatio('#ff0000', '#ffffff')).toBeCloseTo(4.0, 2);
    });

    it('writes a ratio rounded down, so 4.48 never reads as 4.5', () => {
        expect(ratioText(4.48)).toBe('4.4');
        expect(ratioText(21)).toBe('21.0');
        expect(contrastRatio('red', '#fff')).toBeNull();
    });
});

/**
 * A delete check with nothing in use.
 *
 * @param {object} extra Fields to override.
 * @returns {object} The check.
 */
const check = (extra: Partial<DeleteCheck> = {}): DeleteCheck => ({
    candelete: true, descendants: 0, parenthasrule: false, exact: true, templates: 0, plans: 0, courses: 0, users: 0,
    badges: false, blockedcompetencies: 0, ...extra,
});

describe('the delete confirmation and the outcomes', () => {
    it('sorts the checks into what core deletes and what it refuses, with why; an unread check is refused', () => {
        const summary = deletionSummary(labels, [
            {id: 11, name: 'A', check: check({descendants: 1, parenthasrule: true})},
            {id: 12, name: 'B', check: check({candelete: false, courses: 2, badges: true})},
            {id: 3, name: 'C', check: check({descendants: 2})},
            {id: 4, name: 'D', check: null},
        ]);
        expect(summary.allowed).toEqual([11, 3]);
        expect(summary.descendants).toBe(3);
        expect(summary.parentrule).toBe(true);
        expect(summary.refused).toEqual([
            {id: 12, name: 'B', reasons: ['2 courses', 'badges']},
            {id: 4, name: 'D', reasons: []},
        ]);
    });

    it('says a reason without a number to a manager below the site', () => {
        const summary = deletionSummary(labels, [{id: 12, name: 'B', check: check({candelete: false, exact: false, courses: 1})}]);
        expect(summary.refused[0].reasons).toEqual(['some courses']);
    });

    it('names the first five and how many more', () => {
        expect(preview(['a', 'b', 'c'])).toEqual({shown: ['a', 'b', 'c'], more: 0});
        expect(preview(['a', 'b', 'c', 'd', 'e', 'f', 'g'])).toEqual({shown: ['a', 'b', 'c', 'd', 'e'], more: 2});
    });

    it('says what an appearance change did', () => {
        expect(appearanceOutcome(labels, 0)).toBe('nothing changed');
        expect(appearanceOutcome(labels, 1)).toBe('changed one');
        expect(appearanceOutcome(labels, 3)).toBe('changed 3');
    });

    it('says what a move did, what it skipped, which one core refused and what was not tried', () => {
        expect(moveOutcome(labels, {moved: 3, skipped: 0, refused: null, left: 0})).toBe('moved 3');
        expect(moveOutcome(labels, {moved: 1, skipped: 2, refused: {name: 'X', reason: 'No.'}, left: 1}))
            .toBe('moved one skipped 2 X refused: No. left one');
    });

    it('counts the picks, and says how many sit in closed branches when some do', () => {
        expect(dockCount(labels, 1, 0)).toBe('1 selected');
        expect(dockCount(labels, 3, 0)).toBe('3 selected');
        expect(dockCount(labels, 3, 1)).toBe('3 selected (1 not shown)');
        expect(dockCount(labels, 5, 2)).toBe('5 selected (2 not shown)');
    });

    it('says what a delete did and which were kept', () => {
        expect(deleteOutcome(labels, 2, [])).toBe('deleted 2');
        expect(deleteOutcome(labels, 1, ['B', 'D'])).toBe('deleted one kept B, D');
    });
});
