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

namespace local_dimensions\local;

use core_competency\competency;
use core_competency\competency_rule_all;
use core_competency\plan;
use local_dimensions\constants;
use local_dimensions\customfield\lp_handler;
use local_dimensions\helper;

/**
 * Tests which failures of reading a plan the learner pages report as an invalid plan.
 *
 * Only a plan that cannot be found is invalid; every other failure surfaces as core's own error,
 * as admin/tool/lp/plan.php shows it. See {@see plan_access::read_plan()}.
 *
 * Also which competencies a plan reaches: its own, and the related competencies and rule children
 * the accordion links to outside it. See {@see plan_access::competency_scope()}. And that the tracker
 * page, which describes the plan owner, refuses a viewer who may not read the owner's user
 * competencies. See {@see plan_access::require_owner_readable()}.
 *
 * The covers tag stays in this docblock because moodle-cs for Moodle 4.5 cannot see PHPUnit
 * attributes.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\local\plan_access
 */
final class plan_access_test extends \advanced_testcase {
    /**
     * The owner reads their own active plan.
     *
     * @return void
     */
    public function test_owner_reads_an_active_plan(): void {
        $this->resetAfterTest();
        [$owner, $planid] = $this->create_plan(plan::STATUS_ACTIVE);
        $this->setUser($owner);

        $this->assertSame($planid, (int) plan_access::read_plan($planid)->get('id'));
    }

    /**
     * A plan id with no record is an invalid plan.
     *
     * @return void
     */
    public function test_missing_plan_is_invalid(): void {
        $this->resetAfterTest();
        [, $planid] = $this->create_plan(plan::STATUS_ACTIVE);
        $this->setAdminUser();

        $this->assert_invalid_plan($planid + 1000);
    }

    /**
     * Ids that can never name a plan are invalid too.
     *
     * core_competency\persistent loads nothing for an id of zero or less, and api::read_plan() then
     * fails looking up the context of user 0 - a dml_missing_record_exception about a user, not a
     * plan. This pins that it still reads as an invalid plan, so a change in core cannot quietly turn
     * it into a raw database error.
     *
     * @return void
     */
    public function test_ids_below_one_are_invalid(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assert_invalid_plan(0);
        $this->assert_invalid_plan(-1);
    }

    /**
     * The owner of a draft plan is refused by core, and is told so rather than told it does not exist.
     *
     * No default archetype holds moodle/competency:planviewowndraft, so this is what every learner on
     * an unmodified site meets with their own draft, waiting-for-review or in-review plan.
     *
     * @return void
     */
    public function test_owner_refused_a_draft_plan_gets_the_permission_error(): void {
        $this->resetAfterTest();
        [$owner, $planid] = $this->create_plan(plan::STATUS_DRAFT);
        $this->setUser($owner);

        $this->expectException(\required_capability_exception::class);
        plan_access::read_plan($planid);
    }

    /**
     * With competencies turned off the page says so, not that the plan is invalid.
     *
     * @return void
     */
    public function test_disabled_competencies_surface_as_core_error(): void {
        $this->resetAfterTest();
        [$owner, $planid] = $this->create_plan(plan::STATUS_ACTIVE);
        set_config('enabled', 0, 'core_competency');
        $this->setUser($owner);

        try {
            plan_access::read_plan($planid);
            $this->fail('Reading a plan with competencies disabled must throw.');
        } catch (\moodle_exception $e) {
            $this->assertSame('competenciesarenotenabled', $e->errorcode);
        }
    }

    /**
     * A competency of the plan is in scope, with no related-competency switch on.
     *
     * @return void
     */
    public function test_plan_competency_is_in_scope(): void {
        $this->resetAfterTest();
        $fixture = $this->create_scope_fixture();
        set_config('showrelated', 0, 'local_dimensions');
        set_config('showrelatedlink', 0, 'local_dimensions');

        $this->assertSame(plan_access::SCOPE_PLAN, plan_access::competency_scope($fixture['plan'], $fixture['inplan']));
        $this->assertSame(
            plan_access::SCOPE_PLAN,
            plan_access::require_competency_in_scope($fixture['plan'], $fixture['inplan'])
        );
    }

