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
 * Cache helper for template-based course caching.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions;

/**
 * Cache helper for template-based course caching.
 *
 * This class provides efficient caching of valid course IDs per learning plan template.
 * Since all plans from the same template share the same competencies and linked courses,
 * we cache by template ID to serve hundreds of thousands of students with a single cache entry.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine (anderson@blaine.com.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_course_cache {
    /** @var \cache The cache instance */
    private static $cache = null;

    /**
     * Get the cache instance.
     *
     * @return \cache
     */
    private static function get_cache(): \cache {
        if (self::$cache === null) {
            self::$cache = \cache::make('local_dimensions', 'template_courses');
        }
        return self::$cache;
    }

    /**
     * Get valid course IDs for a template.
     *
     * @param int $templateid The template ID.
     * @return array Array of course IDs linked to all competencies in the template.
     */
    public static function get_courses_for_template(int $templateid): array {
        $cache = self::get_cache();
        $courses = $cache->get($templateid);

        if ($courses !== false) {
            return $courses;
        }

        // Cache miss - fetch from database.
        $courses = self::fetch_courses_for_template($templateid);
        $cache->set($templateid, $courses);

        return $courses;
    }

    /**
     * Get valid course IDs for a plan (uses the plan's template).
     *
     * @param \core_competency\plan $plan The plan object.
     * @return array Array of course IDs linked to all competencies in the plan's template.
     */
    public static function get_courses_for_plan(\core_competency\plan $plan): array {
        $templateid = $plan->get('templateid');

        if (empty($templateid)) {
            // Plan is not based on a template, fetch directly from plan competencies.
            return self::fetch_courses_for_plan($plan);
        }

        return self::get_courses_for_template($templateid);
    }

    /**
     * Fetch courses for a template from database.
     *
     * @param int $templateid The template ID.
     * @return array Array of course IDs.
     */
    private static function fetch_courses_for_template(int $templateid): array {
        global $DB;

        // Get all competencies from the template and their linked courses in one query.
        $sql = "SELECT DISTINCT cc.courseid
                  FROM {competency_templatecomp} tc
                  JOIN {competency_coursecomp} cc ON cc.competencyid = tc.competencyid
                 WHERE tc.templateid = :templateid";

        $courses = $DB->get_fieldset_sql($sql, ['templateid' => $templateid]);

        return array_map('intval', $courses);
    }

    /**
     * Fetch courses for a plan without template (ad-hoc plan).
     *
     * @param \core_competency\plan $plan The plan object.
     * @return array Array of course IDs.
     */
    private static function fetch_courses_for_plan(\core_competency\plan $plan): array {
        global $DB;

        $planid = $plan->get('id');

        // Get all competencies from the plan and their linked courses.
        $sql = "SELECT DISTINCT cc.courseid
                  FROM {competency_plancomp} pc
                  JOIN {competency_coursecomp} cc ON cc.competencyid = pc.competencyid
                 WHERE pc.planid = :planid";

        $courses = $DB->get_fieldset_sql($sql, ['planid' => $planid]);

        return array_map('intval', $courses);
    }

    /**
     * Invalidate cache for a template.
     *
     * Call this when template competencies or course-competency links change.
     *
     * @param int $templateid The template ID to invalidate.
     */
    public static function invalidate_template(int $templateid): void {
        $cache = self::get_cache();
        $cache->delete($templateid);
    }

    /**
     * Purge all cached template data.
     */
    public static function purge_all(): void {
        $cache = self::get_cache();
        $cache->purge();
    }
}
