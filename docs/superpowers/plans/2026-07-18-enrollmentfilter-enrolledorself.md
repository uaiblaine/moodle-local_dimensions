# Enrolment filter "enrolled and self-enrolable" Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a fourth `enrollmentfilter` value, `enrolledorself`, that shows each learner the courses they are enrolled in (including future/suspended enrolments) plus the linked courses they could self-enrol into, at the global / per-plan / per-competency cascade levels.

**Architecture:** One new membership test in `calculator` (reusing the existing `can_self_enrol` gate) drives both learner views through the single existing filter method. The option is appended last to `enrollmentfilter_options()` so the custom-field select's 1-based stored index never remaps. Existing sites get the new option added to their already-provisioned select fields by a one-shot `db/upgrade.php` step (the provisioning catch-all cannot, as it short-circuits on the existing field), gated behind a `version.php` bump.

**Tech Stack:** Moodle 4.5–5.2 local plugin (PHP), `core_customfield` select fields, PHPUnit (`advanced_testcase`). No JS/CSS changes.

---

## Reference — verified facts (read before starting)

- The filter is one method: `calculator::filter_courses_by_enrollment($courses, $userid, $filtermode)` at `classes/calculator.php:344`. `all` short-circuits; otherwise it keeps courses via `is_enrolled($ctx, $userid, '', $onlyactive)`.
- Two production callers, both routing through that method: `view-competency.php:88-93` and `classes/external/get_competency_courses.php:100-102`. **Editing the one method covers both views.**
- The self-enrol logic already exists in `calculator::user_can_access_course()` at `classes/calculator.php:376`.
- **Invariant 1 — option order is data.** The per-plan/per-competency select stores a 1-based index into `enrollmentfilter_options()` (`helper::get_template_enrollmentfilter` maps back with `$allowed[$value - 1]`, `classes/helper.php:767`). Order today: `1=inherit, 2=all, 3=enrolled, 4=active`. **Append the new option last (index 5).**
- **Invariant 2 — existing sites do not re-sync.** `helper::get_enrollmentfilter_field()` (`classes/helper.php:620`) returns early on an existing field and never rewrites `configdata['options']`; the upgrade catch-all inherits that early-return. A dedicated upgrade step is required.
- `calculator` and `constants` share `namespace local_dimensions`, so `constants::FOO` resolves without a `use`.
- Custom-field `configdata` is stored as a JSON string with an `options` key of `"\n"`-joined option labels (see `create_custom_field`, `classes/helper.php:138`).

---

## Task 1: Add the constant, option, lang strings, and global setting

Foundational data change. A regression test locks the append-only option order (Invariant 1). The lang strings and global menu entry are validated by CI (lang alphabetic ordering) and used by the select provisioning.

**Files:**
- Modify: `classes/constants.php` (constant block near line 96; `enrollmentfilter_options()` near line 177)
- Modify: `lang/en/local_dimensions.php` (after line 353)
- Modify: `lang/pt_br/local_dimensions.php` (after line 353)
- Modify: `settings.php` (choices array, lines 172-176)
- Test (create): `tests/enrollmentfilter_options_test.php`

- [ ] **Step 1: Write the failing test for option order**

Create `tests/enrollmentfilter_options_test.php`:

```php
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

namespace local_dimensions;

/**
 * Tests for the enrollmentfilter option list ordering.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\constants::enrollmentfilter_options
 */
final class enrollmentfilter_options_test extends \advanced_testcase {
    /**
     * The option keys are append-only: the first four indices must never move,
     * because the per-plan/per-competency select stores a 1-based index into this list.
     *
     * @return void
     */
    public function test_option_order_is_append_only(): void {
        $this->assertSame(
            [
                constants::ENROLLMENTFILTER_INHERIT,
                constants::ENROLLMENTFILTER_ALL,
                constants::ENROLLMENTFILTER_ENROLLED,
                constants::ENROLLMENTFILTER_ACTIVE,
                constants::ENROLLMENTFILTER_ENROLLEDORSELF,
            ],
            array_keys(constants::enrollmentfilter_options())
        );
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run (on the user's Moodle server, from the Moodle root — there is no local PHPUnit runner in this checkout):
`vendor/bin/phpunit public/local/dimensions/tests/enrollmentfilter_options_test.php`
Expected: FAIL — `Undefined constant ... ENROLLMENTFILTER_ENROLLEDORSELF` (or an assertion mismatch).

- [ ] **Step 3: Add the constant in `classes/constants.php`**

After the `ENROLLMENTFILTER_ACTIVE` constant (line 96), add:

```php
    /** @var string Enrollment filter: show enrolled courses plus courses the user can self-enrol into */
    const ENROLLMENTFILTER_ENROLLEDORSELF = 'enrolledorself';
