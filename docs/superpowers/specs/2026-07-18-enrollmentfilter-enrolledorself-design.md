# Enrolment filter — new "enrolled and self-enrolable" option

Design for a fourth `enrollmentfilter` value that shows, per learner, the courses
they are **enrolled in (including future/suspended enrolments)** *plus* the linked
courses they **could self-enrol into** — reusing the same `can_self_enrol` gate the
`singlecourseredirect` feature already relies on.

Date: 2026-07-18 · Status: approved (design), pending implementation.

## Problem / goal

`local_dimensions/enrollmentfilter` decides which of a competency's linked courses a
learner sees in the **Competency tracker** and the **Full plan overview** accordion.
Today it offers three modes:

- `all` — every linked course (default).
- `enrolled` — only courses with any enrolment record, `is_enrolled(onlyactive=false)`
  (so future-dated and suspended enrolments still count).
- `active` — only actively enrolled courses, `is_enrolled(onlyactive=true)`.

We want a fourth mode that is the **aggregate** the user described:

> "Exibir todos os cursos inscritos e autoinscrições" — enrolled courses (incl.
> future) **plus** linked courses the user can self-enrol into.

It must be selectable at all three cascade levels: the global admin setting, the
per-plan-template override, and the per-competency override.

## Current mechanics (verified)

- **The filter is one method.** [`calculator::filter_courses_by_enrollment($courses,
  $userid, $filtermode)`](../../classes/calculator.php) at `classes/calculator.php:344`
  is the only place courses are kept/dropped. `all` short-circuits; otherwise it loops
  `is_enrolled($ctx, $userid, '', $onlyactive)` with `$onlyactive = ($filtermode === 'active')`.
- **Exactly two production callers**, both routing through that one method after resolving
  the cascade and skipping when the mode is `all`:
  `view-competency.php:88-93` (server-rendered tracker) and
  `classes/external/get_competency_courses.php:100-102` (accordion lazy-load web service).
  **A single new branch covers both views.**
- **The self-enrol logic we need already exists.** [`calculator::user_can_access_course()`](../../classes/calculator.php)
  at `classes/calculator.php:376` does `is_enrolled(onlyactive=true)` **OR**, for the
  current `$USER` only, iterates `enrol_get_instances($course->id, true)` and accepts a
  `self` instance when `$selfplugin->can_self_enrol($instance, false) === true` (strict
  `=== true`; the API returns `true` on success or a localized error string). This is the
  gate behind `singlecourseredirect` (`view-competency.php:110`).
- **The cascade, cache, CSV, and validation are option-list-driven.** The resolver
  (`helper::resolve_enrollmentfilter_for_view`, `classes/helper.php:1175`), the template
  resolver (`helper::get_template_enrollmentfilter`, `classes/helper.php:752`), the metadata
  cache (`template_metadata_cache::normalise_payload`, `classes/template_metadata_cache.php:550`),
  and CSV import/export (`helper::customfields_to_formdata` /
  `local/framework_csv_serializer.php`) all validate against
  `array_keys(constants::enrollmentfilter_options())`. Adding the option there propagates
  automatically — **no change needed in any of them.**
- **Rendering needs no change.** A course card links to `/course/view.php?id=X`
  (`classes/output/view_competency_page.php:159`). A self-enrolable-but-not-enrolled course
  therefore lands the learner on Moodle's own self-enrolment page — the correct destination.

## Two load-bearing invariants (the "special attention")

**1. Option order is data, not cosmetics.** The per-plan / per-competency override is a
`select` custom field that stores a **1-based index** into `enrollmentfilter_options()`
(`helper::get_template_enrollmentfilter` maps back with `$allowed[$value - 1]`,
`classes/helper.php:767`; CSV maps forward with `select_key_to_index`). Current order:
`1=inherit, 2=all, 3=enrolled, 4=active`. **The new option MUST be appended last (index 5).**
Inserting it anywhere else silently remaps every already-stored override. The global admin
setting stores the string key, not an index, so its menu order is cosmetic only.

