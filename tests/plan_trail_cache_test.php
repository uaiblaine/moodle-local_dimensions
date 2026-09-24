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
 * Tests for the trail a completed plan shows.
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
     * The archive is read for this plan: another plan's archived rating never leaks in.
     *
     * The learner holds the same competency in two plans, archived proficient in both. Dropping
     * this plan's archive row must leave the trail unrated rather than borrowing the other one.
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

        $this->assertSame(0, $this->proficiency($payload));
        $other = plan_trail_cache::get_trail_data($this->templateplanid, (int) $this->user->id, $this->templateid, true);
        $this->assertSame(1, $this->proficiency($other));
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
