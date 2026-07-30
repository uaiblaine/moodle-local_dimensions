# aiplacement_dimensions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A new `aiplacement_dimensions` plugin that suggests competencies for the activity being edited, resolves the model's answer to real competency IDs server-side, and pre-fills the form's own competencies field so the native Save creates the link.

**Architecture:** One read-only external function owns prompt building, the model call and resolution. Writes are delegated: the course link goes to `local_dimensions_link_competency_course`, and the module link is never written by us — we append `<option selected>` to the hidden select that `tool_lp` already renders, and `tool_lp_coursemodule_edit_post_actions` creates the link on Save. The model never returns names: it returns **indices** into a numbered candidate list built server-side, so a wrong match is structurally impossible.

**Tech Stack:** Moodle 5.1–5.3 `aiplacement` subplugin, PHP 8.2+, `core_ai` (`generate_text`), `core_competency`, plain AMD (`define`), Mustache, PHPUnit and Behat via Moodle's runner.

**Spec:** `docs/superpowers/specs/2026-07-29-aiplacement-dimensions-design.md`

**Repository:** this plugin is a **new repository** at `public/ai/placement/dimensions/`, sibling to `local_dimensions` — not a directory inside it. The spec and this plan live in the `local_dimensions` repo because that is where the design conversation happened; Task 1 creates the new repo.

## Global Constraints

- **Moodle 5.1 to 5.3.** `$plugin->requires = 2025100600`, `$plugin->supported = [501, 503]`. `supported` must be an array of exactly two ascending ints (`lib/classes/plugininfo/base.php:311-317`).
- **Hard dependency:** `$plugin->dependencies = ['local_dimensions' => 2026072801]` (the v2.0 anchor).
- **`MOODLE_503_STABLE` does not exist upstream yet.** CI covers 5.1 and 5.2 only. Add the 5.3 leg when the branch appears.
- **Do not push.** Commit locally only. Pushing happens on the user's explicit command.
- All code, comments, commit messages and documentation in **English**.
- **Never write to-do or merge-conflict marker tokens literally** in any file, including documentation — CI's development-leftover checker fails the build on them.
- Every PHP class, method, property and constant needs a docblock. `@param` array types must be the bare word `array`; put the shape in the prose.
- Hand-check changed PHP before each commit for: lines over 132 characters; inline `//` comments that start lowercase or run over multiple lines (use a `/* … */` block instead); one space around `===`/`?`/`:`.
- `lang/en/aiplacement_dimensions.php` and `lang/pt_br/aiplacement_dimensions.php` are kept **alphabetically sorted and in sync** — the `validate` CI step enforces the ordering.
- Every `amd/src` edit ships its rebuilt `amd/build/*.min.js` and `.map` **in the same commit**.
- **No new database tables.** No `db/install.xml`, no `db/upgrade.php`. Suggestions are ephemeral.
- **Never write the module competency link.** See Task 6.

### PHPUnit prerequisites (once per machine session)

Docker Desktop must be running first.

```bash
docker start moodle-phpunit-pg || docker run -d --name moodle-phpunit-pg -e POSTGRES_USER=moodle -e POSTGRES_PASSWORD=moodle -e POSTGRES_DB=moodle -p 55432:5432 postgres:16
```

Then, once per session, create the ini override that the Moodle CLI needs (the `-d` flag does **not** reach the subprocesses `init.php` spawns):

```bash
mkdir -p /tmp/phpini && printf 'max_input_vars=5000\nmemory_limit=512M\n' > /tmp/phpini/99-moodle.ini
```

Every PHPUnit command in this plan is prefixed with `PHP_INI_SCAN_DIR="/opt/homebrew/etc/php/8.5/conf.d:/tmp/phpini"`. After adding or renaming a test file, or after Task 1 creates the plugin, re-run init so `phpunit.xml` picks up the new testsuite:

```bash
cd /Volumes/N1TB/dev/github/moodle
PHP_INI_SCAN_DIR="/opt/homebrew/etc/php/8.5/conf.d:/tmp/phpini" php public/admin/tool/phpunit/cli/init.php --disable-composer
```

`--disable-composer` is required: PHP 8.5 exposes a `lib-curl-boringssl` platform package that Composer 2.10 rejects, and `init.php` runs `composer install` first.

---

## File Structure

| File | Responsibility |
|---|---|
| `version.php` | Metadata, version support range, hard dependency |
| `lib.php` | `aiplacement_dimensions_coursemodule_standard_elements()` — the six availability gates and the button |
| `classes/placement.php` | `get_action_list()` → `[generate_text::class]`. Nothing else |
| `classes/local/candidates.php` | **DB-facing.** Walks the competency subtree for the chosen roots |
| `classes/local/prompt.php` | **Pure.** Turns a competency array into a numbered candidate list plus prompt text |
| `classes/local/resolver.php` | **Pure.** Turns the model's raw answer into resolved suggestions plus a discard count |
| `classes/external/suggest_competencies.php` | The only external function. Gates, orchestration, typed return |
| `amd/src/suggest.js` | Own drawer, the four-step flow, and apply. Extends nothing |
| `templates/*.mustache` | Drawer, framework/branch pickers, suggestion list, empty and error states |
| `db/services.php`, `db/access.php`, `lang/{en,pt_br}/`, `classes/privacy/provider.php` | Wiring |

**Deviation from the spec, recorded deliberately:** the spec listed `prompt.php` and `resolver.php`. This plan adds a third unit, `candidates.php`, so that the DB walk is separable from the pure list-building. That keeps `prompt` and `resolver` free of `$DB` and testable without a site, which is the whole point of extracting them.

---

## Task 1: Repository, scaffold, and green CI

**Files:**
- Create: `public/ai/placement/dimensions/version.php`
- Create: `public/ai/placement/dimensions/classes/placement.php`
- Create: `public/ai/placement/dimensions/classes/privacy/provider.php`
- Create: `public/ai/placement/dimensions/db/access.php`
- Create: `public/ai/placement/dimensions/lang/en/aiplacement_dimensions.php`
- Create: `public/ai/placement/dimensions/.github/workflows/ci.yml`
- Create: `public/ai/placement/dimensions/.gitattributes`, `.gitignore`, `.moodle-plugin-ci.yml`, `README.md`
- Test: `public/ai/placement/dimensions/tests/plugin_test.php`

**Interfaces:**
- Consumes: nothing.
- Produces: the component name `aiplacement_dimensions`; the capability `aiplacement/dimensions:suggest`; `\aiplacement_dimensions\placement::get_action_list(): array`.

- [ ] **Step 1: Create the plugin directory and initialise the repository**

```bash
mkdir -p /Volumes/N1TB/dev/github/moodle/public/ai/placement/dimensions
cd /Volumes/N1TB/dev/github/moodle/public/ai/placement/dimensions
git init -q && git symbolic-ref HEAD refs/heads/main
```

- [ ] **Step 2: Write `version.php`**

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
 * Plugin version and other metadata.
 *
 * @package    aiplacement_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component    = 'aiplacement_dimensions';
$plugin->version      = 2026072900;
$plugin->requires     = 2025100600;
$plugin->supported    = [501, 503];
$plugin->maturity     = MATURITY_ALPHA;
$plugin->release      = 'v0.1';
$plugin->dependencies = ['local_dimensions' => 2026072801];
```

- [ ] **Step 3: Write `classes/placement.php`**

Use the same licence header as Step 2 in every PHP file from here on; it is omitted below for brevity but is required.

```php
namespace aiplacement_dimensions;

/**
 * AI placement for competency suggestions on the activity form.
 *
 * @package    aiplacement_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class placement extends \core_ai\placement {
    /**
     * Get the list of AI actions this placement supports.
     *
     * @return array Action class names.
     */
    public static function get_action_list(): array {
        return [\core_ai\aiactions\generate_text::class];
    }
}
```

`get_action_list()` is the only abstract method on `\core_ai\placement` (`ai/classes/placement.php:34`). Do not add defensive `method_exists()` probes anywhere in this plugin.

- [ ] **Step 4: Write `db/access.php`**

```php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'aiplacement/dimensions:suggest' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
        ],
    ],
];
```

`read` is correct: this capability gates asking the model, not writing. The writes are gated by `moodle/competency:coursecompetencymanage`, which core enforces in `api::add_competency_to_course` (`competency/classes/api.php:1625`).

- [ ] **Step 5: Write `lang/en/aiplacement_dimensions.php` (alphabetically sorted)**

```php
defined('MOODLE_INTERNAL') || die();

$string['dimensions:suggest'] = 'Suggest competencies with AI';
$string['pluginname'] = 'AI competency suggestions';
$string['privacy:metadata'] = 'The AI competency suggestions placement does not store any personal data. Activity content is sent to the configured AI provider, which records the request under the core AI subsystem.';
```

- [ ] **Step 6: Write `classes/privacy/provider.php`**

```php
namespace aiplacement_dimensions\privacy;

/**
 * Privacy provider.
 *
 * @package    aiplacement_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\null_provider {
    /**
     * Get the reason why this plugin stores no data.
     *
     * @return string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
```

The null provider is accurate here **only because** the request itself is recorded by `core_ai`, under `core_ai`'s own privacy metadata, and this plugin stores nothing of its own. Revisit this file the moment any provenance is stored.

- [ ] **Step 7: Write `.gitattributes` (release hygiene) and `.gitignore`**

```
# Release hygiene — `git archive HEAD` builds the install zip, so every tracked
# path lands in the package unless it is export-ignore'd here.
#
# Verify with:  git check-attr export-ignore -- <path>

docs                  export-ignore
.github               export-ignore
.moodle-plugin-ci.yml export-ignore
.gitignore            export-ignore
.gitattributes        export-ignore
```

`.gitignore` must include `.DS_Store` — a tracked `.DS_Store` shipped inside every release zip of the plugin this one replaces, because `rsync --exclude='.git*'` does not match it.

`.moodle-plugin-ci.yml`:

```yaml
filter:
  notPaths:
    - node_modules
    - vendor
```

- [ ] **Step 8: Write `.github/workflows/ci.yml`**

This mirrors the house pattern in `local_dimensions`: one reusable-workflow call per supported branch, highest branch runs the full matrix. The floor is 5.1, so there is no 5.0 leg, and `MOODLE_503_STABLE` does not exist yet.

```yaml
name: ci

on:
  push:
  pull_request:
  workflow_dispatch:

jobs:
  # One reusable-workflow call per supported Moodle branch (version.php
  # $plugin->supported). MOODLE_503_STABLE does not exist upstream yet; add its
  # leg when the branch appears, so the top of the declared range is tested.
  ci-502:
    name: Moodle 5.02
    uses: moodle-an-hochschulen/moodle-workflows/.github/workflows/moodle-plugin-ci.yml@main
    with:
      moodle-core-branch: MOODLE_502_STABLE
      plugin-dependencies: |
        uaiblaine/moodle-local_dimensions,main

  ci-501:
    name: Moodle 5.01
    uses: moodle-an-hochschulen/moodle-workflows/.github/workflows/moodle-plugin-ci.yml@main
    with:
      moodle-core-branch: MOODLE_501_STABLE
      one-db-only: true
      plugin-dependencies: |
        uaiblaine/moodle-local_dimensions,main
```

The `plugin-dependencies` line is mandatory: this plugin declares a hard dependency, so CI cannot install it without `local_dimensions` present. The input name and its `owner/repo,branch` value shape were verified against the reusable workflow's source during Task 1 — an earlier draft of this plan named a non-existent `extra-plugin-runners` input taking a CLI command string.

- [ ] **Step 9: Write the smoke test `tests/plugin_test.php`**

```php
namespace aiplacement_dimensions;

/**
 * Tests for plugin registration.
 *
 * @package    aiplacement_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiplacement_dimensions\placement
 */
final class plugin_test extends \advanced_testcase {
    /**
     * The placement declares exactly the generate_text action.
     *
     * @return void
     */
    public function test_action_list(): void {
        $this->assertSame(
            [\core_ai\aiactions\generate_text::class],
            placement::get_action_list()
        );
    }

