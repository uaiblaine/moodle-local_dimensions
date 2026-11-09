# Proposal — Evidence hub: validity, readiness and dossiers (2026-10-29)

A future direction, not a plan of record. It answers an architecture review request: turn core
competencies into a **compliance and work-readiness** tool by giving prior-learning evidence
(certificates, ASO, ART/RRT, diplomas, practical assessments) an issue date, an expiry date and a
type, computing a validity status per worker and competency, showing it in an **Evidence hub**, and
exporting a **dossier** (ZIP of a PDF summary plus the original files) that proves to a contracting
company that the workers on a contract hold every required qualification.

Everything about core below was read in `~/dev/moodle-503` (5.3) on 2026-10-29, with `file:line`
where it matters. Nothing is built. The owner approved every recommendation in
[Decisions](#decisions-for-the-owner) on 2026-10-29; stage S0 (the spike) is under way, its results
in [`spike/`](spike/). Mockups: [`mockups.html`](mockups.html).

## In one paragraph

Build it as a **companion plugin** (working name `local_evidencehub`), not inside
`local_dimensions`: the feature needs its own tables (core's `competency_userevidence` has no spare
column and no extension point), it makes the plugin a personal-data plugin with health data in
scope, and it serves a different audience on a different release cadence. Keep core's flags
untouched: **validity is a separate, materialised status per (user, competency)**, computed from
the evidence by a pure calculator, refreshed by event observers and by a scheduled task that only
visits rows whose next date transition has passed. The hub grid pages with a **two-step
"deferred join"** (the S0 spike measured a report builder system report at 0.5–0.8 s on the worst
case, the two-step query under 300 ms on both database families); downloads go through core's
dataformat API, and a report builder datasource still gives admins custom reports and e-mailed
schedules. Dossiers stream a ZIP when small
and build in an ad hoc task when large, with a QR code pointing to a public verification page
that shows a snapshot, never the worker's identifiers.

## What Moodle 5.3 already does

| Need | Core in 5.3 | Where |
|---|---|---|
| Evidence record | `competency_userevidence`: userid, name, description, url, timestamps. **No other column**; one index (`userid`) | `lib/db/install.xml:3919-3938` |
| Evidence ↔ competency | `competency_userevidencecomp`, UNIQUE (userevidenceid, competencyid) | `install.xml:3939-3956` |
| Files | `core_competency/userevidence`, owner's **user context**, itemid = evidence id; served only to `user_evidence::can_read_user()`, always as a download | `competency/lib.php:290-316` |
| Who reads / manages evidence | `userevidenceview` and `userevidencemanage` (manager), `userevidencemanageown` (user), **all at USER context** | `lib/db/access.php:2454-2468` |
| Linking evidence | `api::create_user_evidence_competency()` writes an `ACTION_LOG` row in `competency_evidence` and creates the `competency_usercomp` row if missing; **never** touches grade, proficiency or status | `competency/classes/api.php:3927-3961` |
| Events | `competency_user_evidence_created/_updated/_deleted` (user context, objectid = evidence id, no `other`); link and unlink fire only `competency_evidence_created` (`other`: usercompetencyid, competencyid, action) | `lib/classes/event/` |
| Delete order | row, then files, then one unlink per competency (each a `competency_evidence_created`), then `_deleted` | `api.php:3872-3897` |
| Extending the evidence form | **None**: `tool_lp\form\user_evidence` is a `core\form\persistent`; no hook, no callback, no customfield handler for competency or evidence anywhere in core | `admin/tool/lp/classes/form/user_evidence.php` |
| Expiry, validity, verification | **None** | — |
| Users of a template | No helper; `competency_plan.templateid` (or `origtemplateid`), or `cohort_members` through `competency_templatecohort` | `template_cohort.php:102` |
| Row-level capability in SQL | `api::filter_users_with_capability_on_user_context_sql($cap)` returns an `IN` fragment over user ids, honouring system grants and user-context role assignments | `api.php:5261` |
| PDF and QR | `\pdf` extends TCPDF; `write2DBarcode($url, 'QRCODE,M', ...)` is bundled | `lib/pdflib.php:159`, `lib/tcpdf/tcpdf_barcodes_2d.php:294` |
| Streaming ZIP | `\core_files\archive_writer::get_stream_writer()` (ZipStream straight to the client) and `get_file_writer()`; `add_file_from_stored_file()` | `files/classes/archive_writer.php:51,68`; used by `mod/folder/download_folder.php` |
| Build later, deliver by message | `tool_dataprivacy` `process_data_request_task`: file into a user-context file area, `message_send()`, served by its own `pluginfile` after a capability check | `admin/tool/dataprivacy/classes/task/process_data_request_task.php:136-244` |
| Grid with bulk actions | `system_report::set_checkbox_toggleall()`; precedent: the 5.x admin **Browse users** page | `reportbuilder/classes/system_report.php:151`, `admin/classes/reportbuilder/local/systemreports/users.php` |
| Identity / CPF in bulk | `core_user\fields::for_identity($context)->including('profile_field_<cpf>')->get_sql('u')` | `user/classes/fields.php:538` |
| Side drawer | **No Bootstrap offcanvas** in Boost's AMD; `local_dimensions` has none either. `core/modal` with a side-sheet class keeps the focus trap | `theme/boost/amd/src/` |

## Review of the plan, point by point

### 1. Fit with `local_dimensions`: inside, or a companion plugin?

**A companion plugin, with soft integration.** The reasons, each one enough on its own:

- **Tables.** `local_dimensions` keeps a rule, *no own tables, core data wherever core has it*.
  This feature cannot keep it: core has no column for an expiry date, nothing records a
  verification, and KPIs over thousands of workers need a materialised status (see 3). Putting
  tables into `local_dimensions` breaks the rule that made its four-branch maintenance cheap
  (`legacy_repair` instead of numbered upgrade steps).
- **Privacy weight.** `local_dimensions` has a preference-only provider today. Evidence metadata,
  verification trails and exported dossiers are personal data, and an ASO is **health data**, a
  sensitive category under LGPD art. 11. That should not ride along in a plugin that schools
  install for learning plans.
- **Audience and cadence.** Learning designers use the Competency hub; compliance officers, SESMT
  and contract managers use this. A separate plugin starts on 5.3 only (`[503, 503]`) without the
  cherry-pick tax of four branches.
- **Coupling cost of a hub tab.** The hub's tabs are hard-coded in five places in `central.php`
  (instances, allowlist, labels, icons, loop). A tab from another plugin would need a registry
  there first.

What it reuses from the family, without a hard dependency:

- the same page shape as the Competency hub (context bar locked to a category entry, icon tabs,
  sticky footer) and the same front-end conventions (ESM, `core/modal`, `ModalForm`, toast in the
  modal body, `flashRow`, plain-vs-escaped names);
- the family colour contract: its own `--local-evidencehub-*` tokens wrapped as
  `var(--dimensions-x, <own chain>)`, so `theme_dimensions` paints it like the rest;
- a **Hooks API** bridge for the learner views: `local_dimensions` dispatches a new hook
  (`\local_dimensions\hook\competency_status_badges`), and the companion answers with a validity
  pill for each competency in the plan overview and the tracker. Neither plugin requires the
  other; the hook is the whole contract.
- a link from the Competency hub's context bar when the companion is installed
  (`core_plugin_manager` lookup, one line).

Considered and rejected: a subplugin type of `local_dimensions` (all the coupling of "inside",
plus a `subplugins.json` machinery for one consumer); a `report_` plugin (the feature writes data,
runs tasks and serves files, which is not what the report type is for).

### 2. Core impact: validity without corrupting `core_competency`

The plan is right to decouple, and it should go further: **the engine never writes core state by
default**.

- `competency_usercomp.proficiency` means *demonstrated*; validity means *still current*. They
  are orthogonal, and the UI shows both (core's proficiency badge beside the validity pill).
  Course completion rules, plan completion and `competency_usercompplan` archives keep working
  untouched because nothing here writes to them.
- The only core write the engine may make is an **`ACTION_LOG` evidence row** through
  `api::add_evidence()` when a status changes ("Validity expired on 2026-11-03: recycling
  required"), so the competency's own history in `tool_lp` tells the story. Optionally, per
  policy, with `recommend = true`, which moves an idle user competency to *waiting for review*:
  a human reviewer decides a re-rating.
- **No automatic demotion of proficiency.** The plan's "rebaixamento do nível de proficiência"
  for technical competencies would be an `ACTION_OVERRIDE` with a lower grade written by cron.
  It overwrites a rating a person gave, it cascades through competency rules, and it cannot be
  told apart from a real assessment later. Offer the review request instead (decision D6).
- Readiness is a composite the policy states: valid evidence, and (for technical competencies,
  when the policy says so) core proficiency as well.

### 3. Data model and performance

The plan's "MAX(expirydate) per (user, competency) at query time" works for one worker; it does
not survive a KPI bar over a contract of 2,000 workers × 25 competencies with a cohort filter,
paging and sorting. The proposal:

- **Typed metadata table**, one row per core evidence (`userevidenceid` UNIQUE), with indexed
  `expirydate`. Not `customfield_data`: the engine depends on these fields, so an admin must not
  be able to delete or retype them, cross-field rules (expiry ≥ issue, "never expires" excludes a
  date) need code anyway, and `customfield_data` has no value index. Free-form extra fields can
  come later through a customfield handler area of the companion (decision D3).
- **Materialised status**, one row per (user, competency) that has ever had evidence: status,
  current evidence, effective expiry, evidence count, and **`nexttransition`**, the next instant
  at which the status changes on its own (expiry − 60 d, expiry − 30 d, expiry + 1 d, a future
  issue date). The daily task selects `nexttransition <= now` (indexed) and recomputes only
  those: work proportional to change, not to population.
- **Pending is computed at query time**, never stored: required pairs come from the template
  (`competency_templatecomp`) × its users (`competency_plan.templateid`, active plans), LEFT JOIN
  the status table. A missing row is *Pending*. This keeps plan, template and cohort churn out of
  the engine entirely.
- **One query per screen region**, no N+1:
  - grid, in two steps (S0 spike): step 1 sorts only the narrow key (user, competency, sort
    value) of base pairs ⋈ status and keeps one page; step 2 reads that page's details (status,
    metadata, identity fields through one `core_user\fields::get_sql()`) by primary and unique
    keys. One statement with every join, which is what a system report emits, missed 300 ms;
  - KPI bar: the same base with `GROUP BY status`, plus a per-user `MIN(rank)` sub-select for the
    readiness rate;
  - row-level access: the user set is cut by
    `filter_users_with_capability_on_user_context_sql('moodle/competency:userevidenceview')`, one
    `IN` fragment, instead of a capability check per row;
  - history counts: read from the status row (`evidencecount`), not counted per row.
- Indexes: status (userid, competencyid) UNIQUE, (competencyid, status), (nexttransition);
  metadata (userid), (expirydate). Stage S0 seeds 10,000 workers × 30 competencies with a
  generator and reads the query plans on both PostgreSQL and MariaDB before the hub is built.

### 4. File API, PDF and ZIP

The flow is sound with three changes:

- **Two drivers, one builder.** `dossier_builder` writes into any `archive_writer`. Below a
  threshold (decision D9; default 10 workers and 100 MB of files) it streams through
  `get_stream_writer()`, so nothing is held in memory or on disk. Above it, an **ad hoc task**
  (`set_userid()` to the requester, so every capability check runs as them) writes through
  `get_file_writer()`, stores the ZIP in the requester's user context, sends a message, and a
  cleanup task deletes it after N days. Same pattern as `tool_dataprivacy`.
- **Batch the files.** One SQL over `{files}` for every evidence id in the dossier (component
  `core_competency`, filearea `userevidence`, the subjects' user contexts), then
  `get_file_instance()` per record, never `get_area_files()` in a loop.
- **PDF per worker, in memory**: TCPDF `Output('', 'S')`, one worker at a time, then
  `add_file_from_string()`. Use `freesans` for Portuguese diacritics; the logo comes from core's
  `logo` setting (or a plugin setting) through the file API.
- Names: `<ID>_<COMPETENCY idnumber>_<EXPIRY YYYY-MM-DD>_<original name>.<ext>` through
  `clean_param(PARAM_FILE)`, de-duplicated with a counter, inside one folder per worker. `<ID>`
  is the configured identifier: **idnumber by default, CPF only by setting** (decision D8).
- Add `manifest.csv` with the SHA-256 of every file, and store the PDF's hash on the dossier row:
  the verification page can then prove the PDF a contractor holds is the one issued.

### 5. Gaps and edge cases the plan does not cover

**Permissions**

- **Category managers cannot see evidence.** `userevidenceview` lives in user contexts, which are
  children of the system context, not of course categories. A contract manager with a category
  role sees nothing. Core's answer is `tool_cohortroles` (role in each team member's user
  context, synced by cron); `local_dimensions` already drives it ("cohort roles"). The README
  must say so, and the hub must say "0 workers you can view" instead of an empty grid.
- **Who verifies.** Core reviews user competencies, not evidence. A worker's upload is not proof
  until someone checks it. New capability `local/evidencehub:verify` (USER context, RISK_PERSONAL),
  a verification state on the metadata (pending, verified, rejected), and an append-only audit
  table. Regulatory policies count only verified evidence (decision D4).
- **Verified evidence edited afterwards.** When the owner edits a verified evidence, the
  `_updated` observer resets it to pending, unless the editor holds `verify` in that context.
- **Sensitive evidence (ASO).** Core serves the files to anyone with `userevidenceview` in the
  user context; a plugin cannot narrow that. So: a `sensitive` flag on the evidence type hides
  the details in the hub without `local/evidencehub:viewsensitive`, **excludes the file from
  dossiers by default** (the PDF says "ASO: fit, valid until …"), and the README tells sites
  that `userevidenceview` is effectively health-data access (decision D7).

**Data and dates**

- **Dates are calendar dates, not timestamps.** "Valid until 2026-12-31" must mean the same day
  in São Paulo and in Manaus. Store `issuedate`/`expirydate` as `YYYYMMDD` integers, compute
  "today" in the site timezone, and treat the expiry day as valid. Month arithmetic for default
  validity clamps (29 Feb + 12 months = 28 Feb).
- A future issue date is *not yet valid* (pending), with `nexttransition` at the issue date.
- One evidence linked to several competencies (an NR-10 card covering two) is one metadata row
  and several status rows.
- Evidence created in `tool_lp` (core form, no metadata) shows as **Pending — incomplete data**
  with a "Complete details" action; a `before_footer_html_generation` callback guarded by
  pagetype adds the same prompt on `tool_lp` evidence pages.
- Minimum hours per competency (NR recycling): an evidence under the policy's `minhours` does not
  count, and the drawer says why.
- Suspended users and completed plans are excluded from KPIs by default (a filter brings them
  back).

**Events the plan names, corrected**

- `\core\event\competency_evidence_created` is the **action log** on a user competency, not the
  prior-learning record. The engine listens to it for two reasons only: link/unlink of evidence
  (`descidentifier` `evidence_evidenceofpriorlearninglinked` / `...unlinked`, read from the row)
  and ratings, for the behavioural source.
- The evidence lifecycle events are `competency_user_evidence_created/_updated/_deleted`. On
  delete, core has already unlinked every competency (one `competency_evidence_created` each), so
  the observer recomputes the status rows that pointed at the evidence and then drops its
  metadata row.
- Also: `competency_deleted`, `competency_framework_deleted` and `user_deleted` clean up;
  the companion fires its own `evidence_details_updated`, `evidence_verified`,
  `evidence_rejected`, `validity_status_changed` (other: from, to, competencyid),
  `validity_policy_updated` and `dossier_exported` (other: template, subject count).
- Races: recompute takes a per-user lock and upserts against the UNIQUE index; a weekly
  reconciliation task recomputes everything in chunks to heal a missed event.

**Backup, restore, uninstall**

- Course backups never carry user evidence, so there is nothing to back up per course. The
  tables follow the site (database dump). Uninstall drops the tables and the dossier file area;
  core keeps its evidence untouched.

**Privacy and LGPD**

- Full provider: metadata, status, audit and dossier tables, the dossier file area, and the
  user preference for the hub filters; delete for all; `component_is_compliant()` asserted in
  the plugin's own tests.
- **Transparency to the worker**: a "Shared dossiers" list on the worker's own evidence page
  (when, by whom, for which contract), which is also the privacy export. LGPD art. 18 gives the
  data subject the right to know with whom data was shared.
- The verification page is public on purpose (it never calls `require_login()`, so it works
  under `forcelogin`), shows the issuance snapshot, the masked name ("J*** S***"), never CPF or
  files, and accepts only a 128-bit random code. Revoking a dossier marks it on that page.
- The README states the retention of each table and the legal basis a site would rely on
  (art. 7 II, legal obligation, for NR compliance), because the plugins directory asks for it.

## Ideas beyond the prompt

Ranked by value for the contract use case; the plan below includes the first five.

1. **Readiness matrix view.** Workers as rows, the contract's competencies as columns, one
   coloured cell each. It answers "who can go to the site tomorrow" in one screen, which the
   per-pair list cannot.
2. **Evidence sources, not just uploads.** The status engine takes a *source* interface. v1:
   uploaded evidence. Then: **last proficient rating** (core `competency_evidence` with a grade),
   which is what the behavioural type actually needs ("no assessment in this cycle"); then
   course completion or a `tool_certificate` issue with an expiry, so an NR course taken in
   Moodle counts without an upload.
3. **Verification queue.** A tab listing evidence awaiting verification, oldest first, with
   verify/reject in the drawer.
4. **Report builder datasource** "Evidence validity": admins build their own reports and use
   core schedules to e-mail a weekly CSV to each contract manager, with no code of ours.
5. **Readiness web service** (`local_evidencehub_get_readiness`) for gate systems, ERPs or a
   turnstile at the site: one call, yes/no plus the blocking competencies.
6. **Register recycling** from the drawer: a new evidence pre-linked to the same competencies,
   type and issuer copied, the old one kept as history.
7. **CSV import of legacy certificates** (user, competency idnumber, type, dates, issuer, file
   name in an uploaded ZIP), for the first load of a company's spreadsheet.
8. **Alerts to mentors.** Users holding `local/evidencehub:receivealerts` in the worker's user
   context (the parent/mentor pattern, fed by `tool_cohortroles`) get a weekly digest.
9. Later: an `availability_competency` condition on validity (block an activity until the NR is
   current), and validity pills in `block_dimensions`.

## Domain model

### Statuses

| Code | Label | Rule | Readiness |
|---|---|---|---|
| `valid` | Compliant | a counted evidence covers today, more than *alert1* days left | ready |
| `due60` | Due soon | covers today, ≤ *alert1* (default 60) days left | ready |
| `due30` | Due — critical | covers today, ≤ *alert2* (default 30) days left | ready |
| `expired` | Expired | counted evidence exists, none covers today | not ready |
| `unverified` | Pending — awaiting verification | evidence exists, none counted yet (unverified, incomplete, under `minhours`) | not ready |
| `missing` | Pending — no evidence | required by the template, no status row | not ready |

For non-blocking policies (technical, behavioural) `expired` reads "Recycling required" and the
readiness column shows *at risk* instead of *not ready*, by policy.

### The multi-evidence rule

For a user *u*, a competency *c* and today *t* (site timezone, calendar day):

```
E      = evidence linked to c, owned by u, with complete details,
         verified (if the policy requires it), hours >= minhours (if set)
covers = { e in E : e.issue <= t <= e.expiry }        (expiry = +infinity when "never expires")
current = argmax over covers of e.expiry               (ties: latest issue, then highest id)

status = covers non-empty   -> by days left of current: valid / due60 / due30
         E non-empty        -> expired   (effective expiry = max over E of e.expiry)
         any linked evidence-> unverified
         otherwise          -> missing (computed at query time)

nexttransition = earliest future instant among current.expiry - alert1, current.expiry - alert2,
                 current.expiry + 1 day, and the issue dates of not-yet-valid evidence
```

Older evidence is never overwritten: recycling adds a row, and the drawer's timeline shows every
evidence with its own interval.

### Policies by operational type

Set per framework, overridable per competency (`local_evidencehub_policy`). Not to be confused
with any educational taxonomy.

| Type | Source | Expiry | On expiry | Blocking |
|---|---|---|---|---|
| Regulatory (NR, health and safety) | uploaded evidence, **verified only** | strict, the evidence's own date | log + alert worker and mentors | yes: not ready |
| Technical / role-specific | evidence or last proficient rating; optional `requireproficiency` | the evidence's date, plus *grace* days | log + optional review request (core) | no: at risk |
| Behavioural / organisational | last proficient rating | cyclic: rating date + *cycle* months | log + review request | no: at risk |

## Data model (companion plugin)

| Table | Purpose | Key columns |
|---|---|---|
| `local_evidencehub_type` | Evidence types, admin-managed, seeded with the five of the prompt | shortname UNIQUE, name, defaultvalidity (months), requireexpiry, requireauthcode, sensitive, sortorder |
| `local_evidencehub_meta` | Details of one core evidence | userevidenceid UNIQUE, userid, typeid, issuedate, expirydate (YYYYMMDD, null = never), noexpiry, issuer, authcode, hours (decimal), verifystatus, verifiedby, timeverified, verifynote |
| `local_evidencehub_policy` | Validity policy | frameworkid, competencyid (one of), kind, requireverified, requireproficiency, minhours, alert1, alert2, gracedays, cyclemonths, onexpiry (log, review) |
| `local_evidencehub_status` | Materialised validity | userid + competencyid UNIQUE, status, metaid (current), effectiveexpiry, evidencecount, nexttransition, timechanged |
| `local_evidencehub_audit` | Append-only trail | metaid, action (created, details, verified, rejected, reset), userid, note, timecreated |
| `local_evidencehub_dossier` | One issued dossier per worker | code UNIQUE (32 hex), userid, templateid, exportid, createdby, snapshot (JSON), pdfhash, revoked, timecreated |
| `local_evidencehub_export` | Export job | requestedby, templateid, subjectcount, status, timecreated, timecompleted, timeexpires (S1: a count, not a JSON list of ids: the export's dossier rows say who it covered, and a deletion request could not clean ids inside a text column) |

The audit table exists because the log store's `loglifetime` may purge events a compliance audit
still needs; events still fire for the log.

### Capabilities

| Capability | Context | Archetype | Risk |
|---|---|---|---|
| `local/evidencehub:view` | course category | manager | RISK_PERSONAL |
| `local/evidencehub:verify` | user | manager | RISK_PERSONAL |
| `local/evidencehub:viewsensitive` | user | none | RISK_PERSONAL |
| `local/evidencehub:managepolicies` | course category | manager | RISK_CONFIG |
| `local/evidencehub:managetypes` | system | manager | RISK_CONFIG |
| `local/evidencehub:exportdossier` | course category | manager | RISK_PERSONAL |
| `local/evidencehub:receivealerts` | user | none | — |

Row access always also needs core's `userevidenceview` in the subject's user context (filtered
in SQL); editing evidence details needs core's `userevidencemanage[own]`.

## Decisions for the owner

**Approved by the owner on 2026-10-29: every row takes its recommendation.**

| # | Decision | Recommendation |
|---|---|---|
| D1 | Companion plugin or inside `local_dimensions` | Companion `local_evidencehub`, 5.3 only, no hard dependency; Hooks API bridge |
| D2 | Name | `local_evidencehub` ("Evidence hub"), or `local_dimensionsevidence` to read as family |
| D3 | Metadata storage | Typed table for the engine fields; customfield handler for extra fields in a later stage |
| D4 | Does unverified evidence count? | Per policy; regulatory default *no*, technical and behavioural default *yes* |
| D5 | The "Plan" filter | Plan **template** (the contract matrix); users = active plans from it; individual plans out of scope |
| D6 | On expiry of a technical competency | Log + optional review request; never an automatic proficiency demotion |
| D7 | Sensitive types (ASO) | Hidden without `viewsensitive`; file excluded from dossiers by default; README warning |
| D8 | Identifier in file names and PDF | idnumber by default; CPF profile field by setting; never in the verification page |
| D9 | Sync vs async export | Stream up to 10 workers / 100 MB, ad hoc task above; ZIPs kept 7 days |
| D10 | Verification page content | Snapshot at issuance + "revoked" flag; live status off by default (setting) |
| D11 | Alert recipients | Worker + `receivealerts` holders in the worker's user context; weekly digest |
| D12 | First evidence source beyond uploads | Last proficient rating (needed by the behavioural type) |

## Implementation plan

Stages land as separate pull requests on the companion's `main`, each green on
`mdl ci <repo> --matrix --behat` before merge, each with its mutation spec swept before the commit
that adds the gates. Estimates are agent hours including tests.

| Stage | Scope | Done when | Estimate |
|---|---|---|---|
| **S0 Spike** ✔ | Owner decisions; seed generator (10k × 30) and query plans on pgsql + MariaDB; side-sheet `core/modal` prototype on 5.3; TCPDF `freesans` + QR sample | **Done 2026-10-29**, results in [`spike/`](spike/README.md): criteria met with the two-step grid; KPIs cached by status generation | 6–10 h |
| **S1 Scaffold + data** (done 2026-10-30, uaiblaine/moodle-local_evidencehub `fc13456`) | `mdl new-plugin`; `install.xml` (7 tables), types seeded, capabilities, lang en + pt_br, privacy provider (full), marketplace readiness test, README skeleton | `component_is_compliant()` green; readiness test green | 12–18 h |
| **S2 Evidence details** | Own `dynamic_form` creating/updating core evidence **through `api::create_user_evidence()`** + metadata in one save; "Complete details" for tool_lp evidence (footer hook); worker self-service page; verify / reject actions; audit rows; events | Worker and manager paths tested both directions of every capability; edit-after-verify resets | 16–24 h |
| **S3 Engine** | `validity_calculator` (pure, no DB) with a data provider over the rule; `status_repository` upsert under a per-user lock; observers; `evaluate_validity` scheduled task over `nexttransition`; weekly reconciliation; policy admin UI (framework + competency override); `ACTION_LOG` write | Boundary tests: expiry today, alert days exact, leap day, timezone, future issue, multi-evidence ties; mutation sweep held | 20–30 h |
| **S4 Hub page** | `index.php` (site + category entry, locked context bar), KPI bar (one grouped query), filters (template, cohort, status chips, search), `hub_query` two-step grid (own pager, checkboxes, default view "needs attention" by expiry), download through `\core\dataformat::download_data()`, KPI cache keyed by status generation; report builder datasource | Row-level access tested with a category manager with and without `tool_cohortroles`; KPI = grid counts; spike queries re-measured on the plugin's real SQL | 22–32 h |
| **S5 Drawer** | Side sheet: current evidence, files, timeline of history, audit trail, actions (verify, reject, register recycling, download) | Behat: open, verify, timeline order | 12–18 h |
| **S6 Matrix view** | Workers × competencies grid, sticky first column, legend, same filters | Behat smoke; 200 × 25 renders without layout shift | 8–12 h |
| **S7 Dossier** | `dossier_builder` (archive_writer), PDF per worker with QR, manifest, naming, stream vs ad hoc task, `dossier` + `export` rows, pluginfile, cleanup task, public `verify.php`, message providers | ZIP contents asserted in PHPUnit; sensitive file excluded; verify page shows no identifier; task runs as requester | 24–36 h |
| **S8 Alerts + WS** | Message providers (due, expired, verification requested, dossier ready), weekly digest to mentors, `get_readiness` web service | Messages asserted with the message sink; WS allowlist returns | 10–16 h |
| **S9 Family bridge + release** | `local_dimensions` hook `competency_status_badges` (its own small PR on `main`), pills in the plan overview and tracker; docs, screenshots, `security-diff-read`, MDL Shield review, release | Hook tested in both plugins; matrix green; owner review | 10–16 h |

Total: **140–212 h**. S1 → S3 is the critical path; S4/S5/S6 can run in parallel once S3 lands,
and S7 needs S3 and S4's query layer.

### Test strategy

- The calculator is a pure class: most of the rule lives in PHPUnit data providers, not Behat.
- Every guard has its mutation; every "not shown" assertion has a control that shows it.
- Capability tests in both directions for: hub entry, row filter, sensitive details, export,
  verification, verify page.
- File-scanner suites copied from `local_dimensions`: colour tokens (family contract),
  Bootstrap markup, stylesheet scoping.
- Behat thin: hub smoke, drawer verify, dossier request (async path asserted by the message).

## Risks

| Risk | Mitigation |
|---|---|
| Category managers see an empty hub | Onboarding text + `tool_cohortroles` recipe in the README; the hub shows the count of workers hidden by permissions |
| Health data exposure through core's file serving | Cannot be narrowed by a plugin; documented, sensitive types excluded from dossiers, hub hides details |
| Status drift after a missed event | Weekly reconciliation; `timecomputed` shown in the drawer |
| Large dossier exhausts a web request | Threshold to the ad hoc task; streaming writer below it |
| Owner wants it inside `local_dimensions` after all | The companion's classes are namespaced and table-prefixed so a later merge is a rename, not a rewrite |
