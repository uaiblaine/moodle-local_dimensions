# Learner kit — token parity + as-is↔to-be slicing

**Date:** 2026-07-18 · **Scope:** `docs/learner-kit/` (the learner-facing screens `view-plan.php` +
`view-competency.php`). No runtime code changes in this pass.

## Goal

Bring the *learner kit* (visual replica of the two learner screens) to the same rigor as the
*admin kit*:

1. **Trustworthy maps** — fix 3 audited inaccuracies and add **all ~29 currently-unmapped states**
   (user decision: maximum coverage, one row per state).
2. **Slice by screen+state** — replace the 2 monolith screens with ~15 files, each holding
   **as-is │ to-be panels side by side** (convention inherited from the admin kit `screens/`).
3. **Token parity** — the to-be migrates the Material/Google layer to Moodle tokens (Boost/BS),
   **keeping the orange** of the completion rules. Delivered as a migration table +
   two-column `tokens.html`. The **real** `styles.css` migration is a later slice (out of scope here).

## Out of scope (future slice)

- Editing runtime `styles.css` / `amd` / `.min`, bumping `version.php`. That becomes its own slice,
  driven by the `token-migration.md` this pass produces, and only after annotation.

## Release hygiene (done in this pass)

`docs/` is now versioned (`.gitignore`), but the release (`git archive HEAD`) strips it via
`.gitattributes` (`export-ignore`): `docs`, `.github`, `.moodle-plugin-ci.yml`, `CLAUDE.md`,
`.gitignore`, `.gitattributes`. `tests/` is kept (Moodle convention). Verified with `git archive`.

## Language

Everything committed (docs, maps, screens, tokens, code, commit messages) is **English**. Only the
chat is bilingual.

## Factual map fixes (audited)

| ID | Map says | Fix |
|---|---|---|
| `TRK-LOCK-BORDER` | dashed SVG border "only if `animatelockedborder`" | border is **always** on a locked card (`competency_view.js:149` + `styles.css:749`); the setting only enables the marching-ants **animation** (`js:331-333` + `@keyframes` `styles.css:765`) |
| `OVW-CRS` (data) | `list_courses_using_competency` (core tool_lp) | actually calls the plugin WS `local_dimensions_get_competency_courses` (SQL on `competency_coursecomp`, `visible==1`); the WS docblock is also stale |
| TRK settings note | `percentagedisplaymode (hover/inline)` | real options are **fixed / hover(default) / hidden** — "inline" does not exist |
| `OVW-TAX` (nuance) | "12 color accents" | 12 keys, 11 icons (behavior≈behaviour); the `taxonomy-card-<type>` classes **do not exist** in the CSS — the accent comes from the icon only |

## New states → every one becomes a row (maximum coverage)

**TRK (12):** `TRK-CARD-LOADING` ("Loading progress…" spinner, the initial state of every card) ·
`TRK-CARD-ERROR` (error + Retry) · `TRK-CARD-NOCOMPLETION` ("Completion disabled.") ·
`TRK-CARD-ERROR-INLINE` (`{{#error}}` alert-danger from the payload) · `TRK-TL-LOCK` (per-section
lock marker, top of the marker priority) · `TRK-CHIP-NAV` (scroll paddles + indicator + arrow-key
nav, `filter_tabs_nav`) · `TRK-CHIP-ACTIVE` (chip `aria-pressed` state) · `TRK-TL-INFO-TIP`
("No completion tracking" tooltip) · `TRK-TL-SEC-SPAN` (`<span>` fallback when a section has no URL) ·
`TRK-CARD-A11Y` (aria-label "View course: {n}") · `TRK-TL-RING-ARIA` (ring role=progressbar) ·
`TRK-FAB` (Return-to-Plan FAB — **reference** to the global component, does not coin a new ID).

