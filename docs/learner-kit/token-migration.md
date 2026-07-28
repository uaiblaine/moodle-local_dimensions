# Token migration — learner views (Material/Google → Moodle DS)

**A record of a completed change, not a plan.** The learner views once carried a Material/Google
skin over Boost (`#667eea` purple, `#1a73e8` Google blue, `#f1f3f4`/`#5f6368` Google greys). That
palette is gone: the learner surfaces now use Moodle DS values — `#0f6cbf` for the accent and
Bootstrap 5 neutrals — while the **rules orange** was deliberately kept.

Landed in two commits:

- **`345ffb4`** (2026-07-20) — 49 value lines in `styles.css` plus the two colour-picker defaults in
  `settings.php` (`returnbuttoncolor`, `learnmorebuttoncolor`, both `#667eea` → `#0f6cbf`).
- **`cd89ad1`** (2026-07-23) — the literals the CSS sweep could not reach: the accent fallbacks in
  `view-competency.php:180`, `amd/src/competency_view.js:110`, `classes/hook_callbacks.php:97` and
  `templates/return_button.mustache` (both the example-context block at `:37` and the inline
  `^buttoncolor` fallback at `:45`), plus the green inside `pix/status/check-circle-fill.svg` and
  `pix/status/rules-proficient.svg` — both are loaded through `<img>`, so CSS `fill` cannot reach
  them and the colour had to come from the file. (`.local-dimensions-icon-check`'s `fill` rule at
  `styles.css:1270` is inert for that reason; it was left in place rather than retired, since
  removing a selector can break a site's custom SCSS.)

`version.php` **was** bumped — the repo is not under a version freeze. It reads `2026072700` at the
time of this audit and has been bumped many times since the migration. (An earlier revision of this
file claimed a freeze at `2026071801`; that claim was wrong.)

## Verification — the old palette is gone

Every retired literal returns **zero** hits in `styles.css` today: `#667eea`, `#764ba2`, `#1a73e8`,
`#f1f3f4`, `#5f6368`, `#e8eaed`, `#e8f0fe`, `#e1e3e6`, `#9aa0a6`, `#356df3`, `#005fcc`, `#fef9c3`,
`#713f12`, `#eab308`, `#28a745`. So does `#667eea` across `*.php`, `amd/src` and `templates/`.

## The mapping that was applied

The **swaps** column is the exact count from `345ffb4`'s `styles.css` diff (49 lines in total). The
last column is where the *new* value can be read today — often more sites than were swapped, because
surfaces built after the migration adopted the Moodle DS value directly.

| Was | Swaps | Is | Role | Where the new value lives today |
|---|---|---|---|---|
| `#1a73e8` | 9 | `#0f6cbf` | active tab/counter text, search focus border, tab focus ring | active count `3659`, search focus border `2364`, tab-strip token `4629`, tab focus `4768` |
| `#28a745` | 7 | `#198754` | success green — **BS4 → BS5, unified** | `1239`, `1271`, `1333`, `1347`, `1461`, `1625`, `2814`, `2842`, `2965`; plus both `pix/status/*.svg` |
| `#5f6368` | 6 | `#6c757d` | inactive pill text/icon | item token `4628`, counter text `3652` |
| `#f1f3f4` | 5 | `#e9ecef` | filter-pill platter, counter pill, rules track | platter token `4625`, counter `3651`, rules track `4122` |
| `#667eea` | 5 | `#0f6cbf` | accent — FAB, course placeholder, active tab underline, per-competency focus rings | FAB `4342`, placeholder `2569`, underline `3639`, scroll-btn focus `3870`, acts-toggle focus `2887`; `settings.php:68` + `:138` defaults |
| `#005fcc` | 5 | `#0f6cbf` | accessible focus ring — **unified with the accent** | 26 `outline: 2px/3px solid #0f6cbf` rules |
| `#f9fafb` | 2 | `#f8f9fa` | view-competency card + header bg | header `825`, card `840` |
| `#9aa0a6` | 2 | `#6c757d` | search icon + placeholder | `2340`, `2360` |
| `#764ba2` | 1 | `#0a5aa0` | course-image placeholder gradient end | `2569` (`linear-gradient(135deg, #0f6cbf 0%, #0a5aa0 100%)`) — the only `#0a5aa0` in the file |
| `#e8eaed` | 1 | `#e9ecef` | inactive counter pill bg | `3651` |
| `#e8f0fe` | 1 | `#cfe2ff` | active counter pill bg | `3658` — plus 14 other `#cfe2ff` sites in the file today, e.g. the availability-date chip `971` and the course-when chip `2667` |
| `#e1e3e6` | 1 | `#dee2e6` | search input border | `2349` — one of 31 `#dee2e6` sites; only the search border was swapped by this migration |
| `#356df3` | 1 | `#0f6cbf` | taxonomy label — **review decision: → primary** | `3755`; the surface itself was redrawn too, from an eyebrow label to `.local-dimensions-tax-link` inside `.local-dimensions-desc-footnote` |
| `#fef9c3` | 1 | `#fff3cd` | evidence-modal note bg — **review decision: → BS warning-light** | `3442`, plus 6 other `#fff3cd` sites today |
| `#713f12` | 1 | `#664d03` | " note text | `3443` |
| `#eab308` | 1 | `#ffc107` | " note accent border | `3446` — the only `#ffc107` in the file |
| `#212121` | 1 | `#212529` | view-competency card title text | `831` |

The focus-ring unification lands at ≈5.4:1 on white; that was never re-measured on coloured
surfaces, so it remains the one accessibility claim in this record that is unverified.

The sixth open decision was **`--lk-amber-rated` `#e5a100`**: **kept**, not mapped to a warning
token. It survives as a single rule — the `rated:not(.completed)` accordion left border,
`styles.css:1465`.

## Kept deliberately — the rules orange

`#fd7e14` survives in nine rules, with two alpha variants — `rgb(253 126 20 / 22%)` (required-child
border, `4187`) and `rgb(253 126 20 / 30%)` (submit-evidence button shadow, `4300`). It has since
spread past the Rules tab: the **favourites star** (`2049` hover, `2053` pressed) and the
**stale-evidence callout** (`3143` left border, `3156` action-pill border, alongside its own
`#fff9f3` fill and `#ad4e00` text) both use it, on top of the original Rules surfaces — note
(`4100`), progress fill (`4129`), child-name hover (`4232`), required tag (`4248`) and
submit-evidence button (`4289`).

Two things the older checklist listed under "keep" are no longer in the file, because the surfaces
were redrawn rather than recoloured: `#ff922b` (the gradient light stop) and
`linear-gradient(90deg, #fd7e14, #ff922b)` both return **zero** hits — the rules progress fill is now
a flat `background: #fd7e14` (`4129`), over a `#e9ecef` track (`4122`). The 8% orange info-box tint
is likewise gone; only the 22% and 30% alphas above survive.

## Kept deliberately — not learner UI

The custom-SCSS editor textarea on the admin template form uses a Catppuccin theme
(`#1e1e2e` / `#cdd6f4` / `#45475a` / `#89b4fa`, `styles.css:3985-3999`). Intentional, outside the
learner palette, untouched by the migration.

## Pending — one residual glow

`styles.css:2366` still reads `box-shadow: 0 0 0 3px rgb(26 115 232 / 15%)` on
`.local-dimensions-search-input:focus` — the old Google-blue glow, sitting directly under the
`border-color: #0f6cbf` that *did* migrate on `:2364`. The space-separated `rgb()` spelling is why
the sweep missed it; it dates to `c5fe747` (2026-05-17) and no commit has touched it since. The
Moodle DS equivalent would be `rgb(15 108 191 / 15%)`, which is already the spelling used at `983`,
`984` and `2853`.

## Pending — the loose neutrals

The migration was value-for-value and deliberately did not normalise the one-off greys. Seven
survive: `#333` (`1405`), `#e5e0e0` (`1453-1455`), `#f0f0f0` (`3397`, `4463`), `#f1f3f5` (8 hits),
`#8da1b6` (`3876`), `#ccc` (`4446`), `#1d2125` (22 hits). Five of the twelve the original checklist
listed are already gone (`#fafafa`, `#555`, `#b0b2b5`, `#dfe3e8`, `#273240`), as is the Stripe-style
shadow tint `rgb(50 50 93 / 25%)`; `rgb(17 24 39 / 8%)` remains at `3846`. Normalising these to
Bootstrap greys and neutral black-alpha shadows is optional and unscheduled.

## How the accent reaches the markup

Most accent uses read through a variable with the literal only as a fallback —
`var(--dimension-custombgcolor, var(--primary, #0f6cbf))` for per-competency theming (`2887`,
`3639`, `3870`), `var(--local-dimensions-fab-color, #0f6cbf)` for the FAB (`4342`),
`var(--bs-primary, #0f6cbf)` in the hub. Only the literal fallback changed at those sites. New
accent work should keep going through those variables rather than adding literals, so a future theme
swap stays one block.

---

*Line numbers verified against `styles.css` at 7434 lines (HEAD `d0adc3b`). They move whenever the
file grows; the class names and hex values are the durable part.*
