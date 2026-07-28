# Learner kit — Learner Views (local_dimensions)

An **as-is record** of the two learner-facing screens of the `local_dimensions`
plugin — **Competency tracker** (`view-competency.php`) and **Full plan overview**
(`view-plan.php`) — plus the shared hero and filter chips. Every file describes
what the code renders **today**; where a design was drawn but never built, the
screen says so and cites the evidence of absence. It follows the **admin-kit
method**: every `.html` is a self-contained preview carrying a `@dsCard` marker on
its first line, and every significant element gets an **ID badge** (`.idb`) in a
caption line for review.

Each screen inlines one canonical `--lk-*` **token block** — the same values
`styles.css` ships — and writes its markup against `var(--lk-*)`, so a value change
lands in one place across the whole kit.

Mirrored in two places, kept in sync:
- this `docs/learner-kit/` folder (the source of truth in the repo);
- the **"Learner Views"** project on Claude Design (`claude.ai/design`).

## Foundations
| File | What it is |
|---|---|
| `tokens.html` | The real `styles.css` palette tokenized as `--lk-*`: Moodle DS core, the kept rules orange, the decisions taken during the migration, the evidence/status pills, plus radii and type. Also records the one survivor of the old palette — the Google-blue search-focus glow at `styles.css:2366`. |
| `token-migration.md` | Repo-only companion to `tokens.html`: the record of the **completed** Material/Google → Moodle DS migration — the two commits that landed it, the value-for-value mapping with the sites where each new value can be read today, what was kept on purpose, and the two residues (the search glow, the loose neutrals). Changes no runtime code. |
| `core-issue-notes.md` | Repo-only. The **core** finding behind the stale-evidence callout: a competency rule has no override control of its own, so a rule completion cannot lift an earlier manual rating. Traced and re-verified in core, not changed there — the plugin ships the "Send for review" mitigation instead. |

## Shared components (`screens/`)
Components that appear in both views, so they carry **both** `TRK-*` and `OVW-*` IDs.

| File | Component | Source |
|---|---|---|
| `screens/hero.html` | Hero — title, collapsible description with a masked fade, glass "See more" pill, open⇄slim collapse handle, bg colour/image/overlay, plan due-date card | `hero_header.mustache` + `hero_collapse.js` |
| `screens/chips.html` | Filter chips — groups, pressed state, scroll paddles/indicator, clear. They render inside the **Filter panel** opened from the toolbar, not inline under the hero | `chip_filters` + `filter_tabs_nav` |

## Tracker (TRK) screens (`screens/`)
The "Competency tracker" mode — a grid of the courses linked to one competency.
`view_competency_page` → `view_competency.mustache`; a `get_courses_completion_status`
batch tags each card and fills the completion tabs, then each card's body loads over
AJAX (`competency_view.js` → `get_course_progress` → `progress_card_body.mustache`).

| File | Screen |
|---|---|
| `screens/trk-tracker.html` | Toolbar, course grid and progress timeline (done / ring / circle / info / section), completed seal, Continue |
| `screens/trk-card-states.html` | Card body states — spinner, JS error + Retry, completion off, payload error |
| `screens/trk-locked.html` | Locked card — self-enrol, learn-more, blocked, "Opens" date, blurred sections, plus the two compact card shapes |
| `screens/trk-empty.html` | Empty states — no linked courses (neutral chip) / competency not found (warning chip) |

## Plan (OVW) screens (`screens/`)
The "Full plan overview" mode — every competency in the plan, laid out either as an
accordion (list mode) or as a card grid whose cards open a detail modal.
`view_plan_summary_page` → `view_plan_summary.mustache`; `accordion.js` builds the
expanded detail on first open from three web services.

