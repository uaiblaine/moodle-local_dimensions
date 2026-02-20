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
 * View plan page renderable.
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
use local_dimensions\picture_manager;
use local_dimensions\scss_manager;

/**
 * Renderable class for the view plan page.
 *
 * Prepares all data needed for the view_plan template.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class view_plan_page implements renderable, templatable {
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
            $bgcolor = $this->get_competency_custom_field($this->competency->id, 'custombgcolor');
            $textcolor = $this->get_competency_custom_field($this->competency->id, 'customtextcolor');

            $data['hero'] = [
                'title' => format_string($this->competency->shortname),
                'description' => format_text(
                    $this->competency->description,
                    $this->competency->descriptionformat
                ),
                'hasdescription' => !empty($this->competency->description),
                'bgcolor' => $bgcolor,
                'hasbgcolor' => !empty($bgcolor),
                'textcolor' => $textcolor,
                'hastextcolor' => !empty($textcolor),
                'bgimage' => $this->get_custom_field_image_url($this->competency->id, 'custombgimage', 'competency'),
                'hasbgimage' => !empty($this->get_custom_field_image_url($this->competency->id, 'custombgimage', 'competency')),
            ];
        }

        // Prepare course cards data.
        foreach ($this->courses as $course) {
            $locked = calculator::is_locked($course, $this->userid);
            $data['courses'][] = [
                'courseid' => $course->id,
                'fullname' => format_string($course->fullname),
                'courseurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                'viewcoursestr' => get_string('view_course', 'local_dimensions', format_string($course->fullname)),
                'locked' => $locked,
            ];
        }

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

    /**
     * Get a custom field value for a competency.
     *
     * @param int $competencyid The competency ID.
     * @param string $shortname The field shortname to retrieve.
     * @return string|null The field value or null if not found.
     */
    protected function get_competency_custom_field(int $competencyid, string $shortname): ?string {
        global $DB;

        // Get the field definition.
        $sql = "SELECT f.id
                  FROM {customfield_field} f
                  JOIN {customfield_category} c ON c.id = f.categoryid
                 WHERE f.shortname = :shortname
                   AND c.component = :component
                   AND c.area = :area";

        $field = $DB->get_record_sql($sql, [
            'shortname' => $shortname,
            'component' => 'local_dimensions',
            'area' => 'competency',
        ]);

        if (!$field) {
            return null;
        }

        // Get the data for this instance.
        $data = $DB->get_record('customfield_data', [
            'fieldid' => $field->id,
            'instanceid' => $competencyid,
        ]);

        if (!$data || empty($data->value)) {
            return null;
        }

        // For text/color fields, the value is directly stored.
        // Validate it looks like a hex color.
        $value = trim($data->value);
        if (preg_match('/^#?([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $value)) {
            // Ensure it starts with #.
            if ($value[0] !== '#') {
                $value = '#' . $value;
            }
            return $value;
        }

        return null;
    }

    /**
     * Get a custom field image URL.
     *
     * @param int $instanceid The instance ID (template or competency)
     * @param string $shortname The field shortname to retrieve
     * @param string $area The custom field area (lp or competency)
     * @return string|null The image URL or null if not found
     */
    protected function get_custom_field_image_url(int $instanceid, string $shortname, string $area): ?string {
        // Built-in mode: try picture_manager first, fall back to external storage.
        if (picture_manager::is_builtin_mode()) {
            $type = ($shortname === 'customcard') ? 'cardimage' : 'bgimage';
            $url = picture_manager::get_image_url($area, $instanceid, $type);
            if ($url) {
                return $url;
            }
            // Fall through to check external storage for legacy images.
        }

        // External mode: use customfield_picture component.
        global $DB;

        // Get the field definition.
        $sql = "SELECT f.id
                  FROM {customfield_field} f
                  JOIN {customfield_category} c ON c.id = f.categoryid
                 WHERE f.shortname = :shortname
                   AND c.component = :component
                   AND c.area = :area";

        $field = $DB->get_record_sql($sql, [
            'shortname' => $shortname,
            'component' => 'local_dimensions',
            'area' => $area,
        ]);

        if (!$field) {
            return null;
        }

        // Get the data for this instance.
        $data = $DB->get_record('customfield_data', [
            'fieldid' => $field->id,
            'instanceid' => $instanceid,
        ]);

        if (!$data) {
            return null;
        }

        // Get the file from storage (using customfield_picture component).
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $data->contextid,
            'customfield_picture',
            'file',
            $data->id,
            '',
            false
        );

        if (empty($files)) {
            return null;
        }

        $file = reset($files);
        return moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        )->out();
    }
}
