# Field map — `MOD.DELPLANS` · Delete a template that has plans (as-is)

Delete confirmation for a learning plan template, opened by `PLN-DELETE` — the **Delete template**
button in the Plans tab's **sticky footer**. There are **two dialogues, not one**: `plans.js` asks
core whether the template has related data and, **only if it does**, renders the plugin's own modal —
which names the template, shows the **real count** of plans and spells out the **consequence of each
choice** (unlink, the default; or delete the plans). With no plans, the flow falls through to a core
`deleteCancelPromise`. The checked radio becomes the `deleteplans` argument of
`core_competency_delete_template`.

- **Mustache:** [`delete_template_modal.mustache`](../../../templates/delete_template_modal.mustache)
  (73) — **at the root of `templates/`, not in `templates/central/`**; it is the **only** modal in this
  kit outside `central/`. Trigger in [`plans.mustache`](../../../templates/central/plans.mustache) (`:481-485`)
- **AMD:** [`plans.js`](../../../amd/src/central/plans.js) — `deleteTemplate` at `:223-261`, dispatch
  at `:735-737`. Imports `core/modal_delete_cancel` (`:28`); uses `errors.js` (`notifyError`) and
  `tabs.js` (`reloadPane`)
- **PHP:** [`plans.php`](../../../classes/output/dynamictabs/plans.php) `:319-321` exports
  `selectedtemplateplancount` via `helper::count_plans_by_template`
- **WS:** core `core_competency_template_has_related_data` (`js:225-228`, **the gate**) and core
  `core_competency_delete_template` (`js:230-233`, the write). **No plugin WS** — both are core's,
  which is why this modal has no entry in `db/services.php`
