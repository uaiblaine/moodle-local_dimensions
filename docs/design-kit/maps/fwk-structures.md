# Field map — `FWK` · Structures (as-is)

A list of structure (competency framework) **cards** for the resolved context. The whole card is a
selection button: choosing one marks the card and publishes its actions in the **page's sticky
footer**. The toolbar carries the counter ("· N hidden") and **three** header actions (new / import
/ export). The System/Category selector comes from the contextbar (`BAR`).

**The card's actions do not live in the card** — they live in the page's sticky footer, injected by
`selectFramework` (`frameworks.js:466-474`) and routed back by `dispatchFrameworksAction`
(`frameworks.js:429-447`).

- **Mustache:** [`templates/central/frameworks.mustache`](../../../templates/central/frameworks.mustache), [`frameworks_row.mustache`](../../../templates/central/frameworks_row.mustache), [`frameworks_footer_actions.mustache`](../../../templates/central/frameworks_footer_actions.mustache), [`frameworks_export.mustache`](../../../templates/central/frameworks_export.mustache), [`showhidden_toggle.mustache`](../../../templates/central/showhidden_toggle.mustache)
- **PHP:** [`classes/output/dynamictabs/frameworks.php`](../../../classes/output/dynamictabs/frameworks.php)
- **AMD:** [`amd/src/central/frameworks.js`](../../../amd/src/central/frameworks.js) (525 lines), [`central/tabs.js`](../../../amd/src/central/tabs.js), [`central/action_footer.js`](../../../amd/src/central/action_footer.js)
- **Component in the DS:** no dedicated component — the card carries name + idnumber + description +
  count pill (`frameworks_row.mustache:46-58`), and `reloadPane`'s loading shipped as a busy curtain
  (see IMP-03 at the end of this map).

> **Name note (verified).** `FWK` is the **first** tab and is born active (`central.php:114-115`), and
> its label is `central_frameworks_tab` = **"Structures"** (`central.php:100`; `lang/{en,pt_br}:164`).
> The one called "Structure" in the code (`dynamictabs/structure.php`, map `EST`) is the **second**
> tab, whose label is `managecompetencies_structure` = **"Competencies"** (`central.php:101`;
> `lang/{en,pt_br}:487`). The internal names and the visible labels are **inverted** —
> `bar-contextbar.md` already uses the visible labels ("the Structures tab toolbar"), and this map
> follows the same convention.

> **Anchor note.** Every `file:line` ref in this map was re-derived against `d0adc3b`.
> Reference sizes at the moment of derivation: `frameworks.mustache` 109 lines,
> `frameworks_row.mustache` 59, `frameworks_footer_actions.mustache` 59,
> `frameworks_export.mustache` 43, `frameworks.js` 525, `styles.css` 7434.

## Root and page data

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `FWK-ROOT` | `[no label]` | region/root | `frameworks.mustache:63-65` | `data-region="frameworks"` | carries `contexttype`, `categoryid`, `contextid`, `canmanage`, `canscalespage`; `init` resolves it by selector (`frameworks.js:483`) and keeps it in `activeRegion`/`activePane` (`:488-489`) |
| `FWK-CANSCALES` | `[no label]` | flag | `frameworks.mustache:65` | `data-canscalespage` | `has_capability('moodle/course:managescales', system)` (`dynamictabs/frameworks.php:130`); its **only** consumer is `injectScalesLink` (`frameworks.js:138`) → `FWK-SCALES-LINK` |

