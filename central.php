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
 * The plugin's admin surface: Structure, Learning plans and Frameworks tabs,
 * each acting in place through core/modal and local_dimensions web services.
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

admin_externalpage_setup('local_dimensions_central');
api::require_enabled();

// Restore the last-visited view (tab / context / selection) from the user's saved preference;
// an explicit URL param always wins so deep-links keep working.
$prefs = helper::get_central_prefs();
$nav = $prefs['nav'];
$contexttype = optional_param('contexttype', $nav['contexttype'], PARAM_ALPHA);
$categoryid = optional_param('categoryid', $nav['categoryid'], PARAM_INT);
$frameworkid = optional_param('frameworkid', $nav['frameworkid'], PARAM_INT);
$templateid = optional_param('templateid', $nav['templateid'], PARAM_INT);
$activetab = optional_param('tab', $nav['tab'], PARAM_ALPHA);
if (!in_array($activetab, ['frameworks', 'structure', 'plans'], true)) {
    $activetab = 'frameworks';
}

$PAGE->set_url(new moodle_url('/local/dimensions/central.php'));
$PAGE->set_title(get_string('central', 'local_dimensions'));
$PAGE->set_heading(get_string('central', 'local_dimensions'));
$PAGE->add_body_class('local-dimensions-central-page');
\local_dimensions\local\bootstrap::mark_page();


// Resolve the shared context once so the page-level selector and both tabs agree.
$resolved = helper::resolve_central_context($contexttype, $categoryid);
$contexttype = $resolved['contexttype'];
$categoryid = (int) $resolved['categoryid'];

// Init the shared view-state store first (before the context selector) with the resolved nav +
// display, so the client saves changes against the state the page actually rendered (e.g. a
// downgraded coursecat context) and context.js can read the saved tab to restore it on load.
$prefs['nav'] = [
    'tab' => $activetab,
    'contexttype' => $contexttype,
    'categoryid' => $categoryid,
    'frameworkid' => $frameworkid,
    'templateid' => $templateid,
    'showhiddencats' => (bool) $nav['showhiddencats'],
];
$PAGE->requires->js_call_amd('local_dimensions/central/preferences', 'init', [$prefs]);

// The context selector is page-level (governs both tabs); init it once on load.
$contextbar = new contextbar($contexttype, $categoryid, (bool) $nav['showhiddencats']);
$PAGE->requires->js_call_amd('local_dimensions/central/context', 'init');
// The page-level sticky footer is shared by both tabs; init its coordinator once.
$PAGE->requires->js_call_amd('local_dimensions/central/action_footer', 'init');

/*
 * Build the three tabs. core/dynamic_tabs opens the FIRST tab in the DOM and ignores the server's
 * active flag — unless the URL fragment names a tab, which the tab_hash template below supplies
 * synchronously before core initialises. With the fragment in place, core opens the same tab the
 * server marked active, so pre-render THAT one: it paints immediately while core's unconditional
 * getContent re-fetches it.
 *
 * Until 2026-08-06 this pre-rendered Frameworks unconditionally and let context.js click the saved
 * tab after load. That cost two concurrent getContent calls on every visit whose saved tab was not
 * Frameworks — measured 845 ms and 1023 ms starting in the same millisecond — with the Frameworks
 * pane left fully rendered and invisible, on top of a PHP render core discarded.
 */
$tabinstances = [
    'frameworks' => new frameworks(['contexttype' => $contexttype, 'categoryid' => $categoryid]),
    'structure' => new structure([
        'contexttype' => $contexttype,
        'categoryid' => $categoryid,
        'frameworkid' => $frameworkid,
    ]),
    'plans' => new plans([
        'contexttype' => $contexttype,
        'categoryid' => $categoryid,
        'templateid' => $templateid,
    ]),
];
$tablabels = [
    'frameworks' => get_string('central_frameworks_tab', 'local_dimensions'),
    'structure' => get_string('managecompetencies_structure', 'local_dimensions'),
    'plans' => get_string('learningplans', 'local_dimensions'),
];
/*
 * A leading FontAwesome glyph per tab (decorative; the label carries the accessible name).
 * Core dynamic_tabs triple-stashes displayname, so the icon rides in the label, no template change.
 */
$tabicons = [
    'frameworks' => 'fa-sitemap',
    'structure' => 'fa-crosshairs',
    'plans' => 'fa-graduation-cap',
];
$tabs = [];
foreach (['frameworks', 'structure', 'plans'] as $shortname) {
    $isactive = ($shortname === $activetab);
    $tab = $tabinstances[$shortname];
    $content = '';
    if ($isactive) {
        $tab->require_access();
        $content = $OUTPUT->render_from_template($tab->get_template(), $tab->export_for_template($OUTPUT));
    }
    $icon = '<i class="fa ' . $tabicons[$shortname] . ' me-1" aria-hidden="true"></i>';
    $tabs[] = [
        'shortname' => $shortname,
        'displayname' => $icon . $tablabels[$shortname],
        'tabclass' => get_class($tab),
        'enabled' => true,
        'active' => $isactive,
        'content' => $content,
    ];
}

$tabsdata = [
    'showtabsnavigation' => true,
    'dataattributes' => [
        ['name' => 'contexttype', 'value' => $contexttype],
        ['name' => 'categoryid', 'value' => $categoryid],
        ['name' => 'frameworkid', 'value' => $frameworkid],
        ['name' => 'templateid', 'value' => $templateid],
    ],
    'tabs' => $tabs,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_dimensions/central/contextbar', $contextbar->export_for_template($OUTPUT));
// Must precede the tabs: it names the tab core opens, and core reads the fragment as it initialises.
echo $OUTPUT->render_from_template('local_dimensions/central/tab_hash', ['activetab' => $activetab]);
echo $OUTPUT->render_from_template('core/dynamic_tabs', $tabsdata);

// One page-level sticky footer shared by all three tabs; rendered disabled so it
// stays hidden until a tab enables it on selection.
$stickyfooter = new \core\output\sticky_footer();
$stickyfooter->set_auto_enable(false);
echo $OUTPUT->render($stickyfooter);

echo $OUTPUT->footer();
