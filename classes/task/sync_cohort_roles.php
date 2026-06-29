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
 * Adhoc task: synchronise cohort role assignments in the background.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\task;

/**
 * Run core's (slow, global) cohort-role sync off-request, so huge cohorts do not block the UI.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_cohort_roles extends \core\task\adhoc_task {
    /**
     * Queue a single background cohort-role sync, running as a user who holds moodle/role:manage.
     *
     * @param int $userid User whose capabilities govern the sync (the requesting admin).
     * @return void
     */
    public static function queue(int $userid): void {
        $task = new self();
        $task->set_userid($userid);
        // Dedupe: core's sync is global, so at most one pending sync per user is needed.
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Get the task name shown in the admin task UI.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_sync_cohort_roles', 'local_dimensions');
    }

    /**
     * Apply all pending cohort role assignments/removals.
     *
     * @return void
     */
    public function execute(): void {
        \tool_cohortroles\api::sync_all_cohort_roles();
    }
}
