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
 * Tests for the export_framework external function.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\external\export_framework
 */
final class export_framework_test extends \advanced_testcase {
    /**
     * Export a system-context framework and check the returned CSV.
     *
     * @return void
     */
    public function test_export_system_framework(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework(['shortname' => 'FW', 'idnumber' => 'FW-1']);
        $ccg->create_competency(['competencyframeworkid' => (int) $framework->get('id'), 'idnumber' => 'C1']);

        $result = export_framework::execute((int) $framework->get('id'));
        $result = \core_external\external_api::clean_returnvalue(export_framework::execute_returns(), $result);

        $this->assertStringEndsWith('.csv', $result['filename']);
        $this->assertStringContainsString('parentidnumber', $result['content']);
        $this->assertStringContainsString('FW-1', $result['content']);
        $this->assertStringContainsString('C1', $result['content']);
    }

    /**
     * A framework living in a course-category context exports under that context.
     *
     * @return void
     */
    public function test_export_coursecat_framework(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        $catcontext = \context_coursecat::instance($category->id);
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework([
            'shortname' => 'CatFW',
            'idnumber' => 'CAT-1',
            'contextid' => $catcontext->id,
        ]);

        $result = export_framework::execute((int) $framework->get('id'));
        $result = \core_external\external_api::clean_returnvalue(export_framework::execute_returns(), $result);
        $this->assertStringContainsString('CAT-1', $result['content']);
    }
}
