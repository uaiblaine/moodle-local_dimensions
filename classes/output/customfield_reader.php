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
 * Shared custom-field reader for the learner-view renderables.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\output;

use local_dimensions\constants;
use local_dimensions\helper;
use local_dimensions\picture_manager;

/**
 * Resolves competency/template custom fields (colour + image) with per-render memoisation.
 *
 * Used by view_competency_page and view_plan_summary_page so the field-resolution,
 * image-URL and hex-colour logic lives in one place.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait customfield_reader {
    /**
     * Memoised cache of resolved custom-field controllers, keyed by "{area}|{shortname}".
     * Avoids repeating the handler walk inside per-instance loops.
     *
     * @var array<string, \core_customfield\field_controller|false>
     */
    private $fieldcache = [];

    /**
     * Resolve a custom field controller by shortname, memoised per render.
     *
     * @param string $shortname Custom field shortname.
     * @param string $area Custom field area ('lp' or 'competency').
     * @return \core_customfield\field_controller|null
     */
    private function get_field(string $shortname, string $area): ?\core_customfield\field_controller {
        $key = $area . '|' . $shortname;
        if (!array_key_exists($key, $this->fieldcache)) {
            $this->fieldcache[$key] = helper::find_field_by_shortname($shortname, $area) ?? false;
        }
        return $this->fieldcache[$key] ?: null;
    }

    /**
     * Fetch the data_controller for a given field/instance pair.
     *
     * Returns null when the customfield_data row does not exist (the API
     * synthesises a default controller in that case; we discriminate by
     * checking the persisted id).
     *
     * @param \core_customfield\field_controller $field
     * @param int $instanceid
     * @return \core_customfield\data_controller|null
     */
    private function get_field_data(
        \core_customfield\field_controller $field,
        int $instanceid
    ): ?\core_customfield\data_controller {
        $datas = \core_customfield\api::get_instance_fields_data(
            [$field->get('id') => $field],
            $instanceid
        );
        foreach ($datas as $data) {
            if ((int) $data->get('id') > 0) {
                return $data;
            }
        }
        return null;
    }

    /**
     * Get a custom field color value for a competency.
     *
     * @param int $competencyid The competency ID.
     * @param string $shortname The field shortname to retrieve.
     * @return string|null The field value or null if not found.
     */
    protected function get_competency_custom_field(int $competencyid, string $shortname): ?string {
        // Field controller is memoised across calls, so the per-shortname lookup
        // happens once for the whole render even though this method runs inside
        // the per-competency loop.
        $field = $this->get_field($shortname, 'competency');
        if (!$field) {
            return null;
        }

        $data = $this->get_field_data($field, $competencyid);
        if (!$data) {
            return null;
        }

        $value = trim((string) $data->get('value'));
        if ($value === '') {
            return null;
        }

        // For text/color fields, the value is directly stored.
        // Validate it looks like a hex color.
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
            $type = ($shortname === constants::CFIELD_CUSTOMCARD) ? 'cardimage' : 'bgimage';
            $url = picture_manager::get_image_url($area, $instanceid, $type);
            if ($url) {
                return $url;
            }
            // Fall through to check external storage for legacy images.
        }

        // External mode: use customfield_picture component.
        $field = $this->get_field($shortname, $area);
        if (!$field) {
            return null;
        }

        $data = $this->get_field_data($field, $instanceid);
        if (!$data) {
            return null;
        }

        // Get the file from storage (using customfield_picture component).
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            (int) $data->get('contextid'),
            'customfield_picture',
            'file',
            (int) $data->get('id'),
            '',
            false
        );

        if (empty($files)) {
            return null;
        }

        $file = reset($files);
        return \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        )->out();
    }
}
