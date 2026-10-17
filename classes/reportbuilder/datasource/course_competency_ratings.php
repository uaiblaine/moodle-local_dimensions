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
use core_course\reportbuilder\local\entities\course_category;
use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\{course, user};
use core_reportbuilder\local\helpers\database;
use local_dimensions\reportbuilder\local\entities\{competency_extras, usercompetency_course};

/**
 * Competency ratings in courses datasource.
 *
 * One row per rating a user received for a competency inside a course
 * (`competency_usercompcourse`): the rating and proficiency, the user and their cohorts, the
 * course and its category, and the competency with its framework and Dimensions custom fields.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_competency_ratings extends datasource {
    /**
     * Human-readable name shown in the report-builder UI.
     */
    public static function get_name(): string {
        return get_string('datasource_course_competency_ratings', 'local_dimensions');
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
        $ratingentity = new usercompetency_course();
        $ratingalias = $ratingentity->get_table_alias('competency_usercompcourse');
        $this->set_main_table('competency_usercompcourse', $ratingalias);
        $this->add_entity($ratingentity);

        $userentity = new user();
        $useralias = $userentity->get_table_alias('user');
        $this->add_entity($userentity
            ->add_join("JOIN {user} {$useralias} ON {$useralias}.id = {$ratingalias}.userid"));

        $usercohortentity = (new cohort())
            ->set_entity_name('usercohort')
            ->set_entity_title(new lang_string('entityname_usercohort_rated', 'local_dimensions'));
        $usercohortalias = $usercohortentity->get_table_alias('cohort');
        $memberalias = database::generate_alias();
        $this->add_entity($usercohortentity
            ->add_joins($userentity->get_joins())
            ->add_joins([
                "LEFT JOIN {cohort_members} {$memberalias} ON {$memberalias}.userid = {$useralias}.id",
                "LEFT JOIN {cohort} {$usercohortalias} ON {$usercohortalias}.id = {$memberalias}.cohortid",
            ]));

        $courseentity = new course();
        $coursealias = $courseentity->get_table_alias('course');
        $this->add_entity($courseentity
            ->add_join("JOIN {course} {$coursealias} ON {$coursealias}.id = {$ratingalias}.courseid"));

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
        $frameworkcontextalias = $frameworkentity->get_table_alias('context');
        $competencyjoins = [
            "JOIN {competency} {$competencyalias} ON {$competencyalias}.id = {$ratingalias}.competencyid",
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
            'user:fullname',
            'competency:name',
            'usercompetency_course:rating',
            'usercompetency_course:proficient',
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
            'user:fullname' => SORT_ASC,
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
            'user:fullname',
            'competency:name',
            'usercompetency_course:proficient',
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
