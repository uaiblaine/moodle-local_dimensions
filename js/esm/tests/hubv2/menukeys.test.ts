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
 * Tests for a menu's keyboard, as a reducer, and its place under its anchor.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {firstActive, menuKey, placeMenu} from '../../src/hubv2/menukeys';

const items = [
    {label: 'Edit', disabled: false},
    {label: 'Rule', disabled: true},
    {label: 'Move up', disabled: false},
    {label: 'Move down', disabled: false},
    {label: 'Delete', disabled: false},
];

describe('menuKey', () => {
    it('opens on the first item that can run', () => {
        expect(firstActive(items)).toBe(0);
        expect(firstActive([{label: 'A', disabled: true}, {label: 'B', disabled: false}])).toBe(1);
        expect(firstActive([{label: 'A', disabled: true}])).toBe(0);
        expect(firstActive([])).toBe(-1);
    });

    it('moves with Up and Down, wrapping, and reaches a disabled item', () => {
        expect(menuKey(items, 0, {key: 'ArrowDown'}).active).toBe(1);
        expect(menuKey(items, 4, {key: 'ArrowDown'}).active).toBe(0);
        expect(menuKey(items, 0, {key: 'ArrowUp'}).active).toBe(4);
    });

    it('goes to the ends with Home and End', () => {
        expect(menuKey(items, 2, {key: 'Home'}).active).toBe(0);
        expect(menuKey(items, 2, {key: 'End'}).active).toBe(4);
    });

    it('runs the item with Enter or Space, unless it is disabled', () => {
        expect(menuKey(items, 2, {key: 'Enter'}).run).toBe(2);
        expect(menuKey(items, 2, {key: ' '}).run).toBe(2);
        const refused = menuKey(items, 1, {key: 'Enter'});
        expect(refused.run).toBeNull();
        expect(refused.handled).toBe(true);
        expect(refused.close).toBe(false);
    });

    it('closes with Escape and Tab', () => {
        expect(menuKey(items, 2, {key: 'Escape'}).close).toBe(true);
        expect(menuKey(items, 2, {key: 'Tab'}).close).toBe(true);
        expect(menuKey(items, 2, {key: 'Tab', shiftKey: true}).close).toBe(true);
    });

    it('moves to the next label a typed character starts, and cycles on repeats', () => {
        expect(menuKey(items, 0, {key: 'm'}, 'm').active).toBe(2);
        expect(menuKey(items, 2, {key: 'm'}, 'mm').active).toBe(3);
        expect(menuKey(items, 3, {key: 'm'}, 'mmm').active).toBe(2);
        expect(menuKey(items, 0, {key: 'd'}, 'd').active).toBe(4);
        expect(menuKey(items, 2, {key: 'd'}, 'move d').active).toBe(3);
        // No match keeps the place.
        expect(menuKey(items, 2, {key: 'z'}, 'z').active).toBe(2);
    });

    it('leaves other keys, and keys held with Ctrl or Alt, to the page', () => {
        expect(menuKey(items, 0, {key: 'F5'}).handled).toBe(false);
        expect(menuKey(items, 0, {key: 'k', ctrlKey: true}, 'k').handled).toBe(false);
        expect(menuKey(items, 0, {key: 'ArrowDown', altKey: true}).handled).toBe(false);
    });
});

describe('placeMenu', () => {
    const view = {width: 1000, height: 800};
    const size = {width: 220, height: 300};

    it('hangs under the anchor\'s real bottom, its end on the anchor\'s end', () => {
        const anchor = {left: 500, top: 100, right: 532, bottom: 132};
        expect(placeMenu(anchor, size, view, null, false)).toEqual({left: 312, top: 138, above: false});
    });

    it('hangs from the anchor\'s start right to left', () => {
        const anchor = {left: 500, top: 100, right: 532, bottom: 132};
        expect(placeMenu(anchor, size, view, null, true).left).toBe(500);
    });

    it('goes above when below would leave the viewport', () => {
        const anchor = {left: 500, top: 600, right: 532, bottom: 632};
        expect(placeMenu(anchor, size, view, null, false)).toEqual({left: 312, top: 294, above: true});
    });

    it('goes above when below would leave the anchor\'s pane, though the viewport has room', () => {
        const anchor = {left: 500, top: 400, right: 532, bottom: 432};
        expect(placeMenu(anchor, size, view, null, false).above).toBe(false);
        expect(placeMenu(anchor, size, view, {left: 0, top: 0, right: 1000, bottom: 500}, false).above).toBe(true);
    });

    it('stays inside the viewport at the edges', () => {
        const left = placeMenu({left: 0, top: 10, right: 30, bottom: 40}, size, view, null, false);
        expect(left.left).toBe(8);
        const right = placeMenu({left: 980, top: 10, right: 1010, bottom: 40}, size, view, null, true);
        expect(right.left).toBe(1000 - 8 - 220);
        // Neither side fits: the roomier one, held on the screen.
        const tall = placeMenu({left: 500, top: 390, right: 532, bottom: 410}, {width: 220, height: 700}, view, null, false);
        expect(tall.top).toBeGreaterThanOrEqual(8);
        expect(tall.top + 700).toBeLessThanOrEqual(800 - 8 + 0.5);
    });
});
