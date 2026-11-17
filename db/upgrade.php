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
    // No numbered steps: every former step's lasting effect is an idempotent repair that runs on
    // each upgrade instead. See local\legacy_repair for why numbered steps cannot work here.
    \local_dimensions\local\legacy_repair::run();

    // Re-ensure every custom field after any upgrade, so a new field needs only a getter wired
    // into helper::ensure_custom_fields_exist() and a version bump. Each getter short-circuits
    // via find_field_by_shortname(), so this is idempotent.
    \local_dimensions\helper::ensure_custom_fields_exist(\local_dimensions\helper::AREA_LP);
    \local_dimensions\helper::ensure_custom_fields_exist(\local_dimensions\helper::AREA_COMPETENCY);

    return true;
}
