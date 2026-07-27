# Course-card shape Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the single-section course and the single-activity course their own card bodies, resolved once on the server, and render them identically in both learner views.

**Architecture:** One new resolver in `calculator` answers "what shape should this card take" and returns the data that shape needs. Both web services call it, so the rule has one implementation. The two client renderers switch on the returned mode instead of on two independent flags, one of which is currently derived in the browser.

**Tech Stack:** Moodle 4.5–5.2 local plugin · plain AMD (`define`, not ESM) · Mustache for the tracker card, string-built HTML for the plan card · PHPUnit · `styles.css` (no SCSS build).

**Spec:** `docs/superpowers/specs/2026-07-26-card-shape-single-section-and-single-activity-design.md`

## Global Constraints

- **This checkout cannot run PHPUnit or Behat.** `public/config.php` declares no `phpunit_prefix` / `phpunit_dataroot`, and there is no `phpunit.xml`. Every "run the test" step means **run it in CI on the next push**. Write the test first anyway — the red/green order still shapes the test.
- **Runnable locally, and required before every push**, from `/Volumes/N1TB/dev/github/moodle`:
  ```bash
  npx eslint --max-warnings 0 public/local/dimensions/amd/src
  ```
  ```bash
  npx stylelint --config .stylelintrc public/local/dimensions/styles.css
  ```
  CI runs `grunt --max-lint-warnings 0`, so **every warning fails the build**. A plain local `grunt amd` prints ESLint warnings and still exits 0 — do not rely on it.
- **Every commit that touches `amd/src` must ship the rebuilt `amd/build`** in the same commit:
  ```bash
  npx grunt amd --root=public/local/dimensions
  ```
- **CSS hard errors under core's stylelint:** no `!important` anywhere; no `clamp()`/`min()`/`max()` in **any** length-valued property (`calc()` and grid `minmax()` are fine); no transition or animation under `100ms`. **Placement matters:** a rule that overrides another at equal specificity must come after it — this codebase has already shipped one bug from getting that backwards.
- **PHP style, no local runner:** hard line limit 180, soft limit **132** (the soft one fails `phpdoc --max-warnings 0`); inline `//` comments start with a capital and end with punctuation; lowercase or multi-line commentary belongs in a `/* … */` block. Variables lower-case only. Methods `lower_snake_case`. Every class, method, property and constant needs a docblock with explicit `@param`/`@return`; **`@param` array types must be plain `array`**.
- **`execute_returns()` is an allowlist** — an undeclared key is silently stripped by `clean_returnvalue`, and both sides of a comparison then read `undefined`.
- **Lang files** `lang/en/local_dimensions.php` and `lang/pt_br/local_dimensions.php` are kept in sync and **alphabetically sorted**; CI's `validate` step enforces the ordering.
- **Supported branches are 4.5 through 5.2** and CI runs all four. Do not reach for core API that arrived later than 4.5 without checking; `core_courseformat\main_activity_interface` is the specific trap this slice already avoided.
- **Never write a bare to-do or merge-conflict marker in any file**, docs included.
- **Do not push.** Commit locally; the user drives pushes.

---

### Task 1: `calculator::resolve_card_shape()`

**Files:**
- Modify: `classes/constants.php` (add three constants)
- Modify: `classes/calculator.php` (add one public method and five private helpers, above `is_locked()`)
- Test: `tests/calculator_card_shape_test.php` (create)

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `\local_dimensions\constants::CARDMODE_ACTIVITY` = `'activity'`, `CARDMODE_SECTION` = `'section'`, `CARDMODE_TIMELINE` = `'timeline'`.
  - `\local_dimensions\calculator::resolve_card_shape(int $courseid, int $userid): array` returning
    `['mode' => string, 'activity' => array|null, 'section' => array|null]`, where
    `activity` is `['cmid' => int, 'name' => string, 'url' => string, 'completed' => bool, 'tracked' => bool]`
    and `section` is `['name' => string, 'hasownname' => bool, 'url' => string]`.
  - Tasks 2 and 4 call it; Tasks 3 and 5 render its output.

- [ ] **Step 1: Write the failing test**

Create `tests/calculator_card_shape_test.php`:

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
 * Tests the resolver that decides which shape a course card takes.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\calculator::resolve_card_shape
 */
final class calculator_card_shape_test extends \advanced_testcase {
    /**
     * A single-activity course names its activity, tracked or not.
     *
     * @return void
     */
    public function test_single_activity_format_resolves_to_the_activity(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([
            'format' => 'singleactivity',
            'enablecompletion' => 1,
        ]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Submit portfolio',
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $this->setUser($user);
        $shape = calculator::resolve_card_shape((int) $course->id, (int) $user->id);

        $this->assertSame(constants::CARDMODE_ACTIVITY, $shape['mode']);
        $this->assertSame('Submit portfolio', $shape['activity']['name']);
        $this->assertTrue($shape['activity']['tracked']);
        $this->assertNull($shape['section']);
    }

    /**
     * The format branch survives completion being switched off, which the count cannot.
     *
     * @return void
     */
    public function test_single_activity_format_without_completion_still_names_it(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([
            'format' => 'singleactivity',
            'enablecompletion' => 0,
        ]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Read the brief',
        ]);

        $this->setUser($user);
        $shape = calculator::resolve_card_shape((int) $course->id, (int) $user->id);

        $this->assertSame(constants::CARDMODE_ACTIVITY, $shape['mode']);
        $this->assertSame('Read the brief', $shape['activity']['name']);
        $this->assertFalse($shape['activity']['tracked']);
        $this->assertFalse($shape['activity']['completed']);
    }

