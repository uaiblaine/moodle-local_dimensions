# Claude instructions for `local_dimensions`

This file is auto-loaded as context whenever Claude works in this plugin's
directory tree. It captures the **Moodle development standards** this plugin
follows so future edits stay in the same style and pass CI on the first try.
The conventions are shared with the sibling plugins `block_feedback_tracker`
and `local_profilefield_repeatable`; this file keeps only what is true here.

Plugin context: a Moodle **local** plugin ("Competency Dimensions") that
extends core competencies and learning plan templates with custom fields and
renders two learner-facing views — **Competency tracker** (course-card grid)
and **Full plan overview** (expandable accordion) — plus a draggable "Return
to Plan" FAB. It defines **no database tables of its own**: all data lives in
core competency tables and `customfield_data`. Supports Moodle **4.5 through
5.2** (`$plugin->requires = 2024100700`, `$plugin->supported = [405, 502]`).
CI is the **moodle-an-hochschulen/moodle-workflows** reusable workflow, called
once per supported branch in `.github/workflows/ci.yml` (5.02 full PHP × DB
matrix; 5.01/5.00/4.05 one-DB-only) — **update those calls when `supported`
changes**. Development happens on Moodle 5.1.

## Commands

This plugin lives as a **real git clone at `public/local/dimensions` inside the
dev Moodle checkout** (`/Volumes/N1TB/dev/github/moodle`, 5.x split layout with
the webroot under `public/`). That clone is the **single working tree**: edit,
build and `git archive` all happen there directly — no separate standalone clone
and no rsync are involved. It has its own history (branch `main`, remote
`uaiblaine/moodle-local_dimensions`), so run git from the plugin dir (or
`git -C`). `git fetch && git pull` before starting so you don't build on a stale
base.

### Building JavaScript assets (required before committing JS)

The AMD modules in `amd/src/*.js` compile to `amd/build/*.min.js` via Moodle's
grunt. Since the plugin already sits at its real mirror path inside the checkout
(`public/local/dimensions` is a real directory, not a symlink), grunt builds it
**in place** — run it from the Moodle root, where `node_modules` and
`Gruntfile.js` live (the plugin has none of its own):

```sh
cd /Volumes/N1TB/dev/github/moodle
npx grunt amd --root=public/local/dimensions
```

The build writes the rebuilt `.min.js` + `.map` straight into the clone's
`amd/build/`. (On a 4.5 checkout the mirror path is `local/dimensions`, so
`--root=local/dimensions` instead.)

`amd/build/**` is **tracked in git** — Moodle serves the compiled output, not
`amd/src`. Every `amd/src` edit must ship its rebuilt `.min.js` + `.map` in the
same commit, and a `version.php` bump so the cache revision changes. Hand-edited
minified files are a stopgap only; regenerate with grunt before pushing so the
module-name annotation, source map, and minification match what Moodle expects.

### Linting (run from the Moodle root, pre-push)

```sh
npx eslint --max-warnings 0 public/local/dimensions/amd/src
npx stylelint public/local/dimensions/styles.css
```

CI runs `grunt --max-lint-warnings 0`, so **every ESLint/Stylelint warning fails
the build** — there is no warning tier. A plain local `npx grunt amd` build does
**not** fail on ESLint warnings (it prints them and exits 0, easy to miss in
filtered output) — always run the `npx eslint --max-warnings 0` command above
before pushing. `promise/no-nesting` is the usual offender: an intentional
nested chain (e.g. a recovery reload inside a `.catch` handler) needs
`// eslint-disable-next-line promise/no-nesting` on the line directly above the
nested call, with a comment saying why. The local stylelint config
(`.stylelintrc.json`) extends `stylelint-config-standard` with 4-space indent,
single quotes, short hex, and `selector-class-pattern ^[a-z0-9\-]+$`. The repo's
own `package.json` has only stylelint devDeps — **don't** run `npm run build`
here; the canonical artefacts come from Moodle's Gruntfile.

