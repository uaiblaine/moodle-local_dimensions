# Design: persist the Central hub's view state via user preferences (+ full privacy)

- **Date:** 2026-07-10
- **Component:** `local_dimensions`
- **Status:** approved design, pending implementation plan
- **Scope:** the Competency hub (`central.php`) — its active tab, the page-level context
  (System / Course-category + chosen category), the selected content per tab (framework on
  Structure, template on Plans), and every display toggle (the fa-cog gear panels + the
  show-hidden / show-disabled pills). Plus the matching **privacy provider** and **uninstall
  cleanup**. Learner views (`view-plan.php`, `view-competency.php`) and the Return-to-Plan FAB are
  out of scope.

## 1. Goal

When a user leaves the Central hub and comes back (new session, even a new device), restore
"minimally the last screen": the tab they were on, the context/category they had chosen, the
framework/template they had selected, and how they had configured the display toggles. Today all of
that is either URL-only (lost on F5) or `sessionStorage`-only (per-tab, per-session). We move the
persisted state to **Moodle user preferences** (server-side, per user) and implement the
**Privacy API** completely — including cleaning up the preference rows on plugin uninstall.

### Non-goals
- No persistence of *deep ephemeral* state: expanded/collapsed tree nodes, the Plans competency
  intersection filter (`competencyids`), the selected competency row, or whether a gear panel is
  itself collapsed/expanded. ("minimamente" — out of scope by decision.)
- No change to the master-pane width resizers (already `localStorage`, `pane_resizer.js`) — left
  as-is.
- **No new web service** and no `db/services.php` change (see §3).
- No change to the learner views or the FAB.

## 2. Investigation summary (facts this design relies on)

All file:line references verified in this checkout (Moodle 5.1 working tree; plugin supports
`4.5`→`5.2`, `version.php`: `requires = 2024100700`, `supported = [405, 502]`, current
`version = 2026070903`).

### 2.1 Hub structure & where state lives today
- `central.php` renders **one** page: a page-level contextbar above `core/dynamic_tabs`. Three
  tabs — **frameworks** (landing, active, body pre-rendered server-side, `central.php:63-106`),
  **structure**, **plans** (both `active => false`, empty content, lazy-loaded via
  `core_dynamic_tabs_get_content`). ("Competencies" is not a tab — it is content inside Structure
  and Plans.)
- Tab switching is **client-side** Bootstrap tabs; there is **no** tab/activetab URL param, so a
  fresh load always lands on Frameworks. No `history.pushState` anywhere in `amd/src/central/`.
- Context is seeded from URL params `contexttype` (PARAM_ALPHA, default `system`) and `categoryid`
  (PARAM_INT, default 0) at `central.php:38-40`, validated by `helper::resolve_central_context`
  (`helper.php:1529`), which **falls back to system when the saved category is no longer readable**
  and sets `needscategory` when coursecat is chosen without a valid category. After load, context
  lives only in DOM datasets; switching it (`context.js` `setContext`/`setCategory`) never touches
  URL, session or server — so **F5 reverts to system**.
- Per-tab selected content lives in pane datasets: Structure `frameworkid` (seeded once from the
  `frameworkid` URL param via `central.php:78`, never written back); Plans `templateid` (not read by
  `central.php` at all today — the tab auto-selects) and the `competencyids` filter.

### 2.2 Exhaustive toggle inventory (the display state to persist)
Shared pill markup: `templates/central/showhidden_toggle.mustache` (`{id,label,action,checked}`).
All current persistence is **`sessionStorage`, un-suffixed globals** (not per framework/template/
category), lost across sessions.