    /**
     * A completed plan reaches the competencies archived when it was completed, not its live rows.
     *
     * @return void
     */
    public function test_archived_competency_of_a_completed_plan_is_in_scope(): void {
        $this->resetAfterTest();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $owner = $this->getDataGenerator()->create_user();
        $frameworkid = (int) $ccg->create_framework()->get('id');
        $archived = (int) $ccg->create_competency(['competencyframeworkid' => $frameworkid])->get('id');
        $live = (int) $ccg->create_competency(['competencyframeworkid' => $frameworkid])->get('id');
        $planid = (int) $ccg->create_plan(['userid' => $owner->id, 'status' => plan::STATUS_COMPLETE])->get('id');
        $ccg->create_user_competency_plan(['userid' => $owner->id, 'competencyid' => $archived, 'planid' => $planid]);
        // The control: a live plan row, which a completed plan no longer reads.
        $ccg->create_plan_competency(['planid' => $planid, 'competencyid' => $live]);
        $this->setUser($owner);
        $plan = plan_access::read_plan($planid);

        $this->assertSame(plan_access::SCOPE_PLAN, plan_access::competency_scope($plan, $archived));
        $this->assertNull(plan_access::competency_scope($plan, $live));
    }

    /**
     * A competency related to one of the plan's is in scope when both switches are on.
     *
     * Core stores a relation with the lower id first, so the two fixtures cover a plan competency on
     * either side of the relation row.
     *
     * @return void
     */
    public function test_related_competency_is_in_scope_either_side_of_the_relation(): void {
        $this->resetAfterTest();
        $fixture = $this->create_scope_fixture();

        $this->assertSame(plan_access::SCOPE_RELATED, plan_access::competency_scope($fixture['plan'], $fixture['relatedbefore']));
        $this->assertSame(plan_access::SCOPE_RELATED, plan_access::competency_scope($fixture['plan'], $fixture['relatedafter']));
        $this->assertSame(
            plan_access::SCOPE_RELATED,
            plan_access::require_competency_in_scope($fixture['plan'], $fixture['relatedafter'])
        );
    }

    /**
     * A related competency is refused while showrelatedlink is off: the accordion renders no link.
     *
     * @return void
     */
    public function test_related_competency_needs_showrelatedlink(): void {
        $this->resetAfterTest();
        $fixture = $this->create_scope_fixture();
        // The control: in scope while the link is on.
        $this->assertSame(plan_access::SCOPE_RELATED, plan_access::competency_scope($fixture['plan'], $fixture['relatedbefore']));

        set_config('showrelatedlink', 0, 'local_dimensions');

        $this->assertNull(plan_access::competency_scope($fixture['plan'], $fixture['relatedbefore']));
    }

    /**
     * A related competency is refused while showrelated is off: the accordion renders no related section.
     *
     * @return void
     */
    public function test_related_competency_needs_showrelated(): void {
        $this->resetAfterTest();
        $fixture = $this->create_scope_fixture();
        // The control: in scope while the section is on.
        $this->assertSame(plan_access::SCOPE_RELATED, plan_access::competency_scope($fixture['plan'], $fixture['relatedbefore']));

        set_config('showrelated', 0, 'local_dimensions');

        $this->assertNull(plan_access::competency_scope($fixture['plan'], $fixture['relatedbefore']));
    }

    /**
     * Without competencyview in the framework's own context nothing outside the plan is reached.
     *
     * The framework sits in a course category and the learner keeps competencyview at the site, so a
     * check made anywhere but the competency's own context would still let both links through. The
     * plan's own competency stays in scope, as on core's user_competency_in_plan.php.
     *
     * @return void
     */
    public function test_reach_outside_the_plan_needs_competencyview_in_the_framework_context(): void {
        global $CFG;

        $this->resetAfterTest();
        $category = $this->getDataGenerator()->create_category();
        $categorycontext = \context_coursecat::instance($category->id);
        $fixture = $this->create_scope_fixture($categorycontext);
        // The control: both links reach their competency before the capability is withdrawn.
        $this->assertSame(plan_access::SCOPE_RELATED, plan_access::competency_scope($fixture['plan'], $fixture['relatedbefore']));
        $this->assertSame(plan_access::SCOPE_RULECHILD, plan_access::competency_scope($fixture['plan'], $fixture['child']));

        assign_capability(
            'moodle/competency:competencyview',
            CAP_PROHIBIT,
            (int) $CFG->defaultuserroleid,
            $categorycontext->id,
            true
        );
        $this->assertTrue(has_capability('moodle/competency:competencyview', \context_system::instance()));

        $this->assertNull(plan_access::competency_scope($fixture['plan'], $fixture['relatedbefore']));
        $this->assertNull(plan_access::competency_scope($fixture['plan'], $fixture['child']));
        $this->assertSame(plan_access::SCOPE_PLAN, plan_access::competency_scope($fixture['plan'], $fixture['inplan']));
    }

