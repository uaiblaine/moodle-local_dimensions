# Learning Plan Template CSV Transfer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** give the Learning plans hub tab a CSV export and a CSV import whose import path shows the operator the projected result — per template, per competency link — before writing anything, and then writes only what was ticked.

**Architecture:** a serializer (format), an analyser (pure projection, zero writes), an immutable plan value object (the projection), an importer (the only write path), three web services, one upload-only dynamic form, two modals, two audit events.

**Tech Stack:** Moodle 4.5-5.2, `core_competency` API, `core_customfield` handler API, `csv_import_reader`, `core_form\dynamic_form`, `core/modal_save_cancel`, plain AMD (ESM), Mustache.

**Spec:** `docs/superpowers/specs/2026-07-27-learning-plan-template-csv-transfer-design.md`

**Kit:** `docs/design-kit/maps/pln-plans.md`, `docs/design-kit/maps/mod-forms.md`

## Status at handoff (2026-07-27)

Task 1's **server half is landed**: the serializer, the helper extensions, the export web service,
its registration, the `version.php` bump, four lang strings and both test files. Nothing is
reachable from the UI yet, so the plugin behaves exactly as before this work.

Task 1's **front-end half is not started**, and was deliberately stopped rather than half-built:
the machine it was written on had no `node_modules` in the Moodle checkout and no mirror of this
plugin at `<moodle>/public/local/dimensions`, so `npx grunt amd --root=public/local/dimensions`,
eslint and stylelint could none of them run — and shipping an `amd/src` change without its
rebuilt `amd/build` output violates this repo's own rule. A toolbar button with no working
handler would also be a dead control. To resume, put the plugin clone at
`<moodle>/public/local/dimensions` as a **real directory** (grunt does not follow a symlink) and
run `npm install` in the Moodle root.

Also unwritten for the same reason: the design-kit map rows and the README bullet. Both document
**shipped** regions with line references, and writing them before the UI exists would put a known
falsehood into a kit the spec already criticises for staleness. Write them in task 8, from the
real markup.

## Global Constraints

- This checkout cannot run PHPUnit or Behat. Every "run the test" step means CI on the next push — write the test first anyway, and treat its expected assertions as the contract.
- There is no `php` binary on this machine either, so not even `php -l` runs. Syntax and phpcs/phpdoc conformance are eyeballed at write time against the rules below, and the two greps in the last constraint are the only mechanical PHP checks available.
- The two locally runnable lints, from the Moodle root (`/Users/uaiblaine/dev/moodle` or the dev checkout in use): `npx eslint --max-warnings 0 public/local/dimensions/amd/src` and `npx stylelint --config .stylelintrc public/local/dimensions/styles.css`. The plugin ships no `.stylelintrc.json` or `package.json` today, so core's config is the only one available — and it is the one CI runs.
- Every `amd/src` commit ships its rebuilt `amd/build` `.min.js` + `.map` from `npx grunt amd --root=public/local/dimensions`.
- CSS hard errors: no `!important` anywhere including `@keyframes`; no `clamp()`, `min()` or `max()` in any length-valued property (`calc()` and grid `minmax()` are fine); no transition or animation under 100ms.
- PHP style traps: hard max 180 characters, soft max 132 (the warning count fails `phpdoc --max-warnings 0`); inline `//` comments start with a capital and end with punctuation, lower-case or multi-line commentary goes in a `/* … */` block; variables are lower-case only; `@param` array types are the plain word `array` with the shape in the prose; typed properties still need `@var`; omit `defined('MOODLE_INTERNAL') || die();` from pure namespaced single-class files.
- `execute_returns()` is an allowlist — `clean_returnvalue` silently strips undeclared keys. Assert every web-service payload through it.
- Supported branches are 4.5 through 5.2. Do not reach for a core API newer than 2024100700, and never name `customfield_data.component` / `.area` / `.itemid` in SQL.
- Never write a bare to-do or merge-conflict marker in any file, docs included — the CI leftover checker scans everything.
- Both lang files stay in sync and alphabetically sorted, with identical key counts (625 today).
- Do not push. The user drives pushes.

