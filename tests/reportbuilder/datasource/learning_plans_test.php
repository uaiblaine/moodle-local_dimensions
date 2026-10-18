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
use core_reportbuilder\local\helpers\format;
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
     * The template's cohorts and the owner's cohorts are two entities.
     *
     * @return void
     */
    public function test_template_and_owner_cohorts(): void {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var \core_competency_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $template = $generator->create_template(['shortname' => 'Template']);
        $templatecohort = $this->getDataGenerator()->create_cohort(['name' => 'Linked to the template']);
        $generator->create_template_cohort(['templateid' => $template->get('id'), 'cohortid' => $templatecohort->id]);
        $user = $this->getDataGenerator()->create_user();
        $ownercohort = $this->getDataGenerator()->create_cohort(['name' => 'Holds the owner']);
        cohort_add_member($ownercohort->id, $user->id);
        $generator->create_plan(['name' => 'Plan', 'userid' => $user->id, 'templateid' => $template->get('id')]);

        /** @var \core_reportbuilder_generator $rbgenerator */
        $rbgenerator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $rbgenerator->create_report(['name' => 'Plans', 'source' => learning_plans::class, 'default' => 0]);
        foreach (['plan:name', 'cohort:name', 'usercohort:name'] as $column) {
            $rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => $column]);
        }

        $this->assertEquals(
            [['Plan', 'Linked to the template', 'Holds the owner']],
            array_map('array_values', $this->get_custom_report_content($report->get('id')))
        );
    }

    /**
     * A new report starts with the status condition only, and the plan has no numeric user filter.
     *
     * @return void
     */
    public function test_defaults_and_retired_filter(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var \core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report(['name' => 'Default', 'source' => learning_plans::class, 'default' => 1]);

        $conditions = $DB->get_fieldset('reportbuilder_filter', 'uniqueidentifier', [
            'reportid' => $report->get('id'),
            'iscondition' => 1,
        ]);
        $this->assertSame(['plan:status'], $conditions);
        $this->assertNull(manager::get_report_from_persistent($report)->get_filter('plan:userid'));
    }

    /**
     * The source is offered only while competencies are enabled.
     *
     * @return void
     */
    public function test_is_available(): void {
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $this->assertTrue(learning_plans::is_available());
        set_config('enabled', 0, 'core_competency');
        $this->assertFalse(learning_plans::is_available());
    }

    /**
     * Plan progress, origin and reviewer, and the template's counts.
     *
     * "Linked plan" follows a template with two competencies, one proficient now. "Unlinked plan"
     * was made from the same template and unlinked: it holds one competency itself, is complete,
     * and its archive marks it proficient.
     *
     * @return void
     */
    public function test_plan_and_template_columns(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var \core_competency_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework();
        $alpha = $generator->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $beta = $generator->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $template = $generator->create_template(['shortname' => 'Template']);
        foreach ([$alpha, $beta] as $competency) {
            $generator->create_template_competency([
                'templateid' => $template->get('id'),
                'competencyid' => $competency->get('id'),
            ]);
        }
        $cohort = $this->getDataGenerator()->create_cohort();
        $generator->create_template_cohort(['templateid' => $template->get('id'), 'cohortid' => $cohort->id]);

        $learner = $this->getDataGenerator()->create_user(['firstname' => 'Lea', 'lastname' => 'Learner']);
        $reviewer = $this->getDataGenerator()->create_user(['firstname' => 'Rita', 'lastname' => 'Reviewer']);
        $linked = $generator->create_plan([
            'name' => 'Linked plan',
            'userid' => $learner->id,
            'templateid' => $template->get('id'),
            'reviewerid' => $reviewer->id,
        ]);
        $generator->create_user_competency([
            'userid' => $learner->id,
            'competencyid' => $alpha->get('id'),
            'proficiency' => true,
            'grade' => 1,
        ]);
        $unlinked = $generator->create_plan([
            'name' => 'Unlinked plan',
            'userid' => $learner->id,
            'origtemplateid' => $template->get('id'),
            'status' => \core_competency\plan::STATUS_COMPLETE,
        ]);
        $generator->create_plan_competency(['planid' => $unlinked->get('id'), 'competencyid' => $beta->get('id')]);
        $generator->create_user_competency_plan([
            'userid' => $learner->id,
            'competencyid' => $beta->get('id'),
            'planid' => $unlinked->get('id'),
            'proficiency' => true,
            'grade' => 1,
        ]);

        /** @var \core_reportbuilder_generator $rbgenerator */
        $rbgenerator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $rbgenerator->create_report(['name' => 'Plans', 'source' => learning_plans::class, 'default' => 0]);
        $columns = [
            'plan:name',
            'plan:basedontemplate',
            'plan:origtemplate',
            'plan:competencies',
            'plan:proficient',
            'plan:progress',
            'planreviewer:fullname',
            'template:plans',
            'template:cohorts',
            'template:context',
        ];
        foreach ($columns as $index => $column) {
            $rbgenerator->create_column([
                'reportid' => $report->get('id'),
                'uniqueidentifier' => $column,
                'sortenabled' => (int) ($index === 0),
            ]);
        }
        $rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'plan:namewithlink']);

        $system = \core\context\system::instance()->get_context_name(false);
        $content = array_map('array_values', $this->get_custom_report_content($report->get('id')));
        $links = array_column($content, 10);
        $this->assertEquals([
            ['Linked plan', 'Yes', '', 2, 1, format::percent(50.0), 'Rita Reviewer', 1, 1, $system],
            ['Unlinked plan', 'No', 'Template', 1, 1, format::percent(100.0), '', '', '', ''],
        ], array_map(static fn(array $row): array => array_slice($row, 0, 10), $content));
        $this->assertStringContainsString('/admin/tool/lp/plan.php?id=' . $linked->get('id'), $links[0]);
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
