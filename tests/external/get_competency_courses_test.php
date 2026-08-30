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

use core_competency\course_competency;
use core_competency\course_module_competency;
use core_external\external_api;
use local_dimensions\constants;
use local_dimensions\customfield\lp_handler;
use local_dimensions\helper;

/**
 * Tests for the Panorama courses web service's enrolment-filter cascade and related content.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\external\get_competency_courses
 */
final class get_competency_courses_test extends \advanced_testcase {
    /**
     * A per-plan (template-level) enrollmentfilter of "active" hides an un-enrolled linked course.
     *
     * The competency itself stores no override (inherit), so the cascade falls through to the
     * plan's template, which is looked up from the given planid.
     *
     * @return void
     */
    public function test_execute_applies_plan_template_enrollmentfilter(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_LP);
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);

        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework(['visible' => 1]);
        $competency = $ccg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $competencyid = (int) $competency->get('id');

        $template = $ccg->create_template();
        $templateid = (int) $template->get('id');
        $ccg->create_template_competency([
            'templateid' => $templateid,
            'competencyid' => $competencyid,
        ]);

        $user = $this->getDataGenerator()->create_user();
        $plan = $ccg->create_plan([
            'userid' => $user->id,
            'templateid' => $templateid,
            // Active (not draft) so the owning user can read it via the default planviewown
            // capability, with no extra role assignment needed in this test.
            'status' => \core_competency\plan::STATUS_ACTIVE,
        ]);
        $planid = (int) $plan->get('id');

        $enrolledcourse = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();
        \core_competency\api::add_competency_to_course((int) $enrolledcourse->id, $competencyid);
        \core_competency\api::add_competency_to_course((int) $othercourse->id, $competencyid);

        $this->getDataGenerator()->enrol_user((int) $user->id, (int) $enrolledcourse->id, 'student');

        // Set the template's enrollmentfilter to "active" via the plugin's own customfield path.
        $keys = array_keys(constants::enrollmentfilter_options());
        $data = (object) ['id' => $templateid];
        $data->{'customfield_' . constants::CFIELD_ENROLLMENTFILTER} =
            array_search(constants::ENROLLMENTFILTER_ACTIVE, $keys, true) + 1;
        lp_handler::create()->instance_form_save($data, true);

        $this->setUser($user);
        $result = get_competency_courses::execute($competencyid, $planid);

        $resultids = array_map('intval', array_column($result, 'id'));
        $this->assertContains((int) $enrolledcourse->id, $resultids);
        $this->assertNotContains((int) $othercourse->id, $resultids);
    }

    /**
     * enrolledorself returns enrolled AND self-enrolable linked courses, and drops the rest.
     *
     * @return void
     */
    public function test_execute_enrolledorself_includes_self_enrolable(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_LP);
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);

        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework(['visible' => 1]);
        $competency = $ccg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $competencyid = (int) $competency->get('id');

        $template = $ccg->create_template();
        $templateid = (int) $template->get('id');
        $ccg->create_template_competency([
            'templateid' => $templateid,
            'competencyid' => $competencyid,
        ]);

        $user = $this->getDataGenerator()->create_user();
        $plan = $ccg->create_plan([
            'userid' => $user->id,
            'templateid' => $templateid,
            'status' => \core_competency\plan::STATUS_ACTIVE,
        ]);
        $planid = (int) $plan->get('id');

        $enrolledcourse = $this->getDataGenerator()->create_course();
        $selfcourse = $this->getDataGenerator()->create_course();
        $hiddencourse = $this->getDataGenerator()->create_course();
        \core_competency\api::add_competency_to_course((int) $enrolledcourse->id, $competencyid);
        \core_competency\api::add_competency_to_course((int) $selfcourse->id, $competencyid);
        \core_competency\api::add_competency_to_course((int) $hiddencourse->id, $competencyid);

        $this->getDataGenerator()->enrol_user((int) $user->id, (int) $enrolledcourse->id, 'student');

        // Enable self-enrolment on the second course only.
        $self = enrol_get_plugin('self');
        $selfinstance = $DB->get_record('enrol', ['courseid' => $selfcourse->id, 'enrol' => 'self'], '*', MUST_EXIST);
        $self->update_status($selfinstance, ENROL_INSTANCE_ENABLED);

        // Set the template's enrollmentfilter to "enrolledorself" via the plugin's own customfield path.
        $keys = array_keys(constants::enrollmentfilter_options());
        $data = (object) ['id' => $templateid];
        $data->{'customfield_' . constants::CFIELD_ENROLLMENTFILTER} =
            array_search(constants::ENROLLMENTFILTER_ENROLLEDORSELF, $keys, true) + 1;
        lp_handler::create()->instance_form_save($data, true);

        $this->setUser($user);
        $result = get_competency_courses::execute($competencyid, $planid);
        $resultids = array_map('intval', array_column($result, 'id'));

        $this->assertContains((int) $enrolledcourse->id, $resultids);
        $this->assertContains((int) $selfcourse->id, $resultids);
        $this->assertNotContains((int) $hiddencourse->id, $resultids);
    }

    /**
     * A competency, its framework and a global "show every linked course" filter.
     *
     * @return int The competency id.
     */
    private function set_up_competency(): int {
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_LP);
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);
        set_config('enrollmentfilter', constants::ENROLLMENTFILTER_ALL, 'local_dimensions');

        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework(['visible' => 1]);
        $competency = $ccg->create_competency(['competencyframeworkid' => $framework->get('id')]);

        return (int) $competency->get('id');
    }

    /**
     * Run the service as the given user and clean the payload through the returns structure.
     *
     * clean_returnvalue strips keys the structure does not declare, silently. Asserting on the
     * cleaned payload is therefore the only way a missing allowlist entry fails the test rather
     * than passing unnoticed. The plan id is 0 here: the template cascade has its own tests
     * above, and this way the global filter applies directly.
     *
     * @param int $competencyid The competency id.
     * @param \stdClass $user The user to run as.
     * @return array The cleaned payload, keyed by course id.
     */
    private function cleaned_result_for(int $competencyid, \stdClass $user): array {
        $this->setUser($user);
        $result = external_api::clean_returnvalue(
            get_competency_courses::execute_returns(),
            get_competency_courses::execute($competencyid, 0)
        );

        return array_column($result, null, 'id');
    }

    /**
     * The payload carries each course link's outcome and nests activities under their own course.
     *
     * @return void
     */
    public function test_execute_returns_ruleoutcome_and_groups_activities_by_course(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->libdir . '/completionlib.php');
        set_config('enablecompletion', 1);
        $competencyid = $this->set_up_competency();

        $first = $this->getDataGenerator()->create_course(['fullname' => 'Aaa', 'enablecompletion' => 1]);
        $second = $this->getDataGenerator()->create_course(['fullname' => 'Bbb', 'enablecompletion' => 1]);
        \core_competency\api::add_competency_to_course((int) $first->id, $competencyid);
        \core_competency\api::add_competency_to_course((int) $second->id, $competencyid);
        \core_competency\api::set_course_competency_ruleoutcome(
            course_competency::get_record(['courseid' => (int) $first->id, 'competencyid' => $competencyid]),
            course_competency::OUTCOME_COMPLETE
        );

        $tracked = $this->getDataGenerator()->create_module('page', [
            'course' => $first->id,
            'name' => 'Reflective essay',
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $untracked = $this->getDataGenerator()->create_module('page', [
            'course' => $second->id,
            'name' => 'Discussion prep',
        ]);
        \core_competency\api::add_competency_to_course_module((int) $tracked->cmid, $competencyid);
        \core_competency\api::add_competency_to_course_module((int) $untracked->cmid, $competencyid);
        \core_competency\api::set_course_module_competency_ruleoutcome(
            course_module_competency::get_record(['cmid' => (int) $tracked->cmid, 'competencyid' => $competencyid]),
            course_module_competency::OUTCOME_RECOMMEND
        );

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int) $user->id, (int) $first->id, 'student');
        $this->getDataGenerator()->enrol_user((int) $user->id, (int) $second->id, 'student');

        $bycourse = $this->cleaned_result_for($competencyid, $user);

        $this->assertSame(course_competency::OUTCOME_COMPLETE, $bycourse[(int) $first->id]['ruleoutcome']);
        // Core's DB default, which is why only Complete and Recommend earn a badge.
        $this->assertSame(course_competency::OUTCOME_EVIDENCE, $bycourse[(int) $second->id]['ruleoutcome']);

        $firstactivities = $bycourse[(int) $first->id]['activities'];
        $this->assertCount(1, $firstactivities);
        $this->assertSame((int) $tracked->cmid, $firstactivities[0]['cmid']);
        $this->assertSame('Reflective essay', $firstactivities[0]['name']);
        $this->assertSame(course_module_competency::OUTCOME_RECOMMEND, $firstactivities[0]['ruleoutcome']);
        $this->assertTrue($firstactivities[0]['has_completion']);
        $this->assertFalse($firstactivities[0]['is_completed']);
        $this->assertFalse($firstactivities[0]['locked']);
        $this->assertStringContainsString('/mod/page/view.php?id=' . $tracked->cmid, $firstactivities[0]['url']);
        $this->assertNotSame('', $firstactivities[0]['iconurl']);

        $secondactivities = $bycourse[(int) $second->id]['activities'];
        $this->assertCount(1, $secondactivities);
        $this->assertSame((int) $untracked->cmid, $secondactivities[0]['cmid']);
        $this->assertFalse($secondactivities[0]['has_completion']);
    }

    /**
     * A restricted-but-shown activity comes back locked, pointing at the course rather than itself.
     *
     * Mirrors the section rule: core explains the restriction on the course page, so that is where
     * a locked row has to lead.
     *
     * @return void
     */
    public function test_execute_returns_a_restricted_activity_locked_with_the_course_url(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->enableavailability = 1;
        $competencyid = $this->set_up_competency();

        $course = $this->getDataGenerator()->create_course();
        \core_competency\api::add_competency_to_course((int) $course->id, $competencyid);

        // Date condition in the future, with showc true so the activity is shown greyed.
        $restricted = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Locked essay',
            'availability' => '{"op":"&","c":[{"type":"date","d":">=","t":' . (time() + WEEKSECS) . '}],"showc":[true]}',
        ]);
        \core_competency\api::add_competency_to_course_module((int) $restricted->cmid, $competencyid);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int) $user->id, (int) $course->id, 'student');

        $bycourse = $this->cleaned_result_for($competencyid, $user);
        $activities = $bycourse[(int) $course->id]['activities'];

        $this->assertCount(1, $activities);
        $this->assertTrue($activities[0]['locked']);
        $this->assertStringContainsString('/course/view.php?id=' . $course->id, $activities[0]['url']);
    }

    /**
     * Neither a hidden activity nor one restricted with "hide entirely" reaches the learner.
     *
     * @return void
     */
    public function test_execute_omits_hidden_and_hidden_entirely_activities(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->enableavailability = 1;
        $competencyid = $this->set_up_competency();

        $course = $this->getDataGenerator()->create_course();
        \core_competency\api::add_competency_to_course((int) $course->id, $competencyid);

        $survivor = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'name' => 'Visible']);
        $hidden = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Hidden',
            'visible' => 0,
        ]);
        // Same date condition as above, but showc false - core hides it entirely.
        $hiddenentirely = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Hidden entirely',
            'availability' => '{"op":"&","c":[{"type":"date","d":">=","t":' . (time() + WEEKSECS) . '}],"showc":[false]}',
        ]);
        foreach ([$survivor, $hidden, $hiddenentirely] as $module) {
            \core_competency\api::add_competency_to_course_module((int) $module->cmid, $competencyid);
        }

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int) $user->id, (int) $course->id, 'student');

        $bycourse = $this->cleaned_result_for($competencyid, $user);
        $cmids = array_column($bycourse[(int) $course->id]['activities'], 'cmid');

        $this->assertSame([(int) $survivor->cmid], $cmids);
    }

    /**
     * An actively enrolled viewer gets an open card.
     *
     * @return void
     */
    public function test_execute_reports_open_access_for_an_enrolled_user(): void {
        $this->resetAfterTest();
        $competencyid = $this->set_up_competency();
        $course = $this->getDataGenerator()->create_course();
        \core_competency\api::add_competency_to_course($course->id, $competencyid);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $rows = $this->cleaned_result_for($competencyid, $user);

        $this->assertSame('open', $rows[$course->id]['access']);
        $this->assertSame(0, $rows[$course->id]['lockdate']);
    }

    /**
     * Not enrolled but able to self-enrol: the lock becomes an invitation.
     *
     * @return void
     */
    public function test_execute_reports_enrol_access_when_self_enrolment_is_open(): void {
        $this->resetAfterTest();
        $competencyid = $this->set_up_competency();
        $course = $this->getDataGenerator()->create_course();
        \core_competency\api::add_competency_to_course($course->id, $competencyid);

        $plugin = enrol_get_plugin('self');
        $instance = null;
        foreach (enrol_get_instances($course->id, false) as $candidate) {
            if ($candidate->enrol === 'self') {
                $instance = $candidate;
            }
        }
        $this->assertNotNull($instance, 'The self enrolment instance should exist by default.');
        $plugin->update_status($instance, ENROL_INSTANCE_ENABLED);

        $user = $this->getDataGenerator()->create_user();
        $rows = $this->cleaned_result_for($competencyid, $user);

        $this->assertSame('enrol', $rows[$course->id]['access']);
    }

    /**
     * A course reachable only through enrol_apply reports 'enrol', not 'locked'.
     *
     * Skipped where enrol_apply is not installed. ci.yml checks it out on the 5.01 and 5.02
     * jobs, so those legs run this for real; 4.05 does not, because enrol_apply declares
     * supported = [501, 502] and the integration cannot exist there.
     *
     * @return void
     */
    public function test_execute_reports_enrol_access_for_an_apply_only_course(): void {
        $this->resetAfterTest();
        $plugin = $this->require_apply_plugin();
        $competencyid = $this->set_up_competency();
        $course = $this->getDataGenerator()->create_course();
        \core_competency\api::add_competency_to_course($course->id, $competencyid);
        $this->add_apply_instance($plugin, $course);

        $rows = $this->cleaned_result_for($competencyid, $this->getDataGenerator()->create_user());

        $this->assertSame('enrol', $rows[$course->id]['access']);
    }

    /**
     * An application awaiting a decision gets its own state, between open and locked.
     *
     * @return void
     */
    public function test_execute_reports_pending_access_for_a_lodged_application(): void {
        $this->resetAfterTest();
        $plugin = $this->require_apply_plugin();
        $competencyid = $this->set_up_competency();
        $course = $this->getDataGenerator()->create_course();
        \core_competency\api::add_competency_to_course($course->id, $competencyid);
        $instance = $this->add_apply_instance($plugin, $course);

        $applicant = $this->getDataGenerator()->create_user();
        $plugin->enrol_user($instance, (int) $applicant->id, null, 0, 0, ENROL_USER_SUSPENDED);

        $rows = $this->cleaned_result_for($competencyid, $applicant);

        $this->assertSame('pending', $rows[$course->id]['access']);

        /* Control: an untouched user on the same course is offered the way in, so 'pending'
           above is about this applicant's own row and not about the instance being shut. */
        $rows = $this->cleaned_result_for($competencyid, $this->getDataGenerator()->create_user());
        $this->assertSame('enrol', $rows[$course->id]['access']);
    }

    /**
     * Switch enrol_apply on for this test, or skip when the optional plugin is absent.
     *
     * @return \enrol_plugin The apply plugin.
     */
    private function require_apply_plugin(): \enrol_plugin {
        $plugin = enrol_get_plugin('apply');
        if (!$plugin || !is_callable([$plugin, 'allow_apply'])) {
            $this->markTestSkipped('enrol_apply is not installed on this site.');
        }

        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        return $plugin;
    }

    /**
     * Add an enabled apply instance accepting applications from anybody.
     *
     * @param \enrol_plugin $plugin The apply plugin.
     * @param \stdClass $course The course to add the instance to.
     * @return \stdClass The stored enrol record.
     */
    private function add_apply_instance(\enrol_plugin $plugin, \stdClass $course): \stdClass {
        global $DB;

        $id = $plugin->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'customint3' => 0,
            'customint5' => 0,
            'customint6' => 1,
        ]);

        return $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Neither enrolled nor able to join: locked, and the card gets a date to show.
     *
     * @return void
     */
    public function test_execute_reports_locked_access_with_the_course_start_date(): void {
        $this->resetAfterTest();
        $competencyid = $this->set_up_competency();
        $startdate = time() + WEEKSECS;
        $course = $this->getDataGenerator()->create_course(['startdate' => $startdate]);
        \core_competency\api::add_competency_to_course($course->id, $competencyid);

        $user = $this->getDataGenerator()->create_user();
        $rows = $this->cleaned_result_for($competencyid, $user);

        $this->assertSame('locked', $rows[$course->id]['access']);
        $this->assertSame($startdate, $rows[$course->id]['lockdate']);
        $this->assertFalse($rows[$course->id]['isenrolstart']);
    }

    /**
     * A course with one tracked activity carries it; one with two does not.
     *
     * @return void
     */
    public function test_execute_carries_the_single_trackable_activity(): void {
        $this->resetAfterTest();
        $competencyid = $this->set_up_competency();

        $single = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        \core_competency\api::add_competency_to_course($single->id, $competencyid);
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $single->id,
            'name' => 'Weekly reflection',
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $many = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        \core_competency\api::add_competency_to_course($many->id, $competencyid);
        foreach (['First', 'Second'] as $name) {
            $this->getDataGenerator()->create_module('page', [
                'course' => $many->id,
                'name' => $name,
                'completion' => COMPLETION_TRACKING_MANUAL,
            ]);
        }

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $single->id, 'student');
        $this->getDataGenerator()->enrol_user($user->id, $many->id, 'student');

        $rows = $this->cleaned_result_for($competencyid, $user);

        $this->assertSame('Weekly reflection', $rows[$single->id]['activity']['name']);
        $this->assertSame((int) $page->cmid, $rows[$single->id]['activity']['cmid']);
        $this->assertArrayNotHasKey('activity', $rows[$many->id]);
        $this->assertSame(\local_dimensions\constants::CARDMODE_ACTIVITY, $rows[$single->id]['cardmode']);
        $this->assertTrue($rows[$single->id]['activity']['tracked']);
        $this->assertSame(\local_dimensions\constants::CARDMODE_TIMELINE, $rows[$many->id]['cardmode']);
    }

    /**
     * A one-section course reports the section shape, with its own URL.
     *
     * @return void
     */
    public function test_execute_reports_the_section_card_shape(): void {
        $this->resetAfterTest();
        $competencyid = $this->set_up_competency();
        $course = $this->getDataGenerator()->create_course([
            'numsections' => 0,
            'enablecompletion' => 1,
        ]);
        \core_competency\api::add_competency_to_course($course->id, $competencyid);
        foreach (['First', 'Second'] as $name) {
            $this->getDataGenerator()->create_module('page', [
                'course' => $course->id,
                'name' => $name,
                'completion' => COMPLETION_TRACKING_MANUAL,
            ]);
        }
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $rows = $this->cleaned_result_for($competencyid, $user);

        $this->assertSame(\local_dimensions\constants::CARDMODE_SECTION, $rows[$course->id]['cardmode']);
        $this->assertFalse($rows[$course->id]['section']['hasownname']);
        $this->assertStringContainsString('/course/section.php', $rows[$course->id]['section']['url']);
        $this->assertTrue($rows[$course->id]['section']['tracked']);
    }
}