    /**
     * A competency related only to one outside the plan is refused.
     *
     * @return void
     */
    public function test_competency_related_only_outside_the_plan_is_refused(): void {
        $this->resetAfterTest();
        $fixture = $this->create_scope_fixture();
        // The control: the switches and the relation lookup let a real relative through.
        $this->assertSame(plan_access::SCOPE_RELATED, plan_access::competency_scope($fixture['plan'], $fixture['relatedbefore']));

        $this->assertNull(plan_access::competency_scope($fixture['plan'], $fixture['relatedoutside']));
    }

    /**
     * A competency of the same framework with no link to the plan is refused.
     *
     * @return void
     */
    public function test_unrelated_competency_is_refused(): void {
        $this->resetAfterTest();
        $fixture = $this->create_scope_fixture();

        $this->assertNull(plan_access::competency_scope($fixture['plan'], $fixture['unrelated']));
    }

    /**
     * A missing competency is refused exactly as an unreachable one is, so a refusal says nothing about existence.
     *
     * @return void
     */
    public function test_missing_competency_is_refused_like_an_unreachable_one(): void {
        global $DB;

        $this->resetAfterTest();
        $fixture = $this->create_scope_fixture();
        $missingid = (int) $DB->get_field_sql('SELECT MAX(id) FROM {competency}') + 1000;

        $messages = [];
        foreach ([$missingid, 0, -1, $fixture['unrelated']] as $competencyid) {
            $this->assertNull(plan_access::competency_scope($fixture['plan'], $competencyid), 'Competency id ' . $competencyid);
            try {
                plan_access::require_competency_in_scope($fixture['plan'], $competencyid);
                $this->fail('Competency id ' . $competencyid . ' must be refused.');
            } catch (\moodle_exception $e) {
                $this->assertSame('competency_id_missing', $e->errorcode, 'Competency id ' . $competencyid);
                $this->assertSame('local_dimensions', $e->module, 'Competency id ' . $competencyid);
                $messages[] = $e->getMessage();
            }
        }
        $this->assertCount(1, array_unique($messages));
    }

    /**
     * The plan's template can switch the link on while the site setting is off.
     *
     * @return void
     */
    public function test_template_override_turns_the_related_link_on(): void {
        $this->resetAfterTest();
        $fixture = $this->create_template_plan(true, false);
        $this->assertSame(plan_access::SCOPE_PLAN, plan_access::competency_scope($fixture['plan'], $fixture['inplan']));
        $this->assertNull(plan_access::competency_scope($fixture['plan'], $fixture['related']));

        $this->set_template_toggle(
            $fixture['templateid'],
            constants::CFIELD_SHOWRELATEDLINK,
            constants::showrelatedlink_options(),
            constants::SHOWRELATED_YES
        );

        $this->setUser($fixture['ownerid']);
        $this->assertSame(plan_access::SCOPE_RELATED, plan_access::competency_scope($fixture['plan'], $fixture['related']));
    }

    /**
     * The plan's template can switch the related section off while both site settings are on.
     *
     * The accordion then renders no related competency to link, so the link setting alone reaches nothing.
     *
     * @return void
     */
    public function test_template_override_turns_the_related_section_off(): void {
        $this->resetAfterTest();
        $fixture = $this->create_template_plan(true, true);
        // The control: in scope while the template inherits the site settings.
        $this->assertSame(plan_access::SCOPE_RELATED, plan_access::competency_scope($fixture['plan'], $fixture['related']));

        $this->set_template_toggle(
            $fixture['templateid'],
            constants::CFIELD_SHOWRELATED,
            constants::showrelated_options(),
            constants::SHOWRELATED_NO
        );

        $this->setUser($fixture['ownerid']);
        $this->assertNull(plan_access::competency_scope($fixture['plan'], $fixture['related']));
        $this->assertSame(plan_access::SCOPE_PLAN, plan_access::competency_scope($fixture['plan'], $fixture['inplan']));
    }

