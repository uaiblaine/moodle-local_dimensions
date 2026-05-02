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
 * Manage competencies page - Navigate and edit competencies with custom fields.
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
use core_competency\course_competency;
use local_dimensions\helper;

// Parameters.
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$showhidden = optional_param('showhidden', 0, PARAM_BOOL);
$view = optional_param('view', 'tree', PARAM_ALPHA);
$search = optional_param('search', '', PARAM_RAW_TRIMMED);

if (!in_array($view, ['tree', 'table'], true)) {
    $view = 'tree';
}

// Admin page setup.
admin_externalpage_setup('local_dimensions_manage');
api::require_enabled();

$PAGE->set_url(new moodle_url('/local/dimensions/manage_competencies.php', [
    'frameworkid' => $frameworkid,
    'showhidden' => $showhidden,
    'view' => $view,
    'search' => $search,
]));
$PAGE->set_title(get_string('managecompetencies', 'local_dimensions'));
$PAGE->set_heading(get_string('managecompetencies', 'local_dimensions'));
$PAGE->add_body_class('local-dimensions-manage-page');

// Get frameworks, optionally including hidden structures.
$allframeworks = competency_framework::get_records([], 'shortname');
$frameworks = [];
$hashiddenframeworks = false;
foreach ($allframeworks as $frameworkrecord) {
    $isvisible = (bool)$frameworkrecord->get('visible');
    if (!$isvisible) {
        $hashiddenframeworks = true;
    }
    if (!$isvisible && !$showhidden) {
        continue;
    }

    if (!competency_framework::can_read_context($frameworkrecord->get_context())) {
        continue;
    }

    $frameworks[(int)$frameworkrecord->get('id')] = $frameworkrecord;
}

// If no framework selected, use the first readable framework.
if ($frameworkid == 0 && !empty($frameworks)) {
    $frameworkids = array_keys($frameworks);
    $frameworkid = reset($frameworkids);
}

// Build framework options for template.
$frameworkoptions = [];
$selectedframework = null;
foreach ($frameworks as $frameworkrecord) {
    $id = (int)$frameworkrecord->get('id');
    $selected = $id === $frameworkid;
    if ($selected) {
        $selectedframework = $frameworkrecord;
    }

    $idnumber = $frameworkrecord->get('idnumber');
    $name = format_string($frameworkrecord->get('shortname'));
    if ($idnumber !== '') {
        $name .= ' - ' . s($idnumber);
    }

    $frameworkoptions[] = [
        'id' => $id,
        'name' => $name,
        'shortname' => format_string($frameworkrecord->get('shortname')),
        'idnumber' => s($idnumber),
        'hidden' => !(bool)$frameworkrecord->get('visible'),
        'selected' => $selected,
        'competencycount' => competency::count_records(['competencyframeworkid' => $id]),
    ];
}

// Get page context for links.
$pagecontext = $selectedframework ? $selectedframework->get_context() : context_system::instance();
$pagecontextid = $pagecontext->id;
$canmanage = $selectedframework && has_capability('moodle/competency:competencymanage', $pagecontext);

$PAGE->set_context($pagecontext);

/**
 * Return the taxonomy label at a zero-based depth.
 *
 * @param competency_framework $framework Competency framework.
 * @param int $depth Zero-based tree depth.
 * @return string
 */
function local_dimensions_manage_taxonomy_label(competency_framework $framework, int $depth): string {
    $level = max(1, $depth + 1);
    $taxonomy = $framework->get_taxonomy($level);
    if (!$taxonomy) {
        $taxonomy = competency_framework::TAXONOMY_COMPETENCY;
    }
    return get_string('taxonomy_' . $taxonomy, 'core_competency');
}

/**
 * Return a display label for a competency scale.
 *
 * @param int|null $scaleid Scale ID.
 * @return string
 */
function local_dimensions_manage_scale_label(?int $scaleid): string {
    static $scales = [];

    if (empty($scaleid)) {
        return get_string('inheritfromframework', 'tool_lp');
    }

    if (!array_key_exists($scaleid, $scales)) {
        $scale = \grade_scale::fetch(['id' => $scaleid]);
        $scales[$scaleid] = $scale ? format_string($scale->name) : get_string('unknown');
    }

    return $scales[$scaleid];
}

/**
 * Return a display label for the competency rule.
 *
 * @param competency $competencyrecord Competency record.
 * @return string
 */
function local_dimensions_manage_rule_label(competency $competencyrecord): string {
    $ruletype = $competencyrecord->get('ruletype');
    if (empty($ruletype) || (int)$competencyrecord->get('ruleoutcome') <= 0) {
        return get_string('managecompetencies_norule', 'local_dimensions');
    }

    $rules = competency::get_available_rules();
    if (isset($rules[$ruletype])) {
        return (string)$rules[$ruletype];
    }

    return get_string('competencyrule', 'tool_lp');
}

/**
 * Count linked courses for many competencies while preserving core visibility checks.
 *
 * @param array $competencyids Competency IDs.
 * @return array Counts indexed by competency ID.
 */
