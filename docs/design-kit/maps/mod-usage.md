# Field map — `MOD.USAGE` · Where the competency is used (as-is)

A modal opened by **one of the three counters** on the Competencies tab's detail card — Linked courses,
Linked activities or Linked learning plans. It shows, in a **striped table with column headers**,
where that competency appears. It is the simplest modal in the kit and the easiest to describe
wrongly, because **two of its rules are not visible in the Mustache**: the web service returns **all
three** lists and the template renders **only the one the user clicked**; and the modal **does not
navigate in place** — courses and activities open in a **new tab**, so the Central hub behind it is
never destroyed.

- **Mustache:** [`competency_usage_modal.mustache`](../../../templates/central/competency_usage_modal.mustache)
  (145) — the **body** only; the `Modal.create` is all in JS. Triggers in
  [`structure_detail_content.mustache`](../../../templates/central/structure_detail_content.mustache)
  (`:79-82`, `:91-94`, `:103-106`)
- **AMD:** [`structure.js`](../../../amd/src/central/structure.js) — the `USAGE_SECTIONS` map at
  `:1192-1196`, `openUsageModal` at `:1208-1233`, dispatch at `:1250-1252`. It uses `core/modal`
  (import `:30`), `core/templates` (`:36`), `getString` (`:40`) and `errors.js` (`notifyError`, `:35`)
- **WS:** `local_dimensions_competency_usage` (`db/services.php:90-97` →
  [`classes/external/competency_usage.php`](../../../classes/external/competency_usage.php), 161
  lines) — **one plugin WS, one call, three lists**. No core WS
- **CSS:** `styles.css:6998-7018` — three rules only: `margin-bottom: 0` on the table (`:7005-7007`),
  cells roomier than Boost's default (`padding: 0.625rem 0.75rem` + `vertical-align: middle`,
  `:7009-7013`) and the new-tab glyph small and quiet (`font-size: 0.75em; opacity: 0.75`,
  `:7015-7018`). Beyond that the body is **pure Bootstrap**
  (`table table-striped table-hover generaltable mb-0`, `font-monospace small text-muted`,
  `badge bg-success` / `badge bg-secondary`)
- **Behat:** none. There is no `.feature` touching the counters
- **Screen in the DS:** **none, on purpose.** It is a two-column table with no design decision of its
  own — it mirrors the *Manage participants* grid (the comment at `styles.css:7001` says so). The
  rules are all here

**Abbreviations used in the tables:** `mustache:` = `templates/central/competency_usage_modal.mustache`
· `js:` = `amd/src/central/structure.js` · `detail:` =
`templates/central/structure_detail_content.mustache` · `php:` =
`classes/external/competency_usage.php`.