| Tab | Toggle | Identifier / current key | Default | Effect |
|---|---|---|---|---|
| Structure | Show hidden frameworks | `local_dimensions_structure_showhidden` (`0/1`) | off | client-side filter of the framework `<select>` options |
| Structure | Taxonomy | `tax` in `local_dimensions_structure_display` (JSON) | off | `show-tax` class on the tree |
| Structure | Identifiers | `id` in same JSON | off | `show-id` |
| Structure | Competency rule | `rule` in same JSON | **on** | `show-rule` |
| Plans | Show disabled templates | `local_dimensions_plans_showdisabled` (`0/1`) | off | reveal disabled template rows |
| Plans (list gear) | Identifiers | `id` in `local_dimensions_plans_listdisplay` (JSON) | off | `show-id` on the template rows |
| Plans (list gear) | Due date | `duedate` in same JSON | off | `show-duedate` |
| Plans (detail gear) | Taxonomy | `tax` in `local_dimensions_plans_display` (JSON) | off | `show-tax` on the competency list |
| Plans (detail gear) | Paths | `path` in same JSON | off | `show-path` |
| Plans (detail gear) | Identifiers | `id` in same JSON | off | `show-id` |
| Frameworks | Show hidden frameworks | `pane.dataset.showhidden` + **server reload** (no sessionStorage) | off | server-side row filter |

Note: there is **no "Indicators" toggle** — the Structure gear panel is Taxonomy / Identifiers /
Competency rule. The user's "indicadores" maps to **Identifiers** (`id`).

### 2.3 AMD seams (where saves/reads hook in)
- Central page inits (once, page-level): `local_dimensions/central/context` and
  `.../action_footer` (`central.php:57,59`). Per-tab modules (`.../structure`, `.../plans`,
  `.../frameworks`) re-run their `init` on every pane reload.
- **Tab change:** `context.js:275` already listens `shown.bs.tab` on the tab anchors (jQuery-bridged
  via `addEventListener`). **A fresh *native* `shown.bs.tab` listener does NOT fire on 4.5/BS4** —
  reuse this exact hook.
- **Context/category change:** `context.js` `setContext` (`:164`) / `setCategory` (`:201`), both
  passing through `applyContextToPanes` (`:134`).
- **Toggle change:** six change handlers, each already writing `sessionStorage` — the save call
  goes right beside each `setItem` (Structure show-hidden `structure.js:~1492`; Structure display
  `structure.js:~1430-1442`; Plans show-disabled `plans.js:~209`; Plans detail display
  `plans.js:~371`; Plans list display `plans.js:~435`; Frameworks show-hidden `frameworks.js:472`,
  which writes `pane.dataset` + `reloadPane`).
- Initial state today is **hybrid**: server renders the toggle `checked`/`data-*` defaults, then the
  client overrides from `sessionStorage` using a `hasStored ? stored : cb.checked` pattern
  (`structure.js:320`, `plans.js:423`). There is **zero** existing user-preference plumbing in the
  plugin (`grep set_user_preference` → none). Greenfield.

### 2.4 Core API patterns (Moodle 4.5→5.2)
- **The old `user_preference_allow_ajax_update()` is REMOVED** (deprecated 4.3, removed —
  `lib/UPGRADING.md`; the YUI `M.util.set_user_preference` and `lib/ajax/setuserpref.php` went with
  it). The current mechanism is a **component callback**: a function `local_dimensions_user_preferences()`
  in `lib.php` returning an array of definitions, discovered by `get_plugins_with_function`
  (`lib/classes/user.php:1182-1204`). No db registration, no version bump for the callback itself.
- Definition keys (enforced in `lib/classes/user.php`): `type` (a `PARAM_*`), `null`
  (`NULL_ALLOWED`/`NULL_NOT_ALLOWED`), `default`, optional `choices` allow-list, and
  `permissioncallback` — `callable($user, $prefname): bool`. Standard value:
  `[core_user::class, 'is_current_user']`. A preference is settable via AJAX **only if** some
  callback declares it (by exact name or `isregex`).
- **JS setter:** `import {setUserPreference} from 'core_user/repository'` →
  `setUserPreference(name, value, userid = 0)` (`user/amd/src/repository.js:109`). Used by
  `block_myoverview` and the sibling `block_feedback_tracker`. Passing `null` deletes. On 5.x it
  POSTs to the core router REST route; on 4.5 it calls `core_user_update_user_preferences` — both
  consult the same callback registry, so the mechanism is uniform across the support window.
