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

namespace local_dimensions\reportbuilder\local;

use core_reportbuilder\tests\core_reportbuilder_testcase;
use local_dimensions\reportbuilder\datasource\learning_plans;

/**
 * The description columns render a row whose owner or context no longer exists.
 *
 * Core deletes a user's plans with the user, and a category's templates before its context, so
 * such a row is left only by an interrupted deletion; the plans source joins the owner with a LEFT
 * JOIN, so the row still reaches the column, and it must not take the whole report down with it.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\reportbuilder\local\entities\plan
 * @covers     \local_dimensions\reportbuilder\local\entities\template
 */
final class description_missing_context_test extends core_reportbuilder_testcase {
    /**
     * A report of one source with the given columns.
     *
     * @param string $source The datasource class.
     * @param string[] $columns Column identifiers.
     * @return int The report id.
     */
    private function report(string $source, array $columns): int {
        /** @var \core_reportbuilder_generator $rbgenerator */
        $rbgenerator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $rbgenerator->create_report(['name' => 'Report', 'source' => $source, 'default' => 0]);
        foreach ($columns as $column) {
            $rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => $column]);
        }
        return (int) $report->get('id');
    }

    /**
     * Remove a context row the way an interrupted deletion leaves it missing.
     *
     * @param \core\context $context The context.
     * @return void
     */
    private function lose(\core\context $context): void {
        global $DB;
        $DB->delete_records('context', ['id' => $context->id]);
        \core\context_helper::reset_caches();
    }

    /**
     * The plan and template descriptions render with the owner and the category's context gone;
     * before they are gone (the control) they render the same.
     *
     * @return void
     */
    public function test_plan_and_template_descriptions_survive_a_missing_context(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $category = \core\context\coursecat::instance((int) $this->getDataGenerator()->create_category()->id);
        $template = $ccg->create_template(['contextid' => $category->id, 'description' => 'Template text']);
        $user = $this->getDataGenerator()->create_user();
        $ccg->create_plan(['userid' => $user->id, 'templateid' => $template->get('id'), 'description' => 'Plan text']);
        $reportid = $this->report(learning_plans::class, ['plan:description', 'template:description']);

        $expected = [['Plan text', 'Template text']];
        $strip = fn(array $rows): array => array_map(fn($row) => array_map('strip_tags', array_values($row)), $rows);
        $this->assertSame($expected, $strip($this->get_custom_report_content($reportid)), 'Control');

        // The owner's record goes too: core rebuilds a missing user context while the user exists.
        $usercontext = \core\context\user::instance((int) $user->id);
        $DB->delete_records('user', ['id' => $user->id]);
        $this->lose($usercontext);
        $this->lose($category);
        $this->assertSame($expected, $strip($this->get_custom_report_content($reportid)));
    }
}
