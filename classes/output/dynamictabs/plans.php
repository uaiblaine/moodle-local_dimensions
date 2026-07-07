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
use local_dimensions\template_metadata_cache;

/**
 * Learning plans tab: browse learning plan templates and the competencies they bundle
 * (which may come from several frameworks), with template CRUD, the cross-framework
 * competency picker and cohort/participant assignment all handled in place via modals.
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

        $canmanage = has_capability('moodle/competency:templatemanage', $context);

        // Managers also see disabled (hidden) templates; the tab hides them client-side
        // behind the "show disabled plans" toggle. Non-managers only get visible ones.
        $templates = [];
        if (!$needscategory) {
            foreach (api::list_templates('shortname', 'ASC', 0, 0, $context, 'self', !$canmanage) as $template) {
                if (template::can_read_context($template->get_context())) {
                    $templates[(int) $template->get('id')] = $template;
                }
            }
        }

        // Optional filter: only templates that contain every chosen competency (cross-framework
        // intersection). The ids arrive as a CSV in the pane dataset; unknown or unreadable
        // competencies are silently dropped from the filter.
        $filterids = array_values(array_unique(array_filter(array_map(
            'intval',
            explode(',', (string) ($data['competencyids'] ?? ''))
        ))));
        $competencyfilters = [];
        foreach ($filterids as $filterid) {
            $competency = competency::get_record(['id' => $filterid]);
            $framework = $competency
                ? competency_framework::get_record(['id' => $competency->get('competencyframeworkid')])
                : null;
            if (!$competency || !$framework || !competency_framework::can_read_context($framework->get_context())) {
                continue;
            }
            $usingids = [];
            foreach (api::list_templates_using_competency($filterid) as $usingtemplate) {
                $usingids[(int) $usingtemplate->get('id')] = true;
            }
            foreach (array_keys($templates) as $id) {
                if (!isset($usingids[$id])) {
                    unset($templates[$id]);
                }
            }
            $competencyfilters[] = [
                'id' => $filterid,
                'label' => format_string($competency->get('shortname')),
            ];
        }
        $filteredbycompetency = !empty($competencyfilters);

        if ($templateid <= 0 || !isset($templates[$templateid])) {
            // Prefer the first visible template so the auto-selected detail matches the
            // default list view (disabled templates start hidden behind the toggle).
            $templateid = 0;
            foreach ($templates as $id => $template) {
                if ($template->get('visible')) {
                    $templateid = $id;
                    break;
                }
            }
            if (!$templateid) {
                $templateid = (int) (array_key_first($templates) ?? 0);
            }
        }
        $selected = $templates[$templateid] ?? null;

        $metadatamap = template_metadata_cache::get_metadata_for_many(array_keys($templates));
        $hashiddentemplates = false;
        $templateoptions = [];
        foreach ($templates as $id => $template) {
            $name = format_string($template->get('shortname'));
            $idnumber = (string) (($metadatamap[$id] ?? [])['idnumber'] ?? '');
            $visible = (bool) $template->get('visible');
            $hashiddentemplates = $hashiddentemplates || !$visible;
            $templateoptions[] = [
                'id' => $id,
                'name' => $name,
                'idnumber' => s($idnumber),
                'search' => \core_text::strtolower($name . ' ' . $idnumber),
                'competencycount' => api::count_competencies_in_template($id),
                'visible' => $visible,
                'selected' => $id === $templateid,
            ];
        }

        // Selected template competencies, each tagged with its source framework (cross-framework).
        // first/last drive the move up/down buttons; excludeids feeds the add picker so it never
        // offers a competency already on the template (core's add throws on a duplicate).
        // taxonomy/path/idnumber feed the display-options toggles on the detail pane.
        $competencies = [];
        $excludeids = [];
        if ($selected) {
            $frameworks = [];
            $frameworktags = [];
            $pathsbyid = [];
            $records = api::list_competencies_in_template($templateid);
            foreach ($records as $competency) {
                $fwid = (int) $competency->get('competencyframeworkid');
                if (!array_key_exists($fwid, $frameworks)) {
                    $framework = competency_framework::get_record(['id' => $fwid]);
                    $frameworks[$fwid] = $framework ?: null;
                    $frameworktags[$fwid] = $framework
                        ? ($framework->get('idnumber') !== '' ? $framework->get('idnumber') : $framework->get('shortname'))
                        : '';
                }
                $pathsbyid[(int) $competency->get('id')] = $competency->get('path');
            }
            $breadcrumbs = helper::competency_breadcrumbs($pathsbyid, $context);
            foreach ($records as $competency) {
                $fwid = (int) $competency->get('competencyframeworkid');
                $cid = (int) $competency->get('id');
                $excludeids[] = $cid;
                $taxonomy = '';
                if (!empty($frameworks[$fwid])) {
                    $taxonomy = (string) (helper::get_taxonomy_at_level(
                        $frameworks[$fwid],
                        (int) $competency->get_level()
                    )['term'] ?? '');
                }
                $competencies[] = [
                    'id' => $cid,
                    'shortname' => format_string($competency->get('shortname')),
                    'idnumber' => (string) $competency->get('idnumber'),
                    'taxonomy' => $taxonomy,
                    'path' => $breadcrumbs[$cid]['path'] ?? '',
                    'frameworktag' => format_string($frameworktags[$fwid]),
                    'frameworkid' => $fwid,
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

        $duedate = $selected ? (int) $selected->get('duedate') : 0;

        $PAGE->requires->js_call_amd('local_dimensions/central/plans', 'init');

        return [
            'contexttype' => $contexttype,
            'selectedcategoryid' => $categoryid,
            'contextid' => (int) $context->id,
            'competencyids' => implode(',', array_column($competencyfilters, 'id')),
            'competencyfilters' => $competencyfilters,
            'filteredbycompetency' => $filteredbycompetency,
            'needscategoryselection' => $needscategory,
            'hastemplates' => !empty($templateoptions),
            'hashiddentemplates' => $hashiddentemplates,
            'templates' => $templateoptions,
            'selectedtemplateid' => $templateid,
            'selectedtemplatename' => $selected ? format_string($selected->get('shortname')) : '',
            'selectedtemplatevisible' => $selected ? (bool) $selected->get('visible') : false,
            'selectedtemplatehasduedate' => $duedate > 0,
            'selectedtemplateduedate' => $duedate > 0
                ? userdate($duedate, get_string('strftimedatefullshort', 'langconfig'))
                : '',
            'selectedtemplateplancount' => $selected
                ? (helper::count_plans_by_template([$templateid])[$templateid] ?? 0)
                : 0,
            'selectedtemplatecohortcount' => $selected
                ? (helper::count_cohorts_by_template([$templateid])[$templateid] ?? 0)
                : 0,
            'hascompetencies' => !empty($competencies),
            'competencies' => $competencies,
            'competencycount' => count($competencies),
            'excludeids' => implode(',', $excludeids),
            'canmanage' => (int) $canmanage,
            'canassignroles' => (int) $canassignroles,
        ];
    }
}