---

### Task 1: Serializer and export

The export path is shippable on its own: a CSV comes out of the Plans tab and nothing can yet be imported, so requirement 6 is trivially satisfied for the whole task.

**Files:**
- Create: `classes/local/template_csv_serializer.php`
- Modify: `classes/helper.php` (add `export_template_customfields()`, `template_customfields_to_formdata()`; add an `$area` parameter defaulting to `AREA_COMPETENCY` to `get_competency_select_raw:1275`, `read_competency_cf_data:1417`, `select_label_to_index:1494`; make `normalise_hex_color:2558` and `select_raw_options:1529` public)
- Create: `classes/external/export_templates.php`
- Modify: `db/services.php`, `version.php`, `classes/output/dynamictabs/plans.php`, `templates/central/plans.mustache`, `amd/src/central/plans.js`, `amd/src/central/frameworks.js`
- Create: `templates/central/plans_export.mustache`, `amd/src/central/download.js`, `amd/src/central/plans_transfer.js`
- Test: `tests/local/template_csv_serializer_test.php` (create), `tests/external/export_templates_test.php` (create)

**Interfaces:**
- Produces: `template_csv_serializer::HEADERS_CORE` (13 tokens), `::HEADERS_CF` (14 tokens), `::headers(bool $includescss): array`, `::export_templates(array $templateids, bool $includescss): array{filename, content, frameworks}`, `::parse(string $text, string $encoding, string $delimiter): array{templates, links, error, legacy}`. Tasks 3 and 5 consume `parse()`; task 4 consumes nothing from here.
- Produces: `helper::export_template_customfields(int $templateid): array` keyed by the `cf_*` tokens; `helper::template_customfields_to_formdata(array $cfrow): array`. Task 5 consumes the second.
- Consumes: the existing `local_dimensions_export_framework` web service, unchanged, for the companion structure downloads.

