# Field map — `BAR` · Contextbar (as-is)

Context selector rendered once above the `dynamic_tabs` (`central.php:145`), **outside** the tab
panes. Switching context does not reload the page: `applyContextToPanes`
(`context.js:173-185`) writes to **every** pane (`.dynamictabs [data-tab-content]`,
`context.js:57`) and reloads only the active one (`refreshActive`, `context.js:190-195`). The
category select is always rendered (hidden in system mode, `contextbar.mustache:82`).

- **Mustache:** [`templates/central/contextbar.mustache`](../../../templates/central/contextbar.mustache), [`showhidden_toggle.mustache`](../../../templates/central/showhidden_toggle.mustache)
- **AMD:** [`amd/src/central/context.js`](../../../amd/src/central/context.js)
- **Renderable:** [`classes/output/central/contextbar.php`](../../../classes/output/central/contextbar.php)
- **Component in the DS:** `bar-contextbar.html` (the bar); `hierarchy-nav.html` section 3 (the icons
  and the indicator of the hub's tabs, shipped — `central.php:108-112`, `:122`, `:125` and
  `styles.css:7583-7631`).

## The bar

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `BAR-ROOT` | `[no label]` | region/root | `contextbar.mustache:58-64` | `data-region="contextbar"` | carries `contexttype`, `categoryid`, `activemode` and the two system counts; `init` stamps `data-initialised="1"` and bails out if already stamped (`context.js:344-348`) |
| `BAR-REFRESH` | Refresh | button | `contextbar.mustache:101-105` | `data-action="refresh"` · `fa fa-rotate` | str `refresh` (core). Reloads the **active pane** through `reloadPane` via `refresh` (`context.js:206-230`), delegated on the bar's click (`:356-359`). Busy discipline: disables (`:212`) + `fa-spin` (`:214`) and undoes both in a `finally` (`:218-229`), returning focus to itself when `disabled` dropped it on the `<body>` (`:226-228`) — `reloadPane` only re-homes focus **inside** the pane (`tabs.js:93-99`), and the button lives outside it. `reloadPane` covers the pane with its own busy curtain; this control only signals the button the user pressed (docblock at `context.js:198-201`). It does **not** re-sync the bar's counter — see the caveat at the end |

## Context (System / Category)

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `BAR-CTX-LABEL` | Context | label/heading | `contextbar.mustache:66` | str `managecompetencies_context` | the group label; the same string repeats in the `btn-group`'s `aria-label` (`:67`) |
| `BAR-CTX-01` | System | toggle button | `contextbar.mustache:68-70` | `data-context="system"` | `btn-primary` when `issystem`, otherwise `btn-outline-secondary`; `fa-globe` icon; click → `setContext` (`context.js:238-272`) |
| `BAR-CTX-02` | Course category | toggle button | `contextbar.mustache:71-73` | `data-context="coursecat"` | the same with `iscoursecat`; `fa-folder-open-o` icon; delegated on the bar's click (`context.js:350-360`) |

## Category

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `BAR-CAT-WRAPPER` | `[no label]` | region | `contextbar.mustache:82` | `data-region="category-wrapper"` | `hidden` when `^iscoursecat`; cloned in `init` as `pristineCategoryNode` (`context.js:366`) and restored by `enhanceCategory` on the way back to category mode (`context.js:305-308`) — `core/form-autocomplete` has no reset API |
| `BAR-CAT-LABEL` | Course category | label | `contextbar.mustache:83-85` | str `managecompetencies_category` | `for="local-dimensions-central-category"` |
| `BAR-CAT-01` | Course category (select) | select → autocomplete | `contextbar.mustache:86` | `data-region="category-select"` | `form-select`; becomes an autocomplete via `enhance` (`context.js:321`); the `change` is re-bound on the enhanced node (`:322-323`) → `setCategory` (`context.js:280-287`) |
| `BAR-CAT-PLACEHOLDER` | "Select a course category" | option | `contextbar.mustache:87` | `value="0"` | placeholder; 0 = no category → `selectedCounts` returns `null` and the counter disappears (`context.js:89-91`, `:113-115`) |
| `BAR-CAT-OPTION` | `name (count)` | option (loop) | `contextbar.mustache:89` | `categoryoptions` | `data-name`/`data-frameworkcount`/`data-templatecount`/`data-hidden`; rendered with `frameworkcount`, **rewritten** by `renderOptionLabels` according to the active tab (`context.js:126-132`) |

