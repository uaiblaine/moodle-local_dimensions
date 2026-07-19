# Central hub view-state persistence Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Persist the Competency hub's last-visited view (active tab, System/Course-category context + category, selected framework/template) and its display-toggle choices as per-user Moodle preferences, and implement a complete Privacy API provider that also cleans those preferences up on uninstall.

**Architecture:** Two JSON user preferences (`local_dimensions_central_nav`, `local_dimensions_central_display`) declared through a `lib.php` `*_user_preferences()` callback (no web service). The server (`central.php`) restores navigation and pre-renders the saved tab; a new `preferences.js` module holds the state in memory (seeded from the server) and saves changes via `core_user/repository`'s `setUserPreference` (debounced), replacing the old `sessionStorage`. A preference-only privacy provider exports the data; `db/uninstall.php` purges it.

**Tech Stack:** Moodle 5.x plugin API (supports 4.5→5.2), PHP (moodle coding standard), AMD/ES6 JavaScript built with grunt, PHPUnit + Behat, `core_user/repository`, `core_privacy`.

---

## Verification model (read first)

This working tree has **no installed Moodle** (no `config.php`), so PHP-side checks have **no local runner**:

- **Runnable locally:** `npx eslint`, `npx stylelint`, `npx grunt amd`, `grep`/`awk` pre-push checks. Run from the Moodle root `/Volumes/N1TB/dev/github/moodle`.
- **CI / user's Moodle only:** PHPUnit, Behat, phpcs, phpdoc, mustache-lint, `validate`. For those steps the plan gives the exact command to run on a Moodle-enabled environment; treat "verify it fails/passes" as happening in CI (the repo pushes to `main` and CI runs the moodle-workflows matrix).

**Branch first.** The repo's convention is direct-to-`main`, but per the harness rule create a working branch before the first commit (e.g. `git -C public/local/dimensions checkout -b feature/central-prefs`), and only push / open the change when the user authorizes it. All `git` commands below run inside the plugin clone `/Volumes/N1TB/dev/github/moodle/public/local/dimensions`. Every commit message ends with the `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>` trailer.

**Reference (design):** `docs/superpowers/specs/2026-07-10-central-preferences-persistence-design.md`.

---

## File structure

| File | Create/Modify | Responsibility |
|---|---|---|
| `classes/constants.php` | Modify | Add `PREF_CENTRAL_NAV` / `PREF_CENTRAL_DISPLAY` name constants. |
| `lib.php` | Modify | Add `local_dimensions_user_preferences()` callback. |
| `classes/helper.php` | Modify | Add `get_central_prefs()` (read+sanitise) and `purge_user_preferences()`. |
| `classes/privacy/provider.php` | Modify (rewrite) | `metadata\provider` + `user_preference_provider`. |
| `db/uninstall.php` | Modify | Call `helper::purge_user_preferences()`. |
| `lang/en/local_dimensions.php` | Modify | Add two `privacy:metadata:preference:*` strings; remove stale `privacy:metadata`. |
| `lang/pt_br/local_dimensions.php` | Modify | Same, in sync. |
| `version.php` | Modify | Bump to `2026071000`. |
| `db/upgrade.php` | Modify | Savepoint block for `2026071000`. |
| `amd/src/central/preferences.js` | Create | In-memory view-state store + debounced `setUserPreference`. |
| `central.php` | Modify | Restore nav, pre-render saved tab, seed `templateid`, init `preferences.js`. |
| `classes/output/dynamictabs/frameworks.php` | Modify | Fall back `showhidden` to the saved pref. |
| `amd/src/central/context.js` | Modify | Save tab + context/category changes. |
| `amd/src/central/structure.js` | Modify | Delegate display/show-hidden to `preferences.js`; save framework. |
| `amd/src/central/plans.js` | Modify | Delegate display/show-disabled to `preferences.js`; save template. |
| `amd/src/central/frameworks.js` | Modify | Save frameworks show-hidden. |
| `amd/build/**` | Modify (generated) | grunt rebuild of the five touched modules. |
| `tests/preferences_test.php` | Create | Callback + `get_central_prefs` + `purge_user_preferences`. |
| `tests/privacy/provider_test.php` | Create | Metadata + export. |
| `tests/behat/central_restore.feature` | Create | Thin last-tab restore smoke test. |
| `README.md` | Modify | Document persistence + privacy behaviour. |

---

## Task 1: Preference names + callback + helper (backend foundation)

**Files:**
- Modify: `classes/constants.php`
- Modify: `lib.php:129` (append after the last function)
- Modify: `classes/helper.php` (add two public static methods; place near `resolve_central_context`)
- Test: `tests/preferences_test.php` (create)

- [ ] **Step 1: Add the preference-name constants**

In `classes/constants.php`, after the `CFIELD_SINGLECOURSEREDIRECT` constant (line 72), add:

```php
    /** @var string User preference: Competency hub last-visited view (tab/context/category/selection) as JSON */
    const PREF_CENTRAL_NAV = 'local_dimensions_central_nav';

    /** @var string User preference: Competency hub display-toggle visibility state as JSON */
    const PREF_CENTRAL_DISPLAY = 'local_dimensions_central_display';
```

- [ ] **Step 2: Add the `*_user_preferences()` callback**

Append to `lib.php` (after `local_dimensions_get_return_context_for_course()`, at line 129):

```php

/**
 * Declare the plugin's AJAX-updatable user preferences (Competency hub view state).
 *
 * Registers the two JSON preferences that persist the hub's last-visited view and its
 * display-toggle choices, so core_user's preference web service accepts them from the hub's
 * JavaScript. Each is writable only by its owner. Discovered by get_plugins_with_function().
 *
 * @return array Preference definitions keyed by preference name.
 */
function local_dimensions_user_preferences(): array {
    $definition = [
        'null' => NULL_ALLOWED,
        'default' => '',
        'type' => PARAM_RAW,
        'permissioncallback' => [\core_user::class, 'is_current_user'],
    ];
    return [
        \local_dimensions\constants::PREF_CENTRAL_NAV => $definition,
        \local_dimensions\constants::PREF_CENTRAL_DISPLAY => $definition,
    ];
}
```

- [ ] **Step 3: Add `get_central_prefs()` + `purge_user_preferences()` to the helper**

In `classes/helper.php`, add these two public static methods (place them just before `resolve_central_context()`). `constants` is in the same `local_dimensions` namespace, so reference it as `constants::`:

