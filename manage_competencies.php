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

use core_competency\competency;
use core_competency\competency_framework;

// Parameters.
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);

// Admin page setup.
admin_externalpage_setup('local_dimensions_manage');

$PAGE->set_url(new moodle_url('/local/dimensions/manage_competencies.php', ['frameworkid' => $frameworkid]));
$PAGE->set_title(get_string('managecompetencies', 'local_dimensions'));
$PAGE->set_heading(get_string('managecompetencies', 'local_dimensions'));

// Get all visible frameworks only.
$allframeworks = competency_framework::get_records([], 'shortname');
$frameworks = [];
foreach ($allframeworks as $fw) {
    // Only include visible frameworks.
    if ($fw->get('visible')) {
        $frameworks[$fw->get('id')] = $fw;
    }
}

// Build framework options for template.
$frameworkoptions = [];
foreach ($frameworks as $fw) {
    $frameworkoptions[] = [
        'id' => $fw->get('id'),
        'name' => $fw->get('shortname') . ' - ' . $fw->get('idnumber'),
        'selected' => ($fw->get('id') == $frameworkid),
    ];
}

// If no framework selected, use the first one.
if ($frameworkid == 0 && !empty($frameworks)) {
    $frameworkid = array_key_first($frameworks);
    // Update selection.
    if (!empty($frameworkoptions)) {
        $frameworkoptions[0]['selected'] = true;
    }
}

// Get the current framework.
$currentframework = null;
if ($frameworkid > 0 && isset($frameworks[$frameworkid])) {
    $currentframework = $frameworks[$frameworkid];
}

// Get page context for links.
$pagecontextid = $currentframework ? $currentframework->get_context()->id : context_system::instance()->id;

/**
 * Recursively build competency tree data for Mustache.
 *
 * @param array $competencies
 * @param int $frameworkid
 * @param int $pagecontextid
 * @param int $depth
 * @return array
 */
function build_competency_tree_data($competencies, $frameworkid, $pagecontextid, $depth = 0) {
    $result = [];

    foreach ($competencies as $comp) {
        $editurl = new moodle_url('/local/dimensions/edit_competency.php', [
            'id' => $comp['id'],
            'competencyframeworkid' => $frameworkid,
            'pagecontextid' => $pagecontextid,
        ]);

        $item = [
            'id' => $comp['id'],
            'shortname' => format_string($comp['shortname']),
            'idnumber' => $comp['idnumber'],
            'editurl' => $editurl->out(false),
            'depth' => $depth,
            'haschildren' => !empty($comp['children']),
            'children' => [],
        ];

        if (!empty($comp['children'])) {
            $item['children'] = build_competency_tree_data($comp['children'], $frameworkid, $pagecontextid, $depth + 1);
        }

        $result[] = $item;
    }

    return $result;
}

// Build competency tree.
$competencytree = [];
if ($frameworkid > 0) {
    $allcompetencies = competency::get_records(['competencyframeworkid' => $frameworkid], 'path, sortorder');

    // Build tree structure.
    $competencymap = [];
    foreach ($allcompetencies as $comp) {
        $competencymap[$comp->get('id')] = [
            'id' => $comp->get('id'),
            'shortname' => $comp->get('shortname'),
            'idnumber' => $comp->get('idnumber'),
            'parentid' => $comp->get('parentid'),
            'path' => $comp->get('path'),
            'children' => [],
        ];
    }

    // Organize into tree.
    foreach ($competencymap as $id => &$comp) {
        if ($comp['parentid'] > 0 && isset($competencymap[$comp['parentid']])) {
            $competencymap[$comp['parentid']]['children'][] = &$comp;
        } else {
            $competencytree[] = &$comp;
        }
    }
}

// Build template data.
$templatedata = [
    'hasframeworks' => !empty($frameworkoptions),
    'frameworks' => $frameworkoptions,
    'selectedframeworkid' => $frameworkid,
    'hascompetencies' => !empty($competencytree),
    'competencies' => build_competency_tree_data($competencytree, $frameworkid, $pagecontextid),
    'isfirst' => true,
];

// Output.
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_dimensions/manage_competencies', $templatedata);
echo $OUTPUT->footer();