function local_dimensions_manage_course_counts(array $competencyids): array {
    global $DB;

    $competencyids = array_values(array_unique(array_filter(array_map('intval', $competencyids))));
    if (empty($competencyids)) {
        return [];
    }

    $counts = array_fill_keys($competencyids, 0);
    $courseaccess = [];
    $capabilities = ['moodle/competency:coursecompetencyview', 'moodle/competency:coursecompetencymanage'];

    try {
        foreach (array_chunk($competencyids, 1000) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'competencyid');
            $sql = "SELECT coursecomp.id, coursecomp.competencyid, coursecomp.courseid, course.visible
                      FROM {" . course_competency::TABLE . "} coursecomp
                      JOIN {course} course ON course.id = coursecomp.courseid
                     WHERE coursecomp.competencyid $insql";
            $records = $DB->get_records_sql($sql, $params);

            foreach ($records as $record) {
                $courseid = (int)$record->courseid;
                if (!array_key_exists($courseid, $courseaccess)) {
                    try {
                        $coursecontext = context_course::instance($courseid);
                        $coursevisible = (bool)$record->visible
                            || has_capability('moodle/course:viewhiddencourses', $coursecontext);
                        $courseaccess[$courseid] = $coursevisible && has_any_capability($capabilities, $coursecontext);
                    } catch (Throwable $exception) {
                        $courseaccess[$courseid] = false;
                    }
                }

                if ($courseaccess[$courseid]) {
                    $counts[(int)$record->competencyid]++;
                }
            }
        }
    } catch (Throwable $exception) {
        return $counts;
    }

    return $counts;
}

/**
 * Return the native tool_lp competency rule modules available in core.
 *
 * @return array Rule module descriptors.
 */
function local_dimensions_manage_rules_modules(): array {
    return helper::get_competency_rule_modules();
}

/**
 * Recursively build competency tree data for Mustache.
 *
 * @param int $parentid Parent competency ID.
 * @param array $childrenbyparent Competencies grouped by parent ID.
 * @param competency_framework $framework Competency framework.
 * @param int $pagecontextid Page context ID.
 * @param bool $canmanage Whether the user can manage competencies.
 * @param array $coursecounts Course counts indexed by competency ID.
 * @param array $ancestorlabels Parent labels.
 * @param array $flatrows Flat row accumulator.
 * @param array $competencymodels Flat JS model accumulator.
 * @param int $depth Zero-based depth.
 * @return array
 */
function local_dimensions_build_competency_tree_data(
    int $parentid,
    array $childrenbyparent,
    competency_framework $framework,
    int $pagecontextid,
    bool $canmanage,
    array $coursecounts,
    array $ancestorlabels,
    array &$flatrows,
    array &$competencymodels,
    int $depth = 0
): array {
    $items = [];
    $frameworkid = (int)$framework->get('id');

    foreach ($childrenbyparent[$parentid] ?? [] as $competencyrecord) {
        $competencyid = (int)$competencyrecord->get('id');
        $shortname = format_string($competencyrecord->get('shortname'));
        $idnumber = s($competencyrecord->get('idnumber'));
        $description = format_text(
            $competencyrecord->get('description'),
            $competencyrecord->get('descriptionformat'),
            ['context' => $framework->get_context()]
        );
        $descriptionplain = trim(strip_tags($description));
        $pathlabels = array_merge($ancestorlabels, [$shortname]);
        $haschildren = !empty($childrenbyparent[$competencyid]);
        $rulelabel = local_dimensions_manage_rule_label($competencyrecord);
        $coursecount = $coursecounts[$competencyid] ?? 0;

        $editurl = new moodle_url('/local/dimensions/edit_competency.php', [
            'id' => $competencyid,
            'competencyframeworkid' => $frameworkid,
            'parentid' => (int)$competencyrecord->get('parentid'),
            'pagecontextid' => $pagecontextid,
        ]);
        $addchildurl = new moodle_url('/local/dimensions/edit_competency.php', [
            'competencyframeworkid' => $frameworkid,
            'parentid' => $competencyid,
            'pagecontextid' => $pagecontextid,
        ]);

        $item = [
            'id' => $competencyid,
            'parentid' => (int)$competencyrecord->get('parentid'),
            'competencyframeworkid' => $frameworkid,
            'shortname' => $shortname,
            'idnumber' => $idnumber,
            'editurl' => $editurl->out(false),
            'addchildurl' => $addchildurl->out(false),
            'depth' => $depth,
            'indent' => $depth * 22,
            'taxonomy' => local_dimensions_manage_taxonomy_label($framework, $depth),
            'childtaxonomy' => local_dimensions_manage_taxonomy_label($framework, $depth + 1),
            'pathlabel' => implode(' / ', $pathlabels),
            'description' => $description,
            'descriptionplain' => $descriptionplain !== '' ? $descriptionplain : get_string('nodescription', 'local_dimensions'),
            'scale' => local_dimensions_manage_scale_label($competencyrecord->get('scaleid')),
            'rule' => $rulelabel,
            'hasrule' => $rulelabel !== get_string('managecompetencies_norule', 'local_dimensions'),
            'coursecount' => $coursecount,
            'hascourses' => $coursecount > 0,
            'haschildren' => $haschildren,
            'childcount' => count($childrenbyparent[$competencyid] ?? []),
            'canmanage' => $canmanage,
            'children' => [],
        ];

        $flatrow = $item;
        unset($flatrow['children']);
        $flatrows[] = $flatrow;

        $competencymodels[] = [
            'id' => $competencyid,
            'parentid' => (int)$competencyrecord->get('parentid'),
            'competencyframeworkid' => $frameworkid,
            'shortname' => $shortname,
            'path' => $competencyrecord->get('path'),
            'ruletype' => $competencyrecord->get('ruletype'),
            'ruleoutcome' => (int)$competencyrecord->get('ruleoutcome'),
            'ruleconfig' => $competencyrecord->get('ruleconfig'),
        ];

        if ($haschildren) {
            $item['children'] = local_dimensions_build_competency_tree_data(
                $competencyid,
                $childrenbyparent,
                $framework,
                $pagecontextid,
                $canmanage,
                $coursecounts,
                $pathlabels,
                $flatrows,
                $competencymodels,
                $depth + 1
            );
        }

        $items[] = $item;
    }

    return $items;
}

