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
use local_dimensions\helper;

// Parameters.
$id = optional_param('id', 0, PARAM_INT);
$frameworkid = required_param('competencyframeworkid', PARAM_INT);
$parentid = optional_param('parentid', 0, PARAM_INT);
$pagecontextid = optional_param('pagecontextid', 0, PARAM_INT);
$contexttype = optional_param('contexttype', 'system', PARAM_ALPHA);
$categoryid = optional_param('categoryid', 0, PARAM_INT);
$showhidden = optional_param('showhidden', 0, PARAM_BOOL);
$view = optional_param('view', 'tree', PARAM_ALPHA);
$search = optional_param('search', '', PARAM_RAW_TRIMMED);

if (!in_array($contexttype, ['system', 'coursecat'], true)) {
    $contexttype = 'system';
}

if (!in_array($view, ['tree', 'table'], true)) {
    $view = 'tree';
}

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

$pagecontext = context::instance_by_id($pagecontextid, IGNORE_MISSING);
if ($pagecontext && $pagecontext->contextlevel === CONTEXT_COURSECAT && $contexttype === 'system') {
    $contexttype = 'coursecat';
    $categoryid = (int)$pagecontext->instanceid;
}
if ($contexttype === 'system') {
    $categoryid = 0;
}

$returnparams = [
    'frameworkid' => $frameworkid,
    'contexttype' => $contexttype,
    'categoryid' => $categoryid,
    'showhidden' => $showhidden,
    'view' => $view,
    'search' => $search,
];

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
if ($competency) {
    $parent = $competency->get_parent();
} else if ($parentid > 0) {
    $parent = competency::get_record(['id' => $parentid]);
}

// Page setup.
$url = new moodle_url('/local/dimensions/edit_competency.php', [
    'id' => $id,
    'competencyframeworkid' => $frameworkid,
    'parentid' => $parentid,
    'pagecontextid' => $pagecontextid,
] + $returnparams);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->add_body_class('local-dimensions-edit-competency-page');

$title = $id > 0
    ? get_string('editcompetency', 'tool_lp')
    : get_string('addcompetency', 'tool_lp');

$PAGE->set_title($title);
$PAGE->set_heading($title);

$returnurl = new moodle_url('/local/dimensions/manage_competencies.php', $returnparams);

// Navbar.
$PAGE->navbar->add(get_string('competencies', 'core_competency'), new moodle_url('/admin/tool/lp/competencyframeworks.php'));
$PAGE->navbar->add($framework->get('shortname'), new moodle_url('/admin/tool/lp/competencies.php', [
    'competencyframeworkid' => $frameworkid,
]));
$PAGE->navbar->add($title);

$rulemodels = [];
$rulesmodules = [];
$rulecontext = [
    'competencyid' => 0,
    'canconfigure' => false,
    'status' => get_string('managecompetencies_norule', 'local_dimensions'),
    'summary' => get_string('editcompetency_rule_new_summary', 'local_dimensions'),
    'detail' => '',
];

if ($competency) {
    $rulemodels = helper::get_competency_rule_model($competency);
    $childcount = max(0, count($rulemodels) - 1);
    $rulesmodules = helper::get_competency_rule_modules();
    $ruletype = $competency->get('ruletype');
    $ruleoutcome = (int)$competency->get('ruleoutcome');
    $hasrule = !empty($ruletype) && $ruleoutcome > 0;
    $simpleruletype = strpos((string)$ruletype, 'competency_rule_points') !== false ? 'points' : 'all';
    $outcomedetail = $hasrule ? helper::get_rule_outcome_text($simpleruletype, $ruleoutcome, $competency, $framework) : '';

    $rulecontext = [
        'competencyid' => (int)$competency->get('id'),
        'canconfigure' => $childcount > 0 && !empty($rulesmodules),
        'status' => $hasrule ? helper::get_competency_rule_label($ruletype)
            : get_string('managecompetencies_norule', 'local_dimensions'),
        'summary' => $hasrule ? get_string(
            'editcompetency_rule_active_summary',
            'local_dimensions',
            helper::get_competency_rule_outcome_label($ruleoutcome)
        ) : get_string('editcompetency_rule_none_summary', 'local_dimensions'),
        'detail' => $outcomedetail !== '' ? $outcomedetail : get_string(
            $childcount > 0 ? 'editcompetency_rule_configurable_help' : 'editcompetency_rule_nochildren_help',
            'local_dimensions'
        ),
    ];
}

// Create form.
$customdata = [
    'competency' => $competency,
    'framework' => $framework,
    'parent' => $parent,
    'pagecontextid' => $pagecontextid,
    'rulecontext' => $rulecontext,
];
$form = new competency_form($url, $customdata);

$PAGE->requires->js_call_amd('local_dimensions/edit_competency', 'init', [[
    'competencyId' => $competency ? (int)$competency->get('id') : 0,
    'backgroundColourField' => \local_dimensions\constants::CFIELD_CUSTOMBGCOLOR,
    'textColourField' => \local_dimensions\constants::CFIELD_CUSTOMTEXTCOLOR,
]]);

// Handle form submission.
if ($form->is_cancelled()) {
    redirect($returnurl);
}

if ($data = $form->get_data()) {
    // Prepare competency data.
    $record = new stdClass();
    $record->shortname = $data->shortname;
    $record->idnumber = $data->idnumber;
    $record->description = $data->description['text'];
    $record->descriptionformat = $data->description['format'];
    $record->competencyframeworkid = $data->competencyframeworkid;
    $record->parentid = (int)($data->parentid ?? 0);
    $record->scaleid = $data->scaleid ?? null;
    $record->scaleconfiguration = $data->scaleconfiguration ?? null;

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

        // Invalidate competency metadata cache.
        \local_dimensions\competency_metadata_cache::invalidate_competency($competencyid);

        // Invalidate compiled SCSS cache for this competency.
        if (get_config('local_dimensions', 'enablecustomscss')) {
            \local_dimensions\scss_manager::invalidate_cache($competencyid, 'competency');
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

$level = $competency ? $competency->get_level() : ($parent ? $parent->get_level() + 1 : 1);
$taxonomydata = helper::get_taxonomy_at_level($framework, $level);
$jsonoptions = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;

// Output.
echo $OUTPUT->header();

ob_start();
$form->display();
$formhtml = ob_get_clean();

echo $OUTPUT->render_from_template('local_dimensions/edit_competency', [
    'title' => $title,
    'heading' => $competency ? format_string($competency->get('shortname')) : $title,
    'frameworkname' => format_string($framework->get('shortname')),
    'taxonomy' => $taxonomydata['term'] ?? '',
    'idnumber' => $competency ? $competency->get('idnumber') : '',
    'hasidnumber' => $competency && $competency->get('idnumber') !== '',
    'returnurl' => $returnurl->out(false),
    'formhtml' => $formhtml,
    'competencymodeljson' => json_encode($rulemodels, $jsonoptions),
    'rulesmodulesjson' => json_encode($rulesmodules, $jsonoptions),
    'sections' => [
        [
            'id' => 'sec-basic',
            'label' => get_string('editcompetency_section_basic', 'local_dimensions'),
            'active' => true,
        ],
        [
            'id' => 'sec-eval',
            'label' => get_string('editcompetency_section_evaluation', 'local_dimensions'),
        ],
        [
            'id' => 'sec-rule',
            'label' => get_string('editcompetency_section_rule', 'local_dimensions'),
        ],
        [
            'id' => 'sec-fields',
            'label' => get_string('customfields', 'local_dimensions'),
        ],
    ],
]);

echo $OUTPUT->footer();
