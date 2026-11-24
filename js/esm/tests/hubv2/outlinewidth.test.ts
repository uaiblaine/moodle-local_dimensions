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
 * Tests for the splitter between the outline and the map: the width's bounds, its keys, a drag, the
 * right-to-left mirror and the kept width.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
    MAP_RESERVE, OUTLINE_WIDTH, STORAGE_KEY, clampWidth, dragWidth, keyWidth, storeWidth, storedWidth, upperBound,
} from '../../src/hubv2/outlinewidth';

describe('the outline\'s width', () => {
    it('is held between its minimum and its maximum', () => {
        expect(clampWidth(100, 0)).toBe(OUTLINE_WIDTH.minimum);
        expect(clampWidth(900, 0)).toBe(OUTLINE_WIDTH.maximum);
        expect(clampWidth(300.4, 0)).toBe(300);
        expect(clampWidth(Number.NaN, 0)).toBe(OUTLINE_WIDTH.initial);
    });

    it('leaves the map its reserve when the panes are narrow, never going under the minimum', () => {
        expect(upperBound(800)).toBe(800 - MAP_RESERVE);
        expect(clampWidth(560, 800)).toBe(480);
        expect(upperBound(400)).toBe(OUTLINE_WIDTH.minimum);
        expect(upperBound(2000)).toBe(OUTLINE_WIDTH.maximum);
    });
});

describe('the splitter\'s keys', () => {
    it('widen with Right and narrow with Left, a step at a time, inside the bounds', () => {
        expect(keyWidth('ArrowRight', 264, 1200, false)).toBe(288);
        expect(keyWidth('ArrowLeft', 264, 1200, false)).toBe(240);
        expect(keyWidth('ArrowLeft', 210, 1200, false)).toBe(OUTLINE_WIDTH.minimum);
        expect(keyWidth('ArrowRight', 550, 1200, false)).toBe(OUTLINE_WIDTH.maximum);
    });

    it('go to the limits with Home and End', () => {
        expect(keyWidth('Home', 300, 1200, false)).toBe(OUTLINE_WIDTH.minimum);
        expect(keyWidth('End', 300, 1200, false)).toBe(OUTLINE_WIDTH.maximum);
        expect(keyWidth('End', 300, 700, false)).toBe(700 - MAP_RESERVE);
    });

    it('mirror in a right-to-left page, where the outline is at the right', () => {
        expect(keyWidth('ArrowLeft', 264, 1200, true)).toBe(288);
        expect(keyWidth('ArrowRight', 264, 1200, true)).toBe(240);
    });

    it('leave every other key alone', () => {
        expect(keyWidth('ArrowUp', 264, 1200, false)).toBeNull();
        expect(keyWidth('Enter', 264, 1200, false)).toBeNull();
    });
});

describe('a drag of the splitter', () => {
    it('adds the pointer\'s travel towards the map, from the grab point, held to the bounds', () => {
        expect(dragWidth(264, 500, 540, 1200, false)).toBe(304);
        expect(dragWidth(264, 500, 300, 1200, false)).toBe(OUTLINE_WIDTH.minimum);
        expect(dragWidth(264, 500, 540, 1200, true)).toBe(224);
    });
});

describe('the kept width', () => {
    beforeEach(() => window.localStorage.clear());

    it('is the default until one is kept, then the one kept, until it is forgotten', () => {
        expect(storedWidth()).toBe(OUTLINE_WIDTH.initial);
        storeWidth(333.6);
        expect(window.localStorage.getItem(STORAGE_KEY)).toBe('334');
        expect(storedWidth()).toBe(334);
        storeWidth(null);
        expect(storedWidth()).toBe(OUTLINE_WIDTH.initial);
    });

    it('is named as the hub\'s other pane widths are', () => {
        expect(STORAGE_KEY).toMatch(/^local_dimensions_[a-z0-9_]+_width$/);
    });
});