- **CSS:** [`styles.css`](../../../styles.css) `:6919-6991` — its own block, **literals**, **no dark
  variant** (the comment at `:6923-6927` explains: the body is born outside `.local-dimensions-manage`,
  so the hub's custom properties are not in scope)
- **Behat:** [`manage_plans.feature`](../../../tests/behat/manage_plans.feature) `:33-42` — covers
  **only the no-plans path**; see the coverage note
- **Screen in the DS:** [`screens/mod-delplans.html`](../screens/mod-delplans.html) — with the gate
  storyboarded and the two real options, clickable and measured

**Abbreviations used in the tables:** `js:` = `amd/src/central/plans.js` · `mustache:` =
`templates/delete_template_modal.mustache` · `plans.mustache:` =
`templates/central/plans.mustache`. Paths starting with `lib/` are **core's**; for those this map
cites the **symbol**, not the line — core's numbers vary across the 4.5–5.2 range the plugin supports.

> **Resync 2026-07-15 — the previous map inventoried a file that no longer exists.** Measured, not
> estimated:
>
> - **3 refs; 3 broken (3/3) — but for a different reason than the rest of the series.** A
>   `grep -oE '[a-z_/.]+\.(php|js|mustache|css):[0-9]+(-[0-9]+)?'` over the old map returns
>   **exactly 3**, all of them in `delete_template_plans.mustache`. They **did not age through drift:
>   they were right when they were written**. A
>   `git show 820a449^:templates/central/delete_template_plans.mustache` confirms that `:29` really was
>   the `deletetemplatewithplans` `<p>`, `:31` the `value="0" checked` radio and `:37` the `value="1"`
>   radio. What happened is that the **whole file was deleted** — `820a449` removed it (42
>   lines, `-`) while creating the replacement. The refs are **orphaned**, not wrong; it is the first
>   time in this series that the cause is deletion rather than drift.
> - **The explicit-consequence modal is what shipped.** `820a449` ("feat: explicit-consequence
>   delete modal, shared by both flows", 2026-07-01) brought the template name, the real count and a
>   consequence note per option — it is the body inventoried in the tables below.
> - **The file's path changed folder, not just name.** The old map pointed at
>   `templates/central/delete_template_plans.mustache`; what shipped is
>   `templates/delete_template_modal.mustache` — **at the root**. A `find templates -iname '*delete*'`
>   returns **one** line, the root one. The reason is in the commit: the modal was born **shared**
>   between the Central hub and the old `manage_templates.js` screen, and so it stayed outside
>   `central/`. **The reason no longer holds**: `f804e14` ("refactor(admin): remove the legacy
>   manage/edit admin surface", 2026-07-07) deleted `amd/src/manage_templates.js`, and today a
>   `grep -rn 'delete_template_modal' --include='*.js' --include='*.php' --include='*.mustache' .`
>   (outside `build/`) returns **a single** renderer: `plans.js:236`. **The template sits at the root
>   because of a sharing arrangement that ended** — moving it to `central/` is a one-line change in the
>   `renderForPromise`, and it is recorded here as debt, outside the scope of this task.
> - **The gate was never mapped, and it decides which of the two dialogues opens.** The old map
>   described the modal as if it were the only outcome of "Delete template". It is not: `js:225-228`
>   calls `core_competency_template_has_related_data` **before** any render, and the `if (hasplans)`
>   (`:235`) chooses. **With no plans there is no plugin modal** — it falls through to core's
>   `deleteCancelPromise` (`:254-260`). Both paths are now drawn in the screen (a directed storyboard)
>   and in the tables below.
> - **Zero JS refs, as in every previous map in the series** — and here that erased the whole flow.
>   Nothing in `.js` was cited: not the gate, not the title, not the radio read, not the fallback, not
>   the dispatch. **The old map covered 3 controls; this one covers 12** (plus `PLN-DELETE`, borrowed
>   from the tab's map) — counted with
>   `grep -oE '^\| \`MOD\.DELPLANS-[A-Z-]+\`' | sort -u | wc -l`.
> - **The IDs gained the `MOD.` prefix.** The old map used bare `DELPLANS-MSG`/`-UNLINK`/`-DELETE`;
>   this kit's `README.md` defines the prefix as `MOD.{…,DELPLANS}` and the three fresh neighbours
>   (`MOD.BROWSER-*`, `MOD.LINKS-*`, `MOD.RELATED-*`) already use it. Normalised here. `UNLINK` and
>   `DELETE` keep their suffix (same control); **`DELPLANS-MSG` was retired** — the generic message it
>   named (`deletetemplatewithplans`, from `tool_lp`) is no longer rendered, and a
>   `grep -rn 'deletetemplatewithplans'` over the plugin (outside `build/`) returns **nothing**. Two
>   elements with content of their own took its place: `MOD.DELPLANS-NAME` and `MOD.DELPLANS-INPLANS`.
> - **A Behat note glued to the wrong dialogue.** The old map said "the dialogue is matched by its
>   **title** (`deleteCancelPromise`)". The **with plans** path is not a `deleteCancelPromise` — it is
>   a `ModalDeleteCancel.create` (`js:240`). The observation only holds for the **fallback**, and that
>   is exactly what Behat exercises. See the coverage note.
> - **The trigger gets no new ID here — it already has one.** The old map said "Triggered by
>   `PLN-DELETE`", and that **checks out**: `pln-plans.md` maps `PLN-DELETE` at
>   `plans.mustache:481-485`, exactly the ref derived here independently, and it already publishes the
>   cross-reference `MOD.DELPLANS` ← `PLN-DELETE` **when there are plans**. This map **reuses**
>   `PLN-DELETE` instead of minting a `MOD.DELPLANS-ACTION`, so as not to give one button two IDs. The
>   divergence this paragraph used to record — `mod-browser.md` having minted a `MOD.BROWSER-ACTION`
>   parallel to `PLN-BROWSE` — is **resolved**: that map now merely references, and a
>   `grep -rn 'MOD.BROWSER-ACTION' docs/design-kit/` returns no ID any more, only prose.

## Trigger (on the Plans tab, outside the modal)

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `PLN-DELETE` | Delete template | button (trigger) | `plans.mustache:481-485` — ID from [`pln-plans.md`](pln-plans.md) | `data-action="delete-template"` · `data-id` · `data-name` · `data-plancount` · `fa fa-trash` | str `managetemplates_delete` = "Delete template" — **the same str as the modal title** (`js:241`), so the button and the dialogue it opens carry an identical label. It lives in the `data-region="plans-footer-actions"` holder (`plans.mustache:462`), which is born `hidden` and is moved to the page's `#sticky-footer` by `plans.js`; it only renders under `{{#canmanage}}` (`:457`). **The footer is this modal's only door.** Dispatch at `js:735-737`, with `target.dataset.name \|\| ''` and `target.dataset.plancount \|\| 0` |

**The count is already there at click time.** `data-plancount` (`plans.mustache:482`) is
`selectedtemplateplancount`, exported server-side by `plans.php:319-321`
(`helper::count_plans_by_template([$templateid])[$templateid] ?? 0` — the same source as the
`PLN-COUNT-PLANS` pill in the tab's map). `js:238` does `Number(plancount) || 0` and passes it to the
template. Two consequences worth recording:

- **The gate's WS does not bring the number.** `has_related_data` returns a boolean; what knows "12"
  is the server, from the previous render. The gate decides **the path**, never the text.
- **The number can be stale.** If someone created plans since the last `reloadPane`, the modal shows
  the render's count, not the click's — while the gate, that one, is queried live. It is possible
  (though a narrow window) for the gate to say `true` and the count to say `0`: the modal would open
  saying "This template is used in **0 learner plans**".

## The gate — which of the two dialogues opens

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `MOD.DELPLANS-GATE` | `[no label]` | rule (fork) | `js:225-228` (WS), `js:235` (`if`) | `core_competency_template_has_related_data` | it runs **before** any render and is `await`ed — the click opens nothing until the WS returns, **with no spinner and no waiting state**. The hub's waiting coverage is `reloadPane`'s **busy cover** (`tabs.js:69-108`, CSS `styles.css:4028-4069`), which covers a **reloaded pane**; this wait is **prior to the modal** and falls outside it. `true` → the plugin's modal (`:236-251`, with a `return` at `:251`); `false` → core's `deleteCancelPromise` (`:254-260`). **Note the asymmetry**: the gate asks about *related data*, not about *plans* — the WS name is core's and covers more than plans, but the modal it opens talks **only** about plans |

## Modal shell (the **with plans** path)

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `MOD.DELPLANS-TITLE` | Delete template | title | `js:241` (str), `:240-245` (`ModalDeleteCancel.create`) | str `managetemplates_delete` | it is `ModalDeleteCancel` (`import` at `:28`), **not** `ModalSaveCancel` like `MOD.BROWSER` and `MOD.RELATED` — the footer already comes with Cancel + a red Delete, which is why this modal has no `setSaveButtonText` call at all. `removeOnClose: true` **in the config** (`:244`), not via a setter, alongside `show: true` (`:243`) — the modal shows itself. The `title:` receives the `getString` **Promise** **without `await`** (`:241`) and that is legal: core's `setTitle` delegates to `asyncSet`, which resolves promises — the `body` next to it is an already-resolved string (`:236-239`) |
| `MOD.DELPLANS-ROOT` | `[no label]` | region/root | `mustache:40` | `.local-dimensions-delete-template-modal` | the body's wrapper, **with no rule of its own**: `styles.css` styles its children (`:6928` onwards), never the root's class. But it is not a dead hook like `MOD.BROWSER-ROOT`'s — see `MOD.DELPLANS-X` |
| `MOD.DELPLANS-CONFIRM` | Delete | destructive button (footer) | core (`lib/templates/modal_delete_cancel.mustache`) | `data-action="delete"` · `.btn-danger` · core str `delete` | it comes free with `ModalDeleteCancel`; the plugin does not touch it. **It is red for both choices** — including when the checked one is "Unlink", which destroys nothing. Handler at `js:246-250`; see "Confirming" |
| `MOD.DELPLANS-CANCEL` | Cancel | button (footer) | core (`lib/templates/modal_delete_cancel.mustache`) | `data-action="cancel"` · core str `cancel` | `core/modal_delete_cancel`'s `registerCloseOnCancel()` closes without calling anything |
| `MOD.DELPLANS-X` | Close | close chip | core (`lib/templates/modal.mustache`) | — | it gets the hub's `1.75rem` blue restyle (`styles.css:5074-5086`, glyph at `:5088-5096`) through the same selector as its neighbours, which requires a `[class*='local-dimensions-']` in the body. Here **what matches is `MOD.DELPLANS-ROOT`** — and, unlike `MOD.BROWSER`, there is no second candidate: every class in the body is a child of it and starts with the same prefix, but the selector looks at the **root**. Deleting the root's class (because it looks unused, since no rule cites it) **would take the restyle off the X** |

## Body — name, count and the two options

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `MOD.DELPLANS-NAME` | Template: {name} | text | `mustache:41-44` — str at `:42`, value at `:43` | str `managetemplates_delete_template` = "Template:" · `{{name}}` | the name comes from the trigger's `data-name` (`js:237`), **escaped** by Mustache (`{{name}}`, not `{{{name}}}`). The `<strong>` is `.local-dimensions-delete-template-shortname` (`styles.css:6933-6937`: `#1c2433`, 1.05rem/600). It is the `shortname`, not the `name` — the template's context documents "Template short name" (`mustache:28`) |
| `MOD.DELPLANS-INPLANS` | This template is used in **N learner plans**. | text | `mustache:45-47` | str `managetemplates_delete_inplans` with `{{plancount}}` | **the `<strong>` is inside the string itself** (`lang/en:493` = `'This template is used in <strong>{$a} learner plans</strong>.'`), not in the template — the `{{#str}}` hands it over as HTML. **The plural is not handled**: with one plan it reads "1 learner plans" (true for both notes as well, `:492` and `:497`) |
| `MOD.DELPLANS-LEGEND` | What should be done with the learning plans? | legend (sr-only) | `mustache:49` | str `managetemplates_delete_options` · `.sr-only.visually-hidden` | **invisible**; it exists only so a screen reader can name the `<fieldset>` (`:48`). It carries **both** classes — `sr-only` (BS4, Moodle 4.5) and `visually-hidden` (BS5) — because the *classes* are bridged on 4.5 (unlike the `data-` attributes, which are not). It is the only text in the modal a sighted user does not read, and the only place where the old map's question ("What should be done with them?") survived |
| `MOD.DELPLANS-UNLINK` | Unlink | radio (**default**) | `mustache:50-60` — radio `:51`, title `:54`, note `:57` | `value="unlink"` · `checked` | strs `managetemplates_delete_unlink` + `managetemplates_delete_unlink_note` ("The {$a} plans will continue to exist, without a template."). Born checked: **the default state is the non-destructive one**, and the `!!checked &&` at `js:249` guarantees that even with nothing checked the result would be `false` (unlink). The `<label>` wraps the input — **no `for`**, so the whole row is a click target |
| `MOD.DELPLANS-DELETE` | Delete the plans | radio (destructive) | `mustache:61-71` — radio `:62`, title `:64-66`, note `:68` | `value="delete"` · `.text-danger` on the title | strs `managetemplates_delete_deleteplans` + `managetemplates_delete_deleteplans_note` ("Removes the {$a} learner plans — irreversible."). The `.text-danger` (`:64`) is **the only danger signal by colour**, and it is **text** colour: the checked box goes blue, like the safe one. See "Contrast" |

## Confirming — it closes before writing

The handler (`js:246-250`) listens on `ModalEvents.delete` — which is `'modal-delete-cancel:delete'`
(`core/modal_events`, where the key is quoted in the object because, as core's comment puts it,
*"Delete is a reserved word"*). It reads the checked radio with
`querySelector('input[name="local-dimensions-delete-template-choice"]:checked')` and calls
`remove(!!checked && checked.value === 'delete')` (`:249`). **The entire contract between the Mustache
and the AMD is the `name`/`value` pair**, spelled out in full on both sides (`mustache:51`, `:62`;
`js:248-249`) — there is no `data-` attribute, class or id in between.

**The modal closes before the write returns.** Core's `registerCloseOnDelete` (wired by
`core/modal_delete_cancel`) fires the event and, **if nobody called `preventDefault()`**, destroys the
dialogue (`removeOnClose: true` → `destroy()`). `deleteTemplate` does **not** call `preventDefault`: a
`grep -n 'preventDefault' amd/src/central/plans.js` returns two lines (`:468`, `:482`), and both belong
to the tree's drag-and-drop, not to this modal. The consequence, read straight off the chain:
`core_competency_delete_template` (`js:230-233`) resolves with the dialogue **already off screen** —
an error becomes a **page toast** (`notifyError`), not an error inside the dialogue; success shows up
as the reloaded pane (`reloadPane`, `:233`).

It is the **same mechanic** as `MOD.BROWSER`, and here, as there, it is **right**: a one-shot
confirmation, with no state to preserve. The difference is **where the guarantee against an empty
click comes from**: in `MOD.BROWSER` it is the footer button being born disabled and following the
selection (`competency_browser.js:110`, `:48-50`), with `preventDefault` as a backstop; **here there is
nothing to guarantee**, because a radio is always checked and there is no "empty choice" — the
`!!checked` is a seatbelt for a case the `checked` at `mustache:51` already prevents.

## The **no plans** path (core's fallback)

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `MOD.DELPLANS-FALLBACK` | Delete · "Delete learning plan template '{name}'?" | core dialogue | `js:254-260` | `Notification.deleteCancelPromise` | **title** = core str `delete` ("Delete"); **body** = the `deletetemplate` str from **`tool_lp`** (`admin/tool/lp/lang/en/tool_lp.php` = `Delete learning plan template '{$a}'?`) with the name. The argument order is easy to misread: `core/notification`'s signature is `deleteCancelPromise(title, question, deleteLabel, …)`, and `js:256` passes `getString('delete')` as the **title** and the `tool_lp` str as the **question** — `deleteLabel` is left `undefined`, so the button uses core's default label. Cancelling **rejects** the promise → `catch` → `return` (`:257-259`), calling nothing. Confirming calls `remove(false)` (`:260`): **always unlink** — a harmless argument, since there is no plan to unlink |

**The only `tool_lp` dependency left.** The new modal is 100% plugin strings; the fallback still asks
for a `tool_lp` string (`js:254`) — a `grep -rn "deletetemplate'" amd/src/` returns **that single
line**. It is what is left of the pre-`820a449` era, when both sides came from there
(`deletetemplatewithplans`, `unlinkplanstemplate`, `deleteplans`, all three unused in the plugin today).

**Coverage note — Behat tests the path the old map did not even mention, and only that one.**
`manage_plans.feature:33-42` is the *"Delete a template that has no plans"* scenario: it creates a
`Disposable` template **with no plans**, clicks "Delete template" (`:40`) and then
`I click on "Delete" "button" in the "Delete" "dialogue"` (`:41`). That `"Delete"` as the
**dialogue**'s name matches the **title** of the `deleteCancelPromise` — the core `delete` str —,
which confirms the house Behat rule: the dialogue is matched **by its title**, not by the word
"Confirmation". In other words, the old map's observation was **right**, but glued to the wrong
dialogue.

**The with-plans path has no test at all**: neither Behat (the existing scenario deliberately picks a
template with no plans, which is what makes the gate divert) nor PHPUnit (what `js:249` decides is
client-side, and the `core_competency_delete_template` it calls is core's). The radios, the `value`
and the conversion to boolean are, today, verified by reading alone.

## Contrast — measured on the shipped literals

The `styles.css:6919-6991` block uses **literals**, with no dark variant, by a decision recorded in
its own comment (`:6923-6927`): the body is rendered at `<body>` level, outside
`.local-dimensions-manage`, so the hub's custom properties are not in scope. Measured in the DOM
(WCAG 2.x formula; animations cancelled before reading, otherwise the reading returns the previous
theme):

| Pair | Where | Ratio | Verdict |
| --- | --- | --- | --- |
| `#1c2433` on white | template name (`:6936`) | **15.56:1** | passes |
| `#3a4658` on white | "is used in N plans" (`:6942`) | **9.56:1** | passes |
| `#6c7787` on **white** | note of the **unchecked** option (`:6990`) | **4.54:1** | passes by 0.04 |
| `#6c7787` on **`#e6f0fb`** | note of the **checked** option (`:6990` over `:6967`) | **3.94:1** | **fails** 4.5:1 |
| `#cdc3b0` on white | border of the unchecked option (`:6960`) | **1.75:1** | fails 3:1 (non-text) |
| `#cee0f3` on white | border of the **checked** option (`:6966`) | **1.35:1** | fails 3:1 — **lower** than the unchecked one |

Two findings that only show up when you measure the **checked state**, not the resting one:

1. **The note fails in exactly the default state.** `#6c7787` passes on white (4.54:1) and fails on
   the `#e6f0fb` that the checked state itself paints (3.94:1). Since `MOD.DELPLANS-UNLINK` is born
   `checked` (`mustache:51`), **that is the state the modal opens in** — it is not an edge case: the
   label passes at rest and fails in the real state.
2. **Checking makes the box less visible.** The checked border (`#cee0f3`, 1.35:1) is **lighter** than
   the unchecked one (`#cdc3b0`, 1.75:1). The visual reinforcement runs backwards. **It is not a
   control failure** — what actually carries the state is the native `<input type="radio">`, which the
   CSS only touches on `margin-top` (`:6975-6977`) —, but the tint that should reinforce weakens.

The weak borders are the kit's same known case (`--border-strong`/`--border-stronger` fail 3:1 on
every recent surface) and are **not** fixed here.

**One rule serves both options.** `styles.css:6965-6968` is a **single** one —
`.local-dimensions-delete-template-option:has(input:checked)` — and it applies to `unlink` **and** to
`delete`: `background: #e6f0fb` and `border-color: #cee0f3` in both. The **irreversible** choice is
confirmed in the **same blue** as the safe one, in a modal whose `MOD.DELPLANS-CONFIRM` is already red
for both. The body states the consequence **in prose**; the **colour** does not follow it. This is
as-is, not a freshly discovered defect — it is here so it is not rediscovered.

> **Pending — a danger pair for the destructive checked state.** A second selector
> `.local-dimensions-delete-template-option:has(input[value="delete"]:checked)`, after the current
> rule, would make the colour follow the consequence with no JS and no new contract (the
> `value="delete"` is already at `mustache:62` and is already what `js:249` reads). **Not built:**
> `grep -c 'value="delete"' styles.css` returns **0** (and the 8 `delete-template-option` lines in the
> file carry no per-`value` variant). Measurement kept for whoever builds it: `#8a1e12` in light
> (6.77:1 over the red fill) and `#e89b93` in dark (6.55:1) — the theme's `--text-danger` `#ca3120`
> over `--bg-danger` `#f4d6d2` pair fails (3.88:1 on the title, 3.54:1 on the note), and what carries
> the box is the **border**, as with today's blue.

## Do not re-litigate — what the kit used to get wrong

| What the kit said | What shipped |
| --- | --- |
| `templates/central/delete_template_plans.mustache` | `templates/delete_template_modal.mustache` — **at the root**, the kit's only modal outside `central/` |
| Body = the `tool_lp` `deletetemplatewithplans` str + 2 bare radios `value="0"`/`"1"` | **its own** strings (`managetemplates_delete_*`), `value="unlink"`/`"delete"`, a consequence note per option; `deletetemplatewithplans` **unused** in the plugin |
| One dialogue only | **two**: the `has_related_data` gate (`js:225-228`) → the plugin's modal **or** core's `deleteCancelPromise` (`js:254-260`) |
| "The value (0/1) becomes the `deleteplans` argument" | the value is `unlink`/`delete`; the **JS** converts it to a boolean (`js:249`) |
| Behat note glued to the modal | it holds for the **fallback** (`manage_plans.feature:33-42`); the modal **has no coverage** |
| "Part of `modal-shell.html` (saveCancel confirmation)" | it is **`ModalDeleteCancel`** (`js:240`), not `saveCancel`; and a `grep -c 'DELPLANS\|delete_template' modal-shell.html` returns **0** — the screen was never there |
| `DELPLANS-MSG` / `-UNLINK` / `-DELETE` (3 controls, no prefix) | `MOD.DELPLANS-*` (12 controls) + `PLN-DELETE` reused; `MSG` retired, became `-NAME` + `-INPLANS` |
