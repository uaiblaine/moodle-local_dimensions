# Field map — `MOD.BROWSER` · Browse-structures modal (as-is)

Modal opened from the **+ Add competency** button in the **sticky footer** of the Plans tab. It picks a
**structure** in a `<select>` and, below it, builds the **same tree browser** as the "Related
competencies" modal (shared partial) — a debounced filter, a paths toggle, checkbox rows, "Load more"
and infinite scroll. The ticked ones go into the plan's template and the pane reloads.

It is the **only** point in the whole plugin that calls `setSaveButtonText`, and the only one where
**core's close-on-save is right on the normal path** — the opposite of `MOD.RELATED`'s wiring, which
vetoes every save. Detailed at the end of this map.

- **Mustache:** [`competency_browser.mustache`](../../../templates/central/competency_browser.mustache) (56, selector + shell) · [`competency_tree_browser.mustache`](../../../templates/central/competency_tree_browser.mustache) (44, partial shared with `MOD.RELATED`) · trigger in [`plans.mustache`](../../../templates/central/plans.mustache) (`:469-472`)
- **AMD:** [`competency_browser.js`](../../../amd/src/central/competency_browser.js) (148) — builds the browser through [`competency_tree_browser.js`](../../../amd/src/central/competency_tree_browser.js) (511, `initBrowser`/`applyMode`/`getCheckedIds`/`destroyBrowser`); uses `errors.js` (`notifyError`) and `tabs.js` (`reloadPane`, imported at `:34`)
- **WS:** `core_competency_list_competency_frameworks` (`js:87-90`, populates the selector), `local_dimensions_browse_competencies` (`db/services.php:109-116` → [`classes/external/browse_competencies.php`](../../../classes/external/browse_competencies.php), the tree/search), core's `core_competency_add_competency_to_template` (`js:61`, the write)
- **CSS:** **none**. A `grep -n 'local-dimensions-cb\|competency-browser' styles.css` returns **one** line — `:7670`, and it is scoped under `.local-dimensions-central-related`. See the note on the uncapped box below.
- **Screen in the DS:** [`screens/mod-browser.html`](../screens/mod-browser.html) — with the structure-switch storyboard and the tick→enable demonstration, both driven and measured.

**Abbreviations used in the tables** (the `MOD.RELATED` map uses the same ones): `js:` =
`amd/src/central/competency_browser.js` · `tree.js:` = `amd/src/central/competency_tree_browser.js`
· `tree.mustache:` = `templates/central/competency_tree_browser.mustache`. Paths starting with
`lib/` are **core's**; for those this map cites a **symbol**, not a line — core's numbers change
between 4.5 and 5.2, which this plugin supports.

## The search is server-side and paginated

The filter is **server-side**, not a client-side sieve over an already-loaded list:
`local_dimensions_browse_competencies` takes `limitfrom`/`limitnum`
(`browse_competencies.php:60-61`), the page is **25** (`PAGE_SIZE`, `tree.js:46`), the debounce is
**250 ms** (`tree.js:387`) and the minimum is **2** characters (`SEARCH_MIN`, `tree.js:47`, checked at
`:382`); below that it falls back to tree mode (`:384-385`). What paginates the top of the list is the
`IntersectionObserver` **sentinel** (`tree.js:490-498`) — infinite scroll, no page numbers.

> **Retired:** `paginated-picker.html` once sketched **numbered pagination** for this list. It went
> when the card became the form-autocomplete overflow warning (a different control) — pagination is
> already server-side here, and the shape chosen was the sentinel.

