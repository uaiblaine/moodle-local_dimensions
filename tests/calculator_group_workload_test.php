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

use PHPUnit\Framework\Attributes\CoversMethod;

/**
 * A learner's workload in a course whose activities are split between groups.
 *
 * One course in separate groups mode, forced, with activities restricted to group A, to group
 * B, to A or B, to a group C nobody here is in, and unrestricted ones, spread over two sections
 * and two subsections; a third section is restricted to group C and holds a subsection of its
 * own. Every expected count is written out by hand from that layout, so the bar and each ring
 * are checked against the real workload, not against another computation.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversMethod(calculator::class, 'get_course_section_progress')]
#[CoversMethod(calculator::class, 'course_completion_percentage')]
final class calculator_group_workload_test extends \advanced_testcase {
    /** @var \stdClass The course. */
    private \stdClass $course;

    /** @var array Group ids by letter. */
    private array $groups = [];

    /** @var array Activity ids by label. */
    private array $cms = [];

    /**
     * A learner in two groups owes each activity once, and every ring counts their own work.
     *
     * L is in A and B. Section 1 holds a1-a3 (A), b1-b2 (B), u1 (no restriction) and c1 (C):
     * six are L's. Section 2 holds a4-a5 (A), b3-b4 (B), ab1 (A or B), subsection S1 with b5
     * (B) and u2, and subsection S2, restricted to C, with u3 and u4: seven are L's. Section 3,
     * restricted to C, holds u5 and subsection S3 with u6: none are L's, and its ring is locked.
     * L completed a1, b1, u1, ab1 and b5.
     *
     * @return void
     */
    public function test_a_learner_in_two_groups(): void {
        $userid = $this->build_course(['A', 'B'], ['a1', 'b1', 'u1', 'ab1', 'b5']);

        $sections = calculator::get_course_section_progress((int) $this->course->id, $userid)['sections'];

        $this->assertSame(50, $sections[1]['percentage'], 'section 1: 3 of 6');
        $this->assertSame(29, $sections[2]['percentage'], 'section 2: 2 of 7, ab1 once');
        $this->assertNull($sections[3]['percentage'], 'section 3 is restricted to C');
        $this->assertTrue($sections[3]['locked']);
        $this->assertSame(38, calculator::course_completion_percentage((int) $this->course->id, $userid), 'course: 5 of 13');
    }

    /**
     * A learner in one group owes only that group's work and the unrestricted work.
     *
     * M is in A: section 1 holds a1-a3 and u1 for them, section 2 a4, a5, ab1 and u2 (S1). M
     * completed a2.
     *
     * @return void
     */
    public function test_a_learner_in_one_group(): void {
        $userid = $this->build_course(['A'], ['a2']);

        $sections = calculator::get_course_section_progress((int) $this->course->id, $userid)['sections'];

        $this->assertSame(25, $sections[1]['percentage'], 'section 1: 1 of 4');
        $this->assertSame(0, $sections[2]['percentage'], 'section 2: 0 of 4');
        $this->assertSame(13, calculator::course_completion_percentage((int) $this->course->id, $userid), 'course: 1 of 8');
    }

    /**
     * Separate groups mode takes no work away from a learner in no group.
     *
     * N is in no group: u1 in section 1 and u2 (S1) in section 2 are theirs, although the course
     * forces separate groups. N completed u2.
     *
     * @return void
     */
    public function test_a_learner_in_no_group_under_separate_groups(): void {
        $userid = $this->build_course([], ['u2']);

        $sections = calculator::get_course_section_progress((int) $this->course->id, $userid)['sections'];

        $this->assertSame(0, $sections[1]['percentage'], 'section 1: 0 of 1');
        $this->assertSame(100, $sections[2]['percentage'], 'section 2: 1 of 1');
        $this->assertSame(50, calculator::course_completion_percentage((int) $this->course->id, $userid), 'course: 1 of 2');
    }

    /**
     * Builds the course described on the first test, with one learner, and logs them in.
     *
     * @param array $groups The letters of the groups the learner is in.
     * @param array $completed The labels of the activities they have completed.
     * @return int The learner's id.
     */
    private function build_course(array $groups, array $completed): int {
        global $DB;
        $this->resetAfterTest();
        set_config('enableavailability', 1);
        $manager = \core_plugin_manager::resolve_plugininfo_class('mod');
        $manager::enable_plugin('subsection', 1);

        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course(['enablecompletion' => 1, 'numsections' => 3,
            'groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        foreach (['A', 'B', 'C'] as $letter) {
            $this->groups[$letter] = (int) $gen->create_group(['courseid' => $this->course->id])->id;
        }

        $this->add(1, ['a1', 'a2', 'a3'], $this->only('A'));
        $this->add(1, ['b1', 'b2'], $this->only('B'));
        $this->add(1, ['u1'], null);
        $this->add(1, ['c1'], $this->only('C'));
        $this->add(2, ['a4', 'a5'], $this->only('A'));
        $this->add(2, ['b3', 'b4'], $this->only('B'));
        $this->add(2, ['ab1'], json_encode(['op' => '|', 'c' => [['type' => 'group', 'id' => $this->groups['A']],
            ['type' => 'group', 'id' => $this->groups['B']]], 'show' => true]));

        $s1 = $this->subsection(2, null);
        $this->add($s1, ['b5'], $this->only('B'));
        $this->add($s1, ['u2'], null);
        $s2 = $this->subsection(2, $this->only('C'));
        $this->add($s2, ['u3', 'u4'], null);

        $DB->set_field('course_sections', 'availability', $this->only('C'), ['course' => $this->course->id, 'section' => 3]);
        rebuild_course_cache($this->course->id, true);
        $this->add(3, ['u5'], null);
        $s3 = $this->subsection(3, null);
        $this->add($s3, ['u6'], null);

        $user = $gen->create_user();
        $gen->enrol_user($user->id, $this->course->id, 'student');
        foreach ($groups as $letter) {
            $gen->create_group_member(['groupid' => $this->groups[$letter], 'userid' => $user->id]);
        }

        \course_modinfo::clear_instance_cache();
        $completion = new \completion_info($this->course);
        $modinfo = get_fast_modinfo($this->course, $user->id);
        foreach ($completed as $label) {
            $completion->update_state($modinfo->get_cm($this->cms[$label]), COMPLETION_COMPLETE, $user->id);
        }

        $this->setUser($user);
        \course_modinfo::clear_instance_cache();
        return (int) $user->id;
    }

    /**
     * A restriction to one group, shown greyed to everyone else.
     *
     * @param string $letter The group.
     * @return string The stored restriction.
     */
    private function only(string $letter): string {
        return json_encode(['op' => '&', 'c' => [['type' => 'group', 'id' => $this->groups[$letter]]], 'showc' => [true]]);
    }

    /**
     * Adds tracked pages to a section.
     *
     * @param int $section The section number.
     * @param array $labels One label per page.
     * @param string|null $availability The restriction, or null.
     * @return void
     */
    private function add(int $section, array $labels, ?string $availability): void {
        foreach ($labels as $label) {
            $page = $this->getDataGenerator()->create_module('page', [
                'course' => $this->course->id,
                'section' => $section,
                'completion' => COMPLETION_TRACKING_MANUAL,
                'availability' => $availability,
            ]);
            $this->cms[$label] = (int) $page->cmid;
        }
    }

    /**
     * Adds a subsection to a section.
     *
     * @param int $section The parent section number.
     * @param string|null $availability The subsection's restriction, or null.
     * @return int The number of its delegated section.
     */
    private function subsection(int $section, ?string $availability): int {
        $subsection = $this->getDataGenerator()->create_module('subsection', [
            'course' => $this->course->id,
            'section' => $section,
            'availability' => $availability,
        ]);
        \course_modinfo::clear_instance_cache();
        return (int) get_fast_modinfo($this->course->id)->get_cm($subsection->cmid)->get_delegated_section_info()->sectionnum;
    }
}
