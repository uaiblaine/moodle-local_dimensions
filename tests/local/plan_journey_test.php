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
 * Tests for the plan overview's Journey view: who reads it and which records it holds.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\local;

use core_competency\api;
use core_competency\evidence;
use core_competency\plan;
use core_competency\user_competency;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The Journey's read: the plan read through core, the detail rule of the overview, the plan's own
 * competencies only, keyset paging, the fold of other components' logs and a constant query count.
 *
 * Every refusal sits beside a control that reads the same plan and gets its records, so a refusal
 * that is merely an empty answer cannot pass.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(plan_journey::class)]
final class plan_journey_test extends \advanced_testcase {
    /** @var int 14 October 2026, 12:00 UTC. */
    private const int OCTOBER = 1791979200;

    /** @var int 14 September 2026, 12:00 UTC. */
    private const int SEPTEMBER = 1789387200;

    /**
     * The fixture's clock reads UTC, so a record's month does not move with the test site's zone.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setTimezone('UTC');
        set_config('enabled', 1, 'core_competency');
    }

    /**
     * A plan of two competencies (Alpha, Bravo) for a new owner, and a third competency outside it.
     *
     * @param int $status The plan status.
     * @return \stdClass owner, planid, alpha, bravo, outside (competency ids), framework.
     */
    private function create_plan(int $status = plan::STATUS_ACTIVE): \stdClass {
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework(['visible' => 1]);
        $owner = $this->getDataGenerator()->create_user(['firstname' => 'Olga', 'lastname' => 'Owner']);
        $plan = $ccg->create_plan(['userid' => $owner->id, 'status' => $status]);

        $ids = [];
        foreach (['Alpha competency', 'Bravo competency', 'Outside competency'] as $shortname) {
            $ids[] = (int) $ccg->create_competency([
                'competencyframeworkid' => $framework->get('id'),
                'shortname' => $shortname,
            ])->get('id');
        }
        $ccg->create_plan_competency(['planid' => $plan->get('id'), 'competencyid' => $ids[0]]);
        $ccg->create_plan_competency(['planid' => $plan->get('id'), 'competencyid' => $ids[1]]);

        return (object) [
            'owner' => $owner,
            'planid' => (int) $plan->get('id'),
            'alpha' => $ids[0],
            'bravo' => $ids[1],
            'outside' => $ids[2],
        ];
    }

    /**
     * Record one evidence on a user's competency at a given time.
     *
     * @param int $userid The learner.
     * @param int $competencyid The competency.
     * @param int $time Its timecreated.
     * @param array $fields Evidence fields over a core log of the rule.
     * @return int The evidence id.
     */
    private function evidence(int $userid, int $competencyid, int $time, array $fields = []): int {
        global $DB;

        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $uc = user_competency::get_record(['userid' => $userid, 'competencyid' => $competencyid]);
        if (!$uc) {
            $uc = $ccg->create_user_competency(['userid' => $userid, 'competencyid' => $competencyid]);
        }
        $record = $fields + [
            'usercompetencyid' => $uc->get('id'),
            'action' => evidence::ACTION_LOG,
            'descidentifier' => 'evidence_competencyrule',
            'desccomponent' => 'core_competency',
        ];
        $id = (int) $ccg->create_evidence($record)->get('id');
        $DB->set_field('competency_evidence', 'timecreated', $time, ['id' => $id]);

        return $id;
    }

    /**
     * The ids of a page's records, in order.
     *
     * @param array $page A page of plan_journey::page().
     * @return array
     */
    private static function ids(array $page): array {
        return array_map(static fn(array $entry): int => $entry['id'], $page['entries']);
    }

    /**
     * A user whose one competency capability is at the system context.
     *
     * @param string $capability The capability.
     * @return \stdClass The user.
     */
    private function create_holder(string $capability): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        $syscontext = \context_system::instance();
        assign_capability($capability, CAP_ALLOW, $roleid, $syscontext->id);
        role_assign($roleid, $user->id, $syscontext->id);
        accesslib_clear_all_caches_for_unit_testing();

