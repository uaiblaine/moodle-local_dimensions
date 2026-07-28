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
 * Tests for the export_templates external function.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\external\export_templates
 */
final class export_templates_test extends \advanced_testcase {
    /**
     * Export one system-context template and check the returned CSV.
     *
     * @return void
     */
    public function test_export_single_template(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework(['shortname' => 'FW', 'idnumber' => 'FW-1']);
        $competency = $ccg->create_competency([
            'competencyframeworkid' => (int) $framework->get('id'),
            'idnumber' => 'C1',
        ]);
        $template = $ccg->create_template(['shortname' => 'Nursing']);
        $ccg->create_template_competency([
            'templateid' => (int) $template->get('id'),
            'competencyid' => (int) $competency->get('id'),
        ]);

        $systemid = \context_system::instance()->id;
        $result = export_templates::execute((string) (int) $template->get('id'), $systemid);
        $result = \core_external\external_api::clean_returnvalue(export_templates::execute_returns(), $result);

        $this->assertStringEndsWith('.csv', $result['filename']);
        $this->assertStringContainsString('rowtype', $result['content']);
        $this->assertStringContainsString('Nursing', $result['content']);
        $this->assertStringContainsString('C1', $result['content']);
        // The referenced framework comes back declared, so the modal can offer it as a download.
        $this->assertCount(1, $result['frameworks']);
        $this->assertSame('FW-1', $result['frameworks'][0]['idnumber']);
    }

    /**
     * Two templates in two different course categories export in one call.
     *
     * validate_context() must run only once, on the requesting hub context: calling it per
     * template would change $PAGE's context twice away from a course category and emit an
     * unexpected debugging() notice, which fails PHPUnit under --fail-on-warning.
     *
     * @return void
     */
    public function test_export_two_templates_in_two_categories(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $first = $this->getDataGenerator()->create_category(['name' => 'One']);
        $second = $this->getDataGenerator()->create_category(['name' => 'Two']);
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $alpha = $ccg->create_template([
            'shortname' => 'Alpha',
            'contextid' => \context_coursecat::instance((int) $first->id)->id,
        ]);
        $beta = $ccg->create_template([
            'shortname' => 'Beta',
            'contextid' => \context_coursecat::instance((int) $second->id)->id,
        ]);

        $ids = (int) $alpha->get('id') . ',' . (int) $beta->get('id');
        $result = export_templates::execute($ids, \context_system::instance()->id);
        $result = \core_external\external_api::clean_returnvalue(export_templates::execute_returns(), $result);

        $this->assertSame('learningplantemplates.csv', $result['filename']);
        $this->assertStringContainsString('Alpha', $result['content']);
        $this->assertStringContainsString('Beta', $result['content']);
        $this->assertStringContainsString('One', $result['content']);
        $this->assertStringContainsString('Two', $result['content']);
    }

    /**
     * A user without moodle/competency:templateview cannot export.
     *
     * @return void
     */
    public function test_export_requires_the_view_capability(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $template = $ccg->create_template(['shortname' => 'Guarded']);

        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\required_capability_exception::class);
        export_templates::execute((string) (int) $template->get('id'), \context_system::instance()->id);
    }

    /**
     * An empty or unusable id list is refused with a readable message, never an empty file.
     *
     * @return void
     */
    public function test_export_refuses_an_empty_selection(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->expectException(\moodle_exception::class);
        export_templates::execute('', \context_system::instance()->id);
    }
}
