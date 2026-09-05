# Field map — `MOD.PART` · Participants modal (as-is)

Host modal (`core/modal`, **with a footer** — the escape links to the admin pages live there, see
`PART-FOOT`) with an `<h5>` carrying the template name and **four** tabs:
**Cohorts / Users / Assign roles / Enrolment methods**. The tabs are **hand-rolled** — there is no
dependency on Bootstrap's tab JS inside the modal (`participants_manager.js:137`): `activateTab`
flips `.active`/`.show` and `aria-selected` by hand, with a WAI-ARIA *roving tabindex*
(Arrows/Home/End) on top.

Three of the four panes are born **empty** in the Mustache and are mounted by JS; only the Users one
arrives rendered from the server. That asymmetry is the origin of the loading gap recorded at the end
of this map — it is **not** the same gap as `EST`/`FWK`/`PLN`.

- **Mustache:** [`templates/central/participants_manager.mustache`](../../../templates/central/participants_manager.mustache) (154 lines, host), [`cohort_manager.mustache`](../../../templates/central/cohort_manager.mustache) (50), [`roles_manager.mustache`](../../../templates/central/roles_manager.mustache) (77), [`enrol_methods.mustache`](../../../templates/central/enrol_methods.mustache) (121)
- **PHP:** [`classes/output/dynamictabs/plans.php`](../../../classes/output/dynamictabs/plans.php) — the modal **has no renderable of its own**; it reads everything from the `data-*` of the `PLN` region (`:329-333`)
- **AMD:** [`participants_manager.js`](../../../amd/src/central/participants_manager.js) (297, host), [`cohort_manager.js`](../../../amd/src/central/cohort_manager.js) (234), [`participants_users.js`](../../../amd/src/central/participants_users.js) (312), [`roles_manager.js`](../../../amd/src/central/roles_manager.js) (252), [`enrol_methods.js`](../../../amd/src/central/enrol_methods.js) (1112) · header controls in [`modal_expander.js`](../../../amd/src/central/modal_expander.js) (120) and [`modal_refresh.js`](../../../amd/src/central/modal_refresh.js) (85)
- **Components in the DS:** [`modal-shell.html`](../modal-shell.html) — the shell this modal uses (refresh + expand/restore in the header, admin links in the footer), all of it shipped. [`cohort-assign.html`](../cohort-assign.html) draws the Cohorts tab in a style that is **not** the shipped one; see the pending note at the end of this map

> **Resync 2026-07-14.** The previous version of this map froze at `159a800` (2026-06-29) — the same
> vintage as `EST`, `FWK` and `PLN`. Measured, not estimated:
>
> - **24 refs in the old map; 12 broken — all 12 in `participants_manager.mustache`.** The 3 in
>   `cohort_manager.mustache` and the 9 in `roles_manager.mustache` **resolve**: neither of those two
>   files has changed since. The damage is concentrated, not diffuse.
> - **Two of the breaks are the worst kind — they resolve to a real control, only one of another ID.**
>   `PART-INDIVIDUAL` pointed at `:84`, which today is the **assign-to-user select**; `PART-ADD`
>   pointed at `:95`, which today is the **`fa-filter` icon** of the filters button. A reader checks,
>   sees a plausible element and moves on. The other ten landed on `</ul>`, `</li>`,
>   `{{#canassignroles}}`, layout `<div>`s and a `{{#str}}` in the middle of a `<label>`.
> - **`PART-TAB-ENROL` was marked as something still to come.** It **shipped** in `3d1d5cb`
>   (2026-07-11 23:03) — **~70 minutes** after `0b3782c` (21:53) wrote the line calling it a proposal.
> - **Zero JS refs**, as in `BAR`. The map listed four AMD modules in a bullet and did not point a
>   single line inside them: nothing on `activateTab`, `ensureMounted`, roving tabindex, `modal-xl`,
>   the toast region or `setRemoveOnClose`. (The names `HEADER_PAGES` and `injectHeaderLinks` it cited
>   **no longer exist** either: the links moved down to the footer and the functions are called
>   `ADMIN_PAGES` and `injectFooterLinks` today.)
> - **What was missing entirely:** the **filters dropdown** (`7c54c0b`, 2026-07-03) — the old map
>   listed cohort/search/individual as though they were loose controls on the bar; they live **inside
>   a dropdown** with BS4+BS5 attributes side by side. Plus: the 4th tab, `ROLES-FORM` (the whole
>   roles form is born `hidden`), the accessibility `<caption>`s of the three tables, and the entire
>   modal shell (title, close chip, `modal-xl`, header controls).
>
> The template went from **119 → 154** lines and the host JS from **158 → 297**. Five commits ran
> over it: `7c54c0b` (filters), `94734d0` (header links + table and close restyle),
> `3d1d5cb` (enrolment tab), `f84d30a` (`modal-xl`) and `0598289` (links moved to the footer).

