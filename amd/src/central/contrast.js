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
 * Real-time WCAG contrast panel for the bg/text colour custom-field inputs.
 *
 * Renders a panel beside the two colour inputs in the competency and template
 * dynamic forms that grades the contrast between them (ratio, verdict, WCAG AA
 * and AAA badges) and, when the pair fails AA, offers up to two one-click fixes.
 * It only reads and writes the two hex text inputs — it never touches how the
 * form saves. Wired from definition_after_data() via js_call_amd, alongside the
 * decorative colour_swatch module which owns the per-input swatch.
 *
 * The contrast maths follow WCAG 2.x exactly (sRGB linearisation, relative
 * luminance, (L1+0.05)/(L2+0.05)). Thresholds are fixed to normal text for now
 * (AA 4.5:1, AAA 7:1).
 *
 * @module     local_dimensions/central/contrast
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Templates from 'core/templates';
import {getStrings} from 'core/str';
import Notification from 'core/notification';

/** @var {RegExp} Matches a #RGB or #RRGGBB hex colour. */
const HEXRE = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i;

/** @var {Object} WCAG thresholds for the current (normal) text size. */
const THRESHOLDS = {aa: 4.5, aaa: 7, attention: 3};

/** @var {Object} Glyphs used for the verdict pill and the badge state icons. */
const GLYPH = {pass: '✓', warn: '!', fail: '✕', muted: '–'};

/** @var {Object} Maps a badge state to its DS feedback tone. */
const TONE_BY_STATE = {pass: 'success', fail: 'danger', muted: 'muted'};

/** @var {?Object} Cached lang strings used by the panel, keyed by short name. */
let labels = null;

/**
 * Parse a #RGB or #RRGGBB string into 8-bit channels.
 *
 * @param {String} value Raw input value.
 * @return {?Object} {r, g, b} or null when the value is not a valid hex colour.
 */
const parseHex = (value) => {
    const match = HEXRE.exec((value || '').trim());
    if (!match) {
        return null;
    }
    let hex = match[1];
    if (hex.length === 3) {
        hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
    }
    return {
        r: parseInt(hex.slice(0, 2), 16),
        g: parseInt(hex.slice(2, 4), 16),
        b: parseInt(hex.slice(4, 6), 16),
    };
};

/**
 * Linearise one 8-bit sRGB channel.
 *
 * @param {Number} channel Channel value 0-255.
 * @return {Number} Linear-light value 0-1.
 */
const linearise = (channel) => {
    const c = channel / 255;
    return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
};

/**
 * Relative luminance of an sRGB colour.
 *
 * @param {Object} rgb {r, g, b} channels 0-255.
 * @return {Number} Relative luminance 0-1.
 */
const luminance = (rgb) => 0.2126 * linearise(rgb.r) + 0.7152 * linearise(rgb.g) + 0.0722 * linearise(rgb.b);

/**
 * WCAG contrast ratio between two colours.
 *
 * @param {Object} rgb1 First colour {r, g, b}.
 * @param {Object} rgb2 Second colour {r, g, b}.
 * @return {Number} Contrast ratio in the range 1-21.
 */
const contrast = (rgb1, rgb2) => {
    const l1 = luminance(rgb1);
    const l2 = luminance(rgb2);
    return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
};

/**
 * Convert an sRGB colour to HSL.
 *
 * @param {Object} rgb {r, g, b} channels 0-255.
 * @return {Object} {h: 0-360, s: 0-100, l: 0-100}.
 */
const rgbToHsl = (rgb) => {
    const r = rgb.r / 255;
    const g = rgb.g / 255;
    const b = rgb.b / 255;
    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    const delta = max - min;
    let h = 0;
    if (delta !== 0) {
        if (max === r) {
            h = ((g - b) / delta) % 6;
        } else if (max === g) {
            h = (b - r) / delta + 2;
        } else {
            h = (r - g) / delta + 4;
        }
        h *= 60;
        if (h < 0) {
            h += 360;
        }
    }
    const l = (max + min) / 2;
    const s = delta === 0 ? 0 : delta / (1 - Math.abs(2 * l - 1));
    return {h: h, s: s * 100, l: l * 100};
};

