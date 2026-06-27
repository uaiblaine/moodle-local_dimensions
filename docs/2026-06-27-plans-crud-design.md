# Design — Learning plan (template) CRUD in the Competency hub

> Spec for the next slice of the Competency hub rebuild. Companion to
> [`admin-redesign.md`](admin-redesign.md) (§9.2 plan form) and
> [`admin-redesign-codeplan.md`](admin-redesign-codeplan.md). Status:
> [`implementation-status.md`](implementation-status.md). Date: 2026-06-27.

## Goal

Add create / edit / delete of learning plan templates to the **Plans** tab, in a modal, with no
page reload — mirroring the already-shipped competency modal (`competency_dynamic_form`). This closes
the "no New/Edit buttons for plans" gap.

## Non-goals

- The cross-framework competency picker (add/remove `template_competency`) and cohort assignment —
  separate slice.
- Reworking the legacy `manage_templates.php` / `edit_template.php` (additive; left untouched).

## Approach

A new `\core_form\dynamic_form` (`template_dynamic_form`) opened by `core_form/modalform`, with **full
field parity** with the legacy `template_form.php` (basic info + publication + lp custom fields). It
mirrors `competency_dynamic_form` — which already proves the lp/competency custom fields (colour
pickers, picture draft-areas, SCSS) work inside the modal. Templates have no scale, so the
`scaleconfig` / `data-random-ids` issue from the competency modal does **not** apply here.

**No new web service.** Create/update run server-side in `process_dynamic_submission` via
`core_competency\api::create_template` / `update_template` + `lp_handler`. Delete uses two existing core
AJAX WS: `core_competency_template_has_related_data` and `core_competency_delete_template`.

## Component: `classes/form/template_dynamic_form.php`

Extends `\core_form\dynamic_form`. Mirrors `competency_dynamic_form` structure and reuses
`template_form.php`'s field set and validation verbatim where applicable.

- **`get_context_for_dynamic_submission()`**: if `id > 0`, return the template's own context
  (`template::get_record(['id' => $id])->get_context()`); else resolve from the `contextid` request
  param via `\context::instance_by_id($contextid)` (the Plans tab passes the resolved central context).
  Fallback to system context.
- **`check_access_for_dynamic_submission()`**: `require_capability('moodle/competency:templatemanage',
  $this->get_context_for_dynamic_submission())`.
- **`get_page_url_for_dynamic_submission()`**: `/local/dimensions/central.php`.
- **`definition()`** (mirror `template_form` fields): hidden `id`; hidden `contextid` (default = the
  resolved context id); `shortname` text (required, maxlength 100); `description` editor; `visible`
  selectyesno (default 1); `duedate` date_time_selector (optional); then
  `lp_handler::create()->instance_form_definition($mform, $this->get_templateid())`. No `js_call_amd`
  here.
- **`definition_after_data()`**: call `lp_handler::create()->instance_form_definition_after_data($mform,
  $this->get_templateid())` (mirrors `template_form`), plus `template_form`'s `customscss`
  editor↔plain reconciliation. Any future `js_call_amd` belongs here, not in `definition()`.
- **`set_data_for_dynamic_submission()`**: when editing, load `shortname`, `visible`, `duedate`,
  `description` (text+format), then `instance_form_before_set_data_with_image($data)`; `set_data($data)`.