CI's stylelint is **Moodle's own config** (`/Volumes/N1TB/dev/github/moodle/.stylelintrc`) —
stricter than the plugin's `.stylelintrc.json`, which carries none of the rules below.
**It IS reproducible locally** — point stylelint at core's config, from the Moodle root:

```sh
npx stylelint --config .stylelintrc public/local/dimensions/styles.css
```

Not byte-identical to grunt's invocation (grunt adds an `at-rule-no-unknown` override for
raw CSS), so treat its `max-line-length` warnings as advisory — but all three rules below
are **errors**, and this reproduces them exactly. Run it before pushing CSS:

- `declaration-no-important` — never write `!important`. When a Bootstrap
  utility in the markup (`.d-flex`, `.d-block` — both `!important`) would fight
  a `display` you need to toggle, drop the utility from the template and own
  the property in a plugin class instead (see the plan-row visibility rules).
  `keyframe-declaration-no-important` closes the same door inside `@keyframes`.
- `csstree/validator` — rejects property values its (older) grammar doesn't
  know: `clamp()`/`min()`/`max()` fail with "Invalid value" in **every
  length-valued property** — not just `height`-like ones: `width`, `max-width`,
  `font-size`, `padding`, `margin`, `gap`, `flex-basis` all reject them. Use plain
  `height` + `min-height`/`max-height` pairs; `calc()` is accepted, as is grid `minmax()`.
- `time-min-milliseconds: 100` — a transition/animation under 100ms is a hard error.
  The kit's motion scale (150/250/1500ms) clears it, but "80ms, snappier" fails CI.

### Test deploy / dev loop

Deployment is a **manual zip install** on a test server, produced by `git archive`
from the plugin clone. `git archive` packages a **commit** (a tree), never the
working tree — so commit first; uncommitted edits never enter the zip.

To test local work **before it is pushed**, archive `HEAD` (the current branch
tip, pushed or not); name the zip `dimensions-<version>-<shortSHA>.zip` so each
test install is traceable. The short SHA is **required**, not optional: the
`version.php` version is frozen (many slices share one version number), so the
version alone can't tell two builds apart — the commit SHA is what does:

```sh
ver=$(grep -oE '\$plugin->version[[:space:]]*=[[:space:]]*[0-9]+' \
  /Volumes/N1TB/dev/github/moodle/public/local/dimensions/version.php | grep -oE '[0-9]+')
sha=$(git -C /Volumes/N1TB/dev/github/moodle/public/local/dimensions rev-parse --short HEAD)
git -C /Volumes/N1TB/dev/github/moodle/public/local/dimensions archive \
  --format=zip --prefix=dimensions/ HEAD -o ~/Downloads/dimensions-$ver-$sha.zip
```

To package the **published** state instead, `git fetch origin` first and archive
`origin/main` in place of `HEAD` (and read the SHA from `origin/main`) — the fetch
is only needed there, to refresh the remote ref `origin/main` resolves to. For the JS dev loop, set *Site admin →
Development → Debug = DEVELOPER* and *cachejs = off* so Moodle serves `amd/src`
directly without a rebuild.

## CI gating

`moodle-plugin-ci install` runs per job. The **static leg** gates on: `phplint`,
`phpmd` (informational), `phpcs --max-warnings 0` (**warnings fail**),
`phpdoc --max-warnings 0`, a development-leftover checker that fails on stray
to-do markers / merge-conflict markers in **any** file (docs included — never
write those tokens literally), `validate`, `savepoints`, `mustache`, and
`grunt --max-lint-warnings 0` (incl. stylelint). **Runtime legs** run PHPUnit
(`--fail-on-warning`) and Behat on every PHP × DB combination.
`.moodle-plugin-ci.yml` filters `node_modules`/`vendor` from the scan.

phpcs/phpdoc/PHPUnit/Behat have **no local runner** here — eyeball them at write
time against the rules below; only eslint/stylelint are verifiable locally.

## Code layout

