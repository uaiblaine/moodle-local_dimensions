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

use core_competency\api as competencyapi;
use local_dimensions\task\process_enrol_method;
/**
 * The status toggle acts only on courses the template's competencies are linked to.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\external\set_enrol_instance_status
 */
final class set_enrol_instance_status_scope_test extends \advanced_testcase {
    /**
     * A course not linked to the template is refused with every capability held; the linked one works.
     *
     * The administrator holds every capability, so only the template-membership check can refuse.
     *
     * @return void
     */
    public function test_a_course_not_linked_to_the_template_is_refused(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $dg = $this->getDataGenerator();
        $lpg = $dg->get_plugin_generator('core_competency');
        $framework = $lpg->create_framework();
        $competency = $lpg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $template = $lpg->create_template();
        $lpg->create_template_competency([
            'templateid' => $template->get('id'),
            'competencyid' => $competency->get('id'),
        ]);
        $linked = $dg->create_course();
        $outside = $dg->create_course();
        $lpg->create_course_competency(['competencyid' => $competency->get('id'), 'courseid' => $linked->id]);
        $cohort = $dg->create_cohort();
        competencyapi::create_template_cohort($template->get('id'), $cohort->id);
        $studentroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        foreach ([$linked, $outside] as $course) {
            enrol_get_plugin('cohort')->add_instance($course, ['customint1' => $cohort->id, 'roleid' => $studentroleid]);
        }

        try {
            set_enrol_instance_status::execute(
                (int) $template->get('id'),
                (int) $outside->id,
                (int) $cohort->id,
                process_enrol_method::METHOD_COHORT,
                false
            );
            $this->fail('A course outside the template was toggled.');
        } catch (\invalid_parameter_exception $e) {
            $this->assertStringContainsString('not linked', $e->debuginfo ?? $e->getMessage());
        }
        $instances = process_enrol_method::get_instances((int) $outside->id, 'cohort', (int) $cohort->id);
        $this->assertSame(ENROL_INSTANCE_ENABLED, (int) reset($instances)->status);

        // The control: the linked course is toggled.
        $result = set_enrol_instance_status::execute(
            (int) $template->get('id'),
            (int) $linked->id,
            (int) $cohort->id,
            process_enrol_method::METHOD_COHORT,
            false
        );
        $this->assertTrue($result['configured']);
        $instances = process_enrol_method::get_instances((int) $linked->id, 'cohort', (int) $cohort->id);
        $this->assertSame(ENROL_INSTANCE_DISABLED, (int) reset($instances)->status);
    }

    /**
     * A template whose competency is linked to one course, with the template's cohort, as the
     * administrator.
     *
     * @return array Keys: templateid, courseid, cohortid, course.
     */
    private function linked_course(): array {
        $this->setAdminUser();
        $dg = $this->getDataGenerator();
        $lpg = $dg->get_plugin_generator('core_competency');
        $competency = $lpg->create_competency(['competencyframeworkid' => $lpg->create_framework()->get('id')]);
        $template = $lpg->create_template();
        $lpg->create_template_competency(['templateid' => $template->get('id'), 'competencyid' => $competency->get('id')]);
        $course = $dg->create_course();
        $lpg->create_course_competency(['competencyid' => $competency->get('id'), 'courseid' => $course->id]);
        $cohort = $dg->create_cohort();
        competencyapi::create_template_cohort($template->get('id'), $cohort->id);
        return [
            'templateid' => (int) $template->get('id'),
            'courseid' => (int) $course->id,
            'cohortid' => (int) $cohort->id,
            'course' => $course,
        ];
    }

    /**
     * The status of the method's instance in the course.
     *
     * @param array $f The fixture.
     * @param string $method The method.
     * @return int
     */
    private function instance_status(array $f, string $method): int {
        $instances = process_enrol_method::get_instances($f['courseid'], $method, $f['cohortid']);
        return (int) reset($instances)->status;
    }

    /**
     * Toggle the fixture's instance.
     *
     * @param array $f The fixture.
     * @param string $method The method.
     * @param bool $enabled The requested state.
     * @return array
     */
    private function toggle(array $f, string $method, bool $enabled): array {
        return set_enrol_instance_status::execute($f['templateid'], $f['courseid'], $f['cohortid'], $method, $enabled);
    }

    /**
     * A method disabled on the site is refused and its instance left as it was.
     *
     * @return void
     */
    public function test_a_method_disabled_on_the_site_is_refused(): void {
        global $DB;
        $this->resetAfterTest();
        $f = $this->linked_course();
        $studentroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        enrol_get_plugin('cohort')->add_instance($f['course'], ['customint1' => $f['cohortid'], 'roleid' => $studentroleid]);
        set_config('enrol_plugins_enabled', 'manual,guest,self');

        try {
            $this->toggle($f, process_enrol_method::METHOD_COHORT, false);
            $this->fail('A method disabled on the site was toggled.');
        } catch (\moodle_exception $e) {
            $this->assertSame('central_enrol_methoddisabled', $e->errorcode);
        }
        $this->assertSame(ENROL_INSTANCE_ENABLED, $this->instance_status($f, process_enrol_method::METHOD_COHORT));
    }

    /**
     * While the adhoc task holds the combination's lock the toggle is refused and writes nothing.
     *
     * @return void
     */
    public function test_the_toggle_waits_for_the_enrolment_task(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $f = $this->linked_course();
        $studentroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        enrol_get_plugin('cohort')->add_instance($f['course'], ['customint1' => $f['cohortid'], 'roleid' => $studentroleid]);
        require_once(__DIR__ . '/../fixtures/refusing_lock_factory.php');
        $CFG->lock_factory = '\\local_dimensions\\refusing_lock_factory';

        try {
            $this->toggle($f, process_enrol_method::METHOD_COHORT, false);
            $this->fail('The toggle ran without the lock.');
        } catch (\moodle_exception $e) {
            $this->assertSame('central_enrol_busy', $e->errorcode);
        } finally {
            unset($CFG->lock_factory);
        }
        $this->assertSame(ENROL_INSTANCE_ENABLED, $this->instance_status($f, process_enrol_method::METHOD_COHORT));
    }

    /**
     * Self enrolment refuses to be enabled without the password the site requires, as core's
     * instances page refuses; with a password (the control) it is enabled.
     *
     * @return void
     */
    public function test_the_plugins_own_status_rule_applies(): void {
        global $DB;
        $this->resetAfterTest();
        $f = $this->linked_course();
        $plugin = enrol_get_plugin('self');
        $instanceid = $plugin->add_instance($f['course'], [
            'customint5' => $f['cohortid'],
            'status' => ENROL_INSTANCE_DISABLED,
            'password' => '',
        ]);
        set_config('requirepassword', 1, 'enrol_self');

        try {
            $this->toggle($f, process_enrol_method::METHOD_SELF, true);
            $this->fail('A self enrolment without the required password was enabled.');
        } catch (\moodle_exception $e) {
            $this->assertSame('central_enrol_cannotchangestatus', $e->errorcode);
        }
        $this->assertSame(ENROL_INSTANCE_DISABLED, $this->instance_status($f, process_enrol_method::METHOD_SELF));

        $DB->set_field('enrol', 'password', 'Secret-123', ['id' => $instanceid]);
        $this->assertTrue($this->toggle($f, process_enrol_method::METHOD_SELF, true)['active']);
        $this->assertSame(ENROL_INSTANCE_ENABLED, $this->instance_status($f, process_enrol_method::METHOD_SELF));
    }
}