- **`process_dynamic_submission()`**: build `$record` (shortname, description[text]+descriptionformat,
  visible, duedate, contextid); `api::update_template($record)` when `id > 0` else
  `api::create_template($record)`; then `lp_handler::create()->instance_form_save_with_image($data,
  $templateid)` (note: lp_handler's save is **2-arg** — `($data, $instanceid)` — unlike
  competency_handler's 3-arg version); invalidate `template_metadata_cache` and (if `enablecustomscss`)
  the template SCSS cache. Return `['templateid' => $templateid]`.
- **`validation($data, $files)`**: shortname required + unique within the context (reuse
  `template_form`'s check; `template::record_exists_select` on contextid+shortname excluding self).

## Component: `classes/output/dynamictabs/plans.php`

Export one new key: `contextid` = the resolved context's id (`$context->id`), used by the "New plan"
action and placed on the region dataset. `canmanage` (templatemanage) is already exported. No other
changes; the competency filter, context resolution, and template/competency lists stay as-is.

## Component: `templates/central/plans.mustache`

- Root region gains `data-contextid="{{contextid}}"`.
- A **"New plan"** button (`data-action="new-template"`), shown when `{{canmanage}}`, in the
  `plan-search` row (right-aligned).
- In the detail pane (`plan-detail`), when a template is selected and `{{canmanage}}`: an actions row
  with **Edit** (`data-action="edit-template"`) and **Delete** (`data-action="delete-template"`,
  `data-id="{{selectedtemplateid}}"`).
- Docblock + `Example context (json)` updated with `contextid`.

## Component: `templates/central/delete_template_plans.mustache`

Body for the "template has plans" modal: a `<p>` with `deletetemplatewithplans` (tool_lp) and two
radios named `deleteplans` — value `1` = `deleteplans` ("Delete the learning plans"), value `0` =
`unlinkplanstemplate` ("Unlink the learning plans from their template"), **value `0` checked by
default** (safe). Includes an `Example context (json)` block (empty object is fine — no variables).

## Component: `amd/src/central/plans.js`

Add, alongside the existing template-select and competency-filter handlers:

- **New** (`new-template`): open `ModalForm` with `formClass:
  'local_dimensions\\form\\template_dynamic_form'`, `args: {id: 0, contextid: <region data-contextid>}`,
  title `managetemplates_addtemplate`. On `FORM_SUBMITTED` → `reloadPane(pane)`.
- **Edit** (`edit-template`): same form, `args: {id: <selectedtemplateid>}`, title `edittemplate`
  (tool_lp). On submit → `reloadPane(pane)`.
- **Delete** (`delete-template`): `Ajax.call core_competency_template_has_related_data({id})`:
  - **has plans** → render `local_dimensions/central/delete_template_plans` into a
    `core/modal_save_cancel` titled `deletetemplate` (`$a` = template name); on save, read the checked
    `deleteplans` radio → `core_competency_delete_template({id, deleteplans})` → `reloadPane`.
  - **no plans** → `Notification.deleteCancelPromise(deletetemplate title)` → on confirm
    `core_competency_delete_template({id, deleteplans: false})` → `reloadPane`.
- All open via the pane dataset as source of truth; failures via `Notification.exception`.

## Strings (en + pt_br)

Reuse existing where possible: `managetemplates_addtemplate` (New template), and tool_lp
`edittemplate`, `deletetemplate`, `deletetemplatewithplans`, `deleteplans`, `unlinkplanstemplate`,
`delete`, `edit`. New plugin strings only if a label is missing after this review — none anticipated;
if the delete radios need a wrapper label, add `central_deletetemplate_planshandling` to both files
alphabetically. Keep `lang/{en,pt_br}` sorted and in sync.

## Data flow

New/Edit → `ModalForm` (generic `core_form_dynamic_form` WS renders `template_dynamic_form`) → submit →
`process_dynamic_submission` (api + lp_handler) → `reloadPane` re-renders the Plans tab. Delete →
`template_has_related_data` → simple confirm or radio modal → `delete_template` → `reloadPane`.

## Testing

- **Behat** (`@local @local_dimensions @javascript`, CI-only — no installed Moodle here): create a plan
  (New → fill shortname → save → appears in list); edit it (Edit → change name → save → updated);
  delete a plan **with** an associated learning plan (Delete → radio "Unlink…" → confirm → template
  gone, the user plan survives as standalone) and a plan **without** plans (simple confirm).
- **PHPUnit** (optional, runs in CI): instantiate `template_dynamic_form` and exercise
  `process_dynamic_submission` for create and update (asserts the template row + an lp custom field
  value persisted).

## Files

- **Create:** `classes/form/template_dynamic_form.php`; `templates/central/delete_template_plans.mustache`;
  `tests/behat/manage_plans.feature` (CI-only); optional `tests/form/template_dynamic_form_test.php`.
- **Modify:** `classes/output/dynamictabs/plans.php`; `templates/central/plans.mustache`;
  `amd/src/central/plans.js`; `version.php` (AMD change); `lang/en|pt_br/local_dimensions.php` (only if a
  new string is needed). Rebuild `amd/build/central/plans.*` via grunt.
- **Reuse unchanged:** `lp_handler`, `customfields_io`, `api::{create,update,delete,read}_template`,
  core WS `core_competency_{delete_template,template_has_related_data}`, caches.

## Risks / notes

- **Custom-field parity:** copy `template_form.php`'s `definition_after_data` and `customscss`
  editor↔plain handling exactly; the competency modal omitted `instance_form_definition_after_data`, so
  do NOT use it as the reference for that part — `template_form` is authoritative for templates.
- **Picture fields in modal:** handled by `instance_form_*_with_image` (proven in the competency modal).
- **Delete safety:** default radio is "unlink" (`deleteplans=false`) so user plans are not destroyed by
  accident; `api::delete_template` unlinks via `unlink_plan_from_template` in that mode.
- **Context for create:** the modal trusts `contextid` from the Plans tab but re-validates via
  `validate_context` + `templatemanage`, so a forged id cannot escalate.
