# Field map — `MOD.RELATED` · Related competencies modal (as-is)

Modal opened by the **⇄ Related competencies** button in the Competencies tab's **sticky footer**. It
lists a competency's current relations, removes them one at a time, and adds new ones through the
**same tree browser** as the "Browse structures" modal (a shared partial), **minus** the structure
selector — a relation only reaches competencies from the same structure. The relation rows and the
tree rows are **all built in JS**; the Mustache is only the shell.

It is a `core/modal_save_cancel`: the primary action ("Add selected") is the **footer's save
button** (Cancel relabelled "Close", because the modal manages in place and has nothing to cancel).
The section **The primary action lives in the footer core already builds** records the mechanics and
the reason it cannot be copied from its neighbour `MOD.BROWSER`.

- **Mustache:** [`related_competencies.mustache`](../../../templates/central/related_competencies.mustache) (40 lines, shell), [`competency_tree_browser.mustache`](../../../templates/central/competency_tree_browser.mustache) (44 lines, partial shared with `MOD.BROWSER`)
- **AMD:** [`related_competencies.js`](../../../amd/src/central/related_competencies.js) (299 lines) — builds the browser through [`competency_tree_browser.js`](../../../amd/src/central/competency_tree_browser.js) (511 lines, `initBrowser`/`applyMode`/`getCheckedIds`/`destroyBrowser`), flashes the new row with the shared helper [`flash.js`](../../../amd/src/central/flash.js) (import `:31`) and uses `errors.js` (`notifyError`, import `:35`)
- **WS:** `local_dimensions_list_related_competencies` (`db/services.php:133-134` → [`classes/external/list_related_competencies.php`](../../../classes/external/list_related_competencies.php), relations + ancestor path), `local_dimensions_browse_competencies` (`db/services.php:109-110`, the tree/search), core's `core_competency_add_related_competency` and `core_competency_remove_related_competency` (writing)
- **CSS:** [`styles.css:7278-7287`](../../../styles.css) (the 40vh cap on the tree box, exclusive to this modal), [`styles.css:6586-6658`](../../../styles.css) (the detail's chips)
- **Screen in the DS:** [`screens/mod-related.html`](../screens/mod-related.html) (with the scripted, measured check→enable demonstration)

> **A module and a button that do not exist — do not go looking for them.** `related_datasource.js` was born in `da4489a`
> and was **deleted in `44ac031`** ("related modal adds via the shared framework tree browser",
> −61 lines): adding is not an autocomplete, it is the tree. And the body's
> `<button data-action="add-selected">` left along with `SELECTORS.addSelected` when the action moved
> down into the footer — the ID `MOD.RELATED-ADDBTN` is **retired**; what answers for the primary
> action is `MOD.RELATED-FOOT`.

## Trigger and chips (in the Competencies tab, outside the modal)

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `MOD.RELATED-ACTION` | Related competencies | button (trigger) | `structure_footer_actions.mustache:57-60` | `data-action="related"` · `fa fa-exchange` | str `central_related_button`. Lives in the tab's shared **sticky footer**, not in a row — and not in `structure.mustache`. `structure.js:1273-1278` calls `openRelatedModal({competencyid, competencyname, frameworkid})` (imported at `:39`) with the active row's `data-*` |
| `MOD.RELATED-CHIPS` | Referenced competencies | detail section | `structure_detail_content.mustache:116-125` | `data-region="detail-related"` · ships `hidden` | str `central_related_referenced`. It only emits under `{{#showrelated}}`, and the whole partial only enters inside `{{#detailconfig}}` (`structure.mustache:215-217` — the section **also switches the context**, which is why `showrelated` resolves in there). **It does not exist in the modal pane**, and `populateRelated` (`structure.js:478-504`) returns silently when the region is not there (`:482-484`) |
| `MOD.RELATED-CHIPS-COUNT` | `[no label]` | counter | `structure_detail_content.mustache:121` | `data-region="detail-related-count"` | `structure.js:496-498`. Born `0` in the Mustache and painted with `items.length` |
| `MOD.RELATED-CHIP` | {name} | chip (button) | `structure_related_chips.mustache:36-43` | `data-action="open-related"` · `data-id` | `title` = str `central_related_view` ("View details"). `structure.js:1244-1248` opens the related competency in **another** modal (`openCompetencyDetailModal`, `competency_detail.js:265`) — which enters with `showrelated: false` (`:275`), so related competencies **do not nest** inside related competencies. The list is reset **before** the fetch (`:486-487`) and re-checked afterwards (`isactive()`, `:493`, `:500`) so that a quick row switch does not paint stale chips |

> **A naming trap — `.local-dimensions-related-modal` is not this modal.** The class looks like the
> one belonging to this map and **is not**: what applies it is `competency_detail.js:285`, on the
> modal **the chip** opens (`structure_related_modal.mustache`), whose `.modal-header` is hidden on
> purpose (`styles.css:6671-6673`) because the card carries its **own** close button
> (`data-action="close-related-modal"`, `structure_related_modal.mustache:37`). The "Related
> competencies" modal **receives no class at all** on its root — `related_competencies.js:239` is a
> bare `ModalSaveCancel.create`.
>
> The consequence is easy to read backwards: the hub's close-chip restyle is
> `.modal:not(.local-dimensions-related-modal):has(.modal-body [class*='local-dimensions-'])`
> (`styles.css:5074`), and the comment above it (`:5067-5068`) says that "a referenced-competency
> modal" is **excluded**. It is talking about the **chip's** modal, not this one. This modal
> **matches** both sides of the selector (it does not carry the class; its body has
> `.local-dimensions-central-related`) and **gets** the `1.75rem` blue chip as normal
> (`styles.css:5074-5103`, background `#e7f0f9`, glyph `#0f4d85`).

## Modal shell

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `MOD.RELATED-TITLE` | Related competencies — {name} | title | `related_competencies.js:224` (str), `:239-244` (`ModalSaveCancel.create`) | str `central_related_title`, `$a` = name | Cancel/Save footer filled in by core; `removeOnClose: true` goes in `configure()` itself (`:242`), and save is born disabled (`setButtonDisabled('save', true)`, `:246`) |
| `MOD.RELATED-ROOT` | `[no label]` | region/root | `related_competencies.mustache:31` | `data-region="related-competencies"` · `.local-dimensions-central-related` | the class is the hook for the 40vh cap (`styles.css:7284`). The delegated remove listener lands here (`js:274-278`) |
| `MOD.RELATED-ADDLABEL` | Add related competency | label | `related_competencies.mustache:32` | str `central_related_add` | it is a `<div class="small fw-medium">`, **not** a `<label>` — there is no `for`, because the target is a tree, not a field |
| `MOD.RELATED-SAMEFW` | Only competencies from the same structure can be related. | note | `related_competencies.mustache:33` | str `central_related_sameframework` | it is core's constraint in prose: `competency::share_same_framework`, required by core's `related_competency` validator. That is why the partial enters **without** a structure selector |
| `MOD.RELATED-TOAST` | `[no label]` | feedback | `related_competencies.js:266-269` | `addToastRegion(modal.getBody()[0])` on `ModalEvents.shown` | strs `central_related_added` / `central_related_removed`. See the toast section below |

## The tree (a partial shared with `MOD.BROWSER`)

`related_competencies.mustache:34` includes the whole partial; what drives it is
`competency_tree_browser.js`, with the `state` assembled by the modal.

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `MOD.RELATED-FILTER` | Filter competencies | search field | `competency_tree_browser.mustache:31-35` | `data-region="filter"` · `aria-label` = the same str | str `central_browseframeworks_filter`. **250 ms** debounce (`browser.js:376-388`, the `250` at `:387`), minimum of **2** characters (`SEARCH_MIN`, `:47`, tested at `:382`); below that it returns to tree mode (`:384-385`) |
| `MOD.RELATED-PATHS` | Show paths | switch | `competency_tree_browser.mustache:36-41` | `data-region="path-toggle"` · id with `{{uniqid}}` | str `central_browseframeworks_showpaths`. In **search** mode it is forced `checked` **and** `disabled` (`browser.js:328-329`), because `pathsVisible` is already always true there (`:72`). It governs **the tree only** — the relation rows always show the path |
| `MOD.RELATED-TREE` | `[no label]` | JS container | `competency_tree_browser.mustache:42-44` | `data-region="competency-list"` (`:43`) inside the wrapper `.local-dimensions-cb-scroll` (`:42`) | `styles.css:7284-7287` gives `max-height:40vh` + `overflow-y:auto` **here only** (`MOD.BROWSER` leaves it loose): that is what keeps the relation rows below it reachable. The infinite-scroll sentinel is inserted **inside** the box on purpose (`browser.js:490-491`, with the reason in the comment at `:487-489`) |
| `MOD.RELATED-ROW` | {name} | row (checkbox) | `competency_tree_browser.js:82-156` (`makeNode`; the checkbox at `:111-123`) | `input.form-check-input` + name + path | **no `for`**: the whole row is the click target (`:125-126`, `onListClick` `:416-442`), with Shift range selection (`handleShiftSelect`, `:354-364`). The selection is **persistent** (`state.checked`) and survives a re-render (`:120-122`). Indents `20px` per level (`INDENT_STEP`, `:48`, applied at `:94`) |
| `MOD.RELATED-ROW-LOCK` | {name} (This competency) / (Already related) | locked row | `competency_tree_browser.js:117-119`, `:130` | `checked` + `disabled` · suffix on the name | the set is `state.excluded`: the competency itself plus the ones already related, rebuilt on every `loadRelations` (`js:110-114`). The suffix comes from `state.excludedsuffix` (`js:258`) → strs `central_related_self` / `central_related_alreadyrelated`. `getCheckedIds` filters out the excluded ones (`browser.js:451-453`) |
| `MOD.RELATED-MORE` | Load more | button | `competency_tree_browser.js:180-192` (`appendLoadMore`; the label at `:186`) | str `central_browseframeworks_loadmore` | pages of **25** (`PAGE_SIZE`, `:46`) |
| `MOD.RELATED-TREE-EMPTY` | No competencies in this structure. | empty state | `related_competencies.js:233` (str), `:260` (state) | str `central_browseframeworks_empty` | passed in `state.emptylabel`; it is the **tree's** empty state, not the relations' |

## Action and current relations

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `MOD.RELATED-FOOT` | Add selected · Close | modal footer | `related_competencies.js:239-244` (`buttons: {save, cancel}`), `:290-296` (`ModalEvents.save`) | save = str **`central_browseframeworks_add`** (reused from `MOD.BROWSER`); cancel = core's str `closebuttontitle` ("Close", `js:226`) | core reveals the `.modal-footer` (always rendered, hidden when empty) because `ModalSaveCancel` fills it. `updateAddButton` (`js:125-127`) enables save through `state.modal.setButtonDisabled('save', …)` while `getCheckedIds(state).length !== 0`; the listeners are registered **after** the browser's, on purpose (`js:284-285`). The save's `preventDefault()` is **unconditional** (`:294`) — see the footer section |
| `MOD.RELATED-ROWS` | `[no label]` | JS container | `related_competencies.mustache:36` | `data-region="related-list"` | rows through `makeRow` (`js:55-96`): name + mono `idnumber` + ancestor path. The path comes from the WS (`list_related_competencies` → `helper::competency_breadcrumbs`, `classes/external/list_related_competencies.php:78`) and is rendered **always** (`js:72-77`) — `MOD.RELATED-PATHS` does not reach it |
| `MOD.RELATED-ROW-REMOVE` | Remove related competency | button (per row) | `related_competencies.js:79-94` | `data-action="remove-related"` · `data-relatedid` on the row (`:58`) | `fa fa-trash` icon + `.visually-hidden` carrying the label. `removeRelated` (`js:154-179`): `deleteCancelPromise` confirm (`:162`, strs `central_related_remove` / `central_related_remove_confirm`) → core WS (`:166-169`) → removes the row (`:170`) → **re-renders the tree** so the competency becomes pickable again (`:174`) → hands focus back (`:177`), because the confirm had handed it back to a button already detached from the DOM |
| `MOD.RELATED-EMPTY` | No related competencies yet. | empty state | `related_competencies.mustache:37-39` | `data-region="related-empty"` · ships `hidden` · `role="status"` | str `central_related_empty`. Toggled at `js:116` (`loadRelations`) and `:172` (`removeRelated`) |

**The add flow (`addSelected`, `js:187-211`).** It fires **N calls in parallel** (one
`core_competency_add_related_competency` per checked id, `:194-197`). The `finally` (`:199-208`)
re-syncs rows **and** tree with the server **even on error**, with the reason recorded in the code
itself: a call that fails mid-batch **does not undo** the earlier ones. The still-pending checks are
preserved so the user can retry. Afterwards each new row flashes (`flashRow`, from the `flash.js`
helper, `:209`) and a toast goes out (`:210`).

## The relation is symmetric for a mechanical reason

Core **normalises** the pair before writing: `related_competency::get_relation()` always writes the
**lower id** as `competencyid`, because the validator **requires**
`competencyid < relatedcompetencyid`. Written once only, in a canonical direction, the symmetric read
**has to** be a `UNION ALL` of both directions — and it is, in
`related_competency::get_related_competencies()`. `api::list_related_competencies` exists, but
delegates. That is why the plugin's WS reads the relations in both directions without writing
anything twice, and why removing the A→B relation makes B disappear from A's list **and** A from B's.

> **Core refs with no line number, on purpose.** This map cites Moodle core files and symbols
> (`related_competency`, `competency`, `api`, `core/modal`, `core/modal_save_cancel`, Boost)
> **without** `:line`: the plugin supports 4.5 through 5.2 and the numbers diverge between branches.
> The symbol is what one looks for; the number would be false in at least one supported branch.

## The toast — why the region lives inside the modal

`related_competencies.js:269` calls `addToastRegion(modal.getBody()[0])` on `ModalEvents.shown`.
It is one of the plugin's **4** points with this pattern (`participants_manager.js:236`,
`competency_links.js:914`, `frameworks.js:316`, and this one) — counted with
`grep -rn 'addToastRegion(' amd/src/`, **with the parenthesis**: without it the grep returns **11**,
because it adds the hosts' 4 `import` lines plus three from the `toast.js` wrapper (the docblock
`:21`, the core import `:29` and the re-export `:41`).

