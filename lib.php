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
use local_dimensions\helper;
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
    require_login();

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
 * Store per-course return URLs in session cache.
 *
 * @param moodle_url $url The URL to store as return destination.
 * @param array $validcourseids Array of course IDs where the button should appear.
 */
function local_dimensions_set_return_context(moodle_url $url, array $validcourseids = []): void {
    helper::set_return_context($url, $validcourseids);
}

/**
 * Store return context for a single course.
 *
 * Used by block_dimensions and other external callers that already know
 * the specific course being navigated to.
 *
 * @param int $courseid The course ID.
 * @param moodle_url $returnurl The URL to return to (typically a plan view page).
 */
function local_dimensions_set_return_context_for_course(int $courseid, moodle_url $returnurl): void {
    helper::set_return_context_for_course($courseid, $returnurl);
}

/**
 * Get the stored return context for a specific course.
 *
 * @param int $courseid The course ID to look up.
 * @return array|null Array with 'url' key, or null if not set.
 */
function local_dimensions_get_return_context_for_course(int $courseid): ?array {
    return helper::get_return_context_for_course($courseid);
}