```

- [ ] **Step 4: Append the option in `enrollmentfilter_options()`**

In `classes/constants.php`, change the `enrollmentfilter_options()` return so the new entry is **last**:

```php
    public static function enrollmentfilter_options(): array {
        return [
            self::ENROLLMENTFILTER_INHERIT => new \lang_string('enrollmentfilter_inherit', 'local_dimensions'),
            self::ENROLLMENTFILTER_ALL => new \lang_string('enrollmentfilter_all', 'local_dimensions'),
            self::ENROLLMENTFILTER_ENROLLED => new \lang_string('enrollmentfilter_enrolled', 'local_dimensions'),
            self::ENROLLMENTFILTER_ACTIVE => new \lang_string('enrollmentfilter_active', 'local_dimensions'),
            self::ENROLLMENTFILTER_ENROLLEDORSELF =>
                new \lang_string('enrollmentfilter_enrolledorself', 'local_dimensions'),
        ];
    }
```

- [ ] **Step 5: Add the EN lang string**

In `lang/en/local_dimensions.php`, insert **between** `enrollmentfilter_enrolled` and `enrollmentfilter_inherit` (after line 353):

```php
$string['enrollmentfilter_enrolledorself'] = 'Show enrolled and self-enrolable courses';
```

Also append the third-party-view note to `enrollmentfilter_desc` (line 352), so it reads:

```php
$string['enrollmentfilter_desc'] = 'Filter which courses are displayed in the Competency Tracker based on the user\'s enrolment status. Self-enrolable courses are only counted when a learner views their own plan.';
```

- [ ] **Step 6: Add the PT-BR lang string**

In `lang/pt_br/local_dimensions.php`, insert in the same slot (after line 353):

```php
$string['enrollmentfilter_enrolledorself'] = 'Exibir cursos inscritos e disponíveis para autoinscrição';
```

And update `enrollmentfilter_desc` (line 352):

```php
$string['enrollmentfilter_desc'] = 'Filtre os cursos exibidos na visualização por competência com base no status de inscrição do usuário. Cursos com autoinscrição disponível só são contabilizados quando o próprio aluno visualiza seu plano.';
```

- [ ] **Step 7: Add the option to the global admin menu**

In `settings.php`, add the new choice **last** in the `admin_setting_configselect` choices array (lines 172-176):

```php
        [
            'all' => get_string('enrollmentfilter_all', 'local_dimensions'),
            'enrolled' => get_string('enrollmentfilter_enrolled', 'local_dimensions'),
            'active' => get_string('enrollmentfilter_active', 'local_dimensions'),
            'enrolledorself' => get_string('enrollmentfilter_enrolledorself', 'local_dimensions'),
        ]
```

- [ ] **Step 8: Run the test to verify it passes**

Run: `vendor/bin/phpunit public/local/dimensions/tests/enrollmentfilter_options_test.php`
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git -C /Volumes/N1TB/dev/github/moodle/public/local/dimensions add \
  classes/constants.php lang/en/local_dimensions.php lang/pt_br/local_dimensions.php \
  settings.php tests/enrollmentfilter_options_test.php
git -C /Volumes/N1TB/dev/github/moodle/public/local/dimensions commit -m "feat(filter): add 'enrolledorself' enrolment-filter option (data + strings)"
```

---

## Task 2: Add the filter membership logic in `calculator`

