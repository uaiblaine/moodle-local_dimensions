# Token migration — learner views (Material/Google → Moodle DS)

> **Status: IMPLEMENTED (2026-07-20).** The 13 clear-cut tokens + the 6 "review" decisions below were
> applied to the real `styles.css` as a value-only slice (50 literal swaps), plus the two colour-picker
> **defaults** in `settings.php` (`returnbuttoncolor`, `learnmorebuttoncolor` `#667eea`→`#0f6cbf`).
> Review decisions taken: taxonomy-label → `#0f6cbf`; focus-ring → `#0f6cbf` (AA ~5.4:1 on white — verify
> on coloured surfaces); `--lk-amber-rated` `#e5a100` **kept**; modal-note trio → BS warning-light. The
> ~10 loose greys were **left** (optional future normalisation). Kept as documentation of the change.
> `version.php` was **not** bumped (version is frozen at `2026071306` until 2.0); cache busts at the
> next real bump / purge on the test server.

**As-is** = the real `styles.css` palette (a Material/Google skin over Boost). **To-be** = Moodle
DS parity, achieved by overriding the `--lk-*` token block with the `.moodle` class (see
[`tokens.html`](tokens.html) — that override block IS the proposed CSS change). The rules **orange
is kept**.

## How the kit encodes it

Every screen inlines the canonical `--lk-*` token block (as-is values) and shows two panels:
`as-is` and `to-be` (`.moodle` class). The markup is written once with `var(--lk-*)`; only the
token values differ between panels. To implement for real later, the `.moodle` values below become
the new literals/vars in `styles.css` at the listed lines.

## Migrate (differ as-is → to-be)

| Token | As-is | To-be | Role | `styles.css` |
|---|---|---|---|---|
| `--lk-accent` | `#667eea` | `#0f6cbf` `var(--primary)` | tab underline, focus ring, FAB, course placeholder, learn-more btn | 1606, 2162, 2376, 2477, 3059; `settings.php:138` (learnmorebuttoncolor default) |
| `--lk-accent-grad-end` | `#764ba2` | `#0a5aa0` | course placeholder gradient end | 1606 |
| `--lk-pill-active-fg` | `#1a73e8` | `#0f6cbf` | active tab/counter text, search focus | 1298, 1304, 1324, 1364, 3322, 3461, 3467, 3525, 3575 |
| `--lk-pill-platter` | `#f1f3f4` | `#e9ecef` | filter-pill platter bg | 1272, 1719, 2093, 2494, 3318 |
| `--lk-pill-text` | `#5f6368` | `#6c757d` | inactive pill text/icon | 1288, 1316, 1380, 1508, 3321, 3441 |
| `--lk-counter-bg` | `#e8eaed` | `#e9ecef` | inactive counter pill bg | 1315 |
| `--lk-counter-active-bg` | `#e8f0fe` | `#cfe2ff` | active counter pill bg | 1323 |
| `--lk-search-border` | `#e1e3e6` | `#dee2e6` | search input border | 1349 |
| `--lk-search-icon` | `#9aa0a6` | `#6c757d` | search icon + placeholder | 1340, 1360 |
| `--lk-search-glow` | `rgba(26,115,232,.15)` | `rgba(15,108,191,.15)` | search focus glow | 1366 |
| `--lk-card-bg` | `#f9fafb` | `#f8f9fa` | view-competency card + header bg | 693, 708 |
| `--lk-card-title` | `#212121` | `#212529` | view-competency card title text | 699 |
| `--lk-success` | `#28a745` | `#198754` | success green — **unify BS4→BS5** (see below) | 459/920, 521/982, 627/888, 1253, 1278, 1714, 1739 |

## Keep (identical both panels)

| Token | Value | Role |
|---|---|---|
| `--lk-orange` | `#fd7e14` | progress fill, mandatory tag/button, rules alert, info title/icon |
| `--lk-orange-2` | `#ff922b` | progress-fill gradient light stop |
| `--lk-orange-dark` | `#e8590c` | rules alert text, submit-evidence hover |

Also keep the `rgba(253 126 20 / …)` variants (info-box 8%, required border 22%, button shadow 30%)
and `linear-gradient(90deg,#fd7e14,#ff922b)`.

## Review — proposed, to annotate

| Token | As-is | Proposed | Note |
|---|---|---|---|
| `--lk-taxonomy-label` | `#356df3` | `#0f6cbf` | taxonomy eyebrow label → primary? (2257) |
| `--lk-focus-ring` | `#005fcc` | `#0f6cbf` | accessible focus ring — unify with `--primary`? verify AA (1124, 1900, 2002, 2049, 2155) |
| `--lk-amber-rated` | `#e5a100` | `#e5a100` | "rated/pending" amber — keep, or map to a warning token? (1092, 1743) |
| `--lk-modal-note-bg` | `#fef9c3` | `#fff3cd` | evidence-modal note (Tailwind yellow) → BS warning-light? (1977) |
| `--lk-modal-note-fg` | `#713f12` | `#664d03` | " note text → BS warning-dark? (1978) |
| `--lk-modal-note-accent` | `#eab308` | `#ffc107` | " note accent border → BS warning? (1981) |

Plus ~10 loose greys/darks that are one-off spellings of neutrals we already have
(`#e5e0e0`, `#f1f3f5`, `#fafafa`, `#f0f0f0`, `#333`, `#555`, `#b0b2b5`, `#ccc`, `#1d2125`,
`#8da1b6`, `#dfe3e8`, `#273240`) and the Stripe-style shadow tint `rgb(50 50 93 / 25%)` /
slate alphas `rgb(17 24 39 / 8%)`, `rgb(15 23 42 / 4%)` — normalize to Bootstrap greys / neutral
black-alpha shadows while migrating, if desired.

## Success-green inconsistency (fix in passing)

`styles.css` mixes **BS4 `#28a745`** and **BS5 `#198754`** for the same "success" role
(e.g. `#28a745` at 888/920 vs `#198754` at 996/1252). The to-be standardizes on `#198754`.

## Keep — not learner UI

The custom-SCSS editor textarea (admin template form) uses a Catppuccin theme
(`#1e1e2e/#cdd6f4/#45475a/#89b4fa`) — intentional, not part of the learner palette. Leave as-is.

## Implementation notes (later `styles.css` slice)

- Most accent uses are already `var(--dimension-custombgcolor, var(--primary, #667eea))`; only the
  **literal fallback** `#667eea` → `#0f6cbf` changes there. The FAB uses
  `var(--local-dimensions-fab-color, #667eea)` — same treatment.
- Prefer introducing the `--lk-*` (or Boost `--primary`/`--bs-*`) vars over scattering new literals,
  so a future theme swap is one block.
- This is a **visual-parity** change only; no markup/JS/behaviour changes. Normally it would want a
  `version.php` cache-revision bump, but the repo is under a version freeze (`2026071306` until 2.0) —
  so it was **not** bumped; the cache busts at the next real bump, or purge caches on the test server.
