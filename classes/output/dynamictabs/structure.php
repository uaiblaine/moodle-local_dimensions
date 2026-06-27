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

        // Frameworks readable in the resolved context.
        $frameworks = [];
        if (!$needscategory) {
            foreach (api::list_frameworks('shortname', 'ASC', 0, 0, $pagecontext, 'self', true) as $framework) {
                if (competency_framework::can_read_context($framework->get_context())) {
                    $frameworks[(int) $framework->get('id')] = $framework;
                }
            }
        }

        if ($frameworkid <= 0 || !isset($frameworks[$frameworkid])) {
            $frameworkid = (int) (array_key_first($frameworks) ?? 0);
        }
        $selected = $frameworks[$frameworkid] ?? null;

        $frameworkoptions = [];
        foreach ($frameworks as $id => $framework) {
            $frameworkoptions[] = [
                'id' => $id,
                'name' => format_string($framework->get('shortname')),
                'idnumber' => s($framework->get('idnumber')),
                'selected' => $id === $frameworkid,
                'competencycount' => competency::count_records(['competencyframeworkid' => $id]),
            ];
        }

        [$competencies, $count, $model] = $selected ? $this->build_tree($selected) : [[], 0, []];

        $canmanage = $selected && competency_framework::can_manage_context($selected->get_context());

        $PAGE->requires->js_call_amd('local_dimensions/central/structure', 'init');

        $jsonoptions = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

        return [
            'contexttype' => $contexttype,
            'selectedcategoryid' => $categoryid,
            'needscategoryselection' => $needscategory,
            'hasframeworks' => !empty($frameworkoptions),
            'frameworks' => $frameworkoptions,
            'selectedframeworkid' => $frameworkid,
            'selectedframeworkname' => $selected ? format_string($selected->get('shortname')) : '',
            'selectedframeworkidnumber' => $selected ? s($selected->get('idnumber')) : '',
            'hascompetencies' => $count > 0,
            'competencycount' => $count,
            'competencies' => $competencies,
            'canmanage' => (int) $canmanage,
            'competenciesjson' => json_encode(array_values($model), $jsonoptions),
            'rulesmodulesjson' => json_encode(
                $canmanage ? helper::get_competency_rule_modules() : [],
                $jsonoptions
            ),
        ];
    }

    /**
     * Build the competency node model for the selected framework.
     *
     * Returns the root nodes (for initial render) plus the full node model (used by the
     * client to expand children lazily and by tool_lp/competencyruleconfig).
     *
     * @param competency_framework $framework
     * @return array{0: array, 1: int, 2: array} [root nodes, total, full node model]
     */
    private function build_tree(competency_framework $framework): array {
        global $DB;

        $records = competency::get_records(['competencyframeworkid' => (int) $framework->get('id')], 'path, sortorder');
        if (empty($records)) {
            return [[], 0, []];
        }

        // Group by parent to compute depth and "has children".
        $childrenbyparent = [];
        foreach ($records as $record) {
            $childrenbyparent[(int) $record->get('parentid')][] = $record;
        }

        // One grouped query for linked-course counts (visibility refinement is pending).
        $ids = array_map(static fn(competency $c): int => (int) $c->get('id'), $records);
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'cid');
        $counts = $DB->get_records_sql_menu(
            "SELECT competencyid, COUNT(1)
               FROM {competency_coursecomp}
              WHERE competencyid $insql
           GROUP BY competencyid",
            $params
        );

        // Build the full node model (display + rule fields) depth-first. The template
        // renders only the roots; the JS expands children client-side from this model,
        // and tool_lp/competencyruleconfig also consumes it. This keeps the DOM small for
        // large frameworks while the rule editor still sees the whole tree.
        $model = [];
        $walk = function (int $parentid, int $depth) use (&$walk, &$model, $childrenbyparent, $framework, $counts): void {
            foreach ($childrenbyparent[$parentid] ?? [] as $record) {
                $id = (int) $record->get('id');
                $level = max(1, $depth + 1);
                $taxonomy = $framework->get_taxonomy($level) ?: competency_framework::TAXONOMY_COMPETENCY;
                $model[] = [
                    'id' => $id,
                    'parentid' => (int) $record->get('parentid'),
                    'competencyframeworkid' => (int) $record->get('competencyframeworkid'),
                    'shortname' => format_string($record->get('shortname')),
                    'idnumber' => s($record->get('idnumber')),
                    'path' => $record->get('path'),
                    'depth' => $depth,
                    'indent' => $depth * 22,
                    'haschildren' => !empty($childrenbyparent[$id]),
                    'taxonomy' => get_string('taxonomy_' . $taxonomy, 'core_competency'),
                    'coursecount' => (int) ($counts[$id] ?? 0),
                    'ruletype' => $record->get('ruletype'),
                    'ruleoutcome' => (int) $record->get('ruleoutcome'),
                    'ruleconfig' => $record->get('ruleconfig'),
                ];
                $walk($id, $depth + 1);
            }
        };
        $walk(0, 0);

        $roots = array_values(array_filter($model, static fn(array $node): bool => $node['parentid'] === 0));

        return [$roots, count($records), $model];
    }
}