```
settings.php                 Admin tree — added under the 'competencies' admin
                             category (not 'localplugins'), gated on
                             get_config('core_competency', 'enabled')
lib.php                      Procedural hooks + SCSS injection
version.php                  component / version / requires / supported
view-plan.php                Learner views (plan overview / competency tracker)
view-competency.php          Single-competency detail view
central.php                  Admin: the Competency hub (Structure / Learning
                             plans / Frameworks tabs — the whole admin surface)
customfield*.php             Custom field config landing pages (core field defs)
classes/
  hook_callbacks.php         before_footer_html_generation → Return FAB
  helper.php                 Custom-field provisioning + return-context + queries
  observer.php               core_competency event observers (cache + cleanup)
  calculator.php             Real-time course/section progress
  constants.php              CFIELD_* shortnames + shared constants
  *_cache.php                MUC loader wrappers (template/competency metadata,
                             template_course, plan_trail)
  scss_manager.php           Per-template/competency SCSS compile + cache
  picture_manager.php        Card image handling (builtin vs customfield_picture)
  chip_filters.php           Custom-field-driven chip filter model
  admin/                     setting_iconpicker (AJAX FontAwesome picker)
  customfield/               competency_handler + lp_handler (two CF areas)
  event/                     Audit events (cohort links/roles, customfields,
                             course/module links, duplication)
  external/                  Web-service functions (one class each)
  form/                      dynamic_form subclasses (competency/template/framework)
  local/                     plan_status and other value helpers
  output/                    Renderables: learner (view_*_page) + hub
                             (central/, dynamictabs/ tab classes)
  task/                      Adhoc tasks (cohort role + template cohort sync)
  privacy/                   Null provider (no personal data stored)
  reportbuilder/             Datasources + entities (competencies, plans)
db/                          access, caches, events, hooks, services, install,
                             upgrade, uninstall  (NO install.xml — no own tables)
templates/                   Mustache (server-rendered UI)
amd/src/                     Plain AMD modules (define([], …)) — NOT Preact/React
amd/build/                   Committed minified output (grunt) — keep in sync
lang/{en,pt_br}/             English + Brazilian Portuguese, both kept in sync
tests/                       PHPUnit (observer, helper_* et al) + behat/
                             (hub smoke-test .features + step definitions)
```

## Architecture gotchas

### Custom-field auto-provisioning
The plugin owns two `customfield` areas via `classes/customfield/`:
`competency_handler` and `lp_handler` (learning plan templates). Fields are
provisioned lazily: `helper::ensure_all_fields()` runs once per session from the
footer hook (guarded by `get_config('core_competency', 'enabled')`), and
`helper::ensure_custom_fields_on_setting_change()` runs from setting
`set_updatedcallback`s. Field shortnames are constants in
`classes/constants.php` (`CFIELD_*`) — reference those, never string literals.
Storage facts that bite: the both-areas fields (colors, tags, filters) reuse the
**same shortname** in the lp and competency areas — never `get_record` on
`customfield_field` by bare shortname (dml_multiple_records); scope through the
category (component+area) or the handler. Data rows carry `instanceid = <id>`
and **`itemid = 0`**; files embedded in field data are keyed by the **data row
id** (`customfield_textarea`/`value`, `customfield_picture`/`file`), not the
instance. Provisioning is serialised under a core Lock API lock and calls
`reset_configuration_cache()` after acquiring it (the plugin handlers override
`create()` as **singletons**, unlike core's per-call `create()`) — keep both
when touching `ensure_custom_fields_exist()`; neither `customfield_field` nor
`customfield_category` has a DB unique index to catch duplicates.

### Custom-field data cleanup on delete (Moodle 5.1+)
Core destroys the instance context **before** firing `competency_deleted` /
`competency_template_deleted`, so a context-scoped `delete_instance()` cleanup
finds nothing. `observer.php` therefore sweeps `customfield_data` by instance id
+ area directly. Preserve that context-independent path when touching deletion.

### Return-to-Plan FAB (`hook_callbacks::before_footer_html_generation`)
Renders only when: feature enabled, logged-in non-guest, a course is in context,
the page is **course content** — a pagelayout **allowlist** (`course`/`incourse`,
fails closed so `secure` quiz windows, popups, `mypublic` profiles and
layout-less scripts never get the button) plus a pagetype blocklist for the
administrative core pages that ship layout `incourse` (participants, tool_lp,
`grade-*`, quiz editing…) — and a stored return context exists for that course.
Anti-loop invariant: every FAB URL `view-competency.php` writes carries
`noredirect=1` (honoured in its `$willredirect` check), and when it does
redirect it writes the **plan** URL for the destination course instead — keep
both when touching the redirect path. Both views write contexts **only for the
plan's own user** (staff reviewing someone else's plan must not pollute their
session), and the `returncontext` session cache has a 4h defensive TTL. The FAB
is draggable; its position persists in `sessionStorage` (per-tab, current
session) — see `amd/src/return_button.js`.

