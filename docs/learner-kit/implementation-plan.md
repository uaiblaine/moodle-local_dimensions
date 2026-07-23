# Learner views — implementation plan

The execution companion to [`development-plan.md`](development-plan.md). That file is the *backlog*
(what the redesign implies, per screen); this file is the *plan* (what ships, in what order, and how
each slice is proved). Slices are independently shippable: one commit each, local, pushed only on
request.

Grounded against the code at `f86dc3b`. Every claim below that shaped a decision was read in the
source, and the load-bearing ones were adversarially re-verified; citations are `file:line`.

---

## Decisions taken (2026-07-20)

| # | Decision | Consequence |
|---|---|---|
| **A** | **Bump once per phase boundary.** | Five bumps, each with a matching `db/upgrade.php` savepoint (§11). Removes ~20 manual cache purges and the risk of testing stale `accordion.min.js`. The freeze is relaxed deliberately, not abandoned. |
| **B** | **Badge only the two decisive outcomes** (`OUTCOME_COMPLETE`, `OUTCOME_RECOMMEND`). | `OUTCOME_EVIDENCE` — core's actual DB default — renders clean, preserving the kit's intent now that "most cards stay clean" is known to be inverted. |
| **C** | **The detail modal is grid-mode only.** | It does **not** coexist with the accordion, so the DOM-id collision that would have forced an instance suffix on all five Phase-4 renderers **does not arise** — provided grid mode renders no accordion detail panes (see 6.6). Phase 4 is unblocked. |
| **D** | **Taxonomy definitions stay in scope.** | 11 definitions × 2 languages, authored as lang strings so sites can override. Also: retire the word "card" from the `showtaxonomycard` setting's visible text. |
| **E** | **Single-activity / single-section cards stay in scope**, with a corrected trigger. | The kit's "no trackable sections" trigger is unreachable; the real cases are *one activity* and *one section*. See 5.3 / 6.7. |
| **F** | **Ghost card stays in scope**, scoped to favourites-only mode. | It exists to stop learners forgetting an active favourites filter — which also settles its undefined semantics under search/chip filters: it renders only in favourites-only mode. |
| **G** | **"About this scale" modal stays in scope.** | Ships silent-by-default on sites with an empty `mdl_scale.description`; that is accepted. |
| **H** | **Dropped:** `showevidencecounts` setting · the "Recently updated" sort option · `enrol_url`. | |
| **I** | **The Rules-tab enrolment gate is dropped entirely.** Show every child as today. | The backlog's one High-priority item closes as *already handled*: `view-competency.php` applies the filter itself, and a competency with no linked courses shows no section. Phase 4 loses its server slice; no WS change, no new test. |
| **J** | **"Linked {label} & activities" → "Related content."** | Neutral and simpler — and it turned out to be the *last* consumer of the competency label. **Slice 1.2 is dropped entirely** (§3), taking the pt_br agreement trap and the index-vs-string trap with it. |
| **K** | **Favourites star is hidden when viewing another user's plan**, per the existing own-plan guard. | Removes the confusing case where a manager stars competencies from someone else's plan into their own list. |
| **L** | **Linked activities must honour access restrictions and use official icons.** | Mirrors the section rules already in `calculator.php`: hidden ⇒ not listed, restricted ⇒ lock + link to the **course**, not the activity. Icons come from core's API, and must respect Boost Union's `modiconsenable` override. Scope added to 5.1 — see that slice. |

**Follow-up parked:** once the grid modal's expand/close controls are implemented here, port that
visual back to the Competency hub (which currently has the weaker treatment). Not a slice in this plan.

---

## 0. Three findings that reshape the backlog

Read these before the slice tables — each one changes what the backlog assumed.

### 0.1 The version freeze survives almost intact

The backlog and several kit screens assume "a WS return changes, so this bumps". That is mostly
false here, for two independent reasons:

1. **Four of the five learner web services return an opaque `PARAM_RAW` JSON string**, not a typed
   structure — `get_competency_rule_data.php:357`
   and `get_user_competency_summary_in_plan.php:129`.
   There is no `external_single_structure`, therefore no `clean_returnvalue` allowlist, therefore
   **new payload keys reach the JS with no returns edit at all**. The scale description and the
   evidence work are both free.
2. **A returns-only change is not persisted anyway.** `external_functions` stores name, classname,
   methodname, classpath, component, capabilities, services — not the returns shape;
   `execute_returns()` is resolved at call time. Only a **new function name** (or a changed
   classname/capabilities) needs the services reinstall that an upgrade performs.

Only **two** learner WS are typed: `get_competency_courses.php:159-170`
and `get_course_progress`. They are the only places the allowlist gotcha can bite.

**Consequence:** the redesign needs **zero bumps by mechanism**. The real question is cache
revision, not correctness — see §0.2.

### 0.2 The freeze’s real cost is a manual cache purge per slice

Under the freeze, an `amd/src` or `styles.css` change ships with **no cache-revision move**, so the
test server serves stale `accordion.min.js` until caches are purged. Across ~20 slices that is ~20
purge steps and ~20 chances to test stale JS and conclude a slice is broken.

**Resolved (decision A): bump once per phase boundary** — five bumps, each with a matching
`db/upgrade.php` savepoint, in the *last* commit of the phase. Individual slices inside a phase stay
at the current version; if you need to verify a mid-phase slice on the site, purge caches or set
`cachejs = off`.

### 0.3 The Rules-tab enrolment gate is **dropped** — the target page already handles it

The backlog's one High-priority item asked to enrolment-filter the child-competency list on the
Rules tab, so a child with no accessible course isn't a dead click.

**Resolved: do nothing.** Show every child, exactly as today. The dead-click concern is already
answered one level down — `view-competency.php` applies the enrolment filter itself, and a competency
with no linked courses does not render the section at all (decision F). Gating a second time in the
Rules tab would duplicate a rule that already works, in the one place where it is most dangerous to
get wrong.

That danger is worth recording, because it is why the literal reading had to be rejected before the
simpler answer became visible. **Filtering children here silently corrupts the progress maths**, in
the learner's disfavour:

- **Points rules:** `totalrequired` comes from `$config['base']['points']`, computed *outside* the
  children loop (`get_competency_rule_data.php:228`),
  while `earnedpoints` accumulates *inside* it (`:231-251`). Dropping a proficient child cuts the
  numerator and leaves the denominator — a genuine 45/60 renders 20/60, and the bar's target becomes
  unreachable from the visible rows.
- **Worse:** `pendingmandatorycount` shrinking flips `hasmissingmandatory` (`:100-101`) from true to
  false, which suppresses the warning triangle, the striped fill and the missing-mandatory notice
  (`accordion.js:748-752, :781-783, :791`) **while a required child is
  still genuinely unmet**. The filter would hide a blocker.
- **All-or-nothing rules:** `totalrequired = count($children)` (`:295`) *does* follow the filter, which
  is arguably worse — hiding one unmet child renders "2 / 2, 100%" for a rule core will never mark
  satisfied.

**Therefore:** every child stays in the payload, in the maths, and on screen — unchanged from today.
No `accessible` flag, no gate, no slice. The backlog's High-priority item is closed as
*already handled elsewhere*, and Phase 4 loses its server slice entirely.

---

## 1. Governing conventions for every slice

| | |
|---|---|
| **Commit** | one per slice, local. `feat(learner):` / `fix(learner):` / `refactor(learner):`. Push only on explicit request. |
| **AMD** | any `amd/src` edit ships its rebuilt `amd/build/*.min.js` + `.map` in the same commit (`npx grunt amd --root=public/local/dimensions` from the Moodle root). |
| **Local gates (always, pre-commit)** | `npx eslint --max-warnings 0 public/local/dimensions/amd/src` · `npx stylelint --config .stylelintrc public/local/dimensions/styles.css` (core's config, not the plugin's) · `php -l` on changed PHP · the two phpcs greps from `CLAUDE.md` (132-col, lowercase inline comment). |
| **Lang** | `en` + `pt_br` in the same commit, both alphabetically placed. |
| **CSS** | no `clamp()`/`min()`/`max()` in any length property, no `!important`, no duration under 100ms. Baseline verified clean: `styles.css` currently produces **0 stylelint errors** under core's config (two pre-existing `max-line-length` *warnings* at `:4122` and `:4516` cannot fail the build — `--max-lint-warnings` is wired only into the eslint task). |
| **Retiring a CSS class** | list the retired selectors in the commit body — see [risk R1](#r1-custom-scss-is-customer-code). |
| **Site verification** | each slice below carries a one-line "user verifies" recipe. Set *Debug = DEVELOPER* and *cachejs = off* for JS slices. |

---

## 2. Phase 0 — prerequisite defect fixes — **SHIPPED**

Four live defects, found while grounding. All small, no design dependency, and any of them would
otherwise be blamed on a redesign slice that merely exposed it.

| Slice | Commit | |
|---|---|---|
| 0.1 filter-tab scope | `52a4705` | done |
| 0.2 progress payload guard | `9a42e72` | done |
| 0.3 palette tail | `cd89ad1` | done |
| 0.4 `isGradeProficient` | `a781d75` | done |

Local gates green across all four (eslint 0, stylelint 0 under core's config, `php -l`, leftover-token
sweep). **Not pushed, and not yet verified on the site** — the version is still frozen at
`2026071801`, so purge caches or set `cachejs = off` before testing. CI has not run.

One correction landed during execution: slice 0.1's CSS half was **dropped**. The claim that the
completion tabs have no focus ring was wrong — the chip block supplies
`:focus-visible {box-shadow: inset 0 0 0 2px #0f6cbf}` at `styles.css:3460`, which does reach them.
The remaining entanglement (the chip block also wins on padding and font-size) is cosmetic, and
Phase 3 rewrites that area anyway. The slice shipped as JS-only.

### 0.1 — Scope the filter-tab click handler *(live bug)*

`initFilterTabs()` selects **bare** `.local-dimensions-filter-tab` across the whole document
(`accordion.js:2321`) — and the chip buttons carry that exact class
(`chip_filters.mustache:55`) alongside the real completion tabs
(`view_plan_summary.mustache:78,:82`). Clicking any chip
therefore runs the completion-tab handler, strips `.active` from both real tabs, and `getActiveFilter()`
reads `undefined` — **the Not-completed filter silently degrades to All with no tab selected**, on
any plan with `viewplan_filter_fields` configured, today.

This does *not* go away when the chips move into a panel (`querySelectorAll` finds hidden nodes), so
it blocks Phase 3.

| | |
|---|---|
| **Files** | `amd/src/accordion.js` (+ build), `styles.css` |
| **Change** | scope the selector to `.local-dimensions-filter-bar .local-dimensions-filter-tab`; scope the chip CSS block so `outline: none` at `styles.css:3456` stops eating the filter bar's focus ring at `:1297` (WCAG 2.4.7 — the completion tabs have **no visible focus ring today**) |
| **WS / bump / lang** | none / **no bump** / none |
| **Verify** | site only — no test can see it. *On a plan with chip fields configured: click a chip; the Not-completed tab must keep its selection and completed items must stay hidden. Then Tab to a completion tab and confirm a visible ring.* |

### 0.2 — Guard the completion-disabled payload

`get_course_progress` emits PHP notices for a course with completion tracking off.

| | |
|---|---|
| **Files** | `classes/external/get_course_progress.php` |
| **WS / bump / lang** | returns structure untouched / **no bump** / none |
| **Verify** | site, DEVELOPER debugging: *tracker card for a course with completion off — no "Undefined array key" notices.* Also the natural home for the first WS PHPUnit test (§8). |

### 0.3 — Finish the palette-migration tail

Commit `345ffb4` migrated `styles.css` and the two colour-picker defaults, but four `'#667eea'`
fallbacks and two `#28A745` SVG fills survive outside CSS.

| | |
|---|---|
| **Files** | `pix/status/check-circle-fill.svg`, `pix/status/rules-proficient.svg`, `amd/src/competency_view.js:70` (+ build), `view-competency.php:177`, `classes/hook_callbacks.php:97`, `templates/return_button.mustache:45` |
| **WS / bump / lang** | none / **no bump** / none |
| **Verify** | `grep -rn '667eea\|28A745'` returns empty. Site: only visible where the colour setting was never saved. |

---

### 0.4 — Fix `isGradeProficient` *(live bug — confirmed against core)*

You asked what the off-by-one point was. It is real — and it is actually worse than an off-by-one.
In plain terms:

**The format.** Core stores a competency's scale configuration as a JSON array with two properties
the plugin's function does not expect:

1. **The first element is a header**, `{"scaleid":N}` — not a scale level.
2. **The array is sparse.** Core's writer *skips* any scale value that is neither the default nor
   proficient: `if (!scaledefault && !proficient) { return; }`
   (`admin/tool/lp/amd/src/scaleconfig.js:164-166`). Entries are identified by their `id` property,
   which is the grade — **position carries no meaning at all.**

A real example from core's own tests (`competency/tests/external/external_test.php:172-174`):

```json
[{"scaleid":"7"},{"name":"value1","id":1,"scaledefault":1,"proficient":0},
                 {"name":"value2","id":2,"scaledefault":0,"proficient":1}]
```

**Core reads it** by dropping the header and matching on `id`: `array_shift($config)` then
`if ($part->id == $grade)` (`competency/classes/competency_framework.php:391-406`).

**The plugin reads it** by array *position* on the *unshifted* array — `config[grade - 1].proficient`
(`amd/src/accordion.js:1128-1133`).

**How bad is it in practice?** Worst on the most common setup there is. A plain two-value
*Not competent / Competent* scale produces `[{scaleid},{id:1,p:0},{id:2,p:1}]`; grade 2 reads slot 1,
finds level 1, and reports **not proficient** — so "Competent" renders grey on every such site. Where
proficiency runs contiguously to the top (say levels 3–4 of 4), the top grade happens to come out
right and the *boundary* grade — the lowest proficient one — is the one shown grey. And because the
array is sparse, a 4-level scale proficient only at the top makes the lookup run off the end and
return false for the one grade that matters. The error is not a uniform one-level shift; it depends
on which levels the admin ticked.

**Both array shapes exist on a single site**, which is the clincher for matching on `id`: the plugin's
own framework modal writes a *dense* array (`amd/src/central/framework_scaleconfig.js:56-68` pushes
every row), while competency-level configs saved through the plugin's form go through core's *sparse*
writer — `competency_dynamic_form.php:222-226` wires up `tool_lp/scaleconfig` directly. Core reads
both correctly because it never trusts position. Neither shape is wrong; positional lookup is.

**Root cause, in the code itself:** the function's docblock (`accordion.js:1112-1113`) gives an
example *without* the header — it was written against a format core never produces. Nothing upstream
compensates: the string is passed straight from the WS payload (`accordion.js:266-267`), and there is
no `shift()` or `slice(1)` anywhere in the module.

**The plugin already gets this right everywhere else**, which is what makes it a defect rather than a
design choice — `helper::scaleconfig_is_complete()` does `array_shift` (`classes/helper.php:2703`)
and `amd/src/central/framework_scaleconfig.js:43` does `.slice(1)`. This one function is the outlier.

| | |
|---|---|
| **Files** | `amd/src/accordion.js` (+ build) |
| **Change** | mirror core: `config.slice(1).some(part => +part.id === grade && +part.proficient === 1)`. **Strictly less code than today** — no index arithmetic, no bounds check — and it handles the sparse case correctly (no matching entry ⇒ false, exactly what core returns). Fix the docblock in the same edit. |
| **Blast radius** | one call site — the evidence detail modal's grade badge (`:1156`). |
| **Do not touch** | `view_plan_summary_page.php:179-180`. That is a *different* array — the scale **items**, which are dense and 0-based — where `$grade - 1` is correct and matches core's own `evidence_exporter`. |
| **WS / bump / lang** | none / **no bump** / none |
| **Verify** | site: open an evidence item graded at a proficient level of a scale where some middle level is neither default nor proficient — the badge must read proficient. Fixing it in Phase 0 means 4.4 inherits correct behaviour instead of re-touching it. |

## 3. Phase 1 — substrate

One slice, after decision J removed the second. Landing it first prevents three later slices from
each inventing their own preference plumbing.

### 1.1 — User-preference substrate

The plugin owns no tables; favourites, view state and the hero choice all persist as user
preferences. The mechanism **already exists** for the Central admin UI and is web-service-free —
`lib.php:140-149` declares the callback, and the write goes through **core's**
`core_user/repository` `setUserPreference` (`amd/src/central/preferences.js:28,:77`),
not a plugin WS. It is **not currently reachable from the learner views** (`central.php:75` is the
sole initialiser, behind `admin_externalpage_setup`), so this slice extends it.

| | |
|---|---|
| **Scope** | two new preference names: `local_dimensions_learner_view` (chrome — sort, filter, layout, hero; ~70 bytes, fixed size) and `local_dimensions_learner_fav` (favourites, one preference holding every plan as `{"<planid>": [competencyid, …]}`) |
| **Files** | `classes/constants.php`, `lib.php`, `classes/privacy/provider.php`, `lang/{en,pt_br}`, **`tests/privacy/provider_test.php`**, **`tests/preferences_test.php`** |
| **Trap** | `tests/privacy/provider_test.php:57` asserts `assertCount(2, $items)` and `tests/preferences_test.php:41-51` asserts the callback contents — **both fail unless updated in this commit.** The provider is *not* a null provider; that note in `CLAUDE.md` is stale for preferences. |
| **Free** | `helper::purge_user_preferences()` already deletes by the `local_dimensions_` name prefix (`helper.php:2195-2202`) — uninstall needs no change. |
| **WS / bump** | none — the callback is discovered at runtime via `get_plugins_with_function()` / **no bump** |
| **Verify** | CI runs the two updated tests. Site: preference row appears in `mdl_user_preferences` after a toggle. |

### 1.2 — Competency-label plumbing — **DROPPED** (decision J)

Neutralising the section title to "Related content" removed the last consumer that needed it. A sweep
of all 15 kit screens finds label interpolation in exactly **two** files, four strings total — both
plan empty states (`ovw-empty.html:153-154,178-180`) and a section-empty line plus a nav aria-label
(`ovw-detail-courses.html`). None of them *requires* the label: each reads as well with a neutral
noun, and the two on the courses screen sit inside the very section whose title is being neutralised,
so keeping them label-driven would be inconsistent. `trk-empty.html` never used it, and the Rules
tab's "View all N dimensions" uses *dimensions* as the plugin's own fixed word, not the custom field.

Dropping this removes, at no cost to the design: the index-vs-string trap (the option labels are
baked at provisioning time in the provisioning admin's language, `helper.php:363-364`, so any literal
string comparison breaks on a non-English site), the hardest plumbing case (an *empty* plan has no
competencies loaded, so the label would have to be resolved from the template/framework), and the
pt_br agreement problem outright.

**Copy to use instead** (neutral, same register as "Related content"):

| Where | String |
|---|---|
| Plan, no competencies | "No competencies in this plan yet" / "When competencies are added to this learning plan, they'll appear here to explore and track." |
| Plan, no filter results | "No competencies match your filters" / "Try a different search term, or clear the filters to see all {$a} competencies." + *Clear filters* |

Only the second is a genuinely new string, and it keeps its count parameter and loses the label one.
Adjust the wording if you prefer a different noun — the point is that it is fixed, not derived.

---

## 4. Phase 2 — pure CSS and markup

No data movement, lowest risk, immediately visible on the site. All **no bump**.

| Slice | Scope | Screens | Files |
|---|---|---|---|
| **2.1** | Hero description mask replaces the boxy dark fade; glass-pill "See more" toggle **with a real focus ring** (the current rule sets `outline: none` at `styles.css:3246`) | `hero` | `styles.css` |
| **2.2** | Tracker card `min-height: 320px`; locked-card blurred sections behind a lighter veil; neutral locked shadows | `trk-locked`, `trk-tracker` | `styles.css` |
| **2.3** | Empty / no-results states, label-aware (consumes 1.2) | `ovw-empty`, `trk-empty` | `templates/*.mustache`, `lang/{en,pt_br}`, `styles.css` |

**Scoping trap for 2.1:** `collapsible_description.mustache` has **five consumers** — the hero, the
Central plans pane, `central/competency_detail.js`, both view initialisers, and a hand-built markup
duplicate at `accordion.js:2097-2109`. All the others sit on light
backgrounds and still want the existing fade. **Scope the restyle under `.local-dimensions-hero`;
do not edit the shared partial.**

---

## 5. Phase 3 — the toolbar and the chips retirement

Three slices (prerequisite + one per view), not one — the two views have separate templates,
separate renderables and separate consuming JS, and shipping them together makes a ~10-file commit
whose failure mode is ambiguous.

**Blocked on 0.1.** Ship that first or this phase inherits a broken baseline it will appear to have caused.

### The measurement question, settled

`chip_filters.js` contains **no measurement code at all** — it is 175 lines of pure event wiring.
All measurement lives in `filter_tabs_nav.js`, reached through one call at
`chip_filters.js:147`. Rendering it inside a hidden panel produces a
**transient wrong state, not a crash**: while `display:none`, `offsetWidth`/`scrollWidth` are 0, so
`filter_tabs_nav.js:212` wrongly concludes the strip is scrollable —
but nothing is cached (`_computeProps` recomputes on every call) and the constructor installs a
`ResizeObserver` on the wrapper (`:154-155`) whose callback re-centres on reveal. The observer *is*
the designed recovery path.

**The real trap is the obvious workaround:** re-calling `ChipFilters.init()` after the panel opens
**double-binds every chip's click handler** — `setupContainer` (`chip_filters.js:105-116`)
has no dedupe guard, and the `registry` at `:144` is written but never read (dead state).
`FilterTabsNav.initAll` *is* idempotent (`:450-452`), so the fix is a `refresh()` that re-enters the
nav only — not a re-init.

### Panel mechanism

**A plain `<div>` toggled by JS via `el.hidden`,** with `aria-expanded` on the button and
`aria-controls` on the panel. No Bootstrap JS component — which is precisely what makes it safe
across 4.5/5.x, since `data-toggle` vs `data-bs-toggle` is not bridged. Zero BS-version-specific code.

> Do **not** use `.d-flex`/`.d-block` on anything toggled by `hidden` — they are `!important` and beat
> `[hidden]`. This is the same footgun that would render every accordion item permanently open (§9 R3).

| Slice | Scope | Bump |
|---|---|---|
| **3.1** | Filter button + panel shell + count badge (computed host-side, zero change to `chip_filters.js`); `refresh()` on first open | **no bump** |
| **3.2** | Plan view: chips out of the inline bar, into the panel; `applyFilter` unchanged | **no bump** |
| **3.3** | Tracker: same, + reword the three setting `_desc` strings that describe the retired placement (`lang/en:580-585` + mirrors) | **no bump** |

**Drop while you are here:** the tracker's competency-area chip group is built from a *single*
instance (`view_competency_page.php:169-178`), so every
card carries the same value and the chip matches all cards or none. Easy to ignore in an inline bar;
behind a Filter button with a count badge it becomes a prominent control that does nothing. Removing
it is a deletion.

---

## 6. Phase 4 — accordion detail-pane rewrites

Five redesigns land in one 2432-line file. Ordering is chosen to minimise re-touching the same lines.
All **no bump** (both WS involved are `PARAM_RAW`).

> **The modal no longer blocks this phase (decision C).** The concern was that rendering the same
> competency into a modal *while the accordion exists* duplicates the DOM ids
> `local-dimensions-tab-{tabid}-{competencyId}` (`accordion.js:384-402`), which have no instance
> suffix — and `getElementById` returns the **first** document-order match, so the single-open
> handler at `:2232-2240` would start hiding the wrong element. Because the modal is **grid-mode
> only**, and grid mode renders no accordion panes, the two never coexist and no instance suffix is
> needed. **This is an invariant, not a coincidence — 6.6 must enforce it** (see that slice).

| Slice | Scope | Notes |
|---|---|---|
| **4.1** | **Rules — client only.** Soften the X/Y progress bar, promote the "how it's completed" text. The child list is left exactly as today (§0.3). | Server gate dropped; no WS change at all. |
| **4.3** | **Status.** Rating leads (`gradename` as primary fact), proficiency becomes a qualifier pill; "Not yet rated" when ungraded. Replaces the two-cell grid at `renderStatusSection` (~`:2028`). | Authors the shared `.local-dimensions-*-pill` CSS that 4.4 reuses. |
| **4.4** | **Evidence.** `evidence_competencyrule` case in `getEvidenceTypeInfo`; rule-completion beats a proficient rating in precedence; outcome-first list replaces the slider. | Settle the `isGradeProficient` question first — it changes badges on already-shipped modals. |
| **4.5** | **Evidence modal** + **"About this scale" modal** (`mdl_scale.description`, rendered only when non-empty — decision G). | Free of a returns edit: the summary WS is `PARAM_RAW` and already injects undeclared keys (taxonomy at `:113-114`). |
| **4.6** | **Description / Path / Taxonomy.** Delete the heavy aside card, fold the path into a footnote, and add the **taxonomy definition modal** (decision D). | See below — this slice carries a content deliverable. |

**4.6 carries writing, not just code.** Core ships only the taxonomy *term*
(`lang_string('taxonomy_<key>', 'core_competency')`); there is no definition anywhere in core or the
plugin, and no framework column to hold one. The 11 definitions are therefore authored as **plugin
lang strings** — which is what makes them site-overridable, and is why this is viable at all.
Deliverable: 11 definitions × `en` + `pt_br` = 22 strings, drafted for your review *before* the code
lands. Also in this slice: retire the word "card" from the `showtaxonomycard` setting's visible text.

> **Keep the setting *key* `showtaxonomycard`.** Only the label and `_desc` change. Renaming the key
> would need a `db/upgrade.php` step to migrate existing config, and would silently reset the setting
> on every site that had customised it — real cost, zero user-visible gain.

**Do not build a shared badge/pill renderer in JS.** The three pills are genuinely different objects;
a parameterised renderer costs more lines than the three string builders it replaces. The real
sharing is **CSS** — one pill component with modifiers, authored in 4.3. (House preference: least code.)

**Do not split `accordion.js` into modules** as part of this work. Splitting before the rewrites means
splitting code that is about to be deleted; the file will be materially smaller afterwards, and the
seams clearer. Revisit then.

---

## 7. Phase 5 — server-data slices

The only two slices that touch a **typed** returns structure, and therefore the only genuine
bump candidates.

### 5.1 — "Related content": outcome badge + activities

The biggest slice in the plan. Consider splitting the server half from the client disclosure UI if
the commit gets unwieldy.

Extend **`get_competency_courses`**, not the existing `get_competency_module_links`. That function is
a useful precedent but not a drop-in, on four counts verified in the source: it reports only whether
completion is *configured*, not the user's completion (`:105`);
it returns no activity view URL; it has no `userid` param and resolves everything through the calling
user via `get_fast_modinfo`/`uservisible` (`:92-94`), so it cannot serve staff reviewing another
learner's plan — a case `view-plan.php:63-65` explicitly anticipates; and it is
per-course, forcing one call per card where `accordion.js:90` makes exactly one call today. It also
ships admin-only payload (`available[]`, `sharedcount`, `canmanage`, `editurl`).

#### Access restrictions — mirror the section rules exactly (decision L)

Activities carry restrictions just as sections do, and the plugin already has the canonical rule in
`calculator::get_course_section_progress()`. **Mirror it rather than inventing one.** The established
section cascade is: skip delegated sections; skip on the **raw** `visible` flag; then if
`!$section->uservisible`, *skip entirely* when `availableinfo` is empty ("hide") but mark
**locked** when `availableinfo` is non-empty ("show greyed") — and a locked section links to
`/course/view.php?id=<courseid>` instead of the section, exactly as you described.

The activity mirror, per linked cmid:

| Gate | Rule |
|---|---|
| 0 | `competency_modulecomp` can outlive its module — skip if the cmid is absent from modinfo, or `$cm->deletioninprogress` |
| 1 | skip if the parent section is hidden (`!$cm->get_section_info()->visible`) — nothing may render beneath a section the card itself does not show |
| 2 | skip if `!$cm->visible` (raw flag, like the section rule) |
| 3 | skip if `!$cm->visibleoncoursepage` (stealth activities) |
| 4 | if `!$cm->uservisible`: **skip** when `availableinfo` is empty, else mark **locked** |
| 5 | inherit the parent section's lock — `cm_info` does **not** copy a section's `availableinfo` down, so a cm under a restricted section has an empty `availableinfo` and would otherwise slip through gate 4 |
| 6 | URL: `''` when `!$cm->has_view()` (labels); the **course** URL when locked; else `$cm->url` |
| 7 | course-level lock (`calculator::is_locked`) overrides everything: `url = ''`, `locked = false` — the card overlay already carries the message |

Keep the raw/`uservisible` split deliberate: raw flags for the skip gates, `uservisible` for the lock
gate. That is what reproduces the section semantics, including staff with
`ignoreavailabilityrestrictions` seeing restricted rows unlocked.

#### Icons — use core's API, and write **no** Boost Union code

Use `cm_info::get_icon_url()`. That is the whole job, and the plugin's own WS already does exactly
this (`get_competency_module_links.php:103`).

Boost Union's `modiconsenable` needs **zero** plugin code, which is worth stating explicitly because
the intuitive design would be to read the setting. The override is not a renderer, filter, hook or
callback: the theme copies uploaded icons into `$CFG->dataroot/pix_plugins/mod/<modname>/`
(`theme/boost_union/locallib.php:1597,1632`), and core's image resolver checks exactly that path as a
fallback layer (`lib/classes/output/theme_config.php:1925`). Because every icon URL points at
`/theme/image.php`, which resolves through that function at **serve** time, an overridden icon is
picked up automatically — server-side or client-side, in any theme.

> **Do not** call `get_config('theme_boost_union', 'modiconsenable')`, and do not add a theme-active
> guard. It would be dead code that risks diverging from core. Just don't hand-build icon URLs.

Boost Union's own `activityiconcolor<purpose>` settings are inherited for free too — the theme injects
them as **global** SCSS variables (`theme/boost_union/lib.php:531-558`), not scoped rules, so an
admin's recoloured purposes reach our containers as well.

> **Testing caveat (theme-side, not ours):** the file-placement callback hangs off the *files* setting,
> while `modiconsenable` only carries the cleanup callback. Enabling the setting **without re-saving
> the icon files** does not place the icons. If overridden icons don't appear while testing, re-save
> the files setting before suspecting our code.

Two caveats: `core_course\output\activity_icon` does **not** exist on Moodle 4.5, so re-emit core's
container markup by hand rather than using that helper; and Boost Union's *separate* per-activity
purpose **re-mapping** (`activitypurpose<modname>`) is scoped to four hard-coded selectors that a
plugin's markup will not match — activities show the module's core default purpose colour. Note it;
don't chase it by faking core's course-page DOM.

| | |
|---|---|
| **Files** | `classes/external/get_competency_courses.php`, `classes/calculator.php` (extract the gate cascade if it pays), `amd/src/accordion.js` (+ build), `styles.css`, `lang/{en,pt_br}` |
| **New returns keys** | `ruleoutcome` on the course; `activities[]` grouped by course — `cmid`, `name`, `modname`, `modtype`, `iconurl`, `purpose`, `url`, `has_link`, `locked`, `restriction_info`, `ruleoutcome`, `has_completion`, `is_completed`. Every flag **pre-resolved server-side**, so the client maps to markup and never re-derives the rule — the same contract `get_course_progress` already uses for sections. |
| **Allowlist** | this WS is the **only** channel for these keys — no other `execute_returns()` needs updating. `clean_returnvalue` strips undeclared keys **silently**, and the existing test asserts only on ids, so assert on the post-clean payload. |
| **Bump** | phase-boundary bump covers it (decision A). One of the two slices where a bump is arguably required *by mechanism* too — see §0.1 |

> **Non-issue, confirmed by you:** the `INNER JOIN` from `competency_coursecomp` at `:83-88` cannot
> miss an activity-only link, because Moodle does not allow linking a competency to an activity
> unless it is already linked to the course. The join is correct, not a limitation.

> **Data flag, resolved (decision B):** core's DB default for `ruleoutcome` is `OUTCOME_EVIDENCE`,
> **not** `OUTCOME_NONE` — so the kit's "no badge for the default, so most cards stay clean" is
> inverted in practice. Badge **only** `OUTCOME_COMPLETE` ("Completes the competency") and
> `OUTCOME_RECOMMEND` ("Sends for review"); render `OUTCOME_EVIDENCE` and `OUTCOME_NONE` clean. The
> grey *Attach evidence* badge from the kit is dropped.

### 5.2 — Locked card: self-enrol + anticipatory date

The one screen (`trk-locked`) with no design coverage anywhere in the backlog's per-screen notes.

| | |
|---|---|
| **Scope** | swap the padlock for `fa-right-to-bracket` + "Enrol to start" when self-enrolment is available; reframe availability as "Opens {date}"; drop a **past** date in learn-more mode |
| **Blocker** | `calculator::current_user_can_self_enrol()` is **`private static`** (`calculator.php:408`) — it must be made public or wrapped. That is a change to established, shipped code; flagging per house rule. |
| **New returns keys** | `can_self_enrol`, `start_date` on `get_course_progress` (typed) |
| **Drop** | `enrol_url` (decision H) — core already bounces a non-enrolled user from `/course/view.php` to the enrol page, so the existing `course_url` suffices. Saves a returns key. |
| **Bump** | phase-boundary bump covers it |

### 5.3 — Single-activity course: the server half

Decision E keeps this, with a **corrected trigger**. The kit's stated trigger ("no trackable
sections") provably cannot occur — `calculator.php:110-131` skips only delegated, invisible and
hidden-entirely sections, so section 0 always survives and the result is never empty. The two real
cases are:

- **(a) one activity** — a course with a single trackable activity, or the single-activity course
  format. Show the **activity name + its own completion state**, with a direct "Go to activity";
  no ring, no timeline.
- **(b) one section, many activities** — no timeline; a single centred progress ring.

**Case (b) is free and client-side** — `sections.length === 1` is already derivable from the existing
payload, so it ships in 6.7 with no server change. **Case (a) is this slice**, because
`get_course_progress` returns only section-level data (`name`, `percentage`, `has_activities`, `url`,
`locked`, `is_completed`, `is_started`) and carries **no activity-level data at all**.

| | |
|---|---|
| **Files** | `classes/calculator.php`, `classes/external/get_course_progress.php`, `lang/{en,pt_br}` |
| **New returns keys** | a single optional `activity` object (`name`, `url`, `completed`) emitted only when the course resolves to one trackable activity |
| **Allowlist** | typed structure — `clean_returnvalue` strips silently. Assert on the post-clean payload in the test. |
| **Bump** | phase-boundary bump covers it |

---

## 8. Phase 6 — new interaction surfaces

Most new code, highest risk. Sequenced last deliberately — everything above improves the views
without it. Grid mode and the modal are the substantial items here; the tracker slices are small.

| Slice | Scope | Bump |
|---|---|---|
| **6.1** | Plan overview: sort + completion filter, client-side over already-loaded rows, persisted via 1.1. Read server-side so the first paint is correct (no flash of default state). Sort ships **Name + Completed-first only** — "Recently updated" is dropped (decision H), no data source exists. | no bump |
| **6.2** | Per-plan favourites + star control, **gated by the existing own-plan guard** `(int) $plan->get('userid') === (int) $USER->id` (`view-plan.php:63-66`) so staff reviewing someone else's plan see no star (decision I). **Includes the ghost card**: "N more to explore" renders **only in favourites-only mode**, so a learner cannot forget the filter is on. | no bump — writes through `core_user/repository`, **no new WS** |
| **6.3** | Tracker completion tabs (`Not completed` / `All`). | no bump |
| **6.4** | Tracker "Continue" shortcut → first `is_started && !is_completed` section. | no bump |
| **6.5** | Tracker completed-card seal + collapsed timeline summary. | no bump |
| **6.6** | **Grid mode + detail modal + full-screen expand.** See the invariant below. | no bump |
| **6.7** | Single-activity/section card rendering — case (a) consumes 5.3, case (b) is pure client-side (`sections.length === 1` → centred ring, no timeline). | no bump |

### 6.6 — the invariant that keeps the modal safe

Decision C rests on grid mode and the accordion never coexisting. **Enforce it explicitly:**

1. Grid mode renders **no accordion detail panes** — the modal is the only detail surface in grid.
2. Toggling list↔grid **tears down** the outgoing surface before building the incoming one, so no
   orphaned pane with a duplicate `local-dimensions-tab-{tabid}-{competencyId}` id survives the swap.
3. Detail stays **lazy per competency** in grid too (it is heavy; do not pre-load the grid).

If a future change ever renders both at once, the instance-suffix work returns — note it in the
commit body so the constraint is discoverable.

Two further constraints on this slice:

- **R4 applies.** A `core/modal` appends to `document.body`, outside the
  `percentagemode-{{…}}` wrapper, so any ring or percentage text inside the modal loses its mode
  styling. Re-apply the wrapper class on the modal root.
- **Do not reuse `amd/src/central/modal_expander.js` as-is** — it writes the **admin hub's** stored
  display preference, so a learner expanding a modal would mutate hub state. Either parameterise the
  preference name or write the learner's own small toggle. (The parked follow-up — porting this
  visual back to the hub — becomes easier if the name is parameterised.)

