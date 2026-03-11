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
use local_dimensions\helper;

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

        $simpleruletype = self::get_simplified_rule_type($ruletype);
        $config = self::decode_rule_config($competency->get('ruleconfig'));

        // Resolve scale for grade names.
        $scale = self::get_competency_scale($competency);
        $framework = api::read_framework($competency->get('competencyframeworkid'));
        $taxonomydata = helper::get_competency_taxonomy_data($competency, $framework);

        $ruledata = $simpleruletype === 'points'
            ? self::build_points_rule_data($config, $userid, $params['planid'], $scale)
            : self::build_all_rule_data($competency, $userid, $params['planid'], $scale);

        $hasmissingmandatory = $ruledata['earnedpoints'] >= $ruledata['totalrequired']
            && $ruledata['pendingmandatorycount'] > 0;
        $outcometext = helper::get_rule_outcome_text($simpleruletype, $ruleoutcome, $competency, $framework);
        $requiredwarningtext = get_string('rules_required_warning', 'local_dimensions');

        $result = [
            'hasrule' => true,
            'ruletype' => $simpleruletype,
            'ruleoutcome' => $ruleoutcome,
            'taxonomy' => $taxonomydata,
            'outcometext' => $outcometext,
            'requiredwarningtext' => $requiredwarningtext,
            'totalrequired' => $ruledata['totalrequired'],
            'earnedpoints' => $ruledata['earnedpoints'],
            'hasrequired' => $ruledata['hasrequired'],
            'mandatorycount' => $ruledata['mandatorycount'],
            'pendingmandatorycount' => $ruledata['pendingmandatorycount'],
            'hasmissingmandatory' => $hasmissingmandatory,
            'children' => $ruledata['children'],
            'childcount' => count($ruledata['children']),
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
        $usercompetency = self::get_user_competency_record($childid, $userid, $planid);
        $grade = $usercompetency ? $usercompetency->get('grade') : null;
        $isproficient = $usercompetency ? (bool) $usercompetency->get('proficiency') : false;

        // Resolve grade name from scale.
        $gradename = self::get_grade_name_from_scale($scale, $grade);

        // Resolve child-specific scale if different from parent.
        if ($grade && empty($gradename)) {
            $childscale = self::get_competency_scale($childcomp);
            $gradename = self::get_grade_name_from_scale($childscale, $grade);
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
     * Resolve the simplified rule type used by the frontend.
     *
     * @param string $ruletype Raw Moodle rule type
     * @return string
     */
    private static function get_simplified_rule_type(string $ruletype): string {
        if (strpos($ruletype, 'competency_rule_points') !== false) {
            return 'points';
        }

        return 'all';
    }

    /**
     * Decode the stored rule configuration.
     *
     * @param string|null $ruleconfig Raw JSON config
     * @return array|null
     */
    private static function decode_rule_config(?string $ruleconfig): ?array {
        if (empty($ruleconfig)) {
            return null;
        }

        return json_decode($ruleconfig, true);
    }

    /**
     * Build the response data for points-based rules.
     *
     * @param array|null $config Rule config
     * @param int $userid User ID
     * @param int $planid Plan ID
     * @param \grade_scale|null $scale Parent scale
     * @return array
     */
    private static function build_points_rule_data(?array $config, int $userid, int $planid, $scale): array {
        $children = [];
        $earnedpoints = 0;
        $hasrequired = false;
        $mandatorycount = 0;
        $pendingmandatorycount = 0;
        $totalrequired = isset($config['base']['points']) ? (int) $config['base']['points'] : 0;
        $childconfigs = $config['competencies'] ?? [];

        foreach ($childconfigs as $childconfig) {
            $childpoints = isset($childconfig['points']) ? (int) $childconfig['points'] : 0;
            $childrequired = !empty($childconfig['required']);
            $childdata = self::get_child_data((int) $childconfig['id'], $userid, $planid, $scale);
            $childdata['points'] = $childpoints;
            $childdata['required'] = $childrequired;

            if ($childrequired) {
                $hasrequired = true;
                $mandatorycount++;
                if (empty($childdata['isproficient'])) {
                    $pendingmandatorycount++;
                }
            }

            if ($childdata['isproficient']) {
                $earnedpoints += $childpoints;
            }

            $children[] = $childdata;
        }

        return [
            'children' => $children,
            'earnedpoints' => $earnedpoints,
            'totalrequired' => $totalrequired,
            'hasrequired' => $hasrequired,
            'mandatorycount' => $mandatorycount,
            'pendingmandatorycount' => $pendingmandatorycount,
        ];
    }

    /**
     * Build the response data for all-or-nothing rules.
     *
     * @param \core_competency\competency $competency Parent competency
     * @param int $userid User ID
     * @param int $planid Plan ID
     * @param \grade_scale|null $scale Parent scale
     * @return array
     */
    private static function build_all_rule_data($competency, int $userid, int $planid, $scale): array {
        $children = [];
        $completedcount = 0;
        $childcompetencies = api::list_competencies([
            'parentid' => $competency->get('id'),
            'competencyframeworkid' => $competency->get('competencyframeworkid'),
        ]);

        foreach ($childcompetencies as $childcomp) {
            $childdata = self::get_child_data($childcomp->get('id'), $userid, $planid, $scale);
            $childdata['points'] = 0;
            $childdata['required'] = false;

            if ($childdata['isproficient']) {
                $completedcount++;
            }

            $children[] = $childdata;
        }

        return [
            'children' => $children,
            'earnedpoints' => $completedcount,
            'totalrequired' => count($children),
            'hasrequired' => false,
            'mandatorycount' => 0,
            'pendingmandatorycount' => 0,
        ];
    }

    /**
     * Return the available user competency record for this plan/competency pair.
     *
     * @param int $childid Child competency ID
     * @param int $userid User ID
     * @param int $planid Plan ID
     * @return \core_competency\user_competency|\core_competency\user_competency_plan|null
     */
    private static function get_user_competency_record(int $childid, int $userid, int $planid) {
        $planrecord = \core_competency\user_competency_plan::get_record([
            'userid' => $userid,
            'competencyid' => $childid,
            'planid' => $planid,
        ]);

        if ($planrecord && $planrecord->get('grade')) {
            return $planrecord;
        }

        $liverecord = \core_competency\user_competency::get_record([
            'userid' => $userid,
            'competencyid' => $childid,
        ]);

        if ($liverecord && $liverecord->get('grade')) {
            return $liverecord;
        }

        return null;
    }

    /**
     * Resolve a grade name from a Moodle scale.
     *
     * @param \grade_scale|null $scale Scale object
     * @param mixed $grade Numeric grade value
     * @return string
     */
    private static function get_grade_name_from_scale($scale, $grade): string {
        if (!$grade || !$scale) {
            return '';
        }

        $scaleitems = $scale->load_items();
        $index = ((int) $grade) - 1;

        return isset($scaleitems[$index]) ? $scaleitems[$index] : '';
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
