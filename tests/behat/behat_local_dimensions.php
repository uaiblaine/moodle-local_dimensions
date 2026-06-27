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
 * Behat step definitions for local_dimensions.
 *
 * @package    local_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.
require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Step definitions for local_dimensions Behat features.
 *
 * @package    local_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_dimensions extends behat_base {
    /**
     * Creates a site-wide grading scale with the given comma-separated values.
     *
     * The competency edit modal offers every site scale in its "Scale" dropdown, so a
     * deterministic scale is needed to exercise the "Configure scale" dialogue.
     *
     * @Given /^a competency scale "(?P<name_string>(?:[^"]|\\")*)" with values "(?P<values_string>(?:[^"]|\\")*)" exists$/
     * @param string $name Scale name shown in the dropdown.
     * @param string $values Comma-separated scale values (e.g. "Bad,Good").
     */
    public function a_competency_scale_with_values_exists(string $name, string $values): void {
        \testing_util::get_data_generator()->create_scale([
            'name' => $name,
            'scale' => $values,
        ]);
    }
}
