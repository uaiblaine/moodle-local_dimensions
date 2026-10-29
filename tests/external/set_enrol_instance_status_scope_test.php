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
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The status toggle acts only on courses the template's competencies are linked to.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_dimensions\external\set_enrol_instance_status::class)]
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
}
