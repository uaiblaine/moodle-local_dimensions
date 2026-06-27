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
 * Shared context selector for the Competency hub.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\output\central;

use renderable;
use templatable;
use renderer_base;
use core\context\system as context_system;
use core_competency\competency_framework;
use core_competency\template;
use local_dimensions\helper;

/**
 * Page-level context selector governing both the Structure and Plans tabs.
 *
 * Renders the System / Course category switch plus the category picker once, above the
 * dynamic tabs. The category options and the system totals carry both adaptive counts
 * (frameworks and learning plans) so the client swaps the displayed count for the active
 * tab without a round-trip. See docs/admin-redesign.md §9.1.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class contextbar implements renderable, templatable {
    /** @var string Resolved context type ('system' or 'coursecat'). */
    private $contexttype;

    /** @var int Resolved (readable) course category id, or 0. */
    private $categoryid;

    /** @var bool Whether course-category mode is active without a chosen category. */
    private $needscategory;

    /**
     * Constructor.
     *
     * @param string $contexttype Either 'system' or 'coursecat'.
     * @param int $categoryid Selected course category id (course-category mode only).
     */
    public function __construct(string $contexttype, int $categoryid) {
        $resolved = helper::resolve_central_context($contexttype, $categoryid);
        $this->contexttype = $resolved['contexttype'];
        $this->categoryid = (int) $resolved['categoryid'];
        $this->needscategory = (bool) $resolved['needscategory'];
    }

    /**
     * Export the context selector state for the template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $systemcontext = context_system::instance();
        $systemframeworkcount = competency_framework::count_records(['contextid' => $systemcontext->id, 'visible' => 1]);
        $systemtemplatecount = template::count_records(['contextid' => $systemcontext->id, 'visible' => 1]);

        $categoryoptions = helper::central_category_options($this->categoryid);

        // Counts of the currently selected context, in both modes. The Structure tab is
        // active first, so the headline counter starts in framework mode.
        $selectedframeworkcount = $systemframeworkcount;
        $selectedtemplatecount = $systemtemplatecount;
        if ($this->contexttype === 'coursecat' && $this->categoryid > 0) {
            $selectedframeworkcount = 0;
            $selectedtemplatecount = 0;
            foreach ($categoryoptions as $option) {
                if ($option['id'] === $this->categoryid) {
                    $selectedframeworkcount = $option['frameworkcount'];
                    $selectedtemplatecount = $option['templatecount'];
                    break;
                }
            }
        }

        return [
            'contexttype' => $this->contexttype,
            'issystem' => $this->contexttype === 'system',
            'iscoursecat' => $this->contexttype === 'coursecat',
            'needscategory' => $this->needscategory,
            'selectedcategoryid' => $this->categoryid,
            'hascategories' => !empty($categoryoptions),
            'categoryoptions' => $categoryoptions,
            'systemframeworkcount' => (int) $systemframeworkcount,
            'systemtemplatecount' => (int) $systemtemplatecount,
            'selectedframeworkcount' => (int) $selectedframeworkcount,
            'selectedtemplatecount' => (int) $selectedtemplatecount,
        ];
    }
}
