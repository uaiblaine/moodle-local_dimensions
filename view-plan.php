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
 * View Plan Page
 *
 * Two modes:
 * 1. Full Plan Overview Mode (only plan ID) - Shows all competencies in accordion timeline
 * 2. Competency Tracker Mode (plan ID + competency ID) - Shows courses for a competency
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use local_dimensions\output\view_plan_page;
use local_dimensions\output\view_plan_summary_page;
use local_dimensions\calculator;
use core_competency\api;

// Parameters received via URL.
$planid = required_param('id', PARAM_INT);
$competencyid = optional_param('competencyid', 0, PARAM_INT);

// Security and Login Checks.
require_login();
$context = context_system::instance();
require_capability('local/dimensions:view', $context);

// Moodle Page Configuration.
$PAGE->set_url(new moodle_url('/local/dimensions/view-plan.php', [
    'id' => $planid,
    'competencyid' => $competencyid,
]));
$PAGE->set_context($context);
$PAGE->add_body_class('local-dimensions-viewplan');

// Detect mode based on competencyid parameter.
if ($competencyid) {
    // COMPETENCY TRACKER MODE.
    // Shows courses linked to a competency.

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
    $page = new view_plan_page($competency, $courses, $USER->id);
    $templatedata = $page->export_for_template($OUTPUT);
    echo $OUTPUT->render_from_template('local_dimensions/view_plan', $templatedata);

    // Inject compiled custom CSS via AMD (no inline template JS).
    if (!empty($templatedata['hascustomcss'])) {
        $PAGE->requires->js_call_amd(
            'local_dimensions/customcss_injector',
            'init',
            ['local-dimensions-customcss-source-viewplan']
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
        $PAGE->requires->js_call_amd('local_dimensions/ui', 'init', [$uisettings]);
    }
} else {
    // FULL PLAN OVERVIEW MODE.
    // Shows all competencies in accordion.

    // Get the plan using competency API.
    try {
        $plan = api::read_plan($planid);
    } catch (\Exception $e) {
        throw new moodle_exception('invalidplan', 'local_dimensions');
    }

    // Get plan name for title.
    $pagetitle = format_string($plan->get('name'));

    $PAGE->set_title($pagetitle);
    $PAGE->set_heading('');

    // Store the return URL and valid course IDs using cached template data.
    // This uses a single cache entry per template to serve all students efficiently.
    if (get_config('local_dimensions', 'enablereturnbutton')) {
        // Get valid courses from cache (one entry per template serves all students).
        $validcourseids = \local_dimensions\template_course_cache::get_courses_for_plan($plan);
        \local_dimensions\helper::set_return_context($PAGE->url, $validcourseids);
    }

    // Prepare accordion display settings from admin config.
    // These will be passed to the JavaScript module via js_call_amd().
    $summaryenrollmentfilter = get_config('local_dimensions', 'summaryenrollmentfilter');
    if (empty($summaryenrollmentfilter)) {
        $summaryenrollmentfilter = 'all';
    }

    $accordionsettings = [
        'showdescription' => (bool) get_config('local_dimensions', 'showdescription'),
        'showtaxonomycard' => (bool) get_config('local_dimensions', 'showtaxonomycard'),
        'showpath' => (bool) get_config('local_dimensions', 'showpath'),
        'showrelated' => (bool) get_config('local_dimensions', 'showrelated'),
        'showrelatedlink' => (bool) get_config('local_dimensions', 'showrelatedlink'),
        'viewplanurl' => (new \moodle_url('/local/dimensions/view-plan.php'))->out(false),
        'showevidence' => (bool) get_config('local_dimensions', 'showevidence'),
        'summaryenrollmentfilter' => $summaryenrollmentfilter,
        'enableevidencesubmitbutton' => (bool) get_config('local_dimensions', 'enableevidencesubmitbutton')
            && has_capability(
                'moodle/competency:userevidencemanageown',
                \context_user::instance($plan->get('userid')),
                $plan->get('userid')
            ),
    ];

    // Start HTML Output.
    echo $OUTPUT->header();

    // Render full plan overview using Mustache template.
    $page = new view_plan_summary_page($plan, $USER->id);
    $templatedata = $page->export_for_template($OUTPUT);
    echo $OUTPUT->render_from_template('local_dimensions/view_plan_summary', $templatedata);

    // Inject compiled custom CSS via AMD (no inline template JS).
    if (!empty($templatedata['hascustomcss'])) {
        $PAGE->requires->js_call_amd(
            'local_dimensions/customcss_injector',
            'init',
            ['local-dimensions-customcss-source-summary']
        );
    }

    // Core strings for accordion.
    $PAGE->requires->string_for_js('access_course', 'local_dimensions');
    $PAGE->requires->string_for_js('rating_label', 'local_dimensions');
    $PAGE->requires->string_for_js('proficient_label', 'local_dimensions');
    $PAGE->requires->string_for_js('evidence_label', 'local_dimensions');
    $PAGE->requires->string_for_js('yes', 'local_dimensions');
    $PAGE->requires->string_for_js('no', 'local_dimensions');

    // Tab strings.
    $PAGE->requires->string_for_js('assessment_status', 'local_dimensions');
    $PAGE->requires->string_for_js('description_label', 'local_dimensions');
    $PAGE->requires->string_for_js('taxonomycard_label', 'local_dimensions');

    // Competency path and related strings.
    $PAGE->requires->string_for_js('competency_path', 'local_dimensions');
    $PAGE->requires->string_for_js('in_framework', 'local_dimensions');
    $PAGE->requires->string_for_js('related_dimensions', 'local_dimensions');
    $PAGE->requires->string_for_js('path', 'tool_lp');

    // Evidence strings.
    $PAGE->requires->string_for_js('evidence_type_file', 'local_dimensions');
    $PAGE->requires->string_for_js('evidence_type_manual', 'local_dimensions');
    $PAGE->requires->string_for_js('evidence_type_activity', 'local_dimensions');
    $PAGE->requires->string_for_js('evidence_type_coursegrade', 'local_dimensions');
    $PAGE->requires->string_for_js('evidence_type_prior', 'local_dimensions');
    $PAGE->requires->string_for_js('evidence_type_other', 'local_dimensions');
    $PAGE->requires->string_for_js('evidence_by', 'local_dimensions');
    $PAGE->requires->string_for_js('no_evidence', 'local_dimensions');
    $PAGE->requires->string_for_js('evidence_slider_prev', 'local_dimensions');
    $PAGE->requires->string_for_js('evidence_slider_next', 'local_dimensions');

    // Rules tab strings.
    $PAGE->requires->string_for_js('rules_tab', 'local_dimensions');
    $PAGE->requires->string_for_js('rules_progress', 'local_dimensions');
    $PAGE->requires->string_for_js('rules_total_competencies', 'local_dimensions');
    $PAGE->requires->string_for_js('rules_required_tag', 'local_dimensions');
    $PAGE->requires->string_for_js('rules_assessment_prefix', 'local_dimensions');
    $PAGE->requires->string_for_js('rules_pts', 'local_dimensions');
    $PAGE->requires->string_for_js('rules_no_points', 'local_dimensions');
    $PAGE->requires->string_for_js('evidence_submit', 'local_dimensions');
    $PAGE->requires->string_for_js('rules_todo', 'local_dimensions');
    $PAGE->requires->string_for_js('rules_completed_count', 'local_dimensions');
    $PAGE->requires->string_for_js('rules_info_title', 'local_dimensions');
    $PAGE->requires->string_for_js('rules_missing_mandatory_notice', 'local_dimensions');
    $PAGE->requires->string_for_js('rules_filter_label', 'local_dimensions');
    $PAGE->requires->string_for_js('rules_filter_all', 'local_dimensions');
    $PAGE->requires->string_for_js('rules_filter_required', 'local_dimensions');
    $PAGE->requires->string_for_js('rules_sr_alert', 'local_dimensions');

    $PAGE->requires->js_call_amd('local_dimensions/accordion', 'init', [$accordionsettings]);
}

echo $OUTPUT->footer();
