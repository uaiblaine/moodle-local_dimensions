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
 * Competency hub v2: the page around the canvas of a framework's tree.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\output;

use core_competency\competency;
use core_competency\competency_framework;
use core_competency\api;
use local_dimensions\helper;
use local_dimensions\local\unlisted_categories;
use renderable;
use renderer_base;
use templatable;

/**
 * Competency hub v2 (hubv2.php): context and framework choice, and the props of the canvas.
 *
 * The page is server-drawn around one React mount: the context form, the framework select and the
 * link to the list view are plain GET navigation, and the canvas receives everything its first
 * paint needs (the framework, its first page of root competencies, the rule modules and the outcome
 * options the hub's dialogues take), so it asks the server for nothing until the viewer acts.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hubv2_page implements renderable, templatable {
    /**
     * Every local_dimensions string the canvas reads, handed to the page with strings_for_js().
     *
     * Kept in step with KEYS in js/esm/src/hubv2/strings.ts, which reads them in one call at mount;
     * hubv2_page_test holds the two lists equal and every key present in both languages.
     */
    public const array STRINGS = [
        'central_browseframeworks_framework',
        'central_links_button',
        'central_related_button',
        'central_related_empty',
        'central_rule_nochildren',
        'hubv2_a11y_controls',
        'hubv2_a11y_handle',
        'hubv2_a11y_node',
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
        'hubv2_fitview',
        'hubv2_loading',
        'hubv2_loadmore',
        'hubv2_minimap',
        'hubv2_move',
        'hubv2_movecancel',
        'hubv2_movecancelled',
        'hubv2_moveconfirm',
        'hubv2_moveconfirmroot',
        'hubv2_movedown',
        'hubv2_moved',
        'hubv2_moveinvalid',
        'hubv2_movepick',
        'hubv2_moveroot',
        'hubv2_movetitle',
        'hubv2_moveup',
        'hubv2_nocolour',
        'hubv2_noderole',
        'hubv2_noselection',
        'hubv2_path',
        'hubv2_relate',
        'hubv2_related_offcanvas',
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
        'managecompetencies_norule',
        'managecompetencies_searchplaceholder',
    ];

    /**
     * The other components' strings the canvas reads, by component: the wording core's own
     * competency dialogues use, so the canvas and the list view title them alike.
     *
     * Kept in step with OTHER in js/esm/src/hubv2/strings.ts (hubv2_page_test).
     */
    public const array OTHER_STRINGS = [
        'core' => ['changessaved', 'idnumber'],
        'tool_lp' => ['addcompetency', 'competencycannotbedeleted', 'deletecompetency', 'editcompetency'],
    ];

    /** @var array The resolved context selector state (helper::resolve_central_context()). */
    private readonly array $resolved;

    /** @var int The framework asked for; 0 or an unknown one picks the first visible one. */
    private readonly int $frameworkid;

    /**
     * Constructor.
     *
     * @param string $contexttype Either 'system' or 'coursecat', as on central.php.
     * @param int $categoryid Selected course category id (course-category mode only).
     * @param int $frameworkid The framework asked for; 0 or an unknown one picks the first visible one.
     */
    public function __construct(string $contexttype, int $categoryid, int $frameworkid) {
        $this->frameworkid = $frameworkid;
        $this->resolved = helper::resolve_central_context($contexttype, $categoryid);
    }

    /**
     * The resolved context selector state.
     *
     * @return array Keys: context, contexttype, categoryid, iscoursecat, needscategory.
     */
    public function resolved(): array {
        return $this->resolved;
    }

    /**
     * Refuse a viewer who may read no framework in the resolved context, as central.php does.
     *
     * central.php refuses with competencyview when none of its tabs is available; the canvas is the
     * structure tab's view, which is available exactly when frameworks are readable there.
     *
     * @return void
     * @throws \required_capability_exception When the viewer may not read frameworks in the context.
     */
    public function require_access(): void {
        $context = $this->resolved['context'];
        if (!competency_framework::can_read_context($context)) {
            throw new \required_capability_exception($context, 'moodle/competency:competencyview', 'nopermissions', '');
        }
    }

    /**
     * The frameworks the viewer may read in the resolved context, keyed by id, by short name.
     *
     * The structure tab's own listing for the site entry ({@see \local_dimensions\output\dynamictabs\structure})
     * restated: the resolved context alone ('self'), unlisted subcategories withheld, each framework
     * checked in its own context. Keep the two in step.
     *
     * @return array Map framework id => competency_framework.
     */
    private function frameworks(): array {
        if ($this->resolved['needscategory']) {
            return [];
        }
        $frameworks = [];
        $listed = unlisted_categories::filter_by_context(
            api::list_frameworks('shortname', 'ASC', 0, 0, $this->resolved['context'], 'self', false)
        );
        foreach ($listed as $framework) {
            if (competency_framework::can_read_context($framework->get_context())) {
                $frameworks[(int) $framework->get('id')] = $framework;
            }
        }
        return $frameworks;
    }

    /**
     * The framework shown: the one asked for when listed, else the first visible one, else the first.
     *
     * @param array $frameworks Map framework id => competency_framework.
     * @return int The framework id, 0 when there is none.
     */
    private function pick_framework(array $frameworks): int {
        if ($this->frameworkid > 0 && isset($frameworks[$this->frameworkid])) {
            return $this->frameworkid;
        }
        foreach ($frameworks as $id => $framework) {
            if ((bool) $framework->get('visible')) {
                return (int) $id;
            }
        }
        return (int) (array_key_first($frameworks) ?? 0);
    }

    /**
     * Export the page: the context form, the framework select, the list-view link and the props.
     *
     * @param renderer_base $output
     * @return array
     */
    #[\Override]
    public function export_for_template(renderer_base $output): array {
        $contexttype = $this->resolved['contexttype'];
        $categoryid = (int) $this->resolved['categoryid'];
        $frameworks = $this->frameworks();
        $frameworkid = $this->pick_framework($frameworks);
        $selected = $frameworks[$frameworkid] ?? null;

        $options = [];
        foreach ($frameworks as $id => $framework) {
            // Plain spelling: the option label is a double stash.
            $options[] = [
                'id' => (int) $id,
                'name' => format_string(
                    $framework->get('shortname'),
                    true,
                    ['context' => $framework->get_context(), 'escape' => false]
                ),
                'selected' => $id === $frameworkid,
                'hidden' => !((bool) $framework->get('visible')),
            ];
        }

        $categoryoptions = [];
        $category = $contexttype === 'coursecat' && $categoryid > 0 ? helper::central_category_option($categoryid) : null;
        if ($category !== null) {
            $categoryoptions[] = ['id' => (int) $category['id'], 'name' => $category['name'], 'selected' => true];
        }

        $navigation = ['contexttype' => $contexttype, 'categoryid' => $categoryid];
        $listurl = helper::hub_page_url(\core\context\system::instance(), $navigation + [
            'tab' => 'structure',
            'frameworkid' => $frameworkid,
        ]);

        return [
            'formaction' => (new \moodle_url('/local/dimensions/hubv2.php'))->out(false),
            'issystem' => $contexttype === 'system',
            'iscoursecat' => $contexttype === 'coursecat',
            'needscategory' => (bool) $this->resolved['needscategory'],
            'categoryoptions' => $categoryoptions,
            'hasframeworks' => !empty($options),
            'frameworks' => $options,
            'hasframework' => $selected !== null,
            'listurl' => $listurl->out(false),
            // A stray invalid byte in admin text must not empty the whole props, and the mount with them.
            'props' => $selected !== null ? json_encode($this->props($selected, $listurl), JSON_INVALID_UTF8_SUBSTITUTE) : '',
        ];
    }

    /**
     * The canvas's props: what its first paint shows and what its dialogues need.
     *
     * @param competency_framework $framework The framework shown.
     * @param \moodle_url $listurl The list view of the same framework.
     * @return array Keys: framework, roots, pagesize, rulesmodules, courseoutcomes, moduleoutcomes, listurl.
     */
    public function props(competency_framework $framework, \moodle_url $listurl): array {
        $context = $framework->get_context();
        $frameworkid = (int) $framework->get('id');
        $canmanage = competency_framework::can_manage_context($context);

        $filters = ['competencyframeworkid' => $frameworkid, 'parentid' => 0];
        $total = competency::count_records($filters);
        $records = $total > 0
            ? competency::get_records($filters, 'sortorder', 'ASC', 0, helper::STRUCTURE_PAGE_SIZE)
            : [];

        return [
            'framework' => [
                'id' => $frameworkid,
                'shortname' => format_string($framework->get('shortname'), true, ['context' => $context, 'escape' => false]),
                'idnumber' => (string) $framework->get('idnumber'),
                'competencycount' => competency::count_records(['competencyframeworkid' => $frameworkid]),
                'canmanage' => $canmanage,
            ],
            'roots' => [
                'items' => helper::structure_nodes($records, $framework, $context),
                'total' => (int) $total,
            ],
            'pagesize' => helper::STRUCTURE_PAGE_SIZE,
            'rulesmodules' => $canmanage ? helper::get_competency_rule_modules() : [],
            'courseoutcomes' => helper::course_outcome_options(),
            'moduleoutcomes' => helper::module_outcome_options(),
            'listurl' => $listurl->out(false),
        ];
    }
}
