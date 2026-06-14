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
use core_reportbuilder\local\helpers\custom_fields;
use local_dimensions\helper;

/**
 * Competency customfield entity (Dimensions area).
 *
 * Standalone entity that contributes ONLY the customfield columns/filters
 * defined by local_dimensions in the `competency` area. It does not include
 * the competency table itself — the datasource that registers this entity is
 * expected to have already joined `{competency}` and to call
 * {@see \core_reportbuilder\local\entities\base::set_table_alias()} so the
 * customfield helper joins against the same rows.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class competency_extras extends base {
    /**
     * Database tables that this entity uses.
     *
     * Listed so the entity can resolve an alias for `competency`; the actual
     * JOIN is provided by the host datasource (e.g. dimensions_competencies).
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return ['competency'];
    }

    /**
     * The default title for this entity.
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entityname_competency_extras', 'local_dimensions');
    }

    /**
     * Initialise the entity: auto-generate columns and filters from the
     * `competency` area customfield handler.
     */
    public function initialise(): base {
        $tablealias = $this->get_table_alias('competency');

        $customfields = (new custom_fields(
            "{$tablealias}.id",
            $this->get_entity_name(),
            'local_dimensions',
            helper::AREA_COMPETENCY,
        ))->add_joins($this->get_joins());

        foreach ($customfields->get_columns() as $col) {
            $this->add_column($col);
        }
        foreach ($customfields->get_filters() as $f) {
            $this->add_filter($f)->add_condition($f);
        }

        return $this;
    }
}
