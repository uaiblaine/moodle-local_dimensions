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

    'local_dimensions_get_courses_completion_status' => [
        'classname' => 'local_dimensions\external\get_courses_completion_status',
        'methodname' => 'execute',
        'classpath' => 'local/dimensions/classes/external/get_courses_completion_status.php',
        'description' => 'Lightweight batch lookup of completion + lock status for many courses.',
        'type' => 'read',
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
    'local_dimensions_get_competency_rule_data' => [
        'classname' => 'local_dimensions\external\get_competency_rule_data',
        'methodname' => 'execute',
        'classpath' => 'local/dimensions/classes/external/get_competency_rule_data.php',
        'description' => 'Get competency rule data (children, points, required status) for the Rules tab.',
        'type' => 'read',
        'ajax' => true,
    ],

    // Competency template CRUD with local_dimensions customfield support.
    'local_dimensions_create_template' => [
        'classname' => 'local_dimensions\external\create_template',
        'methodname' => 'execute',
        'description' => 'Create a learning plan template, including local_dimensions custom fields.',
        'type' => 'write',
        'capabilities' => 'moodle/competency:templatemanage',
        'ajax' => true,
    ],
    'local_dimensions_update_template' => [
        'classname' => 'local_dimensions\external\update_template',
        'methodname' => 'execute',
        'description' => 'Update a learning plan template, including local_dimensions custom fields.',
        'type' => 'write',
        'capabilities' => 'moodle/competency:templatemanage',
        'ajax' => true,
    ],
    'local_dimensions_read_template' => [
        'classname' => 'local_dimensions\external\read_template',
        'methodname' => 'execute',
        'description' => 'Read a learning plan template, including local_dimensions custom fields.',
        'type' => 'read',
        'capabilities' => 'moodle/competency:templateview',
        'ajax' => true,
    ],

    // Competency CRUD with local_dimensions customfield support.
    'local_dimensions_create_competency' => [
        'classname' => 'local_dimensions\external\create_competency',
        'methodname' => 'execute',
        'description' => 'Create a competency, including local_dimensions custom fields.',
        'type' => 'write',
        'capabilities' => 'moodle/competency:competencymanage',
        'ajax' => true,
    ],
    'local_dimensions_update_competency' => [
        'classname' => 'local_dimensions\external\update_competency',
        'methodname' => 'execute',
        'description' => 'Update a competency, including local_dimensions custom fields.',
        'type' => 'write',
        'capabilities' => 'moodle/competency:competencymanage',
        'ajax' => true,
    ],
    'local_dimensions_read_competency' => [
        'classname' => 'local_dimensions\external\read_competency',
        'methodname' => 'execute',
        'description' => 'Read a competency, including local_dimensions custom fields.',
        'type' => 'read',
        'capabilities' => 'moodle/competency:competencyview',
        'ajax' => true,
    ],

    // Competency hub Plans tab — autocomplete search.
    'local_dimensions_search_competencies' => [
        'classname' => 'local_dimensions\external\search_competencies',
        'methodname' => 'execute',
        'description' => 'Search competencies across readable frameworks for the Competency hub.',
        'type' => 'read',
        'capabilities' => 'moodle/competency:competencyview',
        'ajax' => true,
    ],

    'local_dimensions_browse_competencies' => [
        'classname' => 'local_dimensions\external\browse_competencies',
        'methodname' => 'execute',
        'description' => 'Browse a framework\'s competencies as a lazy tree, or search within it.',
        'type' => 'read',
        'capabilities' => 'moodle/competency:competencyview',
        'ajax' => true,
    ],
    'local_dimensions_browse_structure' => [
        'classname' => 'local_dimensions\external\browse_structure',
        'methodname' => 'execute',
        'description' => 'Browse a framework\'s competencies as a paginated lazy tree for the Structure tab.',
        'type' => 'read',
        'capabilities' => 'moodle/competency:competencyview',
        'ajax' => true,
    ],

    // Competency hub Plans tab — template cohort management + background plan sync.
    'local_dimensions_list_template_cohorts' => [
        'classname' => 'local_dimensions\external\list_template_cohorts',
        'methodname' => 'execute',
        'description' => 'List the cohorts attached to a learning plan template.',
        'type' => 'read',
        'capabilities' => 'moodle/competency:templateview',
        'ajax' => true,
    ],
    'local_dimensions_add_template_cohort' => [
        'classname' => 'local_dimensions\external\add_template_cohort',
        'methodname' => 'execute',
        'description' => 'Attach a cohort to a template and queue background plan generation.',
        'type' => 'write',
        'capabilities' => 'moodle/competency:templatemanage',
        'ajax' => true,
    ],
    'local_dimensions_sync_template_cohort' => [
        'classname' => 'local_dimensions\external\sync_template_cohort',
        'methodname' => 'execute',
        'description' => 'Queue background plan generation for a template cohort.',
        'type' => 'write',
        'capabilities' => 'moodle/competency:templatemanage',
        'ajax' => true,
    ],
    'local_dimensions_remove_template_cohort' => [
        'classname' => 'local_dimensions\external\remove_template_cohort',
        'methodname' => 'execute',
        'description' => 'Detach a cohort from a template (keeps existing plans).',
        'type' => 'write',
        'capabilities' => 'moodle/competency:templatemanage',
        'ajax' => true,
    ],

    // Competency hub Plans tab — individual-user participants grid.
    'local_dimensions_list_template_participants' => [
        'classname' => 'local_dimensions\external\list_template_participants',
        'methodname' => 'execute',
        'description' => 'List a template\'s participants (plans) with cohort/individual filters.',
        'type' => 'read',
        'capabilities' => 'moodle/competency:templateview',
        'ajax' => true,
    ],
    'local_dimensions_add_template_user_plan' => [
        'classname' => 'local_dimensions\external\add_template_user_plan',
        'methodname' => 'execute',
        'description' => 'Assign a template to an individual user (creates a plan).',
        'type' => 'write',
        'capabilities' => 'moodle/competency:templatemanage',
        'ajax' => true,
    ],
    'local_dimensions_unlink_template_user_plan' => [
        'classname' => 'local_dimensions\external\unlink_template_user_plan',
        'methodname' => 'execute',
        'description' => 'Unlink a user\'s plan from its template (becomes individual).',
        'type' => 'write',
        'capabilities' => 'moodle/competency:planmanage',
        'ajax' => true,
    ],
    'local_dimensions_delete_template_user_plan' => [
        'classname' => 'local_dimensions\external\delete_template_user_plan',
        'methodname' => 'execute',
        'description' => 'Delete a user\'s plan created from a template.',
        'type' => 'write',
        'capabilities' => 'moodle/competency:planmanage',
        'ajax' => true,
    ],

    // Competency hub Structure tab — competency course/activity links.
    'local_dimensions_get_competency_links' => [
        'classname' => 'local_dimensions\external\get_competency_links',
        'methodname' => 'execute',
        'description' => 'List the courses linked to a competency with rule outcome.',
        'type' => 'read',
        'capabilities' => 'moodle/competency:competencyview',
        'ajax' => true,
    ],
    'local_dimensions_search_linkable_courses' => [
        'classname' => 'local_dimensions\external\search_linkable_courses',
        'methodname' => 'execute',
        'description' => 'Search courses the user may link to a competency.',
        'type' => 'read',
        'capabilities' => 'moodle/competency:competencyview',
        'ajax' => true,
    ],
    'local_dimensions_link_competency_course' => [
        'classname' => 'local_dimensions\external\link_competency_course',
        'methodname' => 'execute',
        'description' => 'Link a competency to a course.',
        'type' => 'write',
        'capabilities' => 'moodle/competency:coursecompetencymanage',
        'ajax' => true,
    ],
    'local_dimensions_unlink_competency_course' => [
        'classname' => 'local_dimensions\external\unlink_competency_course',
        'methodname' => 'execute',
        'description' => 'Unlink a competency from a course.',
        'type' => 'write',
        'capabilities' => 'moodle/competency:coursecompetencymanage',
        'ajax' => true,
    ],
    'local_dimensions_set_course_link_outcome' => [
        'classname' => 'local_dimensions\external\set_course_link_outcome',
        'methodname' => 'execute',
        'description' => 'Set the rule outcome of a course-competency link.',
        'type' => 'write',
        'capabilities' => 'moodle/competency:coursecompetencymanage',
        'ajax' => true,
    ],
    'local_dimensions_get_competency_module_links' => [
        'classname' => 'local_dimensions\external\get_competency_module_links',
        'methodname' => 'execute',
        'description' => 'List a course\'s activities for a competency (linked and available).',
        'type' => 'read',
        'capabilities' => 'moodle/competency:coursecompetencyview',
        'ajax' => true,
    ],
    'local_dimensions_link_competency_module' => [
        'classname' => 'local_dimensions\external\link_competency_module',
        'methodname' => 'execute',
        'description' => 'Link a competency to a course module.',
        'type' => 'write',
        'capabilities' => 'moodle/competency:coursecompetencymanage',
        'ajax' => true,
    ],
    'local_dimensions_unlink_competency_module' => [
        'classname' => 'local_dimensions\external\unlink_competency_module',
        'methodname' => 'execute',
        'description' => 'Unlink a competency from a course module.',
        'type' => 'write',
        'capabilities' => 'moodle/competency:coursecompetencymanage',
        'ajax' => true,
    ],
    'local_dimensions_set_module_link_outcome' => [
        'classname' => 'local_dimensions\external\set_module_link_outcome',
        'methodname' => 'execute',
        'description' => 'Set the rule outcome of a module-competency link.',
        'type' => 'write',
        'capabilities' => 'moodle/competency:coursecompetencymanage',
        'ajax' => true,
    ],

    // Competency hub Frameworks tab.
    'local_dimensions_set_framework_visibility' => [
        'classname' => 'local_dimensions\external\set_framework_visibility',
        'methodname' => 'execute',
        'description' => 'Toggle a competency framework\'s visibility.',
        'type' => 'write',
        'capabilities' => 'moodle/competency:competencymanage',
        'ajax' => true,
    ],

    // Competency hub Plans tab — cohort role assignments.
    'local_dimensions_list_template_cohort_roles' => [
        'classname' => 'local_dimensions\external\list_template_cohort_roles',
        'methodname' => 'execute',
        'description' => 'List cohort role assignments over a learning plan\'s cohorts, with sync status.',
        'type' => 'read',
        'capabilities' => 'moodle/role:manage',
        'ajax' => true,
    ],
    'local_dimensions_add_cohort_role' => [
        'classname' => 'local_dimensions\external\add_cohort_role',
        'methodname' => 'execute',
        'description' => 'Assign a user-context role to a user over a learning plan cohort.',
        'type' => 'write',
        'capabilities' => 'moodle/role:manage',
        'ajax' => true,
    ],
    'local_dimensions_remove_cohort_role' => [
        'classname' => 'local_dimensions\external\remove_cohort_role',
        'methodname' => 'execute',
        'description' => 'Remove a cohort role assignment.',
        'type' => 'write',
        'capabilities' => 'moodle/role:manage',
        'ajax' => true,
    ],
];
