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

namespace local_dimensions;

/**
 * Tests for the trail a plan card shows, and for invalidating it from another user's request.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\plan_trail_cache
 */
final class plan_trail_cache_test extends \advanced_testcase {
    /** @var \stdClass The plan owner. */
    protected $user;

    /** @var int Competency the plan carries. */
    protected $competencyid;

    /** @var int Manual plan id. */
    protected $planid;

    /** @var int Template id of the template-based plan. */
    protected $templateid;

    /** @var int Template-based plan id. */
    protected $templateplanid;

    /**
     * One learner, one competency, and two plans holding it: a manual one and a template one.
     *
     * The live rating says not proficient and the archived rating of both plans says proficient,
     * so every assertion below reads a value only one of the two tables can produce.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $competency = $generator->get_plugin_generator('core_competency');

        $this->user = $generator->create_user();
        $framework = $competency->create_framework();
        $this->competencyid = (int) $competency->create_competency([
            'competencyframeworkid' => $framework->get('id'),
        ])->get('id');

        $this->planid = (int) $competency->create_plan(['userid' => $this->user->id])->get('id');
        $competency->create_plan_competency([
            'planid' => $this->planid,
            'competencyid' => $this->competencyid,
        ]);

        $this->templateid = (int) $competency->create_template()->get('id');
        $competency->create_template_competency([
            'templateid' => $this->templateid,
            'competencyid' => $this->competencyid,
        ]);
        $this->templateplanid = (int) $competency->create_plan([
            'userid' => $this->user->id,
            'templateid' => $this->templateid,
        ])->get('id');

        /* Core refuses a live rating's proficiency without a grade, so every row carries one;
           the default generator scale is A,B,C,D, which makes 1 and 4 valid grades. */
        $competency->create_user_competency([
            'userid' => $this->user->id,
            'competencyid' => $this->competencyid,
            'grade' => 1,
            'proficiency' => 0,
        ]);
        foreach ([$this->planid, $this->templateplanid] as $planid) {
            $competency->create_user_competency_plan([
                'userid' => $this->user->id,
                'competencyid' => $this->competencyid,
                'planid' => $planid,
                'grade' => 4,
                'proficiency' => 1,
            ]);
        }
    }

    /**
     * Read the first competency's proficiency out of a trail payload.
     *
     * @param array $payload Payload from get_trail_data().
     * @return int
     */
    protected function proficiency(array $payload): int {
        $this->assertSame(1, $payload['total']);

        return (int) $payload['competencies'][0]['proficiency'];
    }

    /**
     * A manual plan reads the frozen archive when complete and the live rating when it is not.
     *
     * @return void
     */
    public function test_manual_plan_reads_the_archive_only_when_complete(): void {
        $live = plan_trail_cache::get_trail_data($this->planid, (int) $this->user->id, null);
        $complete = plan_trail_cache::get_trail_data($this->planid, (int) $this->user->id, null, true);

        $this->assertSame(0, $this->proficiency($live));
        $this->assertSame(1, $this->proficiency($complete));
    }

    /**
     * The template-based plan has its own query, a separate branch that must make the same switch.
     *
     * @return void
     */
    public function test_template_plan_reads_the_archive_only_when_complete(): void {
        $userid = (int) $this->user->id;
        $live = plan_trail_cache::get_trail_data($this->templateplanid, $userid, $this->templateid);
        $complete = plan_trail_cache::get_trail_data($this->templateplanid, $userid, $this->templateid, true);

        $this->assertSame(0, $this->proficiency($live));
        $this->assertSame(1, $this->proficiency($complete));
    }

    /**
     * The archive is read for this plan: another plan's archived row never leaks in.
     *
     * The learner holds the same competency in two plans, archived in both. Dropping this plan's
     * archive row must leave its trail without the competency rather than borrowing the other
     * plan's row.
     *
     * @return void
     */
    public function test_the_archive_is_scoped_to_the_plan_being_read(): void {
        global $DB;

        $DB->delete_records('competency_usercompplan', [
            'userid' => $this->user->id,
            'planid' => $this->planid,
        ]);
        plan_trail_cache::purge_all();

        $payload = plan_trail_cache::get_trail_data($this->planid, (int) $this->user->id, null, true);

        $this->assertSame(['total' => 0, 'competencies' => []], $payload);
        $other = plan_trail_cache::get_trail_data($this->templateplanid, (int) $this->user->id, $this->templateid, true);
        $this->assertSame(1, $this->proficiency($other));
    }

    /**
     * A completed plan lists the competencies it was completed with, as core's plan page does.
     *
     * plan::get_competencies() lists a completed plan from the archive, so a competency added to
     * the template afterwards is not part of it, and one removed from the template still is.
     *
     * @return void
     */
    public function test_a_completed_plan_lists_the_competencies_it_archived(): void {
        global $DB;

        $this->setAdminUser();
        $competency = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $competency->create_framework();
        $archived = (int) $competency->create_competency(['competencyframeworkid' => $framework->get('id')])->get('id');
        $added = (int) $competency->create_competency(['competencyframeworkid' => $framework->get('id')])->get('id');
        $template = (int) $competency->create_template()->get('id');
        $competency->create_template_competency(['templateid' => $template, 'competencyid' => $archived]);
        $userid = (int) $this->user->id;
        $plan = $competency->create_plan([
            'userid' => $userid,
            'templateid' => $template,
            'status' => \core_competency\plan::STATUS_ACTIVE,
        ]);
        \core_competency\api::complete_plan($plan);

        // After completion the template loses the archived competency and gains another.
        $DB->delete_records('competency_templatecomp', ['templateid' => $template, 'competencyid' => $archived]);
        $competency->create_template_competency(['templateid' => $template, 'competencyid' => $added]);
        $planid = (int) $plan->get('id');

        $complete = plan_trail_cache::get_trail_data($planid, $userid, $template, true);
        $live = plan_trail_cache::get_trail_data($planid, $userid, $template);

        $this->assertSame([$archived], array_column($complete['competencies'], 'id'));
        $this->assertSame(1, $complete['total']);
        $this->assertSame([$added], array_column($live['competencies'], 'id'));
    }

    /**
     * A completed manual plan is listed from the archive too, in the archive's order.
     *
     * @return void
     */
    public function test_a_completed_manual_plan_lists_the_archive_in_its_order(): void {
        global $DB;

        $competency = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $competency->create_framework();
        $second = (int) $competency->create_competency(['competencyframeworkid' => $framework->get('id')])->get('id');
        $userid = (int) $this->user->id;
        // Archived ahead of the setUp competency, and never linked to the plan itself.
        $competency->create_user_competency_plan([
            'userid' => $userid,
            'competencyid' => $second,
            'planid' => $this->planid,
            'sortorder' => 0,
        ]);
        $DB->set_field('competency_usercompplan', 'sortorder', 1, [
            'userid' => $userid,
            'planid' => $this->planid,
            'competencyid' => $this->competencyid,
        ]);

        $complete = plan_trail_cache::get_trail_data($this->planid, $userid, null, true);

        $this->assertSame([$second, $this->competencyid], array_column($complete['competencies'], 'id'));
    }

    /**
     * The definition asks for an application cache, which is what the tests below exercise.
     *
     * Definitions are read on install and upgrade, so the behaviour below is that of the
     * installed definition; this reads the declaration itself.
     *
     * @return void
     */
    public function test_the_plan_trail_definition_is_application_wide(): void {
        global $CFG;

        $definitions = [];
        include($CFG->dirroot . '/local/dimensions/db/caches.php');

        $this->assertSame(\cache_store::MODE_APPLICATION, $definitions['plan_trail']['mode']);
    }

    /**
     * The cache is shared between users, so one user's request can serve and clear another's entry.
     *
     * A session cache would purge the learner's entry the moment another user touched it, and
     * could never reach the learner's own session from a teacher's request. Here the teacher
     * reads the learner's cached trail, stale on purpose, which only a shared cache returns.
     *
     * @return void
     */
    public function test_the_trail_cache_is_shared_between_users(): void {
        global $DB;

        $learnerid = (int) $this->user->id;
        $this->setUser($this->user);
        $this->assertSame(0, $this->proficiency(plan_trail_cache::get_trail_data($this->planid, $learnerid, null)));

        // The rating moves underneath without any invalidation.
        $DB->set_field('competency_usercomp', 'proficiency', 1, ['userid' => $learnerid, 'competencyid' => $this->competencyid]);

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertSame(0, $this->proficiency(plan_trail_cache::get_trail_data($this->planid, $learnerid, null)));
    }

    /**
     * Invalidating a plan from another user's request reaches the learner's next read.
     *
     * @return void
     */
    public function test_another_user_invalidates_the_learners_plan(): void {
        global $DB;

        $learnerid = (int) $this->user->id;
        $this->setUser($this->user);
        $this->assertSame(0, $this->proficiency(plan_trail_cache::get_trail_data($this->planid, $learnerid, null)));

        $this->setUser($this->getDataGenerator()->create_user());
        $DB->set_field('competency_usercomp', 'proficiency', 1, ['userid' => $learnerid, 'competencyid' => $this->competencyid]);
        plan_trail_cache::invalidate_plan($this->planid, $learnerid);

        $this->setUser($this->user);
        $this->assertSame(1, $this->proficiency(plan_trail_cache::get_trail_data($this->planid, $learnerid, null)));
    }

    /**
     * Invalidating a user clears every plan of that user, and no other user's.
     *
     * @return void
     */
    public function test_invalidating_a_user_clears_all_of_that_users_plans_only(): void {
        global $DB;

        $learnerid = (int) $this->user->id;
        $competency = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $other = $this->getDataGenerator()->create_user();
        $otherplanid = (int) $competency->create_plan(['userid' => $other->id])->get('id');
        $competency->create_plan_competency(['planid' => $otherplanid, 'competencyid' => $this->competencyid]);
        $competency->create_user_competency([
            'userid' => $other->id,
            'competencyid' => $this->competencyid,
            'grade' => 1,
            'proficiency' => 0,
        ]);

        foreach ([[$this->planid, null], [$this->templateplanid, $this->templateid]] as [$planid, $templateid]) {
            $this->assertSame(0, $this->proficiency(plan_trail_cache::get_trail_data($planid, $learnerid, $templateid)));
        }
        $this->assertSame(0, $this->proficiency(plan_trail_cache::get_trail_data($otherplanid, (int) $other->id, null)));

        $DB->set_field('competency_usercomp', 'proficiency', 1, ['competencyid' => $this->competencyid]);
        $this->setUser($this->getDataGenerator()->create_user());
        plan_trail_cache::invalidate_user($learnerid);

        foreach ([[$this->planid, null], [$this->templateplanid, $this->templateid]] as [$planid, $templateid]) {
            $this->assertSame(1, $this->proficiency(plan_trail_cache::get_trail_data($planid, $learnerid, $templateid)));
        }
        // The other learner's entry was not invalidated, so it still serves the cached value.
        $this->assertSame(0, $this->proficiency(plan_trail_cache::get_trail_data($otherplanid, (int) $other->id, null)));
    }

    /**
     * The two readings of one plan are cached apart, and invalidation clears both.
     *
     * @return void
     */
    public function test_the_live_and_complete_readings_do_not_share_a_cache_entry(): void {
        global $DB;

        $userid = (int) $this->user->id;
        $this->assertSame(0, $this->proficiency(plan_trail_cache::get_trail_data($this->planid, $userid, null)));
        $this->assertSame(1, $this->proficiency(plan_trail_cache::get_trail_data($this->planid, $userid, null, true)));

        // Both readings are cached now; move both underlying tables and invalidate once.
        $DB->set_field('competency_usercomp', 'proficiency', 1, [
            'userid' => $userid,
            'competencyid' => $this->competencyid,
        ]);
        $DB->set_field('competency_usercompplan', 'proficiency', 0, [
            'userid' => $userid,
            'planid' => $this->planid,
        ]);
        plan_trail_cache::invalidate_plan($this->planid, $userid);

        $this->assertSame(1, $this->proficiency(plan_trail_cache::get_trail_data($this->planid, $userid, null)));
        $this->assertSame(0, $this->proficiency(plan_trail_cache::get_trail_data($this->planid, $userid, null, true)));
    }
}