```php
    /**
     * Read and sanitise the Competency hub's per-user view-state preferences.
     *
     * Returns a two-key array: 'nav' (last tab, context, category and the selected framework /
     * template) and 'display' (the visibility toggles). Each is decoded from its JSON user
     * preference and validated against defaults, so a missing, empty or corrupt preference
     * always yields safe defaults. Booleans/ints are coerced; unknown values fall back.
     *
     * @return array ['nav' => array, 'display' => array]
     */
    public static function get_central_prefs(): array {
        $navraw = json_decode((string) get_user_preferences(constants::PREF_CENTRAL_NAV, ''), true);
        $displayraw = json_decode((string) get_user_preferences(constants::PREF_CENTRAL_DISPLAY, ''), true);
        if (!is_array($navraw)) {
            $navraw = [];
        }
        if (!is_array($displayraw)) {
            $displayraw = [];
        }

        $tab = (string) ($navraw['tab'] ?? 'frameworks');
        if (!in_array($tab, ['frameworks', 'structure', 'plans'], true)) {
            $tab = 'frameworks';
        }
        $nav = [
            'tab' => $tab,
            'contexttype' => ($navraw['contexttype'] ?? 'system') === 'coursecat' ? 'coursecat' : 'system',
            'categoryid' => (int) ($navraw['categoryid'] ?? 0),
            'frameworkid' => (int) ($navraw['frameworkid'] ?? 0),
            'templateid' => (int) ($navraw['templateid'] ?? 0),
        ];

        $dispbool = static function (array $src, string $key, bool $default): bool {
            return array_key_exists($key, $src) ? (bool) $src[$key] : $default;
        };
        $structsrc = is_array($displayraw['structure'] ?? null) ? $displayraw['structure'] : [];
        $listsrc = is_array($displayraw['planslist'] ?? null) ? $displayraw['planslist'] : [];
        $detailsrc = is_array($displayraw['plansdetail'] ?? null) ? $displayraw['plansdetail'] : [];
        $display = [
            'structure' => [
                'tax' => $dispbool($structsrc, 'tax', false),
                'id' => $dispbool($structsrc, 'id', false),
                'rule' => $dispbool($structsrc, 'rule', true),
                'showhidden' => $dispbool($structsrc, 'showhidden', false),
            ],
            'planslist' => [
                'id' => $dispbool($listsrc, 'id', false),
                'duedate' => $dispbool($listsrc, 'duedate', false),
            ],
            'plansdetail' => [
                'tax' => $dispbool($detailsrc, 'tax', false),
                'path' => $dispbool($detailsrc, 'path', false),
                'id' => $dispbool($detailsrc, 'id', false),
            ],
            'plansshowdisabled' => $dispbool($displayraw, 'plansshowdisabled', false),
            'frameworksshowhidden' => $dispbool($displayraw, 'frameworksshowhidden', false),
        ];

        return ['nav' => $nav, 'display' => $display];
    }

    /**
     * Delete every user preference this plugin owns, for all users.
     *
     * Moodle does not purge a component's user_preferences rows on uninstall (the table has no
     * component column), so the uninstall hook calls this to avoid orphaned rows. Deletes by the
     * plugin's frankenstyle name prefix.
     *
     * @return void
     */
    public static function purge_user_preferences(): void {
        global $DB;
        $DB->delete_records_select(
            'user_preferences',
            $DB->sql_like('name', ':pattern'),
            ['pattern' => $DB->sql_like_escape('local_dimensions_') . '%']
        );
    }
```

- [ ] **Step 4: Write the failing tests**

Create `tests/preferences_test.php`:

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
 * Tests for the Competency hub view-state user preferences.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions;

use advanced_testcase;

/**
 * Unit tests for the preference callback, helper reader and uninstall purge.
 *
 * @covers \local_dimensions\helper::get_central_prefs
 * @covers \local_dimensions\helper::purge_user_preferences
 */
final class preferences_test extends advanced_testcase {
    /**
     * The lib callback declares both preferences with the expected type and owner gate.
     *
     * @return void
     */
    public function test_user_preferences_callback_declares_both_preferences(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/dimensions/lib.php');
        $prefs = local_dimensions_user_preferences();
        $this->assertArrayHasKey(constants::PREF_CENTRAL_NAV, $prefs);
        $this->assertArrayHasKey(constants::PREF_CENTRAL_DISPLAY, $prefs);
        $this->assertSame(PARAM_RAW, $prefs[constants::PREF_CENTRAL_NAV]['type']);
        $this->assertSame(
            [\core_user::class, 'is_current_user'],
            $prefs[constants::PREF_CENTRAL_NAV]['permissioncallback']
        );
    }

    /**
     * With nothing stored, defaults are returned (frameworks / system, rule on).
     *
     * @return void
     */
    public function test_get_central_prefs_returns_defaults_when_unset(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $prefs = helper::get_central_prefs();
        $this->assertSame('frameworks', $prefs['nav']['tab']);
        $this->assertSame('system', $prefs['nav']['contexttype']);
        $this->assertSame(0, $prefs['nav']['categoryid']);
        $this->assertTrue($prefs['display']['structure']['rule']);
        $this->assertFalse($prefs['display']['structure']['tax']);
        $this->assertFalse($prefs['display']['frameworksshowhidden']);
    }

    /**
     * Stored values are read and coerced to the right types; unset keys keep defaults.
     *
     * @return void
     */
    public function test_get_central_prefs_reads_and_sanitises_stored_values(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_user_preference(constants::PREF_CENTRAL_NAV, json_encode([
            'tab' => 'plans', 'contexttype' => 'coursecat', 'categoryid' => '7',
            'frameworkid' => '3', 'templateid' => '9',
        ]), $USER->id);
        set_user_preference(constants::PREF_CENTRAL_DISPLAY, json_encode([
            'structure' => ['tax' => true, 'rule' => false],
            'plansshowdisabled' => true,
        ]), $USER->id);
        $prefs = helper::get_central_prefs();
        $this->assertSame('plans', $prefs['nav']['tab']);
        $this->assertSame('coursecat', $prefs['nav']['contexttype']);
        $this->assertSame(7, $prefs['nav']['categoryid']);
        $this->assertSame(3, $prefs['nav']['frameworkid']);
        $this->assertSame(9, $prefs['nav']['templateid']);
        $this->assertTrue($prefs['display']['structure']['tax']);
        $this->assertFalse($prefs['display']['structure']['rule']);
        $this->assertFalse($prefs['display']['structure']['id']);
        $this->assertTrue($prefs['display']['plansshowdisabled']);
    }

