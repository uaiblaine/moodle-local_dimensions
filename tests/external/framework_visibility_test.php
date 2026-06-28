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

use core_competency\competency_framework;

/**
 * Tests for the set_framework_visibility external function.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\external\set_framework_visibility
 */
final class framework_visibility_test extends \advanced_testcase {
    /**
     * Toggling visibility flips the persisted flag and preserves the scale.
     *
     * @return void
     */
    public function test_toggle_visibility(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework(['visible' => 1]);
        $id = (int) $framework->get('id');
        $scaleid = (int) $framework->get('scaleid');

        $result = set_framework_visibility::execute($id, 0);
        $this->assertTrue($result['success']);
        $reloaded = new competency_framework($id);
        $this->assertSame(0, (int) $reloaded->get('visible'));
        $this->assertSame($scaleid, (int) $reloaded->get('scaleid'));

        set_framework_visibility::execute($id, 1);
        $this->assertSame(1, (int) (new competency_framework($id))->get('visible'));
    }
}
