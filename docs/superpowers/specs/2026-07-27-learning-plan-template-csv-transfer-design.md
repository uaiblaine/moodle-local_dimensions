# Learning plans hub tab — CSV export and import with a dry-run preview

**Date:** 2026-07-27
**Surface(s):** `templates/central/plans.mustache` (new toolbar strip), two new modals (Export templates / Import templates), `classes/local/template_csv_*`, `classes/external/{export,preview_import,apply_import}_templates.php`
**Kit:** `docs/design-kit/maps/pln-plans.md`, `docs/design-kit/maps/mod-forms.md`, `docs/design-kit/screens/pln-plans.html`

## Why

The Frameworks tab already exports and imports a competency framework as CSV
(`classes/local/framework_csv_serializer.php` + `framework_csv_importer.php`). The Learning
plans tab has no equivalent: a template built over dozens of competency links, with the
plugin's own custom fields on top, can only be recreated by hand on another site.

The reference plugin `admin/tool/lptmanager` (CMU-SEI) covers the same ground and shows
exactly what not to build. Its LP CSV is five columns; it throws when a template spans two
frameworks; it echoes `$OUTPUT->notification()` from inside the importer (forcing
`NO_OUTPUT_BUFFERING`, making a redirect impossible and the class untestable); a missing
framework only `debugging()`s, so the template is still created **empty** and still reported
"created successfully"; and its "confirm" step confirms nothing about the data — it is a
column-mapping form. Nothing in it carries custom fields, `duedate`, `visible` or context.

The requirement that shapes this design is the inverse of that behaviour: **the operator must
see the projected result before anything is written, and then choose to apply all of it, part
of it, or none of it.** Core's own framework CSV tool is all-or-nothing with a fatal error;
this one must not be.

## Scope

Nine changes, all reachable from the Learning plans tab:

1. A CSV format for learning plan templates — two row types (`template`, `link`), every column
   read by header **name**, one file carrying many templates.
2. Export of the templates the tab currently lists, with the plugin's 14 template custom
   fields, plus a companion download for each referenced framework through the **existing**
   `local_dimensions_export_framework` web service.
3. A pure analysis pass (`template_import_analyser`) that resolves the file against the target
   site and returns a per-item verdict model without writing anything.
4. Automatic competency mapping through reported, confidence-ranked cascades — framework by
   idnumber then shortname, competency by idnumber then shortname **within that framework**.
5. A preview screen listing every row with its verdict, its field diff, its resolved links and
   its blast radius on existing learner plans, grouped by verdict, with per-row and per-link
   checkboxes and per-row remedies.
6. Partial apply: only the ticked items are written, each template in its own transaction, with
   a per-item outcome report.
7. Apply-time re-validation: the file is re-parsed and re-analysed, and any item whose verdict
   or fingerprint moved since the preview is refused and repainted rather than written.
8. Two audit events (`template_imported` per template, `templates_imported` per run).
9. A fix to `lp_handler::can_edit()`, which today resolves both capabilities at the **system**
   context and so silently drops every custom field for a category-scoped manager.

## Decisions