| File | Screen |
|---|---|
| `screens/ovw-overview.html` | Plan overview — sticky toolbar (favourites · completion · search · sort · filter · layout), list ⇄ grid, ghost card, paged detail modal |
| `screens/ovw-detail-tabs.html` | Detail — the tab strip (Related content │ Description │ Progress │ Rules): order, gating, which tab leads, keyboard |
| `screens/ovw-detail-courses.html` | Detail — Related content (the leading tab; cards with a decisive outcome badge, activities drawer, access states, footer link to the tracker) |
| `screens/ovw-detail-desc.html` | Detail — Description / Path / Related / Taxonomy: description-first single column, clickable relation pills, footnote carrying the path and the taxonomy definition modal |
| `screens/ovw-detail-progress.html` | Detail — Progress tab (assessment card, decisive result strip, journey list, submit) |
| `screens/ovw-detail-evidence.html` | Detail — the evidence pieces **inside** the Progress tab: decisive strip, 4-column journey row, the 7 types, short date, truncated grade chip + tooltip |
| `screens/ovw-detail-rules.html` | Detail — Rules tab (rule as a sentence, quiet progress line + 6px bar, All/Required pills, child rows, missing-mandatory) |
| `screens/ovw-accordion-states.html` | Accordion load states — per-item spinner placeholder, hidden content sink, `alert-danger` on failure |
| `screens/ovw-modal.html` | Evidence detail modal — prev/next pager, type badge, one labelled section per present field |
| `screens/ovw-empty.html` | Empty & no-results states — no competencies in the plan, and filters matching none (with a Clear-filters action) |

`ovw-detail-status.html` was **folded into** `ovw-detail-progress.html`, which owns
the merged tab; its IDs (`OVW-STATUS`, `OVW-STATUS-SCALE`) carry over unchanged. The
tab strip it also carried (`OVW-TAB-NAV`, `OVW-TAB-KBD`) moved to
`ovw-detail-tabs.html`.

## Borrowed from the admin kit
| File | Why it is used here |
|---|---|
| `../design-kit/tooltip.html` | The truncation↔tooltip pair — used by the evidence journey's grade chip and the assessment card's rating level. It lives in the admin kit because that folder is the single source of the Design System's foundations; this kit references it rather than copying it, and `styles.css:7357` points back at it from the code. |

## ID convention
Format `PREFIX-SECTION[-NN]`, **stable** across re-syncs. Prefixes: **`TRK`**
(view-competency) and **`OVW`** (view-plan). Sections in use: `HERO`, `CHIP`,
`FILTER`, `GRID`, `CARD`, `TL`, `LOCK`, `CONTINUE`, `EMPTY`, `FAB`, `SORT`
(tracker); `HERO`, `TOOLBAR`, `BAR`, `FAV`, `SORT`, `VIEW`, `GRID`/`CARD`, `GHOST`,
`PCTMODE`, `CHIP`, `FILTER`, `ACC`, `TAB`, `STATUS`, `PROG`, `DESC`/`PATH`/`REL`/
`TAX`, `EVID`, `RULES`, `CRS`, `MODAL`, `EMPTY` (plan). Every interactive element
and every meaningful static region gets an ID; pure layout wrappers do not. A
**shared element** (hero, chips) is shown once but **referenced under both
prefixes** — e.g. the hero title is `TRK-HERO-TITLE` and `OVW-HERO-TITLE`. An ID is
kept even when the element it named was retired or was never built, so the record
of why survives (`TRK-CHIP-COMP`, `TRK-SORT`, `OVW-RULES-CHIP`).

## Field maps (`maps/`) — repo-only
An as-is inventory per screen: each element with its **stable ID**, label, type,
**source** (`mustache:line` or the `amd`/renderable that builds it), the data it
carries, and its business rule. Each map ends with its own **Pending** and (for the
plan) **Dropped** lists. These stay in the repo; they are not synced to Claude Design.

| File | Screen |
|---|---|
| `maps/viewcompetency.md` | `TRK` · Competency tracker |
| `maps/viewplan.md` | `OVW` · Full plan overview |

