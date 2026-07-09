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
 * Frameworks dynamic tab — manage competency frameworks in the resolved context.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\output\dynamictabs;

use core\context\system as context_system;
use core_competency\competency_framework;
use local_dimensions\helper;

/**
 * Frameworks tab: list frameworks with native management (visibility, duplicate, delete, edit).
 *
 * Args (from the pane data attributes / getContent): contexttype, categoryid.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class frameworks extends \core\output\dynamic_tabs\base {
    /**
     * The label shown on the tab.
     *
     * @return string
     */
    public function get_tab_label(): string {
        return get_string('central_frameworks_tab', 'local_dimensions');
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
        return 'local_dimensions/central/frameworks';
    }

    /**
     * Export the framework list for the resolved context.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        global $PAGE;

        $data = $this->get_data();
        $resolved = helper::resolve_central_context(
            (string) ($data['contexttype'] ?? 'system'),
            (int) ($data['categoryid'] ?? 0)
        );
        $contexttype = $resolved['contexttype'];
        $categoryid = (int) $resolved['categoryid'];
        $needscategory = (bool) $resolved['needscategory'];
        $pagecontext = $resolved['context'];
        $showhidden = (bool) ($data['showhidden'] ?? false);

        // Fetch the full set (incl. hidden) once so the "show hidden" toggle can be gated on
        // whether any hidden structure actually exists, mirroring the Plans tab; then filter the
        // display rows by the current toggle state.
        $allrows = $needscategory ? [] : helper::framework_rows($pagecontext, true);
        $hiddencount = 0;
        $rows = [];
        foreach ($allrows as $row) {
            if (empty($row['visible'])) {
                $hiddencount++;
                if (!$showhidden) {
                    continue;
                }
            }
            $rows[] = $row;
        }
        $hashiddenframeworks = $hiddencount > 0;
        // Structures hidden from the current view (only when the toggle is off) — surfaced as
        // the "· N hidden" suffix on the count so the number stays honest.
        $excludedcount = $showhidden ? 0 : $hiddencount;
        $canmanage = !$needscategory && competency_framework::can_manage_context($pagecontext);

        $PAGE->requires->js_call_amd('local_dimensions/central/frameworks', 'init');

        return [
            'contexttype' => $contexttype,
            'selectedcategoryid' => $categoryid,
            'contextid' => $pagecontext->id,
            'needscategoryselection' => $needscategory,
            'hasframeworks' => !empty($rows),
            'hashiddenframeworks' => $hashiddenframeworks,
            'frameworks' => $rows,
            'frameworkcount' => count($rows),
            'hasexcluded' => $excludedcount > 0,
            'excludedcount' => $excludedcount,
            'canmanage' => (int) $canmanage,
            'canexport' => (int) ($canmanage && !empty($rows)),
            'showhidden' => $showhidden,
            'showhiddentoggle' => $hashiddenframeworks ? [
                'id' => 'local-dimensions-frameworks-showhidden',
                'label' => get_string('managecompetencies_showhiddenframeworks', 'local_dimensions'),
                'action' => 'toggle-hidden',
                'checked' => $showhidden,
            ] : null,
        ];
    }
}
