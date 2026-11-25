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
 * Tests for the command palette's list: its groups, their order, and what a text keeps of them.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {flatEntries, fold, paletteGroups, searchable, stepEntry} from '../../src/hubv2/paletteitems';
import type {PaletteEntry} from '../../src/hubv2/paletteitems';

/**
 * An entry.
 *
 * @param {string} id Its id.
 * @param {string} label Its label.
 * @returns {object} The entry.
 */
const entry = (id: string, label: string): PaletteEntry => ({id, label, icon: 'fa-x'});

const input = {
    actions: [entry('edit', 'Edit'), entry('moveto', 'Move to…'), entry('rule', 'Régua')],
    commands: [entry('viewmap', 'Map'), entry('expandall', 'Expand all'), entry('summary', 'Framework summary')],
    gotos: [entry('goto-7', 'Leaf seven')],
    titles: {actions: 'On Leaf', hits: 'Go to', commands: 'Structure and view'},
};

/**
 * The groups' keys and their entries' ids, for a text.
 *
 * @param {string} query The text.
 * @returns {Array} The groups.
 */
const shape = (query: string) => paletteGroups(input, query).map((group) => [group.key, group.entries.map((item) => item.id)]);

describe('paletteGroups', () => {
    it('lists the actions and the commands for no text, and no "Go to"', () => {
        expect(shape('')).toEqual([
            ['actions', ['edit', 'moveto', 'rule']],
            ['commands', ['viewmap', 'expandall', 'summary']],
        ]);
    });

    it('filters by label, ignoring case and accents, and leaves out an emptied group', () => {
        expect(shape('M')).toEqual([['actions', ['moveto']], ['commands', ['viewmap', 'summary']]]);
        expect(shape('regua')).toEqual([['actions', ['rule']], ['goto', ['goto-7']]]);
    });

    it('adds the search\'s hits from the minimum length on, between the actions and the commands', () => {
        expect(shape('ex')).toEqual([['goto', ['goto-7']], ['commands', ['expandall']]]);
        // One character asks the server nothing: the hits held from a longer text are not shown.
        expect(shape('e').map(([key]) => key)).not.toContain('goto');
    });

    it('titles each group', () => {
        expect(paletteGroups(input, 'ex').map((group) => group.title)).toEqual(['Go to', 'Structure and view']);
    });

    it('walks every entry in the order shown', () => {
        expect(flatEntries(paletteGroups(input, 'ex')).map((item) => item.id)).toEqual(['goto-7', 'expandall']);
    });
});

describe('the palette\'s helpers', () => {
    it('fold case and accents', () => {
        expect(fold('Régua ÇÃO')).toBe('regua cao');
    });

    it('know when a text is long enough to search', () => {
        expect(searchable(' a ')).toBe(false);
        expect(searchable('ab')).toBe(true);
    });

    it('step through the entries and wrap', () => {
        expect(stepEntry(3, 0, true)).toBe(1);
        expect(stepEntry(3, 2, true)).toBe(0);
        expect(stepEntry(3, 0, false)).toBe(2);
        expect(stepEntry(3, -1, true)).toBe(0);
        expect(stepEntry(0, 0, true)).toBe(-1);
    });
});
