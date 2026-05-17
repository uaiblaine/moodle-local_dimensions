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
use core_reportbuilder\local\filters\{date, number, select, text};
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\{column, filter};
use stdClass;

/**
 * Learning plan entity.
 *
 * Wraps the core `competency_plan` table. The plugin owns no plan-area
 * customfields, so this entity exposes only native columns/filters.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plan extends base {

    /**
     * Database tables that this entity uses.
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return ['competency_plan'];
    }

    /**
     * The default title for this entity.
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entityname_plan', 'local_dimensions');
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
        $tablealias = $this->get_table_alias('competency_plan');

        $columns = [];

        $columns[] = (new column(
            'name',
            new lang_string('name'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("{$tablealias}.name")
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
            ->add_callback(static function (?string $description, stdClass $row): string {
                if ($description === null) {
                    return '';
                }
                return format_text($description, $row->descriptionformat ?? FORMAT_HTML);
            });

        $columns[] = (new column(
            'status',
            new lang_string('status'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$tablealias}.status")
            ->set_is_sortable(true)
            ->add_callback(static function (?int $status): string {
                return self::format_plan_status($status);
            });

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
        $tablealias = $this->get_table_alias('competency_plan');

        $filters = [];

        $filters[] = (new filter(
            text::class,
            'name',
            new lang_string('name'),
            $this->get_entity_name(),
            "{$tablealias}.name"
        ))->add_joins($this->get_joins());

        $filters[] = (new filter(
            select::class,
            'status',
            new lang_string('status'),
            $this->get_entity_name(),
            "{$tablealias}.status"
        ))
            ->add_joins($this->get_joins())
            ->set_options_callback(static function (): array {
                return [
                    core_plan::STATUS_DRAFT => self::format_plan_status(core_plan::STATUS_DRAFT),
                    core_plan::STATUS_ACTIVE => self::format_plan_status(core_plan::STATUS_ACTIVE),
                    core_plan::STATUS_COMPLETE => self::format_plan_status(core_plan::STATUS_COMPLETE),
                    core_plan::STATUS_WAITING_FOR_REVIEW =>
                        self::format_plan_status(core_plan::STATUS_WAITING_FOR_REVIEW),
                    core_plan::STATUS_IN_REVIEW => self::format_plan_status(core_plan::STATUS_IN_REVIEW),
                ];
            });

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

        $filters[] = (new filter(
            number::class,
            'userid',
            new lang_string('user'),
            $this->get_entity_name(),
            "{$tablealias}.userid"
        ))->add_joins($this->get_joins());

        return $filters;
    }

    /**
     * Map a competency_plan.status integer to its localised label.
     *
     * Mirrors {@see \core_competency\plan::get_statusname()} but works without
     * an instantiated plan model (so it's safe in callbacks operating on raw
     * column values).
     */
    private static function format_plan_status(?int $status): string {
        $map = [
            core_plan::STATUS_DRAFT => 'planstatusdraft',
            core_plan::STATUS_ACTIVE => 'planstatusactive',
            core_plan::STATUS_COMPLETE => 'planstatuscomplete',
            core_plan::STATUS_WAITING_FOR_REVIEW => 'planstatuswaitingforreview',
            core_plan::STATUS_IN_REVIEW => 'planstatusinreview',
        ];
        if ($status === null || !isset($map[$status])) {
            return '';
        }
        return get_string($map[$status], 'core_competency');
    }
}