### Caches and invalidation
`observer.php` invalidates the metadata/trail caches on the relevant
`core\event\competency_*` events. When you add a query that reads cached
metadata, add the matching invalidation to the observer rather than relying on
the defensive TTL alone.

### Audit events (`classes/event/`)
The hub logs decisions core never does (cohort attach/detach, cohort-role
rules, customfield value changes, course/module links, duplication). Events
need **no registration** (`db/events.php` is observers-only); `objecttable`
over a core table is legal (mod_quiz precedent) but then `objectid` is
required — fetch link rows **before** deleting them. Core APIs that return
`false` on the idempotent duplicate path (`create_cohort_role_assignment`,
`add_competency_to_template`) must not reach a trigger's `->get()`. The two
`*_customfields_updated` events fire from the `instance_form_save()` override
in both handlers (covers modals, WSes, observer repost, CSV import — new
handler writes are auto-logged) and diff **effective** values via
`get_value()`, redacting textarea bodies to `'(updated)'`. In PHPUnit, core
refuses a module link unless the competency is on the course first.

## Coding style

### File header
Every PHP file starts with the GPL block, then a file docblock with
`@package local_dimensions`, `@copyright`, `@license` (no `@author`).
Namespaced class files add `namespace local_dimensions\<sub>;`. Use
`defined('MOODLE_INTERNAL') || die();` in every file **with side-effects**
(procedural files, `db/*.php`, files with `require_once`/globals). **Omit** it in
pure namespaced single-class files (constants/enums/handlers with no
side-effects) — the sniff `moodle.Files.MoodleInternal.MoodleInternalNotNeeded`
fails the build otherwise. (This plugin's classes do not use
`declare(strict_types=1)`; match the surrounding files.)

### PHPDoc (`phpdoc --max-warnings 0`)
- Every class, method, property, constant has a `/** */` docblock; `@param`,
  `@return`, `@throws` declared explicitly even when implied by the signature.
- **`@param` array types must be plain `array`** — `local_moodlecheck` can't pair
  `$var` to its parameter when the type is a generic (`array<int,string>`) or a
  shape (`array{...}`), and reports "incomplete parameters list (error)". Put the
  shape in the description prose. `@return array{...}`/`array<…>` is fine (no var
  to pair).
- Property docblocks need `@var` even with typed properties
  (`moodle.Commenting.VariableComment.MissingVar`).

### Naming
- Classes/methods: `lower_snake_case` (Moodle, not PSR-4 PascalCase).
- Constants: `UPPER_SNAKE_CASE`. Properties: single lowercase word where possible.
- Frankenstyle prefix on globals/functions: `local_dimensions_*`.

### CodeSniffer rules that routinely bite (pre-empt at write time)
1. **Variables are lower-case only** — no camelCase/snake_case
   (`...ValidVariableName.VariableNameLowerCase`). `$courseid`, not `$courseId`.
2. **PSR-2 multi-line calls** — `(` last on its line, one arg per line, `)` on its
   own line at call indent.
