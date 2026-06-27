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
 * Tests for the search_competencies external function.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for the search_competencies external function.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_dimensions\external\search_competencies
 */
final class search_competencies_test extends \externallib_advanced_testcase {
    /**
     * Matches on shortname and idnumber, tagged with the framework.
     *
     * @return void
     */
    public function test_search_matches_shortname_and_idnumber(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $gen->create_framework(['shortname' => 'FW', 'idnumber' => 'FWID']);
        $fwid = (int) $framework->get('id');
        $alpha = $gen->create_competency(['competencyframeworkid' => $fwid, 'shortname' => 'Alpha skill', 'idnumber' => 'A-100']);
        $beta = $gen->create_competency(['competencyframeworkid' => $fwid, 'shortname' => 'Beta skill', 'idnumber' => 'B-200']);

        $byname = search_competencies::execute('Alpha', 0, 25);
        $byname = \core_external\external_api::clean_returnvalue(search_competencies::execute_returns(), $byname);
        $this->assertSame(1, $byname['total']);
        $this->assertSame((int) $alpha->get('id'), $byname['items'][0]['id']);
        $this->assertSame('FWID', $byname['items'][0]['frameworktag']);

        $byidnumber = search_competencies::execute('B-200', 0, 25);
        $byidnumber = \core_external\external_api::clean_returnvalue(search_competencies::execute_returns(), $byidnumber);
        $this->assertSame(1, $byidnumber['total']);
        $this->assertSame((int) $beta->get('id'), $byidnumber['items'][0]['id']);
    }

    /**
     * A query shorter than the minimum returns nothing (avoids scanning everything).
     *
     * @return void
     */
    public function test_short_query_returns_nothing(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $gen->create_framework(['shortname' => 'FW']);
        $gen->create_competency([
            'competencyframeworkid' => (int) $framework->get('id'),
            'shortname' => 'Alpha',
            'idnumber' => 'A1',
        ]);

        $result = \core_external\external_api::clean_returnvalue(
            search_competencies::execute_returns(),
            search_competencies::execute('A', 0, 25)
        );
        $this->assertSame(0, $result['total']);
        $this->assertEmpty($result['items']);
    }

    /**
     * Total reflects all matches while items honour the page size.
     *
     * @return void
     */
    public function test_pagination(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $gen->create_framework(['shortname' => 'FW']);
        $fwid = (int) $framework->get('id');
        for ($i = 0; $i < 5; $i++) {
            $gen->create_competency(['competencyframeworkid' => $fwid, 'shortname' => "Match $i", 'idnumber' => "M-$i"]);
        }

        $page = search_competencies::execute('Match', 0, 2);
        $page = \core_external\external_api::clean_returnvalue(search_competencies::execute_returns(), $page);
        $this->assertSame(5, $page['total']);
        $this->assertCount(2, $page['items']);
    }

    /**
     * A user without competencyview cannot search.
     *
     * @return void
     */
    public function test_requires_competencyview_capability(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->expectException(\required_capability_exception::class);
        search_competencies::execute('Alpha', 0, 25);
    }
}