## Header and toolbar

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `FWK-EMPTY-CAT` | "Choose the course category first…" | empty state | `frameworks.mustache:67-69` | str `managecompetencies_selectcategory_help` | blocks the whole tab (the `{{^needscategoryselection}}` at `:71` wraps everything else) |
| `FWK-SHOWHIDDEN` | Show hidden structures | switch | `showhidden_toggle.mustache:44-45`, called from `frameworks.mustache:72-74` | `data-action="{{action}}"` → `toggle-hidden` | **shared partial** with `EST`/`PLN`: the `data-action` is a **variable** in the template and the literal value comes from `dynamictabs/frameworks.php:152` (context at `:149-154`; **null when there is not a single hidden one** → does not render). State in the `frameworksshowhidden` preference (`frameworks.js:521`) **and** in `pane.dataset.showhidden` (`:520`), then `reloadPane` (`:522`) |
| `FWK-TOOLBAR` | `[no label]` | container | `frameworks.mustache:76` | `.local-dimensions-central-fwtoolbar` | `space-between`; counter on the left, actions on the right |
| `FWK-COUNT` | "Structures listed: N" | counter | `frameworks.mustache:77-78` | `frameworkcount` | str `central_frameworks_listed`; it counts the rows **displayed** (`count($rows)`, `dynamictabs/frameworks.php:142`) — the **2nd of the hub's three counters** (see `bar-contextbar.md`). 15px `#495057` (`styles.css:5157-5161`), number in `#1d2125` (`:5163-5166`) |
| `FWK-HIDDENCOUNT` | "· N hidden" / "· 1 hidden" | suffix | `frameworks.mustache:78` | `hasexcluded` / `hiddenlabel` | strs `central_frameworks_hiddencount` + `central_frameworks_hiddencount_one`, chosen by a literal `if` and **resolved in PHP** (`dynamictabs/frameworks.php:118-127`) — the template receives finished text, it does not call `{{#str}}`, because `get_string` has no plural forms and pt_br inflects the adjective. `excludedcount = showhidden ? 0 : hiddencount` (`:117`) — it disappears while the toggle is on, because then nothing is being hidden. It exists to keep `FWK-COUNT` **honest** (comment at `:115-116`). Colour at `styles.css:5168-5170` |
| `FWK-ACTIONS` | `[no label]` | group | `frameworks.mustache:81` | `.local-dimensions-central-fwactions` | the whole group is gated by `{{#canmanage}}` (`:80-94`) |
| `FWK-NEW` | New structure | button | `frameworks.mustache:82-84` | `data-action="new"` | `fa-plus`; primary (`.local-dimensions-central-plans-new`); `createFramework` → a modal with the region's `contextid` (`frameworks.js:201-202`) |
| `FWK-IMPORT` | Import | button | `frameworks.mustache:85-87` | `data-action="import"` | `fa-upload`; outline; `openImportForm` (`frameworks.js:259-276`) → dynamic form with CSV |
| `FWK-EXPORT` | Export | button | `frameworks.mustache:88-92` | `data-action="export"` | `fa-download`; outline; **double gate** — `{{#canexport}}` (`:88`) nested inside the `{{#canmanage}}`, and `canexport = canmanage && !empty($rows)` (`dynamictabs/frameworks.php:147`), so it disappears when there is no structure to export |
| `FWK-LIST` | `[no label]` | container | `frameworks.mustache:98` | `data-region="framework-rows"` | only with `hasframeworks`; receives the `FWK-ROW`s |
| `FWK-EMPTY` | "No structures in this context." | empty state | `frameworks.mustache:105-107` | str `central_frameworks_none` | `alert alert-info role="status"` |

## Structure card (`frameworks_row`)

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `FWK-ROW` | `[no label]` | card (wrapper) | `frameworks_row.mustache:41-45` | `data-framework="{id}"` | carries `frameworkid`, `name`, `count`, `visible`, `deletable`; the `is-hidden` class (`:41`) has been a state hook with no CSS since 2026-07-15 (the `opacity: 0.6` was removed for blocking AA; see the rules below — searching for `is-hidden` in `styles.css` returns nothing). The JS row selector is `[data-framework]` (`frameworks.js:44`) |
| `FWK-ROW-SELECT` | `[no label]` | button | `frameworks_row.mustache:46` | `data-action="select-framework"` | **the whole card is a button**: `selectFramework` marks it `.active` and publishes the footer (`frameworks.js:458-475`). The `data-action` is **decorative** — the handler matches through `closest('[data-framework]')` (`:511-514`), not through the action |
| `FWK-ROW-NAME` | name | text | `frameworks_row.mustache:49` | `shortname` | 17px/700 (`styles.css:5264-5269`) |
| `FWK-ROW-ID` | idnumber | mono chip | `frameworks_row.mustache:50` | `idnumber` | only when `idnumber` (`styles.css:5271-5279`) |
| `FWK-ROW-HIDDEN` | "Hidden" | badge | `frameworks_row.mustache:51` | `^visible` | `fa-eye-slash` + str `hidden, tool_lp` (`styles.css:5281-5292`) |
| `FWK-ROW-DESC` | `[no label]` | description | `frameworks_row.mustache:53` | `description` | only when `description`; a single line with an ellipsis and the full text in the `title` (`styles.css:5294-5302`). The server flattens it to plain text and cuts it at 300 (`helper.php:2861-2872`) |
| `FWK-ROW-COUNT` | "N competencies" / "1 competency" | pill | `frameworks_row.mustache:55-57` | `competencycount` / `competencylabel` | **only the noun** arrives finished from PHP (strs `central_frameworks_competencieslabel` + `_one`, chosen by a literal `if` at `dynamictabs/frameworks.php:105-111`); the number stays in its `<strong>`, because the pill is 15px bold blue (`styles.css:5318-5322`) + `gap: 6px` + a 13.5px grey noun (`:5304-5316`) — resolving the whole phrase would kill that contrast. Accent pill on the right |

