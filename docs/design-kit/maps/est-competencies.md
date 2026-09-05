# Field map — `EST` · Competencies (as-is)

Master-detail: a subheader (framework select + counter + a level-sensitive "Add competency")
over two panels. The left panel is a white card with a **toolbar** (expand/collapse + gear),
**display options** in a collapsible panel, **search** and the lazy tree. A **resizer** resizes
the panels. The right panel wears a gradient header (title + taxonomy + chips) over a white body
holding the three metric cards, the description and the referenced competencies; it is
`position: sticky`.

**The node's CRUD does not live in the pane** — it lives in the page's sticky footer, injected by
`selectRow` (`structure.js:550-557`) and routed back by `dispatchStructureAction`
(`structure.js:1298-1305`).

- **Mustache:** [`templates/central/structure.mustache`](../../../templates/central/structure.mustache), [`structure_node.mustache`](../../../templates/central/structure_node.mustache), [`structure_detail_content.mustache`](../../../templates/central/structure_detail_content.mustache), [`structure_footer_actions.mustache`](../../../templates/central/structure_footer_actions.mustache), [`showhidden_toggle.mustache`](../../../templates/central/showhidden_toggle.mustache)
- **PHP:** [`classes/output/dynamictabs/structure.php`](../../../classes/output/dynamictabs/structure.php)
- **AMD:** [`amd/src/central/structure.js`](../../../amd/src/central/structure.js) (1501 lines), [`central/tabs.js`](../../../amd/src/central/tabs.js), [`central/action_footer.js`](../../../amd/src/central/action_footer.js)
- **Component in the DS:** `master-detail.html` — the tab's detail has the rich chips the component
  draws (`structure_detail_content.mustache:51-71`).

> **Anchor note.** Every `file:line` ref in this map was re-derived against `d0adc3b`.
> Reference sizes at the moment of derivation: `structure.mustache` 233 lines,
> `structure_node.mustache` 120, `structure_detail_content.mustache` 126,
> `structure_footer_actions.mustache` 71, `structure.js` 1501.

## Root and page data

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `EST-ROOT` | `[no label]` | region/root | `structure.mustache:92-94` | `data-region="structure"` | carries `contexttype`, `categoryid`, `frameworkid`, `canmanage`; `init` reads all of it from here (`structure.js:1332-1340`) |
| `EST-JSON-RULES` | `[no label]` | JSON data | `structure.mustache:95` | `data-region="rules-modules"` | read by `readJson` (`structure.js:124-134`, called at `:1354`); feeds `MOD.RULE` |
| `EST-JSON-COURSEOUT` | `[no label]` | JSON data | `structure.mustache:96` | `data-region="course-outcomes"` | course outcomes → `MOD.LINKS` (`structure.js:1355`) |
| `EST-JSON-MODOUT` | `[no label]` | JSON data | `structure.mustache:97` | `data-region="module-outcomes"` | module outcomes → `MOD.LINKS` (`structure.js:1356`) |

