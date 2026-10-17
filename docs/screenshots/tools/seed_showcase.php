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
 * Seed the showcase data behind the README screenshots (development sites only, CLI).
 *
 * Reads showcase-data.json beside this script and builds, through core's competency, cohort,
 * course and enrolment APIs and the plugin's own custom-field handlers: competency frameworks at
 * the system and in course categories, two-level competency trees with scales, rules and related
 * competencies, learning plan templates with every local_dimensions field filled in, courses and
 * activities linked to competencies, cohorts synced to templates, cohort roles that let mentors
 * review the plans of their cohort, plans in every status, ratings, evidence and favourites.
 *
 * Idempotent and additive: every record it creates is found again by an idnumber starting with
 * "SC-" (frameworks, competencies, the template identifier field) or "showcase" (courses, course
 * modules, cohorts, users), by a role shortname, or by a plan's owner and name. A second run
 * creates nothing new and never changes a record it did not create, except that it appends
 * missing options ("Digital", "Safety") to the plugin's tag selects.
 *
 * The pictures come from imagery/render.mjs (run it first; see README.md). A missing picture is
 * reported and skipped, never fatal.
 *
 * Usage, inside the stack's webserver container:
 *   php public/local/dimensions/docs/screenshots/tools/seed_showcase.php
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/cohort/lib.php');

use core_competency\api;
use core_competency\competency;
use core_competency\course_module_competency;
use core_competency\evidence;
use core_competency\plan;
use core_competency\template;
use core_competency\user_competency;
use local_dimensions\constants;
use local_dimensions\helper;
use local_dimensions\picture_manager;

[$options, $unrecognised] = cli_get_params(['help' => false], ['h' => 'help']);
if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'admin', implode(PHP_EOL . '  ', $unrecognised)));
}
if ($options['help']) {
    echo "Seed the local_dimensions / block_dimensions showcase data from showcase-data.json.\n";
    exit(0);
}

\core\session\manager::set_user(get_admin());
api::require_enabled();

$seeder = new local_dimensions_showcase_seeder(__DIR__);
$seeder->run();