/**
 * Convert an HSL colour to sRGB.
 *
 * @param {Object} hsl {h: 0-360, s: 0-100, l: 0-100}.
 * @return {Object} {r, g, b} channels 0-255.
 */
const hslToRgb = (hsl) => {
    const s = hsl.s / 100;
    const l = hsl.l / 100;
    const c = (1 - Math.abs(2 * l - 1)) * s;
    const x = c * (1 - Math.abs(((hsl.h / 60) % 2) - 1));
    const m = l - c / 2;
    let r = 0;
    let g = 0;
    let b = 0;
    if (hsl.h < 60) {
        r = c;
        g = x;
    } else if (hsl.h < 120) {
        r = x;
        g = c;
    } else if (hsl.h < 180) {
        g = c;
        b = x;
    } else if (hsl.h < 240) {
        g = x;
        b = c;
    } else if (hsl.h < 300) {
        r = x;
        b = c;
    } else {
        r = c;
        b = x;
    }
    return {
        r: Math.round((r + m) * 255),
        g: Math.round((g + m) * 255),
        b: Math.round((b + m) * 255),
    };
};

/**
 * Format an sRGB colour as a #RRGGBB string.
 *
 * @param {Object} rgb {r, g, b} channels 0-255.
 * @return {String} Lowercase #RRGGBB hex.
 */
const rgbToHex = (rgb) => '#' + [rgb.r, rgb.g, rgb.b]
    .map((v) => v.toString(16).padStart(2, '0'))
    .join('');

/**
 * Format a contrast ratio as "4.59:1" (two decimals, or one for values >= ~10).
 *
 * @param {Number} ratio Raw contrast ratio.
 * @return {String} The ratio followed by ":1".
 */
const formatRatio = (ratio) => (ratio >= 9.995 ? ratio.toFixed(1) : ratio.toFixed(2)) + ':1';

/**
 * Find a replacement colour that reaches the target ratio against a fixed colour.
 *
 * Keeps the hue and saturation of the colour being adjusted and walks lightness
 * from 0 to 100, choosing the lightness closest to the original that meets the
 * target contrast.
 *
 * @param {Object} fixedrgb The colour that stays put {r, g, b}.
 * @param {Object} adjustrgb The colour being adjusted {r, g, b}.
 * @param {Number} target Target contrast ratio (AA for the current text size).
 * @return {?Object} {hex, ratio} of the suggestion, or null when none reaches the target.
 */
const suggest = (fixedrgb, adjustrgb, target) => {
    const hsl = rgbToHsl(adjustrgb);
    let best = null;
    for (let l = 0; l <= 100; l++) {
        const candidate = hslToRgb({h: hsl.h, s: hsl.s, l: l});
        const ratio = contrast(fixedrgb, candidate);
        if (ratio < target) {
            continue;
        }
        const distance = Math.abs(l - hsl.l);
        if (best === null || distance < best.distance) {
            best = {distance: distance, hex: rgbToHex(candidate), ratio: ratio};
        }
    }
    return best;
};

/**
 * Resolve the verdict for a ratio: label key, DS feedback tone, and glyph.
 *
 * @param {Number} ratio Contrast ratio.
 * @return {Object} {key, tone, glyph} where tone is success|warning|danger.
 */
const verdictFor = (ratio) => {
    if (ratio >= THRESHOLDS.aaa) {
        return {key: 'excellent', tone: 'success', glyph: GLYPH.pass};
    }
    if (ratio >= THRESHOLDS.aa) {
        return {key: 'pass', tone: 'success', glyph: GLYPH.pass};
    }
    if (ratio >= THRESHOLDS.attention) {
        return {key: 'attention', tone: 'warning', glyph: GLYPH.warn};
    }
    return {key: 'fail', tone: 'danger', glyph: GLYPH.fail};
};

/**
 * Apply a feedback tone class (is-success/is-warning/is-danger/is-muted) to an element.
 *
 * @param {HTMLElement} element The element to tone.
 * @param {String} tone success|warning|danger|muted.
 */
const setTone = (element, tone) => {
    element.classList.remove('is-success', 'is-warning', 'is-danger', 'is-muted');
    element.classList.add('is-' + tone);
};

