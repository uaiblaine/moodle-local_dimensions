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
 * Event: one learning plan template was written by a CSV import.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\event;

use core\event\base;

/**
 * Event: one learning plan template was created or updated by a CSV import.
 *
 * Core fires competency_template_created/updated for the row itself; this event records the
 * decision behind it — which row of which import produced it, and how many competencies came
 * with it. Triggered after the per-template transaction commits, never inside it.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_imported extends base {
    /**
     * Initialise the event static data.
     */
    protected function init() {
        $this->data['objecttable'] = \core_competency\template::TABLE;
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventtemplateimported', 'local_dimensions');
    }

    /**
     * Non-localised description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' applied CSV import row '{$this->other['itemkey']}' to the "
            . "learning plan template with id '$this->objectid' ('{$this->other['shortname']}'), outcome "
            . "'{$this->other['outcome']}', adding {$this->other['linksadded']} competency link(s).";
    }

    /**
     * No restore mapping: these events are not restored into course logs.
     *
     * @return string
     */
    public static function get_objectid_mapping() {
        return base::NOT_MAPPED;
    }
}