> **Resync 2026-07-15 — the previous map described a client-side filter that no longer exists, an AMD
> file this modal does not import, and a label the plugin renamed.** Measured, not estimated:
>
> - **6 refs; 4 broken (4/6).** A `grep -oE '[a-z_/.]+\.(php|js|mustache|css):[0-9]+(-[0-9]+)?'`
>   over the old file returns **exactly 6**, all of them in `competency_browser.mustache` — and that
>   file has **56 lines**:
>   - `:40` (`MOD.BROWSER-FW-LABEL`) and `:43` (`MOD.BROWSER-FW`) **are still right**. They survived
>     because `44ac031` **removed** lines from the top of the file instead of adding them: the filter
>     and the toggle moved out to the partial and what stayed above the selector did not shift.
>   - `:53-57` (called `MOD.BROWSER-PATHTOGGLE`) is the worst break of the series, and is worth reading
>     slowly: the **first** line of the range resolves today to the `{{#str}}` of
>     `central_browseframeworks_noframeworks` — that is, it points at the content of
>     **`MOD.BROWSER-EMPTY`**, a **real control, under another ID** in this same map. The next three
>     are `</div>`, `{{/hasframeworks}}`, `</div>`, and the fifth (`:57`) falls **past the end of the
>     file**. Anyone checking sees a plausible string and moves on.
>   - `:59` (`MOD.BROWSER-LIST`) and `:62` (`MOD.BROWSER-EMPTY`) point **past the end of the file**.
>     The map sent `EMPTY` into the void and `PATHTOGGLE` to `EMPTY` — the two IDs swapped places,
>     and neither in the right one.
>   - `:50` (`MOD.BROWSER-FILTER`) resolves to `{{/hasframeworks}}`.
> - **Zero JS refs**, as in every earlier map of the series — and here that erased **10 of the 19**
>   controls in this map: the ones that exist only on a `.js` line are the title, the save, the
>   structure-switch rule, and the whole tree row (chevron, checkbox, path, lock, "Load more", empty
>   state, sentinel). The old map itself admitted the gap in a footnote (*"Injected via JS (detail it
>   when inventorying `competency_browser.js`)"*) and proposed **one** hypothetical ID,
>   `MOD.BROWSER-ROW-*`. **The old map covered 6 controls; this one covers 19** — counted with
>   `grep -oE '^\| \`MOD\.BROWSER-[A-Z-]+\`' | sort -u | wc -l`. (It was 20 until `29ffb41`
>   handed the trigger back to `pln-plans.md` as `PLN-BROWSE`; the count had lagged behind.)
> - **`competency_datasource.js` does not belong to this modal.** The old map's **AMD** bullet linked
>   it; a `grep -rn 'competency_datasource' amd/src/` returns **two** hits — its own `@module`
>   declaration (`:19`) and **`plans.js:48`**, which uses it as the `DATASOURCE` of the **Plans tab's**
>   autocomplete. `competency_browser.js` does not import it: its 8 `import`s (`:27-34`) are
>   `core/ajax`, `core/modal_save_cancel`, `core/modal_events`, `errors`, `core/templates`, `core/str`,
>   `competency_tree_browser` and `tabs`.
> - **The label aged twice.** The map and the screen said "framework"; `f817430`
>   ("reorder + rebrand tabs", 2026-07-07) rewrote the strings **without** renaming the keys:
>   `central_browseframeworks` = "Browse **structures**", `central_browseframeworks_framework`
>   = "**Structure**". The keys still say `framework` — the user reads `structure`.
> - **The filter placeholder was never "Search competency…".** It is
>   `central_browseframeworks_filter` = "**Filter competencies**", and it serves as `placeholder`
>   **and** `aria-label` (`tree.mustache:33-34`).

## Trigger (on the Plans tab, outside the modal)

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `PLN-BROWSE` ↗ | Add competency | button (trigger) — **ID from `pln-plans.md`, not from this map**: the trigger belongs to the surface it lives on, and this map only references it (same convention as `MOD.DELPLANS ← PLN-DELETE`) | `plans.mustache:495-498` | `data-action="browse-frameworks"` · `fa fa-plus` | str **`central_addcompetency`** — **not** `central_browseframeworks` (that one is the modal title). Lives in the `data-region="plans-footer-actions"` holder (`:488`), which is born `hidden` and is moved into the page's `#sticky-footer` by `plans.js` (comment at `:484-487`); it is only emitted under `{{#canmanage}}` (`:483`). `plans.js:713` calls `showCompetencyBrowser(pane, region)` (imported at `:37`) |

