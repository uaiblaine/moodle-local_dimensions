# Plan overview accordion refinement — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restructure the expanded detail of each plan-overview accordion item into four ordered tabs — Related content, Description, Progress, Rules — with course access states, a proper assessment card, an aligned evidence journey and a CSS-only tooltip.

**Architecture:** Server side, one web service grows four return keys and one lean `calculator` helper is added; the other web service gains a setting gate. Client side, `accordion.js` keeps its existing tab machinery and changes what is pushed into it: the linked-course renderer moves inside the tab wrapper, two panes merge into one, and three section renderers are restyled. No new AMD module, no new DB table, no new web-service function.

**Tech Stack:** Moodle 4.5–5.2 local plugin · plain AMD (`define`, not ESM) · Mustache is not involved (this detail is built client-side) · PHPUnit · `styles.css` (no SCSS build for this file).

**Spec:** `docs/superpowers/specs/2026-07-24-plan-accordion-refinement-design.md`
**Kit:** `docs/learner-kit/screens/ovw-detail-{tabs,progress,courses,evidence}.html`, `docs/design-kit/tooltip.html`

## Global Constraints

- **This checkout cannot run PHPUnit or Behat.** `public/config.php` exists but declares no `phpunit_prefix` / `phpunit_dataroot`, and there is no `phpunit.xml`. Every "run the test" step below therefore means **run it in CI on your next push**. Write the test first anyway — the red/green order still matters for the design of the test, and Task 1 is deliberately the smallest PHP task so its fixture pattern is proven in CI before Tasks 2–3 build on it.
- **Runnable locally, and required before every push:**
  ```bash
  cd /Volumes/N1TB/dev/github/moodle && npx eslint --max-warnings 0 public/local/dimensions/amd/src
  ```
  ```bash
  cd /Volumes/N1TB/dev/github/moodle && npx stylelint --config .stylelintrc public/local/dimensions/styles.css
  ```
  CI runs `grunt --max-lint-warnings 0`, so **every warning fails the build**. A plain local `grunt amd` prints ESLint warnings and still exits 0 — do not rely on it.
- **Every commit that touches `amd/src` must ship the rebuilt `amd/build`** in the same commit:
  ```bash
  cd /Volumes/N1TB/dev/github/moodle && npx grunt amd --root=public/local/dimensions
  ```
- **CSS rules that are hard errors under core's stylelint:** no `!important` (anywhere, including `@keyframes`); no `clamp()` / `min()` / `max()` in **any** length-valued property (`width`, `max-width`, `font-size`, `padding`, `margin`, `gap`, `flex-basis` included) — `calc()` and grid `minmax()` are fine; no transition or animation under `100ms`.
- **PHP style traps with no local runner:** hard line limit 180, soft limit **132** (the soft one fails `phpdoc --max-warnings 0`); inline `//` comments start with a capital and end with punctuation — lowercase or multi-line commentary belongs in a `/* … */` block. Before pushing, grep the changed PHP:
  ```bash
  awk 'length($0)>132{print FILENAME":"NR" ("length($0)")"}' <changed .php files>
  ```
  ```bash
  grep -nE '^\s*// [a-z]' <changed .php files>
  ```
- **Never write a bare to-do or merge-conflict marker in any file, docs included** — CI's development-leftover checker scans everything.
- **Lang files** `lang/en/local_dimensions.php` and `lang/pt_br/local_dimensions.php` are kept in sync and **alphabetically sorted**; the `validate` CI step enforces the ordering.
- **Variables are lower-case only** (`$courseid`, never `$courseId`). Classes and methods are `lower_snake_case`.
- **Do not push unless explicitly asked.** Commit locally; the user drives pushes.

---

### Task 1: The `showscaledescription` setting, the five new lang strings, and the version bump

The gate lives **only** in the web service. The client already renders no button when
the description is empty (`renderStatusSection` guards on `if (scaleDescription)` and
`initScaleAbout` returns early on a falsy one), so returning `''` is the whole feature —
no new key in the accordion's display settings, no client change.

**Files:**
- Modify: `settings.php` (after the `showrelatedlink` block, before `showevidence`)
- Modify: `lang/en/local_dimensions.php`
- Modify: `lang/pt_br/local_dimensions.php`
- Modify: `classes/external/get_user_competency_summary_in_plan.php:115`
- Modify: `version.php:28`
- Test: `tests/external/summary_scale_description_test.php` (create)

**Interfaces:**
- Consumes: nothing.
- Produces: config key `local_dimensions/showscaledescription` (checkbox, default `1`); strings `evidence_journey`, `progress_tab`, `showscaledescription`, `showscaledescription_desc`, `view_detailed_progress`. Tasks 4, 6 and 7 read those strings.

- [ ] **Step 1: Write the failing test**

Create `tests/external/summary_scale_description_test.php`:

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

namespace local_dimensions\external;

/**
 * Tests the admin gate on the rating scale's description.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\external\get_user_competency_summary_in_plan
 */
final class summary_scale_description_test extends \advanced_testcase {
    /**
     * With the setting on, the payload carries the scale's own description.
     *
     * @return void
     */
    public function test_execute_returns_the_scale_description_when_enabled(): void {
        $this->resetAfterTest();
        set_config('showscaledescription', 1, 'local_dimensions');
        [$competencyid, $planid, $user] = $this->set_up_plan_with_described_scale();

        $this->setUser($user);
        $payload = json_decode(get_user_competency_summary_in_plan::execute($competencyid, $planid));

        $this->assertStringContainsString(
            'Four-level skills scale',
            $payload->usercompetencysummary->competency->scaledescription
        );
    }

    /**
     * With the setting off, the field is empty and the client renders no button.
     *
     * @return void
     */
    public function test_execute_suppresses_the_scale_description_when_disabled(): void {
        $this->resetAfterTest();
        set_config('showscaledescription', 0, 'local_dimensions');
        [$competencyid, $planid, $user] = $this->set_up_plan_with_described_scale();

        $this->setUser($user);
        $payload = json_decode(get_user_competency_summary_in_plan::execute($competencyid, $planid));

        $this->assertSame('', $payload->usercompetencysummary->competency->scaledescription);
    }

    /**
     * A plan holding one competency whose framework scale carries a description.
     *
     * @return array The competency id, the plan id and the plan's owner.
     */
    private function set_up_plan_with_described_scale(): array {
        global $DB;

        $this->setAdminUser();
        $scale = $this->getDataGenerator()->create_scale(['scale' => 'Emerging,Developing,Competent']);
        $DB->set_field('scale', 'description', '<p>Four-level skills scale.</p>', ['id' => $scale->id]);

        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework([
            'visible' => 1,
            'scaleid' => $scale->id,
            /* core_competency\competency_framework::validate_scaleconfiguration() shifts off
               the leading scaleid entry and then requires at least one remaining entry with a
               truthy scaledefault AND at least one with a truthy proficient. Miss either and
               create_framework() throws before the test reaches an assertion. */
            'scaleconfiguration' => json_encode([
                ['scaleid' => (int) $scale->id],
                ['id' => 2, 'scaledefault' => 1, 'proficient' => 1],
                ['id' => 3, 'proficient' => 1],
            ]),
        ]);
        $competency = $ccg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $competencyid = (int) $competency->get('id');

        $template = $ccg->create_template();
        $ccg->create_template_competency([
            'templateid' => (int) $template->get('id'),
            'competencyid' => $competencyid,
        ]);

        $user = $this->getDataGenerator()->create_user();
        $plan = $ccg->create_plan([
            'userid' => $user->id,
            'templateid' => (int) $template->get('id'),
            'status' => \core_competency\plan::STATUS_ACTIVE,
        ]);

        return [$competencyid, (int) $plan->get('id'), $user];
    }
}
```

Add a third test alongside those two, for the branch `resolve_show_scale_description()`
exists to serve: a setting that was **never written** must behave as on, so an install
upgrading into this release keeps the link it has today. It calls
`unset_config('showscaledescription', 'local_dimensions')` and asserts the description
still comes back populated — it would fail if the defaulting were removed.

- [ ] **Step 2: Run the test to verify it fails**

Not runnable in this checkout (see Global Constraints). In CI, expect
`test_execute_suppresses_the_scale_description_when_disabled` to **FAIL** —
the description comes back populated because nothing reads the setting yet.

- [ ] **Step 3: Add the setting**

In `settings.php`, immediately after the `showrelatedlink` checkbox block and before the
`showevidence` one:

```php
    // The "About this scale" link in the assessment card. Off means the web service
    // does not even read the scale, so the link cannot render.
    $settings->add(new admin_setting_configcheckbox(
        'local_dimensions/showscaledescription',
        get_string('showscaledescription', 'local_dimensions'),
        get_string('showscaledescription_desc', 'local_dimensions'),
        1
    ));
```

- [ ] **Step 4: Add the five strings to both lang files, in their alphabetical slots**

`lang/en/local_dimensions.php` — `evidence_journey` goes between `evidence_grade` and
`evidence_label`; `progress_tab` after `proficient`; `showscaledescription` and its
`_desc` after `showrelatedlink`; `view_detailed_progress` between `view_courses` and
`view_grid`:

```php
$string['evidence_journey'] = 'On the journey';
$string['progress_tab'] = 'Progress';
$string['showscaledescription'] = 'Show the scale description';
$string['showscaledescription_desc'] = 'Show an "About this scale" link in the assessment card, opening the rating scale\'s own description. The link appears only when the scale actually has a description.';
$string['view_detailed_progress'] = 'View detailed progress';
```

`lang/pt_br/local_dimensions.php`, same slots:

```php
$string['evidence_journey'] = 'Percurso';
$string['progress_tab'] = 'Progresso';
$string['showscaledescription'] = 'Mostrar a descrição da escala';
$string['showscaledescription_desc'] = 'Mostra um link "Sobre esta escala" no cartão de avaliação, que abre a descrição da própria escala de avaliação. O link só aparece quando a escala tem descrição.';
$string['view_detailed_progress'] = 'Ver progresso detalhado';
```

- [ ] **Step 5: Gate the web service**

In `classes/external/get_user_competency_summary_in_plan.php`, replace line 115:

```php
            $result->usercompetencysummary->competency->scaledescription = self::get_scale_description($competency);
