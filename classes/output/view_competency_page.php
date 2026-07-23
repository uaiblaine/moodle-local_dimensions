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

    /** @var object|null The competency object */
    private $competency;

    /** @var array The list of courses */
    private $courses;

    /** @var int The user ID */
    private $userid;

    /**
     * Constructor.
     *
     * @param object|null $competency The competency record or null
     * @param array $courses Array of course records
     * @param int $userid The current user ID
     */
    public function __construct($competency, array $courses, int $userid) {
        $this->competency = $competency;
        $this->courses = $courses;
        $this->userid = $userid;
    }

    /**
     * Export data for use in a Mustache template.
     *
     * @param renderer_base $output The renderer
     * @return array Template context data
     */
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
            $bgcolor = $this->get_competency_custom_field($this->competency->id, constants::CFIELD_CUSTOMBGCOLOR);
            $textcolor = $this->get_competency_custom_field($this->competency->id, constants::CFIELD_CUSTOMTEXTCOLOR);
            $bgimage = $this->get_custom_field_image_url(
                $this->competency->id,
                constants::CFIELD_CUSTOMBGIMAGE,
                'competency'
            );

            $herodescription = format_text(
                $this->competency->description,
                $this->competency->descriptionformat
            );

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
            $locked = calculator::is_locked($course, $this->userid);
            $cid = (int) $course->id;
            /* Course-area values for client-side chip filtering. The keys keep their
               historical "course:" prefix so stored selections and the DOM lookups in
               chip_filters.js continue to match. */
            $combinedvalues = [];
            foreach (($coursevalues[$cid] ?? []) as $sn => $val) {
                $combinedvalues['course:' . $sn] = $val;
            }
            $data['courses'][] = [
                'courseid' => $course->id,
                'fullname' => format_string($course->fullname),
                'courseurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                'viewcoursestr' => get_string('view_course', 'local_dimensions', format_string($course->fullname)),
                'locked' => $locked,
                'filtervaluesjson' => json_encode((object) $combinedvalues),
            ];
        }

        /* Build the chip-filter groups. Course-area only: a competency-area group was
           built from the page's single competency, so every card carried the same value
           and pressing a chip matched all cards or none. */
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

        $data['chipfilters'] = [
            'id' => 'local-dimensions-viewcompetency-chip-filters',
            'controlsid' => 'local-dimensions-viewcompetency-grid',
            'hasgroups' => !empty($chipgroups),
            'groups' => $chipgroups,
            'clearlabel' => get_string('filter_chip_clear', 'local_dimensions'),
        ];
        $data['haschipfilters'] = !empty($chipgroups);

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