The reason is `z-index` arithmetic, and both numbers are worth checking (the same ones the plugin's
`CLAUDE.md` fixes as house rule):

- the page's `.toast-wrapper`: **`z-index: 1051`** (`theme/boost/scss/moodle/core.scss`).
- `$zindex-modal`: **`1055`** (`theme/boost/scss/bootstrap/_variables.scss`).

A toast fired from inside the modal, with no region of its own, would land in the page wrapper and
sit **behind** the dialog. An awkward, verified detail: core's comment, on the line **above** the
`z-index: 1051`, says it sits *"above any modals"* — and it has **aged**. In Bootstrap 4
`$zindex-modal` was 1050 and the arithmetic worked; the jump to BS5 raised the modal to 1055 and left
the wrapper underneath, without the comment changing. The house pattern exists because of that
mismatch.

Core removes the region itself on close (`removeToastRegion` in `core/modal`), so there is no leak
and global `z-index` is **not** touched.

## The primary action lives in the footer core already builds

**The footer was not missing: it was hidden.** The whole chain, verified symbol by symbol in core:

1. `lib/templates/modal.mustache` **always** renders the `div.modal-footer` with
   `data-region="footer"`, and an empty `{{$footer}}` block by default.
2. `Modal.show()` (`lib/amd/src/modal.js`) asks `hasFooterContent()` — which is literally
   `this.getFooter().children().length ? true : false`.
