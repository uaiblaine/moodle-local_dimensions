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
 * Handles saving custom field data when competencies or learning plan
 * templates are created or updated via the core tool_lp forms, and keeps
 * MUC caches consistent on create/update/delete events.
 *
 * The plugin's own edit forms (edit_competency.php, edit_template.php)
 * persist custom fields and invalidate caches inline, so the observers
 * skip those code paths to avoid double-writes.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions;

use core_customfield\handler;
use local_dimensions\customfield\competency_handler;
use local_dimensions\customfield\lp_handler;
use local_dimensions\plan_trail_cache;

/**
 * Event observer.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /** Internal edit script for competencies (skip target). */
    private const SCRIPT_EDIT_COMPETENCY = '/local/dimensions/edit_competency.php';

    /** Internal edit script for templates (skip target). */
    private const SCRIPT_EDIT_TEMPLATE = '/local/dimensions/edit_template.php';

    /**
     * Observer for competency created event.
     *
     * @param \core\event\base $event
     */
    public static function competency_created(\core\event\base $event) {
        self::save_customfields_for($event, competency_handler::create(), self::SCRIPT_EDIT_COMPETENCY, true);
        self::invalidate_competency_caches((int) $event->objectid);
    }

    /**
     * Observer for competency updated event.
     *
     * @param \core\event\base $event
     */
    public static function competency_updated(\core\event\base $event) {
        self::save_customfields_for($event, competency_handler::create(), self::SCRIPT_EDIT_COMPETENCY, false);
        self::invalidate_competency_caches((int) $event->objectid);
    }

    /**
     * Observer for competency deleted event.
     *
     * Removes any associated custom field data and invalidates caches.
     *
     * @param \core\event\base $event
     */
    public static function competency_deleted(\core\event\base $event) {
        $instanceid = (int) $event->objectid;
        if ($instanceid <= 0) {
            return;
        }

        // Remove custom field data tied to this competency. delete_instance is a no-op
        // if no data exists, so it is safe to call unconditionally.
        try {
            competency_handler::create()->delete_instance($instanceid);
        } catch (\Throwable $e) {
            debugging('local_dimensions: failed to delete competency customfield data for id '
                . $instanceid . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        self::invalidate_competency_caches($instanceid);
    }

    /**
     * Observer for learning plan template created event.
     *
     * @param \core\event\base $event
     */
    public static function template_created(\core\event\base $event) {
        self::save_customfields_for($event, lp_handler::create(), self::SCRIPT_EDIT_TEMPLATE, true);
        self::invalidate_template_caches((int) $event->objectid);
    }

    /**
     * Observer for learning plan template updated event.
     *
     * @param \core\event\base $event
     */
    public static function template_updated(\core\event\base $event) {
        self::save_customfields_for($event, lp_handler::create(), self::SCRIPT_EDIT_TEMPLATE, false);
        self::invalidate_template_caches((int) $event->objectid);
    }

    /**
     * Observer for learning plan template deleted event.
     *
     * Removes associated custom field data and invalidates caches (including the
     * cached list of valid course IDs for the template).
     *
     * @param \core\event\base $event
     */
    public static function template_deleted(\core\event\base $event) {
        $instanceid = (int) $event->objectid;
        if ($instanceid <= 0) {
            return;
        }

        try {
            lp_handler::create()->delete_instance($instanceid);
        } catch (\Throwable $e) {
            debugging('local_dimensions: failed to delete template customfield data for id '
                . $instanceid . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        self::invalidate_template_caches($instanceid, true);
    }

    /**
     * Persist custom field data carried by the current form submission, if any.
     *
     * Implements the following safety contract before delegating to the core
     * handler (which throws coding_exception when the instance id is missing or
     * when the call originates outside a valid form context):
     *
     *  1. Skip when the request originates from the plugin's own edit script,
     *     since that controller already saves the data and would cause a double
     *     write (and a clash with the picture_manager file flow).
     *  2. Require a valid form submission with a matching sesskey.
     *  3. Short-circuit when no customfield_* fields are present in the payload
     *     (mirrors the optimisation inside core_customfield\handler).
     *  4. Inject the authoritative instance id from the event before calling
     *     the handler.
     *
     * @param \core\event\base $event       The event triggering the save.
     * @param handler          $handler     The custom field handler to delegate to.
     * @param string           $skipscript  Script path that owns inline saving.
     * @param bool             $isnew       Whether this is the create event.
     */
    protected static function save_customfields_for(
        \core\event\base $event,
        handler $handler,
        string $skipscript,
        bool $isnew
    ): void {
        // 1. Skip if the submit was handled by the plugin's own edit form.
        $script = qualified_me();
        if ($script !== false && strpos($script, $skipscript) !== false) {
            return;
        }

        // 2. Only act on real form submissions with a valid sesskey.
        $formdata = data_submitted();
        if (!$formdata || !confirm_sesskey()) {
            return;
        }

        // 3. Short-circuit when the payload carries no customfield_* keys.
        if (!preg_grep('/^customfield_/', array_keys((array) $formdata))) {
            return;
        }

        // 4. Use the event objectid as the authoritative instance id.
        $instanceid = (int) $event->objectid;
        if ($instanceid <= 0) {
            return;
        }
        $formdata->id = $instanceid;

        try {
            $handler->instance_form_save($formdata, $isnew);
        } catch (\Throwable $e) {
            debugging('local_dimensions: instance_form_save failed for instance '
                . $instanceid . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Invalidate competency-scoped caches.
     *
     * @param int $competencyid
     */
    protected static function invalidate_competency_caches(int $competencyid): void {
        if ($competencyid <= 0) {
            return;
        }
        competency_metadata_cache::invalidate_competency($competencyid);
        if (get_config('local_dimensions', 'enablecustomscss')) {
            scss_manager::invalidate_cache($competencyid, 'competency');
        }
    }

    /**
     * Invalidate template-scoped caches.
     *
     * @param int  $templateid
     * @param bool $includecourses Also invalidate the template_courses cache (delete only).
     */
    protected static function invalidate_template_caches(int $templateid, bool $includecourses = false): void {
        if ($templateid <= 0) {
            return;
        }
        template_metadata_cache::invalidate_template($templateid);
        if (get_config('local_dimensions', 'enablecustomscss')) {
            scss_manager::invalidate_cache($templateid, 'lp');
        }
        if ($includecourses) {
            template_course_cache::invalidate_template($templateid);
        }
    }

    /**
     * Observer for user competency rated event.
     * Invalidates all plan trail caches for the affected user.
     *
     * @param \core\event\base $event
     */
    public static function user_competency_rated(\core\event\base $event) {
        $userid = $event->relateduserid;
        if ($userid) {
            plan_trail_cache::invalidate_user((int)$userid);
        }
    }

    /**
     * Observer for user competency rated in plan event.
     * Invalidates the specific plan trail cache.
     *
     * @param \core\event\base $event
     */
    public static function user_competency_rated_in_plan(\core\event\base $event) {
        $other = $event->other;
        $userid = $event->relateduserid;
        if ($userid && !empty($other['planid'])) {
            plan_trail_cache::invalidate_plan((int)$other['planid'], (int)$userid);
        }
    }

    /**
     * Observer for evidence created event.
     * Invalidates all plan trail caches for the affected user (catch-all).
     *
     * @param \core\event\base $event
     */
    public static function evidence_created(\core\event\base $event) {
        $userid = $event->relateduserid;
        if ($userid) {
            plan_trail_cache::invalidate_user((int)$userid);
        }
    }
}
