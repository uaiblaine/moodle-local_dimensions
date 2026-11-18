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

namespace local_dimensions\check;

use core\check\check;
use core\check\result;
use core\output\action_link;
use core\url;
use local_dimensions\helper;

/**
 * Whether PostgreSQL has the `unaccent` extension the accent-insensitive search needs.
 *
 * NA on other databases, where the collation does the work; OK when installed; WARNING when
 * missing, because search then still works but is accent-sensitive.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unaccent extends check {
    /**
     * The check's name on the System status report.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('checkunaccent', 'local_dimensions');
    }

    /**
     * The plugin settings, where the extension is created.
     *
     * @return action_link|null
     */
    public function get_action_link(): ?action_link {
        return new action_link(
            new url('/admin/settings.php', ['section' => 'local_dimensions_settings'], 'admin-unaccent'),
            get_string('checkunaccent_action', 'local_dimensions')
        );
    }

    /**
     * Read the database family and the extension's state.
     *
     * @return result
     */
    public function get_result(): result {
        global $DB;

        if ($DB->get_dbfamily() !== 'postgres') {
            return new result(
                result::NA,
                get_string('checkunaccent_na', 'local_dimensions'),
                get_string('checkunaccent_na_details', 'local_dimensions')
            );
        }
        if (helper::has_unaccent()) {
            return new result(
                result::OK,
                get_string('checkunaccent_ok', 'local_dimensions'),
                get_string('checkunaccent_ok_details', 'local_dimensions')
            );
        }
        return new result(
            result::WARNING,
            get_string('checkunaccent_warning', 'local_dimensions'),
            get_string('checkunaccent_warning_details', 'local_dimensions')
        );
    }
}