## Subheader and select

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `EST-EMPTY-CAT` | "Choose the course category first…" | empty state | `structure.mustache:100` | str `managecompetencies_selectcategory_help` | blocks the whole tab until a category is chosen (the `{{^needscategoryselection}}` at `:103` wraps everything else) |
| `EST-SHOWHIDDEN` | Show hidden structures | switch | `showhidden_toggle.mustache:44-45`, called from `structure.mustache:105-107` | `data-action="{{action}}"` → `toggle-hidden` | **shared partial** with `FWK`/`PLN`: the `data-action` is a **variable** in the template and the literal value comes from `dynamictabs/structure.php:169` (context at `:166-171`; null → does not render), label str `managecompetencies_showhiddenframeworks`. State in a preference (`Preferences.saveDisplay`, `structure.js:1492`), **not** on the server |
| `EST-FW-LABEL` | Structure | label | `structure.mustache:112-114` | str `central_browseframeworks_framework` | `for="local-dimensions-central-framework"` |
| `EST-FW-SELECT` | Structure (select) | select | `structure.mustache:115` | `data-region="framework-select"` | `form-select`; `change` → writes `pane.dataset.frameworkid` + `reloadPane` (`structure.js:1465-1474`) |
| `EST-FW-OPTION` | `name · idnumber · hidden` | option (loop) | `structure.mustache:117` | `frameworks` | `data-hidden="1"` on the hidden ones; the options are snapshotted in `init` (`structure.js:1478-1485`) for the client-side filter |
| `EST-FW-COUNT` | "items: N" | counter | `structure.mustache:121-123` | `competencycount` | str `managecompetencies_items`; **it counts the selected framework** — the 3rd of the hub's three counters (see `bar-contextbar.md`) |
| `EST-ADDROOT` | Add competency | button | `structure.mustache:127-129` | `data-action="add"` | only with `canmanage`. **Level-sensitive:** with no selection it creates a root, with an active node it creates a child of it (`structure.js:1425-1429`) |
| `EST-ADDHINT` | "New top-level competency" / "New child of X" | hint | `structure.mustache:130-132` | `data-region="add-hint"` | str `managecompetencies_addhint_root`; rewritten by `selectRow` to `..._addhint_child` with the node's name (`structure.js:536-545`) |

## Toolbar and display options (left panel)

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `EST-TREE-PANE` | `[no label]` | panel/card | `structure.mustache:139` | `data-region="tree-pane"` | wrapper; width controlled by `EST-RESIZER` |
| `EST-TOOL-EXPAND` | Expand all | button | `structure.mustache:142-144` | `data-action="expand-all"` | `expandAll` (`structure.js:616-630`); **ceiling of 200 nodes** (`EXPAND_CAP`, `:110`), announced by toast (`:626`) |
| `EST-TOOL-COLLAPSE` | Collapse all | button | `structure.mustache:145-147` | `data-action="collapse-all"` | `collapseAll` (`structure.js:637-650`); pure DOM, no network |
| `EST-TOOL-GEAR` | `[title/sr only]` | icon button | `structure.mustache:148-153` | `data-action="display-options"` | `fa-cog`; toggles `EST-DISP-PANEL` and persists in `Preferences.saveDisplay({panels:{structure}})` (`structure.js:1415-1424`, write at `:1421`) |
| `EST-DISP-PANEL` | Display options | group | `structure.mustache:155-156` | `data-region="display-options-panel"` | `role="group"`; state restored by `applyPanelState` (`structure.js:320-329`) |
| `EST-DISP-TAX` | Show taxonomy | switch | `structure.mustache:159` | `data-display-toggle="tax"` | turns on the `show-tax` class on `EST-TREE` (`DISPLAY_CLASSES`, `structure.js:114`); **off** by default |
| `EST-DISP-ID` | Show identifiers | switch | `structure.mustache:164` | `data-display-toggle="id"` | `show-id` class; **off** by default |
| `EST-DISP-RULE` | Show competency rule | switch | `structure.mustache:169` | `data-display-toggle="rule"` | `show-rule` class; **on** by default (the `checked` in the template and the class already on `EST-TREE`, `:184`) |

## Search (left panel)

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `EST-SEARCH` | Search by name or ID number | search input | `structure.mustache:179-180` | `data-region="structure-search"` | `visually-hidden` label (`:176-178`); **minimum 2 characters**, **250ms** debounce (`structure.js:1450-1463`) |
| `EST-SEARCH-RESULTS` | `[no label]` | JS container | `structure.mustache:182` | `data-region="search-results"` | `hidden` by default; `renderSearchResults` (`structure.js:337-372`); clicking a result calls `revealNode`, which expands the path down to the node (`structure.js:441-466`, ceiling `REVEAL_CAP`=100, `:390`) and flashes it (`:465`) |