## Hidden categories — `BAR-CATHIDDEN`

Sibling of "Show hidden structures" (`EST`/`FWK`), now for **course categories** in the bar's
picker. Design in `bar-contextbar.html`; spec in
[`docs/superpowers/specs/2026-07-18-central-bar-hidden-categories-design.md`](../../superpowers/specs/2026-07-18-central-bar-hidden-categories-design.md).

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `BAR-CATHIDDEN` | Show hidden categories | toggle (partial `showhidden_toggle`) | `contextbar.mustache:75-79`, partial at `showhidden_toggle.mustache:43-48` | `data-action="toggle-hidden-cats"` | **reuses the shared partial** `local_dimensions/central/showhidden_toggle` (`{id,label,action,checked}`, the same one as `EST`/`FWK`) through the `{{#hiddencatstoggle}}` section (`:75`) — the gate is the object itself being `null`. It sits **inside the Context column**, in a `[data-region="hidden-cats"] .mt-2` **below the System/Category group** (`:76`), `hidden` in System mode (`^iscoursecat`); `setContext` follows the switch (`context.js:250-253`). A **real** wrapping `<label>` (`showhidden_toggle.mustache:43`) — Behat's "checkbox" named selector needs for/wrapping, not `aria-label`. Str `central_bar_showhiddencategories` (`lang/{en,pt_br}:64`) |

**Alignment.** The bar uses `align-items-start` (`contextbar.mustache:58`), so the
"Context"/"Course category" labels line up at the top; `BAR-COUNT-01` (`:94`) and `BAR-REFRESH`
(`:101`) carry `align-self-center` so they sit centred in the tall bar. The autocomplete's
**selected-category chip** sits **below** the input, through CSS scoped to the bar
(`styles.css:7559-7582`: `[data-region='category-wrapper']:not([hidden])` becomes a column and
`.form-autocomplete-selection { order: 1 }`) — it depends on core's DOM, so revalidate on upgrade.
The `:not([hidden])` guard is mandatory: the rule beats the UA's `[hidden]{display:none}` and
without it the wrapper would show up in System mode (comment at `styles.css:7570-7572`).

**Semantics.** By default the picker shows only visible categories; the toggle reveals the
`visible=0` ones **that the user can already see** — `make_categories_list()` (`helper.php:2489`)
only brings them for whoever holds `moodle/category:viewhiddencategories` (comment at
`helper.php:2503-2505`). With no reachable hidden category, `contextbar.php:118-126` returns `null`
and the toggle does not render.

**Behaviour (client-side, no `reloadPane`).** The server marks each hidden option with
`data-hidden="1"` (`helper.php:2525` → `contextbar.mustache:89`). `applyHiddenCats`
(`context.js:332-337`) rebuilds the wrapper from the pristine clone and `filterHiddenOptions`
(`context.js:154-164`) drops the hidden ones while the toggle is off, **always preserving the
selected one**. Only the **list** changes — `BAR-COUNT-01` is independent (it counts the context,
not categories).

**Edge case.** A persisted selected category that is hidden → the toggle **starts on**
(`contextbar.php:124`: `$this->showhiddencats || $selectedhidden`), or the current context would
vanish from the list.

**Persistence.** Preference `local_dimensions_central_nav` (`preferences.js:32`), key
`showhiddencats` — default at `preferences.js:40`, written at `context.js:336`, sanitised in
`helper::get_central_prefs` (`helper.php:2347`) and seeded into the render by `central.php:73`/`:78`.
It survives sessions and devices. Privacy already covers `central_nav`
(`classes/privacy/provider.php:62`, `:93`).

**Backend.** Since 2026-09-02 the picker searches on demand: `helper::central_category_search()`
answers the `local_dimensions_search_categories` web service (25 hits, name match, `hidden`
per hit, the toggle passed as `includehidden`), and `helper::central_category_option()` renders
only the selected category server-side; `contextbar.php` decides the toggle from "a hidden
category exists and the viewer may see hidden categories" and its initial state from the
selected option. There is **no** `hashiddencategories` key — the gate is the null
`hiddencatstoggle`.