**OVW (17):** `OVW-ACC-LOADING` (per-item spinner) · `OVW-ACC-ERROR` (per-item alert-danger) ·
`OVW-RULES-LOADING` (Rules-tab spinner) · `OVW-RULES-ALERT` (missing mandatory: warning triangle +
striped bar + notice) · `OVW-EVID-NAV` (slider prev/next, disabled at edges) · `OVW-EVID-EMPTY`
("no evidence") · `OVW-BAR-SEARCHCLEAR` (search clear X button) · `OVW-CHIP-CLEAR` (clear chips +
group label) · `OVW-ACC-LIVE` (accordion `aria-live` region) · `OVW-CRS-NAV` (scroll arrows +
100% check) · `OVW-DESC-TOGGLE` (collapsible show-more/less toggle) · `OVW-TAB-KBD` (roving-tabindex
on the tab strip) · `OVW-RULES-ARIA` (progressbar + child sr-only labels) · `OVW-PCTMODE`
(`percentagemode-*` wrapper) · `OVW-HERO-BG` (refine: custom text colour + image+overlay, currently
under-documented) · `OVW-FILTER-NORESULTS` (**absent in code** — to-be only: proposed "no results"
state when search/chip filters empty the list).

## Slicing (files to generate) — retire the 2 monoliths

Each file: one `@dsCard` (line 1), **as-is │ to-be** panels, `.idb` badges per element, stable IDs.
A shared element is **referenced**, not re-coined ("one element, one ID"). As-is = faithful replica
(Material, untouched); to-be = Moodle tokens + orange kept.

### Shared (group "Shared")
| File | Covers |
|---|---|
| `hero.html` | `*-HERO-TITLE/DESC/BG`, `OVW-HERO-DUEDATE`, `OVW-HERO-BG` (colour/image/overlay/custom text), `OVW-DESC-TOGGLE` |
| `chips.html` | filter bar: `TRK-CHIP-*` / `OVW-CHIP` + `-ACTIVE` + `-NAV` + `-CLEAR` + group label |

### TRK · Tracker (group "Learner screens · Tracker (TRK)")
| File | Covers |
|---|---|
| `trk-tracker.html` | hero+chips (ref), `TRK-GRID`, `TRK-CARD(-HEAD/-A11Y/-PROG)`, loaded timeline `TRK-TL-DONE/RING/RING-ARIA/CIRCLE/INFO/INFO-TIP/SEC/SEC-SPAN` |
| `trk-card-states.html` | card-body gallery: `TRK-CARD-LOADING/-ERROR/-NOCOMPLETION/-ERROR-INLINE` |
| `trk-locked.html` | `TRK-LOCK(-ICON/-LEARNMORE/-MSG/-DATE/-BORDER)` + `TRK-TL-LOCK` |
| `trk-empty.html` | `TRK-EMPTY-NOCOURSES`, `TRK-EMPTY-NOCOMP` |

### OVW · Plan (group "Learner screens · Plan (OVW)")
| File | Covers |
|---|---|
| `ovw-overview.html` | hero+duedate (ref), `OVW-BAR-TABS/-SEARCH/-SEARCHCLEAR`, chips (ref), `OVW-ACC-ITEM/-TITLE/-SUBLINE/-TOGGLE/-LIVE`, `OVW-PCTMODE` |
| `ovw-accordion-states.html` | `OVW-ACC-LOADING`, `OVW-ACC-ERROR` |
| `ovw-detail-status.html` | `OVW-TAB-NAV`, `OVW-TAB-KBD`, `OVW-STATUS` |
| `ovw-detail-desc.html` | `OVW-DESC(-TOGGLE)`, `OVW-PATH`, `OVW-REL`, `OVW-TAX` |
| `ovw-detail-evidence.html` | `OVW-EVID-CARD/-DETAIL/-NAV/-SUBMIT/-EMPTY` |
| `ovw-detail-rules.html` | `OVW-RULES-INFO/-PROGRESS/-ARIA/-FILTER/-CHILD/-ALERT/-LOADING` |
| `ovw-detail-courses.html` | `OVW-CRS`, `OVW-CRS-NAV` |
| `ovw-modal.html` | `OVW-MODAL` |
| `ovw-empty.html` | `OVW-EMPTY`, `OVW-FILTER-NORESULTS` (to-be) |

