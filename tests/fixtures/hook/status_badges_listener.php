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

namespace local_dimensions\fixtures;

use local_dimensions\hook\competency_status_badges;

/**
 * Test listener for the competency_status_badges hook: records the hook and the user it ran as, then
 * adds the answers a test set.
 *
 * @package    local_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class status_badges_listener {
    /** @var competency_status_badges[] The hooks dispatched, in order. */
    public static array $seen = [];

    /** @var int[] The id of the current user at each dispatch. */
    public static array $viewers = [];

    /** @var array The badges to add: competency id => list of [label, tone]. */
    public static array $answers = [];

    /**
     * Forget everything.
     */
    public static function reset(): void {
        self::$seen = [];
        self::$viewers = [];
        self::$answers = [];
    }

    /**
     * Record the hook and add the configured badges.
     *
     * @param competency_status_badges $hook The hook.
     */
    public static function handle(competency_status_badges $hook): void {
        global $USER;
        self::$seen[] = $hook;
        self::$viewers[] = (int) $USER->id;
        foreach (self::$answers as $competencyid => $badges) {
            foreach ($badges as [$label, $tone]) {
                $hook->add_badge((int) $competencyid, $label, $tone);
            }
        }
    }
}
