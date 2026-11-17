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

/**
 * One learner's permanent restrictions, judged without listing anybody else.
 *
 * Core's {@see \core_availability\info_module::filter_user_list()} is the oracle: every case
 * asserts the same answer for every user, and also the answer written out by hand, so that a
 * change in core's rule shows up here as a disagreement rather than passing silently.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\local\learner_restrictions
 * @covers     \local_dimensions\local\single_user_capability_checker
 */
final class learner_restrictions_test extends \advanced_testcase {
    /** @var \stdClass The course. */
    private \stdClass $course;

    /** @var array Group ids by letter (A, B, C, D, E). */
    private array $groups = [];

    /** @var array Grouping ids by name (G1 holds A and B, G2 holds C, G3 holds C and D). */
    private array $groupings = [];

    /** @var array User ids by name. */
    private array $users = [];

    /**
     * Restriction sets and who passes them.
     *
     * Groups A and B are visible to all; C is visible to nobody, D to its members, E to each
     * member for their own membership; X belongs to another course, where inB is its member.
     * Users: inA; inB (also in D); inAC (in A, C and E); none (in no group); city (city
     * Recife, in no group); aag (a learner holding moodle/site:accessallgroups); teacher (a
     * non-editing teacher in B, who holds
     * moodle/course:ignoreavailabilityrestrictions and moodle/course:viewhiddengroups).
     *
     * The expected list is the learner's own reading. Core's filter shows a named group's members
     * as the viewer may see them, so the hidden-group cases read differently to a teacher, and
     * for those the test holds only core's answer.
     *
     * @return array
     */
    public static function restriction_provider(): array {
        $all = ['inA', 'inB', 'inAC', 'none', 'city', 'aag', 'teacher'];
        return [
            'group A' => ['cm', ['op' => '&', 'c' => [['type' => 'group', 'id' => 'A']], 'showc' => [true]],
                ['inA', 'inAC', 'aag', 'teacher']],
            'any group' => ['cm', ['op' => '&', 'c' => [['type' => 'group']], 'showc' => [true]],
                ['inA', 'inB', 'inAC', 'aag', 'teacher']],
            'group C, visible to nobody' => ['cm', ['op' => '&', 'c' => [['type' => 'group', 'id' => 'C']],
                'showc' => [false]], ['aag', 'teacher']],
            'group D, visible to members' => ['cm', ['op' => '&', 'c' => [['type' => 'group', 'id' => 'D']],
                'showc' => [true]], ['inB', 'aag', 'teacher']],
            'group E, visible to each member' => ['cm', ['op' => '&', 'c' => [['type' => 'group', 'id' => 'E']],
                'showc' => [true]], ['inAC', 'aag', 'teacher']],
            'grouping G3, of groups not visible to all' => ['cm', ['op' => '&', 'c' => [['type' => 'grouping', 'id' => 'G3']],
                'showc' => [true]], ['inB', 'inAC', 'aag', 'teacher']],
            'grouping G1' => ['cm', ['op' => '&', 'c' => [['type' => 'grouping', 'id' => 'G1']], 'showc' => [true]],
                ['inA', 'inB', 'inAC', 'aag', 'teacher']],
            'grouping of the activity' => ['cm', ['op' => '&', 'c' => [['type' => 'grouping', 'activity' => true]],
                'showc' => [true]], ['inA', 'inB', 'inAC', 'aag', 'teacher']],
            'not in group A' => ['cm', ['op' => '!&', 'c' => [['type' => 'group', 'id' => 'A']], 'show' => true],
                ['inB', 'none', 'city', 'aag', 'teacher']],
            'in neither A nor B' => ['cm', ['op' => '!|', 'c' => [['type' => 'group', 'id' => 'A'],
                ['type' => 'group', 'id' => 'B']], 'showc' => [true, true]], ['none', 'city', 'aag', 'teacher']],
            'a future date or group B' => ['cm', ['op' => '|', 'c' => [['type' => 'date', 'd' => '>=', 't' => 'future'],
                ['type' => 'group', 'id' => 'B']], 'show' => true], $all],
            'a past date and group A' => ['cm', ['op' => '&', 'c' => [['type' => 'date', 'd' => '>=', 't' => 'past'],
                ['type' => 'group', 'id' => 'A']], 'showc' => [true, true]], ['inA', 'inAC', 'aag', 'teacher']],
            'a future date only' => ['cm', ['op' => '&', 'c' => [['type' => 'date', 'd' => '>=', 't' => 'future']],
                'showc' => [true]], $all],
            'city is Recife' => ['cm', ['op' => '&', 'c' => [['type' => 'profile', 'sf' => 'city', 'op' => 'isequalto',
                'v' => 'Recife']], 'showc' => [true]], ['city', 'teacher']],
            'city is not Recife' => ['cm', ['op' => '!&', 'c' => [['type' => 'profile', 'sf' => 'city', 'op' => 'isequalto',
                'v' => 'Recife']], 'show' => true], ['inA', 'inB', 'inAC', 'none', 'aag', 'teacher']],
            'nested trees' => ['cm', ['op' => '|', 'c' => [
                ['op' => '&', 'c' => [['type' => 'group', 'id' => 'A'], ['type' => 'date', 'd' => '<', 't' => 'future']]],
                ['op' => '!|', 'c' => [['type' => 'grouping', 'id' => 'G2'],
                    ['type' => 'profile', 'sf' => 'city', 'op' => 'isequalto', 'v' => 'Recife']]],
            ], 'show' => true], ['inA', 'inB', 'inAC', 'none', 'aag', 'teacher']],
            'group X, of another course' => ['cm', ['op' => '&', 'c' => [['type' => 'group', 'id' => 'X']],
                'showc' => [true]], ['inB', 'aag', 'teacher']],
            'grouping 0' => ['cm', ['op' => '&', 'c' => [['type' => 'grouping', 'id' => 0]], 'showc' => [true]],
                ['aag', 'teacher']],
            'section restricted to grouping G1' => ['section', ['op' => '&', 'c' => [['type' => 'grouping', 'id' => 'G1']],
                'showc' => [true]], ['inA', 'inB', 'inAC', 'aag', 'teacher']],
        ];
    }

