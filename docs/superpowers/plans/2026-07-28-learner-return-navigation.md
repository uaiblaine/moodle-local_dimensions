# Learner Return Navigation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the competency tracker its own return button and make the course FAB's label name the destination it actually holds, so "Return to plan" never delivers the tracker.

**Architecture:** Two independent return buttons sharing one template and one AMD module. The course FAB keeps its existing plumbing (footer hook reading the `returncontext` session cache) and gains a label derived from the cached URL's script name. The tracker renders the same template itself — the footer hook cannot reach it, because the page keeps core's default `base` pagelayout — and needs no cache at all, since the plan id is already a required parameter of the page. The related-competency pill, which opens a new tab, marks its URL so the tracker stays quiet there.

**Tech Stack:** Moodle 4.5–5.2 plugin (`local_dimensions`), PHP 8.1+, Moodle MUC session cache, Mustache templates, plain AMD (`define`), PHPUnit via Moodle's runner.

**Spec:** `docs/superpowers/specs/2026-07-28-learner-return-navigation-design.md`

## Global Constraints

- **Do not bump `version.php`.** The version is frozen; no schema change, no cache-definition change, no new web service in this work. A JavaScript cache revision is explicitly *not* a reason to bump — the site owner purges caches when installing a test zip.
- **Do not push.** Commit locally only. Pushing happens on the user's explicit command.
- **Do not add a `db/upgrade.php` step.** Nothing here changes the schema or a cache definition.
- All code, comments, commit messages and documentation in **English**.
- **phpcs runs only in CI** — there is no local runner. Before each commit, check the changed PHP by hand for: lines over 132 characters (soft max, and its warning count fails `phpdoc --max-warnings 0`); inline `//` comments that start lowercase or run over multiple lines (use a `/* … */` block instead); one space around `===`/`?`/`:`.
- **Never write to-do or merge-conflict marker tokens literally** in any file, including documentation — CI's development-leftover checker fails the build on them.
- Every PHP class, method, property and constant needs a docblock. `@param` array types must be the bare word `array`; put the shape in the prose.
- `lang/en/local_dimensions.php` and `lang/pt_br/local_dimensions.php` are kept **alphabetically sorted and in sync** — the `validate` CI step enforces the ordering.
- Every `amd/src` edit ships its rebuilt `amd/build/*.min.js` and `.map` **in the same commit**.

### PHPUnit prerequisites (once per machine session)

PHPUnit runs locally in this checkout. Docker Desktop must be running first.

```bash
docker start moodle-phpunit-pg || docker run -d --name moodle-phpunit-pg -e POSTGRES_USER=moodle -e POSTGRES_PASSWORD=moodle -e POSTGRES_DB=moodle -p 55432:5432 postgres:16
```

Use PHP 8.3, not the `php` on PATH:

```bash
/opt/homebrew/opt/php@8.3/bin/php -d max_input_vars=5000 /Volumes/N1TB/dev/github/moodle/public/admin/tool/phpunit/cli/util.php --buildconfig
```

Run the plugin's suite from the Moodle root:

```bash
cd /Volumes/N1TB/dev/github/moodle && /opt/homebrew/opt/php@8.3/bin/php -d max_input_vars=5000 vendor/bin/phpunit --no-coverage --testsuite local_dimensions_testsuite
```

## File Structure

| File | Responsibility |
|---|---|
| `classes/helper.php` | Gains two pure decisions: classify a stored return URL, and build the tracker's button context. Both sit beside the existing return-context methods (`set_return_context` at `:2095`, `get_return_context_for_course` at `:2123`). |
| `classes/hook_callbacks.php` | Course FAB. Consumes the classifier to pick a label. No other change. |
| `view-competency.php` | Reads the `related` marker and renders the tracker's button. |
| `amd/src/accordion.js` | The related-competency pill emits the marker. |
| `lang/{en,pt_br}/local_dimensions.php` | One new string each. |
| `db/caches.php` | Comment correction only. |
| `tests/helper_return_navigation_test.php` | New. First test coverage the return-context subsystem has ever had. |
| `CLAUDE.md` | Records the two new invariants. |

---

### Task 1: The destination classifier and the course FAB's label

Makes the existing course FAB say "Return to competency" when the stored context points at the tracker. Nothing else changes: same writers, same guards, same allowlist.

**Files:**
- Create: `tests/helper_return_navigation_test.php`
- Modify: `classes/helper.php` (insert after line 2130)
- Modify: `classes/hook_callbacks.php:96-105`
- Modify: `lang/en/local_dimensions.php` (insert before `:653`)
- Modify: `lang/pt_br/local_dimensions.php` (insert before `:653`)

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: `\local_dimensions\helper::return_destination_kind(string $url): string`, returning the literal `'competency'` or `'plan'`. Task 4 adds more tests to the same test file.

- [ ] **Step 1: Write the failing test**

Create `tests/helper_return_navigation_test.php`:

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

/**
 * Tests for the learner return-navigation helpers.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions;

use advanced_testcase;
use moodle_url;

/**
 * Tests for the return-context classification and the tracker's return button.
 *
 * @covers \local_dimensions\helper::return_destination_kind
 * @covers \local_dimensions\helper::tracker_return_context
 */
final class helper_return_navigation_test extends advanced_testcase {
    /**
     * A stored plan URL classifies as the plan.
     *
     * @return void
     */
    public function test_return_destination_kind_classifies_plan_url(): void {
        $url = (new moodle_url('/local/dimensions/view-plan.php', ['id' => 7]))->out(false);

        $this->assertSame('plan', helper::return_destination_kind($url));
    }

