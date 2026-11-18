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
 * Competency hub v2: a framework's tree as a canvas of nodes, beside the hub it does not replace.
 *
 * Site administration entry only. The context and the framework come from the URL, defaulting to
 * the hub's remembered ones, which this page reads and never writes.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use core_competency\api;
use local_dimensions\helper;
use local_dimensions\output\hubv2_page;

admin_externalpage_setup('local_dimensions_centralv2');
api::require_enabled();

$nav = helper::get_central_prefs()['nav'];
$contexttype = optional_param('contexttype', $nav['contexttype'], PARAM_ALPHA);
$categoryid = optional_param('categoryid', $nav['categoryid'], PARAM_INT);
$frameworkid = optional_param('frameworkid', $nav['frameworkid'], PARAM_INT);

$page = new hubv2_page($contexttype, $categoryid, $frameworkid);
$resolved = $page->resolved();
$page->require_access();

$PAGE->set_url(new moodle_url('/local/dimensions/hubv2.php', [
    'contexttype' => $resolved['contexttype'],
    'categoryid' => (int) $resolved['categoryid'],
    'frameworkid' => $frameworkid,
]));
$PAGE->set_title(get_string('hubv2', 'local_dimensions'));
$PAGE->set_heading(get_string('hubv2', 'local_dimensions'));
$PAGE->add_body_class('local-dimensions-hubv2-page');

// The canvas reads its strings in one call at mount; handed over here, that call asks the server nothing.
$PAGE->requires->strings_for_js(hubv2_page::STRINGS, 'local_dimensions');
foreach (hubv2_page::OTHER_STRINGS as $component => $keys) {
    $PAGE->requires->strings_for_js($keys, $component);
}

$data = $page->export_for_template($OUTPUT);
// The category picker searches as the viewer types, with the hub's own datasource.
$placeholder = get_string('managecompetencies_category_placeholder', 'local_dimensions');
$PAGE->requires->js_call_amd('core/form-autocomplete', 'enhance', [
    '#local-dimensions-hubv2-category',
    false,
    'local_dimensions/central/category_datasource',
    $placeholder,
    false,
    true,
    $placeholder,
    true,
]);

$PAGE->requires->js_call_amd('local_dimensions/hubv2_toolbar', 'init', ['.local-dimensions-hubv2-toolbar']);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_dimensions/hubv2', $data);
echo $OUTPUT->footer();
