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
 * The former address of the competency map: sends a kept link to the hub's map view.
 *
 * The map is a view of the hub's Competencies tab. A link to this page passes the gate the page had
 * (the hub's Site administration entry: login and competencymanage at the site, competencies
 * enabled) before anything is redirected, so a viewer the page refused is refused here the same way
 * and learns nothing from where the redirect would have gone.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use core_competency\api;
use local_dimensions\output\hub_map;

admin_externalpage_setup('local_dimensions_central');
api::require_enabled();

redirect(hub_map::legacy_url(
    optional_param('contexttype', '', PARAM_ALPHA),
    optional_param('categoryid', 0, PARAM_INT),
    optional_param('frameworkid', 0, PARAM_INT)
));