| Decision | Chosen | Why |
| --- | --- | --- |
| Wire format | CSV, two row types discriminated by a leading `rowtype` column, all columns read by header name | A core template is legitimately cross-framework (`api::list_competencies_in_template` joins `template_competency` with no framework filter) and competency **order** is payload (`template_competency::list_competencies` orders `sortorder ASC, id ASC`). A per-link row carrying its own `framework_idnumber` and an explicit `sortorder` expresses both directly, where a single comma-joined cell would need three layers of escaping. Unlike `framework_csv_serializer`, whose 14 positional columns exist only to interchange with core's `tool_lpimportcsv`, there is **no** core LP-template contract to honour — core has no learning plan template import or export at all, and `backup/` has zero `competency_template` references. Name-indexed headers are therefore strictly better: a hand-authored file may omit any column, and an absent column means "leave untouched" while a present-but-empty cell means "clear". Never the localised `get_string()` header row the reference emits, which makes a pt_br export unreadable on an en site without re-mapping. |
| Identity key across sites | The lp-area custom field `local_dimensions_template_idnumber`, promoted to a first-class CSV column (not a `cf_*` one), back-filled on create | `competency_template` has **no** idnumber column and no unique index on `shortname`, so core offers no cross-site key. The plugin already ships this field. Back-filling it on create makes the format idempotent by construction with no new concept: the second import of the same file is an update, not a duplicate. |
| Competency identity | The pair (`framework_idnumber`, `competency_idnumber`) | The only DB-enforced cross-site key: `competency_framework.idnumber` is globally unique and `competency.idnumber` is unique within a framework. Shortname columns are carried for readability and as an explicit, badged fallback — never as the authoritative key. |
| Cross-framework competency fallback | Not offered | The same idnumber in another framework is a different competency. A silent cross-framework match is exactly the plausible-looking corruption this feature exists to prevent. The analyser instead **reports** "this idnumber now exists in framework X" as an unticked hint. |
| Preview architecture | A separate analyser class with a fixed verdict enum, per-item fingerprints, `persistent::validate()` as the validity oracle, and a unit test asserting DB row counts are byte-identical before and after `analyse()` | Makes requirement 6 structural rather than aspirational. The analyser holds no write of any kind, so "nothing has been written yet" is provable, not promised. |
| Provisioning inside the analyser | Removed — the analyser never calls `ensure_custom_fields_exist()` | It is a write path: on an unprovisioned site it inserts a `customfield_category` row and up to 14 `customfield_field` rows, which would make the preview's own reassurance false and the "read" web service a writer. Provisioning already runs per session from the footer hook; the analyser reports `fieldnotprovisioned` for any field that still resolves to null, and only the apply path calls `ensure_custom_fields_exist()`. |
| Transaction granularity | One delegated transaction **per template** | A deliberate divergence from `framework_csv_importer.php:106,125`, which wraps its whole run in one transaction. That shape cannot express partial apply: a mid-loop bail rolls back everything already written, so "proceed with the seven that work" is impossible. Per-template gives atomicity where it matters (core row + custom fields + links commit or vanish together) and partiality at the run level. Recorded here so a later reviewer does not "fix" it back. |
| Failure handling per item | `catch (\Throwable)`, then `$transaction->rollback()` guarded, then `$DB->force_transaction_rollback()` on any leak, then continue | Core's own per-item idiom (`tool_lpmigrate\framework_processor`) catches `moodle_exception`, which is not enough: a `\TypeError` or `\Error` leaves the delegated transaction open and `moodle_database::$force_rollback` stuck true, turning every later write in the request into `dml_transaction_exception` — the apply run dies mid-way with a half-written batch and no outcome report. |
| `update existing` default | **Off** | Every matched template lands as `skip` with its diff visible and individually tickable, so a first run is a pure comparison the operator opts into row by row. On would pre-tick updates and arm, on the first Apply, the bulk `UPDATE` that renames every open learner plan built from the template. |
| Past `duedate` remedy default | **Clear the date** (`clearduedate`) | `template::validate_duedate()` rejects any new or changed duedate `<= time() - 600`, so this fires on almost every real cross-site import. Clearing is safe and stated in the preview; shifting would invent a date nobody chose. `shiftduedate` and `keepduedate` stay available per row. |
| `duedate` serialisation | `YYYY-MM-DD` (or `YYYY-MM-DD HH:MM`) in **UTC**, parsed back in UTC | Not a unix timestamp, because the operator will routinely have to edit this cell and editing `1767225600` in a spreadsheet is hostile. UTC is pinned because the form builds the timestamp in the acting user's timezone: without pinning, the same file re-imported by an admin in another timezone reconstructs a different integer, `validate_duedate`'s equality short-circuit misses, and the row blocks with `keepduedate` withheld. |
| Export scope | The templates the tab currently lists | `dynamictabs/plans.php:104` lists with `includes = 'self'` and hides disabled templates from non-managers, so the export offering matches what is on screen. An "all readable contexts" option would export templates the operator cannot see. |
| `cf_customscss` column | Emitted only when `enablecustomscss` is on, exactly as the framework export gates it | Same precedent, same `includescss` argument threading, same `array_diff` drop in `headers()`. |
| `lp_handler::can_edit()` | Fixed to resolve `moodle/competency:templatemanage` at the **template's own context** when an `$instanceid` is given, falling back to system for the field-configuration screens | Today both capabilities resolve at `context_system::instance()`, so `handler::instance_form_save()` — which saves only `get_editable_fields()` — silently writes **zero** custom fields for a manager who holds `templatemanage` only in a course category. That includes `template_idnumber`, so the identity key never lands and every re-import creates a duplicate. The category context is a supported target (`template::validate_contextid()` accepts `CONTEXT_COURSECAT`) and the Import button already renders there (`plans.php:98` resolves `canmanage` against the tab's context). `editcustomscss` stays system-scoped: it gates RISK_XSS content site-wide. |
| Images and cohorts | Out of the payload, each surfaced as a preview **and** export notice | `CFIELD_CUSTOMCARD` / `CFIELD_CUSTOMBGIMAGE` and the built-in `template_cardimage` / `template_bgimage` fileareas use two incompatible layouts (`customfield_picture` files keyed by the `customfield_data` **row** id vs system-context fileareas keyed by the **template** id), neither expressible as a cell — the framework CSV documents the same exclusion. Cohort links are excluded on safety grounds: attaching a cohort makes the hourly `core\task\sync_plans_from_template_cohorts_task` create a plan per member. |
| Preview payload shape | Server-rendered Mustache HTML plus **declared scalar** counts | `clean_returnvalue` silently strips keys an `execute_returns()` structure does not declare, so a deeply nested preview structure fails by rendering a blank cell with no error anywhere. The JS reads `data-itemkey` / `data-fingerprint` / `data-verdict` off each `<tr>` and needs no nested payload. |
| State between preview and apply | The filepicker draft file area itemid, and nothing else | The draft file is immutable and user-scoped (a foreign `draftitemid` is unreachable), so item keys are stable across requests; the DB is not, so verdicts are recomputed at apply time. A cached plan would be a second source of truth authoritative for nothing, and would cost a `db/caches.php` definition plus a `cachedef_*` string in both lang files. |
| Link removal on update | Never — links present on the target but absent from the file are **kept** and reported as `linkskept` | Removing a link does not clean up the `user_competency` and evidence rows it produced. Because nothing is removed, the apply step renumbers the **whole** final set (file links `0..n-1`, then kept extras `n..m`), which is also what stops the file's own order from colliding with the retained rows' pre-existing `sortorder` and silently falling back to `id ASC`. |