- **Strict-route nuance (5.x):** `user/classes/route/api/preferences.php:219-245` throws
  `invalid_parameter_exception` if `clean_preference()` *changes* the incoming value. Therefore the
  stored value must survive its `PARAM_*` clean unchanged → we use **`PARAM_RAW`** (identity) for the
  JSON strings and **do not** use a `cleancallback` (a re-encoding cleancallback would alter the
  string and trip the strict route). Validation/sanitisation happens on the **read** side instead.

### 2.5 Privacy & uninstall (the critical facts)
- A preference-only privacy provider implements exactly **`\core_privacy\local\metadata\provider`**
  + **`\core_privacy\local\request\user_preference_provider`** — the latter declares only
  `export_user_preferences(int $userid)` (its parents `core_data_provider`/`data_provider` are empty
  markers). The `delete_data_*` methods live on `core_user_data_provider`, which is for DB-table
  plugins — a preference-only provider **must not** implement them. `block_myoverview`'s provider is
  the canonical example.
- **On uninstall, `user_preferences` rows ORPHAN.** The table has no `component` column
  (`lib/db/install.xml:941-946`), and `uninstall_plugin()` (`lib/adminlib.php:132-260`) never touches
  it. The only correct cleanup is the plugin's own `db/uninstall.php` hook
  (`xmldb_local_dimensions_uninstall()`, invoked at `lib/adminlib.php:180-188`) deleting the rows by
  frankenstyle name prefix. This file **already exists** (deletes custom-field data + files today).
- The current provider is a `null_provider` returning the `privacy:metadata` lang key, whose value
  asserts "does not store any personal data" — that assertion becomes false and must be revised.

## 3. Architecture

**Approach chosen: core callback + two JSON preferences, server-seeded state, client save-on-change.**
No web service; no `classes/external/` file; no `db/services.php` entry. This is the least code and
avoids the services install/version coupling.

### 3.1 The two preferences

`local_dimensions_central_nav` — "where I was":
```json
{ "tab": "frameworks|structure|plans", "contexttype": "system|coursecat",
  "categoryid": 0, "frameworkid": 0, "templateid": 0 }
```
`local_dimensions_central_display` — "how I view it":
```json
{ "structure":   {"tax": false, "id": false, "rule": true, "showhidden": false},
  "planslist":   {"id": false, "duedate": false},
  "plansdetail": {"tax": false, "path": false, "id": false},
  "plansshowdisabled": false, "frameworksshowhidden": false }
```
Both are single JSON strings, `type => PARAM_RAW`, `null => NULL_ALLOWED` (empty/null = reset to
defaults), `permissioncallback => [core_user::class, 'is_current_user']`, no `choices`. Defaults
mirror today's behaviour (only `structure.rule` starts on).

### 3.2 Pieces

