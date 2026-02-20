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
 * Hook listener for form extension.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\hook;

use core_form\hook\after_definition;
use local_dimensions\customfield\competency_handler;

/**
 * Hook listener for form extension.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class form_extension {
    /**
     * Callback for core_form\hook\after_definition.
     *
     * @param after_definition $hook
     */
    public static function callback(after_definition $hook) {
        $form = $hook->get_form();
        if (!$form || !is_object($form)) {
            return;
        }

        $debug = optional_param('lddebug', 0, PARAM_BOOL);
        if ($debug) {
            debugging('local_dimensions form class: ' . get_class($form), DEBUG_DEVELOPER);
        }

        if (debugging('', DEBUG_DEVELOPER)) {
            debugging('local_dimensions form class: ' . get_class($form), DEBUG_DEVELOPER);
        }

        if ($debug) {
            debugging('[LOCAL_DIMENSIONS] Hook callback started. Form class: ' . get_class($form), DEBUG_DEVELOPER);
        }

        if (!self::is_competency_form($form)) {
            if ($debug) {
                debugging('[LOCAL_DIMENSIONS] Not a competency form. Class: ' . get_class($form), DEBUG_DEVELOPER);
            }
            return;
        }

        $mform = $hook->get_mform();
        if (!$mform || !is_object($mform)) {
            if ($debug) {
                debugging('[LOCAL_DIMENSIONS] No mform found.', DEBUG_DEVELOPER);
            }
            return;
        }

        if ($debug) {
            debugging('[LOCAL_DIMENSIONS] Competency form detected. Adding custom fields.', DEBUG_DEVELOPER);
            $mform->addElement('static', 'local_dimensions_debug', 'Local Dimensions', 'Hook active and form detected.');
        }

        $handler = competency_handler::create();
        $instanceid = self::resolve_instanceid($form);

        if ($debug) {
            debugging('[LOCAL_DIMENSIONS] Instance ID resolved: ' . $instanceid, DEBUG_DEVELOPER);
        }

        $handler->instance_form_definition($mform, $instanceid);
    }

    /**
     * Check if the form is a tool_lp competency form.
     *
     * @param object $form
     * @return bool
     */
    private static function is_competency_form(object $form): bool {
        if ($form instanceof \tool_lp\form\competency) {
            return true;
        }

        $classname = get_class($form);
        return strpos($classname, 'tool_lp\\form\\competency') !== false;
    }

    /**
     * Resolve the competency instance ID for editing.
     *
     * @param object $form
     * @return int
     */
    private static function resolve_instanceid(object $form): int {
        if (method_exists($form, 'get_new_id')) {
            $id = (int) $form->get_new_id();
            if ($id > 0) {
                return $id;
            }
        }

        $customdata = null;
        if (method_exists($form, 'get_custom_data')) {
            $customdata = $form->get_custom_data();
        } else if (property_exists($form, '_customdata')) {
            $customdata = $form->_customdata;
        }

        $id = self::extract_id_from_customdata($customdata);
        if ($id > 0) {
            return $id;
        }

        return (int) optional_param('id', 0, PARAM_INT);
    }

    /**
     * Extract competency ID from custom data.
     *
     * @param mixed $customdata
     * @return int
     */
    private static function extract_id_from_customdata($customdata): int {
        $keys = ['id', 'competencyid'];
        if (is_array($customdata)) {
            foreach ($keys as $key) {
                if (!empty($customdata[$key])) {
                    return (int) $customdata[$key];
                }
            }
            if (
                !empty($customdata['competency']) && is_object($customdata['competency'])
                && !empty($customdata['competency']->id)
            ) {
                return (int) $customdata['competency']->id;
            }
        } else if (is_object($customdata)) {
            foreach ($keys as $key) {
                if (!empty($customdata->{$key})) {
                    return (int) $customdata->{$key};
                }
            }
            if (
                !empty($customdata->competency) && is_object($customdata->competency)
                && !empty($customdata->competency->id)
            ) {
                return (int) $customdata->competency->id;
            }
        }

        return 0;
    }
}
