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
 * The enrolment state this plugin works out on its own.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\local;

use local_dimensions\calculator;

/**
 * The frozen rule: the states this plugin has always told apart, and no new ones.
 *
 * Three facts, each a calculator predicate the cards asked before this class existed: an enrolment
 * that starts later ({@see calculator::get_enrolment_start_date()}), an enrol_apply application
 * awaiting a decision ({@see calculator::has_pending_application()}) and a self or apply route
 * open to the current user ({@see calculator::current_user_can_enrol()}). Nothing is added here:
 * a site that wants the waiting list, suspended and ended enrolments, guest access or a
 * prerequisite installs local_unlistedcourses and switches on {@see enrolment_provider_unlisted},
 * which owns that rule.
 *
 * The facts are read as the shared state area reads them: a relationship (scheduled, then pending)
 * is the state and an open route beside it is the route line; with no relationship, an open route
 * is the state. Every destination is the course page, as it always was, which core forwards a
 * viewer who is not enrolled to the enrolment page.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrolment_provider_core extends enrolment_provider {
    /**
     * Which implementation this is.
     *
     * @return string
     */
    public function name(): string {
        return 'core';
    }

    /**
     * The state of one course the viewer cannot open.
     *
     * @param \stdClass $course A course record with at least an id.
     * @param int $viewerid The viewer, who must be the current user: the route is asked of $USER.
     * @return array The state facts.
     */
    public function locked_state(\stdClass $course, int $viewerid): array {
        $courseid = (int) $course->id;
        $courseurl = self::course_url($courseid);
        $route = calculator::current_user_can_enrol($courseid) ? $courseurl : null;

        $startdate = calculator::get_enrolment_start_date($course, $viewerid);
        if ($startdate !== null) {
            return self::state(self::STATE_SCHEDULED, $courseid, (int) $startdate, null, $route);
        }
        if (calculator::has_pending_application($courseid, $viewerid)) {
            // No action of its own: the learner applied and waits for somebody else's decision.
            return self::state(self::STATE_PENDING, $courseid, 0, null, $route);
        }
        if ($route !== null) {
            return self::state(self::STATE_OPEN, $courseid, 0, $route);
        }
        return self::none_state($courseid);
    }

    /**
     * The states of several courses the viewer cannot open, one course at a time.
     *
     * @param array $courses Course id => course record with at least an id.
     * @param int $viewerid The viewer, who must be the current user.
     * @return array Course id => state facts.
     */
    public function locked_states(array $courses, int $viewerid): array {
        $states = [];
        foreach ($courses as $courseid => $course) {
            $states[(int) $courseid] = $this->locked_state($course, $viewerid);
        }
        return $states;
    }
}
