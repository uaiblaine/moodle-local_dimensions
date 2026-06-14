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

namespace local_dimensions\reportbuilder\datasource;

use core_competency\reportbuilder\datasource\competencies;
use core_competency\reportbuilder\local\entities\competency as competency_entity;
use local_dimensions\reportbuilder\local\entities\competency_extras;

/**
 * Competencies datasource extended with local_dimensions customfields.
 *
 * Inherits everything the core competencies datasource provides (framework,
 * competency, usercompetency, user, cohort entities) and adds a
 * {@see competency_extras} entity contributing the customfield columns and
 * filters local_dimensions defines in the `competency` area.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dimensions_competencies extends competencies {
    /**
     * Human-readable name shown in the report-builder UI.
     */
    public static function get_name(): string {
        return get_string('datasource_dimensions_competencies', 'local_dimensions');
    }

    /**
     * Wire entities and joins.
     *
     * Delegates the standard competency wiring to the parent, then attaches
     * our customfield-only entity using the same `competency` alias parent
     * registered (so the customfield helper's JOIN against `customfield_data`
     * keys correctly to the competency rows the report already exposes).
     */
    protected function initialise(): void {
        parent::initialise();

        $competencyalias = null;
        foreach ($this->get_entities() as $entity) {
            if ($entity instanceof competency_entity) {
                $competencyalias = $entity->get_table_alias('competency');
                $competencyjoins = $entity->get_joins();
                break;
            }
        }

        if ($competencyalias === null) {
            return;
        }

        $extrasentity = new competency_extras();
        $extrasentity->set_table_alias('competency', $competencyalias);
        $extrasentity->add_joins($competencyjoins);
        $this->add_entity($extrasentity);
        $this->add_all_from_entity($extrasentity->get_entity_name());
    }
}
