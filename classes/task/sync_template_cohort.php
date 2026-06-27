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
 * Adhoc task: generate learning plans for a template cohort in the background.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\task;

use core_competency\api;
use core_competency\template;
use core_competency\template_cohort;

/**
 * Create missing learning plans from a template cohort (queued so large cohorts do not block requests).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_template_cohort extends \core\task\adhoc_task {
    /**
     * Queue a background sync for a template/cohort pair, running as the requesting user.
     *
     * @param int $templateid Template id.
     * @param int $cohortid Cohort id.
     * @param int $userid User whose capabilities govern plan creation.
     * @param bool $recreateunlinked Whether to recreate plans that were unlinked.
     * @return void
     */
    public static function queue(int $templateid, int $cohortid, int $userid, bool $recreateunlinked = false): void {
        $task = new self();
        $task->set_custom_data([
            'templateid' => $templateid,
            'cohortid' => $cohortid,
            'recreateunlinked' => $recreateunlinked,
        ]);
        $task->set_userid($userid);
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Get the task name shown in the admin task UI.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_sync_template_cohort', 'local_dimensions');
    }

    /**
     * Create the cohort's missing plans, skipping silently if the template/relation is gone.
     *
     * @return void
     */
    public function execute(): void {
        $data = $this->get_custom_data();
        if (empty($data) || empty($data->templateid) || empty($data->cohortid)) {
            return;
        }
        $templateid = (int) $data->templateid;
        $cohortid = (int) $data->cohortid;
        $recreateunlinked = !empty($data->recreateunlinked);

        if (!template::record_exists($templateid)) {
            return;
        }
        if (!template_cohort::get_relation($templateid, $cohortid)->get('id')) {
            return;
        }
        api::create_plans_from_template_cohort($templateid, $cohortid, $recreateunlinked);
    }
}