Extract the self-enrol loop (DRY), add the aggregate membership test, and branch the filter. `user_can_access_course` keeps identical behaviour.

**Files:**
- Modify: `classes/calculator.php` (`filter_courses_by_enrollment` 344-362; `user_can_access_course` 376-399; add two methods)
- Test (create): `tests/calculator_filter_test.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/calculator_filter_test.php`:

```php
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

namespace local_dimensions;

/**
 * Tests for calculator::filter_courses_by_enrollment in 'enrolledorself' mode.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\calculator::filter_courses_by_enrollment
 */
final class calculator_filter_test extends \advanced_testcase {
    /**
     * enrolledorself keeps active, future-dated and self-enrolable courses; drops the rest.
     *
     * @return void
     */
    public function test_enrolledorself_keeps_enrolled_and_self_enrolable(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $active = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $active->id, 'student');

        // Future-dated enrolment: counts for onlyactive=false, not for onlyactive=true.
        $future = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $future->id, null, 'manual', time() + DAYSECS);

        $selfcourse = $this->getDataGenerator()->create_course();
        $self = enrol_get_plugin('self');
        $instance = $DB->get_record('enrol', ['courseid' => $selfcourse->id, 'enrol' => 'self'], '*', MUST_EXIST);
        $self->update_status($instance, ENROL_INSTANCE_ENABLED);

        // No enrolment and the default self instance stays disabled -> dropped.
        $none = $this->getDataGenerator()->create_course();

        $courses = [
            $active->id => $active,
            $future->id => $future,
            $selfcourse->id => $selfcourse,
            $none->id => $none,
        ];
        $filtered = calculator::filter_courses_by_enrollment(
            $courses,
            (int) $user->id,
            constants::ENROLLMENTFILTER_ENROLLEDORSELF
        );
        $ids = array_map('intval', array_column($filtered, 'id'));

        $this->assertContains((int) $active->id, $ids);
        $this->assertContains((int) $future->id, $ids);
        $this->assertContains((int) $selfcourse->id, $ids);
        $this->assertNotContains((int) $none->id, $ids);
    }

    /**
     * For another user's plan the self-enrol leg is skipped (degrades to enrolled-only).
     *
     * @return void
     */
    public function test_enrolledorself_other_user_skips_self_leg(): void {
        global $DB;
        $this->resetAfterTest();
        $learner = $this->getDataGenerator()->create_user();
        $manager = $this->getDataGenerator()->create_user();

        $enrolled = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($learner->id, $enrolled->id, 'student');

        $selfcourse = $this->getDataGenerator()->create_course();
        $self = enrol_get_plugin('self');
        $instance = $DB->get_record('enrol', ['courseid' => $selfcourse->id, 'enrol' => 'self'], '*', MUST_EXIST);
        $self->update_status($instance, ENROL_INSTANCE_ENABLED);

        // Viewer is the manager, not the learner.
        $this->setUser($manager);

        $filtered = calculator::filter_courses_by_enrollment(
            [$enrolled->id => $enrolled, $selfcourse->id => $selfcourse],
            (int) $learner->id,
            constants::ENROLLMENTFILTER_ENROLLEDORSELF
        );
        $ids = array_map('intval', array_column($filtered, 'id'));

        $this->assertContains((int) $enrolled->id, $ids);
        $this->assertNotContains((int) $selfcourse->id, $ids);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit public/local/dimensions/tests/calculator_filter_test.php`
Expected: FAIL — `enrolledorself` falls through the current method to the `is_enrolled(onlyactive=false)` path, so `$selfcourse` is dropped and `test_enrolledorself_keeps_enrolled_and_self_enrolable` fails on `assertContains($selfcourse)`.

- [ ] **Step 3: Extract the self-enrol loop and refactor `user_can_access_course`**

In `classes/calculator.php`, replace `user_can_access_course()` (lines 376-399) with the refactored version plus a private helper:

