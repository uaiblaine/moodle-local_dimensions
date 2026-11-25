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
 * Tests for the workspace's keyboard, shared by the outline and the map: the walk, the keys (right to
 * left too, and repeated faster than the panes draw), type-ahead, where the focus enters and lands,
 * the map's names and when the map pans.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
    branchTarget, entryKey, isTypeAheadKey, keyAction, navOrder, nodeName, panTarget, survivorKey, typeAhead,
} from '../../src/hubv2/keynav';
import type {Labels} from '../../src/hubv2/strings';
import {ROOT, createTree, insertChildren, removeNode, setExpanded} from '../../src/hubv2/tree';
import type {StructureNode} from '../../src/hubv2/types';

/**
 * A node with the fields the walk reads.
 *
 * @param {number} id The id.
 * @param {number} parentid The parent.
 * @param {boolean} haschildren Whether it has children.
 * @returns {object} The node.
 */
const node = (id: number, parentid: number = ROOT, haschildren: boolean = false): StructureNode => ({
    id, parentid, shortname: `C${id}`, idnumber: `ID${id}`, taxonomy: 'Competency', scale: '', description: '',
    coursecount: 0, activitycount: 0, templatecount: 0, depth: 0, indent: 0, haschildren, canmanage: true,
    ruletype: null, ruleoutcome: 0, ruleconfig: null, rulelabel: '', type: '', tag1: '', tag2: '', bgcolor: '', textcolor: '',
});

/**
 * Roots 1 (open: children 11, and 12 which is closed with child 121 loaded; one more page) and 2 (a
 * leaf), and 3 (closed, nothing loaded).
 *
 * @returns {object} The tree.
 */
const sample = () => {
    let tree = createTree({items: [node(1, ROOT, true), node(2), node(3, ROOT, true)], total: 3});
    tree = insertChildren(tree, 1, {items: [node(11, 1), node(12, 1, true)], total: 30});
    tree = insertChildren(tree, 12, {items: [node(121, 12)], total: 1});
    return setExpanded(tree, 1, true);
};

/**
 * The action of a key without modifiers.
 *
 * @param {string} current The focused node.
 * @param {string} key The key.
 * @param {boolean} rtl Whether Left and Right swap.
 * @returns {object|null} The action.
 */
const press = (current: string, key: string, rtl: boolean = false) => keyAction(navOrder(sample()), current, {key}, rtl);