## Modal shell

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `MOD.BROWSER-TITLE` | Browse structures | title | `competency_browser.js:103` (str), `:106` (`ModalSaveCancel.create`) | str `central_browseframeworks` | `setRemoveOnClose(true)` at `:108`. It is a `ModalSaveCancel`, like `MOD.RELATED` since `0898acf` (`related_competencies.js:239`) — what separates the two is **not** the modal class, it is the save wiring (see the end of this map) |
| `MOD.BROWSER-ROOT` | `[no label]` | region/root | `competency_browser.mustache:37` | `data-region="competency-browser"` · `.local-dimensions-competency-browser` | **the class carries no styling at all**: a `grep -n 'local-dimensions-competency-browser' styles.css` returns nothing. It is a dead hook — left over from when the modal owned the tree |
| `MOD.BROWSER-SAVE` | Add selected | primary button (footer) | `competency_browser.js:107` (`setSaveButtonText`), `:102-105` (str) | str `central_browseframeworks_add` · `data-action="save"` (core) | **the plugin's only `setSaveButtonText` call** — `grep -rn 'setSaveButtonText' amd/src/` returns 1 line. **Born disabled** (`:110`) and follows the selection: `updateAddButton` (`:48-50`) re-enables it once at least one row is ticked. See the empty-save section below |
| `MOD.BROWSER-CANCEL` | Cancel | button (footer) | core (`lib/templates/modal_save_cancel.mustache`) | `data-action="cancel"` · core str `cancel` | comes free with `ModalSaveCancel`; the plugin does not touch it |
| `MOD.BROWSER-X` | Close | close chip | core (`lib/templates/modal.mustache`) | — | gets the hub's `1.75rem` blue restyle (`styles.css:5374-5388`, glyph at `:5088-5096`, hover at `:5098-5102`): the root has no `.local-dimensions-related-modal` and the body matches `[class*='local-dimensions-']` — the two sides of the selector. The match does **not** depend on `MOD.BROWSER-ROOT`'s dead class: `.local-dimensions-cb-scroll` and `.local-dimensions-competency-browser-list` (`tree.mustache:42-43`) would already suffice |

## Body — the structure selector and the empty state

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `MOD.BROWSER-FW-LABEL` | Structure | label | `competency_browser.mustache:40-42` | str `central_browseframeworks_framework` | a real `<label>`, with `for="local-dimensions-cb-framework"` — the target is a field, unlike `MOD.RELATED-ADDLABEL`, which is a `<div>` because it points at a tree |
| `MOD.BROWSER-FW` | Structure (select) | select | `competency_browser.mustache:43-47` | `data-region="framework"` · `class="form-select"` | `form-select`, never `custom-select` (the BS5 classes are bridged on 4.5). Populated by `core_competency_list_competency_frameworks` (`js:87-90`) with `sort: 'shortname'`, `includes: 'parents'` (structures from parent contexts are included) and `onlyvisible: true`. The **first** one comes selected (`selected: index === 0`, `js:97`) and seeds `state.frameworkid` (`js:114`). **Fixed id**, no `{{uniqid}}` — it only avoids a collision because the modal is `setRemoveOnClose(true)` and there are never two |
| `MOD.BROWSER-FWSWITCH` | `[no label]` | rule | `competency_browser.js:128-135` | `change` listener | **switching structure clears the selection** (`state.checked.clear()`, `:132`) and reloads the tree from scratch (`applyMode(state, 'tree', '')`, `:134`). The reason is in the code itself (`:130-131`): keeping the ticks across the switch would **add** competencies from a structure that left the screen. The switch also recomputes the footer (`updateAddButton`, `:133`) — without that line "Add selected" would stay enabled over a selection that had just been emptied. It remains the **only `.clear()`** of `state.checked` in this modal: what exists outside it is **seeding**, not clearing — the `state` literal creates the Set (`:115`) and `initBrowser` recreates it once on open (`tree.js:463`) — and the row-click `syncChecked` (`tree.js:399-407`) only adds/removes per rendered checkbox, never empties the Set; `getCheckedIds` neither consumes nor empties it |
| `MOD.BROWSER-EMPTY` | No competency structures available. | empty state | `competency_browser.mustache:51-55` (the `alert` at `:52-54`) | `.alert.alert-info` · `role="status"` | str `central_browseframeworks_noframeworks`. **It replaces the whole body** (`{{^hasframeworks}}`): no selector, no tree. And the JS follows — the entire wiring block sits under `if (frameworks.length)` (`js:125-142`), so neither the listener nor `initBrowser` runs. **But the footer stays, disabled**: the `setButtonDisabled('save', true)` at `:110` runs **before** the `if`, and with no structures nothing re-enables it (`updateAddButton` is only bound inside the `if`). Before `e14977c`, "Add selected" sat there **enabled** over a body with nothing to tick — and clicking it **threw a `TypeError`**: what seeded `state.checked` was `initBrowser`, which the `if` skips, but the save was bound unconditionally, so `getCheckedIds` reached `Array.from(undefined)`. The `state` literal now seeds the Set (`:115`), which **eliminates** the failure instead of leaving it unreachable behind a disabled button |

## The tree (partial shared with `MOD.RELATED`)