## Structure actions — **the page's sticky footer**, not the card

Rendered by `selectFramework` through `Templates.renderForPromise('…/frameworks_footer_actions')` and
handed to `ActionFooter.show(html, dispatchFrameworksAction)` (`frameworks.js:466-474`). Only with
`canmanage` (otherwise `ActionFooter.hide()`, `:462-465`). The buttons **carry no dataset**: they act
on the module-level `activeFrameworkRow` (`frameworks.js:429-447`), which is why they work from
outside the tab's region.

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `FWK-ROW-EDIT` | Edit details | footer button | `frameworks_footer_actions.mustache:41-44` | `data-action="edit"` | `fa-pencil`; str `central_plans_editdetails` (**shared with `PLN`**, it has no string of its own); opens the form with `MOD.SCALE` embedded (`framework_dynamic_form.php:192-194`); saving → toast + `reloadPane` (`frameworks.js:177-180`) |
| `FWK-ROW-VIS` | Toggle visibility | footer button | `frameworks_footer_actions.mustache:45-48` | `data-action="visibility"` | the icon **mirrors the selected card's state** — `fa-eye`/`fa-eye-slash` decided at footer render time from the `visible` that `selectFramework` passes (`:46`, `frameworks.js:468`); WS `set_framework_visibility` → `reloadPane` (`:369-375`) |
| `FWK-ROW-DUP` | Duplicate | footer button | `frameworks_footer_actions.mustache:49-52` | `data-action="duplicate"` | `fa-copy`; **core's** WS `core_competency_duplicate_competency_framework` → `reloadPane` (`frameworks.js:384-386`) |
| `FWK-ROW-DEL` | Delete | footer button | `frameworks_footer_actions.mustache:53-56` | `data-action="delete"` | `fa-trash`; core's `delete` str. **No colour variant** — core's raw sticky-footer pattern does not use `btn-outline-danger`. Gated at **two** points, see the rules below |

## Import modal

The banner `showImportLoading` injects is the plugin's **loading treatment for a modal body**:
`alert alert-info` + `spinner-border spinner-border-sm`, prepended to the modal body
(`frameworks.js:222-236`).

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `FWK-IMP-BANNER` | "Importing…" | banner (JS) | `frameworks.js:222-236` | `data-region="import-loading"` | str `central_frameworks_importing` (`:227`); born on `SUBMIT_BUTTON_PRESSED` (`:265`) and removed by `hideImportLoading` (`:244-250`) on both validation errors (`:266-267`), because the form comes back and the banner would spin forever. Duplicate guard at `:224` |
| `FWK-IMP-DONE` | "Import complete: N competencies processed." | toast | `frameworks.js:268-274` | str `central_frameworks_import_done` | the count comes from the form's `event.detail.competencycount` (`:269`); `reloadPane` (`:270`) + success toast (`:271-273`) |

> **ARIA (measured).** The banner and the export loader share the same `makeSpinner()`
> (`frameworks.js:209-214`), which marks the spinner `aria-hidden="true"` (`:212`) and **nothing
> else** — the accessible name sits on the container: the import banner puts `role="status"` on
> itself (`:231`) and holds the text inside, which is the correct pattern from `states.html`. The
> **export loader** has no such pair: `FWK-EXP-LOADER` only hosts the `aria-hidden` spinner (`:314`),
> with no `role` and no text, so that wait is not announced.

