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
use core_course\reportbuilder\local\entities\{course_category, course_module};
use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\course;
use core_reportbuilder\local\helpers\database;
use local_dimensions\reportbuilder\local\entities\{competency_extras, course_competency, module_competency};

/**
 * Course and activity competencies datasource.
 *
 * One row per competency linked to a course (`competency_coursecomp`), with the course, its
 * category, the competency, its framework and its Dimensions custom fields. The activity entities
 * add one row per activity of that course linked to the same competency: an activity can only
 * hold a competency its course holds, so the course link is the grain of both.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_competencies extends datasource {
    /**
     * Human-readable name shown in the report-builder UI.
     */
    public static function get_name(): string {
        return get_string('datasource_course_competencies', 'local_dimensions');
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
        // Main entity: competency_coursecomp.
        $coursecompentity = new course_competency();
        $coursecompalias = $coursecompentity->get_table_alias('competency_coursecomp');
        $this->set_main_table('competency_coursecomp', $coursecompalias);
        $this->add_entity($coursecompentity);

        // Course (core).
        $courseentity = new course();
        $coursealias = $courseentity->get_table_alias('course');
        $this->add_entity($courseentity
            ->add_join("JOIN {course} {$coursealias} ON {$coursealias}.id = {$coursecompalias}.courseid"));

        // Course category (core).
        $categoryentity = new course_category();
        $categoryalias = $categoryentity->get_table_alias('course_categories');
        $this->add_entity($categoryentity
            ->add_joins($courseentity->get_joins())
            ->add_join("JOIN {course_categories} {$categoryalias} ON {$categoryalias}.id = {$coursealias}.category"));

        // Competency and framework (core), sharing the framework's context.
        $competencyentity = new competency();
        $competencyalias = $competencyentity->get_table_alias('competency');
        $frameworkentity = new framework();
        $frameworkalias = $frameworkentity->get_table_alias('competency_framework');
        $contextalias = $frameworkentity->get_table_alias('context');
        $competencyjoins = [
            "JOIN {competency} {$competencyalias} ON {$competencyalias}.id = {$coursecompalias}.competencyid",
            "JOIN {competency_framework} {$frameworkalias}
                ON {$frameworkalias}.id = {$competencyalias}.competencyframeworkid",
            "LEFT JOIN {context} {$contextalias} ON {$contextalias}.id = {$frameworkalias}.contextid",
        ];
        $this->add_entity($competencyentity
            ->set_table_join_alias('context', $contextalias)
            ->add_joins($competencyjoins));
        $this->add_entity($frameworkentity
            ->set_table_join_alias('context', $contextalias)
            ->add_joins($competencyjoins));

        // Competency customfields, keyed to the same competency rows.
        $extrasentity = new competency_extras();
        $this->add_entity($extrasentity
            ->set_table_alias('competency', $competencyalias)
            ->add_joins($competencyjoins));

        // Activities of the course linked to the same competency. The course check keeps a competency
        // that several courses hold from reaching another course's activities.
        $modulecompentity = new module_competency();
        $modulecompalias = $modulecompentity->get_table_alias('competency_modulecomp');
        $cmentity = new course_module();
        $cmalias = $cmentity->get_table_alias('course_modules');
        $cmcheckalias = database::generate_alias();
        $modulejoins = [
            "LEFT JOIN {competency_modulecomp} {$modulecompalias}
                ON {$modulecompalias}.competencyid = {$coursecompalias}.competencyid
               AND EXISTS (
                   SELECT 1
                     FROM {course_modules} {$cmcheckalias}
                    WHERE {$cmcheckalias}.id = {$modulecompalias}.cmid
                      AND {$cmcheckalias}.course = {$coursecompalias}.courseid
                      AND {$cmcheckalias}.deletioninprogress = 0
               )",
            "LEFT JOIN {course_modules} {$cmalias} ON {$cmalias}.id = {$modulecompalias}.cmid",
        ];
        $this->add_entity($modulecompentity
            ->set_table_alias('course_modules', $cmalias)
            ->add_joins($modulejoins));
        $this->add_entity($cmentity
            ->add_joins($modulejoins));

        $this->add_all_from_entities();
    }

    /**
     * Columns added when a report is first built from this datasource.
     *
     * @return string[]
     */
    public function get_default_columns(): array {
        return [
            'course:fullname',
            'competency:name',
            'course_competency:ruleoutcome',
            'module_competency:activityname',
            'module_competency:ruleoutcome',
        ];
    }

    /**
     * Default initial sort.
     *
     * @return int[]
     */
    public function get_default_column_sorting(): array {
        return [
            'course:fullname' => SORT_ASC,
            'competency:name' => SORT_ASC,
        ];
    }

    /**
     * Default filters offered to a new report built from this datasource.
     *
     * @return string[]
     */
    public function get_default_filters(): array {
        return [
            'course:fullname',
            'competency:name',
            'course_competency:ruleoutcome',
        ];
    }

    /**
     * Default conditions on a new report.
     *
     * @return string[]
     */
    public function get_default_conditions(): array {
        return [
            'course_category:name',
            'framework:name',
        ];
    }
}
