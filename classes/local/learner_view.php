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
 * The learner views' remembered chrome, read once for both pages.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\local;

use local_dimensions\constants;

/**
 * Reads the learner view preference and validates every key against what the views offer.
 *
 * One preference holds the chrome of both learner views, and the client writes it back entire
 * (amd/src/learner_prefs.js), so each page has to seed every key, its own and the other page's:
 * a key a page left out would be reset by that page's next save. That is why both pages read it
 * here, and why a key added here must reach both seeds ({@see self::KEYS}).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learner_view {
    /** @var array Every key of the stored state, in the order the seeds write them. */
    const KEYS = ['sort', 'filter', 'favonly', 'view', 'expanded', 'trackerfilter'];

    /** @var array The plan overview's orders. */
    const SORTS = ['planorder', 'name', 'completed', 'favourites'];

    /**
     * The stored state, each key validated against what the views offer.
     *
     * Anything unrecognised falls back to the default rather than being trusted: the preference
     * is user-writable through core's own repository, and a stale value survives a redesign that
     * drops an option. Both scopes default to the learner's open work ("To do"): the plan's has
     * always, and the tracker's does since the filter line, where it was "All" before; a stored
     * "all" still wins on either.
     *
     * @return array Keys: sort, filter, favonly, view, expanded, trackerfilter.
     */
    public static function resolve(): array {
        $stored = json_decode((string) get_user_preferences(constants::PREF_LEARNER_VIEW, ''), true);
        $stored = is_array($stored) ? $stored : [];
        $sort = $stored['sort'] ?? '';

        return [
            'sort' => in_array($sort, self::SORTS, true) ? $sort : 'planorder',
            'filter' => (($stored['filter'] ?? '') === 'all') ? 'all' : 'incomplete',
            'favonly' => !empty($stored['favonly']),
            'view' => (($stored['view'] ?? '') === 'grid') ? 'grid' : 'list',
            'expanded' => !empty($stored['expanded']),
            'trackerfilter' => (($stored['trackerfilter'] ?? '') === 'all') ? 'all' : 'incomplete',
        ];
    }
}
