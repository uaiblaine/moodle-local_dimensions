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

namespace local_dimensions\event;

use core\event\base;

/**
 * Tests for how the events logged in a course or module context map their ids on course restore.
 *
 * Restoring a course's logs calls get_objectid_mapping() and, for an event carrying 'other',
 * get_other_mapping(); core's default of the latter only raises a developer debugging message.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\event\course_link_added
 * @covers     \local_dimensions\event\course_link_outcome_updated
 * @covers     \local_dimensions\event\course_link_removed
 * @covers     \local_dimensions\event\module_link_added
 * @covers     \local_dimensions\event\module_link_outcome_updated
 * @covers     \local_dimensions\event\module_link_removed
 * @covers     \local_dimensions\event\enrol_method_applied
 * @covers     \local_dimensions\event\enrol_method_removed
 */
final class restore_mapping_test extends \advanced_testcase {
    /**
     * Each event's 'other' keys, as its trigger site fills them, and the mapping each id needs.
     *
     * Keys holding no id (a method name, a rule outcome) are absent from the mapping, which
     * leaves them as they are.
     *
     * @return array
     */
    public static function other_mapping_provider(): array {
        $competency = ['db' => 'competency', 'restore' => 'competency'];
        $course = [
            'competencyid' => $competency,
            'courseid' => ['db' => 'course', 'restore' => 'course'],
        ];
        $module = [
            'competencyid' => $competency,
            'cmid' => ['db' => 'course_modules', 'restore' => 'course_module'],
        ];
        $enrol = [
            'templateid' => base::NOT_MAPPED,
            'cohortid' => base::NOT_MAPPED,
            'roleid' => ['db' => 'role', 'restore' => 'role'],
        ];
        return [
            'course link added' => [course_link_added::class, $course],
            'course link outcome updated' => [course_link_outcome_updated::class, $course],
            'course link removed' => [course_link_removed::class, $course],
            'module link added' => [module_link_added::class, $module],
            'module link outcome updated' => [module_link_outcome_updated::class, $module],
            'module link removed' => [module_link_removed::class, $module],
            'enrol method applied' => [enrol_method_applied::class, $enrol],
            'enrol method removed' => [enrol_method_removed::class, $enrol],
        ];
    }

    /**
     * Every id an event carries in 'other' has a declared mapping, and nothing else does.
     *
     * @dataProvider other_mapping_provider
     * @param string $classname The event class.
     * @param array $expected The mapping, keyed by 'other' key.
     * @return void
     */
    public function test_other_ids_declare_their_restore_mapping(string $classname, array $expected): void {
        $this->assertSame($expected, $classname::get_other_mapping());
        $this->assertDebuggingNotCalled();
    }

    /**
     * The enrol method events point their objectid at the enrol instance core's restore maps.
     *
     * @return void
     */
    public function test_enrol_method_events_map_the_enrol_instance(): void {
        $expected = ['db' => 'enrol', 'restore' => 'enrol'];

        $this->assertSame($expected, enrol_method_applied::get_objectid_mapping());
        $this->assertSame($expected, enrol_method_removed::get_objectid_mapping());
    }
}
