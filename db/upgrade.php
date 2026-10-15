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
 * Plugin upgrade steps.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the local_dimensions plugin.
 *
 * @param int $oldversion The old version of the plugin.
 * @return bool
 */
function xmldb_local_dimensions_upgrade($oldversion) {
    // No numbered steps: releases before the branch-per-version split were numbered by date, above
    // every branch's own numbers, so a site switching branches resets its version row and a
    // numbered step would run again or never. Every former step's lasting effect is a repair that
    // runs on each upgrade instead (local\legacy_repair); the plugin owns no tables.
    \local_dimensions\local\legacy_repair::run();

    // Catch-all: re-ensure every customfield exists after any upgrade. Adding a
    // new customfield in the future only needs a version bump plus a new getter
    // wired into helper::ensure_custom_fields_exist(); no per-version savepoint
    // block is required for field provisioning. Each getter short-circuits via
    // find_field_by_shortname(), so this is idempotent.
    \local_dimensions\helper::ensure_custom_fields_exist(\local_dimensions\helper::AREA_LP);
    \local_dimensions\helper::ensure_custom_fields_exist(\local_dimensions\helper::AREA_COMPETENCY);

    return true;
}
