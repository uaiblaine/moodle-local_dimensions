# Field map — `MOD.STRUCTREL` · Related-competency peek modal (as-is)

Modal opened by **clicking a related-competency chip** in the Competencies tab's detail panel. It shows
the **same detail card** as the inline panel (the shared partial `structure_detail_content`),
wrapped to fill the dialog with **its own** close button. It is read-only: the metric counters
render as **non-interactive** numbers and the related-competencies section is **omitted** — both so
as not to open a `MOD.USAGE` nor another `MOD.STRUCTREL` on top of this one.

Not to be confused with **`MOD.RELATED`** (`related_competencies.mustache`), which is the modal for
**editing** references (add/remove). This is the modal for **peeking** at a reference that already
exists — the card, not the editor. It is the plugin's most unusual surface: a dialog **with no
header**, where **the card is the dialog**.

The surface was born in `47677dd`; this map closes the gap recorded in Section 3 of the 2026-07-14
design.

- **Mustache:** [`structure_related_modal.mustache`](../../../templates/central/structure_related_modal.mustache) (shell) + the shared partial [`structure_detail_content.mustache`](../../../templates/central/structure_detail_content.mustache) (the card, the same one as the inline panel) + [`structure_related_chips.mustache`](../../../templates/central/structure_related_chips.mustache) (the chip that opens this modal)
- **JS:** [`competency_detail.js`](../../../amd/src/central/competency_detail.js) (`openCompetencyDetailModal`, 265-297) · [`structure.js`](../../../amd/src/central/structure.js) (trigger, 73 + 1245)
- **CSS:** [`styles.css`](../../../styles.css) (6660-6712, the dialog contract; + the exclusion at 5074)

## IDs

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `MOD.STRUCTREL-TRIGGER` | {competency name} | chip (trigger) | `structure_related_chips.mustache:37-39`, rendered client-side by `populateRelated` (`structure.js:478-504`) into the `detail-related-list` region that the `structure_detail_content` partial only creates **when `showrelated` is on** (`:116-125`) | `data-action="open-related"` · `data-id` | selector `structure.js:73` (`openRelated`); the handler (`structure.js:1244-1249`) reads `dataset.id` and calls `openCompetencyDetailModal`. Not to be confused with `data-action="related"` (`structure.js:64`, the actions-footer button that opens the `MOD.RELATED` editor, `:1273-1274`). **It does not exist inside the modal itself** — in there `showrelated` is `false`, so a chip never opens another modal. Only the **inline** panel fires it |
| `MOD.STRUCTREL-MODAL` | {name} | `core/modal` | `competency_detail.js:277-283` | `title: data.name`, `body`, `large: true`, `show: true`, `removeOnClose: true` | **plain** `core/modal` — no `footer`. The `title` **is** passed, but the header is `display:none` (see the CSS), so the title lives **only** in the card. The class `local-dimensions-related-modal` enters **afterwards**, at `:285` (`root.addClass`), and it is what triggers the whole CSS contract. `removeOnClose` → the modal dies on close, so the asynchronous chip/description renders are guarded by `isConnected` (`:291`) |
| `MOD.STRUCTREL-CARD` | — | wrapper | `structure_related_modal.mustache:36` | `.local-dimensions-related-modal-card` | `position:relative;overflow:hidden` (`styles.css:7011-7025`) — the anchor for the absolutely positioned button and the card's clipping. It is the same card as the inline panel's (`local-dimensions-central-plans-detail … structure-detail`), only with the modal's extra class |
| `MOD.STRUCTREL-CONTENT` | — | content | `structure_related_modal.mustache:41-45` + `renderDetailInto` (`competency_detail.js:291`) | `data-region="detail-content"` · `detailconfig {linksclickable:false, showrelated:false}` | the **same** `structure_detail_content` partial as the inline one, with **two locks**: `linksclickable:false` leaves the metric counters as plain numbers (otherwise a click would open `MOD.USAGE` **on top of** this modal); `showrelated:false` **omits** the related-competencies section (otherwise a chip would open another `MOD.STRUCTREL`, recursion). Filled client-side from `local_dimensions_get_structure_node` (`:267`) |
| `MOD.STRUCTREL-CLOSE` | Close | button (icon-only) | `structure_related_modal.mustache:37-40` | `data-action="close-related-modal"` · `aria-label` `{{#str}}closebuttontitle{{/str}}` | **this modal's real close** — core's `.btn-close` is `display:none` along with the header. `fa-times`; the **colour** is set in JS from the competency's `data.textcolor` (`competency_detail.js:294`, fallback `#fff`), to contrast with the card's coloured header. Closes through `modal.hide()` (`:295`). Scoped in `.local-dimensions-related-modal-close` (`styles.css:7022-7025`): absolute 18/18, 36×36, translucent white border and background, `transition:background .15s` |

## The CSS contract — why it is the only dialog like this

`local-dimensions-related-modal` (the class on the `root`) rewrites the whole `core/modal` shell so
that **the card is the dialog**, with no modal frame around it (`styles.css:6989-7015`):

| Rule | Value | Why |
| --- | --- | --- |
| `.modal-dialog` | `max-width:620px` + `border-radius:24px` | the card fits in 620; core focuses `.modal-dialog` (tabindex 0) on open and the focus outline hugs the card — rounding at 24px matches the card, since body and content carry no padding (comment at `:6666-6667`) |
| `.modal-header` | `display:none` (`:6671-6673`) | the coloured header belongs to **the card**, not to the modal; core's title would be redundant |
| `.modal-content` | `border:0;background:transparent;box-shadow:none` (`:6675-6679`) | removes the modal frame — what one sees is only the card |
| `.modal-body` | `padding:0` (`:6681-6683`) | the card touches the dialog's edge |

**The exclusion that closes the contract:** the global `.btn-close` restyle (the light-blue chip the
plugin applies to the close button of **every** modal with plugin content) carries a
`:not(.local-dimensions-related-modal)` in each selector (`styles.css:5374, 5088, 5098-5099`) —
that is, this modal is **explicitly taken out** of the restyle, because it has **its own** close
button (`MOD.STRUCTREL-CLOSE`), coloured by the competency, and core's `.btn-close` is hidden
anyway. Without that exclusion, two close buttons would fight over the corner.

## Summary — what this map fixes

| Fact | Where |
| --- | --- |
| It is **peeking**, not **editing** — the card, not `MOD.RELATED` | `competency_detail.js:265` vs `related_competencies.mustache` |
| The trigger lives **outside** the modal (only in the inline panel, `showrelated` on) | `structure.js:73` + `:1244-1249` |
| The two locks (`linksclickable`/`showrelated` off) exist so as **not to stack** modals | `competency_detail.js:275` |
| The `title` is passed but the header is `display:none` — the name lives in the card | `:279` + `styles.css:6995` |
| It is the only dialog **with no header** and **outside** the global `.btn-close` restyle | `styles.css:5374` (`:not(...)`) + `6671` |
| The close button's colour comes from the competency's `textcolor`, in JS | `competency_detail.js:294` |
