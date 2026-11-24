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
 * Structure dynamic tab — context switch + framework switcher + competency tree.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\output\dynamictabs;

use core_competency\api;
use core_competency\competency;
use core_competency\competency_framework;
use local_dimensions\helper;
use local_dimensions\local\unlisted_categories;
use local_dimensions\output\hub_map;

/**
 * Structure tab: navigate frameworks and their competencies without reloading.
 *
 * Args (from the pane data attributes / getContent): contexttype, categoryid, locked, frameworkid,
 * showhidden, view (one of VIEWS), competencyid (the workspace's deep link), classic (the hub's mode for the
 * visit, {@see \local_dimensions\local\hub_mode}).
 *
 * Every view shares everything this class resolves (the context, the framework list with its
 * unlisted-category filter, the selected framework, canmanage); only the body differs: the classic
 * tree and detail panes, or the React workspace ({@see hub_map}) in one of its three layouts.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class structure extends \core\output\dynamic_tabs\base {
    /**
     * @var string[] The tab's views, in the switch's order. 'list', the classic list, is offered in classic mode only
     * ({@see \local_dimensions\local\hub_mode}); 'map' is the map alone, as links and saved preferences from
     * before the outline name it.
     */
    public const array VIEWS = ['list', 'outline', 'map', 'both'];

    /**
     * @var string[] The views the React workspace draws, one mount for the three: the tab's switch moves among
     * them without reloading the pane (amd/src/central/structure.js), and only a move to or from the list does.
     */
    public const array WORKSPACE_VIEWS = ['outline', 'map', 'both'];

    /** @var string The view shown when neither the pane nor the saved preference names one the mode offers. */
    public const string DEFAULT_VIEW = 'both';

    /** @var string The default view in classic mode, which is the hub as it was: the classic list. */
    public const string CLASSIC_DEFAULT_VIEW = 'list';

    /**
     * The views the switch offers in a mode: all four in classic mode, the workspace's three otherwise.
     *
     * @param bool $classic Whether the hub is in classic mode.
     * @return string[] Views, in the switch's order.
     */
    public static function views(bool $classic): array {
        return $classic ? self::VIEWS : self::WORKSPACE_VIEWS;
    }

    /**
     * The view the tab shows: the one the pane names, else the viewer's saved one, else the mode's default.
     *
     * The pane names a view when the URL or the view switch did; any value the mode does not offer counts as
     * none, so a mistyped link opens what the viewer last chose, and outside classic mode a link or a saved
     * choice naming the classic list opens the workspace. The saved value is sanitised on read, and is
     * checked again here so that this method alone decides.
     *
     * @param string $requested The pane's view value, '' when it names none.
     * @param string $saved The viewer's saved view (helper::get_central_prefs()), '' when none.
     * @param bool $classic Whether the hub is in classic mode.
     * @return string One of views($classic).
     */
    public static function resolve_view(string $requested, string $saved, bool $classic = false): string {
        $offered = self::views($classic);
        if (in_array($requested, $offered, true)) {
            return $requested;
        }
        if (in_array($saved, $offered, true)) {
            return $saved;
        }
        return $classic ? self::CLASSIC_DEFAULT_VIEW : self::DEFAULT_VIEW;
    }

    /**
     * The view switch's cells, in VIEWS' order: each view's value, label, icon and whether it is shown.
     *
     * @param string $view The view shown.
     * @param bool $classic Whether the hub is in classic mode; without it the classic list has no cell.
     * @return array Each: value (string), label (string), icon (string, a Font Awesome class), pressed (bool).
     */
    public static function view_cells(string $view, bool $classic = true): array {
        $cells = [];
        foreach (self::views($classic) as $value) {
            $cells[] = [
                'value' => $value,
                'label' => match ($value) {
                    'list' => get_string('managecompetencies_view_list', 'local_dimensions'),
                    'outline' => get_string('managecompetencies_view_outline', 'local_dimensions'),
                    'map' => get_string('managecompetencies_view_map', 'local_dimensions'),
                    'both' => get_string('managecompetencies_view_both', 'local_dimensions'),
                },
                'icon' => match ($value) {
                    'list' => 'fa-list',
                    'outline' => 'fa-folder-tree',
                    'map' => 'fa-sitemap',
                    'both' => 'fa-table-columns',
                },
                'pressed' => $value === $view,
            ];
        }
        return $cells;
    }

    /**
     * The label shown on the tab.
     *
     * @return string
     */
    #[\Override]
    public function get_tab_label(): string {
        return get_string('managecompetencies_structure', 'local_dimensions');
    }

    /**
     * Whether the current user may see this tab in the context the pane names.
     *
     * Resolved from the pane's own contexttype/categoryid, never from the system context: a manager
     * holding the competency capabilities in one course category only must get this tab there, and
     * core's dynamic-tabs web service re-instantiates the tab from the same pane data before calling
     * require_access(). The resolver downgrades an unreadable category to the system context, so the
     * check then correctly refuses a category-scoped viewer instead of leaking a system listing.
     *
     * @return bool
     */
    #[\Override]
    public function is_available(): bool {
        $data = $this->get_data();
        $resolved = helper::resolve_central_context(
            (string) ($data['contexttype'] ?? 'system'),
            (int) ($data['categoryid'] ?? 0)
        );
        return competency_framework::can_read_context($resolved['context']);
    }

    /**
     * Template used to render the tab body.
     *
     * @return string
     */
    #[\Override]
    public function get_template(): string {
        return 'local_dimensions/central/structure';
    }

    /**
     * Export the context selector, framework list and the competency tree.
     *
     * @param \renderer_base $output
     * @return array
     */
    #[\Override]
    public function export_for_template(\renderer_base $output): array {
        global $PAGE;

        $data = $this->get_data();

        // The System / Course category context is resolved by the shared page-level
        // selector helper; this tab only navigates frameworks within it.
        $resolved = helper::resolve_central_context(
            (string) ($data['contexttype'] ?? 'system'),
            (int) ($data['categoryid'] ?? 0)
        );
        $contexttype = $resolved['contexttype'];
        $categoryid = (int) $resolved['categoryid'];
        $needscategory = (bool) $resolved['needscategory'];
        $pagecontext = $resolved['context'];
        $frameworkid = (int) ($data['frameworkid'] ?? 0);
        $showhidden = (bool) ($data['showhidden'] ?? false);
        $prefs = helper::get_central_prefs();
        // The visit's mode rides in the pane data (central.php resolved it, the address included); a pane
        // asked for without it reads the saved one. Display only: is_available() admitted the viewer.
        $classic = array_key_exists('classic', $data) ? (bool) (int) $data['classic'] : (bool) $prefs['display']['classic'];
        $view = self::resolve_view(
            (string) ($data['view'] ?? ''),
            (string) $prefs['display']['structure']['view'],
            $classic
        );
        $isworkspace = in_array($view, self::WORKSPACE_VIEWS, true);

        // Frameworks readable in the resolved context. All (including hidden) are sent so the
        // "show hidden" toggle can reveal them client-side without reloading the tab; the default
        // selection still prefers a visible framework.
        $frameworks = [];
        // The locked category entry lists the category and its descendants, as tool_lp's category
        // page does; the site entry lists the resolved context alone.
        $includes = empty($data['locked']) ? 'self' : 'children';
        if (!$needscategory) {
            // A framework in a subcategory local_unlistedcourses withholds from the viewer is left out.
            $listed = unlisted_categories::filter_by_context(
                api::list_frameworks('shortname', 'ASC', 0, 0, $pagecontext, $includes, false)
            );
            foreach ($listed as $framework) {
                if (competency_framework::can_read_context($framework->get_context())) {
                    $frameworks[(int) $framework->get('id')] = $framework;
                }
            }
        }

        if ($frameworkid <= 0 || !isset($frameworks[$frameworkid])) {
            $frameworkid = 0;
            foreach ($frameworks as $id => $framework) {
                if ((bool) $framework->get('visible')) {
                    $frameworkid = $id;
                    break;
                }
            }
            if ($frameworkid === 0) {
                $frameworkid = (int) (array_key_first($frameworks) ?? 0);
            }
        }
        $selected = $frameworks[$frameworkid] ?? null;

        $count = 0;
        $hashiddenframeworks = false;
        $frameworkoptions = [];
        foreach ($frameworks as $id => $framework) {
            $competencycount = competency::count_records(['competencyframeworkid' => $id]);
            if ($id === $frameworkid) {
                $count = $competencycount;
            }
            $ishidden = !((bool) $framework->get('visible'));
            $hashiddenframeworks = $hashiddenframeworks || $ishidden;
            // Plain spelling: the option label is a double stash, and the tab's JS copies it
            // back through textContent when "show hidden" filters the list.
            $frameworkoptions[] = [
                'id' => $id,
                'name' => format_string(
                    $framework->get('shortname'),
                    true,
                    ['context' => $framework->get_context(), 'escape' => false]
                ),
                'idnumber' => (string) $framework->get('idnumber'),
                'selected' => $id === $frameworkid,
                'competencycount' => $competencycount,
                'hidden' => $ishidden,
            ];
        }

        [$rootnodes, $roottotal] = $selected ? $this->first_page_of_roots($selected) : [[], 0];

        // Load-more affordance: the size of the next root page and a "Showing N of T" hint.
        $rootshown = count($rootnodes);
        $rootmorecount = max(0, min(helper::STRUCTURE_PAGE_SIZE, $roottotal - $rootshown));
        $rootloadmorehint = get_string('central_structure_loadmoreshown', 'local_dimensions', (object) [
            'shown' => $rootshown,
            'total' => $roottotal,
        ]);

        $canmanage = $selected && competency_framework::can_manage_context($selected->get_context());
        $structures = self::structures_commands($pagecontext, $needscategory);

        $PAGE->requires->js_call_amd('local_dimensions/central/structure', 'init');

        $jsonoptions = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

        // The workspace draws the selected framework even when it holds no competency yet: it adds the first.
        $mapprops = '';
        if ($isworkspace && $selected) {
            hub_map::require_strings($PAGE);
            $props = hub_map::props(
                $selected,
                $count,
                $rootnodes,
                $roottotal,
                $canmanage,
                hub_map::reveal_target($selected, (int) ($data['competencyid'] ?? 0)),
                $view,
                $structures
            );
            // Read back by core's React helper with JSON.parse; the hex flags keep the markup inert, and a
            // stray invalid byte in admin text must not empty the whole props, and the mount with them.
            $mapprops = (string) json_encode(
                $props,
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_INVALID_UTF8_SUBSTITUTE
            );
        }

        // The Structures menu beside the framework select: outside classic mode it is the only way to create,
        // import or reveal hidden structures; in classic mode the workspace's views carry it too, the list keeps
        // its own toggle. It carries the "show hidden" toggle, so the standalone one is left out with it.
        $showmenu = !$needscategory && (!$classic || $isworkspace)
            && ($structures['cancreate'] || $structures['canscalespage'] || $hashiddenframeworks);

        return [
            'contexttype' => $contexttype,
            'contextid' => $pagecontext->id,
            'classic' => $classic,
            'view' => $view,
            'views' => self::view_cells($view, $classic),
            'structuresmenu' => $showmenu ? [
                'cancreate' => $structures['cancreate'],
                'canscalespage' => $structures['canscalespage'],
                'scalesurl' => $structures['scalesurl'],
                'hashidden' => $hashiddenframeworks,
                'showhidden' => (bool) $prefs['display']['structure']['showhidden'],
                'sepbeforehidden' => $structures['cancreate'],
                'sepbeforescales' => $structures['cancreate'] || $hashiddenframeworks,
            ] : null,
            'isworkspace' => $mapprops !== '',
            'mapprops' => $mapprops,
            'selectedcategoryid' => $categoryid,
            // Flags for the shared detail partial: the inline pane has clickable usage
            // counters and the referenced-competencies section (the referenced-competency
            // modal reuses the same partial with both switched off).
            'detailconfig' => ['linksclickable' => true, 'showrelated' => true],
            'needscategoryselection' => $needscategory,
            'showhidden' => $showhidden,
            'hashiddenframeworks' => $hashiddenframeworks,
            'showhiddentoggle' => $hashiddenframeworks && !$showmenu ? [
                'id' => 'local-dimensions-structure-showhidden',
                'label' => get_string('managecompetencies_showhiddenframeworks', 'local_dimensions'),
                'action' => 'toggle-hidden',
                'checked' => $showhidden,
            ] : null,
            'hasframeworks' => !empty($frameworkoptions),
            'frameworks' => $frameworkoptions,
            'selectedframeworkid' => $frameworkid,
            'selectedframeworkname' => $selected
                ? format_string($selected->get('shortname'), true, ['context' => $selected->get_context(), 'escape' => false])
                : '',
            'selectedframeworkidnumber' => $selected ? (string) $selected->get('idnumber') : '',
            'hascompetencies' => $count > 0,
            'competencycount' => $count,
            'competencies' => $rootnodes,
            'rootshown' => $rootshown,
            'roottotal' => $roottotal,
            'hasmoreroots' => $roottotal > $rootshown,
            'rootmorecount' => $rootmorecount,
            'rootloadmorehint' => $rootloadmorehint,
            'canmanage' => (int) $canmanage,
            'rulesmodulesjson' => json_encode(
                $canmanage ? helper::get_competency_rule_modules() : [],
                $jsonoptions
            ),
            'courseoutcomesjson' => json_encode(helper::course_outcome_options(), $jsonoptions),
            'moduleoutcomesjson' => json_encode(helper::module_outcome_options(), $jsonoptions),
        ];
    }

    /**
     * What the commands that are not about one framework need: creating or importing one in the tab's context,
     * and the scales page.
     *
     * Creating and importing are offered to whoever manages competencies in the context the tab lists, which is
     * the context the framework form and the import form check again on submission
     * (framework_dynamic_form and import_framework_dynamic_form, check_access_for_dynamic_submission()).
     *
     * @param \context $context The tab's resolved context.
     * @param bool $needscategory Whether category mode still waits for a category (nothing can be created then).
     * @return array Keys: contextid (int), cancreate (bool), canscalespage (bool), scalesurl (string).
     */
    public static function structures_commands(\context $context, bool $needscategory): array {
        // The scales page's own gate, as the Structures tab reads it.
        $canscalespage = has_capability('moodle/course:managescales', \context_system::instance());
        return [
            'contextid' => (int) $context->id,
            'cancreate' => !$needscategory && competency_framework::can_manage_context($context),
            'canscalespage' => $canscalespage,
            'scalesurl' => $canscalespage ? (new \moodle_url('/grade/edit/scale/index.php'))->out(false) : '',
        ];
    }

    /**
     * Build the first page of root competency nodes for the selected framework.
     *
     * The tree is lazy: only the first page of roots is rendered server-side; deeper levels
     * and overflow roots are fetched by the client via local_dimensions_browse_structure.
     *
     * @param competency_framework $framework
     * @return array{0: array, 1: int} [root nodes for the first page, total root count]
     */
    private function first_page_of_roots(competency_framework $framework): array {
        $filters = ['competencyframeworkid' => (int) $framework->get('id'), 'parentid' => 0];
        $total = competency::count_records($filters);
        if ($total === 0) {
            return [[], 0];
        }
        $records = competency::get_records($filters, 'sortorder', 'ASC', 0, helper::STRUCTURE_PAGE_SIZE);
        $nodes = helper::structure_nodes($records, $framework, $framework->get_context());
        return [$nodes, (int) $total];
    }
}