## Export modal

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `FWK-EXP-LABEL` | "Structure to export" | label | `frameworks_export.mustache:32-34` | str `central_frameworks_export_pick` | `for="local-dimensions-export-select"` |
| `FWK-EXP-SELECT` | `[no label]` | select | `frameworks_export.mustache:35` | `data-region="export-select"` | `form-select` (never `custom-select`); born **empty** and populated client-side from the tab's `FWK-ROW`s, on `ModalEvents.shown` (`frameworks.js:345-354`) — the modal only knows what the tab has already listed |
| `FWK-EXP-DOWNLOAD` | Export | button | `frameworks_export.mustache:38-40` | `data-action="download"` | `btn-primary`; WS `local_dimensions_export_framework` (`frameworks.js:316-319`) → `Blob` + `<a download>` (`triggerDownload`, `:285-295`) |
| `FWK-EXP-LOADER` | `[no label]` | spinner slot | `frameworks_export.mustache:41` | `data-region="export-loader"` | `hidden` by default; `downloadFramework` disables the button and shows the spinner (`frameworks.js:312-314`) and **restores both in a `finally`** (`:324-328`) — the discipline `states.html` demands |

The modal hosts a **toast region of its own** (`addToastRegion(modal.getBody()[0])`, `frameworks.js:346`):
a toast fired from inside it renders **above** the dialogue (the house pattern; see `CLAUDE.md`).

## Scales shortcut in the form

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `FWK-SCALES-LINK` | "Open scales page" | link (JS) | `frameworks.js:133-161` | str `central_frameworks_openscales` (`:145`) | injected into the form's **`.modal-footer`** (`:141`), as the **first child** so the group's `margin-right: auto` pushes Save/Cancel to the right (`:159-160`), inside a `.local-dimensions-modal-footer-links` (`:147`). Fired on the `LOADED` event (`:181`); `target="_blank"` + `rel="noopener noreferrer"` (`:150-151`), `fa fa-external-link` icon, `aria-hidden` (`:154-155`). Gated by `FWK-CANSCALES` (`:138`) and idempotent (`:142`). The group's CSS is at `styles.css:5028-5033` (+ a focus ring of its own for the `btn-link` at `:5041-5045`, which Moodle 4.5's Bootstrap 4 does not draw). It is the same escape-link pattern as the participants modal — the links **moved down from the header to the footer** in D2 (comment at `styles.css:5020-5027`) |
| `FWK-SCALES-CHIP` | `[no label]` | close chip | `styles.css:5074-5104` | `.local-dimensions-central-page .modal-form-dialogue .modal-header .btn-close` | **Pure CSS, no class from JS.** A `1.75rem` chip, `#e7f0f9` background, Font Awesome's `\f00d` glyph in `#0f4d85` (`:5088-5097`), hover/focus `#d4e6fb` (`:5099-5104`). The selector's second arm matches the `.modal-form-dialogue` that core puts on the dialogue **synchronously, before `show()`** — it paints from the first frame; the `:has()` arm on its own flashed (comment at `:5063-5073`). The old `local-dimensions-headerlink-modal` class was **removed** (zero hits for `headerlink` in `amd/`, `templates/`, `classes/` and `styles.css`) |

## Business rules (verified in the code)

- **Deletion has two gates, and the second does not trust the first.** `deleteFramework` refuses
  early when `data-deletable !== '1'` (`frameworks.js:397-400`), confirms with `deleteCancelPromise`
  (`:407`) and **still** treats `success === false` from core's WS as a block (`:415-418`). The
  dataset is a snapshot of the render; between render and click the structure may have come into
  use. `deletable` comes out of `competency::can_all_be_deleted()` (`helper.php:2875`).
- **`hashiddenframeworks` is a dead key on this tab.** `dynamictabs/frameworks.php:140` exports it,
  but `frameworks.mustache` **does not use it** anywhere — the toggle's gate became "is
  `showhiddentoggle` null or not" (`:149`). The name's only other occurrences are the docblock and
  the *Example context* of `structure.mustache` (`:36`, `:55`), which has a context of its own
  (`dynamictabs/structure.php:165`) and likewise does not consume it in the body.