`competency_browser.mustache:49` includes the whole partial, **below** the selector; what drives it is
`competency_tree_browser.js`, with the `state` built at `js:113-123`.

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `MOD.BROWSER-FILTER` | Filter competencies | search field | `competency_tree_browser.mustache:31-35` | `data-region="filter"` · `placeholder` **and** `aria-label` = the same str | str `central_browseframeworks_filter`. **250 ms** debounce (`tree.js:387`), **2**-character minimum (`SEARCH_MIN`, `:47`, checked at `:382`); below that it falls back to tree mode (`:384-385`). The search is **server-side** and **within the chosen structure** (`frameworkid` travels in the args, `:265`) — it does not cross structures |
| `MOD.BROWSER-PATHS` | Show paths | switch | `competency_tree_browser.mustache:36-41` | `data-region="path-toggle"` · id with `{{uniqid}}` | str `central_browseframeworks_showpaths`. In **search** mode it is forced `checked` **and** `disabled` (`tree.js:328-329`), because `pathsVisible` is always true there anyway (`:72`). The `{{uniqid}}` comes from the **JS**, not from the PHP helper: `Templates.renderForPromise` goes through core's renderer (`lib/amd/src/local/templates/renderer.js`), which does `context.uniqid = (Renderer.uniqInstances++)` — a new integer per render. That is what lets the two modals host the same partial |
| `MOD.BROWSER-LIST` | `[no label]` | JS container | `competency_tree_browser.mustache:42-44` | `data-region="competency-list"` · `.local-dimensions-competency-browser-list` inside `.local-dimensions-cb-scroll` | **the box here is uncapped**: the `max-height:40vh` + `overflow-y:auto` at `styles.css:7670-7673` is scoped under `.local-dimensions-central-related`, and the plugin's own comment says so in as many words (`:7282`: *"the Browse frameworks modal leaves the box uncapped"*). What scrolls, here, is the `.modal-body` |
| `MOD.BROWSER-ROW` | {name} | row (checkbox) | `competency_tree_browser.js:82-156` (`makeNode`; the checkbox at `:111-123`) | `input.form-check-input` + name + path | **no `for`**: the whole row is the click target (`:125-126`, `onListClick` `:416-442`), with range selection on Shift (`handleShiftSelect`, `:353-367`). The selection is **persistent** (`state.checked`) and survives a re-render (`:120-122`), so `getCheckedIds` returns **also** what the current filter is not showing. Indents **20px** per level (`INDENT_STEP`, `:48`, applied at `:94`) |
| `MOD.BROWSER-ROW-TOGGLE` | See more: {name} | chevron (per row) | `competency_tree_browser.js:96-109` | `data-action="toggle"` · `aria-expanded` · `fa fa-chevron-right` | `aria-label` = str **`show_more`** ("See more") + `: {name}` (`:106`), seeded in `initBrowser` (`:464`). With no children the button stays in the DOM and takes `.invisible` (`:108`) — it keeps the columns aligned. Children load **on first expansion** (`toggleNode` `:229-250` → `loadChildren` `:201-220`), also 25 at a time |
| `MOD.BROWSER-ROW-LOCK` | {name} (Already on this plan) | locked row | `competency_tree_browser.js:117-119`, `:133` | `checked` + `disabled` · suffix on the name | `state.excluded` comes from `region.dataset.excludeids` (`js:80`, `:119`) — the ids already on the template, published by `plans.mustache:134` (`data-excludeids`, documented at `:55`). The suffix comes from `state.excludedsuffix` (`js:117`) → str `central_browseframeworks_alreadyadded` ("Already on this plan"); here it is **constant**, whereas `MOD.RELATED` passes a function that picks between two labels. `getCheckedIds` filters the excluded ones again on the way out (`tree.js:451-453`) |
| `MOD.BROWSER-ROW-PATH` | `[no label]` | ancestor path | `competency_tree_browser.js:132-137` | `.local-dimensions-cb-path.text-muted.small` · `hidden` according to `pathsVisible` | comes from the WS (`browse_competencies.php:136` → `helper::competency_breadcrumbs`), **empty for roots** (`execute_returns`, `:176`). Toggled in bulk by `applyPathVisibility` (`:338-343`) |
| `MOD.BROWSER-MORE` | Load more | button | `competency_tree_browser.js:180-192` | `data-role="load-more"` | str `central_browseframeworks_loadmore` (`js:83`). It appears **only among children** (`loadChildren` `:217-219`): the top of the list does not use it — there the sentinel paginates. It disappears when clicked (`:188`) |
| `MOD.BROWSER-TREE-EMPTY` | No competencies in this structure. | empty state | `competency_tree_browser.js:306-311` (str at `competency_browser.js:84`) | `.text-muted.small` · `role="status"` | str `central_browseframeworks_empty`. It is the **tree's** empty state (a structure with no competencies, or a search with no hit) — not to be confused with `MOD.BROWSER-EMPTY`, which is the **structures** empty state and comes from the Mustache |
| `MOD.BROWSER-SENTINEL` | `[no label]` | infinite scroll | `competency_tree_browser.js:490-498` | empty `<div>` + `IntersectionObserver` | inserted **after** the list but **inside** the scroll box (`insertAdjacentElement('afterend')`, `:491`), with the reason in the comment at `:487-489`. Disconnected on `ModalEvents.hidden` (`js:145` → `destroyBrowser`, `tree.js:507-511`) |