```php
    /**
     * Whether the user can actually open a course: actively enrolled, or able to self-enrol.
     *
     * The self branch only answers for the current $USER (core's can_self_enrol is $USER-scoped).
     *
     * @param \stdClass $course A course record with at least an id.
     * @param int $userid The user id.
     * @return bool
     */
    public static function user_can_access_course(\stdClass $course, int $userid): bool {
        global $USER;

        $coursecontext = \core\context\course::instance($course->id);
        if (is_enrolled($coursecontext, $userid, '', true)) {
            return true;
        }

        if ($userid !== (int) $USER->id) {
            return false;
        }

        return self::current_user_can_self_enrol((int) $course->id);
    }

    /**
     * Whether the current $USER can self-enrol into the course via an enabled self instance.
     *
     * Scoped to $USER by core's can_self_enrol(); callers must gate on $userid === $USER->id.
     * The self plugin already enforces the instance status, dates, max-enrolled and cohort
     * restriction (customint5), so a plan's synced restriction cohort is honoured for free.
     *
     * @param int $courseid The course id.
     * @return bool
     */
    private static function current_user_can_self_enrol(int $courseid): bool {
        global $CFG;

        require_once($CFG->dirroot . '/enrol/self/lib.php');
        $selfplugin = enrol_get_plugin('self');
        if (!$selfplugin) {
            return false;
        }
        foreach (enrol_get_instances($courseid, true) as $instance) {
            if ($instance->enrol === 'self' && $selfplugin->can_self_enrol($instance, false) === true) {
                return true;
            }
        }
        return false;
    }
```

- [ ] **Step 4: Add the aggregate membership test method**

In `classes/calculator.php`, add after `current_user_can_self_enrol()`:

```php
    /**
     * Whether the user is enrolled (incl. future/suspended) or — for the current $USER — can self-enrol.
     *
     * Membership test for the 'enrolledorself' display filter: the existing 'enrolled' semantics
     * (is_enrolled onlyactive=false, so future-dated and suspended enrolments count) plus the linked
     * courses the current viewer could self-enrol into. The self leg is evaluable only for $USER, so
     * when staff view another learner's plan it degrades to enrolled-only.
     *
     * @param \stdClass $course A course record with at least an id.
     * @param int $userid The user id.
     * @return bool
     */
    public static function user_enrolled_or_self_enrolable(\stdClass $course, int $userid): bool {
        global $USER;

        $coursecontext = \core\context\course::instance($course->id);
        if (is_enrolled($coursecontext, $userid, '', false)) {
            return true;
        }

        if ($userid !== (int) $USER->id) {
            return false;
        }

        return self::current_user_can_self_enrol((int) $course->id);
    }
```

- [ ] **Step 5: Branch the filter method**

In `classes/calculator.php`, update `filter_courses_by_enrollment()` (lines 344-362). Change the docblock `@param` and add the branch before the active/enrolled loop:

```php
    /**
     * Filter courses based on the enrollment filter setting.
     *
     * @param array $courses Array of course records (must have ->id property)
     * @param int $userid The user ID to check enrollment for
     * @param string $filtermode One of 'all', 'enrolled', 'active', 'enrolledorself'
     * @return array Filtered array of course records
     */
    public static function filter_courses_by_enrollment(array $courses, int $userid, string $filtermode): array {
        if ($filtermode === 'all' || empty($courses)) {
            return $courses;
        }

        if ($filtermode === constants::ENROLLMENTFILTER_ENROLLEDORSELF) {
            $filtered = [];
            foreach ($courses as $key => $course) {
                if (self::user_enrolled_or_self_enrolable($course, $userid)) {
                    $filtered[$key] = $course;
                }
            }
            return $filtered;
        }

        // Active mode: only actively enrolled (is_enrolled with onlyactive=true).
        // Enrolled mode: any enrollment record (is_enrolled with onlyactive=false).
        $onlyactive = ($filtermode === 'active');

        $filtered = [];
        foreach ($courses as $key => $course) {
            $coursecontext = \core\context\course::instance($course->id);
            if (is_enrolled($coursecontext, $userid, '', $onlyactive)) {
                $filtered[$key] = $course;
            }
        }

        return $filtered;
    }
```

- [ ] **Step 6: Run both test files to verify pass + no regression**