    /**
     * The suggest capability is declared at module level.
     *
     * @return void
     */
    public function test_capability_is_declared(): void {
        $this->resetAfterTest();
        $caps = get_all_capabilities();
        $this->assertArrayHasKey('aiplacement/dimensions:suggest', $caps);
        $this->assertSame(CONTEXT_MODULE, (int) $caps['aiplacement/dimensions:suggest']['contextlevel']);
    }
}
```

This asserts against the runtime capability registry, not against a re-`require` of `db/access.php`. Re-requiring the file and asserting the array contains what the array contains is a tautology and was one of the audited anti-patterns.

- [ ] **Step 10: Re-init PHPUnit so the new testsuite is registered, then run it**

```bash
cd /Volumes/N1TB/dev/github/moodle
PHP_INI_SCAN_DIR="/opt/homebrew/etc/php/8.5/conf.d:/tmp/phpini" php public/admin/tool/phpunit/cli/init.php --disable-composer
PHP_INI_SCAN_DIR="/opt/homebrew/etc/php/8.5/conf.d:/tmp/phpini" php vendor/bin/phpunit --testdox --testsuite aiplacement_dimensions_testsuite
```

Expected: 2 tests, 2 passing. If you get `No tests executed!`, you passed a directory instead of `--testsuite`.

- [ ] **Step 11: Commit**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/ai/placement/dimensions
git add -A
git commit -m "feat: scaffold the aiplacement_dimensions placement

Declares generate_text, the suggest capability, a null privacy provider,
and CI covering Moodle 5.1 and 5.2."
```

---

## Task 2: `prompt` — the numbered candidate list (pure)

**Files:**
- Create: `classes/local/prompt.php`
- Test: `tests/local/prompt_test.php`

**Interfaces:**
- Consumes: nothing. This unit touches neither `$DB` nor `core_ai`.
- Produces: `\aiplacement_dimensions\local\prompt::build(array $competencies, string $content, int $budget = 200): array`, returning the keys `candidates` (a list, **0-indexed internally, presented to the model as 1..N**), `text` (string), `candidatecount` (int, the count *before* truncation), `truncated` (bool). Each candidate is an array with keys `id`, `idnumber`, `shortname`.

- [ ] **Step 1: Write the failing test**

`tests/local/prompt_test.php`:

```php
namespace aiplacement_dimensions\local;

/**
 * Tests for the prompt builder.
 *
 * @package    aiplacement_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiplacement_dimensions\local\prompt
 */
final class prompt_test extends \basic_testcase {
    /**
     * Build a competency row as the fetcher returns it.
     *
     * @param int $id Competency id.
     * @param string $short Shortname.
     * @return array
     */
    private function row(int $id, string $short): array {
        return ['id' => $id, 'idnumber' => 'K' . $id, 'shortname' => $short];
    }

    /**
     * Candidates keep fetch order and are numbered from one in the text.
     *
     * @return void
     */
    public function test_numbering_starts_at_one_and_follows_order(): void {
        $result = prompt::build([$this->row(7, 'Alpha'), $this->row(3, 'Beta')], 'some content');

        $this->assertSame(7, $result['candidates'][0]['id']);
        $this->assertSame(3, $result['candidates'][1]['id']);
        $this->assertStringContainsString('1. Alpha', $result['text']);
        $this->assertStringContainsString('2. Beta', $result['text']);
        $this->assertSame(2, $result['candidatecount']);
        $this->assertFalse($result['truncated']);
    }

    /**
     * Exceeding the budget truncates and says so.
     *
     * @return void
     */
    public function test_budget_truncates_and_reports(): void {
        $rows = [];
        for ($i = 1; $i <= 5; $i++) {
            $rows[] = $this->row($i, 'C' . $i);
        }

        $result = prompt::build($rows, 'some content', 3);

        $this->assertCount(3, $result['candidates']);
        $this->assertSame(5, $result['candidatecount']);
        $this->assertTrue($result['truncated']);
        $this->assertStringNotContainsString('C4', $result['text']);
    }

    /**
     * An empty candidate set is not an error.
     *
     * @return void
     */
    public function test_empty_candidates(): void {
        $result = prompt::build([], 'some content');

        $this->assertSame([], $result['candidates']);
        $this->assertSame(0, $result['candidatecount']);
        $this->assertFalse($result['truncated']);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
cd /Volumes/N1TB/dev/github/moodle
PHP_INI_SCAN_DIR="/opt/homebrew/etc/php/8.5/conf.d:/tmp/phpini" php vendor/bin/phpunit --filter prompt_test --testsuite aiplacement_dimensions_testsuite
```

Expected: FAIL — `Class "aiplacement_dimensions\local\prompt" not found`. If instead you get `No tests executed!`, re-run `init.php` (the new test file needs registering).

- [ ] **Step 3: Write the implementation**

`classes/local/prompt.php`:

```php
namespace aiplacement_dimensions\local;

/**
 * Builds the numbered candidate list and the instruction text sent to the model.
 *
 * This class is deliberately free of $DB and core_ai so it can be unit tested
 * without a site. The candidate array it returns is the single source of truth
 * for resolution: the model answers with positions in this list, never names.
 *
 * @package    aiplacement_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class prompt {
    /** @var int Default maximum number of candidates sent to the model. */
    public const DEFAULT_BUDGET = 200;

    /**
     * Build the candidate list and prompt text.
     *
     * @param array $competencies Rows with keys id, idnumber, shortname, in fetch order.
     * @param string $content The activity content to classify.
     * @param int $budget Maximum candidates to send.
     * @return array Keys: candidates, text, candidatecount, truncated.
     */
    public static function build(array $competencies, string $content, int $budget = self::DEFAULT_BUDGET): array {
        $all = array_values($competencies);
        $total = count($all);
        $candidates = array_slice($all, 0, $budget);
        $truncated = $total > count($candidates);

        $lines = [];
        foreach ($candidates as $index => $candidate) {
            $lines[] = ($index + 1) . '. ' . $candidate['shortname'];
        }

        $text = get_string('promptinstruction', 'aiplacement_dimensions', (object) [
            'list' => implode("\n", $lines),
            'content' => $content,
        ]);

        return [
            'candidates' => $candidates,
            'text' => $text,
            'candidatecount' => $total,
            'truncated' => $truncated,
        ];
    }
}
```

- [ ] **Step 4: Add the prompt lang string**

Insert into `lang/en/aiplacement_dimensions.php`, keeping alphabetical order. It sorts **after** `privacy:metadata`, not before: the two diverge at the third character, and `i` precedes `o`. Do not take this plan's word for it — run `sort()` over the resulting key list and compare.

```php
$string['promptinstruction'] = 'You are mapping educational content to competencies.

CANDIDATE COMPETENCIES (choose only from this numbered list):
{$a->list}

CONTENT TO CLASSIFY:
{$a->content}

Return JSON only, with no prose and no markdown fence:
{"picks": [{"n": 1, "confidence": 0.0, "why": "one short sentence"}]}

Rules:
1) "n" must be a number from the list above. Never invent a number outside it.
2) Do not invent competency names or codes. You are choosing positions, not writing names.
3) If you are not confident a competency genuinely applies, leave it out.
4) Return {"picks": []} if nothing clearly applies. An empty answer is a valid and useful answer.
5) "confidence" is between 0 and 1. "why" is one short sentence naming the evidence in the content.';
```

- [ ] **Step 5: Run the test to verify it passes**

```bash
cd /Volumes/N1TB/dev/github/moodle
PHP_INI_SCAN_DIR="/opt/homebrew/etc/php/8.5/conf.d:/tmp/phpini" php vendor/bin/phpunit --filter prompt_test --testsuite aiplacement_dimensions_testsuite
```

Expected: 3 tests, 3 passing.

- [ ] **Step 6: Commit**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/ai/placement/dimensions
git add classes/local/prompt.php tests/local/prompt_test.php lang/en/aiplacement_dimensions.php
git commit -m "feat: build the numbered candidate list sent to the model

The candidate array is the source of truth for resolution; the model
answers with positions, never names."
```

---

## Task 3: `resolver` — indices back to competencies (pure)

**Files:**
- Create: `classes/local/resolver.php`
- Test: `tests/local/resolver_test.php`

**Interfaces:**
- Consumes: the `candidates` array produced by `prompt::build()` — a 0-indexed list of rows with keys `id`, `idnumber`, `shortname`.
- Produces: `\aiplacement_dimensions\local\resolver::resolve(string $raw, array $candidates): array`, returning `suggestions` (list of arrays with keys `id`, `idnumber`, `shortname`, `confidence` (float), `why` (string)), `discarded` (int), and `undecodable` (bool — true when no `picks` payload could be found in the model's answer at all).

This is the unit where the plugin being replaced went wrong. Every branch below is a defect that shipped there.

- [ ] **Step 1: Write the failing test**

`tests/local/resolver_test.php`:

```php
namespace aiplacement_dimensions\local;

/**
 * Tests for resolving model output back to competencies.
 *
 * @package    aiplacement_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiplacement_dimensions\local\resolver
 */
final class resolver_test extends \basic_testcase {
    /**
     * Two candidates, positions 1 and 2.
     *
     * @return array
     */
    private function candidates(): array {
        return [
            ['id' => 11, 'idnumber' => 'K11', 'shortname' => 'Alpha'],
            ['id' => 22, 'idnumber' => 'K22', 'shortname' => 'Beta'],
        ];
    }

    /**
     * A valid pick resolves to the competency at that position.
     *
     * @return void
     */
    public function test_valid_pick(): void {
        $raw = '{"picks":[{"n":2,"confidence":0.9,"why":"mentions Beta"}]}';
        $result = resolver::resolve($raw, $this->candidates());

        $this->assertSame(0, $result['discarded']);
        $this->assertCount(1, $result['suggestions']);
        $this->assertSame(22, $result['suggestions'][0]['id']);
        $this->assertSame('K22', $result['suggestions'][0]['idnumber']);
        $this->assertSame(0.9, $result['suggestions'][0]['confidence']);
        $this->assertSame('mentions Beta', $result['suggestions'][0]['why']);
    }

    /**
     * Out-of-range indices are counted, never silently dropped.
     *
     * @return void
     */
    public function test_out_of_range_is_counted(): void {
        $raw = '{"picks":[{"n":1},{"n":9},{"n":0},{"n":-3}]}';
        $result = resolver::resolve($raw, $this->candidates());

        $this->assertCount(1, $result['suggestions']);
        $this->assertSame(3, $result['discarded']);
    }

    /**
     * A repeated index yields one suggestion and no discard.
     *
     * @return void
     */
    public function test_duplicate_index(): void {
        $raw = '{"picks":[{"n":1},{"n":1}]}';
        $result = resolver::resolve($raw, $this->candidates());

        $this->assertCount(1, $result['suggestions']);
        $this->assertSame(0, $result['discarded']);
    }

    /**
     * A fenced JSON payload is still readable.
     *
     * @return void
     */
    public function test_code_fence_is_tolerated(): void {
        $raw = "```json\n{\"picks\":[{\"n\":1}]}\n```";
        $result = resolver::resolve($raw, $this->candidates());

        $this->assertCount(1, $result['suggestions']);
    }

    /**
     * Unparseable output is an empty answer, not an exception.
     *
     * @return void
     */
    public function test_malformed_json_is_empty_not_fatal(): void {
        $result = resolver::resolve('I am afraid I cannot help with that.', $this->candidates());

        $this->assertSame([], $result['suggestions']);
        $this->assertSame(0, $result['discarded']);
    }

    /**
     * Missing confidence and why get safe defaults.
     *
     * @return void
     */
    public function test_missing_optional_fields(): void {
        $result = resolver::resolve('{"picks":[{"n":1}]}', $this->candidates());

        $this->assertSame(0.0, $result['suggestions'][0]['confidence']);
        $this->assertSame('', $result['suggestions'][0]['why']);
    }