    /**
     * A direct child counted by a plan competency's rule is in scope, with the related switches off.
     *
     * The rule needs both an outcome and a type, as the accordion's Rules tab does; a grandchild is
     * never listed there.
     *
     * @return void
     */
    public function test_rule_child_is_in_scope(): void {
        $this->resetAfterTest();
        $fixture = $this->create_scope_fixture();
        set_config('showrelated', 0, 'local_dimensions');
        set_config('showrelatedlink', 0, 'local_dimensions');

        $this->assertSame(plan_access::SCOPE_RULECHILD, plan_access::competency_scope($fixture['plan'], $fixture['child']));
        $this->assertNull(plan_access::competency_scope($fixture['plan'], $fixture['grandchild']));

        $parent = new competency($fixture['inplan']);
        $parent->set('ruleoutcome', competency::OUTCOME_NONE);
        $parent->update();
        $this->assertNull(plan_access::competency_scope($fixture['plan'], $fixture['child']), 'No outcome');

        $parent->set('ruleoutcome', competency::OUTCOME_EVIDENCE);
        $parent->set('ruletype', null);
        $parent->update();
        $this->assertNull(plan_access::competency_scope($fixture['plan'], $fixture['child']), 'No rule type');
    }

    /**
     * A child counted by the rule of a competency outside the plan is refused.
     *
     * Only the plan's own competencies link to their rule's children. The control is the plan
     * competency's child, reached through the same kind of rule.
     *
     * @return void
     */
    public function test_rule_child_of_a_competency_outside_the_plan_is_refused(): void {
        $this->resetAfterTest();
        $fixture = $this->create_scope_fixture();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $frameworkid = (int) (new competency($fixture['inplan']))->get('competencyframeworkid');
        $outside = (int) $ccg->create_competency([
            'competencyframeworkid' => $frameworkid,
            'ruletype' => competency_rule_all::class,
            'ruleoutcome' => competency::OUTCOME_EVIDENCE,
        ])->get('id');
        $outsidechild = (int) $ccg->create_competency([
            'competencyframeworkid' => $frameworkid,
            'parentid' => $outside,
        ])->get('id');
        $this->assertSame(plan_access::SCOPE_RULECHILD, plan_access::competency_scope($fixture['plan'], $fixture['child']));

        $this->assertNull(plan_access::competency_scope($fixture['plan'], $outsidechild));
    }

    /**
     * The tracker page shows a competency only when the plan reaches it, and logs only the plan's own.
     *
     * The tests above pin the predicate; this one pins that view-competency.php obeys it, which no
     * other test sees. Its two controls are a plan competency and a related one, so the page cannot
     * pass by refusing everything or by reading the plan alone. Only the plan's own competency logs a
     * view: api::get_plan_competency() throws for any other.
     *
     * @return void
     */
    public function test_tracker_page_renders_only_a_competency_the_plan_reaches(): void {
        global $DB;

        $this->resetAfterTest();
        $fixture = $this->create_scope_fixture();
        $planid = (int) $fixture['plan']->get('id');
        foreach (['inplan' => 'Owned', 'relatedbefore' => 'Related', 'unrelated' => 'Unreachable'] as $key => $name) {
            $DB->update_record('competency', (object) [
                'id' => $fixture[$key],
                'shortname' => $name . ' shortname',
                'description' => $name . ' description',
            ]);
        }
        $notfound = get_string('competency_id_missing', 'local_dimensions');

        foreach (['inplan' => ['Owned', 1], 'relatedbefore' => ['Related', 0]] as $key => [$name, $views]) {
            $sink = $this->redirectEvents();
            $html = $this->render_tracker_page($planid, $fixture[$key]);
            $events = array_filter(
                $sink->get_events(),
                static fn(\core\event\base $event): bool => $event instanceof \core\event\competency_user_competency_viewed_in_plan
            );
            $sink->close();
            $this->assertStringContainsString($name . ' shortname', $html, $key);
            $this->assertStringContainsString($name . ' description', $html, $key);
            $this->assertStringNotContainsString($notfound, $html, $key);
            $this->assertCount($views, $events, $key);
        }

        $html = $this->render_tracker_page($planid, $fixture['unrelated']);
        $this->assertStringContainsString($notfound, $html);
        $this->assertStringNotContainsString('Unreachable shortname', $html);
        $this->assertStringNotContainsString('Unreachable description', $html);
    }

