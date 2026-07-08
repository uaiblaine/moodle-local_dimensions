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
 * View full plan overview page renderable.
 *
 * Displays plan with competencies in accordion timeline when
 * view-plan.php is accessed with only plan ID (no competency ID).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\output;

use renderable;
use templatable;
use renderer_base;
use core_competency\api;
use core_competency\plan;
use local_dimensions\constants;
use local_dimensions\scss_manager;

/**
 * Renderable class for the full plan overview.
 *
 * Shows all competencies in a plan with proficiency status,
 * rating and details in an accordion layout.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class view_plan_summary_page implements renderable, templatable {
    use customfield_reader;

    /** @var plan The plan object */
    private $plan;

    /**
     * Constructor.
     *
     * @param plan $plan The learning plan
     */
    public function __construct(plan $plan) {
        $this->plan = $plan;
    }

    /**
     * Export data for use in a Mustache template.
     *
     * @param renderer_base $output The renderer
     * @return array Template context data
     */
    public function export_for_template(renderer_base $output): array {
        global $DB;

        // Get percentage display mode setting.
        $percentagemode = get_config('local_dimensions', 'percentagedisplaymode');
        if (empty($percentagemode)) {
            $percentagemode = 'hover';
        }

        // Get plan template for hero header data.
        // For individual plans (no template), use the plan's own name and description.
        $template = $this->plan->get_template();
        $templatename = $template ? format_string($template->get('shortname')) : format_string($this->plan->get('name'));
        if ($template) {
            $templatedesc = format_text($template->get('description'), $template->get('descriptionformat'));
        } else {
            // Individual plan - use plan's own description.
            $templatedesc = format_text($this->plan->get('description'), $this->plan->get('descriptionformat'));
        }

        // Get template custom fields for styling.
        $bgcolor = null;
        $textcolor = null;
        if ($template) {
            $bgcolor = $this->get_template_custom_field($template->get('id'), constants::CFIELD_CUSTOMBGCOLOR);
            $textcolor = $this->get_template_custom_field($template->get('id'), constants::CFIELD_CUSTOMTEXTCOLOR);
        }

        // Get due date if set.
        $duedate = $this->plan->get('duedate');
        $duedateformatted = null;
        if ($duedate && $duedate > 0) {
            $duedateformatted = userdate($duedate, get_string('strftimedaydatetime', 'langconfig'));
        }

        $bgimage = $template ? $this->get_custom_field_image_url(
            $template->get('id'),
            constants::CFIELD_CUSTOMBGIMAGE,
            'lp'
        ) : null;

        $data = [
            'planid' => $this->plan->get('id'),
            'percentagemode' => $percentagemode,
            'hero' => [
                'title' => $templatename,
                'description' => [
                    'html' => $templatedesc,
                    'id' => 'local-dimensions-plan-' . (int) $this->plan->get('id') . '-desc',
                ],
                'hasdescription' => !empty($templatedesc),
                'bgcolor' => $bgcolor,
                'hasbgcolor' => !empty($bgcolor),
                'textcolor' => $textcolor,
                'hastextcolor' => !empty($textcolor),
                'duedate' => $duedateformatted,
                'hasduedate' => !empty($duedateformatted),
                'bgimage' => $bgimage,
                'hasbgimage' => !empty($bgimage),
                'duedateiconurl' => $output->image_url('status/calendar-light', 'local_dimensions')->out(false),
            ],
            'competencies' => [],
            'competencycount' => 0,
        ];

        // Determine which property to use based on plan status.
        $iscompleted = $this->plan->get('status') == plan::STATUS_COMPLETE;
        $ucproperty = $iscompleted ? 'usercompetencyplan' : 'usercompetency';

        // Resolve the configured accordion subline source for this template.
        // Individual plans (no template) keep the legacy "status" behaviour.
        $sublinesource = $template
            ? \local_dimensions\helper::get_template_subline_source($template->get('id'))
            : constants::SUBLINE_STATUS;
        $data['subline_is_status'] = ($sublinesource === constants::SUBLINE_STATUS);
        $data['subline_is_rating'] = ($sublinesource === constants::SUBLINE_RATING);
        $data['subline_is_text'] = in_array(
            $sublinesource,
            [constants::SUBLINE_TAG1, constants::SUBLINE_TAG2],
            true
        );

        // Configured chip-filter shortnames for this view (competency area).
        $filtershortnames = \local_dimensions\chip_filters::parse_shortnames(
            (string) get_config('local_dimensions', 'viewplan_filter_fields')
        );

        // Get all competencies in the plan with user data.
        try {
            $pclist = api::list_plan_competencies($this->plan->get('id'));
        } catch (\Exception $e) {
            $pclist = [];
        }

        foreach ($pclist as $pc) {
            $comp = $pc->competency;
            $usercomp = $pc->$ucproperty;

            // Get proficiency status for timeline marker.
            $isproficient = $usercomp ? $usercomp->get('proficiency') : false;

            // Get grade/rating.
            $grade = $usercomp ? $usercomp->get('grade') : null;
            $hasrating = !empty($grade);
            $ratingtext = get_string('not_evaluated', 'local_dimensions');
            if ($grade) {
                // Get scale value.
                $scale = $comp->get_scale();
                if ($scale) {
                    $scalevalues = $scale->load_items();
                    if (isset($scalevalues[$grade - 1])) {
                        $ratingtext = $scalevalues[$grade - 1];
                    }
                }
            }

            // Resolve the dynamic subline shown in the accordion header.
            // The legacy behaviour (rating badge / "to do" pill) lives under
            // the "status" source; other sources surface a configurable
            // custom-field value.
            $sublinetext = '';
            switch ($sublinesource) {
                case constants::SUBLINE_RATING:
                    $sublinetext = $hasrating ? $ratingtext : '';
                    break;
                case constants::SUBLINE_TAG1:
                    $sublinetext = (string) ($this->get_competency_custom_field(
                        $comp->get('id'),
                        constants::CFIELD_TAG1
                    ) ?? '');
                    break;
                case constants::SUBLINE_TAG2:
                    $sublinetext = (string) ($this->get_competency_custom_field(
                        $comp->get('id'),
                        constants::CFIELD_TAG2
                    ) ?? '');
                    break;
                case constants::SUBLINE_NONE:
                case constants::SUBLINE_STATUS:
                default:
                    // STATUS uses the existing rating/todo template block;
                    // NONE is rendered as no subline at all.
                    break;
            }

            // Read configured chip-filter values for this competency.
            $filtervalues = !empty($filtershortnames)
                ? \local_dimensions\chip_filters::get_competency_values($comp->get('id'), $filtershortnames)
                : [];

            $data['competencies'][] = [
                'id' => $comp->get('id'),
                'shortname' => format_string($comp->get('shortname')),
                'isproficient' => $isproficient,
                'rating' => $ratingtext,
                'hasrating' => $hasrating,
                'badgeproficienticonurl' => $output->image_url('status/check-circle-fill', 'local_dimensions')->out(false),
                'badgewarningiconurl' => $output->image_url('status/warning-triangle-fill', 'local_dimensions')->out(false),
                'sublinetext' => $sublinetext,
                'hassublinetext' => ($sublinetext !== ''),
                'filtervaluesjson' => json_encode((object) $filtervalues),
            ];
        }

        $data['competencycount'] = count($data['competencies']);
        $data['hascompetencies'] = !empty($data['competencies']);

        // Count incomplete (not proficient) competencies for filter UI.
        $data['incompletecount'] = count(array_filter($data['competencies'], function ($c) {
            return !$c['isproficient'];
        }));

        // Build chip-filter groups from the per-competency values collected above.
        $instancevalues = [];
        foreach ($data['competencies'] as $c) {
            $vals = json_decode($c['filtervaluesjson'], true);
            $instancevalues[$c['id']] = is_array($vals) ? $vals : [];
        }
        $fieldlabels = \local_dimensions\chip_filters::get_field_labels('competency', $filtershortnames);
        $chipgroups = \local_dimensions\chip_filters::build_filterfields_payload(
            $filtershortnames,
            $instancevalues,
            $fieldlabels
        );
        $data['chipfilters'] = [
            'id' => 'local-dimensions-viewplan-chip-filters',
            'controlsid' => 'local-dimensions-viewplan-accordion',
            'hasgroups' => !empty($chipgroups),
            'groups' => $chipgroups,
            'clearlabel' => get_string('filter_chip_clear', 'local_dimensions'),
        ];
        $data['haschipfilters'] = !empty($chipgroups);

        // Get compiled custom CSS from template SCSS if feature is enabled.
        if (get_config('local_dimensions', 'enablecustomscss') && $template) {
            $customcss = scss_manager::get_compiled_css($template->get('id'));
            if (!empty($customcss)) {
                $data['customcss'] = $customcss;
                $data['hascustomcss'] = true;
            }
        }

        return $data;
    }

    /**
     * Get a custom field value for a template.
     *
     * @param int $templateid The template ID.
     * @param string $shortname The field shortname to retrieve.
     * @return string|null The field value or null if not found.
     */
    protected function get_template_custom_field(int $templateid, string $shortname): ?string {
        $field = $this->get_field($shortname, 'lp');
        if (!$field) {
            return null;
        }

        $data = $this->get_field_data($field, $templateid);
        if (!$data) {
            return null;
        }

        $value = trim((string) $data->get('value'));
        if ($value === '') {
            return null;
        }

        // Validate hex color.
        if (preg_match('/^#?([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $value)) {
            if ($value[0] !== '#') {
                $value = '#' . $value;
            }
            return $value;
        }

        return null;
    }
}