/**
 * Build one suggestion row (swatch, label, new hex + ratio, Apply button).
 *
 * @param {Object} option {label, hex, ratio, apply} for the suggestion.
 * @param {Function} onapply Called with the option when Apply is pressed.
 * @return {HTMLElement} The suggestion row element.
 */
const buildSuggestionRow = (option, onapply) => {
    const row = document.createElement('div');
    row.className = 'local-dimensions-contrast-suggestion';

    const swatch = document.createElement('span');
    swatch.className = 'local-dimensions-contrast-suggestion-swatch';
    swatch.style.backgroundColor = option.hex;

    const text = document.createElement('span');
    text.className = 'local-dimensions-contrast-suggestion-text';
    const which = document.createElement('span');
    which.className = 'local-dimensions-contrast-suggestion-which';
    which.textContent = option.label;
    const meta = document.createElement('span');
    meta.className = 'local-dimensions-contrast-suggestion-meta';
    meta.textContent = option.hex + ' · ' + formatRatio(option.ratio);
    text.appendChild(which);
    text.appendChild(meta);

    const apply = document.createElement('button');
    apply.type = 'button';
    apply.className = 'local-dimensions-contrast-suggestion-apply btn btn-sm btn-primary';
    apply.textContent = labels.apply;
    apply.setAttribute('aria-label', labels.apply + ': ' + option.label);
    apply.addEventListener('click', () => onapply(option));

    row.appendChild(swatch);
    row.appendChild(text);
    row.appendChild(apply);
    return row;
};

/**
 * Panel controller: caches its DOM hooks and applies computed state to them.
 *
 * @param {HTMLElement} panel The rendered panel root.
 * @param {HTMLInputElement} bginput The background-colour input.
 * @param {HTMLInputElement} textinput The text-colour input.
 * @return {Object} An object with an update() method.
 */
