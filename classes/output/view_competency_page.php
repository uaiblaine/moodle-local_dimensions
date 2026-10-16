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
 * View competency page renderable.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\output;

use renderable;
use templatable;
use renderer_base;
use moodle_url;
use local_dimensions\calculator;
use local_dimensions\constants;
use local_dimensions\scss_manager;

/**
 * Renderable class for the view competency page.
 *
 * Prepares all data needed for the view_competency template.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class view_competency_page implements renderable, templatable {
    use customfield_reader;

    /** @var \stdClass|null The competency record, or null when the page has none */
    private readonly ?\stdClass $competency;

    /** @var array The list of courses */
    private readonly array $courses;

    /** @var int The learner the cards describe: the plan owner */
    private readonly int $userid;

    /** @var int The user viewing the page */
    private readonly int $viewerid;

    /**
     * Constructor.
     *
     * @param \stdClass|false|null $competency The competency record; false (a failed get_record) or null when there is none
     * @param array $courses Array of course records
     * @param int $userid The learner the cards describe: the plan owner
     * @param int $viewerid The user viewing the page; 0 when it is the learner
     */
    public function __construct(\stdClass|false|null $competency, array $courses, int $userid, int $viewerid = 0) {
        $this->competency = $competency ?: null;
        $this->courses = $courses;
        $this->userid = $userid;
        $this->viewerid = $viewerid > 0 ? $viewerid : $userid;
    }

    /**
     * Export data for use in a Mustache template.
     *
     * @param renderer_base $output The renderer
     * @return array Template context data
     */
    #[\Override]
    public function export_for_template(renderer_base $output): array {
        // Get percentage display mode setting.
        $percentagemode = get_config('local_dimensions', 'percentagedisplaymode');
        if (empty($percentagemode)) {
            $percentagemode = 'hover';
        }

        $data = [
            'hascompetency' => !empty($this->competency),
            'hascourses' => !empty($this->courses),
            'hero' => null,
            'courses' => [],
            'percentagemode' => $percentagemode,
        ];

        // Prepare hero header data.
        if ($this->competency) {
            $bgcolor = $this->get_colour_field((int) $this->competency->id, constants::CFIELD_CUSTOMBGCOLOR, 'competency');
            $textcolor = $this->get_colour_field((int) $this->competency->id, constants::CFIELD_CUSTOMTEXTCOLOR, 'competency');
            $bgimage = $this->get_custom_field_image_url(
                $this->competency->id,
                constants::CFIELD_CUSTOMBGIMAGE,
                'competency'
            );

            $herodescription = format_text(
                $this->competency->description,
                $this->competency->descriptionformat
            );

            // The fold is stored per competency, not per plan: this hero is the competency's.
            $herostate = \local_dimensions\helper::resolve_hero_state('c' . (int) $this->competency->id);

            $data['hero'] = [
                'title' => format_string($this->competency->shortname),
                'description' => [
                    'html' => $herodescription,
                    'id' => 'local-dimensions-competency-' . (int) $this->competency->id . '-desc',
                ],
                'hasdescription' => !empty($this->competency->description),
                'bgcolor' => $bgcolor,
                'hasbgcolor' => !empty($bgcolor),
                'textcolor' => $textcolor,
                'hastextcolor' => !empty($textcolor),
                'bgimage' => $bgimage,
                'hasbgimage' => !empty($bgimage),
                'slim' => $herostate['slim'],
                'herokey' => $herostate['key'],
                'herostatejson' => $herostate['statejson'],
            ];
        }

        // Prepare course cards data.
        // Resolve the chip-filter shortnames once, then read the values in batch.
        $courseshortnames = \local_dimensions\chip_filters::parse_shortnames(
            (string) get_config('local_dimensions', 'viewcompetency_filter_fields_course')
        );

        $courseids = array_map('intval', array_keys($this->courses));
        $coursevalues = !empty($courseshortnames)
            ? \local_dimensions\chip_filters::get_course_values($courseids, $courseshortnames)
            : [];

        foreach ($this->courses as $course) {
            /* Whether the viewer can open the course from the card, as the card services answer it: whether
               the viewer is actively enrolled, on the learner's own page and on a reviewer's alike. */
            $locked = calculator::is_locked_for_viewer($course, $this->userid, $this->viewerid);
            $cid = (int) $course->id;
            /* Course-area values for client-side chip filtering, keyed "course:<shortname>" to
               match the group shortnames remapped below; chip_filters.js pairs them by key. */
            $combinedvalues = [];
            foreach (($coursevalues[$cid] ?? []) as $sn => $val) {
                $combinedvalues['course:' . $sn] = $val;
            }
            /* fullname lands in a triple stash, so it keeps the escaped spelling; the label lands in
               a double-stashed aria-label, so it is built from the plain one. */
            $coursecontext = \core\context\course::instance($cid);
            $plainname = format_string($course->fullname, true, ['context' => $coursecontext, 'escape' => false]);
            $data['courses'][] = [
                'courseid' => $course->id,
                'fullname' => format_string($course->fullname, true, ['context' => $coursecontext]),
                'courseurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                'viewcoursestr' => get_string('view_course', 'local_dimensions', $plainname),
                'locked' => $locked,
                'filtervaluesjson' => json_encode((object) $combinedvalues),
            ];
        }

        /* Build the chip-filter groups, course area only: a competency-area group would give
           every card the page's single competency's value, so a chip would match all or none. */
        $chipgroups = [];
        if (!empty($courseshortnames)) {
            $courselabels = \local_dimensions\chip_filters::get_field_labels('course', $courseshortnames);
            $coursegroups = \local_dimensions\chip_filters::build_filterfields_payload(
                $courseshortnames,
                $coursevalues,
                $courselabels
            );
            // Remap shortnames to the "course:" prefix so DOM lookups match.
            foreach ($coursegroups as &$g) {
                $g['shortname'] = 'course:' . $g['shortname'];
            }
            unset($g);
            $chipgroups = array_merge($chipgroups, $coursegroups);
        }

        /* The filter line's scope opens on the learner's open work ("To do") unless they chose "All"
           on an earlier visit, and the whole stored state goes to the client, which writes the
           preference back entire: a key this page left out would be reset by its next save. */
        $view = \local_dimensions\local\learner_view::resolve();
        $data['scope_is_incomplete'] = ($view['trackerfilter'] === 'incomplete');
        $data['scope_is_all'] = ($view['trackerfilter'] === 'all');
        $data['viewstatejson'] = json_encode($view);

        $data['chipfilters'] = [
            'id' => 'local-dimensions-viewcompetency-chip-filters',
            'controlsid' => 'local-dimensions-viewcompetency-grid',
            'hasgroups' => !empty($chipgroups),
            'groups' => $chipgroups,
            'clearlabel' => get_string('filter_chip_clear', 'local_dimensions'),
        ];
        $data['haschipfilters'] = !empty($chipgroups);
        $data['line'] = [
            'id' => 'local-dimensions-viewcompetency',
            'scopelabel' => get_string('filter_courses', 'local_dimensions'),
            // The counts arrive with the completion batch; competency_view reveals the scope then.
            'scopehidden' => true,
            'scope' => [
                [
                    'value' => 'incomplete',
                    'label' => get_string('todo', 'local_dimensions'),
                    'count' => 0,
                    'hascount' => false,
                    'checked' => $data['scope_is_incomplete'],
                    'absent' => false,
                ],
                [
                    'value' => 'all',
                    'label' => get_string('filter_all', 'local_dimensions'),
                    'count' => 0,
                    'hascount' => false,
                    'checked' => $data['scope_is_all'],
                    'absent' => false,
                ],
            ],
            'searchlabel' => get_string('line_search_courses_label', 'local_dimensions'),
            'searchplaceholder' => get_string('line_search_courses', 'local_dimensions'),
            'itemslabel' => get_string('line_suggest_courses', 'local_dimensions'),
            'hasstar' => false,
            'haschipfilters' => $data['haschipfilters'],
            'chipfilters' => $data['chipfilters'],
            'showcount' => count($data['courses']),
            'hasorganise' => false,
            'hasviews' => false,
        ];

        // Get compiled custom CSS from competency SCSS if feature is enabled.
        if (get_config('local_dimensions', 'enablecustomscss') && $this->competency) {
            $customcss = scss_manager::get_compiled_css($this->competency->id, 'competency');
            if (!empty($customcss)) {
                $data['customcss'] = $customcss;
                $data['hascustomcss'] = true;
            }
        }

        return $data;
    }
}
