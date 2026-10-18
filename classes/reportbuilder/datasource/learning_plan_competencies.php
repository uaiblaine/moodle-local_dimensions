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
use core_competency\reportbuilder\local\entities\{competency, framework};
use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\helpers\database;
use local_dimensions\reportbuilder\local\entities\{competency_details, competency_extras, plan, plan_competency, template,
    template_extras, usercompetency};

/**
 * Learning plan competencies datasource.
 *
 * One row per learning plan and competency of that plan: its template's competencies or, for a
 * plan without a template, its own. Beside the plan, its template with its Dimensions custom
 * fields, the owner and the owner's cohorts, it gives the competency with its framework and
 * custom fields, its proficiency and rating in the plan (archived when the plan is complete),
 * and the owner's current user competency.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learning_plan_competencies extends datasource {
    /**
     * Human-readable name shown in the report-builder UI.
     */
    #[\Override]
    public static function get_name(): string {
        return get_string('datasource_learning_plan_competencies', 'local_dimensions');
    }

    /**
     * Offered only while competencies are enabled on the site.
     */
    #[\Override]
    public static function is_available(): bool {
        return \core_competency\api::is_enabled();
    }

    /**
     * Wire entities and joins.
     */
    #[\Override]
    protected function initialise(): void {
        $planentity = new plan();
        $planalias = $planentity->get_table_alias('competency_plan');
        $this->set_main_table('competency_plan', $planalias);
        $this->add_entity($planentity);

        $templateentity = new template();
        $templatealias = $templateentity->get_table_alias('competency_template');
        $this->add_entity($templateentity
            ->add_join("LEFT JOIN {competency_template} {$templatealias}
                ON {$templatealias}.id = {$planalias}.templateid"));

        $templateextras = new template_extras();
        $this->add_entity($templateextras
            ->set_table_alias('competency_template', $templatealias)
            ->add_joins($templateentity->get_joins()));

        $userentity = new user();
        $useralias = $userentity->get_table_alias('user');
        $this->add_entity($userentity
            ->add_join("JOIN {user} {$useralias} ON {$useralias}.id = {$planalias}.userid"));

        // The plan's reviewer.
        $reviewerentity = (new user())
            ->set_entity_name('planreviewer')
            ->set_entity_title(new lang_string('entityname_planreviewer', 'local_dimensions'));
        $revieweralias = $reviewerentity->get_table_alias('user');
        $this->add_entity($reviewerentity
            ->add_join("LEFT JOIN {user} {$revieweralias} ON {$revieweralias}.id = {$planalias}.reviewerid"));

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

        // The plan's competencies: its own, or its template's, as one derived table.
        $plancompetencyentity = new plan_competency();
        $linkalias = $plancompetencyentity->get_table_alias('competency_plancomp');
        $archivealias = $plancompetencyentity->get_table_alias('competency_usercompplan');
        $usercompetencyentity = new usercompetency();
        $usercompetencyalias = $usercompetencyentity->get_table_alias('competency_usercomp');
        $linkjoin = "JOIN (
                SELECT pc.planid, pc.competencyid, pc.sortorder, 0 AS fromtemplate
                  FROM {competency_plancomp} pc
                 UNION ALL
                SELECT tp.id AS planid, tc.competencyid, tc.sortorder, 1 AS fromtemplate
                  FROM {competency_plan} tp
                  JOIN {competency_templatecomp} tc ON tc.templateid = tp.templateid
            ) {$linkalias} ON {$linkalias}.planid = {$planalias}.id";
        $usercompetencyjoin = "LEFT JOIN {competency_usercomp} {$usercompetencyalias}
            ON {$usercompetencyalias}.userid = {$planalias}.userid
           AND {$usercompetencyalias}.competencyid = {$linkalias}.competencyid";
        $this->add_join($linkjoin);

        $this->add_entity($plancompetencyentity
            ->set_table_alias('competency_plan', $planalias)
            ->set_table_alias('competency_usercomp', $usercompetencyalias)
            ->add_joins([
                "LEFT JOIN {competency_usercompplan} {$archivealias}
                    ON {$archivealias}.planid = {$planalias}.id
                   AND {$archivealias}.competencyid = {$linkalias}.competencyid",
                $usercompetencyjoin,
            ]));

        $this->add_entity($usercompetencyentity->add_join($usercompetencyjoin));

        // Competency and framework (core), sharing the framework's context.
        $competencyentity = new competency();
        $competencyalias = $competencyentity->get_table_alias('competency');
        $frameworkentity = new framework();
        $frameworkalias = $frameworkentity->get_table_alias('competency_framework');
        $frameworkcontextalias = $frameworkentity->get_table_alias('context');
        $competencyjoins = [
            "JOIN {competency} {$competencyalias} ON {$competencyalias}.id = {$linkalias}.competencyid",
            "JOIN {competency_framework} {$frameworkalias}
                ON {$frameworkalias}.id = {$competencyalias}.competencyframeworkid",
            "LEFT JOIN {context} {$frameworkcontextalias} ON {$frameworkcontextalias}.id = {$frameworkalias}.contextid",
        ];
        $this->add_entity($competencyentity
            ->set_table_join_alias('context', $frameworkcontextalias)
            ->add_joins($competencyjoins));
        $this->add_entity($frameworkentity
            ->set_table_join_alias('context', $frameworkcontextalias)
            ->add_joins($competencyjoins));

        $competencyextras = new competency_extras();
        $this->add_entity($competencyextras
            ->set_table_alias('competency', $competencyalias)
            ->add_joins($competencyjoins));

        // Hierarchy, scale and links of the competency.
        $detailsentity = new competency_details();
        $this->add_entity($detailsentity
            ->set_table_alias('competency', $competencyalias)
            ->add_joins($competencyjoins));

        $this->add_all_from_entities();
    }

    /**
     * Columns added when a report is first built from this datasource.
     *
     * @return string[]
     */
    #[\Override]
    public function get_default_columns(): array {
        return [
            'user:fullname',
            'plan:name',
            'plan:status',
            'competency:name',
            'plan_competency:proficient',
            'plan_competency:rating',
        ];
    }

    /**
     * Default initial sort.
     *
     * @return int[]
     */
    #[\Override]
    public function get_default_column_sorting(): array {
        return [
            'user:fullname' => SORT_ASC,
            'plan:name' => SORT_ASC,
            'competency:name' => SORT_ASC,
        ];
    }

    /**
     * Default filters offered to a new report built from this datasource.
     *
     * @return string[]
     */
    #[\Override]
    public function get_default_filters(): array {
        return [
            'user:fullname',
            'plan:status',
            'competency:name',
            'plan_competency:proficient',
        ];
    }

    /**
     * Default conditions on a new report.
     *
     * @return string[]
     */
    #[\Override]
    public function get_default_conditions(): array {
        return [
            'plan:status',
            'framework:name',
        ];
    }
}
