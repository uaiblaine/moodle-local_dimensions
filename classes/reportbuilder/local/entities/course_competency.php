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

namespace local_dimensions\reportbuilder\local\entities;

use core\lang_string;
use core_competency\course_competency as core_course_competency;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\{boolean_select, select};
use core_reportbuilder\local\helpers\{database, format};
use core_reportbuilder\local\report\{column, filter};

/**
 * Course competency entity.
 *
 * Wraps the core `competency_coursecomp` table (one row per competency linked to a course) and
 * the course's `competency_coursecompsetting` row, which holds "Push course ratings to individual
 * learning plans". A course without a settings row follows the site default, as
 * {@see \core_competency\course_competency_settings} does.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_competency extends base {
    /**
     * Database tables that this entity uses.
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return [
            'competency_coursecomp',
            'competency_coursecompsetting',
        ];
    }

    /**
     * The default title for this entity.
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entityname_course_competency', 'local_dimensions');
    }

    /**
     * Initialise the entity.
     */
    public function initialise(): base {
        foreach ($this->get_all_columns() as $col) {
            $this->add_column($col);
        }
        foreach ($this->get_all_filters() as $f) {
            $this->add_filter($f)->add_condition($f);
        }
        return $this;
    }

    /**
     * Native columns for the entity.
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        $tablealias = $this->get_table_alias('competency_coursecomp');
        [$pushsql, $pushparams] = $this->get_push_ratings_sql();

        $columns = [];

        $columns[] = (new column(
            'ruleoutcome',
            new lang_string('column_ruleoutcome_course', 'local_dimensions'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            // An outcome is a category, not a quantity: as text, the report builder offers no numeric aggregation.
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$tablealias}.ruleoutcome")
            ->set_is_sortable(true)
            ->add_callback(static fn(?string $outcome): string => self::format_outcome($outcome));

        $columns[] = (new column(
            'sortorder',
            new lang_string('column_sortorder', 'local_dimensions'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$tablealias}.sortorder")
            ->set_is_sortable(true);

        $columns[] = (new column(
            'pushratingstouserplans',
            new lang_string('pushcourseratingstouserplans', 'core_competency'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_join($this->get_settings_join())
            ->set_type(column::TYPE_BOOLEAN)
            ->add_field($pushsql, 'pushratingstouserplans', $pushparams)
            ->set_is_sortable(true)
            ->add_callback([format::class, 'boolean_as_text']);

        $columns[] = (new column(
            'timecreated',
            new lang_string('timecreated', 'core_reportbuilder'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$tablealias}.timecreated")
            ->set_is_sortable(true)
            ->set_callback([format::class, 'userdate']);

        return $columns;
    }

    /**
     * Native filters for the entity.
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $tablealias = $this->get_table_alias('competency_coursecomp');
        [$pushsql, $pushparams] = $this->get_push_ratings_sql();

        $filters = [];

        $filters[] = (new filter(
            select::class,
            'ruleoutcome',
            new lang_string('column_ruleoutcome_course', 'local_dimensions'),
            $this->get_entity_name(),
            "{$tablealias}.ruleoutcome"
        ))
            ->add_joins($this->get_joins())
            ->set_options_callback(static fn(): array => array_map('strval', core_course_competency::get_ruleoutcome_list()));

        $filters[] = (new filter(
            boolean_select::class,
            'pushratingstouserplans',
            new lang_string('pushcourseratingstouserplans', 'core_competency'),
            $this->get_entity_name(),
            $pushsql,
            $pushparams
        ))
            ->add_joins($this->get_joins())
            ->add_join($this->get_settings_join());

        return $filters;
    }

    /**
     * The course's settings row, when it has one.
     *
     * @return string
     */
    private function get_settings_join(): string {
        $tablealias = $this->get_table_alias('competency_coursecomp');
        $settingsalias = $this->get_table_alias('competency_coursecompsetting');

        return "LEFT JOIN {competency_coursecompsetting} {$settingsalias}
            ON {$settingsalias}.courseid = {$tablealias}.courseid";
    }

    /**
     * The course's "push ratings" setting, the site default when the course has no settings row.
     *
     * @return array{0: string, 1: array} The SQL expression and its parameters.
     */
    private function get_push_ratings_sql(): array {
        $settingsalias = $this->get_table_alias('competency_coursecompsetting');
        $param = database::generate_param_name();

        return [
            "COALESCE({$settingsalias}.pushratingstouserplans, :{$param})",
            [$param => (int) (bool) get_config('core_competency', 'pushcourseratingstouserplans')],
        ];
    }

    /**
     * Map a ruleoutcome value to its localised label.
     *
     * @param string|null $outcome Raw `ruleoutcome` value.
     * @return string Localised label, or '' when there is none.
     */
    private static function format_outcome(?string $outcome): string {
        $list = core_course_competency::get_ruleoutcome_list();
        if ($outcome === null || !isset($list[(int) $outcome])) {
            return '';
        }
        return (string) $list[(int) $outcome];
    }
}
