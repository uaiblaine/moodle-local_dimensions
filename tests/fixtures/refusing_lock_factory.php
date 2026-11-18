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
 * A lock factory that never grants a lock, standing in for a lock another process holds.
 *
 * Core's database lock factories are re-entrant within one process, so a test cannot make the
 * code under test wait by taking the lock itself.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions;

/**
 * A lock factory that never grants a lock.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class refusing_lock_factory implements \core\lock\lock_factory {
    /**
     * Nothing to set up.
     *
     * @param string $type The lock type.
     */
    public function __construct($type) {
    }

    /**
     * Waiting is supported, and still ends in a refusal.
     *
     * @return bool
     */
    public function supports_timeout() {
        return true;
    }

    /**
     * Locks would be released with the process.
     *
     * @return bool
     */
    public function supports_auto_release() {
        return true;
    }

    /**
     * Always available, so lock_config hands it out.
     *
     * @return bool
     */
    public function is_available() {
        return true;
    }

    /**
     * Refuse every lock.
     *
     * @param string $resource The resource.
     * @param int $timeout The wait.
     * @param int $maxlifetime The lifetime.
     * @return false
     */
    public function get_lock($resource, $timeout, $maxlifetime = 86400) {
        return false;
    }

    /**
     * Nothing was ever granted.
     *
     * @param \core\lock\lock $lock The lock.
     * @return bool
     */
    public function release_lock(\core\lock\lock $lock) {
        return false;
    }
}