## Tree (left panel)

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `EST-TREE` | `[no label]` | container | `structure.mustache:184` | `data-region="competency-tree"` | receives the `EST-NODE`s; born with the `show-rule` class |
| `EST-TREE-LOADMORE` | Load more | button | `structure.mustache:190-194` | `data-region="root-loadmore"` | only when `hasmoreroots`; `data-offset`/`data-total`; page of **25** (`PAGE_SIZE`, `structure.js:49`) |
| `EST-TREE-LOADMORE-HINT` | "Showing N of M" | hint | `structure.mustache:195` | `rootloadmorehint` | str `central_structure_loadmoreshown`, assembled in `dynamictabs/structure.php:145-148` |
| `EST-RESIZER` | Resize panels | separator | `structure.mustache:202-206` | `data-region="structure-resizer"` | `role="separator"`, `tabindex="0"`; `initStructureResize` (`structure.js:1313-1326`) delegates to the shared `initMasterResizer` — drag, dblclick resets, arrow keys resize, persisted in `localStorage` (key `local_dimensions_structure_master_width`, minimum width 320 / maximum 1200) |

### Tree node (`structure_node`)

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `EST-NODE` | `[no label]` | row (wrapper) | `structure_node.mustache:61` | `data-node="{id}"` | server-rendered for the roots, client-rendered on expand |
| `EST-NODE-TOGGLE` | `[aria-label only]` | chevron button | `structure_node.mustache:65-68` | `data-action="toggle"` | only when `haschildren`; `fa-chevron-right`; `aria-expanded`; `toggleNode` fetches the children on the 1st open (`structure.js:571-607`) |
| `EST-NODE-ICON` | `[no label]` | bullet | `structure_node.mustache:70-72` | — | **leaves only**: a `•` in place of the chevron; the icon of a node with children is `EST-NODE-TOGGLE`'s chevron |
| `EST-NODE-ROW` | competency name | button | `structure_node.mustache:73-95` | `data-action="select"` | carries **20** payload `data-*` beyond the `data-action` (id, parentid, name, idnumber, taxonomy, scale, description, type, tag1, tag2, bgcolor, textcolor, courses, activities, templates, haschildren, ruletype, ruleoutcome, ruleconfig, rulelabel) — the detail is assembled from **those alone**, with no round-trip |
| `EST-NODE-NAME` | name | text | `structure_node.mustache:96` | `shortname` | — |
| `EST-NODE-TAX` | taxonomy | badge | `structure_node.mustache:97` | `taxonomy` | visible only with `show-tax` (`EST-DISP-TAX`) |
| `EST-NODE-ID` | idnumber | badge | `structure_node.mustache:99` | `idnumber` | only when `idnumber`; visible only with `show-id` |
| `EST-NODE-RULE` | rule label | badge | `structure_node.mustache:103` | `rulelabel` | **double gate**: only when `haschildren` **and** `ruletype` (`:101-105`) — a leaf cannot have a rule, so it does not display "none" |
| `EST-NODE-DRAG` | "Move to position…: {name}" | icon button | `structure_node.mustache:111-116` | `data-region="node-drag-handle"` | `canmanage` only. It sits **after** the row in the DOM and is pulled left with `order:-1` in the CSS: the `aria-label` embeds the name and Behat clicks the first hit in document order (comment in the template, `:109-110`). **It carries two jobs:** dragging reorders directly; **clicking opens [`MOD.MOVETO`](mod-moveto.md)** — and not through `data-action` (it has none), but through a branch of its own in the region's listener (`structure.js:1374-1382`, with `preventDefault()` so the click does not select the row) |
| `EST-NODE-CHILDREN` | `[no label]` | JS container | `structure_node.mustache:119` | `data-children="{id}"` | `data-offset="0"`, `hidden`; `loadChildPage` pages by 25 (`structure.js:228-247`) |

## Detail (right panel) — `structure_detail_content`

