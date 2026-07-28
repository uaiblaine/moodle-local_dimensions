# Field map — `MOD.DETAIL` · The card is the dialog (as-is)

The plugin's most unusual surface: a modal **with no header**, in which **the competency's detail
card _is_ the dialog**. No core shell is visible — no title, no bar, no `.btn-close`, no footer. The
`core/modal` becomes a transparent 620px container and everything the user sees is the same card the
Competencies tab shows in its inline panel, floating over the backdrop, with a close button **of its
own**, drawn inside the body and painted in JS with the competency's text colour.

It is also the only place in the kit where the reader **cannot infer the construction without
opening three files** — the Mustache does not say the header disappears, the JS does not say why the
`title` is passed, and the CSS does not say who applies the class. That is why **the CSS contract
lives here**: it is the only place where it would be written.

> **File name × ID name — read this before going looking for `MOD.STRUCTRELATED`.**
> The file is called `mod-detail.md` because the **ID** is `MOD.DETAIL` — named earlier, in `pln-plans.md`. The **template** is still called `structure_related_modal.mustache`: it is the template's name that aged, not the ID.
> The **prefix is `MOD.DETAIL`**, not `MOD.STRUCTRELATED`, because **the modal already had a name**:
> [`pln-plans.md`](pln-plans.md) named it in the *Modals reached* table (`MOD.DETAIL` ←
> `competency_detail.js:277`) and refers to it again on the `PLN-COMP-NAME` row. Issuing a second
> name for the same dialog is exactly the defect the ID convention forbids (the
> `MOD.BROWSER-ACTION` × `PLN-BROWSE` case), and it would leave `pln-plans.md`'s reference pointing
> at nothing. **It is the template's name that aged**, not the ID: it was born as the target of the
> Competencies tab's chips (`47677dd`) and **one day later** became the card shared by the two tabs
> (`a59d5fb`, which extracted `competency_detail.js` and took 261 lines out of `structure.js`).
> Today "structure related" describes **one** of the two doors.

- **Mustache:** [`structure_related_modal.mustache`](../../../templates/central/structure_related_modal.mustache)
  (46, the headless shell) + [`structure_detail_content.mustache`](../../../templates/central/structure_detail_content.mustache)
  (126, **the partial shared with the inline panel** — it is what makes the two visuals identical by
  construction, not by coincidence)
- **AMD:** [`competency_detail.js`](../../../amd/src/central/competency_detail.js) (297) —
  `openCompetencyDetailModal` at `:265-297`; `renderDetailInto` (`:220-226`), `nodeToDetailData`
  (`:235-253`), `applyHeaderColors` (`:121-137`), `darkenHex` (`:102-112`). It imports `core/modal`
  (`:29`) and `local_dimensions/collapsible_description` (`:34`)
- **WS:** `local_dimensions_get_structure_node` (`db/services.php:125-132`) — **it always fetches the
  node fresh**, even when the caller already holds the data on the row (see rule 4)
- **CSS:** [`styles.css:6660-6712`](../../../styles.css) — the whole contract (table below); plus the
  **exclusion** at `:5074`, `:5088`, `:5098-5099`, and the inherited card at `:5813-5817` (24px
  radius) and `:5823-5830` (the 140deg gradient)
- **Behat:** none
- **Screen in the DS:** [`screens/mod-detail.html`](../screens/mod-detail.html) — single panel

**Abbreviations used in the tables:** `js:` = `amd/src/central/competency_detail.js` · `mustache:` =
`templates/central/structure_related_modal.mustache` · `detail:` =
`templates/central/structure_detail_content.mustache` · `css:` = `styles.css`. Paths that begin with
`lib/` are **core** (relative to `public/`) and are cited **without a line number**: the core
checkout does not live in this repository, so no line of it is verifiable from here.

## The name trap — answered head-on

There are **two** modals with "related" in the name, and they are **different things**:

| | `MOD.RELATED` ([`mod-related.md`](mod-related.md)) | **`MOD.DETAIL`** (this one) |
| --- | --- | --- |
| **What it is** | the relations **manager**: it lists, removes, adds from the tree | the **card** of the referenced competency, as a dialog |
| **Opened by** | `EST-DETAIL-RELATED` — the ⇄ button in the **sticky footer** (`structure_footer_actions.mustache:57-60`) | the **chips** (`MOD.RELATED-CHIP`) and the **names** on the Learning plans tab (`PLN-COMP-NAME`) |
| **Module** | `related_competencies.js:239` | `competency_detail.js:277` |
| **Core header** | **visible** (title "Related competencies — {name}") | **hidden** (`css:6671-6673`) |
| **Carries `.local-dimensions-related-modal`** | **no** | **yes** — `js:285` |