> **Label note (verified, and awkward).** The old map called the 3rd tab "Papéis" and its cohort
> column "Coorte". Neither is what the screen shows. `central_roles_tab` = **"Assign roles"** (en) /
> **"Atribuir papéis"** (`lang/pt_br:285`), and in the roles pane pt-BR translates *cohort* as
> **"Público-alvo"** (`central_roles_col_cohort` `:268`, `central_roles_selectcohort` `:280`) — while
> the rest of the modal says **"Coorte"** (`central_participants_col_cohort` `:206`,
> `central_participants_tab_cohorts` `:222`). The en side is uniform (`Cohort` / `Cohorts`); the
> divergence is pt_br's alone. **The leak this map used to record is fixed:** `central_roles_nocohorts`
> (`lang/pt_br:274`) now sends the user to the *"aba Coortes"* — the tab's real name — instead of an
> "aba Públicos-alvo" that does not exist. What is left is the vocabulary inconsistency inside the
> roles pane itself. This map records labels **as they are rendered**; harmonising pt_br belongs to
> `lang/`, outside the kit's scope.

## Modal shell (JS only — there is no Mustache for this)

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `PART-MODAL` | Manage participants | modal | `participants_manager.js:171` | `Modal.create({title, body})` | plain `core/modal` — it does **not** pass `footer` to `create`; core's empty footer is revealed later by the admin links (see `PART-FOOT`). `setRemoveOnClose(true)` (`:172`): the modal is discarded on close, so **all the mounted state dies with it** (see `PART-LATCH`). Title via `central_participants_title` (`:164`) |
| `PART-DIALOG` | `[no label]` | classes on `.modal-dialog` | `participants_manager.js:178-183` | `modal-xl` + `local-dimensions-participants-modal` | **both** at once — there is no "modal with header links" class: the close chip already arrives through the `:has(.modal-body [class*='local-dimensions-'])` arm, because the body carries `.local-dimensions-participants` (comment at `:180-181`). `modal-xl` is **Bootstrap's own** (800px at `lg`, 1140px at `xl`, identical in 4 and 5) — core's API only exposes `setLarge()`, hence the hand-set class. The plugin's own class only hooks the **height** rule (`styles.css:5191-5205`, full viewport height when autocomplete suggestions are open) |
| `PART-EXPAND` | Expand / Restore size | pair of buttons (header) | `participants_manager.js:232` → `modal_expander.js:83` | `data-action="modal-expand"` / `="modal-restore"` · `fa fa-expand`/`fa-compress` | `mtube: expand`. Two buttons always present, injected before the `.btn-close` (`modal_expander.js:97-99`); they **share the header with `PART-REFRESH`** — the final order is [refresh][expand][restore][close], because the refresh anchors itself **before** the first `.local-dimensions-modal-sizetoggle`. **Both toggles use the same blue chip as the close button**: `1.75rem`, background `#e7f0f9`, glyph `#0f4d85`, hover `#d4e6fb` — combined base rule `.local-dimensions-modal-sizetoggle, .local-dimensions-modal-refresh` (`styles.css:5229-5253`), hover (`:4947-4952`). Its own focus ring via `:focus-visible` (`:4955-4958`), no `.btn`. With the links in the footer, the grouping with the close button comes from the `.modal-header:has(.local-dimensions-modal-sizetoggle) .modal-title` rule (`styles.css:5313-5324`), not from insertion order. The CSS shows what matches `.local-dimensions-modal-expanded` on the `.modal-dialog` (`:4966-4976`), with no icon swap in JS. Expanded = **real fullscreen** (`3c91646`): edge to edge, full height, square corners — `.modal-dialog.local-dimensions-modal-expanded` sets `width:100%`/`max-width:none`/`height:100%`/`margin:0` (`styles.css:5288-5295`) and the `.modal-content` gains `height:100%`/`border:0`/`border-radius:0` (`:4993-4997`); header and footer zero their radius (`:4999-5002`) and the body is `overflow-y:auto` (`:5004-5006`). It mirrors BS5's `.modal-fullscreen` without the class and without `!important`. The expander **seeds the saved size synchronously** (`modal_expander.js:90`, before the first `await` at `:92`), so the modal opens at the right size even with the refresh chained after it. The size persists in the `modalexpanded` key of `PREF_CENTRAL_DISPLAY` (**shared with `mod-links`**) — see `mod-links.md` for the mechanics and the two a11y decisions (its own focus ring, focus handed back to the opposite button on toggle, `modal_expander.js:106-118`) |
| `PART-REFRESH` | Refresh | button (header) | `participants_manager.js:232` → `modal_refresh.js:58` | `data-action="modal-refresh"` · `fa fa-rotate` | `central/modal_refresh` injects **one** button into the header, anchored **before** the first `.local-dimensions-modal-sizetoggle` (otherwise before the `.btn-close`) → order [refresh][expand][restore][close] (`modal_refresh.js:67-68`). Chained **after** the expander: `attachExpander(dialog).then(() => attachRefresh(dialog, refreshActiveTab))` (`:232`). Core label `getString('refresh')` (`modal_refresh.js:63`) — **no new string**. It owns the *busy* state: on click it **disables the button and adds `fa-spin`** to the icon and **clears both in a `finally`** (`modal_refresh.js:70-84`), so a failed reload never pins the button. Same blue chip as the toggles (`styles.css:5229-5253`), its own focus ring (`:4955-4958`), disabled state at `:4962-4964`. The `refreshActiveTab` callback (`participants_manager.js:213-229`) dispatches the **handle per tab** — enrol→`init`, cohorts/roles→`refresh`, users→`applyFilters` (each `mount` resolves with `{refresh}`); if the pane never mounted, it **re-mounts** (recovery, safe against double wiring — the comment at `:223-227` explains why) |
| `PART-FOOT` | `[no label]` | revealed footer | `participants_manager.js:185` | `.modal-footer` + `.local-dimensions-modal-footer-links` | core calls `hideFooter()` in `show()` when the footer has no children (`hasFooterContent` = `getFooter().children().length`, in `lib/amd/src/modal.js`). The plugin exploits exactly that: `injectFooterLinks` (`:103-134`) is **awaited before** `show()` (`:185` → `:296`), so the link group is already a child of the footer at `show()` time and core reveals it by itself |
| `PART-TOAST` | `[no label]` | toast region | `participants_manager.js:236` | `addToastRegion(modal.getBody()[0])` | house pattern: without it, the managers' toast renders **behind** the dialog (`.toast-wrapper` is `z-index:1051`, the modal is `1055`). The **host** owns the region; `cohort_manager` and `participants_users` do **not** create their own. Core removes it on close |
| `PART-CLOSE` | Close | chip | `styles.css:5374-5413` | core's `.btn-close`, restyled | `1.75rem`, radius `8px`, background `#e7f0f9` (`:5074-5086`), FA glyph `\f00d` in `#0f4d85` (`:5088-5096`, **7.53:1** measured), hover `#d4e6fb` (`:5098-5102`, **6.82:1**). Literals, no dark variant |