- [x] **Step 1: Write the failing serializer test.** Fixture built programmatically as the framework CSV tests do — no `tests/fixtures/` directory exists. Assert: a full round trip through `parse()` of a template carrying every custom field; a template linking competencies from two different frameworks; a competency-less template exporting one `template` row and no `link` rows; `headers(false)` dropping only `cf_customscss`; a `link` row whose parent key matches nothing surviving `parse()` for the analyser to mark `orphanlink`; the five-column `tool_lptmanager` shim; and a 14-column framework CSV refused with the Structures-tab error rather than parsed. Landed as `tests/local/template_csv_serializer_test.php`, 14 cases, also covering the UTC duedate round trip, the absent-versus-empty column contract, the category `sourcecontext`, and an over-long shortname surviving the parse untruncated.
- [x] **Step 2: Run the test to verify it fails.** Not runnable here — verify in CI.
- [x] **Step 3: Write the serializer.** Header row from `headers()`; `encode_row()` copied from `framework_csv_serializer.php:165`, never `csv_export_writer`. Export reads real stored values only, keeping the `(int) $data->get('id') > 0` guard, and never the `helper::get_template_*()` resolvers, which collapse `inherit` into the site default. `duedate` serialised `YYYY-MM-DD` (or `YYYY-MM-DD HH:MM`) in UTC. `sourcecontext` carries the source category name and idnumber, descriptive only. `parse()` reads by header name; an absent column means untouched, a present-but-empty cell means clear; `shortname` is `clean_param(…, PARAM_TEXT)` with no `shorten_text()`, so task 3 can report `shortnametoolong`.
- [x] **Step 4: Extend the helper.** Thread `$area` through the three hardcoded readers with `AREA_COMPETENCY` as the default so `framework_csv_serializer` is untouched; add the two template custom-field functions covering the 4 lp-only tokens plus the 10 shared ones. Landed: `read_competency_cf_data`, `read_competency_text_cf`, `read_competency_select_label`, `read_competency_select_key`, `select_label_to_index` and `customfields_to_formdata` all gained a trailing `$area` defaulting to `AREA_COMPETENCY`, so every existing caller is unchanged; `export_template_customfields()` and `template_customfields_to_formdata()` added; `normalise_hex_color()` and `select_raw_options()` made public for task 3. Note `display_mode_options()` is keyed by int, so its keys are cast with `strval` before the strict option search.
- [x] **Step 5: Write the export web service.** `validate_context()` **once** on the resolved target context, then `require_capability('moodle/competency:templateview', $template->get_context())` per template — not `validate_context()` per template, which changes `$PAGE`'s context and emits `debugging()` from `CONTEXT_COURSECAT`, failing PHPUnit under `--fail-on-warning`. Raise the time limit and memory. Declare `frameworks[]` in `execute_returns()`.
- [x] **Step 6: Wire the tab.** Add `templatecount` and `canexport` to `dynamictabs/plans.php`; stop `s()`-escaping `idnumber` at `plans.php:171` (the Mustache tag escapes it again); add the toolbar strip after the `showhiddentoggle` close; label the button **Export templates**, never "Export", because the hidden Frameworks pane holds a button labelled exactly that, earlier in document order.
- [x] **Step 7: Write the front end.** Lift `triggerDownload`/`makeSpinner` into `download.js` with no ARIA change — the defect the kit records at `fwk-structures.md:98-102` does not exist at HEAD — and give the export loader span an accessible name while dropping its `d-inline-flex`, which beats `[hidden]` on Bootstrap 4.
- [x] **Step 8: Write the export web-service test.** Through `clean_returnvalue()`, plus a `required_capability_exception` case, plus a two-different-category export asserting no unexpected `debugging()`. Landed as `tests/external/export_templates_test.php`, 4 cases. `moodle/competency:templateview` is manager-only in core, so the plain-user case is a genuine capability failure.
- [x] **Step 9: Lint, build and check the PHP style traps.** `awk 'length($0)>132{print FILENAME":"FNR" ("length($0)")"}'` and `grep -nE '^\s*// [a-z]'` over every changed PHP file; eslint; stylelint; grunt.
- [x] **Step 10: Commit.** `feat(hub): export learning plan templates as CSV with their custom fields`

---

### Task 2: The verdict enum and the plan value object

Pure scaffolding — nothing consumes it yet, so it ships without behaviour change.

**Files:**
- Create: `classes/local/template_import_verdict.php`, `classes/local/template_import_plan.php`
- Modify: `lang/en/local_dimensions.php`, `lang/pt_br/local_dimensions.php`
- Test: `tests/local/template_import_plan_test.php` (create)

**Interfaces:**
- Produces: the verdict constants (`create`, `update`, `insync`, `skip`, `conflict`, `blocked`, `orphanlink`), the reason constants, the link-status constants, the confidence constants, the remedy constants (`clearduedate`, `shiftduedate`, `keepduedate`, `truncate`, `partial`, `adopt`, `createhere`), the outcome constants (`created`, `updated`, `skipped`, `changed`, `gone`, `failed`), and one `match()`-based label method per set. Tasks 3, 4 and 5 all consume these.
- Produces: `template_import_plan` with `get_items()`, `get_item(string $itemkey)`, `get_counts()`, `get_notices()`, `get_missing_structures()`, `get_target_context()`.

