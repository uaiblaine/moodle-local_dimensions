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
 * Built-in picture/image manager for local_dimensions.
 *
 * Provides image upload, storage and retrieval functionality without
 * requiring the external customfield_picture plugin.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions;

use core\context\system as context_system;
use moodle_url;
use MoodleQuickForm;
use stdClass;

/**
 * Manages background images for competencies and templates.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class picture_manager {
    /** @var string Component name for file storage. */
    const COMPONENT = 'local_dimensions';

    /** @var string File area for competency background images. */
    const FILEAREA_COMPETENCY = 'competency_bgimage';

    /** @var string File area for template background images. */
    const FILEAREA_TEMPLATE = 'template_bgimage';

    /** @var string File area for competency card images. */
    const FILEAREA_COMPETENCY_CARD = 'competency_cardimage';

    /** @var string File area for template card images. */
    const FILEAREA_TEMPLATE_CARD = 'template_cardimage';

    /** @var string Form element name for competency background images. */
    const FORM_ELEMENT_COMPETENCY = 'dims_bgimage_competency';

    /** @var string Form element name for template background images. */
    const FORM_ELEMENT_TEMPLATE = 'dims_bgimage_template';

    /** @var string Form element name for competency card images. */
    const FORM_ELEMENT_COMPETENCY_CARD = 'dims_cardimage_competency';

    /** @var string Form element name for template card images. */
    const FORM_ELEMENT_TEMPLATE_CARD = 'dims_cardimage_template';

    /**
     * Check if the built-in image handler is active.
     *
     * @return bool True if built-in mode is active.
     */
    public static function is_builtin_mode(): bool {
        $mode = get_config('local_dimensions', 'imagehandler');
        // Default to built-in if not set.
        if (empty($mode)) {
            return true;
        }
        return $mode === 'builtin';
    }

    /**
     * Check if the external customfield_picture plugin is installed.
     *
     * @return bool True if the plugin is available.
     */
    public static function is_external_plugin_available(): bool {
        $pluginman = \core_plugin_manager::instance();
        $plugininfo = $pluginman->get_plugin_info('customfield_picture');
        return !empty($plugininfo);
    }

    /**
     * Get the filemanager options for image uploads.
     *
     * @return array File manager options.
     */
    public static function get_filemanager_options(): array {
        return [
            'maxbytes' => 10485760, // 10MB.
            'maxfiles' => 1,
            'subdirs' => 0,
            'accepted_types' => 'web_image',
        ];
    }

    /**
     * Get the form element name for a given area and image type.
     *
     * @param string $area 'competency' or 'lp'.
     * @param string $type 'bgimage' or 'cardimage'.
     * @return string The form element name.
     */
    public static function get_form_element_name(string $area, string $type = 'bgimage'): string {
        if ($type === 'cardimage') {
            return $area === 'competency' ? self::FORM_ELEMENT_COMPETENCY_CARD : self::FORM_ELEMENT_TEMPLATE_CARD;
        }
        return $area === 'competency' ? self::FORM_ELEMENT_COMPETENCY : self::FORM_ELEMENT_TEMPLATE;
    }

    /**
     * Get the file area for a given area type and image type.
     *
     * @param string $area 'competency' or 'lp'.
     * @param string $type 'bgimage' or 'cardimage'.
     * @return string The file area name.
     */
    public static function get_filearea(string $area, string $type = 'bgimage'): string {
        if ($type === 'cardimage') {
            return $area === 'competency' ? self::FILEAREA_COMPETENCY_CARD : self::FILEAREA_TEMPLATE_CARD;
        }
        return $area === 'competency' ? self::FILEAREA_COMPETENCY : self::FILEAREA_TEMPLATE;
    }

    /**
     * Add a filemanager element to the form for image upload.
     *
     * @param MoodleQuickForm $mform The form object.
     * @param string $area 'competency' or 'lp'.
     * @param string $type 'bgimage' or 'cardimage'.
     */
    public static function add_filemanager_to_form(MoodleQuickForm $mform, string $area, string $type = 'bgimage'): void {
        $elementname = self::get_form_element_name($area, $type);
        $langkey = $type === 'cardimage' ? 'customcard' : 'custombgimage';
        $mform->addElement(
            'filemanager',
            $elementname,
            get_string($langkey, 'local_dimensions'),
            null,
            self::get_filemanager_options()
        );
        $mform->addHelpButton($elementname, $langkey, 'local_dimensions');
    }

    /**
     * Add all built-in filemanager elements (background + card image) to the form.
     *
     * @param MoodleQuickForm $mform The form object.
     * @param string $area 'competency' or 'lp'.
     */
    public static function add_all_filemanagers_to_form(MoodleQuickForm $mform, string $area): void {
        self::add_filemanager_to_form($mform, $area, 'bgimage');
        self::add_filemanager_to_form($mform, $area, 'cardimage');
    }

    /**
     * Prepare the draft area for editing (loads existing file into the form).
     *
     * @param string $area 'competency' or 'lp'.
     * @param int $instanceid The competency or template ID.
     * @param stdClass $data The form data object to populate.
     * @param string $type 'bgimage' or 'cardimage'.
     */
    public static function prepare_draft_area(string $area, int $instanceid, stdClass $data, string $type = 'bgimage'): void {
        $elementname = self::get_form_element_name($area, $type);
        $filearea = self::get_filearea($area, $type);
        $context = context_system::instance();

        $draftid = file_get_submitted_draft_itemid($elementname);
        file_prepare_draft_area(
            $draftid,
            $context->id,
            self::COMPONENT,
            $filearea,
            $instanceid,
            self::get_filemanager_options()
        );

        $data->$elementname = $draftid;
    }

    /**
     * Prepare all draft areas (background + card image) for editing.
     *
     * @param string $area 'competency' or 'lp'.
     * @param int $instanceid The competency or template ID.
     * @param stdClass $data The form data object to populate.
     */
    public static function prepare_all_draft_areas(string $area, int $instanceid, stdClass $data): void {
        self::prepare_draft_area($area, $instanceid, $data, 'bgimage');
        self::prepare_draft_area($area, $instanceid, $data, 'cardimage');
    }

    /**
     * Save the uploaded file from the form to permanent storage.
     *
     * @param stdClass $data The submitted form data.
     * @param string $area 'competency' or 'lp'.
     * @param int $instanceid The competency or template ID.
     * @param string $type 'bgimage' or 'cardimage'.
     */
    public static function save_from_form(stdClass $data, string $area, int $instanceid, string $type = 'bgimage'): void {
        $elementname = self::get_form_element_name($area, $type);
        $filearea = self::get_filearea($area, $type);
        $context = context_system::instance();

        if (isset($data->$elementname)) {
            file_save_draft_area_files(
                $data->$elementname,
                $context->id,
                self::COMPONENT,
                $filearea,
                $instanceid,
                self::get_filemanager_options()
            );
        }
    }

    /**
     * Save all uploaded files (background + card image) from the form.
     *
     * @param stdClass $data The submitted form data.
     * @param string $area 'competency' or 'lp'.
     * @param int $instanceid The competency or template ID.
     */
    public static function save_all_from_form(stdClass $data, string $area, int $instanceid): void {
        self::save_from_form($data, $area, $instanceid, 'bgimage');
        self::save_from_form($data, $area, $instanceid, 'cardimage');
    }

    /**
     * Get the URL of a stored image.
     *
     * @param string $area 'competency' or 'lp'.
     * @param int $instanceid The competency or template ID.
     * @param string $type 'bgimage' or 'cardimage'.
     * @return string|null The image URL or null if not found.
     */
    public static function get_image_url(string $area, int $instanceid, string $type = 'bgimage'): ?string {
        $filearea = self::get_filearea($area, $type);
        $context = context_system::instance();

        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $context->id,
            self::COMPONENT,
            $filearea,
            $instanceid,
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

    /**
     * Delete all images for a given instance.
     *
     * @param string $area 'competency' or 'lp'.
     * @param int $instanceid The competency or template ID.
     */
    public static function delete_image(string $area, int $instanceid): void {
        $filearea = self::get_filearea($area);
        $context = context_system::instance();

        get_file_storage()->delete_area_files(
            $context->id,
            self::COMPONENT,
            $filearea,
            $instanceid
        );
    }
}
