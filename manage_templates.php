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
 * Manage learning plan templates page - Navigate templates with custom fields and link out to native cohort/plan management.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use core_competency\api;
use core_competency\template;
use local_dimensions\helper;
use local_dimensions\template_metadata_cache;

// Parameters mirror manage_competencies.php.
$contexttype = optional_param('contexttype', 'system', PARAM_ALPHA);
$categoryid = optional_param('categoryid', 0, PARAM_INT);
$pagecontextid = optional_param('pagecontextid', 0, PARAM_INT);
$view = optional_param('view', 'cards', PARAM_ALPHA);
$search = optional_param('search', '', PARAM_RAW_TRIMMED);
$showhidden = optional_param('showhidden', 0, PARAM_BOOL);

if (!in_array($view, ['cards', 'table'], true)) {
    $view = 'cards';
}

if (!in_array($contexttype, ['system', 'coursecat'], true)) {
    $contexttype = 'system';
}

if ($pagecontextid > 0 && $contexttype === 'system') {
    $pagecontext = context::instance_by_id($pagecontextid, IGNORE_MISSING);
    if ($pagecontext && $pagecontext->contextlevel === CONTEXT_COURSECAT) {
        $contexttype = 'coursecat';
        $categoryid = (int)$pagecontext->instanceid;
    }
}

// In system context route through admin_externalpage_setup so the capability
// registered in settings.php (moodle/competency:templatemanage) becomes the
// real gate, not just a menu filter. The coursecat path keeps the lighter
// require_login + per-context check below to avoid blocking users who only
// hold templateview on a category.
if ($contexttype === 'system') {
    admin_externalpage_setup('local_dimensions_manage_templates');
} else {
    require_login(null, false);
}
api::require_enabled();

// Build readable course category options for the context selector.
$categoryoptions = [];
foreach (core_course_category::make_categories_list() as $optioncategoryid => $categoryname) {
    try {
        $categorycontext = context_coursecat::instance((int)$optioncategoryid);
        if (!template::can_read_context($categorycontext)) {
            continue;
        }
        $categoryoptions[(int)$optioncategoryid] = $categoryname;
    } catch (Throwable $exception) {
        continue;
    }
}

if ($contexttype === 'system') {
    $categoryid = 0;
} else if ($categoryid > 0 && !array_key_exists($categoryid, $categoryoptions)) {
    $categoryid = 0;
}

$iscoursecatcontext = $contexttype === 'coursecat';
$needscategoryselection = $iscoursecatcontext && $categoryid <= 0;
$pagecontext = context_system::instance();
$selectedcategoryname = '';

if ($iscoursecatcontext && $categoryid > 0) {
    $pagecontext = context_coursecat::instance($categoryid);
    $selectedcategoryname = $categoryoptions[$categoryid] ?? '';
}

if (!$needscategoryselection && !template::can_read_context($pagecontext)) {
    throw new required_capability_exception($pagecontext, 'moodle/competency:templateview', 'nopermissions', '');
}

$pagecontextid = $pagecontext->id;
$stateparams = [
    'contexttype' => $contexttype,
    'categoryid' => $categoryid,
    'view' => $view,
    'search' => $search,
    'showhidden' => $showhidden,
];

$url = new moodle_url('/local/dimensions/manage_templates.php', $stateparams);

$PAGE->set_context($pagecontext);
$PAGE->set_pagelayout('admin');
$PAGE->set_url($url);
$PAGE->set_title(get_string('managetemplates', 'local_dimensions'));
$PAGE->set_heading(get_string('managetemplates', 'local_dimensions'));
$PAGE->add_body_class('local-dimensions-manage-page');

if ($pagecontext->contextlevel === CONTEXT_COURSECAT) {
    core_course_category::page_setup();
    if ($templatesnode = $PAGE->settingsnav->find('competencytemplates', navigation_node::TYPE_SETTING)) {
        $templatesnode->make_active();
    }
}

