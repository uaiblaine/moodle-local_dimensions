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
 * List a course's activities for a competency, split into linked and available.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core\context\course as context_course;
use core_competency\course_module_competency;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use moodle_url;

/**
 * Web service: a course's activities for a competency (linked + available to link).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_competency_module_links extends external_api {
    /**
     * Define the input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'competencyid' => new external_value(PARAM_INT, 'The competency id'),
            'courseid' => new external_value(PARAM_INT, 'The course id'),
        ]);
    }

    /**
     * List the course's activities split into linked and available.
     *
     * Linked rows carry the data behind the activity card extras: whether completion tracking is
     * configured, how many other competencies share the activity, and the settings deep links.
     *
     * @param int $competencyid The competency id.
     * @param int $courseid The course id.
     * @return array Keys: linked (list), available (list), canmanage (bool).
     */
    public static function execute(int $competencyid, int $courseid): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/completionlib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'competencyid' => $competencyid,
            'courseid' => $courseid,
        ]);
        $competencyid = $params['competencyid'];
        $courseid = $params['courseid'];

        $coursecontext = context_course::instance($courseid);
        self::validate_context($coursecontext);
        require_capability('moodle/competency:coursecompetencyview', $coursecontext);
        $canmanage = has_capability('moodle/competency:coursecompetencymanage', $coursecontext);
        $canedit = has_capability('moodle/course:manageactivities', $coursecontext);

        // Existing activity links for this competency in this course, keyed by cmid.
        $outcomes = [];
        foreach (course_module_competency::get_records(['competencyid' => $competencyid]) as $record) {
            $outcomes[(int) $record->get('cmid')] = (int) $record->get('ruleoutcome');
        }

        $completioninfo = new \completion_info(get_course($courseid));
        $modinfo = get_fast_modinfo($courseid);
        $linked = [];
        $available = [];
        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible || $cm->deletioninprogress) {
                continue;
            }
            if (array_key_exists((int) $cm->id, $outcomes)) {
                $row = [
                    'cmid' => (int) $cm->id,
                    'name' => $cm->get_formatted_name(),
                    'modname' => $cm->modname,
                    'iconurl' => $cm->get_icon_url()->out(false),
                    'ruleoutcome' => $outcomes[(int) $cm->id],
                    'hascompletion' => (int) ($completioninfo->is_enabled($cm) != COMPLETION_TRACKING_NONE),
                    'sharedcount' => 0,
                    'canmanage' => (int) $canmanage,
                ];
                if ($canedit) {
                    $row['editurl'] = (new moodle_url(
                        '/course/modedit.php',
                        ['update' => (int) $cm->id, 'showonly' => 'activitycompletionheader']
                    ))->out(false);
                    $row['competenciesurl'] = (new moodle_url(
                        '/course/modedit.php',
                        ['update' => (int) $cm->id, 'showonly' => 'competenciessection']
                    ))->out(false);
                }
                $linked[] = $row;
            } else {
                $available[] = ['cmid' => (int) $cm->id, 'name' => $cm->get_formatted_name(), 'modname' => $cm->modname];
            }
        }

        // One grouped query: how many competencies each linked activity carries (shared = count - 1).
        if (!empty($linked)) {
            $cmids = array_column($linked, 'cmid');
            [$insql, $inparams] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED, 'sc');
            $counts = $DB->get_records_sql_menu(
                "SELECT mc.cmid, COUNT(1)
                   FROM {competency_modulecomp} mc
                  WHERE mc.cmid $insql
               GROUP BY mc.cmid",
                $inparams
            );
            foreach ($linked as $index => $row) {
                $linked[$index]['sharedcount'] = max(0, (int) ($counts[$row['cmid']] ?? 1) - 1);
            }
        }

        return ['linked' => $linked, 'available' => $available, 'canmanage' => $canmanage];
    }

    /**
     * Define the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'linked' => new external_multiple_structure(new external_single_structure([
                'cmid' => new external_value(PARAM_INT, 'Course module id'),
                'name' => new external_value(PARAM_RAW, 'Activity name'),
                'modname' => new external_value(PARAM_PLUGIN, 'Module type'),
                'iconurl' => new external_value(PARAM_URL, 'Activity icon URL', VALUE_OPTIONAL),
                'ruleoutcome' => new external_value(PARAM_INT, 'Module competency rule outcome'),
                'hascompletion' => new external_value(PARAM_INT, 'Whether the activity has completion tracking configured'),
                'sharedcount' => new external_value(PARAM_INT, 'Number of other competencies linked to the activity'),
                'canmanage' => new external_value(PARAM_INT, 'Whether the user can manage activity links'),
                'editurl' => new external_value(
                    PARAM_URL,
                    'URL of the activity completion settings (only when the user may edit the activity)',
                    VALUE_OPTIONAL
                ),
                'competenciesurl' => new external_value(
                    PARAM_URL,
                    'URL of the activity competencies settings (only when the user may edit the activity)',
                    VALUE_OPTIONAL
                ),
            ])),
            'available' => new external_multiple_structure(new external_single_structure([
                'cmid' => new external_value(PARAM_INT, 'Course module id'),
                'name' => new external_value(PARAM_RAW, 'Activity name'),
                'modname' => new external_value(PARAM_PLUGIN, 'Module type'),
            ])),
            'canmanage' => new external_value(PARAM_BOOL, 'Whether the user can manage activity links'),
        ]);
    }
}