    /**
     * Corrupt JSON and wrong-shaped sections fall back to defaults without error.
     *
     * @return void
     */
    public function test_get_central_prefs_falls_back_on_corrupt_data(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_user_preference(constants::PREF_CENTRAL_NAV, 'not-json', $USER->id);
        set_user_preference(constants::PREF_CENTRAL_DISPLAY, json_encode([
            'structure' => 'wrong-shape',
        ]), $USER->id);
        $prefs = helper::get_central_prefs();
        $this->assertSame('frameworks', $prefs['nav']['tab']);
        $this->assertTrue($prefs['display']['structure']['rule']);
    }

    /**
     * An unknown tab value is rejected in favour of the default.
     *
     * @return void
     */
    public function test_get_central_prefs_rejects_unknown_tab(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_user_preference(constants::PREF_CENTRAL_NAV, json_encode(['tab' => 'bogus']), $USER->id);
        $this->assertSame('frameworks', helper::get_central_prefs()['nav']['tab']);
    }

    /**
     * The uninstall purge removes only this plugin's preference rows.
     *
     * @return void
     */
    public function test_purge_user_preferences_removes_only_plugin_rows(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_user_preference(constants::PREF_CENTRAL_NAV, json_encode(['tab' => 'plans']), $USER->id);
        set_user_preference(constants::PREF_CENTRAL_DISPLAY, json_encode([]), $USER->id);
        set_user_preference('somethingelse_pref', 'keep', $USER->id);
        helper::purge_user_preferences();
        $remaining = $DB->count_records_select(
            'user_preferences',
            $DB->sql_like('name', ':p'),
            ['p' => $DB->sql_like_escape('local_dimensions_') . '%']
        );
        $this->assertEquals(0, $remaining);
        $this->assertEquals('keep', get_user_preferences('somethingelse_pref', null, $USER->id));
    }
}
```

- [ ] **Step 5: Run the tests (CI / Moodle-enabled env — no local runner)**

Run on a Moodle install: `vendor/bin/phpunit local/dimensions/tests/preferences_test.php`
Expected before Steps 1-3 are in place: FAIL (methods/constant undefined). After: PASS.

- [ ] **Step 6: Static pre-check (local)**

Run from the Moodle root:
```bash
awk 'length($0)>132{print FILENAME":"NR" ("length($0)")"}' public/local/dimensions/lib.php public/local/dimensions/classes/helper.php public/local/dimensions/classes/constants.php public/local/dimensions/tests/preferences_test.php
grep -nE '^\s*// [a-z]' public/local/dimensions/classes/helper.php public/local/dimensions/lib.php
```
Expected: no output (no >132-char lines added, no lowercase-leading inline comments).

- [ ] **Step 7: Commit**

```bash
git add classes/constants.php lib.php classes/helper.php tests/preferences_test.php
git commit -m "feat(central): user-preference backend for hub view state"
```

---

## Task 2: Privacy provider + lang + uninstall cleanup

**Files:**
- Modify (rewrite): `classes/privacy/provider.php`
- Modify: `lang/en/local_dimensions.php`, `lang/pt_br/local_dimensions.php`
- Modify: `db/uninstall.php`
- Test: `tests/privacy/provider_test.php` (create)

- [ ] **Step 1: Add the lang strings (both files, alphabetical, in sync)**

In `lang/en/local_dimensions.php`, **remove** the existing line:
```php
$string['privacy:metadata'] = 'The Competency Dimensions plugin does not store any personal data. It extends competencies with custom fields and calculates course progress in real-time using Moodle core data.';
```
and **add**, in its place (these sort right after where `privacy:metadata` was, before `proficiency`):
```php
$string['privacy:metadata:preference:central_display'] = 'Your show/hide display choices on the Competency hub (whether taxonomy, identifiers, competency rule, due dates and hidden items are shown), stored so the hub looks the same when you return.';
$string['privacy:metadata:preference:central_nav'] = 'The Competency hub view you last visited (active tab, System or Course-category context, and the framework or learning-plan template you had selected), stored so you return to where you left off.';
```

In `lang/pt_br/local_dimensions.php`, **remove** the matching `privacy:metadata` line and **add**:
```php
$string['privacy:metadata:preference:central_display'] = 'Suas escolhas de exibição na Central de Competências (se taxonomia, identificadores, regra de competência, prazos e itens ocultos são mostrados), guardadas para que a Central fique igual quando você voltar.';
$string['privacy:metadata:preference:central_nav'] = 'A tela da Central de Competências que você visitou por último (aba ativa, contexto Sistema ou Categoria de curso e o framework ou modelo de plano selecionado), guardada para você voltar de onde parou.';
```

- [ ] **Step 2: Rewrite the privacy provider**

Replace the body of `classes/privacy/provider.php` (from the file docblock down) with:

```php
/**
 * Privacy API implementation for local_dimensions.
 *
 * The plugin stores no personal data of its own beyond two per-user preferences that remember
 * the Competency hub's last-visited view and its display-toggle choices. It has no database
 * tables (custom-field data belongs to competencies/templates, not users), so this is a
 * preference-only provider.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\user_preference_provider;
use core_privacy\local\request\writer;
use local_dimensions\constants;

/**
 * Preference-only privacy provider for the Competency hub view state.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider, user_preference_provider {
    /**
     * Describe the user preferences this plugin stores.
     *
     * @param collection $collection The metadata collection to add to.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_user_preference(
            constants::PREF_CENTRAL_NAV,
            'privacy:metadata:preference:central_nav'
        );
        $collection->add_user_preference(
            constants::PREF_CENTRAL_DISPLAY,
            'privacy:metadata:preference:central_display'
        );
        return $collection;
    }

    /**
     * Export the stored view-state preferences for the given user.
     *
     * @param int $userid The id of the user to export for.
     * @return void
     */
    public static function export_user_preferences(int $userid): void {
        $nav = get_user_preferences(constants::PREF_CENTRAL_NAV, null, $userid);
        if ($nav !== null && $nav !== '') {
            writer::export_user_preference(
                'local_dimensions',
                constants::PREF_CENTRAL_NAV,
                $nav,
                get_string('privacy:metadata:preference:central_nav', 'local_dimensions')
            );
        }
        $display = get_user_preferences(constants::PREF_CENTRAL_DISPLAY, null, $userid);
        if ($display !== null && $display !== '') {
            writer::export_user_preference(
                'local_dimensions',
                constants::PREF_CENTRAL_DISPLAY,
                $display,
                get_string('privacy:metadata:preference:central_display', 'local_dimensions')
            );
        }
    }
}
```

Note: keep the file **without** a `defined('MOODLE_INTERNAL') || die();` guard — it is a pure namespaced single-class file and the current provider omits it (the sniff fails otherwise).

- [ ] **Step 3: Wire the uninstall purge**

In `db/uninstall.php`, update the file docblock (line 20-21) to read `Removes custom field categories, fields, data, stored files and user preferences that were created by this plugin.` Then, inside `xmldb_local_dimensions_uninstall()`, immediately before `return true;` (line 58), add:

```php
    // 3. Delete this plugin's user preferences (Competency hub view state). Core does not purge
    // a component's user_preferences rows on uninstall (the table has no component column), so
    // remove them here by name prefix to avoid orphaned rows.
    \local_dimensions\helper::purge_user_preferences();