    /**
     * A completed activity is reported as completed.
     *
     * This branch decides whether the card reads "Completed" or "Not completed", and it
     * is the coverage that `tests/calculator_single_activity_test.php` gained in review
     * before Task 4 deletes it — it must not be lost with the file.
     *
     * @return void
     */
    public function test_completed_activity_is_reported_completed(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([
            'format' => 'singleactivity',
            'enablecompletion' => 1,
        ]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Submit portfolio',
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $completion = new \completion_info(get_course((int) $course->id));
        $cm = get_coursemodule_from_id('page', (int) $page->cmid, 0, false, MUST_EXIST);
        $completion->update_state($cm, COMPLETION_COMPLETE, (int) $user->id);

        $this->setUser($user);
        $shape = calculator::resolve_card_shape((int) $course->id, (int) $user->id);

        $this->assertTrue($shape['activity']['completed']);
        $this->assertSame((int) $page->cmid, $shape['activity']['cmid']);
    }

    /**
     * A course that boils down to one tracked activity takes the same shape.
     *
     * @return void
     */
    public function test_one_tracked_activity_resolves_to_the_activity(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([
            'numsections' => 3,
            'enablecompletion' => 1,
        ]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'The only tracked thing',
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Untracked reading',
        ]);

        $this->setUser($user);
        $shape = calculator::resolve_card_shape((int) $course->id, (int) $user->id);

        $this->assertSame(constants::CARDMODE_ACTIVITY, $shape['mode']);
        $this->assertSame('The only tracked thing', $shape['activity']['name']);
    }

    /**
     * One section with several tracked activities takes the section shape, and reports
     * that its name was generated rather than authored.
     *
     * @return void
     */
    public function test_single_generated_section_resolves_to_the_section(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([
            'numsections' => 0,
            'enablecompletion' => 1,
        ]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        foreach (['First', 'Second'] as $name) {
            $this->getDataGenerator()->create_module('page', [
                'course' => $course->id,
                'name' => $name,
                'completion' => COMPLETION_TRACKING_MANUAL,
            ]);
        }

        $this->setUser($user);
        $shape = calculator::resolve_card_shape((int) $course->id, (int) $user->id);

        $this->assertSame(constants::CARDMODE_SECTION, $shape['mode']);
        $this->assertFalse($shape['section']['hasownname']);
        $this->assertSame('', $shape['section']['name']);
        $this->assertStringContainsString('/course/section.php', $shape['section']['url']);
        $this->assertNull($shape['activity']);
    }

    /**
     * An authored section name is reported and carried.
     *
     * @return void
     */
    public function test_authored_section_name_is_carried(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([
            'numsections' => 0,
            'enablecompletion' => 1,
        ]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        foreach (['First', 'Second'] as $name) {
            $this->getDataGenerator()->create_module('page', [
                'course' => $course->id,
                'name' => $name,
                'completion' => COMPLETION_TRACKING_MANUAL,
            ]);
        }
        $DB->set_field('course_sections', 'name', 'Main pathway', ['course' => $course->id, 'section' => 0]);
        rebuild_course_cache((int) $course->id, true);

        $this->setUser($user);
        $shape = calculator::resolve_card_shape((int) $course->id, (int) $user->id);

        $this->assertSame(constants::CARDMODE_SECTION, $shape['mode']);
        $this->assertTrue($shape['section']['hasownname']);
        $this->assertSame('Main pathway', $shape['section']['name']);
    }

    /**
     * Several sections keep the timeline.
     *
     * @return void
     */
    public function test_several_sections_resolve_to_the_timeline(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([
            'numsections' => 3,
            'enablecompletion' => 1,
        ]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        foreach (['First', 'Second'] as $name) {
            $this->getDataGenerator()->create_module('page', [
                'course' => $course->id,
                'name' => $name,
                'completion' => COMPLETION_TRACKING_MANUAL,
            ]);
        }

        $this->setUser($user);
        $shape = calculator::resolve_card_shape((int) $course->id, (int) $user->id);

        $this->assertSame(constants::CARDMODE_TIMELINE, $shape['mode']);
        $this->assertNull($shape['activity']);
        $this->assertNull($shape['section']);
    }
}
```

**The fixtures must write `activitytype` directly, not pass it to `create_course()`.**
That looks like it works and does not: `create_course()` routes format options through
`base::validate_format_options()`, which calls
`format_singleactivity::course_format_options(true)`, which filters the legal values
through `has_capability("mod/{$activity}:addinstance", …)`. The tests create the course
before `setUser()`, so `$USER->id === 0`, and `has_capability()` returns false
unconditionally for user 0 on any write or risky capability — `mod/page:addinstance` is
both. The key is dropped and the course falls back to the site default, `forum`.

`setAdminUser()` before `create_course()` clears that gate but not a second trap:
`course_format_options()` caches its capability-filtered list in a function-static that
`resetAfterTest()` never clears, so in a shared CI process the result depends on whichever
test ran first. Write the row into `course_format_options` (columns `courseid`, `format`,
`sectionid` — `0` for a course-level option — `name`, `value`) and call
`rebuild_course_cache($courseid, true)`, which resets the per-course format instance so the
next read sees it. Put that in one shared private helper with a docblock saying why, or the
next reader will fold it back into `create_course()` and silently break every case.

The leftover-module test only guards anything if its decoy module is **also**
completion-tracked. With an untracked decoy the fallback branch filters it out, leaves one
trackable module and returns the right answer for the wrong reason — green whether or not
`resolve_main_activity()` works at all.

- [ ] **Step 2: Run the test to verify it fails**

Not runnable in this checkout. In CI, expect all six to **FAIL** with
`Call to undefined method local_dimensions\calculator::resolve_card_shape()`.

- [ ] **Step 3: Add the three constants**

In `classes/constants.php`, alongside the other shared constants:

```php
    /** @var string Card shape: the course's single activity, with no sequence to draw. */
    const CARDMODE_ACTIVITY = 'activity';

    /** @var string Card shape: one visible section holding several activities. */
    const CARDMODE_SECTION = 'section';

    /** @var string Card shape: the full section timeline. */
    const CARDMODE_TIMELINE = 'timeline';
```

- [ ] **Step 4: Write the resolver and its helpers**

In `classes/calculator.php`, insert directly above `is_locked()`:

```php
    /**
     * The shape the course card should take, and the data that shape needs.
     *
     * Three shapes, first match wins:
     * - activity: the course is in the single-activity format, or it boils down to one
     *   trackable module. Either way there is no sequence to draw, and a progress bar
     *   could only ever read 0% or 100%.
     * - section: one visible section holding several activities. A timeline of a single
     *   row naming a section usually called "General" informs nobody.
     * - timeline: everything else.
     *
     * The course-level lock is the caller's business and takes precedence over all three.
     *
     * @param int $courseid The course id.
     * @param int $userid The user whose completion and visibility are read.
     * @return array Keys mode, activity and section; activity and section are null unless
     *               mode names them.
     */
    public static function resolve_card_shape(int $courseid, int $userid): array {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        $course = get_course($courseid);
        $completion = new \completion_info($course);
        $modinfo = get_fast_modinfo($course, $userid);

        $main = self::resolve_main_activity($course, $modinfo);
        if ($main !== null) {
            return [
                'mode' => constants::CARDMODE_ACTIVITY,
                'activity' => self::describe_activity($main, $completion, $userid),
                'section' => null,
            ];
        }

        // Two is enough to answer "is there exactly one", and stops the walk early.
        $tracked = self::collect_trackable_cms($modinfo, $completion, 2);
        if (count($tracked) === 1) {
            return [
                'mode' => constants::CARDMODE_ACTIVITY,
                'activity' => self::describe_activity($tracked[0], $completion, $userid),
                'section' => null,
            ];
        }

        $sections = self::collect_card_sections($modinfo);
        if (count($sections) === 1) {
            return [
                'mode' => constants::CARDMODE_SECTION,
                'activity' => null,
                'section' => self::describe_section($course, $sections[0]),
            ];
        }

        return [
            'mode' => constants::CARDMODE_TIMELINE,
            'activity' => null,
            'section' => null,
        ];
    }

    /**
     * The activity of a single-activity-format course, when it has one.
     *
     * core_courseformat\main_activity_interface::get_main_activity() answers this
     * directly, but it arrived in Moodle 5.1 and this plugin supports 4.5 upward. An
     * instanceof against a missing interface returns false rather than failing, so that
     * branch would silently never fire on two of the four branches CI runs. The format
     * string is on the course record everywhere, so detecting the format that way is
     * unchanged - but the format does NOT guarantee a single activity: Moodle neither
     * deletes sections nor modules when a course's format is switched to singleactivity,
     * nor when the format's activity type is changed later, so a course migrated from
     * another format keeps its old modules around. This mirrors
     * format_singleactivity::get_activitytype() + get_main_activity()
     * (course/format/singleactivity/lib.php): the format option 'activitytype' names the
     * one module type that counts, and only section 0 is searched for the first module of
     * that type - exactly what format_singleactivity::page_set_course() redirects the
     * learner to, so the card must name the same one. Unlike core, this method does not
     * force-show a hidden match: a deletioninprogress or non-uservisible candidate falls
     * through to null instead, and the caller's next branch handles it.
     *
     * @param \stdClass $course The course record.
     * @param \course_modinfo $modinfo Its modinfo for the reading user.
     * @return \cm_info|null The activity, or null when the format is not single-activity,
     *                       its activity type is unset or unavailable, or the configured
     *                       module is missing, being deleted, or not visible to the user.
     */
    private static function resolve_main_activity(\stdClass $course, \course_modinfo $modinfo): ?\cm_info {
        global $CFG;

        if (($course->format ?? '') !== 'singleactivity') {
            return null;
        }

        require_once($CFG->dirroot . '/course/format/lib.php');
        $options = course_get_format($course)->get_format_options();
        $activitytype = $options['activitytype'] ?? '';
        if ($activitytype === '' || !array_key_exists($activitytype, \format_singleactivity::get_supported_activities())) {
            // Unset, or names a type the format itself would not offer (no view page,
            // a subsection delegate, or hidden from the course by an admin).
            return null;
        }

        $found = null;
        foreach ($modinfo->sections[0] ?? [] as $cmid) {
            if ($modinfo->cms[$cmid]->modname === $activitytype) {
                // Core takes the first match in section 0 and stops there; mirror that
                // instead of continuing past it if this candidate fails our guards below.
                $found = $modinfo->cms[$cmid];
                break;
            }
        }

        if ($found === null || $found->deletioninprogress || !$found->uservisible) {
            return null;
        }

        return $found;
    }

    /**
     * The course's trackable, user-visible modules, up to a limit.
     *
     * @param \course_modinfo $modinfo The course modinfo.
     * @param \completion_info $completion The course completion info.
     * @param int $limit Stop once this many are found.
     * @return array List of cm_info.
     */
    private static function collect_trackable_cms(
        \course_modinfo $modinfo,
        \completion_info $completion,
        int $limit
    ): array {
        if (!$completion->is_enabled()) {
            return [];
        }

        $found = [];
        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->modname === 'subsection' || $cm->deletioninprogress || !$cm->uservisible) {
                continue;
            }
            if ($completion->is_enabled($cm) == \COMPLETION_TRACKING_NONE) {
                continue;
            }
            $found[] = $cm;
            if (count($found) >= $limit) {
                break;
            }
        }

        return $found;
    }