const controller = (panel, bginput, textinput) => {
    const dom = {
        ratio: panel.querySelector('[data-region="contrast-ratio"]'),
        verdict: panel.querySelector('[data-region="contrast-verdict"]'),
        verdictglyph: panel.querySelector('[data-region="contrast-verdict-glyph"]'),
        verdictlabel: panel.querySelector('[data-region="contrast-verdict-label"]'),
        aa: panel.querySelector('[data-region="contrast-aa"]'),
        aaglyph: panel.querySelector('[data-region="contrast-aa-glyph"]'),
        aamin: panel.querySelector('[data-region="contrast-aa-min"]'),
        aaname: panel.querySelector('[data-region="contrast-aa"] .local-dimensions-contrast-badge-name'),
        aaa: panel.querySelector('[data-region="contrast-aaa"]'),
        aaaglyph: panel.querySelector('[data-region="contrast-aaa-glyph"]'),
        aaamin: panel.querySelector('[data-region="contrast-aaa-min"]'),
        aaaname: panel.querySelector('[data-region="contrast-aaa"] .local-dimensions-contrast-badge-name'),
        suggestions: panel.querySelector('[data-region="contrast-suggestions"]'),
        suggestionstitle: panel.querySelector('[data-region="contrast-suggestions-title"]'),
        list: panel.querySelector('[data-region="contrast-suggestions-list"]'),
        note: panel.querySelector('[data-region="contrast-note"]'),
        noteglyph: panel.querySelector('[data-region="contrast-note-glyph"]'),
        notetext: panel.querySelector('[data-region="contrast-note-text"]'),
    };

    /**
     * Set the verdict pill's tone, glyph and label.
     *
     * @param {String} tone success|warning|danger|muted.
     * @param {String} glyph Icon character (empty for muted).
     * @param {String} label Verdict text.
     */
    const setVerdict = (tone, glyph, label) => {
        setTone(dom.verdict, tone);
        dom.verdictglyph.textContent = glyph;
        dom.verdictlabel.textContent = label;
    };

    /**
     * Set one WCAG badge (AA or AAA) to pass, fail or muted.
     *
     * @param {HTMLElement} badge The badge wrapper.
     * @param {HTMLElement} glyph The badge glyph element.
     * @param {HTMLElement} min The badge minimum-ratio element.
     * @param {HTMLElement} name The badge name element.
     * @param {String} state pass|fail|muted.
     * @param {Number} threshold The badge's minimum ratio.
     */
    const setBadge = (badge, glyph, min, name, state, threshold) => {
        setTone(badge, TONE_BY_STATE[state] || 'muted');
        glyph.textContent = GLYPH[state] || GLYPH.muted;
        min.textContent = labels.min + ' ' + formatRatio(threshold);
        let status = '—';
        if (state === 'pass') {
            status = labels.pass;
        } else if (state === 'fail') {
            status = labels.fail;
        }
        badge.setAttribute('aria-label', name.textContent.trim() + ': ' + status);
    };

    /**
     * Show or hide the fallback note.
     *
     * @param {?String} tone success|warning|danger|muted, or null to hide.
     * @param {String} glyph Icon character (empty for none).
     * @param {String} text Note text.
     */
    const setNote = (tone, glyph, text) => {
        if (tone === null) {
            dom.note.hidden = true;
            return;
        }
        setTone(dom.note, tone);
        dom.noteglyph.textContent = glyph;
        dom.notetext.textContent = text;
        dom.note.hidden = false;
    };

    /**
     * Write a suggestion into its input and re-run the swatch + panel updates.
     *
     * @param {Object} option {hex, apply} where apply is 'text' or 'bg'.
     */
    const applyOption = (option) => {
        const input = option.apply === 'text' ? textinput : bginput;
        input.value = option.hex;
        input.dispatchEvent(new Event('input', {bubbles: true}));
        input.dispatchEvent(new Event('change', {bubbles: true}));
        input.focus();
    };

    /**
     * Populate the suggestions block for a failing pair. Returns whether any were shown.
     *
     * @param {Object} bg Background colour {r, g, b}.
     * @param {Object} text Text colour {r, g, b}.
     * @return {Boolean} True when at least one suggestion was rendered.
     */
    const renderSuggestions = (bg, text) => {
        dom.list.textContent = '';
        const options = [];
        const fixText = suggest(bg, text, THRESHOLDS.aa);
        if (fixText) {
            options.push({label: labels.suggesttext, hex: fixText.hex, ratio: fixText.ratio, apply: 'text'});
        }
        const fixBg = suggest(text, bg, THRESHOLDS.aa);
        if (fixBg) {
            options.push({label: labels.suggestbg, hex: fixBg.hex, ratio: fixBg.ratio, apply: 'bg'});
        }
        if (!options.length) {
            dom.suggestions.hidden = true;
            return false;
        }
        dom.suggestionstitle.textContent = labels.suggestheading;
        options.forEach((option) => dom.list.appendChild(buildSuggestionRow(option, applyOption)));
        dom.suggestions.hidden = false;
        return true;
    };

    /**
     * Recompute everything from the current input values.
     */
    const update = () => {
        const bg = parseHex(bginput.value);
        const text = parseHex(textinput.value);

        if (!bg || !text) {
            panel.classList.add('local-dimensions-contrast--invalid');
            dom.ratio.textContent = '—';
            setVerdict('muted', '', '—');
            setBadge(dom.aa, dom.aaglyph, dom.aamin, dom.aaname, 'muted', THRESHOLDS.aa);
            setBadge(dom.aaa, dom.aaaglyph, dom.aaamin, dom.aaaname, 'muted', THRESHOLDS.aaa);
            dom.suggestions.hidden = true;
            dom.list.textContent = '';
            setNote('muted', '', labels.invalidhex);
            return;
        }

        panel.classList.remove('local-dimensions-contrast--invalid');
        const ratio = contrast(bg, text);
        dom.ratio.textContent = formatRatio(ratio);

        const verdict = verdictFor(ratio);
        setVerdict(verdict.tone, verdict.glyph, labels[verdict.key]);

        setBadge(dom.aa, dom.aaglyph, dom.aamin, dom.aaname, ratio >= THRESHOLDS.aa ? 'pass' : 'fail', THRESHOLDS.aa);
        setBadge(dom.aaa, dom.aaaglyph, dom.aaamin, dom.aaaname, ratio >= THRESHOLDS.aaa ? 'pass' : 'fail', THRESHOLDS.aaa);

        if (ratio >= THRESHOLDS.aa) {
            dom.suggestions.hidden = true;
            dom.list.textContent = '';
            setNote(null, '', '');
            return;
        }

        if (renderSuggestions(bg, text)) {
            setNote(null, '', '');
        } else {
            setNote('danger', GLYPH.fail, labels.belowaa);
        }
    };

    return {update: update};
};

