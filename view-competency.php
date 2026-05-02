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
 * View Competency Page (Competency Tracker Mode).
 *
 * Shows the courses linked to a competency for a given learning plan.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use local_dimensions\output\view_competency_page;
use local_dimensions\calculator;

// Parameters received via URL.
$planid = required_param('id', PARAM_INT);
$competencyid = required_param('competencyid', PARAM_INT);

// Security and Login Checks.
require_login();
$context = context_system::instance();
require_capability('local/dimensions:view', $context);

// Moodle Page Configuration.
$PAGE->set_url(new moodle_url('/local/dimensions/view-competency.php', [
    'id' => $planid,
    'competencyid' => $competencyid,
]));
$PAGE->set_context($context);
$PAGE->add_body_class('local-dimensions-viewcompetency');

// Load the competency.
$competency = $DB->get_record('competency', ['id' => $competencyid]);
$pagetitle = $competency ? format_string($competency->shortname) : get_string('pluginname', 'local_dimensions');

$PAGE->set_title($pagetitle);
$PAGE->set_heading('');

// Fetch courses linked to the competency.
$courses = [];
if ($competency) {
    $sql = "SELECT DISTINCT c.id, c.fullname, c.startdate
              FROM {competency_coursecomp} cc
              JOIN {course} c ON c.id = cc.courseid
             WHERE cc.competencyid = :competencyid AND c.visible = 1
          ORDER BY c.startdate ASC";

    $courses = $DB->get_records_sql($sql, ['competencyid' => $competencyid]);

    // Apply enrollment filter setting.
    $enrollmentfilter = get_config('local_dimensions', 'enrollmentfilter');
    if (!empty($enrollmentfilter) && $enrollmentfilter !== 'all') {
        $courses = calculator::filter_courses_by_enrollment($courses, $USER->id, $enrollmentfilter);
    }

    // Redirect directly to course if only one active enrolment and setting is enabled.
    if (
        $enrollmentfilter === 'active'
        && !empty(get_config('local_dimensions', 'singlecourseredirect'))
        && count($courses) === 1
    ) {
        $singlecourse = reset($courses);
        redirect(new moodle_url('/course/view.php', ['id' => $singlecourse->id]));
    }
}

// Store the return URL and valid course IDs for the "Return to Plan" button feature.
if (get_config('local_dimensions', 'enablereturnbutton')) {
    $validcourseids = array_keys($courses);
    \local_dimensions\helper::set_return_context($PAGE->url, $validcourseids);
}

// Start HTML Output.
echo $OUTPUT->header();

// Render page content using Mustache template.
$page = new view_competency_page($competency, $courses, $USER->id);
$templatedata = $page->export_for_template($OUTPUT);
echo $OUTPUT->render_from_template('local_dimensions/view_competency', $templatedata);

// Initialise the collapsible description for the hero.
$PAGE->requires->js_call_amd('local_dimensions/collapsible_description', 'init');

// Inject compiled custom CSS via AMD (no inline template JS).
if (!empty($templatedata['hascustomcss'])) {
    $PAGE->requires->js_call_amd(
        'local_dimensions/customcss_injector',
        'init',
        ['local-dimensions-customcss-source-viewcompetency']
    );
}

// Load AMD module for hero repositioning and progress loading.
if ($competency) {
    // Prepare locked card settings for JavaScript.
    $lockedcardmode = get_config('local_dimensions', 'lockedcardmode');
    if (empty($lockedcardmode)) {
        $lockedcardmode = 'blocked';
    }
    $showlockeddate = (bool) get_config('local_dimensions', 'showlockeddate');
    // Default to true if not set.
    if (get_config('local_dimensions', 'showlockeddate') === false) {
        $showlockeddate = true;
    }
    $cardicon = get_config('local_dimensions', 'cardicon');
    $learnmorebuttoncolor = get_config('local_dimensions', 'learnmorebuttoncolor');
    if (empty($learnmorebuttoncolor)) {
        $learnmorebuttoncolor = '#667eea';
    }

    $uisettings = [
        'lockedcardmode' => $lockedcardmode,
        'showlockeddate' => $showlockeddate,
        'cardicon' => $cardicon ? (string) $cardicon : '',
        'learnmorebuttoncolor' => $learnmorebuttoncolor,
        'animatelockedborder' => (bool) get_config('local_dimensions', 'animatelockedborder'),
    ];

    $PAGE->requires->string_for_js('connection_error', 'local_dimensions');
    $PAGE->requires->string_for_js('learn_more', 'local_dimensions');
    $PAGE->requires->string_for_js('locked_content', 'local_dimensions');
    $PAGE->requires->string_for_js('available_at', 'local_dimensions');
    $PAGE->requires->string_for_js('enrolment_starts', 'local_dimensions');
    $PAGE->requires->js_call_amd('local_dimensions/competency_view', 'init', [$uisettings]);
}

echo $OUTPUT->footer();