| Piece | Change |
|---|---|
| `lib.php` | New `local_dimensions_user_preferences(): array` returning the two definitions. Names are `local_dimensions_*`-prefixed constants (add to `classes/constants.php`, e.g. `PREF_CENTRAL_NAV`, `PREF_CENTRAL_DISPLAY`). |
| `classes/helper.php` | `get_central_prefs(): array` — read both prefs for `$USER`, `json_decode`, validate against a schema with defaults (tab in allowlist, ids `(int)`, booleans coerced), return `['nav' => [...], 'display' => [...]]`. Single sanitisation point, reused everywhere. `purge_user_preferences(): void` — `delete_records_select('user_preferences', $DB->sql_like('name', ':p'), ['p' => $DB->sql_like_escape('local_dimensions_') . '%'])`, for the uninstall hook + a unit test. |
| `central.php` | Read `helper::get_central_prefs()` once. Use `nav` as the **default** for `contexttype`/`categoryid`/`frameworkid`/`templateid` via `optional_param($x, $nav[$x], PARAM_…)` (**URL still wins** — deep-links intact). Add `templateid` to the tabs `dataattributes`. Generalise the pre-render block so the **`nav.tab`** tab is the `active` + pre-rendered one (validated to the 3 shortnames, default frameworks) instead of hard-coding frameworks. |
| `classes/output/dynamictabs/structure.php` + `structure.mustache` | Seed the show-hidden pill `checked` and the three display switches' `checked` from `display.structure` (some are hard-coded `checked` in Mustache today — make them data-driven from the renderable). |
| `classes/output/dynamictabs/plans.php` + `plans.mustache` | Seed the show-disabled pill and the list/detail gear switches' `checked` from `display.planslist` / `display.plansdetail` / `display.plansshowdisabled`. |
| `classes/output/dynamictabs/frameworks.php` | Seed the show-hidden pill/pane arg from `display.frameworksshowhidden` (central.php passes it into the frameworks pre-render + the lazy-load args). |
| `amd/src/central/preferences.js` *(new)* | Tiny module holding the merged nav/display state in memory (seeded from a page-level `data-*` blob the server renders once), exposing `saveNav(partial)` / `saveDisplay(partial)` that deep-merge and call `setUserPreference(name, JSON.stringify(state))`, **debounced ~400ms**. |
| `amd/src/central/context.js` | `saveNav({tab})` in the `shown.bs.tab` handler (`:275`); `saveNav({contexttype, categoryid})` at the tail of `setContext`/`setCategory`. |
| `amd/src/central/structure.js` | `saveNav({frameworkid})` on framework selection; `saveDisplay({structure:{...}})` in the show-hidden + display change handlers. **Remove** the `sessionStorage` read/write for these (server seeds initial; pref persists) — initial state comes from `cb.checked` (server-seeded). |
| `amd/src/central/plans.js` | `saveNav({templateid})` on template selection; `saveDisplay({...})` in the show-disabled + list + detail handlers; drop their `sessionStorage`. |
| `amd/src/central/frameworks.js` | `saveDisplay({frameworksshowhidden})` in the `:472` handler (keeps its `pane.dataset` + `reloadPane`). |
| `classes/privacy/provider.php` | Replace `null_provider` with `metadata\provider` + `request\user_preference_provider`: `get_metadata()` (two `add_user_preference`), `export_user_preferences(int $userid)` (two literal `writer::export_user_preference` blocks, each guarded by `isset`). No delete methods. Keep the file's no-`MOODLE_INTERNAL`-guard convention (matches the current file). |
| `db/uninstall.php` | Add `\local_dimensions\helper::purge_user_preferences();` and update the docblock to mention preferences. |
| `lang/en` + `lang/pt_br` | Add `privacy:metadata:preference:central_nav`, `privacy:metadata:preference:central_display` (in the correct alphabetical slot, both files in sync). Remove the now-false bare `privacy:metadata` string (its only consumer, `null_provider::get_reason`, is gone). |
| `version.php` | Bump `2026070903 → 2026071000`. |
| `db/upgrade.php` | `if ($oldversion < 2026071000) { purge_all_caches(); upgrade_plugin_savepoint(true, 2026071000, 'local', 'dimensions'); }`. |
| `amd/build/**` | Rebuild via `npx grunt amd --root=public/local/dimensions`; commit the `.min.js` + `.map` for every touched module. |
| `README.md` | Document the persisted view state + the privacy behaviour (export + uninstall cleanup). |

### 3.3 Why server-seeded initial state (not client-read)
The client already prefers a provided value over `cb.checked`. By making the **server** render the
saved-preference state into the toggle `checked`/`data-*` and the correct active tab / context /
selected content, we (a) get the right first paint with **no flash and no extra AJAX read**, (b)
delete the client `sessionStorage` read path, and (c) let the tab renderers' existing graceful
fallbacks handle stale ids (a deleted framework/template/category degrades to the auto-selection;
`resolve_central_context` already downgrades an unreadable category to system).

## 4. Data flow / lifecycle