3. With zero children it falls into the `else` and calls `hideFooter()`, which applies the `.hidden`
   class.
4. `.hidden { display: none; }` (`theme/boost/scss/moodle/core.scss`) — the footer **collapses**.

**Therefore: giving the footer a child makes core reveal it by itself** (`showFooter()`). And that is
exactly what `ModalSaveCancel` is — the **same** `core/modal` with the `{{$footer}}` block filled
with Cancel + Save (`lib/templates/modal_save_cancel.mustache`).

**It is less code, not more.** Core's `configure()` accepts `buttons` and `removeOnClose` in the
same object (`buttons` is applied through `setButtonText`), so creation is a single call:

```js
const modal = await ModalSaveCancel.create({
    title, body: html, removeOnClose: true,
    buttons: {save: addlabel, cancel: closelabel},
});
```

(`related_competencies.js:239-244`). **No new string for the button:**
`central_browseframeworks_add` ("Add selected") is the same one `MOD.BROWSER` uses
(`js:225`), and "Close" is core's `closebuttontitle` (`js:226`).

**Disabled-until-checked is one line.** `setButtonDisabled(action, disabled)` is public
on `core/modal` and does the `getFooter()` + `getActionSelector()` internally.
`updateAddButton` (`js:125-127`) is
`state.modal.setButtonDisabled('save', getCheckedIds(state).length === 0)` — with no `state.addbtnEl`
and no `querySelector` in the body. The `state.modal` kept on the `state` (`:252`) is the only hook.