Run: `vendor/bin/phpunit public/local/dimensions/tests/calculator_filter_test.php`
Expected: PASS.
Run: `vendor/bin/phpunit public/local/dimensions/tests/calculator_access_test.php`
Expected: PASS (the extraction is behaviour-preserving).

- [ ] **Step 7: Commit**

```bash
git -C /Volumes/N1TB/dev/github/moodle/public/local/dimensions add \
  classes/calculator.php tests/calculator_filter_test.php
git -C /Volumes/N1TB/dev/github/moodle/public/local/dimensions commit -m "feat(filter): 'enrolledorself' keeps enrolled + self-enrolable courses"
```

---

## Task 3: Add the field-option sync helper

An idempotent helper that appends the new option to an already-provisioned select field's `configdata`. Reads `configdata` fresh from the DB by field id (so a stale cached controller cannot hide the real state), area-scoped to avoid the duplicate-shortname `dml_multiple_records` trap.

**Files:**
- Modify: `classes/helper.php` (add a public static method; a good home is next to `get_enrollmentfilter_field`, ~line 645)
- Test (create): `tests/sync_enrollmentfilter_option_test.php`

- [ ] **Step 1: Write the failing test**

Create `tests/sync_enrollmentfilter_option_test.php`:

```php
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

namespace local_dimensions;

/**
 * Tests for helper::sync_enrollmentfilter_option.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\helper::sync_enrollmentfilter_option
 */
final class sync_enrollmentfilter_option_test extends \advanced_testcase {
    /**
     * Sync appends the fifth option to a pre-upgrade (four-option) field, idempotently.
     *
     * @return void
     */
    public function test_sync_appends_missing_option_idempotently(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_LP);

        $field = helper::find_field_by_shortname(constants::CFIELD_ENROLLMENTFILTER, helper::AREA_LP);
        $this->assertNotNull($field);
        $fieldid = (int) $field->get('id');

        // The freshly provisioned field already carries all five options.
        $full = json_decode($DB->get_field('customfield_field', 'configdata', ['id' => $fieldid]), true);
        $lines = explode("\n", $full['options']);
        $this->assertCount(5, $lines);
        $label = (string) new \lang_string('enrollmentfilter_enrolledorself', 'local_dimensions');
        $this->assertSame($label, end($lines));

        // Simulate a site provisioned before the option existed: strip the fifth line.
        $four = $full;
        array_pop($lines);
        $four['options'] = implode("\n", $lines);
        $DB->set_field('customfield_field', 'configdata', json_encode($four), ['id' => $fieldid]);

        // Sync appends the fifth option back.
        helper::sync_enrollmentfilter_option(helper::AREA_LP);
        $after = json_decode($DB->get_field('customfield_field', 'configdata', ['id' => $fieldid]), true);
        $afterlines = explode("\n", $after['options']);
        $this->assertCount(5, $afterlines);
        $this->assertSame($label, end($afterlines));

        // Idempotent: a second run changes nothing.
        helper::sync_enrollmentfilter_option(helper::AREA_LP);
        $again = json_decode($DB->get_field('customfield_field', 'configdata', ['id' => $fieldid]), true);
        $this->assertSame($after['options'], $again['options']);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit public/local/dimensions/tests/sync_enrollmentfilter_option_test.php`
Expected: FAIL — `Call to undefined method ...::sync_enrollmentfilter_option()`.

- [ ] **Step 3: Implement the helper in `classes/helper.php`**

Add after `get_enrollmentfilter_field()` (after line 645):