A partial **shared** between the inline pane and the referenced-competency modal. Two flags adjust
it: `linksclickable` (the metrics become buttons that open the usage modal — inline) and
`showrelated` (the referenced section — inline); both come from `dynamictabs/structure.php:162` for
the pane. Every value is born empty and filled client-side.

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `EST-DETAIL-PANE` | `[no label]` | panel | `structure.mustache:208` | `data-region="detail-pane"` | `position: sticky` — it stays in view while the tree scrolls |
| `EST-DETAIL-EMPTY` | "Select a row in the tree or table…" | empty state | `structure.mustache:210-213` | `data-region="detail-empty"` | visible until the 1st selection; `selectRow` hides it (`structure.js:529`) |
| `EST-DETAIL-CONTENT` | `[no label]` | container | `structure.mustache:214` | `data-region="detail-content"` | `hidden` until something is selected; hosts the partial |
| `EST-DETAIL-TITLE` | `[no label]` | `h2` | `structure_detail_content.mustache:48` | `data-region="detail-title"` | comes from `EST-NODE-ROW`'s `data-name` |
| `EST-DETAIL-TAXONOMY` | `[no label]` | badge | `structure_detail_content.mustache:49` | `data-region="detail-taxonomy"` | next to the title, in the gradient header |
| `EST-DETAIL-RULECHIP` | `[no label]` | accent chip | `structure_detail_content.mustache:52-54` | `data-region="detail-rule-wrap"` | `hidden` until there is a rule; `fa-check` |
| `EST-DETAIL-LABEL` | `[no label]` | glass chip | `structure_detail_content.mustache:55-57` | `data-region="detail-label-wrap"` | `fa-tag`; comes from `data-type` |
| `EST-DETAIL-IDNUMBER` | "ID number:" | glass chip | `structure_detail_content.mustache:58-61` | `data-region="detail-idnumber-wrap"` | prefix str `idnumber`; value in `font-monospace` (`data-region="detail-idnumber"`) |
| `EST-DETAIL-SCALE` | `[no label]` | glass chip | `structure_detail_content.mustache:62-64` | `data-region="detail-scale-wrap"` | the competency's scale |
| `EST-DETAIL-TAG1` | `[no label]` | glass chip | `structure_detail_content.mustache:65-67` | `data-region="detail-tag1-wrap"` | custom field |
| `EST-DETAIL-TAG2` | `[no label]` | glass chip | `structure_detail_content.mustache:68-70` | `data-region="detail-tag2-wrap"` | custom field |
| `EST-DETAIL-COURSES` | Linked courses | metric card | `structure_detail_content.mustache:79-82` (button) · `:85` (text) | `data-action="show-usage" data-usage="courses"` | with `linksclickable` it is a button → `openUsageModal` (`structure.js:1208-1233`, fired at `:1252`); without it, inert text (avoids a modal over a modal) |
| `EST-DETAIL-ACTIVITIES` | Linked activities | metric card | `structure_detail_content.mustache:91-94` (button) · `:97` (text) | `data-usage="activities"` | the same |
| `EST-DETAIL-PLANS` | Linked learning plans | metric card | `structure_detail_content.mustache:103-106` (button) · `:109` (text) | `data-usage="templates"` | the same; str `central_structure_linkedplans` |
| `EST-DETAIL-DESC` | `[no label]` | description | `structure_detail_content.mustache:113-114` | `data-region="detail-description-wrap"` | `hidden` when empty |

### Referenced competencies (inline only, `showrelated`)

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `EST-REFS-PANEL` | Referenced competencies | section | `structure_detail_content.mustache:117` | `data-region="detail-related"` | `hidden` until there are referenced ones; `populateRelated` (`structure.js:478-504`) |
| `EST-REFS-COUNT` | `[no label]` | counter | `structure_detail_content.mustache:121` | `data-region="detail-related-count"` | born at `0` |
| `EST-REFS-LIST` | `[no label]` | JS container | `structure_detail_content.mustache:123` | `data-region="detail-related-list"` | `data-action="open-related"` chips → open the referenced competency in a modal (`structure.js:1245-1249`) |

## Node actions — **the page's sticky footer**, not the pane