    /**
     * The tracker page refuses a viewer who reads a draft plan but not its owner's user competencies.
     *
     * The page lists the courses the plan owner is enrolled in, so it asks what its card services and
     * core's own competency-in-plan page ask (plan_access::require_owner_readable()). A role holding
     * planviewdraft alone reads the draft plan and is refused; the control grants the same role
     * usercompetencyview, and the same page lists the owner's course.
     *
     * @return void
     */
    public function test_tracker_page_refuses_a_draft_reader_who_cannot_read_the_owner(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enrollmentfilter', constants::ENROLLMENTFILTER_ACTIVE, 'local_dimensions');
        $dg = $this->getDataGenerator();
        $ccg = $dg->get_plugin_generator('core_competency');
        $frameworkid = (int) $ccg->create_framework()->get('id');
        $competencyid = (int) $ccg->create_competency([
            'competencyframeworkid' => $frameworkid,
            'shortname' => 'Drafted shortname',
        ])->get('id');
        [$owner, $planid] = $this->create_plan(plan::STATUS_DRAFT);
        $ccg->create_plan_competency(['planid' => $planid, 'competencyid' => $competencyid]);
        $courseid = (int) $dg->create_course()->id;
        \core_competency\api::add_competency_to_course($courseid, $competencyid);
        $dg->enrol_user((int) $owner->id, $courseid, 'student');

        $viewer = $dg->create_user();
        $roleid = $dg->create_role();
        $syscontextid = \context_system::instance()->id;
        assign_capability('moodle/competency:planviewdraft', CAP_ALLOW, $roleid, $syscontextid);
        role_assign($roleid, (int) $viewer->id, $syscontextid);
        $this->setUser($viewer);
        // The precondition: core lets this viewer read the draft plan.
        $plan = plan_access::read_plan($planid);
        $refusal = get_capability_string('moodle/competency:usercompetencyview');

        try {
            plan_access::require_owner_readable($plan);
            $this->fail('The owner check must refuse a viewer who cannot read the owner.');
        } catch (\required_capability_exception $e) {
            $this->assertSame($refusal, $e->a);
        }
        try {
            $this->render_tracker_page($planid, $competencyid);
            $this->fail('The tracker page must refuse a viewer who cannot read the owner.');
        } catch (\required_capability_exception $e) {
            $this->assertSame($refusal, $e->a);
        }

        assign_capability('moodle/competency:usercompetencyview', CAP_ALLOW, $roleid, $syscontextid);

        $this->assertSame((int) $owner->id, plan_access::require_owner_readable($plan));
        $html = $this->render_tracker_page($planid, $competencyid);
        $this->assertStringContainsString('Drafted shortname', $html);
        $this->assertStringContainsString('data-courseid="' . $courseid . '"', $html);
    }

    /**
     * Run view-competency.php as a request by the current user would, and return what it printed.
     *
     * The page finds config.php relative to the working directory, which PHPUnit has already loaded,
     * so the require resolves to a file already included and does nothing. Each run gets a fresh page
     * and renderer, as a request does.
     *
     * @param int $planid The plan id parameter.
     * @param int $competencyid The competency id parameter.
     * @return string The page's HTML.
     */
    private function render_tracker_page(int $planid, int $competencyid): string {
        // The page runs in this method's scope and reads these as its globals.
        global $CFG, $DB, $OUTPUT, $PAGE, $USER;

        $PAGE = new \moodle_page();
        $OUTPUT = new \bootstrap_renderer();
        $_GET = ['id' => $planid, 'competencyid' => $competencyid];
        $cwd = getcwd();
        chdir($CFG->dirroot . '/local/dimensions');
        ob_start();
        try {
            require($CFG->dirroot . '/local/dimensions/view-competency.php');
        } finally {
            $html = ob_get_clean();
            chdir($cwd);
            $_GET = [];
        }

        return $html;
    }

    /**
     * Assert that reading a plan id fails as the plugin's invalid plan error.
     *
     * @param int $planid The plan id to read.
     * @return void
     */
    private function assert_invalid_plan(int $planid): void {
        try {
            plan_access::read_plan($planid);
            $this->fail('Plan id ' . $planid . ' must not read.');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidplan', $e->errorcode, 'Plan id ' . $planid);
            $this->assertSame('local_dimensions', $e->module, 'Plan id ' . $planid);
        }
    }

    /**
     * A plan owned by a new user.
     *
     * @param int $status The plan status.
     * @return array The owner and the plan id.
     */
    private function create_plan(int $status): array {
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $owner = $this->getDataGenerator()->create_user();
        $plan = $ccg->create_plan(['userid' => $owner->id, 'status' => $status]);

        return [$owner, (int) $plan->get('id')];
    }

