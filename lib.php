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
 * Library functions for local_dimensions plugin.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_dimensions\customfield\competency_handler;
use local_dimensions\customfield\lp_handler;
use local_dimensions\picture_manager;

/**
 * Serve plugin files from storage (built-in image handler).
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool false if the file not found, otherwise serve the file
 */
function local_dimensions_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    // Only serve files from our known file areas.
    $validareas = [
        picture_manager::FILEAREA_COMPETENCY,
        picture_manager::FILEAREA_TEMPLATE,
        picture_manager::FILEAREA_COMPETENCY_CARD,
        picture_manager::FILEAREA_TEMPLATE_CARD,
    ];

    if (!in_array($filearea, $validareas)) {
        return false;
    }

    // Context must be system (that's where we store the files).
    if ($context->contextlevel !== CONTEXT_SYSTEM) {
        return false;
    }

    $itemid = array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_dimensions', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false;
    }

    send_stored_file($file, DAYSECS, 0, $forcedownload, $options);
}

/**
 * Extend the competency form to include custom fields.
 *
 * @param MoodleQuickForm $form The form object.
 */
function local_dimensions_extend_form($form) {
    if ($form instanceof \tool_lp\form\competency) {
        $handler = competency_handler::create();
        $id = $form->_form->getElementValue('id') ?? 0;
        if (is_array($id)) {
            $id = reset($id);
        }
        $id = (int) $id;
        $handler->instance_form_definition($form->_form, $id);
    }
}

/**
 * Returns the custom field handler for the given component and area.
 *
 * @param string $component The component name.
 * @param string $area The area name.
 * @param int $itemid The item ID.
 * @return \core_customfield\handler|null The handler or null if not found.
 */
function local_dimensions_customfield_get_handler(string $component, string $area, int $itemid = 0): ?\core_customfield\handler {
    if ($component !== 'local_dimensions') {
        return null;
    }

    switch ($area) {
        case 'competency':
            return competency_handler::create($itemid);
        case 'lp':
            return lp_handler::create($itemid);
        default:
            return null;
    }
}

/**
 * Store the return URL and valid course IDs in session.
 *
 * @param moodle_url $url The URL to store as return destination.
 * @param array $validcourseids Array of course IDs where the button should appear.
 */
function local_dimensions_set_return_context(moodle_url $url, array $validcourseids = []): void {
    global $SESSION;
    $SESSION->local_dimensions_return_url = $url->out(false);
    $SESSION->local_dimensions_valid_courses = $validcourseids;
}

/**
 * Get the stored return context from session.
 *
 * @return array|null Array with 'url' and 'courses' keys, or null if not set.
 */
function local_dimensions_get_return_context(): ?array {
    global $SESSION;
    if (empty($SESSION->local_dimensions_return_url)) {
        return null;
    }
    return [
        'url' => $SESSION->local_dimensions_return_url,
        'courses' => $SESSION->local_dimensions_valid_courses ?? [],
    ];
}

/**
 * Clear the return context from session.
 *
 */
function local_dimensions_clear_return_context(): void {
    global $SESSION;
    unset($SESSION->local_dimensions_return_url);
    unset($SESSION->local_dimensions_valid_courses);
}

/**
 * Legacy function for backwards compatibility.
 *
 * @param moodle_url $url The URL to store as return destination.
 * @deprecated Use local_dimensions_set_return_context() instead.
 */
function local_dimensions_set_return_url(moodle_url $url): void {
    local_dimensions_set_return_context($url, []);
}

/**
 * Legacy function for backwards compatibility.
 *
 * @deprecated Use local_dimensions_clear_return_context() instead.
 */
function local_dimensions_clear_return_url(): void {
    local_dimensions_clear_return_context();
}
