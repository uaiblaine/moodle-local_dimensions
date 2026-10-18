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
use core_competency\evidence as core_evidence;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\{date, number, select, text};
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\{column, filter};
use html_writer;
use local_dimensions\reportbuilder\local\helpers\rating;
use stdClass;

/**
 * Competency evidence entity.
 *
 * Wraps the core `competency_evidence` table: what happened to a user competency (a log entry,
 * a completion, an overridden rating), with its description, link, rating and note.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class evidence extends base {
    /**
     * Database tables that this entity uses.
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return ['competency_evidence'];
    }

    /**
     * The default title for this entity.
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entityname_evidence', 'local_dimensions');
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
        $alias = $this->get_table_alias('competency_evidence');

        $columns = [];

        // An action is a category, not a quantity: as text, no numeric aggregation is offered.
        $columns[] = (new column(
            'action',
            new lang_string('column_evidenceaction', 'local_dimensions'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.action")
            ->set_is_sortable(true)
            ->add_callback(static fn(?string $action): string => self::action_name($action));

        $columns[] = (new column(
            'description',
            new lang_string('description'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_fields("{$alias}.descidentifier, {$alias}.desccomponent, {$alias}.desca")
            ->add_callback(static function (?string $identifier, stdClass $row): string {
                if ($identifier === null || $identifier === '') {
                    return '';
                }
                if (!get_string_manager()->string_exists($identifier, (string) $row->desccomponent)) {
                    return s($identifier);
                }
                $a = $row->desca === null ? null : json_decode((string) $row->desca);
                return format_string((string) new lang_string($identifier, (string) $row->desccomponent, $a));
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
            'rating',
            new lang_string('rating', 'core_competency'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("{$alias}.grade")
            ->add_field(rating::scaleid_sql_for_usercompetency("{$alias}.usercompetencyid"), 'scaleid')
            ->set_is_sortable(true, ["{$alias}.grade"])
            ->add_callback(static fn($grade, stdClass $row): string => rating::format($grade, $row->scaleid ?? null));

        $columns[] = (new column(
            'note',
            new lang_string('column_evidencenote', 'local_dimensions'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_LONGTEXT)
            ->add_field("{$alias}.note")
            ->add_callback(static fn(?string $note): string => $note === null ? '' : s($note));

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

        return $columns;
    }

    /**
     * Native filters for the entity.
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $alias = $this->get_table_alias('competency_evidence');

        $filters = [];

        $filters[] = (new filter(
            select::class,
            'action',
            new lang_string('column_evidenceaction', 'local_dimensions'),
            $this->get_entity_name(),
            "{$alias}.action"
        ))
            ->add_joins($this->get_joins())
            ->set_options_callback(static function (): array {
                $options = [];
                foreach ([core_evidence::ACTION_LOG, core_evidence::ACTION_COMPLETE, core_evidence::ACTION_OVERRIDE] as $action) {
                    $options[$action] = self::action_name((string) $action);
                }
                return $options;
            });

        $filters[] = (new filter(
            number::class,
            'rating',
            new lang_string('filter_ratingvalue', 'local_dimensions'),
            $this->get_entity_name(),
            "{$alias}.grade"
        ))->add_joins($this->get_joins());

        $filters[] = (new filter(
            text::class,
            'note',
            new lang_string('column_evidencenote', 'local_dimensions'),
            $this->get_entity_name(),
            "{$alias}.note"
        ))->add_joins($this->get_joins());

        $filters[] = (new filter(
            date::class,
            'timecreated',
            new lang_string('timecreated', 'core_reportbuilder'),
            $this->get_entity_name(),
            "{$alias}.timecreated"
        ))->add_joins($this->get_joins());

        return $filters;
    }

    /**
     * Localised name of an evidence action.
     *
     * @param string|null $action Raw competency_evidence.action value.
     * @return string The name, or '' when unknown.
     */
    private static function action_name(?string $action): string {
        $map = [
            core_evidence::ACTION_LOG => 'evidenceaction_log',
            core_evidence::ACTION_COMPLETE => 'evidenceaction_complete',
            core_evidence::ACTION_OVERRIDE => 'evidenceaction_override',
        ];
        if ($action === null || !isset($map[(int) $action])) {
            return '';
        }
        return get_string($map[(int) $action], 'local_dimensions');
    }
}