describe('the keyboard walk', () => {
    it('visits the framework, then what the canvas draws in tree order, "load more" included', () => {
        expect(navOrder(sample()).map((item) => `${item.key}<${item.parent}`)).toEqual([
            'f<null', 'c1<f', 'c11<c1', 'c12<c1', 'm1<c1', 'c2<f', 'c3<f',
        ]);
        expect(navOrder(sample()).find((item) => item.key === 'c12')).toMatchObject({expandable: true, expanded: false});
    });

    it('moves Down and Up through the visible items and stops at either end', () => {
        expect(press('c11', 'ArrowDown')).toEqual({type: 'focus', key: 'c12'});
        expect(press('c12', 'ArrowDown')).toEqual({type: 'focus', key: 'm1'});
        expect(press('m1', 'ArrowDown')).toEqual({type: 'focus', key: 'c2'});
        expect(press('c2', 'ArrowUp')).toEqual({type: 'focus', key: 'm1'});
        expect(press('f', 'ArrowUp')).toEqual({type: 'none'});
        expect(press('c3', 'ArrowDown')).toEqual({type: 'none'});
    });

    it('jumps Home to the framework and End to the last visible item', () => {
        expect(press('c12', 'Home')).toEqual({type: 'focus', key: 'f'});
        expect(press('c11', 'End')).toEqual({type: 'focus', key: 'c3'});
    });

    it('opens a closed parent with Right, then goes to its first child', () => {
        expect(press('c12', 'ArrowRight')).toEqual({type: 'expand', id: 12});
        expect(press('c3', 'ArrowRight')).toEqual({type: 'expand', id: 3});
        expect(press('c1', 'ArrowRight')).toEqual({type: 'focus', key: 'c11'});
        expect(press('f', 'ArrowRight')).toEqual({type: 'focus', key: 'c1'});
    });

    it('does nothing with Right on a leaf, a "load more" node or an open parent with nothing drawn', () => {
        expect(press('c11', 'ArrowRight')).toEqual({type: 'none'});
        expect(press('m1', 'ArrowRight')).toEqual({type: 'none'});
        const empty = setExpanded(insertChildren(sample(), 3, {items: [], total: 0}), 3, true);
        expect(keyAction(navOrder(empty), 'c3', {key: 'ArrowRight'})).toEqual({type: 'none'});
    });

    it('closes an open parent with Left, otherwise goes to the parent', () => {
        expect(press('c1', 'ArrowLeft')).toEqual({type: 'collapse', id: 1});
        expect(press('c12', 'ArrowLeft')).toEqual({type: 'focus', key: 'c1'});
        expect(press('c11', 'ArrowLeft')).toEqual({type: 'focus', key: 'c1'});
        expect(press('m1', 'ArrowLeft')).toEqual({type: 'focus', key: 'c1'});
        expect(press('c2', 'ArrowLeft')).toEqual({type: 'focus', key: 'f'});
        // The framework is always open and has no parent.
        expect(press('f', 'ArrowLeft')).toEqual({type: 'none'});
    });

    it('selects with Enter and Space, and loads the next page on a "load more" node', () => {
        expect(press('c12', 'Enter')).toEqual({type: 'select', id: 12});
        expect(press('c12', ' ')).toEqual({type: 'select', id: 12});
        expect(press('f', 'Enter')).toEqual({type: 'select', id: ROOT});
        expect(press('m1', 'Enter')).toEqual({type: 'loadmore', parent: 1});
        expect(press('m1', ' ')).toEqual({type: 'loadmore', parent: 1});
    });

    it('swaps Left and Right when asked, as core/tree does in a right-to-left language', () => {
        expect(press('c12', 'ArrowLeft', true)).toEqual({type: 'expand', id: 12});
        expect(press('c1', 'ArrowRight', true)).toEqual({type: 'collapse', id: 1});
        expect(press('c12', 'ArrowRight', true)).toEqual({type: 'focus', key: 'c1'});
        // Left, towards the children in a right-to-left tree, enters an open parent.
        expect(press('c1', 'ArrowLeft', true)).toEqual({type: 'focus', key: 'c11'});
        expect(press('f', 'ArrowLeft', true)).toEqual({type: 'focus', key: 'c1'});
        expect(press('f', 'ArrowRight', true)).toEqual({type: 'none'});
        // Up, Down, Home, End, Enter do not swap.
        expect(press('c11', 'ArrowDown', true)).toEqual({type: 'focus', key: 'c12'});
        expect(press('c12', 'Home', true)).toEqual({type: 'focus', key: 'f'});
        expect(press('m1', 'Enter', true)).toEqual({type: 'loadmore', parent: 1});
        // The control: the same keys left to right do the opposite.
        expect(press('c1', 'ArrowLeft')).toEqual({type: 'collapse', id: 1});
        expect(press('f', 'ArrowRight')).toEqual({type: 'focus', key: 'c1'});
    });

    it('leaves to the browser any other key and any key held with a modifier', () => {
        expect(press('c12', 'Tab')).toBeNull();
        expect(press('c12', 'a')).toBeNull();
        expect(press('c12', 'Escape')).toBeNull();
        const order = navOrder(sample());
        expect(keyAction(order, 'c12', {key: 'ArrowDown', shiftKey: true})).toBeNull();
        expect(keyAction(order, 'c12', {key: 'ArrowDown', altKey: true})).toBeNull();
        expect(keyAction(order, 'c12', {key: 'ArrowDown', ctrlKey: true})).toBeNull();
        expect(keyAction(order, 'c12', {key: 'ArrowDown', metaKey: true})).toBeNull();
        // The control: the same key without a modifier moves.
        expect(keyAction(order, 'c12', {key: 'ArrowDown'})).toEqual({type: 'focus', key: 'm1'});
    });

    it('starts again from the framework when the focused node is no longer in the walk', () => {
        expect(press('c999', 'ArrowDown')).toEqual({type: 'focus', key: 'f'});
        expect(press('c999', 'Tab')).toBeNull();
    });
});

