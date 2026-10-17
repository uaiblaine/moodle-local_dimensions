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
use core_competency\course_module_competency as core_module_competency;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\{boolean_select, select};
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\{column, filter};
use stdClass;

/**
 * Activity competency entity.
 *
 * Wraps the core `competency_modulecomp` table (one row per competency linked to an activity).
 * The activity's name and type come from `course_modules`, which the host datasource joins and
 * hands over with {@see \core_reportbuilder\local\entities\base::set_table_alias()}: core's
 * course module entity has no name column, and the name lives in each module's own table.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class module_competency extends base {
    /**
     * Database tables that this entity uses.
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return [
            'competency_modulecomp',
            'course_modules',
            'modules',
        ];
    }

    /**
     * The default title for this entity.
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entityname_module_competency', 'local_dimensions');
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
        $tablealias = $this->get_table_alias('competency_modulecomp');
        $cmalias = $this->get_table_alias('course_modules');
        $modulesalias = $this->get_table_alias('modules');

        $columns = [];

        $columns[] = (new column(
            'activityname',
            new lang_string('activity'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_fields("{$cmalias}.id, {$cmalias}.course")
            ->add_callback(static function (?string $cmid, stdClass $row): string {
                if ($cmid === null || empty($row->course)) {
                    return '';
                }
                $cm = get_fast_modinfo((int) $row->course)->get_cms()[(int) $cmid] ?? null;
                return $cm === null ? '' : $cm->get_formatted_name();
            });

        $columns[] = (new column(
            'activitytype',
            new lang_string('column_activitytype', 'local_dimensions'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_join($this->get_modules_join())
            ->add_field("{$modulesalias}.name")
            ->set_is_sortable(true)
            ->add_callback(static function (?string $modname): string {
                if ($modname === null) {
                    return '';
                }
                return get_string_manager()->string_exists('modulename', "mod_{$modname}")
                    ? get_string('modulename', "mod_{$modname}")
                    : $modname;
            });

        $columns[] = (new column(
            'ruleoutcome',
            new lang_string('column_ruleoutcome_module', 'local_dimensions'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            // An outcome is a category, not a quantity: as text, the report builder offers no numeric aggregation.
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$tablealias}.ruleoutcome")
            ->set_is_sortable(true)
            ->add_callback(static fn(?string $outcome): string => self::format_outcome($outcome));

        $columns[] = (new column(
            'overridegrade',
            new lang_string('column_overridegrade', 'local_dimensions'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_BOOLEAN)
            ->add_field("{$tablealias}.overridegrade")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'boolean_as_text']);

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
        $tablealias = $this->get_table_alias('competency_modulecomp');
        $modulesalias = $this->get_table_alias('modules');

        $filters = [];

        $filters[] = (new filter(
            select::class,
            'activitytype',
            new lang_string('column_activitytype', 'local_dimensions'),
            $this->get_entity_name(),
            "{$modulesalias}.name"
        ))
            ->add_joins($this->get_joins())
            ->add_join($this->get_modules_join())
            ->set_options_callback(static function (): array {
                global $CFG;
                require_once($CFG->dirroot . '/course/lib.php');
                return get_module_types_names();
            });

        $filters[] = (new filter(
            select::class,
            'ruleoutcome',
            new lang_string('column_ruleoutcome_module', 'local_dimensions'),
            $this->get_entity_name(),
            "{$tablealias}.ruleoutcome"
        ))
            ->add_joins($this->get_joins())
            ->set_options_callback(static fn(): array => array_map('strval', core_module_competency::get_ruleoutcome_list()));

        $filters[] = (new filter(
            boolean_select::class,
            'overridegrade',
            new lang_string('column_overridegrade', 'local_dimensions'),
            $this->get_entity_name(),
            "{$tablealias}.overridegrade"
        ))
            ->add_joins($this->get_joins());

        return $filters;
    }

    /**
     * The activity's module type.
     *
     * @return string
     */
    private function get_modules_join(): string {
        $cmalias = $this->get_table_alias('course_modules');
        $modulesalias = $this->get_table_alias('modules');

        return "LEFT JOIN {modules} {$modulesalias} ON {$modulesalias}.id = {$cmalias}.module";
    }

    /**
     * Map a ruleoutcome value to its localised label.
     *
     * @param string|null $outcome Raw `ruleoutcome` value.
     * @return string Localised label, or '' when there is none.
     */
    private static function format_outcome(?string $outcome): string {
        $list = core_module_competency::get_ruleoutcome_list();
        if ($outcome === null || !isset($list[(int) $outcome])) {
            return '';
        }
        return (string) $list[(int) $outcome];
    }
}
