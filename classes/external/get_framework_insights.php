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
 * Tell the hub's workspace how a framework's competencies are used, ruled and related, and what
 * its structure gets wrong.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core_competency\api;
use core_competency\competency;
use core_competency\competency_framework;
use core_competency\competency_rule_all;
use core_competency\competency_rule_points;
use core_competency\course_competency;
use core_competency\course_module_competency;
use core_competency\plan;
use core_competency\plan_competency;
use core_competency\related_competency;
use core_competency\template_competency;
use core_competency\user_competency_plan;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_dimensions\constants;
use local_dimensions\helper;
use local_dimensions\local\contrast_ratio;
use local_dimensions\local\unlisted_categories;

/**
 * Web service: read-only insights over one competency framework, for the hub's lenses and checker.
 *
 * One answer per framework: per-competency usage, rules, relation counts, the structure's
 * findings and a summary. Each list holds only the competencies with something to say; a
 * competency absent from a list has zeros there. The whole answer costs a fixed number of
 * statements, joined on the framework id, whatever the framework's size.
 *
 * Nothing is cached, on purpose. The usage part depends on the viewer, and the rest is a handful
 * of aggregate statements over shared tables, so the answer is computed from the database on
 * whichever web node takes the request: nothing node-local is written or read, and nothing can go
 * stale on one node while another has moved on.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_framework_insights extends external_api {
    /** @var string Usage counted exactly: the viewer may list every use core's pages list. */
    public const string USAGE_EXACT = 'exact';

    /** @var string Usage reduced to 0 or 1 per kind, for a manager of the framework's competencies. */
    public const string USAGE_PRESENCE = 'presence';

    /** @var string No usage at all, for a reader who manages nothing. */
    public const string USAGE_NONE = 'none';

    /**
     * @var array The capabilities, each a list read as "any of", that core's own lists ask before
     * they show a use; held all at the site, the viewer may count every use exactly.
     *
     * Courses: api::list_courses_using_competency() (course competency view or manage, and
     * api::validate_course() for a hidden course). Activities: api::list_course_modules_using_competency()
     * keeps a module only when cm_info::$uservisible (hidden activities, availability). Templates:
     * api::list_templates_using_competency() shows hidden ones only to templatemanage. Plans:
     * plan::can_read_user() and plan::can_read_user_draft(). The first group is granted to every
     * authenticated user by default, so it decides only on a site that took it from that role; it
     * stays listed because core's course list asks it.
     */
    public const array EXACT_CAPABILITIES = [
        ['moodle/competency:coursecompetencyview', 'moodle/competency:coursecompetencymanage'],
        ['moodle/course:viewhiddencourses'],
        ['moodle/course:viewhiddenactivities'],
        ['moodle/course:ignoreavailabilityrestrictions'],
        ['moodle/competency:templatemanage'],
        ['moodle/competency:planview', 'moodle/competency:planmanage'],
        ['moodle/competency:planviewdraft', 'moodle/competency:planmanagedraft'],
    ];

    /**
     * Define the parameters for the get_framework_insights external function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'frameworkid' => new external_value(PARAM_INT, 'Competency framework id'),
        ]);
    }

    /**
     * Compute the insights of one framework for the current user.
     *
     * Access: logged in, not the guest, competencies enabled, the framework's category
     * discoverable (local_unlistedcourses, as the hub's framework lists decide it; a withheld
     * framework gets the answer of a missing one), and competencyview or competencymanage in the
     * framework's own context, as browse_structure asks.
     *
     * Usage: `exact` (counts) for a viewer holding every one of EXACT_CAPABILITIES at the site,
     * when no role refuses any of them in any context (a prevent or prohibit anywhere demotes the
     * viewer, the site administrator excepted), so no count includes a course, activity, template or
     * plan core would not list to them;
     * otherwise `presence` (0 or 1 per kind) for a viewer holding competencymanage in the
     * framework's context, who may already learn exactly that from competency_delete_check; otherwise
     * `none`. The `unused` check is computed only from exact counts.
     *
     * @param int $frameworkid The framework id.
     * @return array Keys: frameworkid, exact, usagelevel, usage, rules, relations, pairs, checks, summary.
     * @throws \dml_missing_record_exception When the framework does not exist or its category is withheld.
     * @throws \require_login_exception When nobody is logged in.
     * @throws \moodle_exception noguest for the guest; competenciesarenotenabled when competencies are off.
     * @throws \required_capability_exception When the viewer may not read the framework's competencies.
     */
    public static function execute(int $frameworkid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['frameworkid' => $frameworkid]);
        $framework = competency_framework::get_record(['id' => $params['frameworkid']]);
        if (!$framework) {
            throw self::missing_framework();
        }
        $context = $framework->get_context();
        self::validate_context($context);
        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }
        api::require_enabled();
        // The hub's lists leave out a framework in a category local_unlistedcourses withholds from
        // the viewer; asked by id, the framework is refused the way a missing one is.
        if (unlisted_categories::filter_by_context([$framework]) === []) {
            throw self::missing_framework();
        }
        if (!competency_framework::can_read_context($context)) {
            throw new \required_capability_exception($context, 'moodle/competency:competencyview', 'nopermissions', '');
        }

        $fwid = (int) $framework->get('id');
        $records = $DB->get_records(
            competency::TABLE,
            ['competencyframeworkid' => $fwid],
            'id ASC',
            'id, parentid, path, idnumber, description, ruletype, ruleoutcome, ruleconfig'
        );
        $competencies = [];
        $children = [];
        $maxdepth = 0;
        foreach ($records as $record) {
            $id = (int) $record->id;
            $competencies[$id] = $record;
            $children[(int) $record->parentid][] = $id;
            // Core's competency::get_framework_depth(): the slashes in the path, less one ('/0/' is 1).
            $maxdepth = max($maxdepth, substr_count((string) $record->path, '/') - 1);
        }

        $level = self::usage_level($context);
        $usage = $level === self::USAGE_NONE ? [] : self::usage_counts($fwid);
        [$relations, $pairs] = self::relation_counts($fwid);
        $colours = self::colours($fwid);

        $rules = [];
        $checks = [];
        foreach ($competencies as $id => $record) {
            $childids = $children[$id] ?? [];
            $rule = self::rule($record, $childids, $competencies);
            if ($rule !== null) {
                $rules[] = $rule;
                if ($childids === []) {
                    $checks[] = self::check('rule_without_children', $id);
                } else {
                    if (count($childids) === 1) {
                        $checks[] = self::check('rule_single_child', $id);
                    }
                    if ($rule['type'] === 'points' && !$rule['canfire']) {
                        $checks[] = self::check('rule_cannot_fire', $id, [
                            'required' => $rule['required'],
                            'available' => $rule['available'],
                            'missing' => $rule['missing'],
                        ]);
                    }
                }
            }
            if (self::is_blank_description((string) ($record->description ?? ''))) {
                $checks[] = self::check('description_empty', $id);
            }
            if (trim((string) ($record->idnumber ?? '')) === '') {
                $checks[] = self::check('idnumber_empty', $id);
            }
            if ($level === self::USAGE_EXACT && !isset($usage[$id])) {
                $checks[] = self::check('unused', $id);
            }
            $bg = $colours[$id][constants::CFIELD_CUSTOMBGCOLOR] ?? '';
            $text = $colours[$id][constants::CFIELD_CUSTOMTEXTCOLOR] ?? '';
            if ($bg !== '' && $text !== '') {
                $ratio = contrast_ratio::of($bg, $text);
                if ($ratio !== null && $ratio < contrast_ratio::MINIMUM_TEXT) {
                    $checks[] = self::check('colour_contrast', $id, [
                        'ratio' => contrast_ratio::format($ratio),
                        'bgcolor' => $bg,
                        'textcolor' => $text,
                    ]);
                }
            }
        }

        $shown = static fn(int $count): int => $level === self::USAGE_EXACT ? $count : min($count, 1);
        $usagerows = [];
        foreach ($usage as $id => $counts) {
            $usagerows[] = [
                'competencyid' => $id,
                'courses' => $shown($counts['courses']),
                'modules' => $shown($counts['modules']),
                'templates' => $shown($counts['templates']),
                'plans' => $shown($counts['plans']),
            ];
        }
        $relationrows = [];
        foreach ($relations as $id => $count) {
            $relationrows[] = ['competencyid' => $id, 'count' => $count];
        }

        return [
            'frameworkid' => $fwid,
            'exact' => $level === self::USAGE_EXACT,
            'usagelevel' => $level,
            'usage' => $usagerows,
            'rules' => array_map(static function (array $rule): array {
                unset($rule['missing']);
                return $rule;
            }, $rules),
            'relations' => $relationrows,
            'pairs' => $pairs,
            'checks' => $checks,
            'summary' => [
                'competencies' => count($competencies),
                'maxdepth' => $maxdepth,
                'withrule' => count($rules),
                'relations' => count($pairs),
                'findings' => count($checks),
            ],
        ];
    }

    /**
     * The one refusal for a framework that does not exist and for one the viewer may not discover.
     *
     * @return \dml_missing_record_exception
     */
    private static function missing_framework(): \dml_missing_record_exception {
        return new \dml_missing_record_exception(competency_framework::TABLE);
    }

    /**
     * How much the current user may learn about usage; see execute().
     *
     * @param \context $context The framework's context.
     * @return string One of the USAGE_* constants.
     */
    private static function usage_level(\context $context): string {
        $system = \core\context\system::instance();
        $exact = true;
        foreach (self::EXACT_CAPABILITIES as $anyof) {
            if (!has_any_capability($anyof, $system)) {
                $exact = false;
                break;
            }
        }
        if ($exact && !is_siteadmin() && self::has_negative_exact_row()) {
            $exact = false;
        }
        if ($exact) {
            return self::USAGE_EXACT;
        }
        if (competency_framework::can_manage_context($context)) {
            return self::USAGE_PRESENCE;
        }
        return self::USAGE_NONE;
    }

    /**
     * Whether any role, in any context, refuses one of the EXACT_CAPABILITIES (a prevent or a prohibit).
     *
     * The site-level check in usage_level() cannot see a refusal in a lower context, yet core decides
     * per context what it lists: {@see \core_competency\api::list_courses_using_competency()} per
     * course, {@see \core_competency\plan::can_read_user()} per user, templates per category. A count
     * over the whole framework is only exact when no such refusal can exist, so one negative row
     * demotes every viewer but the site administrator. The row's own context does not bound its reach:
     * a role definition's prohibit, written at the site, applies in every context where that role is
     * assigned to the viewer, a course included, so no context is excluded. A stock site has no such
     * row. One statement, run only for a viewer who already holds every group at the site.
     *
     * @return bool True when a negative permission exists for any capability of EXACT_CAPABILITIES.
     */
    private static function has_negative_exact_row(): bool {
        global $DB;

        $names = array_merge(...self::EXACT_CAPABILITIES);
        [$insql, $params] = $DB->get_in_or_equal($names, SQL_PARAMS_NAMED, 'cap');
        return $DB->record_exists_select('role_capabilities', "permission < 0 AND capability $insql", $params);
    }

    /**
     * Count, per competency of the framework, the courses, activities, templates and plans using it.
     *
     * Four statements whatever the framework's size. Plans are those plan::get_by_user_and_competency()
     * finds, without its user: a competency of the plan itself, an archived rating of a completed
     * plan, or a competency of the template the plan is based on. An activity being deleted is not
     * counted (modinfo, which core's lists read, leaves it out).
     *
     * @param int $fwid The framework id.
     * @return array Competency id => ['courses' => int, 'modules' => int, 'templates' => int,
     *               'plans' => int], only for the competencies with a use.
     */
    private static function usage_counts(int $fwid): array {
        global $DB;

        $counts = [];
        $add = static function (array $menu, string $kind) use (&$counts): void {
            foreach ($menu as $id => $count) {
                $id = (int) $id;
                $counts[$id] ??= ['courses' => 0, 'modules' => 0, 'templates' => 0, 'plans' => 0];
                $counts[$id][$kind] = (int) $count;
            }
        };

        $add($DB->get_records_sql_menu(
            'SELECT cc.competencyid, COUNT(DISTINCT cc.courseid)
               FROM {' . course_competency::TABLE . '} cc
               JOIN {' . competency::TABLE . '} c ON c.id = cc.competencyid
              WHERE c.competencyframeworkid = :fw
           GROUP BY cc.competencyid',
            ['fw' => $fwid]
        ), 'courses');

        $add($DB->get_records_sql_menu(
            'SELECT mc.competencyid, COUNT(DISTINCT mc.cmid)
               FROM {' . course_module_competency::TABLE . '} mc
               JOIN {' . competency::TABLE . '} c ON c.id = mc.competencyid
               JOIN {course_modules} cm ON cm.id = mc.cmid
              WHERE c.competencyframeworkid = :fw
                AND cm.deletioninprogress = 0
           GROUP BY mc.competencyid',
            ['fw' => $fwid]
        ), 'modules');

        $add($DB->get_records_sql_menu(
            'SELECT tc.competencyid, COUNT(DISTINCT tc.templateid)
               FROM {' . template_competency::TABLE . '} tc
               JOIN {' . competency::TABLE . '} c ON c.id = tc.competencyid
              WHERE c.competencyframeworkid = :fw
           GROUP BY tc.competencyid',
            ['fw' => $fwid]
        ), 'templates');

        // Mirrors plan::get_by_user_and_competency(), user aside: three ways a plan holds a competency.
        $add($DB->get_records_sql_menu(
            'SELECT u.competencyid, COUNT(DISTINCT u.planid)
               FROM (
                     SELECT pc.competencyid, pc.planid
                       FROM {' . plan_competency::TABLE . '} pc
                       JOIN {' . competency::TABLE . '} c1 ON c1.id = pc.competencyid
                      WHERE c1.competencyframeworkid = :fw1
                      UNION
                     SELECT ucp.competencyid, ucp.planid
                       FROM {' . user_competency_plan::TABLE . '} ucp
                       JOIN {' . competency::TABLE . '} c2 ON c2.id = ucp.competencyid
                      WHERE c2.competencyframeworkid = :fw2
                      UNION
                     SELECT tc.competencyid, p.id AS planid
                       FROM {' . plan::TABLE . '} p
                       JOIN {' . template_competency::TABLE . '} tc ON tc.templateid = p.templateid
                       JOIN {' . competency::TABLE . '} c3 ON c3.id = tc.competencyid
                      WHERE c3.competencyframeworkid = :fw3
                    ) u
           GROUP BY u.competencyid',
            ['fw1' => $fwid, 'fw2' => $fwid, 'fw3' => $fwid]
        ), 'plans');

        ksort($counts);
        return $counts;
    }

    /**
     * List the framework's relation pairs and count each competency's relations from them.
     *
     * Core stores one row per pair (related_competency::get_relation() orders the two ids), so a
     * row counts once for each of its two ends. Only pairs inside the framework are read, the only
     * ones api::add_related_competency() creates: a row reaching another framework, which only a
     * direct write leaves, says nothing about a competency the viewer may not read. One statement.
     *
     * @param int $fwid The framework id.
     * @return array [competency id => count (only those with a relation), list of ['a' => int, 'b' => int]
     *               with a < b, sorted].
     */
    private static function relation_counts(int $fwid): array {
        global $DB;

        $rows = $DB->get_records_sql(
            'SELECT rc.id, rc.competencyid, rc.relatedcompetencyid
               FROM {' . related_competency::TABLE . '} rc
               JOIN {' . competency::TABLE . '} c1 ON c1.id = rc.competencyid
               JOIN {' . competency::TABLE . '} c2 ON c2.id = rc.relatedcompetencyid
              WHERE c1.competencyframeworkid = :fw1
                AND c2.competencyframeworkid = :fw2',
            ['fw1' => $fwid, 'fw2' => $fwid]
        );
        // Each pair once, lower id first as related_competency::get_relation() stores it; a pair a
        // direct write left twice, once in each order, is still one pair.
        $pairs = [];
        foreach ($rows as $row) {
            $a = min((int) $row->competencyid, (int) $row->relatedcompetencyid);
            $b = max((int) $row->competencyid, (int) $row->relatedcompetencyid);
            $pairs[$a . '-' . $b] = ['a' => $a, 'b' => $b];
        }
        $pairs = array_values($pairs);
        usort($pairs, static fn(array $x, array $y): int => [$x['a'], $x['b']] <=> [$y['a'], $y['b']]);
        $counts = [];
        foreach ($pairs as $pair) {
            foreach ([$pair['a'], $pair['b']] as $end) {
                $counts[$end] = ($counts[$end] ?? 0) + 1;
            }
        }
        ksort($counts);
        return [$counts, $pairs];
    }

    /**
     * Read the custom background and text colours of every competency of the framework.
     *
     * One statement over the rows helper::structure_customfield_data() and competency_metadata_cache
     * read per competency, joined on the framework instead of an id list, each value normalised by
     * helper::normalise_hex_color() as both of them do. The metadata cache is not used: on a miss it
     * reads every competency's card image apart, which would make the cost grow with the framework.
     *
     * @param int $fwid The framework id.
     * @return array Competency id => [shortname => '#hex'], only the valid colours that are set.
     */
    private static function colours(int $fwid): array {
        global $DB;

        [$insql, $inparams] = $DB->get_in_or_equal(
            [constants::CFIELD_CUSTOMBGCOLOR, constants::CFIELD_CUSTOMTEXTCOLOR],
            SQL_PARAMS_NAMED,
            'sn'
        );
        $rows = $DB->get_records_sql(
            "SELECT d.id, d.instanceid, f.shortname, d.value
               FROM {customfield_data} d
               JOIN {customfield_field} f ON f.id = d.fieldid
               JOIN {customfield_category} cat ON cat.id = f.categoryid
               JOIN {" . competency::TABLE . "} c ON c.id = d.instanceid
              WHERE c.competencyframeworkid = :fw
                AND cat.component = :component
                AND cat.area = :area
                AND f.shortname $insql",
            $inparams + ['fw' => $fwid, 'component' => 'local_dimensions', 'area' => helper::AREA_COMPETENCY]
        );
        $colours = [];
        foreach ($rows as $row) {
            $hex = helper::normalise_hex_color((string) ($row->value ?? ''));
            if ($hex !== '') {
                $colours[(int) $row->instanceid][(string) $row->shortname] = $hex;
            }
        }
        return $colours;
    }

    /**
     * Describe a competency's rule, or null when it has none.
     *
     * `canfire` is decided for core's two rules: "all" can fire when the competency has children
     * (competency_rule_all::matches() refuses a leaf); "points" by points_rule(). Another rule class
     * cannot be mirrored and is reported as able to fire.
     *
     * @param \stdClass $record The competency row.
     * @param array $childids Ids of its children.
     * @param array $competencies The framework's competencies, keyed by id.
     * @return array|null The rule row, plus `missing` (required entries gone) for the caller.
     */
    private static function rule(\stdClass $record, array $childids, array $competencies): ?array {
        $class = ltrim((string) ($record->ruletype ?? ''), '\\');
        if ($class === '') {
            return null;
        }
        $rule = [
            'competencyid' => (int) $record->id,
            'type' => match ($class) {
                competency_rule_all::class => 'all',
                competency_rule_points::class => 'points',
                default => substr((string) strrchr('\\' . $class, '\\'), 1),
            },
            'label' => helper::get_competency_rule_label($class),
            'outcome' => match ((int) $record->ruleoutcome) {
                competency::OUTCOME_EVIDENCE => 'evidence',
                competency::OUTCOME_COMPLETE => 'complete',
                competency::OUTCOME_RECOMMEND => 'recommend',
                default => 'none',
            },
            'required' => 0,
            'available' => 0,
            'requiredchildren' => 0,
            'children' => [],
            'canfire' => $class !== competency_rule_all::class || $childids !== [],
            'missing' => 0,
        ];
        if ($class === competency_rule_points::class) {
            $rule = array_merge($rule, self::points_rule((string) ($record->ruleconfig ?? ''), $competencies));
        }
        return $rule;
    }

    /**
     * Read a points rule's configuration and whether a learner could ever meet it.
     *
     * Mirrors competency_rule_points::matches() for the best learner, proficient in every listed
     * competency: it fires when every entry marked required is met and the points of the entries
     * met reach `base.points`. An entry whose competency no longer exists can never be met (core
     * refuses to delete a competency with ratings, so no proficient rating outlives it): its points
     * leave `available`, and a required one makes the rule unable to fire. A configuration without
     * `base.points` or `competencies`, which competency_rule_points::validate_config() refuses,
     * cannot fire.
     *
     * @param string $json The stored configuration.
     * @param array $competencies The framework's competencies, keyed by id.
     * @return array Keys: required, available, requiredchildren, children, canfire, missing.
     */
    private static function points_rule(string $json, array $competencies): array {
        $config = $json !== '' && json_validate($json) ? json_decode($json, true) : null;
        $wellformed = is_array($config)
            && is_array($config['base'] ?? null)
            && isset($config['base']['points'])
            && is_array($config['competencies'] ?? null);

        $entries = [];
        $available = 0;
        $requiredchildren = 0;
        $missing = 0;
        foreach ($wellformed ? $config['competencies'] : [] as $entry) {
            if (!is_array($entry) || !isset($entry['id'])) {
                continue;
            }
            $id = (int) $entry['id'];
            $points = (int) ($entry['points'] ?? 0);
            $required = !empty($entry['required']);
            $exists = isset($competencies[$id]);
            $entries[] = ['id' => $id, 'points' => $points, 'required' => $required];
            if ($exists) {
                $available += $points;
            }
            if ($required) {
                $requiredchildren++;
                if (!$exists) {
                    $missing++;
                }
            }
        }
        $required = $wellformed ? (int) $config['base']['points'] : 0;

        return [
            'required' => $required,
            'available' => $available,
            'requiredchildren' => $requiredchildren,
            'children' => $entries,
            'canfire' => $wellformed && $missing === 0 && $available >= $required,
            'missing' => $missing,
        ];
    }

    /**
     * Whether a description says nothing once its tags, entities and white space are gone.
     *
     * An embedded image, video, audio or frame is content even without text.
     *
     * @param string $description The stored description.
     * @return bool
     */
    private static function is_blank_description(string $description): bool {
        if (preg_match('/<(img|video|audio|iframe|object|embed)\b/i', $description)) {
            return false;
        }
        $text = html_entity_decode(strip_tags($description), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return preg_replace('/[\s\x{00A0}\x{200B}]+/u', '', $text) === '';
    }

    /**
     * One finding.
     *
     * @param string $code The check code; its strings are hubv2_check_<code> and hubv2_check_<code>_desc.
     * @param int $competencyid The competency, 0 for the framework.
     * @param array $params Placeholder name => value for the _desc string.
     * @return array
     */
    private static function check(string $code, int $competencyid, array $params = []): array {
        $list = [];
        foreach ($params as $name => $value) {
            $list[] = ['name' => (string) $name, 'value' => (string) $value];
        }
        return ['code' => $code, 'competencyid' => $competencyid, 'params' => $list];
    }

    /**
     * Define the return structure for the get_framework_insights external function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'frameworkid' => new external_value(PARAM_INT, 'Competency framework id'),
            'exact' => new external_value(
                PARAM_BOOL,
                'Whether the usage counts are exact: only for a viewer holding at the site every capability core\'s '
                . 'usage lists ask; otherwise each count is presence (0 or 1) or usage is empty'
            ),
            'usagelevel' => new external_value(PARAM_ALPHA, 'How much usage the viewer is told: exact, presence or none'),
            'usage' => new external_multiple_structure(new external_single_structure([
                'competencyid' => new external_value(PARAM_INT, 'Competency id'),
                'courses' => new external_value(PARAM_INT, 'Courses linked to it (0 or 1 unless exact)'),
                'modules' => new external_value(PARAM_INT, 'Activities linked to it (0 or 1 unless exact)'),
                'templates' => new external_value(PARAM_INT, 'Learning plan templates holding it (0 or 1 unless exact)'),
                'plans' => new external_value(PARAM_INT, 'Learning plans holding it (0 or 1 unless exact)'),
            ]), 'Usage of the competencies with any use; empty for usagelevel none'),
            'rules' => new external_multiple_structure(new external_single_structure([
                'competencyid' => new external_value(PARAM_INT, 'Competency id'),
                'type' => new external_value(PARAM_ALPHANUMEXT, 'all, points, or the rule class\'s short name'),
                'label' => new external_value(PARAM_TEXT, 'The rule\'s localised label, plain text'),
                'outcome' => new external_value(PARAM_ALPHA, 'none, evidence, complete or recommend'),
                'required' => new external_value(PARAM_INT, 'Points the rule asks for (points rules; 0 otherwise)'),
                'available' => new external_value(PARAM_INT, 'Points the existing listed competencies add up to'),
                'requiredchildren' => new external_value(PARAM_INT, 'How many listed competencies are marked required'),
                'children' => new external_multiple_structure(new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Competency id'),
                    'points' => new external_value(PARAM_INT, 'Points it gives'),
                    'required' => new external_value(PARAM_BOOL, 'Whether it is required'),
                ]), 'The points rule\'s configured competencies; empty for other rules'),
                'canfire' => new external_value(PARAM_BOOL, 'Whether a learner could ever meet the rule'),
            ])),
            'relations' => new external_multiple_structure(new external_single_structure([
                'competencyid' => new external_value(PARAM_INT, 'Competency id'),
                'count' => new external_value(PARAM_INT, 'Number of related competencies'),
            ])),
            'pairs' => new external_multiple_structure(new external_single_structure([
                'a' => new external_value(PARAM_INT, 'The lower competency id of the pair'),
                'b' => new external_value(PARAM_INT, 'The higher competency id of the pair'),
            ]), 'Each related pair of the framework once, both ends in the framework, sorted'),
            'checks' => new external_multiple_structure(new external_single_structure([
                'code' => new external_value(PARAM_ALPHANUMEXT, 'Finding code; strings hubv2_check_<code> and _desc'),
                'competencyid' => new external_value(PARAM_INT, 'Competency id, 0 for the framework'),
                'params' => new external_multiple_structure(new external_single_structure([
                    'name' => new external_value(PARAM_ALPHANUMEXT, 'Placeholder name in the _desc string'),
                    'value' => new external_value(PARAM_TEXT, 'Placeholder value, plain text'),
                ])),
            ])),
            'summary' => new external_single_structure([
                'competencies' => new external_value(PARAM_INT, 'Number of competencies'),
                'maxdepth' => new external_value(PARAM_INT, 'Number of levels, as competency::get_framework_depth()'),
                'withrule' => new external_value(PARAM_INT, 'Competencies with a rule'),
                'relations' => new external_value(PARAM_INT, 'Relation pairs in the framework, the length of pairs'),
                'findings' => new external_value(PARAM_INT, 'Number of checks'),
            ]),
        ]);
    }
}