        return $user;
    }

    /**
     * A user who may not read the plan is refused; its owner, the control, reads its records.
     *
     * @return void
     */
    public function test_a_viewer_who_may_not_read_the_plan_is_refused(): void {
        $f = $this->create_plan();
        $id = $this->evidence((int) $f->owner->id, $f->alpha, self::OCTOBER);

        $this->setUser($f->owner);
        $this->assertSame([$id], self::ids(plan_journey::page($f->planid, 0, 0)));

        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\required_capability_exception::class);
        plan_journey::page($f->planid, 0, 0);
    }

    /**
     * The plan is read through core first: a viewer who may read the owner's competencies but not the plan is refused.
     *
     * The control is the same viewer once the plan is readable to them too.
     *
     * @return void
     */
    public function test_the_plan_is_read_through_core_before_the_detail_rule(): void {
        $f = $this->create_plan();
        $id = $this->evidence((int) $f->owner->id, $f->alpha, self::OCTOBER);
        $reader = $this->create_holder('moodle/competency:usercompetencyview');
        $this->setUser($reader);
        $this->assertTrue(user_competency::can_read_user((int) $f->owner->id));

        try {
            plan_journey::page($f->planid, 0, 0);
            $this->fail('A viewer who may not read the plan read its journey.');
        } catch (\required_capability_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }

        $this->setAdminUser();
        $roleid = (int) $this->getDataGenerator()->create_role();
        assign_capability('moodle/competency:planview', CAP_ALLOW, $roleid, \context_system::instance()->id);
        role_assign($roleid, $reader->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($reader);
        $this->assertSame([$id], self::ids(plan_journey::page($f->planid, 0, 0)));
    }

    /**
     * A viewer who reads the plan but not its competencies' detail gets no journey; one who has the detail does.
     *
     * The draft-only reader is the overview's own case (candetail false): core lets them read a draft plan
     * and refuses them every competency's detail.
     *
     * @return void
     */
    public function test_a_viewer_without_the_detail_is_refused(): void {
        $f = $this->create_plan(plan::STATUS_DRAFT);
        $id = $this->evidence((int) $f->owner->id, $f->alpha, self::OCTOBER);

        $this->setAdminUser();
        $this->assertSame([$id], self::ids(plan_journey::page($f->planid, 0, 0)));

        $this->setUser($this->create_holder('moodle/competency:planviewdraft'));
        $this->assertSame($f->planid, (int) api::read_plan($f->planid)->get('id'));
        $this->assertFalse(user_competency::can_read_user((int) $f->owner->id));
        $this->expectException(\required_capability_exception::class);
        plan_journey::page($f->planid, 0, 0);
    }

    /**
     * A plan that does not exist is the plugin's invalidplan, the error the page itself gives.
     *
     * @return void
     */
    public function test_a_missing_plan_is_invalid(): void {
        $this->setAdminUser();
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('invalidplan', 'local_dimensions'));
        plan_journey::page(987654, 0, 0);
    }

    /**
     * Only the plan's competencies speak: the owner's evidence on another competency is left out.
     *
     * The records of both plan competencies are the control: the query reads the owner's evidence.
     *
     * @return void
     */
    public function test_only_the_plans_competencies_appear(): void {
        $f = $this->create_plan();
        $ownerid = (int) $f->owner->id;
        $alpha = $this->evidence($ownerid, $f->alpha, self::OCTOBER);
        $bravo = $this->evidence($ownerid, $f->bravo, self::OCTOBER - 60);
        $this->evidence($ownerid, $f->outside, self::OCTOBER + 60);
        // Another learner's evidence on a plan competency is not the owner's.
        $this->evidence((int) $this->getDataGenerator()->create_user()->id, $f->alpha, self::OCTOBER + 120);

        $this->setUser($f->owner);
        $page = plan_journey::page($f->planid, 0, 0);

        $this->assertSame([$alpha, $bravo], self::ids($page));
        $this->assertSame([$f->alpha, $f->bravo], array_column($page['entries'], 'competencyid'));
        $this->assertSame(['Alpha competency', 'Bravo competency'], array_column($page['entries'], 'competencyname'));
    }

    /**
     * A completed plan lists its archived competencies' evidence up to its completion, as core's own list does.
     *
     * Core's api::list_evidence() is the reference: the same ids for each competency, so a record made after the
     * plan was completed is out and one made before it is in.
     *
     * @return void
     */
    public function test_a_completed_plan_stops_at_its_completion_as_core_does(): void {
        global $DB;

        $f = $this->create_plan();
        $ownerid = (int) $f->owner->id;
        $before = $this->evidence($ownerid, $f->alpha, self::SEPTEMBER);
        $this->setAdminUser();
        api::complete_plan($f->planid);
        $DB->set_field('competency_plan', 'timemodified', self::OCTOBER, ['id' => $f->planid]);
        $after = $this->evidence($ownerid, $f->alpha, self::OCTOBER + DAYSECS);
        $bravo = $this->evidence($ownerid, $f->bravo, self::OCTOBER - DAYSECS);

        $this->setUser($f->owner);
        $page = plan_journey::page($f->planid, 0, 0);

        $this->assertSame([$bravo, $before], self::ids($page));
        $this->assertNotContains($after, self::ids($page));
        $core = [];
        foreach ([$f->alpha, $f->bravo] as $competencyid) {
            foreach (api::list_evidence($ownerid, $competencyid, $f->planid) as $record) {
                $core[] = (int) $record->get('id');
            }
        }
        sort($core);
        $mine = self::ids($page);
        sort($mine);
        $this->assertSame($core, $mine);
    }

    /**
     * Keyset paging returns every record once, newest first, across pages that split equal timestamps.
     *
     * @return void
     */
    public function test_paging_returns_each_record_once_across_equal_timestamps(): void {
        $f = $this->create_plan();
        $ownerid = (int) $f->owner->id;
        $expected = [];
        foreach ([100, 100, 100, 90, 90, 80, 80] as $offset) {
            $time = self::OCTOBER + $offset;
            $expected[] = ['time' => $time, 'id' => $this->evidence($ownerid, $f->alpha, $time)];
        }
        usort($expected, static fn(array $a, array $b): int => [$b['time'], $b['id']] <=> [$a['time'], $a['id']]);

        $this->setUser($f->owner);
        $seen = [];
        $pages = 0;
        [$time, $id] = [0, 0];
        do {
            $page = plan_journey::page($f->planid, $time, $id, 3);
            $seen = array_merge($seen, self::ids($page));
            [$time, $id] = [$page['nexttime'], $page['nextid']];
            $pages++;
        } while ($page['hasmore'] && $pages < 10);

        $this->assertSame(3, $pages);
        $this->assertSame(array_column($expected, 'id'), $seen);
    }

    /**
     * Another component's logs are folded into one count per month; a core record and a rating from elsewhere are listed.
     *
     * @return void
     */
    public function test_other_components_logs_fold_into_one_line_per_month(): void {
        $f = $this->create_plan();
        $ownerid = (int) $f->owner->id;
        $other = ['desccomponent' => 'local_dimensions', 'descidentifier' => 'pluginname'];
        $core = $this->evidence($ownerid, $f->alpha, self::OCTOBER + 300);
        $this->evidence($ownerid, $f->alpha, self::OCTOBER + 200, $other);
        $this->evidence($ownerid, $f->bravo, self::OCTOBER + 100, $other);
        $rated = $this->evidence($ownerid, $f->alpha, self::OCTOBER, $other + [
            'action' => evidence::ACTION_OVERRIDE,
            'grade' => 2,
        ]);
        $this->evidence($ownerid, $f->alpha, self::SEPTEMBER, $other);

        $this->setUser($f->owner);
        $page = plan_journey::page($f->planid, 0, 0);

        $this->assertSame([$core, $rated], self::ids($page));
        $this->assertSame('B', $page['entries'][1]['rating']);
        $this->assertSame(
            [['2026-10', 2], ['2026-09', 1]],
            array_map(static fn(array $fold): array => [$fold['monthkey'], $fold['count']], $page['folds'])
        );
    }

    /**
     * A record as the reader sees it: core's words for what happened, the rating's name, who did it, the note.
     *
     * Every text is plain (the client writes it as text), an address only when it is http(s).
     *
     * @return void
     */
    public function test_a_record_carries_what_core_shows(): void {
        global $DB;

        $f = $this->create_plan();
        $ownerid = (int) $f->owner->id;
        $DB->set_field('competency', 'shortname', 'R&D <b>basics</b>', ['id' => $f->alpha]);
        $teacher = $this->getDataGenerator()->create_user(['firstname' => 'Lucia', 'lastname' => 'Prado']);
        $rated = $this->evidence($ownerid, $f->alpha, self::OCTOBER, [
            'action' => evidence::ACTION_OVERRIDE,
            'grade' => 1,
            'descidentifier' => 'evidence_manualoverride',
            'actionuserid' => $teacher->id,
            'note' => 'Uses rubrics & checklists',
            'url' => 'https://example.com/work',
        ]);
        $this->evidence($ownerid, $f->bravo, self::OCTOBER - 60, ['url' => 'ftp://example.com/file']);

        $this->setUser($f->owner);
        $page = plan_journey::page($f->planid, 0, 0);
        [$entry, $plain] = $page['entries'];

        $this->assertSame($rated, $entry['id']);
        $this->assertSame('R&D basics', $entry['competencyname']);
        $this->assertSame(get_string('evidence_manualoverride', 'core_competency'), $entry['description']);
        $this->assertTrue($entry['hasrating']);
        $this->assertSame('A', $entry['rating']);
        $this->assertTrue($entry['hasactor']);
        $this->assertSame(get_string('journey_by', 'local_dimensions', 'Lucia Prado'), $entry['actor']);
        $this->assertSame('Uses rubrics & checklists', $entry['note']);
        $this->assertSame('https://example.com/work', $entry['url']);
        $this->assertSame('2026-10', $entry['monthkey']);
        $this->assertSame(self::OCTOBER, $entry['timecreated']);

        $this->assertFalse($plain['hasrating']);
        $this->assertFalse($plain['hasactor']);
        $this->assertFalse($plain['hasnote']);
        $this->assertFalse($plain['hasurl']);
        $this->assertSame('', $plain['url']);
        $this->assertFalse($page['hasmore']);
        $this->assertSame(get_string('journey_planstarted', 'local_dimensions'), $page['planstart']['label']);
    }

    /**
     * The number of statements does not grow with the number of records on a page.
     *
     * Both plans are read once first, so a cache one of them fills cannot make the other look cheaper.
     *
     * @return void
     */
    public function test_the_statement_count_is_constant_in_the_rows(): void {
        global $DB;

        $teacher = $this->getDataGenerator()->create_user();
        $plans = [];
        foreach ([3, 12] as $rows) {
            $f = $this->create_plan();
            for ($i = 0; $i < $rows; $i++) {
                $this->evidence((int) $f->owner->id, ($i % 2) ? $f->alpha : $f->bravo, self::OCTOBER - $i * DAYSECS * 7, [
                    'action' => evidence::ACTION_OVERRIDE,
                    'grade' => 1 + ($i % 3),
                    'descidentifier' => 'evidence_manualoverride',
                    'actionuserid' => $i ? $teacher->id : $f->owner->id,
                    'note' => 'Note ' . $i,
                ]);
            }
            $plans[$rows] = $f->planid;
        }

        $this->setAdminUser();
        foreach ($plans as $planid) {
            plan_journey::page($planid, 0, 0);
        }
        $reads = [];
        foreach ($plans as $rows => $planid) {
            $start = $DB->perf_get_reads();
            $page = plan_journey::page($planid, 0, 0);
            $reads[$rows] = $DB->perf_get_reads() - $start;
            $this->assertCount($rows, $page['entries']);
        }

        $this->assertSame($reads[3], $reads[12]);
    }

    /**
     * A cursor is both parts or neither, and never negative.
     *
     * @return void
     */
    public function test_a_cursor_is_both_parts_or_none(): void {
        $f = $this->create_plan();
        $this->setUser($f->owner);
        $this->assertSame([], plan_journey::page($f->planid, 0, 0)['entries']);
        $this->assertSame([], plan_journey::page($f->planid, self::OCTOBER, 5)['entries']);

        foreach ([[-1, 0], [0, -1], [self::OCTOBER, 0], [0, 5], [-5, -5]] as [$time, $id]) {
            try {
                plan_journey::page($f->planid, $time, $id);
                $this->fail("The cursor {$time}/{$id} was accepted.");
            } catch (\invalid_parameter_exception $e) {
                $this->assertSame('invalidparameter', $e->errorcode);
            }
        }
    }
}
