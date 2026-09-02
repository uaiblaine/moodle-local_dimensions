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
 * Behat data generator for local_dimensions.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Lets a feature create frameworks and learning plan templates inside a course category.
 *
 * Usage: the following "local_dimensions > frameworks" exist, with columns shortname, idnumber
 * and category (the course category's idnumber); the same for "local_dimensions > templates".
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_dimensions_generator extends behat_generator_base {
    /**
     * The entities this generator creates.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'frameworks' => [
                'singular' => 'framework',
                'datagenerator' => 'framework',
                'required' => ['shortname', 'idnumber', 'category'],
                'switchids' => ['category' => 'categoryid', 'scale' => 'scaleid'],
            ],
            'templates' => [
                'singular' => 'template',
                'datagenerator' => 'template',
                'required' => ['shortname', 'category'],
                'switchids' => ['category' => 'categoryid'],
            ],
        ];
    }
}
