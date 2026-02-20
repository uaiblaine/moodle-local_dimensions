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
 * External API to get course progress.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");
require_once("$CFG->dirroot/local/dimensions/classes/calculator.php"); // Calculator class.

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use local_dimensions\calculator;

/**
 * External API to get course progress.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_course_progress extends external_api {
    /**
     * Define input parameters (args)
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'courseids' => new external_multiple_structure(
                new external_value(PARAM_INT, get_string('api_course_id', 'local_dimensions')),
                'List of course IDs to calculate',
            ),
        ]);
    }

    /**
     * The main function that executes logic
     */
    public static function execute($courseids) {
        // Automatic parameter validation.
        $params = self::validate_parameters(self::execute_parameters(), ['courseids' => $courseids]);
        $courseids = $params['courseids'];

        // Capability check (system context is enough to start,
        // but we will check enrollment/access inside the loop or calculator if necessary,
        // but for consistency, we verify general capability here).
        $systemcontext = \context_system::instance();
        self::validate_context($systemcontext);
        require_capability('local/dimensions:view', $systemcontext);

        $results = [];

        foreach ($courseids as $courseid) {
            try {
                // Calculator already performs context/enrollment checks internally
                // or assumes caller has permission.
                // The original calculator checked is_enrolled.

                $data = calculator::get_course_section_progress($courseid);

                // Prepare structured return.
                $sections = [];
                if ($data['enabled'] && !empty($data['sections'])) {
                    foreach ($data['sections'] as $s) {
                        $percentage = $s['percentage'] !== null ? (int) $s['percentage'] : 0;
                        $hasactivities = (bool) $s['has_activities'];

                        $iscompleted = $hasactivities && $percentage >= 100;
                        $isstarted = $hasactivities && $percentage > 0 && $percentage < 100;

                        $sections[] = [
                            'name' => $s['name'],
                            'percentage' => $percentage,
                            'has_activities' => $hasactivities,
                            'url' => (string) $s['url'],
                            'locked' => (bool) $s['locked'],
                            'is_completed' => $iscompleted,
                            'is_started' => $isstarted,
                        ];
                    }
                }

                $results[] = [
                    'courseid' => $courseid,
                    'enabled' => $data['enabled'],
                    'locked' => $data['locked'],
                    'formatted_start_date' => $data['formatted_start_date'],
                    'is_enrolment_start' => !empty($data['is_enrolment_start']),
                    'course_url' => $data['course_url'] ?? '',
                    'sections' => $sections,
                    'error' => '',
                ];
            } catch (\Exception $e) {
                // Error fallback.
                $results[] = [
                    'courseid' => $courseid,
                    'enabled' => false,
                    'locked' => false,
                    'formatted_start_date' => '',
                    'is_enrolment_start' => false,
                    'course_url' => '',
                    'sections' => [],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Define return structure (JSON Schema)
     */
    public static function execute_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'courseid' => new external_value(
                    PARAM_INT,
                    get_string('api_course_id', 'local_dimensions'),
                ),
                'enabled' => new external_value(
                    PARAM_BOOL,
                    get_string('api_completion_enabled', 'local_dimensions'),
                ),
                'locked' => new external_value(
                    PARAM_BOOL,
                    get_string('api_content_locked', 'local_dimensions'),
                ),
                'formatted_start_date' => new external_value(
                    PARAM_TEXT,
                    get_string('api_formatted_start_date', 'local_dimensions'),
                ),
                'is_enrolment_start' => new external_value(
                    PARAM_BOOL,
                    'Whether the date is an enrolment start date',
                    VALUE_OPTIONAL,
                ),
                'course_url' => new external_value(
                    PARAM_URL,
                    'URL to the course page',
                    VALUE_OPTIONAL,
                ),
                'error' => new external_value(
                    PARAM_TEXT,
                    get_string('api_error_message', 'local_dimensions'),
                    VALUE_OPTIONAL,
                ),
                'sections' => new external_multiple_structure(
                    new external_single_structure([
                        'name' => new external_value(
                            PARAM_TEXT,
                            get_string('api_section_name', 'local_dimensions'),
                        ),
                        'percentage' => new external_value(
                            PARAM_INT,
                            get_string('api_completion_percentage', 'local_dimensions'),
                            VALUE_OPTIONAL,
                        ),
                        'has_activities' => new external_value(
                            PARAM_BOOL,
                            get_string('api_has_activities', 'local_dimensions'),
                        ),
                        'url' => new external_value(
                            PARAM_URL,
                            get_string('api_section_url', 'local_dimensions'),
                        ),
                        'locked' => new external_value(
                            PARAM_BOOL,
                            get_string('api_is_locked', 'local_dimensions'),
                            VALUE_OPTIONAL,
                        ),
                        'is_completed' => new external_value(
                            PARAM_BOOL,
                            'Is completed',
                            VALUE_OPTIONAL,
                        ),
                        'is_started' => new external_value(
                            PARAM_BOOL,
                            'Is started',
                            VALUE_OPTIONAL,
                        ),
                    ]),
                ),
            ]),
        );
    }
}
