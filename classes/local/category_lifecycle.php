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

namespace local_dimensions\local;

use core_competency\api;
use core_competency\competency;
use core_competency\competency_framework;
use core_competency\plan;
use core_competency\template;

/**
 * What happens to a course category's frameworks and learning plan templates when it goes.
 *
 * Core's course category deletion knows nothing about competency data: delete_full() moves
 * cohorts and deletes grade categories, content bank items and calendar events, then drops
 * the context, leaving competency_framework and competency_template rows pointing at a
 * deleted context - invisible in every listing, unreachable and undeletable. Neither core
 * nor tool_lp registers a callback. The category entry of the hub makes category-scoped
 * frameworks and templates normal rather than exceptional, so this class answers the four
 * callbacks core offers (lib.php forwards to it):
 *
 * - "Delete all" refuses when anything in the category is in use, mirroring core's own
 *   refusal to delete a competency that a course, plan or template still references, and
 *   deletes the rest through the competency API so nothing is orphaned.
 * - "Move contents" re-homes the category's frameworks and templates to the destination,
 *   the way core moves the category's cohorts, and is offered only to a viewer who may
 *   manage them there.
 *
 * Only the category's OWN context is handled per call: delete_full() recurses into child
 * categories and calls the callbacks again for each, and delete_move() moves child
 * categories whole, contexts included.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category_lifecycle {
    /**
     * Frameworks living in the category's own context.
     *
     * @param int $categoryid The course category id.
     * @return competency_framework[]
     */
    public static function frameworks(int $categoryid): array {
        $context = \context_coursecat::instance($categoryid, IGNORE_MISSING);
        return $context ? competency_framework::get_records(['contextid' => $context->id], 'shortname') : [];
    }

    /**
     * Learning plan templates living in the category's own context.
     *
     * @param int $categoryid The course category id.
     * @return template[]
     */
    public static function templates(int $categoryid): array {
        $context = \context_coursecat::instance($categoryid, IGNORE_MISSING);
        return $context ? template::get_records(['contextid' => $context->id], 'shortname') : [];
    }

    /**
     * Count what the category holds and how much of it is in use.
     *
     * A framework is in use when core would refuse to delete any of its competencies (linked
     * to a course, an activity, a template or a plan); a template is in use when a plan is
     * still linked to it.
     *
     * @param int $categoryid The course category id.
     * @return array Keys: frameworks (int), templates (int), inuse (int, objects that block a deletion).
     */
    public static function summary(int $categoryid): array {
        $frameworks = self::frameworks($categoryid);
        $templates = self::templates($categoryid);
        $inuse = 0;
        foreach ($frameworks as $framework) {
            if (!competency::can_all_be_deleted(competency::get_ids_by_frameworkid((int) $framework->get('id')))) {
                $inuse++;
            }
        }
        foreach ($templates as $template) {
            if (plan::count_records(['templateid' => (int) $template->get('id')]) > 0) {
                $inuse++;
            }
        }
        return ['frameworks' => count($frameworks), 'templates' => count($templates), 'inuse' => $inuse];
    }

    /**
     * The line the category deletion form shows about competency data, or '' when there is none.
     *
     * @param int $categoryid The course category id.
     * @return string
     */
    public static function describe(int $categoryid): string {
        $summary = self::summary($categoryid);
        if ($summary['frameworks'] === 0 && $summary['templates'] === 0) {
            return '';
        }
        return get_string('central_categorydelete_contents', 'local_dimensions', (object) $summary);
    }

    /**
     * Delete the category's frameworks and templates, or refuse when any of them is in use.
     *
     * Runs from core's pre_course_category_delete callback, before core deletes anything, so a
     * refusal leaves the category and everything in it untouched.
     *
     * @param \stdClass $category The course category record.
     * @return void
     * @throws \moodle_exception When a framework or template in the category is still in use.
     */
    public static function delete_contents(\stdClass $category): void {
        $summary = self::summary((int) $category->id);
        if ($summary['inuse'] > 0) {
            $summary['category'] = format_string($category->name, true, ['context' => \context_system::instance()]);
            throw new \moodle_exception('central_categorydelete_blocked', 'local_dimensions', '', (object) $summary);
        }
        foreach (self::templates((int) $category->id) as $template) {
            api::delete_template((int) $template->get('id'), false);
        }
        foreach (self::frameworks((int) $category->id) as $framework) {
            api::delete_framework((int) $framework->get('id'));
        }
    }

    /**
     * Whether the viewer may move the category's frameworks and templates into another category.
     *
     * True when the category holds nothing of ours; otherwise the viewer must be able to manage
     * each kind at the destination, or the objects would land where the mover cannot reach them.
     *
     * @param int $categoryid The category being deleted.
     * @param int $newcategoryid The category its contents move to.
     * @return bool
     */
    public static function can_move_contents(int $categoryid, int $newcategoryid): bool {
        $newcontext = \context_coursecat::instance($newcategoryid, IGNORE_MISSING);
        if (!$newcontext) {
            return false;
        }
        if (!empty(self::frameworks($categoryid)) && !competency_framework::can_manage_context($newcontext)) {
            return false;
        }
        if (!empty(self::templates($categoryid)) && !template::can_manage_context($newcontext)) {
            return false;
        }
        return true;
    }

    /**
     * Re-home the category's frameworks and templates to another category's context.
     *
     * One UPDATE per table, the way core's cohort_delete_category() moves cohorts: the
     * persistents refuse a context change by validation ("the context must never change"),
     * and this is the one moment the change is the point. Plans keep working (they reference
     * the template by id), the plugin's custom-field data is keyed by instance id, and its
     * pictures live at the system context.
     *
     * @param int $categoryid The category being deleted.
     * @param int $newcategoryid The category its contents move to.
     * @return void
     */
    public static function move_contents(int $categoryid, int $newcategoryid): void {
        global $DB;
        $oldcontext = \context_coursecat::instance($categoryid, IGNORE_MISSING);
        if (!$oldcontext) {
            return;
        }
        $newcontext = \context_coursecat::instance($newcategoryid);
        $params = ['newcontext' => $newcontext->id, 'oldcontext' => $oldcontext->id, 'now' => time()];
        foreach ([competency_framework::TABLE, template::TABLE] as $table) {
            $DB->execute(
                "UPDATE {" . $table . "} SET contextid = :newcontext, timemodified = :now WHERE contextid = :oldcontext",
                $params
            );
        }
    }
}
