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
 * Tests for the search_structure external function.
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
 * Tests for the search_structure external function.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_dimensions\external\search_structure
 */
final class search_structure_test extends \externallib_advanced_testcase {
    /**
     * Helper: clean a raw execute() return value through the declared return structure.
     *
     * @param array $raw Raw return value from search_structure::execute().
     * @return array Cleaned result.
     */
    private function clean(array $raw): array {
        return \core_external\external_api::clean_returnvalue(search_structure::execute_returns(), $raw);
    }

    /**
     * A 3-level chain returns the grandchild with full ancestor pathids and path string.
     *
     * @return void
     */
    public function test_ancestor_path_for_grandchild(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator()->get_plugin_generator('core_competency');

        $fw1 = $gen->create_framework(['shortname' => 'FW1', 'idnumber' => 'FW1']);
        $fwid = (int) $fw1->get('id');

        $root = $gen->create_competency([
            'competencyframeworkid' => $fwid,
            'shortname' => 'Root competency',
            'idnumber' => 'R-001',
        ]);
        $rid = (int) $root->get('id');

        $child = $gen->create_competency([
            'competencyframeworkid' => $fwid,
            'shortname' => 'Child competency',
            'idnumber' => 'C-001',
            'parentid' => $rid,
        ]);
        $cid = (int) $child->get('id');

        $grandchild = $gen->create_competency([
            'competencyframeworkid' => $fwid,
            'shortname' => 'Grandchild unique xyz',
            'idnumber' => 'G-001',
            'parentid' => $cid,
        ]);
        $gid = (int) $grandchild->get('id');

        $result = $this->clean(search_structure::execute($fwid, 'unique xyz', 0, 25));

        $this->assertSame(1, $result['total']);
        $this->assertCount(1, $result['items']);

        $item = $result['items'][0];
        $this->assertSame($gid, $item['id']);
        // Pathids must be root then parent (no own id, no sentinel 0).
        $this->assertSame([$rid, $cid], $item['pathids']);
        // Path string must contain both ancestor shortnames.
        $this->assertStringContainsString('Root competency', $item['path']);
        $this->assertStringContainsString('Child competency', $item['path']);
    }

    /**
     * A root-level competency has empty pathids and an empty path string.
     *
     * @return void
     */
    public function test_root_competency_has_empty_path(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator()->get_plugin_generator('core_competency');

        $fw = $gen->create_framework(['shortname' => 'FW2', 'idnumber' => 'FW2']);
        $fwid = (int) $fw->get('id');
        $root = $gen->create_competency([
            'competencyframeworkid' => $fwid,
            'shortname' => 'Solo root alpha',
            'idnumber' => 'SR-001',
        ]);

        $result = $this->clean(search_structure::execute($fwid, 'Solo root', 0, 25));

        $this->assertSame(1, $result['total']);
        $item = $result['items'][0];
        $this->assertSame((int) $root->get('id'), $item['id']);
        $this->assertSame([], $item['pathids']);
        $this->assertSame('', $item['path']);
    }

    /**
     * Search is scoped to the given framework; a matching competency in a different framework is excluded.
     *
     * @return void
     */
    public function test_scope_excludes_other_framework(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator()->get_plugin_generator('core_competency');

        $fw1 = $gen->create_framework(['shortname' => 'FW1scope', 'idnumber' => 'FW1scope']);
        $fw2 = $gen->create_framework(['shortname' => 'FW2scope', 'idnumber' => 'FW2scope']);
        $fw1id = (int) $fw1->get('id');
        $fw2id = (int) $fw2->get('id');

        // A matching competency in FW1.
        $gen->create_competency([
            'competencyframeworkid' => $fw1id,
            'shortname' => 'Shared term competency',
            'idnumber' => 'ST-001',
        ]);
        // A matching competency in FW2 — must NOT appear in FW1-scoped search.
        $gen->create_competency([
            'competencyframeworkid' => $fw2id,
            'shortname' => 'Shared term competency',
            'idnumber' => 'ST-002',
        ]);

        $result = $this->clean(search_structure::execute($fw1id, 'Shared term', 0, 25));

        $this->assertSame(1, $result['total']);
        $this->assertCount(1, $result['items']);
        $this->assertSame('ST-001', $result['items'][0]['idnumber']);
    }

    /**
     * A query shorter than the minimum length returns no results.
     *
     * @return void
     */
    public function test_short_query_returns_nothing(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $fw = $gen->create_framework(['shortname' => 'FW3', 'idnumber' => 'FW3']);
        $fwid = (int) $fw->get('id');
        $gen->create_competency([
            'competencyframeworkid' => $fwid,
            'shortname' => 'Alpha',
            'idnumber' => 'A1',
        ]);

        $result = $this->clean(search_structure::execute($fwid, 'A', 0, 25));

        $this->assertSame(0, $result['total']);
        $this->assertEmpty($result['items']);
    }

    /**
     * A user without competencyview cannot use this function.
     *
     * @return void
     */
    public function test_requires_competencyview_capability(): void {
        $this->resetAfterTest();
        $context = \context_system::instance();
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        role_assign($roleid, $user->id, $context->id);
        assign_capability('moodle/competency:competencyview', CAP_PROHIBIT, $roleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($user);

        $gen = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $fw = $gen->create_framework(['shortname' => 'FWcap', 'idnumber' => 'FWcap']);

        $this->expectException(\required_capability_exception::class);
        search_structure::execute((int) $fw->get('id'), 'anything', 0, 25);
    }
}