**2. Existing sites do not re-sync a provisioned field.** `helper::get_enrollmentfilter_field()`
(`classes/helper.php:620`) returns early when the field exists and never rewrites its
`configdata['options']`. The upgrade catch-all that re-runs `ensure_custom_fields_exist`
inherits that early-return, so it will **not** add the new option to an already-provisioned
select. To make the option appear in the per-plan / per-competency modals on existing
installs, a dedicated upgrade step must append it to the two select fields' `configdata`.

## Decisions (confirmed with the user)

- **Key & labels.** Constant `ENROLLMENTFILTER_ENROLLEDORSELF = 'enrolledorself'`.
  EN label: `Show enrolled and self-enrolable courses`.
  PT-BR label: `Exibir cursos inscritos e disponíveis para autoinscrição`.
- **Semantics.** `is_enrolled(onlyactive=false)` (the existing `enrolled` leg — includes
  future/suspended) **OR** self-enrolable by the current `$USER`. A strict superset of `enrolled`.
- **Migration.** A dedicated `db/upgrade.php` step + a `version.php` bump (the freeze policy
  explicitly permits a bump for a structural change). Fresh installs get all five options via
  the create path; existing installs get option 5 appended by the upgrade step.
- **Third-party view.** `can_self_enrol` only answers for the logged-in `$USER`. When a
  manager views **another** learner's plan, the self-enrol leg is skipped and the mode
  degrades to `enrolled`-only for that learner (only their real enrolments show). This mirrors
  the existing `user_can_access_course` limitation. We **accept** it **and document it** by
  appending a sentence to `enrollmentfilter_desc` in both languages.

## Design — changes per file

### 1. `classes/constants.php`
- Add `const ENROLLMENTFILTER_ENROLLEDORSELF = 'enrolledorself';` in the enrolment-filter
  constant block, after `ENROLLMENTFILTER_ACTIVE`.
- **Append** to `enrollmentfilter_options()` as the **last** entry (index 5):
  `self::ENROLLMENTFILTER_ENROLLEDORSELF => new \lang_string('enrollmentfilter_enrolledorself', 'local_dimensions')`.

### 2. `classes/calculator.php` (change to established code — flagged)
- **Extract** the `self`-instance loop from `user_can_access_course()` into a private
  `current_user_can_self_enrol(int $courseid): bool` (the `require_once` +
  `enrol_get_plugin('self')` + `enrol_get_instances(..., true)` + `can_self_enrol($instance, false) === true`
  block). `user_can_access_course()` keeps identical behaviour by calling it. *Alternative if
  minimal-touch is preferred: duplicate ~6 lines instead of extracting — noted, not chosen.*
- **New method** `user_enrolled_or_self_enrolable(\stdClass $course, int $userid): bool`:
  returns true if `is_enrolled($ctx, $userid, '', false)`; else, for `$userid === (int)$USER->id`
  only, returns `current_user_can_self_enrol($course->id)`; else false.
- **New branch** in `filter_courses_by_enrollment()`: when
  `$filtermode === constants::ENROLLMENTFILTER_ENROLLEDORSELF`, keep courses where
  `self::user_enrolled_or_self_enrolable($course, $userid)`. The existing `all` short-circuit
  and the `active`/`enrolled` path are untouched. (`calculator` is in `namespace local_dimensions`,
  so `constants::` resolves directly.)

### 3. `settings.php`
- Add `'enrolledorself' => get_string('enrollmentfilter_enrolledorself', 'local_dimensions')`
  to the `admin_setting_configselect` choices array (append last; order is cosmetic here).

### 4. `lang/en/local_dimensions.php` & `lang/pt_br/local_dimensions.php`
- Add `enrollmentfilter_enrolledorself` in **both** files, in the correct alphabetic slot —
  **between** `enrollmentfilter_enrolled` and `enrollmentfilter_inherit`.
- Append the third-party-view note to `enrollmentfilter_desc` in **both** files, e.g.
  EN: `… Self-enrolable courses are only counted when a learner views their own plan.`
  PT: `… Cursos com autoinscrição disponível só são contabilizados quando o próprio aluno visualiza seu plano.`

### 5. `version.php`
- Bump `$plugin->version` from `2026071306` to `2026071800` (unfreezes for this structural
  change; `requires`/`supported` unchanged, so `.github/workflows/ci.yml` is untouched).

