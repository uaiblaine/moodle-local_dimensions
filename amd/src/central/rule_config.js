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
 * Native competency rule-configuration modal for the Competency hub Structure tab.
 *
 * Renders a Bootstrap modal (outcome + rule type + points table) from the client-side tree model and
 * resolves with the chosen {ruletype, ruleoutcome, ruleconfig}. Replaces the legacy YUI
 * tool_lp/competencyruleconfig.
 *
 * @module     local_dimensions/central/rule_config
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalSaveCancel from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';
import Templates from 'core/templates';
import {getString, getStrings} from 'core/str';

const OUTCOME_NONE = 0;
const RULE_POINTS = 'core_competency\\competency_rule_points';

// Outcome codes in display order with their tool_lp string keys.
const OUTCOMES = [
    {value: 0, key: 'competencyoutcome_none'},
    {value: 1, key: 'competencyoutcome_evidence'},
    {value: 3, key: 'competencyoutcome_recommend'},
    {value: 2, key: 'competencyoutcome_complete'},
];

/**
 * Parse a competency points ruleconfig JSON string into {requiredpoints, byid}.
 *
 * @param {String|null} ruleconfig
 * @return {Object}
 */
const parseConfig = (ruleconfig) => {
    const result = {requiredpoints: 1, byid: {}};
    if (!ruleconfig) {
        return result;
    }
    try {
        const parsed = JSON.parse(ruleconfig);
        if (parsed && parsed.base && parsed.base.points) {
            result.requiredpoints = Number(parsed.base.points);
        }
        const comps = parsed && parsed.competencies ? parsed.competencies : [];
        comps.forEach((comp) => {
            result.byid[Number(comp.id)] = {
                points: Number(comp.points || 0),
                required: Number(comp.required) === 1,
            };
        });
    } catch (e) {
        return result;
    }
    return result;
};

/**
 * Build the Mustache context for the modal body.
 *
 * @param {Object} competency Target competency from the tree model.
 * @param {Array} children Child competencies ({id, shortname}).
 * @param {Array} rulesModules Available rule modules ({type, name}).
 * @param {Array} outcomelabels Localised outcome labels in OUTCOMES order.
 * @return {Object}
 */
const buildContext = (competency, children, rulesModules, outcomelabels) => {
    const currenttype = competency.ruletype || '';
    const currentoutcome = Number(competency.ruleoutcome || 0);
    const parsed = parseConfig(competency.ruleconfig);
    return {
        hasrule: currentoutcome !== OUTCOME_NONE,
        ispoints: currenttype === RULE_POINTS,
        showerror: false,
        haschildren: children.length > 0,
        requiredpoints: parsed.requiredpoints,
        outcomes: OUTCOMES.map((outcome, index) => ({
            value: outcome.value,
            label: outcomelabels[index],
            selected: outcome.value === currentoutcome,
        })),
        ruletypes: rulesModules.map((module) => ({
            type: module.type,
            name: module.name,
            selected: module.type === currenttype,
        })),
        children: children.map((child) => {
            const cfg = parsed.byid[Number(child.id)] || {points: 0, required: false};
            return {id: child.id, shortname: child.shortname, points: cfg.points, required: cfg.required};
        }),
    };
};

/**
 * Read and validate the points rule config from the rendered points table.
 *
 * @param {HTMLElement} pointsEl The [data-region="points"] element.
 * @return {String|null} The ruleconfig JSON, or null when invalid.
 */
const readPointsConfig = (pointsEl) => {
    const requiredinput = pointsEl.querySelector('[name="requiredpoints"]');
    const requiredpoints = requiredinput ? Number(requiredinput.value || 0) : 0;
    const competencies = Array.from(pointsEl.querySelectorAll('tr[data-competency]')).map((rowel) => ({
        id: Number(rowel.dataset.competency),
        points: Number(rowel.querySelector('[name="points"]').value || 0),
        required: rowel.querySelector('[name="required"]').checked ? 1 : 0,
    }));
    const total = competencies.reduce((sum, comp) => sum + Math.max(0, comp.points), 0);
    if (requiredpoints < 1 || total < requiredpoints) {
        return null;
    }
    return JSON.stringify({base: {points: requiredpoints}, competencies: competencies});
};

/**
 * Open the rule-config modal for a competency and resolve with the chosen config (or null on cancel).
 *
 * @param {Object} competency Target competency (from the tree model).
 * @param {Array} children Child competencies ({id, shortname}).
 * @param {Array} rulesModules Available rule modules ({type, name}).
 * @return {Promise<Object|null>} {ruletype, ruleoutcome, ruleconfig} or null.
 */
export const show = async(competency, children, rulesModules) => {
    const outcomelabels = await getStrings(OUTCOMES.map((outcome) => ({key: outcome.key, component: 'tool_lp'})));
    const title = await getString('competencyrule', 'tool_lp');
    const context = buildContext(competency, children, rulesModules, outcomelabels);
    const {html} = await Templates.renderForPromise('local_dimensions/central/rule_config', context);

    const modal = await ModalSaveCancel.create({title, body: html});
    modal.setRemoveOnClose(true);
    const root = modal.getRoot()[0];
    const outcomeEl = root.querySelector('[data-region="outcome"]');
    const ruletypeWrap = root.querySelector('[data-region="ruletype-wrap"]');
    const ruletypeEl = root.querySelector('[data-region="ruletype"]');
    const pointsEl = root.querySelector('[data-region="points"]');
    const errorEl = root.querySelector('[data-region="error"]');

    const refresh = () => {
        const hasrule = Number(outcomeEl.value) !== OUTCOME_NONE;
        ruletypeWrap.hidden = !hasrule;
        pointsEl.hidden = !(hasrule && ruletypeEl.value === RULE_POINTS);
        // The invalid-points alert outlives the table it is about; drop it with the table.
        errorEl.hidden = errorEl.hidden || pointsEl.hidden;
    };
    outcomeEl.addEventListener('change', refresh);
    ruletypeEl.addEventListener('change', refresh);

    return new Promise((resolve) => {
        modal.getRoot().on(ModalEvents.save, (event) => {
            const outcome = Number(outcomeEl.value);
            if (outcome === OUTCOME_NONE) {
                resolve({ruletype: null, ruleoutcome: 0, ruleconfig: null});
                return;
            }
            const ruletype = ruletypeEl.value;
            if (ruletype !== RULE_POINTS) {
                resolve({ruletype: ruletype, ruleoutcome: outcome, ruleconfig: null});
                return;
            }
            const ruleconfig = readPointsConfig(pointsEl);
            if (ruleconfig === null) {
                event.preventDefault();
                errorEl.hidden = false;
                return;
            }
            resolve({ruletype: ruletype, ruleoutcome: outcome, ruleconfig: ruleconfig});
        });
        modal.getRoot().on(ModalEvents.hidden, () => resolve(null));
        modal.show();
    });
};