    /**
     * An empty picks array is a valid answer.
     *
     * @return void
     */
    public function test_empty_picks(): void {
        $result = resolver::resolve('{"picks":[]}', $this->candidates());

        $this->assertSame([], $result['suggestions']);
        $this->assertSame(0, $result['discarded']);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
cd /Volumes/N1TB/dev/github/moodle
PHP_INI_SCAN_DIR="/opt/homebrew/etc/php/8.5/conf.d:/tmp/phpini" php vendor/bin/phpunit --filter resolver_test --testsuite aiplacement_dimensions_testsuite
```

Expected: FAIL — `Class "aiplacement_dimensions\local\resolver" not found`.

- [ ] **Step 3: Write the implementation**

`classes/local/resolver.php`:

```php
namespace aiplacement_dimensions\local;

/**
 * Resolves the model's answer into competencies.
 *
 * The model answers with positions in the candidate list built by
 * {@see prompt::build()}. Resolution is therefore an array lookup, and matching
 * the wrong competency is not possible. Anything the model returns that is not
 * a usable position is counted in "discarded" and surfaced to the user.
 *
 * @package    aiplacement_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class resolver {
    /**
     * Resolve raw model output against the candidate list.
     *
     * @param string $raw The model's generated content.
     * @param array $candidates The list returned by prompt::build(), in order.
     * @return array Keys: suggestions (list), discarded (int).
     */
    public static function resolve(string $raw, array $candidates): array {
        $decoded = self::decode($raw);
        $picks = is_array($decoded['picks'] ?? null) ? $decoded['picks'] : [];

        $suggestions = [];
        $seen = [];
        $discarded = 0;

        foreach ($picks as $pick) {
            if (!is_array($pick) || !isset($pick['n']) || !is_numeric($pick['n'])) {
                $discarded++;
                continue;
            }

            $position = (int) $pick['n'];
            $index = $position - 1;

            if ($index < 0 || !isset($candidates[$index])) {
                $discarded++;
                continue;
            }

            if (isset($seen[$index])) {
                continue;
            }
            $seen[$index] = true;

            $candidate = $candidates[$index];
            $suggestions[] = [
                'id' => (int) $candidate['id'],
                'idnumber' => (string) $candidate['idnumber'],
                'shortname' => (string) $candidate['shortname'],
                'confidence' => isset($pick['confidence']) && is_numeric($pick['confidence'])
                    ? (float) $pick['confidence']
                    : 0.0,
                'why' => isset($pick['why']) && is_scalar($pick['why'])
                    ? clean_param((string) $pick['why'], PARAM_TEXT)
                    : '',
            ];
        }

        return [
            'suggestions' => $suggestions,
            'discarded' => $discarded,
            'undecodable' => $decoded === null,
        ];
    }

    /** @var int Ceiling on brace-span attempts, so a brace-heavy answer cannot spin. */
    private const MAX_SPANS = 20;

    /**
     * Decode the model output, tolerating fences and surrounding prose.
     *
     * Tries a list of candidate substrings in priority order and accepts the first
     * that decodes to an array whose "picks" value is itself an array. Checking the
     * value and not merely the key matters: a decoy fence carrying {"picks":null}
     * satisfies key-presence, is accepted, and masks a real answer in a later fence.
     *
     * @param string $raw The model's generated content.
     * @return array|null Decoded payload, or null when no payload could be found.
     */
    private static function decode(string $raw): ?array {
        foreach (self::candidate_payloads($raw) as $candidate) {
            $decoded = json_decode($candidate, true);
            if (is_array($decoded) && is_array($decoded['picks'] ?? null)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Build the candidate substrings that might hold the payload, best first.
     *
     * @param string $raw The model's generated content.
     * @return array Candidate strings, in the order they should be tried.
     */
    private static function candidate_payloads(string $raw): array {
        $text = trim($raw);
        $candidates = [$text];

        /*
         * Every fenced block, not just the first: models put a worked example in one
         * fence and the answer in another. The closing fence is anchored to its own
         * line so a triple-backtick inside a string value cannot truncate the capture.
         */
        if (preg_match_all('/^```[a-z]*[ \t]*\R(.*?)\R```[ \t]*$/ims', $text, $matches)) {
            foreach ($matches[1] as $block) {
                $candidates[] = trim($block);
            }
        }

        /*
         * Last resort for unfenced JSON: every span from a brace to the final brace.
         * Trying successive opening braces means a brace in the preamble no longer
         * swallows the payload, because a later start eventually lands on it.
         */
        $close = strrpos($text, '}');
        if ($close !== false) {
            $offset = 0;
            $tries = 0;
            while ($tries < self::MAX_SPANS) {
                $open = strpos($text, '{', $offset);
                if ($open === false || $open >= $close) {
                    break;
                }
                $candidates[] = substr($text, $open, $close - $open + 1);
                $offset = $open + 1;
                $tries++;
            }
        }

        return $candidates;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

```bash
cd /Volumes/N1TB/dev/github/moodle
PHP_INI_SCAN_DIR="/opt/homebrew/etc/php/8.5/conf.d:/tmp/phpini" php vendor/bin/phpunit --filter resolver_test --testsuite aiplacement_dimensions_testsuite
```

Expected: 7 tests, 7 passing.

- [ ] **Step 5: Commit**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/ai/placement/dimensions
git add classes/local/resolver.php tests/local/resolver_test.php
git commit -m "feat: resolve model indices back to competencies

Out-of-range and unparseable answers are counted, never dropped silently."
```

---

## Task 4: `candidates` — the competency subtree fetch

**Files:**
- Create: `classes/local/candidates.php`
- Test: `tests/local/candidates_test.php`

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: `\aiplacement_dimensions\local\candidates::fetch(int $frameworkid, array $rootids): array` — a list of rows with keys `id`, `idnumber`, `shortname`, ordered by `shortname` ascending, feeding `prompt::build()`.

- [ ] **Step 1: Write the failing test**

The important assertion is the **grandchild**: fetching one level of children was a shipped defect in the plugin being replaced, and it makes deep frameworks unusable.

`tests/local/candidates_test.php`:

```php
namespace aiplacement_dimensions\local;

/**
 * Tests for the competency subtree fetch.
 *
 * @package    aiplacement_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiplacement_dimensions\local\candidates
 */
final class candidates_test extends \advanced_testcase {
    /**
     * The whole subtree is returned, not only direct children.
     *
     * @return void
     */
    public function test_fetch_returns_the_whole_subtree(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework();
        $root = $generator->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'shortname' => 'Root',
        ]);
        $child = $generator->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'parentid' => $root->get('id'),
            'shortname' => 'Child',
        ]);
        $grandchild = $generator->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'parentid' => $child->get('id'),
            'shortname' => 'Grandchild',
        ]);

        $rows = candidates::fetch($framework->get('id'), [$root->get('id')]);
        $ids = array_column($rows, 'id');

        $this->assertContains($child->get('id'), $ids);
        $this->assertContains($grandchild->get('id'), $ids, 'depth 3 must be reachable');
        $this->assertNotContains($root->get('id'), $ids, 'the chosen root is scope, not a candidate');
    }

