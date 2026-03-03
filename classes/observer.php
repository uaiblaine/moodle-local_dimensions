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
 * Event observer for local_dimensions plugin.
 *
 * Handles saving custom field data when competencies are created or updated
 * via the core tool_lp forms (not the local_dimensions edit form, which
 * handles its own saving).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions;

use local_dimensions\customfield\competency_handler;

/**
 * Event observer.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Observer for competency created event.
     *
     * @param \core\event\base $event
     */
    public static function competency_created(\core\event\base $event) {
        self::save_custom_fields($event);
    }

    /**
     * Observer for competency updated event.
     *
     * @param \core\event\base $event
     */
    public static function competency_updated(\core\event\base $event) {
        self::save_custom_fields($event);
    }

    /**
     * Save custom fields data from the form submission.
     *
     * Uses Moodle's data_submitted() to retrieve sanitized form data
     * instead of accessing $_POST directly, ensuring proper sesskey
     * validation and data cleaning.
     *
     * @param \core\event\base $event
     */
    protected static function save_custom_fields(\core\event\base $event) {
        // Only process if there was a valid form submission with sesskey.
        $formdata = data_submitted();
        if (!$formdata || !confirm_sesskey()) {
            return;
        }

        $handler = competency_handler::create();
        $instanceid = $event->objectid;

        $handler->instance_form_save($formdata, $instanceid);
    }
}