    /**
     * A learner's active plan from a template over one competency, and a competency related to it.
     *
     * The template's related-competency fields exist and inherit the site settings. The learner is the
     * current user on return.
     *
     * @param bool $showrelated The site's showrelated setting.
     * @param bool $showrelatedlink The site's showrelatedlink setting.
     * @return array Keys: ownerid, plan (read as the learner), templateid, inplan and related.
     */
    private function create_template_plan(bool $showrelated, bool $showrelatedlink): array {
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_LP);
        set_config('showrelated', (int) $showrelated, 'local_dimensions');
        set_config('showrelatedlink', (int) $showrelatedlink, 'local_dimensions');

        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $frameworkid = (int) $ccg->create_framework()->get('id');
        $inplan = (int) $ccg->create_competency(['competencyframeworkid' => $frameworkid])->get('id');
        $related = (int) $ccg->create_competency(['competencyframeworkid' => $frameworkid])->get('id');
        $ccg->create_related_competency(['competencyid' => $inplan, 'relatedcompetencyid' => $related]);
        $templateid = (int) $ccg->create_template()->get('id');
        $ccg->create_template_competency(['templateid' => $templateid, 'competencyid' => $inplan]);
        $ownerid = (int) $this->getDataGenerator()->create_user()->id;
        $planid = (int) $ccg->create_plan([
            'userid' => $ownerid,
            'templateid' => $templateid,
            'status' => plan::STATUS_ACTIVE,
        ])->get('id');
        $this->setUser($ownerid);

        return [
            'ownerid' => $ownerid,
            'plan' => plan_access::read_plan($planid),
            'templateid' => $templateid,
            'inplan' => $inplan,
            'related' => $related,
        ];
    }

    /**
     * Set one of a template's related-competency fields, leaving the administrator as the current user.
     *
     * Saved as an administrator: instance_form_save() drops fields the current user cannot edit.
     *
     * @param int $templateid The template id.
     * @param string $shortname The field's shortname, one of the constants::CFIELD_SHOWRELATED* names.
     * @param array $options The field's options, keyed by the SHOWRELATED_* constants in stored order.
     * @param string $value One of the SHOWRELATED_* constants.
     * @return void
     */
    private function set_template_toggle(int $templateid, string $shortname, array $options, string $value): void {
        $this->setAdminUser();
        lp_handler::create()->instance_form_save((object) [
            'id' => $templateid,
            'customfield_' . $shortname => array_search($value, array_keys($options), true) + 1,
        ], true);
    }

    /**
     * A learner's active plan over one competency with a rule, and competencies around it.
     *
     * Every competency sits in one framework, the only kind of relation core allows. The learner is
     * the current user on return, and both related-competency switches are on at the site.
     *
     * @param \context|null $context The framework's context, the system when null.
     * @return array Keys: plan (read as the learner), inplan (in the plan, with a rule), relatedbefore
     *     and relatedafter (related to it, created before and after it), child (a direct child),
     *     grandchild, relatedoutside (related only to a competency outside the plan) and unrelated.
     */
    private function create_scope_fixture(?\context $context = null): array {
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework(['contextid' => ($context ?? \context_system::instance())->id]);
        $frameworkid = (int) $framework->get('id');
        $create = static function (array $record = []) use ($ccg, $frameworkid): int {
            return (int) $ccg->create_competency(['competencyframeworkid' => $frameworkid] + $record)->get('id');
        };

        $relatedbefore = $create();
        $inplan = $create([
            'ruletype' => competency_rule_all::class,
            'ruleoutcome' => competency::OUTCOME_EVIDENCE,
        ]);
        $relatedafter = $create();
        $child = $create(['parentid' => $inplan]);
        $grandchild = $create(['parentid' => $child]);
        $outside = $create();
        $relatedoutside = $create();
        $unrelated = $create();
        $ccg->create_related_competency(['competencyid' => $relatedbefore, 'relatedcompetencyid' => $inplan]);
        $ccg->create_related_competency(['competencyid' => $inplan, 'relatedcompetencyid' => $relatedafter]);
        $ccg->create_related_competency(['competencyid' => $outside, 'relatedcompetencyid' => $relatedoutside]);

        [$owner, $planid] = $this->create_plan(plan::STATUS_ACTIVE);
        $ccg->create_plan_competency(['planid' => $planid, 'competencyid' => $inplan]);
        set_config('showrelated', 1, 'local_dimensions');
        set_config('showrelatedlink', 1, 'local_dimensions');
        $this->setUser($owner);

        return [
            'plan' => plan_access::read_plan($planid),
            'inplan' => $inplan,
            'relatedbefore' => $relatedbefore,
            'relatedafter' => $relatedafter,
            'child' => $child,
            'grandchild' => $grandchild,
            'relatedoutside' => $relatedoutside,
            'unrelated' => $unrelated,
        ];
    }
}