```

- [ ] **Step 4: Write the failing provider test**

Create `tests/privacy/provider_test.php`:

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
 * Tests for the local_dimensions privacy provider.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\privacy;

use advanced_testcase;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;
use local_dimensions\constants;

/**
 * Unit tests for the preference-only privacy provider.
 *
 * @covers \local_dimensions\privacy\provider
 */
final class provider_test extends advanced_testcase {
    /**
     * get_metadata declares exactly the two view-state preferences.
     *
     * @return void
     */
    public function test_get_metadata_declares_both_preferences(): void {
        $collection = new collection('local_dimensions');
        $items = provider::get_metadata($collection)->get_collection();
        $this->assertCount(2, $items);
        $names = array_map(static fn($item) => $item->get_name(), $items);
        $this->assertContains(constants::PREF_CENTRAL_NAV, $names);
        $this->assertContains(constants::PREF_CENTRAL_DISPLAY, $names);
    }

    /**
     * A set preference is exported for the user.
     *
     * @return void
     */
    public function test_export_user_preferences_exports_set_values(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_user_preference(constants::PREF_CENTRAL_NAV, json_encode(['tab' => 'plans']), $USER->id);
        provider::export_user_preferences((int) $USER->id);
        $writer = writer::with_context(\context_user::instance($USER->id));
        $this->assertTrue($writer->has_any_data());
        $exported = (array) $writer->get_user_preferences('local_dimensions');
        $this->assertArrayHasKey(constants::PREF_CENTRAL_NAV, $exported);
    }

    /**
     * Nothing is exported when the user has no stored preferences.
     *
     * @return void
     */
    public function test_export_user_preferences_skips_unset(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        provider::export_user_preferences((int) $USER->id);
        $writer = writer::with_context(\context_user::instance($USER->id));
        $this->assertFalse($writer->has_any_data());
    }
}
```

If the writer singleton needs resetting between assertions, mirror the setup used by the core reference test `blocks/myoverview/tests/privacy/provider_test.php` (the canonical preference-only provider test).

- [ ] **Step 5: Run tests (CI / Moodle env)**

`vendor/bin/phpunit local/dimensions/tests/privacy/provider_test.php` → PASS.
Also run the site privacy metadata check to confirm the two lang keys resolve:
`vendor/bin/phpunit --filter test_metadata_is_documented core/tests/privacy_legacy_polyfill_test.php` is not it — instead the moodle-plugin-ci `phpunit` leg runs `core_privacy` provider validation across all components; the mustache/validate legs are unaffected.

- [ ] **Step 6: Static pre-check (local)**

```bash
awk 'length($0)>132{print FILENAME":"NR" ("length($0)")"}' public/local/dimensions/classes/privacy/provider.php public/local/dimensions/db/uninstall.php public/local/dimensions/tests/privacy/provider_test.php public/local/dimensions/lang/en/local_dimensions.php public/local/dimensions/lang/pt_br/local_dimensions.php
```
Expected: no output. Also eyeball that both lang files are still alphabetically ordered around the new keys.

- [ ] **Step 7: Commit**

```bash
git add classes/privacy/provider.php db/uninstall.php lang/en/local_dimensions.php lang/pt_br/local_dimensions.php tests/privacy/provider_test.php
git commit -m "feat(privacy): preference-only provider + uninstall cleanup for hub view state"
```

---

## Task 3: version bump + upgrade savepoint

**Files:**
- Modify: `version.php:28`
- Modify: `db/upgrade.php` (insert a new block after the `2026070100` block, before the trailing unconditional customfield catch-all)

- [ ] **Step 1: Bump the version**

In `version.php`, change line 28 from `$plugin->version = 2026070903;` to:
```php
$plugin->version = 2026071000;
```

- [ ] **Step 2: Add the upgrade savepoint block**

In `db/upgrade.php`, after the `if ($oldversion < 2026070100) { ... }` block (which ends at the `upgrade_plugin_savepoint(true, 2026070100, ...)` around line 222) and **before** the unconditional customfield-provisioning catch-all near the end, insert:

```php
    if ($oldversion < 2026071000) {
        // Persist the Competency hub view state via user preferences plus a full privacy
        // provider; purge so the new AMD bundles, the preference callback and the new strings
        // are served fresh.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026071000, 'local', 'dimensions');
    }

```

- [ ] **Step 3: Verify (local)**

```bash
grep -n '2026071000' public/local/dimensions/version.php public/local/dimensions/db/upgrade.php
```
Expected: one hit in `version.php`, two in `db/upgrade.php` (the `if` guard and the savepoint).

- [ ] **Step 4: Commit**

```bash
git add version.php db/upgrade.php
git commit -m "chore: bump version + upgrade savepoint for hub view-state persistence"
```

---

## Task 4: `preferences.js` (new AMD module)

**Files:**
- Create: `amd/src/central/preferences.js`

- [ ] **Step 1: Write the module**

Create `amd/src/central/preferences.js`:

```javascript
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
 * Shared view-state store for the Competency hub. Holds the user's last-visited navigation
 * (tab / context / category / selected framework / template) and the display-toggle choices in
 * memory, seeded once from the server on page load, and persists changes to Moodle user
 * preferences (debounced) so the hub is restored on the next visit — across sessions and
 * devices. Replaces the previous per-session sessionStorage persistence.
 *
 * @module     local_dimensions/central/preferences
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {setUserPreference} from 'core_user/repository';
import {notifyError} from 'local_dimensions/central/errors';

/** @type {String} User preference name for the hub navigation state. */
const PREF_NAV = 'local_dimensions_central_nav';
/** @type {String} User preference name for the hub display-toggle state. */
const PREF_DISPLAY = 'local_dimensions_central_display';
/** @type {Number} Debounce (ms) before a change is written to the server. */
const SAVE_DELAY = 400;

/** @type {Object} Default navigation state. */
const NAV_DEFAULTS = {tab: 'frameworks', contexttype: 'system', categoryid: 0, frameworkid: 0, templateid: 0};
/** @type {Object} Default display state. */
const DISPLAY_DEFAULTS = {
    structure: {tax: false, id: false, rule: true, showhidden: false},
    planslist: {id: false, duedate: false},
    plansdetail: {tax: false, path: false, id: false},
    plansshowdisabled: false,
    frameworksshowhidden: false,
};

/**
 * Deep-clone a plain JSON-safe object.
 *
 * @param {Object} value
 * @return {Object}
 */
const clone = (value) => JSON.parse(JSON.stringify(value));

/** @type {Object} Live navigation state (authoritative for the session). */
let nav = clone(NAV_DEFAULTS);
/** @type {Object} Live display state (authoritative for the session). */
let display = clone(DISPLAY_DEFAULTS);
/** @type {Object} Pending debounce timer ids, keyed by preference name. */
const timers = {};

/**
 * Schedule a debounced write of a preference to the server.
 *
 * @param {String} name Preference name.
 * @param {Object} value Value to JSON-encode and store.
 */
const scheduleSave = (name, value) => {
    window.clearTimeout(timers[name]);
    timers[name] = window.setTimeout(() => {
        setUserPreference(name, JSON.stringify(value)).catch(notifyError);
    }, SAVE_DELAY);
};

/**
 * Seed the store from the server-rendered state. Called once on page load.
 *
 * @param {Object} state {nav: Object, display: Object} from the server.
 */
export const init = (state) => {
    const seed = state || {};
    nav = {...clone(NAV_DEFAULTS), ...(seed.nav || {})};
    const incoming = seed.display || {};
    display = {
        structure: {...DISPLAY_DEFAULTS.structure, ...(incoming.structure || {})},
        planslist: {...DISPLAY_DEFAULTS.planslist, ...(incoming.planslist || {})},
        plansdetail: {...DISPLAY_DEFAULTS.plansdetail, ...(incoming.plansdetail || {})},
        plansshowdisabled: Boolean(incoming.plansshowdisabled),
        frameworksshowhidden: Boolean(incoming.frameworksshowhidden),
    };
};

/**
 * The current navigation state.
 *
 * @return {Object}
 */
export const getNav = () => nav;

/**
 * The current display state.
 *
 * @return {Object}
 */
export const getDisplay = () => display;

/**
 * Merge a partial navigation change and persist it (debounced).
 *
 * @param {Object} partial Keys to overwrite on the navigation state.
 */
export const saveNav = (partial) => {
    nav = {...nav, ...partial};
    scheduleSave(PREF_NAV, nav);
};

/**
 * Merge a partial display change (one level deep for the nested sections) and persist it.
 *
 * @param {Object} partial e.g. {structure: {tax: true}} or {plansshowdisabled: true}.
 */
export const saveDisplay = (partial) => {
    Object.keys(partial).forEach((key) => {
        const value = partial[key];
        if (value && typeof value === 'object' && display[key] && typeof display[key] === 'object') {
            display[key] = {...display[key], ...value};
        } else {
            display[key] = value;
        }
    });
    scheduleSave(PREF_DISPLAY, display);
};
```

- [ ] **Step 2: Lint the new module (local)**

From the Moodle root:
```bash
npx eslint --max-warnings 0 public/local/dimensions/amd/src/central/preferences.js
```
Expected: no output (exit 0). If `promise/catch-or-return` fires on the `setTimeout` body, add `return` before `setUserPreference(...)`.

- [ ] **Step 3: Build it (local) — needed so the module resolves before wiring**

```bash
cd /Volumes/N1TB/dev/github/moodle
npx grunt amd --root=public/local/dimensions
```
Expected: writes `public/local/dimensions/amd/build/central/preferences.min.js` + `.map`.

- [ ] **Step 4: Commit**

```bash
git add amd/src/central/preferences.js amd/build/central/preferences.min.js amd/build/central/preferences.min.js.map
git commit -m "feat(central): preferences.js view-state store (setUserPreference)"
```

---

## Task 5: Server-side restore in `central.php` + `frameworks.php` fallback

**Files:**
- Modify: `central.php:38-106` (param reads + tab building)
- Modify: `classes/output/dynamictabs/frameworks.php:86`

- [ ] **Step 1: Restore nav and pre-render the saved tab**

In `central.php`, **delete** the three param reads at lines 38-40:
```php
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$contexttype = optional_param('contexttype', 'system', PARAM_ALPHA);
$categoryid = optional_param('categoryid', 0, PARAM_INT);
```
and instead, **after** `api::require_enabled();` (line 43), insert:

```php

// Restore the last-visited view (tab / context / selection) from the user's saved preference;
// an explicit URL param always wins so deep-links keep working.
$prefs = helper::get_central_prefs();
$nav = $prefs['nav'];
$contexttype = optional_param('contexttype', $nav['contexttype'], PARAM_ALPHA);
$categoryid = optional_param('categoryid', $nav['categoryid'], PARAM_INT);
$frameworkid = optional_param('frameworkid', $nav['frameworkid'], PARAM_INT);
$templateid = optional_param('templateid', $nav['templateid'], PARAM_INT);
$activetab = optional_param('tab', $nav['tab'], PARAM_ALPHA);
if (!in_array($activetab, ['frameworks', 'structure', 'plans'], true)) {
    $activetab = 'frameworks';
}
```

Then **replace** the whole tab-building block — the `$frameworkstab` pre-render and the `$tabsdata` array (current lines 61-106) — with:

