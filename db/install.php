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
 * Code to be executed after the plugin's database scheme has been installed.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Provision the plugin's customfields synchronously on install.
 *
 * The runtime hook fallback only fires for logged-in admins; calling the
 * provisioner here guarantees fields exist after CLI installs too.
 *
 * @return bool
 */
function xmldb_local_dimensions_install() {
    \local_dimensions\helper::ensure_custom_fields_exist(\local_dimensions\helper::AREA_LP);
    \local_dimensions\helper::ensure_custom_fields_exist(\local_dimensions\helper::AREA_COMPETENCY);

    return true;
}
