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
 * Tests for where the focus goes when a menu, the palette or "Move to" closes: never the page's body.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {drawnPane, focusReturn} from '../../src/hubv2/focusreturn';

const both = {outline: true, map: true};
const maponly = {outline: false, map: true};
const outlineonly = {outline: true, map: false};

describe('focusReturn', () => {
    it('gives a menu opened on an item back to that item, in its own pane', () => {
        expect(focusReturn({kind: 'item', pane: 'map'}, 'outline', both)).toEqual({kind: 'item', pane: 'map'});
        expect(focusReturn({kind: 'item', pane: 'outline'}, 'map', both)).toEqual({kind: 'item', pane: 'outline'});
    });

    it('moves to the pane still drawn when the item\'s pane has gone', () => {
        expect(focusReturn({kind: 'item', pane: 'outline'}, null, maponly)).toEqual({kind: 'item', pane: 'map'});
        expect(focusReturn({kind: 'item', pane: 'map'}, null, outlineonly)).toEqual({kind: 'item', pane: 'outline'});
    });

    it('gives it back to the control that opened the layer while it is on the page', () => {
        expect(focusReturn({kind: 'control', connected: true}, 'map', both)).toEqual({kind: 'control'});
        // Control: the same control gone goes to the pane that last had the focus.
        expect(focusReturn({kind: 'control', connected: false}, 'map', both)).toEqual({kind: 'item', pane: 'map'});
    });

    it('gives a layer opened by another layer to the selected item of the pane that last had the focus', () => {
        expect(focusReturn({kind: 'layer'}, 'map', both)).toEqual({kind: 'item', pane: 'map'});
        expect(focusReturn({kind: 'layer'}, 'outline', both)).toEqual({kind: 'item', pane: 'outline'});
        expect(focusReturn({kind: 'layer'}, 'map', outlineonly)).toEqual({kind: 'item', pane: 'outline'});
        expect(focusReturn({kind: 'layer'}, null, maponly)).toEqual({kind: 'item', pane: 'map'});
    });

    it('has nowhere to go only when neither pane is drawn', () => {
        expect(focusReturn({kind: 'layer'}, 'map', {outline: false, map: false})).toEqual({kind: 'none'});
    });
});

describe('drawnPane', () => {
    it('prefers the pane asked for, then the outline, then the map', () => {
        expect(drawnPane('map', both)).toBe('map');
        expect(drawnPane(null, both)).toBe('outline');
        expect(drawnPane('outline', maponly)).toBe('map');
        expect(drawnPane(null, {outline: false, map: false})).toBeNull();
    });
});
