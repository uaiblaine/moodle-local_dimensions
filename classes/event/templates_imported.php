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
 * Event: a learning plan template CSV import run finished.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\event;

use core\event\base;

/**
 * Event: one CSV import run finished, with its per-outcome tally.
 *
 * The run-level companion to template_imported: it records what the operator actually chose out
 * of what they were shown, including the refusals. It names no object table, because a run is
 * not a row.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class templates_imported extends base {
    /**
     * Initialise the event static data.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventtemplatesimported', 'local_dimensions');
    }

    /**
     * Non-localised description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' applied a learning plan template CSV import: "
            . "{$this->other['created']} created, {$this->other['updated']} updated, "
            . "{$this->other['skipped']} not applied, {$this->other['changed']} refused as changed, "
            . "{$this->other['gone']} gone, {$this->other['failed']} failed.";
    }
}
