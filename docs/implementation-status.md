# Implementation status — Competency hub (central.php)

> Snapshot of where the admin rebuild stands. Companion to `admin-redesign.md` (IA + field
> catalog) and `admin-redesign-codeplan.md` (roadmap). Updated 2026-06-27.

## What this is
Rebuild of the `local_dimensions` admin as a **single-surface "Competency hub"**
(`public/local/dimensions/central.php`) using `core/dynamic_tabs` + `core_form\dynamic_form`
modals. **Additive**: runs alongside the legacy `manage_competencies.php` / `manage_templates.php`
without touching them. Registered as admin external page `local_dimensions_central` in `settings.php`.

Patterns: dynamic-tabs base (`\core\output\dynamic_tabs\base`) + `core_form/modalform` + tab refresh
via `core_dynamic_tabs_get_content` (`getContent`). Reuses existing Dimensions external functions
(`local_dimensions_{create,update,read}_competency`) + `competency_handler` + `customfields_io`, and
core competency web services (`core_competency_delete_competency`, `_move_up/_move_down_competency`,
`_set_parent_competency`, `_update_competency`) and `tool_lp/competencyruleconfig`.

## Done (code on disk, all `php -l` clean)
- **Companion plugin `public/local/modfields/`** — installable MVP scaffold (native core_customfield
  for activity modules; see `docs/companion-modfields-proposal.md`).
- **Shared context selector (page-level)** — `classes/output/central/contextbar.php`,
  `templates/central/contextbar.mustache`, `amd/src/central/context.js`. Rendered once in `central.php`
  above the tabs and governs **both** tabs: toggling System / Course category (or picking a category)
  pushes the context onto every tab pane and reloads the active one. **Adaptive counter** (no round-trip):
  each option carries `frameworkcount`+`templatecount` and the bar carries the system totals; JS swaps
  the count/noun on `shown.bs.tab` (Structure→frameworks, Plans→plans). Resolution centralised in
  `helper::resolve_central_context()` / `central_category_options()` / `can_read_competency_context()`.
  **Architecture rule:** the pane `dataset` is the single source of truth for getContent args — controls
  write to `pane.dataset.*` then call `reloadPane()` (shared `amd/src/central/tabs.js`).
- **Structure tab** — `classes/output/dynamictabs/structure.php`, `templates/central/structure.mustache`
  + `structure_node.mustache`, `amd/src/central/structure.js`, form `classes/form/competency_dynamic_form.php`:
  - Framework switcher (the context switch moved up to the page-level selector above).
  - **Lazy-render tree**: renders only roots; expands children client-side from the in-memory model
    (the full model is kept for the rule editor).
  - **Full CRUD in modal, no reload**: add (root/child), edit, **relocate** (parent select →
    `api::set_parent_competency`), **rules** (`tool_lp/competencyruleconfig`), move up/down, delete.
  - **Scale config** (`tool_lp/scaleconfig`) wired in the modal — needed two fixes: (1) `dynamic_form`
    sets `data-random-ids`, so the `scaleid` select / button must carry **explicit ids**
    (`id_scaleid_central`, `id_scaleconfigbutton_central`) or scaleconfig's hard-coded selectors bind to
    nothing; (2) request the `js_call_amd` in `definition_after_data()` (during render, inside the WS's
    JS-collection window), not `definition()` (constructor, before collection — lost). The legacy
    `edit_competency.php` page works because regular forms don't randomise ids.
- **Plans tab** — `classes/output/dynamictabs/plans.php`, `templates/central/plans.mustache`,
  `amd/src/central/plans.js`: template list (competency count + visibility) + detail showing the plan's
  competencies **cross-framework** (framework-origin tag). **Context-aware** (resolves the shared context;
  lists templates in it; shows the guided "select a category" state). Search and CRUD are the two
  bullets below.
- **Plans: search / filter by competency** — new read WS `local_dimensions_search_competencies`
  (paginated, cross-framework, shortname+idnumber match; registered in `db/services.php`); AMD datasource
  `amd/src/central/competency_datasource.js` powers a `core/form-autocomplete` field; selecting a
  competency writes `competencyid` to the pane dataset and reloads via `core_dynamic_tabs_get_content`;
  `plans.php` intersects the template list with `api::list_templates_using_competency`; active-filter
  chip + filter-aware empty state in the Mustache template. `search_competencies` will be reused by the
  future cross-framework competency picker (slice 1 below). Behat regression:
  `tests/behat/search_plans_by_competency.feature`.
- **Plans: CRUD** — `template_dynamic_form` modal reusing `api::{create,update}_template` + `lp_handler`
  (lp custom fields: displaymode, subline_source, template_idnumber + appearance/colors/drag-drop images);
  delete via `core_competency_delete_template` with the unlink/delete radio dialog
  (`delete_template_plans.mustache`) for templates that have plans; New/Edit/Delete buttons on the Plans
  tab (`plans.mustache` + `amd/src/central/plans.js`). Behat regression:
  `tests/behat/manage_plans.feature`.

## Next slices (suggested order)
1. Plans: **cross-framework competency picker** (add/remove `template_competency`) + **cohort/users
   assignment + sync** modal (mtube group-management style, `template_cohort`).
2. Competency → course/activity links in a modal with `ruleoutcome` (codeplan phase 2).
3. TODOs: lazy-**fetch** for huge frameworks; strict visibility in the linked-course count; SCSS parity in
   the modal; broader Behat/PHPUnit for the hub (the scale-dialogue regression exists:
   `tests/behat/configure_scale.feature`); a11y; make rule save refresh instead of reload.

## Reminders
- After any `amd/src/**` change: `npx grunt amd --root=public/local/dimensions`.
- Code in **English** (names/identifiers/comments); translations only in `lang/pt_br`.
- Reuse existing external functions / core WS — don't invent new ones where core already provides them.
