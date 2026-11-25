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
 * Tests for the move confirmation: it names every rule core will reset, and only those.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {moveBody} from '../../src/hubv2/move';
import type {Labels} from '../../src/hubv2/strings';

const labels: Labels = {
    'hubv2_moveconfirm': 'MOVE {$a->name} UNDER {$a->parent}?',
    'hubv2_moveconfirmroot': 'MOVE {$a} TO TOP?',
    'hubv2_moverule_newparent': 'NEW {$a} LOSES ITS RULE.',
    'hubv2_moverule_oldparent': 'OLD {$a} LOSES ITS RULE.',
};

const ruled = (shortname: string) => ({shortname, ruletype: 'core_competency\\competency_rule_points'});
const plain = (shortname: string) => ({shortname, ruletype: null});

describe('moveBody', () => {
    it('says nothing about rules when neither parent has one', () => {
        expect(moveBody(labels, 'Leaf', plain('Old'), plain('New'))).toBe('<p>MOVE Leaf UNDER New?</p>');
        expect(moveBody(labels, 'Leaf', null, null)).toBe('<p>MOVE Leaf TO TOP?</p>');
        expect(moveBody(labels, 'Leaf', {shortname: 'Old', ruletype: ''}, null)).toBe('<p>MOVE Leaf TO TOP?</p>');
    });

    it('names the current parent when it has a rule', () => {
        expect(moveBody(labels, 'Leaf', ruled('Old'), null)).toBe('<p>MOVE Leaf TO TOP?</p><p>OLD Old LOSES ITS RULE.</p>');
    });

    it('names the new parent when it has a rule', () => {
        expect(moveBody(labels, 'Leaf', null, ruled('New')))
            .toBe('<p>MOVE Leaf UNDER New?</p><p>NEW New LOSES ITS RULE.</p>');
    });

    it('names both, the current parent first', () => {
        expect(moveBody(labels, 'Leaf', ruled('Old'), ruled('New')))
            .toBe('<p>MOVE Leaf UNDER New?</p><p>OLD Old LOSES ITS RULE.</p><p>NEW New LOSES ITS RULE.</p>');
    });

    it('escapes every name it puts in the dialogue', () => {
        expect(moveBody(labels, '<b>', ruled('A&B'), ruled('"C"')))
            .toBe('<p>MOVE &lt;b&gt; UNDER &quot;C&quot;?</p>'
                + '<p>OLD A&amp;B LOSES ITS RULE.</p><p>NEW &quot;C&quot; LOSES ITS RULE.</p>');
    });
});