## Design

### The file

Column names are stable lowercase-ASCII tokens. `template` rows fill columns 0-7 plus every
`cf_*` column; `link` rows fill column 0, the parent key, and columns 8-12.

**Core columns (13):** `rowtype`, `template_idnumber`, `shortname`, `description`,
`descriptionformat`, `visible`, `duedate`, `sourcecontext`, `framework_idnumber`,
`framework_shortname`, `competency_idnumber`, `competency_shortname`, `sortorder`.

`rowtype` is `template` or `link`; any other value is counted as `unknownrowtype` and ignored.
On a `link` row, `template_idnumber` is the parent key and `shortname` the fallback parent key.
`sourcecontext` is **descriptive only** — never read to choose a target — and carries the source
category's name **and** idnumber, because most categories have no idnumber and an empty cell
would be indistinguishable from "system". `descriptionformat` preserves 0 (`FORMAT_MOODLE`);
never `?:` it to HTML, and note `FORMAT_WIKI` (3) fails core's `choices` check.

**Custom-field columns (14):** the 4 lp-only fields (`cf_displaymode`, `cf_subline_source`,
`cf_showrelated`, `cf_showrelatedlink`) then the same 10 tokens the framework CSV already uses,
in the same order and with the same encoding, `cf_customscss` last.

Encoding per field, against `classes/constants.php`: `cf_displaymode` is the plain integer 1 or
2 (the option array is keyed by int, so the 1-based index equals the constant); cascade selects
carry their canonical option **key** (`inherit`, `all`, `enrolled`, `active`,
`enrolledorself` / `inherit`, `yes`, `no` / `inherit`, `blocked`, `learnmore`), noting that
`showrelatedlink_options()` reuses the `SHOWRELATED_*` constants as its keys;
`cf_subline_source` carries the canonical key, whose verified option **order** is `status`,
`rating`, `tag1`, `tag2`, `none` — not the constant declaration order; and the admin-editable
selects (`cf_tag1`, `cf_tag2`, `cf_type`) carry the option **label**, matching the shipped
framework CSV so the two formats keep one mental model.

Rows are encoded in memory by a private `encode_row()` copied from
`framework_csv_serializer.php:165` (RFC 4180, every cell quoted, internal quotes doubled) and
returned through the web service as a string. Never `csv_export_writer`: its fixed per-user
temp path is double-unlinked when two exports share a request, a PHP warning that fails
`phpunit --fail-on-warning`.

Export emits only **real stored** values. `\core_customfield\api::get_instance_fields_data()`
defaults `$adddefaults = true` and returns a controller for every field whose `get_value()`
then yields the field default, so the readers keep the `(int) $data->get('id') > 0` guard from
`helper.php:1416-1428`. The `helper::get_template_*()` resolvers are unusable here — they
collapse `inherit` into the site-wide setting and would bake the source site's globals into
every row.

**Legacy ingest shim, one-directional.** A file whose header row has no `rowtype` column and
**exactly** five columns is read as a `tool_lptmanager` export: positions 0-4 as shortname,
description, descriptionformat, framework idnumber and comma-split competency idnumbers, one
template item plus one link item per idnumber, with the notice `legacyformat`. A header row
matching `framework_csv_serializer::CORE_HEADERS` is refused with a distinct message pointing at
the Structures tab — the plugin's own framework CSV is 14 columns with no `rowtype`, and a
loose "at least five columns" test would swallow it and produce a garbage preview. Our own
export is not made readable by `tool_lptmanager`.

### The analyser

`classes/local/template_import_analyser.php`, constructed with
`(array $parsed, \context $target, bool $updateexisting)`, one public `analyse():
template_import_plan`. It contains no `api::create_*` / `update_*` / `add_*` / `remove_*`, no
`$DB->insert_record` / `update_record` / `delete_records`, no `->create()` / `->update()` on any
persistent, and no provisioning call.

**Validity is asked of core.** `persistent::validate()` returns `true|lang_string[]` and writes
nothing. Create: `new \core_competency\template(0, $record)` then `get_errors()`. Update:
`new \core_competency\template($existingid)` then `->from_record($newvalues)` then
`get_errors()` — this order matters because `template::before_validate()` re-reads its snapshot
from the DB (`$this->beforeupdate = new self($this->get('id'))`) and `validate_duedate()`
short-circuits true when the new duedate equals the stored one. Note the oracle only genuinely
consults core on the changed-value path: `read()` sets `validated = true` and `raw_set()` clears
it only under loose comparison, so an unchanged field set short-circuits — faithful, because
`api::update_template()` caches identically, but no test may assert the oracle rejects an
already-invalid stored row.

**Identity cascade**, 0/1/N handled at every tier, with the fall-through stated: an empty
`template_idnumber` is **not** a key (skip tier 1 entirely — the unique indexes permit one empty
value per scope, so a tier-1 lookup on `''` would match a different empty-idnumber row and
report `exact` confidence for it).

