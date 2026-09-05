# Bootstrap 4 parity on Moodle 4.5 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make every plugin surface render correctly on Moodle 4.5 (Bootstrap 4) without forking the branch, and add the gate that stops this from recurring a fourth time.

**Decision context:** The compatibility matrix stays single-branch through Moodle 5.2. Per-Moodle-version branches begin when 5.3 work starts (5.3 LTS releases 2026-10-05), in the Boost Union style. `aiplacement_dimensions` deliberately declares `supported = [501, 503]` against this plugin's `[405, 502]` — the AI API only has the needed features from 5.1, and the mismatch is accepted and revisited with the 5.3 branch.

**Root cause:** not architecture. The plugin writes Bootstrap **5** class names, and BS5 vocabulary is the non-portable direction:

| | bridge file | size | covers |
|---|---|---|---|
| Moodle 4.5 → BS5 names | `theme/boost/scss/moodle/bs5-bridge.scss` | **116 lines** | only `g-0`, `btn-close`, `ms/me/ps/pe` spacers, `float/text/border/rounded-start/end` |
| Moodle 5.2 → BS4 names | `theme/boost/scss/moodle/bs4-compat.scss` | **1009 lines** | ~38 names incl. `sr-only`, `custom-select`, `form-group`, `badge-*`, `ml-/mr-/text-left` |

So BS4 names resolve on both; BS5 names resolve only on 5.x.

**Verified empirically on the running m405 stack** (`http://localhost:8405`, computed-style diff against a bare element in the same DOM), not inferred from source:

| class used | computed result on 4.5 | BS4 equivalent | result |
|---|---|---|---|
| `visually-hidden` | `position: static` — does not hide | `sr-only` | `absolute` |
| `form-select` | radius 0, no chevron, padding 0, height 21.5px | `custom-select` | radius 8px, chevron, padding 12px, 36.5px |
| `gap-1/2/3` | `gap: normal` — no effect | — (BS4 has no gap utilities) | — |
| `fw-bold` / `fw-medium` / `fw-normal` | `font-weight: 400` — no effect | `font-weight-bold` | `700` |
| `font-monospace` | system font — no effect | `text-monospace` | SFMono |
| `form-switch` | no knob image; renders as a plain checkbox | `custom-switch` | padding-left 43px, knob drawn |
| `form-label` | no effect | (core styles bare `<label>`) | — |
| `badge` + `bg-success` | text `rgb(29,33,37)` on `rgb(53,122,50)` — ~2.2:1 | — | needs `text-white` |
| `form-check-input` outside `.form-check` | `position: absolute; margin-left: -20px` | — | needs the wrapper |

Confirmed working through the 116-line bridge, leave alone: `me-*`, `ms-auto`, `ps-*`, `text-start`, `float-start`, `g-0`, `btn-close`, `w-100`, `m-0`.

**Blast radius:** 90 sites, essentially all in the **Central hub** (`templates/central/`, `amd/src/central/`). The learner-facing views (`view_plan_summary`, `course_grid`, `accordion`) are almost untouched.

## Global Constraints

- **Do not push.** Commit locally only. Pushing happens on explicit command.
- All code, comments, commit messages and documentation in **English**.
- **Never write to-do or merge-conflict marker tokens literally** in any file, including this one — CI's development-leftover checker scans every file and fails the build on them.
- Every `amd/src` edit ships its rebuilt `amd/build/*.min.js` and `.map` in the **same commit**, plus a `version.php` bump so the cache revision changes.
- Hand-check changed PHP/Mustache for: lines over 132 characters; inline `//` comments that start lowercase or run over multiple lines; one space around `===`/`?`/`:`.
- `styles.css` is linted by **core's** `.stylelintrc`, not a plugin-local one (`docs/design-kit/moodle-ds-alignment.md:139-152`). No `!important` in `styles.css`; the per-theme `styles_<theme>.css` files are not linted.
- Verify every visual change on **both** `http://localhost:8405` (4.5) and `http://localhost:8502` (5.2) before committing.

---

## Task 1 — Delete the two false statements that caused this