- **The scale-config delegation is global and set up once per page** (`setupScaleConfigDelegation`,
  `frameworks.js:95-123`), in the **capture phase** (`:107`): the form is born inside a `modalform`
  whose lifecycle does not run the tab's `init`, so the click is listened for on the document. The
  select is resolved **by `name`, not by `id`** (`:65` and `:109`): `core_form\dynamic_form` suffixes
  the ids with a random string (`id_scaleid_c5fLCIS8ExDrcVf`), so `#id_scaleid` would never match
  (comment at `:63-64`).
- **Changing the scale clears the proficiency config — except when the select is `readonly`**
  (`frameworks.js:109-113`): an already-rated framework has its scale frozen, and clearing there
  would erase a config the server is going to re-pin anyway.
- **The footer is defended against races at three points** (the same pattern as `EST`):
  `selectFramework` only shows it when the card is still `.active` **and** the tab is still the
  active one (`:470`); `dispatchFrameworksAction` ignores clicks when the tab has lost focus
  (`:432-434`); and `init` only clears the footer when the tab is the active one (`:493-495`),
  because the dynamic tabs re-run `init` from an out-of-order asynchronous load.
- **Every action reloads the pane.** This tab's **6** `reloadPane` calls (`frameworks.js:179`,
  `:270`, `:374`, `:386`, `:419`, `:522`) cover saving, importing, toggling visibility, duplicating,
  deleting and flipping the toggle. **There is no in-place path here** — unlike `EST`, which has
  four. That is why IMP-03 paid off more on this tab: every action click rebuilds the list, and now
  under a busy curtain.
- **`showhidden` has two sources, and the arg wins** (`dynamictabs/frameworks.php:88-90`): the pane's
  arg (written by the toggle) wins; without it, it falls back to the `frameworksshowhidden`
  preference. That way the choice survives a full page reload.
- **i18n · both counters inflected wrongly in the singular · FIXED on 2026-07-15.** `get_string`
  has no plural forms, so both used **one** plural key and broke at `N = 1`:
  - `FWK-HIDDENCOUNT` was pt_br `'{$a} ocultas'` → "**1 ocultas**". English never suffered
    ("hidden" is invariable).
  - `FWK-ROW-COUNT` was the bare noun pt_br `'competências'` → "**1 competências**" — and here
    **English got it wrong too** ("1 competencies"), because "competencies" is a count noun.
  Both now choose between **two literal keys** with an `if` — never a constructed key, which the
  string checker cannot validate: `central_frameworks_hiddencount` + `_one`
  (`lang/{en,pt_br}:138-139`, resolved at `dynamictabs/frameworks.php:118-127`) and
  `central_frameworks_competencieslabel` + `_one` (`lang/{en,pt_br}:127-128`, resolved at `:105-111`).
  The difference in shape between the two is **deliberate**: the suffix resolves the whole phrase
  (`hiddenlabel`), but the pill resolves **only the noun** (`competencylabel`), because the number
  has to stay inside the `<strong>` to keep `FWK-ROW-COUNT`'s 15px blue / 13.5px grey contrast.
  `excludedcount` **stopped being exported** (nothing read it any more once the `{{#str}}` left the
  template), and the key `central_frameworks_competencies` (en `'{$a} competencies'` / pt_br
  `'{$a} competências'`) was **removed** — it was dead, with no use in PHP, Mustache or JS, and it
  carried the same latent defect.
