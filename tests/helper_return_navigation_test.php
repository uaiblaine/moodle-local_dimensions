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
 * Tests for the learner return-navigation helpers.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions;

use advanced_testcase;
use moodle_url;
use local_dimensions\customfield\lp_handler;

/**
 * Tests for the return-context classification and the tracker's return button.
 *
 * @covers \local_dimensions\helper::return_destination_kind
 * @covers \local_dimensions\helper::tracker_return_context
 * @covers \local_dimensions\helper::plan_overview_is_routed
 * @covers \local_dimensions\helper::redirect_return_url
 * @covers \local_dimensions\helper::set_return_context
 * @covers \local_dimensions\helper::get_return_context_for_course
 */
final class helper_return_navigation_test extends advanced_testcase {
    /**
     * A stored plan URL classifies as the plan.
     *
     * @return void
     */
    public function test_return_destination_kind_classifies_plan_url(): void {
        $url = (new moodle_url('/local/dimensions/view-plan.php', ['id' => 7]))->out(false);

        $this->assertSame('plan', helper::return_destination_kind($url));
    }

    /**
     * A stored tracker URL classifies as the competency, with or without the anti-loop flag.
     *
     * @return void
     */
    public function test_return_destination_kind_classifies_tracker_url(): void {
        $plain = (new moodle_url('/local/dimensions/view-competency.php', [
            'id' => 7,
            'competencyid' => 3,
        ]))->out(false);
        $flagged = (new moodle_url('/local/dimensions/view-competency.php', [
            'id' => 7,
            'competencyid' => 3,
            'noredirect' => 1,
        ]))->out(false);

        $this->assertSame('competency', helper::return_destination_kind($plain));
        $this->assertSame('competency', helper::return_destination_kind($flagged));
    }

    /**
     * Anything unrecognised falls back to the plan, the root of the journey.
     *
     * @return void
     */
    public function test_return_destination_kind_defaults_to_plan(): void {
        $this->assertSame('plan', helper::return_destination_kind('https://example.invalid/course/view.php?id=2'));
        $this->assertSame('plan', helper::return_destination_kind(''));
    }

    /**
     * The tracker's button points at the plan it was opened from.
     *
     * @return void
     */
    public function test_tracker_return_context_points_at_the_plan(): void {
        $this->resetAfterTest();
        set_config('enablereturnbutton', 1, 'local_dimensions');
        set_config('returnbuttoncolor', '#ff0000', 'local_dimensions');

        $context = helper::tracker_return_context(42, 0, false);

        $this->assertNotNull($context);
        $expected = (new moodle_url('/local/dimensions/view-plan.php', ['id' => 42]))->out(false);
        $this->assertSame($expected, $context['returnurl']);
        $this->assertSame(get_string('returntoplan', 'local_dimensions'), $context['label']);
        $this->assertSame('#ff0000', $context['buttoncolor']);
    }

    /**
     * A tracker opened by a related-competency pill gets no button: it is a new tab.
     *
     * @return void
     */
    public function test_tracker_return_context_suppressed_when_related(): void {
        $this->resetAfterTest();
        set_config('enablereturnbutton', 1, 'local_dimensions');

        $this->assertNull(helper::tracker_return_context(42, 0, true));
    }

    /**
     * The tracker's button honours the same feature switch as the course FAB.
     *
     * @return void
     */
    public function test_tracker_return_context_suppressed_when_feature_disabled(): void {
        $this->resetAfterTest();
        set_config('enablereturnbutton', 0, 'local_dimensions');

        $this->assertNull(helper::tracker_return_context(42, 0, false));
    }

    /**
     * A write fans the same URL out to one cache entry per course.
     *
     * @return void
     */
    public function test_set_return_context_writes_one_entry_per_course(): void {
        $this->resetAfterTest();
        $url = new moodle_url('/local/dimensions/view-plan.php', ['id' => 9]);

        helper::set_return_context($url, [11, 12]);

        $expected = $url->out(false);
        $this->assertSame($expected, helper::get_return_context_for_course(11)['url']);
        $this->assertSame($expected, helper::get_return_context_for_course(12)['url']);
    }

    /**
     * A write with no courses is a silent no-op: it never clears what is stored.
     *
     * @return void
     */
    public function test_set_return_context_with_no_courses_writes_nothing(): void {
        $this->resetAfterTest();
        helper::set_return_context(new moodle_url('/local/dimensions/view-plan.php', ['id' => 9]), [13]);

        helper::set_return_context(new moodle_url('/local/dimensions/view-competency.php', [
            'id' => 9,
            'competencyid' => 4,
        ]), []);

        $this->assertStringContainsString('view-plan.php', helper::get_return_context_for_course(13)['url']);
    }

