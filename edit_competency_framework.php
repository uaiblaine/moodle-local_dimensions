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
 * Edit a competency framework and return to the local_dimensions management page.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use core_competency\api;
use core_competency\competency_framework;

$id = optional_param('id', 0, PARAM_INT);
$pagecontextid = required_param('pagecontextid', PARAM_INT);
$showhidden = optional_param('showhidden', 0, PARAM_BOOL);
$view = optional_param('view', 'tree', PARAM_ALPHA);
$search = optional_param('search', '', PARAM_RAW_TRIMMED);

if (!in_array($view, ['tree', 'table'], true)) {
    $view = 'tree';
}

$framework = null;
if ($id > 0) {
    $framework = new competency_framework($id);
    $context = $framework->get_context();
} else {
    $context = context::instance_by_id($pagecontextid);
}

require_login(null, false);
api::require_enabled();
require_capability('moodle/competency:competencymanage', $context);

$returnurl = new moodle_url('/local/dimensions/manage_competencies.php', [
    'frameworkid' => $id,
    'showhidden' => $showhidden,
    'view' => $view,
    'search' => $search,
]);

$url = new moodle_url('/local/dimensions/edit_competency_framework.php', [
    'id' => $id,
    'pagecontextid' => $pagecontextid,
    'showhidden' => $showhidden,
    'view' => $view,
    'search' => $search,
]);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('editcompetencyframework', 'tool_lp'));
$PAGE->set_heading(get_string('managecompetencies', 'local_dimensions'));
$PAGE->navbar->add(get_string('managecompetencies', 'local_dimensions'), $returnurl);
$PAGE->navbar->add(get_string('editcompetencyframework', 'tool_lp'), $url);

$output = $PAGE->get_renderer('tool_lp');
$form = new \tool_lp\form\competency_framework($url->out(false), [
    'context' => $context,
    'persistent' => $framework,
]);

if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $form->get_data()) {
    if (empty($data->id)) {
        $data->contextid = $context->id;
        $framework = api::create_framework($data);
        $returnurl->param('frameworkid', $framework->get('id'));
        $messagesuccess = get_string('competencyframeworkcreated', 'tool_lp');
    } else {
        api::update_framework($data);
        $messagesuccess = get_string('competencyframeworkupdated', 'tool_lp');
    }

    redirect($returnurl, $messagesuccess, 0, \core\output\notification::NOTIFY_SUCCESS);
}

echo $output->header();
echo $output->heading(get_string('editcompetencyframework', 'tool_lp'), 2);
$form->display();
echo $output->footer();
