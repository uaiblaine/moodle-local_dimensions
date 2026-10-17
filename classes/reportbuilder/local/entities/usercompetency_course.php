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
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\{boolean_select, date, number};
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\{column, filter};
use local_dimensions\reportbuilder\local\helpers\rating;
use stdClass;

/**
 * User competency in course entity.
 *
 * Wraps the core `competency_usercompcourse` table: the rating a user received for a competency
 * inside one course, and whether it made them proficient.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class usercompetency_course extends base {
    /**
     * Database tables that this entity uses.
     *
     * @return string[]
     */
    #[\Override]
    protected function get_default_tables(): array {
        return ['competency_usercompcourse'];
    }

    /**
     * The default title for this entity.
     */
    #[\Override]
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entityname_usercompetency_course', 'local_dimensions');
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
     * Native columns for the entity.
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        $alias = $this->get_table_alias('competency_usercompcourse');

        $columns = [];

        $columns[] = (new column(
            'proficient',
            new lang_string('proficient', 'core_competency'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_BOOLEAN)
            ->add_field("{$alias}.proficiency")
            ->set_is_sortable(true)
            ->set_callback([format::class, 'boolean_as_text']);

        $columns[] = (new column(
            'rating',
            new lang_string('rating', 'core_competency'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("{$alias}.grade")
            ->add_field(rating::scaleid_sql("{$alias}.competencyid"), 'scaleid')
            ->set_is_sortable(true, ["{$alias}.grade"])
            ->add_callback(static fn($grade, stdClass $row): string => rating::format($grade, $row->scaleid ?? null));

        $columns[] = (new column(
            'timemodified',
            new lang_string('timemodified', 'core_reportbuilder'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$alias}.timemodified")
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
        $alias = $this->get_table_alias('competency_usercompcourse');

        $filters = [];

        $filters[] = (new filter(
            boolean_select::class,
            'proficient',
            new lang_string('proficient', 'core_competency'),
            $this->get_entity_name(),
            "{$alias}.proficiency"
        ))->add_joins($this->get_joins());

        $filters[] = (new filter(
            number::class,
            'rating',
            new lang_string('filter_ratingvalue', 'local_dimensions'),
            $this->get_entity_name(),
            "{$alias}.grade"
        ))->add_joins($this->get_joins());

        $filters[] = (new filter(
            date::class,
            'timemodified',
            new lang_string('timemodified', 'core_reportbuilder'),
            $this->get_entity_name(),
            "{$alias}.timemodified"
        ))->add_joins($this->get_joins());

        return $filters;
    }
}