**6.3–6.5 are three slices, not one,** because they have three different data timings: the tabs and
the seal need the Phase-A completion stamp (`competency_view.js:281`),
Continue needs the Phase-B `get_course_progress` payload, and the collapsed timeline needs both
joined. The tab counts **cannot** be server-rendered the way the plan view does it — completion is
only known after `get_courses_completion_status` resolves — so the pre-AJAX window needs an explicit
stance.

---

## 9. Risks

### R1 — Custom SCSS is customer code

`scss_manager::get_compiled_css()` runs per template and per competency
(`view_plan_summary_page.php:267`,
`view_competency_page.php:206`) and is injected
client-side. **That SCSS is author-written against the current class names.** Phase 4 retires
`.local-dimensions-status-grid`, `-status-cell`, `-status-badge`, `.local-dimensions-ev-slider-*`
and — in 4.6 — the taxonomy-card classes. Every site that styled any of them loses its customisation
with no warning. **This is the only regression in the plan that breaks *customer* code.**
Mitigation: list retired selectors in each commit body; consider keeping old classes as empty
aliases for one release.

> Note the taxonomy-card classes go even though the taxonomy *feature* stays (decision D) — 4.6
> replaces the heavy aside card with a footnote plus a definition modal, so the old selectors are
> genuinely gone. The `taxonomy-card-<type>` accent classes are safe to drop outright: the field map
> records that they **never existed in `styles.css`** — the accent has always been icon-driven.