describe('keys repeated faster than the panes draw', () => {
    /**
     * Press keys one after another, each read against the tree the previous one left, as the
     * workspace does (it reads its latest tree, not the last render), and apply what each asks.
     *
     * @param {Array} keys The keys.
     * @param {string} from The focused item.
     * @returns {Array} Where the focus is after each key, and the tree at the end.
     */
    const run = (keys: string[], from: string) => {
        let tree = sample();
        let current = from;
        const trail: string[] = [];
        keys.forEach((key) => {
            const action = keyAction(navOrder(tree), current, {key});
            if (action?.type === 'focus') {
                current = action.key;
            } else if (action?.type === 'collapse') {
                tree = setExpanded(tree, action.id, false);
            } else if (action?.type === 'expand') {
                tree = setExpanded(tree, action.id, true);
            }
            trail.push(current);
        });
        return {trail, tree};
    };

    it('walks one item a press', () => {
        expect(run(['ArrowDown', 'ArrowDown', 'ArrowDown', 'ArrowDown'], 'f').trail).toEqual(['c1', 'c11', 'c12', 'm1']);
    });

    it('closes a branch on the first Left and goes to its parent on the second, never opening it again', () => {
        const {trail, tree} = run(['ArrowLeft', 'ArrowLeft', 'ArrowLeft'], 'c1');
        expect(trail).toEqual(['c1', 'f', 'f']);
        expect(tree.expanded).not.toContain(1);
    });

    it('opens a branch on the first Right and enters it on the second', () => {
        expect(run(['ArrowRight', 'ArrowRight', 'ArrowDown'], 'c12').trail).toEqual(['c12', 'c121', 'm1']);
    });

    it('asks to open a branch again while its first page is still loading: the store must make that a no-op', () => {
        // Nothing loaded under 3: the tree does not change until the page arrives.
        const order = navOrder(sample());
        expect(keyAction(order, 'c3', {key: 'ArrowRight'})).toEqual({type: 'expand', id: 3});
        expect(keyAction(order, 'c3', {key: 'ArrowRight'})).toEqual({type: 'expand', id: 3});
    });
});

describe('type-ahead in the outline', () => {
    const names = [
        {key: 'f', name: 'Framework'},
        {key: 'c1', name: 'Alpha'},
        {key: 'c11', name: 'Beta'},
        {key: 'c12', name: 'alphabet'},
        {key: 'm1', name: ''},
        {key: 'c2', name: 'Álgebra'},
        {key: 'c3', name: 'Bravo'},
    ];

    it('moves to the next name that starts with the character, after the focused one, and wraps', () => {
        expect(typeAhead(names, 'f', 'b')).toBe('c11');
        expect(typeAhead(names, 'c11', 'b')).toBe('c3');
        expect(typeAhead(names, 'c3', 'b')).toBe('c11');
        expect(typeAhead(names, 'c1', 'a')).toBe('c12');
    });

    it('cycles through the names a repeated character starts', () => {
        expect(typeAhead(names, 'c1', 'aa')).toBe('c12');
        expect(typeAhead(names, 'c12', 'aaa')).toBe('c1');
    });

    it('refines from the focused item with several characters, and ignores case', () => {
        expect(typeAhead(names, 'c1', 'alphab')).toBe('c12');
        expect(typeAhead(names, 'c12', 'ALPHAB')).toBe('c12');
        expect(typeAhead(names, 'c1', 'alp')).toBe('c1');
    });

    it('keeps accents as typed, skips "load more" items, and finds nothing without a match', () => {
        expect(typeAhead(names, 'f', 'á')).toBe('c2');
        expect(typeAhead(names, 'f', 'z')).toBeNull();
        expect(typeAhead(names, 'f', '')).toBeNull();
        expect(typeAhead(names, 'c999', 'b')).toBe('c11');
    });

    it('takes one printable character with no modifier but Shift, and never Space', () => {
        expect(isTypeAheadKey({key: 'a'})).toBe(true);
        expect(isTypeAheadKey({key: 'A', shiftKey: true})).toBe(true);
        expect(isTypeAheadKey({key: ' '})).toBe(false);
        expect(isTypeAheadKey({key: 'a', ctrlKey: true})).toBe(false);
        expect(isTypeAheadKey({key: 'a', metaKey: true})).toBe(false);
        expect(isTypeAheadKey({key: 'a', altKey: true})).toBe(false);
        expect(isTypeAheadKey({key: 'ArrowDown'})).toBe(false);
    });
});

