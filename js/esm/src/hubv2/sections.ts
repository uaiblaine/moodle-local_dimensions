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
 * The companion sections of the panel as the workspace keeps them: what other plugins add for the selected
 * competency through the hook competency_panel_sections (local_dimensions_get_competency_panel_sections).
 *
 * The workspace asks when a competency is selected and after every action of the registry that changes it
 * (commands.ts `changes`), and only when the page says some plugin listens (HubProps `sections`). Each request
 * has its number; an answer or a failure of any request but the newest is dropped, so a slow answer for an
 * earlier selection never shows under a later one. A new selection clears what was shown at once; a reload of
 * the same selection keeps it until the answer replaces it. A failure clears the sections and is said in one
 * quiet line: the rest of the panel stays as it is, and no error dialogue opens.
 *
 * The answer is read again here as the server declared it (an allowlist): a malformed entry is dropped, a tone
 * outside the hook's is none, and a link is kept only when it is an http(s) address of the page's own origin.
 *
 * Pure: Jest pins it without a DOM.
 *
 * @module     local_dimensions/hubv2/sections
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {PanelSection, SectionRow} from './types';

/** The tones the hook accepts (competency_panel_sections::tones()). */
export const TONES = ['success', 'warning', 'danger', 'info', 'neutral'] as const;

/** Where the sections of the selection stand. */
export type SectionsStatus = 'idle' | 'loading' | 'loaded' | 'failed';

/** The sections the panel shows, and for which request. */
export type SectionsState = {
    // The competency they are for, null with nothing selected.
    competencyid: number | null,
    // The newest request's number: an answer to another one is stale.
    ask: number,
    status: SectionsStatus,
    sections: PanelSection[],
};

/** Nothing asked, nothing shown. */
export const NO_SECTIONS: SectionsState = {competencyid: null, ask: 0, status: 'idle', sections: []};

/** What happens to the sections. */
export type SectionsAction =
    // A request for a competency; keep says whether what is shown stays until the answer (a reload).
    | {type: 'ask', ask: number, competencyid: number, keep: boolean}
    | {type: 'answer', ask: number, sections: PanelSection[]}
    | {type: 'fail', ask: number}
    // Nothing selected, or nobody listens: nothing to show, and every request in flight is stale.
    | {type: 'clear', ask: number};

/**
 * The next state of the sections.
 *
 * @param {object} state The state.
 * @param {object} action What happened.
 * @returns {object} The next state; the same object when the action is stale.
 */
export const sectionsReducer = (state: SectionsState, action: SectionsAction): SectionsState => {
    switch (action.type) {
        case 'ask':
            return {
                competencyid: action.competencyid,
                ask: action.ask,
                status: 'loading',
                sections: action.keep && action.competencyid === state.competencyid ? state.sections : [],
            };
        case 'answer':
            return action.ask === state.ask ? {...state, status: 'loaded', sections: action.sections} : state;
        case 'fail':
            return action.ask === state.ask ? {...state, status: 'failed', sections: []} : state;
        case 'clear':
            return {competencyid: null, ask: action.ask, status: 'idle', sections: []};
        default:
            return state;
    }
};

/**
 * The sections the panel draws for a selection: the state when it is that competency's, else none, so sections of an
 * earlier selection never show under a later one even for the render before the new request is made.
 *
 * @param {object} state The state.
 * @param {number|null} selected The competency selected, null for none.
 * @returns {object|undefined} The state, or undefined.
 */
export const sectionsFor = (state: SectionsState, selected: number | null): SectionsState | undefined =>
    (selected !== null && state.competencyid === selected ? state : undefined);

/**
 * An address the panel may link to: an absolute http(s) URL of the page's own origin, as it was given.
 *
 * @param {string} value The address.
 * @param {string} origin The page's origin (window.location.origin).
 * @returns {string} The address, or '' when it is anything else.
 */