- [ ] **Step 1: Write the failing test.** Every enum constant resolves to a non-empty label on both languages; `get_counts()` declares exactly the scalar set task 4's `execute_returns()` will declare.
- [ ] **Step 2: Run the test to verify it fails.** Not runnable here — verify in CI.
- [ ] **Step 3: Write both classes.** Every label a literal `match()` returning a fixed `get_string` key — the string checker cannot verify a constructed id, so no `get_string('reason_' . $x)`.
- [ ] **Step 4: Add every lang key to both files in one alphabetical pass.** Include `central_plans_import_reason_shortnametaken`, and replace the two existing `get_string('shortnametaken', 'tool_lp')` calls in `classes/form/template_dynamic_form.php:344` and `classes/form/framework_dynamic_form.php:290` — that string does not exist in `tool_lp` and renders `[[shortnametaken]]` plus a missing-string `debugging()`.
- [ ] **Step 5: Verify the lang files.** Identical key counts, both alphabetically ordered, every new key resolving.
- [ ] **Step 6: Commit.** `feat(hub): add the learning plan import verdict model and its strings`

---

### Task 3: The analyser, with the zero-writes proof

The architectural centre. Shippable because it is pure analysis with no caller.

**Files:**
- Create: `classes/local/template_import_analyser.php`
- Modify: `classes/helper.php` (add `count_open_plans_by_template()`)
- Test: `tests/local/template_import_analyser_test.php` (create)

**Interfaces:**
- Consumes: `template_csv_serializer::parse()` output, `template_import_verdict::*`, `template_import_plan`.
- Produces: `analyse(): template_import_plan`, and the per-item fingerprint contract task 5 re-checks.

- [ ] **Step 1: Write the failing test, starting with the zero-writes proof.** Snapshot the row counts of `competency_template`, `competency_templatecomp`, `competency_templatecohort`, `competency_plan`, `customfield_data`, `customfield_field`, `customfield_category` and `files`, plus the full `competency_template` row set; run `analyse()` over a fixture exercising every verdict; assert every count and every row unchanged. `customfield_field` and `customfield_category` are in the snapshot because provisioning was deliberately removed from the analyser — the fixture asserts the fields already exist as a precondition. Then one test per verdict and per link status, including: no structure at all; a competency moved to another framework reported as a hint; a duplicate shortname offering `adopt`; an idnumber match in another context offering `createhere`; a past duedate with `clearduedate` pre-selected; a zero-link template **not** blocked; an empty `template_idnumber` skipping tier 1.
- [ ] **Step 2: Run the test to verify it fails.** Not runnable here — verify in CI.
- [ ] **Step 3: Write the identity cascade.** The `customfield_data` → `customfield_field` → `customfield_category` join on `component` + `area`, with `$DB->sql_equal('d.charvalue', ':v', true, true)`; the same query without the context filter for `contextmismatch`; then `template::get_records(['shortname', 'contextid'])` — plural, because `get_record()` with `IGNORE_MISSING` silently returns the first of N and emits a `debugging()` notice. Do not write a test asserting `dml_multiple_records`.
- [ ] **Step 4: Write the framework and competency cascades.** Framework: exact idnumber (skipped for an empty cell), then exact shortname over the **union** of `self`, `parents` and `children` — `'children'` alone cannot see a system framework from a category. Wrap `api::list_frameworks()`, which throws `required_capability_exception` when no context is readable, and report `frameworkunreadable`. Competency: prefetch per framework, then exact idnumber, then exact shortname within the same framework. No cross-framework fallback — report it as a hint only.
- [ ] **Step 5: Write the validity oracle and the field diff.** Create: `new template(0, $record)` then `get_errors()`. Update: `new template($id)` then `from_record()` then `get_errors()`. Normalise `description` with `clean_param(…, PARAM_CLEANHTML)` on **both** sides, compare `cf_*` selects as resolved indexes, and compare `duedate` as the UTC-reconstructed integer.
- [ ] **Step 6: Write the pre-checks, the roll-up and the blast radius.** `templatemanage` in the target context; `templatemanage` at the system context too, or resolve `lp_handler::create()->get_editable_fields(0)` and report `fieldnotwritable` naming the dropped columns (task 6 fixes the cause); framework visibility, because `add_competency_to_template()` throws `coding_exception` for a hidden framework; field provisioning; hex colours via the now-public `normalise_hex_color()`; SCSS via `scss_manager::validate_scss(string)` — **not** `helper::validate_customscss()`, which takes submitted form data; `core_text::strlen()` on shortname and on every `cf_*` text value. Roll-up only when `linksmatched === 0 && linksunresolved > 0`. Add `helper::count_open_plans_by_template()` (`status != STATUS_COMPLETE`), since the shipped counter counts all statuses.
- [ ] **Step 7: Write the fingerprints and the notices.** sha1 over verdict, reason, matched template id, field diff, ordered resolved competency ids and existing-link id set. Item keys `t<n>` / `t<n>l<m>`.
- [ ] **Step 8: Prove the class holds no writes.** `grep -nE 'insert_record|update_record|delete_records|api::create_|api::update_|api::add_|api::remove_|->create\(\)|->update\(\)|ensure_custom_fields_exist' classes/local/template_import_analyser.php` returns nothing.
- [ ] **Step 9: Check the PHP style traps and commit.** `feat(hub): project a learning plan CSV import without writing anything`

