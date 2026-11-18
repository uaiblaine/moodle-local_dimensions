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

namespace local_dimensions\local;

use local_dimensions\helper;

/**
 * The administrator's request to create the PostgreSQL `unaccent` extension.
 *
 * The page /local/dimensions/unaccent.php is a thin caller of {@see self::create()}, so the gate
 * (capability, then session key) is testable without a page.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unaccent_admin {
    /**
     * Create the extension for an administrator who asked for it.
     *
     * @return bool True when the extension is usable afterwards.
     * @throws \required_capability_exception When the user lacks moodle/site:config at the system context.
     * @throws \moodle_exception When the session key is missing or wrong.
     */
    public static function create(): bool {
        require_capability('moodle/site:config', \context_system::instance());
        require_sesskey();
        return helper::ensure_unaccent();
    }
}
