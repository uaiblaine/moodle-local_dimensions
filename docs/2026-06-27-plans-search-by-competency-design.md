# Design — Search learning plans by competency (Competency hub, Plans tab)

> Spec for the next slice of the Competency hub rebuild. Companion to
> [`admin-redesign.md`](admin-redesign.md) (IA §9.1: "planos são cross-framework; busca por
> competência") and [`admin-redesign-codeplan.md`](admin-redesign-codeplan.md) (`search_competencies`,
> `list_templates` filtered by `competencyid`). Status: [`implementation-status.md`](implementation-status.md).
> Date: 2026-06-27.

## Goal

On the **Plans** tab, let an administrator find which learning plan templates contain a given
competency. A `core/form-autocomplete` field searches competencies **cross-framework** (with the
origin-framework tag); selecting one filters the template list to plans that include it. Clearing the
field restores the full list.

## Non-goals

- No template CRUD (separate slice).
- No in-plan cross-framework picker / add-remove competency (separate slice — though it will reuse the
  new `search_competencies` WS built here).
- No change to the shared context selector behaviour beyond resetting the new filter on context change.

## Approach

`core/form-autocomplete` (canonical Moodle ajax autocomplete: accessible, keyboard, built-in
"no results") enhanced over a single `<select>`, backed by a small AMD datasource that calls a new
read web service. Rejected: a hand-rolled debounced input + dropdown (reinvents a11y/keyboard), and
the `tool_lp` competency pickers (modal trees, too heavy for an inline filter).

## New web service: `local_dimensions_search_competencies`

- File `classes/external/search_competencies.php`, class extends `\core_external\external_api`,
  `type => read`, `ajax => true`. Registered in `db/services.php` → **`version.php` bump**.
- **Params:** `query` (PARAM_RAW_TRIMMED), `limitfrom` (PARAM_INT, default 0), `limitnum`
  (PARAM_INT, default 25).
- **Access:** `validate_context(context_system::instance())` + `require_capability(
  'moodle/competency:competencyview', context_system::instance())`. The hub is a system-level admin
  surface, so system-scope read is consistent with the other tabs.
- **Logic (no N+1, cross-DB):**
  1. Collect readable framework ids: `competency_framework::get_records()` filtered by
     `competency_framework::can_read_context()` (frameworks are few). Keep `id => tag` (tag = framework
     `idnumber`, else `shortname`).
  2. If `query` is empty/under 2 chars or there are no readable frameworks → return `{items: [], total: 0}`.
  3. `WHERE c.competencyframeworkid {$insql} AND (` `$DB->sql_like('c.shortname', :q, false)` `OR`
     `$DB->sql_like('c.idnumber', :q, false)` `)`, with `:q = '%'.$DB->sql_like_escape($query).'%'`.
     `count_records_sql` for `total`; `get_records_sql(... , $limitfrom, $limitnum)` for the page,
     `ORDER BY c.shortname`.
- **Returns:** `{items: [{id, shortname, idnumber, frameworktag}], total}` — `shortname`/`frameworktag`
  via `format_string`, `idnumber` via `s()`. No cache (localised names rendered per request).

## AMD datasource: `amd/src/central/competency_datasource.js`

Implements the `core/form-autocomplete` ajax contract:
- `transport(selector, query, success, failure)` → `Ajax.call([{methodname:
  'local_dimensions_search_competencies', args:{query, limitfrom:0, limitnum:25}}])` → `success(items)`.
- `processResults(selector, results)` → map to `[{value: id, label: "shortname · frameworktag"}]`
  (tag appended only when present).

## `plans.php` (export)

- New arg `competencyid` (int, from the pane dataset).
- After building the context-readable `$templates` (unchanged), if `competencyid > 0`:
  - Load the competency; if missing **or** its framework is not readable → treat as no filter
    (`competencyid = 0`).
  - Otherwise intersect: keep only templates whose id appears in
    `api::list_templates_using_competency($competencyid)` (dedup by id).
  - Export `selectedcompetencyid`, `selectedcompetencylabel` (`shortname` + tag), `filteredbycompetency => true`.
- Export keys added: `competencyid` (for the pane/region dataset), `filteredbycompetency`,
  `selectedcompetencyid`, `selectedcompetencylabel`. The "no templates" empty state distinguishes
  "no plans contain this competency" (filtered) from "no plans" (unfiltered).

## `plans.mustache`

- A labelled `<select data-region="competency-search" id="local-dimensions-central-competency-search">`;
  when `filteredbycompetency`, it pre-renders the selected `<option value="{id}" selected>{label}</option>`
  so the autocomplete shows the active choice after a tab reload.
- An active-filter chip ("Plans containing: *label*") with a clear control (`data-action="clear-competency"`).
- Filtered empty state: `central_noplanswithcompetency`; unfiltered keeps `noplans`.

## `plans.js`

- On `init`: enhance the search select with `core/form-autocomplete`
  (`Autocomplete.enhance(selector, false, 'local_dimensions/central/competency_datasource',
  placeholder, false, true, noSelectionString, true)`), re-run safe on each tab refresh.
- On the select's `change`: `pane.dataset.competencyid = value || 0; reloadPane(pane)`.
- On `clear-competency` click: `pane.dataset.competencyid = 0; reloadPane(pane)`.
- Keeps `templateid` as the dataset source of truth (existing behaviour).

## `context.js`

`applyContextToPanes()` also resets `competencyid` to 0 (alongside `frameworkid`/`templateid`) so a
context switch starts the filter clean. One line; rebuild AMD.

## Data flow

type → `transport` → `search_competencies` → dropdown → select → `change` writes
`pane.dataset.competencyid` → `reloadPane` → `getContent` → `plans.php` intersects context templates
with `list_templates_using_competency` → filtered list + active chip. Clear → `competencyid = 0` → full list.

## Lang strings (en + pt_br, alphabetical, both files)

- `central_searchcompetency` = "Filter plans by competency" (field label/placeholder).
- `central_filteredbycompetency` = "Plans containing: {$a}" (active-filter chip).
- `central_clearcompetencyfilter` = "Clear competency filter".
- `central_noplanswithcompetency` = "No learning plans contain this competency."

## Files

- **Create:** `classes/external/search_competencies.php`; `amd/src/central/competency_datasource.js`;
  `tests/behat/search_plans_by_competency.feature` (CI-only); PHPUnit
  `tests/external/search_competencies_test.php`.
- **Modify:** `classes/output/dynamictabs/plans.php`; `templates/central/plans.mustache`;
  `amd/src/central/plans.js`; `amd/src/central/context.js`; `db/services.php`; `version.php`;
  `lang/en/local_dimensions.php`; `lang/pt_br/local_dimensions.php`. Rebuild `amd/build/**` via grunt.

## Testing

- **PHPUnit** (`search_competencies_test.php`, runs in CI): query matches shortname and idnumber
  (case-insensitive, `sql_like`); results limited to readable frameworks; pagination (`total` vs page);
  empty/short query returns nothing; string ids cast to int.
- **Behat** (`@local_dimensions @javascript`, CI-only — this working copy has no installed Moodle, so
  it can't be run locally): with two templates (one containing competency X), search "X" in the
  Plans tab and assert only the containing plan is listed; clear and assert both return.

## Risks / notes

- Readability is enforced by framework ids in SQL, so `total`/pagination stay correct (no post-filter skew).
- `list_templates_using_competency` returns templates regardless of context; the context intersection in
  `plans.php` keeps results scoped to the active System/Category context.
- `form-autocomplete` re-enhances on every tab refresh; initialise idempotently (guard against double-enhance).