---

### Task 4: The preview web service, renderable and template

Shippable: the projection is reachable and renderable, and there is still no apply path.

**Files:**
- Create: `classes/form/import_templates_dynamic_form.php`, `classes/output/central/template_import_preview.php`, `classes/external/preview_import_templates.php`, `templates/central/plans_import_preview.mustache`, `templates/central/plans_import_row.mustache`
- Modify: `db/services.php`, `version.php`, `amd/src/central/plans_transfer.js`, `amd/src/central/plans.js`, `templates/central/plans.mustache`, `styles.css`, both lang files
- Test: `tests/external/preview_import_templates_test.php` (create)

**Interfaces:**
- Consumes: `template_import_analyser`, `template_import_plan`, `template_import_verdict`.
- Produces: `local_dimensions_preview_import_templates` returning `{html PARAM_RAW, counts, missingframeworks[], canapply PARAM_BOOL}`; the `<tr data-itemkey data-fingerprint data-verdict>` contract task 5's JS reads back.

- [ ] **Step 1: Write the failing web-service test.** Through `clean_returnvalue()`; a `required_capability_exception` case; a bogus `contextid` returning a readable error rather than an unhandled `moodle_exception`; and an assertion that the preview wrote nothing.
- [ ] **Step 2: Run the test to verify it fails.** Not runnable here — verify in CI.
- [ ] **Step 3: Write the upload-only dynamic form.** `process_dynamic_submission()` returns only the draft handle and the parse settings and contains no write of any kind. `validation()` blocks an empty or unparseable file, carrying `csv_import_reader::get_error()` rather than a generic string, and a file with no `template` row. Label the file element with a new `central_plans_import_file` key whose English value matches the Frameworks one ("CSV file").
- [ ] **Step 4: Write the renderable and the templates.** `classes/output/central/` (where hub renderables live) implementing `export_for_template(renderer_base $output)`; the plugin has no `renderer.php`. The body template holds the summary strip, the "nothing has been written yet" line, the missing-structures alert, the file notices, one collapsible group per verdict with `blocked` and `conflict` expanded, and the row partial; the row partial is separate so task 5 can repaint a single row. Both need a non-empty `Example context (json):` block, and no `{{…}}` tag inside the docblock — comments do not nest and close at the first `}}`.
- [ ] **Step 5: Write the preview web service.** Read type; `context::instance_by_id()` in try/catch; raise the time limit and memory; render at most 500 rows and state how many were not shown; declare every count as a flat scalar.
- [ ] **Step 6: Wire the two-step modal.** Open the preview `ModalSaveCancel` on `ModalEvents.hidden` after `preventDefault()`, not inside the submit handler, so it does not race Bootstrap's `hidden.bs.modal` cleanup. Add the toast region to the modal body on `ModalEvents.shown`. Attach `modal_refresh` as the re-check button. Keep Apply disabled with an in-body `alert-danger` when nothing is ticked. Label the toolbar button **Import templates**.
- [ ] **Step 7: Add the styles.** `.local-dimensions-tplimport-*`; no `!important`, no `clamp()`/`min()`/`max()` in a length property, nothing under 100ms.
- [ ] **Step 8: Lint, build, check the PHP style traps and commit.** `feat(hub): preview a learning plan CSV import before anything is written`