```php
    /**
     * Append the "enrolled and self-enrolable" option to an already-provisioned
     * enrollmentfilter select field, if missing.
     *
     * The provisioning path short-circuits on an existing field and never re-syncs its
     * option list, so sites installed before the option existed keep a four-option select.
     * This appends the fifth option (index 5), leaving the first four indices — and therefore
     * every stored override — untouched. Idempotent: a re-run with the option already present
     * is a no-op. Reads configdata fresh from the DB by field id so a stale cached controller
     * cannot mask the real state; quietly returns when the field was never provisioned.
     *
     * @param string $area One of self::AREA_LP or self::AREA_COMPETENCY.
     * @return void
     */
    public static function sync_enrollmentfilter_option(string $area): void {
        global $DB;

        $field = self::find_field_by_shortname(constants::CFIELD_ENROLLMENTFILTER, $area);
        if (!$field) {
            return;
        }
        $fieldid = (int) $field->get('id');

        $configjson = $DB->get_field('customfield_field', 'configdata', ['id' => $fieldid]);
        $config = json_decode((string) $configjson, true);
        if (!is_array($config) || !isset($config['options'])) {
            return;
        }

        $lines = explode("\n", (string) $config['options']);
        $label = (string) new \lang_string('enrollmentfilter_enrolledorself', 'local_dimensions');
        if (in_array($label, $lines, true)) {
            return;
        }

        $lines[] = $label;
        $config['options'] = implode("\n", $lines);
        $DB->set_field('customfield_field', 'configdata', json_encode($config), ['id' => $fieldid]);
        self::get_handler($area)->reset_configuration_cache();
    }
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit public/local/dimensions/tests/sync_enrollmentfilter_option_test.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git -C /Volumes/N1TB/dev/github/moodle/public/local/dimensions add \
  classes/helper.php tests/sync_enrollmentfilter_option_test.php
git -C /Volumes/N1TB/dev/github/moodle/public/local/dimensions commit -m "feat(filter): helper to sync the new option into existing select fields"
```

---

## Task 4: Wire the upgrade step and bump the version

The upgrade step calls the Task 3 helper for both areas; the version bump makes it run. No new unit test (the helper is covered in Task 3); verified by CI's `savepoints`/`validate` checks and the user's runtime upgrade.

**Files:**
- Modify: `version.php` (line 28)
- Modify: `db/upgrade.php` (insert a block just before the `// Catch-all:` comment near the tail)

- [ ] **Step 1: Bump the version**

In `version.php`, change line 28:

```php
$plugin->version = 2026071800;
```

(from `2026071306`.) Leave `requires` and `supported` unchanged — `.github/workflows/ci.yml` is not affected.

- [ ] **Step 2: Add the upgrade block**

In `db/upgrade.php`, insert immediately **before** the `// Catch-all: re-ensure every customfield exists ...` comment block:

```php
    if ($oldversion < 2026071800) {
        // Append the new "enrolled and self-enrolable" option to the existing
        // enrollmentfilter select fields (lp + competency). The provisioning
        // catch-all below short-circuits on the existing field and never re-syncs
        // its option list, so this reconcile is required on upgraded sites.
        \local_dimensions\helper::sync_enrollmentfilter_option(\local_dimensions\helper::AREA_LP);
        \local_dimensions\helper::sync_enrollmentfilter_option(\local_dimensions\helper::AREA_COMPETENCY);

        upgrade_plugin_savepoint(true, 2026071800, 'local', 'dimensions');
    }

```

- [ ] **Step 3: Verify the savepoint matches the version**

Run: `grep -n "2026071800" /Volumes/N1TB/dev/github/moodle/public/local/dimensions/version.php /Volumes/N1TB/dev/github/moodle/public/local/dimensions/db/upgrade.php`
Expected: `version.php` line 28 and the `upgrade_plugin_savepoint(...)` line both show `2026071800`.

- [ ] **Step 4: Commit**

```bash
git -C /Volumes/N1TB/dev/github/moodle/public/local/dimensions add version.php db/upgrade.php
git -C /Volumes/N1TB/dev/github/moodle/public/local/dimensions commit -m "feat(filter): upgrade step + version bump to sync the new option into existing fields"
```

---

## Task 5: Extend cascade and web-service coverage

Regression/coverage tests for behaviour already implemented in Tasks 1-2 — they should pass on first run. Closes the audit gaps (cascade resolves the new value; the WS filters end-to-end for the new mode).

**Files:**
- Modify: `tests/helper_cascade_test.php` (inside `test_enrollmentfilter_cascade`, after the "Competency = active" block near line 88)
- Modify: `tests/external/get_competency_courses_test.php` (add a test method)

