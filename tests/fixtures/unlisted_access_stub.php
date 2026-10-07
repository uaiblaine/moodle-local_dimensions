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
 * A stand-in for local_unlistedcourses' access class, for sites without that plugin.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\local;

/**
 * Answers the three API calls the unlisted provider makes with canned values, and records each call.
 *
 * The answers are shaped as local_unlistedcourses\access documents them. A course with no canned
 * answer has no relationship and is offered nothing.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unlisted_access_stub {
    /** @var array Course id => the answer of get_enrolment_state(). */
    public static array $relationships = [];

    /** @var array Course id => the answer of get_next_action() and of get_next_actions() for it. */
    public static array $nextactions = [];

    /** @var array Every call, as [method, argument]. */
    public static array $calls = [];

    /**
     * Forget every answer and every call.
     *
     * @return void
     */
    public static function reset(): void {
        self::$relationships = [];
        self::$nextactions = [];
        self::$calls = [];
    }

    /**
     * The relationship of the current user with a course.
     *
     * @param int $courseid The course id.
     * @return array type, startsat, endsat.
     */
    public static function get_enrolment_state(int $courseid): array {
        self::$calls[] = ['get_enrolment_state', $courseid];
        return self::$relationships[$courseid] ?? ['type' => 'none', 'startsat' => 0, 'endsat' => 0];
    }

    /**
     * The next action of the current user on a course.
     *
     * @param int $courseid The course id.
     * @return array type, routes, guest, conditional, blocked.
     */
    public static function get_next_action(int $courseid): array {
        self::$calls[] = ['get_next_action', $courseid];
        return self::answer($courseid);
    }

    /**
     * The next actions of the current user on several courses.
     *
     * @param array $courseids Course ids.
     * @return array Course id => the answer of get_next_action().
     */
    public static function get_next_actions(array $courseids): array {
        self::$calls[] = ['get_next_actions', $courseids];
        $answers = [];
        foreach ($courseids as $courseid) {
            $answers[(int) $courseid] = self::answer((int) $courseid);
        }
        return $answers;
    }

    /**
     * The canned next action of a course.
     *
     * @param int $courseid The course id.
     * @return array The answer.
     */
    private static function answer(int $courseid): array {
        return self::$nextactions[$courseid]
            ?? ['type' => 'none', 'routes' => [], 'guest' => null, 'conditional' => null, 'blocked' => null];
    }
}
