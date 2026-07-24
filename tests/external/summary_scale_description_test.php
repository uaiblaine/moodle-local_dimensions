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
 * Tests the admin gate on the rating scale's description.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\external\get_user_competency_summary_in_plan
 */
final class summary_scale_description_test extends \advanced_testcase {
    /**
     * With the setting on, the payload carries the scale's own description.
     *
     * @return void
     */
    public function test_execute_returns_the_scale_description_when_enabled(): void {
        $this->resetAfterTest();
        set_config('showscaledescription', 1, 'local_dimensions');
        [$competencyid, $planid, $user] = $this->set_up_plan_with_described_scale();

        $this->setUser($user);
        $payload = json_decode(get_user_competency_summary_in_plan::execute($competencyid, $planid));

        $this->assertStringContainsString(
            'Four-level skills scale',
            $payload->usercompetencysummary->competency->scaledescription
        );
    }

    /**
     * With the setting off, the field is empty and the client renders no button.
     *
     * @return void
     */
    public function test_execute_suppresses_the_scale_description_when_disabled(): void {
        $this->resetAfterTest();
        set_config('showscaledescription', 0, 'local_dimensions');
        [$competencyid, $planid, $user] = $this->set_up_plan_with_described_scale();

        $this->setUser($user);
        $payload = json_decode(get_user_competency_summary_in_plan::execute($competencyid, $planid));

        $this->assertSame('', $payload->usercompetencysummary->competency->scaledescription);
    }

    /**
     * A plan holding one competency whose framework scale carries a description.
     *
     * @return array The competency id, the plan id and the plan's owner.
     */
    private function set_up_plan_with_described_scale(): array {
        global $DB;

        $this->setAdminUser();
        $scale = $this->getDataGenerator()->create_scale(['scale' => 'Emerging,Developing,Competent']);
        $DB->set_field('scale', 'description', '<p>Four-level skills scale.</p>', ['id' => $scale->id]);

        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework([
            'visible' => 1,
            'scaleid' => $scale->id,
            'scaleconfiguration' => json_encode([
                ['scaleid' => (int) $scale->id],
                ['id' => 3, 'scaledefault' => 0, 'proficient' => 1],
            ]),
        ]);
        $competency = $ccg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $competencyid = (int) $competency->get('id');

        $template = $ccg->create_template();
        $ccg->create_template_competency([
            'templateid' => (int) $template->get('id'),
            'competencyid' => $competencyid,
        ]);

        $user = $this->getDataGenerator()->create_user();
        $plan = $ccg->create_plan([
            'userid' => $user->id,
            'templateid' => (int) $template->get('id'),
            'status' => \core_competency\plan::STATUS_ACTIVE,
        ]);

        return [$competencyid, (int) $plan->get('id'), $user];
    }
}