/**
 * Move the two colour fitems into a left column and mount the panel beside them.
 *
 * @param {HTMLInputElement} bginput The background-colour input.
 * @param {HTMLInputElement} textinput The text-colour input.
 * @param {HTMLElement} aside The panel wrapper to place on the right.
 */
const layout = (bginput, textinput, aside) => {
    const bgfitem = bginput.closest('.fitem') || bginput.closest('.form-group');
    const textfitem = textinput.closest('.fitem') || textinput.closest('.form-group');
    if (!bgfitem || !textfitem || !bgfitem.parentNode) {
        (bgfitem || bginput).insertAdjacentElement('afterend', aside);
        return;
    }
    const wrapper = document.createElement('div');
    wrapper.className = 'local-dimensions-contrast-layout';
    const fields = document.createElement('div');
    fields.className = 'local-dimensions-contrast-fields';
    bgfitem.parentNode.insertBefore(wrapper, bgfitem);
    fields.appendChild(bgfitem);
    fields.appendChild(textfitem);
    wrapper.appendChild(fields);
    wrapper.appendChild(aside);
};

/**
 * Build and wire the panel. Separated from init() so init can stay synchronous.
 *
 * @param {String} bgfield Background-colour custom-field shortname.
 * @param {String} textfield Text-colour custom-field shortname.
 * @return {Promise<void>}
 */
const setup = async(bgfield, textfield) => {
    if (!bgfield || !textfield) {
        return;
    }
    const bginput = document.querySelector('[name="customfield_' + bgfield + '"]');
    const textinput = document.querySelector('[name="customfield_' + textfield + '"]');
    if (!bginput || !textinput || bginput.dataset.localDimensionsContrast === '1') {
        return;
    }
    bginput.dataset.localDimensionsContrast = '1';

    const aamin = formatRatio(THRESHOLDS.aa);
    const strings = await getStrings([
        {key: 'contrast_excellent', component: 'local_dimensions'},
        {key: 'contrast_pass', component: 'local_dimensions'},
        {key: 'contrast_attention', component: 'local_dimensions'},
        {key: 'contrast_fail', component: 'local_dimensions'},
        {key: 'contrast_min', component: 'local_dimensions'},
        {key: 'contrast_suggest_text', component: 'local_dimensions'},
        {key: 'contrast_suggest_bg', component: 'local_dimensions'},
        {key: 'contrast_apply', component: 'local_dimensions'},
        {key: 'contrast_suggest_heading', component: 'local_dimensions', param: aamin},
        {key: 'contrast_invalidhex', component: 'local_dimensions'},
        {key: 'contrast_belowaa', component: 'local_dimensions', param: aamin},
    ]);
    labels = {
        excellent: strings[0],
        pass: strings[1],
        attention: strings[2],
        fail: strings[3],
        min: strings[4],
        suggesttext: strings[5],
        suggestbg: strings[6],
        apply: strings[7],
        suggestheading: strings[8],
        invalidhex: strings[9],
        belowaa: strings[10],
    };

    const {html, js} = await Templates.renderForPromise('local_dimensions/contrast_panel', {});
    const aside = document.createElement('div');
    aside.className = 'local-dimensions-contrast-aside';
    await Templates.replaceNodeContents(aside, html, js);
    const panel = aside.querySelector('[data-region="contrast-panel"]');
    if (!panel) {
        return;
    }

    layout(bginput, textinput, aside);

    const panelcontroller = controller(panel, bginput, textinput);
    [bginput, textinput].forEach((input) => {
        input.addEventListener('input', panelcontroller.update);
        input.addEventListener('change', panelcontroller.update);
    });
    panelcontroller.update();
};

/**
 * Entry point: build the contrast panel for the two colour inputs, if present.
 *
 * @param {String} bgfield Background-colour custom-field shortname.
 * @param {String} textfield Text-colour custom-field shortname.
 */
export const init = (bgfield, textfield) => {
    setup(bgfield, textfield).catch(Notification.exception);
};