> **The chip's second arm — for `.modal-form-dialogue` (`025c2f6`).** The close chip comes out of a
> **group of two selectors** at `styles.css:5374-5377`: the first
> (`.modal:not(.local-dimensions-related-modal):has(.modal-body [class*='local-dimensions-'])`) catches
> the modals whose **body** carries a plugin class — that is how the participants modal gets the chip
> (body with `.local-dimensions-participants`, comment at `participants_manager.js:180-181`), and it is
> why there is no "modal with header links" class here. The second selector
> (`.local-dimensions-central-page .modal-form-dialogue .modal-header .btn-close`) covers the **form
> modals** (framework/competency/plan): their body is core form markup and **escapes** the `:has()`, so
> before, the chip only appeared once the async body arrived (the flash). The `.modal-form-dialogue`
> core puts on the dialogue **synchronously before `show()`** pays for the chip from the first frame.
> The title's `flex-grow` lives in
> `.modal-header:has(.local-dimensions-modal-sizetoggle) .modal-title` (`styles.css:5313-5324`),
> grouping expander + close without depending on any class on the dialogue.

## Footer links (injected by JS, one per active tab)

`ADMIN_PAGES` (`participants_manager.js:57-66`) declares **4** destinations — one per tab; each object
carries `pane`/`path`/`flag`/`strkey`. `injectFooterLinks` (`:103-134`) filters the permitted ones
(`region.dataset[flag] === '1'`, `:108`) and, if any survive, builds a `<div
class="local-dimensions-modal-footer-links">` (`:113-114`) with one `<a target="_blank"
rel="noopener noreferrer" class="btn btn-link p-0 d-none">` (`:116-120`, **each link is born `d-none`
stamped with `data-pane`**, `:120-121`) + `<i class="fa fa-external-link me-1">` (`:122-124`) per
destination and calls `footer.appendChild(group)` (`:130`) — the previously hidden footer gains a child
and core reveals it (see `PART-FOOT`). They sit **on the left** (`margin-right:auto`,
`styles.css:5335`), with the primary action — when there is one — on the right. **Only the active
tab's link shows:** `injectFooterLinks` reveals the initial tab's link (`:131-133`) and
`showFooterLinkFor(root, activepane)` (`:76-91`) toggles the `d-none` of each `a[data-pane]` on a tab
change, adding `local-dimensions-modal-footer-empty` (`styles.css:5347-5361`, `display:none`) to the
footer when the active tab has no permitted link; `selectTab` calls it on the change (`:254`). The
visible label **is** the accessible name (`:126-127`: no extra `title`/`aria-label`), and the
`btn-link` gets its own focus ring for both BS4 and BS5 (`styles.css:5342-5354`).

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `PART-LINK-COHORTS` | Open cohorts page | link | `participants_manager.js:58-59` | `/cohort/index.php` · flag `cancohortpage` | `moodle/cohort:view` **or** `:manage` at system level (`dynamictabs/plans.php:242-243`) |
| `PART-LINK-USERS` | Open users page | link | `participants_manager.js:60-61` | `/admin/user.php` · flag `canuserpage` | `moodle/user:update` **or** `:delete` (`:241-242`) |
| `PART-LINK-ROLES` | Open roles page | link | `participants_manager.js:62-63` | `/admin/roles/manage.php` · flag `canassignroles` | `moodle/role:manage` (`:238`) |
| `PART-LINK-ENROL` | Manage enrol plugins | link | `participants_manager.js:64-65` | `/admin/settings.php?section=manageenrols` · flag `canenrolpage` | `moodle/site:config` (`:243`) |
| `PART-LINK-ALL` | `[no label]` | display rule | `participants_manager.js:76-91`, `:103-134` | `showFooterLinkFor` + `injectFooterLinks` | **one per active tab**: `showFooterLinkFor` (`:76-91`) toggles the `d-none` of each `a[data-pane]` and collapses the footer (`local-dimensions-modal-footer-empty`, `styles.css:5347-5361`) when the active tab has no permitted link. `injectFooterLinks` returns early if the footer does not exist (`:104-107`) or if no link is permitted (`:108-111`) |