1. Non-empty `template_idnumber` → one query joining `customfield_data` → `customfield_field` →
   `customfield_category` on `component = 'local_dimensions' AND area = 'lp'`, then
   `competency_template`, filtered on `contextid`, comparing with
   `$DB->sql_equal('d.charvalue', ':v', true, true)`. The category join is mandatory: the
   both-areas fields reuse the same shortname across the lp and competency areas. `sql_equal`
   with case- and accent-sensitive flags is required because MariaDB's default collation is
   case-insensitive and PostgreSQL's is not. `d.component` / `d.area` / `d.itemid` are never
   named — the plugin's own cross-version query at `helper.php:2908-2921` deliberately avoids
   them. The same query without the context filter detects `contextmismatch`.
2. On a tier-1 miss, `template::get_records(['shortname' => …, 'contextid' => …])` — plural,
   because `get_record()` with the default `IGNORE_MISSING` silently returns the first of N and
   emits a `debugging()` notice, which hides the ambiguity and fails PHPUnit. (It does **not**
   throw `dml_multiple_records`; no test may assert that.)

**Framework cascade**, per distinct value: exact `idnumber` (globally unique, but skipped for an
empty cell) then `can_read_context()`; then exact shortname among frameworks readable from the
target, resolved over the **union** of `self`, `parents` and `children` — `'children'` alone
returns the context plus descendant categories only, so a category import would report a
site-wide framework as missing; 2+ hits are `ambiguous`. `api::list_frameworks()` throws
`required_capability_exception` when no context is readable, so it is wrapped and reported as
`frameworkunreadable`.

**Competency cascade**, per link, scoped to the resolved framework: exact `idnumber` (unique per
framework) → `exact`; then exact shortname in the **same** framework, 2+ → `ambiguous`, 1 →
`competencyshortname`; else `missingcompetency`, naming the framework searched and — report
only, never auto-ticked — any other framework where that idnumber now exists. Competencies are
prefetched per framework, not queried per link.

**Template verdicts** (`classes/local/template_import_verdict.php`, every label resolved by a
literal `match()` returning a fixed `get_string` key — dynamic string ids are forbidden):
`create`, `update`, `insync`, `skip`, `conflict`, `blocked`, `orphanlink`.

`insync` is **not** pre-ticked and its help text states the real consequence, because applying
it is not a no-op: `data_controller::instance_form_save()` performs no value comparison and
calls `save()` unconditionally, and `api::update_template()` calls `update()` unconditionally, so
both `customfield_data.timemodified` and `competency_template.timemodified` move — which
re-arms the hourly cohort sync for that template.