    /**
     * The sections the card would draw, mirroring the timeline's own filter.
     *
     * @param \course_modinfo $modinfo The course modinfo.
     * @return array List of section_info.
     */
    private static function collect_card_sections(\course_modinfo $modinfo): array {
        $sections = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            // A delegated section belongs to its subsection activity, never to the card.
            if (!empty($section->component)) {
                continue;
            }
            if (!$section->visible) {
                continue;
            }
            // Hidden entirely, with no availability text to show: the timeline skips it too.
            if (!$section->uservisible && empty($section->availableinfo)) {
                continue;
            }
            $sections[] = $section;
        }

        return $sections;
    }

    /**
     * Describe one activity for the card.
     *
     * @param \cm_info $cm The module.
     * @param \completion_info $completion The course completion info.
     * @param int $userid The user whose completion is read.
     * @return array Keys cmid, name, url, completed and tracked.
     */
    private static function describe_activity(\cm_info $cm, \completion_info $completion, int $userid): array {
        $tracked = $completion->is_enabled()
            && $completion->is_enabled($cm) != \COMPLETION_TRACKING_NONE;

        $completed = false;
        if ($tracked) {
            $data = $completion->get_data($cm, true, $userid);
            $completed = $data->completionstate == \COMPLETION_COMPLETE
                || $data->completionstate == \COMPLETION_COMPLETE_PASS;
        }

        return [
            'cmid' => (int) $cm->id,
            'name' => $cm->get_formatted_name(),
            'url' => $cm->url ? $cm->url->out(false) : '',
            'completed' => $completed,
            'tracked' => $tracked,
        ];
    }

    /**
     * Describe the card's single section.
     *
     * hasownname reports whether a teacher named the section: Moodle stores NULL when the
     * label is generated ("Topic 1", "General"), and repeating a generated label under the
     * course name informs nobody. The name is returned empty in that case rather than
     * filled with the generated label, so the caller cannot render it by accident.
     *
     * @param \stdClass $course The course record.
     * @param \section_info $section The section.
     * @return array Keys name, hasownname and url.
     */
    private static function describe_section(\stdClass $course, \section_info $section): array {
        $ownname = trim((string) ($section->name ?? ''));
        $context = \core\context\course::instance($course->id);

        return [
            'name' => $ownname !== '' ? format_string($ownname, true, ['context' => $context]) : '',
            'hasownname' => $ownname !== '',
            'url' => (new \moodle_url('/course/section.php', ['id' => $section->id]))->out(false),
        ];
    }