**Do this first.** Fixing markup while the rule that generated the bug is still written down guarantees a fourth recurrence. The claim exists in **two** places, and fixing one regenerates the other.

- [ ] `CLAUDE.md:478` — replace *"`form-select` (the BS5 classes are bridged on 4.5); never `custom-select`"* with the verified bridge inventory: 4.5 bridges **only** `g-0`, `btn-close`, the `ms/me/ps/pe` spacers and `float/text/border/rounded-start/end`. Everything else BS5-only is dead on 4.5.
- [ ] `docs/design-kit/moodle-ds-alignment.md:164-165` — same correction. This file is the plugin's actual Bootstrap contract and is more accurate than `CLAUDE.md` on the JS axis; keep that part, fix the class claim.
- [ ] Add to both: the polyfill block introduced in Task 2 is the sanctioned mechanism, and BS5 utility names outside that block are a defect.
- [ ] State the asymmetry explicitly in `CLAUDE.md` so the reasoning survives: *BS4 names resolve on both branches, BS5 names do not.*

**Why the existing rule held on the JS axis and failed on the CSS axis:** the `data-toggle`/`data-bs-toggle` rule is at 100% compliance (zero one-sided lines across the whole repo) because the 4.05 Behat leg **throws** when a dropdown fails to open. The class rules failed 90 times with CI fully green. The difference is enforcement, not diligence — which is what Task 7 fixes.

## Task 2 — Polyfill the six missing utility families in `styles.css`

Do **not** fix these by writing dual names (`sr-only visually-hidden`, `form-select custom-select`). Every BS4 name written today is wrapped in `@include deprecated-styles(...)` on 5.x — it paints a red outline and a "Deprecated style in use" label under `behat-site` and theme-designer mode — and Moodle 6.0 removes `bs4-compat.scss` entirely (MDL-84465). Dual naming would deliberately write expiring vocabulary into 25 template files and guarantee a second full sweep.

Polyfilling reaches all 90 sites through ~60 lines of one file, keeps the markup in one modern dialect, and deletes in a single edit when `405` leaves `$plugin->supported`.