---

### Task 5: The importer and the apply web service

The write path lands last, on top of a preview that already works.

**Files:**
- Create: `classes/local/template_csv_importer.php`, `classes/event/template_imported.php`, `classes/event/templates_imported.php`, `classes/external/apply_import_templates.php`
- Modify: `db/services.php`, `version.php`, `amd/src/central/plans_transfer.js`, both lang files
- Test: `tests/local/template_csv_importer_test.php` (create), `tests/external/apply_import_templates_test.php` (create)

**Interfaces:**
- Consumes: the draft itemid, `template_import_analyser`, the fingerprint contract from task 3.
- Produces: `apply(array $selections): array` of per-item outcomes plus roll-up counts; `local_dimensions_apply_import_templates`.

- [ ] **Step 1: Write the failing tests.** Only the ticked items are written; a DB mutated between preview and apply yields `changed` with nothing written; a per-item failure does not abort the run and leaves no open transaction; link order survives an update that keeps extra links; custom fields land through the handler and `template_customfields_updated` fires for every template whose values actually changed (`instance_change_logger` returns early when nothing changed, so seed the fixture accordingly); `template_idnumber` is back-filled on create, making a second apply an update rather than a duplicate.
- [ ] **Step 2: Run the tests to verify they fail.** Not runnable here — verify in CI.
- [ ] **Step 3: Write the re-validation gate.** Re-read the caller's own draft area, re-parse and re-analyse **once** per call — not per selection. A missing draft gets its own file-level message. Then per selection: absent → `gone`; verdict or fingerprint moved → `changed`, nothing written, fresh item returned for the repaint; not selectable → `skipped`; a remedy the fresh item does not offer → `changed`. Re-run the roll-up on the selection so deselecting every link of a `create` row cannot produce an outcome the preview never projected.
- [ ] **Step 4: Write the per-template write sequence.** Own `start_delegated_transaction()`; the six-field record with `contextid` omitted on update; `api::create_template()` / `update_template()`; custom fields through `lp_handler::create()->instance_form_save()` with the analyser's real `$isnew` probe — never `instance_form_save_with_image()`, which hardcodes `$isnew = true` and wraps the call in a retry that cannot recover inside a poisoned PostgreSQL transaction; the `template_idnumber` back-fill folded into the same formdata; links in file order, counting `add_competency_to_template()`'s `false` return as `alreadylinked` and never feeding it to an event trigger's `->get()`; then renumber the **whole** final set through the persistent, guarding the lookup because `template_competency::get_record()` returns literal `false` and `->set()` on `false` raises an `\Error`.
- [ ] **Step 5: Write the failure handling.** `catch (\Throwable)`, guarded `rollback()`, `$DB->force_transaction_rollback()` on any leak, continue. Catching only `moodle_exception` would let an `\Error` leave the transaction open and `force_rollback` stuck true, turning every later write in the request into `dml_transaction_exception`.
- [ ] **Step 6: Invalidate the caches after each commit, never inside it.** `template_metadata_cache::invalidate_template()`; `scss_manager::invalidate_cache($id, helper::AREA_LP)` when `enablecustomscss` is on — mandatory, because `template_scss` has no TTL and caches the empty string on a miss, so one learner render between "created" and "SCSS written" would poison `css_<id>` permanently; and the template-courses invalidation when links changed. `db/events.php` is observers-only and registers no plugin event, so nothing else invalidates for us.
- [ ] **Step 7: Write the two events and the apply web service.** Both events triggered after `allow_commit()`, mirroring `template_duplicated.php`, `get_objectid_mapping()` returning `NOT_MAPPED`, no `db/events.php` entry. The write service declares the per-item `html` repaint using the row partial from task 4.
- [ ] **Step 8: Wire the selection read-back and the result rendering.** Read `data-itemkey` / `data-fingerprint` / `data-verdict` off each row; render in-place outcome pills; flash each changed row with `el.animate([...], {duration: 1500})`; reload the pane on success.
- [ ] **Step 9: Lint, build, check the PHP style traps and commit.** `feat(hub): apply a learning plan CSV import partially, re-validating at write time`

