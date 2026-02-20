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
 * Manage custom fields for learning plan templates.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_dimensions_customfield_template');

$handler = \local_dimensions\customfield\lp_handler::create();
$output = $PAGE->get_renderer('core_customfield');

$PAGE->set_url(new moodle_url('/local/dimensions/customfield_template.php'));
$PAGE->set_title(get_string('templatecustomfields', 'local_dimensions'));
$PAGE->set_heading(get_string('templatecustomfields', 'local_dimensions'));

echo $OUTPUT->header();
$management = new \core_customfield\output\management($handler);
echo $output->render($management);
echo $OUTPUT->footer();
