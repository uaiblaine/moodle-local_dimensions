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
 * Tests for the ID number the inline editor offers: the next one when the siblings show a pattern, none
 * otherwise.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {suggestIdnumber} from '../../src/hubv2/idnumber';

describe('suggestIdnumber', () => {
    it('offers the number after the highest, on the siblings\' shared stem', () => {
        expect(suggestIdnumber(['A1', 'A2'])).toBe('A3');
        expect(suggestIdnumber(['A2', 'A1'])).toBe('A3');
        expect(suggestIdnumber(['7'])).toBe('8');
        expect(suggestIdnumber(['LA1'])).toBe('LA2');
    });

    it('increments the last part of a dotted number', () => {
        expect(suggestIdnumber(['DCE-1.1', 'DCE-1.2', 'DCE-1.3'])).toBe('DCE-1.4');
        expect(suggestIdnumber(['1.2.9', '1.2.10'])).toBe('1.2.11');
    });

    it('keeps a zero-padded width and grows an unpadded one', () => {
        expect(suggestIdnumber(['C01', 'C02'])).toBe('C03');
        expect(suggestIdnumber(['C09'])).toBe('C10');
        expect(suggestIdnumber(['C8', 'C9'])).toBe('C10');
    });

    it('skips a number the framework already uses elsewhere', () => {
        expect(suggestIdnumber(['A1', 'A2'], ['A3', 'B1'])).toBe('A4');
        expect(suggestIdnumber(['A1', 'A2'], ['A3', 'A4'])).toBe('A5');
    });

    it('offers nothing when no pattern is clear', () => {
        expect(suggestIdnumber([])).toBe('');
        expect(suggestIdnumber(['DA', 'DB'])).toBe('');
        expect(suggestIdnumber(['A1', 'B2'])).toBe('');
        expect(suggestIdnumber(['A1', 'intro'])).toBe('');
        expect(suggestIdnumber(['A1', ''])).toBe('');
    });
});