    /**
     * Every user gets core's answer, read by themselves, by a teacher and by two classmates.
     *
     * @param string $where Whether the set restricts the activity or its section.
     * @param array $set The restriction set, with group, grouping and time placeholders.
     * @param array $passing The names of the users who pass.
     * @return void
     * @dataProvider restriction_provider
     */
    public function test_each_learner_gets_cores_answer(string $where, array $set, array $passing): void {
        $this->resetAfterTest();
        $this->build_course();
        $cmid = $this->add_restricted_activity($where, $set);

        // A teacher reviewing a plan reads the cm from their own modinfo; inB and inAC are classmates.
        foreach (['self', 'teacher', 'inB', 'inAC'] as $viewer) {
            foreach ($this->users as $name => $userid) {
                $this->setUser($viewer === 'self' ? $userid : $this->users[$viewer]);
                $cm = get_fast_modinfo($this->course->id)->get_cm($cmid);
                $one = [$userid => (object) ['id' => $userid]];
                $core = !empty((new \core_availability\info_module($cm))->filter_user_list($one));

                if ($viewer === 'self') {
                    $this->assertSame(in_array($name, $passing, true), $core, "core, $name");
                }
                $this->assertSame($core, learner_restrictions::passes($cm, $userid), "$name, viewed by $viewer");
            }
        }
    }