1. Load → `central.php` reads `get_central_prefs()`; the URL (if present) overrides `nav`. It
   pre-renders the `nav.tab` tab as active with the restored context/selection, renders the
   contextbar in the restored context, and seeds every toggle `checked` from `display`. It also
   emits a page-level `data-*` blob of the merged state for the JS.
2. `preferences.js` init reads that blob into memory (no server round-trip).
3. User acts → the existing change handler applies the UI change **and** calls `saveNav`/`saveDisplay`
   (debounced) → `setUserPreference`.
4. Next visit (any session/device) → step 1 restores it.

## 5. Testing

- **PHPUnit** (`tests/`):
  - `lib.php` callback: `local_dimensions_user_preferences()` returns the two definitions with the
    expected `type`/`null`/`permissioncallback`.
  - `helper::get_central_prefs()`: valid pref round-trips; corrupt JSON → defaults; out-of-range /
    unknown values sanitised (bad `tab` → `frameworks`, non-numeric id → 0, `structure.rule`
    defaults on).
  - `provider`: `get_metadata()` yields the two items and their lang keys resolve;
    `export_user_preferences()` exports set prefs and skips unset ones (use the core privacy
    provider testcase helpers).
  - `helper::purge_user_preferences()`: set a `local_dimensions_*` pref + a foreign pref, purge,
    assert only ours is gone.
- **Behat** (CI-only, thin): switch to the Structure tab, reload the page, assert Structure is the
  active tab (robust — needs no gear panel). Toggle-level persistence is covered by the PHPUnit
  callback/seed tests, per the repo's "logic in PHPUnit, Behat stays a smoke test" rule. Grep
  `tests/behat/` for any label touched by the toggle seeding change and fix affected steps in the
  same change.
- **Lint/build:** `npx eslint --max-warnings 0 public/local/dimensions/amd/src`; `npx stylelint`;
  `grunt amd` rebuild. Grep changed PHP for >132-char lines and lowercase-leading `//` comments
  before push (phpcs has no local runner).

## 6. Compatibility

- `core_user/repository`'s `setUserPreference` + the `*_user_preferences()` callback registry are
  present and behave uniformly across 4.5→5.2 (verify on the CI 4.05 leg — the only version-specific
  checkpoint). Reference by module name.
- `PARAM_RAW` + read-side validation sidesteps the 5.x strict-route throw while staying lenient on
  4.5.
- No new capability needed: setting one's own preference is gated by `is_current_user`, mirroring
  `core_user`.

## 7. Risks / open questions (resolved during implementation)

1. **Mustache `checked` seeding:** several display switches hard-code `checked` in markup today;
   converting them to renderable-driven `{{#checked}}checked{{/checked}}` must keep the Mustache lint
   `Example context (json)` blocks valid.
2. **Pre-rendering a non-frameworks landing tab:** confirm `structure`/`plans` `require_access()` +
   `export_for_template` are safe to call eagerly in `central.php` (same code path as the lazy
   `getContent`, just earlier).
3. **Debounce vs. navigation:** a `saveNav` fired just before the browser unloads (tab click that
   also reloads) must still land — discrete nav events are low-frequency; keep the debounce short and
   flush on the tab/context handlers.
4. **`null` reset semantics:** `NULL_ALLOWED` lets a future "reset my view" action delete the pref;
   read-side treats missing/empty as defaults.
5. **pt_br sync:** the removed `privacy:metadata` string and the two added keys must land in both
   language files in the same change (the `validate` step enforces ordering).

## 8. Rollout

Single logical change delivered as one commit (repo's direct-to-`main` convention): `lib.php`,
`helper.php`, `central.php`, the three tab renderables + templates, `preferences.js` (new) + four
edited AMD modules, `provider.php`, `db/uninstall.php`, `db/upgrade.php`, `version.php`, both lang
files, `amd/build/**` rebuild, `README.md`, and tests. Then a versioned `git archive` zip for a test
install, exercising: leave on Structure/coursecat/selected-template with custom toggles → return in a
fresh session → state restored; and an uninstall → assert no `local_dimensions_*` rows remain in
`user_preferences`.