Rendered by `selectRow` through `Templates.renderForPromise('…/structure_footer_actions')` and
handed to `ActionFooter.show(html, dispatchStructureAction)` (`structure.js:550-557`). Only with
`canmanage`. The buttons **carry no dataset**: they act on the module-level `activeRow`
(`structure.js:1298-1305` → `handleDetailAction`, `:1244-1287`), which is why they work from
outside the tab's region.

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `EST-DETAIL-EDIT` | Edit details | footer button | `structure_footer_actions.mustache:41-44` | `data-action="edit"` | `fa-pencil`; opens `openForm` (`structure.js:797-812`); on save it calls `refreshNode` — an **in-place** update (`structure.js:806`) |
| `EST-DETAIL-ADDCHILD` | Add child | footer button | `structure_footer_actions.mustache:45-48` | `data-action="addchild"` | `fa-plus`; creating reloads the pane (`structure.js:808`) |
| `EST-DETAIL-RULES` | Competency rule | footer button | `structure_footer_actions.mustache:49-52` | `data-action="rules"` | `fa-list`; str `competencyrule, tool_lp`; opens `MOD.RULE`; saving writes **in-place** + flash (`persistRule`, `structure.js:848-880`, flash at `:876`) |
| `EST-DETAIL-LINKS` | Courses & activities | footer button | `structure_footer_actions.mustache:53-56` | `data-action="links"` | `fa-link`; opens `MOD.LINKS`; on close it updates the count **in-place** (`updateCourseCount`, `structure.js:909-921`, wired at `:1271`) |
| `EST-DETAIL-RELATED` | Related competencies | footer button | `structure_footer_actions.mustache:57-60` | `data-action="related"` | `fa-exchange`; opens `MOD.RELATED` (`structure.js:1273-1278`) |
| `EST-DETAIL-MOVETO` | Move to position… | footer button | `structure_footer_actions.mustache:61-64` | `data-action="moveto"` | `fa-arrows-up-down-left-right`; opens [`MOD.MOVETO`](mod-moveto.md) (`openNodeMoveModal`, `structure.js:973-1008`). **Replaces** `EST-DETAIL-MOVEUP`/`-MOVEDOWN`. **It is not the only door** — `EST-NODE-DRAG` opens the same modal on click |
| `EST-DETAIL-DELETE` | Delete | footer button | `structure_footer_actions.mustache:65-68` | `data-action="delete"` | `fa-trash`; `confirmDelete` (`structure.js:820-839`) → reloads the pane (`:836`). **No colour variant** — core's raw sticky-footer pattern does not use `btn-outline-danger` |

## Retired IDs

> Do not reuse. A dangling ID is worse than a recorded retirement.

| ID | Status | Replacement | Note |
| --- | --- | --- | --- |
| `EST-DETAIL-MOVEUP` | **Retired** (2026-07-14) | `EST-DETAIL-MOVETO` → [`MOD.MOVETO`](mod-moveto.md) | It was `data-action="moveup"`, an icon button that reordered among siblings. It exists in no template and not in `structure.js` |
| `EST-DETAIL-MOVEDOWN` | **Retired** (2026-07-14) | `EST-DETAIL-MOVETO` → [`MOD.MOVETO`](mod-moveto.md) | the same for `movedown`. The two arrows became **one** button that opens the move modal, and direct dragging (`EST-NODE-DRAG`) covers the quick case |

## Empty states

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `EST-EMPTY-COMP` | "No competencies in this structure" | empty state | `structure.mustache:225` | str `nocompetencies, local_dimensions` | framework with no competencies; it replaces the whole master-detail |
| `EST-EMPTY-FW` | "No competency structures found" | empty state | `structure.mustache:230` | str `noframeworks, local_dimensions` | context with no frameworks |

## Business rules (verified in the code)

- **The detail makes no round-trip.** `selectRow` (`structure.js:512-562`) assembles the whole panel
  from `EST-NODE-ROW`'s `data-*` (`renderDetailInto`, `:533`). The selection's only network call is
  `populateRelated` (`:534`).
