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

use core_competency\reportbuilder\local\entities\{competency, framework};
use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\helpers\database;
use local_dimensions\reportbuilder\local\entities\{competency_details, competency_extras, user_evidence, usercompetency};

/**
 * Evidence of prior learning datasource.
 *
 * One row per evidence of prior learning (`competency_userevidence`) and competency it is
 * linked to: the user who submitted it, the competency with its framework and Dimensions custom
 * fields, and that user's competency (status, proficiency, rating). An evidence linked to no
 * competency keeps one row with the competency columns empty.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class competency_user_evidence extends datasource {
    /**
     * Human-readable name shown in the report-builder UI.
     */
    public static function get_name(): string {
        return get_string('datasource_competency_user_evidence', 'local_dimensions');
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
        $evidenceentity = new user_evidence();
        $evidencealias = $evidenceentity->get_table_alias('competency_userevidence');
        $this->set_main_table('competency_userevidence', $evidencealias);
        $this->add_entity($evidenceentity);

        $userentity = new user();
        $useralias = $userentity->get_table_alias('user');
        $this->add_entity($userentity
            ->add_join("JOIN {user} {$useralias} ON {$useralias}.id = {$evidencealias}.userid"));

        // Competency and framework (core), sharing the framework's context.
        $competencyentity = new competency();
        $competencyalias = $competencyentity->get_table_alias('competency');
        $frameworkentity = new framework();
        $frameworkalias = $frameworkentity->get_table_alias('competency_framework');
        $frameworkcontextalias = $frameworkentity->get_table_alias('context');
        $linkalias = database::generate_alias();
        $competencyjoins = [
            "LEFT JOIN {competency_userevidencecomp} {$linkalias} ON {$linkalias}.userevidenceid = {$evidencealias}.id",
            "LEFT JOIN {competency} {$competencyalias} ON {$competencyalias}.id = {$linkalias}.competencyid",
            "LEFT JOIN {competency_framework} {$frameworkalias}
                ON {$frameworkalias}.id = {$competencyalias}.competencyframeworkid",
            "LEFT JOIN {context} {$frameworkcontextalias} ON {$frameworkcontextalias}.id = {$frameworkalias}.contextid",
        ];
        $this->add_entity($competencyentity
            ->set_table_join_alias('context', $frameworkcontextalias)
            ->add_joins($competencyjoins));
        $this->add_entity($frameworkentity
            ->set_table_join_alias('context', $frameworkcontextalias)
            ->add_joins($competencyjoins));

        $extrasentity = new competency_extras();
        $this->add_entity($extrasentity
            ->set_table_alias('competency', $competencyalias)
            ->add_joins($competencyjoins));

        // Hierarchy, scale and links of the competency.
        $detailsentity = new competency_details();
        $this->add_entity($detailsentity
            ->set_table_alias('competency', $competencyalias)
            ->add_joins($competencyjoins));

        // The submitter's competency: where the evidence stands in their review.
        $usercompetencyentity = new usercompetency();
        $usercompetencyalias = $usercompetencyentity->get_table_alias('competency_usercomp');
        $this->add_entity($usercompetencyentity
            ->add_joins($competencyjoins)
            ->add_join("LEFT JOIN {competency_usercomp} {$usercompetencyalias}
                ON {$usercompetencyalias}.userid = {$evidencealias}.userid
               AND {$usercompetencyalias}.competencyid = {$linkalias}.competencyid"));

        $this->add_all_from_entities();
    }

    /**
     * Columns added when a report is first built from this datasource.
     *
     * @return string[]
     */
    public function get_default_columns(): array {
        return [
            'user:fullname',
            'user_evidence:name',
            'user_evidence:files',
            'competency:name',
            'usercompetency:status',
            'user_evidence:timecreated',
        ];
    }

    /**
     * Default initial sort.
     *
     * @return int[]
     */
    public function get_default_column_sorting(): array {
        return [
            'user:fullname' => SORT_ASC,
            'user_evidence:name' => SORT_ASC,
        ];
    }

    /**
     * Default filters offered to a new report built from this datasource.
     *
     * @return string[]
     */
    public function get_default_filters(): array {
        return [
            'user:fullname',
            'competency:name',
            'usercompetency:status',
            'user_evidence:timecreated',
        ];
    }

    /**
     * Default conditions on a new report.
     *
     * @return string[]
     */
    public function get_default_conditions(): array {
        return [
            'user_evidence:timecreated',
        ];
    }
}