### 6. `db/upgrade.php`
- Add, **above** the field-provisioning catch-all, a block:
  ```php
  if ($oldversion < 2026071800) {
      // Append the "enrolled and self-enrolable" option to the existing
      // enrollmentfilter select fields (lp + competency). The provisioning
      // catch-all short-circuits on the existing field and never re-syncs its
      // option list, so this reconcile is required on already-installed sites.
      \local_dimensions\helper::sync_enrollmentfilter_option(\local_dimensions\helper::AREA_LP);
      \local_dimensions\helper::sync_enrollmentfilter_option(\local_dimensions\helper::AREA_COMPETENCY);

      upgrade_plugin_savepoint(true, 2026071800, 'local', 'dimensions');
  }
  ```
- **New helper** `helper::sync_enrollmentfilter_option(string $area): void` (kept in `helper.php`
  so it is unit-testable and re-usable, not inlined in upgrade.php):
  1. Locate the field **area-scoped** via `find_field_by_shortname(CFIELD_ENROLLMENTFILTER, $area)`
     — never a bare-shortname `get_record` (the same shortname exists in both areas →
     `dml_multiple_records`). Return quietly if the field does not exist (site never provisioned).
  2. Decode `configdata`, split `options` on `"\n"`. Compute the new label
     `(string) new \lang_string('enrollmentfilter_enrolledorself', 'local_dimensions')`.
  3. **Append iff the label is absent** (idempotent; a re-run is a no-op). Do **not** gate on
     the option count — appending only ever adds a trailing entry, so it never reorders the
     first four indices, and it still works if an admin had manually added their own option.
  4. Append the label, re-encode `configdata`, persist, and purge the handler
     configuration cache (mirror the `reset_configuration_cache()` the provisioning path uses)
     so the modals see the new option immediately.

### 7. `tests/`
- Extend `tests/calculator_access_test.php` (or a sibling) to cover
  `filter_courses_by_enrollment` in `enrolledorself` mode: (a) active enrolment → kept;
  (b) future-dated enrolment → kept (proves the `onlyactive=false` leg); (c) not enrolled but a
  `self` enrol instance exists → kept for the current user; (d) not enrolled, no `self` instance
  → dropped; (e) `$userid !== $USER->id` with only self-enrolable → dropped (documents the
  third-party degrade). This also covers the `enrolled`-vs-`active` distinction the audit found
  untested.
- Add an end-to-end assertion in `tests/external/get_competency_courses_test.php` for the new
  mode (the WS currently only exercises `active`).
- Add a `sync_enrollmentfilter_option` test: provision the 4-option field, run the helper,
  assert the field now has 5 options in the right order and that a second run is a no-op.
- Cheap value-selection assertion for `enrolledorself` in `tests/helper_cascade_test.php`.
- **No Behat.** This is a display filter; self-enrolment scenarios are headless-fragile and the
  logic belongs in PHPUnit (project convention).

## What does NOT change (auto-included via the option list)
`helper.php` resolvers, `template_metadata_cache.php`, CSV import/export
(`customfields_to_formdata`, `framework_csv_serializer`), `chip_filters`, the learner
renderables (`view_competency_page`, `view_plan_page`), and all Mustache templates. All derive
their allowed values from `enrollmentfilter_options()`.

## Risks & notes
- **Performance.** The self-enrol leg runs only for the current viewer, only on courses they are
  not already enrolled in, and is bounded by the competency's linked-course count (typically a
  handful). Comparable to the existing `is_enrolled` loop and to `user_can_access_course`.
- **Keyed / guest / fee enrol.** Inherited from the existing gate: a key-protected `self`
  instance passes the filter and the learner is prompted for the key on the course page; `guest`
  and `fee` methods are not `self` instances and do not count. Consistent with `singlecourseredirect`.
- **CI.** `phpcs`/`phpdoc` (180 hard / 132 soft line limits, inline-comment casing), lang
  alphabetic ordering (both files), and the Mustache/leftover checkers apply as usual. `supported`
  is unchanged so the `ci.yml` matrix is untouched.
- **Follow-up (not part of this change).** Update the `dimensions-version-freeze` memory once the
  bump lands (freeze moves from `2026071306` to `2026071800`).

## Out of scope
- No new web service, no new custom field, no schema. No changes to the learner UI layout, the
  hub, or the FAB. No change to how `active`/`enrolled`/`all` behave.
