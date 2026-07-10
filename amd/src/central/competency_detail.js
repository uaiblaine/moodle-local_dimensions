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
 * Shared competency detail rendering: paints a competency's detail card (gradient header,
 * chips, metric counts and description) into a detail-content container, and opens that same
 * card in a modal for any competency. Reused by the Structure tab (inline detail pane +
 * referenced-competency chips) and the Learning plans tab (clickable competency names), so
 * every surface stays visually identical.
 *
 * @module     local_dimensions/central/competency_detail
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Modal from 'core/modal';
import Notification from 'core/notification';
import Templates from 'core/templates';
import {getString} from 'core/str';
import {notifyError} from 'local_dimensions/central/errors';
import CollapsibleDescription from 'local_dimensions/collapsible_description';

const SELECTORS = {
    detailContent: '[data-region="detail-content"]',
    detailHeader: '.local-dimensions-central-structure-detail-header',
    detailTitle: '[data-region="detail-title"]',
    detailTaxonomy: '[data-region="detail-taxonomy"]',
    detailRule: '[data-region="detail-rule"]',
    detailRuleWrap: '[data-region="detail-rule-wrap"]',
    detailLabel: '[data-region="detail-label"]',
    detailLabelWrap: '[data-region="detail-label-wrap"]',
    detailIdnumber: '[data-region="detail-idnumber"]',
    detailIdnumberWrap: '[data-region="detail-idnumber-wrap"]',
    detailScale: '[data-region="detail-scale"]',
    detailScaleWrap: '[data-region="detail-scale-wrap"]',
    detailTag1: '[data-region="detail-tag1"]',
    detailTag1Wrap: '[data-region="detail-tag1-wrap"]',
    detailTag2: '[data-region="detail-tag2"]',
    detailTag2Wrap: '[data-region="detail-tag2-wrap"]',
    detailDescription: '[data-region="detail-description"]',
    detailDescriptionWrap: '[data-region="detail-description-wrap"]',
    detailCourses: '[data-region="detail-courses"]',
    detailActivities: '[data-region="detail-activities"]',
    detailPlans: '[data-region="detail-plans"]',
    closeModal: '[data-action="close-related-modal"]',
};

/**
 * Set a header chip's value and hide its wrapper when the value is empty.
 *
 * @param {HTMLElement} content The detail-content container.
 * @param {String} valueselector Selector for the value span.
 * @param {String} wrapselector Selector for the chip wrapper to hide/show.
 * @param {String} value The value; an empty string hides the chip.
 */
const setChip = (content, valueselector, wrapselector, value) => {
    const target = content.querySelector(valueselector);
    const wrap = content.querySelector(wrapselector);
    if (target) {
        target.textContent = value;
    }
    if (wrap) {
        wrap.hidden = value === '';
    }
};

/**
 * Apply async-composed chip text, but only while the detail is still current (guards rapid switches).
 *
 * @param {Function} isactive Returns whether the detail this text belongs to is still shown.
 * @param {HTMLElement|null} target The value span to fill.
 * @param {String} text The composed chip text.
 * @return {null}
 */
const applyChipText = (isactive, target, text) => {
    if (target && isactive()) {
        target.textContent = text;
    }
    return null;
};

/**
 * Darken a hex colour towards black by the given fraction (0-1), mirroring helper::darken_hex.
 *
 * @param {String} hex Source colour, e.g. "#2274c6".
 * @param {Number} amount Fraction to darken by (0 = unchanged, 1 = black).
 * @return {String} The darkened "#rrggbb" colour (or the input when unparseable).
 */
const darkenHex = (hex, amount) => {
    const clean = String(hex).replace('#', '');
    const full = clean.length === 3 ? clean.split('').map((char) => char + char).join('') : clean;
    if (!(/^[0-9a-fA-F]{6}$/).test(full)) {
        return hex;
    }
    const channel = (start) => Math.round(parseInt(full.slice(start, start + 2), 16) * (1 - amount))
        .toString(16)
        .padStart(2, '0');
    return '#' + channel(0) + channel(2) + channel(4);
};

