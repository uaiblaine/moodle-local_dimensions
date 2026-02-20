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
     * Save custom fields data.
     *
     * @param \core\event\base $event
     */
    protected static function save_custom_fields(\core\event\base $event) {
        $handler = competency_handler::create();
        // The event objectid is the competency ID.
        $instanceid = $event->objectid;

        // We need to save the data from the form submission.
        // Since we don't have direct access to the form object here, we rely on the handler
        // to pick up the data from the POST request (optional_param_array presumably used by core_customfield).
        // core_customfield\handler::instance_form_save($data) expects an object with the data.
        // However, usually we pass the full form data object.

        // Let's try to get the data from the generic submit.
        // Note: instance_form_save checks for field names in the data object.
        // We can reconstruct a data object from the POST data if needed, but let's see if we can get it via
        // standard form submission patterns.

        // BETTER: Use $handler->instance_form_save((object)$_POST);
        // But $_POST might be unsafe or raw.
        // Moodle forms submit data cleaned.

        // Let's use optional_param_array to be safer, for the custom fields.
        // Actually, instance_form_save iterates over the defined fields and looks for them in the $data object.
        // So we can pass (object)$_POST (or better, use required_param_array if possible, but we don't know the keys easily).
        // Since we are in an observer, we are likely in the same request as the form submit.

        // A common pattern in local plugins for this.
        $data = (object) $_POST;
        // This is a bit raw, but instance_form_save does type cleaning based on the field definition.

        $handler->instance_form_save($data, $instanceid);
    }
}