---

### Task 6: The custom-field capability fix and the export completion

**Files:**
- Modify: `classes/customfield/lp_handler.php`, `classes/local/template_import_analyser.php`, `templates/central/plans_export.mustache`, `classes/external/export_templates.php`, `amd/src/central/plans_transfer.js`, `classes/local/template_csv_importer.php`
- Test: `tests/customfield/lp_handler_test.php` (create or extend)

**Interfaces:**
- Consumes: the `fieldnotwritable` reason from task 3.
- Produces: `can_edit()` resolving at the instance's own context; the per-value remap selects.

- [ ] **Step 1: Write the failing capability test.** A manager holding `templatemanage` only in a course category can write the custom fields of a template in that category, and still cannot write `cf_customscss` without `local/dimensions:editcustomscss`.
- [ ] **Step 2: Fix `can_edit()`.** Resolve `templatemanage` at the template's own context when `$instanceid` is given, falling back to the system context for the field-configuration screens. Keep `editcustomscss` system-scoped — it gates RISK_XSS content site-wide. Verify `get_instance_context()` returns the template's own context, or a category template's `customfield_data.contextid` is wrong on a fresh insert.
- [ ] **Step 3: Complete the export modal.** The "Referenced structures" list downloading each distinct framework through the existing `local_dimensions_export_framework` service, plus the lossiness notice (images, cohorts, cohort-role rules and embedded files are not carried) — the import preview already says this, the export side did not.
- [ ] **Step 4: Add the per-value remap selects.** For every `tag1` / `tag2` / `type` label matching no option on the target field, populated from the target's option list plus an explicit "clear this value" entry, honoured in `template_customfields_to_formdata()` so a label never silently lands on index 0.
- [ ] **Step 5: Lint, build, check the PHP style traps and commit.** `fix(customfield): resolve learning plan custom-field editing at the template's own context`

---

### Task 7: Behat smoke and the existing features

**Files:**
- Modify: `tests/behat/manage_plans.feature`
- Check and fix if a label moved: `tests/behat/central_restore.feature` (asserts "New template" twice), `tests/behat/manage_enrol_methods.feature`, `tests/behat/search_plans_by_competency.feature`, `tests/behat/manage_template_competencies.feature`, `tests/behat/manage_template_cohorts.feature`, `tests/behat/manage_template_participants.feature`

- [ ] **Step 1: Add one thin scenario.** Both toolbar buttons present, each modal opens matched **by title**, one field label asserted inside the dialogue, Cancel. No upload, no preview interaction — Behat is CI-only here, so budget one fix-and-repush.
- [ ] **Step 2: Grep `tests/behat/` for every label the toolbar touched** and fix each affected scenario in the same commit. "New template" stays exactly where it is, so no existing step that clicks it moves.
- [ ] **Step 3: Commit.** `test(hub): smoke the learning plan CSV transfer modals`

---

### Task 8: Docs and design kit

**Files:**
- Modify: `docs/design-kit/maps/pln-plans.md`, `docs/design-kit/maps/mod-forms.md`, `docs/design-kit/screens/pln-plans.html`, `README.md`, `CHANGELOG.md`