`conflict` reasons are `ambiguous` and — now with remedies rather than dead ends —
`shortnametaken` (remedy `adopt`: treat the same-shortname template in the context as the match,
show its diff, back-fill `template_idnumber` on apply; core permits the duplicate, only the
hub's own form forbids it) and `contextmismatch` (remedy `createhere`: create a new template in
the target carrying the same idnumber, legal because the field is created with
`uniquevalues => 0`, with the preview naming the other context's copy). Without these two
remedies the most likely real first import — a target that already holds hand-made templates
whose shortnames match the file — would be unimportable, and requirement 5 would collapse to
"abort".

`blocked` reasons: `duedatepast` (remedies `clearduedate` — pre-selected — `shiftduedate`, and
`keepduedate` only when the stored value is byte-identical), `duedateunparseable`,
`shortnametoolong` (remedy `truncate`), `shortnamemissing`, `invalidformat`, `structuremissing`,
`nocapability`, `fieldnotprovisioned`, `fieldnotwritable`, `cfvaluetoolong`, `validationfailed`.

`shortnametoolong` requires the parse to **stop** using `shorten_text()`: that function counts
its ending against the budget, so its result can never exceed 100 characters, which would make
the verdict dead code, the remedy unreachable and the truncation silent — the exact class of
invisible change requirement 6 exists to prevent. Parse with `clean_param(…, PARAM_TEXT)`,
measure with `core_text::strlen()`, and let `truncate` apply `core_text::substr($v, 0, 100)`.
`cfvaluetoolong` exists because `customfield_text` enforces its configured maxlength only in
`instance_form_validation()`, which the write path bypasses: a 150-character
`template_idnumber` would otherwise be written past its own maxlength of 100, and a
1400-character value would be a raw DML error surfacing only as outcome `failed`.

**Link statuses**, each carrying an explicit confidence: `matched` (exact, ticked),
`matchedfallback` (ticked but badged `bg-warning text-dark` so the inference is visible),
`alreadylinked` (from a prefetched `[competencyid => true]` map tested with `isset()` — never
`array_flip` + `!empty`, since `array_flip([5])` is `[5 => 0]`), `missingframework`,
`missingcompetency`, `hiddenframework` (pre-checked, because
`api::add_competency_to_template()` throws `coding_exception` for a competency in a hidden
framework), `ambiguous` (never auto-ticked, candidate count shown), `emptyreference`.

**The structure roll-up** — requirement 2's core decision. A template with
`linksmatched === 0 && linksunresolved > 0` becomes `blocked` / `structuremissing`: "the
structure this plan needs is not on this site — import the framework first". A template with
**zero** link rows is explicitly **not** blocked; a competency-less template is a legitimate
export. A template with some resolvable links stays `create` / `update`, carries
`linksmatched` / `linkstotal`, and offers remedy `partial`. The file lists once, at the top, the
distinct missing framework idnumbers and shortnames — an actionable "import these first" list
naming the Structures tab's own CSV import.

**Blast radius**, on every `update` and `skip` row, because updating rewrites learner data:
`api::update_template()` cascades shortname, description, descriptionformat and duedate onto
every non-complete plan through a raw bulk `UPDATE` that bypasses the plan model. `blast` =
`{openplans, frozenplans, cohorts, linksadded, linkskept, reordered}`, where `openplans` needs a
new `helper::count_open_plans_by_template()` (`status != STATUS_COMPLETE`) because the shipped
`count_plans_by_template()` counts all statuses and so cannot express "N learners' plans will be
renamed". The split matters: draft and active plans read the template **live** from
`template_competency`, while complete plans are frozen against `user_competency_plan` — so
`linksadded` is reported as "N active plans gain M competencies", the part learners actually see.

**Field diff** — requirement 4's "what can be synchronised" — over shortname, description,
descriptionformat, visible, duedate and every `cf_*` token present in the file. Three
normalisations are mandatory or the diff lies: `description` is compared
`clean_param(…, PARAM_CLEANHTML)` on both sides, because `persistent::validate()` silently
rewrites `PARAM_CLEANHTML` and a naive export-import-diff would otherwise report a change
nobody made; `cf_*` select values are compared as resolved **indexes**, not labels; and
`duedate` is compared as the UTC-reconstructed integer against the stored integer.

**Fingerprint** per item: sha1 over the verdict, the reason, the matched template id, the
projected field diff, the ordered list of resolved competency ids and the resolved existing-link
id set. The verdict alone is insufficient — "the competency I was going to link got deleted" and
"another admin edited this template's description" both leave the verdict `update` while
changing what would happen. Item keys are `t<n>` and `t<n>l<m>`: `PARAM_ALPHANUM`-safe, no
colon, stable across a re-parse of the same immutable draft.

**File-level notices**: SCSS dropped when the caller lacks `local/dimensions:editcustomscss`
(which has `archetypes => []`, so no role holds it by default); the `cf_customscss` column
absent because `enablecustomscss` is off, or present against a site where it is off and so
unprovisioned; images not carried; cohorts and cohort-role rules not carried, plus, for a matched
target, "cohorts detected on the target, not touched by this import"; `inherit` cascade values
keeping their intent but resolving against the **target** site's settings; every `tag1` / `tag2` /
`type` label matching no option on the target, listed by name with its per-value remap control;
every `cf_bgcolor` / `cf_textcolor` cell failing `helper::normalise_hex_color()`'s pattern; every
SCSS body failing `scss_manager::validate_scss(string)` (note `helper::validate_customscss()`
takes submitted form data, not a string); `visible = 0` meaning the template can generate no
plans at all; any cell containing U+FFFD, which means the chosen encoding was wrong and a
mojibaked `template_idnumber` would miss the identity match and land as a duplicate `create`;
`syncqueued` when a written row will be re-evaluated by the hourly cohort sync — which is
narrower than it looks, since `template_cohort::get_all_missing_plans()` joins on `t.visible = 1`
and `(t.duedate = 0 OR t.duedate > :time)`, so hidden or past-due templates are not re-evaluated;
and `legacyformat`.

Both web services raise `core_php_time_limit::raise()` and
`raise_memory_limit(MEMORY_EXTRA)`, as the shipped framework importer does. The preview renders
at most 500 rows and states how many were not shown, so a multi-thousand-row file cannot become
a multi-megabyte payload injected into a modal DOM.

### Apply

`apply_import_templates` receives `(draftitemid, contextid, delimiter, encoding,
updateexisting, selections[])` where each selection is
`{itemkey PARAM_ALPHANUM, verdict PARAM_ALPHA, fingerprint PARAM_ALPHANUM, remedy PARAM_ALPHA,
links[] PARAM_ALPHANUM}`. Note what is **not** sent: no competency ids, no template ids, no
field values. Nothing the browser says is used as data — only as a choice among options the
server itself computed. This deliberately rejects the reference's
`required_param('competencies', PARAM_RAW)` + `json_decode` reused as the authoritative id list.

The file is re-read from the caller's own draft area, re-parsed and re-analysed **once** per
call, not per selection. A missing draft file (cron cleanup, or a cleared area) gets its own
file-level message rather than degrading to every item reporting `gone`. Then per selection:
absent from the fresh plan → `gone`; verdict or fingerprint moved → `changed`, nothing written,
and the fresh item is returned so the row repaints in place and the operator re-decides; not
selectable → `skipped` with a reason; a remedy the fresh item does not offer → `changed`.
Deselecting every link of a `create` row re-runs the roll-up on the selection, so the operator
cannot express a selection whose outcome the preview never projected.

The write, per accepted template item, inside its own `start_delegated_transaction()`:

1. Build the six-field core record. `contextid` is the resolved target on create and is **not
   sent at all** on update — `api::update_template()` throws `coding_exception` when a submitted
   contextid differs from the stored one. Apply the remedy.
2. `api::create_template()` or `api::update_template()`, which fires the core events that reach
   `observer::template_updated`.
3. Custom fields through the handler, never by SQL:
   `lp_handler::create()->instance_form_save((object) (['id' => $id] +
   helper::template_customfields_to_formdata($cf)), $isnew)`. `template_customfields_updated`
   then fires automatically from the handler override, so custom-field auditing is free and must
   not be duplicated. `$isnew` comes from the analyser's real `customfield_data` existence probe.
   Deliberately not `instance_form_save_with_image()`: it hardcodes `$isnew = true` (so the audit
   flag would be wrong) and wraps the call in a `dml_write_exception` retry that cannot recover
   inside a PostgreSQL transaction a failed statement has poisoned.
4. Back-fill `template_idnumber` on create, folded into the same formdata.
5. Links in file `sortorder` order, only the selected and resolved ones, through
   `api::add_competency_to_template()`. Its `false` return on the duplicate path is counted as
   `alreadylinked` and never fed to an event trigger's `->get()`.
6. Renumber the **whole** final set through the persistent — file links `0..n-1`, then kept
   extras — guarding the lookup, because `template_competency::get_record()` returns literal
   `false` when no row matches and `->set()` on `false` raises an `\Error` that is not a
   `moodle_exception`. This is reachable: `add_competency_to_template()` returns false without
   creating a row when the link already exists. Explicit non-null sortorders survive
   `before_validate()`, which only overwrites when the value is null.
7. `allow_commit()`.
8. **After** the commit, mandatory not defensive: `template_metadata_cache::invalidate_template()`,
   `scss_manager::invalidate_cache($id, helper::AREA_LP)` when `enablecustomscss` is on, and the
   template-courses invalidation when links changed. `db/events.php` registers only core
   competency events, so the plugin's own `template_customfields_updated` has no observer and
   writing custom-field values invalidates nothing by itself; and `template_scss` has no TTL and
   caches the empty string on a miss, so one learner render between "template created" and "SCSS
   written" would poison `css_<id>` permanently.
9. Trigger the audit events.

Outcomes are `created`, `updated`, `skipped`, `changed`, `gone`, `failed`, collected into a
struct and rendered after the last commit — nothing is echoed from inside the importer.

### UI

A new full-width toolbar strip in `templates/central/plans.mustache`, after the
`showhiddentoggle` close and before the plans body, copying the Frameworks toolbar's shape and
reusing `.local-dimensions-central-fwtoolbar` / `-fwactions` / `-btn-outline` unchanged. Its
left region needs a new `templatecount` key from `dynamictabs/plans.php` (the Frameworks
template interpolates `frameworkcount` plus a hidden-count clause, which `plans.php` does not
export today), and `canexport` alongside the existing `canmanage`.

The buttons are labelled **Import templates** and **Export templates**, not "Import" and
"Export". The Frameworks pane is server-rendered first and merely hidden once the user switches
tabs, Behat's `find_all` does not filter by visibility, and its buttons are labelled exactly
"Import" and "Export" — so a name-based click would resolve to the hidden pane and die with
`ElementNotInteractableException`. The plugin already recorded this hazard in
`amd/src/central/plans.js:783`.

Import is a two-step modal handoff: an upload-only `dynamic_form` whose
`process_dynamic_submission()` returns only the draft handle and the parse settings and contains
no write, then a `ModalSaveCancel` carrying the preview. The second modal opens on
`ModalEvents.hidden` after `preventDefault()`, not inside the submit handler, so it does not race
Bootstrap's `hidden.bs.modal` body-class cleanup. The preview body hosts its own toast region
(the page-level wrapper sits below the modal's z-index), offers the shipped
`modal_refresh.attach()` re-check button — which re-calls the preview web service on the same
`draftitemid`, so after importing the missing framework in another tab one click turns every
`structuremissing` row into `create`, and requirement 4's "re-validate" needs no second code
path — and keeps Apply disabled with an in-body `alert-danger` when nothing is ticked.

Export is a single modal with a multiple `form-select`, a select-all checkbox, the lossiness
notice, and a "Referenced structures" list offering each distinct referenced framework as a
companion download through the **existing** `local_dimensions_export_framework` web service.
Its loader span must not carry `d-inline-flex`: that utility is `display: inline-flex !important`
and beats `[hidden]` on Bootstrap 4, so the loader could never hide on the 4.05 leg.

## Code changes

- **`classes/local/template_csv_serializer.php`** (new) — `HEADERS_CORE` (13) and `HEADERS_CF`
  (14) constants, `headers(bool $includescss)`, `export_templates(array $templateids, bool
  $includescss)` returning `array{filename, content, frameworks}`, `parse(string $text, string
  $encoding, string $delimiter)` returning `array{templates, links, error, legacy}`, the legacy
  five-column shim, the framework-CSV refusal, and a private `encode_row()`.
- **`classes/local/template_import_verdict.php`** (new) — verdict, reason, link-status,
  confidence, remedy and outcome constants plus one `match()`-based label method each.
- **`classes/local/template_import_analyser.php`** (new) — `analyse(): template_import_plan`.
- **`classes/local/template_import_plan.php`** (new) — immutable value object: `get_items()`,
  `get_item()`, `get_counts()`, `get_notices()`, `get_missing_structures()`,
  `get_target_context()`.
- **`classes/local/template_csv_importer.php`** (new) — `apply(array $selections): array`.
- **`classes/output/central/template_import_preview.php`** (new) — `renderable` + `templatable`
  with `export_for_template(renderer_base $output)`; hub renderables live under
  `classes/output/central/` and the plugin has no `renderer.php`.
- **`classes/external/export_templates.php`** (new) — read; `validate_context()` **once** on the
  resolved target context, then `require_capability('moodle/competency:templateview',
  $template->get_context())` per template. Not once per template: `validate_context()` calls
  `$PAGE->set_context()`, and a second change from `CONTEXT_COURSECAT` emits `debugging()`, which
  fails PHPUnit under `--fail-on-warning`.
- **`classes/external/preview_import_templates.php`** (new) — read; returns
  `{html PARAM_RAW, counts (declared scalars), missingframeworks[], canapply PARAM_BOOL}`;
  `context::instance_by_id()` wrapped in try/catch as the shipped import form does.
- **`classes/external/apply_import_templates.php`** (new) — write; returns
  `{results[] {itemkey, outcome, message, html}, counts}`.
- **`classes/form/import_templates_dynamic_form.php`** (new) — upload only.
- **`classes/event/template_imported.php`**, **`classes/event/templates_imported.php`** (new) —
  mirroring `template_duplicated.php`; triggered after `allow_commit()`, never inside a
  transaction; no `db/events.php` entry (that file is observers-only).
- **`classes/customfield/lp_handler.php`** — `can_edit()` resolves `templatemanage` at the
  template's own context when `$instanceid` is given; `editcustomscss` stays system-scoped. Also
  verify `get_instance_context()` returns the template's own context, or a category template's
  `customfield_data.contextid` will be wrong on a fresh insert (the duplication path copies
  contextid verbatim and so has never exercised this).
- **`classes/helper.php`** — add `export_template_customfields(int $templateid)`,
  `template_customfields_to_formdata(array $cfrow)` and `count_open_plans_by_template()`; add an
  `$area` parameter (default `AREA_COMPETENCY`, so `framework_csv_serializer` is untouched) to
  the three readers that hardcode the area — `get_competency_select_raw:1275`,
  `read_competency_cf_data:1417` and `select_label_to_index:1494`; make
  `normalise_hex_color()` and `select_raw_options()` public (`read_competency_select_label()` is
  already public).
- **`classes/form/template_dynamic_form.php`**, **`classes/form/framework_dynamic_form.php`** —
  replace `get_string('shortnametaken', 'tool_lp')`, which does not exist and renders
  `[[shortnametaken]]` plus a missing-string `debugging()`, with the new plugin key.
- **`classes/output/dynamictabs/plans.php`** — add `templatecount` and `canexport`; stop
  pre-escaping `idnumber` with `s()` (or render the data attribute triple-stashed), since the
  Mustache tag escapes it again.
- **`templates/central/plans.mustache`** — the toolbar strip; `data-name` and `data-idnumber`
  on each row.
- **`templates/central/plans_import_preview.mustache`**,
  **`templates/central/plans_import_row.mustache`** (the per-row partial the apply response
  repaints), **`templates/central/plans_export.mustache`** (new).
- **`amd/src/central/plans_transfer.js`**, **`amd/src/central/download.js`** (new; the shared
  `triggerDownload`/`makeSpinner` lifted out of `frameworks.js` — with no ARIA change, since the
  defect the design kit records at `fwk-structures.md:98-102` does not exist at HEAD; the real
  gap is the export loader's missing accessible name), **`amd/src/central/plans.js`** and
  **`amd/src/central/frameworks.js`** modified, all four with rebuilt `amd/build` output.
- **`db/services.php`** — three functions, `ajax => true`, no `classpath`, mirroring the
  `local_dimensions_export_framework` entry.
- **`lang/en/local_dimensions.php`** and **`lang/pt_br/local_dimensions.php`** — roughly 70
  `central_plans_import_*` / `central_plans_export_*` keys plus the fixed label sets and the two
  event names, in the correct alphabetic slot in both files, which today hold 625 keys each.
- **`styles.css`** — `.local-dimensions-tplimport-*` rules; no `!important`, no
  `clamp()`/`min()`/`max()` in any length-valued property, no sub-100ms timing.
- **`version.php`** — bump from `2026072600`: three new web-service functions install only on
  upgrade, and the rebuilt `amd/build` needs a new cache revision.

## Kit and map changes

- `docs/design-kit/maps/pln-plans.md` — `PLN-IMPORT` / `PLN-EXPORT` toolbar rows and the
  `PLN-IMP-*` / `PLN-EXP-*` region rows. Re-derive every JS line reference: the map claims
  `plans.js` is 871 lines and HEAD is 860.
- `docs/design-kit/maps/mod-forms.md` — a `FORM-TPLIMP-*` section beside `FORM-IMP`, publishing
  `-FILE`, `-DELIM`, `-ENCODING`, `-UPDATE`, `-CONTEXTID` rows.
- `docs/design-kit/screens/pln-plans.html` — both modals drawn.
- `README.md` line 59 (the Learning plans bullet) gains CSV import/export with a dry-run
  preview; `CHANGELOG.md` gets one `## [Unreleased]` / `### Added` entry.

## Tests

- The serializer round-trips a template with every custom field set, a cross-framework link set,
  and a competency-less template; `headers(false)` drops `cf_customscss` cleanly; a `link` row
  whose parent key matches nothing is an `orphanlink`; the legacy five-column shim parses a
  `tool_lptmanager` export; a 14-column framework CSV is refused with the Structures-tab message.
- The analyser writes nothing: snapshot `competency_template`, `competency_templatecomp`,
  `competency_templatecohort`, `competency_plan`, `customfield_data`, `customfield_field`,
  `customfield_category` and `files` row counts, plus the full `competency_template` row set,
  run `analyse()` over a fixture exercising every verdict, and assert every count and row is
  unchanged. `customfield_field` and `customfield_category` are in the snapshot precisely because
  provisioning was removed from the analyser; the fixture asserts the fields already exist as a
  precondition.
- One case per template verdict and per link status, including: no structure at all; a competency
  moved to another framework; a duplicate shortname in the context resolving to `conflict` with
  the `adopt` remedy offered; a template matched by idnumber in a different context offering
  `createhere`; a past duedate offering `clearduedate` pre-selected; a zero-link template
  **not** blocked; an empty `template_idnumber` skipping tier 1.
- The importer applies only the ticked items; a mutated DB between preview and apply yields
  outcome `changed` with nothing written; a per-item failure does not abort the run and does not
  leave a transaction open; link order survives an update that keeps extra links.
- Each web service asserted **through** `clean_returnvalue()` so a missing allowlist entry fails
  loudly, plus a `required_capability_exception` case each, plus a two-category export proving no
  unexpected `debugging()`.
- One thin Behat smoke scenario: both toolbar buttons present, each modal opens with its own
  title, Cancel. No upload, no preview interaction. Grep `tests/behat/` for every moved label —
  beyond `manage_plans.feature`, the Plans tab is also driven by `central_restore.feature`,
  `manage_enrol_methods.feature` and `search_plans_by_competency.feature`.

PHPUnit does not run in this checkout; these run in CI.

## Out of scope

- **Cohort links and cohort-role rules** — excluded on safety grounds (the hourly sync task
  mass-creates a plan per member), surfaced as a notice on both the export and the import side.
  A future opt-in is possible; the mechanism already detects and counts them.
- **Images** — `CFIELD_CUSTOMCARD`, `CFIELD_CUSTOMBGIMAGE` and the built-in card/background
  fileareas, two incompatible storage layouts, neither expressible as a cell. Same exclusion the
  framework CSV documents.
- **Files embedded in `cf_customscss` or description bodies** — keyed by the `customfield_data`
  row id; a cell containing `@@PLUGINFILE@@` raises a notice instead.
- **User plans, ratings and per-user progress** — never in a template transfer.
- **Making our export readable by `tool_lptmanager`** — the shim is one-directional. Its
  five-column format cannot carry a second framework, a custom field, `visible`, `duedate` or a
  context, and its header row is localised.
- **A MUC cache for the analysis** — the draft file is the only state; a cached plan would be a
  second source of truth authoritative for nothing.
- **Batching or resumable apply** — deferred. The row cap plus the raised limits cover the sizes
  a hub operator realistically imports; see Risks.
- **An Import affordance in the tab's empty state** — mechanically trivial, but it has not earned
  the visual weight while the guided empty state dominates the pane.

## Risks

- **A timeout mid-apply commits an unknown prefix and returns no report.** This is the direct
  cost of per-template transactions, and it is the one place where the "see it before it happens"
  guarantee degrades. Mitigated by the raised time limit and memory, by the row cap, and by the
  fact that a re-preview after a timeout shows exactly what landed (written templates come back
  as `insync` or `update`) — but not eliminated. Batching is the follow-up if real files get big.
- **`lp_handler::can_edit()` changes behaviour outside this feature.** Resolving
  `templatemanage` at the template's own context also affects the template edit modal and the
  duplication path for category templates — which is the point, since those silently dropped
  custom fields too, but it is a behavioural change a category-scoped manager will notice.
- **`insync` is mildly harmful, not inert.** Applying it moves two `timemodified` columns and
  re-arms the cohort sync for that template. It is left tickable, unticked, with the consequence
  in its help text, rather than made non-selectable — the operator may legitimately want to
  force-touch a row.
- **`inherit` cascade values keep their intent, not their behaviour.** A template exported from a
  site whose `enrollmentfilter` default is `enrolled` and imported into one defaulting to `all`
  renders differently with an identical payload. Reported as a notice; not resolvable in the
  format without baking the source site's globals into every row.
- **The admin-editable `tag1` / `tag2` / `type` option lists are seeded once and never
  re-synced**, and are separate per area, so their labels are genuinely site-local. Handled by
  reporting every unmatched label with a per-value remap control rather than silently landing on
  index 0 — but a large tag vocabulary makes for a long notice.
- **The framework shortname fallback can still under-reach.** `'parents'` and `'children'` are
  mutually exclusive in `api::get_related_contexts()`, so the union is assembled by two calls;
  a framework in a *sibling* category remains invisible, and is reported as missing.