    /**
     * A stored tracker URL classifies as the competency, with or without the anti-loop flag.
     *
     * @return void
     */
    public function test_return_destination_kind_classifies_tracker_url(): void {
        $plain = (new moodle_url('/local/dimensions/view-competency.php', [
            'id' => 7,
            'competencyid' => 3,
        ]))->out(false);
        $flagged = (new moodle_url('/local/dimensions/view-competency.php', [
            'id' => 7,
            'competencyid' => 3,
            'noredirect' => 1,
        ]))->out(false);

        $this->assertSame('competency', helper::return_destination_kind($plain));
        $this->assertSame('competency', helper::return_destination_kind($flagged));
    }

    /**
     * Anything unrecognised falls back to the plan, the root of the journey.
     *
     * @return void
     */
    public function test_return_destination_kind_defaults_to_plan(): void {
        $this->assertSame('plan', helper::return_destination_kind('https://example.invalid/course/view.php?id=2'));
        $this->assertSame('plan', helper::return_destination_kind(''));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
cd /Volumes/N1TB/dev/github/moodle && /opt/homebrew/opt/php@8.3/bin/php -d max_input_vars=5000 vendor/bin/phpunit --no-coverage public/local/dimensions/tests/helper_return_navigation_test.php
```

Expected: FAIL — `Error: Call to undefined method local_dimensions\helper::return_destination_kind()`.

- [ ] **Step 3: Implement the classifier**

In `classes/helper.php`, insert immediately after `get_return_context_for_course()` closes at line 2130 (before the `count_frameworks_by_category` docblock that starts at 2132):

```php
    /**
     * Classify a stored return URL by the page it points at.
     *
     * The return context holds a bare URL string and no origin, so the button's
     * label is derived from its destination. Anything unrecognised is treated as
     * the plan: the plan is the root of the journey and every writer except the
     * tracker stores it.
     *
     * @param string $url The stored return URL.
     * @return string Either 'competency' or 'plan'.
     */
    public static function return_destination_kind(string $url): string {
        if (str_contains($url, '/local/dimensions/view-competency.php')) {
            return 'competency';
        }
        return 'plan';
    }
```

- [ ] **Step 4: Run the test to verify it passes**

```bash
cd /Volumes/N1TB/dev/github/moodle && /opt/homebrew/opt/php@8.3/bin/php -d max_input_vars=5000 vendor/bin/phpunit --no-coverage public/local/dimensions/tests/helper_return_navigation_test.php
```

Expected: PASS, 3 tests.

- [ ] **Step 5: Add the new string to both language files**

In `lang/en/local_dimensions.php`, insert a new line between `returnbuttoncolor_desc` (`:652`) and `returntoplan` (`:653`):

```php
$string['returntocompetency'] = 'Return to competency';
```

In `lang/pt_br/local_dimensions.php`, in the same slot between `:652` and `:653`:

```php
$string['returntocompetency'] = 'Voltar à competência';
```

Leave the neighbouring `returntoplan` alone for this step — its "Voltar ao Plano" capitalisation
is pre-existing here and out of scope for Task 1. A later fix (generalising the return-button
strings once both buttons shared one setting and colour) revisited it, lower-casing it to
"Voltar ao plano" to match its new sibling "Voltar à competência".

- [ ] **Step 6: Wire the label into the hook**

In `classes/hook_callbacks.php`, replace lines 96-105, which currently read:

```php
        // Get configured button color.
        $buttoncolor = get_config('local_dimensions', 'returnbuttoncolor') ?: '#0f6cbf';

        // Render the return button with iframe detection script.
        $renderer = $hook->renderer;
        $html = $renderer->render_from_template('local_dimensions/return_button', [
            'returnurl' => $context['url'],
            'label' => get_string('returntoplan', 'local_dimensions'),
            'buttoncolor' => $buttoncolor,
        ]);
```

with:

```php
        // Get configured button color.
        $buttoncolor = get_config('local_dimensions', 'returnbuttoncolor') ?: '#0f6cbf';

        /* Name the destination the context actually holds. Literal string keys
           only: the string checker cannot verify a constructed identifier. */
        $label = match (helper::return_destination_kind($context['url'])) {
            'competency' => get_string('returntocompetency', 'local_dimensions'),
            default => get_string('returntoplan', 'local_dimensions'),
        };

        // Render the return button with iframe detection script.
        $renderer = $hook->renderer;
        $html = $renderer->render_from_template('local_dimensions/return_button', [
            'returnurl' => $context['url'],
            'label' => $label,
            'buttoncolor' => $buttoncolor,
        ]);
```

- [ ] **Step 7: Verify the whole suite is still green**

```bash
cd /Volumes/N1TB/dev/github/moodle && /opt/homebrew/opt/php@8.3/bin/php -d max_input_vars=5000 vendor/bin/phpunit --no-coverage --testsuite local_dimensions_testsuite
```

Expected: PASS, no failures and no warnings.

- [ ] **Step 8: Check the changed PHP against the two rules phpcs will catch**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions && awk 'length($0)>132{print FILENAME":"FNR" ("length($0)")"}' classes/helper.php classes/hook_callbacks.php lang/en/local_dimensions.php lang/pt_br/local_dimensions.php tests/helper_return_navigation_test.php
```

Expected: no output. Then:

```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions && grep -nE '^\s*// [a-z]' classes/helper.php classes/hook_callbacks.php tests/helper_return_navigation_test.php | grep -v 'GNU General Public License\|it under the terms\|the Free Software\|(at your option)\|but WITHOUT ANY\|MERCHANTABILITY\|GNU General Public\|along with Moodle'
```

Expected: no output.

- [ ] **Step 9: Commit**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions && git add classes/helper.php classes/hook_callbacks.php lang/en/local_dimensions.php lang/pt_br/local_dimensions.php tests/helper_return_navigation_test.php && git commit -m "$(cat <<'EOF'
feat(learner): label the course FAB with the destination it holds

The return context stores one URL per course and no origin, so the button
read "Return to plan" even when the tracker had overwritten the plan URL
with its own. Classify the stored URL by the page it points at and pick
the matching string.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: The tracker's own return button

Ends the dead end. The tracker cannot receive the footer FAB — it leaves the page layout at core's default `base`, which the hook's `['course', 'incourse']` allowlist excludes — so it renders the same template itself.

**Files:**
- Modify: `classes/helper.php` (insert after the method added in Task 1)
- Modify: `view-competency.php:35` and `:146`
- Modify: `tests/helper_return_navigation_test.php`

**Interfaces:**
- Consumes: nothing from Task 1 at runtime; shares its test file.
- Produces: `\local_dimensions\helper::tracker_return_context(int $planid, bool $related): ?array`, returning `['returnurl' => string, 'label' => string, 'buttoncolor' => string]` or `null`.

- [ ] **Step 1: Write the failing tests**

Append these three methods to `tests/helper_return_navigation_test.php`, inside the class:

```php
    /**
     * The tracker's button points at the plan it was opened from.
     *
     * @return void
     */
    public function test_tracker_return_context_points_at_the_plan(): void {
        $this->resetAfterTest();
        set_config('enablereturnbutton', 1, 'local_dimensions');
        set_config('returnbuttoncolor', '#ff0000', 'local_dimensions');

        $context = helper::tracker_return_context(42, false);

        $this->assertNotNull($context);
        $expected = (new moodle_url('/local/dimensions/view-plan.php', ['id' => 42]))->out(false);
        $this->assertSame($expected, $context['returnurl']);
        $this->assertSame(get_string('returntoplan', 'local_dimensions'), $context['label']);
        $this->assertSame('#ff0000', $context['buttoncolor']);
    }

    /**
     * A tracker opened by a related-competency pill gets no button: it is a new tab.
     *
     * @return void
     */
    public function test_tracker_return_context_suppressed_when_related(): void {
        $this->resetAfterTest();
        set_config('enablereturnbutton', 1, 'local_dimensions');

        $this->assertNull(helper::tracker_return_context(42, true));
    }

    /**
     * The tracker's button honours the same feature switch as the course FAB.
     *
     * @return void
     */
    public function test_tracker_return_context_suppressed_when_feature_disabled(): void {
        $this->resetAfterTest();
        set_config('enablereturnbutton', 0, 'local_dimensions');

        $this->assertNull(helper::tracker_return_context(42, false));
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
cd /Volumes/N1TB/dev/github/moodle && /opt/homebrew/opt/php@8.3/bin/php -d max_input_vars=5000 vendor/bin/phpunit --no-coverage public/local/dimensions/tests/helper_return_navigation_test.php
```

Expected: FAIL — `Error: Call to undefined method local_dimensions\helper::tracker_return_context()`.

- [ ] **Step 3: Implement the builder**

In `classes/helper.php`, insert immediately after `return_destination_kind()` added in Task 1:

```php
    /**
     * Build the competency tracker's own return-button context.
     *
     * The tracker cannot receive the footer FAB: it leaves the page layout at
     * core's default 'base', which the hook's allowlist excludes. It needs no
     * return-context cache either, because the plan id is a required parameter
     * of the page, so this button is built from the request alone.
     *
     * @param int $planid The plan the tracker was opened from.
     * @param bool $related Whether a related-competency pill opened this page in a new tab.
     * @return array|null Keys returnurl, label and buttoncolor, or null when no button belongs here.
     */
    public static function tracker_return_context(int $planid, bool $related): ?array {
        if ($related || !get_config('local_dimensions', 'enablereturnbutton')) {
            return null;
        }

        return [
            'returnurl' => (new moodle_url('/local/dimensions/view-plan.php', ['id' => $planid]))->out(false),
            'label' => get_string('returntoplan', 'local_dimensions'),
            'buttoncolor' => get_config('local_dimensions', 'returnbuttoncolor') ?: '#0f6cbf',
        ];
    }
```

`moodle_url` is already imported at `classes/helper.php:36`.

- [ ] **Step 4: Run the tests to verify they pass**

```bash
cd /Volumes/N1TB/dev/github/moodle && /opt/homebrew/opt/php@8.3/bin/php -d max_input_vars=5000 vendor/bin/phpunit --no-coverage public/local/dimensions/tests/helper_return_navigation_test.php
```

Expected: PASS, 6 tests.

- [ ] **Step 5: Read the marker in the page**

In `view-competency.php`, after line 35 (`$competencyid = required_param('competencyid', PARAM_INT);`), add:

```php
/* Set by the related-competency pill, which opens a new tab. Deliberately kept
   out of $PAGE->set_url below: it must not leak into the URL this page caches
   for the course FAB, or the way back from a course would find no button. */
$related = optional_param('related', 0, PARAM_BOOL);
```

Do **not** add `related` to the `$PAGE->set_url()` call at `:43-46`.

- [ ] **Step 6: Render the button**

In `view-competency.php`, immediately after the line that reads

```php
echo $OUTPUT->render_from_template('local_dimensions/view_competency', $templatedata);
```

insert (anchor on that line's content, not on a number — Step 5 has already shifted
everything below it down by four lines):

```php

/* The tracker renders its own return button: the footer hook fires only on
   course-content layouts and this page keeps core's default 'base'. Outside the
   competency guard on purpose, because the empty state is where a learner has
   the fewest ways out. */
$returnbutton = \local_dimensions\helper::tracker_return_context($planid, $related);
if ($returnbutton !== null) {
    echo $OUTPUT->render_from_template('local_dimensions/return_button', $returnbutton);
    $PAGE->requires->js_call_amd('local_dimensions/return_button', 'init');
}
```

- [ ] **Step 7: Verify the whole suite is still green**

```bash
cd /Volumes/N1TB/dev/github/moodle && /opt/homebrew/opt/php@8.3/bin/php -d max_input_vars=5000 vendor/bin/phpunit --no-coverage --testsuite local_dimensions_testsuite
```

Expected: PASS, no failures and no warnings.

- [ ] **Step 8: Check for a second FAB and for the marker leaking**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions && grep -n "set_pagelayout" view-competency.php view-plan.php; grep -n "related" view-competency.php
```

Expected: no `set_pagelayout` hit at all (invariant N1), and `related` appearing only in the `optional_param` line, its comment, and the `tracker_return_context` call — never inside `set_url` (invariant N2).

- [ ] **Step 9: Check line lengths and comment style**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions && awk 'length($0)>132{print FILENAME":"FNR" ("length($0)")"}' classes/helper.php view-competency.php tests/helper_return_navigation_test.php
```

Expected: no output.

- [ ] **Step 10: Commit**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions && git add classes/helper.php view-competency.php tests/helper_return_navigation_test.php && git commit -m "$(cat <<'EOF'
feat(learner): give the competency tracker its own way back to the plan

The tracker was a dead end. It keeps core's default 'base' pagelayout, so
the footer hook's course-content allowlist excludes it, and the template
carries no anchor of its own — a learner arriving from a block competency
card, the product's default entry, had only the browser's Back button.

It renders the shared return-button template itself. No cache is involved:
the plan id is already a required parameter of the page. The button sits
outside the competency guard, because the empty state is where a learner
has the fewest ways out.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: The related-competency pill marks its new tab

The pill opens the tracker with `target="_blank"`, so a return button there would point at a plan still open in the other tab. The pill is the only thing that knows this, so it says so in the URL.

**Files:**
- Modify: `amd/src/accordion.js:2471-2477`
- Modify: `amd/build/accordion.min.js` and `amd/build/accordion.min.js.map` (generated)

**Interfaces:**
- Consumes: the `related` parameter read in Task 2's `view-competency.php` change.
- Produces: nothing for later tasks.

- [ ] **Step 1: Add the marker to the pill URL**

In `amd/src/accordion.js`, the block at lines 2471-2477 currently reads:

```javascript
            data.relatedcompetencies.forEach(function(related) {
                if (useLink && related.id) {
                    const href = displaySettings.viewcompetencyurl + '?id=' + planId + '&competencyid=' + related.id;
                    html += '<a href="' + escapeHtml(href) +
                        '" target="_blank" rel="noopener"' +
                        ' class="local-dimensions-related-pill-v2 local-dimensions-related-pill-link">'
                        + escapeHtml(related.shortname) + '</a>';
```

Replace the `const href` line with:

```javascript
                    // Opens a new tab, so related=1 tells the tracker to skip its own return button.
                    const href = displaySettings.viewcompetencyurl + '?id=' + planId
                        + '&competencyid=' + related.id + '&related=1';
```

Change nothing else — the pill keeps `target="_blank" rel="noopener"` and its `showrelatedlink` gate at `:2463`.

- [ ] **Step 2: Lint the source**

```bash
cd /Volumes/N1TB/dev/github/moodle && npx eslint --max-warnings 0 public/local/dimensions/amd/src/accordion.js
```

Expected: no output, exit 0. CI runs `grunt --max-lint-warnings 0`, so any warning here is a build failure.

- [ ] **Step 3: Rebuild the AMD bundle**

```bash
cd /Volumes/N1TB/dev/github/moodle && npx grunt amd --root=public/local/dimensions
```

Expected: `Done.` A plain grunt build does not fail on ESLint warnings, which is why Step 2 runs separately and first.

- [ ] **Step 4: Verify the marker reached the built file**

```bash
cd /Volumes/N1TB/dev/github/moodle && grep -c 'related=1' public/local/dimensions/amd/build/accordion.min.js
```

Expected: `1`. A `0` means grunt did not rebuild this module — do not commit until it is `1`.

- [ ] **Step 5: Commit source and build together**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions && git add amd/src/accordion.js amd/build/accordion.min.js amd/build/accordion.min.js.map && git commit -m "$(cat <<'EOF'
feat(learner): mark the related-competency pill's new tab

The pill opens the tracker with target=_blank, so a return button there
would lead to a plan the learner already has open in the other tab. The
pill is the only place that knows this, so it says so in the URL.

The marker does not reach the cache: the tracker's set_url declares only
id and competencyid, so the URL it stores for the course FAB never carries
it — a learner who descends into a course and comes back does get the
button, by which point a way out to the plan has become useful.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: Cover the cache API, correct its comments, record the invariants

The return-context cache has never had a test in either plugin, and `db/caches.php` documents it wrongly. This task closes both, and writes down the two invariants the new design depends on.

**Files:**
- Modify: `tests/helper_return_navigation_test.php`
- Modify: `db/caches.php:90-92`
- Modify: `CLAUDE.md:217-231`

**Interfaces:**
- Consumes: `helper::set_return_context` and `helper::get_return_context_for_course`, both pre-existing.
- Produces: nothing.

- [ ] **Step 1: Write the failing tests**

Append these three methods to `tests/helper_return_navigation_test.php`, inside the class:

```php
    /**
     * A write fans the same URL out to one cache entry per course.
     *
     * @return void
     */
    public function test_set_return_context_writes_one_entry_per_course(): void {
        $this->resetAfterTest();
        $url = new moodle_url('/local/dimensions/view-plan.php', ['id' => 9]);

        helper::set_return_context($url, [11, 12]);

        $expected = $url->out(false);
        $this->assertSame($expected, helper::get_return_context_for_course(11)['url']);
        $this->assertSame($expected, helper::get_return_context_for_course(12)['url']);
    }

    /**
     * A write with no courses is a silent no-op: it never clears what is stored.
     *
     * @return void
     */
    public function test_set_return_context_with_no_courses_writes_nothing(): void {
        $this->resetAfterTest();
        helper::set_return_context(new moodle_url('/local/dimensions/view-plan.php', ['id' => 9]), [13]);

        helper::set_return_context(new moodle_url('/local/dimensions/view-competency.php', [
            'id' => 9,
            'competencyid' => 4,
        ]), []);

        $this->assertStringContainsString('view-plan.php', helper::get_return_context_for_course(13)['url']);
    }

    /**
     * A course with no stored context reads back as null.
     *
     * @return void
     */
    public function test_get_return_context_for_course_returns_null_when_absent(): void {
        $this->resetAfterTest();

        $this->assertNull(helper::get_return_context_for_course(987654));
    }
```

Also extend the class docblock's `@covers` list so it names the two methods now under test:

```php
 * @covers \local_dimensions\helper::return_destination_kind
 * @covers \local_dimensions\helper::tracker_return_context
 * @covers \local_dimensions\helper::set_return_context
 * @covers \local_dimensions\helper::get_return_context_for_course
```

- [ ] **Step 2: Run the tests**

```bash
cd /Volumes/N1TB/dev/github/moodle && /opt/homebrew/opt/php@8.3/bin/php -d max_input_vars=5000 vendor/bin/phpunit --no-coverage public/local/dimensions/tests/helper_return_navigation_test.php
```

Expected: PASS, 9 tests. These three describe behaviour that already exists, so they pass on the first run — that is the point: they pin it before anyone changes it.

- [ ] **Step 3: Correct the cache documentation**

In `db/caches.php`, lines 90-92 currently read:

```php
    // Session cache for the "Return to Plan" button context.
    // Key: 'returncontext'
    // Value: serialised array with return URL and valid course IDs.
```

Replace with:

```php
    // Session cache for the "Return to Plan" button context.
    // Key: course_{courseid} - one entry per course, and the last writer wins.
    // Value: ['url' => string]. Which courses the button covers is expressed by
    // which keys exist, not by a list stored inside the value.
```

- [ ] **Step 4: Record the two invariants**

In `CLAUDE.md`, append to the end of the "Return-to-Plan FAB" paragraph that ends at line 231 with `session) — see `amd/src/return_button.js`.`:

```markdown
The tracker renders a **second, separate** return button of its own
(`helper::tracker_return_context`, echoed by `view-competency.php`), because the
hook cannot reach it: both learner views leave `$PAGE->pagelayout` at core's
default `base`, which the allowlist excludes. Two consequences are invariants.
**Never call `set_pagelayout('course'|'incourse')` in a learner view** — the hook
would then render a second FAB sharing the fixed DOM id
`local-dimensions-return-fab`, and the tracker would become a destination for
itself. And **never add `related` to the tracker's `$PAGE->set_url`** — the
related-competency pill sets it to suppress the tracker's button in the new tab it
opens, and leaking it into the cached URL would suppress the button on the way back
from a course too. The course FAB's label is derived from the cached URL
(`helper::return_destination_kind`), so a context pointing at the tracker reads
"Return to competency"; keep the mapping a literal `match`, since the string
checker cannot verify a constructed identifier.
```

- [ ] **Step 5: Verify the whole suite one last time**

```bash
cd /Volumes/N1TB/dev/github/moodle && /opt/homebrew/opt/php@8.3/bin/php -d max_input_vars=5000 vendor/bin/phpunit --no-coverage --testsuite local_dimensions_testsuite
```

Expected: PASS, no failures and no warnings.

- [ ] **Step 6: Confirm no version bump crept in**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions && git diff 857f39f --stat -- version.php db/upgrade.php
```

`857f39f` is the spec commit this work sits on, so the diff covers every task
regardless of how the commits were split. Expected: no output. Neither file may
appear in this work.

- [ ] **Step 7: Commit**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions && git add tests/helper_return_navigation_test.php db/caches.php CLAUDE.md && git commit -m "$(cat <<'EOF'
test(learner): pin the return-context cache and correct what it claims

The cache had no test in either plugin, and db/caches.php described a key
and a payload it has never had: the key is course_{courseid}, the value is
just ['url'], and the set of covered courses is which keys exist rather
than a list inside the value.

Also records the two invariants the tracker's own button depends on: no
course pagelayout on a learner view, and no related marker in the tracker's
set_url.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
EOF
)"
```

---

## Manual verification on the user's site

None of this is verifiable locally beyond PHPUnit — this checkout has no web site. After the four commits, build a test zip and check these five journeys on the running Moodle:

1. Plan overview → a competency (rule child or the "View detailed progress" footer link) → a course. The FAB reads **"Return to competency"** and lands on the tracker. The tracker shows a button reading **"Return to plan"** that lands on the plan.
2. Plan overview → a course card directly. The FAB reads **"Return to plan"**.
3. A related-competency pill. It opens a new tab and that tracker shows **no** button. Open a course from it and come back with the FAB: now the button **is** there.
4. A block competency card → tracker. The button is there even though the plan was never visited.
5. Turn `enablereturnbutton` off. No button on either surface.

Build the zip with the short SHA, which is what distinguishes builds while the version is frozen:

```bash
ver=$(grep -oE '\$plugin->version[[:space:]]*=[[:space:]]*[0-9]+' /Volumes/N1TB/dev/github/moodle/public/local/dimensions/version.php | grep -oE '[0-9]+') && sha=$(git -C /Volumes/N1TB/dev/github/moodle/public/local/dimensions rev-parse --short HEAD) && git -C /Volumes/N1TB/dev/github/moodle/public/local/dimensions archive --format=zip --prefix=dimensions/ HEAD -o ~/Downloads/dimensions-$ver-$sha.zip
```

---

### Task 5: The tracker's button only offers a plan overview the learner is routed to

Found in manual review after Tasks 1-4 landed, and it is the **default** configuration rather than an edge case.

The sibling plugin `block_dimensions` routes a learner by the plan's display mode: `DISPLAYMODE_PLAN` yields a plan card leading to `view-plan.php`, anything else yields competency cards leading straight to `view-competency.php` (`blocks/dimensions/classes/local/dataset_provider.php:124`). A templated plan whose template never set the field resolves to `DISPLAYMODE_COMPETENCIES` (`dataset_provider.php:232`, and the same default in `classes/template_metadata_cache.php:264`). So in the common case the learner **only ever sees competency cards** — and Task 2's button offers them a plan overview the configuration deliberately never routes to.

A plan with **no** template is the opposite case: the block defaults it to `DISPLAYMODE_PLAN` (`dataset_provider.php:228`), the learner does get a plan card, and the button is correct for them.

The rule this task adds: the tracker offers the plan overview only when the plan overview is a page this learner is actually routed to. In competency-card mode the tracker *is* their root, so `course → FAB → tracker` is arriving home rather than dead-ending.

**Files:**
- Modify: `classes/helper.php` (the `tracker_return_context` added in Task 2, plus one new method beside it)
- Modify: `view-competency.php:158`
- Modify: `tests/helper_return_navigation_test.php`
- Modify: `docs/superpowers/specs/2026-07-28-learner-return-navigation-design.md`
- Modify: `CLAUDE.md`

**Interfaces:**
- Consumes: `helper::tracker_return_context(int $planid, bool $related): ?array` from Task 2 — its signature changes here.
- Produces: `helper::plan_overview_is_routed(int $templateid): bool`, and `helper::tracker_return_context(int $planid, int $templateid, bool $related): ?array`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/helper_return_navigation_test.php`, inside the class. The existing `tracker_return_context` tests need their calls widened to the new signature — pass `0` as the templateid, which keeps them on the routed path.

The display mode lives in the `local_dimensions_displaymode` custom field on the learning-plan template. Follow the existing precedent in `tests/customfield/lp_handler_test.php`: `helper::find_field_by_shortname(constants::CFIELD_DISPLAYMODE, helper::AREA_LP)` to locate the field, then `lp_handler::create()->instance_form_save($formdata, true)` to write it. Call `helper::ensure_all_fields()` first so the fields exist, and invalidate `template_metadata_cache` for the template after writing so the read does not see a stale entry.

```php
    /**
     * A plan with no template is routed to the plan overview, matching the block's default.
     *
     * @return void
     */
    public function test_plan_overview_is_routed_without_a_template(): void {
        $this->assertTrue(helper::plan_overview_is_routed(0));
    }

    /**
     * A template that routes learners to competency cards suppresses the tracker's button.
     *
     * @return void
     */
    public function test_tracker_return_context_suppressed_in_competency_card_mode(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enablereturnbutton', 1, 'local_dimensions');

        $templateid = $this->create_template_with_displaymode(constants::DISPLAYMODE_COMPETENCIES);

        $this->assertFalse(helper::plan_overview_is_routed($templateid));
        $this->assertNull(helper::tracker_return_context(42, $templateid, false));
    }

    /**
     * A template that routes learners to the plan overview keeps the tracker's button.
     *
     * @return void
     */
    public function test_tracker_return_context_shown_in_plan_mode(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enablereturnbutton', 1, 'local_dimensions');

        $templateid = $this->create_template_with_displaymode(constants::DISPLAYMODE_PLAN);

        $this->assertTrue(helper::plan_overview_is_routed($templateid));
        $this->assertNotNull(helper::tracker_return_context(42, $templateid, false));
    }
```

Write the `create_template_with_displaymode(int $displaymode): int` private helper in the same test class, with its own docblock. Keep it small and give it a `@param` and a `@return`.

- [ ] **Step 2: Run the tests to verify they fail**

```bash
cd /Volumes/N1TB/dev/github/moodle && /opt/homebrew/opt/php@8.3/bin/php -d max_input_vars=5000 vendor/bin/phpunit --no-coverage public/local/dimensions/tests/helper_return_navigation_test.php
```

Expected: FAIL — `Error: Call to undefined method local_dimensions\helper::plan_overview_is_routed()`.

- [ ] **Step 3: Add the routing resolver**

In `classes/helper.php`, immediately before `tracker_return_context()`:

```php
    /**
     * Whether the plan overview is a page this plan's learners are routed to.
     *
     * The display mode is a template custom field, and block_dimensions routes on
     * it: DISPLAYMODE_PLAN yields a plan card leading to the overview, anything
     * else yields competency cards leading straight to the tracker. A plan with no
     * template has no such field, and the block treats it as plan mode.
     *
     * @param int $templateid The plan's template id, or 0 when it has none.
     * @return bool True when the overview is part of this learner's journey.
     */
    public static function plan_overview_is_routed(int $templateid): bool {
        if (!$templateid) {
            return true;
        }

        $metadata = template_metadata_cache::get_template_metadata($templateid);
        $displaymode = (int) ($metadata['displaymode'] ?? constants::DISPLAYMODE_COMPETENCIES);

        return $displaymode === constants::DISPLAYMODE_PLAN;
    }
```

- [ ] **Step 4: Widen `tracker_return_context` and gate on the resolver**

Change its signature to `tracker_return_context(int $planid, int $templateid, bool $related): ?array`, document the new parameter, and add the gate after the existing one:

```php
        if (!self::plan_overview_is_routed($templateid)) {
            return null;
        }
```

Extend the method's docblock to say the button is suppressed when the plan's display mode routes learners to competency cards, because the tracker is then their root.

- [ ] **Step 5: Pass the plan's template id at the call site**

In `view-competency.php:158`, change the call to pass `$templateid` — the **plan's** template id read at `:66`, **not** `$effectivetemplateid`. The display mode is a property of the plan, and `$effectivetemplateid` is deliberately zeroed when the competency is not in the plan (`:70-71`); using it would make a related competency's tracker look plan-routed when it is not.

- [ ] **Step 6: Run the tests to verify they pass**

```bash
cd /Volumes/N1TB/dev/github/moodle && /opt/homebrew/opt/php@8.3/bin/php -d max_input_vars=5000 vendor/bin/phpunit --no-coverage public/local/dimensions/tests/helper_return_navigation_test.php
```

Then the full suite:

```bash
cd /Volumes/N1TB/dev/github/moodle && /opt/homebrew/opt/php@8.3/bin/php -d max_input_vars=5000 vendor/bin/phpunit --no-coverage --testsuite local_dimensions_testsuite
```

- [ ] **Step 7: Update the spec**

In `docs/superpowers/specs/2026-07-28-learner-return-navigation-design.md`:

- Add a Decisions row: **when the tracker's button appears at all** — only when the plan overview is routed to, with the reasoning above.
- Update the navigation matrix rows that enter the tracker from the block, and any row implying the tracker always offers the button.
- Record the cross-plugin risk plainly: `local_dimensions` now encodes a routing rule that `block_dimensions` also implements. The plugin owns the *value* (it is a template custom field of this plugin) but the block owns the *routing*, so if the two defaults drift the button lies again. Name `dataset_provider.php:124` and `:228` as the code that must agree.

- [ ] **Step 8: Record the cross-plugin contract in CLAUDE.md**

Add to the "Return-to-Plan FAB" section: the tracker's button is gated on `helper::plan_overview_is_routed()`, which must keep agreeing with `block_dimensions`' `dataset_provider::resolve_plan_display_context()` — no template means plan mode, a template without the field means competency mode.

- [ ] **Step 9: Commit**

Two commits: the resolver, the gate, the call site and the tests; then the documentation.

---

### Task 6: The single-course redirect obeys the same routing rule

Found by Task 5's reviewer, out of that task's scope. `view-competency.php:123-131` writes the **plan overview** URL for the destination course whenever `singlecourseredirect` fires, unconditionally — reproducing in that branch exactly the defect Task 5 fixed in the tracker's own button. A learner in competency-card mode is redirected into a course and finds a button offering a view they have never seen.

The comment's stated reason — "this page would just redirect again" — is already answered by `noredirect=1`, which `$willredirect` honours at `view-competency.php:113`. Storing the tracker's own URL with that flag does not loop.

This task also repairs the spec content Task 5's change made stale.

**Files:**
- Modify: `classes/helper.php` (one new method beside `plan_overview_is_routed`)
- Modify: `view-competency.php:123-131`
- Modify: `tests/helper_return_navigation_test.php`
- Modify: `docs/superpowers/specs/2026-07-28-learner-return-navigation-design.md`
- Modify: `CLAUDE.md`

**Interfaces:**
- Consumes: `helper::plan_overview_is_routed(int $templateid): bool` from Task 5.
- Produces: `helper::redirect_return_url(int $planid, int $competencyid, int $templateid): moodle_url`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/helper_return_navigation_test.php`, inside the class, reusing the `create_template_with_displaymode()` helper Task 5 added:

```php
    /**
     * A plan whose learners are routed to the overview keeps the redirect pointing there.
     *
     * @return void
     */
    public function test_redirect_return_url_points_at_the_plan_when_routed(): void {
        $url = helper::redirect_return_url(42, 7, 0);

        $this->assertStringContainsString('/local/dimensions/view-plan.php', $url->out(false));
        $this->assertSame('plan', helper::return_destination_kind($url->out(false)));
    }

    /**
     * In competency-card mode the redirect points back at the tracker, carrying noredirect.
     *
     * @return void
     */
    public function test_redirect_return_url_points_at_the_tracker_in_competency_card_mode(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $templateid = $this->create_template_with_displaymode(constants::DISPLAYMODE_COMPETENCIES);
        $url = helper::redirect_return_url(42, 7, $templateid)->out(false);

        $this->assertStringContainsString('/local/dimensions/view-competency.php', $url);
        $this->assertStringContainsString('noredirect=1', $url);
        $this->assertSame('competency', helper::return_destination_kind($url));
    }
```

The second test pins the anti-loop invariant directly: the URL this branch stores must always carry `noredirect=1`, or a learner pressing the button would be bounced straight back into the course they just left.

- [ ] **Step 2: Run the tests to verify they fail**

```bash
cd /Volumes/N1TB/dev/github/moodle && /opt/homebrew/opt/php@8.3/bin/php -d max_input_vars=5000 vendor/bin/phpunit --no-coverage public/local/dimensions/tests/helper_return_navigation_test.php
```

Expected: FAIL — `Error: Call to undefined method local_dimensions\helper::redirect_return_url()`.

- [ ] **Step 3: Add the method**

In `classes/helper.php`, immediately after `plan_overview_is_routed()`:

```php
    /**
     * The URL a single-course redirect leaves behind for the destination course.
     *
     * When the plan overview is routed to, the course points back at it, because
     * this page would only redirect again. When it is not - competency-card mode -
     * the overview is a page the learner never sees, so the course points at the
     * tracker instead, carrying noredirect=1 so that the tracker renders rather
     * than bouncing the learner straight back into the course they just left.
     *
     * @param int $planid The plan being viewed.
     * @param int $competencyid The competency being viewed.
     * @param int $templateid The plan's template id, or 0 when it has none.
     * @return moodle_url The URL to store for the destination course.
     */
    public static function redirect_return_url(int $planid, int $competencyid, int $templateid): moodle_url {
        if (self::plan_overview_is_routed($templateid)) {
            return new moodle_url('/local/dimensions/view-plan.php', ['id' => $planid]);
        }

        return new moodle_url('/local/dimensions/view-competency.php', [
            'id' => $planid,
            'competencyid' => $competencyid,
            'noredirect' => 1,
        ]);
    }
```

- [ ] **Step 4: Use it at the call site**

In `view-competency.php`, replace the `$willredirect` branch's body — currently the comment at `:124-127` plus the `set_return_context_for_course()` call at `:128-131` — with:

```php
            /* Leave the destination course a button that points where this learner
               is actually routed: the plan overview when the block sends them there,
               and this tracker otherwise. The tracker URL carries noredirect=1, so
               pressing the button renders it instead of redirecting again. */
            \local_dimensions\helper::set_return_context_for_course(
                (int) reset($courses)->id,
                \local_dimensions\helper::redirect_return_url($planid, $competencyid, $templateid)
            );
```

Pass `$templateid` — the plan's own, read at `:66` — never `$effectivetemplateid`.

- [ ] **Step 5: Run the tests to verify they pass**

```bash
cd /Volumes/N1TB/dev/github/moodle && /opt/homebrew/opt/php@8.3/bin/php -d max_input_vars=5000 vendor/bin/phpunit --no-coverage public/local/dimensions/tests/helper_return_navigation_test.php
```

Then the full suite:

```bash
cd /Volumes/N1TB/dev/github/moodle && /opt/homebrew/opt/php@8.3/bin/php -d max_input_vars=5000 vendor/bin/phpunit --no-coverage --testsuite local_dimensions_testsuite
```

- [ ] **Step 6: Repair the stale spec content**

Task 5 changed `tracker_return_context`'s signature and Task 6 changes the redirect branch, leaving earlier sections of `docs/superpowers/specs/2026-07-28-learner-return-navigation-design.md` describing code that no longer exists. Fix all of it:

- The code sample showing the two-argument `tracker_return_context($planid, $related)` call.
- The "Code changes" table row for `tracker_return_context`, whose signature and null-conditions are both now incomplete: it must name the display-mode gate.
- The "Code changes" table, which needs rows for `plan_overview_is_routed` and `redirect_return_url`.
- The "Tests" table, which lists neither Task 5's three tests nor Task 6's two.
- The navigation matrix row for the single-course redirect, which states the cached URL is the plan overview. It now depends on the display mode.

Read each section before editing it, and make every claim true of the code as it now stands.

- [ ] **Step 7: Correct the invariant in CLAUDE.md**

The "Return-to-Plan FAB" section states the anti-loop invariant as: when the page redirects it writes the plan URL for the destination course. That is now conditional. Reword it so the invariant that survives is the one that actually holds — every `view-competency.php` URL the button stores carries `noredirect=1` — and the destination is described as following the display-mode routing.

- [ ] **Step 8: Commit**

Two commits: the method, the call site and the tests; then the documentation repairs.