- [ ] **Step 1: Add the kit rows.** `PLN-IMPORT` / `PLN-EXPORT` and the `PLN-IMP-*` / `PLN-EXP-*` regions; a `FORM-TPLIMP-*` section beside `FORM-IMP` publishing `-FILE`, `-DELIM`, `-ENCODING`, `-UPDATE`, `-CONTEXTID`.
- [ ] **Step 2: Re-derive every line reference rather than trusting the kit.** `pln-plans.md:16` claims `plans.js` is 871 lines; HEAD is 860. `fwk-structures.md:99` points at the wrong lines for `makeSpinner`.
- [ ] **Step 3: Draw both modals in the screens file.**
- [ ] **Step 4: Update `README.md` line 59** (the Learning plans bullet) and add one `CHANGELOG.md` entry under `## [Unreleased]` / `### Added`.
- [ ] **Step 5: Commit.** `docs(kit): map the learning plan CSV transfer surfaces`

---

## Verification before handing back

- [ ] `npx eslint --max-warnings 0 public/local/dimensions/amd/src` clean.
- [ ] `npx stylelint --config .stylelintrc public/local/dimensions/styles.css` clean of the three hard errors.
- [ ] `git status` shows a rebuilt `amd/build` file beside every `amd/src` change.
- [ ] `version.php` reads the expected bumped number, and every task that added a web service bumped it.
- [ ] `grep -rnE 'insert_record|update_record|delete_records|api::(create|update|add|remove)_|ensure_custom_fields_exist' classes/local/template_import_analyser.php` returns nothing.
- [ ] `grep -rn "shortnametaken', 'tool_lp'" classes/` returns nothing.
- [ ] Both lang files carry every new key in alphabetical order with identical key counts.
- [ ] `awk 'length($0)>132{print FILENAME":"FNR" ("length($0)")"}'` and `grep -nE '^\s*// [a-z]'` clean over every changed PHP file.
- [ ] No bare to-do or merge-conflict marker anywhere, docs included.
- [ ] The PHPUnit and Behat suites are green **in CI** — they cannot run here.

## Runtime check on the test site

```sh
ver=$(grep -oE '\$plugin->version[[:space:]]*=[[:space:]]*[0-9]+' \
  /Users/uaiblaine/dev/moodle-local_dimensions/version.php | grep -oE '[0-9]+')
sha=$(git -C /Users/uaiblaine/dev/moodle-local_dimensions rev-parse --short HEAD)
git -C /Users/uaiblaine/dev/moodle-local_dimensions archive \
  --format=zip --prefix=dimensions/ HEAD -o ~/Downloads/dimensions-$ver-$sha.zip
```

What to look at, in order:

1. Plans tab → **Export templates** → pick two templates → the CSV downloads, and the "Referenced structures" list offers each framework.
2. Open the CSV in a spreadsheet: one `template` row per template, `link` rows beneath it in order, the duedate readable as a date, every custom field in its own column.
3. On a **clean** site with no frameworks → **Import templates** → upload that file → every template reads `blocked / structure missing`, the top alert names the frameworks to import first, and Apply is disabled.
4. Import the frameworks through the Structures tab in another tab, then press **Re-check** in the still-open preview → every row turns to `create`.
5. Untick one template and some links of another → Apply → only the ticked items are written, and the outcome pills match.
6. Re-upload the same file → every template reads `in sync` (not a duplicate), proving the `template_idnumber` back-fill.
7. Edit one description in the CSV and re-upload with **update existing** off → the row reads `skip` **and still shows the diff**; tick it alone → Apply → only that template changes.
8. Set a past duedate in the CSV → the row is `blocked` with **Clear the date** pre-selected, and the other two remedies available.
9. Delete a framework in another tab, then Apply the still-open preview → the affected rows come back as `changed`, repainted, with nothing written.