3. **Inline `//` comments**: one space, capital first letter, terminal
   punctuation. Lowercase-start / version-tagged / multi-line commentary belongs
   in a `/* … */` block (`moodle.Commenting.InlineComment.*`). The same applies to
   the leftover checker — never type to-do or merge-conflict tokens literally.
4. **Operator spacing**: exactly one space around `===`/`!==`/`?`/`:` — column
   alignment with extra spaces fails (`Squiz.WhiteSpace.OperatorSpacing`).
5. **Multi-line `if`**: first expression on the line after `(`, `)` on its own
   line (`PSR12.ControlStructures.ControlStructureSpacing.*`).
6. **Line length**: hard max **180** (error), soft max **132** (warning, and the
   warning count fails `phpdoc --max-warnings 0`). Wrap long `@return` shapes.
7. **No "commented-out code"** false positives: drop trivial trailing `//`
   comments containing `=` or PHP-looking text (`Squiz.PHP.CommentedOutCode`).

phpcs has no local runner here, so rules 3 and 6 are the ones that slip through
eyeballing and fail CI. **Before pushing, grep the changed PHP for both** — every
hit is a CI failure (`phpcs --max-warnings 0`):
```sh
# soft-max 132 line length (rule 6)
awk 'length($0)>132{print FILENAME":"NR" ("length($0)")"}' <files>
# inline // comment starting lowercase — first line of a block only; continuation
# lines whose first line is capitalised are fine (rule 3)
grep -nE '^\s*// [a-z]' <files>   # ignore the GPL header lines 5-15
```

### Dynamic string references
The string checker can't verify constructed IDs. **Don't**
`get_string('foo_' . $x, …)` — use a literal `switch`/`match` returning each
fixed key.

## Lang strings
`lang/en/local_dimensions.php` and `lang/pt_br/local_dimensions.php` are kept in
**sync** and **alphabetically sorted** (the `validate` step enforces ordering).
Conventions here: a setting uses plain keys `<key>` + `<key>_desc` (e.g.
`enablereturnbutton` / `enablereturnbutton_desc`); each cache definition has a
`cachedef_<name>`; each capability a `dimensions:<capname>` (the `local/` prefix
is dropped in the lang key). When adding a string, insert it in the correct
alphabetic slot in **both** language files.

## Web services
- Function classes under `classes/external/`, one per file, extend
  `\core_external\external_api`; `execute_parameters()` →
  `external_function_parameters`, `execute_returns()` → an external structure.
- Every read function does `validate_context()` + `require_capability()`; writes
  add an event. Register in `db/services.php` (`type` read/write, `ajax => true`)
  — **services install only on upgrade, so a new function needs a `version.php`
  bump.**
- A WS that emits localised strings must include `current_language()` in any
  cache key.
- **`execute_returns()` is an allowlist**: `clean_returnvalue` silently strips
  keys the structure doesn't declare. When a shared builder (e.g.
  `helper::structure_nodes()`) gains a field, update the returns of **every**
  WS that channels it (`browse_structure` etc.). Symptom: server-rendered rows
  carry the new data, WS-rendered ones (lazily-fetched children) don't — e.g.
  drag grips appearing only on root nodes.

## MUC caches (`db/caches.php`)
Cache **keys must avoid `:`** (unsafe in file-store paths). This plugin's keys:
`returncontext` → `course_{id}`, `*_scss` → `css_{id}`, `plan_trail` →
`{planid}_{userid}`, metadata caches → bare id. Application caches use defensive
TTLs + `staticacceleration`; session caches hold per-user transient state. Each
definition needs a `cachedef_<name>` lang string.

