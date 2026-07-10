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
 * The plugin stores no personal data of its own beyond two per-user preferences that remember
 * the Competency hub's last-visited view and its display-toggle choices. It has no database
 * tables (custom-field data belongs to competencies/templates, not users), so this is a
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
 * Preference-only privacy provider for the Competency hub view state.
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
        return $collection;
    }

    /**
     * Export the stored view-state preferences for the given user.
     *
     * @param int $userid The id of the user to export for.
     * @return void
     */
    public static function export_user_preferences(int $userid): void {
        $nav = get_user_preferences(constants::PREF_CENTRAL_NAV, null, $userid);
        if ($nav !== null && $nav !== '') {
            writer::export_user_preference(
                'local_dimensions',
                constants::PREF_CENTRAL_NAV,
                $nav,
                get_string('privacy:metadata:preference:central_nav', 'local_dimensions')
            );
        }
        $display = get_user_preferences(constants::PREF_CENTRAL_DISPLAY, null, $userid);
        if ($display !== null && $display !== '') {
            writer::export_user_preference(
                'local_dimensions',
                constants::PREF_CENTRAL_DISPLAY,
                $display,
                get_string('privacy:metadata:preference:central_display', 'local_dimensions')
            );
        }
    }
}