/**
 * Paint the detail header with the competency's custom colours (the same 140deg darkening
 * gradient the Plans tab uses), or clear them to fall back to the brand default.
 *
 * @param {HTMLElement} content The detail-content container.
 * @param {Object} data The flat detail data (row.dataset shape).
 */
const applyHeaderColors = (content, data) => {
    const header = content.querySelector(SELECTORS.detailHeader);
    if (!header) {
        return;
    }
    const bg = data.bgcolor || '';
    if (bg) {
        header.style.setProperty('--ld-plans-hdr-0', bg);
        header.style.setProperty('--ld-plans-hdr-48', darkenHex(bg, 0.16));
        header.style.setProperty('--ld-plans-hdr-100', darkenHex(bg, 0.34));
    } else {
        header.style.removeProperty('--ld-plans-hdr-0');
        header.style.removeProperty('--ld-plans-hdr-48');
        header.style.removeProperty('--ld-plans-hdr-100');
    }
    header.style.color = data.textcolor || '';
};

/**
 * Populate the header metadata chips from the detail data, hiding each empty one.
 *
 * @param {HTMLElement} content The detail-content container.
 * @param {Object} data The flat detail data (row.dataset shape).
 * @param {Function} isactive Returns whether this detail is still the shown one.
 */
const populateDetailChips = (content, data, isactive) => {
    setChip(content, SELECTORS.detailIdnumber, SELECTORS.detailIdnumberWrap, data.idnumber || '');
    setChip(content, SELECTORS.detailScale, SELECTORS.detailScaleWrap, data.scale || '');
    setChip(content, SELECTORS.detailTag1, SELECTORS.detailTag1Wrap, data.tag1 || '');
    setChip(content, SELECTORS.detailTag2, SELECTORS.detailTag2Wrap, data.tag2 || '');

    // Rule chip (accent) — only a node WITH children AND a rule shows it; a leaf never does.
    const hasrule = data.haschildren === '1' && (data.ruletype || '') !== '';
    content.querySelector(SELECTORS.detailRuleWrap).hidden = !hasrule;
    if (hasrule) {
        getString('central_structure_rule', 'local_dimensions', data.rulelabel || '')
            .then((label) => applyChipText(isactive, content.querySelector(SELECTORS.detailRule), label))
            .catch(notifyError);
    }

    // Competency-label chip = the Type custom field (mirrors the Plans "label" chip).
    const type = data.type || '';
    content.querySelector(SELECTORS.detailLabelWrap).hidden = type === '';
    if (type !== '') {
        getString('central_plans_labelchip', 'local_dimensions', type)
            .then((label) => applyChipText(isactive, content.querySelector(SELECTORS.detailLabel), label))
            .catch(notifyError);
    }
};

/**
 * Populate the metric counts and the collapsible description from the detail data.
 *
 * @param {HTMLElement} content The detail-content container.
 * @param {Object} data The flat detail data (row.dataset shape).
 * @param {Function} isactive Returns whether this detail is still the shown one.
 * @param {String} idscope Namespace for the collapsible-description id ('tree' or 'modal').
 */
const populateDetailBody = (content, data, isactive, idscope) => {
    content.querySelector(SELECTORS.detailCourses).textContent = data.courses || '0';
    content.querySelector(SELECTORS.detailActivities).textContent = data.activities || '0';
    content.querySelector(SELECTORS.detailPlans).textContent = data.templates || '0';

    const description = data.description || '';
    const descwrap = content.querySelector(SELECTORS.detailDescriptionWrap);
    const desctarget = content.querySelector(SELECTORS.detailDescription);
    descwrap.hidden = description === '';
    desctarget.innerHTML = '';
    if (description === '') {
        return;
    }
    // The description is trusted server-rendered HTML (format_text — from the row's
    // data-description attribute, or the get_structure_node web service for the modal) in the
    // reusable collapsible container. The render is async, so only inject while this detail is
    // still the shown one (guards rapid row switches / a closed modal).
    Templates.renderForPromise('local_dimensions/collapsible_description', {
        html: description,
        id: 'local-dimensions-structure-desc-' + idscope + '-' + data.id,
    }).then(({html}) => {
        if (isactive()) {
            desctarget.innerHTML = html;
            CollapsibleDescription.refresh(desctarget);
        }
        return null;
    }).catch(Notification.exception);
};