- **Four deliberate in-place paths**, all of which exist so that neither expansion nor selection is
  lost: `refreshNode` (editing, `:726-786`), `persistRule` (rule, `:848-880`), `updateCourseCount`
  (links, `:909-921`) and `applyShowHidden` (the dropdown's filter, `:272-291`). The first three
  confirm themselves with a **flash** (`flashRow`, `--mds-motion-flash` read at `flash.js:43`
  through the WAAPI: `structure.js:876`, `:920`, plus `persistNodeMove` at `:954`);
  `applyShowHidden` does not flash because it swaps no row at all — it rebuilds `EST-FW-SELECT`'s
  `<option>`s from the snapshot taken in `init`, precisely so as **not** to reload the pane and
  avoid flashing the toggles (comment at `:1476-1477`).
- **`refreshNode` degrades to `reloadPane` at four points** (`:730`, `:739`, `:748`, `:764`): the
  node vanished from the DOM, the WS did not find the competency, the node was **reparented** by the
  edit (its position in the tree changed and an in-place row swap does not represent that, so it
  reloads and calls `revealNode`, `:749-752`) or the re-rendered row did not bring back the expected
  markup.
- **`reloadPane` is core's `loadTab` minus its icon, plus a curtain of its own.** The same
  `getContent` → `renderForPromise` → `replaceNodeContents` path (`tabs.js:82-92`); instead of
  core's `addIconToContainer`, the plugin's adds `LOADING_CLASS` + `aria-busy="true"`
  (`tabs.js:44`, `:77-80`) and restores focus inside the pane (`:93-99`). Generation guard by
  `Symbol` (`tabs.js:37`, `:74-75`, `:83-91`) so that a slow reload cannot overwrite newer content.
  See IMP-03 below.
- **The footer is defended against races at three points**: `selectRow` only shows it when the row
  is still active **and** the tab is still the active one (`:556`); `dispatchStructureAction`
  ignores clicks when the tab has lost focus (`:1301-1303`); and `init` only clears the footer when
  the tab is the active one (`:1344-1346`), because the dynamic tabs re-run `init` from an
  out-of-order asynchronous load.
- **Clicking the row of a node with children also expands/collapses it** — not just the chevron
  (`structure.js:1398-1404`).
- **Preferences**: display (`tax`/`id`/`rule`) goes through `writeDisplayPrefs` (`:261-263`), the
  gear's panel at `:1421` and `showhidden` at `:1492`, all through `Preferences.saveDisplay`; the
  chosen framework goes to `Preferences.saveNav` (`:1471`). The panel width goes to `localStorage`
  (`:1313-1326`).
- **Ceilings**: page of 25 (`PAGE_SIZE`, `:49`), expand-all stops at 200 nodes (`EXPAND_CAP`,
  `:110`), reveal-path stops at 100 (`REVEAL_CAP`, `:390`).

## IMP-03 — busy curtain in `reloadPane` (shipped, `mtube: loading`)

> **A measured correction the delivery preserved.** The plan described IMP-03 as "loading on tab
> switch". Tab switching already had loading, and it comes from core (`lib/amd/src/dynamic_tabs.js`
> listens for `shown.bs.tab` → `loadTab`, which opens with `addIconToContainer`, and the preceding
> `show.bs.tab` empties the pane). The gap was the **plugin's `reloadPane`**, which redid `loadTab`'s
> path **without** any wait indication — and it is the one that runs at **24** call sites across 5
> modules. One change in `reloadPane` and all 24 sites gained together.

**What shipped, verified:**

- `tabs.js:77-80` adds `LOADING_CLASS` (`local-dimensions-central-tab-loading`, `:44`) and
  `aria-busy="true"` on the pane; the `finally` (`:103-106`) removes both — and only the generation
  that still owns the pane removes them, so that a superseded reload cannot switch off the newer
  one's curtain.
