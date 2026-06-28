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
 * Competency hub — single-surface admin built on core dynamic tabs.
 *
 * Work in progress (Phase 1: the Structure tab). Runs alongside the existing
 * manage_competencies.php; see docs/admin-redesign-codeplan.md.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use core_competency\api;
use local_dimensions\helper;
use local_dimensions\output\central\contextbar;
use local_dimensions\output\dynamictabs\frameworks;
use local_dimensions\output\dynamictabs\plans;
use local_dimensions\output\dynamictabs\structure;

$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$contexttype = optional_param('contexttype', 'system', PARAM_ALPHA);
$categoryid = optional_param('categoryid', 0, PARAM_INT);

admin_externalpage_setup('local_dimensions_central');
api::require_enabled();

$PAGE->set_url(new moodle_url('/local/dimensions/central.php'));
$PAGE->set_title(get_string('central', 'local_dimensions'));
$PAGE->set_heading(get_string('central', 'local_dimensions'));
$PAGE->add_body_class('local-dimensions-central-page');

// Resolve the shared context once so the page-level selector and both tabs agree.
$resolved = helper::resolve_central_context($contexttype, $categoryid);
$contexttype = $resolved['contexttype'];
$categoryid = (int) $resolved['categoryid'];

// The context selector is page-level (governs both tabs); init it once on load.
$contextbar = new contextbar($contexttype, $categoryid);
$PAGE->requires->js_call_amd('local_dimensions/central/context', 'init');

// Build the Structure tab and pre-render its body; refresh after actions is done
// client-side via core_dynamic_tabs_get_content (no page reload).
$structuretab = new structure([
    'contexttype' => $contexttype,
    'categoryid' => $categoryid,
    'frameworkid' => $frameworkid,
]);
$structuretab->require_access();
$structurecontent = $OUTPUT->render_from_template(
    $structuretab->get_template(),
    $structuretab->export_for_template($OUTPUT)
);

$tabsdata = [
    'showtabsnavigation' => true,
    'dataattributes' => [
        ['name' => 'contexttype', 'value' => $contexttype],
        ['name' => 'categoryid', 'value' => $categoryid],
        ['name' => 'frameworkid', 'value' => $frameworkid],
    ],
    'tabs' => [
        [
            'shortname' => 'structure',
            'displayname' => $structuretab->get_tab_label(),
            'tabclass' => structure::class,
            'enabled' => true,
            'active' => true,
            'content' => $structurecontent,
        ],
        [
            'shortname' => 'plans',
            'displayname' => get_string('learningplans', 'local_dimensions'),
            'tabclass' => plans::class,
            'enabled' => true,
            'active' => false,
            'content' => '',
        ],
        [
            'shortname' => 'frameworks',
            'displayname' => get_string('central_frameworks_tab', 'local_dimensions'),
            'tabclass' => frameworks::class,
            'enabled' => true,
            'active' => false,
            'content' => '',
        ],
    ],
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_dimensions/central/contextbar', $contextbar->export_for_template($OUTPUT));
echo $OUTPUT->render_from_template('core/dynamic_tabs', $tabsdata);
echo $OUTPUT->footer();
