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
 * Edit learning plan template page with full form support.
 *
 * This page allows editing both native template fields and custom fields
 * in a single unified form, similar to tool_lp/edittemplate.php but with
 * custom field integration from local_dimensions.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use core_competency\template;
use core_competency\api;
use local_dimensions\form\template_form;
use local_dimensions\customfield\lp_handler;

// Parameters.
$id = required_param('id', PARAM_INT);
$pagecontextid = optional_param('pagecontextid', 0, PARAM_INT);

// Require login.
require_login(0, false);
\core_competency\api::require_enabled();

// Load the template.
$template = template::get_record(['id' => $id]);
if (!$template) {
    throw new moodle_exception('invalidrecord', 'error');
}

// Determine context.
$context = $template->get_context();
if (!$pagecontextid) {
    $pagecontextid = $context->id;
}

// Check capabilities.
require_capability('moodle/competency:templatemanage', $context);

// Page setup.
$url = new moodle_url('/local/dimensions/edit_template.php', [
    'id' => $id,
    'pagecontextid' => $pagecontextid,
]);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');

$title = get_string('edittemplate', 'local_dimensions');

$PAGE->set_title($title);
$PAGE->set_heading($title);

// Navbar.
$PAGE->navbar->add(get_string('learningplans', 'tool_lp'), new moodle_url('/admin/tool/lp/learningplans.php'));
$PAGE->navbar->add(
    get_string('templates', 'tool_lp'),
    new moodle_url('/admin/tool/lp/learningplans.php', ['pagecontextid' => $pagecontextid])
);
$PAGE->navbar->add($title);

// Return URL.
$returnurl = new moodle_url('/local/dimensions/manage_templates.php');

// Create form.
$customdata = [
    'template' => $template,
    'context' => $context,
];
$form = new template_form($url, $customdata);

// Handle form submission.
if ($form->is_cancelled()) {
    redirect($returnurl);
}

if ($data = $form->get_data()) {
    try {
        // Prepare template data for update.
        $templatedata = new stdClass();
        $templatedata->id = $id;
        $templatedata->shortname = $data->shortname;
        $templatedata->visible = $data->visible;
        $templatedata->duedate = $data->duedate;

        // Handle description editor field.
        if (isset($data->description) && is_array($data->description)) {
            $templatedata->description = $data->description['text'];
            $templatedata->descriptionformat = $data->description['format'];
        } else {
            $templatedata->description = $data->description ?? '';
            $templatedata->descriptionformat = FORMAT_HTML;
        }

        // Update the template via competency API.
        api::update_template($templatedata);

        // Save custom field data (and built-in image if applicable).
        $handler = lp_handler::create();
        $data->id = $id;
        $handler->instance_form_save_with_image($data, $id);

        // Invalidate compiled SCSS cache for this template.
        if (get_config('local_dimensions', 'enablecustomscss')) {
            \local_dimensions\scss_manager::invalidate_cache($id);
        }

        // Redirect on success.
        redirect(
            $returnurl,
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
echo $OUTPUT->heading($title . ': ' . format_string($template->get('shortname')));

$form->display();

echo $OUTPUT->footer();
