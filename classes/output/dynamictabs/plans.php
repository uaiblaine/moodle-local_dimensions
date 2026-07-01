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
 * Learning plans dynamic tab — template list + competencies (cross-framework).
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
use core_competency\template;
use local_dimensions\helper;

/**
 * Learning plans tab: browse learning plan templates and the competencies they bundle
 * (which may come from several frameworks). CRUD, the cross-framework picker and the
 * cohort assignment are added in later phases (see docs/admin-redesign-codeplan.md).
 *
 * Args (from the pane data attributes / getContent): templateid.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plans extends \core\output\dynamic_tabs\base {
    /**
     * The label shown on the tab.
     *
     * @return string
     */
    public function get_tab_label(): string {
        return get_string('learningplans', 'local_dimensions');
    }

    /**
     * Whether the current user may see this tab.
     *
     * @return bool
     */
    public function is_available(): bool {
        return template::can_read_context(context_system::instance());
    }

    /**
     * Template used to render the tab body.
     *
     * @return string
     */
    public function get_template(): string {
        return 'local_dimensions/central/plans';
    }

    /**
     * Export the template list and the selected template's competencies.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        global $PAGE;

        $data = $this->get_data();

        // The System / Course category context is resolved by the shared page-level
        // selector helper; learning plans are governed by the same context.
        $resolved = helper::resolve_central_context(
            (string) ($data['contexttype'] ?? 'system'),
            (int) ($data['categoryid'] ?? 0)
        );
        $contexttype = $resolved['contexttype'];
        $categoryid = (int) $resolved['categoryid'];
        $needscategory = (bool) $resolved['needscategory'];
        $context = $resolved['context'];
        $templateid = (int) ($data['templateid'] ?? 0);

        $templates = [];
        if (!$needscategory) {
            foreach (api::list_templates('shortname', 'ASC', 0, 0, $context, 'self', true) as $template) {
                if (template::can_read_context($template->get_context())) {
                    $templates[(int) $template->get('id')] = $template;
                }
            }
        }

        // Optional filter: only templates that contain the chosen competency (cross-framework).
        $competencyid = (int) ($data['competencyid'] ?? 0);
        $selectedcompetencylabel = '';
        if ($competencyid > 0) {
            $competency = competency::get_record(['id' => $competencyid]);
            $framework = $competency
                ? competency_framework::get_record(['id' => $competency->get('competencyframeworkid')])
                : null;
            if ($competency && $framework && competency_framework::can_read_context($framework->get_context())) {
                $usingids = [];
                foreach (api::list_templates_using_competency($competencyid) as $usingtemplate) {
                    $usingids[(int) $usingtemplate->get('id')] = true;
                }
                foreach (array_keys($templates) as $id) {
                    if (!isset($usingids[$id])) {
                        unset($templates[$id]);
                    }
                }
                $tag = $framework->get('idnumber') !== '' ? $framework->get('idnumber') : $framework->get('shortname');
                $name = format_string($competency->get('shortname'));
                $tag = format_string($tag);
                $selectedcompetencylabel = $tag !== '' ? "$name · $tag" : $name;
            } else {
                // Unknown or unreadable competency: ignore the filter.
                $competencyid = 0;
            }
        }

        if ($templateid <= 0 || !isset($templates[$templateid])) {
            $templateid = (int) (array_key_first($templates) ?? 0);
        }
        $selected = $templates[$templateid] ?? null;

        $templateoptions = [];
        foreach ($templates as $id => $template) {
            $templateoptions[] = [
                'id' => $id,
                'name' => format_string($template->get('shortname')),
                'competencycount' => api::count_competencies_in_template($id),
                'visible' => (bool) $template->get('visible'),
                'selected' => $id === $templateid,
            ];
        }

        // Selected template competencies, each tagged with its source framework (cross-framework).
        // first/last drive the move up/down buttons; excludeids feeds the add picker so it never
        // offers a competency already on the template (core's add throws on a duplicate).
        $competencies = [];
        $excludeids = [];
        if ($selected) {
            $frameworktags = [];
            foreach (api::list_competencies_in_template($templateid) as $competency) {
                $fwid = (int) $competency->get('competencyframeworkid');
                if (!array_key_exists($fwid, $frameworktags)) {
                    $framework = competency_framework::get_record(['id' => $fwid]);
                    $frameworktags[$fwid] = $framework
                        ? ($framework->get('idnumber') !== '' ? $framework->get('idnumber') : $framework->get('shortname'))
                        : '';
                }
                $cid = (int) $competency->get('id');
                $excludeids[] = $cid;
                $competencies[] = [
                    'id' => $cid,
                    'shortname' => format_string($competency->get('shortname')),
                    'frameworktag' => format_string($frameworktags[$fwid]),
                    'first' => false,
                    'last' => false,
                ];
            }
            if (!empty($competencies)) {
                $competencies[0]['first'] = true;
                $competencies[count($competencies) - 1]['last'] = true;
            }
        }

        $canassignroles = has_capability('moodle/role:manage', context_system::instance());

        $PAGE->requires->js_call_amd('local_dimensions/central/plans', 'init');

        return [
            'contexttype' => $contexttype,
            'selectedcategoryid' => $categoryid,
            'contextid' => (int) $context->id,
            'competencyid' => $competencyid,
            'filteredbycompetency' => $competencyid > 0,
            'selectedcompetencyid' => $competencyid,
            'selectedcompetencylabel' => $selectedcompetencylabel,
            'needscategoryselection' => $needscategory,
            'hastemplates' => !empty($templateoptions),
            'templates' => $templateoptions,
            'selectedtemplateid' => $templateid,
            'selectedtemplatename' => $selected ? format_string($selected->get('shortname')) : '',
            'selectedtemplateplancount' => $selected
                ? (helper::count_plans_by_template([$templateid])[$templateid] ?? 0)
                : 0,
            'hascompetencies' => !empty($competencies),
            'competencies' => $competencies,
            'competencycount' => count($competencies),
            'excludeids' => implode(',', $excludeids),
            'canmanage' => (int) has_capability('moodle/competency:templatemanage', $context),
            'canassignroles' => (int) $canassignroles,
        ];
    }
}
