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
 * Tests for the Journey view's web service, called the way the browser calls it.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core_competency\evidence;
use core_competency\plan;
use core_competency\user_competency;
use core_external\external_api;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * local_dimensions_get_plan_journey through external_api::call_external_function(): its shape, every
 * refusal beside the control it changes, the cursor's validation and the competencies switch.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(get_plan_journey::class)]
final class get_plan_journey_test extends \advanced_testcase {
    /** @var string The service. */
    private const string SERVICE = 'local_dimensions_get_plan_journey';

    /**
     * Competencies on, every test's records written now.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
    }

    /**
     * resetAfterTest() does not restore superglobals: drop the sesskey the calls plant in $_POST.
     *
     * @return void
     */
    protected function tearDown(): void {
        unset($_POST['sesskey']);
        parent::tearDown();
    }

    /**
     * A plan of one competency for a new owner, with one rated record on it.
     *
     * @param int $status The plan status.
     * @param string $shortname The competency's name.
     * @return \stdClass owner, planid, competencyid, evidenceid.
     */
    private function create_plan(int $status = plan::STATUS_ACTIVE, string $shortname = 'Alpha competency'): \stdClass {
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework(['visible' => 1]);
        $owner = $this->getDataGenerator()->create_user();
        $plan = $ccg->create_plan(['userid' => $owner->id, 'status' => $status]);
        $competencyid = (int) $ccg->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'shortname' => $shortname,
        ])->get('id');
        $ccg->create_plan_competency(['planid' => $plan->get('id'), 'competencyid' => $competencyid]);
        $uc = $ccg->create_user_competency(['userid' => $owner->id, 'competencyid' => $competencyid]);
        $evidenceid = (int) $ccg->create_evidence([
            'usercompetencyid' => $uc->get('id'),
            'action' => evidence::ACTION_OVERRIDE,
            'grade' => 1,
            'descidentifier' => 'evidence_manualoverride',
            'desccomponent' => 'core_competency',
            'note' => 'A < B & C',
        ])->get('id');

        return (object) [
            'owner' => $owner,
            'planid' => (int) $plan->get('id'),
            'competencyid' => $competencyid,
            'evidenceid' => $evidenceid,
        ];
    }

    /**
     * Call the service as the current user, as the browser does.
     *
     * @param array $args The arguments.
     * @return array call_external_function()'s answer.
     */
    private function call(array $args): array {
        $_POST['sesskey'] = sesskey();
        return external_api::call_external_function(self::SERVICE, $args, true);
    }

    /**
     * Assert a call was refused with the given error code.
     *
     * @param array $result call_external_function()'s answer.
     * @param string $errorcode The expected error code.
     * @return void
     */
    private function assert_refused(array $result, string $errorcode): void {
        $this->assertTrue($result['error'], 'The call was answered.');
        $this->assertSame($errorcode, $result['exception']->errorcode);
    }

    /**
     * Assert a call answered with the fixture's one record.
     *
     * @param array $result call_external_function()'s answer.
     * @param \stdClass $f The fixture.
     * @return void
     */
    private function assert_answered(array $result, \stdClass $f): void {
        $this->assertFalse($result['error'], json_encode($result['exception'] ?? null));
        $this->assertSame([$f->evidenceid], array_column($result['data']['entries'], 'id'));
    }

    /**
     * The owner's call answers the allowlisted shape, plain texts surviving the return's cleaning.
     *
     * @return void
     */
    public function test_the_owner_reads_the_journey(): void {
        $f = $this->create_plan(plan::STATUS_ACTIVE, 'R&D < research');
        $this->setUser($f->owner);

        $result = $this->call(['planid' => $f->planid]);

        $this->assert_answered($result, $f);
        $entry = $result['data']['entries'][0];
        $this->assertSame([
            'id', 'timecreated', 'datetime', 'monthkey', 'monthlabel', 'daylabel', 'competencyid', 'competencyname',
            'description', 'hasrating', 'rating', 'hasactor', 'actor', 'hasnote', 'note', 'hasurl', 'url',
        ], array_keys($entry));
        $this->assertSame($f->competencyid, $entry['competencyid']);
        $this->assertSame('R&D < research', $entry['competencyname']);
        $this->assertSame('A < B & C', $entry['note']);
        $this->assertSame('A', $entry['rating']);
        $this->assertSame([], $result['data']['folds']);
        $this->assertFalse($result['data']['hasmore']);
        $this->assertSame(
            ['timecreated', 'datetime', 'monthkey', 'monthlabel', 'daylabel', 'label'],
            array_keys($result['data']['planstart'])
        );
    }

    /**
     * Notes with a bare angle bracket or ampersand pass the return's own cleaning, and the quiet lines' shape too.
     *
     * @return void
     */
    public function test_notes_with_markup_characters_and_the_remainder_line_are_answered(): void {
        global $DB;

        $f = $this->create_plan();
        $DB->set_field('competency_evidence', 'note', 'a < b', ['id' => $f->evidenceid]);
        $uc = user_competency::get_record(['userid' => $f->owner->id, 'competencyid' => $f->competencyid]);
        $log = (int) $this->getDataGenerator()->get_plugin_generator('core_competency')->create_evidence([
            'usercompetencyid' => $uc->get('id'),
            'action' => evidence::ACTION_LOG,
            'descidentifier' => 'pluginname',
            'desccomponent' => 'local_dimensions',
            'note' => 'R & D',
        ])->get('id');
        // Years before any clock, so the line is the remainder whatever day this runs.
        $DB->set_field('competency_evidence', 'timecreated', 946728000, ['id' => $log]);

        $this->setUser($f->owner);
        $result = $this->call(['planid' => $f->planid]);

        $this->assertFalse($result['error'], json_encode($result['exception'] ?? null));
        $this->assertSame('a < b', $result['data']['entries'][0]['note']);
        $this->assertCount(1, $result['data']['folds']);
        $this->assertSame(['monthkey', 'monthlabel', 'count', 'timecreated', 'older'], array_keys($result['data']['folds'][0]));
        $this->assertSame('older', $result['data']['folds'][0]['monthkey']);
        $this->assertTrue($result['data']['folds'][0]['older']);
        $this->assertSame(1, $result['data']['folds'][0]['count']);
    }

    /**
     * Somebody who may not read the plan is refused with core's error; the owner is the control.
     *
     * @return void
     */
    public function test_a_stranger_is_refused(): void {
        $f = $this->create_plan();
        $this->setUser($f->owner);
        $this->assert_answered($this->call(['planid' => $f->planid]), $f);

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assert_refused($this->call(['planid' => $f->planid]), 'nopermissions');
    }

    /**
     * A viewer who reads a draft plan but not its competencies is refused; an administrator is the control.
     *
     * @return void
     */
    public function test_a_viewer_without_the_detail_is_refused(): void {
        $f = $this->create_plan(plan::STATUS_DRAFT);
        $this->setAdminUser();
        $this->assert_answered($this->call(['planid' => $f->planid]), $f);

        $reader = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/competency:planviewdraft', CAP_ALLOW, $roleid, \context_system::instance()->id);
        role_assign($roleid, $reader->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($reader);
        $this->assertFalse(user_competency::can_read_user((int) $f->owner->id));

        $this->assert_refused($this->call(['planid' => $f->planid]), 'nopermissions');
    }

    /**
     * The plugin's own capability is asked: the owner without local/dimensions:view is refused.
     *
     * @return void
     */
    public function test_the_plugins_capability_is_required(): void {
        global $DB;

        $f = $this->create_plan();
        $this->setUser($f->owner);
        $this->assert_answered($this->call(['planid' => $f->planid]), $f);

        $userrole = (int) $DB->get_field('role', 'id', ['shortname' => 'user']);
        assign_capability('local/dimensions:view', CAP_PROHIBIT, $userrole, \context_system::instance()->id, true);
        accesslib_clear_all_caches_for_unit_testing();

        $this->assert_refused($this->call(['planid' => $f->planid]), 'nopermissions');
    }

    /**
     * The guest account is refused before anything is read; a logged-in owner is the control above.
     *
     * @return void
     */
    public function test_the_guest_is_refused(): void {
        $f = $this->create_plan();
        $this->setGuestUser();

        $this->assert_refused($this->call(['planid' => $f->planid]), 'noguest');
    }

    /**
     * A cursor is both parts or neither, never negative; the empty cursor is the control.
     *
     * @return void
     */
    public function test_the_cursor_is_validated(): void {
        $f = $this->create_plan();
        $this->setUser($f->owner);
        $this->assert_answered($this->call(['planid' => $f->planid, 'beforetime' => 0, 'beforeid' => 0]), $f);
        $later = $this->call(['planid' => $f->planid, 'beforetime' => time() + DAYSECS, 'beforeid' => PHP_INT_MAX]);
        $this->assert_answered($later, $f);

        foreach ([[-1, 0], [0, 7], [time(), 0], [time(), -3]] as [$time, $id]) {
            $result = $this->call(['planid' => $f->planid, 'beforetime' => $time, 'beforeid' => $id]);
            $this->assert_refused($result, 'invalidparameter');
        }
    }

    /**
     * With competencies turned off the service refuses with core's error; turned on is the control.
     *
     * @return void
     */
    public function test_competencies_disabled_are_refused(): void {
        $f = $this->create_plan();
        $this->setUser($f->owner);
        $this->assert_answered($this->call(['planid' => $f->planid]), $f);

        set_config('enabled', 0, 'core_competency');
        $this->assert_refused($this->call(['planid' => $f->planid]), 'competenciesarenotenabled');
    }

    /**
     * A plan id with no plan is the page's own answer for it.
     *
     * @return void
     */
    public function test_a_missing_plan_is_invalid(): void {
        $this->setAdminUser();
        $this->assert_refused($this->call(['planid' => 987654]), 'invalidplan');
    }
}