- **a11y · FIXED on 2026-07-15.** `FWK-ROW-DESC` and `FWK-HIDDENCOUNT` used `#8b939b` — **3.11:1**
  over the card's `#fff`, below the required 4.5:1, and carrying **real content** (the structure's
  description and the hidden count), not decoration. It was a one-off deviation: `#8b939b` existed
  only at those two points in the file. Both now use **`#495057`** (`styles.css:5298` and `:5169`) —
  the same grey as `FWK-COUNT` (`:5159`) — and pass on **every** real background of the card: 8.18:1
  normal, 7.69:1 on `:hover` (`#f7f8fa`, `styles.css:5217-5219`), 7.59:1 on `.active` (`#f2f7fc`,
  `:5223-5227`). `#6a737b` (the kit's `--mds-text-muted`) was discarded for failing on the
  **selected** card by 0.02 (4.48:1).
- **a11y · the hidden card's `opacity: 0.6` was REMOVED (2026-07-15).** `opacity` on a whole block
  compresses **text and background together** towards the page, so no text colour reached AA there:
  the measured ceiling was **5.74:1 with pure black**, and the "Hidden" badge sat at **2.12:1**. The
  hidden card already signalled itself explicitly — `fa-eye-slash` + the word "Hidden"
  (`frameworks_row.mustache:51`) — so the `opacity` was a **second**, redundant signal that only
  destroyed contrast. Removed; the badge went from `#6a737b` to `#495057` (4.15 → **7.03:1**; today
  at `styles.css:5287`) and now carries the state on its own. The `is-hidden` class stays in the
  template as a state hook, with no CSS rule to read it.

## IMP-03 — busy curtain in `reloadPane` (shipped, `mtube: loading`)

> **A measured correction the delivery preserved** (identical to the one in `est-competencies.md`,
> re-verified here independently). The plan described IMP-03 as "loading on tab switch". Tab
> switching already had loading, and it comes from core (`lib/amd/src/dynamic_tabs.js` listens for
> `shown.bs.tab` → `loadTab`, which opens with `addIconToContainer`, and the preceding `show.bs.tab`
> empties the pane). The gap was the **plugin's `reloadPane`**, which redid the same path **without**
> a wait indication — and it is the one that runs at **24** call sites across 5 modules
> (`structure` 9, `frameworks` 6, `plans` 6, `context` 2, `competency_browser` 1). One change in
> `reloadPane` and all 24 sites gained together.

This tab is the **best case** of the change: its 6 calls are 100% of the tab's CRUD and **none** of
them is in-place, so there is none of the `refreshNode` caveat that constrains `EST`. The house rule
still holds: **pane reloaded → curtain; row swapped → flash.**

**What shipped** (full detail in `est-competencies.md`): `tabs.js:77-80` puts `LOADING_CLASS` (`:44`) +
`aria-busy="true"` on the pane and the `finally` (`:103-106`) takes them off; the design is a
**whole-pane curtain** at `styles.css:4028-4068` — a `rgba(255, 255, 255, 0.55)` veil (`:4033`) over
the old content plus a `2rem` ring in `::after` (`:4044`) with a keyframe of its own (`:4059`). It is
**not** the form this map prescribed (`FWK-IMP-BANNER`'s `alert alert-info` + `spinner-border-sm`);
that remains the form for loading **in a modal body**, and the two coexist. The `{quiet: true}`
opt-out (`tabs.js:66`) exists but **no** call on this tab uses it — the hub's only caller is
`reloadKeepingScroll` on the Plans tab (`plans.js:102`).

## IMP-05 — refresh on the contextbar (shipped, `mtube: refresh`)

See `bar-contextbar.md` (the decision, the `BAR-REFRESH` button and the verifications live there).
A precision this map confirms independently: it is **not** true that "nothing exposes `reloadPane`" —
it has **24** call sites across 5 modules. What is true is that **a single UI control** fires it (the
bar's `refresh`, `context.js:217`); the other 23 are automatic refreshes. Here on the Structures tab
they are the 6 in `frameworks.js` (`:179`, `:270`, `:374`, `:386`, `:419`, `:522`).

## IMP-10 — tab icons + indicator (shipped, `mtube: tab icons`)

See `hierarchy-nav.html` section 3. What this tab confirms: `central.php:108-112` declares the glyph
per tab (`FWK` gets `fa-sitemap`), `:122` assembles the decorative `<i>` and `:125` concatenates it
onto the label in `displayname` — `core/dynamic_tabs.mustache` triple-stashes `displayname`, so the
icon enters through the label **without** changing a core template (comment at `central.php:104-107`).
The indicator lives at `styles.css:7232-7271`, scoped by the `local-dimensions-central-page` that
`central.php:57` puts on the `<body>` so it does not leak to other `dynamic_tabs` consumers on the
site. Since `FWK` is the tab that is **born active** (`central.php:115`), it is the one that shows
the indicator on the first paint.
