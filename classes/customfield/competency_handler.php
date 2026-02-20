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
 * Competency custom field handler.
 *
 * @package   local_dimensions
 * @copyright 2026 Anderson Blaine (anderson@blaine.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\customfield;

use core_customfield\handler;
use core_customfield\api;
use local_dimensions\picture_manager;

/**
 * Competency custom field handler.
 *
 * @package   local_dimensions
 * @copyright 2026 Anderson Blaine (anderson@blaine.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class competency_handler extends handler {
    /**
     * @var competency_handler
     */
    protected static $singleton;

    /**
     * Returns the singleton instance.
     *
     * @param int $itemid
     * @return competency_handler
     */
    public static function create(int $itemid = 0): competency_handler {
        if (static::$singleton === null) {
            static::$singleton = new static(0);
        }
        return static::$singleton;
    }

    /**
     * Run setup for the handler.
     *
     * @return \context
     */
    public function get_configuration_context(): \context {
        return \context_system::instance();
    }

    /**
     * Returns the configuration URL.
     *
     * @param \core_customfield\field_controller|null $field
     * @return \moodle_url
     */
    public function get_configuration_url(?\core_customfield\field_controller $field = null): \moodle_url {
        return new \moodle_url('/local/dimensions/customfield.php');
    }

    /**
     * Returns the context for the data instance.
     *
     * @param int $instanceid
     * @return \context
     */
    public function get_instance_context(int $instanceid = 0): \context {
        return \context_system::instance();
    }

    /**
     * Check if the current user can configure the custom fields.
     *
     * @return bool
     */
    public function can_configure(): bool {
        return has_capability('moodle/site:config', \context_system::instance());
    }

    /**
     * Check if the current user can view the custom fields.
     *
     * @param \core_customfield\field_controller $field
     * @param int $instanceid
     * @return bool
     */
    public function can_view(\core_customfield\field_controller $field, int $instanceid = 0): bool {
        return true;
    }

    /**
     * Check if the current user can edit the custom fields.
     *
     * @param \core_customfield\field_controller $field
     * @param int $instanceid
     * @return bool
     */
    public function can_edit(\core_customfield\field_controller $field, int $instanceid = 0): bool {
        return has_capability('moodle/competency:competencymanage', \context_system::instance());
    }

    /**
     * Returns the component name.
     *
     * @return string
     */
    public function get_component(): string {
        return 'local_dimensions';
    }

    /**
     * Returns the area name.
     *
     * @return string
     */
    public function get_area(): string {
        return 'competency';
    }

    /**
     * Returns the item itemid.
     *
     * @param int $instanceid
     * @return int
     */
    public function get_itemid(int $instanceid = 0): int {
        return $instanceid;
    }

    /**
     * Set up the form data for the custom field.
     *
     * @param \MoodleQuickForm $mform
     * @param int $instanceid
     * @param string|null $headerlangidentifier
     * @param string|null $headerlangcomponent
     */
    public function instance_form_definition(
        \MoodleQuickForm $mform,
        int $instanceid = 0,
        ?string $headerlangidentifier = null,
        ?string $headerlangcomponent = null
    ) {
        $mform->addElement('html', '<h2 class="mt-4 mb-3">' . get_string('customfields', 'local_dimensions') . '</h2>');
        parent::instance_form_definition($mform, $instanceid, $headerlangidentifier, $headerlangcomponent);

        // In built-in mode, add filemanagers for background and card images.
        if (picture_manager::is_builtin_mode()) {
            picture_manager::add_all_filemanagers_to_form($mform, 'competency');
        }
    }

    /**
     * Prepare form data including built-in image draft area.
     *
     * @param \stdClass $data The form data object.
     */
    public function instance_form_before_set_data_with_image(\stdClass $data): void {
        $this->instance_form_before_set_data($data);

        // In built-in mode, prepare the draft area for the image.
        if (picture_manager::is_builtin_mode() && !empty($data->id)) {
            picture_manager::prepare_all_draft_areas('competency', (int)$data->id, $data);
        }
    }

    /**
     * Save form data including built-in image.
     *
     * @param \stdClass $data The submitted form data.
     * @param bool $isnew Whether this is a new instance.
     * @param int $instanceid The competency ID.
     */
    public function instance_form_save_with_image(\stdClass $data, bool $isnew, int $instanceid): void {
        $this->instance_form_save($data, !$isnew);

        // In built-in mode, save the uploaded image.
        if (picture_manager::is_builtin_mode()) {
            picture_manager::save_all_from_form($data, 'competency', $instanceid);
        }
    }
}