**Total:** 2 shared + 4 TRK + 9 OVW = **15 screens** + `tokens.html` (2-col) +
`token-migration.md` + 2 rewritten maps + updated `README.md`.

## Token migration (as-is Material/Google → to-be Moodle)

Good news: the accent is already `var(--dimension-custombgcolor, var(--primary, #667eea))` in most
places — only the **literal fallback** changes. Migration is literal swaps, not a refactor.

| As-is (migrate) | Role | To-be Moodle |
|---|---|---|
| `#667eea` (+`#764ba2`, gradient) | accent: tab underline, focus ring, FAB, course placeholder | `var(--primary)` `#0f6cbf` |
| `#1a73e8` | Google blue: active tab/counter, search focus | `var(--primary)` `#0f6cbf` |
| `#f1f3f4`, `#e8eaed` | pill platter / counter (grey) | `#e9ecef` |
| `#5f6368`, `#9aa0a6` | Google grey text/icon | `#6c757d` |
| `#e8f0fe` | active-counter background | `#cfe2ff` |
| `#e1e3e6`, `rgb(26 115 232/15%)` | search border + focus glow | `#dee2e6`, `rgba(primary,.15)` |
| `#f9fafb`, `#212121` | Material card/text (view-competency) | `#f8f9fa`, `#212529` |

**Keep (rules orange):** `#fd7e14`, `#ff922b`, `#e8590c` + `rgba(253 126 20/…)` variants +
`linear-gradient(90deg,#fd7e14,#ff922b)`.

**Already-Moodle (no change):** the Bootstrap semantic set (evidence/status greens/yellows/blues)
+ shared neutrals.

**To-be decisions to annotate (flagged in `token-migration.md`):**
- One-off greys/blues: `#356df3` (taxonomy label → `--primary`?), `#005fcc` (focus ring → unify
  `--primary`?), `#e5a100` (amber "rated" → warning token?), Stripe shadow tint
  `rgb(50 50 93/25%)` (→ neutral shadow?), the Tailwind modal-note trio `#fef9c3/#713f12/#eab308`
  (→ BS warning-light?), and ~10 loose greys (`#e5e0e0`, `#f1f3f5`, `#fafafa`, `#333`, `#555`,
  `#b0b2b5`…).
- **Inconsistency to unify in passing:** the CSS mixes BS4 green `#28a745` and BS5 green `#198754`
  for the same "success" role.
- **Keep (not learner UI):** the Catppuccin theme of the custom-SCSS editor
  (`#1e1e2e/#cdd6f4/#45475a/#89b4fa`).

## Conventions (from the admin kit)

- `@dsCard` on line 1 (`group`, `name`, `subtitle`). `.idb` = per-element ID badge.
- IDs `PREFIX-SECTION[-NN]`, stable, do not change on reorder. Prefixes `TRK`/`OVW`.
- As-is faithful to shipped output (Boost light mode); to-be = proposal. Panels side by side.
- Mirrored in Claude Design (project "Learner Views") via DesignSync; `maps/*.md` stay repo-only.

## Execution plan

1. Generate the 15 `.html` (as-is│to-be), `tokens.html` (2-col), `token-migration.md`.
2. Rewrite `maps/viewcompetency.md` + `maps/viewplan.md` (3 fixes + all new rows).
3. Update `README.md` (new sliced structure + tokens).
4. Adversarial verification: every to-be reproduces its as-is (same structure/states), every `.idb`
   points at a real element, every new map row matches the code.
5. Sync with Claude Design (optional, on command).
6. Commit the slice (on command) — includes `.gitignore`/`.gitattributes`/docs.
