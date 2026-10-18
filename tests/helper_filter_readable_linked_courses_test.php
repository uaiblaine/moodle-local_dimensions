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
 * Tests for the viewer filter shared by the tracker page and the accordion service.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversMethod(\local_dimensions\helper::class, 'filter_readable_linked_courses')]
final class helper_filter_readable_linked_courses_test extends \advanced_testcase {
    /**
     * A linked course in a category the viewer cannot browse is dropped; a control course is kept.
     *
     * @return void
     */
    public function test_a_course_the_viewer_cannot_browse_is_omitted(): void {
        global $DB;
        $this->resetAfterTest();
        $dg = $this->getDataGenerator();
        $lpg = $dg->get_plugin_generator('core_competency');
        $framework = $lpg->create_framework();
        $competency = $lpg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $category = $dg->create_category();
        assign_capability(
            'moodle/category:viewcourselist',
            CAP_PROHIBIT,
            (int) $DB->get_field('role', 'id', ['archetype' => 'user'], MUST_EXIST),
            \context_coursecat::instance($category->id)->id,
            true
        );
        $blocked = $dg->create_course(['category' => $category->id]);
        $open = $dg->create_course();
        foreach ([$blocked, $open] as $course) {
            $lpg->create_course_competency(['competencyid' => $competency->get('id'), 'courseid' => $course->id]);
        }
        $rows = [
            (int) $blocked->id => (object) ['id' => $blocked->id],
            (int) $open->id => (object) ['id' => $open->id],
        ];

        $this->setUser($dg->create_user());
        $this->assertSame([(int) $open->id], array_keys(helper::filter_readable_linked_courses($rows)));

        $this->setAdminUser();
        $this->assertSame(
            [(int) $blocked->id, (int) $open->id],
            array_keys(helper::filter_readable_linked_courses($rows))
        );
    }
}