**So: the one that carries the `.local-dimensions-related-modal` class is this modal — the headless
card.** It is applied at `js:285` (`root.addClass('local-dimensions-related-modal')`), **after** the
`Modal.create`, on the root returned by `modal.getRoot()`. `MOD.RELATED`, despite the almost
identical name and being *the* "referenced competencies" modal, does **not** carry it.

**And what does the `:not()` protect?** The plugin's `.btn-close` restyle (`css:5063-5103`) paints a
1.75rem light-blue chip (`#e7f0f9`) with a dark-blue "×" (`#0f4d85`) on **every** modal that has
plugin content in its body. The selector is
`.modal:not(.local-dimensions-related-modal):has(.modal-body [class*='local-dimensions-']) .modal-header .btn-close`
(`css:5074`) — that is, it **excludes this modal** from the restyle it applies to all the others. The
comment at `css:5067-5068` gives the reason: *"the referenced-competency modal keeps its own close
button (its header is hidden), so it is excluded"*.

**Measured caveat, because the comment almost contradicts itself:** today the exclusion **protects
nothing that gets painted** — and the selector itself reinforces that. Core's `.btn-close` lives
inside `.modal-header` (`lib/templates/modal.mustache`), and that header is `display: none`
(`css:6671-6673`). An ancestor with `display: none` erases the whole subtree — declaring
`display: inline-flex` on a descendant does not resurrect it. After `02713fb` the selector began
traversing `.modal-header` **explicitly** (to leave the `.btn-close` of a toast hosted in the body
with core's look, `css:5071-5072`), which makes the redundancy even more literal: the target is
declaredly inside the hidden node. This modal's `.btn-close` **never renders, with or without the
`:not()`**; and the body's button has a class of its own (`.local-dimensions-related-modal-close`),
which the selector does not reach. The exclusion is therefore **declared intent + insurance** for the
day the header stops being hidden — not an active mechanism. Recorded as **deliberate redundancy**,
not as an error: the parenthetical in the comment itself ("its header is hidden") is the reason it is
redundant.

## The CSS contract — `css:6660-6712`

Five rules turn an ordinary `core/modal` into the card. **None of them uses `!important`**; they all
win on class specificity.

| Target | Declaration | Origin | Why |
| --- | --- | --- | --- |
| `.modal-dialog` | `max-width: 620px` | `css:6663-6664` | narrower than `modal-lg` — **and it overrides it**: `js:280` passes `large: true`, which puts `.modal-lg` (800px) on the dialog, and `.local-dimensions-related-modal .modal-dialog` (0,2,0) beats `.modal-lg` (0,1,0). The `large: true` is **dead letter** |
| `.modal-dialog` | `border-radius: 24px` | `css:6668` | **not decoration** — the dialog is transparent, so none of that border shows. It exists **only for the focus ring**: `core/modal` focuses the `.modal-dialog` on open (`getModal().focus()` in `lib/amd/src/modal.js`, and the `tabindex="0"` in `lib/templates/modal.mustache`). Without the radius the ring would come out **rectangular around a rounded card**. The 24px matches the card's radius (`css:5814`), and the comment at `css:6666-6667` says so |
| `.modal-header` | `display: none` | `css:6671-6673` | removes the title **and** core's `.btn-close` in one go |
| `.modal-content` | `border: 0` · `background: transparent` · `box-shadow: none` | `css:6675-6679` | erases the shell: what provides background, border and shadow is the **card** (`css:5815-5816`) |
| `.modal-body` | `padding: 0` | `css:6681-6683` | the card meets the dialog's edge — that is what makes the focus ring "hug" the card |

## Headless shell

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `MOD.DETAIL-MODAL` | — | `core/modal` | `js:277-283` | `title`, `body`, `large: true`, `show: true`, `removeOnClose: true` | **plain** `core/modal` — no `footer`. The class that triggers the whole contract comes in **afterwards**, at `js:285` |
| `MOD.DETAIL-TITLE` | {shortname} | title | `js:278` | `title: data.name` | **never paints** (the header is `display: none`) — **but it is not dead code**: it remains the dialog's **accessible name**. Measured, not deduced — see rule 1 |
| `MOD.DETAIL-CARD` | `[no label]` | region/root | `mustache:36` | **three** classes: `.local-dimensions-central-plans-detail` + `.local-dimensions-central-structure-detail` + `.local-dimensions-related-modal-card` | the first two are **borrowed**: they bring the 24px radius, the background, the shadow (`css:5813-5817`) and the header gradient (`css:5823-5830`) ready-made from the inline panel. The third is its own: `position: relative` (the close button's anchor) + `overflow: hidden` (clips the gradient at the rounded corners) — `css:6685-6688` |
| `MOD.DETAIL-CLOSE` | Close | button | `mustache:37-40` | `data-action="close-related-modal"` · `aria-label` = core str `closebuttontitle` (`mustache:38`) · `fa fa-times` (`:39`) | **it lives in the body, not in the header** — it is the substitute for `.btn-close`. `css:6690-6707`: `position: absolute` 18px from the top/right, 36×36, `z-index: 3`, `rgba(255,255,255,0.16)` background and `rgba(255,255,255,0.28)` border — a "glass" over the gradient; hover/focus rises to `rgba(255,255,255,0.3)` (`css:6709-6712`). The **colour** is written in JS (`js:294`): the competency's `data.textcolor`, with a **`'#fff'` fallback**. The listener is on the element directly (`js:295`), not delegated |
| `MOD.DETAIL-CONTENT` | `[no label]` | JS container | `mustache:41-45` | `data-region="detail-content"` | where the partial goes. `js:286` locates it in the modal root and **gives up silently** if it finds nothing (`:287-289`) |

## Content — the shared partial, with two flags switched off

The card's body **has no IDs of its own**: it is the whole `structure_detail_content.mustache`, the
**same** partial as the Competencies tab's inline panel, whose elements are already `EST-DETAIL-*` in
[`est-competencies.md`](est-competencies.md). This map does **not** re-issue them. What changes here is the
context: `{linksclickable: false, showrelated: false}` (`js:275`), and it changes **two** visible
things.

| Element (owner) | In the inline panel | **In this modal** | Mechanism |
| --- | --- | --- | --- |
| `EST-DETAIL-COURSES` · `-ACTIVITIES` · `-PLANS` | `<button data-action="show-usage">` → opens `MOD.USAGE` | an **inert** `<div>` — a number with no click | `{{#linksclickable}}` / `{{^linksclickable}}` (`detail:78-86`, `:90-98`, `:102-110`). **It is what stops a modal being stacked on top of this one** |
| `MOD.RELATED-CHIPS` (the ⇄ referenced section) | renders, with counter and chips | **does not exist in the DOM** | `{{#showrelated}}` (`detail:116-125`). Which is why `populateRelated` (`structure.js:478-504`) returns silently when the region is not there (`:482-484`) |
| Header, chips, description | identical | identical | the same `renderDetailInto` (`js:220-226`) |

**The design consequence:** there are no referenced-within-referenced, and no usage within detail.
The card is a **leaf** — it opens, it informs, it closes. No modal stacks on top of it.

## Entry doors — **two, none new here**

| ID (owner) | Tab | Origin | Path |
| --- | --- | --- | --- |
| `MOD.RELATED-CHIP` | Structure ([`mod-related.md`](mod-related.md)) | `structure_related_chips.mustache:36-42` | `data-action="open-related"` + `data-id` (`:38`) → `structure.js:1245-1249` → `openCompetencyDetailModal(id)` (`:1247`) |
| `PLN-COMP-NAME` | Learning plans ([`pln-plans.md`](pln-plans.md)) | `plans.mustache:407-408` | `data-action="open-competency-detail"` + `data-id` → `plans.js:746-747` → the same `openCompetencyDetailModal(id)` |

**Neither of the two is a footer** — and it is the only modal in the kit of which that is true. The
whole hub opens modals from a sticky-footer button; this one opens from **a chip** and from **a
clickable name in the middle of a list**. The *Modals reached* table in
[`pln-plans.md`](pln-plans.md) already recorded the observation.

## Business rules (verified in the code)

### 1. The title never paints — and still it names the dialog (measured)

`js:278` passes `title: data.name`. The header is `display: none` (`css:6671-6673`), so **none of
this appears on screen**. The tempting conclusion — "the `title` is dead code, it can go" — is
**wrong**.

`core/modal` sets `aria-labelledby="{{uniqid}}-modal-title"` on the dialog root
(`lib/templates/modal.mustache`), pointing at the `<h5 id="{{uniqid}}-modal-title">` — which is
**inside** the hidden header. Under AccName, a hidden node **directly referenced** by
`aria-labelledby` **does** enter the accessible-name computation.

**Measured in Chromium** (real accessibility tree, with positive and negative controls, over a
replica of `core/modal`'s structure + the plugin's rule):

- **real case** (header `display:none`, `aria-labelledby` → the `h5` inside it): the header's subtree
  shows up as `ignored` — the `h5` **never reaches the tree** — and even so the dialog comes out as
  **`dialog "Comunicação Assertiva" modal`**. **Named.**
- **positive control** (same dialog, header visible): `dialog "Visible Title Control" modal`.
- **negative control** (`aria-labelledby` pointing at a non-existent id): `dialog modal` — **no
  name**, proving the tool shows the absence when there is one.

That is: **the `title` is the only thing that names this dialog to a screen reader.** Removing it
would leave the card visually identical and the dialog anonymous. It is the reason
`MOD.DETAIL-TITLE` has an ID without painting a single pixel.

### 2. The focus ring is the reason for the 24px radius

Without `css:6668`'s `border-radius: 24px` nothing would change in appearance — the `.modal-content`
is transparent (`css:6675-6679`) and has no visible border. The rule exists because of **one**
moment: `core/modal` calls `getModal().focus()` on open (`lib/amd/src/modal.js`) and the
`.modal-dialog` carries `tabindex="0"` (`lib/templates/modal.mustache`). The browser's focus ring
follows the `border-radius` of the focused element. Since the `.modal-body` has `padding: 0`
(`css:6681-6683`), the dialog is **exactly** the size of the card — and the ring needs **exactly**
the card's radius (`css:5814`, 24px) so as not to draw a rectangle around a round card. The comment
at `css:6666-6667` records the reasoning; this map records that it **depends on two lines of core**.

### 3. The close button's colour comes from the data, not from the theme

`js:294`: `closebtn.style.color = data.textcolor || '#fff'`. The `textcolor` is the competency's
custom field (through `nodeToDetailData`, `js:246`), the same one that paints the header text
(`applyHeaderColors`, `js:136`). The button is a translucent "glass" (`rgba(255,255,255,0.16)` over
the gradient, `css:6703`), so the glyph has to follow the header text or it clashes.

**The risk this creates:** `textcolor` is free-form. A competency with a dark `textcolor` over a dark
`bgcolor` produces an illegible "×" — and **there is no guard**: no computed contrast, no conditional
fallback (the `|| '#fff'` covers only the **empty** value, not the illegible one). The same holds for
the whole header, so it is not a regression of this modal; it is the plugin's colour policy (the
forms' WCAG panel **advises and does not block** — see the a11y rule in
[`pln-plans.md`](pln-plans.md)), and here it reaches the dialog's **only** control. See the
measurement on the screen.

### 4. The modal always re-fetches, even with the data in hand

`openCompetencyDetailModal` (`js:265`) receives **only the id** and calls
`local_dimensions_get_structure_node` (`js:266-269`) before any render. Both callers **already have**
data: the chip is born from a tree row whose `dataset` holds everything `renderDetailInto` asks for,
and the Learning plans tab likewise.

It is deliberate, and the reason is `nodeToDetailData` (`js:235-253`): the card needs `coursecount`,
`activitycount`, `templatecount`, `ruletype`, `rulelabel` and `haschildren` (`js:247-252`) — numbers
the **originating row does not carry** (the chip only has `id` and `name`,
`structure_related_chips.mustache:38`, `:40`). Fetching the node fresh is what lets the two doors use
the **same** code. The cost: one round trip per opening, with no cache. If the node is gone it exits
silently — `if (!response.found || !response.node) return` (`js:270-272`) — with no toast and no
modal: the click simply does nothing.

### 5. `removeOnClose` is what holds the render guards up

`removeOnClose: true` (`js:282`) destroys the tree on close. That is why the guard on the
asynchronous renders is `() => modalcontent.isConnected` (`js:291`) — and not a flag: when the chips'
`getString`s and the description's `renderForPromise` come back (`js:156-158`, `:165-167`,
`:196-205`), the test is whether the node is still **in the document**. Closing quickly leaves no
"ghost chip" and no exception; `applyChipText` (`js:88-93`) simply does not write. The comment at
`js:290` says this in one line.
