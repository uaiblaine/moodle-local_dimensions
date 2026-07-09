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

use core\context\system as context_system;
use core_competency\api;
use core_competency\competency;
use core_competency\competency_framework;
use local_dimensions\helper;

/**
 * Structure tab: navigate frameworks and their competencies without reloading.
 *
 * Args (from the pane data attributes / getContent): contexttype, categoryid, frameworkid.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class structure extends \core\output\dynamic_tabs\base {
    /**
     * The label shown on the tab.
     *
     * @return string
     */
    public function get_tab_label(): string {
        return get_string('managecompetencies_structure', 'local_dimensions');
    }

    /**
     * Whether the current user may see this tab.
     *
     * @return bool
     */
    public function is_available(): bool {
        return competency_framework::can_read_context(context_system::instance());
    }

    /**
     * Template used to render the tab body.
     *
     * @return string
     */
    public function get_template(): string {
        return 'local_dimensions/central/structure';
    }

    /**
     * Export the context selector, framework list and the competency tree.
     *
     * @param \renderer_base $output
     * @return array
     */
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

        // Frameworks readable in the resolved context. All (including hidden) are sent so the
        // "show hidden" toggle can reveal them client-side without reloading the tab; the default
        // selection still prefers a visible framework.
        $frameworks = [];
        if (!$needscategory) {
            foreach (api::list_frameworks('shortname', 'ASC', 0, 0, $pagecontext, 'self', false) as $framework) {
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
            $frameworkoptions[] = [
                'id' => $id,
                'name' => format_string($framework->get('shortname')),
                'idnumber' => s($framework->get('idnumber')),
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

        $PAGE->requires->js_call_amd('local_dimensions/central/structure', 'init');

        $jsonoptions = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

        return [
            'contexttype' => $contexttype,
            'selectedcategoryid' => $categoryid,
            'needscategoryselection' => $needscategory,
            'showhidden' => $showhidden,
            'hashiddenframeworks' => $hashiddenframeworks,
            'hasframeworks' => !empty($frameworkoptions),
            'frameworks' => $frameworkoptions,
            'selectedframeworkid' => $frameworkid,
            'selectedframeworkname' => $selected ? format_string($selected->get('shortname')) : '',
            'selectedframeworkidnumber' => $selected ? s($selected->get('idnumber')) : '',
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
