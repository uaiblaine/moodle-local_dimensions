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
use core_reportbuilder\local\helpers\{custom_fields, format};
use core_reportbuilder\local\report\{column, filter};
use local_dimensions\helper;
use stdClass;

/**
 * Learning plan template entity.
 *
 * Wraps the core `competency_template` table and embeds all customfields
 * defined by local_dimensions in the `lp` area (auto-generated columns and
 * filters via the customfield helper).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template extends base {
    /**
     * Database tables that this entity uses.
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return ['competency_template'];
    }

    /**
     * The default title for this entity.
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entityname_template', 'local_dimensions');
    }

    /**
     * Initialise the entity: register native columns + filters, then merge
     * customfield-derived columns/filters for the `lp` area.
     */
    public function initialise(): base {
        $tablealias = $this->get_table_alias('competency_template');

        $customfields = (new custom_fields(
            "{$tablealias}.id",
            $this->get_entity_name(),
            'local_dimensions',
            helper::AREA_LP,
        ))->add_joins($this->get_joins());

        foreach (array_merge($this->get_all_columns(), $customfields->get_columns()) as $col) {
            $this->add_column($col);
        }
        foreach (array_merge($this->get_all_filters(), $customfields->get_filters()) as $f) {
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
        $tablealias = $this->get_table_alias('competency_template');

        $columns = [];

        $columns[] = (new column(
            'shortname',
            new lang_string('column_shortname', 'local_dimensions'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("{$tablealias}.shortname")
            ->set_is_sortable(true)
            ->add_callback(static fn(?string $value): string => $value === null ? '' : format_string($value));

        $columns[] = (new column(
            'description',
            new lang_string('description'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_LONGTEXT)
            ->add_fields("{$tablealias}.description, {$tablealias}.descriptionformat")
            ->set_is_sortable(false)
            ->add_callback(static function (?string $description, stdClass $row): string {
                if ($description === null) {
                    return '';
                }
                return format_text($description, $row->descriptionformat ?? FORMAT_HTML);
            });

        $columns[] = (new column(
            'visible',
            new lang_string('visible'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_BOOLEAN)
            ->add_field("{$tablealias}.visible")
            ->set_is_sortable(true)
            ->set_callback([format::class, 'boolean_as_text']);

        $columns[] = (new column(
            'duedate',
            new lang_string('column_duedate', 'local_dimensions'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$tablealias}.duedate")
            ->set_is_sortable(true)
            ->set_callback([format::class, 'userdate']);

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

        $columns[] = (new column(
            'timemodified',
            new lang_string('timemodified', 'core_reportbuilder'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$tablealias}.timemodified")
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
        $tablealias = $this->get_table_alias('competency_template');

        $filters = [];

        $filters[] = (new filter(
            text::class,
            'shortname',
            new lang_string('column_shortname', 'local_dimensions'),
            $this->get_entity_name(),
            "{$tablealias}.shortname"
        ))->add_joins($this->get_joins());

        $filters[] = (new filter(
            boolean_select::class,
            'visible',
            new lang_string('visible'),
            $this->get_entity_name(),
            "{$tablealias}.visible"
        ))->add_joins($this->get_joins());

        $filters[] = (new filter(
            date::class,
            'duedate',
            new lang_string('column_duedate', 'local_dimensions'),
            $this->get_entity_name(),
            "{$tablealias}.duedate"
        ))->add_joins($this->get_joins());

        $filters[] = (new filter(
            date::class,
            'timecreated',
            new lang_string('timecreated', 'core_reportbuilder'),
            $this->get_entity_name(),
            "{$tablealias}.timecreated"
        ))->add_joins($this->get_joins());

        $filters[] = (new filter(
            date::class,
            'timemodified',
            new lang_string('timemodified', 'core_reportbuilder'),
            $this->get_entity_name(),
            "{$tablealias}.timemodified"
        ))->add_joins($this->get_joins());

        return $filters;
    }
}