/**
 * Render a competency's detail (title, taxonomy, header colours, chips and body) into a
 * detail-content container. Shared by the inline pane and the competency-detail modal so the
 * two surfaces stay visually identical; the caller supplies the source data (a selected row's
 * dataset, or a get_structure_node node mapped by nodeToDetailData), an isactive guard for the
 * async chip/description renders and an id scope keeping collapsible ids unique.
 *
 * @param {HTMLElement} content The detail-content container.
 * @param {Object} data The flat detail data (row.dataset shape).
 * @param {Function} isactive Returns whether this detail is still the shown one.
 * @param {String} idscope Namespace for the collapsible-description id ('tree' or 'modal').
 */
export const renderDetailInto = (content, data, isactive, idscope) => {
    content.querySelector(SELECTORS.detailTitle).textContent = data.name || '';
    content.querySelector(SELECTORS.detailTaxonomy).textContent = data.taxonomy || '';
    applyHeaderColors(content, data);
    populateDetailChips(content, data, isactive);
    populateDetailBody(content, data, isactive, idscope);
};

/**
 * Map a get_structure_node web-service node to the flat detail-data shape renderDetailInto
 * expects (the same keys a selected tree row exposes via its dataset).
 *
 * @param {Object} node The web-service node.
 * @return {Object} The detail data object.
 */
export const nodeToDetailData = (node) => ({
    id: String(node.id),
    name: node.shortname || '',
    taxonomy: node.taxonomy || '',
    idnumber: node.idnumber || '',
    scale: node.scale || '',
    description: node.description || '',
    type: node.type || '',
    tag1: node.tag1 || '',
    tag2: node.tag2 || '',
    bgcolor: node.bgcolor || '',
    textcolor: node.textcolor || '',
    courses: String(node.coursecount),
    activities: String(node.activitycount),
    templates: String(node.templatecount),
    haschildren: node.haschildren ? '1' : '',
    ruletype: node.ruletype || '',
    rulelabel: node.rulelabel || '',
});

/**
 * Open a competency in a modal that reuses the detail-card visual. Fetches the competency's
 * fresh node via the shared get_structure_node web service and renders it with non-clickable
 * metric counters (so no usage modal opens over this one) and without the nested
 * referenced-competencies section. Used by the Structure tab's referenced-competency chips and
 * the Learning plans tab's clickable competency names.
 *
 * @param {Number} competencyid The competency id.
 * @return {Promise<void>}
 */
export const openCompetencyDetailModal = async(competencyid) => {
    const response = await Ajax.call([{
        methodname: 'local_dimensions_get_structure_node',
        args: {competencyid: competencyid},
    }])[0];
    if (!response.found || !response.node) {
        return;
    }
    const data = nodeToDetailData(response.node);
    const {html} = await Templates.renderForPromise('local_dimensions/central/structure_related_modal', {
        detailconfig: {linksclickable: false, showrelated: false},
    });
    const modal = await Modal.create({
        title: data.name,
        body: html,
        large: true,
        show: true,
        removeOnClose: true,
    });
    const root = modal.getRoot();
    root.addClass('local-dimensions-related-modal');
    const modalcontent = root[0].querySelector(SELECTORS.detailContent);
    if (!modalcontent) {
        return;
    }
    // A closed modal is removed (removeOnClose); guard the async chip/description renders on that.
    renderDetailInto(modalcontent, data, () => modalcontent.isConnected, 'modal');
    const closebtn = root[0].querySelector(SELECTORS.closeModal);
    if (closebtn) {
        closebtn.style.color = data.textcolor || '#fff';
        closebtn.addEventListener('click', () => modal.hide());
    }
};
