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
 * Privacy API implementation for local_dimensions.
 *
 * The plugin stores no personal data of its own beyond four per-user preferences: two that
 * remember the Competency hub's last-visited view and its display-toggle choices, and two that
 * remember the learner views' chrome and favourite competencies. It has no database tables
 * (custom-field data belongs to competencies/templates, not users), so this is a
 * preference-only provider.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\user_preference_provider;
use core_privacy\local\request\writer;
use local_dimensions\constants;

/**
 * Preference-only privacy provider for the hub and learner view state.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider, user_preference_provider {
    /**
     * Describe the user preferences this plugin stores.
     *
     * @param collection $collection The metadata collection to add to.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_user_preference(
            constants::PREF_CENTRAL_NAV,
            'privacy:metadata:preference:central_nav'
        );
        $collection->add_user_preference(
            constants::PREF_CENTRAL_DISPLAY,
            'privacy:metadata:preference:central_display'
        );
        $collection->add_user_preference(
            constants::PREF_LEARNER_VIEW,
            'privacy:metadata:preference:learner_view'
        );
        $collection->add_user_preference(
            constants::PREF_LEARNER_FAV,
            'privacy:metadata:preference:learner_fav'
        );
        return $collection;
    }

    /**
     * Export the stored view-state preferences for the given user.
     *
     * @param int $userid The id of the user to export for.
     * @return void
     */
    public static function export_user_preferences(int $userid): void {
        self::export_preference(
            $userid,
            constants::PREF_CENTRAL_NAV,
            get_string('privacy:metadata:preference:central_nav', 'local_dimensions')
        );
        self::export_preference(
            $userid,
            constants::PREF_CENTRAL_DISPLAY,
            get_string('privacy:metadata:preference:central_display', 'local_dimensions')
        );
        self::export_preference(
            $userid,
            constants::PREF_LEARNER_VIEW,
            get_string('privacy:metadata:preference:learner_view', 'local_dimensions')
        );
        self::export_preference(
            $userid,
            constants::PREF_LEARNER_FAV,
            get_string('privacy:metadata:preference:learner_fav', 'local_dimensions')
        );
    }

    /**
     * Export one preference, skipping it when the user has nothing stored.
     *
     * The description is passed already resolved so every get_string() call stays a literal at
     * the call site, which is what keeps the strings discoverable by the lang checker.
     *
     * @param int $userid The id of the user to export for.
     * @param string $name The preference name.
     * @param string $description The human-readable description of the preference.
     * @return void
     */
    private static function export_preference(int $userid, string $name, string $description): void {
        $value = get_user_preferences($name, null, $userid);
        if ($value === null || $value === '') {
            return;
        }
        writer::export_user_preference('local_dimensions', $name, $value, $description);
    }
}
