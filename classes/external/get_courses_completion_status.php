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
 * Lightweight external API returning completion + lock status for a list of
 * courses. Used by the view-competency hybrid loader to render completed and
 * locked cards immediately, before issuing the heavier per-course progress
 * call.
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
use local_dimensions\local\plan_access;
use core\context\system as context_system;

/**
 * Get completion + lock status for many courses in a single batched call.
 */
class get_courses_completion_status extends external_api {
    /**
     * Parameters definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'courseids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Course ID'),
                'List of course IDs',
            ),
            'planid' => new external_value(
                PARAM_INT,
                'A learning plan the caller may read, whose owner the statuses describe; 0 for the caller\'s own',
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
     * Return the completion + lock status for each requested course.
     *
     * Without a plan both describe the caller. With one, completion is the plan owner's, and the lock is
     * whether the caller can open the course (calculator::is_locked_for_viewer()), as on the progress cards.
     *
     * @param int[] $courseids
     * @param int $planid The learning plan whose owner the statuses describe, 0 for the caller's own.
     * @param int $competencyid The plan's competency the courses are linked to, read only with a plan.
     * @return array<int, array{courseid:int,iscompleted:bool,islocked:bool}>
     * @throws \moodle_exception 'invalidplan' when no plan has that id, 'competency_id_missing' when the plan does
     *     not reach the competency.
     * @throws \required_capability_exception When the current user may not read the plan or its owner's user
     *     competencies.
     */
    public static function execute($courseids, $planid = 0, $competencyid = 0) {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseids' => $courseids,
            'planid' => $planid,
            'competencyid' => $competencyid,
        ]);
        $courseids = $params['courseids'];

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/dimensions:view', $context);

        /* The same gate as get_course_progress::execute(): the two services feed one card list and must
           answer for the same set of courses, about the same learner. */
        $cards = plan_access::tracker_courses($params['planid'], $params['competencyid'], $courseids);
        $ownerid = $cards['ownerid'];
        $readable = $cards['courses'];

        $results = [];
        foreach ($courseids as $cid) {
            $cid = (int) $cid;
            try {
                if (!isset($readable[$cid])) {
                    // Locked, and nothing else said - see get_course_progress::unavailable_row().
                    $results[] = [
                        'courseid' => $cid,
                        'iscompleted' => false,
                        'islocked' => true,
                    ];
                    continue;
                }

                $course = $readable[$cid];
                $completion = new \completion_info($course);
                $enabled = $completion->is_enabled();
                $iscompleted = false;
                if ($enabled) {
                    $iscompleted = (bool) $completion->is_course_complete($ownerid);
                }
                $islocked = calculator::is_locked_for_viewer($course, $ownerid, (int) $USER->id);
                $results[] = [
                    'courseid' => $cid,
                    'iscompleted' => $iscompleted,
                    'islocked' => $islocked,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'courseid' => $cid,
                    'iscompleted' => false,
                    'islocked' => false,
                ];
            }
        }
        return $results;
    }

    /**
     * Returns definition.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'courseid' => new external_value(PARAM_INT, 'Course ID'),
                'iscompleted' => new external_value(
                    PARAM_BOOL,
                    'Whether the learner the statuses describe completed the course: the plan owner, or the caller without a plan'
                ),
                'islocked' => new external_value(PARAM_BOOL, 'Whether the caller cannot open the course from its card'),
            ])
        );
    }
}
