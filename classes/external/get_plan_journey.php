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
 * The plan overview's Journey view, one page at a time.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core\context\system as context_system;
use core_competency\api;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_dimensions\local\plan_journey;

/**
 * A page of a plan's evidence over time (local_dimensions/plan_journey), newest first.
 *
 * The checks are get_user_competency_summary_in_plan's, the per-competency read the Progress tab already
 * makes: the system context validated, the guest refused, competencies enabled, the plugin's view capability;
 * then plan_journey::page() reads the plan through core and asks the overview's detail rule before it reads
 * a record. Every refusal is core's own exception, raised before anything is built.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_plan_journey extends external_api {
    /**
     * The plan and the cursor of the page to read.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'planid' => new external_value(PARAM_INT, 'The plan id'),
            'beforetime' => new external_value(PARAM_INT, 'The previous page\'s cursor time, 0 for the first', VALUE_DEFAULT, 0),
            'beforeid' => new external_value(PARAM_INT, 'The previous page\'s cursor evidence id, 0 for none', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * One page of the plan's journey for the current user.
     *
     * @param int $planid The plan id.
     * @param int $beforetime The cursor's time, 0 for the first page.
     * @param int $beforeid The cursor's evidence id, 0 for the first page.
     * @return array See execute_returns().
     * @throws \moodle_exception noguest for the guest; invalidplan for a plan that does not exist.
     * @throws \invalid_parameter_exception For a cursor that is not two positive parts or two zeros.
     * @throws \required_capability_exception When the viewer may not read the plan or its owner's competencies.
     */
    public static function execute(int $planid, int $beforetime = 0, int $beforeid = 0): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'planid' => $planid,
            'beforetime' => $beforetime,
            'beforeid' => $beforeid,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }
        api::require_enabled();
        require_capability('local/dimensions:view', $context);

        return plan_journey::page((int) $params['planid'], (int) $params['beforetime'], (int) $params['beforeid']);
    }

    /**
     * The page: an allowlist of what core's evidence exporter shows the same viewer, plain texts and http(s) addresses.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'entries' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'The evidence id'),
                'timecreated' => new external_value(PARAM_INT, 'When it was recorded'),
                'datetime' => new external_value(PARAM_TEXT, 'When it was recorded, as a datetime attribute'),
                'monthkey' => new external_value(PARAM_ALPHANUMEXT, 'Its month in the reader\'s zone, YYYY-MM'),
                'monthlabel' => new external_value(PARAM_TEXT, 'Its month, named'),
                'daylabel' => new external_value(PARAM_TEXT, 'Its day, named'),
                'competencyid' => new external_value(PARAM_INT, 'The plan competency it belongs to'),
                'competencyname' => new external_value(PARAM_TEXT, 'That competency\'s name, plain'),
                'description' => new external_value(PARAM_TEXT, 'Core\'s description of what happened, plain'),
                'hasrating' => new external_value(PARAM_BOOL, 'Whether it brought a rating'),
                'rating' => new external_value(PARAM_TEXT, 'The rating\'s name on the scale, plain'),
                'hasactor' => new external_value(PARAM_BOOL, 'Whether a user is named as its author'),
                'actor' => new external_value(PARAM_TEXT, 'Who did it, as a sentence, plain'),
                'hasnote' => new external_value(PARAM_BOOL, 'Whether it carries a note'),
                'note' => new external_value(PARAM_TEXT, 'The note, plain'),
                'hasurl' => new external_value(PARAM_BOOL, 'Whether it carries an http(s) address'),
                'url' => new external_value(PARAM_URL, 'The address, http(s) only, empty otherwise'),
            ])),
            'folds' => new external_multiple_structure(new external_single_structure([
                'monthkey' => new external_value(PARAM_ALPHANUMEXT, 'The month, YYYY-MM'),
                'monthlabel' => new external_value(PARAM_TEXT, 'The month, named'),
                'count' => new external_value(PARAM_INT, 'Logs of other components in that month of the page'),
                'timecreated' => new external_value(PARAM_INT, 'The newest of them'),
            ])),
            'hasmore' => new external_value(PARAM_BOOL, 'Whether an older page exists'),
            'nexttime' => new external_value(PARAM_INT, 'The next page\'s cursor time, 0 for none'),
            'nextid' => new external_value(PARAM_INT, 'The next page\'s cursor evidence id, 0 for none'),
            'planstart' => new external_single_structure([
                'timecreated' => new external_value(PARAM_INT, 'When the plan was created'),
                'datetime' => new external_value(PARAM_TEXT, 'The same, as a datetime attribute'),
                'monthkey' => new external_value(PARAM_ALPHANUMEXT, 'Its month, YYYY-MM'),
                'monthlabel' => new external_value(PARAM_TEXT, 'Its month, named'),
                'daylabel' => new external_value(PARAM_TEXT, 'Its day, named'),
                'label' => new external_value(PARAM_TEXT, 'The marker\'s words'),
            ]),
        ]);
    }
}
