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
 * Tests for the canvas's tidy tree: columns by depth, leaves in order, parents between their children.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {layoutTree} from '../../src/hubv2/layout';

const GRID = {columnWidth: 300, rowHeight: 100, groupGap: 40};

describe('the tidy tree', () => {
    it('puts the root alone at the origin', () => {
        expect(layoutTree([{key: 'f', parent: null}], GRID)).toEqual({f: {x: 0, y: 0}});
    });

    it('gives every depth a column and every leaf the next row, in order', () => {
        const positions = layoutTree([
            {key: 'f', parent: null},
            {key: 'a', parent: 'f'},
            {key: 'b', parent: 'f'},
            {key: 'c', parent: 'f'},
        ], GRID);
        expect(positions.a).toEqual({x: 300, y: 0});
        expect(positions.b).toEqual({x: 300, y: 100});
        expect(positions.c).toEqual({x: 300, y: 200});
        expect(positions.f).toEqual({x: 0, y: 100});
    });

    it('centres a parent on its first and last child, and keeps subtrees apart', () => {
        const positions = layoutTree([
            {key: 'f', parent: null},
            {key: 'a', parent: 'f'},
            {key: 'a1', parent: 'a'},
            {key: 'a2', parent: 'a'},
            {key: 'a3', parent: 'a'},
            {key: 'b', parent: 'f'},
            {key: 'b1', parent: 'b'},
        ], GRID);
        expect(positions.a1).toEqual({x: 600, y: 0});
        expect(positions.a3).toEqual({x: 600, y: 200});
        expect(positions.a).toEqual({x: 300, y: 100});
        // The gap after the first area with children.
        expect(positions.b1).toEqual({x: 600, y: 340});
        expect(positions.b).toEqual({x: 300, y: 340});
        expect(positions.f.y).toBe((100 + 340) / 2);
    });

    it('adds no gap after a top-level leaf', () => {
        const positions = layoutTree([
            {key: 'f', parent: null},
            {key: 'a', parent: 'f'},
            {key: 'b', parent: 'f'},
        ], GRID);
        expect(positions.b.y - positions.a.y).toBe(100);
    });

    it('never places two nodes of a column on the same row', () => {
        const items = [{key: 'f', parent: null as string | null}];
        for (let area = 0; area < 4; area++) {
            items.push({key: `a${area}`, parent: 'f'});
            for (let child = 0; child < area; child++) {
                items.push({key: `a${area}c${child}`, parent: `a${area}`});
            }
        }
        const positions = layoutTree(items, GRID);
        const seen = new Set(Object.values(positions).map((p) => `${p.x}:${p.y}`));
        expect(seen.size).toBe(items.length);
    });

    it('places an orphan as a root below the others instead of dropping it', () => {
        const positions = layoutTree([
            {key: 'f', parent: null},
            {key: 'a', parent: 'f'},
            {key: 'stray', parent: 'gone'},
        ], GRID);
        expect(positions.stray).toEqual({x: 0, y: 100});
    });
});
