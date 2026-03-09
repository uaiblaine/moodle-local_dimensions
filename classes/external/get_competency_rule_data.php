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
 * External API to retrieve competency rule data for the Rules tab.
 *
 * Reads the parent competency's rule configuration (competency_rule_points or
 * competency_rule_all) and enriches it with child competency details including
 * user grades, proficiency status and points.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core\context\system as context_system;
use core_competency\api;

/**
 * Returns rule data for a competency within a learning plan.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_competency_rule_data extends external_api {
    /**
     * Define input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'competencyid' => new external_value(PARAM_INT, 'The parent competency ID'),
            'planid' => new external_value(PARAM_INT, 'The learning plan ID'),
        ]);
    }

    /**
     * Get rule data for a competency in a learning plan.
     *
     * @param int $competencyid The parent competency ID
     * @param int $planid The learning plan ID
     * @return string JSON-encoded rule data
     */
    public static function execute($competencyid, $planid) {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'competencyid' => $competencyid,
            'planid' => $planid,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/dimensions:view', $context);

        // Read the plan to get the userid.
        $plan = api::read_plan($params['planid']);
        $userid = $plan->get('userid');

        // Read the parent competency.
        $competency = api::read_competency($params['competencyid']);
        $ruleoutcome = (int) $competency->get('ruleoutcome');
        $ruletype = $competency->get('ruletype');

        // No rule configured.
        if ($ruleoutcome === 0 || empty($ruletype)) {
            return json_encode(['hasrule' => false]);
        }

        // Determine simplified rule type.
        $simpleruletype = 'all';
        if (strpos($ruletype, 'competency_rule_points') !== false) {
            $simpleruletype = 'points';
        }

        // Parse rule config.
        $ruleconfig = $competency->get('ruleconfig');
        $config = !empty($ruleconfig) ? json_decode($ruleconfig, true) : null;

        // Resolve scale for grade names.
        $scale = self::get_competency_scale($competency);

        // Build children data.
        $children = [];
        $earnedpoints = 0;
        $totalrequired = 0;
        $hasrequired = false;

        if ($simpleruletype === 'points' && $config) {
            $totalrequired = isset($config['base']['points']) ? (int) $config['base']['points'] : 0;
            $childconfigs = isset($config['competencies']) ? $config['competencies'] : [];

            foreach ($childconfigs as $childconfig) {
                $childid = (int) $childconfig['id'];
                $childpoints = isset($childconfig['points']) ? (int) $childconfig['points'] : 0;
                $childrequired = !empty($childconfig['required']);
                if ($childrequired) {
                    $hasrequired = true;
                }

                $childdata = self::get_child_data($childid, $userid, $params['planid'], $scale);
                $childdata['points'] = $childpoints;
                $childdata['required'] = $childrequired;

                // Earned points: count if proficient.
                if ($childdata['isproficient']) {
                    $earnedpoints += $childpoints;
                }

                $children[] = $childdata;
            }
        } else {
            // Rule_all: get direct children of this competency.
            $childcomps = api::list_competencies([
                'parentid' => $params['competencyid'],
                'competencyframeworkid' => $competency->get('competencyframeworkid'),
            ]);

            $completedcount = 0;
            foreach ($childcomps as $childcomp) {
                $childdata = self::get_child_data(
                    $childcomp->get('id'),
                    $userid,
                    $params['planid'],
                    $scale
                );
                $childdata['points'] = 0;
                $childdata['required'] = false;

                if ($childdata['isproficient']) {
                    $completedcount++;
                }

                $children[] = $childdata;
            }

            $totalrequired = count($children);
            $earnedpoints = $completedcount;
        }

        // Enable evidence button setting.
        $enableevidencebtn = (bool) get_config('local_dimensions', 'enableevidencesubmitbutton');

        $result = [
            'hasrule' => true,
            'ruletype' => $simpleruletype,
            'ruleoutcome' => $ruleoutcome,
            'totalrequired' => $totalrequired,
            'earnedpoints' => $earnedpoints,
            'hasrequired' => $hasrequired,
            'children' => $children,
            'childcount' => count($children),
            'enableevidencebutton' => $enableevidencebtn,
            'userid' => $userid,
        ];

        return json_encode($result);
    }

    /**
     * Get enriched data for a child competency.
     *
     * @param int $childid The child competency ID
     * @param int $userid The user ID
     * @param int $planid The plan ID
     * @param \grade_scale|null $scale The scale object
     * @return array Child competency data
     */
    private static function get_child_data($childid, $userid, $planid, $scale) {
        $childcomp = api::read_competency($childid);

        // Try to get user_competency_plan first, then fall back to user_competency.
        $grade = null;
        $gradename = '';
        $isproficient = false;

        // For completed plans, grades are frozen in user_competency_plan.
        $uc = \core_competency\user_competency_plan::get_record([
            'userid' => $userid,
            'competencyid' => $childid,
            'planid' => $planid,
        ]);
        if ($uc && $uc->get('grade')) {
            $grade = $uc->get('grade');
            $isproficient = (bool) $uc->get('proficiency');
        } else {
            // Active plans: grades are in user_competency (live ratings).
            $uc = \core_competency\user_competency::get_record([
                'userid' => $userid,
                'competencyid' => $childid,
            ]);
            if ($uc && $uc->get('grade')) {
                $grade = $uc->get('grade');
                $isproficient = (bool) $uc->get('proficiency');
            }
        }

        // Resolve grade name from scale.
        if ($grade && $scale) {
            $scaleitems = $scale->load_items();
            $idx = ((int) $grade) - 1;
            if (isset($scaleitems[$idx])) {
                $gradename = $scaleitems[$idx];
            }
        }

        // Resolve child-specific scale if different from parent.
        if ($grade && empty($gradename)) {
            $childscale = self::get_competency_scale($childcomp);
            if ($childscale) {
                $scaleitems = $childscale->load_items();
                $idx = ((int) $grade) - 1;
                if (isset($scaleitems[$idx])) {
                    $gradename = $scaleitems[$idx];
                }
            }
        }

        $hasgrade = !empty($grade) && !empty($gradename);

        return [
            'id' => $childcomp->get('id'),
            'shortname' => $childcomp->get('shortname'),
            'hasgrade' => $hasgrade,
            'gradename' => $gradename,
            'isproficient' => $isproficient,
        ];
    }

    /**
     * Get the scale for a competency, falling back to framework scale.
     *
     * @param \core_competency\competency $competency The competency
     * @return \grade_scale|null The scale object or null
     */
    private static function get_competency_scale($competency) {
        $scaleid = $competency->get('scaleid');
        if (empty($scaleid)) {
            // Inherit from framework.
            try {
                $framework = api::read_framework($competency->get('competencyframeworkid'));
                $scaleid = $framework->get('scaleid');
            } catch (\Exception $e) {
                return null;
            }
        }

        if (empty($scaleid)) {
            return null;
        }

        $scale = \grade_scale::fetch(['id' => $scaleid]);
        return $scale ?: null;
    }

    /**
     * Define return type.
     *
     * @return external_value
     */
    public static function execute_returns() {
        return new external_value(PARAM_RAW, 'JSON-encoded competency rule data');
    }
}