    /**
     * A learner's warm check of a group or grouping rule runs no query at all.
     *
     * Core's filter lists the members of the group, grouping or course on every call (two to six
     * queries, each as long as the membership). The control proves the measurement can see a
     * query: the profile rule reads the learner's field every time.
     *
     * @return void
     */
    public function test_a_warm_group_check_runs_no_query(): void {
        global $DB;
        $this->resetAfterTest();
        $this->build_course();
        $groupcm = $this->add_restricted_activity('cm', ['op' => '&', 'c' => [['type' => 'group']], 'showc' => [true]]);
        $groupingcm = $this->add_restricted_activity('cm', ['op' => '&', 'c' => [['type' => 'grouping', 'id' => 'G1']],
            'showc' => [true]]);
        $activitycm = $this->add_restricted_activity('cm', ['op' => '&', 'c' => [['type' => 'grouping',
            'activity' => true]], 'showc' => [true]]);
        $profilecm = $this->add_restricted_activity('cm', ['op' => '&', 'c' => [['type' => 'profile', 'sf' => 'city',
            'op' => 'isequalto', 'v' => 'Recife']], 'showc' => [true]]);

        $this->setUser($this->users['none']);
        $modinfo = get_fast_modinfo($this->course->id);
        $reads = function (int $cmid) use ($DB, $modinfo): int {
            learner_restrictions::passes($modinfo->get_cm($cmid), $this->users['none']);
            $before = $DB->perf_get_reads();
            learner_restrictions::passes($modinfo->get_cm($cmid), $this->users['none']);
            return $DB->perf_get_reads() - $before;
        };

        $this->assertSame(0, $reads($groupcm));
        $this->assertSame(0, $reads($groupingcm));
        $this->assertSame(0, $reads($activitycm));
        $this->assertGreaterThan(0, $reads($profilecm));
    }

    /**
     * With restrictions off for the site, every restriction set lets everybody through.
     *
     * The control is the same activity with restrictions on: inB is outside group A.
     *
     * @return void
     */
    public function test_restrictions_off_for_the_site_let_everybody_through(): void {
        $this->resetAfterTest();
        $this->build_course();
        $cmid = $this->add_restricted_activity('cm', ['op' => '&', 'c' => [['type' => 'group', 'id' => 'A']], 'showc' => [true]]);
        $this->setUser($this->users['inB']);
        $cm = get_fast_modinfo($this->course->id)->get_cm($cmid);

        $this->assertFalse(learner_restrictions::passes($cm, $this->users['inB']));

        set_config('enableavailability', 0);
        $this->assertTrue(learner_restrictions::passes($cm, $this->users['inB']));
    }

    /**
     * The checker answers with its user when they hold the capability, and with nobody otherwise.
     *
     * @return void
     */
    public function test_the_checker_knows_only_its_user(): void {
        $this->resetAfterTest();
        $this->build_course();
        $context = \core\context\course::instance($this->course->id);

        $aag = new single_user_capability_checker($context, $this->users['aag']);
        $this->assertSame([$this->users['aag']], array_keys($aag->get_users_by_capability('moodle/site:accessallgroups')));

        $none = new single_user_capability_checker($context, $this->users['none']);
        $this->assertSame([], $none->get_users_by_capability('moodle/site:accessallgroups'));
    }

