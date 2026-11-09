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
use core_reportbuilder\local\filters\{boolean_select, date, number, select, text};
use core_reportbuilder\local\helpers\{database, format};
use core_reportbuilder\local\report\{column, filter};
use local_dimensions\reportbuilder\local\helpers\link;
use moodle_url;
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
    #[\Override]
    protected function get_default_tables(): array {
        return ['competency_plan'];
    }

    /**
     * The default title for this entity.
     */
    #[\Override]
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entityname_plan', 'local_dimensions');
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
            ->add_fields("{$tablealias}.description, {$tablealias}.descriptionformat, {$tablealias}.userid")
            ->add_callback(static function (?string $description, stdClass $row): string {
                if ($description === null) {
                    return '';
                }
                return format_text($description, $row->descriptionformat ?? FORMAT_HTML, [
                    'context' => \core\context\user::instance((int) $row->userid),
                ]);
            });

        $columns[] = (new column(
            'status',
            new lang_string('status'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            // A status is a category, not a quantity: as text, the report builder offers no numeric aggregation.
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$tablealias}.status")
            ->set_is_sortable(true)
            ->add_callback(static function (?string $status): string {
                return self::format_plan_status($status === null ? null : (int) $status);
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

        $columns[] = (new column(
            'namewithlink',
            new lang_string('column_namewithlink', 'local_dimensions'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_fields("{$tablealias}.name, {$tablealias}.id")
            ->set_is_sortable(true, ["{$tablealias}.name"])
            ->add_callback(static function (?string $name, stdClass $row): string {
                if ($name === null) {
                    return '';
                }
                return link::to_page(
                    new moodle_url('/admin/tool/lp/plan.php', ['id' => $row->id]),
                    format_string($name, true, ['escape' => false])
                );
            });

        $columns[] = (new column(
            'basedontemplate',
            new lang_string('column_basedontemplate', 'local_dimensions'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_BOOLEAN)
            ->add_field("CASE WHEN {$tablealias}.templateid IS NULL THEN 0 ELSE 1 END", 'basedontemplate')
            ->set_is_sortable(true)
            ->set_callback([format::class, 'boolean_as_text']);

        $columns[] = (new column(
            'origtemplate',
            new lang_string('column_origtemplate', 'local_dimensions'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field(
                "(SELECT ot.shortname FROM {competency_template} ot WHERE ot.id = {$tablealias}.origtemplateid)",
                'origtemplate'
            )
            ->set_is_sortable(true)
            ->add_callback(static fn(?string $value): string => $value === null ? '' : format_string($value));

        $columns[] = (new column(
            'competencies',
            new lang_string('column_competencycount', 'local_dimensions'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_join($this->stats_join())
            ->add_field($this->competencies_sql(), 'competencies')
            ->set_is_sortable(true);

        $columns[] = (new column(
            'proficient',
            new lang_string('column_proficientcount', 'local_dimensions'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_join($this->stats_join())
            ->add_field($this->proficient_sql(), 'proficient')
            ->set_is_sortable(true);

        $columns[] = (new column(
            'progress',
            new lang_string('column_progress', 'local_dimensions'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_FLOAT)
            ->add_join($this->stats_join())
            ->add_field($this->progress_sql(), 'progress')
            ->set_is_sortable(true)
            ->set_callback([format::class, 'percent']);

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
            boolean_select::class,
            'basedontemplate',
            new lang_string('column_basedontemplate', 'local_dimensions'),
            $this->get_entity_name(),
            "CASE WHEN {$tablealias}.templateid IS NULL THEN 0 ELSE 1 END"
        ))->add_joins($this->get_joins());

        $filters[] = (new filter(
            number::class,
            'competencies',
            new lang_string('column_competencycount', 'local_dimensions'),
            $this->get_entity_name(),
            $this->competencies_sql()
        ))
            ->add_joins($this->get_joins())
            ->add_join($this->stats_join());

        $filters[] = (new filter(
            number::class,
            'proficient',
            new lang_string('column_proficientcount', 'local_dimensions'),
            $this->get_entity_name(),
            $this->proficient_sql()
        ))
            ->add_joins($this->get_joins())
            ->add_join($this->stats_join());

        $filters[] = (new filter(
            number::class,
            'progress',
            new lang_string('column_progress', 'local_dimensions'),
            $this->get_entity_name(),
            $this->progress_sql()
        ))
            ->add_joins($this->get_joins())
            ->add_join($this->stats_join());

        return $filters;
    }

    /** @var string|null Alias of the derived table with each plan's competency counts. */
    private ?string $statsalias = null;

    /**
     * Join the competency counts of every plan, as one derived table.
     *
     * A plan's competencies are its own (`competency_plancomp`) or its template's
     * (`competency_templatecomp`); a competency counts as proficient from the archive
     * (`competency_usercompplan`) once the plan is complete, from the owner's current user
     * competency otherwise. One grouped join keeps the columns plain fields, which a report can
     * aggregate and group by cheaply; correlated subqueries repeated in a GROUP BY are not.
     *
     * @return string The LEFT JOIN, with the alias {@see self::$statsalias}.
     */
    private function stats_join(): string {
        $alias = $this->get_table_alias('competency_plan');
        $this->statsalias ??= database::generate_alias();
        $complete = core_plan::STATUS_COMPLETE;
        return "LEFT JOIN (
                    SELECT sp.id AS planid,
                           COUNT(1) AS total,
                           SUM(CASE WHEN sp.status = {$complete} THEN COALESCE(sa.proficiency, 0)
                                    ELSE COALESCE(su.proficiency, 0) END) AS proficient
                      FROM {competency_plan} sp
                      JOIN (
                            SELECT pc.planid, pc.competencyid FROM {competency_plancomp} pc
                             UNION ALL
                            SELECT tp.id AS planid, tc.competencyid
                              FROM {competency_plan} tp
                              JOIN {competency_templatecomp} tc ON tc.templateid = tp.templateid
                           ) sl ON sl.planid = sp.id
                 LEFT JOIN {competency_usercompplan} sa ON sa.planid = sp.id AND sa.competencyid = sl.competencyid
                 LEFT JOIN {competency_usercomp} su ON su.userid = sp.userid AND su.competencyid = sl.competencyid
                  GROUP BY sp.id
                ) {$this->statsalias} ON {$this->statsalias}.planid = {$alias}.id";
    }

    /**
     * SQL for the number of the plan's competencies.
     *
     * @return string
     */
    private function competencies_sql(): string {
        $this->stats_join();
        return "COALESCE({$this->statsalias}.total, 0)";
    }

    /**
     * SQL for the number of the plan's proficient competencies.
     *
     * @return string
     */
    private function proficient_sql(): string {
        $this->stats_join();
        return "COALESCE({$this->statsalias}.proficient, 0)";
    }

    /**
     * SQL for the share of proficient competencies, from 0 to 100, or null for a plan without any.
     *
     * @return string
     */
    private function progress_sql(): string {
        $this->stats_join();
        return "(CASE WHEN {$this->statsalias}.total > 0
                      THEN 100.0 * {$this->statsalias}.proficient / {$this->statsalias}.total END)";
    }

    /**
     * Map a competency_plan.status integer to its localised label.
     *
     * Mirrors {@see \core_competency\plan::get_statusname()} but works without
     * an instantiated plan model (so it's safe in callbacks operating on raw
     * column values).
     *
     * @param int|null $status Raw competency_plan.status value.
     * @return string Localised status label, or '' when unknown.
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
