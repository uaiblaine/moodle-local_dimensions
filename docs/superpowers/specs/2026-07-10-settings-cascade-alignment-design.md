# Design: align enrolment/display settings across global / plan / competency

- **Date:** 2026-07-10
- **Component:** `local_dimensions`
- **Status:** approved design, pending implementation plan
- **Scope:** the resolution logic of the learner-facing settings across the three levels
  (global admin config → learning-plan template → competency). Concretely: unify the
  `enrollmentfilter` cascade across both learner views, decouple `singlecourseredirect` from the
  filter label and gate it on real course accessibility, make the related-competency accordion
  pills open in a new tab and skip the plan layer, promote `showrelated`/`showrelatedlink` to a
  per-plan override, add conditional visibility + guidance to the edit forms, and correct stale
  copy. Learner-view visual design and the Central hub are out of scope.
- **Companion artifact:** interactive settings-logic map + cascade simulator —
  `claude.ai/code/artifact/f82cf98e-b9c6-4f60-98de-80b3a41209d1`.

## 1. Goal

Make the "global → plan → competency" resolution consistent and predictable across both learner
views, and make the two enrolment behaviours (`enrollmentfilter`, `singlecourseredirect`) do what a
site admin intuitively expects:

- The **Panorama do Plano** accordion (`view-plan.php`) must honour the same `enrollmentfilter`
  cascade as the **Trilha da Competência** cards (`view-competency.php`), instead of a separate
  global-only setting.
- The single-course **redirect** must fire whenever there is exactly one *accessible* course,
  regardless of which enrolment filter is selected — where "accessible" also covers courses the
  learner can self-enrol into (native self enrolment, including a cohort restriction).
- Related-competency links open in a new tab and resolve against **global + the competency's own
  value only** (the related competency is not part of the plan).
- Per-plan control over whether the accordion shows related competencies and links.
- Edit forms show only the settings relevant to the chosen display mode and explain the cascade.

### Non-goals (decided)
- **No** per-competency override for `showrelated`/`showrelatedlink` — plan-wide toggles only.
- **No** "force plan over competency" precedence switch — the cascade stays *most-specific-wins*
  (competency beats plan beats global).
- No promotion of the other global-only settings (`showdescription`, `showpath`, `lockedcardmode`,
  `percentagedisplaymode`, …) to plan/competency level.
- No change to the routing itself (which lives in the companion `block_dimensions`).

## 2. Investigation summary (facts this design relies on)

Verified against this checkout (Moodle 5.1 working tree; plugin supports `4.5`→`5.2`, current
`version.php`: `version = 2026071000`). Multi-agent trace, 2026-07-10.

### 2.1 What cascades and what does not
- Only **two** settings resolve through the 3 levels: `enrollmentfilter` and
  `singlecourseredirect`. Both are provisioned as `select` customfields for **both** the `lp`
  (plan) and `competency` areas (`helper.php:435-467` `ensure_custom_fields_exist`), and resolved
  by `helper::resolve_enrollmentfilter_for_view()` / `resolve_singlecourseredirect_for_view()`
  (`helper.php:1025-1065`): competency CF if `!= inherit`, else template CF
  (`get_template_*`, which falls back to global), else global. Passing `templateid = 0` makes the
  resolver skip the plan layer and go competency → global.
- Everything else in `settings.php` is **global-only**, including `summaryenrollmentfilter`,
  `showrelated`, `showrelatedlink`.
- Stale docblock: `constants.php:68,71` label `CFIELD_ENROLLMENTFILTER` /
  `CFIELD_SINGLECOURSEREDIRECT` "(lp area only)" — wrong; they exist in both areas.

### 2.2 The two learner views
- **Trilha** (`view-competency.php`): course cards for one competency. Applies the
  `enrollmentfilter` cascade (`:82-88`) and the `singlecourseredirect` cascade (`:95-117`). Redirect
  condition today: `enrollmentfilter === ACTIVE && singlecourseredirect && count($courses) === 1`
  (`:99-103`), then `redirect()` to the single course (`:114-117`). `count` is measured *after* the
  filter runs.
- **Panorama** (`view-plan.php`): accordion of all plan competencies; each item lazy-loads that
  competency's courses. `accordion.js:87-97` switches to the plugin WS only when
  `summaryenrollmentfilter !== 'all'`; the WS `get_competency_courses` reads **only**
  `get_config('local_dimensions','summaryenrollmentfilter')` (`get_competency_courses.php:87`) and
  its `execute_parameters` accepts **only `competencyid`** (`:53-55`) — so it structurally cannot
  consult the plan/competency layers. **Gap:** the enrollmentfilter cascade has no effect in
  Panorama.