    /**
     * With no roots the whole framework is in scope.
     *
     * @return void
     */
    public function test_empty_rootids_returns_the_framework(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework();
        $one = $generator->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'shortname' => 'Solo',
        ]);

        $rows = candidates::fetch($framework->get('id'), []);

        $this->assertSame([$one->get('id')], array_column($rows, 'id'));
    }

    /**
     * A root from another framework contributes nothing.
     *
     * @return void
     */
    public function test_foreign_root_is_ignored(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $frameworka = $generator->create_framework();
        $frameworkb = $generator->create_framework();
        $foreign = $generator->create_competency([
            'competencyframeworkid' => $frameworkb->get('id'),
            'shortname' => 'Foreign',
        ]);

        $rows = candidates::fetch($frameworka->get('id'), [$foreign->get('id')]);

        $this->assertSame([], $rows);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
cd /Volumes/N1TB/dev/github/moodle
PHP_INI_SCAN_DIR="/opt/homebrew/etc/php/8.5/conf.d:/tmp/phpini" php vendor/bin/phpunit --filter candidates_test --testsuite aiplacement_dimensions_testsuite
```

Expected: FAIL — `Class "aiplacement_dimensions\local\candidates" not found`.

- [ ] **Step 3: Write the implementation**

Use core's `competency::get_descendants_ids()` (`competency/classes/competency.php:832-838`), which walks the full subtree via the `path` column. Do not hand-roll the recursion, and do not query by `parentid`.

`classes/local/candidates.php`:

```php
namespace aiplacement_dimensions\local;

use core_competency\competency;

/**
 * Fetches the competencies that are in scope for a suggestion request.
 *
 * @package    aiplacement_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class candidates {
    /**
     * Fetch the competencies under the chosen roots.
     *
     * The chosen roots define scope and are not themselves candidates. An empty
     * root list means the whole framework. The full subtree is returned, at any
     * depth: fetching only direct children makes deep frameworks unusable.
     *
     * @param int $frameworkid The competency framework id.
     * @param array $rootids Competency ids whose subtrees are in scope.
     * @return array Rows with keys id, idnumber, shortname, sorted by shortname.
     */
    public static function fetch(int $frameworkid, array $rootids): array {
        global $DB;

        if (empty($rootids)) {
            $records = $DB->get_records(
                'competency',
                ['competencyframeworkid' => $frameworkid],
                'shortname ASC',
                'id, idnumber, shortname'
            );
            return array_values(array_map([self::class, 'row'], $records));
        }

        $ids = [];
        foreach ($rootids as $rootid) {
            $root = competency::get_record(['id' => (int) $rootid, 'competencyframeworkid' => $frameworkid]);
            if (!$root) {
                continue;
            }
            $ids = array_merge($ids, competency::get_descendants_ids($root));
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (empty($ids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'cid');
        $params['frameworkid'] = $frameworkid;
        $records = $DB->get_records_select(
            'competency',
            "id {$insql} AND competencyframeworkid = :frameworkid",
            $params,
            'shortname ASC',
            'id, idnumber, shortname'
        );

        return array_values(array_map([self::class, 'row'], $records));
    }

    /**
     * Normalise a database record into a candidate row.
     *
     * @param \stdClass $record A competency record.
     * @return array Keys id, idnumber, shortname.
     */
    private static function row(\stdClass $record): array {
        return [
            'id' => (int) $record->id,
            'idnumber' => (string) ($record->idnumber ?? ''),
            'shortname' => (string) ($record->shortname ?? ''),
        ];
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

```bash
cd /Volumes/N1TB/dev/github/moodle
PHP_INI_SCAN_DIR="/opt/homebrew/etc/php/8.5/conf.d:/tmp/phpini" php vendor/bin/phpunit --filter candidates_test --testsuite aiplacement_dimensions_testsuite
```

Expected: 3 tests, 3 passing. If `test_fetch_returns_the_whole_subtree` fails on the grandchild, you queried `parentid` instead of using `get_descendants_ids()`.

- [ ] **Step 5: Commit**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/ai/placement/dimensions
git add classes/local/candidates.php tests/local/candidates_test.php
git commit -m "feat: fetch the full competency subtree for the chosen roots

Uses core get_descendants_ids so depth beyond direct children is reachable."
```

---
## Task 5: `suggest_competencies` external function

This is the plugin's **only** external function and its **entire authorization boundary**. The third-party plugin this one replaces failed here specifically: a write endpoint with no `require_capability()` of its own, `is_action_enabled_in_context()` never called, the AI policy bypassed, and the service gated more weakly than the UI that called it. Every gate below exists because one of those shipped.

**Files:**
- Create: `classes/external/suggest_competencies.php`
- Create: `db/services.php`
- Modify: `version.php` (bump — a new `db/services.php` only syncs on a version change)
- Modify: `lang/en/aiplacement_dimensions.php`
- Test: `tests/external/suggest_competencies_test.php`

**Interfaces:**
- Consumes: `candidates::fetch()`, `prompt::build()`, `resolver::resolve()` (which returns `suggestions`, `discarded`, **`undecodable`**).
- Produces: the web service `aiplacement_dimensions_suggest_competencies`, taking `cmid` (int, `0` for an activity that does not exist yet), `courseid` (int), `frameworkid` (int), `rootids` (list of int), `content` (raw text); returning `success` (bool), `errorcode` (**int**, `0` when successful), `errormessage` (string), `suggestions` (list of `{id, idnumber, shortname, confidence, why}`), `discarded` (int), `undecodable` (bool), `contenttruncated` (bool), `candidatecount` (int), `truncated` (bool).

**Why `cmid` + `courseid` rather than `contextid`.** A caller-supplied `contextid` makes the AI opt-out gate a no-op the caller chooses: `validate_context()` constrains nothing about the context *level*, and core's `is_action_enabled_in_context()` (`ai/classes/manager.php:349-370`) returns `true` unconditionally for any level outside course/category/module, and only consults the per-activity `enabledaiactions` when the level is `CONTEXT_MODULE`. So passing the parent course's contextid dodges the per-activity opt-out, and passing any block's contextid short-circuits the whole check. Deriving the context server-side from `cmid` removes the class of bypass entirely.

- [ ] **Step 1: Write `db/services.php`**

```php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'aiplacement_dimensions_suggest_competencies' => [
        'classname' => 'aiplacement_dimensions\external\suggest_competencies',
        'description' => 'Suggest competencies for the given activity content.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'aiplacement/dimensions:suggest',
    ],
];
```

`'type' => 'write'`, not `'read'`: `manager::process_action()` writes to `ai_action_register` and `ai_action_generate_text` (`ai/classes/manager.php:171-210`). Core's sibling declares `write` for the identical operation (`ai/placement/courseassist/db/services.php:31`). Declaring `read` would expose it to read-only tokens.

The `capabilities` key is advisory metadata for the admin UI — it enforces nothing. The enforcement is the `require_capability()` calls inside `execute()`. Declaring a capability here and checking a different one, or none, was an audited defect.

- [ ] **Step 2: Bump `version.php`**

Change `$plugin->version` to `2026072901`. A new `db/services.php` is only synced when the version number changes, so without this the web service never registers on a real site.

- [ ] **Step 3: Write the failing test**

`tests/external/suggest_competencies_test.php`:

```php
namespace aiplacement_dimensions\external;

use core_ai\aiactions\generate_text;

/**
 * Tests for the suggest_competencies web service.
 *
 * @package    aiplacement_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiplacement_dimensions\external\suggest_competencies
 */
final class suggest_competencies_test extends \advanced_testcase {
    /**
     * Install a mocked AI manager returning the given generated content.
     *
     * @param string $generated The content the model is pretending to return.
     * @param bool $success Whether the provider call succeeded.
     * @return void
     */
    private function mock_manager(string $generated, bool $success = true): void {
        /*
         * `error` is not optional on a failure response: response_base::__construct()
         * (ai/classes/aiactions/responses/response_base.php:57-59) throws a
         * coding_exception when !$success and either errorcode is 0 or error is empty.
         */
        $response = new \core_ai\aiactions\responses\response_generate_text(
            success: $success,
            errorcode: $success ? 0 : 429,
            error: $success ? '' : 'error_ratelimited',
            errormessage: $success ? '' : 'Rate limited'
        );
        if ($success) {
            $response->set_response_data([
                'generatedcontent' => $generated,
                'finishreason' => 'stop',
            ]);
        }

        $mock = $this->createMock(\core_ai\manager::class);
        $mock->method('process_action')->willReturn($response);
        $mock->method('is_action_enabled')->willReturn(true);
        $mock->method('is_action_enabled_in_context')->willReturn(true);
        $mock->method('get_providers_for_actions')->willReturn([
            generate_text::class => ['aiprovider_openai'],
        ]);

        \core\di::set(\core_ai\manager::class, fn() => $mock);
    }

    /**
     * Accept the AI policy for the current user.
     *
     * get_user_policy_status() and user_policy_accepted() are public STATIC methods
     * on the manager (ai/classes/manager.php:242 and :219), so they are not reachable
     * through the DI container and cannot be mocked. The status lives in the
     * core/ai_policy cache, and the only way to satisfy it in a test is to accept the
     * policy for real, which is what core does in ai/tests/provider/provider_test.php:79.
     *
     * @param \context $context The context the policy is accepted in.
     * @return void
     */
    private function accept_policy(\context $context): void {
        global $USER;

        \core_ai\manager::user_policy_accepted((int) $USER->id, $context->id);
    }

    /**
     * Build a course, module, framework and one competency.
     *
     * @return array Keys: course, cmid, frameworkid, competencyid, context.
     */
    private function scenario(): array {
        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework();
        $competency = $generator->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'shortname' => 'Alpha',
        ]);

        return [
            'course' => $course,
            'cmid' => $module->cmid,
            'frameworkid' => $framework->get('id'),
            'competencyid' => $competency->get('id'),
            'context' => \context_module::instance($module->cmid),
        ];
    }

    /**
     * Call the web service with the given overrides.
     *
     * @param array $scenario The scenario array.
     * @param array $overrides Parameter overrides.
     * @return array The call_external_function result.
     */
    private function call(array $scenario, array $overrides = []): array {
        $_POST['sesskey'] = sesskey();

        return \core_external\external_api::call_external_function(
            'aiplacement_dimensions_suggest_competencies',
            $overrides + [
                'cmid' => $scenario['cmid'],
                'courseid' => $scenario['course']->id,
                'frameworkid' => $scenario['frameworkid'],
                'rootids' => [],
                'content' => 'Some activity description.',
            ]
        );
    }

    /**
     * A valid pick comes back resolved to a real competency id.
     *
     * @return void
     */
    public function test_execute_resolves_a_pick(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $scenario = $this->scenario();
        $this->accept_policy($scenario['context']);
        $this->mock_manager('{"picks":[{"n":1,"confidence":0.7,"why":"covers it"}]}');

        $result = $this->call($scenario);

        $this->assertFalse($result['error']);
        $this->assertTrue($result['data']['success']);
        $this->assertCount(1, $result['data']['suggestions']);
        $this->assertSame($scenario['competencyid'], $result['data']['suggestions'][0]['id']);
        $this->assertSame(0, $result['data']['discarded']);
        $this->assertFalse($result['data']['undecodable']);
        $this->assertFalse($result['data']['contenttruncated']);
        $this->assertSame(1, $result['data']['candidatecount']);
    }

    /**
     * An out-of-range index is reported, not dropped.
     *
     * @return void
     */
    public function test_execute_reports_discards(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $scenario = $this->scenario();
        $this->accept_policy($scenario['context']);
        $this->mock_manager('{"picks":[{"n":99}]}');

        $result = $this->call($scenario);

        $this->assertSame([], $result['data']['suggestions']);
        $this->assertSame(1, $result['data']['discarded']);
    }

    /**
     * An unreadable answer is distinguished from an empty one.
     *
     * @return void
     */
    public function test_execute_reports_undecodable(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $scenario = $this->scenario();
        $this->accept_policy($scenario['context']);
        $this->mock_manager('I am afraid I cannot help with that.');

        $result = $this->call($scenario);

        $this->assertTrue($result['data']['undecodable']);
        $this->assertSame([], $result['data']['suggestions']);
    }

    /**
     * A provider failure returns state, not an exception.
     *
     * @return void
     */
    public function test_execute_returns_provider_failure_as_state(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $scenario = $this->scenario();
        $this->accept_policy($scenario['context']);
        $this->mock_manager('', false);

        $result = $this->call($scenario);

        $this->assertFalse($result['error']);
        $this->assertFalse($result['data']['success']);
        $this->assertSame(429, $result['data']['errorcode']);
    }

    /**
     * A framework with no competencies in scope returns the empty result cleanly.
     *
     * @return void
     */
    public function test_execute_with_no_candidates(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $scenario = $this->scenario();
        $this->accept_policy($scenario['context']);
        $this->mock_manager('{"picks":[]}');

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $empty = $generator->create_framework();

        $result = $this->call($scenario, ['frameworkid' => $empty->get('id')]);

        $this->assertTrue($result['data']['success']);
        $this->assertSame(0, $result['data']['candidatecount']);
        $this->assertSame([], $result['data']['suggestions']);
    }

    /**
     * Content beyond the cap is truncated and the response says so.
     *
     * @return void
     */
    public function test_execute_flags_truncated_content(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $scenario = $this->scenario();
        $this->accept_policy($scenario['context']);
        $this->mock_manager('{"picks":[]}');

        $result = $this->call($scenario, [
            'content' => str_repeat('a', suggest_competencies::MAX_CONTENT + 1),
        ]);

        $this->assertTrue($result['data']['contenttruncated']);
    }

    /**
     * An enrolled user without the capability is refused by the capability gate.
     *
     * The user is enrolled deliberately: an unenrolled user is stopped earlier by
     * validate_context()'s require_login(), which would make this test pass even if
     * both require_capability() calls were deleted.
     *
     * @return void
     */
    public function test_execute_requires_capability(): void {
        $this->resetAfterTest();
        $scenario = $this->scenario();
        $this->mock_manager('{"picks":[]}');

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $scenario['course']->id, 'student');
        $this->setUser($student);
        $this->accept_policy($scenario['context']);

        $result = $this->call($scenario);

        $this->assertTrue($result['error']);
        $this->assertSame('nopermissions', $result['exception']->errorcode);
    }

    /**
     * The acceptable use policy blocks the call when it has not been accepted.
     *
     * @return void
     */
    public function test_execute_requires_policy_acceptance(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $scenario = $this->scenario();
        $this->mock_manager('{"picks":[]}');

        \cache::make('core', 'ai_policy')->purge();

        $result = $this->call($scenario);

        $this->assertTrue($result['error']);
        $this->assertSame('error_policynotaccepted', $result['exception']->errorcode);
    }

    /**
     * The per-context AI opt-out is honoured.
     *
     * @return void
     */
    public function test_execute_honours_context_opt_out(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $scenario = $this->scenario();
        $this->accept_policy($scenario['context']);

        $response = new \core_ai\aiactions\responses\response_generate_text(success: true);
        $response->set_response_data(['generatedcontent' => '{"picks":[]}', 'finishreason' => 'stop']);
        $mock = $this->createMock(\core_ai\manager::class);
        $mock->method('process_action')->willReturn($response);
        $mock->method('is_action_enabled')->willReturn(true);
        $mock->method('is_action_enabled_in_context')->willReturn(false);
        $mock->method('get_providers_for_actions')->willReturn([
            generate_text::class => ['aiprovider_openai'],
        ]);
        \core\di::set(\core_ai\manager::class, fn() => $mock);

        $result = $this->call($scenario);

        $this->assertTrue($result['error']);
        $this->assertSame('error_actiondisabled', $result['exception']->errorcode);
    }

    /**
     * The placement's own admin toggle is honoured.
     *
     * @return void
     */
    public function test_execute_honours_action_toggle(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $scenario = $this->scenario();
        $this->accept_policy($scenario['context']);

        $mock = $this->createMock(\core_ai\manager::class);
        $mock->method('is_action_enabled')->willReturn(false);
        $mock->method('is_action_enabled_in_context')->willReturn(true);
        \core\di::set(\core_ai\manager::class, fn() => $mock);

        $result = $this->call($scenario);

        $this->assertTrue($result['error']);
        $this->assertSame('error_actiondisabled', $result['exception']->errorcode);
    }

    /**
     * A framework the user may not read is refused.
     *
     * @return void
     */
    public function test_execute_requires_framework_read_capability(): void {
        global $DB;

        $this->resetAfterTest();
        $scenario = $this->scenario();
        $this->mock_manager('{"picks":[]}');

        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $scenario['course']->id, 'editingteacher');
        $this->setUser($teacher);
        $this->accept_policy($scenario['context']);

        $frameworkcontext = \core_competency\competency_framework::get_record(
            ['id' => $scenario['frameworkid']]
        )->get_context();
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
        assign_capability(
            'moodle/competency:competencyview',
            CAP_PROHIBIT,
            $roleid,
            $frameworkcontext->id,
            true
        );

        $result = $this->call($scenario);

        $this->assertTrue($result['error']);
        $this->assertSame('error_nosuchframework', $result['exception']->errorcode);
    }

    /**
     * The new-activity path works when no course module exists yet.
     *
     * Without this test the whole cmid-zero branch, including its capability
     * requirement, could be deleted and the suite would stay green.
     *
     * @return void
     */
    public function test_execute_works_for_a_new_activity(): void {
        $this->resetAfterTest();
        $scenario = $this->scenario();
        $this->mock_manager('{"picks":[{"n":1}]}');

        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $scenario['course']->id, 'editingteacher');
        $this->setUser($teacher);
        $this->accept_policy(\context_course::instance($scenario['course']->id));

        $result = $this->call($scenario, ['cmid' => 0]);

        $this->assertFalse($result['error']);
        $this->assertTrue($result['data']['success']);
        $this->assertSame(1, $result['data']['candidatecount']);
    }

    /**
     * The new-activity path requires the capability to add activities.
     *
     * This is the only test that gives the manageactivities gate meaning: it builds
     * the role for which that gate is not inert — someone who curates competency
     * links without being able to edit activities.
     *
     * @return void
     */
    public function test_execute_new_activity_requires_manageactivities(): void {
        $this->resetAfterTest();
        $scenario = $this->scenario();
        $this->mock_manager('{"picks":[]}');

        $coursecontext = \context_course::instance($scenario['course']->id);
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'curriculumlead']);
        assign_capability('moodle/competency:coursecompetencymanage', CAP_ALLOW, $roleid, $coursecontext->id, true);
        assign_capability('aiplacement/dimensions:suggest', CAP_ALLOW, $roleid, $coursecontext->id, true);
        assign_capability('moodle/course:manageactivities', CAP_PROHIBIT, $roleid, $coursecontext->id, true);

        $lead = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($lead->id, $scenario['course']->id, $roleid);
        $this->setUser($lead);
        $this->accept_policy($coursecontext);

        $result = $this->call($scenario, ['cmid' => 0]);

        $this->assertTrue($result['error']);
        $this->assertSame('nopermissions', $result['exception']->errorcode);
    }

    /**
     * A cmid that does not belong to the given course is refused.
     *
     * @return void
     */
    public function test_execute_rejects_mismatched_course(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $scenario = $this->scenario();
        $this->accept_policy($scenario['context']);
        $this->mock_manager('{"picks":[]}');

        $other = $this->getDataGenerator()->create_course();

        $result = $this->call($scenario, ['courseid' => $other->id]);

        $this->assertTrue($result['error']);
    }
}
```

The tests go through `\core_external\external_api::call_external_function()`, not `execute()` directly. That is what exercises `execute_returns()` and the `db/services.php` wiring; calling `execute()` directly leaves both untested, which is how a broken return definition ships.

Signatures verified against `ai/classes/aiactions/responses/response_base.php`: `get_success(): bool` (`:82`), `get_errorcode(): int` (`:109`), `get_errormessage(): string` (`:126`). `\core_ai\aiactions\responses\response_generate_text` exists. Do not add a `class_exists()` probe.

- [ ] **Step 4: Run the tests to verify they fail**

```bash
cd /Volumes/N1TB/dev/github/moodle
PHP_INI_SCAN_DIR="/opt/homebrew/etc/php/8.5/conf.d:/tmp/phpini" php vendor/bin/phpunit --filter suggest_competencies_test --testsuite aiplacement_dimensions_testsuite
```

Expected: FAIL — the web service is not found. **A plain `init.php` re-run will not register it**: Moodle only syncs `db/services.php` when the plugin version changes. Bump the version (Step 2), then rebuild the test database:

```bash
PHP_INI_SCAN_DIR="/opt/homebrew/etc/php/8.5/conf.d:/tmp/phpini" php public/admin/tool/phpunit/cli/util.php --drop
PHP_INI_SCAN_DIR="/opt/homebrew/etc/php/8.5/conf.d:/tmp/phpini" php public/admin/tool/phpunit/cli/init.php --disable-composer
```

- [ ] **Step 5: Write the implementation**

`classes/external/suggest_competencies.php`:

```php
namespace aiplacement_dimensions\external;

