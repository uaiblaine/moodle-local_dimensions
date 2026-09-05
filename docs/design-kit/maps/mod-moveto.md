# Field map — `MOD.MOVETO` · Move to position (as-is)

A reordering modal: a numbered `<select>` with **one option per position**, each annotated with the
competency currently occupying that slot. Saving moves. It is the **keyboard alternative to
dragging** — the grip is pointer-only — and the practical path when the list is too long to drag.

**One template, two callers, two different persistence strategies.** The Plans tab reorders
competencies inside a template with **one** core reorder call; the Competencies tab reorders sibling
nodes of the tree by **stacking |delta| one-step moves**. The body is identical; what happens on save
is not. That contrast is why this map exists.

- **Mustache:** [`move_competency_modal.mustache`](../../../templates/central/move_competency_modal.mustache)
  (45) — the **body** only; the `ModalSaveCancel.create` is in JS on both sides
- **AMD (two callers):**
  - [`plans.js`](../../../amd/src/central/plans.js) — `moveCompetencyTo` at `:537-595`; dispatch at
    `:742`. Helpers: `refreshMoveState` (`:117-129`), `reloadKeepingScroll` (`:93-109`), and the
    `flashRow` of the shared module `local_dimensions/central/flash` (imported at `:26`)
  - [`structure.js`](../../../amd/src/central/structure.js) — `openNodeMoveModal` at `:973-1008`;
    `persistNodeMove` at `:942-962`; `nodeSiblings` at `:929`. Two doors: footer
    (`:1279-1283`) and grip (`:1374-1382`)
  - Both import `core/modal_save_cancel` and `core/modal_events`
- **WS:** **none from the plugin** — core only, and **different in each tab**:
  - Plans: `core_competency_reorder_template_competency` (`plans.js:571-577`) — **one** call
  - Structure: `core_competency_move_up_competency` / `core_competency_move_down_competency`
    (`structure.js:948-952`) — **|delta| calls** in a `Promise.all`
- **CSS:** **none.** A `grep -n 'plans-move' styles.css` returns nothing — the class at `:36` is only a
  semantic hook. The body is pure Bootstrap (`form-select`, `d-block small text-muted mb-1`)
- **Behat:** none. `CLAUDE.md` advises against Behat for drag-and-drop; the **modal**, which is the
  keyboard door, has no coverage either — see the coverage note
- **Screen in the DS:** none. It is a `<label>` + a `<select>`; the design decision lives in the
  **rules**, not in the drawing

**Abbreviations used in the tables:** `mustache:` = `templates/central/move_competency_modal.mustache`
· `plans.js:` = `amd/src/central/plans.js` · `structure.js:` = `amd/src/central/structure.js`.
Paths starting with `lib/` are **core's** (relative to `public/`) and are cited **without a line
number**: core's checkout does not live in this repository, so none of its lines is verifiable from
here.

## Triggers (outside the modal) — **four doors, none new here**

Every door already has an ID in the tabs' maps. This map **references** them.

| ID (owner) | Tab | Origin | Mechanism | Rule |
| --- | --- | --- | --- | --- |
| `PLN-COMP-MOVETO` | Plans ([`pln-plans.md`](pln-plans.md)) | `plans.mustache:449-452` | `data-action="move-competency-to"` → `ACTION_HANDLERS` (`plans.js:745`) | a kebab item; `fa-arrows-v` icon |
| `PLN-COMP-GRIP` | Plans ([`pln-plans.md`](pln-plans.md)) | `plans.mustache:466-471` | `data-action="move-competency-to"` **and** `data-region="drag-handle"` (`:467`) | **it holds both functions**: clicking opens the modal, dragging reorders directly |
| `EST-DETAIL-MOVETO` | Structure ([`est-competencies.md`](est-competencies.md)) | `structure_footer_actions.mustache:61-64` | `data-action="moveto"` → `handleDetailAction` (`structure.js:1279-1283`) | a **sticky-footer** button; it acts on the module's active row |
| `EST-NODE-DRAG` | Structure ([`est-competencies.md`](est-competencies.md)) | `structure_node.mustache:111-116` | **`data-region="node-drag-handle"`** (`:112`) → its own branch (`structure.js:1374-1382`) | **no `data-action`** — the door is the region listener's `closest()`, with `preventDefault()` (`:1376`) so the click does not select the row |

