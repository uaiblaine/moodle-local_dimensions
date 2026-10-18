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
use core\reportbuilder\local\entities\context;
use core_competency\reportbuilder\local\entities\{competency, framework};
use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\{course, user};
use local_dimensions\reportbuilder\local\entities\{competency_details, competency_extras, evidence, usercompetency};

/**
 * Competency evidence datasource.
 *
 * One row per evidence (`competency_evidence`): the user competency it belongs to, with its
 * competency, framework and Dimensions custom fields, the learner, the user who caused it, and
 * where it came from (its context and, for a course or activity context, the course).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class competency_evidence extends datasource {
    /**
     * Human-readable name shown in the report-builder UI.
     */
    public static function get_name(): string {
        return get_string('datasource_competency_evidence', 'local_dimensions');
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
        $evidenceentity = new evidence();
        $evidencealias = $evidenceentity->get_table_alias('competency_evidence');
        $this->set_main_table('competency_evidence', $evidencealias);
        $this->add_entity($evidenceentity);

        $usercompetencyentity = new usercompetency();
        $usercompetencyalias = $usercompetencyentity->get_table_alias('competency_usercomp');
        $usercompetencyjoin = "JOIN {competency_usercomp} {$usercompetencyalias}
            ON {$usercompetencyalias}.id = {$evidencealias}.usercompetencyid";
        $this->add_entity($usercompetencyentity->add_join($usercompetencyjoin));

        // Competency and framework (core), sharing the framework's context.
        $competencyentity = new competency();
        $competencyalias = $competencyentity->get_table_alias('competency');
        $frameworkentity = new framework();
        $frameworkalias = $frameworkentity->get_table_alias('competency_framework');
        $frameworkcontextalias = $frameworkentity->get_table_alias('context');
        $competencyjoins = [
            $usercompetencyjoin,
            "JOIN {competency} {$competencyalias} ON {$competencyalias}.id = {$usercompetencyalias}.competencyid",
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

        $extrasentity = new competency_extras();
        $this->add_entity($extrasentity
            ->set_table_alias('competency', $competencyalias)
            ->add_joins($competencyjoins));

        // Hierarchy, scale and links of the competency.
        $detailsentity = new competency_details();
        $this->add_entity($detailsentity
            ->set_table_alias('competency', $competencyalias)
            ->add_joins($competencyjoins));

        // The learner.
        $userentity = new user();
        $useralias = $userentity->get_table_alias('user');
        $this->add_entity($userentity
            ->add_join($usercompetencyjoin)
            ->add_join("JOIN {user} {$useralias} ON {$useralias}.id = {$usercompetencyalias}.userid"));

        // The user whose action produced the evidence (a teacher, a rule, the learner).
        $authorentity = (new user())
            ->set_entity_name('evidenceauthor')
            ->set_entity_title(new lang_string('entityname_evidenceauthor', 'local_dimensions'));
        $authoralias = $authorentity->get_table_alias('user');
        $this->add_entity($authorentity
            ->add_join("LEFT JOIN {user} {$authoralias} ON {$authoralias}.id = {$evidencealias}.actionuserid"));

        // Where the evidence came from.
        $contextentity = (new context())
            ->set_entity_title(new lang_string('entityname_evidencecontext', 'local_dimensions'));
        $contextalias = $contextentity->get_table_alias('context');
        $contextjoin = "LEFT JOIN {context} {$contextalias} ON {$contextalias}.id = {$evidencealias}.contextid";
        $this->add_entity($contextentity->add_join($contextjoin));

        // The course of a course or activity context.
        $courseentity = new course();
        $coursealias = $courseentity->get_table_alias('course');
        $course = CONTEXT_COURSE;
        $module = CONTEXT_MODULE;
        $this->add_entity($courseentity
            ->add_join($contextjoin)
            ->add_join("LEFT JOIN {course} {$coursealias} ON {$coursealias}.id = CASE
                    WHEN {$contextalias}.contextlevel = {$course} THEN {$contextalias}.instanceid
                    WHEN {$contextalias}.contextlevel = {$module} THEN (
                        SELECT ecm.course FROM {course_modules} ecm WHERE ecm.id = {$contextalias}.instanceid
                    )
                END"));

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
            'competency:name',
            'evidence:action',
            'evidence:description',
            'evidence:rating',
            'evidence:timecreated',
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
            'evidence:timecreated' => SORT_DESC,
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
            'evidence:action',
            'evidence:timecreated',
        ];
    }

    /**
     * Default conditions on a new report.
     *
     * @return string[]
     */
    public function get_default_conditions(): array {
        return [
            'framework:name',
            'evidence:timecreated',
        ];
    }
}