## Mustache templates
Every `templates/*.mustache` needs an `Example context (json):` block in its
docblock — the Mustache lint renders against it and validates the HTML (supply
non-empty loop data so the preview produces valid markup). **Never write a `{{…}}`
tag inside the `{{! … }}` docblock** (e.g. describing the template with
"strings via `{{#str}}`"): Mustache comments don't nest and close at the *first*
`}}`, so the `}}` of the embedded tag ends the comment early and the rest of the
docblock (incl. `Example context (json): { }`) renders as visible text. Describe
tags in prose without the braces. Use triple-stash
`{{{html}}}` only for trusted server-rendered HTML (e.g. `moodleform::render()`).
Server-side rendering uses `renderable` + `templatable` + `render_from_template`
— **zero `html_writer`** in plugin code (moodleform's own markup excepted).

## Forms (moodleform)
Form classes under `classes/form/` start with
`require_once($CFG->libdir . '/formslib.php')` (moodleform isn't autoloaded). The
submit-button label must differ from any collapsible section-header label (a11y +
Behat target the header toggle otherwise). Populate rich-text/editor fields via
`set_data()` (form-level), not `setDefault()`, so TinyMCE initialises with text +
format.

## Upgrade savepoints
Each `db/upgrade.php` step ends with
`upgrade_plugin_savepoint(true, <version>, 'local', 'dimensions');` — match
`<version>` to the `version.php` bump.

## PHPUnit tests
- `tests/<area>/<thing>_test.php`; class
  `local_dimensions\<ns>\<thing>_test extends \advanced_testcase`; `@covers`
  annotation on the class docblock; `$this->resetAfterTest()` in any DB test.
- `$DB->get_records()` / `getDataGenerator()->create_*()` return **string** ids
  under both drivers — cast to `(int)` for typed-int signatures and normalise
  haystacks before strict `assertContains`.

## Behat (JS) — CI-only, locator gotchas
No local Behat here — a new `.feature` is first exercised in CI, so budget one
fix-and-repush; keep scenarios as thin smoke tests and put the logic in PHPUnit.
Hard-won:
- **Autocomplete:** pick a value with **only** `I set the field "<label>" to
  "<text>"` — it types, clicks the auto-activated suggestion and presses ESC
  (`behat_form_autocomplete::add_value`). A following `I click on "…" item in the
  autocomplete list` hits a now-hidden `<li>` → `ElementNotInteractableException`.
- **Confirm dialog:** `… "button" in the "<X>" "dialogue"` matches the modal by its
  **title** (the first arg of `Notification.saveCancelPromise`/`deleteCancelPromise`),
  not the word "Confirmation".
- **Checkbox:** the `"checkbox"` named selector needs a real `<label>` (for/wrapping),
  **not** `aria-label`.
- **Progressive-disclosure UI:** controls inside dropdown menus or collapsed panels
  exist in the DOM but are **not interactable** — the step dies with
  `ElementNotInteractableException` (no retry). Open the container first: the ⋯
  overflow menu ("More actions"), a row kebab ("Actions" scoped to the
  `"list_item"`), the "Add competency" panel, the "Add to filter" picker. After
  any pane reload (add/remove/edit) collapsed panels **re-collapse** — re-open
  before the next interaction, and put a `Then I should see` barrier after the
  reload so the click doesn't race the re-render.
- **Icon-only buttons:** give them an `aria-label`; the `"button"` named selector
  matches it (Moodle 3.11+). Disambiguate per-row toggles by scoping to the row's
  `"list_item"`.
- **aria-labels that embed the row name hijack name-based clicks:** the button
  selector matches `aria-label` by `contains()` and takes the **first
  document-order hit** — a hover-revealed helper (opacity: 0 is still
  WebDriver-interactable!) whose label is "Move to position…: {name}" placed
  before the real row control steals `I click on "{name}" "button"`. Put such
  helpers **after** the main control in the DOM and pull them left visually
  with flex `order: -1` (see the drag grips).
- **Reworking a tab's UI breaks its `.feature` steps** — grep `tests/behat/` for
  every label/button you move, rename or collapse, and fix the scenarios in the
  same commit (this cost a full CI round on the Plans-tab redesign).
- Don't Behat tree expand / infinite scroll / shift-select / drag-drop (headless-fragile)
  — cover them in PHPUnit at the data layer.

## Hub front-end (AMD / modals)
New `amd/src` is **ESM**; the hub is **zero-YUI** — reused legacy YUI components
(`tool_lp/competencypicker`, `competencyruleconfig`) break embedded here, so wrap core
web services in a native `core/modal*` instead.
- **Autocomplete in a modal:** enhance on the `ModalEvents.shown` event —
  `core/form-autocomplete` `enhance()` resolves the element via `document.querySelector`,
  which finds nothing before `modal.show()` attaches the modal. A single-select autocomplete
  has no clear API → re-render the body to reset it.
- **Exclude list:** read `data-exclude` via `element.dataset` (fresh per search) in your own
  datasource; `core/form-cohort-selector` caches it via jQuery `.data()`.
- **Raw `<select>` chevron:** `form-select` (the BS5 *classes* are bridged on 4.5);
  never `custom-select`.
- **Bootstrap 4 vs 5 — JS data attributes are NOT bridged:** Moodle 4.5 runs
  Bootstrap 4, whose data-API listens on `data-toggle`; 5.x listens on
  `data-bs-toggle`. Components wired via markup (dropdowns etc.) need **both**
  attributes side by side, and both alignment classes
  (`dropdown-menu-right dropdown-menu-end`). Symptom of forgetting: the toggle
  clicks fine but the menu never opens on 4.5 — CI's 4.05 Behat leg catches it
  as `ElementNotInteractableException` on the menu item.
- **`[hidden]` vs `.d-block`:** `.d-block { display:block !important }` overrides `[hidden]`;
  to toggle via `el.hidden` use a plain block (`<div>`). `.form-check` adds `margin-left:-1.5em`
  to its input (overlaps a preceding chevron) — use a plain `d-flex` row for custom rows.
- **Feedback in modals (house pattern):** for success/error/info messages fired from inside a
  `core/modal`, **host a toast region in the modal body** so `core/toast` renders *above* the
  dialog. The page-level `.toast-wrapper` is `z-index:1051` (below the modal's `1055`), so a toast
  fired from a modal lands behind it. On `ModalEvents.shown` call
  `addToastRegion(modal.getBody()[0]).catch(Notification.exception)` (from `core/toast`); core's
  `core/modal` auto-removes it on close (`removeToastRegion(this.getBody())`), so no leak and **no
  global z-index override**. The **host** modal owns the region — modules that only `mount()` into
  it (`cohort_manager`, `participants_users`) must not add their own. For an *in-place* change (a row
  added/edited without a full list reload) also briefly **flash** the affected element
  (`el.animate([{backgroundColor: '#fff3cd'}, {backgroundColor: 'transparent'}], {duration: 1500})`)
  so the confirmation is visible where the user is looking. JS-built `<select>`/inputs need an `id`
  or `name` or the browser logs an autofill warning. (Wired in `competency_links` +
  `participants_manager`.)
- **dataset-as-truth panes:** seed `pane.dataset.<arg>` from the server-rendered selected value
  in `init`, or a WS receives 0 → `context::instance_by_id()` "Invalid context id".
- **PHP:** `array_flip([5])` → `[5 => 0]`, so `!empty($map[5])` is **false** — test membership
  with `isset`, or build an explicit `[$id => true]` map.

## Cross-DB SQL
CI runs PostgreSQL and MariaDB. Avoid `SELECT :literal FROM t` (PG infers text);
avoid `ORDER BY … NULLS FIRST` (use `COALESCE(col, 0)`); cast numeric columns
read from the DB to `(int)`/`(float)` when typing matters.

## Git / version.php
The plugin repo (`main`) is separate from the Moodle checkout it's built inside —
run git from the plugin dir (or `git -C`), since `cd` doesn't persist between Bash
calls. When rebasing/cherry-picking conflicts on the `version.php` `$plugin->version`
line, keep the **higher** number so the upgrade still triggers.

## When in doubt
Follow the patterns in existing files. The codebase is internally consistent —
if a new file feels like it matches no existing shape, re-examine the approach.
