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

namespace local_dimensions\external;

/**
 * Tests for the competency course-link external functions.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\external\get_competency_links
 * @covers     \local_dimensions\external\link_competency_course
 * @covers     \local_dimensions\external\unlink_competency_course
 * @covers     \local_dimensions\external\set_course_link_outcome
 */
final class competency_course_links_test extends \advanced_testcase {
    /**
     * Create a visible framework + competency and two courses.
     *
     * @param int $frameworkvisible Whether the framework is visible.
     * @return array [int $competencyid, int $courseid1, int $courseid2]
     */
    private function fixture(int $frameworkvisible = 1): array {
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework(['visible' => $frameworkvisible]);
        $competency = $ccg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        return [(int) $competency->get('id'), (int) $course1->id, (int) $course2->id];
    }

    /**
     * get_competency_links returns linked courses with outcome, paginated.
     *
     * @return void
     */
    public function test_get_links_lists_courses(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$competencyid, $courseid] = $this->fixture();

        link_competency_course::execute($competencyid, $courseid);

        $result = get_competency_links::execute($competencyid, '', 0, 25);
        $this->assertSame(1, $result['total']);
        $this->assertTrue($result['canlink']);
        $this->assertSame($courseid, (int) $result['items'][0]['courseid']);
        $this->assertSame(1, (int) $result['items'][0]['canmanage']);
        $this->assertSame(0, (int) $result['items'][0]['modulecount']);
        $this->assertSame(0, (int) $result['items'][0]['hascompletion']);
        $this->assertStringContainsString('/course/view.php', $result['items'][0]['courseurl']);
        // Admins hold moodle/course:update, so the completion settings link is present.
        $this->assertStringContainsString('/course/completion.php', $result['items'][0]['completionurl']);
    }

    /**
     * The course row exposes completion-rule state and the linked-activity count.
     *
     * @return void
     */
    public function test_get_links_completion_and_module_counts(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/completion/criteria/completion_criteria.php');
        set_config('enablecompletion', 1);
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework(['visible' => 1]);
        $competency = $ccg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $competencyid = (int) $competency->get('id');
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $courseid = (int) $course->id;
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $courseid]);
        $this->getDataGenerator()->create_module('assign', ['course' => $courseid]);
        $DB->insert_record('course_completion_criteria', (object) [
            'course' => $courseid,
            'criteriatype' => COMPLETION_CRITERIA_TYPE_SELF,
        ]);

        link_competency_course::execute($competencyid, $courseid);
        link_competency_module::execute($competencyid, (int) $assign->cmid);

        $result = get_competency_links::execute($competencyid, '', 0, 25);
        $item = $result['items'][0];
        $this->assertSame(1, (int) $item['modulecount']);
        $this->assertSame(1, (int) $item['hascompletion']);
    }

    /**
     * get_competency_links scopes the total and list to the viewer's manageable courses.
     *
     * @return void
     */
    public function test_get_links_respects_manageable_courses(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$competencyid, $courseid1, $courseid2] = $this->fixture();

        link_competency_course::execute($competencyid, $courseid1);
        link_competency_course::execute($competencyid, $courseid2);

        // Site admin sees both.
        $adminresult = get_competency_links::execute($competencyid, '', 0, 25);
        $this->assertSame(2, $adminresult['total']);
        $this->assertCount(2, $adminresult['items']);

        // A user who can view competencies but manages course competencies only in course 1 sees 1.
        $user = $this->getDataGenerator()->create_user();
        $systemcontext = \core\context\system::instance();
        $coursecontext = \core\context\course::instance($courseid1);
        $viewrole = $this->getDataGenerator()->create_role(['shortname' => 'compviewer']);
        assign_capability('moodle/competency:competencyview', CAP_ALLOW, $viewrole, $systemcontext->id);
        role_assign($viewrole, $user->id, $systemcontext->id);
        $managerole = $this->getDataGenerator()->create_role(['shortname' => 'ccmanager']);
        assign_capability('moodle/competency:coursecompetencymanage', CAP_ALLOW, $managerole, $coursecontext->id);
        role_assign($managerole, $user->id, $coursecontext->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($user);

        $scoped = get_competency_links::execute($competencyid, '', 0, 25);
        $this->assertSame(1, $scoped['total']);
        $this->assertCount(1, $scoped['items']);
        $this->assertSame($courseid1, (int) $scoped['items'][0]['courseid']);
    }

    /**
     * canlink is false when the competency's framework is hidden.
     *
     * @return void
     */
    public function test_get_links_canlink_false_for_hidden_framework(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$competencyid] = $this->fixture(0);

        $result = get_competency_links::execute($competencyid, '', 0, 25);
        $this->assertFalse($result['canlink']);
    }

    /**
     * Linking, setting the outcome, and unlinking a course round-trips.
     *
     * @return void
     */
    public function test_link_set_outcome_unlink(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$competencyid, $courseid] = $this->fixture();

        $added = link_competency_course::execute($competencyid, $courseid);
        $this->assertSame($courseid, (int) $added['courseid']);
        $this->assertSame(0, (int) $added['hascompletion']);
        $this->assertStringContainsString('/course/view.php', $added['courseurl']);
        $count = $DB->count_records('competency_coursecomp', ['competencyid' => $competencyid, 'courseid' => $courseid]);
        $this->assertSame(1, $count);

        $outcome = \core_competency\course_competency::OUTCOME_COMPLETE;
        $this->assertTrue(set_course_link_outcome::execute($competencyid, $courseid, $outcome)['success']);
        $links = get_competency_links::execute($competencyid, '', 0, 25);
        $this->assertSame($outcome, (int) $links['items'][0]['ruleoutcome']);

        $this->assertTrue(unlink_competency_course::execute($competencyid, $courseid)['success']);
        $count = $DB->count_records('competency_coursecomp', ['competencyid' => $competencyid, 'courseid' => $courseid]);
        $this->assertSame(0, $count);
    }

    /**
     * Unlinking a course cascades to its activity links.
     *
     * @return void
     */
    public function test_unlink_course_cascades_modules(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$competencyid, $courseid] = $this->fixture();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $courseid]);

        link_competency_course::execute($competencyid, $courseid);
        link_competency_module::execute($competencyid, (int) $assign->cmid);
        $count = $DB->count_records('competency_modulecomp', ['competencyid' => $competencyid, 'cmid' => (int) $assign->cmid]);
        $this->assertSame(1, $count);

        unlink_competency_course::execute($competencyid, $courseid);
        $count = $DB->count_records('competency_modulecomp', ['competencyid' => $competencyid, 'cmid' => (int) $assign->cmid]);
        $this->assertSame(0, $count);
    }

    /**
     * A user without coursecompetencymanage cannot link a course.
     *
     * @return void
     */
    public function test_link_requires_capability(): void {
        $this->resetAfterTest();
        [$competencyid, $courseid] = $this->fixture();
        // Enrolled as a student: can access the course context (passes validate_context) but
        // lacks moodle/competency:coursecompetencymanage, so core's require_capability fails.
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $courseid, 'student');
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        link_competency_course::execute($competencyid, $courseid);
    }
}
