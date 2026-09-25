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
 * MUC caches consistent on create/update/delete events. The hub's dynamic
 * forms save their own custom fields; see observer::save_customfields_for().
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
    /**
     * Observer for competency created event.
     *
     * @param \core\event\base $event
     */
    public static function competency_created(\core\event\base $event) {
        self::save_customfields_for($event, competency_handler::create(), true);
        self::invalidate_competency_caches((int) $event->objectid);
    }

    /**
     * Observer for competency updated event.
     *
     * @param \core\event\base $event
     */
    public static function competency_updated(\core\event\base $event) {
        self::save_customfields_for($event, competency_handler::create(), false);
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

        // Remove custom field data tied to this competency, including files embedded in
        // textarea values. delete_instance is a no-op if no data exists.
        try {
            competency_handler::create()->delete_instance($instanceid);
        } catch (\Throwable $e) {
            debugging('local_dimensions: failed to delete competency customfield data for id '
                . $instanceid . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        // Fallback sweep for rows delete_instance does not match; see delete_customfield_data().
        self::delete_customfield_data($instanceid, helper::AREA_COMPETENCY);

        self::invalidate_competency_caches($instanceid);
    }

    /**
     * Observer for learning plan template created event.
     *
     * @param \core\event\base $event
     */
    public static function template_created(\core\event\base $event) {
        self::save_customfields_for($event, lp_handler::create(), true);
        self::invalidate_template_caches((int) $event->objectid);
    }

    /**
     * Observer for learning plan template updated event.
     *
     * @param \core\event\base $event
     */
    public static function template_updated(\core\event\base $event) {
        self::save_customfields_for($event, lp_handler::create(), false);
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

        // Fallback sweep for rows delete_instance does not match; see delete_customfield_data().
        self::delete_customfield_data($instanceid, helper::AREA_LP);

        self::invalidate_template_caches($instanceid, true);
    }

    /**
     * Persist custom field data carried by the current form submission, if any.
     *
     * Implements the following safety contract before delegating to the core
     * handler (which throws coding_exception when the instance id is missing):
     *
     *  1. Require a valid form submission with a matching sesskey; a POST without
     *     one returns quietly.
     *  2. Short-circuit when no customfield_* fields are present in the payload
     *     (mirrors the optimisation inside core_customfield\handler). The hub's
     *     dynamic forms save their own fields and submit through a web service
     *     call, so this observer finds no customfield_* keys there and never
     *     double-writes their data.
     *  3. Inject the authoritative instance id from the event before calling
     *     the handler.
     *
     * @param \core\event\base $event       The event triggering the save.
     * @param handler          $handler     The custom field handler to delegate to.
     * @param bool             $isnew       Whether this is the create event.
     */
    protected static function save_customfields_for(
        \core\event\base $event,
        handler $handler,
        bool $isnew
    ): void {
        /* 1. Only act on real form submissions with a valid sesskey. confirm_sesskey() with no
           argument calls required_param(), which throws on a POST without one (a web service
           call), and the throw would skip the cache invalidation that follows this call. */
        $formdata = data_submitted();
        $sesskey = optional_param('sesskey', '', PARAM_RAW);
        if (!$formdata || $sesskey === '' || !confirm_sesskey($sesskey)) {
            return;
        }

        // 2. Short-circuit when the payload carries no customfield_* keys.
        if (!preg_grep('/^customfield_/', array_keys((array) $formdata))) {
            return;
        }

        // 3. Use the event objectid as the authoritative instance id.
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
     * Delete every customfield_data row for one instance in a plugin area.
     *
     * Matches rows by instanceid and the area's field ids only. From Moodle 5.1,
     * handler::delete_instance() also filters on the customfield_data component,
     * area and itemid columns, so a row written without them survives it; this
     * sweep removes it. Embedded files are left to delete_instance().
     *
     * @param int $instanceid Competency or template id.
     * @param string $area local_dimensions customfield area (lp or competency).
     */
    protected static function delete_customfield_data(int $instanceid, string $area): void {
        global $DB;

        if ($instanceid <= 0) {
            return;
        }

        $DB->delete_records_select(
            'customfield_data',
            "instanceid = :instanceid
                 AND fieldid IN (
                     SELECT f.id
                       FROM {customfield_field} f
                       JOIN {customfield_category} c ON c.id = f.categoryid
                      WHERE c.component = :component AND c.area = :area)",
            ['instanceid' => $instanceid, 'component' => 'local_dimensions', 'area' => $area]
        );
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