### R2 — `singlecourseredirect` hides the tracker redesign

The redirect fires *before* the page renders when exactly one accessible course remains
(`view-competency.php:106-135`). Two consequences: every tracker slice is
untestable on such a competency unless you append `noredirect=1`; and `$courses` at `:106` is the
**enrolment-filtered** array, so any cascade change can silently turn a rendering page into a
redirect. Mitigation: test tracker slices with `noredirect=1`, and re-check after 4.1.

### R3 — Bootstrap display utilities vs `[hidden]`

The accordion opens and closes via `content.hidden` (`accordion.js:2229,:2247`).
`.d-flex`/`.d-block` are `!important` and beat `[hidden]`. If the starred-row or grid work uses a
Bootstrap utility in the markup instead of a plugin class, **every accordion item renders permanently
open.** Own the property in a plugin class.

### R4 — Modals render outside the `percentagemode` wrapper

Both templates wrap the entire body in `<div class="percentagemode-{{percentagemode}}">`
(`view_competency.mustache:51`,
`view_plan_summary.mustache:70`) and the only consuming
rules are descendant selectors at `styles.css:469,:473`. A `core/modal` appends to `document.body` —
**outside the wrapper**. Every proposed modal silently loses percentage-mode styling for any ring or
percentage text it renders.

