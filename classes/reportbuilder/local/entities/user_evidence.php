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
use core_reportbuilder\local\filters\{date, number, text};
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\{column, filter};
use html_writer;
use stdClass;

/**
 * Evidence of prior learning entity.
 *
 * Wraps the core `competency_userevidence` table: what a user submits to show a competency
 * learned elsewhere, with its link and the number of files attached to it.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_evidence extends base {
    /**
     * Database tables that this entity uses.
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return ['competency_userevidence'];
    }

    /**
     * The default title for this entity.
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entityname_user_evidence', 'local_dimensions');
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
     * SQL counting the files attached to an evidence of prior learning.
     *
     * @return string A scalar subquery.
     */
    private function files_sql(): string {
        $alias = $this->get_table_alias('competency_userevidence');
        return "(SELECT COUNT(1)
                   FROM {files} uef
                  WHERE uef.component = 'core_competency'
                    AND uef.filearea = 'userevidence'
                    AND uef.itemid = {$alias}.id
                    AND uef.filename <> '.')";
    }

    /**
     * Native columns for the entity.
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        $alias = $this->get_table_alias('competency_userevidence');

        $columns = [];

        $columns[] = (new column(
            'name',
            new lang_string('name'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("{$alias}.name")
            ->set_is_sortable(true)
            ->add_callback(static fn(?string $value): string => $value === null ? '' : format_string($value));

        $columns[] = (new column(
            'description',
            new lang_string('description'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_LONGTEXT)
            ->add_fields("{$alias}.description, {$alias}.descriptionformat")
            ->add_callback(static function (?string $description, stdClass $row): string {
                if ($description === null) {
                    return '';
                }
                return format_text($description, $row->descriptionformat ?? FORMAT_HTML);
            });

        $columns[] = (new column(
            'url',
            new lang_string('url'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("{$alias}.url")
            ->set_is_sortable(true)
            ->add_callback(static function (?string $url): string {
                return ($url === null || $url === '') ? '' : html_writer::link($url, s($url));
            });

        $columns[] = (new column(
            'files',
            new lang_string('column_files', 'local_dimensions'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field($this->files_sql(), 'files')
            ->set_is_sortable(true);

        $columns[] = (new column(
            'timecreated',
            new lang_string('timecreated', 'core_reportbuilder'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$alias}.timecreated")
            ->set_is_sortable(true)
            ->set_callback([format::class, 'userdate']);

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
        $alias = $this->get_table_alias('competency_userevidence');

        $filters = [];

        $filters[] = (new filter(
            text::class,
            'name',
            new lang_string('name'),
            $this->get_entity_name(),
            "{$alias}.name"
        ))->add_joins($this->get_joins());

        $filters[] = (new filter(
            number::class,
            'files',
            new lang_string('column_files', 'local_dimensions'),
            $this->get_entity_name(),
            $this->files_sql()
        ))->add_joins($this->get_joins());

        $filters[] = (new filter(
            date::class,
            'timecreated',
            new lang_string('timecreated', 'core_reportbuilder'),
            $this->get_entity_name(),
            "{$alias}.timecreated"
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
