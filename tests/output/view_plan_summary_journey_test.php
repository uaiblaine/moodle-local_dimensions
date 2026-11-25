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
 * Tests for the plan overview's third view, Journey, as the page draws and offers it.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\output;

use core_competency\api;
use core_competency\plan;
use local_dimensions\constants;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Journey is offered beside list and grid only to a viewer who gets the competencies' detail, drawn hidden
 * until its module arms it, and a stored Journey paints the list for a viewer it is not offered to, the
 * stored choice kept for the pages that offer it.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(view_plan_summary_page::class)]
final class view_plan_summary_journey_test extends \advanced_testcase {
    /**
     * A draft plan of one competency for a new owner, with Journey stored as the reader's layout.
     *
     * @return \stdClass owner, planid.
     */
    private function create_plan(): \stdClass {
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework(['visible' => 1]);
        $owner = $this->getDataGenerator()->create_user();
        $plan = $ccg->create_plan(['userid' => $owner->id, 'status' => plan::STATUS_DRAFT]);
        $competency = $ccg->create_competency(['competencyframeworkid' => $framework->get('id'), 'shortname' => 'Alpha']);
        $ccg->create_plan_competency(['planid' => $plan->get('id'), 'competencyid' => $competency->get('id')]);

        return (object) ['owner' => $owner, 'planid' => (int) $plan->get('id')];
    }

    /**
     * The page's export and its rendering, read as the current user with Journey stored.
     *
     * @param int $planid The plan.
     * @return array [the export, the rendered page]
     */
    private function render(int $planid): array {
        global $PAGE;

        set_user_preference(constants::PREF_LEARNER_VIEW, json_encode(['view' => 'journey']));
        $renderer = $PAGE->get_renderer('core');
        $data = (new view_plan_summary_page(api::read_plan($planid)))->export_for_template($renderer);
        return [$data, $renderer->render_from_template('local_dimensions/view_plan_summary', $data)];
    }

    /**
     * The views of the line, keyed by value.
     *
     * @param array $data The page's export.
     * @return array
     */
    private static function views(array $data): array {
        return array_column($data['line']['views'], null, 'value');
    }

    /**
     * With the detail, the line offers list, grid and Journey, the third drawn hidden for its module, and the region is there.
     *
     * @return void
     */
    public function test_journey_is_offered_with_the_detail_and_drawn_hidden(): void {
        $this->resetAfterTest();
        $f = $this->create_plan();
        $this->setAdminUser();

        [$data, $html] = $this->render($f->planid);

        $views = self::views($data);
        $this->assertSame(['list', 'grid', 'journey'], array_keys($views));
        $this->assertTrue($views['journey']['current']);
        $this->assertTrue($views['journey']['hidden']);
        $this->assertFalse($views['list']['hidden']);
        $this->assertTrue($data['view_is_journey']);
        $this->assertSame('journey', $data['viewmode']);
        $this->assertSame('journey', json_decode($data['viewstatejson'], true)['view']);
        $this->assertSame(1, preg_match('~<button type="button" data-view="journey"[^>]* hidden>~', $html));
        $this->assertStringContainsString('data-region="local-dimensions-journey"', $html);
        $this->assertStringContainsString('data-planid="' . $f->planid . '"', $html);
    }

    /**
     * Without the detail Journey is not offered, the region is not drawn, and the stored Journey reads as the list.
     *
     * @return void
     */
    public function test_journey_is_withheld_without_the_detail(): void {
        $this->resetAfterTest();
        $f = $this->create_plan();
        $reader = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/competency:planviewdraft', CAP_ALLOW, $roleid, \context_system::instance()->id);
        role_assign($roleid, $reader->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($reader);

        [$data, $html] = $this->render($f->planid);

        $this->assertFalse($data['candetail']);
        $this->assertSame(['list', 'grid'], array_keys(self::views($data)));
        $this->assertTrue($data['view_is_list']);
        $this->assertFalse($data['view_is_journey']);
        // The stored choice is carried untouched, so a save on this page does not overwrite it with the list.
        $this->assertSame('journey', json_decode($data['viewstatejson'], true)['view']);
        $this->assertStringNotContainsString('data-view="journey"', $html);
        $this->assertStringNotContainsString('data-region="local-dimensions-journey"', $html);
    }
}