### R5 — `$PAGE->url` and the FAB return context

`view-plan.php:69` stores `set_return_context($PAGE->url, …)` **before** the
renderable. If 6.1 adds `optional_param` deep-linking for sort/filter/view, `$PAGE->url` must be set
with those params before line 69 — or the FAB stores a bare URL. And if it *is* set, the FAB starts
returning learners to a filtered view. Decide when 6.1 is written.

### R6 — Positional string map

`accordion.js:189-240` is a 52-entry **positional** `strMap`. Inserting or deleting an entry
renumbers everything after it and produces *wrong words in unrelated places*, with no error.
**Discipline: append only, never insert, never delete** — which costs zero lines and removes the
footgun without the refactor. (A keyed-map refactor was considered and is **not** recommended: ~100
lines rewritten in working, established code for a benefit the discipline already buys.)

---

## 10. Test strategy

There is no local Moodle (`config.php` absent) — PHPUnit, Behat and the site cannot run here. CI is
the first real runner. Budget **one fix-and-repush** for any new `.feature`.

**Existing coverage is thinner than assumed but not zero:** `tests/helper_cascade_test.php`,
`tests/calculator_filter_test.php`, `tests/calculator_access_test.php`, `tests/helper_subline_test.php`
and `tests/external/get_competency_courses_test.php` (2 tests) do cover data-layer helpers the views
call. **Zero** coverage exists for either renderable, every template, both AMD modules, and 4 of the
5 learner WS. All 11 `.feature` files enter only via `central.php`.