describe('where Tab enters the canvas', () => {
    it('is the framework when nothing is selected', () => {
        expect(entryKey(sample(), navOrder(sample()), null)).toBe('f');
    });

    it('is the selected competency when it is drawn', () => {
        expect(entryKey(sample(), navOrder(sample()), 11)).toBe('c11');
    });

    it('is the nearest drawn ancestor of a selected competency under a closed parent', () => {
        expect(entryKey(sample(), navOrder(sample()), 121)).toBe('c12');
        const closed = setExpanded(sample(), 1, false);
        expect(entryKey(closed, navOrder(closed), 121)).toBe('c1');
    });

    it('is the framework for a competency the tree does not know', () => {
        expect(entryKey(sample(), navOrder(sample()), 999)).toBe('f');
    });
});

describe('where the focus lands when its node leaves the canvas', () => {
    it('is the parent that was closed over it', () => {
        const before = navOrder(sample());
        expect(survivorKey(before, navOrder(setExpanded(sample(), 1, false)), 'c11')).toBe('c1');
    });

    it('is the parent of a deleted competency', () => {
        const before = navOrder(sample());
        expect(survivorKey(before, navOrder(removeNode(sample(), 11)), 'c11')).toBe('c1');
    });

    it('is the nearest survivor two levels up', () => {
        const open = setExpanded(sample(), 12, true);
        expect(survivorKey(navOrder(open), navOrder(setExpanded(open, 1, false)), 'c121')).toBe('c1');
    });

    it('is the node itself while it is still drawn, and the framework for one never seen', () => {
        const before = navOrder(sample());
        expect(survivorKey(before, before, 'c12')).toBe('c12');
        expect(survivorKey(before, navOrder(sample()), 'c999')).toBe('f');
    });

    it('is the parent of a "load more" node once its last page is in', () => {
        const before = navOrder(sample());
        const loaded = insertChildren(sample(), 1, {items: [node(13, 1)], total: 3}, true);
        expect(survivorKey(before, navOrder(loaded), 'm1')).toBe('c1');
    });
});

describe('a competency node\'s name', () => {
    const labels: Labels = {
        'hubv2_a11y_collapsed': 'collapsed',
        'hubv2_a11y_expanded': 'expanded',
        'hubv2_a11y_level': 'level {$a}',
        'hubv2_a11y_position': '{$a->position} of {$a->total}',
        'hubv2_a11y_related': 'related to {$a}',
        'hubv2_a11y_rule': 'rule: {$a}',
        'hubv2_children': '{$a} children',
        'hubv2_children_one': '1 child',
    };
    const base = {name: 'Domain 1', level: 1, position: 1, total: 8, expandable: false, expanded: false, children: null,
        rule: '', relatedto: null};

    it('says a leaf\'s place', () => {
        expect(nodeName(labels, base)).toBe('Domain 1, level 1, 1 of 8');
    });

    it('says whether a parent is open, how many children it has once known, and its rule', () => {
        expect(nodeName(labels, {...base, expandable: true, rule: 'All'})).toBe('Domain 1, level 1, 1 of 8, collapsed, rule: All');
        expect(nodeName(labels, {...base, expandable: true, expanded: true, children: 10}))
            .toBe('Domain 1, level 1, 1 of 8, expanded, 10 children');
        expect(nodeName(labels, {...base, expandable: true, children: 1})).toBe('Domain 1, level 1, 1 of 8, collapsed, 1 child');
    });

    it('says the relation to the selected competency', () => {
        expect(nodeName(labels, {...base, relatedto: 'Domain 2'})).toBe('Domain 1, level 1, 1 of 8, related to Domain 2');
    });
});