- [ ] **Step 1: Add the cascade assertion**

In `tests/helper_cascade_test.php`, append inside `test_enrollmentfilter_cascade()` after the existing "Competency = active" assertion block (after line ~90):

```php
        // Competency = enrolledorself -> resolves to the new aggregate value.
        $cdata2 = (object) ['id' => $compid];
        $cdata2->{'customfield_' . constants::CFIELD_ENROLLMENTFILTER} =
            array_search(constants::ENROLLMENTFILTER_ENROLLEDORSELF, $efkeys, true) + 1;
        competency_handler::create()->instance_form_save($cdata2, true);
        $this->assertSame(
            constants::ENROLLMENTFILTER_ENROLLEDORSELF,
            helper::resolve_enrollmentfilter_for_view($compid, $templateid)
        );
```

- [ ] **Step 2: Run the cascade test to verify it passes**

Run: `vendor/bin/phpunit public/local/dimensions/tests/helper_cascade_test.php`
Expected: PASS.

- [ ] **Step 3: Add the web-service end-to-end test**

In `tests/external/get_competency_courses_test.php`, add this method inside the class (after `test_execute_applies_plan_template_enrollmentfilter`):

```php
    /**
     * enrolledorself returns enrolled AND self-enrolable linked courses, and drops the rest.
     *
     * @return void
     */
    public function test_execute_enrolledorself_includes_self_enrolable(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        helper::ensure_custom_fields_exist(helper::AREA_LP);
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);

        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework(['visible' => 1]);
        $competency = $ccg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $competencyid = (int) $competency->get('id');

        $template = $ccg->create_template();
        $templateid = (int) $template->get('id');
        $ccg->create_template_competency([
            'templateid' => $templateid,
            'competencyid' => $competencyid,
        ]);

        $user = $this->getDataGenerator()->create_user();
        $plan = $ccg->create_plan([
            'userid' => $user->id,
            'templateid' => $templateid,
            'status' => \core_competency\plan::STATUS_ACTIVE,
        ]);
        $planid = (int) $plan->get('id');

        $enrolledcourse = $this->getDataGenerator()->create_course();
        $selfcourse = $this->getDataGenerator()->create_course();
        $hiddencourse = $this->getDataGenerator()->create_course();
        \core_competency\api::add_competency_to_course((int) $enrolledcourse->id, $competencyid);
        \core_competency\api::add_competency_to_course((int) $selfcourse->id, $competencyid);
        \core_competency\api::add_competency_to_course((int) $hiddencourse->id, $competencyid);

        $this->getDataGenerator()->enrol_user((int) $user->id, (int) $enrolledcourse->id, 'student');

        // Enable self-enrolment on the second course only.
        $self = enrol_get_plugin('self');
        $selfinstance = $DB->get_record('enrol', ['courseid' => $selfcourse->id, 'enrol' => 'self'], '*', MUST_EXIST);
        $self->update_status($selfinstance, ENROL_INSTANCE_ENABLED);

        // Set the template's enrollmentfilter to "enrolledorself" via the plugin's own customfield path.
        $keys = array_keys(constants::enrollmentfilter_options());
        $data = (object) ['id' => $templateid];
        $data->{'customfield_' . constants::CFIELD_ENROLLMENTFILTER} =
            array_search(constants::ENROLLMENTFILTER_ENROLLEDORSELF, $keys, true) + 1;
        lp_handler::create()->instance_form_save($data, true);

        $this->setUser($user);
        $result = get_competency_courses::execute($competencyid, $planid);
        $resultids = array_map('intval', array_column($result, 'id'));

        $this->assertContains((int) $enrolledcourse->id, $resultids);
        $this->assertContains((int) $selfcourse->id, $resultids);
        $this->assertNotContains((int) $hiddencourse->id, $resultids);
    }
```

- [ ] **Step 4: Run the web-service test to verify it passes**

Run: `vendor/bin/phpunit public/local/dimensions/tests/external/get_competency_courses_test.php`
Expected: PASS (both methods).

- [ ] **Step 5: Commit**

