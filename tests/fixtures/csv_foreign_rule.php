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
 * A class that is not a competency rule but has the static method the CSV importer calls on rules.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\local;

/**
 * Records whether the importer called migrate_config() on it.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class csv_foreign_rule {
    /** @var bool Whether migrate_config() ran. */
    public static bool $called = false;

    /**
     * Stand-in for competency_rule::migrate_config(); only records that it was reached.
     *
     * @param mixed $config The rule config from the CSV.
     * @param array $mappings The exportid map.
     * @return mixed The config, unchanged.
     */
    public static function migrate_config($config, $mappings) {
        self::$called = true;
        return $config;
    }
}