    /**
     * A course with no stored context reads back as null.
     *
     * @return void
     */
    public function test_get_return_context_for_course_returns_null_when_absent(): void {
        $this->resetAfterTest();

        $this->assertNull(helper::get_return_context_for_course(987654));
    }

    /**
     * The cache is last-writer-wins: a tracker write for a course already holding
     * a plan write overrides it, and the classifier reads the newer destination.
     *
     * @return void
     */
    public function test_set_return_context_last_writer_wins_for_a_shared_course(): void {
        $this->resetAfterTest();
        $courseid = 21;

        helper::set_return_context(new moodle_url('/local/dimensions/view-plan.php', ['id' => 17]), [$courseid]);
        helper::set_return_context(new moodle_url('/local/dimensions/view-competency.php', [
            'id' => 17,
            'competencyid' => 8,
            'noredirect' => 1,
        ]), [$courseid]);

        $context = helper::get_return_context_for_course($courseid);

        $this->assertSame('competency', helper::return_destination_kind($context['url']));
    }

    /**
     * A plan with no template is routed to the plan overview, matching the block's default.
     *
     * @return void
     */
    public function test_plan_overview_is_routed_without_a_template(): void {
        $this->assertTrue(helper::plan_overview_is_routed(0));
    }

    /**
     * A template that routes learners to competency cards suppresses the tracker's button.
     *
     * @return void
     */
    public function test_tracker_return_context_suppressed_in_competency_card_mode(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enablereturnbutton', 1, 'local_dimensions');

        $templateid = $this->create_template_with_displaymode(constants::DISPLAYMODE_COMPETENCIES);

        $this->assertFalse(helper::plan_overview_is_routed($templateid));
        $this->assertNull(helper::tracker_return_context(42, $templateid, false));
    }

    /**
     * A template that routes learners to the plan overview keeps the tracker's button.
     *
     * @return void
     */
    public function test_tracker_return_context_shown_in_plan_mode(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enablereturnbutton', 1, 'local_dimensions');

        $templateid = $this->create_template_with_displaymode(constants::DISPLAYMODE_PLAN);

        $this->assertTrue(helper::plan_overview_is_routed($templateid));
        $this->assertNotNull(helper::tracker_return_context(42, $templateid, false));
    }

    /**
     * A plan whose learners are routed to the overview keeps the redirect pointing there.
     *
     * @return void
     */
    public function test_redirect_return_url_points_at_the_plan_when_routed(): void {
        $url = helper::redirect_return_url(42, 7, 0);

        $this->assertStringContainsString('/local/dimensions/view-plan.php', $url->out(false));
        $this->assertSame('plan', helper::return_destination_kind($url->out(false)));
    }

    /**
     * In competency-card mode the redirect points back at the tracker, carrying noredirect.
     *
     * @return void
     */
    public function test_redirect_return_url_points_at_the_tracker_in_competency_card_mode(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $templateid = $this->create_template_with_displaymode(constants::DISPLAYMODE_COMPETENCIES);
        $url = helper::redirect_return_url(42, 7, $templateid)->out(false);

        $this->assertStringContainsString('/local/dimensions/view-competency.php', $url);
        $this->assertStringContainsString('noredirect=1', $url);
        $this->assertSame('competency', helper::return_destination_kind($url));
    }

    /**
     * Create a learning plan template with its display mode custom field set to $displaymode.
     *
     * Provisions the plugin's custom fields, creates a bare template via the core_competency
     * generator, then writes the display mode through the lp custom-field handler — the same
     * save path the template edit form uses (see tests/customfield/lp_handler_test.php) — so the
     * value is stored the way `template_metadata_cache` actually reads it back. The cache is
     * invalidated afterwards so the write is visible immediately instead of leaving a stale
     * (or missing) cached entry from before the field existed.
     *
     * @param int $displaymode One of constants::DISPLAYMODE_COMPETENCIES or DISPLAYMODE_PLAN.
     * @return int The new template's id.
     */
    private function create_template_with_displaymode(int $displaymode): int {
        helper::ensure_all_fields();

        $field = helper::find_field_by_shortname(constants::CFIELD_DISPLAYMODE, helper::AREA_LP);
        $this->assertNotNull($field);

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $template = $generator->create_template();
        $templateid = (int) $template->get('id');

        $formdata = (object) (['id' => $templateid] + helper::template_customfields_to_formdata([
            'cf_displaymode' => (string) $displaymode,
        ]));
        lp_handler::create()->instance_form_save($formdata, true);

        template_metadata_cache::invalidate_template($templateid);

        return $templateid;
    }
}
