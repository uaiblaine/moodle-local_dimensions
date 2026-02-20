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
 * Edit competency page with custom fields support.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use core_competency\api;
use core_competency\competency;
use core_competency\competency_framework;
use local_dimensions\form\competency_form;
use local_dimensions\customfield\competency_handler;

// Parameters.
$id = optional_param('id', 0, PARAM_INT);
$frameworkid = required_param('competencyframeworkid', PARAM_INT);
$parentid = optional_param('parentid', 0, PARAM_INT);
$pagecontextid = optional_param('pagecontextid', 0, PARAM_INT);

// Require login.
require_login(0, false);

// Load the framework.
$framework = competency_framework::get_record(['id' => $frameworkid]);
if (!$framework) {
    throw new moodle_exception('invalidrecord', 'error');
}

// Determine context.
$context = $framework->get_context();
if (!$pagecontextid) {
    $pagecontextid = $context->id;
}

// Check capabilities.
require_capability('moodle/competency:competencymanage', $context);

// Load competency if editing.
$competency = null;
if ($id > 0) {
    $competency = competency::get_record(['id' => $id]);
    if (!$competency) {
        throw new moodle_exception('invalidrecord', 'error');
    }
}

// Load parent if specified.
$parent = null;
if ($parentid > 0) {
    $parent = competency::get_record(['id' => $parentid]);
}

// Page setup.
$url = new moodle_url('/local/dimensions/edit_competency.php', [
    'id' => $id,
    'competencyframeworkid' => $frameworkid,
    'parentid' => $parentid,
    'pagecontextid' => $pagecontextid,
]);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');

$title = $id > 0
    ? get_string('editcompetency', 'tool_lp')
    : get_string('addcompetency', 'tool_lp');

$PAGE->set_title($title);
$PAGE->set_heading($title);

// Navbar.
$PAGE->navbar->add(get_string('competencies', 'core_competency'), new moodle_url('/admin/tool/lp/competencyframeworks.php'));
$PAGE->navbar->add($framework->get('shortname'), new moodle_url('/admin/tool/lp/competencies.php', [
    'competencyframeworkid' => $frameworkid,
]));
$PAGE->navbar->add($title);

// Create form.
$customdata = [
    'competency' => $competency,
    'framework' => $framework,
    'parent' => $parent,
    'pagecontextid' => $pagecontextid,
];
$form = new competency_form($url, $customdata);

// Handle form submission.
if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/dimensions/manage_competencies.php', [
        'frameworkid' => $frameworkid,
    ]));
}

if ($data = $form->get_data()) {
    // Prepare competency data.
    $record = new stdClass();
    $record->shortname = $data->shortname;
    $record->idnumber = $data->idnumber;
    $record->description = $data->description['text'];
    $record->descriptionformat = $data->description['format'];
    $record->competencyframeworkid = $data->competencyframeworkid;

    if (!empty($data->parentid)) {
        $record->parentid = $data->parentid;
    }

    try {
        if ($id > 0) {
            // Update existing competency.
            $record->id = $id;
            api::update_competency($record);
            $competencyid = $id;
        } else {
            // Create new competency.
            $newcompetency = api::create_competency($record);
            $competencyid = $newcompetency->get('id');
        }

        // Save custom field data (and built-in image if applicable).
        $handler = competency_handler::create();
        // The handler needs the instance ID in the data object.
        $data->id = $competencyid;
        $handler->instance_form_save_with_image($data, $id <= 0, $competencyid);

        // Invalidate compiled SCSS cache for this competency.
        if (get_config('local_dimensions', 'enablecustomscss')) {
            \local_dimensions\scss_manager::invalidate_cache($competencyid, 'competency');
        }

        // Redirect on success.
        redirect(
            new moodle_url('/local/dimensions/manage_competencies.php', [
                'frameworkid' => $frameworkid,
            ]),
            get_string('changessaved'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (Exception $e) {
        \core\notification::error($e->getMessage());
    }
}

// Output.
echo $OUTPUT->header();
echo $OUTPUT->heading($title);

$form->display();

echo $OUTPUT->footer();
