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
 * Manage learning plan templates page - Navigate and edit templates with custom fields.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use core_competency\template;

// Admin page setup.
admin_externalpage_setup('local_dimensions_manage_templates');

$PAGE->set_url(new moodle_url('/local/dimensions/manage_templates.php'));
$PAGE->set_title(get_string('managetemplates', 'local_dimensions'));
$PAGE->set_heading(get_string('managetemplates', 'local_dimensions'));

// Get all visible templates only.
$alltemplates = template::get_records([], 'shortname');
$templates = [];
foreach ($alltemplates as $tpl) {
    // Only include visible templates.
    if ($tpl->get('visible')) {
        $editurl = new moodle_url('/local/dimensions/edit_template.php', [
            'id' => $tpl->get('id'),
            'pagecontextid' => $tpl->get_context()->id,
        ]);

        $description = $tpl->get('description');
        $templates[] = [
            'id' => $tpl->get('id'),
            'shortname' => format_string($tpl->get('shortname')),
            'description' => $description ? shorten_text(strip_tags($description), 100) : '',
            'hasdescription' => !empty($description),
            'editurl' => $editurl->out(false),
            'visible' => $tpl->get('visible'),
            'hidden' => !$tpl->get('visible'),
        ];
    }
}

// Build template data.
$templatedata = [
    'hastemplates' => !empty($templates),
    'templates' => $templates,
];

// Output.
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_dimensions/manage_templates', $templatedata);
echo $OUTPUT->footer();
