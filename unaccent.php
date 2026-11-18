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
 * Creates the PostgreSQL unaccent extension on an administrator's request.
 *
 * Posted by the button in the plugin settings. The gate (login, moodle/site:config, session key) and
 * the creation are {@see \local_dimensions\local\unaccent_admin::create()}; this page only reports.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$back = new moodle_url('/admin/settings.php', ['section' => 'local_dimensions_settings'], 'admin-unaccent');

if (\local_dimensions\local\unaccent_admin::create()) {
    redirect($back, get_string('unaccent_created', 'local_dimensions'), null, \core\output\notification::NOTIFY_SUCCESS);
}
redirect($back, get_string('unaccent_failed', 'local_dimensions'), null, \core\output\notification::NOTIFY_ERROR);
