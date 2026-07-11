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
 * Event: the plugin's custom field values of a competency changed.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\event;

use core\event\base;

/**
 * Event: the plugin's custom field values of a competency changed.
 *
 * core_customfield fires no event for data (value) changes, so colours, tags,
 * display settings, custom SCSS and card images changed invisibly next to the
 * generic competency_updated. The 'other' payload carries area, isnew and a
 * per-shortname diff — textarea bodies (custom SCSS) are redacted to the
 * literal '(updated)' marker; image changes appear as bgimage/cardimage keys.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class competency_customfields_updated extends base {
    /**
     * Initialise the event static data.
     */
    protected function init() {
        $this->data['objecttable'] = \core_competency\competency::TABLE;
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventcompetencycustomfieldsupdated', 'local_dimensions');
    }

    /**
     * Non-localised description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $fields = implode(', ', array_keys((array) $this->other['changed']));
        return "The user with id '$this->userid' changed the custom fields ($fields) of the competency "
            . "with id '$this->objectid'.";
    }

    /**
     * No restore mapping: system-scoped, never part of a course backup.
     *
     * @return string
     */
    public static function get_objectid_mapping() {
        return base::NOT_MAPPED;
    }
}
