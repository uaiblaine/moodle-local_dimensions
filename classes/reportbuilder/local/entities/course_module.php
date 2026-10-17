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
use core_reportbuilder\local\filters\{boolean_select, date, text};
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\{column, filter};

/**
 * Course module entity, for the branches whose core has none.
 *
 * Moodle 5.2 added {@see \core_course\reportbuilder\local\entities\course_module}. This entity
 * keeps its entity name (`course_module`) and its column and filter names, so a saved report
 * keeps its elements when the site moves to 5.2 and the datasource switches to the core entity.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_module extends base {
    /**
     * Database tables that this entity uses.
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return ['course_modules'];
    }

    /**
     * The default title for this entity.
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entityname_course_module', 'local_dimensions');
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
     * Columns of the entity, as core 5.2 names them.
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        $cmalias = $this->get_table_alias('course_modules');

        $columns = [];

        $columns[] = (new column(
            'idnumber',
            new lang_string('idnumber'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("{$cmalias}.idnumber")
            ->set_is_sortable(true);

        $columns[] = (new column(
            'visible',
            new lang_string('visible'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_BOOLEAN)
            ->add_field("{$cmalias}.visible")
            ->set_is_sortable(true)
            ->set_callback([format::class, 'boolean_as_text']);

        $columns[] = (new column(
            'timecreated',
            new lang_string('timecreated', 'core_reportbuilder'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$cmalias}.added")
            ->set_is_sortable(true)
            ->set_callback([format::class, 'userdate']);

        return $columns;
    }

    /**
     * Filters of the entity, as core 5.2 names them.
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $cmalias = $this->get_table_alias('course_modules');

        $filters = [];

        $filters[] = (new filter(
            text::class,
            'idnumber',
            new lang_string('idnumber'),
            $this->get_entity_name(),
            "{$cmalias}.idnumber"
        ))->add_joins($this->get_joins());

        $filters[] = (new filter(
            boolean_select::class,
            'visible',
            new lang_string('visible'),
            $this->get_entity_name(),
            "{$cmalias}.visible"
        ))->add_joins($this->get_joins());

        $filters[] = (new filter(
            date::class,
            'timecreated',
            new lang_string('timecreated', 'core_reportbuilder'),
            $this->get_entity_name(),
            "{$cmalias}.added"
        ))->add_joins($this->get_joins());

        return $filters;
    }
}