> **Project rule: the `preventDefault` here is unconditional, and is not copied from the neighbour.**
> `ModalSaveCancel.registerEventListeners()` calls `registerCloseOnSave()`, and core's handler
> **closes the dialog** after `ModalEvents.save` — unless `preventDefault()` is called
> **synchronously** (core fires `ModalEvents.save` and only closes if
> `!saveEvent.isDefaultPrevented()`). This modal **must not close** on "Add selected":
> the toast, the new row's `flash` (`js:209`) and the empty state all happen **in place**, and the
> user goes back to the tree. So this modal's `ModalEvents.save` (`js:290-296`) calls
> `event.preventDefault()` **unconditionally** (`:294`) as its first statement, and only then fires
> the asynchronous `addSelected`.
>
> **The contrast with the neighbours.** `competency_browser.js` (`MOD.BROWSER`) and mtube's
> `competency_picker` make the `preventDefault` **conditional** — only to **block** an empty
> selection; on a real addition they **close**, and that is right for them, being one-shot pickers.
> The related-competencies one is the third case, and the only one: it **manages**, writes on every
> click and **stays**. In it the `preventDefault` is **unconditional** and it is the mechanism, not
> the backstop. The `ModalSaveCancel` + `setButtonDisabled` call is reusable; **the save wiring is
> not**. A future session that "simplifies" this handler into the conditional shape breaks the modal.

**What the footer does not solve:** the `40vh` cap (`styles.css:7284-7287`) exists because of the
**relation rows** below the tree, not because of the button — it stays. And the sentinel is still
inside the box (`browser.js:490-491`), so pagination remains tied to that box's scrolling.