**Worth writing (data layer, cheap, catches real regressions):**

| Slice | Test | Asserts |
|---|---|---|
| 5.1 | extend `tests/external/get_competency_courses_test.php` | `ruleoutcome` present and correct; `modules[]` grouped by course; **a restricted activity is returned locked with the course URL, a hidden one not at all** (decision L); **assert on the post-`clean_returnvalue` payload** so a missing allowlist entry fails loudly |
| 5.3 | `tests/external/get_course_progress_test.php` (same file as 0.2) | the `activity` object appears **only** for a one-trackable-activity course and survives `clean_returnvalue`; a normal multi-section course omits it |
| 0.2 | `tests/external/get_course_progress_test.php` | completion-disabled course returns cleanly, no notices |
| 1.1 | update `tests/privacy/provider_test.php`, `tests/preferences_test.php` | the two new preferences are declared and exported (**both files currently assert the old count and will fail otherwise**) |

**Not worth Behat:** every JS-only slice (Phases 3, 4, 6). Headless-fragile, and the plan's own
Behat rules already exclude tree/drag/scroll interactions. The toolbar Filter panel is the *one*
candidate if you want a single smoke test; I would skip it.

---

## 11. Recommended sequence

```
Phase 0  0.1 filter-tab scope  0.2 payload guard  0.4 isGradeProficient
         0.3 palette tail                                             ⇧BUMP  ← 4 live-bug fixes; unblocks Phase 3
Phase 1  1.1 pref substrate                                                  ← consumed by 6.1, 6.2
Phase 2  2.1 hero   2.2 locked/card CSS   2.3 empty states                   ← visible wins, near-zero risk
Phase 3  3.1 toolbar shell      3.2 plan chips      3.3 tracker chips ⇧BUMP
Phase 4  4.1 rules (client)     4.3 status          4.4 evidence
         4.5 modals (evidence + scale)              4.6 desc/path/tax ⇧BUMP  ← 4.6 needs the 22 definitions drafted
Phase 5  5.1 related content (outcome badge + activities, restrictions + icons)
         5.2 locked self-enrol            5.3 single-activity server   ⇧BUMP  ← the typed-returns slices
Phase 6  6.1 sort/filter  6.2 favourites + ghost  6.3 tabs  6.4 continue
         6.5 seal         6.6 grid + modal        6.7 single-act/section ⇧BUMP
```

