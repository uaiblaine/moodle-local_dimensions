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

use core_competency\api;
use core_competency\plan;
use core_external\external_api;

/**
 * Pins what amd/src/accordion.js relies on to log a competency view.
 *
 * The accordion picks core's view web service from the summary's plan.iscompleted and passes
 * plan.userid, so both fields are a contract with this wrapper's JSON, and the two method names it
 * sends are a contract with core's service registry. Nothing else in the pipeline reads either:
 * a renamed field or a mistyped method would only fail silently in the browser, where the failure
 * is deliberately swallowed.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\external\get_user_competency_summary_in_plan
 */
final class summary_view_logging_contract_test extends \advanced_testcase {
    /** @var string The web service the accordion calls for a plan that is not complete. */
    private const VIEWED_IN_PLAN = 'core_competency_user_competency_viewed_in_plan';

    /** @var string The web service the accordion calls for a completed plan. */
    private const PLAN_VIEWED = 'core_competency_user_competency_plan_viewed';

    /**
     * An active plan's summary says it is not complete and names its owner.
     *
     * @return void
     */
    public function test_active_plan_summary_carries_the_fields_the_view_log_needs(): void {
        $this->resetAfterTest();
        [$owner, $planid, $competencyid] = $this->create_plan();

        $this->setUser($owner);
        $payload = json_decode(get_user_competency_summary_in_plan::execute($competencyid, $planid));

        $this->assertFalse($payload->plan->iscompleted);
        $this->assertSame((int) $owner->id, (int) $payload->plan->userid);
    }

    /**
     * A completed plan's summary says it is complete and names its owner.
     *
     * @return void
     */
    public function test_completed_plan_summary_carries_the_fields_the_view_log_needs(): void {
        $this->resetAfterTest();
        [$owner, $planid, $competencyid] = $this->create_plan();
        $this->setAdminUser();
        api::complete_plan($planid);

        $this->setUser($owner);
        $payload = json_decode(get_user_competency_summary_in_plan::execute($competencyid, $planid));

        $this->assertTrue($payload->plan->iscompleted);
        $this->assertSame((int) $owner->id, (int) $payload->plan->userid);
    }

    /**
     * The accordion names exactly the two core web services, and both exist and accept AJAX calls.
     *
     * @return void
     */
    public function test_accordion_calls_existing_core_view_services(): void {
        $source = file_get_contents(__DIR__ . '/../../amd/src/accordion.js');
        preg_match_all("/'(core_competency_user_competency_[a-z_]*viewed[a-z_]*)'/", $source, $matches);
        $names = array_values(array_unique($matches[1]));
        sort($names);

        $this->assertSame([self::PLAN_VIEWED, self::VIEWED_IN_PLAN], $names);
        foreach ($names as $name) {
            $this->assertTrue((bool) external_api::external_function_info($name)->allowed_from_ajax, $name);
        }
    }

    /**
     * Dispatched with the arguments the accordion sends, each service logs its event for its plan status.
     *
     * @return void
     */
    public function test_view_services_log_with_the_arguments_the_accordion_sends(): void {
        $this->resetAfterTest();
        [$owner, $planid, $competencyid] = $this->create_plan();
        $this->setUser($owner);
        $_POST['sesskey'] = sesskey();
        $args = ['competencyid' => $competencyid, 'userid' => (int) $owner->id, 'planid' => $planid];

        $sink = $this->redirectEvents();
        $response = external_api::call_external_function(self::VIEWED_IN_PLAN, $args, true);
        $this->assertFalse($response['error'], json_encode($response));
        $this->assertCount(1, $this->events_of($sink, \core\event\competency_user_competency_viewed_in_plan::class));
        $sink->close();

        $this->setAdminUser();
        api::complete_plan($planid);
        $this->setUser($owner);
        $_POST['sesskey'] = sesskey();

        $sink = $this->redirectEvents();
        $response = external_api::call_external_function(self::PLAN_VIEWED, $args, true);
        $this->assertFalse($response['error'], json_encode($response));
        $this->assertCount(1, $this->events_of($sink, \core\event\competency_user_competency_plan_viewed::class));
        $sink->close();
    }

    /**
     * The captured events of one class.
     *
     * @param \phpunit_event_sink $sink The sink.
     * @param string $class The fully qualified event class name.
     * @return array
     */
    private function events_of(\phpunit_event_sink $sink, string $class): array {
        return array_values(array_filter($sink->get_events(), static function ($event) use ($class) {
            return $event instanceof $class;
        }));
    }

    /**
     * An active plan owned by a new user and holding one competency.
     *
     * @return array The owner, the plan id and the competency id.
     */
    private function create_plan(): array {
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework();
        $competency = $ccg->create_competency(['competencyframeworkid' => $framework->get('id')]);

        $owner = $this->getDataGenerator()->create_user();
        $plan = $ccg->create_plan(['userid' => $owner->id, 'status' => plan::STATUS_ACTIVE]);
        $ccg->create_plan_competency(['planid' => $plan->get('id'), 'competencyid' => $competency->get('id')]);

        return [$owner, (int) $plan->get('id'), (int) $competency->get('id')];
    }
}
