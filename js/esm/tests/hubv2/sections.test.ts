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
 * Tests for the panel's companion sections: the order kept, nothing for none, a stale answer dropped, a failure
 * quiet, and the answer read again as an allowlist.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
    NO_SECTIONS, TONES, TONE_CLASSES, headingId, readSections, sameOriginHref, sectionsFor, sectionsReducer, toneClass,
} from '../../src/hubv2/sections';
import type {SectionsState} from '../../src/hubv2/sections';
import type {PanelSection} from '../../src/hubv2/types';

const ORIGIN = 'https://example.com';

/**
 * A section as the service answers it.
 *
 * @param {string} key The key.
 * @param {object} extra Fields to change.
 * @returns {object} The section.
 */
const section = (key: string, extra: Record<string, unknown> = {}): Record<string, unknown> => ({
    key,
    component: 'local_evidencehub',
    title: `Title ${key}`,
    rows: [{label: 'Kind', value: 'Regulatory', tone: 'info'}],
    note: '',
    linklabel: 'Edit policy',
    linkurl: `${ORIGIN}/local/evidencehub/policies.php?frameworkid=3`,
    ...extra,
});

/**
 * The state after asking for a competency.
 *
 * @param {number} competencyid The competency.
 * @param {number} ask The request's number.
 * @param {object} from The state before.
 * @param {boolean} keep Whether what is shown stays.
 * @returns {object} The state.
 */
const asked = (competencyid: number, ask: number, from: SectionsState = NO_SECTIONS, keep = false): SectionsState =>
    sectionsReducer(from, {type: 'ask', ask, competencyid, keep});

describe('reading an answer', () => {
    it('keeps the sections in the order received', () => {
        const read = readSections([section('b'), section('a'), section('c')], ORIGIN);
        expect(read.map((one) => one.key)).toEqual(['b', 'a', 'c']);
        expect(read[0]).toEqual({
            key: 'b',
            component: 'local_evidencehub',
            title: 'Title b',
            rows: [{label: 'Kind', value: 'Regulatory', tone: 'info'}],
            note: '',
            linklabel: 'Edit policy',
            linkurl: `${ORIGIN}/local/evidencehub/policies.php?frameworkid=3`,
        });
    });

    it('reads nothing from an empty or malformed answer', () => {
        expect(readSections([], ORIGIN)).toEqual([]);
        expect(readSections(null, ORIGIN)).toEqual([]);
        expect(readSections({key: 'a'}, ORIGIN)).toEqual([]);
        expect(readSections(['a', 3, null, section('', {}), section('x', {title: ''})], ORIGIN)).toEqual([]);
    });

    it('keeps the first of two sections with one key', () => {
        const read = readSections([section('a'), section('a', {title: 'Again'})], ORIGIN);
        expect(read.map((one) => one.title)).toEqual(['Title a']);
    });

    it('drops a row without a label or a value, and a tone outside the hook\'s', () => {
        const read = readSections([section('a', {rows: [
            {label: '', value: 'x'},
            {label: 'y', value: ''},
            {label: 'Kept', value: 'Yes', tone: 'primary'},
            {label: 'Toned', value: 'Due', tone: 'warning'},
            'not a row',
        ]})], ORIGIN);
        expect(read[0].rows).toEqual([{label: 'Kept', value: 'Yes', tone: ''}, {label: 'Toned', value: 'Due', tone: 'warning'}]);
    });

    it('keeps a link of the page\'s origin only, and drops its label with it', () => {
        const links = [
            'https://evil.example.org/x',
            // A script address, spelt in two parts so the linter does not read the test as one.
            ['javascript', 'alert(1)'].join(':'),
            '//example.com/x',
            '/local/x.php',
            'http://example.com/x',
            'data:text/html,hi',
        ];
        links.forEach((linkurl) => {
            const read = readSections([section('a', {linkurl})], ORIGIN);
            expect(read[0].linkurl).toBe('');
            expect(read[0].linklabel).toBe('');
        });
        expect(sameOriginHref(`${ORIGIN}/x.php?a=1`, ORIGIN)).toBe(`${ORIGIN}/x.php?a=1`);
        expect(readSections([section('a', {linklabel: ''})], ORIGIN)[0].linkurl).toBe('');
    });
});

