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
use local_dimensions\helper;

/**
 * Learning plan template customfield entity (Dimensions area).
 *
 * Standalone entity that contributes only the customfield columns/filters
 * defined by local_dimensions in the `lp` area, the counterpart of
 * {@see competency_extras}. It does not include the template table itself —
 * the datasource that registers this entity is expected to have already
 * joined `{competency_template}` and to call
 * {@see \core_reportbuilder\local\entities\base::set_table_alias()} so the
 * customfield helper joins against the same rows.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_extras extends base {
    use dimensions_customfields;

    /**
     * Database tables that this entity uses.
     *
     * Listed so the entity can resolve an alias for `competency_template`; the
     * actual JOIN is provided by the host datasource (e.g. learning_plans).
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
        return new lang_string('entityname_template_extras', 'local_dimensions');
    }

    /**
     * Initialise the entity: the `lp` area's customfield columns and filters,
     * without the ones {@see \local_dimensions\constants::REPORT_HIDDEN_CFIELDS} lists.
     */
    public function initialise(): base {
        $this->add_dimensions_customfields($this->get_table_alias('competency_template'), helper::AREA_LP);

        return $this;
    }
}