describe('when the view pans to a focused node', () => {
    const card = {x: 100, y: 100, width: 240, height: 76};
    const view = {width: 800, height: 600};
    const minimap = {x: 645, y: 489, width: 140, height: 96};

    it('does not pan to a node wholly visible and clear of the overlays', () => {
        expect(panTarget(card, {x: 0, y: 0, zoom: 1}, view, [minimap])).toBeNull();
    });

    it('centres on a node outside the view', () => {
        expect(panTarget(card, {x: -400, y: 0, zoom: 1}, view, [])).toEqual({x: 220, y: 138});
        expect(panTarget({...card, x: 600}, {x: 0, y: 0, zoom: 1}, view, [])).toEqual({x: 720, y: 138});
    });

    it('centres on a node under the overview map, and the control: the same node beside it does not pan', () => {
        expect(panTarget({...card, x: 500, y: 480}, {x: 0, y: 0, zoom: 1}, view, [minimap])).toEqual({x: 620, y: 518});
        expect(panTarget({...card, x: 500, y: 480}, {x: 0, y: 0, zoom: 1}, {width: 800, height: 600}, [])).toBeNull();
    });

    it('keeps a node clear of the overlays on a short canvas, where the centre falls under them', () => {
        // 360 by 240: the centred card (60..300, 82..158) meets the overview map (205..345, 129..225).
        const short = {width: 360, height: 240};
        const overlays = [{x: 205, y: 129, width: 140, height: 96}, {x: 15, y: 129, width: 32, height: 96}];
        const target = panTarget({...card, x: 1000, y: 1000}, {x: 0, y: 0, zoom: 1}, short, overlays);
        expect(target).not.toBeNull();
        // Where the card lands: the flow point at the view's centre, read back to the card's corner.
        const left = short.width / 2 - ((target?.x ?? 0) - 1000);
        const top = short.height / 2 - ((target?.y ?? 0) - 1000);
        expect({left, top}).toEqual({left: 60, top: 16});
        const box = {x: left, y: top, width: card.width, height: card.height};
        overlays.forEach((overlay) => {
            const apart = box.x + box.width <= overlay.x || overlay.x + overlay.width <= box.x
                || box.y + box.height <= overlay.y || overlay.y + overlay.height <= box.y;
            expect(apart).toBe(true);
        });
        // The control: on a tall canvas the same card is centred.
        expect(panTarget({...card, x: 1000, y: 1000}, {x: 0, y: 0, zoom: 1}, view, [minimap])).toEqual({x: 1120, y: 1038});
    });

    it('keeps a node whole at the top start corner when no spot is clear', () => {
        const tiny = {width: 300, height: 120};
        const target = panTarget({...card, x: 0, y: 0}, {x: 500, y: 500, zoom: 1}, tiny, [{x: 0, y: 0, width: 300, height: 120}]);
        expect(target).toEqual({x: 150 - 16, y: 60 - 16});
    });

    it('reads the node through the zoom and the pan', () => {
        // At zoom 2 the card is 480 pixels wide: from x 200 it ends at 680, inside 800 less the margin.
        expect(panTarget(card, {x: 0, y: 0, zoom: 2}, view, [])).toBeNull();
        expect(panTarget(card, {x: 200, y: 0, zoom: 2}, view, [])).toEqual({x: 220, y: 138});
    });
});