use aiplacement_dimensions\local\candidates;
use aiplacement_dimensions\local\prompt;
use aiplacement_dimensions\local\resolver;
use core_ai\aiactions\generate_text;
use core_competency\competency_framework;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Suggest competencies for the given activity content.
 *
 * @package    aiplacement_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class suggest_competencies extends external_api {
    /** @var int Maximum characters of activity content sent to the model. */
    public const MAX_CONTENT = 20000;

    /** @var int Maximum subtree roots a caller may scope a request to. */
    public const MAX_ROOTS = 50;

    /**
     * Describe the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id, or 0 for an activity not yet created'),
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'frameworkid' => new external_value(PARAM_INT, 'Competency framework id'),
            'rootids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Competency id whose subtree is in scope'),
                'Chosen subtree roots; empty means the whole framework',
                VALUE_DEFAULT,
                []
            ),
            'content' => new external_value(PARAM_RAW, 'Activity content to classify'),
        ]);
    }

    /**
     * Suggest competencies.
     *
     * @param int $cmid Course module id, or 0 when the activity does not exist yet.
     * @param int $courseid Course id.
     * @param int $frameworkid Competency framework id.
     * @param array $rootids Chosen subtree roots.
     * @param string $content Activity content.
     * @return array The structure described by execute_returns().
     */
    public static function execute(
        int $cmid,
        int $courseid,
        int $frameworkid,
        array $rootids,
        string $content
    ): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'courseid' => $courseid,
            'frameworkid' => $frameworkid,
            'rootids' => $rootids,
            'content' => $content,
        ]);

        if (count($params['rootids']) > self::MAX_ROOTS) {
            throw new \moodle_exception('error_toomanyroots', 'aiplacement_dimensions');
        }

        /*
         * The context is derived, never accepted from the caller. A caller-supplied
         * contextid would make the AI opt-out gate a no-op the caller chooses: core's
         * is_action_enabled_in_context() only consults a module's enabledaiactions at
         * CONTEXT_MODULE, and returns true outright for levels outside course, category
         * and module, so any block's contextid would have short-circuited the check.
         *
         * The course context is resolved and authorized FIRST so that resolving the
         * course module cannot be used as a pre-authentication membership probe.
         */
        $coursecontext = \context_course::instance($params['courseid'], MUST_EXIST);
        self::validate_context($coursecontext);

        if ($params['cmid'] > 0) {
            $cm = get_coursemodule_from_id('', $params['cmid'], $params['courseid'], false, MUST_EXIST);
            $context = \context_module::instance($cm->id);
            self::validate_context($context);
        } else {
            /*
             * cmid 0 means the activity does not exist yet, so there is no per-activity
             * enabledaiactions to consult and this call is evaluated at course level.
             * That is a real residual hole: a caller editing an EXISTING activity whose
             * action is switched off can send cmid 0 and be evaluated at course level
             * instead. It is not a privilege escalation, because the same editing
             * teacher may flip that switch on the module form anyway, but it does mean
             * the ai_action_register row records the course context rather than the
             * module's. Requiring manageactivities is what being on the add-activity
             * form actually implies, and narrows who can take that path.
             */
            $context = $coursecontext;
            require_capability('moodle/course:manageactivities', $coursecontext);
        }

        require_capability('moodle/competency:coursecompetencymanage', $context);
        require_capability('aiplacement/dimensions:suggest', $context);

        \core_competency\api::require_enabled();

        $manager = \core\di::get(\core_ai\manager::class);

        /*
         * Two distinct switches. is_action_enabled() reads only the per-action toggle
         * (manager.php:327-340); whether the PLACEMENT is enabled at all lives in a
         * separate setting read by \core\plugininfo\aiplacement::is_plugin_enabled().
         * Checking only the first means turning the plugin off in Site administration
         * does not turn it off.
         */
        if (!\core\plugininfo\aiplacement::is_plugin_enabled('dimensions')) {
            throw new \moodle_exception('error_actiondisabled', 'aiplacement_dimensions');
        }

        if (!$manager->is_action_enabled('aiplacement_dimensions', generate_text::class)) {
            throw new \moodle_exception('error_actiondisabled', 'aiplacement_dimensions');
        }

        if (!$manager->is_action_enabled_in_context($context, generate_text::class)) {
            throw new \moodle_exception('error_actiondisabled', 'aiplacement_dimensions');
        }

        /*
         * Static, not an instance call: get_user_policy_status() is a public static
         * method (ai/classes/manager.php:242) reading the core/ai_policy cache. It is
         * therefore not reachable through the DI container, which is also why the test
         * accepts the policy for real instead of mocking it.
         */
        if (!\core_ai\manager::get_user_policy_status((int) $USER->id)) {
            throw new \moodle_exception('error_policynotaccepted', 'aiplacement_dimensions');
        }

        /*
         * The framework is authorized in its OWN context, not the activity's. Frameworks
         * are context-scoped and every core read path checks that context; without this
         * an editing teacher could name any framework id and have its competencies read
         * and shipped to the provider, with candidatecount acting as an enumeration
         * oracle. local_dimensions' own picker refuses such a framework, so omitting the
         * check would gate this service more weakly than the UI that calls it.
         */
        $framework = competency_framework::get_record(['id' => $params['frameworkid']]);
        if (!$framework || !has_capability('moodle/competency:competencyview', $framework->get_context())) {
            /*
             * One error for both cases on purpose. Distinguishing "does not exist" from
             * "you may not read it" would let any editing teacher walk the id space and
             * learn which frameworks exist in categories they cannot see. The sibling
             * local_dimensions picker collapses the same two cases for the same reason.
             */
            throw new \moodle_exception(
                'error_nosuchframework',
                'aiplacement_dimensions',
                '',
                null,
                $framework
                    ? 'framework not readable in context ' . $framework->get('contextid')
                    : 'no such framework id'
            );
        }

        $trimmed = \core_text::substr($params['content'], 0, self::MAX_CONTENT);
        $contenttruncated = \core_text::strlen($params['content']) > self::MAX_CONTENT;

        $rows = candidates::fetch($params['frameworkid'], $params['rootids']);
        $built = prompt::build($rows, $trimmed);

        if (empty($built['candidates'])) {
            return self::result(true, 0, '', [], 0, false, $contenttruncated, $built);
        }

        $action = new generate_text($context->id, (int) $USER->id, $built['text']);
        $response = $manager->process_action($action);

        if (!$response->get_success()) {
            return self::result(
                false,
                $response->get_errorcode(),
                $response->get_errormessage(),
                [],
                0,
                false,
                $contenttruncated,
                $built
            );
        }

        $data = $response->get_response_data();
        $resolved = resolver::resolve((string) ($data['generatedcontent'] ?? ''), $built['candidates']);

        return self::result(
            true,
            0,
            '',
            $resolved['suggestions'],
            $resolved['discarded'],
            $resolved['undecodable'],
            $contenttruncated,
            $built
        );
    }

    /**
     * Assemble the return structure.
     *
     * @param bool $success Whether the provider call succeeded.
     * @param int $errorcode Provider error code, zero when successful.
     * @param string $errormessage Provider error message.
     * @param array $suggestions Resolved suggestions.
     * @param int $discarded Model answers that could not be resolved.
     * @param bool $undecodable Whether the model answer could not be parsed at all.
     * @param bool $contenttruncated Whether the submitted content was cut to the cap.
     * @param array $built The output of prompt::build().
     * @return array The structure described by execute_returns().
     */
    private static function result(
        bool $success,
        int $errorcode,
        string $errormessage,
        array $suggestions,
        int $discarded,
        bool $undecodable,
        bool $contenttruncated,
        array $built
    ): array {
        return [
            'success' => $success,
            'errorcode' => $errorcode,
            'errormessage' => $errormessage,
            'suggestions' => $suggestions,
            'discarded' => $discarded,
            'undecodable' => $undecodable,
            'contenttruncated' => $contenttruncated,
            'candidatecount' => $built['candidatecount'],
            'truncated' => $built['truncated'],
        ];
    }

    /**
     * Describe the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the model was reached successfully'),
            'errorcode' => new external_value(PARAM_INT, 'Provider error code, zero when successful'),
            'errormessage' => new external_value(PARAM_TEXT, 'Provider error message, empty when successful'),
            'suggestions' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Competency id'),
                    'idnumber' => new external_value(PARAM_TEXT, 'Competency idnumber'),
                    'shortname' => new external_value(PARAM_TEXT, 'Competency shortname'),
                    'confidence' => new external_value(PARAM_FLOAT, 'Model confidence between 0 and 1'),
                    'why' => new external_value(PARAM_TEXT, 'One-sentence rationale'),
                ]),
                'Resolved suggestions'
            ),
            'discarded' => new external_value(PARAM_INT, 'Model answers that could not be resolved'),
            'undecodable' => new external_value(PARAM_BOOL, 'True when the model answer could not be parsed at all'),
            'contenttruncated' => new external_value(PARAM_BOOL, 'True when the submitted content was cut to the cap'),
            'candidatecount' => new external_value(PARAM_INT, 'Competencies in scope before truncation'),
            'truncated' => new external_value(PARAM_BOOL, 'Whether the candidate list was truncated'),
        ]);
    }
}
```

- [ ] **Step 6: Add the lang strings**

Work out their correct alphabetical positions and **prove the ordering with a script** — extract the file's `$string[...]` keys in file order and compare against the same list through PHP's `sort()`. Do not place them by eye: a previous task shipped this file out of order by trusting a plan instruction.

```php
$string['error_actiondisabled'] = 'AI competency suggestions are turned off for this activity or course.';
$string['error_nosuchframework'] = 'That competency framework is not available.';
$string['error_policynotaccepted'] = 'You need to accept the AI acceptable use policy before asking for suggestions.';
$string['error_toomanyroots'] = 'Too many competency branches were selected at once.';
```

- [ ] **Step 7: Run the tests to verify they pass**

```bash
cd /Volumes/N1TB/dev/github/moodle
PHP_INI_SCAN_DIR="/opt/homebrew/etc/php/8.5/conf.d:/tmp/phpini" php vendor/bin/phpunit --filter suggest_competencies_test --testsuite aiplacement_dimensions_testsuite
```

Expected: 14 tests, all passing.

- [ ] **Step 8: Commit**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/ai/placement/dimensions
git add classes/external/suggest_competencies.php db/services.php version.php tests/external/suggest_competencies_test.php lang/en/aiplacement_dimensions.php
git commit -m "feat: add the suggest_competencies web service

Derives the context from cmid rather than trusting a caller-supplied
contextid, so the per-activity AI opt-out cannot be dodged. Authorizes the
framework in its own context, enforces the placement's action toggle, the
per-context opt-out and the acceptable use policy."
```

---

## Task 6: `lib.php`, the gates, and the drawer that opens

**Deliverable:** clicking "Suggest competencies with AI" opens the plugin's own drawer showing a framework select and its branch checkboxes. Nothing is sent to a model yet — that is Task 7.

**Files:**
- Create: `lib.php` (hooking `coursemodule_definition_after_data`, not `coursemodule_standard_elements`)
- Create: `amd/src/suggest.js` and the built `amd/build/suggest.min.js` plus `.map`
- Create: `templates/button.mustache`, `templates/drawer.mustache`, `templates/pickers.mustache`
- Modify: `lang/en/aiplacement_dimensions.php`

**Interfaces:**
- Consumes: `local_dimensions_browse_structure` for the framework and branch tree.
- Produces: the AMD module `aiplacement_dimensions/suggest` exporting `init(contextId, courseId)`; the selector constants `SELECTORS`; the launch button `[data-action="aiplacement-dimensions-suggest"]`; the drawer `#aiplacement-dimensions-drawer` with body `.aiplacement-dimensions-body`; the picker regions `[data-region="framework"]` and `[data-region="branch"]`; the run button `[data-action="aiplacement-dimensions-run"]` (wired in Task 7).

- [ ] **Step 1: Write `lib.php` with the six gates**

The hook choice is load-bearing. `get_plugins_with_function()` iterates in `components.json` order — `aiplacement` is index 0, `tool` is index 35 — so a `coursemodule_standard_elements` callback in this plugin runs **before** `tool_lp` creates the competencies section, and the button cannot be placed relative to a field that does not exist yet.

```php
defined('MOODLE_INTERNAL') || die();

/**
 * Add the AI suggestion button to the activity settings form.
 *
 * This runs on coursemodule_definition_after_data, NOT coursemodule_standard_elements.
 * Both hooks iterate plugins in components.json order, where aiplacement is index 0 and
 * tool is index 35 — so on standard_elements our callback fires before tool_lp has
 * created the competencies section at all, and the button lands under whatever header
 * happens to precede it. definition_after_data runs after every standard_elements
 * callback, so the anchor element exists and insertElementBefore can place the button
 * next to the field it fills.
 *
 * Returns silently when any availability gate fails.
 *
 * @param moodleform_mod $formwrapper The form wrapper.
 * @param MoodleQuickForm $mform The form.
 * @return void
 */
