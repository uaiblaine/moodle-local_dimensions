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
 * External services definition.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_dimensions_get_course_progress' => [
        'classname' => 'local_dimensions\external\get_course_progress',
        'methodname' => 'execute',
        'classpath' => 'local/dimensions/classes/external/get_course_progress.php',
        'description' => 'Calculate the progress of course sections.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_dimensions_get_comments' => [
        'classname' => 'local_dimensions\external\get_comments',
        'methodname' => 'execute',
        'classpath' => 'local/dimensions/classes/external/get_comments.php',
        'description' => 'Get comments for a user competency.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_dimensions_add_comment' => [
        'classname' => 'local_dimensions\external\add_comment',
        'methodname' => 'execute',
        'classpath' => 'local/dimensions/classes/external/add_comment.php',
        'description' => 'Add a comment to a user competency.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_dimensions_get_fontawesome_icons' => [
        'classname' => 'local_dimensions\external\get_fontawesome_icons',
        'methodname' => 'execute',
        'classpath' => 'local/dimensions/classes/external/get_fontawesome_icons.php',
        'description' => 'Get FontAwesome icons matching a search query for the icon picker.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_dimensions_get_competency_courses' => [
        'classname' => 'local_dimensions\external\get_competency_courses',
        'methodname' => 'execute',
        'classpath' => 'local/dimensions/classes/external/get_competency_courses.php',
        'description' => 'Get courses linked to a competency with enrollment filter applied.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_dimensions_get_user_competency_summary_in_plan' => [
        'classname' => 'local_dimensions\external\get_user_competency_summary_in_plan',
        'methodname' => 'execute',
        'classpath' => 'local/dimensions/classes/external/get_user_competency_summary_in_plan.php',
        'description' => 'Get user competency summary in plan (wrapper to avoid context issues with theme string loading).',
        'type' => 'read',
        'ajax' => true,
    ],
];