## The add — and what it does not do

`addSelected` (`js:59-70`) fires **N calls in parallel**, one
`core_competency_add_competency_to_template` per ticked id (`:60-63`), with the `templateid` read from
**`pane.dataset`** (`:62`) — not from `region`, unlike `contextid` (`:89`) and `excludeids`
(`:80`). On success, `reloadPane(state.pane)` (`:69`) redraws the whole Plans tab; on error,
`notifyError`.

Two absences and one guard, all verified:

- **No toast, no `flash`.** The feedback is the reloaded pane with the competency in the list. It makes
  sense here and would not in `MOD.RELATED`: this modal **closes**, so there is no "place" for the user
  to come back to. (Measured against a control: `grep -c 'addToast\|flash(' competency_browser.js`
  returns **0**, the same grep on `related_competencies.js` returns **4**.)
- **No partial undo.** As in `MOD.RELATED`, a call that fails mid-batch does not undo the earlier ones.
  Unlike `MOD.RELATED`, there is no `finally` re-syncing here — the `.catch` (`:69`) only notifies, and
  the pane does **not** reload. The modal has already closed. (Control: `grep -c finally` returns
  **0** here and **1** in `related_competencies.js`.)
- **The empty-selection guard is a backstop, not the main defence.** The button is **born disabled**
  (`js:110`) and follows the selection: `updateAddButton` (`:48-50`) recomputes on the list's `click`
  and `change` (`:140-141`, registered **after** `initBrowser` so that the tree's handler has already
  synced `state.checked` — the reason is in the comment at `:138-139`) and on the structure switch
  (`:133`). Behind that, the `if (!calls.length)` (`:64-68`) calls `event.preventDefault()` (`:66`)
  **before** the `return`, with the reason in the comment at `:65`. A disabled button promises nothing,
  so there is no message to give — it is the same answer as `MOD.RELATED`, not a second one.

Before `e14977c` the defence was only the `return`, and the `return` did **not** stop core's close:
clicking "Add selected" with nothing ticked closed the modal, added nothing and said nothing. The save
was bound as a **zero-argument** arrow, so the guard did not even have the `event` to prevent —
structurally it could not step out of core's mechanics.

> **Here the `grep` lied — worth keeping as a lesson, not as a footnote.** This map used to conclude
> that "the button never disables", citing `grep -n 'disabled' amd/src/central/competency_browser.js` →
> **zero**. That command **still returns zero today**, with the button disabled by two lines: `grep` is
> **case-sensitive** and what the file has is `setButtonDisabled`, with a capital **D**. `grep -in`
> returns **two** — `:49` and `:110`. The search failed, and the failure was never proof of absence.
> (In the tree, the lowercase `disabled` appears 5 times outside the docblock, and none of them is the
> footer's: `tree.js:119` marks the **locked row**, `:329` locks the paths switch in search mode, and
> `:355`/`:400`/`:433` are `:not(:disabled)` read guards.)

## Close-on-save: why it is right here and vetoed in `MOD.RELATED`

`ModalSaveCancel.registerEventListeners()` calls `registerCloseOnSave()`; core's handler fires
`ModalEvents.save` and, **if nobody called `preventDefault()`**, closes the dialogue (`destroy()`
when `removeOnClose`, otherwise `hide()`). Core symbols in `lib/amd/src/modal_save_cancel.js` and
`lib/amd/src/modal.js` — with no line numbers on purpose, because they vary across the 4.5–5.2 range.