```

- [ ] **Step 5: Run the test to verify it passes**

Not runnable here — verify in CI. Expected: all six PASS.

- [ ] **Step 6: Check the PHP style traps**

```bash
php -l classes/calculator.php && php -l classes/constants.php && php -l tests/calculator_card_shape_test.php
```
```bash
awk 'length($0)>132{print FILENAME":"FNR" ("length($0)")"}' classes/calculator.php classes/constants.php tests/calculator_card_shape_test.php
```
Expected: no output from `awk`.

- [ ] **Step 7: Commit**

```bash
git add classes/calculator.php classes/constants.php tests/calculator_card_shape_test.php
git commit -m "feat(learner): resolve which shape a course card should take"
```

---

### Task 2: The tracker's server side reports the shape

**Files:**
- Modify: `classes/calculator.php` — `get_course_section_progress()`, both return statements
- Modify: `classes/external/get_course_progress.php` — `execute_returns()`
- Modify: `version.php`
- Test: `tests/external/get_course_progress_test.php` (append)

**Interfaces:**
- Consumes: `calculator::resolve_card_shape()` and the three `constants::CARDMODE_*` (Task 1).
- Produces: the tracker payload gains `cardmode` (string), `activity.tracked` (bool), `activity.cmid` (int) and `section` (`{name, hasownname, url}`, optional). Task 3 renders them.

- [ ] **Step 1: Write the failing test**

Append to the class in `tests/external/get_course_progress_test.php`:

```php
    /**
     * A single-activity course reports the activity shape and names its activity.
     *
     * @return void
     */
    public function test_execute_reports_the_activity_card_shape(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([
            'format' => 'singleactivity',
            'enablecompletion' => 1,
        ]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Submit portfolio',
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $this->setUser($user);
        $result = \core_external\external_api::clean_returnvalue(
            get_course_progress::execute_returns(),
            get_course_progress::execute([(int) $course->id])
        );

        $this->assertSame(\local_dimensions\constants::CARDMODE_ACTIVITY, $result[0]['cardmode']);
        $this->assertSame('Submit portfolio', $result[0]['activity']['name']);
        $this->assertTrue($result[0]['activity']['tracked']);
    }

    /**
     * A one-section course reports the section shape and carries the section's URL.
     *
     * @return void
     */
    public function test_execute_reports_the_section_card_shape(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([
            'numsections' => 0,
            'enablecompletion' => 1,
        ]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        foreach (['First', 'Second'] as $name) {
            $this->getDataGenerator()->create_module('page', [
                'course' => $course->id,
                'name' => $name,
                'completion' => COMPLETION_TRACKING_MANUAL,
            ]);
        }

        $this->setUser($user);
        $result = \core_external\external_api::clean_returnvalue(
            get_course_progress::execute_returns(),
            get_course_progress::execute([(int) $course->id])
        );

        $this->assertSame(\local_dimensions\constants::CARDMODE_SECTION, $result[0]['cardmode']);
        $this->assertFalse($result[0]['section']['hasownname']);
        $this->assertStringContainsString('/course/section.php', $result[0]['section']['url']);
    }
```

If the existing class already imports `external_api`, use the short name to match its
style rather than the fully-qualified one above.

- [ ] **Step 2: Run the tests to verify they fail**

Not runnable here. In CI, expect both to **FAIL** on an undefined `cardmode` key.

- [ ] **Step 3: Return the shape from `get_course_section_progress()`**

In `classes/calculator.php`, inside `get_course_section_progress()`, resolve the shape
once directly after `$modinfo`, `$sections` and `$completion` are set up:

```php
            /* One resolver answers the card's shape for both views, so the tracker and the
               plan can never disagree about the same course. */
            $shape = self::resolve_card_shape((int) $course->id, $USER->id);
```

Replace the early return used when course completion is off:

```php
            if (!$completion->is_enabled()) {
                return [
                    'enabled' => false,
                    'locked' => $locked,
                    'formatted_start_date' => $formattedstartdate,
                    'is_enrolment_start' => $isenrolmentstart,
                    'can_self_enrol' => $canselfenrol,
                    'is_future_date' => $isfuturedate,
                    'course_url' => $courseurl,
                    'sections' => [],
                    'cardmode' => $shape['mode'],
                    'activity' => $shape['activity'],
                    'section' => $shape['section'],
                ];
            }
```

Then delete the block that builds `$activity` from `$trackedcms` — the whole comment and
`if (count($trackedcms) === 1) { … }` — and replace the main return's last three lines:

```php
            return [
                'enabled' => true,
                'locked' => $locked,
                'formatted_start_date' => $formattedstartdate,
                'is_enrolment_start' => $isenrolmentstart,
                'can_self_enrol' => $canselfenrol,
                'is_future_date' => $isfuturedate,
                'course_url' => $courseurl,
                'sections' => $results,
                'cardmode' => $shape['mode'],
                'activity' => $shape['activity'],
                'section' => $shape['section'],
            ];
```

`$trackedcms` is still populated by the section loop and still used for the per-section
percentages; only the single-activity decision moves out.

- [ ] **Step 4: Declare the new keys in `execute_returns()`**

In `classes/external/get_course_progress.php`, add `cardmode` beside `enabled`, extend
the existing `activity` structure, and add `section` after it:

```php
                'cardmode' => new external_value(
                    PARAM_ALPHA,
                    'Which shape the card takes: activity, section or timeline',
                    VALUE_OPTIONAL,
                ),
```

```php
                'activity' => new external_single_structure(
                    [
                        'cmid' => new external_value(PARAM_INT, 'Course module id'),
                        'name' => new external_value(PARAM_TEXT, 'Activity name'),
                        'url' => new external_value(PARAM_URL, 'Activity URL'),
                        'completed' => new external_value(PARAM_BOOL, 'Whether the user completed the activity'),
                        'tracked' => new external_value(PARAM_BOOL, 'Whether completion is tracked for it'),
                    ],
                    'The course\'s single activity, present only when cardmode is activity',
                    VALUE_OPTIONAL,
                ),
                'section' => new external_single_structure(
                    [
                        'name' => new external_value(PARAM_TEXT, 'Section name, empty when Moodle generated it'),
                        'hasownname' => new external_value(PARAM_BOOL, 'Whether a teacher named the section'),
                        'url' => new external_value(PARAM_URL, 'URL of the section'),
                    ],
                    'The course\'s only section, present only when cardmode is section',
                    VALUE_OPTIONAL,
                ),
```

`activity` and `section` are `null` on the shapes that do not name them. A declared
`external_single_structure` rejects an explicit `null`, so **filter both keys out of the
row when they are null** rather than sending them — mirror how `get_competency_courses`
already omits its optional `activity` key instead of nulling it. Do this in
`get_course_progress::execute()`, where the row is assembled.

- [ ] **Step 5: Bump the version**

In `version.php`:

```php
$plugin->version = 2026072600;
```

No `db/upgrade.php` step: no schema change and no new web-service function.

- [ ] **Step 6: Run the tests to verify they pass**

Not runnable here — verify in CI. Expected: both new tests PASS, and every existing test
in `tests/calculator_progress_test.php` and `tests/external/get_course_progress_test.php`
still passes.

- [ ] **Step 7: Check the PHP style traps**

```bash
php -l classes/calculator.php && php -l classes/external/get_course_progress.php && php -l version.php
```
```bash
awk 'length($0)>132{print FILENAME":"FNR" ("length($0)")"}' classes/calculator.php classes/external/get_course_progress.php tests/external/get_course_progress_test.php
```

- [ ] **Step 8: Commit**

```bash
git add classes/calculator.php classes/external/get_course_progress.php version.php tests/external/get_course_progress_test.php
git commit -m "feat(learner): report the card shape from the tracker web service"
```

---

### Task 3: The tracker card renders the two compact bodies

**Files:**
- Modify: `templates/progress_card_body.mustache`
- Modify: `amd/src/competency_view.js` (remove the `issinglesection` derivation)
- Modify: `styles.css`
- Modify: `lang/en/local_dimensions.php`, `lang/pt_br/local_dimensions.php`
- Modify: `amd/build/competency_view.min.js` and `.map` (generated)

**Interfaces:**
- Consumes: `cardmode`, `activity` (`{cmid, name, url, completed, tracked}`) and `section` (`{name, hasownname, url}`) from Task 2.
- Produces: lang key `access_content`; CSS classes `local-dimensions-single-section`, `-single-section-ring`, `-single-section-name`, `-single-go`. Task 5 reuses the string and the classes.

- [ ] **Step 1: Add the lang string to both files, in alphabetical order**

`access_content` sorts near the top of both files, before `activities_count`.

`lang/en/local_dimensions.php`:

```php
$string['access_content'] = 'Access content';
```

`lang/pt_br/local_dimensions.php`:

```php
$string['access_content'] = 'Acessar conteúdo';
```

The label deliberately names neither the course nor the competency's own label: the
stored label options are plural and capitalised, a single destination needs the singular,
and Portuguese needs a gendered article that no rule derives.

- [ ] **Step 2: Switch the card body on `cardmode`**

In `templates/progress_card_body.mustache`, replace the `{{#activity}}` / `{{^activity}}`
pair that currently wraps everything below the completion-disabled block. The order is
load-bearing: the locked overlay still comes first and is unchanged; then the activity
shape, which is the one that survives completion being off; then the disabled message;
then the section shape; then the timeline.

```mustache
    {{#activity}}
    <div class="local-dimensions-single">
        <span class="local-dimensions-single-mark">
            {{#tracked}}
                {{#completed}}
                    <img class="local-dimensions-icon local-dimensions-icon-check" src="{{checkiconurl}}" alt="" aria-hidden="true">
                {{/completed}}
                {{^completed}}
                    <img class="local-dimensions-icon local-dimensions-icon-circle" src="{{circleiconurl}}" alt="" aria-hidden="true">
                {{/completed}}
            {{/tracked}}
        </span>
        <span class="local-dimensions-single-name">{{name}}</span>
        {{#tracked}}
        <span class="local-dimensions-single-status {{#completed}}local-dimensions-single-done{{/completed}}">
            {{#completed}}{{#str}}course_completed, local_dimensions{{/str}}{{/completed}}
            {{^completed}}{{#str}}filter_not_completed, local_dimensions{{/str}}{{/completed}}
        </span>
        {{/tracked}}
        {{#url}}
        <a href="{{url}}" class="local-dimensions-single-go">
            {{#str}}go_to_activity, local_dimensions{{/str}}<i class="fa fa-arrow-right" aria-hidden="true"></i>
        </a>
        {{/url}}
    </div>
    {{/activity}}

    {{^activity}}
    {{^enabled}}
        {{^locked}}
        <div class="p-3 text-muted text-center small">
            {{#str}}completion_disabled, local_dimensions{{/str}}
        </div>
        {{/locked}}
    {{/enabled}}

    {{#section}}
    <div class="local-dimensions-single-section">
        <span class="local-dimensions-single-section-ring">{{sectionpercentage}}%</span>
        {{#hasownname}}
        <span class="local-dimensions-single-section-name">{{name}}</span>
        {{/hasownname}}
        {{#url}}
        <a href="{{url}}" class="local-dimensions-single-go">
            {{#str}}access_content, local_dimensions{{/str}}<i class="fa fa-arrow-right" aria-hidden="true"></i>
        </a>
        {{/url}}
    </div>
    {{/section}}
    {{/activity}}
```

Then wrap the timeline half. Everything that today sits inside the old `{{^activity}}`
block — the `{{#iscompleted}}` seal, the `local-dimensions-timeline-toggle` button, the
`local-dimensions-timeline` element with its `{{#sections}}` loop, and the
`{{#hascontinue}}` link — moves **unchanged** inside a new `{{#istimeline}}` … `{{/istimeline}}`
pair, placed after the `{{#section}}` block and still inside the outer `{{^activity}}`.
Do not edit those lines while moving them; the section shape and the timeline simply
become mutually exclusive.

Expected structure when you are done, outermost first:

```
{{#locked}}          … overlay, unchanged …          {{/locked}}
{{#activity}}        … the activity body …           {{/activity}}
{{^activity}}
  {{^enabled}}{{^locked}} … completion-disabled message … {{/locked}}{{/enabled}}
  {{#section}}       … the section body …            {{/section}}
  {{#istimeline}}    … seal, toggle, timeline, continue … {{/istimeline}}
{{/activity}}
{{#error}}           … unchanged …                   {{/error}}
```

Move the completion-disabled block **out** of the old `{{^enabled}}` position and into
the structure above, as shown, so a single-activity course with completion off shows its
activity instead of the message.

Update the template's docblock context list with `cardmode`, `istimeline`,
`sectionpercentage`, `activity.tracked` and the `section` object, and extend its
`Example context (json)` block so the Mustache lint still renders — the lint fails on a
context that does not produce valid markup.

- [ ] **Step 3: Feed the template the two derived flags**

In `amd/src/competency_view.js`, delete the `issinglesection` derivation (the two comment
lines and the assignment) and replace it with the flags the template now needs:

```js
                /* One section and one activity are both the server's call now, and arrive
                   as data.cardmode. The template needs a boolean per branch, because
                   Mustache cannot compare a value. */
                data.istimeline = data.cardmode === 'timeline';
                /* A one-section course has exactly one row, and its percentage is the
                   course's own: every tracked activity lives in that section. */
                data.sectionpercentage = (data.sections && data.sections.length === 1)
                    ? (data.sections[0].percentage || 0)
                    : 0;
```

Also remove `issinglesection` from the template's usage: the timeline's
`local-dimensions-timeline-single` modifier is no longer reachable, because a course with
one section now renders the section shape instead. Delete that class from the
`{{#issinglesection}}` interpolation in the timeline element and drop its CSS rule if it
has one.

- [ ] **Step 4: Style the section body**

Append to `styles.css`, after the existing `.local-dimensions-single` rules so the two
compact bodies sit together:

```css
/* The one-section body: a percentage where the activity body puts a state marker. */
.local-dimensions-single-section {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
    padding: 1.25rem;
}

.local-dimensions-single-section-ring {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 3rem;
    height: 3rem;
    flex: none;
    border: 2px solid #198754;
    border-radius: 50%;
    color: #198754;
    font-size: 0.8125rem;
    font-weight: 700;
}

.local-dimensions-single-section-name {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.9375rem;
    font-weight: 600;
    color: #212529;
}

.local-dimensions-single-go {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    margin-left: auto;
    font-size: 0.8125rem;
    font-weight: 600;
    color: #0f6cbf;
    white-space: nowrap;
}
```

If `.local-dimensions-single-go` already exists for the activity body, do not duplicate
it — reuse the existing rule and only add what is missing.

- [ ] **Step 5: Lint and build**

```bash
cd /Volumes/N1TB/dev/github/moodle && npx eslint --max-warnings 0 public/local/dimensions/amd/src
```
```bash
cd /Volumes/N1TB/dev/github/moodle && npx stylelint --config .stylelintrc public/local/dimensions/styles.css
```
```bash
cd /Volumes/N1TB/dev/github/moodle && npx grunt amd --root=public/local/dimensions
```
Expected: eslint silent; stylelint reports only pre-existing `max-line-length` warnings
on untouched lines; grunt rebuilds `competency_view.min.js` and its `.map`.

- [ ] **Step 6: Commit**

```bash
git add templates/progress_card_body.mustache amd/src/competency_view.js amd/build/competency_view.min.js amd/build/competency_view.min.js.map styles.css lang/en/local_dimensions.php lang/pt_br/local_dimensions.php
git commit -m "feat(learner): give the tracker card a section body and a shape switch"
```

---

### Task 4: The plan's server side reports the shape

**Files:**
- Modify: `classes/external/get_competency_courses.php`
- Modify: `classes/calculator.php` (remove `resolve_single_activity()`)
- Delete: `tests/calculator_single_activity_test.php`
- Test: `tests/external/get_competency_courses_test.php` (amend one test, add one)

**Interfaces:**
- Consumes: `calculator::resolve_card_shape()` (Task 1).
- Produces: the plan payload's per-course row gains `cardmode` and `section`, and its
  `activity` gains `tracked`. Task 5 renders them.

- [ ] **Step 1: Amend the existing test and add the section case**

In `tests/external/get_competency_courses_test.php`, the existing
`test_execute_carries_the_single_trackable_activity` asserts `activity.name` and
`activity.cmid`. Add `cardmode` and `tracked` to it:

```php
        $this->assertSame(\local_dimensions\constants::CARDMODE_ACTIVITY, $rows[$single->id]['cardmode']);
        $this->assertTrue($rows[$single->id]['activity']['tracked']);
        $this->assertSame(\local_dimensions\constants::CARDMODE_TIMELINE, $rows[$many->id]['cardmode']);
```

Then append:

```php
    /**
     * A one-section course reports the section shape, with its own URL.
     *
     * @return void
     */
    public function test_execute_reports_the_section_card_shape(): void {
        $this->resetAfterTest();
        $competencyid = $this->set_up_competency();
        $course = $this->getDataGenerator()->create_course([
            'numsections' => 0,
            'enablecompletion' => 1,
        ]);
        \core_competency\api::add_competency_to_course($course->id, $competencyid);
        foreach (['First', 'Second'] as $name) {
            $this->getDataGenerator()->create_module('page', [
                'course' => $course->id,
                'name' => $name,
                'completion' => COMPLETION_TRACKING_MANUAL,
            ]);
        }
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $rows = $this->cleaned_result_for($competencyid, $user);

        $this->assertSame(\local_dimensions\constants::CARDMODE_SECTION, $rows[$course->id]['cardmode']);
        $this->assertFalse($rows[$course->id]['section']['hasownname']);
        $this->assertStringContainsString('/course/section.php', $rows[$course->id]['section']['url']);
    }
```

`cleaned_result_for()` routes the payload through `clean_returnvalue`, which is what makes
a missing allowlist entry fail the test rather than pass unnoticed.

- [ ] **Step 2: Run the tests to verify they fail**

Not runnable here. In CI, expect both to **FAIL** on an undefined `cardmode` key.

- [ ] **Step 3: Call the shared resolver**

In `classes/external/get_competency_courses.php`, replace the block that calls
`resolve_single_activity()` with:

```php
            /* The same resolver the tracker uses, so the two views cannot disagree about
               the shape of the same course. A locked or enrol-gated card keeps its state
               strip: naming an activity behind a lock helps nobody. */
            $row['cardmode'] = constants::CARDMODE_TIMELINE;
            if ($access === self::ACCESS_OPEN) {
                $shape = \local_dimensions\calculator::resolve_card_shape(
                    (int) $course->id,
                    (int) $USER->id
                );
                $row['cardmode'] = $shape['mode'];
                if ($shape['activity'] !== null) {
                    $row['activity'] = $shape['activity'];
                }
                if ($shape['section'] !== null) {
                    $row['section'] = $shape['section'];
                }
            }
```

Add `use local_dimensions\constants;` to the file's imports if it is not already there.
Keep omitting `activity` and `section` when null rather than setting them — a declared
`external_single_structure` rejects an explicit null.

- [ ] **Step 4: Declare the new keys**

In the same file's `execute_returns()`, add `cardmode` after `isenrolstart`, extend
`activity` with `tracked`, and add `section`:

```php
                'cardmode' => new external_value(
                    PARAM_ALPHA,
                    'Which shape the card takes: activity, section or timeline'
                ),
```

Inside the existing `activity` structure, after `completed`:

```php
                        'tracked' => new external_value(PARAM_BOOL, 'Whether completion is tracked for it'),
```

After the `activity` structure:

```php
                'section' => new external_single_structure(
                    [
                        'name' => new external_value(PARAM_TEXT, 'Section name, empty when Moodle generated it'),
                        'hasownname' => new external_value(PARAM_BOOL, 'Whether a teacher named the section'),
                        'url' => new external_value(PARAM_URL, 'URL of the section'),
                    ],
                    'The course\'s only section, present only when cardmode is section',
                    VALUE_OPTIONAL
                ),
```

- [ ] **Step 5: Remove the superseded resolver**

Delete `resolve_single_activity()` from `classes/calculator.php` — this task's change was
its only caller — and delete `tests/calculator_single_activity_test.php`, whose four cases
are covered by `tests/calculator_card_shape_test.php`.

```bash
git rm tests/calculator_single_activity_test.php
```

Then confirm nothing else references it:

```bash
grep -rn "resolve_single_activity" --include="*.php" --include="*.js" .
```
Expected: no output outside `docs/`.

- [ ] **Step 6: Run the tests to verify they pass**

Not runnable here — verify in CI. Expected: the amended test and the new one PASS, and
the other tests in that file still pass.

- [ ] **Step 7: Check the PHP style traps**

```bash
php -l classes/external/get_competency_courses.php && php -l classes/calculator.php
```
```bash
awk 'length($0)>132{print FILENAME":"FNR" ("length($0)")"}' classes/external/get_competency_courses.php classes/calculator.php tests/external/get_competency_courses_test.php
```

- [ ] **Step 8: Commit**

```bash
git add classes/external/get_competency_courses.php classes/calculator.php tests/external/get_competency_courses_test.php tests/calculator_single_activity_test.php
git commit -m "feat(learner): report the card shape from the plan web service"
```

---

### Task 5: The plan card renders both compact bodies

**Files:**
- Modify: `amd/src/accordion.js` — `renderCourseState`, and the card renderer's image and link handling
- Modify: `styles.css`
- Modify: `amd/build/accordion.min.js` and `.map` (generated)

**Interfaces:**
- Consumes: `cardmode`, `activity.tracked` and `section` from Task 4; the `access_content`
  string and the compact-body CSS classes from Task 3.
- Produces: nothing further depends on it.

- [ ] **Step 1: Fetch the new string**

In `renderCompetencySummary`, append to the `Str.get_strings([...])` array — at the **end**,
because `strMap` is built by numeric index and inserting anywhere else shifts every later
string:

```js
                {key: 'access_content', component: 'local_dimensions'}
```

Confirm the array's current last index before assigning, and map it accordingly, e.g.:

```js
                    accessContent: strings[86]
```

- [ ] **Step 2: Render the section body and honour `tracked`**

In `renderCourseState`, after the `locked` branch and before the current
`if (course.activity)` branch, add the section shape; and inside the activity branch,
suppress the marker and the state text when the activity is not tracked:

```js
            if (course.cardmode === 'section' && course.section) {
                let html = '<span class="local-dimensions-course-single">';
                html += '<span class="local-dimensions-course-single-pct">' +
                    (Number.parseInt(course.progress, 10) || 0) + '%</span>';
                if (course.section.hasownname) {
                    html += '<span class="local-dimensions-course-single-name">' +
                        escapeHtml(course.section.name) + '</span>';
                }
                html += '</span>';
                return html;
            }
```

In the activity branch, wrap the marker and the status span in `if (course.activity.tracked)`
so a single-activity course with completion off shows the name and the link alone.

- [ ] **Step 3: Give the compact modes two link targets and no image**

In the card renderer, the two compact modes drop the cover image and split the card's
single anchor into two targets — the course name links to the course, the call to action
links to the activity or the section. This reverses the previous slice's fix, which
pointed the whole card at the activity because it had only one target to give.

```js
                const iscompact = course.cardmode === 'activity' || course.cardmode === 'section';
```

Skip the image block entirely when `iscompact` is true. Keep the card's anchor pointing at
`courseUrl` in every mode — the `cardUrl` indirection added by the previous slice is no
longer needed, so remove it. Then, **after** the anchor closes and before the activities
drawer, emit the call to action as a real link:

```js
                if (iscompact) {
                    const golabel = course.cardmode === 'activity'
                        ? strMap.goToActivity
                        : strMap.accessContent;
                    const gourl = course.cardmode === 'activity'
                        ? (course.activity && course.activity.url)
                        : (course.section && course.section.url);
                    if (gourl) {
                        html += '<a href="' + escapeHtml(gourl) + '" class="local-dimensions-course-go">';
                        html += escapeHtml(golabel);
                        html += '<i class="fa fa-arrow-right" aria-hidden="true"></i>';
                        html += '</a>';
                    }
                }
```

Remove the `<span class="local-dimensions-course-go">` currently emitted inside
`renderCourseState`'s activity branch — the link above replaces it, and leaving both
would show the label twice.

The drawer-suppression check stays as it is: it already compares `cmid`, which the payload
still carries.

- [ ] **Step 4: Style the compact plan card**

In `styles.css`, add after the existing `.local-dimensions-course-single` rules:

```css
/* The compact modes carry no cover image, so the body starts at the card's top edge. */
.local-dimensions-course-card-compact .local-dimensions-course-body {
    padding-top: 0.8rem;
}

.local-dimensions-course-single-pct {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    flex: none;
    border: 2px solid #198754;
    border-radius: 50%;
    color: #198754;
    font-size: 0.6875rem;
    font-weight: 700;
}
```

Emit `local-dimensions-course-card-compact` on the card wrapper when `iscompact` is true.

- [ ] **Step 5: Lint and build**

```bash
cd /Volumes/N1TB/dev/github/moodle && npx eslint --max-warnings 0 public/local/dimensions/amd/src
```
Expected: silent. If `complexity` fires on the card renderer, extract the card body into
its own function rather than adding a disable comment — that is how the same situation was
resolved twice in the previous slice.

```bash
cd /Volumes/N1TB/dev/github/moodle && npx stylelint --config .stylelintrc public/local/dimensions/styles.css
```
```bash
cd /Volumes/N1TB/dev/github/moodle && npx grunt amd --root=public/local/dimensions
```

- [ ] **Step 6: Commit**

```bash
git add amd/src/accordion.js amd/build/accordion.min.js amd/build/accordion.min.js.map styles.css
git commit -m "feat(learner): align the plan card with the tracker's compact bodies"
```

---

### Task 6: Correct the kit and the field maps

**Files:**
- Modify: `docs/learner-kit/screens/trk-locked.html`
- Modify: `docs/learner-kit/screens/ovw-detail-courses.html`
- Modify: `docs/learner-kit/maps/viewcompetency.md`
- Modify: `docs/learner-kit/maps/viewplan.md`

**Interfaces:**
- Consumes: nothing. Documentation only.
- Produces: nothing.

- [ ] **Step 1: Split `TRK-CARD-SINGLE` in `trk-locked.html`**

The section currently groups two cards under one ID, and its first card gives the
conflation away with a **60% ring beside an activity name** — impossible for a single
activity, which can only ever be 0% or 100%.

Split it into two labelled sections:

- **`TRK-CARD-SINGLE`** — the `singleactivity`-format course. Keep the second card
  ("Final Assessment" / "Submit portfolio", green check, "Go to activity"). Add a second
  example with completion switched off: activity name and link, no marker and no state
  text. Say in the note that the trigger is the course format, not a count, and why.
- **`TRK-CARD-SECTION`** (new) — the one-section course. Move the 60% ring here, where it
  is possible, and label it with the section, not an activity. Its call to action is
  **"Access content"**, landing on the section. Say that the section name shows only when
  a teacher authored it, because Moodle generates "General" and "Topic 1" and repeating a
  generated label under the course name informs nobody.

- [ ] **Step 2: Update `ovw-detail-courses.html`**

`OVW-CRS-SINGLE` becomes the same two states, drawn **imageless** and with **two link
targets** — the course name to the course, the call to action to the activity or the
section. Keep the badge where it already sits: name → badge → compact body → drawer.
Note that this supersedes the previous slice's single-target fix, and why: the card had
only one target to give, so pointing it at the activity was the best available answer
until the two-target shape arrived.

- [ ] **Step 3: Update both field maps**

These are **as-is** inventories, so they describe what now ships:

- `maps/viewcompetency.md` — add `cardmode` and the two compact bodies; record that
  `issinglesection` is gone from the client and that the timeline's single-section
  modifier is no longer reachable.
- `maps/viewplan.md` — update `OVW-CRS-SINGLE` for the two shapes, the missing image and
  the two targets; add the section shape; note `resolve_single_activity` is replaced by
  `resolve_card_shape`.

- [ ] **Step 4: Commit**

```bash
git add docs/learner-kit
git commit -m "docs(learner-kit): separate the single-section and single-activity cards"
```

---

## Verification before handing back

- [ ] `npx eslint --max-warnings 0 public/local/dimensions/amd/src` — clean
- [ ] `npx stylelint --config .stylelintrc public/local/dimensions/styles.css` — no errors
- [ ] `git status` shows the rebuilt `amd/build` files alongside every commit that touched `amd/src`
- [ ] `version.php` reads `2026072600`
- [ ] `grep -rn "resolve_single_activity\|issinglesection" --include="*.php" --include="*.js" --include="*.mustache" .` returns nothing outside `docs/`
- [ ] Both lang files carry `access_content` in alphabetical order and have the same key count
- [ ] The PHPUnit suites are green **in CI** — they cannot run here

## Runtime check on the test site

```bash
ver=$(grep -oE '\$plugin->version[[:space:]]*=[[:space:]]*[0-9]+' /Volumes/N1TB/dev/github/moodle/public/local/dimensions/version.php | grep -oE '[0-9]+'); sha=$(git -C /Volumes/N1TB/dev/github/moodle/public/local/dimensions rev-parse --short HEAD); git -C /Volumes/N1TB/dev/github/moodle/public/local/dimensions archive --format=zip --prefix=dimensions/ HEAD -o ~/Downloads/dimensions-$ver-$sha.zip
```

What to look at, in order:

1. A course in the `singleactivity` format shows its activity name and "Go to activity" —
   in **both** views, looking the same in each.
2. The same course with completion switched off still shows the name and the link, with no
   state marker. This is the case that showed nothing useful before.
3. A course with one section and several activities shows a percentage and "Access
   content", landing on the section — not a one-row timeline.
4. A course with several sections is unchanged.
5. In the plan, the two compact cards carry **no cover image**, and the outcome badge sits
   where it does on every other card.
6. In the plan, the course name opens the course and the call to action opens the activity
   or section — two different destinations from one card.
