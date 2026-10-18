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
use core_reportbuilder\local\filters\{number, text};
use core_reportbuilder\local\report\{column, filter};
use stdClass;

/**
 * Competency details entity: its place in the framework and what links to it.
 *
 * Standalone entity over the `competency` table, like {@see competency_extras}: the host
 * datasource joins `{competency}` and calls {@see base::set_table_alias()}. It gives the parent,
 * the path of ancestors, the scale that rates the competency (its own or its framework's), and
 * the number of courses and related competencies linked to it.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class competency_details extends base {
    /** @var array<int, array<int, string>> Competency short names keyed by framework, read once per request. */
    private static array $names = [];

    /**
     * Database tables that this entity uses.
     *
     * @return string[]
     */
    #[\Override]
    protected function get_default_tables(): array {
        return ['competency'];
    }

    /**
     * The default title for this entity.
     */
    #[\Override]
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entityname_competency_details', 'local_dimensions');
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
     * SQL for the parent's short name.
     *
     * @return string A scalar subquery.
     */
    private function parent_sql(): string {
        $alias = $this->get_table_alias('competency');
        return "(SELECT cdp.shortname FROM {competency} cdp WHERE cdp.id = {$alias}.parentid)";
    }

    /**
     * SQL for the name of the scale that rates the competency.
     *
     * @return string A scalar subquery.
     */
    private function scale_sql(): string {
        $alias = $this->get_table_alias('competency');
        return "(SELECT cds.name
                   FROM {competency_framework} cdf
                   JOIN {scale} cds ON cds.id = COALESCE({$alias}.scaleid, cdf.scaleid)
                  WHERE cdf.id = {$alias}.competencyframeworkid)";
    }

    /**
     * SQL counting the courses linked to the competency.
     *
     * @return string A scalar subquery.
     */
    private function courses_sql(): string {
        $alias = $this->get_table_alias('competency');
        return "(SELECT COUNT(1) FROM {competency_coursecomp} cdc WHERE cdc.competencyid = {$alias}.id)";
    }

    /**
     * SQL counting the competencies related to the competency, in either direction.
     *
     * @return string A scalar subquery.
     */
    private function related_sql(): string {
        $alias = $this->get_table_alias('competency');
        return "(SELECT COUNT(1) FROM {competency_relatedcomp} cdr
                  WHERE cdr.competencyid = {$alias}.id OR cdr.relatedcompetencyid = {$alias}.id)";
    }

    /**
     * Native columns for the entity.
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        $alias = $this->get_table_alias('competency');

        $columns = [];

        $columns[] = (new column(
            'parent',
            new lang_string('column_parentcompetency', 'local_dimensions'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field($this->parent_sql(), 'parent')
            ->set_is_sortable(true)
            ->add_callback(static fn(?string $value): string => $value === null ? '' : format_string($value));

        $columns[] = (new column(
            'path',
            new lang_string('column_competencypath', 'local_dimensions'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_fields("{$alias}.path, {$alias}.competencyframeworkid")
            ->set_is_sortable(true, ["{$alias}.path"])
            ->add_callback(static function (?string $path, stdClass $row): string {
                return self::path_names($path, $row->competencyframeworkid ?? null);
            });

        $columns[] = (new column(
            'scale',
            new lang_string('column_ratingscale', 'local_dimensions'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field($this->scale_sql(), 'scale')
            ->set_is_sortable(true)
            ->add_callback(static fn(?string $value): string => $value === null ? '' : format_string($value));

        $columns[] = (new column(
            'courses',
            new lang_string('column_coursecount', 'local_dimensions'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field($this->courses_sql(), 'courses')
            ->set_is_sortable(true);

        $columns[] = (new column(
            'related',
            new lang_string('column_relatedcount', 'local_dimensions'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field($this->related_sql(), 'related')
            ->set_is_sortable(true);

        return $columns;
    }

    /**
     * Native filters for the entity.
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $filters = [];

        $filters[] = (new filter(
            text::class,
            'parent',
            new lang_string('column_parentcompetency', 'local_dimensions'),
            $this->get_entity_name(),
            $this->parent_sql()
        ))->add_joins($this->get_joins());

        $filters[] = (new filter(
            text::class,
            'scale',
            new lang_string('column_ratingscale', 'local_dimensions'),
            $this->get_entity_name(),
            $this->scale_sql()
        ))->add_joins($this->get_joins());

        $filters[] = (new filter(
            number::class,
            'courses',
            new lang_string('column_coursecount', 'local_dimensions'),
            $this->get_entity_name(),
            $this->courses_sql()
        ))->add_joins($this->get_joins());

        $filters[] = (new filter(
            number::class,
            'related',
            new lang_string('column_relatedcount', 'local_dimensions'),
            $this->get_entity_name(),
            $this->related_sql()
        ))->add_joins($this->get_joins());

        return $filters;
    }

    /**
     * The short names of a competency's ancestors, root first.
     *
     * Reads each framework's short names once per request, so the column costs no query per row.
     *
     * @param string|null $path The competency's `path` (`/0/12/34/`).
     * @param int|string|null $frameworkid Its framework.
     * @return string The names joined with " / ", or '' for a root competency.
     */
    private static function path_names(?string $path, $frameworkid): string {
        global $DB;
        if ($path === null || !$frameworkid) {
            return '';
        }
        $ids = array_filter(array_map('intval', explode('/', trim($path, '/'))));
        if (!$ids) {
            return '';
        }
        $frameworkid = (int) $frameworkid;
        if (!array_key_exists($frameworkid, self::$names)) {
            self::$names[$frameworkid] = $DB->get_records_menu(
                'competency',
                ['competencyframeworkid' => $frameworkid],
                '',
                'id, shortname'
            );
        }
        $names = [];
        foreach ($ids as $id) {
            $names[] = format_string(self::$names[$frameworkid][$id] ?? (string) $id);
        }
        return implode(' / ', $names);
    }

    /**
     * Forget the names read so far (tests rename competencies between reports).
     *
     * @return void
     */
    public static function reset(): void {
        self::$names = [];
    }
}