```php
// Init the shared view-state store with the resolved nav + display, so the client saves
// changes against the state the page actually rendered (e.g. a downgraded coursecat context).
$prefs['nav'] = [
    'tab' => $activetab,
    'contexttype' => $contexttype,
    'categoryid' => $categoryid,
    'frameworkid' => $frameworkid,
    'templateid' => $templateid,
];
$PAGE->requires->js_call_amd('local_dimensions/central/preferences', 'init', [$prefs]);

// Build the three tabs; pre-render only the restored (active) one — the others lazy-load via
// core_dynamic_tabs_get_content on first activation.
$tabinstances = [
    'frameworks' => new frameworks(['contexttype' => $contexttype, 'categoryid' => $categoryid]),
    'structure' => new structure([
        'contexttype' => $contexttype,
        'categoryid' => $categoryid,
        'frameworkid' => $frameworkid,
    ]),
    'plans' => new plans([
        'contexttype' => $contexttype,
        'categoryid' => $categoryid,
        'templateid' => $templateid,
    ]),
];
$tablabels = [
    'frameworks' => get_string('central_frameworks_tab', 'local_dimensions'),
    'structure' => get_string('managecompetencies_structure', 'local_dimensions'),
    'plans' => get_string('learningplans', 'local_dimensions'),
];
$tabs = [];
foreach (['frameworks', 'structure', 'plans'] as $shortname) {
    $isactive = ($shortname === $activetab);
    $tab = $tabinstances[$shortname];
    $content = '';
    if ($isactive) {
        $tab->require_access();
        $content = $OUTPUT->render_from_template($tab->get_template(), $tab->export_for_template($OUTPUT));
    }
    $tabs[] = [
        'shortname' => $shortname,
        'displayname' => $tablabels[$shortname],
        'tabclass' => get_class($tab),
        'enabled' => true,
        'active' => $isactive,
        'content' => $content,
    ];
}

$tabsdata = [
    'showtabsnavigation' => true,
    'dataattributes' => [
        ['name' => 'contexttype', 'value' => $contexttype],
        ['name' => 'categoryid', 'value' => $categoryid],
        ['name' => 'frameworkid', 'value' => $frameworkid],
        ['name' => 'templateid', 'value' => $templateid],
    ],
    'tabs' => $tabs,
];
```

Keep the existing `$PAGE->requires->js_call_amd('local_dimensions/central/context', 'init');` and `action_footer` init lines (around 57-59) as they are — the `preferences` init is added above, before them, so it runs first.

- [ ] **Step 2: Verify `dataattributes` reach each pane**

Read how `core/dynamic_tabs` applies `dataattributes` (the plans tab reads `pane.dataset.templateid`; the structure tab already relies on `frameworkid` arriving this way). Confirm the added `templateid` lands on the panes exactly as `frameworkid` does. If dynamic_tabs scopes `dataattributes` to a single container rather than each pane, seed `templateid` the same way `frameworkid` is currently seeded. (This is a read-and-confirm step, not a code change, unless the mechanism differs.)

- [ ] **Step 3: Fall back frameworks show-hidden to the saved pref**

In `classes/output/dynamictabs/frameworks.php`, replace line 86:
```php
        $showhidden = (bool) ($data['showhidden'] ?? false);
```
with:
```php
        // Persisted per user: an explicit pane arg (set when the user toggles) wins; otherwise
        // fall back to the saved display preference so the choice survives a fresh page load.
        $showhidden = array_key_exists('showhidden', $data)
            ? (bool) $data['showhidden']
            : (bool) helper::get_central_prefs()['display']['frameworksshowhidden'];
```

- [ ] **Step 4: Static pre-check (local)**

```bash
awk 'length($0)>132{print FILENAME":"NR" ("length($0)")"}' public/local/dimensions/central.php public/local/dimensions/classes/output/dynamictabs/frameworks.php
```
Expected: no output.