function aiplacement_dimensions_coursemodule_definition_after_data($formwrapper, $mform): void {
    global $PAGE, $OUTPUT, $COURSE;

    if (!get_config('core_competency', 'enabled')) {
        return;
    }

    $context = $formwrapper->get_context();

    if (!has_capability('moodle/competency:coursecompetencymanage', $context)) {
        return;
    }

    if (!has_capability('aiplacement/dimensions:suggest', $context)) {
        return;
    }

    if (!\core\plugininfo\aiplacement::is_plugin_enabled('dimensions')) {
        return;
    }

    $manager = \core\di::get(\core_ai\manager::class);
    $actionclass = \core_ai\aiactions\generate_text::class;

    if (!$manager->is_action_enabled('aiplacement_dimensions', $actionclass)) {
        return;
    }

    $providers = $manager->get_providers_for_actions([$actionclass], true);
    if (empty($providers[$actionclass])) {
        return;
    }

    if (!$manager->is_action_enabled_in_context($context, $actionclass)) {
        return;
    }

    /*
     * The competencies element is our anchor. Gate 2 already required the capability
     * tool_lp itself requires to create it, so it should exist — but guard rather than
     * let insertElementBefore throw if core ever changes that condition.
     */
    if (!$mform->elementExists('competencies')) {
        return;
    }

    $element = $mform->createElement(
        'static',
        'aiplacementdimensions',
        '',
        $OUTPUT->render_from_template('aiplacement_dimensions/button', []) .
        $OUTPUT->render_from_template('aiplacement_dimensions/drawer', [])
    );
    $mform->insertElementBefore($element, 'competencies');

    $cm = $formwrapper->get_coursemodule();

    $PAGE->requires->js_call_amd('aiplacement_dimensions/suggest', 'init', [
        $cm ? (int) $cm->id : 0,
        (int) $COURSE->id,
        (int) $context->id,
    ]);
}
```

The gate order matters: the cheap `get_config` and capability checks come before anything that instantiates the AI manager.

On the add path there is no cmid yet and `$formwrapper->get_context()` returns the **course** context. Both capability checks and `is_action_enabled_in_context()` accept a course context (`ai/classes/manager.php:351` admits `CONTEXT_COURSE`), so the gates hold and the feature works on an unsaved activity. Do not add a cmid requirement.

- [ ] **Step 2: Write `templates/button.mustache`**

```
{{!
    @template aiplacement_dimensions/button

    The entry point rendered inside tool_lp's competencies section.

    Example context (json):
    {}
}}
<div class="aiplacement-dimensions-launch mb-2">
    <button type="button"
            class="btn btn-secondary"
            data-action="aiplacement-dimensions-suggest">
        {{#str}} suggestbutton, aiplacement_dimensions {{/str}}
    </button>
</div>
```

Every class here is namespaced `aiplacement-dimensions-*`. Do not reuse `.ai-drawer` or `.course-assist-*`: another placement resolves those document-wide and the two would cross-bind handlers.

- [ ] **Step 3: Write `templates/drawer.mustache`**

```
{{!
    @template aiplacement_dimensions/drawer

    The plugin's own drawer. It inherits nothing from any other placement and
    uses only aiplacement-dimensions-* class names, so no other module's
    document-wide selectors can bind to it.

    Example context (json):
    {}
}}
<div id="aiplacement-dimensions-drawer"
     class="aiplacement-dimensions-drawer"
     role="region"
     aria-label="{{#str}} pluginname, aiplacement_dimensions {{/str}}"
     hidden>
    <div class="aiplacement-dimensions-header d-flex justify-content-end">
        <button type="button" class="btn btn-icon" data-action="aiplacement-dimensions-close">
            {{#pix}} e/cancel, core {{/pix}}
            <span class="visually-hidden">{{#str}} closedrawer, core {{/str}}</span>
        </button>
    </div>
    <div class="aiplacement-dimensions-body"></div>
</div>
```

- [ ] **Step 4: Write `templates/pickers.mustache`**

```
{{!
    @template aiplacement_dimensions/pickers

    Framework select plus the branch checkboxes that scope the request.

    Example context (json):
    {
        "frameworks": [{"id": 1, "shortname": "Framework"}],
        "branches": [{"id": 9, "shortname": "Root"}]
    }
}}
<form class="aiplacement-dimensions-pickers">
    <div class="mb-3">
        <label class="form-label" for="aiplacement-dimensions-framework">
            {{#str}} frameworklabel, aiplacement_dimensions {{/str}}
        </label>
        <select class="form-select" id="aiplacement-dimensions-framework" data-region="framework">
            {{#frameworks}}<option value="{{id}}">{{shortname}}</option>{{/frameworks}}
        </select>
    </div>

    {{#branches.length}}
        <fieldset class="mb-3">
            <legend class="h6">{{#str}} brancheslabel, aiplacement_dimensions {{/str}}</legend>
            {{#branches}}
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="{{id}}"
                           id="aiplacement-dimensions-branch-{{id}}" data-region="branch">
                    <label class="form-check-label" for="aiplacement-dimensions-branch-{{id}}">{{shortname}}</label>
                </div>
            {{/branches}}
        </fieldset>
    {{/branches.length}}

    <button type="button" class="btn btn-primary" data-action="aiplacement-dimensions-run">
        {{#str}} runbutton, aiplacement_dimensions {{/str}}
    </button>
</form>
```

- [ ] **Step 5: Write `amd/src/suggest.js`**

The module extends nothing and owns its drawer.

```js
// Licence header as in every other file.

/**
 * Competency suggestions on the activity form.
 *
 * @module     aiplacement_dimensions/suggest
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/templates', 'core/notification', 'core/str'],
function(Ajax, Templates, Notification, Str) {

    var SELECTORS = {
        LAUNCH: '[data-action="aiplacement-dimensions-suggest"]',
        CLOSE: '[data-action="aiplacement-dimensions-close"]',
        RUN: '[data-action="aiplacement-dimensions-run"]',
        DRAWER: '#aiplacement-dimensions-drawer',
        BODY: '#aiplacement-dimensions-drawer .aiplacement-dimensions-body',
        FRAMEWORK: '[data-region="framework"]',
        BRANCH: '[data-region="branch"]',
        COMPETENCIES: 'select[name="competencies[]"]'
    };

    /**
     * Open the drawer and render the framework and branch pickers.
     *
     * @param {Number} contextId The activity or course context id.
     * @return {Promise} Resolves once the pickers are in the drawer.
     */
    var openPickers = function(contextId) {
        return Ajax.call([{
            methodname: 'local_dimensions_browse_structure',
            args: {contextid: contextId}
        }])[0].then(function(structure) {
            return Templates.renderForPromise('aiplacement_dimensions/pickers', {
                frameworks: structure.frameworks || [],
                branches: []
            });
        }).then(function(rendered) {
            document.querySelector(SELECTORS.DRAWER).hidden = false;
            document.querySelector(SELECTORS.BODY).innerHTML = rendered.html;
            Templates.runTemplateJS(rendered.js);
            return rendered;
        });
    };

    return {
        /**
         * Initialise the placement.
         *
         * @param {Number} cmId The course module id, or 0 for an activity not yet created.
         * @param {Number} courseId The course id.
         * @param {Number} contextId The activity or course context id.
         * @return {void}
         */
        init: function(cmId, courseId, contextId) {
            /*
             * cmId, courseId and contextId are captured in this closure on purpose.
             * The click handler below is a plain function, so `this` inside it
             * is the document, not the module — reading this.contextId there
             * would silently yield undefined.
             */
            document.addEventListener('click', function(e) {
                if (e.target.closest(SELECTORS.LAUNCH)) {
                    e.preventDefault();
                    openPickers(contextId).catch(Notification.exception);
                    return;
                }

                if (e.target.closest(SELECTORS.CLOSE)) {
                    e.preventDefault();
                    document.querySelector(SELECTORS.DRAWER).hidden = true;
                }
            }, false);
        }
    };
});
```

`cmId` and `courseId` are unused in this task and are consumed by Task 7. The web service takes
`cmid` and `courseid` rather than a context id on purpose — see Task 5's rationale. Keep the parameter — `lib.php` already passes it and Task 7 needs it in the same closure.

Confirm the return shape of `local_dimensions_browse_structure` before relying on `structure.frameworks`; read `public/local/dimensions/classes/external/browse_structure.php` and use its real key names.

- [ ] **Step 6: Build the AMD bundle**

```bash
cd /Volumes/N1TB/dev/github/moodle
npx grunt amd --root=public/ai/placement/dimensions
```

Confirm `amd/build/suggest.min.js` and `amd/build/suggest.min.js.map` were written. Grunt compiles the main checkout rather than a worktree and can report success without rebuilding — check the file mtimes before committing.

- [ ] **Step 7: Add the lang strings, alphabetically sorted**

```php
$string['brancheslabel'] = 'Limit to these branches';
$string['frameworklabel'] = 'Competency framework';
$string['runbutton'] = 'Suggest';
$string['suggestbutton'] = 'Suggest competencies with AI';
```

- [ ] **Step 8: Commit**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/ai/placement/dimensions
git add lib.php amd templates lang/en/aiplacement_dimensions.php
git commit -m "feat: add the suggestion button and the drawer it opens

Six availability gates in lib.php, and a drawer that inherits nothing from
any other placement and namespaces every selector."
```

---

## Task 7: Suggest, render, and apply

**Deliverable:** pressing "Suggest" in the drawer calls the model, renders the resolved suggestions with their rationale, and applying them links the competencies to the course and selects them in the form's own field.

**Files:**
- Modify: `amd/src/suggest.js` and its rebuilt bundle
- Create: `templates/suggestions.mustache`, `templates/applied.mustache`
- Modify: `lang/en/aiplacement_dimensions.php`

**Interfaces:**
- Consumes: `aiplacement_dimensions_suggest_competencies` (Task 5); `local_dimensions_link_competency_course(competencyid, courseid)`; `SELECTORS`, `openPickers` and the click handler from Task 6.
- Produces: nothing for later PHP tasks. Behat in Task 8 drives the run button, the checkboxes and `[data-action="aiplacement-dimensions-apply"]`.

- [ ] **Step 1: Spike the chip re-render, before writing the apply code**

This is the one open question in the spec. Budget 30 minutes.

`lib/amd/src/form-autocomplete.js` exports only `enhance` and `enhanceField` (`:1326-1336`), and `updateSelectionList` (`:152`) rebuilds the visible chips from `originalSelect.children('option:selected')` (`:157`) on init and on user interaction — there is **no `change` listener on the original select**. So appending an option externally updates the data but not the chips.

In a browser on an activity edit page, with the competencies field present, run in the console:

```js
require(['jquery', 'core/form-autocomplete'], function($, AC) {
    var sel = document.querySelector('select[name="competencies[]"]');
    var opt = new Option('Test competency', '999', true, true);
    sel.appendChild(opt);
    AC.enhanceField(sel);
});
```

Observe whether the chip list updates and whether the DOM gains a duplicate autocomplete widget.

- If it updates cleanly: use `enhanceField` in Step 5.
- If it duplicates: use the fallback in Step 5 and leave the chips alone.

Either way the save is correct, because the form submits the hidden select and not the chips. Record which branch you took in the commit message.

- [ ] **Step 2: Write `templates/suggestions.mustache`**

`data-suggestion` carries the whole resolved record, which is what the apply handler parses. The empty state and both notices live here, so no state is silent.

```
{{!
    @template aiplacement_dimensions/suggestions

    The resolved suggestions, plus the discard and truncation notices.

    Example context (json):
    {
        "suggestions": [{"id": 5, "idnumber": "K5", "shortname": "Alpha", "confidence": 0.8,
                         "why": "covers it", "json": "{}"}],
        "discarded": 0,
        "truncated": false,
        "candidatecount": 12,
        "sentcount": 12
    }
}}
{{#truncated}}
    <div class="alert alert-warning" role="status">
        {{#str}} truncatednotice, aiplacement_dimensions, {"sent": "{{sentcount}}", "total": "{{candidatecount}}"} {{/str}}
    </div>
{{/truncated}}

{{#discarded}}
    <div class="alert alert-warning" role="status">
        {{#str}} discardednotice, aiplacement_dimensions, {{discarded}} {{/str}}
    </div>
{{/discarded}}

{{^nocandidates}}
    {{#contenttruncated}}
        <div class="alert alert-warning" role="status">
            {{#str}} contenttruncatednotice, aiplacement_dimensions {{/str}}
        </div>
    {{/contenttruncated}}
{{/nocandidates}}

{{#undecodable}}
    <div class="alert alert-danger" role="alert">
        {{#str}} undecodablenotice, aiplacement_dimensions {{/str}}
    </div>
{{/undecodable}}

{{#suggestions.length}}
    <ul class="list-unstyled aiplacement-dimensions-suggestions">
        {{#suggestions}}
            <li class="form-check mb-2">
                <input class="form-check-input" type="checkbox" checked
                       id="aiplacement-dimensions-pick-{{id}}"
                       data-region="aiplacement-dimensions-pick"
                       data-suggestion="{{json}}">
                <label class="form-check-label" for="aiplacement-dimensions-pick-{{id}}">
                    <span class="fw-semibold">{{idnumber}}</span> {{shortname}}
                    {{#why}}<span class="d-block text-muted small">{{why}}</span>{{/why}}
                </label>
            </li>
        {{/suggestions}}
    </ul>
    <button type="button" class="btn btn-primary" data-action="aiplacement-dimensions-apply">
        {{#str}} applybutton, aiplacement_dimensions {{/str}}
    </button>
{{/suggestions.length}}

{{^suggestions.length}}
    {{^undecodable}}
        {{#nocandidates}}
            <p class="text-muted">{{#str}} nocandidates, aiplacement_dimensions {{/str}}</p>
        {{/nocandidates}}
        {{^nocandidates}}
            <p class="text-muted">{{#str}} nosuggestions, aiplacement_dimensions {{/str}}</p>
        {{/nocandidates}}
    {{/undecodable}}
{{/suggestions.length}}
```

- [ ] **Step 3: Write `templates/applied.mustache`**

```
{{!
    @template aiplacement_dimensions/applied

    Outcome notice rendered under the competencies field after applying.

    Example context (json):
    {
        "added": [{"id": 1, "shortname": "Alpha"}],
        "failed": []
    }
}}
<div class="aiplacement-dimensions-applied alert alert-info" role="status">
    {{#added.length}}
        <p class="mb-1">{{#str}} appliedheading, aiplacement_dimensions {{/str}}</p>
        <ul class="mb-0">
            {{#added}}<li>{{shortname}}</li>{{/added}}
        </ul>
    {{/added.length}}
    {{#failed.length}}
        <p class="mb-1 mt-2">{{#str}} failedheading, aiplacement_dimensions {{/str}}</p>
        <ul class="mb-0">
            {{#failed}}<li>{{shortname}}</li>{{/failed}}
        </ul>
    {{/failed.length}}
</div>
```

- [ ] **Step 4: Extend `amd/src/suggest.js`**

Add these to the module alongside Task 6's `openPickers`. Add `PICK: 'input[data-region="aiplacement-dimensions-pick"]'` and `APPLY: '[data-action="aiplacement-dimensions-apply"]'` to `SELECTORS`.

```js
    /**
     * Read the unsaved activity description from the form.
     *
     * @return {String} The content to classify.
     */
    var readContent = function() {
        var textarea = document.querySelector('#id_introeditor, [name="intro[text]"]');
        if (textarea && typeof textarea.value === 'string' && textarea.value.trim()) {
            return textarea.value.trim();
        }
        var editable = document.querySelector('[id^="id_introeditor"][contenteditable="true"]');
        return editable ? editable.textContent.trim() : '';
    };

    /**
     * Ask the model and render the resolved suggestions.
     *
     * @param {Number} cmId The course module id, or 0 for an activity not yet created.
     * @param {Number} courseId The course id.
     * @return {Promise} Resolves when the suggestions are rendered.
     */
    var runSuggestion = function(cmId, courseId) {
        var frameworkSelect = document.querySelector(SELECTORS.FRAMEWORK);
        var branches = Array.prototype.slice.call(
            document.querySelectorAll(SELECTORS.BRANCH + ':checked')
        ).map(function(input) {
            return parseInt(input.value, 10);
        });

        return Ajax.call([{
            methodname: 'aiplacement_dimensions_suggest_competencies',
            args: {
                cmid: cmId,
                courseid: courseId,
                frameworkid: parseInt(frameworkSelect.value, 10),
                rootids: branches,
                content: readContent()
            }
        }])[0].then(function(response) {
            if (!response.success) {
                return Str.get_string('error_provider', 'aiplacement_dimensions', response.errorcode)
                    .then(function(message) {
                        var body = document.querySelector(SELECTORS.BODY);
                        body.innerHTML = '<div class="alert alert-danger" role="alert"></div>';
                        body.querySelector('.alert').textContent = message;
                        return response;
                    });
            }

            return Templates.renderForPromise('aiplacement_dimensions/suggestions', {
                suggestions: response.suggestions.map(function(suggestion) {
                    return Object.assign({}, suggestion, {json: JSON.stringify(suggestion)});
                }),
                discarded: response.discarded,
                undecodable: response.undecodable,
                contenttruncated: response.contenttruncated,
                /*
                 * candidatecount 0 is exactly and only the "nothing was ever in scope"
                 * case: the service returns before calling the provider. Without this
                 * flag the template would tell the user the model found no clear match,
                 * when no model was consulted at all.
                 */
                nocandidates: response.candidatecount === 0,
                truncated: response.truncated,
                candidatecount: response.candidatecount,
                sentcount: response.suggestions.length
            }).then(function(rendered) {
                document.querySelector(SELECTORS.BODY).innerHTML = rendered.html;
                Templates.runTemplateJS(rendered.js);
                return rendered;
            });
        });
    };

    /**
     * Append a competency to the form's own competencies select.
     *
     * The form submits this select, not the visible chips, so appending here is
     * what makes tool_lp create the module link on save. We never write the
     * module link ourselves: tool_lp_coursemodule_edit_post_actions diffs this
     * element and would remove anything absent from it.
     *
     * @param {Object} suggestion The resolved suggestion.
     * @return {void}
     */
    var appendToForm = function(suggestion) {
        var select = document.querySelector(SELECTORS.COMPETENCIES);
        if (!select) {
            return;
        }
        var existing = select.querySelector('option[value="' + suggestion.id + '"]');
        if (existing) {
            existing.selected = true;
            return;
        }
        select.appendChild(new Option(suggestion.shortname, suggestion.id, true, true));
    };

    /**
     * Link the checked suggestions to the course and select them in the form.
     *
     * @param {Number} courseId The course id.
     * @return {Promise} Resolves with one outcome record per suggestion.
     */
    var applyPicks = function(courseId) {
        var picks = Array.prototype.slice.call(
            document.querySelectorAll(SELECTORS.PICK + ':checked')
        ).map(function(input) {
            return JSON.parse(input.dataset.suggestion);
        });

        /*
         * One call per competency. A single Ajax.call batch aborts the
         * remainder on the first exception, losing the rest silently.
         */
        return Promise.all(picks.map(function(pick) {
            return Ajax.call([{
                methodname: 'local_dimensions_link_competency_course',
                args: {competencyid: pick.id, courseid: courseId}
            }])[0].then(function() {
                appendToForm(pick);
                return {pick: pick, ok: true};
            }).catch(function() {
                return {pick: pick, ok: false};
            });
        }));
    };
```

Then extend the click handler with two branches, using the closure variables `contextId` and `courseId` and never `this`:

```js
                if (e.target.closest(SELECTORS.RUN)) {
                    e.preventDefault();
                    runSuggestion(cmId, courseId).catch(Notification.exception);
                    return;
                }

                if (e.target.closest(SELECTORS.APPLY)) {
                    e.preventDefault();
                    applyPicks(courseId).then(function(results) {
                        return showOutcome(results);
                    }).catch(Notification.exception);
                    return;
                }
```

There is no `window.location.reload()` anywhere in this module. Reloading would discard the unsaved content that produced the classification.

- [ ] **Step 5: Write `showOutcome` according to the Step 1 spike**

If `enhanceField` was clean:

```js
    /**
     * Report the outcome and refresh the autocomplete chips.
     *
     * @param {Array} results The outcome records from applyPicks.
     * @return {Promise} Resolves once the chips are refreshed.
     */
    var showOutcome = function(results) {
        return new Promise(function(resolve) {
            require(['core/form-autocomplete'], function(AC) {
                AC.enhanceField(document.querySelector(SELECTORS.COMPETENCIES));
                resolve(results);
            });
        });
    };
```

If it duplicated the widget, use the fallback instead, which leaves the chips untouched:

```js
    /**
     * Report the outcome under the competencies field.
     *
     * @param {Array} results The outcome records from applyPicks.
     * @return {Promise} Resolves once the notice is rendered.
     */
    var showOutcome = function(results) {
        return Templates.renderForPromise('aiplacement_dimensions/applied', {
            added: results.filter(function(r) { return r.ok; }).map(function(r) { return r.pick; }),
            failed: results.filter(function(r) { return !r.ok; }).map(function(r) { return r.pick; })
        }).then(function(rendered) {
            /*
             * The outcome replaces the drawer body rather than being inserted above the
             * drawer. Inserting above meant a user scrolled down a long suggestion list
             * might never see the confirmation, and a second Apply stacked a duplicate
             * notice. Replacing keeps the result where the user is already looking and
             * makes repeat clicks impossible, since the Apply button goes with it.
             */
            document.querySelector(SELECTORS.BODY).innerHTML = rendered.html;
            Templates.runTemplateJS(rendered.js);
            return rendered;
        });
    };
```

Ship exactly one of these two, not both. If you ship the `enhanceField` variant, `templates/applied.mustache` and its two lang strings are unused — delete them rather than leaving dead files.

- [ ] **Step 6: Rebuild the AMD bundle**

```bash
cd /Volumes/N1TB/dev/github/moodle
npx grunt amd --root=public/ai/placement/dimensions
```

Check the mtimes of `amd/build/suggest.min.js` and its map before committing.

- [ ] **Step 7: Add the lang strings, alphabetically sorted**

Ship the `applied*` pair only if Step 5 kept the fallback variant.

```php
$string['appliedheading'] = 'Added to the course and selected below. Save the form to link them to this activity.';
$string['applybutton'] = 'Add selected';
$string['contenttruncatednotice'] = 'The activity content was long, so only the first part of it was sent to the model.';
$string['discardednotice'] = 'The model returned {$a} answer(s) that could not be matched to a competency.';
$string['error_provider'] = 'The AI provider could not complete the request (code {$a}).';
$string['failedheading'] = 'Could not be added:';
$string['nocandidates'] = 'The competencies you chose have no sub-competencies to classify against.';
$string['nosuggestions'] = 'The model did not find a clear match in this framework.';
$string['truncatednotice'] = 'Only the first {$a->sent} of {$a->total} competencies were sent to the model.';
$string['undecodablenotice'] = 'The AI provider replied, but its answer could not be read. Nothing was suggested. Try again.';
```

- [ ] **Step 8: Commit**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/ai/placement/dimensions
git add amd templates lang/en/aiplacement_dimensions.php
git commit -m "feat: suggest, render and apply the resolved competencies

Applies by linking to the course and appending to the form's own
competencies select, so the native save creates the module link.
Suggestions carry their resolved record as JSON on the checkbox, so
nothing is ever re-parsed out of the DOM text."
```

---

## Task 8: Behat — the scenario that proves the design

**Files:**
- Create: `tests/behat/suggest_competencies.feature`

**Interfaces:**
- Consumes: the button `[data-action="aiplacement-dimensions-suggest"]` from Task 6.
- Produces: nothing.

- [ ] **Step 1: Write the availability scenarios**

These need no JavaScript and no provider.

```gherkin
@aiplacement @aiplacement_dimensions
Feature: AI competency suggestions are offered only when they are allowed
  In order to keep AI use under site control
  As a teacher
  I need the suggestion button to appear only when every gate allows it

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email             |
      | teacher1 | Teacher   | One      | teacher1@test.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | name       | course | idnumber |
      | assign   | Assignment | C1     | assign1  |

  Scenario: The button is absent when the placement is disabled
    Given the following config values are set as admin:
      | enabled | 0 | aiplacement_dimensions |
    When I log in as "teacher1"
    And I am on the "Assignment" "assign activity editing" page
    Then "Suggest competencies with AI" "button" should not exist

  Scenario: The button is absent for a role that is prohibited
    Given the following "permission overrides" exist:
      | capability                     | permission | role           | contextlevel | reference |
      | aiplacement/dimensions:suggest | Prohibit   | editingteacher | Course       | C1        |
    When I log in as "teacher1"
    And I am on the "Assignment" "assign activity editing" page
    Then "Suggest competencies with AI" "button" should not exist
```

- [ ] **Step 2: Write the happy-path scenario**

The step that matters is the **save** — it is what proves the module link is created by `tool_lp`, not by us.

```gherkin
  @javascript
  Scenario: Applying a suggestion links the competency once the form is saved
    Given a mocked AI provider returns competency pick 1
    And the following "core_competency > frameworks" exist:
      | shortname | idnumber |
      | Framework | fw1      |
    And the following "core_competency > competencies" exist:
      | shortname | idnumber | competencyframework |
      | Root      | root1    | fw1                 |
      | Alpha     | alpha1   | fw1                 |
    When I log in as "teacher1"
    And I am on the "Assignment" "assign activity editing" page
    And I press "Suggest competencies with AI"
    And I select "Framework" from the "Competency framework" singleselect
    And I press "Suggest"
    And I click on "Alpha" "checkbox"
    And I press "Add selected"
    And I press "Save and return to course"
    And I am on the "Assignment" "assign activity editing" page
    Then I should see "Alpha" in the "#id_competenciessectioncontainer" "css_element"
```

- [ ] **Step 2b: Write the new-activity scenario**

The spec claims this design works on an unsaved activity, which the plugin it replaces had to disable. An untested claim is a guess, so prove it.

```gherkin
  @javascript
  Scenario: Suggestions work on an activity that has never been saved
    Given a mocked AI provider returns competency pick 1
    And the following "core_competency > frameworks" exist:
      | shortname | idnumber |
      | Framework | fw1      |
    And the following "core_competency > competencies" exist:
      | shortname | idnumber | competencyframework |
      | Alpha     | alpha1   | fw1                 |
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Page" to section "1"
    And I set the field "Name" to "Brand new page"
    And I set the field "Description" to "Teaches the Alpha competency."
    And I press "Suggest competencies with AI"
    And I select "Framework" from the "Competency framework" singleselect
    And I press "Suggest"
    And I click on "Alpha" "checkbox"
    And I press "Add selected"
    And I press "Save and return to course"
    And I am on the "Brand new page" "page activity editing" page
    Then I should see "Alpha" in the "#id_competenciessectioncontainer" "css_element"
```

On the add path there is no cmid yet and `$formwrapper->get_context()` returns the **course** context. Both capability checks and `is_action_enabled_in_context()` accept a course context, so the gates hold. If this scenario fails at the gate rather than at the assertion, the cause is `lib.php` assuming a module context — fix `lib.php`, not the test.

- [ ] **Step 3: Write the stub-provider step definition**

The `Given a mocked AI provider returns competency pick 1` step needs a definition. Write it in `tests/behat/behat_aiplacement_dimensions.php`, installing a mocked `\core_ai\manager` through `\core\di::set()` the same way `tests/external/suggest_competencies_test.php` does. Read `ai/placement/courseassist/tests/behat/` first: if core already ships a reusable stub-provider step, use it rather than writing a second one.

Behat traps that have cost this project repeated CI failures: set autocomplete values with the `set-field` step rather than clicking through the widget; address a dialogue by its title; a checkbox step needs a real label.

- [ ] **Step 4: Run Behat**

Behat is not initialised in this checkout. Run it in CI: commit on a branch and let the workflow report. If you want a local run, initialise Behat first — that is a separate setup from the PHPUnit one in the header.

- [ ] **Step 5: Commit**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/ai/placement/dimensions
git add tests/behat
git commit -m "test: cover the availability gates and the save-creates-the-link path"
```

---

## Task 9: String-existence test and pt_br

**Files:**
- Create: `tests/lang_test.php`
- Create: `lang/pt_br/aiplacement_dimensions.php`

**Interfaces:**
- Consumes: every lang key used anywhere in the plugin.
- Produces: nothing.

This test would have caught three shipped defects in the plugin being replaced.

- [ ] **Step 1: Write the failing test**

```php
namespace aiplacement_dimensions;

/**
 * Every referenced language string is defined.
 *
 * @package    aiplacement_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class lang_test extends \advanced_testcase {
    /**
     * Collect every key referenced with this component and assert it exists.
     *
     * @return void
     */
    public function test_every_referenced_string_exists(): void {
        global $CFG;

        $root = $CFG->dirroot . '/ai/placement/dimensions';
        $patterns = [
            '/\{\{#str\}\}\s*([a-z0-9_]+)\s*,\s*aiplacement_dimensions\s*\{\{\/str\}\}/i',
            '/get_string\(\s*[\'"]([a-z0-9_:]+)[\'"]\s*,\s*[\'"]aiplacement_dimensions[\'"]/i',
            '/moodle_exception\(\s*[\'"]([a-z0-9_:]+)[\'"]\s*,\s*[\'"]aiplacement_dimensions[\'"]/i',
        ];

        $keys = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if (!$file->isFile() || !preg_match('/\.(php|mustache|js)$/', $file->getFilename())) {
                continue;
            }
            if (str_contains($file->getPathname(), '/lang/') || str_contains($file->getPathname(), '/amd/build/')) {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $contents, $matches)) {
                    $keys = array_merge($keys, $matches[1]);
                }
            }
        }

        $keys = array_unique($keys);
        $this->assertNotEmpty($keys, 'the scanner found no keys, so it is broken');

        $missing = [];
        foreach ($keys as $key) {
            if (!get_string_manager()->string_exists($key, 'aiplacement_dimensions')) {
                $missing[] = $key;
            }
        }

        $this->assertSame([], $missing, 'undefined language strings: ' . implode(', ', $missing));
    }
}
```

- [ ] **Step 2: Run it and fix whatever it reports**

```bash
cd /Volumes/N1TB/dev/github/moodle
PHP_INI_SCAN_DIR="/opt/homebrew/etc/php/8.5/conf.d:/tmp/phpini" php vendor/bin/phpunit --filter lang_test --testsuite aiplacement_dimensions_testsuite
```

Expected on the first run: it may FAIL, naming keys referenced in Task 6's templates that were never added. Add each missing string to `lang/en/aiplacement_dimensions.php` in alphabetical position, then re-run until green. A failure here is the test doing its job.

- [ ] **Step 3: Write `lang/pt_br/aiplacement_dimensions.php`**

Same keys, same alphabetical order, translated values. The `validate` CI step enforces the ordering, and the two files must stay in sync.

- [ ] **Step 4: Run the whole suite**

```bash
cd /Volumes/N1TB/dev/github/moodle
PHP_INI_SCAN_DIR="/opt/homebrew/etc/php/8.5/conf.d:/tmp/phpini" php vendor/bin/phpunit --testdox --testsuite aiplacement_dimensions_testsuite
```

Expected: every test passing, zero errors, zero skipped. A suite that reports errors or runs zero tests in a file is not green — that is exactly the state the replaced plugin shipped in.

- [ ] **Step 5: Commit**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/ai/placement/dimensions
git add tests/lang_test.php lang
git commit -m "test: assert every referenced language string is defined

Adds pt_br alongside en, both alphabetically sorted."
```

---

## Deliberately not in this plan

Two items from the spec's "Inherited ideas" have no task here. They are deferred, not forgotten:

- **Refusing the feature when there is nothing to classify.** The replaced plugin gated the button on a per-module content check. This design does not need it to be *correct* — empty content simply yields no suggestions, and Task 7's empty state says so — but it does waste a provider call. Add it as a slice once real usage shows whether the wasted call matters.
- **The Competency hub tab (bulk mapping).** Spec slice 4. It reuses `candidates`, `prompt`, `resolver` and the web service unchanged, and only replaces the surface and the target. Nothing in Tasks 1 to 9 should assume a single activity is the only caller — keep the web service taking a `contextid`, not a `cmid`.

## Definition of done

- `--testsuite aiplacement_dimensions_testsuite` runs green with no errors and no file contributing zero tests.
- CI is green on `MOODLE_501_STABLE` and `MOODLE_502_STABLE`, with `local_dimensions` installed as an extra plugin.
- The Behat happy path proves the competency is linked to the activity **after the form is saved**, not before.
- `git check-attr export-ignore -- docs .github` reports both as set, and `git archive HEAD | tar -t` contains no `.DS_Store` and no `docs/`.
- Nothing has been pushed.