/**
 * The seeder: one method per step, in dependency order.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class local_dimensions_showcase_seeder {
    /** @var array The decoded showcase-data.json. */
    private array $data;

    /** @var string Directory of the rendered pictures. */
    private string $imagedir;

    /** @var \testing_data_generator Core's generator, used for activities and enrolments. */
    private \testing_data_generator $generator;

    /** @var array username => user id. */
    private array $users = [];

    /** @var array category idnumber => context (or 'system' => system context). */
    private array $contexts = [];

    /** @var array scale key => scale id. */
    private array $scales = [];

    /** @var array competency idnumber => competency id. */
    private array $competencies = [];

    /** @var array competency idnumber => [framework, light flag, is domain]. */
    private array $competencymeta = [];

    /** @var array template idnumber => template id. */
    private array $templates = [];

    /** @var array course idnumber => course id. */
    private array $courses = [];

    /** @var array cohort idnumber => cohort id. */
    private array $cohorts = [];

    /** @var array role shortname => role id. */
    private array $roles = [];

    /** @var array Counters printed at the end. */
    private array $count = ['images' => 0, 'imagesmissing' => 0, 'ratings' => 0, 'completions' => 0];

    /** @var string[] Notes that reviewers leave on a proficient rating. */
    private const NOTES_PROFICIENT = [
        'Observed in a lesson this month: confident and consistent. Well done.',
        'Strong evidence from the portfolio and the follow-up conversation.',
        'Seen across three observations; ready to share this practice with the team.',
        'The reflection shows real understanding of why this works.',
        'Colleagues mention this as a strength. Agreed in our review meeting.',
        'Clear impact on learners, visible in their books and their answers.',
    ];

    /** @var string[] Notes that reviewers leave on a rating below proficient. */
    private const NOTES_DEVELOPING = [
        'A good start. Next step: try it with the whole class, not only one group.',
        'Seen once; let us look for it again in the next observation.',
        'Promising. Book a peer observation to see how a colleague does it.',
        'The plan is right; keep a short log of what happens when you try it.',
    ];

    /**
     * Constructor.
     *
     * @param string $dir Directory holding showcase-data.json and imagery/.
     */
    public function __construct(string $dir) {
        $json = file_get_contents($dir . '/showcase-data.json');
        $this->data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->imagedir = $dir . '/imagery/out';
        $this->generator = new \testing_data_generator();
    }

    /**
     * Run every step and print a summary.
     *
     * @return void
     */
    public function run(): void {
        $this->check_contrast();
        $this->seed_users();
        $this->seed_categories();
        $this->seed_fields();
        $this->seed_scales();
        $this->seed_frameworks();
        $this->seed_related();
        $this->seed_courses();
        $this->seed_course_links();
        $this->seed_templates();
        $this->seed_roles();
        $this->seed_cohorts();
        $this->seed_individual_plans();
        $this->seed_enrolments();
        $this->seed_progress();
        $this->seed_personal_plans();
        $this->seed_student_extras();
        purge_all_caches();
        $this->report();
    }

    /**
     * Refuse a palette whose custom colour pairs fail WCAG AA (4.5:1) for normal text.
     *
     * @return void
     */
    private function check_contrast(): void {
        foreach ($this->data['palettes'] as $key => $p) {
            foreach ([['bg', 'text'], ['bglight', 'textlight']] as [$b, $t]) {
                $ratio = self::contrast($p[$b], $p[$t]);
                if ($ratio < 4.5) {
                    cli_error("Palette {$key}: {$p[$b]} on {$p[$t]} is {$ratio}:1, below WCAG AA.");
                }
            }
        }
        mtrace('Palettes: every colour pair passes WCAG AA.');
    }

    /**
     * WCAG contrast ratio of two #rrggbb colours.
     *
     * @param string $a First colour.
     * @param string $b Second colour.
     * @return float
     */
    private static function contrast(string $a, string $b): float {
        $lum = static function (string $hex): float {
            $c = array_map(static fn($i) => hexdec(substr($hex, $i, 2)) / 255, [1, 3, 5]);
            $c = array_map(static fn($v) => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, $c);
            return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
        };
        [$hi, $lo] = [max($lum($a), $lum($b)), min($lum($a), $lum($b))];
        return round(($hi + 0.05) / ($lo + 0.05), 2);
    }

    /**
     * The demo users this script needs: the existing demo accounts, plus its own learners and staff.
     *
     * New accounts get a random password nobody records; screenshots sign in through session.php.
     *
     * @return void
     */
    private function seed_users(): void {
        global $DB, $CFG;
        $new = array_merge($this->data['users']['learners'], $this->data['users']['staff']);
        foreach ($new as [$username, $first, $last, $department]) {
            $id = $DB->get_field('user', 'id', ['username' => $username, 'deleted' => 0]);
            if (!$id) {
                $id = \core\user::create_user((object) [
                    'username' => $username,
                    'auth' => 'manual',
                    'password' => random_string(20) . 'Aa1!',
                    'firstname' => $first,
                    'lastname' => $last,
                    'email' => $username . '@example.com',
                    'department' => $department,
                    'institution' => 'Educator Academy',
                    'city' => 'Bristol',
                    'country' => 'GB',
                    'confirmed' => 1,
                    'mnethostid' => $CFG->mnet_localhost_id,
                ], true, false);
            }
            $this->users[$username] = (int) $id;
        }
        // Existing demo accounts referenced by the data (created by block_compass' demo seeder).
        $wanted = ['demo.student', 'demo.teacher', 'dimdemo.facilitator'];
        foreach ($this->data['cohorts'] as $c) {
            $wanted = array_merge($wanted, $c['members'], array_column($c['roles'], 0));
        }
        foreach (array_unique($wanted) as $username) {
            if (isset($this->users[$username])) {
                continue;
            }
            $id = $DB->get_field('user', 'id', ['username' => $username, 'deleted' => 0]);
            if (!$id) {
                cli_error("User {$username} is missing: run block_compass' seed_demo.php on this site first.");
            }
            $this->users[$username] = (int) $id;
        }
        mtrace('Users: ' . count($this->users));
    }

    /**
     * Find, or create, the course categories the data names.
     *
     * @return void
     */
    private function seed_categories(): void {
        global $DB;
        $this->contexts['system'] = \context_system::instance();
        foreach ($this->data['categories'] as $c) {
            $id = $DB->get_field('course_categories', 'id', ['idnumber' => $c['idnumber']]);
            if (!$id) {
                $parent = $c['parent'] === '' ? 0 : $this->contexts[$c['parent']]->instanceid;
                $id = \core_course_category::create(['name' => $c['name'], 'idnumber' => $c['idnumber'], 'parent' => $parent])->id;
            }
            $this->contexts[$c['idnumber']] = \context_coursecat::instance((int) $id);
        }
    }

    /**
     * Provision the plugin's custom fields and append the select options the showcase uses.
     *
     * Options are only appended, so the stored indexes of existing data keep their meaning.
     *
     * @return void
     */
    private function seed_fields(): void {
        helper::ensure_custom_fields_on_setting_change();
        $wanted = [
            'lp' => [constants::CFIELD_TAG1 => [], constants::CFIELD_TAG2 => [], constants::CFIELD_TYPE => []],
            'competency' => [constants::CFIELD_TAG1 => [], constants::CFIELD_TAG2 => [], constants::CFIELD_TYPE => []],
        ];
        foreach ($this->data['templates'] as $t) {
            $wanted['lp'][constants::CFIELD_TAG1][] = $t['tag1'];
            $wanted['lp'][constants::CFIELD_TAG2][] = $t['tag2'];
            $wanted['lp'][constants::CFIELD_TYPE][] = $t['type'];
        }
        foreach ($this->data['frameworks'] as $f) {
            $wanted['competency'][constants::CFIELD_TAG1][] = $f['tag1'];
            $wanted['competency'][constants::CFIELD_TAG2][] = $f['tag2'];
            $wanted['competency'][constants::CFIELD_TYPE][] = $f['type'];
        }
        foreach ($wanted as $area => $fields) {
            foreach ($fields as $shortname => $labels) {
                $field = helper::find_field_by_shortname($shortname, $area);
                if (!$field) {
                    cli_error("Custom field {$shortname} ({$area}) is missing.");
                }
                $options = helper::split_select_options((string) $field->get_configdata_property('options'));
                $missing = array_values(array_diff(array_unique($labels), $options));
                if ($missing) {
                    $config = $field->get('configdata');
                    $config['options'] = implode("\n", array_merge($options, $missing));
                    $field->set('configdata', json_encode($config));
                    $field->save();
                    mtrace("Field {$shortname} ({$area}): appended " . implode(', ', $missing));
                }
            }
        }
        \local_dimensions\customfield\lp_handler::create()->reset_configuration_cache();
        \local_dimensions\customfield\competency_handler::create()->reset_configuration_cache();
    }

    /**
     * The 1-based index of a select option, as core's select field stores it.
     *
     * @param string $area 'lp' or 'competency'.
     * @param string $shortname Field shortname.
     * @param string $label Option label.
     * @return int
     */
    private function option_index(string $area, string $shortname, string $label): int {
        $field = helper::find_field_by_shortname($shortname, $area);
        $options = helper::split_select_options((string) $field->get_configdata_property('options'));
        $index = array_search($label, $options, true);
        if ($index === false) {
            throw new \coding_exception("Option {$label} missing from {$shortname} ({$area})");
        }
        return $index + 1;
    }

    /**
     * The index of a cascade option, from the order the plugin's constants declare.
     *
     * @param array $options One of the constants::*_options() maps.
     * @param string $key Option key.
     * @return int
     */
    private static function key_index(array $options, string $key): int {
        return array_search($key, array_keys($options), true) + 1;
    }

    /**
     * Find, or create, the three showcase scales.
     *
     * @return void
     */
    private function seed_scales(): void {
        global $DB;
        foreach ($this->data['scales'] as $key => $s) {
            $id = $DB->get_field('scale', 'id', ['name' => $s['name'], 'courseid' => 0]);
            if (!$id) {
                $scale = new \grade_scale([
                    'name' => $s['name'],
                    'scale' => implode(',', $s['items']),
                    'courseid' => 0,
                    'userid' => get_admin()->id,
                    'description' => $s['description'],
                    'descriptionformat' => FORMAT_HTML,
                    'standard' => 1,
                ], false);
                $id = $scale->insert();
            }
            $this->scales[$key] = (int) $id;
        }
    }

    /**
     * Core's scale configuration JSON for a showcase scale.
     *
     * @param string $key Scale key.
     * @return string
     */
    private function scale_configuration(string $key): string {
        $s = $this->data['scales'][$key];
        $config = [['scaleid' => (string) $this->scales[$key]]];
        foreach (array_keys($s['items']) as $i) {
            $item = $i + 1;
            $isdefault = $item === $s['default'];
            $isproficient = in_array($item, $s['proficient'], true);
            if ($isdefault || $isproficient) {
                $config[] = ['id' => $item, 'scaledefault' => (int) $isdefault, 'proficient' => (int) $isproficient];
            }
        }
        return json_encode($config);
    }

    /**
     * Frameworks, their competency trees, rules, custom fields and pictures.
     *
     * @return void
     */
    private function seed_frameworks(): void {
        global $DB;
        foreach ($this->data['frameworks'] as $f) {
            $context = $this->contexts[$f['category']];
            $fwid = $DB->get_field('competency_framework', 'id', ['idnumber' => $f['idnumber']]);
            if (!$fwid) {
                $fwid = api::create_framework((object) [
                    'shortname' => $f['shortname'],
                    'idnumber' => $f['idnumber'],
                    'description' => '<p>' . s($f['description']) . '</p>',
                    'descriptionformat' => FORMAT_HTML,
                    'scaleid' => $this->scales[$f['scale']],
                    'scaleconfiguration' => $this->scale_configuration($f['scale']),
                    'contextid' => $context->id,
                    'visible' => 1,
                    'taxonomies' => $f['taxonomies'],
                ])->get('id');
            }
            foreach ($f['domains'] as $di => $d) {
                $parentid = $this->competency($fwid, 0, $d['idnumber'], $d['name'], $d['description']);
                $this->competencymeta[$d['idnumber']] = [$f, false, true];
                $childids = [];
                foreach ($d['children'] as [$idnumber, $name, , $description]) {
                    $childids[] = $this->competency($fwid, $parentid, $idnumber, $name, $description);
                    $this->competencymeta[$idnumber] = [$f, $di % 2 === 1, false];
                }
                $this->rule($parentid, $d['rule'], $childids);
            }
        }
        foreach ($this->competencymeta as $idnumber => [$f, $light, $isdomain]) {
            $this->competency_fields($idnumber, $f, $light, $isdomain);
        }
        mtrace('Frameworks: ' . count($this->data['frameworks']) . ', competencies: ' . count($this->competencies));
    }

    /**
     * Find, or create, one competency.
     *
     * @param int $fwid Framework id.
     * @param int $parentid Parent competency id, 0 for a top-level one.
     * @param string $idnumber Idnumber.
     * @param string $name Short name.
     * @param string $description Description (plain text).
     * @return int
     */
    private function competency(int $fwid, int $parentid, string $idnumber, string $name, string $description): int {
        global $DB;
        $id = $DB->get_field('competency', 'id', ['competencyframeworkid' => $fwid, 'idnumber' => $idnumber]);
        if (!$id) {
            $id = api::create_competency((object) [
                'shortname' => $name,
                'idnumber' => $idnumber,
                'description' => '<p>' . s($description) . '</p>',
                'descriptionformat' => FORMAT_HTML,
                'competencyframeworkid' => $fwid,
                'parentid' => $parentid,
            ])->get('id');
        }
        $this->competencies[$idnumber] = (int) $id;
        return (int) $id;
    }

    /**
     * Set a domain's completion rule over its children, unless it already has one.
     *
     * "all" completes the domain when every child is proficient; "points" recommends it for
     * review when all but one child are, the first child being required.
     *
     * @param int $parentid Domain competency id.
     * @param string $rule 'all', 'points' or 'none'.
     * @param int[] $childids Child competency ids.
     * @return void
     */
    private function rule(int $parentid, string $rule, array $childids): void {
        $parent = new competency($parentid);
        if ($rule === 'none' || $parent->get('ruletype')) {
            return;
        }
        $record = (object) ['id' => $parentid];
        if ($rule === 'all') {
            $record->ruletype = 'core_competency\\competency_rule_all';
            $record->ruleoutcome = competency::OUTCOME_COMPLETE;
            $record->ruleconfig = null;
        } else {
            $record->ruletype = 'core_competency\\competency_rule_points';
            $record->ruleoutcome = competency::OUTCOME_RECOMMEND;
            $children = [];
            foreach ($childids as $i => $cid) {
                $children[] = ['id' => $cid, 'points' => 1, 'required' => (int) ($i === 0)];
            }
            $record->ruleconfig = json_encode([
                'base' => ['points' => max(1, count($childids) - 1)],
                'competencies' => $children,
            ]);
        }
        api::update_competency($record);
    }

    /**
     * Fill a competency's local_dimensions fields and pictures.
     *
     * @param string $idnumber Competency idnumber.
     * @param array $f Its framework's data.
     * @param bool $light Whether it uses the light colour pair.
     * @param bool $isdomain Whether it is a top-level competency.
     * @return void
     */
    private function competency_fields(string $idnumber, array $f, bool $light, bool $isdomain): void {
        $id = $this->competencies[$idnumber];
        $p = $this->data['palettes'][$f['palette']];
        $area = 'competency';
        $data = (object) [
            'id' => $id,
            'customfield_' . constants::CFIELD_CUSTOMBGCOLOR => $light ? $p['bglight'] : $p['bg'],
            'customfield_' . constants::CFIELD_CUSTOMTEXTCOLOR => $light ? $p['textlight'] : $p['text'],
            'customfield_' . constants::CFIELD_TAG1 => $this->option_index($area, constants::CFIELD_TAG1, $f['tag1']),
            'customfield_' . constants::CFIELD_TAG2 =>
                $this->option_index($area, constants::CFIELD_TAG2, $isdomain ? 'Core' : $f['tag2']),
            'customfield_' . constants::CFIELD_TYPE => $this->option_index($area, constants::CFIELD_TYPE, $f['type']),
        ];
        \local_dimensions\customfield\competency_handler::create()->instance_form_save($data);
        $this->picture('competency', $id, 'cardimage', "comp-{$idnumber}-card.jpg");
        $this->picture('competency', $id, 'bgimage', "comp-{$idnumber}-bg.jpg");
        \local_dimensions\competency_metadata_cache::invalidate_competency($id);
    }

    /**
     * Store one rendered picture in the plugin's built-in image area, through a draft area.
     *
     * @param string $area 'competency' or 'lp'.
     * @param int $instanceid Competency or template id.
     * @param string $type 'cardimage' or 'bgimage'.
     * @param string $filename File in imagery/out.
     * @return void
     */
    private function picture(string $area, int $instanceid, string $type, string $filename): void {
        global $USER;
        $path = $this->imagedir . '/' . $filename;
        if (!is_readable($path)) {
            $this->count['imagesmissing']++;
            return;
        }
        $this->count['images']++;
        $fs = get_file_storage();
        $syscontext = \context_system::instance();
        $filearea = picture_manager::get_filearea($area, $type);
        foreach ($fs->get_area_files($syscontext->id, picture_manager::COMPONENT, $filearea, $instanceid, '', false) as $file) {
            if ($file->get_contenthash() === sha1_file($path)) {
                return;
            }
        }
        $draftid = file_get_unused_draft_itemid();
        $fs->create_file_from_pathname([
            'contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftid,
            'filepath' => '/',
            'filename' => $filename,
        ], $path);
        $element = picture_manager::get_form_element_name($area, $type);
        picture_manager::save_from_form((object) [$element => $draftid], $area, $instanceid, $type);
    }

    /**
     * Related competencies (core requires both in the same framework).
     *
     * @return void
     */
    private function seed_related(): void {
        foreach ($this->data['related'] as [$a, $b]) {
            api::add_related_competency($this->competencies[$a], $this->competencies[$b]);
        }
    }

    /**
     * New courses with sections, activities, module competencies and a cover.
     *
     * @return void
     */
    private function seed_courses(): void {
        global $DB;
        foreach ($this->data['courses']['new'] as $c) {
            $course = $DB->get_record('course', ['idnumber' => $c['idnumber']]);
            if (!$course) {
                $course = create_course((object) [
                    'fullname' => $c['fullname'],
                    'shortname' => $c['shortname'],
                    'idnumber' => $c['idnumber'],
                    'category' => $this->contexts[$c['category']]->instanceid,
                    'summary' => '<p>' . s($c['summary']) . '</p>',
                    'summaryformat' => FORMAT_HTML,
                    'format' => 'topics',
                    'numsections' => count($c['sections']),
                    'enablecompletion' => 1,
                    'showcompletionconditions' => 1,
                ]);
            }
            $this->courses[$c['idnumber']] = (int) $course->id;
            $this->cover($course, "course-{$c['idnumber']}.jpg");
            $n = 0;
            foreach ($c['sections'] as $si => [$sectionname, $activities]) {
                $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $si + 1]);
                if ($section && $section->name !== $sectionname) {
                    course_update_section($course, $section, ['name' => $sectionname]);
                }
                foreach ($activities as [$modname, $name, $compidnumber, $outcome]) {
                    $n++;
                    $cmidnumber = $c['idnumber'] . '_' . $n;
                    $cmid = $DB->get_field('course_modules', 'id', ['course' => $course->id, 'idnumber' => $cmidnumber]);
                    if (!$cmid) {
                        $cmid = $this->activity($course, $si + 1, $modname, $name, $cmidnumber);
                    }
                    $this->link_course($course->id, $compidnumber);
                    $compid = $this->competencies[$compidnumber];
                    api::add_competency_to_course_module($cmid, $compid);
                    $cmc = course_module_competency::get_record(['cmid' => $cmid, 'competencyid' => $compid]);
                    $want = $outcome === 'complete' ? course_module_competency::OUTCOME_COMPLETE
                        : course_module_competency::OUTCOME_EVIDENCE;
                    if ((int) $cmc->get('ruleoutcome') !== $want) {
                        api::set_course_module_competency_ruleoutcome($cmc, $want);
                    }
                }
            }
        }
        mtrace('New courses: ' . count($this->courses));
    }

    /**
     * Create one activity with manual completion tracking.
     *
     * @param \stdClass $course Course.
     * @param int $section Section number.
     * @param string $modname page, url, assign or forum.
     * @param string $name Activity name.
     * @param string $idnumber Course module idnumber.
     * @return int Course module id.
     */
    private function activity(\stdClass $course, int $section, string $modname, string $name, string $idnumber): int {
        $record = [
            'course' => $course->id,
            'section' => $section,
            'name' => $name,
            'idnumber' => $idnumber,
            'intro' => '<p>' . s($name) . '.</p>',
            'introformat' => FORMAT_HTML,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ];
        if ($modname === 'page') {
            $record['content'] = '<p>Read this short guide, then try one idea in your next lesson.</p>';
        } else if ($modname === 'url') {
            $record['externalurl'] = 'https://docs.moodle.org/';
        } else if ($modname === 'assign') {
            $record['assignsubmission_onlinetext_enabled'] = 1;
            $record['duedate'] = 0;
        } else if ($modname === 'forum') {
            $record['type'] = 'general';
        }
        return (int) $this->generator->create_module($modname, $record)->cmid;
    }

    /**
     * Put a rendered cover in a course's overview files, replacing a different one.
     *
     * @param \stdClass $course Course.
     * @param string $filename File in imagery/out.
     * @return void
     */
    private function cover(\stdClass $course, string $filename): void {
        $path = $this->imagedir . '/' . $filename;
        if (!is_readable($path)) {
            $this->count['imagesmissing']++;
            return;
        }
        $this->count['images']++;
        $fs = get_file_storage();
        $context = \context_course::instance($course->id);
        $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, '', false);
        foreach ($files as $file) {
            if ($file->get_contenthash() === sha1_file($path)) {
                return;
            }
        }
        $fs->delete_area_files($context->id, 'course', 'overviewfiles', 0);
        $fs->create_file_from_pathname([
            'contextid' => $context->id,
            'component' => 'course',
            'filearea' => 'overviewfiles',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'cover.jpg',
        ], $path);
    }

    /**
     * Link a competency to a course, keeping core's default outcome (attach evidence on course completion).
     *
     * @param int $courseid Course id.
     * @param string $compidnumber Competency idnumber.
     * @return void
     */
    private function link_course(int $courseid, string $compidnumber): void {
        $compid = $this->competencies[$compidnumber];
        api::add_competency_to_course($courseid, $compid);
    }

    /**
     * Link competencies to the existing demo courses.
     *
     * @return void
     */
    private function seed_course_links(): void {
        global $DB;
        foreach ($this->data['courses']['links'] as $idnumber => $comps) {
            $courseid = $DB->get_field('course', 'id', ['idnumber' => $idnumber]);
            if (!$courseid) {
                mtrace("  course {$idnumber} not found, links skipped");
                continue;
            }
            $this->courses[$idnumber] = (int) $courseid;
            foreach ($comps as $comp) {
                $this->link_course((int) $courseid, $comp);
            }
        }
    }

    /**
     * Templates, their competencies, local_dimensions fields and pictures.
     *
     * @return void
     */
    private function seed_templates(): void {
        global $DB;
        $handler = \local_dimensions\customfield\lp_handler::create();
        $idfield = helper::find_field_by_shortname(constants::CFIELD_TEMPLATE_IDNUMBER, 'lp');
        foreach ($this->data['templates'] as $t) {
            $context = $this->contexts[$t['category']];
            // Found by the template identifier field first, so a renamed template is updated in place.
            $id = $DB->get_field_sql(
                'SELECT d.instanceid FROM {customfield_data} d WHERE d.fieldid = :fieldid AND d.charvalue = :idnumber',
                ['fieldid' => $idfield->get('id'), 'idnumber' => $t['idnumber']],
                IGNORE_MULTIPLE
            ) ?: $DB->get_field('competency_template', 'id', ['shortname' => $t['shortname'], 'contextid' => $context->id]);
            $oldname = $id ? $DB->get_field('competency_template', 'shortname', ['id' => $id]) : $t['shortname'];
            if ($oldname !== $t['shortname']) {
                api::update_template((object) ['id' => (int) $id, 'shortname' => $t['shortname']]);
                // Plans copy the template's name when created and core never renames them.
                $DB->set_field('competency_plan', 'name', $t['shortname'], ['templateid' => $id, 'name' => $oldname]);
            }
            if (!$id) {
                $id = api::create_template((object) [
                    'shortname' => $t['shortname'],
                    'description' => '<p>' . s($t['description']) . '</p>',
                    'descriptionformat' => FORMAT_HTML,
                    'contextid' => $context->id,
                    'visible' => 1,
                    'duedate' => time() + $t['dueindays'] * DAYSECS,
                ])->get('id');
            }
            $id = (int) $id;
            $this->templates[$t['idnumber']] = $id;
            foreach ($t['competencies'] as $comp) {
                api::add_competency_to_template($id, $this->competencies[$comp]);
            }
            $p = $this->data['palettes'][$t['palette']];
            $data = (object) [
                'id' => $id,
                'customfield_' . constants::CFIELD_TEMPLATE_IDNUMBER => $t['idnumber'],
                'customfield_' . constants::CFIELD_DISPLAYMODE => (int) $t['displaymode'],
                'customfield_' . constants::CFIELD_SUBLINE_SOURCE =>
                    self::key_index(constants::subline_source_options(), $t['subline']),
                'customfield_' . constants::CFIELD_CUSTOMBGCOLOR => $t['light'] ? $p['bglight'] : $p['bg'],
                'customfield_' . constants::CFIELD_CUSTOMTEXTCOLOR => $t['light'] ? $p['textlight'] : $p['text'],
                'customfield_' . constants::CFIELD_TAG1 => $this->option_index('lp', constants::CFIELD_TAG1, $t['tag1']),
                'customfield_' . constants::CFIELD_TAG2 => $this->option_index('lp', constants::CFIELD_TAG2, $t['tag2']),
                'customfield_' . constants::CFIELD_TYPE => $this->option_index('lp', constants::CFIELD_TYPE, $t['type']),
            ];
            if (!empty($t['showrelated'])) {
                $data->{'customfield_' . constants::CFIELD_SHOWRELATED} =
                    self::key_index(constants::showrelated_options(), $t['showrelated']);
                $data->{'customfield_' . constants::CFIELD_SHOWRELATEDLINK} =
                    self::key_index(constants::showrelatedlink_options(), $t['showrelated']);
            }
            if (!empty($t['lockedcardmode'])) {
                $data->{'customfield_' . constants::CFIELD_LOCKEDCARDMODE} =
                    self::key_index(constants::lockedcardmode_options(), $t['lockedcardmode']);
            }
            $handler->instance_form_save($data);
            $this->picture('lp', $id, 'cardimage', "tpl-{$t['idnumber']}-card.jpg");
            $this->picture('lp', $id, 'bgimage', "tpl-{$t['idnumber']}-bg.jpg");
            \local_dimensions\template_metadata_cache::invalidate_template($id);
        }
        mtrace('Templates: ' . count($this->templates));
    }

    /**
     * The reviewer roles, assignable in the user context, as tool_cohortroles needs.
     *
     * @return void
     */
    private function seed_roles(): void {
        global $DB;
        $syscontext = \context_system::instance();
        foreach ($this->data['roles'] as $r) {
            $id = $DB->get_field('role', 'id', ['shortname' => $r['shortname']]);
            if (!$id) {
                $id = create_role($r['name'], $r['shortname'], $r['description']);
                set_role_contextlevels($id, [CONTEXT_USER]);
            }
            foreach ($r['capabilities'] as $cap) {
                assign_capability($cap, CAP_ALLOW, $id, $syscontext->id, true);
            }
            $this->roles[$r['shortname']] = (int) $id;
        }
        $syscontext->mark_dirty();
    }

    /**
     * Cohorts, their members, their templates (plans come from cohort sync) and cohort roles.
     *
     * @return void
     */
    private function seed_cohorts(): void {
        global $DB;
        foreach ($this->data['cohorts'] as $c) {
            $id = $DB->get_field('cohort', 'id', ['idnumber' => $c['idnumber']]);
            if (!$id) {
                $id = cohort_add_cohort((object) [
                    'contextid' => $this->contexts[$c['category']]->id,
                    'name' => $c['name'],
                    'idnumber' => $c['idnumber'],
                    'description' => '<p>' . s($c['description']) . '</p>',
                    'descriptionformat' => FORMAT_HTML,
                    'visible' => 1,
                    'component' => '',
                ]);
            }
            $this->cohorts[$c['idnumber']] = (int) $id;
            foreach ($c['members'] as $username) {
                if (!$DB->record_exists('cohort_members', ['cohortid' => $id, 'userid' => $this->users[$username]])) {
                    cohort_add_member($id, $this->users[$username]);
                }
            }
            foreach ($c['templates'] as $tidnumber) {
                api::create_template_cohort($this->templates[$tidnumber], $id);
                api::create_plans_from_template_cohort($this->templates[$tidnumber], $id);
            }
            foreach ($c['roles'] as [$username, $role]) {
                \tool_cohortroles\api::create_cohort_role_assignment((object) [
                    'userid' => $this->users[$username],
                    'roleid' => $this->roles[$role],
                    'cohortid' => (int) $id,
                ]);
            }
        }
        \tool_cohortroles\api::sync_all_cohort_roles();
        mtrace('Cohorts: ' . count($this->cohorts));
    }

    /**
     * Plans assigned to individual learners, outside any cohort.
     *
     * @return void
     */
    private function seed_individual_plans(): void {
        foreach ($this->data['individualplans'] as $tidnumber => $usernames) {
            foreach ($usernames as $username) {
                // Returns false when the learner already holds a plan from this template.
                api::create_plan_from_template($this->templates[$tidnumber], $this->users[$username]);
            }
        }
    }

    /**
     * Enrol learners in the courses linked to their plans' competencies.
     *
     * demo.student gets the list the data names (so some of her cards stay locked); every other
     * cohort member is enrolled in the new courses linked to their templates.
     *
     * @return void
     */
    private function seed_enrolments(): void {
        global $DB;
        $student = $this->data['student']['username'];
        foreach ($this->data['student']['enrol'] as $idnumber) {
            if (isset($this->courses[$idnumber])) {
                $this->enrol($this->users[$student], $this->courses[$idnumber]);
            }
        }
        // Each learner's templates, from their cohorts and their individual plans.
        $templatesof = [];
        foreach ($this->data['cohorts'] as $c) {
            foreach ($c['members'] as $username) {
                $templatesof[$username] = array_merge($templatesof[$username] ?? [], $c['templates']);
            }
        }
        foreach ($this->data['individualplans'] as $tidnumber => $usernames) {
            foreach ($usernames as $username) {
                $templatesof[$username][] = $tidnumber;
            }
        }
        unset($templatesof[$student]);
        $compsof = array_column($this->data['templates'], 'competencies', 'idnumber');
        $newcourseids = [];
        foreach ($this->data['courses']['new'] as $c) {
            $newcourseids[] = $this->courses[$c['idnumber']];
        }
        foreach ($templatesof as $username => $tidnumbers) {
            $compids = [];
            foreach (array_unique($tidnumbers) as $tidnumber) {
                foreach ($compsof[$tidnumber] as $comp) {
                    $compids[] = $this->competencies[$comp];
                }
            }
            [$insql, $params] = $DB->get_in_or_equal($compids);
            $courseids = $DB->get_fieldset_select('competency_coursecomp', 'DISTINCT courseid', "competencyid {$insql}", $params);
            foreach ($courseids as $courseid) {
                if (in_array((int) $courseid, $newcourseids, true)) {
                    $this->enrol($this->users[$username], (int) $courseid);
                }
            }
        }
    }

    /**
     * Enrol a user as a student, once.
     *
     * @param int $userid User id.
     * @param int $courseid Course id.
     * @return void
     */
    private function enrol(int $userid, int $courseid): void {
        if (!is_enrolled(\context_course::instance($courseid), $userid)) {
            $this->generator->enrol_user($userid, $courseid, 'student');
        }
    }

    /**
     * Progress on every template-based showcase plan: activity completion, ratings, completion.
     *
     * For a plan at progress f, the first round(f * n) competencies are done (their activities
     * completed, then a reviewer's proficient rating where completion gave none) and the next one
     * is under way (its evidence-only activities completed and a rating below proficient).
     *
     * @return void
     */
    private function seed_progress(): void {
        $student = $this->users[$this->data['student']['username']];
        $reviewers = $this->reviewers_by_template();
        foreach ($this->templates as $tidnumber => $templateid) {
            foreach (plan::get_records(['templateid' => $templateid]) as $plan) {
                if ((int) $plan->get('status') === plan::STATUS_COMPLETE) {
                    continue;
                }
                $userid = (int) $plan->get('userid');
                if ($userid === $student) {
                    $want = $this->data['student']['plans'][$tidnumber] ?? ['progress' => 0.2, 'status' => 'active'];
                } else {
                    $want = $this->other_progress($userid, $tidnumber);
                }
                $compids = array_map(
                    static fn($c) => (int) $c->get('id'),
                    api::list_competencies_in_template($templateid)
                );
                $done = (int) round($want['progress'] * count($compids));
                $actionuser = $reviewers[$tidnumber][$userid % max(1, count($reviewers[$tidnumber] ?? []))] ?? get_admin()->id;
                foreach ($compids as $i => $compid) {
                    if ($i < $done) {
                        $this->complete_activities($userid, $compid, false);
                        $this->rate($userid, $compid, $plan, true, (int) $actionuser);
                    } else if ($i === $done) {
                        $this->complete_activities($userid, $compid, true);
                        $this->rate($userid, $compid, $plan, false, (int) $actionuser);
                    }
                }
                if ($want['status'] === 'complete') {
                    api::complete_plan($plan);
                }
            }
        }
        mtrace("Progress: {$this->count['completions']} activity completions, {$this->count['ratings']} ratings");
    }

    /**
     * The reviewers of each template's learners: the users holding a cohort role over its cohorts.
     *
     * @return array template idnumber => list of user ids
     */
    private function reviewers_by_template(): array {
        $map = [];
        foreach ($this->data['cohorts'] as $c) {
            foreach ($c['templates'] as $tidnumber) {
                foreach ($c['roles'] as [$username]) {
                    $map[$tidnumber][] = $this->users[$username];
                }
            }
        }
        return $map;
    }

    /**
     * A deterministic, varied progress for a learner other than demo.student.
     *
     * @param int $userid User id.
     * @param string $tidnumber Template idnumber.
     * @return array ['progress' => float, 'status' => 'active'|'complete']
     */
    private function other_progress(int $userid, string $tidnumber): array {
        $h = crc32($tidnumber . '/' . $userid);
        $steps = [0.0, 0.15, 0.35, 0.5, 0.65, 0.8, 1.0, 1.0];
        $progress = $steps[$h % count($steps)];
        $status = ($progress >= 1.0 && ($h >> 4) % 3 !== 0) ? 'complete' : 'active';
        return ['progress' => $progress, 'status' => $status];
    }

    /**
     * Mark complete the activities linked to a competency in the courses a user is enrolled in.
     *
     * Completing an activity whose competency outcome is "complete" makes core's observer rate
     * the competency with the scale's default (proficient) value; an "evidence" outcome only
     * adds evidence.
     *
     * @param int $userid User id.
     * @param int $compid Competency id.
     * @param bool $evidenceonly Complete only the activities whose outcome is evidence.
     * @return void
     */
    private function complete_activities(int $userid, int $compid, bool $evidenceonly): void {
        foreach (course_module_competency::get_records(['competencyid' => $compid]) as $cmc) {
            if ($evidenceonly && (int) $cmc->get('ruleoutcome') !== course_module_competency::OUTCOME_EVIDENCE) {
                continue;
            }
            $cm = get_coursemodule_from_id('', $cmc->get('cmid'), 0, false, IGNORE_MISSING);
            if (!$cm || !is_enrolled(\context_course::instance($cm->course), $userid)) {
                continue;
            }
            $course = get_course($cm->course);
            $cminfo = get_fast_modinfo($course, $userid)->get_cm($cm->id);
            $completion = new \completion_info($course);
            if ((int) $completion->get_data($cminfo, false, $userid)->completionstate !== COMPLETION_COMPLETE) {
                $completion->update_state($cminfo, COMPLETION_COMPLETE, $userid);
                $this->count['completions']++;
            }
        }
    }

    /**
     * A reviewer's rating in a plan, unless the competency already carries one.
     *
     * @param int $userid User id.
     * @param int $compid Competency id.
     * @param plan $plan The plan the rating is given in.
     * @param bool $proficient A proficient value, or the one just below.
     * @param int $actionuser The reviewer.
     * @return void
     */
    private function rate(int $userid, int $compid, plan $plan, bool $proficient, int $actionuser): void {
        $uc = user_competency::get_record(['userid' => $userid, 'competencyid' => $compid]);
        if ($uc && $uc->get('grade') !== null) {
            return;
        }
        $competency = new competency($compid);
        $scale = $competency->get_scale();
        $items = count($scale->load_items());
        $config = json_decode($competency->get_framework()->get('scaleconfiguration'), true);
        $proficientitems = array_column(array_filter(array_slice($config, 1), static fn($c) => !empty($c['proficient'])), 'id');
        $h = crc32($userid . '-' . $compid);
        if ($proficient) {
            $grade = (int) $proficientitems[$h % count($proficientitems)];
            $note = self::NOTES_PROFICIENT[$h % count(self::NOTES_PROFICIENT)];
        } else {
            $grade = max(1, (int) min($proficientitems) - 1);
            $note = self::NOTES_DEVELOPING[$h % count(self::NOTES_DEVELOPING)];
        }
        $grade = min($grade, $items);
        api::add_evidence(
            $userid,
            $compid,
            $plan->get_context(),
            evidence::ACTION_OVERRIDE,
            'evidence_manualoverrideinplan',
            'core_competency',
            $plan->get('name'),
            false,
            null,
            $grade,
            $actionuser,
            $note
        );
        $this->count['ratings']++;
    }

    /**
     * Personal plans (no template) in draft, waiting for review and in review.
     *
     * @return void
     */
    private function seed_personal_plans(): void {
        $student = $this->data['student']['username'];
        $plans = array_map(static fn($p) => $p + ['username' => $student], $this->data['student']['personal']);
        foreach (array_merge($plans, $this->data['otherplans']) as $p) {
            $userid = $this->users[$p['username']];
            $plan = plan::get_record(['userid' => $userid, 'name' => $p['name']]);
            if (!$plan) {
                $plan = api::create_plan((object) [
                    'name' => $p['name'],
                    'description' => '<p>' . s($p['description']) . '</p>',
                    'descriptionformat' => FORMAT_HTML,
                    'userid' => $userid,
                    'status' => plan::STATUS_DRAFT,
                    'duedate' => time() + 120 * DAYSECS,
                ]);
                foreach ($p['competencies'] as $comp) {
                    api::add_competency_to_plan($plan->get('id'), $this->competencies[$comp]);
                }
                if ($p['status'] !== 'draft') {
                    api::plan_request_review($plan);
                }
                if ($p['status'] === 'inreview') {
                    api::plan_start_review($plan);
                    $plan->read();
                    $plan->set('reviewerid', $this->users[$p['reviewer']]);
                    $plan->update();
                }
            }
        }
    }

    /**
     * demo.student's prior learning evidence, review requests and favourites.
     *
     * @return void
     */
    private function seed_student_extras(): void {
        global $DB;
        $s = $this->data['student'];
        $userid = $this->users[$s['username']];
        foreach ($s['priorlearning'] as $e) {
            $ue = \core_competency\user_evidence::get_record(['userid' => $userid, 'name' => $e['name']]);
            if (!$ue) {
                $ue = api::create_user_evidence((object) [
                    'userid' => $userid,
                    'name' => $e['name'],
                    'description' => '<p>' . s($e['description']) . '</p>',
                    'descriptionformat' => FORMAT_HTML,
                    'url' => '',
                ]);
                foreach ($e['competencies'] as $comp) {
                    api::create_user_evidence_competency($ue, $this->competencies[$comp]);
                }
            }
        }
        foreach ($s['reviewrequests'] as $comp) {
            $uc = user_competency::get_record(['userid' => $userid, 'competencyid' => $this->competencies[$comp]]);
            if (!$uc || (int) $uc->get('status') === user_competency::STATUS_IDLE) {
                api::user_competency_request_review($userid, $this->competencies[$comp]);
            }
        }
        // The block's favourites (core favourites, component block_dimensions).
        $usercontext = \context_user::instance($userid);
        $service = \core_favourites\service_factory::get_service_for_user_context($usercontext);
        $favourites = [];
        foreach ($s['favourites']['plans'] as $tidnumber) {
            $planid = (int) $DB->get_field(
                'competency_plan',
                'id',
                ['userid' => $userid, 'templateid' => $this->templates[$tidnumber]]
            );
            $favourites[] = ['plan', $planid];
        }
        foreach ($s['favourites']['competencies'] as $comp) {
            $favourites[] = ['competency', $this->competencies[$comp]];
        }
        foreach ($favourites as [$type, $itemid]) {
            if ($itemid && !$service->favourite_exists('block_dimensions', $type, $itemid, $usercontext)) {
                $service->create_favourite('block_dimensions', $type, $itemid, $usercontext);
            }
        }
        // The learner views' own favourites (a preference: plan id => competency ids), on the first favourite plan.
        $planid = (int) $DB->get_field('competency_plan', 'id', [
            'userid' => $userid,
            'templateid' => $this->templates[$s['favourites']['plans'][0]],
        ]);
        $stored = json_decode((string) get_user_preferences(constants::PREF_LEARNER_FAV, '', $userid), true) ?: [];
        $wanted = [];
        foreach ($s['favourites']['competencies'] as $comp) {
            $wanted[] = $this->competencies[$comp];
        }
        $stored[$planid] = array_values(array_unique(array_merge($stored[$planid] ?? [], $wanted)));
        set_user_preference(constants::PREF_LEARNER_FAV, json_encode($stored), $userid);
    }

    /**
     * Print what the showcase holds now.
     *
     * @return void
     */
    private function report(): void {
        global $DB;
        mtrace('');
        mtrace('Showcase summary');
        $rows = $DB->get_records_sql(
            "SELECT f.contextid, COUNT(1) AS n
               FROM {competency_framework} f
              WHERE f.idnumber LIKE 'SC-%'
           GROUP BY f.contextid"
        );
        foreach ($rows as $row) {
            $name = \context::instance_by_id($row->contextid)->get_context_name(false);
            mtrace(sprintf('  frameworks in %-40s %d', $name, $row->n));
        }
        $compcount = $DB->count_records_select('competency', "idnumber LIKE 'SC-%'");
        mtrace("  competencies: {$compcount}");
        mtrace('  templates: ' . count($this->templates));
        [$insql, $params] = $DB->get_in_or_equal(array_values($this->templates), SQL_PARAMS_NAMED);
        $names = array_merge(
            array_column($this->data['student']['personal'], 'name'),
            array_column($this->data['otherplans'], 'name')
        );
        [$namesql, $nameparams] = $DB->get_in_or_equal($names, SQL_PARAMS_NAMED, 'nm');
        $statuses = $DB->get_records_sql(
            "SELECT status, COUNT(1) AS n FROM {competency_plan}
              WHERE templateid {$insql} OR (templateid IS NULL AND name {$namesql})
           GROUP BY status",
            $params + $nameparams
        );
        $labels = [plan::STATUS_DRAFT => 'draft', plan::STATUS_ACTIVE => 'active', plan::STATUS_COMPLETE => 'complete',
            plan::STATUS_WAITING_FOR_REVIEW => 'waiting for review', plan::STATUS_IN_REVIEW => 'in review'];
        foreach ($statuses as $row) {
            mtrace(sprintf('  plans %-20s %d', $labels[$row->status], $row->n));
        }
        $student = $this->users[$this->data['student']['username']];
        $mine = $DB->get_records_sql_menu(
            'SELECT status, COUNT(1) FROM {competency_plan} WHERE userid = ? GROUP BY status',
            [$student]
        );
        $parts = [];
        foreach ($mine as $status => $n) {
            $parts[] = $labels[$status] . ' ' . $n;
        }
        mtrace('  demo.student plans (all, by status): ' . implode(', ', $parts));
        mtrace('  users: ' . count($this->users) . ', cohorts: ' . count($this->cohorts) . ', roles: ' . count($this->roles));
        mtrace('  courses: ' . count($this->data['courses']['new']) . ' new, ' . count($this->courses) . ' linked in all');
        mtrace("  images stored or confirmed: {$this->count['images']}, missing: {$this->count['imagesmissing']}");
        mtrace('Done.');
    }
}
