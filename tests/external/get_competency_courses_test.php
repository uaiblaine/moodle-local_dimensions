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

use local_dimensions\constants;
use local_dimensions\customfield\lp_handler;
use local_dimensions\helper;

/**
 * Tests for the Panorama courses web service's enrolment-filter cascade.
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
}