- CSS at `styles.css:4334-4381`: `min-height: var(--mds-loading-min-height)` (token at
  `styles.css:22`) so the pane does not collapse, a `::before` veil `rgba(255, 255, 255, 0.55)`
  (`:4033`) and a `2rem` `::after` ring (`:4044`) with a keyframe of its own,
  `local-dimensions-central-spin` (`:4059`, 750ms), and `prefers-reduced-motion` taking it to
  1500ms (`:4065-4068`).
- **The visual form diverged from what this map prescribed.** It is neither the `alert alert-info` +
  `spinner-border spinner-border-sm` of `FWK-IMP-BANNER` nor core's `addIconToContainer`: it is a
  **whole-pane curtain** (a translucent veil + a ring drawn in a pseudo-element) that keeps the old
  content visible beneath it. The keyframe is its own so as not to depend on Bootstrap's
  `spinner-border` being present (comment at `styles.css:4302-4316`).
- **Of the ARIA quartet `states.html` asks for, only `aria-busy` shipped.** `role="status"` +
  `aria-live="polite"` + an accessible name + focus movement did **not** enter this curtain — by
  design, they belong to the first-paint placeholder (IMP-11), which covers the absence of content,
  and not to a curtain over existing content.
- **The reservation about the in-place paths shipped as an explicit `{quiet: true}` option**
  (`tabs.js:66`, `:69`, `:77`, `:103`). The caveat still holds: do **not** apply the curtain in
  `refreshNode` — that is a **deliberate** in-place path (`structure.js:726-786`) which swaps the
  row and preserves expansion + selection; a whole-pane curtain there would visually destroy
  exactly the state the function exists to preserve. In practice the four in-place paths **never
  called** `reloadPane`, so they were never at risk: the only caller of `{quiet: true}` is
  `reloadKeepingScroll` (`plans.js:94-109`, called at `:103`), which preserves the scroll position.
  House rule: **pane reloaded → curtain; row swapped → flash.**

## IMP-05 — refresh on the contextbar (shipped, `mtube: refresh`)

See `bar-contextbar.md` (the decision, the `BAR-REFRESH` button and the verifications live there).
A precision this map confirms independently: `reloadPane` has **24** call sites across 5 modules —
`structure` 9, `frameworks` 6, `plans` 6, `context` 2, `competency_browser` 1. It is **not** true
that "nothing exposes `reloadPane`"; what is true is that **a single UI control** fires it (the
bar's `refresh`, `context.js:217`) — the other 23 are automatic refreshes. Here on the Competencies tab
they are the 9 in `structure.js` (`:730`, `:739`, `:748`, `:764`, `:808`, `:836`, `:960`, `:1036`,
`:1472`).

## IMP-10 — tab icons + indicator (shipped, `mtube: tab icons`)

See `hierarchy-nav.html` section 3. What the Competencies tab confirms in the code:
`central.php:108-112` declares one glyph per tab (`fa-sitemap` / `fa-crosshairs` /
`fa-graduation-cap`), `:122` assembles the `<i class="fa … me-1" aria-hidden="true">` and `:125`
concatenates it onto the label in `displayname` — `core/dynamic_tabs.mustache` triple-stashes
`displayname`, so the icon enters through the label **without** changing a core template (comment at
`central.php:104-107`). The indicator lives at `styles.css:7583-7631`, scoped by the
`local-dimensions-central-page` that `central.php:57` puts on the `<body>` so it does not leak to
other `dynamic_tabs` consumers on the site: `box-shadow: inset 0 -2px 0 transparent` at the base
(`:7239-7245`), accent on `.active` (`:7261-7265`), the active tab's text in Boost's dark grey
(`#1d2125`) and **not** in blue, `:focus-visible` with `outline: 2px solid` (`:7255-7259`) to
restore the focus ring that `border: 0` knocked out, and `prefers-reduced-motion` zeroing the
transition (`:7653-7657`). Mtube's `ResizeObserver`-driven overflow dropdown was **not** ported
(zero hits for `ResizeObserver` in `amd/src/central/`) — it was 146 lines of measurement for three
short labels (`central.php:99-103`) that never overflow.