> **Provenance, in three commits.** The template **was born in `6f9fc47`** ("Competencies tab parity —
> tree drag-and-drop, equal panes, usage counters", 2026-07-02). On the **same day**, `ec028d5`
> reworked it and that is where the `show*` flags were born and, with them, the "only the clicked
> section" rule. In `8d5500f` the three `<li>` lists became **striped tables with headers**, and
> courses and activities gained a new-tab link — the WS started exporting `url` (`php:100`, `:116`)
> and `execute_returns` started declaring it (`php:145`, `:152`).

## Triggers (on the Competencies tab, outside the modal)

The three doors **already have IDs** — they belong to the detail card and to
[`est-competencies.md`](est-competencies.md). This map **references** them, it does not re-mint them.

| ID (owner) | Label | Origin | `data-usage` | Rule |
| --- | --- | --- | --- | --- |
| `EST-DETAIL-COURSES` | Linked courses | `detail:79-82` (button) · `:85` (text) | `courses` (`detail:82`) | str `managecompetencies_linkedcourses` |
| `EST-DETAIL-ACTIVITIES` | Linked activities | `detail:91-94` (button) · `:97` (text) | `activities` (`detail:94`) | str `managecompetencies_linkedactivities` |
| `EST-DETAIL-PLANS` | Linked learning plans | `detail:103-106` (button) · `:109` (text) | `templates` (`detail:106`) | str `central_structure_linkedplans`. **The `data-usage` diverges from the name:** the UI says "plans", the dataset says `templates` — and the `USAGE_SECTIONS` map (`js:1195`) pairs `templates` → `central_structure_linkedplans` |

**The rule that governs all three:** the counter is only a `<button>` under `{{#linksclickable}}`;
without the flag it is an inert `<div>` (`detail:84-86`, `:96-98`, `:108-110`). `MOD.DETAIL` comes in
with `linksclickable: false` (`competency_detail.js:275`), so **this modal never stacks on top of that
one** — that is the mechanism, not a convention.

## Shell (assembled in JS, no Mustache)

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `MOD.USAGE-TITLE` | {section} — {name} | title | `js:1226-1227` | the str from `USAGE_SECTIONS[labelkey]` + `' — '` + `row.dataset.name` | the `title` receives the `getString` **Promise** (`Modal.create` accepts that); the em dash is a **literal in the JS**, it does not come from a string. It is the only thing that says **which** section is open on screen — the table's `<caption>` says it too, but it is `visually-hidden` |
| `MOD.USAGE-MODAL` | — | `core/modal` | `js:1225-1232` | `large: true`, `show: true`, `removeOnClose: true` | **plain** `core/modal` — no `footer`, no save/cancel: it is read-only. It closes only through core's header `.btn-close`, which **does get** the plugin's blue chip restyle (`styles.css:5074`) — this modal is **not** in the exclusion's `:not()`; `MOD.DETAIL` is. It does not carry the `sizetoggle` marker, so it also falls **outside** the `overflow` rule for the two dense modals (`0ee36cc`) |
| `MOD.USAGE-ROOT` | `[no label]` | region/root | `mustache:54` | `.local-dimensions-central-usage` | the modal's **only** CSS hook (`styles.css:7005-7018`). The body's only child; the three sections are siblings inside it |

## "Courses" section (`showcourses`)

A **two-column** table — *Course* (`central_usage_col_course`) and *Short name*
(`central_usage_col_shortname`), headers at `mustache:61-62` — with a `visually-hidden` `<caption>`
repeating the counter's label (`:58`).

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `MOD.USAGE-COURSES` | `[no label]` | table | `mustache:57-78` | `table table-striped table-hover generaltable mb-0` | renders under `{{#showcourses}}{{#hascourses}}` (`:55-56`) |
| `MOD.USAGE-COURSE-ROW` | {fullname} | row | `mustache:67-75` | `<tr>` · name `<td>` at `:68-73` | **it is a link** — `<a href="{{url}}" target="_blank" rel="noopener noreferrer">` with `fa-external-link` and a `visually-hidden` carrying the core str `opensinnewwindow` (`:70`). Under `{{^url}}` it degrades to raw text (`:72`), which only happens if the WS sends an empty `url`. `name` = `format_string($course->fullname)` in the course context (`php:94`), `url` = `/course/view.php?id=` (`php:100`) |
| `MOD.USAGE-COURSE-SHORT` | `[no label]` | shortname | `mustache:74` | `<td>` with `.font-monospace.small.text-muted` | it became **its own column** (no longer a suffix in the same cell). `format_string($course->shortname)` (`php:95`) |
| `MOD.USAGE-EMPTY-COURSES` | No courses linked. | empty state | `mustache:81` | str **`central_links_nocourses`** (`lang/en:183`) | `p.text-muted.small.mb-0`. **String asymmetry:** this empty state **reuses `MOD.LINKS`'s string**; the other two have their own (`central_usage_*`). It is not a bug — it is reuse — but whoever edits the `MOD.LINKS` string changes **this** text too |

## "Activities" section (`showactivities`)

Two columns — *Activity* (`central_usage_col_activity`) and *Course* (`central_usage_col_course`),
headers at `mustache:91-92`; `<caption>` at `:88`.

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `MOD.USAGE-ACTIVITIES` | `[no label]` | table | `mustache:87-111` | `table table-striped table-hover generaltable mb-0` | renders under `{{#showactivities}}{{#hasactivities}}` (`:85-86`) |
| `MOD.USAGE-ACT-ROW` | {module name} | row | `mustache:97-108` | `<tr>` · name `<td>` at `:98-103` | **it is a link**, the same shape as the courses (`:100`). The `url` comes from `$cm->url` (`php:110`, `:116`) and is an **empty string when the module has no view page** (`execute_returns` documents that at `php:152`) — in which case the `{{^url}}` at `:102` degrades to raw text. It is the only row in the modal that may not be clickable |
| `MOD.USAGE-ACT-COURSE` | {fullname} | the activity's course | `mustache:105` | `<td>` (the 2nd column) | it became **its own column**: the literal em dash that used to separate name and course in the same cell **no longer exists** — what labels it is the *Course* `<th>` at `:92` |
| `MOD.USAGE-ACT-SHORT` | `[no label]` | the course's shortname | `mustache:106` | `.font-monospace.small.text-muted.ms-1` | inside the course cell, next to the `coursename` |
| `MOD.USAGE-EMPTY-ACTIVITIES` | No linked activities. | empty state | `mustache:114` | str `central_usage_noactivities` (`lang/en:297`) | `p.text-muted.small.mb-0` |

## "Plans" section (`showtemplates`)

Two columns — *Learning plan* (`central_usage_col_plan`) and *Status*
(`central_usage_col_status`), headers at `mustache:124-125`; `<caption>` at `:121`.

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `MOD.USAGE-PLANS` | `[no label]` | table | `mustache:120-139` | `table table-striped table-hover generaltable mb-0` | renders under `{{#showtemplates}}{{#hastemplates}}` (`:118-119`) |
| `MOD.USAGE-PLAN-ROW` | {template shortname} | row | `mustache:130-136` | `<tr>` · name at `:131` | **not a link** — it is the only one of the three sections with no `url`: the WS exports none (`php:124-128`, `execute_returns` at `:154-158`). `format_string($template->get('shortname'))` (`php:126`) — **without `['context' => …]`**, unlike the courses (`php:94-95`) and the activities (`php:113`), which pass the course context. Here it falls back to `$PAGE`'s default context |
| `MOD.USAGE-PLAN-HIDDEN` | Hidden | badge | `mustache:134` | `.badge.bg-secondary` · str `hidden, tool_lp` | the *Status* column is **explicit in both states**: `{{#visible}}` renders a `badge bg-success` with the `visible, tool_lp` str (`:133`) and `{{^visible}}` renders this one. Both strings are **`tool_lp`**'s, not the plugin's |
| `MOD.USAGE-EMPTY-PLANS` | Not part of any learning plan. | empty state | `mustache:142` | str `central_usage_noplans` (`lang/en:298`) | `p.text-muted.small.mb-0` |

## Business rules (verified in the code)

### 1. The WS returns all three lists; the modal shows one

`openUsageModal` (`js:1208-1233`) makes **one** call to `local_dimensions_competency_usage`
(`js:1210-1213`) and passes the template **all three arrays whole** — `courses`, `activities` and
`templates` (`js:1217`, `:1220`, `:1223`) — along with three `show*` flags of which **exactly one is
`true`** (`js:1215`, `:1218`, `:1221`, each one a `labelkey === '…'`). `php:131` confirms the other
side: `return ['courses' => …, 'activities' => …, 'templates' => …]`, always all three.

In other words: **the cost is always three lists; the benefit is one.** Switching counter redoes the
whole call, because the modal is `removeOnClose` and there is no cache. It is no accident —
`ec028d5` was exactly the change that introduced the `show*` flags into a template that used to render
everything. What is left undone is for the WS to accept the section as an argument, not for the
template to discard it.

### 2. The rows navigate — but always in a new tab

Courses and activities are `<a href="…" target="_blank" rel="noopener noreferrer">` (`mustache:70`,
`:100`), with a decorative `fa-external-link` and a `visually-hidden` carrying the core str
`opensinnewwindow` — the pair that makes the target announceable. The `url` is built on the server,
not in the template: `/course/view.php?id=` (`php:100`) and `modinfo`'s `$cm->url` (`php:110`, `:116`),
declared as `PARAM_URL` in `execute_returns` (`php:145`, `:152`).

**The `target="_blank"` is the rule, not a detail.** Navigating **in the same tab** would destroy the
Central hub behind the modal — the tree, the selected row, the expansion, the scroll and the splitter
width. The new tab is what lets the modal be useful (take you there) at no cost in state.
`styles.css:7002-7003` records the glyph's visual intent: *"the new-tab glyph sits small and quiet
beside the course/activity name"*.

**The plans were left out, and it is consistent:** the WS exports no `url` for a template
(`php:124-128`) because **there is no public template page** — the destination would be the hub's own
Plans tab, that is, the page the user is already on. Recorded as a **deliberate** asymmetry.

### 3. An unknown section falls back to "courses", silently

`const labelkey = USAGE_SECTIONS[section] ? section : 'courses';` (`js:1209`). A `data-usage` carrying
junk (or missing) does **not** raise an error: it opens the courses list with the title "Linked
courses". Since the only emitter is the Mustache itself (`detail:82`, `:94`, `:106`), the branch is
defensive — but it is what catches a `data-usage` renamed on one side only.

### 4. An invisible activity is a consequence of an invisible course — structurally

`api::list_courses_using_competency` (`php:92`) already comes filtered by the caller's per-course
capabilities (comment at `php:89`). The activities are gathered **inside** that loop (`php:105-118`),
`get_fast_modinfo` per course (`php:104`). So **the activity list is a function of the course list**:
a course the user cannot see contributes no activities — not because of an activity filter, but
because the loop never gets there. A `$cm` missing from `modinfo` is skipped silently
(`php:106-109`).

**The plans do not follow that rule.** `api::list_templates_using_competency` (`php:123`) goes in with
no per-template filter — the only gate is the global one, up top: `require_capability(
'moodle/competency:competencyview')` in the **system context** (`php:71-73`), plus a
`competency_framework::can_read_context` in the **framework**'s context (`php:80-87`), which throws
`required_capability_exception` when it fails. That is why `visible` is exported (`php:127`) and
becomes the *Status* column: whoever gets here **does see** the hidden template, and the badge is what
tells the truth.

### 5. The title names the section; the `<caption>` repeats it for screen readers only

Opened on "Activities", the table is indistinguishable from the "Courses" one by shape (same classes,
two columns) — what changes are the `<th>`s. What names the section **on screen** is `MOD.USAGE-TITLE`
(`js:1226-1227`), which depends on `USAGE_SECTIONS[labelkey]`; and each table repeats the same label in
a `<caption class="visually-hidden">` (`mustache:58`, `:88`, `:121`), which names the table for
assistive technology without duplicating visible text. The counter's label and the modal's title come
from the **same string** (the comment at `js:1191` records this: "lang key of its title (also the
counter label)"), so they never diverge.
