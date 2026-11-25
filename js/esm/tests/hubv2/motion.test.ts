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
 * Tests for the canvas's motion: instant under reduced motion, as asked otherwise.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {REDUCED_MOTION, motionDuration, prefersReducedMotion} from '../../src/hubv2/motion';

/**
 * Make the system answer the reduced-motion query.
 *
 * @param {boolean} reduce Whether the viewer asked for reduced motion.
 * @returns {jest.Mock} The matchMedia stand-in.
 */
const system = (reduce: boolean): jest.Mock => {
    const matchMedia = jest.fn((query: string) => ({matches: reduce && query === REDUCED_MOTION, media: query}));
    Object.defineProperty(window, 'matchMedia', {configurable: true, writable: true, value: matchMedia});
    return matchMedia;
};

describe('motion', () => {
    it('keeps the duration when the viewer has not asked for reduced motion', () => {
        system(false);
        expect(prefersReducedMotion()).toBe(false);
        expect(motionDuration(300)).toBe(300);
    });

    it('makes every move instant under reduced motion, read at the time of the move', () => {
        const matchMedia = system(true);
        expect(motionDuration(300)).toBe(0);
        expect(matchMedia).toHaveBeenCalledWith('(prefers-reduced-motion: reduce)');
        system(false);
        expect(motionDuration(300)).toBe(300);
    });
});
