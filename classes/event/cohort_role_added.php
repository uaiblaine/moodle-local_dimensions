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
 * Event: a cohort role rule was added from the hub.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\event;

use core\event\base;

/**
 * Event: a cohort role rule was added from the hub.
 *
 * tool_cohortroles fires no event for the mapping decision — only the
 * per-member role_assigned events fire later, from the sync task, attributed
 * to the cron runner. This event records who created the rule and when.
 * relateduserid is the role holder; 'other' carries templateid, cohortid and
 * roleid.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cohort_role_added extends base {
    /**
     * Initialise the event static data.
     */
    protected function init() {
        $this->data['objecttable'] = \tool_cohortroles\cohort_role_assignment::TABLE;
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventcohortroleadded', 'local_dimensions');
    }

    /**
     * Non-localised description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' gave the user with id '$this->relateduserid' the role with id "
            . "'{$this->other['roleid']}' over the members of the cohort with id '{$this->other['cohortid']}' "
            . "(template with id '{$this->other['templateid']}').";
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