```bash
git -C /Volumes/N1TB/dev/github/moodle/public/local/dimensions add \
  tests/helper_cascade_test.php tests/external/get_competency_courses_test.php
git -C /Volumes/N1TB/dev/github/moodle/public/local/dimensions commit -m "test(filter): cascade + end-to-end coverage for 'enrolledorself'"
```

---

## Task 6: Static pre-push verification

phpcs/phpdoc/PHPUnit/Behat have no local runner in this checkout — eyeball the two rules that slip past review, plus the lang ordering and version match. No JS/CSS changed, so no grunt/eslint/stylelint.

**Files:** none (verification only)

- [ ] **Step 1: Line length — soft max 132 on the changed PHP**

Run:
```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions
awk 'length($0)>132{print FILENAME":"NR" ("length($0)")"}' \
  classes/constants.php classes/calculator.php classes/helper.php db/upgrade.php \
  tests/enrollmentfilter_options_test.php tests/calculator_filter_test.php \
  tests/sync_enrollmentfilter_option_test.php tests/helper_cascade_test.php \
  tests/external/get_competency_courses_test.php
```
Expected: no output. If any line prints, wrap it (PSR-2 multi-line style).

- [ ] **Step 2: Inline comment casing — capital first letter**

Run:
```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions
grep -nE '^\s*// [a-z]' classes/constants.php classes/calculator.php classes/helper.php db/upgrade.php
```
Expected: no output (ignore any GPL header hits). Any real hit must start with a capital and end with punctuation, or move to a `/* ... */` block.

- [ ] **Step 3: No stray development-leftover / merge-conflict tokens**

Run:
```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions
git diff --staged --name-only | xargs grep -nE '\b([T]ODO|FIXME|XXX)\b|@todo|<<<<<<<|>>>>>>>' 2>/dev/null || echo "clean"
```
Expected: `clean`.

- [ ] **Step 4: Lang files stay alphabetical and in sync**

Run:
```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions
for f in lang/en/local_dimensions.php lang/pt_br/local_dimensions.php; do
  echo "== $f =="; grep -n "enrollmentfilter" "$f"
done
```
Expected: in **both** files `enrollmentfilter_enrolledorself` sits between `enrollmentfilter_enrolled` and `enrollmentfilter_inherit`, and both files define the same set of `enrollmentfilter*` keys.

- [ ] **Step 5: Version and savepoint agree**

Run:
```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions
grep -n '2026071800' version.php db/upgrade.php
```
Expected: `version.php` `$plugin->version` and the `upgrade_plugin_savepoint` both `2026071800`.

- [ ] **Step 6: Full plugin test run (on the user's server)**

Run: `vendor/bin/phpunit --testsuite local_dimensions_testsuite` (or run each new/edited test file listed in Tasks 1-5).
Expected: all green. This is the point to catch any phpunit `--fail-on-warning` issue before pushing.

---

## Post-implementation follow-ups (not code tasks)
- Runtime check on the user's site: the new option appears in the global setting, in the per-plan modal, and in the per-competency modal; selecting it on a plan shows enrolled + self-enrolable courses; an upgraded site gains the option after the upgrade runs.
- Update the `dimensions-version-freeze` memory: freeze moves from `2026071306` to `2026071800`.
- Test-install zip (only when asked): `dimensions-2026071800-<shortSHA>.zip` per the repo convention.
- Push only on explicit command.

## Self-review notes (author check — done)
- **Spec coverage:** constant + option order (T1), filter branch + self-enrol reuse (T2), upgrade sync helper (T3), upgrade step + bump (T4), cascade + WS tests (T5), CI eyeball (T6). Third-party-view note in `enrollmentfilter_desc` (T1 steps 5-6). All spec sections mapped.
- **Type/name consistency:** `sync_enrollmentfilter_option`, `user_enrolled_or_self_enrolable`, `current_user_can_self_enrol`, `ENROLLMENTFILTER_ENROLLEDORSELF` used identically across tasks and tests.
- **No placeholders:** every code step carries complete code; every run step states the exact command and expected result.
