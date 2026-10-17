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

use core_reportbuilder\local\helpers\custom_fields;
use local_dimensions\constants;

/**
 * Adds one local_dimensions customfield area's columns, filters and conditions to an entity.
 *
 * Shared by {@see competency_extras} and {@see template_extras}. The fields listed in
 * {@see constants::REPORT_HIDDEN_CFIELDS} are left out.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait dimensions_customfields {
    /**
     * Add the area's report elements, keyed to the instance id column.
     *
     * @param string $tablealias Alias of the table whose `id` is the customfield instance id.
     * @param string $area The handler area (helper::AREA_LP or helper::AREA_COMPETENCY).
     * @return void
     */
    protected function add_dimensions_customfields(string $tablealias, string $area): void {
        $customfields = (new custom_fields(
            "{$tablealias}.id",
            $this->get_entity_name(),
            'local_dimensions',
            $area,
        ))->add_joins($this->get_joins());

        $hidden = array_map(static fn(string $shortname): string => 'customfield_' . $shortname, constants::REPORT_HIDDEN_CFIELDS);
        foreach ($customfields->get_columns() as $col) {
            if (!in_array($col->get_name(), $hidden, true)) {
                $this->add_column($col);
            }
        }
        foreach ($customfields->get_filters() as $f) {
            if (!in_array($f->get_name(), $hidden, true)) {
                $this->add_filter($f)->add_condition($f);
            }
        }
    }
}