- [ ] **Step 5: Verify server render (CI / user's Moodle)**

There is no unit seam for the controller; verify at runtime in Task 7 (Behat) and manually: load `/local/dimensions/central.php` — with no saved pref it still lands on Frameworks/System (no regression).

- [ ] **Step 6: Commit**

```bash
git add central.php classes/output/dynamictabs/frameworks.php
git commit -m "feat(central): restore last tab/context/selection from saved preferences"
```

---

## Task 6: Wire JS saves + drop sessionStorage + rebuild

**Files:**
- Modify: `amd/src/central/context.js`
- Modify: `amd/src/central/structure.js`
- Modify: `amd/src/central/plans.js`
- Modify: `amd/src/central/frameworks.js`
- Modify (generated): `amd/build/central/*.min.js` + `.map`

- [ ] **Step 1: `context.js` — save tab + context/category**

Add the import after line 31 (`import {reloadPane} ...`):
```javascript
import * as Preferences from 'local_dimensions/central/preferences';
```
At the end of `setContext` (after `refreshActive();`, line 192), add:
```javascript
    Preferences.saveNav({contexttype: contexttype, categoryid: 0});
```
At the end of `setCategory` (after `refreshActive();`, line 206), add:
```javascript
    Preferences.saveNav({contexttype: 'coursecat', categoryid: categoryid});
```
In the `shown.bs.tab` handler (lines 275-279), add the tab save so the block reads:
```javascript
        toggle.addEventListener('shown.bs.tab', () => {
            bar.dataset.activemode = activeMode();
            renderCounter(bar);
            renderOptionLabels(bar);
            const active = document.querySelector(SELECTORS.activePane);
            if (active) {
                Preferences.saveNav({tab: active.dataset.tabContent});
            }
        });
```

- [ ] **Step 2: `structure.js` — delegate display/show-hidden, save framework**

Add the import alongside the other `local_dimensions/central/*` imports near the top:
```javascript
import * as Preferences from 'local_dimensions/central/preferences';
```
Remove the now-unused constants and their doc comments (lines 109-112):
```javascript
/** @type {String} sessionStorage key for the per-session display-toggle choice. */
const DISPLAY_KEY = 'local_dimensions_structure_display';
/** @type {String} sessionStorage key for the show-hidden-frameworks choice. */
const SHOWHIDDEN_KEY = 'local_dimensions_structure_showhidden';
```
Replace `readDisplayPrefs` / `writeDisplayPrefs` (lines 256-275) with:
```javascript
/**
 * Read the persisted structure display-toggle choice from the shared preferences store.
 *
 * @return {Object} Map of toggle key to boolean.
 */
const readDisplayPrefs = () => ({...Preferences.getDisplay().structure});

/**
 * Persist the structure display-toggle choice via the shared preferences store.
 *
 * @param {Object} prefs Map of toggle key to boolean.
 */
const writeDisplayPrefs = (prefs) => {
    Preferences.saveDisplay({structure: prefs});
};
```
Replace the show-hidden block (lines 1479-1500) with:
```javascript
    const toggleHidden = region.querySelector('[data-action="toggle-hidden"]');
    if (toggleHidden) {
        const showhidden = Boolean(Preferences.getDisplay().structure.showhidden);
        toggleHidden.checked = showhidden;
        applyShowHidden(region, showhidden);
        toggleHidden.addEventListener('change', () => {
            Preferences.saveDisplay({structure: {showhidden: toggleHidden.checked}});
            applyShowHidden(region, toggleHidden.checked);
        });
    }
```
In the framework-select handler (lines 1461-1466), add the save so it reads:
```javascript
        select.addEventListener('change', () => {
            window.clearTimeout(searchDebounce);
            // The pane dataset is the single source of truth for the tab's arguments.
            pane.dataset.frameworkid = select.value;
            Preferences.saveNav({frameworkid: Number(select.value) || 0});
            reloadPane(pane).catch(notifyError);
        });
```

- [ ] **Step 3: `plans.js` — delegate display groups + show-disabled, save template**

Add the import near the top:
```javascript
import * as Preferences from 'local_dimensions/central/preferences';
```
Remove the now-unused constants and their doc comments (the three `*_KEY` consts at lines 52-53, 55-56, 61-62):
```javascript
/** @type {String} sessionStorage key for the show-disabled-plans choice. */
const SHOWDISABLED_KEY = 'local_dimensions_plans_showdisabled';
```
```javascript
/** @type {String} sessionStorage key for the per-session display-toggle choice. */
const DISPLAY_KEY = 'local_dimensions_plans_display';
```
```javascript
/** @type {String} sessionStorage key for the plan-list display-toggle choice. */
const LISTDISPLAY_KEY = 'local_dimensions_plans_listdisplay';
```
(Keep `DISPLAY_CLASSES` and `LISTDISPLAY_CLASSES` — still used.)

Replace `initShowDisabled` (lines 192-218) with:
```javascript
const initShowDisabled = (region) => {
    const toggle = region.querySelector(SELECTORS.showDisabled);
    const rows = region.querySelector(SELECTORS.templateRows);
    if (!toggle || !rows) {
        return;
    }
    const show = Boolean(Preferences.getDisplay().plansshowdisabled);
    toggle.checked = show;
    rows.classList.toggle('show-disabled', show);
    toggle.addEventListener('change', () => {
        Preferences.saveDisplay({plansshowdisabled: toggle.checked});
        rows.classList.toggle('show-disabled', toggle.checked);
        applyPlanSearch(region);
    });
};
```
Replace `readDisplayPrefs` / `writeDisplayPrefs` (lines 324-343) with:
```javascript
const readDisplayPrefs = () => ({...Preferences.getDisplay().plansdetail});

const writeDisplayPrefs = (prefs) => {
    Preferences.saveDisplay({plansdetail: prefs});
};
```
Replace `readListDisplayPrefs` / `writeListDisplayPrefs` (lines 388-407) with:
```javascript
const readListDisplayPrefs = () => ({...Preferences.getDisplay().planslist});

const writeListDisplayPrefs = (prefs) => {
    Preferences.saveDisplay({planslist: prefs});
};
```
In the `ACTION_HANDLERS['select-template']` handler (lines 685-689), add the save so it reads:
```javascript
    'select-template': (pane, region, target) => {
        pane.dataset.templateid = target.dataset.id;
        Preferences.saveNav({templateid: Number(target.dataset.id) || 0});
        // Keep the plan-list scroll; the detail shows new content so its scroll resets.
        reloadKeepingScroll(pane, [SELECTORS.templateRows]).catch(notifyError);
    },
```
(Keep the `readDisplayPrefs`/`writeDisplayPrefs`/`readListDisplayPrefs`/`writeListDisplayPrefs` **doc comments** above each — only the bodies change; retain a one-line `@return`/`@param` block matching the surrounding style.)

- [ ] **Step 4: `frameworks.js` — save show-hidden**

Add the import near the top (alongside the other `local_dimensions/central/*` imports):
```javascript
import * as Preferences from 'local_dimensions/central/preferences';
```
Update the toggle handler (lines 472-478) to:
```javascript
    const toggleHidden = region.querySelector('[data-action="toggle-hidden"]');
    if (toggleHidden && pane) {
        toggleHidden.addEventListener('change', () => {
            pane.dataset.showhidden = toggleHidden.checked ? '1' : '0';
            Preferences.saveDisplay({frameworksshowhidden: toggleHidden.checked});
            reloadPane(pane).catch(notifyError);
        });
    }
```

- [ ] **Step 5: Lint (local) — this is the critical gate**

```bash
npx eslint --max-warnings 0 public/local/dimensions/amd/src
```
Expected: no output. The removed `*_KEY` consts must leave **no** `no-unused-vars`; if any lint error appears, fix it before building.

- [ ] **Step 6: Build (local)**

```bash
cd /Volumes/N1TB/dev/github/moodle
npx grunt amd --root=public/local/dimensions
```
Expected: rebuilds `amd/build/central/{context,structure,plans,frameworks,preferences}.min.js` + maps.

- [ ] **Step 7: Commit (source + build together)**

```bash
git add amd/src/central/context.js amd/src/central/structure.js amd/src/central/plans.js amd/src/central/frameworks.js amd/build/central/
git commit -m "feat(central): save view state on change; drop sessionStorage for hub toggles"
```

---

## Task 7: Behat smoke test + full test/lint sweep

**Files:**
- Create: `tests/behat/central_restore.feature`

- [ ] **Step 1: Write the thin restore scenario**

Create `tests/behat/central_restore.feature`:

```gherkin
@local @local_dimensions @javascript
Feature: The Competency hub remembers the last visited tab
  In order to resume where I left off
  As a competency manager
  I need the hub to reopen on the tab I last used

  Background:
    Given the following "core_competency > frameworks" exist:
      | shortname | idnumber |
      | Framework A | fwka |

  Scenario: Reloading the hub restores the last active tab
    Given I log in as "admin"
    And I am on the "Competency Dimensions" "local_dimensions > Central" page
    When I click on "Structures" "link"
    And I should see "Framework A"
    And I reload the page
    Then "Structures" "link" should be active in the hub tabs
    And I should see "Framework A"
```

Notes for the executor (this is CI-only — budget one fix-and-repush per the repo's Behat conventions):
- `I am on the "..." "local_dimensions > Central" page` needs a page resolver. Check `tests/behat/behat_local_dimensions.php` for an existing hub-navigation step; if none, navigate via the admin tree (`Site administration > Competencies > ...`) or reuse the pattern the existing `.feature` files use to reach `central.php`.
- The exact tab label ("Structures") must match `get_string('managecompetencies_structure', ...)`. Verify against the rendered nav.
- "should be active in the hub tabs" may need a concrete selector (the active `.nav-link`); if there is no matching named selector, assert instead on a Structures-tab-only visible element (e.g. the framework `<select>` region) after reload, which proves the correct tab reopened.
- Set-preference persistence relies on the JS `setUserPreference` firing before reload; the ~400ms debounce is well within Behat's step timing, but if flaky add an explicit wait for the tab content before reloading.

- [ ] **Step 2: Confirm no existing `.feature` steps broke**

Grep the Behat suite for any label/region this change touched (the toggles kept their labels and `data-action`s, so this should be clean, but confirm):
```bash
grep -rnE 'showhidden|show hidden|show disabled|Structures|Learning plans' public/local/dimensions/tests/behat/
```
Fix any scenario that assumed the old always-lands-on-Frameworks behaviour (none is expected, since default has no saved pref).

- [ ] **Step 3: Full local static sweep**

From the Moodle root:
```bash
npx eslint --max-warnings 0 public/local/dimensions/amd/src
npx stylelint public/local/dimensions/styles.css
awk 'length($0)>132{print FILENAME":"NR}' public/local/dimensions/lib.php public/local/dimensions/central.php public/local/dimensions/classes/helper.php public/local/dimensions/classes/privacy/provider.php public/local/dimensions/classes/output/dynamictabs/frameworks.php public/local/dimensions/db/uninstall.php
```
Expected: all clean (no output / exit 0).

- [ ] **Step 4: Full test run (CI / Moodle env)**

`vendor/bin/phpunit local/dimensions/tests/preferences_test.php local/dimensions/tests/privacy/provider_test.php` → PASS.
Behat: the `central_restore.feature` scenario runs on the CI JS leg; expect to iterate once on the page-resolver / active-tab selector.

- [ ] **Step 5: Commit**

```bash
git add tests/behat/central_restore.feature
git commit -m "test(central): behat smoke test for last-tab restore"
```

---

## Task 8: Update README.md

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Read the README and find the right sections**

`README.md` is 462 lines. Locate (a) the feature/overview area describing the Central hub, and (b) any privacy statement. Match the document's existing heading style and tone.

- [ ] **Step 2: Add a "View-state persistence" note**

Under the Central hub description, add a short subsection (adapt wording to the surrounding style):

```markdown
### Remembering where you were

The Competency hub remembers, per user, the tab you were on, the System / Course-category
context and category you had chosen, the framework or learning-plan template you had selected,
and your display-toggle choices (taxonomy, identifiers, competency rule, due dates, show hidden
/ disabled). It is stored as Moodle **user preferences**, so it persists across sessions and
devices and is restored when you reopen the hub. An explicit URL parameter (e.g. a deep link)
still overrides the saved view.
```

- [ ] **Step 3: Update the privacy statement**

If the README states the plugin stores no personal data, revise it to reflect the two view-state preferences and their handling:

```markdown
### Privacy

The plugin stores no personal data beyond two per-user preferences that remember the Competency
hub's last-visited view and display choices. These are exported by the Privacy API on a
data-subject request and removed on plugin uninstall (`db/uninstall.php`).
```

- [ ] **Step 4: Commit**

```bash
git add README.md
git commit -m "docs: document hub view-state persistence + privacy behaviour"
```

---

## Task 9: Final rebuild, sweep, and package

- [ ] **Step 1: Clean rebuild of all AMD (local)**

```bash
cd /Volumes/N1TB/dev/github/moodle
npx grunt amd --root=public/local/dimensions
git -C public/local/dimensions status --porcelain amd/build
```
Expected: no unexpected diff beyond the five touched modules (context, structure, plans, frameworks, preferences). If the build changed a module you did not edit, investigate before committing.

- [ ] **Step 2: Final lint gate (local)**

```bash
npx eslint --max-warnings 0 public/local/dimensions/amd/src
npx stylelint public/local/dimensions/styles.css
```
Expected: clean.

- [ ] **Step 3: Confirm version + savepoint agree**

```bash
grep -n 'version' public/local/dimensions/version.php | head
grep -n '2026071000' public/local/dimensions/db/upgrade.php
```
Expected: `$plugin->version = 2026071000;` and the matching savepoint.

- [ ] **Step 4: Commit any residual build output**

```bash
git -C public/local/dimensions add amd/build/central/
git -C public/local/dimensions commit -m "build: rebuild AMD for hub view-state persistence" || echo "nothing to commit"
```

- [ ] **Step 5: Package a test-install zip (from the committed HEAD)**

```bash
ver=$(grep -oE '\$plugin->version[[:space:]]*=[[:space:]]*[0-9]+' \
  public/local/dimensions/version.php | grep -oE '[0-9]+')
git -C public/local/dimensions archive --format=zip --prefix=dimensions/ HEAD -o ~/Downloads/dimensions-$ver.zip
```
Then install on the test server and verify end-to-end: leave the hub on the Plans tab, a Course-category context, a selected template, with a couple of toggles flipped; open a fresh session; confirm the hub reopens exactly there. Then uninstall the plugin and confirm no `local_dimensions_%` rows remain in `user_preferences`.

- [ ] **Step 6: Hand off**

Do not push / open a PR unless the user asks. Report: what changed, the local lint/build results, and the CI/runtime checks still pending (PHPUnit, Behat, the test-install verification).

---

## Self-review

**Spec coverage:**
- Restore active tab → Task 5 (pre-render saved tab) + Task 6 (`context.js` save). ✓
- Restore context + category → Task 5 (nav defaults) + Task 6 (`context.js`). ✓
- Restore selected framework/template → Task 5 (`frameworkid`/`templateid` seeding) + Task 6 (`structure.js`/`plans.js` saves). ✓
- Persist display toggles → Task 6 (delegation to `preferences.js`) + Task 5 (`frameworks.php` server-filtered show-hidden). ✓
- Preferences API, no web service → Task 1 (callback) + Task 4 (`setUserPreference`). ✓
- Full privacy provider → Task 2. ✓
- Uninstall cleanup → Task 1 (`purge_user_preferences`) + Task 2 (wire into `db/uninstall.php`). ✓
- version/upgrade → Task 3. ✓
- README → Task 8. ✓

**Type/name consistency:** preference names `local_dimensions_central_nav` / `local_dimensions_central_display` match across `constants.php`, `lib.php`, `helper.php`, `provider.php`, `preferences.js`. Display schema keys (`structure`{tax,id,rule,showhidden}, `planslist`{id,duedate}, `plansdetail`{tax,path,id}, `plansshowdisabled`, `frameworksshowhidden`) match between `helper::get_central_prefs`, `preferences.js` defaults, and the tab modules' delegations. `getNav`/`getDisplay`/`saveNav`/`saveDisplay`/`init` are used with the same signatures everywhere.

**Placeholder scan:** no deferred/placeholder steps; every code step shows complete code. Runtime-only verification steps are explicitly marked (CI / no local runner) per the verification model, not left vague.
