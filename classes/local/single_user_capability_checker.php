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
 * A capability checker that answers for one user.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\local;

/**
 * Answers get_users_by_capability() with the one user it was made for, or nobody.
 *
 * A condition filtering a one-person list only looks its user up in the answer, so listing every
 * holder of the capability in the context (core's {@see \core_availability\capability_checker})
 * buys nothing. has_capability() without doanything matches get_users_by_capability(), which
 * returns only users holding a role with the capability.
 */
final class single_user_capability_checker extends \core_availability\capability_checker {
    /** @var int The only user it answers for. */
    private int $userid;

    /**
     * Makes a checker for one user in one context.
     *
     * @param \context $context The course or module context.
     * @param int $userid The only user it answers for.
     */
    public function __construct(\context $context, int $userid) {
        parent::__construct($context);
        $this->userid = $userid;
    }

    /**
     * The user, keyed by id, when they hold the capability; otherwise nobody.
     *
     * @param string $capability The capability.
     * @return array Map of user id to an object holding only id.
     */
    #[\Override]
    public function get_users_by_capability($capability) {
        if (!array_key_exists($capability, $this->cache)) {
            $this->cache[$capability] = has_capability($capability, $this->context, $this->userid, false)
                ? [$this->userid => (object) ['id' => $this->userid]]
                : [];
        }
        return $this->cache[$capability];
    }
}
