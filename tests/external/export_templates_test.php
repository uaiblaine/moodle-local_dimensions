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
     * template would move $PAGE from one course category context to another, which
     * moodle_page::set_context() reports with a debugging() notice the test does not expect.
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

        try {
            export_templates::execute('', \context_system::instance()->id);
            $this->fail('An empty selection was exported.');
        } catch (\moodle_exception $e) {
            $this->assertSame('central_plans_export_none', $e->errorcode);
        }
    }

    /**
     * A user holding the given capabilities in a context through a role of their own.
     *
     * @param string[] $caps Capabilities allowed.
     * @param \context $context Where the role is assigned.
     * @return int The user id.
     */
    private function user_with(array $caps, \context $context): int {
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        foreach ($caps as $cap) {
            assign_capability($cap, CAP_ALLOW, $roleid, $context->id);
        }
        role_assign($roleid, (int) $user->id, $context->id);
        return (int) $user->id;
    }

    /**
     * A hidden template is exported only to those who may manage it; a visible one to any viewer.
     *
     * @return void
     */
    public function test_hidden_template_needs_the_manage_capability(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        $catcontext = \context_coursecat::instance((int) $category->id);
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $hidden = $ccg->create_template(['shortname' => 'SecretPlan', 'visible' => 0, 'contextid' => $catcontext->id]);
        $shown = $ccg->create_template(['shortname' => 'PublicPlan', 'visible' => 1, 'contextid' => $catcontext->id]);
        $ids = (int) $hidden->get('id') . ',' . (int) $shown->get('id');

        $viewer = $this->user_with(['moodle/competency:templateview'], $catcontext);
        $this->setUser($viewer);
        $result = export_templates::execute($ids, $catcontext->id);
        $this->assertStringNotContainsString('SecretPlan', $result['content']);
        // The control: the visible template still exports to the same viewer.
        $this->assertStringContainsString('PublicPlan', $result['content']);

        $manager = $this->user_with(
            ['moodle/competency:templateview', 'moodle/competency:templatemanage'],
            $catcontext
        );
        $this->setUser($manager);
        $result = export_templates::execute($ids, $catcontext->id);
        $this->assertStringContainsString('SecretPlan', $result['content']);
        $this->assertStringContainsString('PublicPlan', $result['content']);
    }

    /**
     * A framework the viewer cannot read is not described in the export; the competency rows stay.
     *
     * @return void
     */
    public function test_unreadable_framework_metadata_is_withheld(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        $catcontext = \context_coursecat::instance((int) $category->id);
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework([
            'shortname' => 'HiddenFwName',
            'idnumber' => 'FW-HIDDEN',
            'contextid' => $catcontext->id,
        ]);
        $competency = $ccg->create_competency([
            'competencyframeworkid' => (int) $framework->get('id'),
            'idnumber' => 'C-KEEP',
        ]);
        $template = $ccg->create_template(['shortname' => 'Plan']);
        $ccg->create_template_competency([
            'templateid' => (int) $template->get('id'),
            'competencyid' => (int) $competency->get('id'),
        ]);
        $systemcontext = \context_system::instance();

        // The control: a viewer who can read the framework gets its metadata.
        $reader = $this->user_with(['moodle/competency:templateview', 'moodle/competency:competencyview'], $systemcontext);
        $this->setUser($reader);
        $result = export_templates::execute((string) (int) $template->get('id'), $systemcontext->id);
        $this->assertStringContainsString('FW-HIDDEN', $result['content']);
        $this->assertCount(1, $result['frameworks']);

        // Prohibited at the framework's category, the same viewer gets the competency but not the framework.
        $blocked = $this->user_with(['moodle/competency:templateview'], $systemcontext);
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/competency:competencyview', CAP_PROHIBIT, $roleid, $catcontext->id);
        role_assign($roleid, $blocked, $systemcontext->id);
        $this->setUser($blocked);
        $result = export_templates::execute((string) (int) $template->get('id'), $systemcontext->id);
        $this->assertStringContainsString('C-KEEP', $result['content']);
        $this->assertStringNotContainsString('FW-HIDDEN', $result['content']);
        $this->assertStringNotContainsString('HiddenFwName', $result['content']);
        $this->assertSame([], $result['frameworks']);
    }
}
