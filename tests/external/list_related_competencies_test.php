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
 * Tests for the list_related_competencies external function.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use advanced_testcase;
use core_competency\api;
use core_external\external_api;

/**
 * @covers \local_dimensions\external\list_related_competencies
 */
final class list_related_competencies_test extends advanced_testcase {
    /**
     * The WS returns a competency's related competencies with fields and ancestor path, symmetrically.
     *
     * @return void
     */
    public function test_lists_related_symmetrically_with_path(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $cgen = $generator->get_plugin_generator('core_competency');
        $framework = $cgen->create_framework();
        $parent = $cgen->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'shortname' => 'Parent',
        ]);
        $alpha = $cgen->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'parentid' => $parent->get('id'),
            'shortname' => 'Alpha',
            'idnumber' => 'A-1',
        ]);
        $bravo = $cgen->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'shortname' => 'Bravo',
            'idnumber' => 'B-1',
        ]);
        api::add_related_competency($alpha->get('id'), $bravo->get('id'));

        // Querying alpha returns bravo (a root, so empty path).
        $forward = list_related_competencies::execute($alpha->get('id'));
        $forward = external_api::clean_returnvalue(list_related_competencies::execute_returns(), $forward);
        $this->assertCount(1, $forward['items']);
        $this->assertSame((int) $bravo->get('id'), (int) $forward['items'][0]['id']);
        $this->assertSame('Bravo', $forward['items'][0]['shortname']);
        $this->assertSame('B-1', $forward['items'][0]['idnumber']);
        $this->assertSame('', $forward['items'][0]['path']);

        // Symmetric: querying bravo returns alpha, whose ancestor path is "Parent".
        $reverse = list_related_competencies::execute($bravo->get('id'));
        $reverse = external_api::clean_returnvalue(list_related_competencies::execute_returns(), $reverse);
        $this->assertCount(1, $reverse['items']);
        $this->assertSame((int) $alpha->get('id'), (int) $reverse['items'][0]['id']);
        $this->assertSame('Parent', $reverse['items'][0]['path']);
    }

    /**
     * A competency with no relations returns an empty item list.
     *
     * @return void
     */
    public function test_lists_empty_when_no_relations(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $cgen = $generator->get_plugin_generator('core_competency');
        $framework = $cgen->create_framework();
        $lonely = $cgen->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'shortname' => 'Lonely',
        ]);

        $result = list_related_competencies::execute($lonely->get('id'));
        $result = external_api::clean_returnvalue(list_related_competencies::execute_returns(), $result);
        $this->assertSame([], $result['items']);
    }
}
