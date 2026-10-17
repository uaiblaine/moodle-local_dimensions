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

declare(strict_types=1);

namespace local_dimensions\reportbuilder\datasource;

use core_reportbuilder\local\filters\text;
use core_reportbuilder\manager;
use core_reportbuilder\tests\core_reportbuilder_testcase;
use local_dimensions\constants;
use local_dimensions\customfield\lp_handler;
use local_dimensions\helper;

/**
 * Tests for the learning plans report source.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\reportbuilder\datasource\learning_plans
 * @covers     \local_dimensions\reportbuilder\local\entities\template_extras
 */
final class learning_plans_test extends core_reportbuilder_testcase {
    /** @var string The template customfield the tests read. */
    private const COLUMN = 'customfield_' . constants::CFIELD_TEMPLATE_IDNUMBER;

    /**
     * Two template-based plans, one per template, each template with its own identifier.
     *
     * @return void
     */
    private function create_plans(): void {
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_LP);

        /** @var \core_competency_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        foreach (['Alpha' => 'TPL-A', 'Beta' => 'TPL-B'] as $name => $idnumber) {
            $template = $generator->create_template(['shortname' => "Template {$name}"]);
            lp_handler::create()->instance_form_save((object) [
                'id' => $template->get('id'),
                'customfield_' . constants::CFIELD_TEMPLATE_IDNUMBER => $idnumber,
            ], true);
            $user = $this->getDataGenerator()->create_user();
            $generator->create_plan([
                'name' => "Plan {$name}",
                'userid' => $user->id,
                'templateid' => $template->get('id'),
            ]);
        }
    }

    /**
     * The template customfields are columns of `template_extras`, no longer of `template`.
     *
     * @return void
     */
    public function test_template_customfields_live_in_their_own_entity(): void {
        $this->resetAfterTest();
        $this->create_plans();

        /** @var \core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report(['name' => 'Plans', 'source' => learning_plans::class, 'default' => 0]);
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'plan:name', 'sortenabled' => 1]);
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'template_extras:' . self::COLUMN]);

        $this->assertEquals([
            ['Plan Alpha', 'TPL-A'],
            ['Plan Beta', 'TPL-B'],
        ], array_map('array_values', $this->get_custom_report_content($report->get('id'))));

        $source = manager::get_report_from_persistent($report);
        $this->assertNotNull($source->get_column('template_extras:' . self::COLUMN));
        $this->assertNull($source->get_column('template:' . self::COLUMN));
        $this->assertNotNull($source->get_filter('template_extras:' . self::COLUMN));
        $this->assertNull($source->get_filter('template:' . self::COLUMN));
    }

    /**
     * A condition on a template customfield narrows the plans to the matching template.
     *
     * @return void
     */
    public function test_template_customfield_condition(): void {
        $this->resetAfterTest();
        $this->create_plans();

        /** @var \core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report(['name' => 'Plans', 'source' => learning_plans::class, 'default' => 0]);
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'plan:name']);
        $condition = 'template_extras:' . self::COLUMN;
        $generator->create_condition(['reportid' => $report->get('id'), 'uniqueidentifier' => $condition]);

        $source = manager::get_report_from_persistent($report);
        $source->set_condition_values([
            "{$condition}_operator" => text::IS_EQUAL_TO,
            "{$condition}_value" => 'TPL-B',
        ]);

        $this->assertEquals([['Plan Beta']], array_map('array_values', $this->get_custom_report_content($report->get('id'))));
    }

    /**
     * The fields that only style or steer the learner pages are not offered; the others are.
     *
     * The control: a field an administrator adds to the plugin's category is offered.
     *
     * @return void
     */
    public function test_presentation_fields_are_not_offered(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_LP);
        $categoryid = (int) $DB->get_field_sql(
            "SELECT MIN(id) FROM {customfield_category} WHERE component = ? AND area = ?",
            ['local_dimensions', helper::AREA_LP]
        );
        $this->getDataGenerator()->get_plugin_generator('core_customfield')->create_field([
            'categoryid' => $categoryid,
            'shortname' => 'workload',
            'type' => 'text',
        ]);
        lp_handler::create()->reset_configuration_cache();

        /** @var \core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report(['name' => 'Plans', 'source' => learning_plans::class, 'default' => 0]);
        $source = manager::get_report_from_persistent($report);

        foreach (constants::REPORT_HIDDEN_CFIELDS as $shortname) {
            $this->assertNull($source->get_column("template_extras:customfield_{$shortname}"), $shortname);
            $this->assertNull($source->get_filter("template_extras:customfield_{$shortname}"), $shortname);
        }
        foreach ([constants::CFIELD_TAG1, constants::CFIELD_TAG2, constants::CFIELD_TYPE, 'workload'] as $shortname) {
            $this->assertNotNull($source->get_column("template_extras:customfield_{$shortname}"), $shortname);
            $this->assertNotNull($source->get_filter("template_extras:customfield_{$shortname}"), $shortname);
        }
    }

    /**
     * Every column, aggregation and condition of the source builds a report that runs.
     *
     * @return void
     */
    public function test_stress_datasource(): void {
        if (!PHPUNIT_LONGTEST) {
            $this->markTestSkipped('PHPUNIT_LONGTEST is not defined');
        }
        $this->resetAfterTest();
        $this->create_plans();

        $this->datasource_stress_test_columns(learning_plans::class);
        $this->datasource_stress_test_columns_aggregation(learning_plans::class);
        $this->datasource_stress_test_conditions(learning_plans::class, 'plan:name');
    }
}
