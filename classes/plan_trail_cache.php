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
 * Cache for plan trail data used by plan cards.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions;

/**
 * Cache for plan trail data used by plan cards.
 *
 * Stores lightweight competency trail data (id, shortname, proficiency)
 * per plan per user, avoiding the overhead of core API Persistent objects.
 *
 * The definition is application-wide, not per session: the requests that change a learner's
 * trail (a teacher rating, evidence from a course) belong to another user, and a session
 * cache can only be invalidated from its own session.
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
    /**
     * Get the cache instance.
     *
     * The MUC factory already memoises one loader per definition per request,
     * so a plugin-level static handle adds no speed and only risks a stale
     * reference surviving PHPUnit's resetAfterTest(). Call cache::make() each time.
     *
     * @return \cache
     */
    private static function get_cache(): \cache {
        return \cache::make('local_dimensions', 'plan_trail');
    }

    /**
     * Build the cache key for a plan+user combination.
     *
     * A completed plan and a live one answer from different tables, so they are different
     * payloads and must not share a key: a plan completed mid-session would otherwise keep
     * serving the live trail it cached minutes earlier.
     *
     * @param int $planid Plan ID.
     * @param int $userid User ID.
     * @param bool $iscomplete Whether the plan is complete.
     * @return string
     */
    private static function cache_key(int $planid, int $userid, bool $iscomplete = false): string {
        return $planid . '_' . $userid . ($iscomplete ? '_c' : '');
    }

    /**
     * Get trail data for a plan, through the cache.
     *
     * A completed plan reads the competencies and ratings core froze when it was completed, not
     * the template's or the learner's current ones - see fetch_trail_data().
     *
     * @param int $planid Plan ID.
     * @param int $userid User ID.
     * @param int|null $templateid Template ID (null for manual plans).
     * @param bool $iscomplete Whether the plan's status is complete.
     * @return array{total: int, competencies: array}
     */
    public static function get_trail_data(int $planid, int $userid, ?int $templateid, bool $iscomplete = false): array {
        $cache = self::get_cache();
        $key = self::cache_key($planid, $userid, $iscomplete);
        $payload = $cache->get($key);

        if ($payload !== false && is_array($payload) && isset($payload['total'])) {
            self::debug('cache hit for plan ' . $planid . ' user ' . $userid);
            return $payload;
        }

        self::debug('cache miss for plan ' . $planid . ' user ' . $userid);
        $payload = self::fetch_trail_data($planid, $userid, $templateid, $iscomplete);
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
        $cache = self::get_cache();
        // Both spellings of the key, because the caller does not know which one is cached.
        $cache->delete(self::cache_key($planid, $userid, false));
        $cache->delete(self::cache_key($planid, $userid, true));
        self::debug('cache invalidated for plan ' . $planid . ' user ' . $userid);
    }

    /**
     * Invalidate cached trail data for every plan of a user.
     *
     * Used when a competency is rated outside a specific plan context. The keys are listed from
     * the user's plans, since a cache cannot be searched by key prefix.
     *
     * @param int $userid User ID.
     */
    public static function invalidate_user(int $userid): void {
        global $DB;

        $keys = [];
        foreach ($DB->get_fieldset_select('competency_plan', 'id', 'userid = ?', [$userid]) as $planid) {
            $keys[] = self::cache_key((int) $planid, $userid, false);
            $keys[] = self::cache_key((int) $planid, $userid, true);
        }
        if ($keys) {
            self::get_cache()->delete_many($keys);
        }
        self::debug('cache invalidated for all plans of user ' . $userid);
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
     * Both the competency list and the proficiency come from the tables core reads for that plan
     * status. On completion, api::complete_plan() archives every competency of the plan with its
     * rating into {competency_usercompplan}, keyed by planid, and plan::get_competencies() then
     * lists the plan from that archive ({@see \core_competency\user_competency_plan::list_competencies()},
     * whose query and order the complete branch below repeats). Every other status lists the
     * template's or the plan's own competencies with the live {competency_usercomp} rating. The
     * live tables would show a completed plan gaining competencies added to its template later,
     * and losing removed ones, where core's plan page shows the plan as it was completed.
     *
     * @param int $planid Plan ID.
     * @param int $userid User ID.
     * @param int|null $templateid Template ID (null for manual plans).
     * @param bool $iscomplete Whether the plan's status is complete.
     * @return array{total: int, competencies: array}
     */
    private static function fetch_trail_data(int $planid, int $userid, ?int $templateid, bool $iscomplete = false): array {
        global $DB;

        if ($iscomplete) {
            // The archive holds the competency list too, whatever the plan's template.
            $sql = "SELECT c.id, c.shortname,
                           COALESCE(ucp.proficiency, 0) AS proficiency
                      FROM {competency_usercompplan} ucp
                      JOIN {competency} c ON c.id = ucp.competencyid
                     WHERE ucp.planid = :planid AND ucp.userid = :userid
                  ORDER BY ucp.sortorder ASC, ucp.id ASC";

            $params = [
                'userid' => $userid,
                'planid' => $planid,
            ];
        } else if ($templateid) {
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
