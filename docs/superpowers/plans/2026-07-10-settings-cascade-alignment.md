# Settings Cascade Alignment — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the global → plan → competency settings cascade consistent across both learner views: Panorama honours the `enrollmentfilter` cascade, `singlecourseredirect` gates on real course accessibility (not the filter label), related-competency links open in a new tab and skip the plan layer, and `showrelated`/`showrelatedlink` become per-plan overrides — plus conditional form visibility and guidance.

**Architecture:** All settings live in core `customfield_data` (no new tables). Two settings already cascade via `helper::resolve_*_for_view()` (competency → plan → global). This plan (a) routes the Panorama accordion's course-filter through that same cascade by giving the `get_competency_courses` WS a `planid`, (b) replaces the redirect's `enrollmentfilter === active` gate with a real accessibility test, (c) adds two plan-only (`lp` area) `select` customfields for `showrelated`/`showrelatedlink` with a 2-level (plan → global) resolver, and (d) wires `hideIf` on the template form so each setting only shows for the display mode it affects.

**Tech Stack:** Moodle 5.1 (supports 4.5→5.2), PHP (Moodle coding standard), core `customfield_select`, AMD/ES6 (grunt build), PHPUnit.

> **⚠️ No local test runner.** This checkout has no Moodle install (`config.php` absent), so PHPUnit/Behat cannot run locally — only `php -l`, `npx eslint`, `npx stylelint`, and `npx grunt amd` are verifiable here. For every "run the test" step, **verify in CI** (or on the user's server). Write the test first regardless (TDD ordering), then implement, then confirm green in CI. See `CLAUDE.md` for the eslint/stylelint/grunt commands (run from the Moodle root with `--root=public/local/dimensions`).

> **Commits:** the plugin repo's working branch is `main` (remote `uaiblaine/moodle-local_dimensions`); run git from the plugin dir. Commit messages follow conventional commits (`feat(...)`, `fix(...)`, `test(...)`, `refactor(...)`) and end with the `Co-Authored-By` trailer. Commit only when the user asks; the commit steps below mark natural boundaries.

---

## File map

| File | Change |
|---|---|
| `classes/calculator.php` | **+** `user_can_access_course()` (active enrolment OR self-enrol) |
| `view-competency.php` | redirect gate → accessibility; skip-plan for non-plan competencies |
| `classes/external/get_competency_courses.php` | **+** `planid` param; resolve cascade instead of `summaryenrollmentfilter` |
| `amd/src/accordion.js` (+ `amd/build/*`) | courses call always uses plugin WS + `planid`; related pill `target="_blank"` |
| `settings.php` | **−** `summaryenrollmentfilter` |
| `view-plan.php` | drop `summaryenrollmentfilter`; resolve `showrelated`/`showrelatedlink` per plan |
| `classes/constants.php` | **+** `CFIELD_SHOWRELATED*`, options + `SHOWRELATED_*` consts; docblock fix |
| `classes/helper.php` | **+** `get_showrelated*_field()`, `resolve_showrelated*_for_template()`, `competency_in_plan()`; provision in `ensure_custom_fields_exist` |
| `classes/form/template_dynamic_form.php` | **+** `hideIf` visibility rules |
| `lang/en/local_dimensions.php`, `lang/pt_br/local_dimensions.php` | new strings; reword `singlecourseredirect_desc`; remove `summaryenrollmentfilter*` |
| `db/upgrade.php`, `version.php` | savepoint block: `unset_config` summary + purge; bump to `2026071001` |
| `tests/helper_cascade_test.php`, `tests/calculator_access_test.php` | new PHPUnit |

---

## Task 1: `calculator::user_can_access_course()`

**Files:**
- Modify: `classes/calculator.php` (add method near `filter_courses_by_enrollment`)
- Test: `tests/calculator_access_test.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// GPL header (copy from tests/helper_scaleconfig_test.php lines 1-15).

namespace local_dimensions;

/**
 * Tests for calculator::user_can_access_course.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\calculator::user_can_access_course
 */
final class calculator_access_test extends \advanced_testcase {
    /**
     * Active enrolment → accessible; a course with no enrolment and no self instance → not.
     *
     * @return void
     */
    public function test_active_enrolment_is_accessible(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $enrolled = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $enrolled->id);
        $none = $this->getDataGenerator()->create_course();

        $this->assertTrue(calculator::user_can_access_course($enrolled, (int) $user->id));
        $this->assertFalse(calculator::user_can_access_course($none, (int) $user->id));
    }

    /**
     * An available self-enrol instance makes an un-enrolled course accessible.
     *
     * @return void
     */
    public function test_self_enrollable_is_accessible(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $course = $this->getDataGenerator()->create_course();
        $self = enrol_get_plugin('self');
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'self'], '*', MUST_EXIST);
        $self->update_status($instance, ENROL_INSTANCE_ENABLED);

        $this->assertTrue(calculator::user_can_access_course($course, (int) $user->id));
    }
}
```

- [ ] **Step 2: Run test to verify it fails** — Run in CI (no local runner): `vendor/bin/phpunit public/local/dimensions/tests/calculator_access_test.php`. Expected: FAIL (`user_can_access_course` undefined).

- [ ] **Step 3: Add the method** to `classes/calculator.php` (immediately after `filter_courses_by_enrollment`):

```php
    /**
     * Whether the user can actually open a course: actively enrolled, or able to self-enrol.
     *
     * Self-enrolment is checked via the core self plugin's can_self_enrol(), which is scoped to
     * the current user ($USER) and already enforces the instance status, dates, max-enrolled and
     * the cohort restriction (customint5) — so a plan's synced restriction cohort is honoured with
     * no extra code. The self branch therefore only answers for the current user.
     *
     * @param \stdClass $course A course record with at least an id.
     * @param int $userid The user id.
     * @return bool
     */
    public static function user_can_access_course(\stdClass $course, int $userid): bool {
        global $CFG, $USER;

        $coursecontext = \core\context\course::instance($course->id);
        if (is_enrolled($coursecontext, $userid, '', true)) {
            return true;
        }

        if ($userid !== (int) $USER->id) {
            return false;
        }

        require_once($CFG->dirroot . '/enrol/self/lib.php');
        $selfplugin = enrol_get_plugin('self');
        if (!$selfplugin) {
            return false;
        }
        foreach (enrol_get_instances($course->id, true) as $instance) {
            if ($instance->enrol === 'self' && $selfplugin->can_self_enrol($instance, false) === true) {
                return true;
            }
        }
        return false;
    }
```

- [ ] **Step 4: Run test to verify it passes** (CI). Expected: PASS.

- [ ] **Step 5: `php -l classes/calculator.php`** → no syntax errors.

- [ ] **Step 6: Commit**

```bash
git -C /Volumes/N1TB/dev/github/moodle/public/local/dimensions add classes/calculator.php tests/calculator_access_test.php
git -C /Volumes/N1TB/dev/github/moodle/public/local/dimensions commit -m "feat(learner): calculator::user_can_access_course (active OR self-enrollable)"
```

---

## Task 2: Decouple `singlecourseredirect` from the filter label

**Files:**
- Modify: `view-competency.php:99-117`

- [ ] **Step 1: Replace the redirect guard.** In `view-competency.php`, change the `$willredirect` block to drop `enrollmentfilter === ACTIVE` and gate on accessibility:

```php
    // Redirect to the single course only when there is exactly one course the user can
    // actually open (actively enrolled, or self-enrollable). Independent of the filter label.
    $singlecourseredirect = \local_dimensions\helper::resolve_singlecourseredirect_for_view(
        $competencyid,
        $templateid
    );
    $willredirect = (
        $singlecourseredirect
        && count($courses) === 1
        && calculator::user_can_access_course(reset($courses), $USER->id)
    );
```

(The FAB-skip block at `:105-112` and the `redirect()` at `:114-117` already key off `$willredirect` — leave them.)

- [ ] **Step 2:** `php -l view-competency.php` → no syntax errors. (`calculator` is already imported at `view-competency.php:30`.)

- [ ] **Step 3: Update `singlecourseredirect_desc`** — deferred to Task 11 (lang batch).

- [ ] **Step 4: Commit**

```bash
git -C <plugindir> add view-competency.php
git -C <plugindir> commit -m "fix(learner): redirect on a single accessible course, not on filter=active"
```

---

## Task 3: Panorama WS honours the `enrollmentfilter` cascade

**Files:**
- Modify: `classes/external/get_competency_courses.php`
- Test: extend `tests/external/` (new `tests/external/get_competency_courses_test.php`)

- [ ] **Step 1: Write the failing test**

```php
<?php
// GPL header.

namespace local_dimensions\external;

use local_dimensions\helper;
use local_dimensions\constants;

/**
 * Tests for get_competency_courses (Panorama cascade).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\external\get_competency_courses
 */
final class get_competency_courses_test extends \advanced_testcase {
    /**
     * A per-plan enrollmentfilter of 'active' hides an un-enrolled linked course in Panorama.
     *
     * @return void
     */
    public function test_plan_cascade_filters_courses(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_LP);
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);

        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework();
        $comp = $ccg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $template = $ccg->create_template();
        $ccg->create_template_competency(['templateid' => $template->get('id'), 'competencyid' => $comp->get('id')]);

        $user = $this->getDataGenerator()->create_user();
        $plan = $ccg->create_plan(['userid' => $user->id, 'templateid' => $template->get('id')]);

        // Two linked courses; the user is enrolled in only one.
        $c1 = $this->getDataGenerator()->create_course();
        $c2 = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $c1->id);
        \core_competency\api::add_competency_to_course($c1->id, $comp->get('id'));
        \core_competency\api::add_competency_to_course($c2->id, $comp->get('id'));

        // Set the template's enrollmentfilter to 'active' via the shared form-data path.
        $data = (object) (['id' => (int) $template->get('id')] + helper::customfields_to_formdata([]));
        $data->{'customfield_' . constants::CFIELD_ENROLLMENTFILTER} =
            array_search(constants::ENROLLMENTFILTER_ACTIVE, array_keys(constants::enrollmentfilter_options()), true) + 1;
        \local_dimensions\customfield\lp_handler::create()->instance_form_save($data, true);

        $this->setUser($user);
        $result = get_competency_courses::execute((int) $comp->get('id'), (int) $plan->get('id'));
        $ids = array_map(fn($r) => (int) $r['id'], $result);
        $this->assertContains((int) $c1->id, $ids);
        $this->assertNotContains((int) $c2->id, $ids);
    }
}
```

- [ ] **Step 2: Run to verify it fails** (CI). Expected: FAIL (`execute()` takes 1 arg / filters by summaryenrollmentfilter).

- [ ] **Step 3: Add the `planid` parameter** to `execute_parameters()`:

```php
    public static function execute_parameters() {
        return new external_function_parameters([
            'competencyid' => new external_value(PARAM_INT, 'The competency ID'),
            'planid' => new external_value(PARAM_INT, 'The learning plan ID (drives the enrolment-filter cascade)'),
        ]);
    }
```

- [ ] **Step 4: Change `execute()`** signature + validation + filter resolution:

```php
    public static function execute($competencyid, $planid) {
        global $USER, $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'competencyid' => $competencyid,
            'planid' => $planid,
        ]);
        $competencyid = $params['competencyid'];
        $planid = $params['planid'];

        $systemcontext = context_system::instance();
        self::validate_context($systemcontext);
        require_capability('local/dimensions:view', $systemcontext);
        // ... (course SQL unchanged) ...

        // Resolve the enrolment filter through the cascade (competency -> plan -> global).
        // The accordion only lists the plan's own competencies, so the plan's template applies.
        $templateid = 0;
        if ($planid > 0) {
            try {
                $templateid = (int) \core_competency\api::read_plan($planid)->get('templateid');
            } catch (\Exception $e) {
                $templateid = 0;
            }
        }
        $filtermode = \local_dimensions\helper::resolve_enrollmentfilter_for_view($competencyid, $templateid);
        if ($filtermode !== \local_dimensions\constants::ENROLLMENTFILTER_ALL) {
            $courses = \local_dimensions\calculator::filter_courses_by_enrollment($courses, $USER->id, $filtermode);
        }
        // ... (response build + return unchanged) ...
    }
```

Delete the old `$filtermode = get_config(... 'summaryenrollmentfilter')` block. Update the class docblock (`:17-23`) to say it resolves the enrolment-filter cascade.

- [ ] **Step 5: Run to verify it passes** (CI). Expected: PASS.

- [ ] **Step 6:** `php -l classes/external/get_competency_courses.php`.

- [ ] **Step 7: Commit**

```bash
git -C <plugindir> add classes/external/get_competency_courses.php tests/external/get_competency_courses_test.php
git -C <plugindir> commit -m "feat(learner): Panorama courses honour the enrollmentfilter cascade (planid)"
```

---

## Task 4: accordion.js — always use the plugin WS with `planid`; related pill new tab

**Files:**
- Modify: `amd/src/accordion.js` (courses call ~87-93; related pill ~1805-1809)
- Build: `amd/build/accordion.min.js` + `.map`

- [ ] **Step 1: Replace the courses method switch** (`amd/src/accordion.js:87-93`) with an unconditional plugin call:

```javascript
            // Always use the plugin WS: it resolves the enrolment-filter cascade
            // (competency -> plan -> global) server-side and returns richer course cards.
            const coursesPromise = Ajax.call([{
                methodname: 'local_dimensions_get_competency_courses',
                args: {competencyid: competencyId, planid: planId}
            }])[0];
```

Remove the now-unused `coursesMethodName` / `coursesArgs` lines and the `summaryenrollmentfilter` branch.

- [ ] **Step 2: Add `target="_blank"`** to the related pill (`amd/src/accordion.js:1806-1809`):

```javascript
                    const href = displaySettings.viewcompetencyurl + '?id=' + planId + '&competencyid=' + related.id;
                    html += '<a href="' + escapeHtml(href) +
                        '" target="_blank" rel="noopener"' +
                        ' class="local-dimensions-related-pill-v2 local-dimensions-related-pill-link">'
                        + escapeHtml(related.shortname) + '</a>';
```

- [ ] **Step 3: Lint** (from Moodle root): `npx eslint --max-warnings 0 public/local/dimensions/amd/src/accordion.js` → 0 warnings.

- [ ] **Step 4: Build** (from Moodle root): `npx grunt amd --root=public/local/dimensions` → writes `amd/build/accordion.min.js` + `.map`.

- [ ] **Step 5: Commit** (source + build together):

```bash
git -C <plugindir> add amd/src/accordion.js amd/build/accordion.min.js amd/build/accordion.min.js.map
git -C <plugindir> commit -m "feat(learner): accordion uses plugin WS (cascade) + related pill opens in new tab"
```

---

## Task 5: Retire `summaryenrollmentfilter`

**Files:**
- Modify: `settings.php:196-207` (remove block), `view-plan.php:69-72,82` (remove seed)

- [ ] **Step 1: Delete** the `summaryenrollmentfilter` admin setting block in `settings.php` (`:196-207`, the `admin_setting_configselect` and its comment).

- [ ] **Step 2: In `view-plan.php`** remove the `$summaryenrollmentfilter` seed (`:69-72`) and its key in `$accordionsettings` (`:82`). The accordion no longer needs it (Task 4 dropped the client-side gate).

- [ ] **Step 3:** `php -l settings.php view-plan.php`.

- [ ] **Step 4:** lang removal + `unset_config` are handled in Task 11 / Task 12. Commit deferred to Task 12 (grouped with lang) to avoid a half-removed string.

---

## Task 6: `helper::competency_in_plan()` + skip the plan layer for related competencies

**Files:**
- Modify: `classes/helper.php` (add `competency_in_plan`), `view-competency.php:61-98`
- Test: covered in Task 13 (`helper_cascade_test.php`)

- [ ] **Step 1: Add `competency_in_plan()`** to `classes/helper.php`:

```php
    /**
     * Whether a competency belongs to a plan (directly or via its template).
     *
     * Used to decide if the plan layer of the cascade applies: a related-competency page reached
     * from the accordion may point at a competency that is not in the plan, in which case only the
     * competency's own value and the global setting apply (the plan layer is skipped).
     *
     * @param int $competencyid Competency id.
     * @param \core_competency\plan $plan The plan.
     * @return bool
     */
    public static function competency_in_plan(int $competencyid, \core_competency\plan $plan): bool {
        foreach (\core_competency\api::list_plan_competencies($plan->get('id')) as $pc) {
            if ((int) $pc->competency->get('id') === $competencyid) {
                return true;
            }
        }
        return false;
    }
```

- [ ] **Step 2: In `view-competency.php`**, after `$templateid = (int) $plan->get('templateid');` (`:61`), compute the effective template id and use it in both resolvers:

```php
    // Related-competency links can point at a competency that is not in this plan; there the plan
    // layer of the cascade does not apply (competency -> global only).
    $effectivetemplateid = \local_dimensions\helper::competency_in_plan($competencyid, $plan)
        ? $templateid
        : 0;
```

Then change `:82-85` and `:95-98` to pass `$effectivetemplateid` instead of `$templateid`.

- [ ] **Step 3:** `php -l view-competency.php classes/helper.php`.

- [ ] **Step 4: Commit**

```bash
git -C <plugindir> add classes/helper.php view-competency.php
git -C <plugindir> commit -m "feat(learner): related-competency page skips the plan cascade layer"
```

---

## Task 7: `showrelated` / `showrelatedlink` — constants & options

**Files:**
- Modify: `classes/constants.php`

- [ ] **Step 1: Add the shortname constants** (near the other `CFIELD_*`, and fix the two stale docblocks at `:68,71` from "(lp area only)" to "(cascade select — both areas)"):

```php
    /** @var string Custom field shortname for per-template "show related competencies" (lp area only) */
    const CFIELD_SHOWRELATED = 'local_dimensions_showrelated';

    /** @var string Custom field shortname for per-template "link related competencies" (lp area only) */
    const CFIELD_SHOWRELATEDLINK = 'local_dimensions_showrelatedlink';
```

- [ ] **Step 2: Add the tri-state value constants** (after the `SINGLECOURSEREDIRECT_*` block):

```php
    /** @var string Show-related toggle: inherit the global setting */
    const SHOWRELATED_INHERIT = 'inherit';

    /** @var string Show-related toggle: enable for this template */
    const SHOWRELATED_YES = 'yes';

    /** @var string Show-related toggle: disable for this template */
    const SHOWRELATED_NO = 'no';
```

- [ ] **Step 3: Add two options methods** (mirroring `singlecourseredirect_options()`):

```php
    /**
     * Localized options for the per-template "show related competencies" select.
     *
     * @return array<string, \lang_string> keyed by option identifier
     */
    public static function showrelated_options(): array {
        return [
            self::SHOWRELATED_INHERIT => new \lang_string('showrelated_inherit', 'local_dimensions'),
            self::SHOWRELATED_YES => new \lang_string('showrelated_yes', 'local_dimensions'),
            self::SHOWRELATED_NO => new \lang_string('showrelated_no', 'local_dimensions'),
        ];
    }

    /**
     * Localized options for the per-template "link related competencies" select.
     *
     * @return array<string, \lang_string> keyed by option identifier
     */
    public static function showrelatedlink_options(): array {
        return [
            self::SHOWRELATED_INHERIT => new \lang_string('showrelatedlink_inherit', 'local_dimensions'),
            self::SHOWRELATED_YES => new \lang_string('showrelatedlink_yes', 'local_dimensions'),
            self::SHOWRELATED_NO => new \lang_string('showrelatedlink_no', 'local_dimensions'),
        ];
    }
```

- [ ] **Step 4:** `php -l classes/constants.php`. Commit deferred to Task 8 (fields depend on these).

---

## Task 8: `showrelated` / `showrelatedlink` — fields, resolvers, provisioning

**Files:**
- Modify: `classes/helper.php`
- Test: Task 13

- [ ] **Step 1: Add two field getters** to `classes/helper.php` (mirror `get_singlecourseredirect_field`, but `AREA_LP` only):

```php
    /**
     * Get or create the per-template "show related competencies" select field (lp area).
     *
     * @return field_controller|null
     */
    public static function get_showrelated_field(): ?field_controller {
        $shortname = constants::CFIELD_SHOWRELATED;
        $field = self::find_field_by_shortname($shortname, self::AREA_LP);
        if ($field) {
            return $field;
        }
        $options = constants::showrelated_options();
        $optionstext = [];
        foreach ($options as $key => $langstring) {
            $optionstext[] = (string) $langstring;
        }
        return self::create_custom_field(
            $shortname,
            'select',
            self::AREA_LP,
            new \lang_string('showrelated', 'local_dimensions'),
            [
                'options' => join("\n", $optionstext),
                'defaultvalue' => (string) $options[constants::SHOWRELATED_INHERIT],
            ],
            ''
        );
    }

    /**
     * Get or create the per-template "link related competencies" select field (lp area).
     *
     * @return field_controller|null
     */
    public static function get_showrelatedlink_field(): ?field_controller {
        $shortname = constants::CFIELD_SHOWRELATEDLINK;
        $field = self::find_field_by_shortname($shortname, self::AREA_LP);
        if ($field) {
            return $field;
        }
        $options = constants::showrelatedlink_options();
        $optionstext = [];
        foreach ($options as $key => $langstring) {
            $optionstext[] = (string) $langstring;
        }
        return self::create_custom_field(
            $shortname,
            'select',
            self::AREA_LP,
            new \lang_string('showrelatedlink', 'local_dimensions'),
            [
                'options' => join("\n", $optionstext),
                'defaultvalue' => (string) $options[constants::SHOWRELATED_INHERIT],
            ],
            ''
        );
    }
```

- [ ] **Step 2: Add two resolvers** (2-level plan → global, returning bool):

```php
    /**
     * Resolve whether related competencies are shown for a template (plan -> global).
     *
     * @param int $templateid Learning plan template ID.
     * @return bool
     */
    public static function resolve_showrelated_for_template(int $templateid): bool {
        return self::resolve_lp_bool_toggle(
            $templateid,
            self::get_showrelated_field(),
            constants::showrelated_options(),
            (bool) get_config('local_dimensions', 'showrelated')
        );
    }

    /**
     * Resolve whether related-competency links are shown for a template (plan -> global).
     *
     * @param int $templateid Learning plan template ID.
     * @return bool
     */
    public static function resolve_showrelatedlink_for_template(int $templateid): bool {
        return self::resolve_lp_bool_toggle(
            $templateid,
            self::get_showrelatedlink_field(),
            constants::showrelatedlink_options(),
            (bool) get_config('local_dimensions', 'showrelatedlink')
        );
    }

    /**
     * Resolve an lp inherit/yes/no toggle field to a bool, falling back to the global default.
     *
     * @param int $templateid Learning plan template ID.
     * @param field_controller|null $field The customfield, or null.
     * @param array $options The inherit/yes/no options map (keys are the option ids).
     * @param bool $global The global default used when the field is unset or inherits.
     * @return bool
     */
    private static function resolve_lp_bool_toggle(
        int $templateid,
        ?field_controller $field,
        array $options,
        bool $global
    ): bool {
        if ($templateid <= 0 || !$field) {
            return $global;
        }
        $allowed = array_keys($options);
        $resolved = constants::SHOWRELATED_INHERIT;
        $fields = \core_customfield\api::get_instance_fields_data([$field->get('id') => $field], $templateid);
        foreach ($fields as $data) {
            $value = $data->get_value();
            if (is_int($value) && isset($allowed[$value - 1])) {
                $value = $allowed[$value - 1];
            }
            $value = (string) $value;
            if ($value !== '' && in_array($value, $allowed, true)) {
                $resolved = $value;
                break;
            }
        }
        if ($resolved === constants::SHOWRELATED_INHERIT) {
            return $global;
        }
        return $resolved === constants::SHOWRELATED_YES;
    }
```

- [ ] **Step 3: Provision the fields.** In `ensure_custom_fields_exist()` (`:437-441`, the `AREA_LP`-only block), add:

```php
            self::get_showrelated_field();
            self::get_showrelatedlink_field();
```

- [ ] **Step 4:** `php -l classes/helper.php`.

- [ ] **Step 5: Commit**

```bash
git -C <plugindir> add classes/constants.php classes/helper.php
git -C <plugindir> commit -m "feat(learner): per-plan showrelated/showrelatedlink customfields + resolvers"
```

---

## Task 9: view-plan.php resolves `showrelated`/`showrelatedlink` per plan

**Files:**
- Modify: `view-plan.php:74-89`

- [ ] **Step 1: Replace the two raw `get_config` reads** (`:78-79`) with the resolvers, using the plan's template id:

```php
    $templateid = (int) $plan->get('templateid');
    // ... inside $accordionsettings:
        'showrelated' => \local_dimensions\helper::resolve_showrelated_for_template($templateid),
        'showrelatedlink' => \local_dimensions\helper::resolve_showrelatedlink_for_template($templateid),
```

(Add `$templateid` just above `$accordionsettings`; the other keys stay `get_config`.)

- [ ] **Step 2:** `php -l view-plan.php`.

- [ ] **Step 3: Commit**

```bash
git -C <plugindir> add view-plan.php
git -C <plugindir> commit -m "feat(learner): Panorama reads showrelated/showrelatedlink from the plan cascade"
```

---

## Task 10: Template form — conditional visibility (`hideIf`)

**Files:**
- Modify: `classes/form/template_dynamic_form.php:129` (in `definition()`)

- [ ] **Step 1: After `lp_handler::create()->instance_form_definition(...)` (`:129`)**, add:

```php
        // Show each cascade setting only for the display mode it affects. The displaymode select
        // submits the 1-based option index, which equals the DISPLAYMODE_* constant by construction.
        $displaymode = 'customfield_' . constants::CFIELD_DISPLAYMODE;
        // singlecourseredirect: Trilha only.
        $mform->hideIf(
            'customfield_' . constants::CFIELD_SINGLECOURSEREDIRECT,
            $displaymode,
            'eq',
            (string) constants::DISPLAYMODE_PLAN
        );
        // showrelated / showrelatedlink: Panorama only.
        $mform->hideIf(
            'customfield_' . constants::CFIELD_SHOWRELATED,
            $displaymode,
            'eq',
            (string) constants::DISPLAYMODE_COMPETENCIES
        );
        $mform->hideIf(
            'customfield_' . constants::CFIELD_SHOWRELATEDLINK,
            $displaymode,
            'eq',
            (string) constants::DISPLAYMODE_COMPETENCIES
        );
        // showrelatedlink is moot when showrelated is off.
        $mform->hideIf(
            'customfield_' . constants::CFIELD_SHOWRELATEDLINK,
            'customfield_' . constants::CFIELD_SHOWRELATED,
            'eq',
            (string) (array_search(constants::SHOWRELATED_NO, array_keys(constants::showrelated_options()), true) + 1)
        );
```

- [ ] **Step 2:** `php -l classes/form/template_dynamic_form.php`.

- [ ] **Step 3: Commit**

```bash
git -C <plugindir> add classes/form/template_dynamic_form.php
git -C <plugindir> commit -m "feat(central): template form shows each setting only for its display mode"
```

---

## Task 11: Lang strings (en + pt_br)

**Files:**
- Modify: `lang/en/local_dimensions.php`, `lang/pt_br/local_dimensions.php`

- [ ] **Step 1: Remove** `summaryenrollmentfilter` and `summaryenrollmentfilter_desc` from **both** files.

- [ ] **Step 2: Reword** `singlecourseredirect_desc` in **both** files (drop the filter=active dependency). EN:

```php
$string['singlecourseredirect_desc'] = 'When enabled, if a competency ends up with exactly one course the learner can open (they are actively enrolled, or can self-enrol), the competency page is skipped and the learner goes straight to that course. Independent of the enrolment filter.';
```

pt_br:

```php
$string['singlecourseredirect_desc'] = 'Quando ativado, se uma competência ficar com exatamente um curso que o aluno pode acessar (inscrito ativo, ou pode se autoinscrever), a tela da competência é ignorada e o aluno vai direto ao curso. Independe do filtro de inscrições.';
```

- [ ] **Step 3: Add** the new field + option strings in **both** files, in alphabetic slots. EN:

```php
$string['showrelated_inherit'] = 'Use global setting (default)';
$string['showrelated_no'] = 'No — hide related competencies';
$string['showrelated_yes'] = 'Yes — show related competencies';
$string['showrelatedlink_inherit'] = 'Use global setting (default)';
$string['showrelatedlink_no'] = 'No — plain (no link)';
$string['showrelatedlink_yes'] = 'Yes — link to the competency page';
```

pt_br:

```php
$string['showrelated_inherit'] = 'Usar configuração global (padrão)';
$string['showrelated_no'] = 'Não — ocultar competências relacionadas';
$string['showrelated_yes'] = 'Sim — mostrar competências relacionadas';
$string['showrelatedlink_inherit'] = 'Usar configuração global (padrão)';
$string['showrelatedlink_no'] = 'Não — sem link';
$string['showrelatedlink_yes'] = 'Sim — link para a página da competência';
```

(`showrelated` / `showrelatedlink` field-name strings already exist — they are the global setting labels — and are reused as the field names.)

- [ ] **Step 4: Add cascade help strings** (used in Task 12). EN:

```php
$string['cascade_help'] = 'This setting overrides the global default for this plan. Individual competencies can override it again — the most specific value wins (competency, then plan, then global).';
$string['cascade_help_competency'] = 'This setting overrides the plan and the global default for this competency. Leave on “inherit” to use the plan’s setting, or the global default when the plan also inherits.';
```

pt_br:

```php
$string['cascade_help'] = 'Esta configuração sobrepõe o padrão global para este plano. Cada competência pode sobrepor de novo — vence o valor mais específico (competência, depois plano, depois global).';
$string['cascade_help_competency'] = 'Esta configuração sobrepõe o plano e o padrão global para esta competência. Deixe em “herdar” para usar a configuração do plano, ou o global quando o plano também herdar.';
```

- [ ] **Step 5: Grep the changed PHP for CI traps** (from `<plugindir>`):

```bash
awk 'length($0)>132{print FILENAME":"NR" ("length($0)")"}' lang/en/local_dimensions.php lang/pt_br/local_dimensions.php
```

Wrap any long `_desc` line if it exceeds 132.

- [ ] **Step 6: Commit** (with the Task 5 settings/view-plan removals):

```bash
git -C <plugindir> add settings.php view-plan.php lang/en/local_dimensions.php lang/pt_br/local_dimensions.php
git -C <plugindir> commit -m "feat(learner): retire summaryenrollmentfilter; lang for cascade toggles + reworded redirect desc"
```

---

## Task 12: Form help text (cascade guidance)

**Files:**
- Modify: `classes/form/template_dynamic_form.php`, `classes/form/competency_dynamic_form.php`

- [ ] **Step 1:** After the custom-field definition in **each** form, add a static help note near the enrolment settings. Template form (add in `definition()`):

```php
        $mform->addElement('static', 'local_dimensions_cascadehelp', '',
            get_string('cascade_help', 'local_dimensions'));
```

Competency form (`competency_dynamic_form.php`, after `competency_handler::create()->instance_form_definition(...)`):

```php
        $mform->addElement('static', 'local_dimensions_cascadehelp', '',
            get_string('cascade_help_competency', 'local_dimensions'));
```

- [ ] **Step 2:** `php -l` both form files.

- [ ] **Step 3: Commit**

```bash
git -C <plugindir> add classes/form/template_dynamic_form.php classes/form/competency_dynamic_form.php
git -C <plugindir> commit -m "feat(central): explain the settings cascade in the plan + competency forms"
```

---

## Task 13: PHPUnit — `helper_cascade_test.php`

**Files:**
- Test: `tests/helper_cascade_test.php`

- [ ] **Step 1: Write the test** covering all resolvers. (Full file — reuse the generator + `customfields_to_formdata` pattern from `tests/local/framework_csv_importer_test.php` and `tests/helper_subline_test.php`.)

```php
<?php
// GPL header.

namespace local_dimensions;

use local_dimensions\customfield\competency_handler;
use local_dimensions\customfield\lp_handler;

/**
 * Tests for the enrolment/display settings cascade resolvers.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\helper::resolve_enrollmentfilter_for_view
 * @covers     \local_dimensions\helper::resolve_singlecourseredirect_for_view
 * @covers     \local_dimensions\helper::resolve_showrelated_for_template
 * @covers     \local_dimensions\helper::resolve_showrelatedlink_for_template
 */
final class helper_cascade_test extends \advanced_testcase {
    /**
     * Set an lp select field to an option key by its 1-based index.
     *
     * @param int $templateid Template id.
     * @param string $shortname Custom-field shortname.
     * @param array $keys Ordered option keys.
     * @param string $key Chosen key.
     * @return void
     */
    private function set_lp(int $templateid, string $shortname, array $keys, string $key): void {
        $pos = array_search($key, $keys, true);
        $data = (object) ['id' => $templateid, 'customfield_' . $shortname => $pos === false ? 0 : $pos + 1];
        lp_handler::create()->instance_form_save($data, true);
    }

    /**
     * enrollmentfilter resolves competency -> plan -> global, and templateid=0 skips the plan.
     *
     * @return void
     */
    public function test_enrollmentfilter_cascade(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_LP);
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);
        set_config('enrollmentfilter', constants::ENROLLMENTFILTER_ALL, 'local_dimensions');

        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework();
        $comp = $ccg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $compid = (int) $comp->get('id');
        $templateid = (int) $ccg->create_template()->get('id');
        $efkeys = array_keys(constants::enrollmentfilter_options());

        // Both inherit -> global (all).
        $this->assertSame(constants::ENROLLMENTFILTER_ALL, helper::resolve_enrollmentfilter_for_view($compid, $templateid));

        // Plan = enrolled, competency inherits -> plan.
        $this->set_lp($templateid, constants::CFIELD_ENROLLMENTFILTER, $efkeys, constants::ENROLLMENTFILTER_ENROLLED);
        $this->assertSame(constants::ENROLLMENTFILTER_ENROLLED, helper::resolve_enrollmentfilter_for_view($compid, $templateid));

        // Competency = active -> competency wins.
        $cdata = (object) ['id' => $compid];
        $cdata->{'customfield_' . constants::CFIELD_ENROLLMENTFILTER} =
            array_search(constants::ENROLLMENTFILTER_ACTIVE, $efkeys, true) + 1;
        competency_handler::create()->instance_form_save($cdata, true);
        $this->assertSame(constants::ENROLLMENTFILTER_ACTIVE, helper::resolve_enrollmentfilter_for_view($compid, $templateid));

        // templateid=0 skips the plan; competency still wins.
        $this->assertSame(constants::ENROLLMENTFILTER_ACTIVE, helper::resolve_enrollmentfilter_for_view($compid, 0));
    }

    /**
     * showrelated/showrelatedlink resolve plan -> global (2-level, no competency layer).
     *
     * @return void
     */
    public function test_showrelated_cascade(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_LP);
        set_config('showrelated', 0, 'local_dimensions');

        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $templateid = (int) $ccg->create_template()->get('id');
        $keys = array_keys(constants::showrelated_options());

        // Inherit -> global (off).
        $this->assertFalse(helper::resolve_showrelated_for_template($templateid));
        // Plan = yes -> on.
        $this->set_lp($templateid, constants::CFIELD_SHOWRELATED, $keys, constants::SHOWRELATED_YES);
        $this->assertTrue(helper::resolve_showrelated_for_template($templateid));
    }
}
```

- [ ] **Step 2: Run** (CI). Expected: PASS.

- [ ] **Step 3:** `php -l tests/helper_cascade_test.php`.

- [ ] **Step 4: Commit**

```bash
git -C <plugindir> add tests/helper_cascade_test.php
git -C <plugindir> commit -m "test(learner): cascade resolvers (enrollmentfilter/showrelated) + skip-plan path"
```

---

## Task 14: Version bump + upgrade savepoint

**Files:**
- Modify: `version.php:28`, `db/upgrade.php` (new savepoint block before the catch-all)

- [ ] **Step 1: Bump** `version.php:28` → `$plugin->version = 2026071001;` (keep the higher number on any rebase).

- [ ] **Step 2: Add an upgrade block** in `db/upgrade.php` immediately before the catch-all `ensure_custom_fields_exist` tail:

```php
    if ($oldversion < 2026071001) {
        // The Panorama accordion now uses the enrollmentfilter cascade, so the separate
        // summaryenrollmentfilter setting is retired. The catch-all below provisions the two new
        // per-plan showrelated/showrelatedlink customfields. Purge so the rebuilt WS signature,
        // AMD bundles and strings are served fresh.
        unset_config('summaryenrollmentfilter', 'local_dimensions');
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071001, 'local', 'dimensions');
    }
```

- [ ] **Step 3:** `php -l version.php db/upgrade.php`.

- [ ] **Step 4: Full pre-push lint gate** (from Moodle root):

```bash
npx eslint --max-warnings 0 public/local/dimensions/amd/src
npx stylelint public/local/dimensions/styles.css
```

Both must be clean (grunt build from Task 4 already regenerated the bundle).

- [ ] **Step 5: Commit**

```bash
git -C <plugindir> add version.php db/upgrade.php
git -C <plugindir> commit -m "chore: bump version; retire summaryenrollmentfilter + provision showrelated fields on upgrade"
```

---

## Self-review notes (coverage vs spec)

- §3.1 unify enrollmentfilter → Tasks 3, 4, 5, 11, 14. ✅
- §3.2 redirect accessibility + Trilha-only → Tasks 1, 2, 10, 11. ✅
- §3.3 related pill new tab + skip-plan → Tasks 4, 6. ✅
- §3.4 showrelated/showrelatedlink per plan → Tasks 7, 8, 9, 10. ✅
- §3.5 form conditional visibility → Task 10. ✅
- §3.6 copy/help + docblock fix → Tasks 7, 11, 12. ✅
- §5 tests → Tasks 1, 3, 13. ✅
- §4 versioning → Task 14. ✅

**Known follow-ups for the executor:** confirm `create_plan` generator args (`userid`, `templateid`) and `add_competency_to_course` signatures against the installed core version before running Task 3's test; wrap any `_desc` lang line that exceeds 132 chars (Task 11 Step 5).
