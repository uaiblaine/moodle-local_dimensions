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
 * Adhoc task: apply or remove one enrolment method on one course, bound to a cohort.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\task;

/**
 * Apply/remove an enrol_cohort or enrol_self instance for one (course, method, cohort) combination.
 *
 * One task per combination: different combinations run in parallel, while the queue scan in
 * pending_map() keeps the same combination unavailable for re-queueing until it completes. The
 * task is idempotent (apply creates only where missing, remove deletes only what exists) and
 * exits silently when the course/cohort vanished, the enrol plugin was disabled or the
 * requesting user lost the course-level enrolment capabilities between queueing and execution.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_enrol_method extends \core\task\adhoc_task {
    /** @var string Action: create the enrolment method where missing. */
    const ACTION_APPLY = 'apply';

    /** @var string Action: delete the enrolment method where present. */
    const ACTION_REMOVE = 'remove';

    /** @var string Method: cohort sync (enrol_cohort, cohort id in customint1). */
    const METHOD_COHORT = 'cohort';

    /** @var string Method: self enrolment restricted to the cohort (enrol_self, cohort id in customint5). */
    const METHOD_SELF = 'self';

    /** @var string[] Course-level capabilities the requester needs (parity with the tab's course filter). */
    const REQUIRED_CAPS = ['moodle/course:enrolconfig', 'enrol/cohort:config', 'enrol/self:config'];

    /**
     * Queue one apply/remove action for a (course, method, cohort) combination.
     *
     * Callers are expected to have consulted pending_map() under the shared queue lock so the
     * same combination is not re-queued while still pending; the core checkforexisting flag
     * only deduplicates byte-identical payloads and is kept as a safety net.
     *
     * @param string $action self::ACTION_APPLY or self::ACTION_REMOVE.
     * @param int $courseid Course id.
     * @param string $method self::METHOD_COHORT or self::METHOD_SELF.
     * @param int $cohortid Cohort id the method is bound to.
     * @param int $roleid Role assigned by the method (apply only; ignored on remove).
     * @param int $templateid Learning plan template id (audit trail only).
     * @param int $userid Requesting user; the task runs with their capabilities.
     * @return void
     */
    public static function queue(
        string $action,
        int $courseid,
        string $method,
        int $cohortid,
        int $roleid,
        int $templateid,
        int $userid
    ): void {
        $task = new self();
        $task->set_custom_data([
            'action' => $action,
            'courseid' => $courseid,
            'method' => $method,
            'cohortid' => $cohortid,
            'roleid' => $roleid,
            'templateid' => $templateid,
        ]);
        $task->set_userid($userid);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Combination key shared by the queue scan and the web services.
     *
     * @param int $courseid Course id.
     * @param string $method Method name.
     * @param int $cohortid Cohort id.
     * @return string
     */
    public static function key(int $courseid, string $method, int $cohortid): string {
        return $courseid . '_' . $method . '_' . $cohortid;
    }

    /**
     * Map of pending (queued or still running) combinations, keyed by key().
     *
     * Includes failed-and-retrying tasks on purpose: the combination stays "processing" until
     * its task completes for good, so it cannot be re-queued meanwhile.
     *
     * @return array Map of combination key => true (test membership with isset).
     */
    public static function pending_map(): array {
        $map = [];
        foreach (\core\task\manager::get_adhoc_tasks(self::class) as $task) {
            $data = $task->get_custom_data();
            if (empty($data->courseid) || empty($data->method) || empty($data->cohortid)) {
                continue;
            }
            $map[self::key((int) $data->courseid, (string) $data->method, (int) $data->cohortid)] = true;
        }
        return $map;
    }

    /**
     * Enrol instances matching a combination (normally zero or one; manual duplicates possible).
     *
     * @param int $courseid Course id.
     * @param string $method Method name.
     * @param int $cohortid Cohort id.
     * @return array Enrol instance records keyed by id.
     */
    public static function get_instances(int $courseid, string $method, int $cohortid): array {
        global $DB;

        $cohortfield = ($method === self::METHOD_COHORT) ? 'customint1' : 'customint5';
        return $DB->get_records('enrol', [
            'courseid' => $courseid,
            'enrol' => $method,
            $cohortfield => $cohortid,
        ], 'id ASC');
    }

    /**
     * Task name for the admin task screens.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_process_enrol_method', 'local_dimensions');
    }

    /**
     * Apply or remove the enrolment method, skipping silently when the world changed.
     *
     * Only the combination-lock timeout throws (so the task retries with a fail delay); every
     * "resource vanished / capability revoked / plugin disabled" path returns without error to
     * avoid poisoning the queue.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        $data = $this->get_custom_data();
        if (empty($data) || empty($data->action) || empty($data->courseid) || empty($data->method) || empty($data->cohortid)) {
            return;
        }
        $action = (string) $data->action;
        $courseid = (int) $data->courseid;
        $method = (string) $data->method;
        $cohortid = (int) $data->cohortid;
        $roleid = (int) ($data->roleid ?? 0);
        $templateid = (int) ($data->templateid ?? 0);

        if (!in_array($action, [self::ACTION_APPLY, self::ACTION_REMOVE], true)) {
            return;
        }
        if (!in_array($method, [self::METHOD_COHORT, self::METHOD_SELF], true)) {
            return;
        }
        if ($action === self::ACTION_APPLY && $roleid <= 0) {
            return;
        }
        if (!enrol_is_enabled($method)) {
            return;
        }
        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course || !$DB->record_exists('cohort', ['id' => $cohortid])) {
            return;
        }
        $context = \context_course::instance($courseid);
        $userid = (int) $this->get_userid();
        foreach (self::REQUIRED_CAPS as $cap) {
            if (!has_capability($cap, $context, $userid)) {
                return;
            }
        }
        $plugin = enrol_get_plugin($method);
        if (!$plugin) {
            return;
        }

        // Serialise mutations of the same combination against a still-running predecessor.
        $lockfactory = \core\lock\lock_config::get_lock_factory('local_dimensions');
        $lock = $lockfactory->get_lock('enrol_' . self::key($courseid, $method, $cohortid), 60);
        if (!$lock) {
            throw new \moodle_exception('central_enrol_busy', 'local_dimensions');
        }
        try {
            if ($action === self::ACTION_APPLY) {
                $this->apply($plugin, $course, $method, $cohortid, $roleid, $templateid, $context);
            } else {
                $this->remove($plugin, $course, $method, $cohortid, $templateid, $context);
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Create the enrol instance when the combination is not configured yet.
     *
     * @param \enrol_plugin $plugin Enrol plugin for the method.
     * @param \stdClass $course Course record.
     * @param string $method Method name.
     * @param int $cohortid Cohort id.
     * @param int $roleid Role the method assigns.
     * @param int $templateid Template id for the audit event.
     * @param \context_course $context Course context.
     * @return void
     */
    private function apply(
        \enrol_plugin $plugin,
        \stdClass $course,
        string $method,
        int $cohortid,
        int $roleid,
        int $templateid,
        \context_course $context
    ): void {
        if (self::get_instances((int) $course->id, $method, $cohortid)) {
            return;
        }
        if ($method === self::METHOD_COHORT) {
            // The cohort plugin syncs the members itself at the end of add_instance(); no group.
            $fields = ['customint1' => $cohortid, 'roleid' => $roleid, 'customint2' => 0];
        } else {
            // Self enrolment: site defaults, always restricted to cohort members via customint5.
            $fields = array_merge($plugin->get_instance_defaults(), ['customint5' => $cohortid, 'roleid' => $roleid]);
        }
        $instanceid = $plugin->add_instance($course, $fields);
        if (!$instanceid) {
            return;
        }
        \local_dimensions\event\enrol_method_applied::create([
            'context' => $context,
            'objectid' => (int) $instanceid,
            'userid' => (int) $this->get_userid(),
            'other' => [
                'templateid' => $templateid,
                'cohortid' => $cohortid,
                'method' => $method,
                'roleid' => $roleid,
            ],
        ])->trigger();
    }

    /**
     * Delete every enrol instance matching the combination (unenrols their users).
     *
     * @param \enrol_plugin $plugin Enrol plugin for the method.
     * @param \stdClass $course Course record.
     * @param string $method Method name.
     * @param int $cohortid Cohort id.
     * @param int $templateid Template id for the audit event.
     * @param \context_course $context Course context.
     * @return void
     */
    private function remove(
        \enrol_plugin $plugin,
        \stdClass $course,
        string $method,
        int $cohortid,
        int $templateid,
        \context_course $context
    ): void {
        foreach (self::get_instances((int) $course->id, $method, $cohortid) as $instance) {
            // Capture before the delete: the audit event needs the removed row's objectid.
            $instanceid = (int) $instance->id;
            $roleid = (int) $instance->roleid;
            $plugin->delete_instance($instance);
            \local_dimensions\event\enrol_method_removed::create([
                'context' => $context,
                'objectid' => $instanceid,
                'userid' => (int) $this->get_userid(),
                'other' => [
                    'templateid' => $templateid,
                    'cohortid' => $cohortid,
                    'method' => $method,
                    'roleid' => $roleid,
                ],
            ])->trigger();
        }
    }
}