// Fetch templates for the selected context (system or coursecat).
$templates = [];
$hashiddentemplates = false;
if (!$needscategoryselection) {
    // Single SELECT to know whether the toggle should be offered, regardless of
    // how many templates exist in the context. Avoids loading hidden rows just
    // to compute the flag.
    $hashiddentemplates = template::record_exists_select(
        'contextid = :ctx AND visible = 0',
        ['ctx' => $pagecontext->id]
    );

    // When the user is not asking for hidden templates, ask the API to skip
    // them at the SQL level (`$onlyvisible=true`) instead of fetching all and
    // filtering in PHP.
    $alltemplates = api::list_templates('shortname', 'ASC', 0, 0, $pagecontext, 'self', !$showhidden);
    foreach ($alltemplates as $tplrecord) {
        if (!template::can_read_context($tplrecord->get_context())) {
            continue;
        }
        $templates[(int)$tplrecord->get('id')] = $tplrecord;
    }
}

$canmanage = !$needscategoryselection && template::can_manage_context($pagecontext);
$canaddtemplate = $canmanage;

// Per-template plan + cohort counts in two aggregate queries; metadata for
// every template fetched in a single grouped customfield_data SELECT (cache
// hits are honoured first, only misses hit the DB).
$templateids = array_keys($templates);
$plancounts = helper::count_plans_by_template($templateids);
$cohortcounts = helper::count_cohorts_by_template($templateids);
$metadatamap = template_metadata_cache::get_metadata_for_many($templateids);

$templateoptions = [];
foreach ($templates as $tplrecord) {
    $id = (int)$tplrecord->get('id');
    $tplcontextid = $tplrecord->get_context()->id;
    $description = $tplrecord->get('description');
    $descriptionformatted = format_text(
        $description,
        $tplrecord->get('descriptionformat'),
        ['context' => $tplrecord->get_context()]
    );
    $descriptionplain = trim(strip_tags($descriptionformatted));
    $duedate = (int)$tplrecord->get('duedate');

    $editurl = new moodle_url('/local/dimensions/edit_template.php', [
        'id' => $id,
        'pagecontextid' => $tplcontextid,
    ] + $stateparams);
    $cohortsurl = new moodle_url('/admin/tool/lp/template_cohorts.php', [
        'id' => $id,
        'pagecontextid' => $tplcontextid,
    ]);
    $plansurl = new moodle_url('/admin/tool/lp/template_plans.php', [
        'id' => $id,
        'pagecontextid' => $tplcontextid,
    ]);
    $editcompetenciesurl = new moodle_url('/admin/tool/lp/templatecompetencies.php', [
        'templateid' => $id,
        'pagecontextid' => $tplcontextid,
    ]);

    $metadata = $metadatamap[$id] ?? template_metadata_cache::get_template_metadata($id);
    $bgcolor = $metadata['bgcolor'] ?? null;
    $textcolor = $metadata['textcolor'] ?? null;
    $cardimageurl = $metadata['templatecardimageurl'] ?? null;
    $type = $metadata['type'] ?? null;
    $tag1 = $metadata['tag1'] ?? null;
    $tag2 = $metadata['tag2'] ?? null;
    $idnumber = (string)($metadata['idnumber'] ?? '');

    $templateoptions[] = [
        'id' => $id,
        'shortname' => format_string($tplrecord->get('shortname')),
        'idnumber' => s($idnumber),
        'hasidnumber' => $idnumber !== '',
        'description' => $descriptionformatted,
        'descriptionplain' => $descriptionplain !== ''
            ? shorten_text($descriptionplain, 180)
            : get_string('nodescription', 'local_dimensions'),
        'hasdescription' => $descriptionplain !== '',
        'hidden' => !(bool)$tplrecord->get('visible'),
        'plancount' => $plancounts[$id] ?? 0,
        'hasplans' => ($plancounts[$id] ?? 0) > 0,
        'cohortcount' => $cohortcounts[$id] ?? 0,
        'hascohorts' => ($cohortcounts[$id] ?? 0) > 0,
        'duedate' => $duedate,
        'duedateformatted' => $duedate > 0
            ? userdate($duedate, get_string('strftimedate', 'core_langconfig'))
            : '',
        'hasduedate' => $duedate > 0,
        'editurl' => $editurl->out(false),
        'cohortsurl' => $cohortsurl->out(false),
        'plansurl' => $plansurl->out(false),
        'editcompetenciesurl' => $editcompetenciesurl->out(false),
        'pagecontextid' => $tplcontextid,
        'cardimageurl' => $cardimageurl,
        'hascardimage' => !empty($cardimageurl),
        'bgcolor' => $bgcolor,
        'hasbgcolor' => !empty($bgcolor),
        'textcolor' => $textcolor,
        'hastextcolor' => !empty($textcolor),
        'type' => $type,
        'hastype' => !empty($type),
        'tag1' => $tag1,
        'hastag1' => !empty($tag1),
        'tag2' => $tag2,
        'hastag2' => !empty($tag2),
        'hastags' => !empty($tag1) || !empty($tag2),
    ];
}

