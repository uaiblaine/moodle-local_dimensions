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

use local_dimensions\hook\competency_panel_sections;

/**
 * Test listener for the competency_panel_sections hook: records the hook, then adds the sections a test set.
 *
 * @package    local_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class panel_sections_listener {
    /** @var competency_panel_sections[] The hooks dispatched, in order. */
    public static array $seen = [];

    /** @var array The sections to add: each the argument list of add_section(). */
    public static array $answers = [];

    /**
     * Forget everything.
     */
    public static function reset(): void {
        self::$seen = [];
        self::$answers = [];
    }

    /**
     * Record the hook and add the configured sections.
     *
     * @param competency_panel_sections $hook The hook.
     */
    public static function handle(competency_panel_sections $hook): void {
        self::$seen[] = $hook;
        foreach (self::$answers as $arguments) {
            $hook->add_section(...$arguments);
        }
    }
}
