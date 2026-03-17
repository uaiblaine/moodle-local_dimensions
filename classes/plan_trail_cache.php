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
 * Session cache for plan trail data used by plan cards.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions;

/**
 * Session cache for plan trail data used by plan cards.
 *
 * Stores lightweight competency trail data (id, shortname, proficiency)
 * per plan per user, avoiding the overhead of core API Persistent objects.
 *
 * Cache payload:
 * - total: int (total competency count)
 * - competencies: array<array{id: int, shortname: string, proficiency: int}>
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plan_trail_cache {
    /** @var \cache|null Cache instance. */
    private static $cache = null;

    /**
     * Get the cache instance.
     *
     * @return \cache
     */
    private static function get_cache(): \cache {
        if (self::$cache === null) {
            self::$cache = \cache::make('local_dimensions', 'plan_trail');
        }
        return self::$cache;
    }

    /**
     * Build the cache key for a plan+user combination.
     *
     * @param int $planid Plan ID.
     * @param int $userid User ID.
     * @return string
     */
    private static function cache_key(int $planid, int $userid): string {
        return $planid . '_' . $userid;
    }

    /**
     * Get trail data for a plan, using session cache.
     *
     * @param int $planid Plan ID.
     * @param int $userid User ID.
     * @param int|null $templateid Template ID (null for manual plans).
     * @return array{total: int, competencies: array}
     */
    public static function get_trail_data(int $planid, int $userid, ?int $templateid): array {
        $cache = self::get_cache();
        $key = self::cache_key($planid, $userid);
        $payload = $cache->get($key);

        if ($payload !== false && is_array($payload) && isset($payload['total'])) {
            self::debug('cache hit for plan ' . $planid . ' user ' . $userid);
            return $payload;
        }

        self::debug('cache miss for plan ' . $planid . ' user ' . $userid);
        $payload = self::fetch_trail_data($planid, $userid, $templateid);
        $cache->set($key, $payload);
        return $payload;
    }

    /**
     * Invalidate cached trail data for a specific plan+user.
     *
     * @param int $planid Plan ID.
     * @param int $userid User ID.
     */
    public static function invalidate_plan(int $planid, int $userid): void {
        self::get_cache()->delete(self::cache_key($planid, $userid));
        self::debug('cache invalidated for plan ' . $planid . ' user ' . $userid);
    }

    /**
     * Purge all cached trail data for a user.
     *
     * Used when a competency is rated outside a specific plan context.
     *
     * @param int $userid User ID.
     */
    public static function invalidate_user(int $userid): void {
        // Session cache is per-user, so purging all keys is safe and correct.
        self::get_cache()->purge();
        self::debug('cache purged for user ' . $userid);
    }

    /**
     * Purge all cached trail data.
     */
    public static function purge_all(): void {
        self::get_cache()->purge();
        self::debug('cache purged');
    }

    /**
     * Fetch trail data from the database using a lightweight query.
     *
     * Returns scalar rows (id, shortname, proficiency) without
     * instantiating Persistent objects.
     *
     * @param int $planid Plan ID.
     * @param int $userid User ID.
     * @param int|null $templateid Template ID (null for manual plans).
     * @return array{total: int, competencies: array}
     */
    private static function fetch_trail_data(int $planid, int $userid, ?int $templateid): array {
        global $DB;

        if ($templateid) {
            // Template-based plan: competencies come from template_competency link.
            $sql = "SELECT c.id, c.shortname,
                           COALESCE(uc.proficiency, 0) AS proficiency
                      FROM {competency_templatecomp} tc
                      JOIN {competency} c ON c.id = tc.competencyid
                 LEFT JOIN {competency_usercomp} uc ON uc.competencyid = c.id AND uc.userid = :userid
                     WHERE tc.templateid = :templateid
                  ORDER BY tc.sortorder ASC, tc.id ASC";

            $params = [
                'userid' => $userid,
                'templateid' => $templateid,
            ];
        } else {
            // Manual plan: competencies come from plan_competency link.
            $sql = "SELECT c.id, c.shortname,
                           COALESCE(uc.proficiency, 0) AS proficiency
                      FROM {competency_plancomp} pc
                      JOIN {competency} c ON c.id = pc.competencyid
                 LEFT JOIN {competency_usercomp} uc ON uc.competencyid = c.id AND uc.userid = :userid
                     WHERE pc.planid = :planid
                  ORDER BY pc.sortorder ASC, pc.id ASC";

            $params = [
                'userid' => $userid,
                'planid' => $planid,
            ];
        }

        $rows = $DB->get_records_sql($sql, $params);

        $competencies = [];
        foreach ($rows as $row) {
            $competencies[] = [
                'id' => (int)$row->id,
                'shortname' => $row->shortname,
                'proficiency' => (int)$row->proficiency,
            ];
        }

        return [
            'total' => count($competencies),
            'competencies' => $competencies,
        ];
    }

    /**
     * Optional DEBUG_DEVELOPER logging for cache operations.
     *
     * @param string $message Debug message.
     */
    private static function debug(string $message): void {
        if (get_config('local_dimensions', 'debugplantrailcache')) {
            debugging('local_dimensions plan_trail_cache: ' . $message, DEBUG_DEVELOPER);
        }
    }
}