export const sameOriginHref = (value: string, origin: string): string => {
    let url: URL;
    try {
        // No base on purpose: a relative or protocol-relative address does not parse, so it is refused.
        url = new URL(value);
    } catch {
        return '';
    }
    if ((url.protocol !== 'http:' && url.protocol !== 'https:') || url.origin !== origin) {
        return '';
    }
    return value;
};

/**
 * A string field of an answer, or ''.
 *
 * @param {object} entry The entry.
 * @param {string} field The field.
 * @returns {string} Its value when it is a string.
 */
const text = (entry: Record<string, unknown>, field: string): string => {
    const value = entry[field];
    return typeof value === 'string' ? value : '';
};

/**
 * Whether a value is a plain object.
 *
 * @param {*} value The value.
 * @returns {boolean} Whether it is one.
 */
const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null && !Array.isArray(value);

/**
 * The rows of an answer's section, each with a label and a value; a tone outside the hook's is none.
 *
 * @param {*} rows The rows as received.
 * @returns {Array} The rows kept, in order.
 */
const readRows = (rows: unknown): SectionRow[] => {
    if (!Array.isArray(rows)) {
        return [];
    }
    return rows.filter(isRecord).map((row) => {
        const tone = text(row, 'tone');
        return {
            label: text(row, 'label'),
            value: text(row, 'value'),
            tone: (TONES as readonly string[]).includes(tone) ? tone : '',
        };
    }).filter((row) => row.label !== '' && row.value !== '');
};

/**
 * The sections of an answer, in the order received; an entry without a key or a title is dropped, and so is a
 * link that is not of the page's origin.
 *
 * @param {*} answer The service's sections.
 * @param {string} origin The page's origin.
 * @returns {Array} The sections kept.
 */
export const readSections = (answer: unknown, origin: string): PanelSection[] => {
    if (!Array.isArray(answer)) {
        return [];
    }
    const seen = new Set<string>();
    const kept: PanelSection[] = [];
    answer.filter(isRecord).forEach((entry) => {
        const key = text(entry, 'key');
        const title = text(entry, 'title');
        if (key === '' || title === '' || seen.has(key)) {
            return;
        }
        seen.add(key);
        const linklabel = text(entry, 'linklabel');
        const linkurl = linklabel === '' ? '' : sameOriginHref(text(entry, 'linkurl'), origin);
        kept.push({
            key,
            component: text(entry, 'component'),
            title,
            rows: readRows(entry.rows),
            note: text(entry, 'note'),
            linklabel: linkurl === '' ? '' : linklabel,
            linkurl,
        });
    });
    return kept;
};

/**
 * The status label classes of each tone, written out in full so the stylesheet contract's scanner sees every class
 * the sheet styles (stylesheet_markup_contract_test): neutral is the plain label, which has no modifier.
 */
export const TONE_CLASSES: Record<typeof TONES[number], string> = {
    success: 'local-dimensions-hubv2-status local-dimensions-hubv2-status--success',
    warning: 'local-dimensions-hubv2-status local-dimensions-hubv2-status--warning',
    danger: 'local-dimensions-hubv2-status local-dimensions-hubv2-status--danger',
    info: 'local-dimensions-hubv2-status local-dimensions-hubv2-status--info',
    neutral: 'local-dimensions-hubv2-status',
};

/**
 * The status label classes of a tone: its entry of TONE_CLASSES, the plain label for none or a tone outside it.
 *
 * @param {string} tone The tone.
 * @returns {string} The classes.
 */
export const toneClass = (tone: string): string =>
    ((TONES as readonly string[]).includes(tone) ? TONE_CLASSES[tone as typeof TONES[number]] : TONE_CLASSES.neutral);

/**
 * The id of a section's heading, for its aria-labelledby: the key is [a-z0-9_-] by the hook's rule.
 *
 * @param {string} key The section's key.
 * @returns {string} The id.
 */
export const headingId = (key: string): string => `local-dimensions-hubv2-companion-${key.replace(/[^a-z0-9_-]/g, '')}`;