This modal binds the save at `js:144` with a **conditional** `preventDefault`: a `grep -n
'preventDefault' amd/src/central/competency_browser.js` returns **one** line — `:66`, inside the
empty-selection guard. On the normal path (at least one ticked) nobody prevents anything and it
**closes**, and that is **right**: it is a one-shot picker, the result shows up in the pane behind it.

`MOD.RELATED` does the opposite, and that is what a future session must not "simplify" on the
assumption that the two neighbours converged. It **manages**: it writes on every click, raises a toast,
flashes the new row, toggles the empty state and the user **stays** — so its handler calls
`event.preventDefault()` **unconditionally**, as its first instruction
(`related_competencies.js:290-296`, with the reason in the comment at `:291-293`). **Conditional and
unconditional are opposites in intent, not neighbours in degree**: the `preventDefault` here exists so
as **not** to close on a no-op and **preserves** close-on-save on the normal path; `MOD.RELATED`'s
vetoes **every** save — that is, it takes the `ModalSaveCancel` footer and switches off the one thing
`ModalSaveCancel` adds to `Modal`. **The modal class and the call are reused; the save wiring is not.**

> **The `format_mtube` precedent was the path taken — with one line saved.** mtube's
> `competency_picker` already did the two things that were missing here:
> `_setSaveEnabled(this._selectedCompetencies.length > 0)` right after the `setSaveButtonText`, and a
> `ModalEvents.save` that calls `event.preventDefault()` **only** when the selection is empty, letting
> core close on the normal path — which is, line for line, the shape this modal has today. The
> difference: mtube's `_setSaveEnabled` is three hand-written lines
> (`this._modal.getFooter().find(this._modal.getActionSelector('save')).prop('disabled', !enabled)`)
> over two public core APIs — and core **already wraps those same three lines** in
> `setButtonDisabled(action, disabled)` (`lib/amd/src/modal.js`, whose body is the same
> `getFooter().find(getActionSelector(action))`). This modal calls the wrapper. The twin **inside the
> plugin** is `related_competencies.js`'s `updateAddButton` (`:125-126`) — same name, same rule
> (`getCheckedIds(state).length === 0`) and, since `0898acf`, **the same wrapper**:
> `state.modal.setButtonDisabled('save', …)`. The in-body button's `state.addbtnEl.disabled` no longer
> exists (`grep -c 'addbtnEl' related_competencies.js` → **0**).
>
> **Where to read that code, because it is not where you would expect.** `format_mtube` **has no
> `amd/src`** — an `ls` of the plugin root shows `amd/` with **`build/` and nothing else**. The source
> is only recoverable through the sourcemap's `sourcesContent`
> (`amd/build/features/competency_picker.min.js.map`, whose `sources[0]` is
> `../../src/features/competency_picker.js`, 607 lines). That is where the numbers above come from:
> `ModalSaveCancel.create` at `:137-142` (with `removeOnClose: true` **in the config**),
> `setSaveButtonText` at `:145`, `_setSaveEnabled` at `:146` and `:470-475`, the empty-selection
> `preventDefault` at `:151-156`.

## Do not re-litigate — what the kit used to say wrongly

| What the kit said | What is live |
| --- | --- |
| "Browse frameworks" · label "Framework" | "Browse structures" · label "Structure" (`f817430` rewrote the strings, kept the keys) |
| **Client-side** filter over a loaded list | **Server-side** search (`local_dimensions_browse_competencies`), 250 ms debounce, 2-char minimum |
| Placeholder "Search competency…" | "Filter competencies" (`central_browseframeworks_filter`), also the `aria-label` |
| Flat list of checkboxes | Lazy **tree** with a per-row chevron, 20px indent, children 25 at a time |
| Numbered pagination (the `paginated-picker.html` sketch, retired) | **Infinite scroll** with a sentinel + `IntersectionObserver` (pagination is already server-side) |
| No mention of locked rows | `data-excludeids` locks the ones already on the plan, with the "(Already on this plan)" suffix |
| `competency_datasource.js` as this modal's AMD | it is the datasource of the **Plans tab's** autocomplete (`plans.js:48`); this modal does not import it |
| Footer "always enabled"; tick→enable as something to do | **live** since `e14977c`: Add is born disabled (`js:110`), follows the selection (`updateAddButton`) and the `preventDefault` (`:66`) is a backstop |
| `MOD.RELATED` uses `Modal`, this one uses `ModalSaveCancel` — opposite classes | **both** are `ModalSaveCancel` since `0898acf`; what differs is the `preventDefault` (conditional here, unconditional there) |