## Counter

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `BAR-COUNT-01` | `[no label]` | counter region | `contextbar.mustache:94-95` | `data-region="context-count"` | `hidden` when `needscategory`; `renderCounter` (`context.js:103-119`) hides and re-shows it according to `selectedCounts` (`context.js:80-96`) |
| `BAR-COUNT-VALUE` | `[no label]` | number | `contextbar.mustache:96` | `selectedframeworkcount` | the initial value comes from the server; after that `renderCounter` writes templates for `plans`, otherwise frameworks (`context.js:118`) |
| `BAR-COUNT-NOUN` | structures in this context / plans in this context | noun | `contextbar.mustache:97-98` | str `central_frameworks` / `central_plans` | two `span[data-mode]`; `renderCounter` shows only the one for the active mode (`context.js:109-111`). The noun is **explicit about the context** (D5 resolved) — it declares that it counts the System/Category context, not the tab |

**Business rules**

- The bar carries both system counts (`data-systemframeworkcount`, `data-systemtemplatecount`, `contextbar.mustache:63-64`) and each option carries its own two (`:89`), so switching needs no round-trip.
- **It counts only what is visible:** `contextbar.php:83-84` filters `visible => 1`, and the per-category counts do the same (`helper.php:2153`, `:2189`). Hidden frameworks/templates never enter — which is why the Structures tab shows the "· N hidden" suffix and the bar does not.
- **`data-activemode` is write-only:** the template seeds `structure` (`contextbar.mustache:62`) and `context.js:385` rewrites it on every tab switch, but **nothing reads** the attribute (a search for `activemode` across `amd/src`, `styles.css`, `templates/` and `classes/` returns only those two write points). `activeMode()` derives from the active pane (`context.js:68-71`) and the counter's initial value comes from the server (`contextbar.mustache:96`).
- `activeMode()` only distinguishes `plans`: **the Structures and Competencies tabs both fall through to the default `'structure'` branch** (`context.js:68-71`).
- Tab switching is listened for through **jQuery** `shown.bs.tab` over `.dynamictabs a[data-toggle="tab"], .dynamictabs a[data-bs-toggle="tab"]` (`context.js:60`, `:384-392`) — Bootstrap 4 (Moodle 4.5) only emits the event through jQuery. Restoring the saved tab filters on the same selector (`context.js:398-405`).
- The bar lives outside the panes and is **not** re-rendered on a tab refresh (`init`'s docblock, `context.js:340-341`; `reloadPane` only swaps the pane's content, `tabs.js:92`), so its counts stay at their page-load values.

## Decision (D5, 2026-07-14) — the counter · resolved (2026-07-17)

> The contextbar counts the **context** (System/Category), not the tab — so the number was always
> right; it was the **noun** that read as if it described the tab. On the Competencies tab,
> `activeMode()` falls through to `'structure'` and the counter shows the context's **structures**
> while the tab's subheader shows the **competency** count — two numbers of different scopes side
> by side.
> **Shipped fix:** the noun became **explicit about the context** — `central_frameworks` =
> "structures in this context", `central_plans` = "plans in this context" (`lang/{en,pt_br}:126` and
> `:226`, both used **only** here) — so the counter declares its own scope instead of looking as if
> it described the tab. The number did **not** change: D5 preserves counting the context, and only
> the label became honest. It was lang-only (no bump).
> **Alternative recorded and discarded:** making the counter follow the active tab. Discarded
> because it contradicts the contextbar's declared purpose. Do not re-litigate without changing
> this note.
> **Context:** the hub has **three** counters where mtube has one.

**Mechanics (verified in the code):**