```

with:

```php
            $result->usercompetencysummary->competency->scaledescription =
                self::resolve_show_scale_description()
                    ? self::get_scale_description($competency)
                    : '';
```

and add this method directly above `get_scale_description()`:

```php
    /**
     * Whether the "About this scale" link is enabled.
     *
     * A setting that has never been written reads as false, which on an install upgrading
     * into this release would silently remove a link it has today. Unset is therefore
     * treated as on, matching the checkbox's own default.
     *
     * @return bool
     */
    protected static function resolve_show_scale_description(): bool {
        $value = get_config('local_dimensions', 'showscaledescription');

        return $value === false || $value === '' ? true : (bool) $value;
    }
```

- [ ] **Step 6: Bump the version**

In `version.php`, line 28:

```php
$plugin->version = 2026072400;
```

No `db/upgrade.php` step: there is no schema change and no new web-service function.
The bump exists so the settings default is applied and the JS cache revision moves.

- [ ] **Step 7: Check the PHP style traps**

```bash
awk 'length($0)>132{print FILENAME":"NR" ("length($0)")"}' settings.php classes/external/get_user_competency_summary_in_plan.php version.php tests/external/summary_scale_description_test.php
```
Expected: only the two long `$string[...]` lines flagged in the lang files if you ran it on those — lang strings are exempt in practice, but keep PHP code lines under 132.

```bash
grep -nE '^\s*// [a-z]' settings.php classes/external/get_user_competency_summary_in_plan.php
```
Expected: no output beyond the GPL header block.

- [ ] **Step 8: Commit**

```bash
git add settings.php lang/en/local_dimensions.php lang/pt_br/local_dimensions.php classes/external/get_user_competency_summary_in_plan.php version.php tests/external/summary_scale_description_test.php
git commit -m "feat(learner): gate the scale description behind an admin setting"
```

---

### Task 2: `calculator::resolve_single_activity()`

**Files:**
- Modify: `classes/calculator.php` (add a public static method after `get_course_section_progress`'s private helper `get_section_cms_recursive`, i.e. before `is_locked`)
- Test: `tests/calculator_single_activity_test.php` (create)

**Interfaces:**
- Consumes: nothing.
- Produces: `\local_dimensions\calculator::resolve_single_activity(int $courseid, int $userid): ?array` returning `['name' => string, 'url' => string, 'completed' => bool]` or `null`. Task 3 calls it.

- [ ] **Step 1: Write the failing test**

Create `tests/calculator_single_activity_test.php`:

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
 * Tests the single-trackable-activity resolver used by the plan's course cards.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\calculator::resolve_single_activity
 */
final class calculator_single_activity_test extends \advanced_testcase {
    /**
     * Exactly one tracked activity: the card gets its name and state instead of a bar.
     *
     * @return void
     */
    public function test_one_tracked_activity_is_returned(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Weekly reflection',
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $this->setUser($user);
        $activity = calculator::resolve_single_activity((int) $course->id, (int) $user->id);

        $this->assertIsArray($activity);
        $this->assertSame('Weekly reflection', $activity['name']);
        $this->assertFalse($activity['completed']);
        $this->assertNotSame('', $activity['url']);
    }

    /**
     * Two tracked activities: there is a sequence, so the card keeps its progress bar.
     *
     * @return void
     */
    public function test_two_tracked_activities_return_null(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        foreach (['First', 'Second'] as $name) {
            $this->getDataGenerator()->create_module('page', [
                'course' => $course->id,
                'name' => $name,
                'completion' => COMPLETION_TRACKING_MANUAL,
            ]);
        }

        $this->setUser($user);

        $this->assertNull(calculator::resolve_single_activity((int) $course->id, (int) $user->id));
    }

    /**
     * A lone module with completion switched off is not trackable, so there is nothing to show.
     *
     * @return void
     */
    public function test_untracked_activity_returns_null(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Untracked',
            'completion' => COMPLETION_TRACKING_NONE,
        ]);

        $this->setUser($user);

        $this->assertNull(calculator::resolve_single_activity((int) $course->id, (int) $user->id));
    }

    /**
     * Course-level completion off: nothing is trackable at all.
     *
     * @return void
     */
    public function test_completion_disabled_returns_null(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 0]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Anything',
        ]);

        $this->setUser($user);

        $this->assertNull(calculator::resolve_single_activity((int) $course->id, (int) $user->id));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Not runnable here. In CI, expect all four to **FAIL** with
`Call to undefined method local_dimensions\calculator::resolve_single_activity()`.

- [ ] **Step 3: Write the implementation**

In `classes/calculator.php`, insert this method between `get_section_cms_recursive()` and
`is_locked()`:

```php
    /**
     * The course's only trackable activity, when it has exactly one.
     *
     * get_course_section_progress() answers a superset of this - it walks the subsection
     * hierarchy and computes a percentage per section - which is far more work than the
     * plan's card needs. Here the count is the whole question: a course with one trackable
     * activity has a progress bar that can only ever read 0% or 100%, so the card shows the
     * activity instead.
     *
     * "Trackable" matches the tracker: completion switched on for the module, the module
     * visible to the user, and the subsection container itself never counted.
     *
     * @param int $courseid The course id.
     * @param int $userid The user whose completion is read.
     * @return array|null Keys name, url and completed; null unless exactly one module qualifies.
     */
    public static function resolve_single_activity(int $courseid, int $userid): ?array {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        $course = get_course($courseid);
        $completion = new \completion_info($course);
        if (!$completion->is_enabled()) {
            return null;
        }

        $modinfo = get_fast_modinfo($course, $userid);
        $found = null;
        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->modname === 'subsection' || $cm->deletioninprogress || !$cm->uservisible) {
                continue;
            }
            if ($completion->is_enabled($cm) == COMPLETION_TRACKING_NONE) {
                continue;
            }
            if ($found !== null) {
                // A second one: there is a sequence to draw, so the bar stays.
                return null;
            }
            $found = $cm;
        }

        if ($found === null) {
            return null;
        }

        $data = $completion->get_data($found, true, $userid);

        return [
            'name' => $found->get_formatted_name(),
            'url' => $found->url ? $found->url->out(false) : '',
            'completed' => $data->completionstate == COMPLETION_COMPLETE
                || $data->completionstate == COMPLETION_COMPLETE_PASS,
        ];
    }
