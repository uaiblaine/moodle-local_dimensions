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
 * Shared queries and policy for the participants modal's Enrolment methods tab.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\local;

use local_dimensions\task\process_enrol_method;

/**
 * Shared queries and policy for the participants modal's Enrolment methods tab.
 *
 * Concentrates what all four enrol web services need: which courses the template links
 * (per competency), which of them the current user may configure, which roles the tab
 * offers, and the configured state of both methods against one cohort.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_methods {
    /**
     * Whether the cohort is attached to the template.
     *
     * @param int $templateid Template id.
     * @param int $cohortid Cohort id.
     * @return bool
     */
    public static function cohort_linked(int $templateid, int $cohortid): bool {
        return (bool) \core_competency\template_cohort::get_relation($templateid, $cohortid)->get('id');
    }

    /**
     * Roles the tab offers: gradebook roles that are also assignable through enrolments.
     *
     * @param \context $context Context the role names are localised for (the template's).
     * @return array Map of roleid => localised role name.
     */
    public static function eligible_roles(\context $context): array {
        global $CFG;

        if (empty($CFG->gradebookroles)) {
            return [];
        }
        $assignable = get_default_enrol_roles($context);
        $roles = [];
        foreach (explode(',', $CFG->gradebookroles) as $roleid) {
            $roleid = (int) $roleid;
            if (isset($assignable[$roleid])) {
                $roles[$roleid] = $assignable[$roleid];
            }
        }
        return $roles;
    }

    /**
     * Default role for the tab: the student archetype when eligible, else the first option.
     *
     * @param array $eligible Map of roleid => name from eligible_roles().
     * @return int Role id, or 0 when no role is eligible.
     */
    public static function default_roleid(array $eligible): int {
        foreach (get_archetype_roles('student') as $role) {
            if (isset($eligible[(int) $role->id])) {
                return (int) $role->id;
            }
        }
        $ids = array_keys($eligible);
        return $ids ? (int) reset($ids) : 0;
    }

    /**
     * Linked course ids of the template, grouped by competency (template order).
     *
     * @param int $templateid Template id.
     * @return array Map of competencyid => list of course ids.
     */
    public static function competency_course_ids(int $templateid): array {
        global $DB;

        $rows = $DB->get_records_sql(
            "SELECT cc.id, tc.competencyid, cc.courseid
               FROM {" . \core_competency\template_competency::TABLE . "} tc
               JOIN {" . \core_competency\course_competency::TABLE . "} cc ON cc.competencyid = tc.competencyid
              WHERE tc.templateid = :templateid
           ORDER BY cc.competencyid ASC, cc.courseid ASC",
            ['templateid' => $templateid]
        );
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->competencyid][] = (int) $row->courseid;
        }
        return $map;
    }

    /**
     * Course records (with category name) for a set of course ids.
     *
     * @param array $courseids Course ids.
     * @return array Map of courseid => record {id, shortname, fullname, visible, category, categoryname}.
     */
    public static function course_records(array $courseids): array {
        global $DB;

        if (!$courseids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_unique($courseids), SQL_PARAMS_NAMED);
        return $DB->get_records_sql(
            "SELECT c.id, c.shortname, c.fullname, c.visible, c.category, cat.name AS categoryname
               FROM {course} c
          LEFT JOIN {course_categories} cat ON cat.id = c.category
              WHERE c.id $insql",
            $params
        );
    }

    /**
     * Courses (of the given set) the current user may configure enrolment methods on.
     *
     * @param array $courseids Course ids.
     * @return array Map of courseid => true (test membership with isset).
     */
    public static function allowed_map(array $courseids): array {
        $allowed = [];
        foreach (array_unique($courseids) as $courseid) {
            $courseid = (int) $courseid;
            $context = \context_course::instance($courseid, IGNORE_MISSING);
            if (!$context) {
                continue;
            }
            $ok = true;
            foreach (process_enrol_method::REQUIRED_CAPS as $cap) {
                if (!has_capability($cap, $context)) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                $allowed[$courseid] = true;
            }
        }
        return $allowed;
    }

    /**
     * Configured state of both methods for a set of courses against one cohort.
     *
     * The "since" value is the earliest matching instance's timecreated (0 when none).
     *
     * @param array $courseids Course ids.
     * @param int $cohortid Cohort id.
     * @return array Map of courseid => [method => object {configured (bool), since (int)}].
     */
    public static function status_map(array $courseids, int $cohortid): array {
        global $DB;

        $statuses = [];
        foreach ($courseids as $courseid) {
            $statuses[(int) $courseid] = [
                process_enrol_method::METHOD_COHORT => (object) ['configured' => false, 'since' => 0],
                process_enrol_method::METHOD_SELF => (object) ['configured' => false, 'since' => 0],
            ];
        }
        if (!$statuses) {
            return $statuses;
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($statuses), SQL_PARAMS_NAMED);
        $params['cohortid1'] = $cohortid;
        $params['cohortid2'] = $cohortid;
        $select = "courseid $insql AND ((enrol = 'cohort' AND customint1 = :cohortid1)"
            . " OR (enrol = 'self' AND customint5 = :cohortid2))";
        $rows = $DB->get_records_select('enrol', $select, $params, 'id ASC', 'id, courseid, enrol, timecreated');
        foreach ($rows as $row) {
            $entry = $statuses[(int) $row->courseid][(string) $row->enrol];
            $entry->configured = true;
            $time = (int) $row->timecreated;
            if ($time && (!$entry->since || $time < $entry->since)) {
                $entry->since = $time;
            }
        }
        return $statuses;
    }
}
