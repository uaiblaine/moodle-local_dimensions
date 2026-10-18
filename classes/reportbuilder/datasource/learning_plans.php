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

use core\lang_string;
use core_cohort\reportbuilder\local\entities\cohort;
use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\helpers\database;
use local_dimensions\reportbuilder\local\entities\{plan, template, template_extras};

/**
 * Learning plans datasource.
 *
 * Plan-focused datasource bundling `competency_plan` with its template, the
 * template's customfields ({@see template_extras}), the plan owner, and any
 * cohorts linked to the template. Built so report authors can answer "all active plans by user with
 * the template's tag1/type" without writing SQL.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learning_plans extends datasource {
    /**
     * Human-readable name shown in the report-builder UI.
     */
    public static function get_name(): string {
        return get_string('datasource_learning_plans', 'local_dimensions');
    }

    /**
     * Offered only while competencies are enabled on the site.
     */
    public static function is_available(): bool {
        return \core_competency\api::is_enabled();
    }

    /**
     * Wire entities and joins.
     */
    protected function initialise(): void {
        // Main entity: competency_plan.
        $planentity = new plan();
        $planalias = $planentity->get_table_alias('competency_plan');
        $this->set_main_table('competency_plan', $planalias);
        $this->add_entity($planentity);

        // Template entity — LEFT JOIN on plan.templateid.
        $templateentity = new template();
        $templatealias = $templateentity->get_table_alias('competency_template');
        $this->add_entity($templateentity
            ->add_join("LEFT JOIN {competency_template} {$templatealias}
                ON {$templatealias}.id = {$planalias}.templateid"));

        // Template customfields, keyed to the same template rows.
        $extrasentity = new template_extras();
        $this->add_entity($extrasentity
            ->set_table_alias('competency_template', $templatealias)
            ->add_joins($templateentity->get_joins()));

        // Plan owner (core user entity).
        $userentity = new user();
        $useralias = $userentity->get_table_alias('user');
        $this->add_entity($userentity
            ->add_join("LEFT JOIN {user} {$useralias} ON {$useralias}.id = {$planalias}.userid"));

        // The plan's reviewer.
        $reviewerentity = (new user())
            ->set_entity_name('planreviewer')
            ->set_entity_title(new lang_string('entityname_planreviewer', 'local_dimensions'));
        $revieweralias = $reviewerentity->get_table_alias('user');
        $this->add_entity($reviewerentity
            ->add_join("LEFT JOIN {user} {$revieweralias} ON {$revieweralias}.id = {$planalias}.reviewerid"));

        // Cohorts linked to the plan's template via competency_templatecohort; titled apart from
        // the owner's cohorts below. 1:N expansion only triggers if the report uses one of its
        // columns, filters or conditions.
        $cohortentity = (new cohort())
            ->set_entity_title(new lang_string('entityname_templatecohort', 'local_dimensions'));
        $cohortalias = $cohortentity->get_table_alias('cohort');
        $tplcohortalias = database::generate_alias();
        $this->add_entity($cohortentity
            ->add_joins($templateentity->get_joins())
            ->add_joins([
                "LEFT JOIN {competency_templatecohort} {$tplcohortalias}
                    ON {$tplcohortalias}.templateid = {$templatealias}.id",
                "LEFT JOIN {cohort} {$cohortalias} ON {$cohortalias}.id = {$tplcohortalias}.cohortid",
            ]));

        // Cohorts the plan owner belongs to.
        $usercohortentity = (new cohort())
            ->set_entity_name('usercohort')
            ->set_entity_title(new lang_string('entityname_usercohort', 'local_dimensions'));
        $usercohortalias = $usercohortentity->get_table_alias('cohort');
        $memberalias = database::generate_alias();
        $this->add_entity($usercohortentity
            ->add_joins($userentity->get_joins())
            ->add_joins([
                "LEFT JOIN {cohort_members} {$memberalias} ON {$memberalias}.userid = {$useralias}.id",
                "LEFT JOIN {cohort} {$usercohortalias} ON {$usercohortalias}.id = {$memberalias}.cohortid",
            ]));

        $this->add_all_from_entities();
    }

    /**
     * Columns added when a report is first built from this datasource.
     *
     * @return string[]
     */
    public function get_default_columns(): array {
        return [
            'plan:name',
            'plan:status',
            'user:fullname',
            'template:shortname',
        ];
    }

    /**
     * Default initial sort.
     *
     * @return int[]
     */
    public function get_default_column_sorting(): array {
        return [
            'plan:name' => SORT_ASC,
        ];
    }

    /**
     * Default filters offered to a new report built from this datasource.
     *
     * @return string[]
     */
    public function get_default_filters(): array {
        return [
            'plan:status',
            'plan:duedate',
            'template:shortname',
        ];
    }

    /**
     * Default conditions on a new report.
     *
     * @return string[]
     */
    public function get_default_conditions(): array {
        // No template condition: a plan without a template has none, and would fail it.
        return [
            'plan:status',
        ];
    }
}
