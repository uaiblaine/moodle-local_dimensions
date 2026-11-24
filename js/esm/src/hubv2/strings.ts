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
 * The canvas's strings: read once at mount, then filled in place.
 *
 * The Competencies tab hands every one of them to core's string cache in its map view
 * (hub_map::STRINGS and OTHER_STRINGS, through strings_for_js), so {@see loadLabels()} resolves
 * without a request. hub_map_test holds these lists equal to the server's and every key present in
 * both languages.
 *
 * @module     local_dimensions/hubv2/strings
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getStrings} from '@moodle/lms/core/stringUtils';

/** The plugin's component. */
const COMPONENT = 'local_dimensions';

/** Every local_dimensions string the canvas reads, in hub_map::STRINGS' order. */
export const KEYS = [
    'central_browseframeworks_framework',
    'central_frameworks_configurescale',
    'central_frameworks_duplicate',
    'central_frameworks_edit',
    'central_frameworks_import_title',
    'central_frameworks_new',
    'central_frameworks_scaledefault',
    'central_frameworks_scaleproficient',
    'central_related_button',
    'central_related_empty',
    'central_rule_nochildren',
    'hubv2_a11y_collapsed',
    'hubv2_a11y_controls',
    'hubv2_a11y_expanded',
    'hubv2_a11y_handle',
    'hubv2_a11y_level',
    'hubv2_a11y_node',
    'hubv2_a11y_position',
    'hubv2_a11y_related',
    'hubv2_a11y_rule',
    'hubv2_actions',
    'hubv2_actionsof',
    'hubv2_appearance',
    'hubv2_assessment',
    'hubv2_canvas',
    'hubv2_children',
    'hubv2_children_one',
    'hubv2_cmd_links',
    'hubv2_cmd_movepick',
    'hubv2_cmd_moveto',
    'hubv2_cmd_quickchild',
    'hubv2_cmd_quicksibling',
    'hubv2_cmd_relate',
    'hubv2_cmd_rename',
    'hubv2_cmd_rule',
    'hubv2_cmd_summary',
    'hubv2_cmd_viewboth',
    'hubv2_cmd_viewmap',
    'hubv2_cmd_viewoutline',
    'hubv2_collapse',
    'hubv2_colour',
    'hubv2_column_edit',
    'hubv2_columns',
    'hubv2_commands',
    'hubv2_competencies',
    'hubv2_competencies_one',
    'hubv2_courses',
    'hubv2_courses_one',
    'hubv2_delete',
    'hubv2_delete_blocked_badges',
    'hubv2_delete_blocked_closing_links',
    'hubv2_delete_blocked_closing_users',
    'hubv2_delete_blocked_courses',
    'hubv2_delete_blocked_courses_one',
    'hubv2_delete_blocked_courses_some',
    'hubv2_delete_blocked_intro',
    'hubv2_delete_blocked_intro_tree',
    'hubv2_delete_blocked_intro_tree_one',
    'hubv2_delete_blocked_plans',
    'hubv2_delete_blocked_plans_one',
    'hubv2_delete_blocked_plans_some',
    'hubv2_delete_blocked_templates',
    'hubv2_delete_blocked_templates_one',
    'hubv2_delete_blocked_templates_some',
    'hubv2_delete_blocked_title',
    'hubv2_delete_blocked_users',
    'hubv2_delete_blocked_users_one',
    'hubv2_delete_blocked_users_some',
    'hubv2_delete_confirm_leaf',
    'hubv2_delete_confirm_tree',
    'hubv2_delete_confirm_tree_one',
    'hubv2_delete_parentrule',
    'hubv2_description',
    'hubv2_edit',
    'hubv2_editor_created',
    'hubv2_editor_name',
    'hubv2_editor_new',
    'hubv2_editor_rename',
    'hubv2_expand',
    'hubv2_expandall_capped',
    'hubv2_fitview',
    'hubv2_fw_context',
    'hubv2_fw_delete',
    'hubv2_fw_duplicated',
    'hubv2_fw_export',
    'hubv2_fw_hide',
    'hubv2_fw_level',
    'hubv2_fw_show',
    'hubv2_fw_visibility',
    'hubv2_hint_actions',
    'hubv2_hint_reorder',
    'hubv2_loading',
    'hubv2_loadmore',
    'hubv2_minimap',
    'hubv2_moreactions',
    'hubv2_move',
    'hubv2_movecancel',
    'hubv2_movecancelled',
    'hubv2_moveconfirm',
    'hubv2_moveconfirmroot',
    'hubv2_moved',
    'hubv2_movedown',
    'hubv2_moveinvalid',
    'hubv2_movepick',
    'hubv2_moveroot',
    'hubv2_moverule_newparent',
    'hubv2_moverule_oldparent',
    'hubv2_movetitle',
    'hubv2_moveto_commit',
    'hubv2_moveto_commitroot',
    'hubv2_moveto_current',
    'hubv2_moveto_parent',
    'hubv2_moveto_pick',
    'hubv2_moveto_search',
    'hubv2_moveto_self',
    'hubv2_moveto_title',
    'hubv2_moveto_top',
    'hubv2_moveup',
    'hubv2_nocolour',
    'hubv2_noderole',
    'hubv2_noselection',
    'hubv2_outline',
    'hubv2_outline_related',
    'hubv2_outline_related_one',
    'hubv2_outline_remaining',
    'hubv2_outline_remaining_one',
    'hubv2_palette_commands',
    'hubv2_palette_field',
    'hubv2_palette_goto',
    'hubv2_palette_keys_close',
    'hubv2_palette_keys_move',
    'hubv2_palette_keys_run',
    'hubv2_palette_none',
    'hubv2_palette_on',
    'hubv2_path',
    'hubv2_reorder_first',
    'hubv2_reorder_last',
    'hubv2_reordered_down',
    'hubv2_reordered_up',
    'hubv2_resizeoutline',
    'hubv2_rootlevel',
    'hubv2_rule',
    'hubv2_scale',
    'hubv2_search',
    'hubv2_searchnone',
    'hubv2_searchresults',
    'hubv2_searchresults_one',
    'hubv2_seeusage',
    'hubv2_summary_levels',
    'hubv2_summary_levels_loaded',
    'hubv2_summary_loaded',
    'hubv2_summary_opened',
    'hubv2_summary_rules',
    'hubv2_summary_rules_loaded',
    'hubv2_summary_title',
    'hubv2_summary_total',
    'hubv2_tag1',
    'hubv2_tag2',
    'hubv2_type',
    'hubv2_usage',
    'hubv2_usage_activities',
    'hubv2_usage_courses',
    'hubv2_usage_none',
    'hubv2_usage_templates',
    'hubv2_zoomhint',
    'hubv2_zoomin',
    'hubv2_zoomout',
    'managecompetencies_addchild',
    'managecompetencies_addcompetency',
    'managecompetencies_collapseall',
    'managecompetencies_displayoptions',
    'managecompetencies_expandall',
    'managecompetencies_norule',
    'managecompetencies_searchplaceholder',
    'managecompetencies_showids',
    'managecompetencies_showtaxonomy',
    'nocompetencies',
];