### 2.3 Related-competency pills
- `view-plan.php:79-80` passes `showrelated`, `showrelatedlink`, and `viewcompetencyurl`
  (`/local/dimensions/view-competency.php`) into `accordion.js`.
- `accordion.js:1796-1811`: when `showrelatedlink && viewcompetencyurl && planId`, renders
  `<a href="{viewcompetencyurl}?id={planId}&competencyid={related.id}">` **in the same tab** (no
  `target="_blank"`, unlike the evidence link at `:1072`); otherwise a plain `<span>`.
- The target `view-competency.php` resolves `templateid` from the *current plan* (`:57-61`) and the
  competency need not belong to the plan (`:50-55`) — so today the plan's cascade layer applies to a
  related competency that isn't in the plan.

### 2.4 Enrolment filtering primitive
- `calculator::filter_courses_by_enrollment()` (`calculator.php:344-362`): `all` returns unfiltered;
  otherwise `is_enrolled($ctx, $userid, '', $onlyactive)` with `$onlyactive = ($filtermode === 'active')`.

### 2.5 Routing (context only, unchanged here)
- The template `displaymode` (1 = Trilha, 2 = Panorama) is read by the companion **`block_dimensions`**
  (`dataset_provider.php:226-235`) to build plan vs competency cards. `local_dimensions` itself does
  not branch on `displaymode` at runtime.

## 3. Design

### 3.1 Unify `enrollmentfilter` across both views (Panorama gap)
- **WS `get_competency_courses`**: add a required `planid` parameter; resolve
  `$templateid = (int) api::read_plan($planid)->get('templateid')`; replace the
  `summaryenrollmentfilter` read with `helper::resolve_enrollmentfilter_for_view($competencyid, $templateid)`.
  Keep `validate_context` + `require_capability('local/dimensions:view', …)`.