- [ ] Append a delimited block at the tail of `styles.css`, with a header comment stating it is deletable in one edit when `405` leaves `supported`, and listing the exact utilities it owns.
- [ ] `.visually-hidden` — the standard clip pattern (mirror BS5's own definition; do not alias `.sr-only`, which is what expires).
- [ ] `.form-select`, `.form-select-sm` — match Boost 4.5's `.custom-select` metrics measured above: radius 8px, 12px left padding, 36.5px height, chevron background image.
- [ ] `.gap-1`, `.gap-2`, `.gap-3` — raw `gap` property. Safe: `styles.css` already declares raw `gap:` 156 times, and it is supported by every browser Moodle 4.5 targets.
- [ ] `.fw-bold`, `.fw-medium`, `.fw-normal` — `font-weight: 700 / 500 / 400`.
- [ ] `.font-monospace` — Boost's `$font-family-monospace` stack.
- [ ] `.form-label` — match core 4.5's bare-`<label>` treatment so the 5 sites are not silently unstyled.
- [ ] **Scope the whole block to the plugin's own surfaces** (`.local-dimensions-central-page`, `.local-dimensions-viewplan`, `.local-dimensions-viewcompetency`, `.local-dimensions-return-fab`) so it can never leak onto core or another plugin's markup on a 4.5 site.
- [ ] Guard it so it is inert on 5.x. Prefer specificity that loses to core rather than a version scope — the invariant worth protecting is *unscoped means correct everywhere*. Only if a rule cannot be expressed that way, register the `$CFG->branch` body class (see Task 9).

## Task 3 — Fix the three defects the polyfill cannot reach (shape, not vocabulary)

These are genuine behaviour differences between BS4 and BS5, all confirmed in the live DOM.

- [ ] **`form-check-input` outside a `.form-check` wrapper** — BS4 makes it `position: absolute; margin-left: -20px`, so it leaves the flow and overlaps its neighbour. Four sites: `templates/central/plans_import_row.mustache:89`, `:123`, `:153`, `templates/central/enrol_group.mustache:38`, plus the two JS-built ones at `amd/src/central/enrol_methods.js:389` and `amd/src/central/competency_tree_browser.js:113`. Wrap each in a `.form-check` — the parent's 20px left padding compensates, and BS5 is unaffected. Wrapping is smaller than restyling and needs no CSS. (The four already-wrapped sites — `plans_export.mustache:45`, `participants_manager.mustache:118`, `enrol_methods.mustache:94`, `competency_tree_browser.mustache:37` — are correct; leave them.)
- [ ] **`form-switch` toggles render as plain checkboxes** — 3 sites: `participants_manager.mustache:117`, `enrol_methods.mustache:93`, `competency_tree_browser.mustache:36`. BS4's switch is a structurally different component (`.custom-control.custom-switch` + `.custom-control-input` + `.custom-control-label`). Own the switch appearance in the plugin's own CSS rather than emitting BS4's markup, so one DOM shape serves both branches.
- [ ] **Badge contrast** — BS4's `.badge` sets no text colour, so every saturated `bg-*` badge renders near-black text on a dark fill (~2.2:1, well under the 4.5:1 floor). Add an explicit text colour utility at each saturated badge in `classes/local/template_import_verdict.php` and the JS badge builders. Confirmed fix: `text-white` yields `rgb(255,255,255)` on 4.5.

## Task 4 — Fix the modal close button on 4.5

Core 4.5's `lib/templates/modal.mustache` emits `<button class="btn-close"><span aria-hidden="true">&times;</span></button>`; core 5.2's emits an empty button. The plugin's 11 `.btn-close` rules in `styles.css` draw the glyph with `::before`, so 4.5 shows **two** close glyphs.

- [ ] Add `.modal-header .btn-close > span { display: none; }`, scoped to the plugin's modal selectors already present around `styles.css:5374`.
- [ ] Note in the comment that this is core's markup, not the plugin's — no branch can change it, which is precisely why a fork would not have avoided this rule.

## Task 5 — Fix the dropdown auto-close behaviour and its wrong comment

`templates/central/participants_manager.mustache:93` carries `data-bs-auto-close="outside"`, and the comment at `:87-89` claims BS4 keeps the dropdown open for in-form clicks. BS4's `Dropdown._clearMenus` exempts only `input` and `textarea`, so the `<select>` at `:106` and the `<label>` at `:121` close the filter panel mid-interaction.

- [ ] Add a guarded `stopPropagation` on the menu container so the panel survives in-form clicks on both branches.
- [ ] Correct the comment to what BS4 actually does.
- [ ] This is the **only** functional (non-presentational) divergence in the whole audit — a 4.5 user cannot complete the filter interaction. Verify by hand on 8405.

## Task 6 — Give up the `--mds-` namespace

`styles.css:22-37` defines `--mds-motion-fast`, `--mds-motion-base`, `--mds-motion-flash`, `--mds-motion-ease` and `--mds-loading-min-height` in **`:root`** — global scope, inside the namespace core is actively expanding. Moodle 5.2 ships `theme/boost/scss/design-system/` with `$mds-*` tokens (SCSS today, so no collision yet); 5.1 has no such directory; 5.3 LTS brings MDS React. The plugin has squatted core's namespace at the one scope where a future collision is unavoidable.

- [ ] Rename to `--local-dimensions-motion-*` / `--local-dimensions-loading-min-height` and move them off `:root` onto the plugin's own wrapper selectors.
- [ ] Update the 91 `--mds-` references in `docs/design-kit/tokens.html` in the same commit.
- [ ] Do this **before** any 5.3 work — it is cheap now and becomes a merge hazard once branches exist.

## Task 7 — Add the gate that makes this fail loudly

The single most important task in this plan. This defect class has now shipped **three times** — `f84d30a` (2026-07-14, "two independent spots assumed Bootstrap 5, silently dead on Moodle 4.5") and `CHANGELOG.md:131` ("Bootstrap 4 dropdowns dead on Moodle 4.5") — was correctly root-caused each time, was documented each time, and recurred anyway. Writing the rule down has already been tried twice. It does not work, because nothing observes it.

- [ ] Add `tests/local/bootstrap_compat_test.php` as a `final class ... extends \basic_testcase` (no `$DB`, no `resetAfterTest`), with `@covers` on the class docblock.
- [ ] It scans `templates/`, `amd/src/` and the PHP files that emit class strings, and **fails** on: any Bootstrap utility absent from 4.5 that the Task 2 polyfill does not define; any `badge` beside a saturated `bg-*` without a text-colour utility; any `form-check-input` not inside a `.form-check`; any selector inside the polyfill block that is not one of its declared names.
- [ ] Keep the banned-name list in one array with a comment pointing at `bs5-bridge.scss` as the source of truth.
- [ ] PHPUnit runs on all three remaining `ci.yml` legs at zero extra runner cost, and unlike stylelint it can see class strings inside Mustache and JS.

## Task 8 — Ship it

- [ ] `mdl grunt m405 local/dimensions` — rebuild the AMD bundles.
- [ ] Commit rebuilt `amd/build` output together with a `version.php` bump.
- [ ] `CHANGELOG.md` entry in the same commit.
- [ ] `mdl ci moodle-local_dimensions --branch MOODLE_405_STABLE` and `--branch MOODLE_502_STABLE`.
- [ ] Side-by-side visual pass: `http://localhost:8405` against `http://localhost:8502`, covering the Structures / Competencies / Learning plans tabs, the participants manager, the plan import preview and at least one modal. **This is the acceptance test for the whole exercise** — seed data into the System context first, since the tables that carry the worst defects (the 8 `<caption class="visually-hidden">` that print as visible headings) are empty on a bare site.

## Task 9 — Record the deferral with its trigger

- [ ] Note in `CLAUDE.md` that the fleet's per-Moodle-version branch plan is deferred for this plugin until 5.3 work starts, and that the mismatch with `aiplacement_dimensions` (`[501, 503]` against `[405, 502]`) is a deliberate consequence of the AI API's 5.1 floor, to be resolved when the 5.3 branch is cut.
- [ ] Do **not** register a `$CFG->branch` body class unless Task 2 proves it necessary. Installing an unused version-scoping mechanism invites version-conditional CSS to spread before any rule requires it.
- [ ] Record the four conditions that reopen the branch decision: 5.3 LTS work starting (2026-10-05); a divergence that cannot be written once in the plugin's **own** markup; Moodle 6.0 removing `bs4-compat.scss` while `405` is still supported; the polyfill block passing ~150 lines or needing a version scope.

## Already done (2026-08-06)

- [x] `ci-500` removed from `.github/workflows/ci.yml`. Moodle 5.0 leaves security support on 2026-10-05; the leg tested a dead branch. Three jobs remain: `ci-502` (full matrix), `ci-501`, `ci-405`. `actionlint` clean.
- [x] The stale comment at `ci.yml:3-6` claiming the plugin has no local Moodle install — false, it is mounted on m405/m501/m502 via `moodle-dev/plugins.conf:23` — replaced with the real local gate and the note that no static check can see a Bootstrap class that resolves to nothing.
- [x] `README.md` checked: "Moodle 4.5 or later (tested up to Moodle 5.2)" remains accurate; no change needed.

## Fleet follow-up (separate work, not this plugin)

- The fleet standard's "Bootstrap 4/5 dual compatibility" bullet in `~/dev/CLAUDE.md` covers only the JS data-API. Extend it to **class vocabulary**, naming the six families absent from 4.5 and prescribing polyfill-in-own-stylesheet over borrowing BS4 names that Moodle 6.0 deletes.
- Sweep every repo whose `$plugin->supported` includes `405` for those six families. `block_dimensions` is `supported = [405, 502]` and depends on `local_dimensions`, so it is the first place to look.
- The false `form-select` claim and the stale "no local Moodle install" comment are template-shaped errors likely copied into sibling repos — grep the fleet for both.