// Apply server-side search so initial render is already filtered.
if ($search !== '') {
    $needle = core_text::strtolower($search);
    $templateoptions = array_values(array_filter($templateoptions, static function (array $row) use ($needle): bool {
        $haystack = core_text::strtolower($row['shortname'] . ' ' . $row['idnumber']);
        return strpos($haystack, $needle) !== false;
    }));
}

$newtemplateurl = $canaddtemplate ? new moodle_url('/local/dimensions/edit_template.php', [
    'id' => 0,
    'pagecontextid' => $pagecontextid,
] + $stateparams) : null;
$nativetemplatesurl = new moodle_url('/admin/tool/lp/learningplans.php', [
    'pagecontextid' => $pagecontextid,
]);

// Compact JS model for the AMD details-pane populator.
$jsonoptions = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$templatemodels = array_map(static function (array $row): array {
    return [
        'id' => $row['id'],
        'shortname' => $row['shortname'],
        'idnumber' => $row['idnumber'],
        'hasidnumber' => $row['hasidnumber'],
        'description' => $row['description'],
        'hasdescription' => $row['hasdescription'],
        'hidden' => $row['hidden'],
        'duedateformatted' => $row['duedateformatted'],
        'hasduedate' => $row['hasduedate'],
        'plancount' => $row['plancount'],
        'cohortcount' => $row['cohortcount'],
        'type' => $row['type'],
        'hastype' => $row['hastype'],
        'tag1' => $row['tag1'],
        'hastag1' => $row['hastag1'],
        'tag2' => $row['tag2'],
        'hastag2' => $row['hastag2'],
        'editurl' => $row['editurl'],
        'cohortsurl' => $row['cohortsurl'],
        'plansurl' => $row['plansurl'],
        'editcompetenciesurl' => $row['editcompetenciesurl'],
    ];
}, $templateoptions);

$templatedata = [
    'contexttype' => $contexttype,
    'issystemcontext' => $contexttype === 'system',
    'iscoursecatcontext' => $iscoursecatcontext,
    'needscategoryselection' => $needscategoryselection,
    'categoryoptions' => (static function () use ($categoryoptions, $categoryid): array {
        $counts = helper::count_templates_by_category(array_keys($categoryoptions));
        return array_map(static function (int $optioncategoryid, string $categoryname) use ($categoryid, $counts): array {
            $count = $counts[$optioncategoryid] ?? 0;
            return [
                'id' => $optioncategoryid,
                'name' => $categoryname,
                'selected' => $optioncategoryid === $categoryid,
                'templatecount' => $count,
                'hastemplates' => $count > 0,
            ];
        }, array_keys($categoryoptions), array_values($categoryoptions));
    })(),
    'hascategoryoptions' => !empty($categoryoptions),
    'selectedcategoryid' => $categoryid,
    'selectedcategoryname' => $selectedcategoryname,
    'selectedcontextname' => $iscoursecatcontext
        ? $selectedcategoryname
        : get_string('managetemplates_context_system', 'local_dimensions'),
    'hastemplates' => !empty($templateoptions),
    'noaddpermission' => empty($templateoptions) && !$canaddtemplate && $search === '' && !$needscategoryselection,
    'templates' => $templateoptions,
    'templatecount' => count($templateoptions),
    'hashiddentemplates' => $hashiddentemplates,
    'showhidden' => $showhidden,
    'search' => s($search),
    'view' => $view,
    'viewcards' => $view === 'cards',
    'viewtable' => $view === 'table',
    'canmanage' => $canmanage,
    'canaddtemplate' => $canaddtemplate,
    'newtemplateurl' => $newtemplateurl ? $newtemplateurl->out(false) : '',
    'nativetemplatesurl' => $nativetemplatesurl->out(false),
    'pagecontextid' => $pagecontextid,
    'templatesjson' => json_encode(array_values($templatemodels), $jsonoptions),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_dimensions/manage_templates', $templatedata);
$PAGE->requires->js_call_amd('local_dimensions/manage_templates', 'init');
echo $OUTPUT->footer();