describe('the state', () => {
    const one: PanelSection[] = readSections([section('a')], ORIGIN);
    const other: PanelSection[] = readSections([section('b')], ORIGIN);

    it('shows an answer for the newest request', () => {
        const state = sectionsReducer(asked(5, 1), {type: 'answer', ask: 1, sections: one});
        expect(state).toEqual({competencyid: 5, ask: 1, status: 'loaded', sections: one});
    });

    it('shows nothing for an empty answer', () => {
        const state = sectionsReducer(asked(5, 1), {type: 'answer', ask: 1, sections: []});
        expect(state.status).toBe('loaded');
        expect(state.sections).toEqual([]);
    });

    it('drops a stale answer for an earlier selection, whichever order the server answers in', () => {
        let state = asked(5, 1);
        state = asked(6, 2, state);
        const before = state;
        state = sectionsReducer(state, {type: 'answer', ask: 1, sections: one});
        expect(state).toBe(before);
        state = sectionsReducer(state, {type: 'answer', ask: 2, sections: other});
        expect(state.competencyid).toBe(6);
        expect(state.sections).toEqual(other);
        // An answer to the earlier request arriving after the newer one changes nothing either.
        expect(sectionsReducer(state, {type: 'answer', ask: 1, sections: one})).toBe(state);
    });

    it('drops a stale failure, and keeps the newer answer', () => {
        let state = asked(5, 1);
        state = asked(6, 2, state);
        state = sectionsReducer(state, {type: 'answer', ask: 2, sections: other});
        expect(sectionsReducer(state, {type: 'fail', ask: 1})).toBe(state);
    });

    it('fails quietly: no sections, a failed status, the request remembered', () => {
        let state = sectionsReducer(asked(5, 1), {type: 'answer', ask: 1, sections: one});
        state = asked(5, 2, state, true);
        state = sectionsReducer(state, {type: 'fail', ask: 2});
        expect(state).toEqual({competencyid: 5, ask: 2, status: 'failed', sections: []});
    });

    it('clears at once on a new selection, and keeps the shown sections through a reload of the same one', () => {
        const loaded = sectionsReducer(asked(5, 1), {type: 'answer', ask: 1, sections: one});
        expect(asked(5, 2, loaded, true).sections).toEqual(one);
        expect(asked(6, 2, loaded, true).sections).toEqual([]);
        expect(asked(5, 2, loaded, false).sections).toEqual([]);
    });

    it('is drawn only for the competency it belongs to', () => {
        const state = sectionsReducer(asked(5, 1), {type: 'answer', ask: 1, sections: one});
        expect(sectionsFor(state, 5)).toBe(state);
        expect(sectionsFor(state, 6)).toBeUndefined();
        expect(sectionsFor(state, null)).toBeUndefined();
        expect(sectionsFor(NO_SECTIONS, null)).toBeUndefined();
    });

    it('clears with nothing selected, and an answer in flight is then stale', () => {
        let state = asked(5, 1);
        state = sectionsReducer(state, {type: 'clear', ask: 2});
        expect(state).toEqual({competencyid: null, ask: 2, status: 'idle', sections: []});
        expect(sectionsReducer(state, {type: 'answer', ask: 1, sections: one})).toBe(state);
    });
});

describe('the markup helpers', () => {
    it('draws a tone as a status label modifier, none and neutral as the plain label', () => {
        expect(toneClass('')).toBe('local-dimensions-hubv2-status');
        expect(toneClass('neutral')).toBe('local-dimensions-hubv2-status');
        expect(toneClass('primary')).toBe('local-dimensions-hubv2-status');
        expect(toneClass('danger')).toBe('local-dimensions-hubv2-status local-dimensions-hubv2-status--danger');
    });

    it('writes one literal class list per tone of the hook, each with its own modifier', () => {
        expect(Object.keys(TONE_CLASSES).sort()).toEqual([...TONES].sort());
        TONES.filter((tone) => tone !== 'neutral').forEach((tone) => {
            expect(TONE_CLASSES[tone]).toBe(`local-dimensions-hubv2-status local-dimensions-hubv2-status--${tone}`);
        });
    });

    it('builds a heading id from the key alone', () => {
        expect(headingId('local_evidencehub-policy')).toBe('local-dimensions-hubv2-companion-local_evidencehub-policy');
        expect(headingId('a"><b')).toBe('local-dimensions-hubv2-companion-ab');
    });
});