**The two grips promise the same thing and deliver.** Both carry `title` **and** `aria-label` =
`central_plans_moveto` + `': '` + shortname (`plans.mustache:468-469`,
`structure_node.mustache:113-114`), and both open the modal on click — by different paths. That is
what keeps the label honest for the keyboard: dragging is pointer-only, clicking is not.

## Body (the same in both callers)

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `MOD.MOVETO-TITLE` | Move to position… | title | `plans.js:559` · `structure.js:989` | str `central_plans_moveto` | a **bare** string, no `$a`: **it does not name the target**. Opened from the grip of the "Communication" row, the title does not say "Communication" — what carries the name is the select's selected option |
| `MOD.MOVETO-MODAL` | — | `core/modal_save_cancel` | `plans.js:558-563` · `structure.js:988-993` | `show: true`, `removeOnClose: true` | **no `large`** (unlike `MOD.USAGE`/`MOD.DETAIL`): it is a single field. The Save/Cancel footer comes free; neither of the two calls `setSaveButtonText` |
| `MOD.MOVETO-ROOT` | `[no label]` | region/root | `mustache:36` | `.local-dimensions-central-plans-move` | **no CSS** |
| `MOD.MOVETO-LABEL` | New position | label | `mustache:37-39` | str `central_plans_moveto_label` · `for` | a **real** `<label>`, with `for` matching the select's `id` — the only modal in the kit whose label is a connected `label` (`MOD.RELATED-ADDLABEL` is a `div`, because there the target is a tree) |
| `MOD.MOVETO-SELECT` | — | select | `mustache:40-44` | `.form-select` · `id` = `name` = `local-dimensions-plans-move-position` (`:41`) | `form-select`, **never `custom-select`** (a `CLAUDE.md` rule). The `id` is **fixed, not a `uniqid`** — only one of these exists at a time because the modal is `removeOnClose`. Read by `querySelector` on save, on both sides (`plans.js:565`, `structure.js:995`) |
| `MOD.MOVETO-OPTION` | {n}. {name} | option | `mustache:42` | `value` = **0-based** index · `selected` | the label is **1-based** (`(index + 1) + '. ' + name`) and the `value` is **0-based** — `plans.js:552-553`, `structure.js:982-983`. The current position's option is born `selected` (`plans.js:554`, `structure.js:984`) |
| `MOD.MOVETO-SAVE` | Save changes | button (footer) | `lib/templates/modal_save_cancel.mustache` | `data-action="save"` | core str `savechanges`. **The only write point** — `ModalEvents.save` (`plans.js:564`, `structure.js:994`) |
| `MOD.MOVETO-CANCEL` | Cancel | button (footer) | `lib/templates/modal_save_cancel.mustache` | `data-action="cancel"` | core str `cancel`. Cancel, X or ESC: **nothing is written** — there is no `hidden` handler in either of them |

## The two callers, side by side

| | **Plans** (`plans.js:538-596`) | **Structure** (`structure.js:973-1008`) |
| --- | --- | --- |
| **Universe** | `[data-competency]` inside `[data-region="competency-items"]` (`:538`, `:543`) — the template's **flat** list | `nodeSiblings(node)` (`:974`) — the **same-parent** siblings in the tree (`:929`: children of the `parentElement` matching `.local-dimensions-central-node`) |
| **Gives up when** | `rows.length < 2` (`:544-546`) | `siblings.length < 2` (`:975-977`) |
| **Option label** | `textContent` of `PLN-COMP-NAME` (`:549-552`) — it reads the **screen** | `row.dataset.name` (`:980-983`) — it reads the **dataset** |
| **Write** | **1** × `core_competency_reorder_template_competency` (`:570-576`), with `competencyidfrom`/`competencyidto` and the `templateid` from `pane.dataset` (`:573`) | **\|delta\|** × `move_up`/`move_down` (`:948-952`), assembled by `Array.from({length: Math.abs(delta)})` (`:949`) and fired in a `Promise.all` (`:953`) |
| **Order** | **WS first, DOM after** (`:577-584`): the `.then` repositions | **DOM first** (`:1001-1005`), `persistNodeMove` after (`:1006`) |
| **Confirmation** | `refreshMoveState(list)` + `flashRow(row)` (`:585-586`) | `flashRow(row)` inside `persistNodeMove` (`:954`) |
| **Rollback** | `reloadKeepingScroll(pane)` (`:592`) | `reloadPane(pane)` (`:960`) |
| **No-op** | `targetindex === current` → `return` (`:566-568`) | the same (`:997-999`), **plus** the `if (!delta) return` in `persistNodeMove` (`:943-946`) |