- **`accordion.js` / `view-plan.php`**: pass `planid` to the WS; drop the
  `summaryenrollmentfilter`-gated method switch — always call the plugin WS (it now returns the
  full cascade result; when it resolves to `all` the WS simply doesn't filter).
- **Retire `summaryenrollmentfilter`**: remove the setting from `settings.php` and its lang strings.
  The global `enrollmentfilter` becomes the shared default for both views.
- **Migration (`db/upgrade.php`):** if `summaryenrollmentfilter` was set and differs from
  `enrollmentfilter`, the Panorama default changes. Decision: on upgrade, `unset_config` the old key
  (no silent value transfer); the global `enrollmentfilter` governs both. Note this in the upgrade
  step comment. *(Open confirm: transfer the old summary value into a plan-level default instead —
  rejected as over-engineering unless requested.)*

### 3.2 `singlecourseredirect`: accessibility gate + Trilha-only
- **New helper** `calculator::user_can_access_course(\stdClass $course, int $userid): bool` —
  `true` when the user is actively enrolled (`is_enrolled($ctx, $userid, '', true)`) **or** can
  self-enrol via an available `enrol_self` instance (`enrol_get_instances($courseid, true)` filtered
  to `enrol === 'self'`, `enrol_get_plugin('self')->can_self_enrol($instance, false) === true`).
  `can_self_enrol` already enforces status/dates/max-enrolled **and** the cohort restriction
  (`customint5`), so the synced-cohort scenario is covered without special-casing.
- **`view-competency.php`**: `$willredirect = $singlecourseredirect && count($courses) === 1 &&
  calculator::user_can_access_course($single, $USER->id)`. Drop the `=== ACTIVE` coupling. The
  FAB-skip logic (`:105-112`) keys off the same `$willredirect`.
- **Trilha-only in the form:** hide `singlecourseredirect` in the template form when
  `displaymode = Panorama` (see §3.5). `enrollmentfilter` stays visible in both modes.
- **Copy:** rewrite `singlecourseredirect_desc` (en + pt_br) to drop the "filtro = ativas"
  dependency and describe the accessibility behaviour.

### 3.3 Related-competency pills: new tab + skip the plan layer
- **`accordion.js:1807`**: add `target="_blank" rel="noopener"` to the related-pill anchor.
- **`view-competency.php`**: determine whether `$competencyid` belongs to `$plan` (e.g.
  `api::list_plan_competencies($planid)` membership, or a `competency_plancomp`/`competency_templatecomp`
  check). If it is **not** in the plan, resolve the cascade with `templateid = 0`
  (`$effectivetemplateid = $inplan ? $templateid : 0`) so both `resolve_enrollmentfilter_for_view`
  and `resolve_singlecourseredirect_for_view` collapse to **competency → global**, per the decision
  that a related competency's page is not governed by the originating plan.

### 3.4 Promote `showrelated` / `showrelatedlink` to per-plan (Panorama-only)
- **New lp customfields** `CFIELD_SHOWRELATED` = `local_dimensions_showrelated`,
  `CFIELD_SHOWRELATEDLINK` = `local_dimensions_showrelatedlink` — `select` with options
  `inherit | yes | no` (mirrors the `singlecourseredirect` field shape). Provisioned for
  **`AREA_LP` only** in `ensure_custom_fields_exist` (plan-wide toggles; no competency layer).
- **Constants + options:** `SHOWRELATED_INHERIT|YES|NO` (+ link variant, or a shared tri-state
  helper) and `showrelated_options()` / `showrelatedlink_options()` on `constants`.
- **Resolvers** (2-level, plan → global) in `helper`:
  `resolve_showrelated_for_template(int $templateid): bool` and
  `resolve_showrelatedlink_for_template(int $templateid): bool` — template CF if `!= inherit`, else
  `get_config('local_dimensions', 'showrelated'|'showrelatedlink')`.
- **`view-plan.php`**: replace the raw `get_config` reads (`:78-79`) with the resolvers, using the
  plan's `templateid` (`$plan->get('templateid')`).
- **Form:** show both fields only when `displaymode = Panorama` (§3.5).
- The global settings remain as the site default.

### 3.5 Template edit form: conditional visibility (symmetry)
In `template_dynamic_form` (after `lp_handler::instance_form_definition`, whose element names are
`customfield_<shortname>`), wire `hideIf` on the `customfield_local_dimensions_displaymode` select
(submitted value = 1-based option index, = the `DISPLAYMODE_*` constant by construction):
- `singlecourseredirect` → `hideIf(..., displaymode, 'eq', (string) DISPLAYMODE_PLAN)` (hide in Panorama).
- `showrelated`, `showrelatedlink` → `hideIf(..., displaymode, 'eq', (string) DISPLAYMODE_COMPETENCIES)` (hide in Trilha).
- `enrollmentfilter` → always visible.
`hideIf` keeps the hidden field's stored value (no data loss); place the calls in `definition()`
after the handler call. Do **not** replicate in `competency_dynamic_form` (no `displaymode` there).

### 3.6 Copy / guidance
- Distinct "inherit" wording per level via **form help text** (recommended, lighter than
  re-provisioning the field options): a help button / static note on the template and competency
  forms explaining the cascade — the competency's inherit option means "use the plan's setting, or
  global". *(Alternative, if preferred later: relabel the competency field's inherit option; heavier —
  requires an upgrade step to rewrite the field configdata.)*
- Correct the `constants.php:68,71` docblocks ("lp area only" → both areas).

## 4. Data model, services, versioning
- **New customfields:** `local_dimensions_showrelated`, `local_dimensions_showrelatedlink` (lp area,
  `select`). No new DB tables (consistent with the plugin — all data in `customfield_data`).
- **WS signature change:** `get_competency_courses` gains `planid`. Services install on upgrade →
  **`version.php` bump + `upgrade_plugin_savepoint`** required.
- **Removed config:** `summaryenrollmentfilter` (+ `unset_config` in upgrade).
- **Lang:** add `showrelated`/`showrelatedlink` field + option strings and any inherit-help strings
  in **both** `en` and `pt_br`, alphabetically; remove `summaryenrollmentfilter*`; reword
  `singlecourseredirect_desc`.

## 5. Testing
- **PHPUnit `helper_cascade_test.php`** (the outstanding Part-C test): assert
  `resolve_enrollmentfilter_for_view` / `resolve_singlecourseredirect_for_view` for
  competency>plan>global, and the `templateid = 0` skip-plan path (competency → global). Add
  `resolve_showrelated_for_template` / `resolve_showrelatedlink_for_template` (plan → global).
- **Redirect accessibility:** a test creating a course with an active enrolment vs a self-enrol
  instance (with/without cohort restriction) asserting `user_can_access_course`.
- Behat stays thin / CI-only (the `hideIf` toggles are JS; cover logic in PHPUnit).

## 6. Out of scope / follow-ups
- The related-pill same-tab vs new-tab change assumes the Return-to-Plan FAB flow still works from
  the new tab (it does — the FAB reads the stored return context).
- IDOR-adjacent surface on `view-competency.php` (competency need not be in the plan) is pre-existing
  and documented; not changed here.