/** The other components' strings, as [component, key], in hub_map::OTHER_STRINGS' order. */
export const OTHER: [string, string][] = [
    ['core', 'cancel'],
    ['core', 'changessaved'],
    ['core', 'closebuttontitle'],
    ['core', 'idnumber'],
    ['core', 'required'],
    ['core', 'save'],
    ['tool_lp', 'addcompetency'],
    ['tool_lp', 'competencycannotbedeleted'],
    ['tool_lp', 'deletecompetency'],
    ['tool_lp', 'editcompetency'],
    ['tool_lp', 'hidden'],
    ['tool_lp', 'taxonomies'],
    ['tool_lp', 'visible'],
];

/** The translated strings: a local_dimensions key alone, another component's as `component:key`. */
export type Labels = Record<string, string>;

/**
 * Read every string the canvas uses, in one call.
 *
 * @returns {Promise} Resolves with the labels.
 */
export const loadLabels = (): Promise<Labels> => {
    const requests = [
        ...KEYS.map((key) => ({key, component: COMPONENT})),
        ...OTHER.map(([component, key]) => ({key, component})),
    ];
    return getStrings(requests).then((values) => {
        const labels: Labels = {};
        KEYS.forEach((key, index) => {
            labels[key] = values[index];
        });
        OTHER.forEach(([component, key], index) => {
            labels[`${component}:${key}`] = values[KEYS.length + index];
        });
        return labels;
    });
};

/**
 * Put a value into every placeholder of a language string, as get_string() does in PHP.
 *
 * Split and join rather than replace(): a string pattern replaces the first occurrence only, and
 * a replacement string would read "$&" in a competency name as a substitution pattern.
 *
 * @param {string} template The translated string, possibly carrying {$a}.
 * @param {string} value What replaces it.
 * @returns {string} The filled string, or the empty string when the template is missing.
 */
export const fill = (template: string | undefined, value: string): string =>
    (template || '').split('{$a}').join(value);

/**
 * Put several values into a language string's {$a->name} placeholders.
 *
 * @param {string} template The translated string.
 * @param {object} values What replaces each one, keyed by name.
 * @returns {string} The filled string, or the empty string when the template is missing.
 */
export const fillObject = (template: string | undefined, values: Record<string, string>): string =>
    Object.entries(values).reduce(
        (text, [name, value]) => text.split(`{$a->${name}}`).join(value),
        template || ''
    );

/**
 * Put a count into the string that fits it: `<key>_one` for exactly 1.
 *
 * @param {object} labels The labels.
 * @param {string} key The plain string's key.
 * @param {number} count The number.
 * @returns {string} The filled string.
 */
export const fillCount = (labels: Labels, key: string, count: number): string =>
    fill(count === 1 && labels[`${key}_one`] ? labels[`${key}_one`] : labels[key], String(count));
