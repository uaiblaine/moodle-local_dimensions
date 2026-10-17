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

declare(strict_types=1);

namespace local_dimensions\reportbuilder\local\helpers;

/**
 * Competency ratings for report columns, without a query per row.
 *
 * A rating is a position in the competency's scale, which is the competency's own scale or,
 * when it has none, its framework's. The column selects that scale's id with
 * {@see self::scaleid_sql()}, and {@see self::format()} turns the position into the scale item,
 * reading each scale once per request.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rating {
    /** @var array<int, string[]> Scale items keyed by scale id, read once per request. */
    private static array $scales = [];

    /**
     * SQL for the id of the scale that rates a competency.
     *
     * @param string $competencyidfield Qualified field holding the competency id.
     * @return string A scalar subquery.
     */
    public static function scaleid_sql(string $competencyidfield): string {
        return "(SELECT COALESCE(rc.scaleid, rf.scaleid)
                   FROM {competency} rc
                   JOIN {competency_framework} rf ON rf.id = rc.competencyframeworkid
                  WHERE rc.id = {$competencyidfield})";
    }

    /**
     * SQL for the id of the scale that rates a user competency.
     *
     * @param string $usercompetencyidfield Qualified field holding the `competency_usercomp` id.
     * @return string A scalar subquery.
     */
    public static function scaleid_sql_for_usercompetency(string $usercompetencyidfield): string {
        return "(SELECT COALESCE(rc.scaleid, rf.scaleid)
                   FROM {competency_usercomp} ruc
                   JOIN {competency} rc ON rc.id = ruc.competencyid
                   JOIN {competency_framework} rf ON rf.id = rc.competencyframeworkid
                  WHERE ruc.id = {$usercompetencyidfield})";
    }

    /**
     * The scale item a rating names.
     *
     * @param int|string|null $grade Position in the scale, from 1.
     * @param int|string|null $scaleid The rating scale.
     * @return string The item, the raw position when the scale lacks it, or '' with no rating.
     */
    public static function format($grade, $scaleid): string {
        if ($grade === null || $grade === '') {
            return '';
        }
        $items = $scaleid ? self::items((int) $scaleid) : [];
        return (string) ($items[(int) $grade - 1] ?? $grade);
    }

    /**
     * Items of a scale, as core's grade_scale::load_items() splits them.
     *
     * @param int $scaleid The scale.
     * @return string[]
     */
    private static function items(int $scaleid): array {
        global $DB;
        if (!array_key_exists($scaleid, self::$scales)) {
            $scale = $DB->get_field('scale', 'scale', ['id' => $scaleid]);
            self::$scales[$scaleid] = $scale === false ? [] : array_map('trim', explode(',', (string) $scale));
        }
        return self::$scales[$scaleid];
    }

    /**
     * Forget the scales read so far (tests edit scales between reports).
     *
     * @return void
     */
    public static function reset(): void {
        self::$scales = [];
    }
}
