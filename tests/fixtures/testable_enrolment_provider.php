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
 * The enrolment provider's selection, with the availability decided by the test.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\local;

/**
 * The provider's selection, with the availability of local_unlistedcourses decided by the test.
 *
 * Abstract: only its static get() is called, which asks static::unlisted_available().
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class testable_enrolment_provider extends enrolment_provider {
    /** @var bool What unlisted_available() answers. */
    public static bool $available = false;

    /**
     * Whether local_unlistedcourses is available, as the test says.
     *
     * @return bool
     */
    public static function unlisted_available(): bool {
        return self::$available;
    }
}
