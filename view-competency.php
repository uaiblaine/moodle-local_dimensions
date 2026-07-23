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
use local_dimensions\constants;

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

// Authorization gate: read_plan() is the access check. We intentionally do NOT
// require the competency to be in this plan — related-competency links rendered
// by the accordion (when local_dimensions/showrelated is enabled) point at
// competencies from competency_related, which is framework-wide and not bound
// to competency_templatecomp / competency_plancomp. The competency framework's
// own read permissions cover broader protection.
try {
    $plan = \core_competency\api::read_plan($planid);
} catch (\Exception $e) {
    throw new \moodle_exception('invalidplan', 'local_dimensions');
}
$templateid = (int) $plan->get('templateid');

// Related-competency links can point at a competency that is not in this plan; there the plan
// layer of the cascade does not apply (competency -> global only).
$effectivetemplateid = \local_dimensions\helper::competency_in_plan($competencyid, $plan)
    ? $templateid
    : 0;

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

    // Apply enrollment filter; cascade competency -> template -> global.
    $enrollmentfilter = \local_dimensions\helper::resolve_enrollmentfilter_for_view(
        $competencyid,
        $effectivetemplateid
    );
    if ($enrollmentfilter !== constants::ENROLLMENTFILTER_ALL) {
        $courses = calculator::filter_courses_by_enrollment($courses, $USER->id, $enrollmentfilter);
    }

    // Resolve the singlecourseredirect cascade (competency -> template -> global).
    // The noredirect=1 flag is baked into every FAB URL this page writes, so a
    // FAB click always renders the tracker instead of redirecting: the redirect
    // conditions (course count, enrolment state, the cascade value) can start
    // holding after the URL was cached, and without the flag a stale FAB would
    // bounce straight back to the course the user clicked it from.
    $singlecourseredirect = \local_dimensions\helper::resolve_singlecourseredirect_for_view(
        $competencyid,
        $effectivetemplateid
    );
    $willredirect = (
        $singlecourseredirect
        && !optional_param('noredirect', 0, PARAM_BOOL)
        && count($courses) === 1
        && calculator::user_can_access_course(reset($courses), $USER->id)
    );

    // Own-plan only: see the matching guard in view-plan.php.
    if (
        get_config('local_dimensions', 'enablereturnbutton')
        && (int) $plan->get('userid') === (int) $USER->id
    ) {
        if ($willredirect) {
            // Point the destination course's FAB at the plan overview: this page
            // would just redirect again, and entry paths that never pass through
            // view-plan.php (block card, direct link) would otherwise leave the
            // course with no FAB at all.
            \local_dimensions\helper::set_return_context_for_course(
                (int) reset($courses)->id,
                new moodle_url('/local/dimensions/view-plan.php', ['id' => $planid])
            );
        } else {
            \local_dimensions\helper::set_return_context(
                new moodle_url($PAGE->url, ['noredirect' => 1]),
                array_keys($courses)
            );
        }
    }

    if ($willredirect) {
        $singlecourse = reset($courses);
        redirect(new moodle_url('/course/view.php', ['id' => $singlecourse->id]));
    }
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
    // Prepare locked card settings for JavaScript. Both cascade competency -> plan
    // -> global; the resolvers already apply the same "blocked"/true defaults the
    // global settings use, so no local fallback is needed here.
    $lockedcardmode = \local_dimensions\helper::resolve_lockedcardmode_for_view(
        $competencyid,
        $effectivetemplateid
    );
    $showlockeddate = \local_dimensions\helper::resolve_showlockeddate_for_view(
        $competencyid,
        $effectivetemplateid
    );
    $cardicon = get_config('local_dimensions', 'cardicon');
    $learnmorebuttoncolor = get_config('local_dimensions', 'learnmorebuttoncolor');
    if (empty($learnmorebuttoncolor)) {
        $learnmorebuttoncolor = '#0f6cbf';
    }

    $uisettings = [
        'lockedcardmode' => $lockedcardmode,
        'showlockeddate' => $showlockeddate,
        'cardicon' => $cardicon ? (string) $cardicon : '',
        'learnmorebuttoncolor' => $learnmorebuttoncolor,
        'animatelockedborder' => (bool) get_config('local_dimensions', 'animatelockedborder'),
    ];

    $PAGE->requires->string_for_js('learn_more', 'local_dimensions');
    $PAGE->requires->string_for_js('locked_content', 'local_dimensions');
    $PAGE->requires->string_for_js('available_at', 'local_dimensions');
    $PAGE->requires->string_for_js('enrolment_starts', 'local_dimensions');
    $PAGE->requires->js_call_amd('local_dimensions/competency_view', 'init', [$uisettings]);
}

echo $OUTPUT->footer();
