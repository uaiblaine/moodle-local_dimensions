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

namespace local_dimensions\external;

/**
 * Tests for the search_linkable_courses external function.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\external\search_linkable_courses
 */
final class linkable_courses_test extends \advanced_testcase {
    /**
     * As admin, the picker returns matching courses and excludes already-linked ones.
     *
     * @return void
     */
    public function test_search_excludes_linked(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework(['visible' => 1]);
        $competency = $ccg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $competencyid = (int) $competency->get('id');
        $linked = $this->getDataGenerator()->create_course(['fullname' => 'Alpha linked']);
        $free = $this->getDataGenerator()->create_course(['fullname' => 'Alpha free']);

        link_competency_course::execute($competencyid, (int) $linked->id);

        $result = search_linkable_courses::execute($competencyid, 'Alpha', 0, 25);
        $ids = array_map(static fn($item): int => (int) $item['id'], $result['items']);
        $this->assertContains((int) $free->id, $ids);
        $this->assertNotContains((int) $linked->id, $ids);
    }
}
