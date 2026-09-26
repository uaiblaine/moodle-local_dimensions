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
 * External API to get course progress.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use local_dimensions\calculator;
use local_dimensions\constants;
use local_dimensions\local\plan_access;
use core\context\system as context_system;

/**
 * External API to get course progress.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_course_progress extends external_api {
    /**
     * Define input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'courseids' => new external_multiple_structure(
                new external_value(PARAM_INT, get_string('api_course_id', 'local_dimensions')),
                'List of course IDs to calculate',
            ),
            'planid' => new external_value(
                PARAM_INT,
                'A learning plan the caller may read, whose owner the cards describe; 0 for the caller\'s own cards',
                VALUE_DEFAULT,
                0
            ),
            'competencyid' => new external_value(
                PARAM_INT,
                'The competency of the plan whose linked courses are asked for; read only with a plan',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * The main function that executes logic.
     *
     * Without a plan the cards describe the caller. With one they describe the plan's owner, as the plan
     * accordion's do: see calculator::get_course_section_progress() for what stays the viewer's.
     *
     * @param array $courseids List of course IDs to calculate progress for.
     * @param int $planid The learning plan whose owner the cards describe, 0 for the caller's own cards.
     * @param int $competencyid The plan's competency the courses are linked to, read only with a plan.
     * @return array List of course progress results.
     * @throws \moodle_exception 'invalidplan' when no plan has that id, 'competency_id_missing' when the plan does
     *     not reach the competency.
     * @throws \required_capability_exception When the current user may not read the plan or its owner's user
     *     competencies.
     */
    public static function execute($courseids, $planid = 0, $competencyid = 0) {
        // Automatic parameter validation.
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseids' => $courseids,
            'planid' => $planid,
            'competencyid' => $competencyid,
        ]);
        $courseids = $params['courseids'];

        /* local/dimensions:view is granted to every authenticated user by default, so it only
           admits the caller to the tracker. The course ids come from the client, so each one is
           gated below (helper::readable_competency_courses()) before any of its structure is read.
           A plan adds the accordion's gates and the link to its competency (plan_access::tracker_courses()). */
        $systemcontext = context_system::instance();
        self::validate_context($systemcontext);
        require_capability('local/dimensions:view', $systemcontext);

        $cards = plan_access::tracker_courses($params['planid'], $params['competencyid'], $courseids);
        $ownerid = $cards['ownerid'];
        $readable = $cards['courses'];

        $results = [];

        foreach ($courseids as $courseid) {
            try {
                /* Three answers collapse into one: the course does not exist, the viewer may
                   not be told it exists, or it carries no competency link (with a plan: no link to
                   the plan's competency) and so is none of this service's business. All three return
                   the same locked, empty card, so the response never reveals which of the three it was. */
                if (!isset($readable[(int) $courseid])) {
                    $results[] = self::unavailable_row((int) $courseid);
                    continue;
                }

                $data = static::progress_data((int) $courseid, $ownerid);

                // Prepare structured return.
                $sections = [];
                if ($data['enabled'] && !empty($data['sections'])) {
                    foreach ($data['sections'] as $s) {
                        $percentage = $s['percentage'] !== null ? (int) $s['percentage'] : 0;
                        $hasactivities = (bool) $s['has_activities'];

                        $iscompleted = $hasactivities && $percentage >= 100;
                        $isstarted = $hasactivities && $percentage > 0 && $percentage < 100;

                        $sections[] = [
                            'name' => $s['name'],
                            'percentage' => $percentage,
                            'has_activities' => $hasactivities,
                            'url' => (string) $s['url'],
                            'locked' => (bool) $s['locked'],
                            'is_completed' => $iscompleted,
                            'is_started' => $isstarted,
                        ];
                    }
                }

                /* Both return paths of calculator::get_course_section_progress() set every key;
                   the defaults are a guard, since a missing required key (locked,
                   formatted_start_date) would fail the response. */
                $row = [
                    'courseid' => $courseid,
                    'enabled' => $data['enabled'],
                    'locked' => $data['locked'] ?? false,
                    'formatted_start_date' => $data['formatted_start_date'] ?? '',
                    'is_enrolment_start' => !empty($data['is_enrolment_start']),
                    'can_self_enrol' => !empty($data['can_self_enrol']),
                    'is_pending' => !empty($data['is_pending']),
                    'is_future_date' => !empty($data['is_future_date']),
                    'course_url' => $data['course_url'] ?? '',
                    'sections' => $sections,
                    'cardmode' => $data['cardmode'] ?? '',
                    'error' => '',
                ];

                /* activity and section are null on the shapes that do not name them. A
                   declared external_single_structure rejects an explicit null, so the key is
                   omitted rather than sent. */
                if (!empty($data['activity'])) {
                    $row['activity'] = $data['activity'];
                }
                if (!empty($data['section'])) {
                    $row['section'] = $data['section'];
                }

                $results[] = $row;
            } catch (\Throwable $e) {
                // One course's failure, an \Error included, becomes that course's row, not the whole response's.
                $results[] = [
                    'courseid' => $courseid,
                    'enabled' => false,
                    'locked' => false,
                    'formatted_start_date' => '',
                    'is_enrolment_start' => false,
                    'can_self_enrol' => false,
                    'is_pending' => false,
                    'is_future_date' => false,
                    'course_url' => '',
                    'sections' => [],
                    'error' => self::error_text($e),
                ];
            }
        }

        return $results;
    }

    /**
     * One course's progress, as the calculator reports it.
     *
     * Its own method so a test can make a single course fail and check that the others still answer.
     *
     * @param int $courseid A course the viewer may be told about.
     * @param int $ownerid The learner the card describes: the plan owner, or the caller without a plan.
     * @return array The calculator's result.
     */
    protected static function progress_data(int $courseid, int $ownerid): array {
        return calculator::get_course_section_progress($courseid, $ownerid);
    }

    /**
     * The error field of a course whose progress could not be read.
     *
     * Cleaned to its PARAM_TEXT spelling because clean_returnvalue() rejects the WHOLE response, every
     * other course included, when strip_tags() would change one value. Under developer debugging a
     * moodle_exception's message carries its debuginfo: for a DML error, SQL such as "status <> :active".
     *
     * @param \Throwable $e What the calculator threw.
     * @return string
     */
    private static function error_text(\Throwable $e): string {
        return clean_param($e->getMessage(), PARAM_TEXT);
    }

    /**
     * The row returned for a course this viewer may not be told anything about.
     *
     * Shaped like a locked card with nothing named: no date, no course URL, no sections. The
     * client already draws the lock overlay for a course the learner cannot open, so an id
     * that should never have been asked for degrades into that instead of an error - and the
     * row carries none of the structure the gate exists to withhold.
     *
     * @param int $courseid The requested course id.
     * @return array The response row.
     */
    private static function unavailable_row(int $courseid): array {
        return [
            'courseid' => $courseid,
            'enabled' => false,
            'locked' => true,
            'formatted_start_date' => '',
            'is_enrolment_start' => false,
            'can_self_enrol' => false,
            'is_pending' => false,
            'is_future_date' => false,
            'course_url' => '',
            'sections' => [],
            'cardmode' => constants::CARDMODE_TIMELINE,
            'error' => '',
        ];
    }

    /**
     * Define return structure.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'courseid' => new external_value(
                    PARAM_INT,
                    get_string('api_course_id', 'local_dimensions'),
                ),
                'enabled' => new external_value(
                    PARAM_BOOL,
                    get_string('api_completion_enabled', 'local_dimensions'),
                ),
                'cardmode' => new external_value(
                    PARAM_ALPHA,
                    'Which shape the card takes: activity, section or timeline',
                    VALUE_OPTIONAL,
                ),
                'locked' => new external_value(
                    PARAM_BOOL,
                    get_string('api_content_locked', 'local_dimensions'),
                ),
                'formatted_start_date' => new external_value(
                    PARAM_TEXT,
                    get_string('api_formatted_start_date', 'local_dimensions'),
                ),
                'is_enrolment_start' => new external_value(
                    PARAM_BOOL,
                    'Whether the date is an enrolment start date',
                    VALUE_OPTIONAL,
                ),
                'can_self_enrol' => new external_value(
                    PARAM_BOOL,
                    'Whether the viewer can enrol themselves into this locked course',
                    VALUE_OPTIONAL,
                ),
                'is_pending' => new external_value(
                    PARAM_BOOL,
                    'Whether the viewer has an enrolment application awaiting a decision',
                    VALUE_OPTIONAL,
                ),
                'is_future_date' => new external_value(
                    PARAM_BOOL,
                    'Whether the availability date still lies ahead',
                    VALUE_OPTIONAL,
                ),
                'course_url' => new external_value(
                    PARAM_URL,
                    'URL to the course page',
                    VALUE_OPTIONAL,
                ),
                'error' => new external_value(
                    PARAM_TEXT,
                    get_string('api_error_message', 'local_dimensions'),
                    VALUE_OPTIONAL,
                ),
                'activity' => new external_single_structure(
                    [
                        'cmid' => new external_value(PARAM_INT, 'Course module id'),
                        'name' => new external_value(PARAM_TEXT, 'Activity name'),
                        'url' => new external_value(PARAM_URL, 'Activity URL'),
                        'completed' => new external_value(PARAM_BOOL, 'Whether the learner the card describes completed it'),
                        'tracked' => new external_value(PARAM_BOOL, 'Whether completion is tracked for it'),
                    ],
                    'The course\'s single activity, present only when cardmode is activity',
                    VALUE_OPTIONAL,
                ),
                'section' => new external_single_structure(
                    [
                        'name' => new external_value(PARAM_TEXT, 'Section name, empty when Moodle generated it'),
                        'hasownname' => new external_value(PARAM_BOOL, 'Whether a teacher named the section'),
                        'url' => new external_value(PARAM_URL, 'URL of the section'),
                        'tracked' => new external_value(PARAM_BOOL, 'Whether the section holds a tracked activity'),
                    ],
                    'The course\'s only section, present only when cardmode is section',
                    VALUE_OPTIONAL,
                ),
                'sections' => new external_multiple_structure(
                    new external_single_structure([
                        'name' => new external_value(
                            PARAM_TEXT,
                            get_string('api_section_name', 'local_dimensions'),
                        ),
                        'percentage' => new external_value(
                            PARAM_INT,
                            get_string('api_completion_percentage', 'local_dimensions'),
                            VALUE_OPTIONAL,
                        ),
                        'has_activities' => new external_value(
                            PARAM_BOOL,
                            get_string('api_has_activities', 'local_dimensions'),
                        ),
                        'url' => new external_value(
                            PARAM_URL,
                            get_string('api_section_url', 'local_dimensions'),
                        ),
                        'locked' => new external_value(
                            PARAM_BOOL,
                            get_string('api_is_locked', 'local_dimensions'),
                            VALUE_OPTIONAL,
                        ),
                        'is_completed' => new external_value(
                            PARAM_BOOL,
                            'Is completed',
                            VALUE_OPTIONAL,
                        ),
                        'is_started' => new external_value(
                            PARAM_BOOL,
                            'Is started',
                            VALUE_OPTIONAL,
                        ),
                    ]),
                ),
            ]),
        );
    }
}