```

- [ ] **Step 4: Run the test to verify it passes**

Not runnable here — verify in CI. Expected: all four PASS.

- [ ] **Step 5: Check the PHP style traps**

```bash
awk 'length($0)>132{print FILENAME":"NR" ("length($0)")"}' classes/calculator.php tests/calculator_single_activity_test.php
```
Expected: no output.

- [ ] **Step 6: Commit**

```bash
git add classes/calculator.php tests/calculator_single_activity_test.php
git commit -m "feat(learner): resolve a course's single trackable activity"
```

---

### Task 3: Access state and single activity in the courses web service

**Files:**
- Modify: `classes/external/get_competency_courses.php` — the per-course loop in `execute()` (currently lines 114–158) and `execute_returns()` (287–316)
- Test: `tests/external/get_competency_courses_test.php` (append to the existing class)

**Interfaces:**
- Consumes: `calculator::resolve_single_activity()` (Task 2); the existing
  `calculator::current_user_can_self_enrol()`, `get_availability_date()`,
  `get_enrolment_start_date()`.
- Produces: four new keys per course in the `local_dimensions_get_competency_courses`
  payload — `access` (`'open'` | `'enrol'` | `'locked'`), `lockdate` (int, 0 when not
  locked), `isenrolstart` (bool), and `activity` (`{name, url, completed}`, **absent**
  unless the course resolves to one trackable activity). Task 7 renders them.

- [ ] **Step 1: Write the failing tests**

Append these four methods to the existing
`local_dimensions\external\get_competency_courses_test` class. They reuse the class's own
`set_up_competency()` and `cleaned_result_for()` helpers, so the allowlist trap is covered:
`cleaned_result_for()` runs the payload through `clean_returnvalue`, which silently strips
any key missing from `execute_returns()`.

```php
    /**
     * An actively enrolled viewer gets an open card.
     *
     * @return void
     */
    public function test_execute_reports_open_access_for_an_enrolled_user(): void {
        $this->resetAfterTest();
        $competencyid = $this->set_up_competency();
        $course = $this->getDataGenerator()->create_course();
        \core_competency\api::add_competency_to_course($course->id, $competencyid);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $rows = $this->cleaned_result_for($competencyid, $user);

        $this->assertSame('open', $rows[$course->id]['access']);
        $this->assertSame(0, $rows[$course->id]['lockdate']);
    }

    /**
     * Not enrolled but able to self-enrol: the lock becomes an invitation.
     *
     * @return void
     */
    public function test_execute_reports_enrol_access_when_self_enrolment_is_open(): void {
        $this->resetAfterTest();
        $competencyid = $this->set_up_competency();
        $course = $this->getDataGenerator()->create_course();
        \core_competency\api::add_competency_to_course($course->id, $competencyid);

        $plugin = enrol_get_plugin('self');
        $instance = null;
        foreach (enrol_get_instances($course->id, false) as $candidate) {
            if ($candidate->enrol === 'self') {
                $instance = $candidate;
            }
        }
        $this->assertNotNull($instance, 'The self enrolment instance should exist by default.');
        $plugin->update_status($instance, ENROL_INSTANCE_ENABLED);

        $user = $this->getDataGenerator()->create_user();
        $rows = $this->cleaned_result_for($competencyid, $user);

        $this->assertSame('enrol', $rows[$course->id]['access']);
    }

    /**
     * Neither enrolled nor able to join: locked, and the card gets a date to show.
     *
     * @return void
     */
    public function test_execute_reports_locked_access_with_the_course_start_date(): void {
        $this->resetAfterTest();
        $competencyid = $this->set_up_competency();
        $startdate = time() + WEEKSECS;
        $course = $this->getDataGenerator()->create_course(['startdate' => $startdate]);
        \core_competency\api::add_competency_to_course($course->id, $competencyid);

        $user = $this->getDataGenerator()->create_user();
        $rows = $this->cleaned_result_for($competencyid, $user);

        $this->assertSame('locked', $rows[$course->id]['access']);
        $this->assertSame($startdate, $rows[$course->id]['lockdate']);
        $this->assertFalse($rows[$course->id]['isenrolstart']);
    }

    /**
     * A course with one tracked activity carries it; one with two does not.
     *
     * @return void
     */
    public function test_execute_carries_the_single_trackable_activity(): void {
        $this->resetAfterTest();
        $competencyid = $this->set_up_competency();

        $single = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        \core_competency\api::add_competency_to_course($single->id, $competencyid);
        $this->getDataGenerator()->create_module('page', [
            'course' => $single->id,
            'name' => 'Weekly reflection',
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $many = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        \core_competency\api::add_competency_to_course($many->id, $competencyid);
        foreach (['First', 'Second'] as $name) {
            $this->getDataGenerator()->create_module('page', [
                'course' => $many->id,
                'name' => $name,
                'completion' => COMPLETION_TRACKING_MANUAL,
            ]);
        }

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $single->id, 'student');
        $this->getDataGenerator()->enrol_user($user->id, $many->id, 'student');

        $rows = $this->cleaned_result_for($competencyid, $user);

        $this->assertSame('Weekly reflection', $rows[$single->id]['activity']['name']);
        $this->assertArrayNotHasKey('activity', $rows[$many->id]);
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Not runnable here. In CI, expect all four to **FAIL** — `access`, `lockdate`,
`isenrolstart` and `activity` do not exist in the payload, so the array accesses raise
undefined-key errors.

- [ ] **Step 3: Extend the per-course loop**

In `classes/external/get_competency_courses.php`, replace the body of the
`foreach ($courses as $course)` loop (currently lines 114–158) with this. Two things
change beyond the new keys: `get_course()` is hoisted out of the two calls that repeat it
today, and `$USER` is already in scope from the method's `global` line.

```php
        foreach ($courses as $course) {
            $coursecontext = context_course::instance($course->id);
            $fullcourse = get_course($course->id);

            // Get course image URL.
            $courseimage = '';
            $courseobj = new \core_course_list_element($course);
            foreach ($courseobj->get_course_overviewfiles() as $file) {
                $isimage = $file->is_valid_image();
                if ($isimage) {
                    $courseimage = \moodle_url::make_pluginfile_url(
                        $file->get_contextid(),
                        $file->get_component(),
                        $file->get_filearea(),
                        null,
                        $file->get_filepath(),
                        $file->get_filename()
                    )->out(false);
                    break;
                }
            }

            // Get course progress for the current user.
            $progress = 0;
            $completion = new \completion_info($fullcourse);
            if ($completion->is_enabled()) {
                $progressvalue = \core_completion\progress::get_course_progress_percentage(
                    $fullcourse,
                    $USER->id
                );
                if ($progressvalue !== null) {
                    $progress = (int) round($progressvalue);
                }
            }

            /* What the viewer can do with this course. calculator::is_locked() is deliberately
               not used: it also reports true for anyone enrolled without the student role, which
               would lock every card for a member of staff reviewing someone's plan. The question
               here is the one the card's own link is about to answer - can this viewer open it. */
            $access = self::ACCESS_OPEN;
            $lockdate = 0;
            $isenrolstart = false;
            if (!is_enrolled($coursecontext, $USER->id, '', true)) {
                $access = \local_dimensions\calculator::current_user_can_self_enrol((int) $course->id)
                    ? self::ACCESS_ENROL
                    : self::ACCESS_LOCKED;
            }
            if ($access === self::ACCESS_LOCKED) {
                $lockdate = (int) \local_dimensions\calculator::get_availability_date($fullcourse, $USER->id);
                $isenrolstart = \local_dimensions\calculator::get_enrolment_start_date(
                    $fullcourse,
                    $USER->id
                ) !== null;
            }

            $row = [
                'id' => (int) $course->id,
                'fullname' => format_string($course->fullname, true, ['context' => $coursecontext]),
                'shortname' => format_string($course->shortname, true, ['context' => $coursecontext]),
                'courseimage' => $courseimage,
                'progress' => $progress,
                'visible' => 1,
                'ruleoutcome' => (int) $course->ruleoutcome,
                'access' => $access,
                'lockdate' => $lockdate,
                'isenrolstart' => $isenrolstart,
                'activities' => $activitiesbycourse[(int) $course->id] ?? [],
            ];

            /* Only a course the viewer can open and that resolves to exactly one trackable
               activity carries this - naming an activity behind a lock helps nobody. The key is
               omitted rather than set to null: clean_returnvalue rejects a null where an
               optional structure is declared. */
            if ($access === self::ACCESS_OPEN) {
                $activity = \local_dimensions\calculator::resolve_single_activity(
                    (int) $course->id,
                    (int) $USER->id
                );
                if ($activity !== null) {
                    $row['activity'] = $activity;
                }
            }

            $result[] = $row;
        }
```

Add the three constants directly under the class declaration, above
`execute_parameters()`:

```php
    /** @var string The viewer is actively enrolled and can open the course. */
    private const ACCESS_OPEN = 'open';

    /** @var string The viewer is not enrolled but self-enrolment is open to them. */
    private const ACCESS_ENROL = 'enrol';

    /** @var string The viewer can neither open the course nor join it. */
    private const ACCESS_LOCKED = 'locked';
```

- [ ] **Step 4: Declare the new keys in `execute_returns()`**

`execute_returns()` is an **allowlist** — an undeclared key is silently stripped by
`clean_returnvalue`. In `classes/external/get_competency_courses.php`, add these four
entries to the `external_single_structure`, after `'ruleoutcome'` and before
`'activities'`:

```php
                'access' => new external_value(
                    PARAM_ALPHA,
                    'What the viewer can do with the course: open, enrol or locked'
                ),
                'lockdate' => new external_value(PARAM_INT, 'Availability timestamp when locked, 0 otherwise'),
                'isenrolstart' => new external_value(PARAM_BOOL, 'Whether the lock date is an enrolment start date'),
                'activity' => new external_single_structure(
                    [
                        'name' => new external_value(PARAM_RAW, 'Activity name'),
                        'url' => new external_value(PARAM_URL, 'Activity URL, empty when it has no view page'),
                        'completed' => new external_value(PARAM_BOOL, 'Whether the user completed the activity'),
                    ],
                    'The single trackable activity, present only when the course resolves to exactly one',
                    VALUE_OPTIONAL
                ),
```

- [ ] **Step 5: Update the class docblock**

The file docblock (lines 17–29) says each course "carries its rule outcome and the
competency's activity links inside it". Extend that sentence:

```php
 * This webservice runs its own query over competency_coursecomp and resolves
 * the enrolment-filter cascade (competency -> plan's template -> global
 * setting) to filter courses based on the user's enrollment status. Each
 * surviving course also carries its rule outcome, the competency's activity
 * links inside it, what the viewer can do with it (open, enrol or locked) and,
 * when the course resolves to exactly one trackable activity, that activity.
```

- [ ] **Step 6: Run the tests to verify they pass**

Not runnable here — verify in CI. Expected: all four new tests PASS and the seven
existing tests in the file still PASS (the hoisted `get_course()` and the new keys must
not disturb them).

- [ ] **Step 7: Check the PHP style traps**

```bash
awk 'length($0)>132{print FILENAME":"NR" ("length($0)")"}' classes/external/get_competency_courses.php tests/external/get_competency_courses_test.php
```
Expected: no output.

```bash
grep -nE '^\s*// [a-z]' classes/external/get_competency_courses.php
```
Expected: no output beyond the GPL header block.

- [ ] **Step 8: Commit**

```bash
git add classes/external/get_competency_courses.php tests/external/get_competency_courses_test.php
git commit -m "feat(learner): report course access and the single activity to the plan cards"
```

---

### Task 4: Tab set, order and the Progress merge

The tab machinery does not change — `tabs[0]` already renders active, `activateTab`
already calls `refreshScrollableControls(pane)` for exactly this case (a scroller that
starts life inside a hidden pane), and `[data-goto-rules]` already reaches the Rules tab
by clicking `[data-tab="rules"]`, which this task does not rename. What changes is what
`buildSummaryTabs` pushes and what `renderSummaryTabPanes` emits.

**Files:**
- Modify: `amd/src/accordion.js` — `renderCompetencySummary` (155–363), `getSummaryState`
  (372–409), `buildSummaryTabs` (418–435), `renderSummaryTabNavigation` (467–485),
  `renderSummaryTabPanes` (496–514); delete `renderStatusPane` (524–532) and
  `renderEvidencePane` (577–603)
- Modify: `styles.css` — add the tab count badge beside `.local-dimensions-tab-btn` (3298)
- Modify: `amd/build/accordion.min.js`, `amd/build/accordion.min.js.map` (generated)

**Interfaces:**
- Consumes: string `progress_tab` (Task 1).
- Produces: `renderCoursesPane(summaryState, tabs, strMap)` and
  `renderProgressPane(summaryState, tabs, strMap)`; `summaryState.hasCourses` (bool);
  tab objects gain an optional `count` (number). Tasks 5–7 render inside these panes.

- [ ] **Step 1: Add the new string to the fetch list and the map**

In `renderCompetencySummary`, add to the `Str.get_strings([...])` array — append it at the
**end** of the array so the existing positional indices are undisturbed:

```js
                {key: 'progress_tab', component: 'local_dimensions'}
```

and to `strMap`, after `activitiesCountOne: strings[74]`:

```js
                    progressTab: strings[75]
```

- [ ] **Step 2: Stop rendering the courses section outside the tab wrapper**

In `renderCompetencySummary`, delete these four lines (currently 323–326):

```js
                if (summaryState.visibleCourses.length > 0) {
                    html += renderCourseCardsScrollable(summaryState.visibleCourses, strMap);
                }
```

- [ ] **Step 3: Add `hasCourses` and tighten `hasPath` in `getSummaryState`**

Replace the `hasPath` line:

```js
            const hasPath = !!(comp && displaySettings.showpath);
```

with one that requires the path to actually exist — the footnote renderer returns an empty
string when both halves are empty, so today a root competency with `showpath` on can
produce an empty Description tab:

```js
            const hasPath = !!(comp && displaySettings.showpath && (
                competencyData?.framework?.shortname
                || (Array.isArray(competencyData?.compparents) && competencyData.compparents.length > 0)
            ));
```

Add above the `return`:

```js
            const hasCourses = visibleCourses.length > 0;
```

and add `hasCourses: hasCourses,` to the returned object, directly above `hasStatus`.

- [ ] **Step 4: Rewrite `buildSummaryTabs`**

Replace the whole function body. The `icon` field is dropped: the nav has never emitted it.

```js
        function buildSummaryTabs(summaryState, strMap) {
            const tabs = [];

            if (summaryState.hasCourses) {
                tabs.push({
                    id: 'courses',
                    label: strMap.relatedContent,
                    count: summaryState.visibleCourses.length
                });
            }
            if (summaryState.hasDesc || summaryState.hasTaxonomyCard || summaryState.hasPath || summaryState.hasRelated) {
                tabs.push({id: 'description', label: strMap.descriptionLabel});
            }
            if (summaryState.hasStatus || summaryState.hasEvidence) {
                tabs.push({id: 'progress', label: strMap.progressTab});
            }
            if (summaryState.hasRules) {
                tabs.push({id: 'rules', label: strMap.rulesTab});
            }

            return tabs;
        }
```

- [ ] **Step 5: Emit the count badge in the nav**

In `renderSummaryTabNavigation`, replace:

```js
                html += escapeHtml(tab.label);
                html += '</button>';
```

with:

```js
                html += escapeHtml(tab.label);
                if (tab.count) {
                    html += '<span class="local-dimensions-tab-count">' + tab.count + '</span>';
                }
                html += '</button>';
```

- [ ] **Step 6: Rewrite `renderSummaryTabPanes` and add the two new pane renderers**

Replace `renderSummaryTabPanes`:

```js
        function renderSummaryTabPanes(summaryState, tabs, strMap, planId) {
            let html = '<div class="local-dimensions-tabs-content">';

            if (summaryState.hasCourses) {
                html += renderCoursesPane(summaryState, tabs, strMap);
            }
            if (summaryState.hasDesc || summaryState.hasTaxonomyCard || summaryState.hasPath || summaryState.hasRelated) {
                html += renderDescriptionPane(summaryState, tabs, strMap, planId);
            }
            if (summaryState.hasStatus || summaryState.hasEvidence) {
                html += renderProgressPane(summaryState, tabs, strMap);
            }
            if (summaryState.hasRules) {
                html += renderRulesPane(summaryState, tabs, strMap, planId);
            }

            html += '</div>';
            return html;
        }
```

Delete `renderStatusPane` and `renderEvidencePane` entirely and put these three functions
in their place:

```js
        /**
         * Render the related-content tab pane.
         *
         * @param {Object} summaryState Normalized summary state
         * @param {Array} tabs Visible tabs
         * @param {Object} strMap Language strings map
         * @return {string} HTML
         */
        function renderCoursesPane(summaryState, tabs, strMap) {
            const isFirst = tabs[0].id === 'courses';
            let html = '<div class="local-dimensions-tab-pane local-dimensions-tab-pane-courses' +
                (isFirst ? ' active' : '') + '"';
            html += ' id="local-dimensions-tabpane-courses-' + summaryState.comp.id + '" data-tab="courses"';
            html += ' role="tabpanel" aria-labelledby="local-dimensions-tab-courses-' + summaryState.comp.id + '">';
            html += renderCourseCardsScrollable(summaryState.visibleCourses, strMap);
            html += '</div>';
            return html;
        }

        /**
         * Render the progress tab pane: where the learner stands, then what produced it.
         *
         * @param {Object} summaryState Normalized summary state
         * @param {Array} tabs Visible tabs
         * @param {Object} strMap Language strings map
         * @return {string} HTML
         */
        function renderProgressPane(summaryState, tabs, strMap) {
            const scaleConfig = summaryState.comp?.scaleconfiguration
                || summaryState.competencyData?.scaleconfiguration
                || null;
            const isFirst = tabs[0].id === 'progress';
            let html = '<div class="local-dimensions-tab-pane local-dimensions-tab-pane-progress' +
                (isFirst ? ' active' : '') + '"';
            html += ' id="local-dimensions-tabpane-progress-' + summaryState.comp.id + '" data-tab="progress"';
            html += ' role="tabpanel" aria-labelledby="local-dimensions-tab-progress-' + summaryState.comp.id + '">';

            if (summaryState.hasStatus) {
                html += renderStatusSection(summaryState.ucs, strMap, summaryState.scaleDescription);
            }
            if (summaryState.hasEvidence) {
                html += renderEvidenceList(summaryState.ucs, strMap, scaleConfig);
                html += renderEvidenceSubmit(summaryState, strMap);
            }

            html += '</div>';
            return html;
        }

        /**
         * Render the "submit prior learning evidence" button, when the admin enabled it.
         *
         * @param {Object} summaryState Normalized summary state
         * @param {Object} strMap Language strings map
         * @return {string} HTML, empty when the setting or the capability is off
         */
        function renderEvidenceSubmit(summaryState, strMap) {
            if (!displaySettings.enableevidencesubmitbutton) {
                return '';
            }
            const uc = summaryState.ucs.usercompetency || summaryState.ucs.usercompetencyplan;
            if (!uc || !uc.userid) {
                return '';
            }

            const evidenceUrl = M.cfg.wwwroot + '/admin/tool/lp/user_evidence_list.php?userid=' + uc.userid;
            let html = '<div class="local-dimensions-evidence-submit-wrapper">';
            html += '<a href="' + escapeHtml(evidenceUrl) + '" class="local-dimensions-evidence-submit-btn">';
            html += escapeHtml(strMap.evidenceSubmit);
            html += '</a>';
            html += '</div>';
            return html;
        }
```

- [ ] **Step 7: Style the count badge**

In `styles.css`, insert after the `.local-dimensions-tab-btn.active` rule (line 3327's
block):

```css
.local-dimensions-tab-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    border-radius: 999px;
    background: #e9ecef;
    color: #6c757d;
    font-size: 0.6875rem;
    font-weight: 700;
}

.local-dimensions-tab-btn.active .local-dimensions-tab-count {
    background: #cfe2ff;
    color: #0f6cbf;
}
```

- [ ] **Step 8: Lint and build**

```bash
cd /Volumes/N1TB/dev/github/moodle && npx eslint --max-warnings 0 public/local/dimensions/amd/src
```
Expected: no output (exit 0). A leftover reference to `renderStatusPane` or
`renderEvidencePane` surfaces here as `no-undef`.

```bash
cd /Volumes/N1TB/dev/github/moodle && npx stylelint --config .stylelintrc public/local/dimensions/styles.css
```
Expected: only pre-existing `max-line-length` warnings, no errors.

```bash
cd /Volumes/N1TB/dev/github/moodle && npx grunt amd --root=public/local/dimensions
```
Expected: `Done.` and a modified `amd/build/accordion.min.js` + `.map`.

- [ ] **Step 9: Commit**

```bash
git add amd/src/accordion.js amd/build/accordion.min.js amd/build/accordion.min.js.map styles.css
git commit -m "feat(learner): fold linked courses into a tab and merge assessment with evidence"
```

---

### Task 5: The assessment card

**Files:**
- Modify: `amd/src/accordion.js` — `renderStatusSection` (2336–2381)
- Modify: `styles.css` — the status block (2777–2810)
- Modify: `amd/build/accordion.min.js`, `amd/build/accordion.min.js.map` (generated)

**Interfaces:**
- Consumes: `strMap.ratingLabel` and `strMap.scaleAbout` (both already fetched);
  `summaryState.scaleDescription`, which Task 1 made the web service return as `''`
  when the setting is off.
- Produces: markup class `.local-dimensions-status-card`; the truncation wrapper
  `.local-dimensions-tip` that Task 6 reuses on the evidence chip.

- [ ] **Step 1: Rewrite `renderStatusSection`**

Replace the whole function body. The docblock needs two edits, not one: its last `@param`
line becomes "or '' to hide the button, which the web service already does when the setting
is off", **and** its summary line — which currently says no card wrapper is needed — has to
go, because the first thing the new body emits is exactly that card.

Note on escaping: `escapeHtml()` builds its result by assigning to `textContent` and reading
`innerHTML`, which encodes `&`, `<` and `>` but **not** quotes — quotes are only encoded in
attribute-value serialisation, and that is a text-node serialisation. This task is the first
to put author-written text into a quoted attribute (`data-dim-tip`), so harden `escapeHtml()`
itself to encode `"` and `'` before relying on it there. Fixing the shared function rather
than the call site also covers the several pre-existing attribute sinks in the file, and the
identical `data-dim-tip` line Task 6 adds. Escaping quotes is safe for text sinks: the entity
decodes back to the literal character when the browser parses the HTML.

```js
        function renderStatusSection(ucs, strMap, scaleDescription) {
            const uc = ucs.usercompetency || ucs.usercompetencyplan;
            if (!uc) {
                return '';
            }

            // JSON-encoded responses return numeric fields as strings; coerce for safe comparison.
            const isProficient = Number.parseInt(uc.proficiency, 10) === 1;
            const hasGrade = !!(uc.grade && uc.gradename);

            let html = '<div class="local-dimensions-status-card">';

            /* The header carries the label on the left and the scale explainer on the right, so
               the button sits with the rating it explains instead of below the whole block. */
            html += '<div class="local-dimensions-status-head">';
            html += '<span class="local-dimensions-status-eyebrow">' + escapeHtml(strMap.ratingLabel) + '</span>';
            if (scaleDescription) {
                html += '<button type="button" class="local-dimensions-status-scale" data-about-scale';
                html += ' aria-haspopup="dialog">';
                html += '<i class="fa fa-info-circle" aria-hidden="true"></i> ';
                html += escapeHtml(strMap.scaleAbout);
                html += '</button>';
            }
            html += '</div>';

            /* The rating is the fact the learner wants; proficiency only qualifies it. So the
               scale level leads as plain strong text and proficiency follows as a pill - and
               with no grade there is nothing to qualify, so the pill is dropped entirely
               rather than saying "No". Scale names are author-written and unbounded, so the
               level is clipped with the tooltip pair rather than allowed to wrap the row. */
            html += '<div class="local-dimensions-status-headline">';
            if (hasGrade) {
                html += '<span class="local-dimensions-tip" data-dim-tip="' + escapeHtml(uc.gradename) + '">';
                html += '<span class="local-dimensions-status-rating">' + escapeHtml(uc.gradename) + '</span>';
                html += '</span>';
                html += '<span class="local-dimensions-pill local-dimensions-pill-' +
                    (isProficient ? 'success' : 'warning') + '">';
                if (isProficient) {
                    html += '<i class="fa fa-check-circle" aria-hidden="true"></i> ';
                }
                html += escapeHtml(isProficient ? strMap.proficientLabel : strMap.statusNotYetProficient);
                html += '</span>';
            } else {
                html += '<span class="local-dimensions-status-rating local-dimensions-status-rating-empty">';
                html += escapeHtml(strMap.statusNotYetRated);
                html += '</span>';
            }
            html += '</div>';

            html += '</div>';

            return html;
        }
```

- [ ] **Step 2: Replace the status CSS**

In `styles.css`, replace the block from `.local-dimensions-status-scale` (2777) through
`.local-dimensions-status-rating-empty` (2807–2810) with:

```css
.local-dimensions-status-card {
    padding: 0.9rem 1rem;
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 0.75rem;
}

.local-dimensions-status-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.4rem;
}

.local-dimensions-status-eyebrow {
    font-size: 0.75rem;
    font-weight: 700;
    color: #6c757d;
}

.local-dimensions-status-scale {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.2rem 0.4rem;
    border: none;
    border-radius: 0.4rem;
    background: none;
    color: #0f6cbf;
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
}

.local-dimensions-status-scale:hover {
    text-decoration: underline;
}

.local-dimensions-status-scale:focus-visible {
    outline: 2px solid #0f6cbf;
    outline-offset: 2px;
}

.local-dimensions-status-headline {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.45rem 0.75rem;
    min-width: 0;
}

.local-dimensions-status-rating {
    display: block;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 1.375rem;
    font-weight: 600;
    color: #212529;
}

.local-dimensions-status-rating-empty {
    font-size: 1.125rem;
    font-weight: 500;
    color: #6c757d;
}
```

- [ ] **Step 3: Add the tooltip primitive**

Append to `styles.css`, at the end of the light-mode block. This is the Design System
component from `docs/design-kit/tooltip.html`; the clip lives on the child and the balloon
on this wrapper, because a wrapper carrying `overflow: hidden` would clip its own balloon.

```css
/* ============================================
      TOOLTIP - see docs/design-kit/tooltip.html
      ============================================ */
/* The full text stays in the DOM and is only clipped visually, so the balloon is
   decoration: no role, no aria-describedby, no Escape handler, no tabindex. */
.local-dimensions-tip {
    position: relative;
    display: inline-flex;
    align-items: center;
    min-width: 0;
    max-width: 100%;
}

.local-dimensions-tip::after,
.local-dimensions-tip::before {
    position: absolute;
    opacity: 0;
    pointer-events: none;
    transition: opacity 150ms ease;
}

.local-dimensions-tip::after {
    content: attr(data-dim-tip);
    bottom: calc(100% + 8px);
    left: 50%;
    transform: translateX(-50%);
    max-width: 260px;
    width: max-content;
    padding: 0.35rem 0.55rem;
    border-radius: 0.25rem;
    background: #1d2125;
    color: #fff;
    font-size: 0.75rem;
    font-weight: 400;
    line-height: 1.35;
    text-align: left;
    white-space: normal;
    overflow-wrap: anywhere;
    box-shadow: 0 0.5rem 1rem rgb(0 0 0 / 15%);
    z-index: 20;
}

.local-dimensions-tip::before {
    content: '';
    bottom: calc(100% + 3px);
    left: 50%;
    transform: translateX(-50%);
    border: 5px solid transparent;
    border-top-color: #1d2125;
    z-index: 21;
}

/* Delay on entry only, so brushing past does not flicker. */
.local-dimensions-tip:hover::after,
.local-dimensions-tip:hover::before,
.local-dimensions-tip:focus-visible::after,
.local-dimensions-tip:focus-visible::before,
.local-dimensions-tip:focus-within::after,
.local-dimensions-tip:focus-within::before {
    opacity: 1;
    transition-delay: 250ms;
}
```

`.is-open` from the kit does **not** ship — it is a forced state the kit uses to photograph
the balloon open.

`.dim-tip-bottom` **does** ship, and Task 6 adds it (see there). The reason is a clipping
ancestor that is easy to miss: `.local-dimensions-accordion-item` (styles.css:1414) and
`.local-dimensions-accordion-content` (styles.css:1553) both set `overflow: hidden`
unconditionally, and both wrap the element the panes are injected into. The `overflow` is
there for the expand/collapse animation, so it cannot simply be relaxed. An upward balloon
near the top of a pane is therefore clipped regardless of where the page is scrolled — and
both of this slice's tooltips sit near a pane's top: the assessment card is the pane's first
element, and the journey's first row is close behind it. Both use the downward variant.

- [ ] **Step 4: Lint and build**

```bash
cd /Volumes/N1TB/dev/github/moodle && npx eslint --max-warnings 0 public/local/dimensions/amd/src
```
Expected: no output.

```bash
cd /Volumes/N1TB/dev/github/moodle && npx stylelint --config .stylelintrc public/local/dimensions/styles.css
```
Expected: no errors. If `declaration-no-important`, `csstree/validator` or
`time-min-milliseconds` fires, the offending rule is new — fix it here, not later.

```bash
cd /Volumes/N1TB/dev/github/moodle && npx grunt amd --root=public/local/dimensions
```

- [ ] **Step 5: Commit**

```bash
git add amd/src/accordion.js amd/build/accordion.min.js amd/build/accordion.min.js.map styles.css
git commit -m "feat(learner): give the assessment its card, label and scale link"
```

---

### Task 6: The evidence journey row

**Files:**
- Modify: `amd/src/accordion.js` — `renderEvidenceRow` (1106–1144), `renderEvidenceList`
  (1158–1222), `formatTimestamp` (2450–2478)
- Modify: `styles.css` — `.local-dimensions-ev-row` and its children (2938–2998)
- Modify: `amd/build/accordion.min.js`, `amd/build/accordion.min.js.map` (generated)

**Interfaces:**
- Consumes: string `evidence_journey` (Task 1); `.local-dimensions-tip` (Task 5).
- Produces: nothing further tasks depend on.

**Invariant that must not break:** `initEvidenceList` looks the container up as
`contentEl.querySelector('.local-dimensions-ev-list')` and delegates every click and
keydown from it — the result strip's review button, the rules jump and the row modal. The
wrapper must keep that class. Rows keep `.local-dimensions-ev-row-clickable` and
`data-evidence-index`, which `openRow` reads.

- [ ] **Step 1: Fetch the group-label string**

In `renderCompetencySummary`, append to the `Str.get_strings([...])` array (after
`progress_tab` from Task 4):

```js
                {key: 'evidence_journey', component: 'local_dimensions'}
```

and to `strMap`, after `progressTab: strings[75]`:

```js
                    evidenceJourney: strings[76]
```

- [ ] **Step 2: Switch the date format to the short one**

In `renderCompetencySummary`'s string list, replace:

```js
                {key: 'strftimedaydate', component: 'core_langconfig'},
```

with:

```js
                {key: 'strftimedatefullshort', component: 'core_langconfig'},
```

The `strMap.dateFormat: strings[20]` mapping is unchanged — same position, different
format string. `buildEvidenceModalContext` prefers the server-formatted `ev.userdate` and
only falls back to this, so the modal keeps its long date whenever the payload carries one.

- [ ] **Step 3: Teach `formatTimestamp` two-digit years and a padded day**

In `formatTimestamp`, replace the `return formatStr` chain with:

```js
                return formatStr
                    .replace('%A', weekdayLong)
                    .replace('%a', weekdayShort)
                    .replace('%d', ('0' + day).slice(-2))
                    .replace('%B', monthLong)
                    .replace('%b', monthShort)
                    .replace('%Y', year)
                    .replace('%y', String(year).slice(-2))
                    .replace('%m', ('0' + (date.getMonth() + 1)).slice(-2));
```

Two changes: `%d` is now zero-padded, which strftime specifies and which is what keeps the
date column rectangular under `tabular-nums`; and `%y` is handled at all — it was missing,
so `strftimedatefullshort` would have rendered a literal `%y`. `%Y` is replaced before
`%y`, and `String.replace` with a string needle only replaces the first match, so the two
cannot collide.

- [ ] **Step 4: Rewrite `renderEvidenceRow`**

```js
        function renderEvidenceRow(ev, index, strMap, scaleConfig) {
            const typeInfo = getEvidenceTypeInfo(ev, strMap);
            const hasGrade = !!(ev.grade && ev.gradename && ev.gradename !== '-');
            const hasExtraDetails = ev.note || ev.url || hasGrade;

            let html = '<li class="local-dimensions-ev-row' +
                (hasExtraDetails ? ' local-dimensions-ev-row-clickable' : '') +
                '" data-evidence-index="' + index + '"';
            if (hasExtraDetails) {
                html += ' role="button" tabindex="0"';
                html += ' aria-label="' + escapeHtml(strMap.evidenceViewDetails) + ': ' + escapeHtml(typeInfo.label) + '"';
            }
            html += '>';

            html += '<span class="local-dimensions-ev-row-icon ' + typeInfo.colorClass + '">';
            html += '<i class="fa ' + typeInfo.icon + '" aria-hidden="true"></i>';
            html += '</span>';

            html += '<span class="local-dimensions-ev-row-body">';
            if (ev.description) {
                html += '<span class="local-dimensions-ev-row-desc">' + ev.description + '</span>';
            }
            html += '<span class="local-dimensions-ev-row-type">' + escapeHtml(typeInfo.label) + '</span>';
            html += '</span>';

            /* The grade sits in an auto column, so an author-written scale name long enough to
               widen it would push the date column out of line on every row. It is clipped and
               the full value is repeated in the tooltip; a row with no grade holds the column
               open with a dash so the grid stays rectangular. */
            if (hasGrade) {
                const proficient = isGradeProficient(ev.grade, scaleConfig);
                html += '<span class="local-dimensions-tip" data-dim-tip="' + escapeHtml(ev.gradename) + '">';
                html += '<span class="local-dimensions-ev-row-assess' +
                    (proficient ? ' local-dimensions-ev-row-assess-prof' : '') + '">' +
                    escapeHtml(ev.gradename) + '</span>';
                html += '</span>';
            } else {
                html += '<span class="local-dimensions-ev-row-assess local-dimensions-ev-row-assess-none">&mdash;</span>';
            }

            html += '<span class="local-dimensions-ev-row-date">';
            if (ev.timecreated) {
                html += escapeHtml(formatTimestamp(ev.timecreated, strMap.dateFormat));
            }
            html += '</span>';

            html += '</li>';
            return html;
        }
```

- [ ] **Step 5: Wrap the rows in a labelled list inside `renderEvidenceList`**

In `renderEvidenceList`, replace the final rows loop and its closing tag — currently:

```js
            evidence.forEach(function(ev, index) {
                if (index === decisiveIndex) {
                    return;
                }
                html += renderEvidenceRow(ev, index, strMap, scaleConfig);
            });

            html += '</div>';
            return html;
```

with:

```js
            /* A heading over an empty list is worse than no heading, so the label and its count
               render only once it is known that at least one row survives the decisive row's
               removal. The rows become a real list; the wrapper keeps its class because
               initEvidenceList delegates every handler from it. */
            const journey = evidence.filter(function(ev, index) {
                return index !== decisiveIndex;
            });

            if (journey.length > 0) {
                html += '<div class="local-dimensions-ev-group">';
                html += escapeHtml(strMap.evidenceJourney);
                html += '<span class="local-dimensions-ev-group-count">' + journey.length + '</span>';
                html += '</div>';
                html += '<ul class="local-dimensions-ev-journey">';
                evidence.forEach(function(ev, index) {
                    if (index === decisiveIndex) {
                        return;
                    }
                    html += renderEvidenceRow(ev, index, strMap, scaleConfig);
                });
                html += '</ul>';
            }

            html += '</div>';
            return html;
```

- [ ] **Step 6: Replace the row CSS**

In `styles.css`, replace the block from `.local-dimensions-ev-row` (2938) through
`.local-dimensions-ev-row-date` (2992–2998) with:

```css
.local-dimensions-ev-group {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin: 0.9rem 0 0.1rem;
    font-size: 0.8125rem;
    font-weight: 700;
    color: #6c757d;
}

.local-dimensions-ev-group-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    border-radius: 999px;
    background: #f8f9fa;
    color: #6c757d;
    font-size: 0.6875rem;
    font-weight: 700;
}

.local-dimensions-ev-journey {
    list-style: none;
    margin: 0;
    padding: 0;
}

/* Four columns, so the date lines up across rows however long the description or the
   grade name is. minmax(0,1fr) rather than 1fr: a plain 1fr floors at the content's
   min-width and the ellipsis would never engage. */
.local-dimensions-ev-row {
    display: grid;
    grid-template-columns: 28px minmax(0, 1fr) auto 84px;
    align-items: center;
    gap: 0.6rem;
    padding: 0.55rem 0.5rem;
    border-top: 1px solid #e9ecef;
}

.local-dimensions-ev-row:first-child {
    border-top: none;
}

.local-dimensions-ev-row-clickable {
    cursor: pointer;
    transition: background-color 150ms ease;
}

.local-dimensions-ev-row-clickable:hover {
    background: #f8f9fa;
}

.local-dimensions-ev-row-clickable:focus-visible {
    outline: 2px solid #0f6cbf;
    outline-offset: -2px;
}

.local-dimensions-ev-row-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    flex-shrink: 0;
    border-radius: 0.375rem;
    font-size: 0.75rem;
}

.local-dimensions-ev-row-body {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.local-dimensions-ev-row-desc {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.875rem;
    color: #212529;
}

.local-dimensions-ev-row-desc p {
    margin: 0;
}

.local-dimensions-ev-row-type {
    font-size: 0.6875rem;
    color: #6c757d;
}

.local-dimensions-ev-row-assess {
    display: block;
    min-width: 0;
    max-width: 104px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    padding: 0.08rem 0.4rem;
    border-radius: 0.35rem;
    background: #e9ecef;
    color: #495057;
    font-size: 0.75rem;
    font-weight: 600;
}

.local-dimensions-ev-row-assess-prof {
    background: #d1e7dd;
    color: #0f5132;
}

.local-dimensions-ev-row-assess-none {
    max-width: none;
    padding: 0;
    background: transparent;
    color: #adb5bd;
}

.local-dimensions-ev-row-date {
    text-align: right;
    white-space: nowrap;
    font-size: 0.75rem;
    color: #6c757d;
    font-variant-numeric: tabular-nums;
}
```

The six `.local-dimensions-ev-row-icon.local-dimensions-evidence-*` type-colour rules that
follow (2999 onward) are untouched — they still match, and the icon is now a rounded square
rather than a circle, matching the kit.

- [ ] **Step 7: Flip both tooltips downward**

Carried from Task 5's review. `.local-dimensions-accordion-item` (styles.css:1414) and
`.local-dimensions-accordion-content` (styles.css:1553) both set `overflow: hidden`
unconditionally, and both wrap the element the panes are injected into — the `overflow` drives
the expand/collapse animation, so it cannot be relaxed. An upward balloon near the top of a
pane is clipped no matter where the page is scrolled, and **both** of this slice's tooltips sit
near a pane's top: the assessment card is the pane's first element, and the journey's first row
is right behind it. A long scale name — exactly what makes the balloon appear — wraps to two or
three lines and clips first.

Add the variant to `styles.css`, directly after the `.local-dimensions-tip` trigger rule:

```css
/* Downward, because the accordion's expand/collapse overflow clips an upward balloon
   near the top of a pane. Both tooltips in this view sit there. */
.local-dimensions-tip-bottom::after {
    bottom: auto;
    top: calc(100% + 8px);
}

.local-dimensions-tip-bottom::before {
    bottom: auto;
    top: calc(100% + 3px);
    border-top-color: transparent;
    border-bottom-color: #1d2125;
}
```

Then add the class at both emitters — in `renderEvidenceRow` (this task) and in
`renderStatusSection` (Task 5's function), changing each wrapper from
`class="local-dimensions-tip"` to
`class="local-dimensions-tip local-dimensions-tip-bottom"`.

Residual limitation, accepted rather than engineered away: the **last** journey row's balloon
opens downward and can clip against the pane's bottom edge when the submit button is disabled
and the balloon wraps to three lines. That is one row in one configuration, against every first
row in every configuration for the upward variant — a strictly better trade for two CSS rules.

- [ ] **Step 8: Lint and build**

```bash
cd /Volumes/N1TB/dev/github/moodle && npx eslint --max-warnings 0 public/local/dimensions/amd/src
```
Expected: no output.

```bash
cd /Volumes/N1TB/dev/github/moodle && npx stylelint --config .stylelintrc public/local/dimensions/styles.css
```
Expected: no errors.

```bash
cd /Volumes/N1TB/dev/github/moodle && npx grunt amd --root=public/local/dimensions
```

- [ ] **Step 9: Commit**

```bash
git add amd/src/accordion.js amd/build/accordion.min.js amd/build/accordion.min.js.map styles.css
git commit -m "feat(learner): align the evidence journey into columns with a short date"
```

---

### Task 7: Course access states and the footer link

**Files:**
- Modify: `amd/src/accordion.js` — `renderCourseCardsScrollable` (1917–2019) and its call
  site in `renderCoursesPane` (added in Task 4)
- Modify: `styles.css` — after `.local-dimensions-course-progress-lg` (2596)
- Modify: `amd/build/accordion.min.js`, `amd/build/accordion.min.js.map` (generated)

**Interfaces:**
- Consumes: `access`, `lockdate`, `isenrolstart`, `activity` from the courses web service
  (Task 3); strings `view_detailed_progress` (Task 1) and the pre-existing
  `enrol_to_start`, `selfenrolment_open`, `locked_content`, `available_at`,
  `enrolment_starts`, `go_to_activity`, `course_completed`, `filter_not_completed`.
- Produces: `renderCourseCardsScrollable(courses, strMap, competencyId, planId)` — the
  signature gains two arguments; `renderCoursesPane` must pass them.

- [ ] **Step 1: Fetch the remaining strings**

In `renderCompetencySummary`, append to the `Str.get_strings([...])` array:

```js
                {key: 'view_detailed_progress', component: 'local_dimensions'},
                {key: 'enrol_to_start', component: 'local_dimensions'},
                {key: 'selfenrolment_open', component: 'local_dimensions'},
                {key: 'locked_content', component: 'local_dimensions'},
                {key: 'available_at', component: 'local_dimensions'},
                {key: 'enrolment_starts', component: 'local_dimensions'}
```

and to `strMap`, after `evidenceJourney: strings[76]`:

```js
                    viewDetailedProgress: strings[77],
                    enrolToStart: strings[78],
                    selfEnrolmentOpen: strings[79],
                    lockedContent: strings[80],
                    availableAt: strings[81],
                    enrolmentStarts: strings[82]
```

`course_completed`, `filter_not_completed` and `go_to_activity` are **not** in the list
yet — add these three as well and map them:

```js
                {key: 'course_completed', component: 'local_dimensions'},
                {key: 'filter_not_completed', component: 'local_dimensions'},
                {key: 'go_to_activity', component: 'local_dimensions'}
```

```js
                    courseCompleted: strings[83],
                    notCompleted: strings[84],
                    goToActivity: strings[85]
```

- [ ] **Step 2: Add the two helpers the card body needs**

Insert directly above `renderCourseCardsScrollable`:

```js
        /**
         * Render the state strip that replaces a card's progress row.
         *
         * A progress bar carries no meaning for a learner who cannot open the course, and on a
         * course with one trackable activity it can only ever read 0% or 100%. So the one row
         * that is meaningless in each case is the one that is replaced - the image, the name,
         * the outcome badge and the activities drawer all survive.
         *
         * @param {Object} course A course row from the web service
         * @param {Object} strMap Language strings map
         * @return {string} HTML, or an empty string when the normal progress bar should render
         */
        function renderCourseState(course, strMap) {
            if (course.access === 'enrol') {
                let html = '<span class="local-dimensions-course-state local-dimensions-course-state-enrol">';
                html += '<i class="fa fa-sign-in" aria-hidden="true"></i>';
                html += escapeHtml(strMap.enrolToStart);
                html += '</span>';
                html += '<span class="local-dimensions-course-hint">' +
                    escapeHtml(strMap.selfEnrolmentOpen) + '</span>';
                return html;
            }

            if (course.access === 'locked') {
                let html = '<span class="local-dimensions-course-state local-dimensions-course-state-locked">';
                html += '<i class="fa fa-lock" aria-hidden="true"></i>';
                html += escapeHtml(strMap.lockedContent);
                html += '</span>';

                /* A date that has already passed explains nothing, so it is dropped rather than
                   shown as history. showlockeddate is resolved server-side into the payload's
                   own lockdate, which is 0 whenever there is nothing to say. */
                const lockdate = Number.parseInt(course.lockdate, 10) || 0;
                if (displaySettings.showlockeddate && lockdate * 1000 > Date.now()) {
                    const template = course.isenrolstart ? strMap.enrolmentStarts : strMap.availableAt;
                    html += '<span class="local-dimensions-course-when">';
                    html += '<i class="fa fa-calendar" aria-hidden="true"></i>';
                    html += escapeHtml(template.replace('{$a}', formatTimestamp(lockdate, strMap.dateFormat)));
                    html += '</span>';
                }
                return html;
            }

            if (course.activity) {
                let html = '<span class="local-dimensions-course-single">';
                html += '<i class="fa ' + (course.activity.completed ? 'fa-check-circle' : 'fa-circle-o') +
                    ' local-dimensions-course-single-mark' +
                    (course.activity.completed ? ' local-dimensions-course-single-done' : '') +
                    '" aria-hidden="true"></i>';
                html += '<span class="local-dimensions-course-single-name">' +
                    escapeHtml(course.activity.name) + '</span>';
                html += '<span class="local-dimensions-course-single-state' +
                    (course.activity.completed ? ' local-dimensions-course-single-done' : '') + '">' +
                    escapeHtml(course.activity.completed ? strMap.courseCompleted : strMap.notCompleted) +
                    '</span>';
                html += '</span>';
                html += '<span class="local-dimensions-course-go">' +
                    escapeHtml(strMap.goToActivity) +
                    '<i class="fa fa-arrow-right" aria-hidden="true"></i></span>';
                return html;
            }

            return '';
        }

        /**
         * Render the normal progress row of a course card.
         *
         * @param {Object} course A course row from the web service
         * @return {string} HTML
         */
        function renderCourseProgress(course) {
            const progress = Number.parseInt(course.progress, 10) || 0;
            let html = '<div class="local-dimensions-course-progress-lg">';
            html += '<div class="local-dimensions-course-progress-track">';
            html += '<div class="local-dimensions-course-progress-fill-lg" style="width: ' + progress + '%;"></div>';
            html += '</div>';
            html += '<span class="local-dimensions-course-progress-pct-lg">' + progress + '%</span>';
            if (progress >= 100) {
                html += '<i class="fa fa-check-circle local-dimensions-course-check" aria-hidden="true"></i>';
            }
            html += '</div>';
            return html;
        }
```

- [ ] **Step 3: Use them in `renderCourseCardsScrollable`, and add the footer link**

Change the signature and the section heading, then swap the progress block. Replace:

```js
        function renderCourseCardsScrollable(courses, strMap) {
```

with:

```js
        function renderCourseCardsScrollable(courses, strMap, competencyId, planId) {
```

Delete the `<h2 class="local-dimensions-section-title">…</h2>` block from
`local-dimensions-courses-head` — the tab label is the heading now and the count rides the
tab — leaving the head holding only the scroll controls:

```js
            html += '<div class="local-dimensions-courses-head">';
            html += '<span class="local-dimensions-courses-scroll-controls" role="group" aria-label="' +
                escapeHtml(strMap.relatedContent) + '">';
```

Inside the card loop, replace the whole progress block:

```js
                // Progress bar.
                html += '<div class="local-dimensions-course-progress-lg">';
                html += '<div class="local-dimensions-course-progress-track">';
                html += '<div class="local-dimensions-course-progress-fill-lg" style="width: ' + progress + '%;"></div>';
                html += '</div>';
                html += '<span class="local-dimensions-course-progress-pct-lg">' + progress + '%</span>';
                if (progress >= 100) {
                    html += '<i class="fa fa-check-circle local-dimensions-course-check" aria-hidden="true"></i>';
                }
                html += '</div>';
```

with:

```js
                const state = renderCourseState(course, strMap);
                html += state === '' ? renderCourseProgress(course) : state;
```

and delete the now-unused `const progress = Number.parseInt(course.progress, 10) || 0;`
line near the top of the loop — `renderCourseProgress` reads it itself. ESLint flags it as
`no-unused-vars` otherwise.

Mark the card when it is out of reach, so the artwork dims. Replace:

```js
                html += '<div class="local-dimensions-course-card-lg">';
```

with:

```js
                const isReachable = course.access !== 'locked' && course.access !== 'enrol';
                const isBlocked = course.access === 'locked'
                    && displaySettings.lockedcardmode === 'blocked';
                html += '<div class="local-dimensions-course-card-lg' +
                    (isReachable ? '' : ' local-dimensions-course-card-dim') +
                    (isBlocked ? ' local-dimensions-course-card-blocked' : '') + '">';
```

Then honour `lockedcardmode` for real: in blocked mode the card stops being a link, so the
wrapping element has to change, not just its cursor. Replace:

```js
                // The whole card is the link to the course; the disclosure below sits outside it.
                html += '<a href="' + escapeHtml(courseUrl) + '" class="local-dimensions-course-link">';
```

with:

```js
                /* The whole card is the link to the course; the disclosure below sits outside it.
                   In blocked mode a locked card leads nowhere, so it is a span - core would only
                   show the same restriction message the card already carries. */
                html += isBlocked
                    ? '<span class="local-dimensions-course-link">'
                    : '<a href="' + escapeHtml(courseUrl) + '" class="local-dimensions-course-link">';
```

and its closing tag:

```js
                html += '</a>'; // End local-dimensions-course-link.
```

with:

```js
                html += isBlocked ? '</span>' : '</a>';
```

Suppress the activities drawer when it would list the very activity the card is already
showing. Replace:

```js
                if (activities.length > 0) {
```

with:

```js
                /* When the card already shows the course's single trackable activity, a drawer
                   listing that same activity would say it twice. */
                const repeats = activities.length === 1 && course.activity
                    && activities[0].name === course.activity.name;
                if (activities.length > 0 && !repeats) {
```

Finally, after the loop and after `html += '</div>'; // End local-dimensions-courses-scroll.`,
before the wrapper's closing div, add the footer:

```js
            /* The way out to the tracker, from three courses up. A card can only summarise a
               course as one percentage; the tracker shows per-section progress, locked sections
               and availability dates. The threshold is a plain count of what is shown, not the
               condition that reveals the scroll arrows - that one also fires with two cards on a
               narrow screen, so the link would come and go on resize. No noredirect flag is
               needed: the single-course redirect requires exactly one course to survive the
               filter, so it cannot fire from three up. */
            if (courses.length >= 3 && competencyId && planId) {
                const baseUrl = displaySettings.viewcompetencyurl
                    || (M.cfg.wwwroot + '/local/dimensions/view-competency.php');
                const trackerUrl = baseUrl + '?id=' + planId + '&competencyid=' + competencyId;
                html += '<div class="local-dimensions-courses-foot">';
                html += '<a class="local-dimensions-courses-more" href="' + escapeHtml(trackerUrl) + '">';
                html += escapeHtml(strMap.viewDetailedProgress);
                html += '<i class="fa fa-arrow-right" aria-hidden="true"></i>';
                html += '</a>';
                html += '</div>';
            }
```

- [ ] **Step 4: Pass the two new arguments from `renderCoursesPane`**

In `renderCoursesPane` (added in Task 4), change the signature and the call:

```js
        function renderCoursesPane(summaryState, tabs, strMap, planId) {
```

```js
            html += renderCourseCardsScrollable(
                summaryState.visibleCourses,
                strMap,
                summaryState.comp.id,
                planId
            );
```

and in `renderSummaryTabPanes`, pass `planId` through:

```js
                html += renderCoursesPane(summaryState, tabs, strMap, planId);
```

- [ ] **Step 5: Pass `showlockeddate` and `lockedcardmode` into the accordion's settings**

The card states read two settings the accordion has never received. In `view-plan.php`,
add to the `$accordionsettings` array (after `'showevidence'`):

```php
    'showlockeddate' => (bool) get_config('local_dimensions', 'showlockeddate'),
    'lockedcardmode' => (string) get_config('local_dimensions', 'lockedcardmode'),
```

and in `amd/src/accordion.js`, add the two defaults to the `displaySettings` object
(the one holding `viewcompetencyurl: ''` at line 64):

```js
            showlockeddate: false,
            lockedcardmode: 'blocked',
```

- [ ] **Step 6: Add the CSS**

In `styles.css`, insert after the `.local-dimensions-course-progress-lg` block (2596):

```css
/* Out of reach: the state is written on the card, so the artwork only has to stop
   competing with it. No overlay - on a 255px card in a scroller a veil would bury the
   image, the name, the outcome badge and the activities drawer. */
.local-dimensions-course-card-dim .local-dimensions-course-img img {
    filter: grayscale(55%);
    opacity: 0.62;
}

.local-dimensions-course-card-dim .local-dimensions-course-img-placeholder {
    opacity: 0.5;
}

/* Blocked mode drops the card's link, so it must not look clickable. */
.local-dimensions-course-card-blocked .local-dimensions-course-link {
    cursor: default;
}

.local-dimensions-course-state {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.5rem;
    border-radius: 0.4rem;
    font-size: 0.8125rem;
    font-weight: 600;
    line-height: 1.25;
}

.local-dimensions-course-state-locked {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    color: #6c757d;
}

.local-dimensions-course-state-enrol {
    justify-content: center;
    background: #0f6cbf;
    color: #fff;
}

.local-dimensions-course-hint {
    font-size: 0.75rem;
    color: #6c757d;
    text-align: center;
}

.local-dimensions-course-when {
    display: inline-flex;
    align-items: center;
    align-self: flex-start;
    gap: 0.3rem;
    padding: 0.15rem 0.5rem;
    border-radius: 999px;
    background: #cfe2ff;
    color: #0f6cbf;
    font-size: 0.75rem;
    font-weight: 600;
}

/* Single trackable activity: the link sits on its own line, because sharing one with the
   name would clip the name at 255px long before it needed to be. */
.local-dimensions-course-single {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.local-dimensions-course-single-mark {
    flex: none;
    color: #6c757d;
}

.local-dimensions-course-single-name {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.8125rem;
    font-weight: 600;
}

.local-dimensions-course-single-state {
    flex: none;
    font-size: 0.75rem;
    color: #6c757d;
}

.local-dimensions-course-single-done {
    color: #0f5132;
    font-weight: 600;
}

.local-dimensions-course-go {
    display: inline-flex;
    align-items: center;
    align-self: flex-start;
    gap: 0.25rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: #0f6cbf;
    white-space: nowrap;
}

.local-dimensions-courses-foot {
    display: flex;
    justify-content: flex-end;
    padding: 0.5rem 0 0.1rem;
}

.local-dimensions-courses-more {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.75rem;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    background: #fff;
    color: #495057;
    font-size: 0.8125rem;
    font-weight: 600;
    text-decoration: none;
}

.local-dimensions-courses-more:hover {
    background: #f8f9fa;
    color: #212529;
    text-decoration: none;
}

.local-dimensions-courses-more:focus-visible {
    outline: 2px solid #0f6cbf;
    outline-offset: 2px;
}
```

- [ ] **Step 7: Lint and build**

```bash
cd /Volumes/N1TB/dev/github/moodle && npx eslint --max-warnings 0 public/local/dimensions/amd/src
```
Expected: no output. The most likely failure here is `complexity` on
`renderCourseCardsScrollable` — if it fires, move the card body into its own
`renderCourseCard(course, strMap)` function rather than adding a disable comment.

```bash
cd /Volumes/N1TB/dev/github/moodle && npx stylelint --config .stylelintrc public/local/dimensions/styles.css
```
Expected: no errors.

```bash
cd /Volumes/N1TB/dev/github/moodle && npx grunt amd --root=public/local/dimensions
```

- [ ] **Step 8: Check the PHP style traps on `view-plan.php`**

```bash
awk 'length($0)>132{print FILENAME":"NR" ("length($0)")"}' view-plan.php
```
Expected: no output.

- [ ] **Step 9: Commit**

```bash
git add amd/src/accordion.js amd/build/accordion.min.js amd/build/accordion.min.js.map styles.css view-plan.php
git commit -m "feat(learner): show course access states and a route to the tracker"
```

---

### Task 8: Refresh the field map and close the loop

The kit and the spec are already written and committed. What is not yet true is the
learner-kit's **as-is** map: it now describes the shipped code from before this slice, and
the "Planned" section lists everything this slice just built.

**Files:**
- Modify: `docs/learner-kit/maps/viewplan.md`

**Interfaces:**
- Consumes: nothing.
- Produces: nothing.

- [ ] **Step 1: Fold the planned section into the as-is inventory**

In `docs/learner-kit/maps/viewplan.md`, delete the whole
"## Planned — accordion refinement slice (not yet in code)" section — its table, its intro
and its closing paragraph — and move its fourteen rows into the "## Expanded detail" table
above, reformatted into that table's six columns (ID · Label · Type · Origin · Data · Rule)
and worded in the present tense. Two worked examples, so the pattern is unambiguous:

| Planned row | Becomes, in the as-is table |
|---|---|
| `OVW-CRS-MORE` — "View detailed progress" link in the pane footer, right-aligned, only when `visibleCourses.length >= 3` | `OVW-CRS-MORE` \| View detailed progress \| footer link \| `renderCourseCardsScrollable` \| `viewcompetencyurl`, plan id, competency id \| right-aligned under the track; rendered only from 3 visible courses up; no `noredirect` flag, the single-course redirect cannot fire from three |
| `OVW-PROG-PANE` — `renderStatusPane` + `renderEvidencePane` → one `renderProgressPane` | `OVW-PROG-PANE` \| Progress \| tab pane \| `renderProgressPane` \| — \| gated by `hasStatus \|\| hasEvidence`; holds the assessment card, the decisive strip, the journey and the submit button |

Then correct these five existing as-is rows, which this slice made false:

- `OVW-TAB-NAV` — the tab list becomes **Related content / Description / Progress / Rules**,
  and the sentence "The linked-course section is **not** a tab — it renders after the whole
  wrapper" is now wrong: delete it.
- `OVW-STATUS` — replace "the scale level as strong text, then a success/warning proficiency
  pill … No card, no label above it" with the card, the `rating_label` eyebrow and the
  right-aligned scale button.
- `OVW-STATUS-SCALE` — "rendered below the headline whenever the scale description is
  non-empty; **no setting gates it**" becomes: rendered in the card header, gated by
  `showscaledescription`, which is resolved server-side so the payload carries `''` when off.
- `OVW-EVID-CARD` — "icon + description + type label + grade pill + date, in a flex row"
  becomes the four-column grid, with the abbreviated `strftimedatefullshort` date and the
  clipped grade chip.
- `OVW-CRS` — "scrollable cards, **outside the tab wrapper**" becomes the leading tab pane,
  and "Skipped entirely when no course survives the enrolment filter, leaving a blank below
  the pane" becomes: the tab is not built at all.

- [ ] **Step 2: Add the new settings line**

Update the "**Settings that affect this view:**" paragraph to include
`showscaledescription`, and note that `showlockeddate` and `lockedcardmode` — until now
tracker-only — are now read by the plan's course cards too.

- [ ] **Step 3: Commit**

```bash
git add docs/learner-kit/maps/viewplan.md
git commit -m "docs(learner-kit): bring the plan overview field map up to the shipped code"
```

---

## Verification before handing back

- [ ] `npx eslint --max-warnings 0 public/local/dimensions/amd/src` — clean
- [ ] `npx stylelint --config .stylelintrc public/local/dimensions/styles.css` — no errors
- [ ] `git status` shows `amd/build/accordion.min.js` and its `.map` modified alongside every
      commit that touched `amd/src/accordion.js`
- [ ] `version.php` reads `2026072400`
- [ ] `grep -rn 'renderStatusPane\|renderEvidencePane' amd/src/` returns nothing
- [ ] Both lang files carry all five new strings, in alphabetical order, and have the same
      key count: `grep -c "^\$string" lang/en/local_dimensions.php lang/pt_br/local_dimensions.php`
- [ ] The PHPUnit suites in Tasks 1–3 are green **in CI** — they cannot be run here

## Runtime check on the test site

Package a zip from the local commit and install it, per `CLAUDE.md`:

```bash
ver=$(grep -oE '\$plugin->version[[:space:]]*=[[:space:]]*[0-9]+' /Volumes/N1TB/dev/github/moodle/public/local/dimensions/version.php | grep -oE '[0-9]+'); sha=$(git -C /Volumes/N1TB/dev/github/moodle/public/local/dimensions rev-parse --short HEAD); git -C /Volumes/N1TB/dev/github/moodle/public/local/dimensions archive --format=zip --prefix=dimensions/ HEAD -o ~/Downloads/dimensions-$ver-$sha.zip
```

What to look at, in order:

1. A competency with linked courses opens on **Related content**, with the count on the tab.
2. A competency with none opens on **Description**; there is no empty strip.
3. **Progress** shows the assessment card with the label above and the scale link on the
   right — and no link at all once `showscaledescription` is switched off.
4. A long scale name in the evidence journey is clipped, the dates line up in a column, and
   hovering the chip shows the full value.
5. A course the learner is not enrolled in shows either "Enrol to start" or "Locked
   content" with its date, and its artwork is dimmed.
6. With three or more courses, "View detailed progress" sits at the bottom right and opens
   the tracker for that competency; with two, it is absent.