> **The flash is the same on both sides, and it is shared.** Neither module carries a local copy of
> `row.animate`: both call the `flashRow` of `local_dimensions/central/flash` (`flash.js:34-48`,
> consolidated in `3c0bf41`), which bails early on `prefers-reduced-motion` (`:38-40`) and reads the
> duration from the `--mds-motion-flash` token (`styles.css:22`) with a 1500ms fallback (`:43`).

## Business rules (verified in the code)

### 1. Mirroring core's semantics — and why only the Plans tab needs it

The comment at `plans.js:579-580` is the key: *"Core lands the row **after** the occupant when moving
down, **before** it when moving up"*. Hence the `reference.after(row)` / `reference.before(row)` pair
(`:580-584`): the DOM imitates what the server has just done, and the list ends up right **without a
reload**.

`structure.js:1001-1005` has the **same** `after`/`before` pair — but for a different reason. There
the DOM moves **before** any call, and persistence is a stack of single steps
(`move_up`/`move_down`), which carry **no destination ambiguity**: each one swaps with its neighbour.
Structure's `after`/`before` does not mirror core semantics; it merely puts the node at the index the
user chose, and `persistNodeMove` counts how many steps that cost (`delta = to - from`, `:943`). The
docblock of `persistNodeMove` (`:932-935`) says why in one line: *"core has no reorder-to-position
service for framework competencies"*.

**The practical consequence:** a 12-position jump on the Plans tab is **1** request; on Structure it is
**12**, in parallel. The `Promise.all` (`:953`) does not guarantee arrival order, but each `move_up`/
`move_down` is relative to the current position on the server — and core serialises them. That is why
a failure drops everything into a `reloadPane` (`:956-961`): the only recoverable truth is the
server's.

### 2. `move_competency_modal` belongs to Plans in name only

The template says, in its own docblock (`:20`), *"Body of the 'move competency to position' modal **on
the Plans tab**"*. That has not been true since Structure started using it (`structure.js:987`). And
the name leaked everywhere:

- the select's `id`/`name` is **`local-dimensions-plans-move-position`** (`mustache:40`) — in the
  competency tree too;
- both strings are **`central_plans_moveto`** and **`central_plans_moveto_label`**;
- the root's class is **`.local-dimensions-central-plans-move`** (`mustache:36`).

None of this breaks — the select is read by `querySelector` inside the modal's own root
(`structure.js:995`), so the `id` only needs to be unique **within that** modal, and it is
(`removeOnClose`). Recorded as a **naming wart**, not a bug: any rename has to touch both modules, the
template and both languages at once.

### 3. The modal does not know whether the position still exists

The options are a snapshot of the DOM at the instant of opening (`plans.js:544`, `structure.js:974`).
The save revalidates **only** the index against the captured array — `!rows[targetindex]` (`:566`),
`!siblings[targetindex]` (`:997`) —, never against the server. Since both arrays are captured in the
same function and the modal is modal (it blocks the tab behind it), the window is narrow; but
**another session** reordering the same list makes the index mean something else. Core resolves by id
(`competencyidfrom`/`competencyidto`), so the outcome is a move to the **wrong** place, not an error.
No test coverage.

### 4. `refreshMoveState` exists only on the Plans side

After an in-place reorder, the "Move up"/"Move down" items in **every** row's kebab need to
recalculate their `disabled` state (the first cannot go up, the last cannot go down) —
`refreshMoveState(list)` (`plans.js:118-130`) sweeps the rows and readjusts both buttons.

Structure **does not have** that pair of buttons: `EST-DETAIL-MOVEUP` and `EST-DETAIL-MOVEDOWN` were
**retired** (see the *Retired IDs* table in [`est-competencies.md`](est-competencies.md)) and became this
modal + the drag. That is why `persistNodeMove` calls nothing equivalent — there is no edge state to
recalculate. The two arrows became a single door, and this is it.

### 5. Coverage: the keyboard door is not tested

There is no `.feature` touching `MOD.MOVETO` — not through the grip, not through the footer, not
through the kebab. `CLAUDE.md` advises against Behat for dragging (fragile headless), and the guidance
was followed; but the **modal** is precisely the deterministic alternative to dragging — a `select`
and a Save button, with nothing fragile about it. It is the cheapest gap in the kit to close: open it
from the kebab (`PLN-COMP-MOVETO`, already inside a dropdown — open the ⋯ first, per `CLAUDE.md`),
`I set the field "New position" to "2. …"`, save, check the order.
