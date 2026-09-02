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

/**
 * Data generator for local_dimensions: competency objects in a course category.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Creates frameworks and learning plan templates in a course category context.
 *
 * Core's competency generator hardcodes the system context when none is given and its Behat
 * generator has no way to name one, so a scenario about the hub's category entry could only
 * create site-wide objects, and would pass while proving nothing about category scoping. These
 * two methods take a course category id (the Behat generator resolves it from the idnumber) and
 * hand the matching context to core's generator.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_dimensions_generator extends component_generator_base {
    /**
     * Create a competency framework, in a course category when categoryid is given.
     *
     * @param array $record Framework fields; categoryid (int) selects the course category context.
     * @return \core_competency\competency_framework
     */
    public function create_framework(array $record): \core_competency\competency_framework {
        return $this->datagenerator->get_plugin_generator('core_competency')
            ->create_framework($this->with_category_context($record));
    }

    /**
     * Create a learning plan template, in a course category when categoryid is given.
     *
     * @param array $record Template fields; categoryid (int) selects the course category context.
     * @return \core_competency\template
     */
    public function create_template(array $record): \core_competency\template {
        return $this->datagenerator->get_plugin_generator('core_competency')
            ->create_template($this->with_category_context($record));
    }

    /**
     * Swap a categoryid for the matching course category context id.
     *
     * @param array $record The record as given.
     * @return array The record with contextid set from categoryid, which is removed.
     */
    private function with_category_context(array $record): array {
        if (!empty($record['categoryid'])) {
            $record['contextid'] = context_coursecat::instance((int) $record['categoryid'])->id;
        }
        unset($record['categoryid']);
        return $record;
    }
}
