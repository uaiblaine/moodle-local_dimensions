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
 * Uninstall script for local_dimensions.
 *
 * Removes custom field categories, fields, data and stored files
 * that were created by this plugin.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Custom uninstall function for local_dimensions.
 *
 * @return bool True on success.
 */
function xmldb_local_dimensions_uninstall() {
    // 1. Delete all custom field categories, fields and data for both handlers.
    $handlers = [
        \local_dimensions\customfield\competency_handler::create(),
        \local_dimensions\customfield\lp_handler::create(),
    ];

    foreach ($handlers as $handler) {
        $handler->delete_all();
    }

    // 2. Delete all stored files (background and card images).
    $fs = get_file_storage();
    $context = \core\context\system::instance();
    $fileareas = [
        'competency_bgimage',
        'competency_cardimage',
        'template_bgimage',
        'template_cardimage',
    ];

    foreach ($fileareas as $filearea) {
        $fs->delete_area_files($context->id, 'local_dimensions', $filearea);
    }

    return true;
}