## Palette
The kit's `--lk-*` token block **is** the shipped palette: the Material/Google →
Moodle DS migration has landed, with the **rules orange** (`#fd7e14`) and the
**rated amber** (`#e5a100`) deliberately kept — see `token-migration.md` for what
moved and what is left over.

## Pending
What the kit records as designed but **not built** (each screen carries the
evidence of absence next to the surface it belongs to):

- **Rules-tab enrolment filter** — gating a rule child whose linked courses the
  learner cannot reach (`ovw-detail-rules.html`). Blocked on arithmetic:
  `build_points_rule_data()` sums `earnedpoints` over the very list it emits, so
  hiding rows would leave a score the remaining rows cannot explain.
  (`maps/viewplan.md` files this one under **Dropped**, not Pending.)
- **Tracker sort control** — "Completed first / Name / Recent" over the grid
  (`trk-tracker.html`, `maps/viewcompetency.md`). Sorting exists on the plan
  overview only; the tracker's order is fixed server-side.
- **Accordion skeleton load states** — a pulsing skeleton in place of the lone
  spinner, and a retry-able error in place of the flat `alert-danger`
  (`ovw-accordion-states.html`). There is no recovery path on the plan overview.
- **Tracker card-state skeletons** — a skeleton loader and friendly iconized
  error/disabled states (`trk-card-states.html`). `renderErrorState` still builds a
  `text-danger` line plus a Retry that swaps in the same spinner.
- **Evidence-modal identity row and footer** — the one-line type/title/scale row,
  and moving the author and Open-activity link into a compact footer
  (`ovw-modal.html`). The template still emits a badge over stacked labelled
  sections; `evm-` has zero hits in it and in `styles.css`.
- **Hero due-date urgency** — a due-date card leading with "Due in {N} days" and
  shifting neutral → amber → red (`hero.html`). The card is a fixed label plus a
  formatted date, and no relative-time string exists in either lang file.

The screens carry further, smaller pending notes inline (rules status chips and
sub-rule modal, the structured scale-level modal, the description pane's bordered
"See more", an `OUTCOME_EVIDENCE` badge, activities whose course is not itself
linked); the two maps' own Pending sections are the fuller list.

## Syncing with Claude Design (DesignSync)
The **HTML previews** (`tokens.html` + `screens/*.html`) and this `README.md` sync
up to the **"Learner Views"** Claude Design project; the **Markdown companions**
(`token-migration.md`, `core-issue-notes.md`, `maps/*.md`) stay repo-only.

Workflow: create or reuse the project → `finalize_plan` (it requires both `writes`
and `deletes`, even if `[]`, with `localDir` set to this folder) → `write_files`
with each `localPath`. **Before overwriting**, call `get_file` on the target — the
project's `updatedAt` does **not** move when `write_files` writes, so it is not a
reliable guard; `get_file` is what detects a user edit made in Design before you
clobber it.

## Code mapping
- `view-competency.php` → `view_competency_page` → `view_competency.mustache`
  (`hero_header`, `chip_filters`, `course_grid` → `course_card` →
  `progress_card_body` via AJAX, preceded by the `get_courses_completion_status`
  batch).
- `view-plan.php` → `view_plan_summary_page` → `view_plan_summary.mustache`
  (`hero_header`, `chip_filters`); `accordion.js` builds the expanded detail from
  `get_user_competency_summary_in_plan`, `get_competency_courses` and
  `get_competency_rule_data` (the last one lazily, on first Rules-tab activation).
- Styles: `styles.css` runs the learner views from `:477` (Hero header) to `:4891`
  (the chip-filter clear button) of a 7434-line file, light mode; the hub's CSS
  resumes at `:4893`. Two admin-only blocks sit inside that span — the custom-SCSS
  textarea (`:3976-4009`) and the icon picker (`:4438-4465`) — and the shared
  tooltip primitive lives at the end of the file (`:7356-7434`).
- Shared reader trait `customfield_reader` (`classes/output/`), used by both
  renderables.
