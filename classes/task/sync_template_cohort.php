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
        /* Deduplicate against an identical pending task (attach + a manual sync
           click both queue for the same pair): a doubled run would race
           get_missing_plans and create every plan twice — competency_plan has
           no unique index on (templateid, userid). */
        \core\task\manager::queue_adhoc_task($task, true);
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
     * Create the cohort's missing plans.
     *
     * Skips silently when the template or the relation is gone, and prints the reason when core
     * would refuse the pair ({@see self::refusal()}).
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
        $refusal = self::refusal(new template($templateid), $cohortid);
        if ($refusal !== '') {
            mtrace('local_dimensions: no plans created from template ' . $templateid . ' for cohort '
                . $cohortid . ': ' . $refusal . '.');
            return;
        }
        api::create_plans_from_template_cohort($templateid, $cohortid, $recreateunlinked);
    }

    /**
     * Why api::create_plans_from_template_cohort() would refuse to run, checked before calling it.
     *
     * Mirrors the preconditions that method throws on, in its order, for the user the task runs
     * as. Each one outlasts a retry, and a throwing adhoc task is retried and blocks a fresh
     * queue() of the same pair, so the task reports them and finishes instead.
     *
     * @param template $template The template the plans come from.
     * @param int $cohortid The cohort whose members get plans.
     * @return string The reason, or '' when the plans can be created.
     */
    private static function refusal(template $template, int $cohortid): string {
        global $DB;

        if (!api::is_enabled()) {
            return 'competencies are disabled';
        }
        if (!$template->can_read()) {
            return 'the task user cannot view the template';
        }
        if (!$template->get('visible')) {
            return 'the template is hidden';
        }
        $cohort = $DB->get_record('cohort', ['id' => $cohortid], 'id, contextid, visible');
        if (!$cohort) {
            return 'the cohort no longer exists';
        }
        if (!$cohort->visible && !has_capability('moodle/cohort:view', \context::instance_by_id($cohort->contextid))) {
            return 'the task user cannot view the hidden cohort';
        }
        return '';
    }
}
