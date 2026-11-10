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
 * Which course categories the viewer may discover, as local_unlistedcourses decides it.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\local;

/**
 * The guarded way to local_unlistedcourses' category rule, for the hub's category surfaces.
 *
 * local_unlistedcourses can mark a course category unlisted: only its cohort members, the holders of
 * a role in it or above it, and its staff may learn it exists. Core's can_view_category() knows
 * nothing of that, so every surface of this plugin that names a category asks here as well, after
 * core. The plugin is optional: on a site without it no category is unlisted, and every category
 * core admits is kept. It is asked unconditionally, unlike the enrolment provider's setting, because
 * an unlisted category is the site's decision, not a presentation choice of this plugin.
 *
 * Next to enrolment_provider, this is the only place that names local_unlistedcourses
 * ({@see \local_dimensions\optional_unlistedcourses_test}).
 */
class unlisted_categories {
    /** @var string The class of local_unlistedcourses' category rule, as method_exists() is asked. */
    public const string CATEGORY_API = 'local_unlistedcourses\category_access';

    /**
     * @var string The API class available() probes; CATEGORY_API in production.
     *
     * Exists so tests can model a site without local_unlistedcourses on a site where it is installed.
     * Nothing else assigns it.
     */
    protected static string $categoryapi = self::CATEGORY_API;

    /**
     * Whether local_unlistedcourses is installed with the category rule this class calls.
     *
     * @return bool
     */
    public static function available(): bool {
        // Method_exists() autoloads the class and answers false when there is none.
        return method_exists(static::$categoryapi, 'filter_categories');
    }

    /**
     * Keep the category ids the current user may discover, in the order given.
     *
     * Costs local_unlistedcourses' budget for one call (a single query when nothing on the site is
     * unlisted), whatever the number of ids, so a caller passes its whole candidate list at once.
     *
     * @param array $categoryids Course category ids.
     * @return array The ids kept, as ints, reindexed.
     */
    public static function filter_ids(array $categoryids): array {
        $categoryids = array_values(array_map('intval', $categoryids));
        if (empty($categoryids) || !static::available()) {
            return $categoryids;
        }
        return array_values(array_map('intval', call_user_func([static::$categoryapi, 'filter_categories'], $categoryids)));
    }

    /**
     * Whether the current user may discover one course category.
     *
     * @param int $categoryid The course category id.
     * @return bool
     */
    public static function is_discoverable(int $categoryid): bool {
        return static::filter_ids([$categoryid]) !== [];
    }
}
