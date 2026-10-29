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

use core_course\customfield\course_handler;
/**
 * Course chip values honour each custom field's visibility for the viewer.
 *
 * The shared cache keeps every field's value; the visibility is applied on output, so a field an
 * admin lists as a filter does not reach a viewer core would not show it to.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\chip_filters
 */
final class chip_filters_visibility_test extends \advanced_testcase {
    /** @var string[] The three fields, listed as the admin setting would. */
    private const SHORTNAMES = ['vall', 'vteach', 'vnone'];

    /**
     * A course with three text fields of different visibility, all filled.
     *
     * @return int The course id.
     */
    private function make_course(): int {
        $generator = $this->getDataGenerator();
        $cfg = $generator->get_plugin_generator('core_customfield');
        $category = $cfg->create_category();
        $visibilities = [
            'vall' => course_handler::VISIBLETOALL,
            'vteach' => course_handler::VISIBLETOTEACHERS,
            'vnone' => course_handler::NOTVISIBLE,
        ];
        $courseid = (int) $generator->create_course()->id;
        foreach ($visibilities as $shortname => $visibility) {
            $field = $cfg->create_field([
                'categoryid' => $category->get('id'),
                'type' => 'text',
                'shortname' => $shortname,
                'configdata' => ['visibility' => $visibility],
            ]);
            $cfg->add_instance_data($field, $courseid, 'value ' . $shortname);
        }
        return $courseid;
    }

    /**
     * Log in a new user with a role in the course.
     *
     * @param int $courseid
     * @param string $role
     * @return void
     */
    private function login_as(int $courseid, string $role): void {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $courseid, $role);
        $this->setUser($user);
    }

    /**
     * A student gets only the field visible to all.
     *
     * @return void
     */
    public function test_student_gets_only_the_field_visible_to_all(): void {
        $this->resetAfterTest();
        $courseid = $this->make_course();
        $this->login_as($courseid, 'student');

        $values = chip_filters::get_course_values([$courseid], self::SHORTNAMES);

        $this->assertSame(['vall' => 'value vall', 'vteach' => '', 'vnone' => ''], $values[$courseid]);
    }

    /**
     * Control: an editing teacher also gets the teacher-only field, never the hidden one.
     *
     * @return void
     */
    public function test_editing_teacher_gets_the_teacher_field_too(): void {
        $this->resetAfterTest();
        $courseid = $this->make_course();
        $this->login_as($courseid, 'editingteacher');

        $values = chip_filters::get_course_values([$courseid], self::SHORTNAMES);

        $this->assertSame(['vall' => 'value vall', 'vteach' => 'value vteach', 'vnone' => ''], $values[$courseid]);
    }

    /**
     * Nobody gets a field configured not visible, an administrator included.
     *
     * @return void
     */
    public function test_nobody_gets_the_hidden_field(): void {
        $this->resetAfterTest();
        $courseid = $this->make_course();
        $this->setAdminUser();

        $values = chip_filters::get_course_values([$courseid], self::SHORTNAMES);

        $this->assertSame('value vteach', $values[$courseid]['vteach']);
        $this->assertSame('', $values[$courseid]['vnone']);
    }

    /**
     * The cache is shared by every viewer: a teacher warming it does not widen what a student reads.
     *
     * @return void
     */
    public function test_a_warm_cache_is_still_filtered_per_viewer(): void {
        $this->resetAfterTest();
        $courseid = $this->make_course();
        $this->login_as($courseid, 'editingteacher');
        chip_filters::get_course_values([$courseid], self::SHORTNAMES);
        $cached = \cache::make('local_dimensions', 'course_customfields')->get($courseid);
        $this->assertSame('value vnone', $cached['vnone']);

        $this->login_as($courseid, 'student');
        $values = chip_filters::get_course_values([$courseid], self::SHORTNAMES);

        $this->assertSame(['vall' => 'value vall', 'vteach' => '', 'vnone' => ''], $values[$courseid]);
    }
}
