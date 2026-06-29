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
 * Tests for helper::framework_rows.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\helper::framework_rows
 */
final class helper_framework_rows_test extends \advanced_testcase {
    /**
     * framework_rows reports competency count, visibility and deletability.
     *
     * @return void
     */
    public function test_framework_rows(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $context = \context_system::instance();

        $empty = $ccg->create_framework(['visible' => 0]);
        $used = $ccg->create_framework(['visible' => 1]);
        $competency = $ccg->create_competency(['competencyframeworkid' => $used->get('id')]);
        $course = $this->getDataGenerator()->create_course();
        $ccg->create_course_competency(['courseid' => $course->id, 'competencyid' => $competency->get('id')]);

        // Pass includehidden=true so both visible and hidden frameworks are returned.
        $rows = helper::framework_rows($context, true);
        $byid = [];
        foreach ($rows as $row) {
            $byid[(int) $row['id']] = $row;
        }

        $this->assertArrayHasKey((int) $empty->get('id'), $byid);
        $this->assertSame(0, $byid[(int) $empty->get('id')]['competencycount']);
        $this->assertFalse($byid[(int) $empty->get('id')]['visible']);
        $this->assertTrue($byid[(int) $empty->get('id')]['deletable']);

        $this->assertSame(1, $byid[(int) $used->get('id')]['competencycount']);
        $this->assertTrue($byid[(int) $used->get('id')]['visible']);
        $this->assertFalse($byid[(int) $used->get('id')]['deletable']);
    }

    /**
     * framework_rows with default (visible-only) hides hidden frameworks;
     * with includehidden=true both visible and hidden frameworks are returned.
     *
     * @return void
     */
    public function test_framework_rows_includehidden(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $context = \context_system::instance();

        $visible = $ccg->create_framework(['visible' => 1]);
        $hidden = $ccg->create_framework(['visible' => 0]);

        // Default: visible-only — hidden framework must not appear.
        $rows = helper::framework_rows($context);
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int) $row['id'];
        }
        $this->assertContains((int) $visible->get('id'), $ids);
        $this->assertNotContains((int) $hidden->get('id'), $ids);

        // With includehidden=true — both frameworks must appear.
        $rowsall = helper::framework_rows($context, true);
        $idsall = [];
        foreach ($rowsall as $row) {
            $idsall[] = (int) $row['id'];
        }
        $this->assertContains((int) $visible->get('id'), $idsall);
        $this->assertContains((int) $hidden->get('id'), $idsall);
    }
}