**Bump points (decision A):** the **last commit of each phase** carries the `version.php` bump plus a
matching `db/upgrade.php` savepoint — five as sequenced above (Phase 1 is a single no-bump slice and
rides Phase 2's; Phase 0 takes its own because those are live-bug fixes worth shipping cleanly). No
bump is required *by mechanism* anywhere except the typed-returns slices in Phase 5; the rest exist to
move the cache revision so what you test on the site is what you just committed.

**One content prerequisite, not code:** the 22 taxonomy definitions (4.6) need drafting before that
slice. Nothing earlier blocks on it.

---

## 12. Deferred and dropped

Only three items are out of scope. Everything else the grounding flagged as droppable was kept —
see the decisions table at the top for why.

| Item | Why |
|---|---|
| **`showevidencecounts` setting** *(decision H)* | a new setting, 2 lang strings and 4 plumbing touches for a line whose "decisive" count is, by the mock's own precedence rule, always 1. Show a plain total or nothing. |
| **`TRK-SORT` "Recently updated"** *(decision H)* | no data source — `view-competency.php:79-83` selects only `id, fullname, startdate`, and neither tracker WS returns a timestamp. Sort ships **Name + Completed-first** only. |
| **`enrol_url` on `get_course_progress`** *(decision H)* | core already redirects a non-enrolled user to the enrol page; `course_url` suffices. Saves a returns key on a typed structure. |

**Kept, with the grounding's objection answered:**

| Item | Objection raised | Why it is still in |
|---|---|---|
| Taxonomy definitions + modal (4.6) | 22 pieces of prose exist nowhere | Authored as **lang strings**, so sites can override them — which is the point, not a workaround |
| Single-activity card (5.3 / 6.7) | the stated trigger cannot occur | True, and the trigger is corrected: *one activity* and *one section*, not "no sections" |
| Ghost card (6.2) | undefined semantics under search/chip filters | Scoped to **favourites-only mode**, which defines them; it exists to stop learners forgetting the filter is on |
| Grid detail modal + expand (6.6) | id collision with the accordion; hub-preference mutation | Grid-only, so the collision cannot arise (enforced as an invariant); the hub preference is parameterised rather than shared |
| "About this scale" modal (4.5) | empty on most installs | Accepted — renders only when non-empty, so it is silent rather than broken |

---

## 13. Open decisions

**None.** All twelve were answered on 2026-07-20 — see the decisions table at the top. Execution can
begin at Phase 0.

Two items are *verification tasks*, not decisions, and are scheduled inside their slices:

- **`isGradeProficient`** — settle the off-by-one question before 4.4 (see §6). It is a correctness
  check against core, not a choice.
- **Boost Union icon override** — confirm the override layer before 5.1 (see §7), so the icon work is
  written once.

### Answered along the way, with reasoning worth keeping

- **The Rules-tab gate (I).** Rejected on the literal reading because it corrupts the progress maths
  (§0.3) — then dropped entirely, because the target page already enforces the rule. The second
  answer is better than the fix I was about to design.
- **`enrollmentfilter = all` with zero linked courses.** Current behaviour is correct: no linked
  courses ⇒ the section is not shown. Nothing to change; the question only existed as an input to
  the gate that is now dropped.
- **The activity-only link gap.** Not a gap: Moodle does not permit linking a competency to an
  activity unless the course is linked first, so the existing `INNER JOIN` is right.