// Build competency tree.
$competencytree = [];
$flatcompetencies = [];
$competencymodels = [];
$competencycount = 0;
$hascompetencies = false;

if ($selectedframework) {
    $allcompetencies = competency::get_records(['competencyframeworkid' => $frameworkid], 'path, sortorder');
    $competencycount = count($allcompetencies);
    $hascompetencies = $competencycount > 0;
    $coursecounts = local_dimensions_manage_course_counts(array_map(static function(competency $competencyrecord): int {
        return (int)$competencyrecord->get('id');
    }, $allcompetencies));
    $childrenbyparent = [];
    foreach ($allcompetencies as $competencyrecord) {
        $parentid = (int)$competencyrecord->get('parentid');
        if (!isset($childrenbyparent[$parentid])) {
            $childrenbyparent[$parentid] = [];
        }
        $childrenbyparent[$parentid][] = $competencyrecord;
    }

    $competencytree = local_dimensions_build_competency_tree_data(
        0,
        $childrenbyparent,
        $selectedframework,
        $pagecontextid,
        $canmanage,
        $coursecounts,
        [],
        $flatcompetencies,
        $competencymodels
    );
}

$rootaddurl = $selectedframework ? new moodle_url('/local/dimensions/edit_competency.php', [
    'competencyframeworkid' => $frameworkid,
    'pagecontextid' => $pagecontextid,
]) : null;
$editframeworkurl = $selectedframework ? new moodle_url('/local/dimensions/edit_competency_framework.php', [
    'id' => $frameworkid,
    'pagecontextid' => $pagecontextid,
    'showhidden' => $showhidden,
    'view' => $view,
    'search' => $search,
]) : null;
$importurl = new moodle_url('/admin/tool/lpimportcsv/index.php');
$exporturl = new moodle_url('/admin/tool/lpimportcsv/export.php');
$jsonoptions = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

// Build template data.
$templatedata = [
    'hasframeworks' => !empty($frameworkoptions),
    'hashiddenframeworks' => $hashiddenframeworks,
    'showhidden' => $showhidden,
    'frameworks' => $frameworkoptions,
    'selectedframeworkid' => $frameworkid,
    'hasselectedframework' => !empty($selectedframework),
    'selectedframeworkname' => $selectedframework ? format_string($selectedframework->get('shortname')) : '',
    'selectedframeworkidnumber' => $selectedframework ? s($selectedframework->get('idnumber')) : '',
    'selectedframeworkhidden' => $selectedframework ? !(bool)$selectedframework->get('visible') : false,
    'selectedframeworkdescription' => $selectedframework ? format_text(
        $selectedframework->get('description'),
        $selectedframework->get('descriptionformat'),
        ['context' => $pagecontext]
    ) : '',
    'competencycount' => $competencycount,
    'hascompetencies' => $hascompetencies,
    'competencies' => $view === 'tree' ? $competencytree : [],
    'flatcompetencies' => $view === 'table' ? $flatcompetencies : [],
    'competenciesjson' => json_encode(array_values($competencymodels), $jsonoptions),
    'rulesmodulesjson' => json_encode($canmanage ? local_dimensions_manage_rules_modules() : [], $jsonoptions),
    'canmanage' => $canmanage,
    'rootaddurl' => $rootaddurl ? $rootaddurl->out(false) : '',
    'editframeworkurl' => $editframeworkurl ? $editframeworkurl->out(false) : '',
    'importurl' => $importurl->out(false),
    'exporturl' => $exporturl->out(false),
    'viewtree' => $view === 'tree',
    'viewtable' => $view === 'table',
    'search' => s($search),
    'pagecontextid' => $pagecontextid,
    'isfirst' => true,
];

// Output.
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_dimensions/manage_competencies', $templatedata);
$PAGE->requires->js_call_amd('local_dimensions/manage_competencies', 'init');
echo $OUTPUT->footer();