    /**
     * A course with three groups, two groupings and the seven users the provider names.
     *
     * @return void
     */
    private function build_course(): void {
        global $DB;
        set_config('enableavailability', 1);
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();

        $visibilities = [
            'A' => GROUPS_VISIBILITY_ALL,
            'B' => GROUPS_VISIBILITY_ALL,
            'C' => GROUPS_VISIBILITY_NONE,
            'D' => GROUPS_VISIBILITY_MEMBERS,
            'E' => GROUPS_VISIBILITY_OWN,
        ];
        foreach ($visibilities as $letter => $vis) {
            $this->groups[$letter] = (int) $gen->create_group(['courseid' => $this->course->id, 'visibility' => $vis,
                'participation' => $vis === GROUPS_VISIBILITY_ALL])->id;
        }
        // Group X belongs to another course: a restore can leave such an id in a restriction.
        $other = $gen->create_course();
        $this->groups['X'] = (int) $gen->create_group(['courseid' => $other->id])->id;

        foreach (['G1' => ['A', 'B'], 'G2' => ['C'], 'G3' => ['C', 'D']] as $name => $letters) {
            $this->groupings[$name] = (int) $gen->create_grouping(['courseid' => $this->course->id])->id;
            foreach ($letters as $letter) {
                $gen->create_grouping_group(['groupingid' => $this->groupings[$name], 'groupid' => $this->groups[$letter]]);
            }
        }

        $members = ['inA' => ['A'], 'inB' => ['B', 'D'], 'inAC' => ['A', 'C', 'E'], 'none' => [], 'city' => [], 'aag' => [],
            'teacher' => ['B']];
        foreach ($members as $name => $letters) {
            $user = $gen->create_user($name === 'city' ? ['city' => 'Recife'] : ['city' => 'Lisboa']);
            $gen->enrol_user($user->id, $this->course->id, $name === 'teacher' ? 'teacher' : 'student');
            foreach ($letters as $letter) {
                $gen->create_group_member(['groupid' => $this->groups[$letter], 'userid' => $user->id]);
            }
            if ($name === 'inB') {
                $gen->enrol_user($user->id, $other->id, 'student');
                $gen->create_group_member(['groupid' => $this->groups['X'], 'userid' => $user->id]);
            }
            $this->users[$name] = (int) $user->id;
        }

        $roleid = create_role('All groups', 'allgroups', '');
        assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $roleid, \core\context\system::instance());
        role_assign($roleid, $this->users['aag'], \core\context\course::instance($this->course->id));

        // Precondition: the teacher is exempt through the capability, not through a group rule.
        $context = \core\context\course::instance($this->course->id);
        $this->assertTrue(has_capability('moodle/course:ignoreavailabilityrestrictions', $context, $this->users['teacher']));
        $this->assertFalse(has_capability('moodle/site:accessallgroups', $context, $this->users['teacher']));
        $this->assertFalse(has_capability('moodle/course:viewhiddengroups', $context, $this->users['inB']));
        $this->assertSame(0, (int) $DB->count_records('groups_members', ['userid' => $this->users['aag']]));
    }

    /**
     * Adds a page restricted by the set, on the activity or on a section of its own.
     *
     * @param string $where 'cm' or 'section'.
     * @param array $set The restriction set with placeholders.
     * @return int The new activity's id.
     */
    private function add_restricted_activity(string $where, array $set): int {
        global $DB;
        $json = json_encode($this->resolve($set));
        $section = $where === 'section' ? 2 : 1;
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $this->course->id,
            'section' => $section,
            'groupingid' => $this->groupings['G1'],
            'availability' => $where === 'cm' ? $json : null,
        ]);
        if ($where === 'section') {
            $DB->set_field('course_sections', 'availability', $json, ['course' => $this->course->id, 'section' => $section]);
            rebuild_course_cache($this->course->id, true);
        }
        return (int) $page->cmid;
    }

    /**
     * Replaces group letters, grouping names and the words future and past with real values.
     *
     * @param array $node A node of the restriction set.
     * @return array The node with real values.
     */
    private function resolve(array $node): array {
        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $node[$key] = $this->resolve($value);
            }
        }
        if (($node['type'] ?? '') === 'group' && isset($node['id'])) {
            $node['id'] = $this->groups[$node['id']];
        }
        if (($node['type'] ?? '') === 'grouping' && is_string($node['id'] ?? null)) {
            $node['id'] = $this->groupings[$node['id']];
        }
        if (($node['type'] ?? '') === 'date') {
            $node['t'] = $node['t'] === 'future' ? time() + WEEKSECS : time() - WEEKSECS;
        }
        return $node;
    }
}