describe('when the map moves after a branch opens on it', () => {
    const size = {width: 240, height: 76};
    const view = {width: 800, height: 600};
    const at = (x: number, y: number) => ({x, y, ...size});

    /**
     * Where a box lands on the canvas once the map is centred on a point, at zoom 1.
     *
     * @param {object} box The box, in flow coordinates.
     * @param {object} centre The point centred on.
     * @param {object} canvas The canvas's size.
     * @returns {object} The box, in pixels.
     */
    const landed = (box: {x: number, y: number}, centre: {x: number, y: number}, canvas = view) =>
        ({x: box.x + canvas.width / 2 - centre.x, y: box.y + canvas.height / 2 - centre.y});

    it('does not move when the parent and its children already fit', () => {
        expect(branchTarget(at(100, 262), [at(420, 212), at(420, 312)], {x: 0, y: 0, zoom: 1}, view, [])).toBeNull();
    });

    it('moves the least that shows the parent and its children when they open off to the right', () => {
        const target = branchTarget(at(500, 262), [at(820, 212), at(820, 312)], {x: 0, y: 0, zoom: 1}, view, []);
        expect(target).toEqual({x: 676, y: 300});
        // The children's column ends at the margin, and the parent stays in view.
        const child = landed({x: 820, y: 212}, target as {x: number, y: number});
        expect(child.x + size.width).toBe(view.width - 16);
        expect(landed({x: 500, y: 262}, target as {x: number, y: number}).x).toBe(224);
    });

    it('mirrors right to left, where the children grow to the left of the parent', () => {
        const target = branchTarget(at(60, 262), [at(-260, 212), at(-260, 312)], {x: 0, y: 0, zoom: 1}, view, [], true);
        expect(target).toEqual({x: 124, y: 300});
        expect(landed({x: -260, y: 212}, target as {x: number, y: number}).x).toBe(16);
        // The control: the same boxes read left to right move the same way, since only their place counts.
        expect(branchTarget(at(60, 262), [at(-260, 212)], {x: 0, y: 0, zoom: 1}, view, [], false)).toEqual({x: 124, y: 300});
    });

    it('keeps the parent\'s side on a canvas narrower than parent and children together', () => {
        const narrow = {width: 500, height: 600};
        const ltr = branchTarget(at(500, 262), [at(820, 262)], {x: 0, y: 0, zoom: 1}, narrow, []);
        expect(landed({x: 500, y: 262}, ltr as {x: number, y: number}, narrow).x).toBe(16);
        const rtl = branchTarget(at(500, 262), [at(180, 262)], {x: 0, y: 0, zoom: 1}, narrow, [], true);
        expect(landed({x: 500, y: 262}, rtl as {x: number, y: number}, narrow).x + size.width).toBe(500 - 16);
    });

    it('lifts the boxes off the overview map', () => {
        const minimap = {x: 645, y: 489, width: 140, height: 96};
        const target = branchTarget(at(300, 470), [at(620, 420), at(620, 520)], {x: 0, y: 0, zoom: 1}, view, [minimap]);
        expect(target).toEqual({x: 476, y: 407});
        const child = landed({x: 620, y: 520}, target as {x: number, y: number});
        expect(child.y + size.height).toBeLessThanOrEqual(minimap.y);
    });

    it('shows the children nearest a parent of a long branch, the column reached even when the height is short', () => {
        // 25 children 100 px apart, the parent level with the middle one, the branch far to the right.
        const children = Array.from({length: 25}, (_, index) => at(1320, index * 100));
        const target = branchTarget(at(1000, 1200), children, {x: 0, y: 0, zoom: 1}, view, []) as {x: number, y: number};
        const parent = landed({x: 1000, y: 1200}, target);
        expect(parent.x).toBeGreaterThanOrEqual(16);
        expect(parent.y).toBeGreaterThanOrEqual(16);
        expect(parent.y + size.height).toBeLessThanOrEqual(view.height - 16);
        const middle = landed({x: 1320, y: 1200}, target);
        expect(middle.x + size.width).toBeLessThanOrEqual(view.width - 16);
    });
});
