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
 * List a template's competencies with configurable-course counts for the Enrolment methods tab.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core_competency\api;
use core_competency\template;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_dimensions\local\enrol_methods;

/**
 * Web service: paginated competencies of a template, counting only courses the user may configure.
 *
 * Courses without the course-level enrolment capabilities are removed server-side; competencies
 * left with no configurable course are omitted. With includebootstrap the response also carries
 * the one-off data the tab needs on mount (eligible roles, categories, enabled methods).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class list_enrol_competencies extends external_api {
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
            'categoryid' => new external_value(PARAM_INT, 'Course category filter (0 = all)', VALUE_DEFAULT, 0),
            'includehidden' => new external_value(PARAM_BOOL, 'Include hidden courses', VALUE_DEFAULT, false),
            'includebootstrap' => new external_value(PARAM_BOOL, 'Include the tab bootstrap data', VALUE_DEFAULT, false),
            'query' => new external_value(PARAM_RAW_TRIMMED, 'Competency name filter', VALUE_DEFAULT, ''),
            'limitfrom' => new external_value(PARAM_INT, 'Pagination offset', VALUE_DEFAULT, 0),
            'limitnum' => new external_value(PARAM_INT, 'Page size', VALUE_DEFAULT, 20),
        ]);
    }

    /**
     * List the template's competencies with per-competency configurable-course counts.
     *
     * @param int $templateid Template id.
     * @param int $categoryid Course category filter (0 = all).
     * @param bool $includehidden Whether hidden courses count.
     * @param bool $includebootstrap Whether to include roles/categories/method availability.
     * @param string $query Competency name filter (case- and accent-insensitive).
     * @param int $limitfrom Pagination offset.
     * @param int $limitnum Page size.
     * @return array Keys: items, total, totalcourses and optionally bootstrap.
     */
    public static function execute(
        int $templateid,
        int $categoryid = 0,
        bool $includehidden = false,
        bool $includebootstrap = false,
        string $query = '',
        int $limitfrom = 0,
        int $limitnum = 20
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'templateid' => $templateid,
            'categoryid' => $categoryid,
            'includehidden' => $includehidden,
            'includebootstrap' => $includebootstrap,
            'query' => $query,
            'limitfrom' => $limitfrom,
            'limitnum' => $limitnum,
        ]);
        $template = template::get_record(['id' => $params['templateid']]);
        if (!$template) {
            return ['items' => [], 'total' => 0, 'totalcourses' => 0];
        }
        $context = $template->get_context();
        self::validate_context($context);
        require_capability('moodle/competency:templatemanage', $context);

        $bycompetency = enrol_methods::competency_course_ids($template->get('id'));
        $allids = $bycompetency ? array_merge(...array_values($bycompetency)) : [];
        $records = enrol_methods::course_records($allids);
        $allowed = enrol_methods::allowed_map(array_keys($records));

        // A course passes when the user may configure it and it survives the filters.
        $passes = static function (int $courseid) use ($records, $allowed, $params): bool {
            if (!isset($allowed[$courseid]) || !isset($records[$courseid])) {
                return false;
            }
            $course = $records[$courseid];
            if (!$params['includehidden'] && !(int) $course->visible) {
                return false;
            }
            return !$params['categoryid'] || (int) $course->category === $params['categoryid'];
        };

        $items = [];
        $passingids = [];
        $needle = self::normalize($params['query']);
        foreach (api::list_competencies_in_template($template->get('id')) as $competency) {
            $competencyid = (int) $competency->get('id');
            $shortname = format_string($competency->get('shortname'), true, ['context' => $context]);
            if ($needle !== '' && strpos(self::normalize($shortname), $needle) === false) {
                continue;
            }
            $count = 0;
            foreach ($bycompetency[$competencyid] ?? [] as $courseid) {
                if ($passes($courseid)) {
                    $count++;
                    $passingids[$courseid] = true;
                }
            }
            if (!$count) {
                continue;
            }
            $items[] = [
                'competencyid' => $competencyid,
                'shortname' => $shortname,
                'coursecount' => $count,
            ];
        }
        $total = count($items);
        $items = array_slice($items, $params['limitfrom'], min($params['limitnum'], self::MAX_LIMIT));

        $result = ['items' => $items, 'total' => $total, 'totalcourses' => count($passingids)];
        if ($params['includebootstrap']) {
            $result['bootstrap'] = self::bootstrap($context, $records, $allowed);
        }
        return $result;
    }

    /**
     * Case- and accent-insensitive normalisation for the competency name filter.
     *
     * @param string $text Raw text.
     * @return string
     */
    private static function normalize(string $text): string {
        return \core_text::strtolower(\core_text::specialtoascii($text));
    }

    /**
     * One-off data the tab needs on mount: roles, categories and method availability.
     *
     * Categories cover every configurable linked course regardless of the current filters,
     * so the category select always offers the full set.
     *
     * @param \context $context Template context (role names localisation).
     * @param array $records Course records map from course_records().
     * @param array $allowed Allowed map from allowed_map().
     * @return array Keys: roles, defaultroleid, categories, cohortenabled, selfenabled.
     */
    private static function bootstrap(\context $context, array $records, array $allowed): array {
        $eligible = enrol_methods::eligible_roles($context);
        $roles = [];
        foreach ($eligible as $roleid => $name) {
            $roles[] = ['id' => (int) $roleid, 'name' => $name];
        }
        $categories = [];
        foreach ($records as $course) {
            $courseid = (int) $course->id;
            $catid = (int) $course->category;
            if (!isset($allowed[$courseid]) || isset($categories[$catid])) {
                continue;
            }
            $categories[$catid] = [
                'id' => $catid,
                'name' => format_string((string) $course->categoryname, true, ['context' => $context]),
            ];
        }
        \core_collator::asort_array_of_arrays_by_key($categories, 'name');
        return [
            'roles' => $roles,
            'defaultroleid' => enrol_methods::default_roleid($eligible),
            'categories' => array_values($categories),
            'cohortenabled' => enrol_is_enabled('cohort'),
            'selfenabled' => enrol_is_enabled('self'),
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(new external_single_structure([
                'competencyid' => new external_value(PARAM_INT, 'Competency id'),
                'shortname' => new external_value(PARAM_TEXT, 'Competency name'),
                'coursecount' => new external_value(PARAM_INT, 'Configurable linked courses after filters'),
            ])),
            'total' => new external_value(PARAM_INT, 'Total competencies with configurable courses'),
            'totalcourses' => new external_value(PARAM_INT, 'Distinct configurable courses after filters'),
            'bootstrap' => new external_single_structure([
                'roles' => new external_multiple_structure(new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Role id'),
                    'name' => new external_value(PARAM_TEXT, 'Role name'),
                ])),
                'defaultroleid' => new external_value(PARAM_INT, 'Preselected role id (student archetype)'),
                'categories' => new external_multiple_structure(new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Category id'),
                    'name' => new external_value(PARAM_TEXT, 'Category name'),
                ])),
                'cohortenabled' => new external_value(PARAM_BOOL, 'Whether enrol_cohort is enabled sitewide'),
                'selfenabled' => new external_value(PARAM_BOOL, 'Whether enrol_self is enabled sitewide'),
            ], 'Tab bootstrap data (only with includebootstrap)', VALUE_OPTIONAL),
        ]);
    }
}
