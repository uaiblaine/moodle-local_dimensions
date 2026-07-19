# Learner kit — Learner Views (local_dimensions)

An **as-is │ to-be visual replica** of the two learner-facing screens of the
`local_dimensions` plugin — **Competency tracker** (`view-competency.php`) and
**Full plan overview** (`view-plan.php`) — plus the shared hero and filter chips.
It follows the **admin-kit method**: every `.html` is a self-contained preview
carrying a `@dsCard` marker on its first line, and every significant element gets
an **ID badge** (`.idb`) in a caption line for review.

Each screen inlines one canonical `--lk-*` **token block** and renders the markup
twice — an **as-is** panel (`.kit`, the Material/Google skin the real `styles.css`
ships) and a **to-be** panel (`.moodle`, which overrides only the tokens that
migrate to Moodle DS). The markup is written once with `var(--lk-*)`; only the
token values differ between the two panels.

Mirrored in two places, kept in sync:
- this `docs/learner-kit/` folder (the source of truth in the repo);
- the **"Learner Views"** project on Claude Design (`claude.ai/design`).

## Foundations
| File | What it is |
|---|---|
| `tokens.html` | The real `styles.css` palette tokenized as `--lk-*`, shown two-column (as-is │ to-be). The `.moodle` override block **is** the proposed CSS change. Covers neutrals, status, rules orange, pills, the 6 evidence types, taxonomy accents, hero, plus radii/type. |
| `token-migration.md` | Repo-only companion to `tokens.html`: the Material/Google → Moodle DS checklist — which tokens **migrate**, which are **kept** (the rules orange), which are **under review**, and the exact `styles.css` line numbers each touches. The annotation surface for the later migration slice; changes no runtime code. |

## Shared components (`screens/`)
Components that appear in both views, so they carry **both** `TRK-*` and `OVW-*` IDs.

| File | Component | Source |
|---|---|---|
| `screens/hero.html` | Hero — title, collapsible description, bg colour/image/overlay, custom text, plan due-date | `hero_header.mustache` |
| `screens/chips.html` | Filter chips — groups, pressed state, scroll paddles/indicator, clear | `chip_filters` + `filter_tabs_nav` |

## Tracker (TRK) screens (`screens/`)
The "Competency tracker" mode — a grid of the courses linked to one competency.
`view_competency_page` → `view_competency.mustache`; card progress via AJAX
(`competency_view.js` → `get_course_progress` → `progress_card_body.mustache`).

| File | Screen |
|---|---|
| `screens/trk-tracker.html` | Grid & progress timeline (done / ring / circle / info / section) |
| `screens/trk-card-states.html` | Card body states — loading, error + retry, completion-disabled, inline error |
| `screens/trk-locked.html` | Locked card — blocked / learn-more / date / dashed border / section lock |
| `screens/trk-empty.html` | Empty states — no linked courses / missing competency id |

## Plan (OVW) screens (`screens/`)
The "Full plan overview" mode — an accordion of every competency in the plan.
`view_plan_summary_page` → `view_plan_summary.mustache`; the expanded detail is
built client-side by `accordion.js`.

| File | Screen |
|---|---|
| `screens/ovw-overview.html` | Plan overview — filter bar (tabs + counts, search, chips) + collapsed accordion |
| `screens/ovw-detail-status.html` | Detail — Status tab (tab strip + 2-col status grid) |
| `screens/ovw-detail-desc.html` | Detail — Description / Path / Related / Taxonomy |
| `screens/ovw-detail-evidence.html` | Detail — Evidence tab (6-type slider, nav, detail button, submit, empty) |
| `screens/ovw-detail-rules.html` | Detail — Rules tab (info box, orange progress bar, All/Required filter, children, missing-mandatory alert) |
| `screens/ovw-detail-courses.html` | Detail — Linked courses (scrollable cards, scroll arrows, 100% check) |
| `screens/ovw-accordion-states.html` | Accordion load states — per-item spinner and load error |
| `screens/ovw-modal.html` | Evidence detail modal (type badge, description, note, link, grade, author, date) |
| `screens/ovw-empty.html` | Empty & no-results states |

## ID convention
Format `PREFIX-SECTION[-NN]`, **stable** across re-syncs. Prefixes: **`TRK`**
(view-competency) and **`OVW`** (view-plan). Sections include `HERO`, `CHIP`,
`GRID`/`CARD`/`TL`/`LOCK` (tracker); `BAR`, `ACC`, `TAB`, `STATUS`,
`DESC`/`PATH`/`REL`/`TAX`, `EVID`, `RULES`, `CRS`, `MODAL` (plan). Every
interactive element and every meaningful static region gets an ID; pure layout
wrappers do not. A **shared element** (hero, chips) is shown once but **referenced
under both prefixes** — e.g. the hero title is `TRK-HERO-TITLE` and
`OVW-HERO-TITLE`.

## Field maps (`maps/`) — repo-only
An as-is inventory per screen: each element with its **stable ID**, label, type,
**source** (`mustache:line` or the `amd`/renderable that builds it), the data it
carries, and its business rule. These stay in the repo; they are not synced to
Claude Design.

| File | Screen |
|---|---|
| `maps/viewcompetency.md` | `TRK` · Competency tracker |
| `maps/viewplan.md` | `OVW` · Full plan overview |

## Token-parity note
The **as-is** panels reproduce the real `styles.css` palette — a Material/Google
skin layered over Boost. The **to-be** panels apply the `.moodle` override to reach
Moodle DS parity. The **rules orange** (`--lk-orange` `#fd7e14` and its gradient/
alpha variants) is deliberately **kept** in both panels. This kit is
documentation only: the actual `styles.css` migration (turning the `.moodle`
values into the new literals/vars at the lines listed in `token-migration.md`) is a
**later slice** — a visual-parity change with no markup/JS/behaviour edits, though
it will still need a `version.php` cache-revision bump when it lands.

## Syncing with Claude Design (DesignSync)
The **HTML previews** (`tokens.html` + `screens/*.html`) and this `README.md` sync
up to the **"Learner Views"** Claude Design project; the **Markdown companions**
(`token-migration.md`, `maps/*.md`) stay repo-only.

Workflow: create or reuse the project → `finalize_plan` (it requires both `writes`
and `deletes`, even if `[]`, with `localDir` set to this folder) → `write_files`
with each `localPath`. **Before overwriting**, call `get_file` on the target — the
project's `updatedAt` does **not** move when `write_files` writes, so it is not a
reliable guard; `get_file` is what detects a user edit made in Design before you
clobber it.

## Code mapping
- `view-competency.php` → `view_competency_page` → `view_competency.mustache`
  (`hero_header`, `chip_filters`, `course_grid` → `course_card` →
  `progress_card_body` via AJAX).
- `view-plan.php` → `view_plan_summary_page` → `view_plan_summary.mustache`
  (`hero_header`, `chip_filters`); `accordion.js` builds the expanded detail
  (tabs, evidence, rules, courses, modal).
- Styles: `styles.css` (roughly lines 462–3340, light mode). Shared reader trait
  `customfield_reader`.