- The tab whose `data-tab-content="structure"` is **labelled "Competencies"** (`managecompetencies_structure`, via `central.php:101` and `dynamictabs/structure.php:48-49`) — the shortname says `structure`, the label says Competencies.
- `activeMode()` (`context.js:68-71`) returns `'plans'` only when `tabContent === 'plans'`; the Competencies tab falls through to the default `'structure'` branch.
- So `BAR-COUNT-VALUE` = `selectedframeworkcount` and `BAR-COUNT-NOUN` = `central_frameworks` = "structures in this context".
- The noun **matches the number** (both speak of the context's structures) and **declares the scope**, so it does not read as a figure about the tab — that is the reading D5 fixes, and "in this context" makes it literal.

**The three counters:**

| # | Where | Origin | Counts |
| --- | --- | --- | --- |
| 1 | contextbar (`BAR-COUNT-01`) | `contextbar.mustache:94-99` | the context's **visible** structures/plans |
| 2 | Structures tab toolbar | `frameworks.mustache:77-78` | `central_frameworks_listed` ("Structures listed"): `frameworkcount` + "· N hidden" |
| 3 | Competencies tab subheader | `structure.mustache:121-123` | `managecompetencies_items` ("items"): `competencycount` of the selected framework |

## IMP-05 — refresh on the contextbar (shipped, `mtube: refresh`)

> Delivered: the contextbar gained the `BAR-REFRESH` button (above), which reloads the **active
> pane** through the `reloadPane` that already existed (`tabs.js:69-108`) and that no UI control
> exposed. No new string — it reuses `{{#str}}refresh, moodle{{/str}}` + `fa fa-rotate`. The busy discipline
> was copied from mtube (disable + `fa-spin` in a `finally`); what was **not** copied is mtube's
> defect of leaving the subtitle stale — see the counter caveat below.

**What shipped, verified:**

- `reloadPane` (`tabs.js:69-108`) has **one** UI control that fires it: the `refresh`
  (`context.js:206-230`), delegated on the bar's click (`:356-359`). Full census: **24** calls
  across 5 modules — `structure` 9 (`structure.js:730,739,748,764,808,836,960,1036,1472`),
  `frameworks` 6 (`frameworks.js:180,270,374,386,419,522`), `plans` 6
  (`plans.js:103,233,614,668,673,825`), `context` 2 (`context.js:193,217`) and `competency_browser`
  1 (`competency_browser.js:69`). Of those, **only `context.js:217` is a UI affordance**; the other
  23 are automatic refreshes (post-action, post-context-switch or post-selection-switch).
- The icon and the string came from the `data-action="enrol-refresh"` buttons of the enrolment
  pane. Those buttons **no longer exist** (zero hits for `enrol-refresh` in `templates/` and
  `amd/src/`): the pane now hands a handle to the modal header (`enrol_methods.js:1111`). The
  precedent is historical.
- Busy discipline: `button.disabled = true` (`context.js:212`) + `fa-spin` (`:214`), and the
  `finally` (`:218-229`) re-enables, removes the spin **and** returns focus to the control when
  `disabled` dropped it on the `<body>` — `reloadPane` only re-homes focus **inside** the pane
  (`tabs.js:93-99`), and the button lives outside it. A reload that fails releases the control
  instead of spinning forever.
- The pane itself gained its own busy curtain inside `reloadPane` (see `est-competencies.md`, IMP-03):
  `LOADING_CLASS` + `aria-busy="true"` (`tabs.js:44`, `:77-80`), cleared in the `finally`
  (`:103-106`). That is why `BAR-REFRESH` only has to signal itself.

**The counter caveat — what did NOT ship, and why.** `BAR-COUNT-01` is **not** re-synced by the
refresh, deliberately: the bar lives outside the panes and is not re-rendered by `reloadPane`
(`context.js:340-341`; `tabs.js:92` only swaps the pane's content), and its counts are render-time
attributes (`contextbar.mustache:63-64`, `:89`, `:96`). Re-syncing them would require a fresh count
from the server — **none of the 43 functions in `db/services.php` returns a context count** — or a
client-side recompute that would duplicate the server's counting (two aggregate queries,
`helper.php:2142-2201`). **And it is not a regression:** the counter already goes stale today on
every add/remove, for the same reason; the refresh does not make that worse, it just does not fix
it. The analogue of mtube's stale-subtitle defect is **recorded as debt**, not pretended resolved —
`BAR-REFRESH` makes no claim that the counter updates.

> **Alternative discarded:** reading the fresh count off the reloaded pane (the Structures toolbar
> carries `frameworkcount`). Rejected: the bar's counter counts the **context** (D5), which is not
> what the pane's toolbar counts (frameworks listed, with a hidden suffix), so the reading would be
> unfaithful for 2 of the 3 modes and would couple the bar to the pane's internal DOM. It waits for
> a fresh-count source to exist.

## Pending

- **A three-segment adaptive trail** (Context → Structure → Competency, with the active segment
  accented), designed in `hierarchy-nav.html`. **Not built** and, unlike the rest of the kit, it is
  a **divergent** redesign of the shipped bar rather than an increment: a search for
  `adaptive|trail|breadcrumb|trilha` across `templates/central/*.mustache`, `amd/src/central/*.js`
  and `classes/output/central/*.php` returns only unrelated prose ("adaptive counts" in
  `contextbar.mustache:23` and `contextbar.php:39`) and an autocomplete label helper
  (`competency_datasource.js:90-92`) — no trail markup, no third segment. What the bar has is the
  context + category pair (`contextbar.mustache:65-92`) plus the counter and refresh (`:94-105`).
