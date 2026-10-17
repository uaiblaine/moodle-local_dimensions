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
use core_competency\plan as core_plan;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\{boolean_select, number};
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\{column, filter};
use local_dimensions\reportbuilder\local\helpers\rating;
use stdClass;

/**
 * Competency in a learning plan entity.
 *
 * A plan's competencies are its template's (`competency_templatecomp`) or, for a plan without a
 * template, its own (`competency_plancomp`). The host datasource joins both as one derived table
 * under the `competency_plancomp` alias, with `planid`, `competencyid`, `sortorder` and
 * `fromtemplate`. A completed plan rates its competencies from the archive
 * (`competency_usercompplan`); any other plan from the user's current competency
 * (`competency_usercomp`).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plan_competency extends base {
    /**
     * Database tables that this entity uses.
     *
     * @return string[]
     */
    #[\Override]
    protected function get_default_tables(): array {
        return ['competency_plan', 'competency_plancomp', 'competency_usercompplan', 'competency_usercomp'];
    }

    /**
     * The default title for this entity.
     */
    #[\Override]
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entityname_plan_competency', 'local_dimensions');
    }

    /**
     * Initialise the entity.
     */
    #[\Override]
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
     * SQL picking the archived value on a completed plan and the current one otherwise.
     *
     * @param string $field `proficiency` or `grade`.
     * @return string
     */
    private function plan_value_sql(string $field): string {
        $plan = $this->get_table_alias('competency_plan');
        $archive = $this->get_table_alias('competency_usercompplan');
        $current = $this->get_table_alias('competency_usercomp');
        $complete = core_plan::STATUS_COMPLETE;
        return "CASE WHEN {$plan}.status = {$complete} THEN {$archive}.{$field} ELSE {$current}.{$field} END";
    }

    /**
     * Native columns for the entity.
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        $link = $this->get_table_alias('competency_plancomp');

        $columns = [];

        $columns[] = (new column(
            'fromtemplate',
            new lang_string('column_fromtemplate', 'local_dimensions'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_BOOLEAN)
            ->add_field("{$link}.fromtemplate")
            ->set_is_sortable(true)
            ->set_callback([format::class, 'boolean_as_text']);

        $columns[] = (new column(
            'sortorder',
            new lang_string('column_sortorder', 'local_dimensions'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$link}.sortorder")
            ->set_is_sortable(true);

        $columns[] = (new column(
            'proficient',
            new lang_string('proficient', 'core_competency'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_BOOLEAN)
            ->add_field($this->plan_value_sql('proficiency'), 'proficiency')
            ->set_is_sortable(true)
            ->set_callback([format::class, 'boolean_as_text']);

        $columns[] = (new column(
            'rating',
            new lang_string('rating', 'core_competency'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field($this->plan_value_sql('grade'), 'grade')
            ->add_field(rating::scaleid_sql("{$link}.competencyid"), 'scaleid')
            ->set_is_sortable(true, [$this->plan_value_sql('grade')])
            ->add_callback(static fn($grade, stdClass $row): string => rating::format($grade, $row->scaleid ?? null));

        return $columns;
    }

    /**
     * Native filters for the entity.
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $link = $this->get_table_alias('competency_plancomp');

        $filters = [];

        $filters[] = (new filter(
            boolean_select::class,
            'fromtemplate',
            new lang_string('column_fromtemplate', 'local_dimensions'),
            $this->get_entity_name(),
            "{$link}.fromtemplate"
        ))->add_joins($this->get_joins());

        $filters[] = (new filter(
            boolean_select::class,
            'proficient',
            new lang_string('proficient', 'core_competency'),
            $this->get_entity_name(),
            $this->plan_value_sql('proficiency')
        ))->add_joins($this->get_joins());

        $filters[] = (new filter(
            number::class,
            'rating',
            new lang_string('filter_ratingvalue', 'local_dimensions'),
            $this->get_entity_name(),
            $this->plan_value_sql('grade')
        ))->add_joins($this->get_joins());

        return $filters;
    }
}
