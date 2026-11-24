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
 * Tests for the canvas's deep link: only the address's competencyid changes, in place.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {PARAM, rememberSelection, withCompetency} from '../../src/hubv2/deeplink';

const HUB = 'https://example.com/local/dimensions/central.php?pagecontextid=12&tab=structure&view=map#structure';

describe('withCompetency', () => {
    it('adds the competency and keeps the path, the other parameters and the tab fragment', () => {
        const url = new URL(withCompetency(HUB, 42));
        expect(url.pathname).toBe('/local/dimensions/central.php');
        expect(url.searchParams.get(PARAM)).toBe('42');
        expect(url.searchParams.get('pagecontextid')).toBe('12');
        expect(url.searchParams.get('tab')).toBe('structure');
        expect(url.searchParams.get('view')).toBe('map');
        expect(url.hash).toBe('#structure');
    });

    it('replaces a competency already named, never adding a second one', () => {
        const url = new URL(withCompetency(withCompetency(HUB, 42), 7));
        expect(url.searchParams.getAll(PARAM)).toEqual(['7']);
    });

    it('removes the competency for null, and for anything that is not a positive integer', () => {
        const named = withCompetency(HUB, 42);
        [null, 0, -3, 1.5, Number.NaN].forEach((id) => {
            const url = new URL(withCompetency(named, id));
            expect(url.searchParams.has(PARAM)).toBe(false);
            expect(url.hash).toBe('#structure');
        });
    });
});

describe('rememberSelection', () => {
    beforeEach(() => {
        window.history.replaceState(null, '', '/local/dimensions/central.php?tab=structure&view=map#structure');
    });

    it('rewrites the current address in place: no history entry is added', () => {
        const length = window.history.length;
        rememberSelection(42);
        expect(window.location.search).toBe('?tab=structure&view=map&competencyid=42');
        expect(window.location.hash).toBe('#structure');
        rememberSelection(43);
        expect(window.location.search).toBe('?tab=structure&view=map&competencyid=43');
        expect(window.history.length).toBe(length);
    });

    it('drops the competency when the framework is selected', () => {
        rememberSelection(42);
        rememberSelection(null);
        expect(window.location.search).toBe('?tab=structure&view=map');
        expect(window.location.hash).toBe('#structure');
    });

    it('leaves the history alone when the address would not change', () => {
        const spy = jest.spyOn(window.history, 'replaceState');
        rememberSelection(null);
        expect(spy).not.toHaveBeenCalled();
        spy.mockRestore();
    });
});
