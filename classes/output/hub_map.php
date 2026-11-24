<?php
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
 * The workspace of the hub's Competencies tab (its Outline, Map and Both views): the props, the strings
 * and the deep link.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\output;

use core_competency\competency;
use core_competency\competency_framework;
use local_dimensions\helper;

/**
 * What the Competencies tab hands the React workspace (js/esm/src/hubv2/Hub.tsx) in its Outline, Map and
 * Both views.
 *
 * The tab ({@see \local_dimensions\output\dynamictabs\structure}) resolves the context, the framework
 * list and the selected framework once, for both views; this class only shapes what it resolved into
 * the canvas's props, so the map can never list a framework the list would not.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hub_map {
    /**
     * @var string[] The workspace's lenses, in its switch's order; 'none' draws the tree as it is. Kept in step with
     * LENSES in js/esm/src/hubv2/insights.ts and STRUCTURE_LENSES in amd/src/central/preferences.js
     * (hub_workspace_contract_test).
     */
    public const array LENSES = ['none', 'usage', 'rules', 'relations'];

    /** @var string The lens shown when neither the address nor the saved preference names a known one. */
    public const string DEFAULT_LENS = 'none';

    /**
     * Every local_dimensions string the canvas reads, handed to the page with strings_for_js().
     *
     * Kept in step with KEYS in js/esm/src/hubv2/strings.ts, which reads them in one call at mount;
     * hub_map_test holds the two lists equal and every key present in both languages.
     */
    public const array STRINGS = [
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
        'custombgcolor',
        'customtextcolor',
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
        'hubv2_bulk_appearance',
        'hubv2_bulk_appearance_commit',
        'hubv2_bulk_appearance_commit_one',
        'hubv2_bulk_appearance_done',
        'hubv2_bulk_appearance_done_one',
        'hubv2_bulk_appearance_intro',
        'hubv2_bulk_appearance_none',
        'hubv2_bulk_appearance_title',
        'hubv2_bulk_appearance_title_one',
        'hubv2_bulk_clear',
        'hubv2_bulk_cleared',
        'hubv2_bulk_clearvalue',
        'hubv2_bulk_colour_help',
        'hubv2_bulk_colour_invalid',
        'hubv2_bulk_contrast',
        'hubv2_bulk_contrast_low',
        'hubv2_bulk_delete',
        'hubv2_bulk_delete_can',
        'hubv2_bulk_delete_can_one',
        'hubv2_bulk_delete_checking',
        'hubv2_bulk_delete_commit',
        'hubv2_bulk_delete_commit_one',
        'hubv2_bulk_delete_done',
        'hubv2_bulk_delete_done_one',
        'hubv2_bulk_delete_kept',
        'hubv2_bulk_delete_none',
        'hubv2_bulk_delete_refused',
        'hubv2_bulk_delete_refused_one',
        'hubv2_bulk_delete_title',
        'hubv2_bulk_delete_title_one',
        'hubv2_bulk_delete_with',
        'hubv2_bulk_delete_with_one',
        'hubv2_bulk_dock',
        'hubv2_bulk_keep',
        'hubv2_bulk_more',
        'hubv2_bulk_move',
        'hubv2_bulk_move_commit',
        'hubv2_bulk_move_commitroot',
        'hubv2_bulk_move_commitroot_one',
        'hubv2_bulk_move_done',
        'hubv2_bulk_move_done_one',
        'hubv2_bulk_move_left',
        'hubv2_bulk_move_left_one',
        'hubv2_bulk_move_progress',
        'hubv2_bulk_move_refused',
        'hubv2_bulk_move_skipped',
        'hubv2_bulk_move_skipped_one',
        'hubv2_bulk_move_title',
        'hubv2_bulk_move_title_one',
        'hubv2_bulk_moveto_picked',
        'hubv2_bulk_notshown',
        'hubv2_bulk_notshown_one',
        'hubv2_bulk_picked',
        'hubv2_bulk_preview',
        'hubv2_bulk_select',
        'hubv2_bulk_selected',
        'hubv2_bulk_selected_one',
        'hubv2_bulk_setcolour',
        'hubv2_bulk_toomany',
        'hubv2_canvas',
        'hubv2_check_colour_contrast',
        'hubv2_check_colour_contrast_desc',
        'hubv2_check_description_empty',
        'hubv2_check_description_empty_desc',
        'hubv2_check_idnumber_empty',
        'hubv2_check_idnumber_empty_desc',
        'hubv2_check_rule_cannot_fire',
        'hubv2_check_rule_cannot_fire_desc',
        'hubv2_check_rule_single_child',
        'hubv2_check_rule_single_child_desc',
        'hubv2_check_rule_without_children',
        'hubv2_check_rule_without_children_desc',
        'hubv2_check_unused',
        'hubv2_check_unused_desc',
        'hubv2_checker',
        'hubv2_checker_again',
        'hubv2_checker_clean',
        'hubv2_checker_close',
        'hubv2_checker_intro',
        'hubv2_checker_none',
        'hubv2_checker_position',
        'hubv2_checker_reveal',
        'hubv2_checker_revealname',
        'hubv2_checker_title',
        'hubv2_children',
        'hubv2_children_one',
        'hubv2_cmd_check',
        'hubv2_cmd_lensnone',
        'hubv2_cmd_lensrelations',
        'hubv2_cmd_lensrules',
        'hubv2_cmd_lensusage',
        'hubv2_cmd_links',
        'hubv2_cmd_movepick',
        'hubv2_cmd_moveto',
        'hubv2_cmd_nextfinding',
        'hubv2_cmd_prevfinding',
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
        'hubv2_findings',
        'hubv2_findings_one',
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
        'hubv2_insights_failed',
        'hubv2_insights_loading',
        'hubv2_insights_retry',
        'hubv2_lens',
        'hubv2_lens_bucket_high',
        'hubv2_lens_bucket_low',
        'hubv2_lens_bucket_mid',
        'hubv2_lens_bucket_unused',
        'hubv2_lens_bucket_used',
        'hubv2_lens_count_courses',
        'hubv2_lens_count_courses_one',
        'hubv2_lens_count_modules',
        'hubv2_lens_count_modules_one',
        'hubv2_lens_count_plans',
        'hubv2_lens_count_plans_one',
        'hubv2_lens_count_templates',
        'hubv2_lens_count_templates_one',
        'hubv2_lens_elsewhere',
        'hubv2_lens_elsewhere_said',
        'hubv2_lens_elsewhere_said_one',
        'hubv2_lens_legend',
        'hubv2_lens_measure',
        'hubv2_lens_measure_courses',
        'hubv2_lens_measure_modules',
        'hubv2_lens_measure_plans',
        'hubv2_lens_measure_templates',
        'hubv2_lens_none',
        'hubv2_lens_pair',
        'hubv2_lens_pairs',
        'hubv2_lens_pairs_empty',
        'hubv2_lens_related',
        'hubv2_lens_relations',
        'hubv2_lens_relations_desc',
        'hubv2_lens_rule_asks',
        'hubv2_lens_rule_asks_all',
        'hubv2_lens_rule_available',
        'hubv2_lens_rule_cannotfire',
        'hubv2_lens_rule_childpoints',
        'hubv2_lens_rule_childpoints_one',
        'hubv2_lens_rule_childsaid',
        'hubv2_lens_rule_points',
        'hubv2_lens_rule_required',
        'hubv2_lens_rule_requiredchildren',
        'hubv2_lens_rule_type',
        'hubv2_lens_rule_with',
        'hubv2_lens_rule_without',
        'hubv2_lens_rules',
        'hubv2_lens_rules_desc',
        'hubv2_lens_rules_empty',
        'hubv2_lens_unrelated',
        'hubv2_lens_unused_empty',
        'hubv2_lens_unused_partial',
        'hubv2_lens_unused_said',
        'hubv2_lens_unusedlist',
        'hubv2_lens_usage',
        'hubv2_lens_usage_desc',
        'hubv2_lens_usage_none',
        'hubv2_lens_usage_presence',
        'hubv2_lens_used_said',
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
        'hubv2_sections_failed',
        'hubv2_seeusage',
        'hubv2_showmore',
        'hubv2_summary_findings',
        'hubv2_summary_levels',
        'hubv2_summary_levels_loaded',
        'hubv2_summary_loaded',
        'hubv2_summary_opened',
        'hubv2_summary_relations',
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
        'tag1',
        'tag2',
        'type',
    ];

    /**
     * The other components' strings the canvas reads, by component: the wording core's own
     * competency dialogues use, so the canvas and the list view title them alike.
     *
     * Kept in step with OTHER in js/esm/src/hubv2/strings.ts (hub_map_test).
     */
    public const array OTHER_STRINGS = [
        'core' => ['cancel', 'changessaved', 'closebuttontitle', 'idnumber', 'required', 'save'],
        'tool_lp' => [
            'addcompetency',
            'competencycannotbedeleted',
            'competencyoutcome_complete',
            'competencyoutcome_evidence',
            'competencyoutcome_none',
            'competencyoutcome_recommend',
            'deletecompetency',
            'editcompetency',
            'hidden',
            'outcome',
            'taxonomies',
            'visible',
        ],
    ];

    /**
     * Hand the canvas's strings to the page, so its one read at mount asks the server nothing.
     *
     * Over AJAX (a pane re-rendered by core_dynamic_tabs_get_content) the fragment requirements
     * manager adds them to M.str in the JavaScript the response carries.
     *
     * @param \moodle_page $page The page the tab renders on.
     * @return void
     */
    public static function require_strings(\moodle_page $page): void {
        $page->requires->strings_for_js(self::STRINGS, 'local_dimensions');
        foreach (self::OTHER_STRINGS as $component => $keys) {
            $page->requires->strings_for_js($keys, $component);
        }
    }

    /**
     * The competency a deep link asks the map to reveal, when it belongs to the framework shown.
     *
     * The id arrives from the URL; it is only a key. What decides is the competency's own record: it
     * is revealed only when it exists and its framework is the one the tab selected, which the tab
     * already checked the viewer may read. Anything else, another framework's competency included, is
     * dropped without a word, as a missing one is.
     *
     * @param competency_framework $framework The framework the map shows.
     * @param int $competencyid The competency the link names; 0 for none.
     * @return array|null Keys: id (int), pathids (int[], its ancestors, root first); null when nothing is revealed.
     */
    public static function reveal_target(competency_framework $framework, int $competencyid): ?array {
        if ($competencyid <= 0) {
            return null;
        }
        $competency = competency::get_record(['id' => $competencyid]);
        if (!$competency || (int) $competency->get('competencyframeworkid') !== (int) $framework->get('id')) {
            return null;
        }
        $path = trim((string) $competency->get('path'), '/');
        $pathids = array_values(array_filter(
            array_map('intval', $path === '' ? [] : explode('/', $path)),
            static fn(int $id): bool => $id > 0
        ));
        return ['id' => (int) $competency->get('id'), 'pathids' => $pathids];
    }

    /**
     * The lens the workspace opens with: the one the pane names (the address's, or the one the workspace last
     * chose), else the viewer's saved one, else none.
     *
     * Each value is only a word: anything but a lens of LENSES counts as none named, so a mistyped link opens the
     * saved lens. The saved value is sanitised on read (helper::get_central_prefs()) and checked again here, so
     * this method alone decides.
     *
     * @param string $requested The pane's lens value, '' when it names none.
     * @param string $saved The viewer's saved lens, '' when none.
     * @return string One of LENSES.
     */
    public static function resolve_lens(string $requested, string $saved): string {
        if (in_array($requested, self::LENSES, true)) {
            return $requested;
        }
        if (in_array($saved, self::LENSES, true)) {
            return $saved;
        }
        return self::DEFAULT_LENS;
    }

    /**
     * Clean a client write of the display preference (core_user's preference services call it, lib.php): the hub's
     * mode is the stored one ({@see \local_dimensions\local\hub_mode::clean_display()}), and so is the lens unless
     * the write carries a lens of LENSES.
     *
     * The client sends the lens only in the write that follows its choice (amd/src/central/preferences.js), so a
     * page left open since an earlier visit, saving another display choice, never writes back the lens it loaded
     * with over one chosen since. The preference is writable by its owner only, so the stored value read here is the
     * writer's own.
     *
     * @param mixed $value The value the client sent (JSON), or null.
     * @param string $name The preference's name.
     * @return string|null The value to store.
     */
    public static function clean_display($value, string $name): ?string {
        $cleaned = \local_dimensions\local\hub_mode::clean_display($value, $name);
        if ($cleaned === null) {
            return null;
        }
        $decoded = json_decode($cleaned, true);
        $display = is_array($decoded) ? $decoded : [];
        $structure = is_array($display['structure'] ?? null) ? $display['structure'] : [];
        if (!in_array($structure['lens'] ?? null, self::LENSES, true)) {
            unset($structure['lens']);
            $stored = (string) helper::get_central_prefs()['display']['structure']['lens'];
            if ($stored !== '') {
                $structure['lens'] = $stored;
            }
        }
        if ($structure !== [] || array_key_exists('structure', $display)) {
            $display['structure'] = $structure;
        }
        return (string) json_encode($display);
    }

    /**
     * The canvas's props, from what the tab resolved for the selected framework.
     *
     * @param competency_framework $framework The framework shown.
     * @param int $competencycount How many competencies it holds.
     * @param array $roots Its first page of root nodes (helper::structure_nodes()).
     * @param int $roottotal How many roots it has.
     * @param bool $canmanage Whether the viewer manages competencies in the framework's context.
     * @param array|null $reveal The deep link's target ({@see reveal_target()}), or null.
     * @param string $layout The view shown, one of structure::WORKSPACE_VIEWS; anything else is the map.
     * @param array $structures The commands not about one framework (structure::structures_commands()).
     * @param string $lens The lens to open with ({@see resolve_lens()}); anything else is none.
     * @return array Keys: framework, roots, pagesize, rulesmodules, courseoutcomes, moduleoutcomes, rtl, reveal,
     *     layout, structures, lens, appearance (null for a reader), sections (whether a plugin adds panel sections).
     */
    public static function props(
        competency_framework $framework,
        int $competencycount,
        array $roots,
        int $roottotal,
        bool $canmanage,
        ?array $reveal,
        string $layout,
        array $structures,
        string $lens = self::DEFAULT_LENS
    ): array {
        return [
            'framework' => self::framework_facts($framework, $competencycount, $canmanage),
            'roots' => [
                'items' => array_values($roots),
                'total' => $roottotal,
            ],
            'pagesize' => helper::STRUCTURE_PAGE_SIZE,
            'rulesmodules' => $canmanage ? helper::get_competency_rule_modules() : [],
            'courseoutcomes' => helper::course_outcome_options(),
            'moduleoutcomes' => helper::module_outcome_options(),
            // The page's language decides the direction the tree grows in, as it decides the page's.
            'rtl' => right_to_left(),
            'reveal' => $reveal,
            // The workspace's first layout; the tab's switch tells it of every later one without a reload.
            'layout' => in_array($layout, \local_dimensions\output\dynamictabs\structure::WORKSPACE_VIEWS, true) ? $layout : 'map',
            // Creating and importing a framework where the tab lists them, and the scales page; nothing for a reader.
            'structures' => [
                'contextid' => (int) ($structures['contextid'] ?? 0),
                'cancreate' => !empty($structures['cancreate']),
                'canscalespage' => !empty($structures['canscalespage']),
            ],
            // The workspace's first lens; it then keeps the address, the pane and the preference in step itself.
            'lens' => in_array($lens, self::LENSES, true) ? $lens : self::DEFAULT_LENS,
            // The bulk dock's "Set appearance" choices; a reader has no dock, so none.
            'appearance' => $canmanage ? self::appearance_options() : null,
            // Whether any plugin listens for the panel's companion sections: without one the panel never asks.
            'sections' => self::has_panel_sections(),
        ];
    }

    /**
     * Whether a plugin listens for {@see \local_dimensions\hook\competency_panel_sections}, so the panel's reader
     * asks for the sections of each selection; a site without one sends no request for them.
     *
     * Read from the hook manager's list of callbacks, which costs no statement.
     *
     * @return bool
     */
    public static function has_panel_sections(): bool {
        $callbacks = \core\di::get(\core\hook\manager::class)
            ->get_callbacks_for_hook(\local_dimensions\hook\competency_panel_sections::class);
        foreach ($callbacks as $definition) {
            if (empty($definition['disabled'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * The options of the appearance fields that are selects, for the bulk dock's "Set appearance" dialogue.
     *
     * Each list is the field's option labels in its own order, plain text, so position + 1 is what
     * local_dimensions_set_competencies_appearance takes as the value (helper::select_raw_options()); a field
     * that is not provisioned has none.
     *
     * @return array Keys: tag1, tag2, type; each a list of strings.
     */
    public static function appearance_options(): array {
        $plain = ['context' => \context_system::instance(), 'escape' => false];
        $options = [];
        foreach (['tag1', 'tag2', 'type'] as $name) {
            $field = helper::find_field_by_shortname(
                \local_dimensions\external\set_competencies_appearance::FIELDS[$name],
                helper::AREA_COMPETENCY
            );
            $labels = $field ? helper::select_raw_options($field) : [];
            $options[$name] = array_map(static fn(string $label): string => format_string($label, true, $plain), $labels);
        }
        return $options;
    }

    /**
     * What the root node says of the framework: the facts the Structures tab and core's framework page show.
     *
     * Names are the plain spelling (the canvas writes text, never markup). The taxonomy terms are core's, level by
     * level as competency_framework::get_taxonomy() reads them, localised with core's strings; a level past the
     * ones the framework names is core's fallback, `taxonomyfallback`. The scale's values carry the framework's
     * configuration (which value is the default, which are proficient), read from its scaleconfiguration.
     *
     * @param competency_framework $framework The framework.
     * @param int $competencycount How many competencies it holds.
     * @param bool $canmanage Whether the viewer manages competencies in the framework's context.
     * @return array Keys: id, shortname, idnumber, competencycount, canmanage, visible, contextname, scale (name,
     *     values: id, name, isdefault, proficient), taxonomies (string[], level 1 first), taxonomyfallback,
     *     deletable.
     */
    public static function framework_facts(competency_framework $framework, int $competencycount, bool $canmanage): array {
        $context = $framework->get_context();
        $plain = ['context' => $context, 'escape' => false];

        $terms = competency_framework::get_taxonomies_list();
        // The persistent's getter for 'taxonomies' is competency_framework::get_taxonomies(): an array of the
        // levels the framework names, keyed from 1, never the stored comma list. Each level is then read through
        // get_taxonomy(), core's own fallback included.
        $levels = count((array) $framework->get('taxonomies'));
        $taxonomies = [];
        for ($level = 1; $level <= $levels; $level++) {
            $taxonomies[] = (string) $terms[$framework->get_taxonomy($level)];
        }

        return [
            'id' => (int) $framework->get('id'),
            'shortname' => format_string($framework->get('shortname'), true, $plain),
            'idnumber' => (string) $framework->get('idnumber'),
            'competencycount' => $competencycount,
            'canmanage' => $canmanage,
            'visible' => (bool) $framework->get('visible'),
            'contextname' => $context->get_context_name(false, false, false),
            'scale' => self::scale_facts($framework),
            'taxonomies' => $taxonomies,
            'taxonomyfallback' => (string) $terms[competency_framework::TAXONOMY_COMPETENCY],
            // Whether core would delete it now; the delete action asks core again, which refuses when it no longer may.
            'deletable' => $canmanage
                && competency::can_all_be_deleted(competency::get_ids_by_frameworkid((int) $framework->get('id'))),
        ];
    }

    /**
     * The framework's scale: its name and values, each with the framework's default and proficiency marks.
     *
     * The value ids are 1-based positions, as core's get_scale_values service and the scaleconfiguration JSON
     * number them.
     *
     * @param competency_framework $framework The framework.
     * @return array Keys: name (string), values (array of id, name, isdefault, proficient); an empty name and no
     *     values when the scale is gone.
     */
    private static function scale_facts(competency_framework $framework): array {
        global $DB;
        $scale = $DB->get_record('scale', ['id' => (int) $framework->get('scaleid')]);
        if (!$scale) {
            return ['name' => '', 'values' => []];
        }
        $system = \context_system::instance();
        $marks = [];
        $config = json_decode((string) $framework->get('scaleconfiguration'));
        if (is_array($config)) {
            foreach (array_slice($config, 1) as $value) {
                if (is_object($value) && isset($value->id)) {
                    $marks[(int) $value->id] = $value;
                }
            }
        }
        $values = [];
        // Split as grade_scale::load_items() does, so the positions are core's.
        foreach (explode(',', (string) $scale->scale) as $index => $name) {
            $id = $index + 1;
            $values[] = [
                'id' => $id,
                'name' => format_string(trim((string) $name), true, ['context' => $system, 'escape' => false]),
                'isdefault' => !empty($marks[$id]->scaledefault),
                'proficient' => !empty($marks[$id]->proficient),
            ];
        }
        return [
            'name' => format_string((string) $scale->name, true, ['context' => $system, 'escape' => false]),
            'values' => $values,
        ];
    }

    /**
     * The hub's map view for what the retired hubv2.php link carried.
     *
     * Only the three navigation values the old page read are carried, each only when it was given;
     * the hub resolves and checks them itself, exactly as it does for a hand-typed link.
     *
     * @param string $contexttype 'system', 'coursecat', or '' when the link named none.
     * @param int $categoryid The category, 0 when the link named none.
     * @param int $frameworkid The framework, 0 when the link named none.
     * @return \moodle_url central.php on the Competencies tab, in the map view.
     */
    public static function legacy_url(string $contexttype, int $categoryid, int $frameworkid): \moodle_url {
        $params = ['tab' => 'structure', 'view' => 'map'];
        if (in_array($contexttype, ['system', 'coursecat'], true)) {
            $params['contexttype'] = $contexttype;
        }
        if ($categoryid > 0) {
            $params['categoryid'] = $categoryid;
        }
        if ($frameworkid > 0) {
            $params['frameworkid'] = $frameworkid;
        }
        return new \moodle_url('/local/dimensions/central.php', $params);
    }
}
