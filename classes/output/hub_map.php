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
     * Every local_dimensions string the canvas reads, handed to the page with strings_for_js().
     *
     * Kept in step with KEYS in js/esm/src/hubv2/strings.ts, which reads them in one call at mount;
     * hub_map_test holds the two lists equal and every key present in both languages.
     */
    public const array STRINGS = [
        'central_browseframeworks_framework',
        'central_links_button',
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
        'hubv2_appearance',
        'hubv2_assessment',
        'hubv2_canvas',
        'hubv2_children',
        'hubv2_children_one',
        'hubv2_collapse',
        'hubv2_colour',
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
        'hubv2_expand',
        'hubv2_expandall_capped',
        'hubv2_fitview',
        'hubv2_loading',
        'hubv2_loadmore',
        'hubv2_minimap',
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
        'hubv2_moveup',
        'hubv2_nocolour',
        'hubv2_noderole',
        'hubv2_noselection',
        'hubv2_outline',
        'hubv2_outline_related',
        'hubv2_outline_related_one',
        'hubv2_outline_remaining',
        'hubv2_outline_remaining_one',
        'hubv2_path',
        'hubv2_relate',
        'hubv2_resizeoutline',
        'hubv2_rootlevel',
        'hubv2_rule',
        'hubv2_scale',
        'hubv2_search',
        'hubv2_searchnone',
        'hubv2_searchresults',
        'hubv2_searchresults_one',
        'hubv2_seeusage',
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
        'managecompetencies_expandall',
        'managecompetencies_norule',
        'managecompetencies_searchplaceholder',
        'nocompetencies',
    ];

    /**
     * The other components' strings the canvas reads, by component: the wording core's own
     * competency dialogues use, so the canvas and the list view title them alike.
     *
     * Kept in step with OTHER in js/esm/src/hubv2/strings.ts (hub_map_test).
     */
    public const array OTHER_STRINGS = [
        'core' => ['changessaved', 'idnumber'],
        'tool_lp' => ['addcompetency', 'competencycannotbedeleted', 'deletecompetency', 'editcompetency'],
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
     * The canvas's props, from what the tab resolved for the selected framework.
     *
     * @param competency_framework $framework The framework shown.
     * @param int $competencycount How many competencies it holds.
     * @param array $roots Its first page of root nodes (helper::structure_nodes()).
     * @param int $roottotal How many roots it has.
     * @param bool $canmanage Whether the viewer manages competencies in the framework's context.
     * @param array|null $reveal The deep link's target ({@see reveal_target()}), or null.
     * @param string $layout The view shown, one of structure::WORKSPACE_VIEWS; anything else is the map.
     * @return array Keys: framework, roots, pagesize, rulesmodules, courseoutcomes, moduleoutcomes, rtl, reveal,
     *     layout.
     */
    public static function props(
        competency_framework $framework,
        int $competencycount,
        array $roots,
        int $roottotal,
        bool $canmanage,
        ?array $reveal,
        string $layout
    ): array {
        $context = $framework->get_context();
        return [
            'framework' => [
                'id' => (int) $framework->get('id'),
                'shortname' => format_string($framework->get('shortname'), true, ['context' => $context, 'escape' => false]),
                'idnumber' => (string) $framework->get('idnumber'),
                'competencycount' => $competencycount,
                'canmanage' => $canmanage,
            ],
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