> **Tab and link are doors with different locks — and in two tabs they diverge.** The Roles tab and
> the roles link share the **same** flag (`canassignroles`), so they travel together. The **Enrolment
> methods** tab, though, is gated on `canmanageenrol`, which `plans.mustache:136` feeds with
> **`{{canmanage}}`** = `moodle/competency:templatemanage` **in context** (`dynamictabs/plans.php:98`,
> `:329`) — while its **link** wants `moodle/site:config` **at system level**. A template manager sees
> the tab and does **not** see the link. The Cohorts and Users tabs are unconditional; their links are
> not.

## Host + tabs

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `PART-ROOT` | `[no label]` | region/root | `participants_manager.mustache:36` | `data-region="participants"` · `data-contextid` | the context comes from the `data-contextid` of the `PLN` region (`participants_manager.js:167`, and again at `:188` for the panes' `opts`) |
| `PART-HEADER` | template name | heading | `participants_manager.mustache:38` | `{{templatename}}` | `<h5 class="mb-0">`, inside `.local-dimensions-participants-header` (`:37`). It **duplicates** the name already in the modal title — the modal is called "Manage participants" and the `<h5>` says which plan |
| `PART-TABLIST` | `[no label]` | tablist | `participants_manager.mustache:40` | `data-region="participant-tabs"` | `<ul class="nav nav-tabs" role="tablist">`; the `<li>`s are `role="presentation"` and the `<button>` is what carries `role="tab"` |
| `PART-TAB-COHORTS` | Cohorts | tab | `participants_manager.mustache:42-46` | `data-target-pane="pane-cohorts"` · `data-region="tab-cohorts"` | **born active** (`.active` and `aria-selected="true"` at `:42`, `:44`); unconditional. Label at `:45` — **plain text, no icon** (see the pending note) |
| `PART-TAB-USERS` | Users | tab | `participants_manager.mustache:49-53` | `data-target-pane="pane-users"` | unconditional; label at `:52` |
| `PART-TAB-ROLES` | **Assign roles** | tab | `participants_manager.mustache:57-61` | `data-target-pane="pane-roles"` | only under `{{#canassignroles}}` (`:55`-`:63`); label at `:60` |
| `PART-TAB-ENROL` | Enrolment methods | tab | `participants_manager.mustache:66-70` | `data-target-pane="pane-enrol"` | only under `{{#canmanageenrol}}` (`:64`-`:72`); label at `:69`. **Shipped in `3d1d5cb`** — mounts `MOD.ENROL` |
| `PART-PANE-COHORTS` | `[no label]` | **empty** pane | `participants_manager.mustache:75-76` | `data-region="pane-cohorts"` | `<div></div>` — filled by `mountCohorts` |
| `PART-PANE-USERS` | `[no label]` | **rendered** pane | `participants_manager.mustache:77-144` | `data-region="pane-users"` | **the only one** that arrives ready from the server |
| `PART-PANE-ROLES` | `[no label]` | **empty** pane | `participants_manager.mustache:146-147` | `data-region="pane-roles"` | `{{#canassignroles}}` (`:145`-`:148`) |
| `PART-PANE-ENROL` | `[no label]` | **empty** pane | `participants_manager.mustache:150-151` | `data-region="pane-enrol"` | `{{#canmanageenrol}}` (`:149`-`:152`) |

## Cohorts tab (`MOD.COHORT`)

Mounted by `cohort_manager.js:208-233`: strings → `renderForPromise` → `replaceNodeContents` →
`setup`, and it resolves with `{refresh}` (`:233`, whose target is the `refresh` at `:82`).

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `COHORT-ADD` | Add cohort | select/autocomplete | `cohort_manager.mustache:35-36` | `data-region="cohort-add"` · `data-contextid` | label at `:34`; enhanced on `ModalEvents.shown` (core's `enhance` resolves through `document.querySelector`) |
| `COHORT-CAPTION` | Cohorts | caption | `cohort_manager.mustache:39` | `visually-hidden` | table accessibility — **was not in the map** |
| `COHORT-HEAD` | Cohort · Members · Plans · Actions | header | `cohort_manager.mustache:42-45` | — | the 4th column is `{{#str}}actions{{/str}}` (it **does** have a label — "Actions"; the old map said "no label") |
| `COHORT-ROWS` | `[no label]` | JS container | `cohort_manager.mustache:48` | `data-region="cohort-rows"` | rows via `local_dimensions_list_template_cohorts` |

## Users tab (server-rendered)

Mounted by `participants_users.js:262-312`: it does **not** call `replaceNodeContents` — the markup
already exists, so the mount only wires the events (`wire`, `:298`) and fetches the rows. That initial
fetch (`applyFilters`) is **swallowed into a toast** (`:310`, with the reason in the comment at
`:307-309`): the wiring is already in place, so a first-load failure does not lock the pane — the
visible filter controls re-run `applyFilters` over the same state (that is what keeps re-mounting via
a released latch safe for this pane; see `PART-LATCH`). It resolves with
`{refresh: () => applyFilters(state)}` (`:311`).

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `PART-ADD` | **Assign to user** | select/autocomplete | `participants_manager.mustache:84-85` | `data-region="participant-add"` | label at `:81-83`. `participants_users.js:272-274` stamps `contextid`/`templateid` onto the select's own `dataset` (*dataset-as-truth*). The old map said "Add participant" — that is not the label |
| `PART-FILTERS` | Filters | dropdown | `participants_manager.mustache:90-96` | `data-region="participant-filters"` | **was missing entirely.** `<i class="fa fa-filter me-1">` + `{{#str}}filters, moodle{{/str}}` (`:95`). It carries **`data-toggle` AND `data-bs-toggle`** side by side (`:93`) — 4.5 is BS4 and listens on `data-toggle`; 5.x is BS5 and listens on `data-bs-toggle`, and the JS attributes are **not** bridged to one another. `data-bs-auto-close="outside"` keeps the menu open on BS5; on BS4 the `<form>` does that job (`:100`) — commented at `:87-89` |
| `PART-FILTERS-MENU` | `[no label]` | menu | `participants_manager.mustache:97-99` | `.dropdown-menu` | **both** alignment classes: `dropdown-menu-right` (BS4) + `dropdown-menu-end` (BS5) |
| `PART-COHORTFILTER` | Filter by cohort | select | `participants_manager.mustache:106-107` | `data-region="participant-cohort"` | label at `:102-105`; **inside** the dropdown |
| `PART-SEARCH` | **Search by name** | text input | `participants_manager.mustache:114-115` | `data-region="participant-search"` | label at `:110-113`; inside the dropdown. Debounce in `participants_users.js` (`state.debounce`, `:284`) |
| `PART-INDIVIDUAL` | **Show individual plans** | switch | `participants_manager.mustache:118-120` | `data-region="participant-individual"` | `.form-check.form-switch` (`:117`), label at `:121-123`; inside the dropdown |
| `PART-CAPTION` | Users | caption | `participants_manager.mustache:130` | `visually-hidden` | — |
| `PART-HEAD` | User · Status · Template · Cohort · Individual · Actions | header | `participants_manager.mustache:133-138` | — | 6 columns, the last one "Actions" (`{{#str}}actions{{/str}}`, `:138`) |
| `PART-ROWS` | `[no label]` | JS container | `participants_manager.mustache:141` | `data-region="participant-rows"` | `<tbody>` |
| `PART-SENTINEL` | `[no label]` | sentinel | `participants_manager.mustache:143` | `data-region="participant-sentinel"` | infinite scroll via `IntersectionObserver` (`participants_users.js:300-305`) |
| `PART-ROWBTN` | `[no label]` | CSS rule | `styles.css:5355-5367` | `#local-dimensions-pane-users button.btn.btn-outline-secondary.btn-sm.me-1` | `margin-bottom: 5px` **scoped to this pane alone** — in the other tabs the extra margin threw solitary buttons out of line |

## Assign roles tab (`MOD.ROLES`)

Mounted by `roles_manager.js:219-251`. `refresh` (`:113`) is what decides which of the three blocks
appears; `mount` returns it as the handle (`:251`).

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `ROLES-NOROLES` | warning: no role is assignable at user context | alert | `roles_manager.mustache:31-33` | `data-region="role-noroles"` | `hidden` until the JS decides |
| `ROLES-NOCOHORTS` | warning: link a cohort first | alert | `roles_manager.mustache:34-36` | `data-region="role-nocohorts"` | `hidden` until the JS. The text sends the user to the **"Cohorts tab"** — the tab's real name (see the label note); what is left is the "público-alvo" vocabulary inside the pane itself in pt_br |
| `ROLES-FORM` | `[no label]` | container | `roles_manager.mustache:37` | `data-region="role-form"` | **was missing.** It wraps **everything** below and is born `hidden` — the pane can be entirely invisible if either warning wins |
| `ROLES-USER` | User | select/autocomplete | `roles_manager.mustache:43` | `data-region="role-user"` | label at `:40-42` |
| `ROLES-ROLE` | Role | select | `roles_manager.mustache:49` | `data-region="role-role"` | label at `:46-48` |
| `ROLES-COHORT` | Cohort | select | `roles_manager.mustache:55` | `data-region="role-cohort"` | label at `:52-54`. Rendered "Público-alvo" in pt_br — see the label note |
| `ROLES-ADD` | **Assign role** | button | `roles_manager.mustache:57-59` | `data-action="role-add"` | `btn-primary` |
| `ROLES-CAPTION` | Assign roles | caption | `roles_manager.mustache:62` | `visually-hidden` | — |
| `ROLES-HEAD` | User · Role · Cohort · Status · Actions | header | `roles_manager.mustache:65-69` | — | 5 columns, the last one "Actions" (`:69`) |
| `ROLES-ROWS` | `[no label]` | JS container | `roles_manager.mustache:72` | `data-region="role-rows"` | `local_dimensions_list_template_cohort_roles` |
| `ROLES-NOTES` | notes (background / global) | text | `roles_manager.mustache:74-75` | — | the assignment is asynchronous and applies **globally**, not only in this plan |

## Enrolment methods tab (`MOD.ENROL`)

The pane is empty (`participants_manager.mustache:150-151`) and mounted by `enrol_methods.js:1082-1112`.
The `mount` **swallows the initial load (`init`) into a toast** (`:1108`) because the listeners are
delegated on the **container element itself** (`state.root`, via `wireEvents` at `:1103`) and survive
`replaceNodeContents` — so "it clears, therefore a re-mount is safe" does **not** hold for enrol (the
comment at `:1104-1107` says so). If the first load fails before revealing any region, `init` reveals
the dedicated `enrol-error` region and hides empty/disabled/main (`enrol_methods.js:939-942`),
rethrowing (`:943`) so the mount's toast still fires; **recovery** is the header's `PART-REFRESH`,
which calls the `{refresh: () => init(state)}` handle (`:1111`). The three alert regions are
**message-only blocks**, with no button — `enrol-disabled` (`enrol_methods.mustache:36-38`),
`enrol-empty` (`:39-41`), `enrol-error` (`:42-44`), with the reason written in the template's comment
(`:33-35`: *"Reloading is the modal header refresh now, so each alert carries just its message"*). The
content has its own map — see [`mod-enrolmethods.md`](mod-enrolmethods.md).

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `ENROL-REFRESH` | **Retired** (`7d69197`, 2026-07-18) | — | absent | — | There were **four** `data-action="enrol-refresh"` buttons (one in each of the three `alert`s + one on the filters bar) plus the click handler in `enrol_methods.js` — **all of them are gone**. A `grep -rn 'enrol-refresh' templates/ amd/src/` returns **nothing**. Recovery moved to the modal header's `PART-REFRESH`. The ID is kept here only for whoever comes looking for it; there is no corresponding control |
| `ENROL-SELBAR` | `[no label]` | selection bar | `enrol_methods.mustache:103-119` | `.border-top.pt-2` | counter (`:104`) + "processing" (`fa-spinner fa-spin`, `:105-108`) + Remove method / Apply method, both `disabled` by default (`:110`, `:114`) |

## Host behaviour (`participants_manager.js`)

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `PART-ACTIVATE` | `[no label]` | tab change | `participants_manager.js:143-154` | `activateTab` | **hand-rolled tabs** (`:137`: "no Bootstrap tab JS dependency in the modal"): it flips `.active` + `aria-selected` on the buttons (`:145-148`) and `.show`/`.active` on the panes (`:149-153`). **Synchronous** |
| `PART-ROVING` | `[no label]` | keyboard | `participants_manager.js:263`, `:273-294` | `tabindex` 0/-1 | WAI-ARIA roving tabindex: the active tab is the **only** tab stop (`:263`, maintained by `selectTab` at `:255`). `ArrowRight` (`:281-282`), `ArrowLeft` (`:283-284`), `Home` (`:285-286`), `End` (`:287-288`) — circular, with `preventDefault` and focus moved (`:291-292`) |
| `PART-MOUNT` | `[no label]` | lazy mount | `participants_manager.js:243-248` | `ensureMounted` | it reads `button.dataset.region` (`:244`) and **looks the row up in the `MOUNTS` table** (`:46-51`, `[key, mountfn, selector]` per region) to call `startMount` (`:246`). It does not mount directly: `startMount` is what claims/releases the latch (see `PART-LATCH`), so **re-clicking a tab whose latch is released re-mounts it** |
| `PART-LATCH` | `[no label]` | mount latch | `participants_manager.js:191`, `:198-210` | `mounted = {cohorts, users, roles, enrol}` + `startMount` | a `mounted` table (`:191`) and a `startMount(key, mountfn, selector)` (`:198-210`) that **claims** the latch synchronously (`:202`, blocking the double click), stores each pane's `{refresh}` handle in `handles[key]` (`:203-205` — see `PART-REFRESH`) and **releases the latch in the `.catch`** (`:207`), so the next activation of the tab re-mounts. The invariant that makes this safe is written in the comment at `:194-197`: **a released latch always means an unwired pane** — cohorts and roles clear and rewire fresh children on a re-mount, and users and enrol only reject **before** the wiring goes in (each one's single post-wiring failure resolves instead of rejecting). Before `c96a3e9` there were three booleans raised **before** the await, with a fire-and-forget mount: one rejection pinned the latch at `true` forever and only reopening the modal recovered |
| `PART-COHORTMOUNT` | `[no label]` | initial mount | `participants_manager.js:237` | `startMount('cohorts', mountCohorts, …)` | runs on `ModalEvents.shown` (`:233`). Before `c96a3e9` it was a fire-and-forget `mountCohorts(...)` **with no latch and no retry trigger** — the default pane failed with no recovery at all; now it enters the `mounted` table via `startMount`, and re-clicking the Cohorts tab (`ensureMounted`, `:243-248`) re-mounts it |

## **This** modal's loading gap (derived from `ensureMounted`)

Two things a future fix needs to know before designing any indicator here.

**1. No existing waiting coverage reaches this modal.** The hub's busy cover belongs to `reloadPane`
(`tabs.js:69-108` + `styles.css:4334-4381`) and covers a reloaded **page-tab pane**; and core's
tab-change loading belongs to `core/dynamic_tabs` (`loadTab` → `addIconToContainer`, in
`lib/amd/src/dynamic_tabs.js`), which governs the **page tabs** (`EST`/`FWK`/`PLN`). **This modal's**
tabs are hand-rolled (`activateTab`, `participants_manager.js:143-154`) and go nowhere near either of
those. There is nothing to inherit here: the gap is ours.

**2. The bigger gap is not the tab change — it is the _first paint_, and on a tab change it is
asymmetric (3 of 4).** `Modal.create` receives the rendered body and `modal.show()` is called at
`:296`; only **afterwards** does `ModalEvents.shown` (`:233`) fire `startMount('cohorts', …)` (`:237`)
→ `mountCohorts`, which still needs strings + `renderForPromise` + `replaceNodeContents` + a WS
(`cohort_manager.js:208-233`). Because the Cohorts pane is **born empty**
(`participants_manager.mustache:75-76`) and is the tab **born active** (`:42`), the modal opens with
the four tabs drawn and **a blank body under them**. On a tab change, `selectTab` calls `activateTab`
(`:253`) **before** `ensureMounted` (`:256`), so the pane sits **visible and empty** while the mount
runs — true for Cohorts, Roles (`:146-147`) and Enrolment methods (`:150-151`), the three
`<div></div>`s. **Users is the exception**: the pane arrives rendered from the server (`:77-144`), so
filters and table header appear at once and only the **rows** are missing. A fix that treats all 4
tabs alike is treating 3 problems and 1 non-problem.

> **Pending — a first-load indicator for the lazy panes.** There is nothing: a
> `grep -niE 'spinner|loading|aria-busy|role="status"|placeholder|skeleton'` over
> `participants_manager.js`, `cohort_manager.js`, `participants_manager.mustache` and
> `cohort_manager.mustache` returns **zero**, and `participants_manager.mustache:75-76` is still a
> literal `<div></div>`. The design asked for was a `spinner-border` in place of the missing content,
> with the container carrying `aria-busy` + `aria-live="polite"` + `aria-label` + `role="status"` (the
> spinner `aria-hidden`) and receiving focus, in the **three** empty panes — the Users one would ask
> for a row skeleton at most. Where to look when building it:
> `participants_manager.js:198-210` (`startMount`) and `:243-248` (`ensureMounted`).

> **Pending — icons on this modal's own tabs.** The hub's **page** tabs have had a glyph since
> `514d246` (`central.php:108-112` defines `fa-sitemap`/`fa-crosshairs`/`fa-graduation-cap`, assembled
> into the label at `:122`/`:125`); **this modal's** four tabs do not.
> `participants_manager.mustache:40-72` renders four bare `{{#str}}` (`:45`, `:52`, `:60`, `:69`) and
> the only `<i class="fa">` in the entire file is the `fa-filter` of the filters button (`:95`).

> **Pending — the Cohorts tab in the "group management" style.** `cohort-assign.html` draws a
> checkbox list, per-row sync pills, a plans roll-up and a bulk apply button. None of that exists:
> `cohort_manager.mustache:38-49` is still the 4-column table
> (cohort / members / plans / actions) inventoried in `MOD.COHORT` above, with no checkbox and no bulk
> action.
