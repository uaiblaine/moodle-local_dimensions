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
 * List a competency's linked courses with per-method status for the Enrolment methods tab.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core_competency\course_competency;
use core_competency\template;
use core_competency\template_competency;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_dimensions\local\enrol_methods;
use local_dimensions\task\process_enrol_method;

/**
 * Web service: one competency's configurable courses, loaded when its accordion group expands.
 *
 * Each row carries the status of BOTH methods against the selected cohort (configured /
 * processing / notconfigured plus the configured-since date), so switching the method
 * segment only repaints client-side and the details modal needs no extra call.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class list_enrol_courses extends external_api {
    /** @var int Hard cap on the page size. */
    const MAX_LIMIT = 100;

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'templateid' => new external_value(PARAM_INT, 'Learning plan template id'),
            'competencyid' => new external_value(PARAM_INT, 'Competency id (must belong to the template)'),
            'cohortid' => new external_value(PARAM_INT, 'Cohort the statuses are evaluated against'),
            'categoryid' => new external_value(PARAM_INT, 'Course category filter (0 = all)', VALUE_DEFAULT, 0),
            'includehidden' => new external_value(PARAM_BOOL, 'Include hidden courses', VALUE_DEFAULT, false),
            'limitfrom' => new external_value(PARAM_INT, 'Pagination offset', VALUE_DEFAULT, 0),
            'limitnum' => new external_value(PARAM_INT, 'Page size', VALUE_DEFAULT, 25),
        ]);
    }

    /**
     * List the competency's configurable courses with both methods' status.
     *
     * @param int $templateid Template id.
     * @param int $competencyid Competency id.
     * @param int $cohortid Cohort id (must be attached to the template).
     * @param int $categoryid Course category filter (0 = all).
     * @param bool $includehidden Whether hidden courses are listed.
     * @param int $limitfrom Pagination offset.
     * @param int $limitnum Page size.
     * @return array Keys: items, total.
     */
    public static function execute(
        int $templateid,
        int $competencyid,
        int $cohortid,
        int $categoryid = 0,
        bool $includehidden = false,
        int $limitfrom = 0,
        int $limitnum = 25
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'templateid' => $templateid,
            'competencyid' => $competencyid,
            'cohortid' => $cohortid,
            'categoryid' => $categoryid,
            'includehidden' => $includehidden,
            'limitfrom' => $limitfrom,
            'limitnum' => $limitnum,
        ]);
        $template = template::get_record(['id' => $params['templateid']]);
        if (!$template) {
            return ['items' => [], 'total' => 0];
        }
        $context = $template->get_context();
        self::validate_context($context);
        require_capability('moodle/competency:templatemanage', $context);
        if (!enrol_methods::cohort_linked($template->get('id'), $params['cohortid'])) {
            throw new \moodle_exception('central_roles_cohortnotlinked', 'local_dimensions');
        }
        $intemplate = $DB->record_exists(template_competency::TABLE, [
            'templateid' => $template->get('id'),
            'competencyid' => $params['competencyid'],
        ]);
        if (!$intemplate) {
            return ['items' => [], 'total' => 0];
        }

        $courseids = array_map(static function ($course) {
            return (int) $course->id;
        }, course_competency::list_courses_min($params['competencyid']));
        $records = enrol_methods::course_records($courseids);
        $allowed = enrol_methods::allowed_map(array_keys($records));

        $courses = [];
        foreach ($records as $course) {
            $courseid = (int) $course->id;
            if (!isset($allowed[$courseid])) {
                continue;
            }
            if (!$params['includehidden'] && !(int) $course->visible) {
                continue;
            }
            if ($params['categoryid'] && (int) $course->category !== $params['categoryid']) {
                continue;
            }
            $courses[] = $course;
        }
        \core_collator::asort_objects_by_property($courses, 'shortname', \core_collator::SORT_NATURAL);
        $courses = array_values($courses);
        $total = count($courses);
        $courses = array_slice($courses, $params['limitfrom'], min($params['limitnum'], self::MAX_LIMIT));

        $pageids = array_map(static function ($course) {
            return (int) $course->id;
        }, $courses);
        $statuses = enrol_methods::status_map($pageids, $params['cohortid']);
        $pending = process_enrol_method::pending_map();
        $rolenames = self::role_names($statuses, $context);
        $dateformat = get_string('strftimedaydatetime', 'langconfig');

        $items = [];
        foreach ($courses as $course) {
            $courseid = (int) $course->id;
            $coursecontext = \context_course::instance($courseid);
            $item = [
                'courseid' => $courseid,
                'shortname' => format_string($course->shortname, true, ['context' => $coursecontext]),
                'fullname' => format_string($course->fullname, true, ['context' => $coursecontext]),
                'categoryname' => format_string((string) $course->categoryname, true, ['context' => $context]),
                'visible' => (bool) $course->visible,
                'courseurl' => (new \moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
            ];
            foreach ([process_enrol_method::METHOD_COHORT, process_enrol_method::METHOD_SELF] as $method) {
                $state = $statuses[$courseid][$method];
                $key = process_enrol_method::key($courseid, $method, $params['cohortid']);
                if (isset($pending[$key])) {
                    $status = 'processing';
                } else {
                    $status = $state->configured ? 'configured' : 'notconfigured';
                }
                $item[$method . 'status'] = $status;
                $item[$method . 'configuredsince'] = ($state->configured && $state->since)
                    ? userdate($state->since, $dateformat)
                    : '';
                $item[$method . 'active'] = $state->configured && $state->active;
                $item[$method . 'rolename'] = $state->configured ? ($rolenames[$state->roleid] ?? '') : '';
            }
            $items[] = $item;
        }
        return ['items' => $items, 'total' => $total];
    }

    /**
     * Localised names of the roles referenced by the page's enrol instances.
     *
     * @param array $statuses Status map from enrol_methods::status_map().
     * @param \context $context Context the role names are localised for.
     * @return array Map of roleid => localised role name.
     */
    private static function role_names(array $statuses, \context $context): array {
        $roleids = [];
        foreach ($statuses as $methods) {
            foreach ($methods as $state) {
                if ($state->roleid) {
                    $roleids[$state->roleid] = true;
                }
            }
        }
        if (!$roleids) {
            return [];
        }
        $names = [];
        foreach (role_fix_names(get_all_roles(), $context, ROLENAME_ALIAS) as $role) {
            if (isset($roleids[(int) $role->id])) {
                $names[(int) $role->id] = $role->localname;
            }
        }
        return $names;
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(new external_single_structure([
                'courseid' => new external_value(PARAM_INT, 'Course id'),
                'shortname' => new external_value(PARAM_TEXT, 'Course short name'),
                'fullname' => new external_value(PARAM_TEXT, 'Course full name'),
                'categoryname' => new external_value(PARAM_TEXT, 'Course category name'),
                'visible' => new external_value(PARAM_BOOL, 'Whether the course is visible'),
                'courseurl' => new external_value(PARAM_URL, 'Course view URL'),
                'cohortstatus' => new external_value(PARAM_ALPHA, 'Cohort sync: configured|processing|notconfigured'),
                'cohortconfiguredsince' => new external_value(PARAM_TEXT, 'Cohort sync configured-since date, or empty'),
                'cohortactive' => new external_value(PARAM_BOOL, 'Whether the cohort sync instance is enabled'),
                'cohortrolename' => new external_value(PARAM_TEXT, 'Role the cohort sync instance assigns, or empty'),
                'selfstatus' => new external_value(PARAM_ALPHA, 'Self enrolment: configured|processing|notconfigured'),
                'selfconfiguredsince' => new external_value(PARAM_TEXT, 'Self enrolment configured-since date, or empty'),
                'selfactive' => new external_value(PARAM_BOOL, 'Whether the self enrolment instance is enabled'),
                'selfrolename' => new external_value(PARAM_TEXT, 'Role the self enrolment instance assigns, or empty'),
            ])),
            'total' => new external_value(PARAM_INT, 'Total configurable courses after filters'),
        ]);
    }
}
