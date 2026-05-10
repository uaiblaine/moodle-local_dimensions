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
 * Edit learning plan template page with hero, sticky section nav and custom field support.
 *
 * Mirrors the UX of edit_competency.php: a Mustache shell wraps the moodleform output
 * with a context-aware header, action bar and section navigation. Custom fields are
 * still rendered by the customfield handler inside the form.
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
use local_dimensions\template_metadata_cache;

// Parameters.
$id = required_param('id', PARAM_INT);
$pagecontextid = optional_param('pagecontextid', 0, PARAM_INT);

// State parameters preserved on the return URL so manage_templates resumes where the user left off.
$contexttype = optional_param('contexttype', 'system', PARAM_ALPHA);
$categoryid = optional_param('categoryid', 0, PARAM_INT);
$showhidden = optional_param('showhidden', 0, PARAM_BOOL);
$view = optional_param('view', 'cards', PARAM_ALPHA);
$search = optional_param('search', '', PARAM_RAW_TRIMMED);

if (!in_array($contexttype, ['system', 'coursecat'], true)) {
    $contexttype = 'system';
}
if (!in_array($view, ['cards', 'table'], true)) {
    $view = 'cards';
}

require_login(0, false);
api::require_enabled();

$template = template::get_record(['id' => $id]);
if (!$template) {
    throw new moodle_exception('invalidrecord', 'error');
}

$context = $template->get_context();
if (!$pagecontextid) {
    $pagecontextid = $context->id;
}

if ($context->contextlevel === CONTEXT_COURSECAT && $contexttype === 'system') {
    $contexttype = 'coursecat';
    $categoryid = (int)$context->instanceid;
}
if ($contexttype === 'system') {
    $categoryid = 0;
}

require_capability('moodle/competency:templatemanage', $context);

$returnparams = [
    'contexttype' => $contexttype,
    'categoryid' => $categoryid,
    'showhidden' => $showhidden,
    'view' => $view,
    'search' => $search,
];

$url = new moodle_url('/local/dimensions/edit_template.php', [
    'id' => $id,
    'pagecontextid' => $pagecontextid,
] + $returnparams);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->add_body_class('local-dimensions-edit-template-page');

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

$returnurl = new moodle_url('/local/dimensions/manage_templates.php', $returnparams);

// Form.
$customdata = [
    'template' => $template,
    'context' => $context,
];
$form = new template_form($url, $customdata);

// Wire AMD module: section navigation, action-bar submit, colour swatches.
$PAGE->requires->js_call_amd('local_dimensions/edit_template', 'init', [[
    'templateId' => (int)$template->get('id'),
    'backgroundColourField' => \local_dimensions\constants::CFIELD_CUSTOMBGCOLOR,
    'textColourField' => \local_dimensions\constants::CFIELD_CUSTOMTEXTCOLOR,
]]);

// Handle form lifecycle.
if ($form->is_cancelled()) {
    redirect($returnurl);
}

if ($data = $form->get_data()) {
    try {
        $templatedata = new stdClass();
        $templatedata->id = $id;
        $templatedata->shortname = $data->shortname;
        $templatedata->visible = $data->visible;
        $templatedata->duedate = $data->duedate;

        if (isset($data->description) && is_array($data->description)) {
            $templatedata->description = $data->description['text'];
            $templatedata->descriptionformat = $data->description['format'];
        } else {
            $templatedata->description = $data->description ?? '';
            $templatedata->descriptionformat = FORMAT_HTML;
        }

        api::update_template($templatedata);

        $handler = lp_handler::create();
        $data->id = $id;
        $handler->instance_form_save_with_image($data, $id);

        template_metadata_cache::invalidate_template($id);

        if (get_config('local_dimensions', 'enablecustomscss')) {
            \local_dimensions\scss_manager::invalidate_cache($id);
        }

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

// Render the shell with the form HTML buffered inside.
$metadata = template_metadata_cache::get_template_metadata($id);
$idnumber = (string)($metadata['idnumber'] ?? '');
$duedate = (int)$template->get('duedate');
$contextname = $context->contextlevel === CONTEXT_SYSTEM
    ? get_string('managetemplates_context_system', 'local_dimensions')
    : $context->get_context_name(false);

ob_start();
$form->display();
$formhtml = ob_get_clean();

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_dimensions/edit_template', [
    'title' => $title,
    'heading' => format_string($template->get('shortname')),
    'contextname' => $contextname,
    'hasidnumber' => $idnumber !== '',
    'idnumber' => s($idnumber),
    'hidden' => !(bool)$template->get('visible'),
    'hasduedate' => $duedate > 0,
    'duedateformatted' => $duedate > 0
        ? userdate($duedate, get_string('strftimedate', 'core_langconfig'))
        : '',
    'returnurl' => $returnurl->out(false),
    'formhtml' => $formhtml,
    'sections' => [
        [
            'id' => 'sec-basic',
            'label' => get_string('edittemplate_section_basic', 'local_dimensions'),
            'active' => true,
        ],
        [
            'id' => 'sec-publish',
            'label' => get_string('edittemplate_section_publish', 'local_dimensions'),
        ],
        [
            'id' => 'sec-fields',
            'label' => get_string('customfields', 'local_dimensions'),
        ],
    ],
]);
echo $OUTPUT->footer();
