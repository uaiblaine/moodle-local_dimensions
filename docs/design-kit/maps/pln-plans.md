# Field map — `PLN` · Learning plans tab (as-is)

Master-detail: a white card on the left (options gear, client-side search, "New template",
multi-competency filter and the template rows) and, on the right, the selected template's panel —
**a gradient header that wears the template's own colours**, a status badge, three count pills, a
second gear, metadata chips and the competency list (grip, name, taxonomy, path, structure badge,
**kebab**). A 22px divider resizes the master.

**The template CRUD does not live in the pane** — it lives in the page's sticky footer, published by
`init` (`plans.js:785-793`) and routed back by `dispatchPlansAction` (`plans.js:755-765`). **The
competency actions inside the list, by contrast, live in a per-row kebab** (`plans.mustache:396-436`)
— and that is correct; see the boundary recorded below.

- **Mustache:** [`templates/central/plans.mustache`](../../../templates/central/plans.mustache) (494 lines), [`showhidden_toggle.mustache`](../../../templates/central/showhidden_toggle.mustache), [`collapsible_description.mustache`](../../../templates/collapsible_description.mustache), [`move_competency_modal.mustache`](../../../templates/central/move_competency_modal.mustache), [`delete_template_modal.mustache`](../../../templates/delete_template_modal.mustache)
- **PHP:** [`classes/output/dynamictabs/plans.php`](../../../classes/output/dynamictabs/plans.php) (336 lines)
- **AMD:** [`amd/src/central/plans.js`](../../../amd/src/central/plans.js) (860 lines), [`central/tabs.js`](../../../amd/src/central/tabs.js), [`central/action_footer.js`](../../../amd/src/central/action_footer.js), [`central/pane_resizer.js`](../../../amd/src/central/pane_resizer.js), [`central/preferences.js`](../../../amd/src/central/preferences.js), [`central/flash.js`](../../../amd/src/central/flash.js)

> **Naming note (verified).** This tab's label is the string `learningplans` = **"Learning plans"**
> (`central.php:102`; `lang/en:447`) — it does **not** follow the `central_<x>_tab` convention that
> `FWK` uses. It is the **third** tab and it **never starts active** (`central.php:115` only marks
> `frameworks`), so the `PLN` pane is **never server-rendered on page load**: it always arrives
> through core's `loadTab`. That matters for the busy blanket — see below.

> **Provenance.** The tab's current shape came out of three reworks: `4c1f521` (2026-07-01, the
> per-row kebab and the "Plans {N}" pill), `9e1a2cc` (2026-07-08, the template actions move to the
> sticky footer) and `64337c8` (2026-07-09, pixel-perfect redesign: the kebab class, the gradient
> header, the two gears, the chips).

## The boundary of the sticky-footer rule (settled 2026-07-14)

> The "never a per-row kebab" rule governs the **CRUD of the tab's own entity** (framework /
> structure node / template) — which goes to the sticky footer because that is what launches the
> modals. The actions on **competencies inside a plan's list** are a **nested list** and legitimately
> use a kebab (`plans.mustache:396-436`): this tab's sticky footer is already occupied by the
> selected template's actions. **This kebab is correct — do not "fix" it.**

What holds the boundary up, verified in the code:

- **The two lists belong to different entities.** The sticky footer acts on the selected **template**
  (`plans.mustache:462-488`, five buttons); the kebab acts on a **competency inside** that template
  (`plans.mustache:396-436`, five items). There is no second footer available — the page's own is
  already occupied.
- **The strongest proof is the shared `openForm`.** The kebab's `edit-competency`
  (`plans.mustache:405-408`) and the footer's `edit-template` (`plans.mustache:465-468`) call **the
  same function**, `openForm` (`plans.js:200-208`, whose `new ModalForm` sits at `:201`) — which the
  header's `new-template` (`plans.mustache:182-184`) calls too. All **three** coexist because
  `openForm` is parameterised by `formclass`: `edit-competency` passes `COMPETENCY_FORM_CLASS`
  (`plans.js:728-734`), while `edit-template` and `new-template` pass `FORM_CLASS` (`:721-727`,
  `:714-720`). **Different entities, same mechanism** — which is exactly why a kebab and a footer can
  coexist without ambiguity.
- **The sticky footer is still the hub's dominant pattern, and these are the numbers** (measured, not
  estimated). The hub has **17** modal construction sites — **4** `new ModalForm` + **13**
  `Modal*.create`, all under `amd/src/central/` (the **four** `Modal.create` calls in
  `amd/src/accordion.js` — `:1378`, `:1476`, `:2526`, `:3470` — belong to the learner view,
  **outside** the hub). Of those 17, the footer **reaches 10** directly; it is the **only door** to
  **7**; and **8 depend** on it (the 7 plus `enrol_methods.js:859`, which is only mounted from inside
  the participants modal — `participants_manager.js:33`, its sole importer).
  - **The 7 with no other door:** `rule_config.js:144` (`rules`), `competency_links.js:862` (`links`),
    `related_competencies.js:239` (`related`), `structure.js:988` (`moveto`) — the four exist only in
    `structure_footer_actions.mustache:49`, `:53`, `:57`, `:61` — plus
    `participants_manager.js:171` (`manage-participants`), `competency_browser.js:106`
    (`browse-frameworks`) and `plans.js:240` (`delete-template` with plans), the three only in
    `plans.mustache:469`, `:473` and `:481`.
  - **The other 3 have a parallel door in the header:** the framework form (`frameworks.js:172` ←
    `frameworks.mustache:82`), the competency form (`structure.js:798` ← `structure.mustache:127`)
    and the template form (`plans.js:201` ← `plans.mustache:182`). **But the footer button is the
    only way to act on the selected row** — the header one only creates.
  - **Do not inflate:** it is **7** with no other door, not 10. The 7 the footer does **not** reach
    directly are `frameworks.js:260` (import), `frameworks.js:343` (export), `plans.js:557` (move to
    position), `competency_detail.js:277` (detail), `structure.js:1225` (usage),
    `framework_scaleconfig.js:139` (delegated from inside the form) and `enrol_methods.js:859`.

## Root and page data

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `PLN-ROOT` | `[no label]` | region/root | `plans.mustache:127-133` | `data-region="plans"` | carries **12** attributes: `contexttype`, `categoryid`, `contextid`, `templateid`, `templatename`, `competencyids`, `excludeids`, `canassignroles`, `cancohortpage`, `canuserpage`, `canmanageenrol`, `canenrolpage`. `init` resolves it by selector (`plans.js:771`) and keeps it in `activeRegion`/`activePane` (`:776-777`) |
| `PLN-MIRROR-TPL` | `[no label]` | dataset mirror | `plans.js:802-804` | `pane.dataset.templateid` | the server **auto-selects** a template (`dynamictabs/plans.php:143-156`); `init` mirrors the id onto the **pane**'s dataset or the web services receive 0 → "Invalid context id" (the *dataset-as-truth* pattern from `CLAUDE.md`) |
| `PLN-MIRROR-FILTER` | `[no label]` | dataset mirror | `plans.js:808-810` | `pane.dataset.competencyids` | mirrors the filter **already validated by the server**, so unreadable or deleted competencies that the server discarded are not left dangling in the dataset |

## Header and toggle

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `PLN-EMPTY-CAT` | "Choose the course category first…" | empty-state | `plans.mustache:134-136` | `needscategoryselection` | `alert-info` with `role="status"` (`:135`); blocks the whole tab (the `{{^needscategoryselection}}` at `:138` wraps everything else) |
| `PLN-SHOWDISABLED` | Show hidden templates | switch | `showhidden_toggle.mustache:44-45`, called at `plans.mustache:139-141` | `data-action="{{action}}"` → `toggle-disabled` | **shared partial** with `EST`/`FWK`: the `data-action` is **variable** in the template and the literal value comes from `dynamictabs/plans.php:289` (context at `:286-291`; **null when nothing is hidden** → does not render). State lives in the `plansshowdisabled` preference (`plans.js:181-186`), **not** on the server — the hidden rows stay in the DOM and the toggle only switches the `show-disabled` class (`:183`, `:186`) |

## Master panel — header and display options (gear 1 of 2)

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `PLN-LIST-TITLE` | "Templates" | heading | `plans.mustache:146` | str `central_plans_templatestitle` | `<h2>` |
| `PLN-LIST-GEAR` | Display options | icon button | `plans.mustache:147-152` | `data-action="list-display-options"` | `fa-cog` (`:150`); toggles `PLN-LIST-OPTS` and **persists** the open/closed state in the `panels.planslist` preference (`plans.js:703-711`) |
| `PLN-LIST-OPTS` | `[no label]` | collapsible panel | `plans.mustache:155-170` | `data-region="list-display-options-panel"` | `role="group"`; state restored by `applyPanelState` (`plans.js:417`) |
| `PLN-LIST-OPT-ID` | Show identifiers | switch | `plans.mustache:158-163` | `data-list-toggle="id"` | switches on `show-id` on the row container (`LISTDISPLAY_CLASSES`, `plans.js:58`) |
| `PLN-LIST-OPT-DUE` | Show due date | switch | `plans.mustache:164-169` | `data-list-toggle="duedate"` | switches on `show-duedate`; `planslist` preference (`plans.js:370-378`) |

## Master panel — search and creation

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `PLN-TOOLBAR` | `[no label]` | container | `plans.mustache:172` | `data-region="plan-toolbar"` | search + new button |
| `PLN-PLANSEARCH` | Search by name or ID number | search input | `plans.mustache:178-179` | `data-region="plan-search-input"` | **client-side search**, reloads nothing: `applyPlanSearch` (`plans.js:149-167`) matches against the `data-search` haystack (name + idnumber lowercased, built on the server at `dynamictabs/plans.php:172`) and toggles the `local-dimensions-central-plan-filtered` class. Label is `visually-hidden` (`:175-177`). **It is a new element — do not confuse it with `PLN-SEARCH`** |
| `PLN-NEW` | New template | button | `plans.mustache:182-184` | `data-action="new-template"` | `fa-plus`; only under `{{#canmanage}}` (`:181`); `openForm` with `FORM_CLASS` and `id: 0` (`plans.js:714-720`) |

## Master panel — multi-competency filter

It replaced the single "Filtered by: X" badge. The filter is a **cross-framework intersection**: only
templates that contain **all** the chosen competencies survive (`dynamictabs/plans.php:119-140`).

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `PLN-FILTER` | `[no label]` | container | `plans.mustache:188` | `data-region="competency-filter"` | — |
| `PLN-FILTER-LABEL` | "Filter by competencies" | label | `plans.mustache:190` | str `central_plans_filterbycompetencies` | — |
| `PLN-FILTER-CLEAR` | Clear competency filter | button | `plans.mustache:192-194` | `data-action="clear-competency"` | `fa-times`; only with `{{#filteredbycompetency}}` (`:191`); clears the dataset and `reloadPane` (`plans.js:666-669`) |
| `PLN-FILTER-CHIP` | competency name | chip (loop) | `plans.mustache:199-206` | `competencyfilters` | `badge local-dimensions-central-chip-accent` (`:199`) — the hub's **soft blue** chip (`styles.css:6808-6814`), not the header's `-plans-chip-accent`; label = `shortname` (`dynamictabs/plans.php:136-139`) |
| `PLN-FILTER-CHIP-REMOVE` | "Remove {$a} from the filter" | icon button | `plans.mustache:201-205` | `data-action="remove-filter-competency"` | the `aria-label` embeds the name (`:203`); drops the id from the CSV and `reloadPane` (`plans.js:670-674`) |
| `PLN-FILTER-ADD` | Add to filter | button | `plans.mustache:208-211` | `data-action="add-filter-competency"` | `fa-plus`; toggles the picker's `hidden` and moves focus to the input (`plans.js:675-691`). **Progressive disclosure** — `CLAUDE.md` warns: in Behat, open it before interacting |
| `PLN-FILTER-PICKER` | `[no label]` | panel | `plans.mustache:213-220` | `data-region="competency-filter-picker"` | starts `hidden` (`:214`) |
| `PLN-SEARCH` | Filter plans by competency | select/autocomplete | `plans.mustache:218` | `data-region="competency-search"` | `form-select` (never `custom-select`); starts **empty** and is enriched by `enhance()` with the `local_dimensions/central/competency_datasource` datasource (`plans.js:827-829`), guarded by `dataset.enhanced` (`:813`) so it is not enriched twice on every `reloadPane`. The `change` adds the id to the CSV and `reloadPane` (`:815-826`) |

## Master panel — template list

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `PLN-TPL-LIST` | `[no label]` | container | `plans.mustache:224` | `data-region="template-rows"` | takes the `show-id` / `show-duedate` / `show-disabled` classes |
| `PLN-TPL-ROW` | template name | button | `plans.mustache:226-240` | `data-action="select-template"`, `data-region="template-row"` | the **whole button** is the row; `active` + `aria-current="true"` on the selected one (`:227`, `:229`); it writes the id into the dataset, **persists** it through `Preferences.saveNav({templateid})` and reloads **preserving the list's scroll** (`plans.js:660-665`) — through `reloadKeepingScroll`, therefore **without** the busy blanket |
| `PLN-TPL-ICON` | `[no label]` | icon | `plans.mustache:230` | `fa-clipboard-list` | decorative |
| `PLN-TPL-NAME` | name | text | `plans.mustache:233` | `name` | — |
| `PLN-TPL-ID` | idnumber | chip | `plans.mustache:234` | `idnumber` | only when `idnumber` is set; visible only with `PLN-LIST-OPT-ID` |
| `PLN-TPL-DUE` | due date | chip | `plans.mustache:236` | `duedate` | `fa-calendar`; only when `duedate` is set; visible only with `PLN-LIST-OPT-DUE`. Formatted on the server with `userdate(..., strftimedate)` (`dynamictabs/plans.php:176-178`) |
| `PLN-TPL-COUNT` | N | counter | `plans.mustache:238` | `competencycount` | `api::count_competencies_in_template($id)` (`dynamictabs/plans.php:173`) — **one query per row**; gains `is-selected` on the active template |
| `PLN-TPL-HIDDEN` | "Hidden" | badge | `plans.mustache:239` | `^visible` | `badge bg-secondary`; str `hidden, tool_lp` |
| `PLN-SEARCH-EMPTY` | "No templates match the current search." | empty-state | `plans.mustache:242-244` | `data-region="plan-search-empty"` | starts `hidden` **with `role="status"`** (`:242`, `f73c260`), so revealing it announces; `applyPlanSearch` reveals it when the visible count reaches zero (`plans.js:163-166`) — and **a hidden row only counts as visible if the toggle revealed it** (`:159`) |

## Divider

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `PLN-RESIZER` | Resize panels | separator | `plans.mustache:258-262` | `data-region="plans-resizer"` | `role="separator"`, `aria-orientation="vertical"`, `tabindex="0"`; only with `{{#hastemplates}}` (`:257`). `initMasterResizer` (`plans.js:843-852`) with `cssvar` `--local-dimensions-plans-master-width`, minimum **300**, maximum **1200**, reserve **382**; the width persists in **`localStorage`** under `local_dimensions_plans_master_width` (`pane_resizer.js:63`, `:69`) — it is not a user preference |

## Detail — gradient header (wears the template's colours)

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `PLN-DETAIL-HEADER` | `[no label]` | header | `plans.mustache:266-269` | `data-region="plan-detail-header"` | three stops through inline custom properties (`--ld-plans-hdr-0/-48/-100`) + the text `color`; the rule is `linear-gradient(140deg, …0%, …48%, …100%)` (`styles.css:5829`, block at `:5823-5837`). The server computes them: base = the template's `bgcolor` field **or `#0f6cbf`**, and the 48/100 stops are `helper::darken_hex(base, 0.16)` and `(base, 0.34)` (`dynamictabs/plans.php:271-272`, `:311-313`). For the `#0f6cbf` default that gives **`#0d5ba0`** and **`#0a477e`** (measured, reproducing `helper.php:3020-3035`). **Gotcha:** the CSS *fallbacks* (`#0d5a9f`, `#0a4680`, `styles.css:5829`) **do not match** what the PHP computes — but they are inert, because `:267` writes the three custom properties **unconditionally**, so the fallback never paints |
| `PLN-DETAIL-GLOW` | `[no label]` | glow | `plans.mustache:268-269` | `aria-hidden="true"` | white `radial-gradient` at 22% in the top-left corner, inline |
| `PLN-DETAIL-TITLE` | template name | heading | `plans.mustache:274` | `selectedtemplatename` | `<h2>` |
| `PLN-STATUS` | "Enabled" / "Hidden" | badge | `plans.mustache:275-280` | `selectedtemplatevisible` | `is-enabled` (str `central_plans_enabled`) or `is-disabled` (str `hidden, tool_lp`); colours at `styles.css:5882-5888` |
| `PLN-DETAIL-COUNT` | "Competencies {N}" | pill | `plans.mustache:283-286` | `competencycount` | `count($competencies)` (`dynamictabs/plans.php:327`) |
| `PLN-COUNT-PLANS` | "Plans {N}" | pill | `plans.mustache:288-291` | `selectedtemplateplancount` | `helper::count_plans_by_template` (`dynamictabs/plans.php:319-321`); it also feeds `PLN-DELETE`'s `data-plancount` |
| `PLN-COUNT-COHORTS` | "Cohorts {N}" | pill | `plans.mustache:293-296` | `selectedtemplatecohortcount` | `helper::count_cohorts_by_template` (`dynamictabs/plans.php:322-324`) |
| `PLN-DETAIL-GEAR` | Display options | icon button | `plans.mustache:299-304` | `data-action="display-options"` | `fa-cog`; **the tab's second gear**; `panels.plansdetail` preference (`plans.js:694-702`) |
| `PLN-DISP-OPTS` | `[no label]` | collapsible panel | `plans.mustache:307-328` | `data-region="display-options-panel"` | `role="group"`; switches in the dark variant (`-switch-dark`) because they sit **on top of the gradient** |
| `PLN-DISP-TAX` | Show taxonomy | switch | `plans.mustache:310-315` | `data-display-toggle="tax"` | switches on `show-tax` on the list (`DISPLAY_CLASSES`, `plans.js:55`) |
| `PLN-DISP-PATH` | Show paths | switch | `plans.mustache:316-321` | `data-display-toggle="path"` | switches on `show-path` |
| `PLN-DISP-ID` | Show identifiers | switch | `plans.mustache:322-327` | `data-display-toggle="id"` | switches on `show-id`; `plansdetail` preference (`plans.js:295-303`) |
| `PLN-CHIP-DISPLAY` | "Display format: {$a}" | chip | `plans.mustache:331-335` | `selectedtemplatehasdisplaymode` | `fa-eye`; the `-plans-chip-accent` variant (`styles.css:5976-5981`, `#495057` + `#fff`). Comes from `constants::display_mode_options()` (`dynamictabs/plans.php:256-258`) |
| `PLN-CHIP-TYPE` | "Competency label: {$a}" | chip | `plans.mustache:336-340` | `selectedtemplatehastype` | `fa-tag`; glass variant; `type` custom field |
| `PLN-CHIP-DUE` | "Due date: …" | chip | `plans.mustache:341-345` | `selectedtemplatehasduedate` | `fa-calendar`; **the colon and the space are literals in the template** (`:343`), they are not part of the string |
| `PLN-CHIP-TAG1` | tag 1 | chip | `plans.mustache:346-348` | `selectedtemplatehastag1` | glass; custom field |
| `PLN-CHIP-TAG2` | tag 2 | chip | `plans.mustache:349-351` | `selectedtemplatehastag2` | glass; custom field |

## Detail — body and competency list

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `PLN-DESC` | `[no label]` | collapsible description | `plans.mustache:357-363` | `selectedtemplatehasdescription` | `collapsible_description` partial; the gate uses the **tag-stripped** version (`strip_tags` + `trim`, `dynamictabs/plans.php:265`, `:301`), so a description holding only `<p></p>` does not open the block. Re-armed on every refresh by `CollapsibleDescription.refresh(pane)` (`plans.js:797`) |
| `PLN-LIST-HEADER` | Plan competencies · Structure · Actions | column header | `plans.mustache:365-372` | — | the title is **dynamic**: with the `type` custom field it uses str `central_plans_competencylistlabelled` ("{$a} of the plan"), otherwise `central_plans_competencylist` (`:368`) |
| `PLN-COMP-ROW` | `[no label]` | row (loop) | `plans.mustache:378` | `data-competency="{id}"` | `<li>`; **the JS row selector is `[data-competency]`** (`plans.js:118`, `:440`, `:454`) |
| `PLN-COMP-NAME` | shortname | button | `plans.mustache:381-382` | `data-action="open-competency-detail"`, `data-region="comp-name"` | opens `MOD.DETAIL` (`plans.js:743-744`); it is **not** the footer nor the kebab — it is the name itself. It is also read as the label by `MOD.MOVETO`'s options (`plans.js:549-552`) |
| `PLN-COMP-TAX` | "– taxonomy" | text | `plans.mustache:383` | `taxonomy` | `helper::get_taxonomy_at_level` by the competency's level (`dynamictabs/plans.php:212-215`); visible only with `PLN-DISP-TAX` |
| `PLN-COMP-ID` | idnumber | chip | `plans.mustache:384` | `idnumber` | visible only with `PLN-DISP-ID` |
| `PLN-COMP-PATH` | path | trail | `plans.mustache:386-390` | `path` | `fa-folder-o` (`:388`); `helper::competency_breadcrumbs` (`dynamictabs/plans.php:205`); visible only with `PLN-DISP-PATH` |
| `PLN-COMP-STRUCT` | structure tag | badge | `plans.mustache:392-394` | `frameworktag` | **cross-framework**: the framework's `idnumber`, or its `shortname` when there is none (`dynamictabs/plans.php:199-201`) |

## Competency kebab — **nested list, legitimate** (`plans.mustache:396-436`)

The whole block is gated by `{{#canmanage}}` (`:395`, closing at `:446`). `:396` opens the
`div.dropdown` and `:436` closes it. **The two `data-*` side by side** (`data-toggle` **and**
`data-bs-toggle`, `:399`) and the **two** alignment classes (`dropdown-menu-right
dropdown-menu-end`, `:403`) are the BS4/BS5 requirement from `CLAUDE.md` — the comment at `:397`
records why.

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `PLN-COMP-MENU` | "Actions: {shortname}" | kebab button | `plans.mustache:398-402` | `data-toggle`/`data-bs-toggle="dropdown"` | `fa-ellipsis-v`; icon-only, so the `aria-label` embeds the row name (`:400`) — the pattern `CLAUDE.md` requires for Behat's `"button"` selector |
| `PLN-COMP-EDIT` | Edit competency | dropdown-item | `plans.mustache:405-408` | `data-action="edit-competency"` | `fa-pencil`; str `editcompetency, tool_lp`; carries `data-frameworkid` (`:406`); `openForm` with `COMPETENCY_FORM_CLASS` (`plans.js:728-734`) — **the same function** as `PLN-EDIT`, different entity |
| `PLN-COMP-UP` | Move up | dropdown-item | `plans.mustache:411-414` | `data-action="move-competency-up"` | `fa-arrow-up`; `disabled` when `{{#first}}` (`:412`); **in-place path** — no reload (`plans.js:625-650`) |
| `PLN-COMP-DOWN` | Move down | dropdown-item | `plans.mustache:417-420` | `data-action="move-competency-down"` | `fa-arrow-down`; `disabled` when `{{#last}}` (`:418`); in-place likewise |
| `PLN-COMP-MOVETO` | Move to position… | dropdown-item | `plans.mustache:423-426` | `data-action="move-competency-to"` | `fa-arrows-v`; opens `MOD.MOVETO` (`plans.js:537-595`) |
| `PLN-COMP-REMOVE` | Remove competency | dropdown-item | `plans.mustache:430-433` | `data-action="remove-competency"` | `fa-times`; **`text-danger`** (`:430`) — unlike the footers, the menu item **does** carry a colour variant; separated by a `dropdown-divider` (`:428`); confirms with `saveCancelPromise` (`plans.js:279`) |
| `PLN-COMP-GRIP` | "Move to position…: {shortname}" | drag grip | `plans.mustache:440-445` | `data-region="drag-handle"`, `data-action="move-competency-to"` | `fa-arrows-up-down-left-right` (`:444`). **Rendered AFTER the kebab on purpose** (comment at `:437-439`): the `aria-label` embeds the name (`:443`) and Behat's `"button"` selector takes the **first hit in document order** — the CSS `order: -1` (`styles.css:6856`) paints it on the **left** all the same. It is the exact trap recorded in `CLAUDE.md`. It starts at `opacity: 0` (`styles.css:6860`) and appears on row hover or on `:focus-visible` (`:6869-6873`) — **but stays interactable for WebDriver** |

## Template actions — **the page's sticky footer** (`plans.mustache:462-488`)

The holder is server-rendered `hidden` (`:462`) **with the selected template's `data-*` already
embedded**, and `init` copies the `innerHTML` into `#sticky-footer` and **removes the holder**
(`plans.js:786-789`) — otherwise a hidden duplicate, earlier in document order, would shadow Behat's
name-based clicks (the comment at `:779-784` records this). Only under `{{#canmanage}}` (`:457`,
closing at `:489`). Core's raw pattern (`btn py-0 d-flex flex-column`): icon above a centred label,
**no colour variant**.

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `PLN-EDIT` | Edit details | footer button | `plans.mustache:465-468` | `data-action="edit-template"` | `fa-pencil`; str `central_plans_editdetails` (**shared with `FWK`**); `openForm` with `FORM_CLASS` (`plans.js:721-727`) |
| `PLN-BROWSE` | Add competency | footer button | `plans.mustache:469-472` | `data-action="browse-frameworks"` | `fa-plus`; str `central_addcompetency`. Opens `MOD.BROWSER` (`plans.js:712`) — **it absorbed the retired `PLN-ADD`'s job**; the `excludeids` (`dynamictabs/plans.php:328`) stops what is already in the template being offered again. **Only door** to this modal |
| `PLN-PARTICIPANTS` | Manage participants | footer button | `plans.mustache:473-476` | `data-action="manage-participants"` | `fa-users`; opens `MOD.PART` (`plans.js:713`). **Only door** — and it is from inside it that `MOD.ENROL` exists |
| `PLN-DUPLICATE` | Duplicate template | footer button | `plans.mustache:477-480` | `data-action="duplicate-template"` | `fa-clone`; the **plugin's** WS `local_dimensions_duplicate_template` (not core's), which also copies the lp-area custom fields, the embedded files and the card images (`plans.js:604-615`); it **selects the copy** by writing the new id into the dataset **before** the reload (`:611-614`) |
| `PLN-DELETE` | Delete template | footer button | `plans.mustache:481-485` | `data-action="delete-template"` | `fa-trash`; carries `data-name` and `data-plancount` (`:482`); **two paths** — see the rules below |

## Modals reached

| ID | Origin | Rule / notes |
| --- | --- | --- |
| `MOD.BROWSER` | `competency_browser.js:106` | ← `PLN-BROWSE`. See [`mod-browser.md`](mod-browser.md) |
| `MOD.PART` | `participants_manager.js:171` | ← `PLN-PARTICIPANTS`. See [`mod-participants.md`](mod-participants.md) |
| `MOD.ENROL` | `enrol_methods.js:859` | mounted **only** from inside `MOD.PART` (`participants_manager.js:33`). See [`mod-enrolmethods.md`](mod-enrolmethods.md) |
| `MOD.DELPLANS` | `plans.js:240-245` | ← `PLN-DELETE` **when there are plans**. See [`mod-delplans.md`](mod-delplans.md) |
| `MOD.MOVETO` | `plans.js:557-562` | ← `PLN-COMP-MOVETO` and `PLN-COMP-GRIP`. Template `local_dimensions/central/move_competency_modal` — **the same one as `EST`** (`structure.js:988`); select `#local-dimensions-plans-move-position` (`plans.js:564`) |
| `MOD.DETAIL` | `competency_detail.js:277` | ← `PLN-COMP-NAME`. Also opened by `EST`'s related chip (`structure.js:1247`) — **neither of the two doors is a footer** |
| `MOD.TPLFORM` | `plans.js:201` | ← `PLN-EDIT` (footer), `PLN-NEW` (header) and `PLN-COMP-EDIT` (kebab, with a different `formclass`) |

## Empty states

All three are born with `role="status"` (`f73c260`), so when the list empties the message announces
itself.

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `PLN-EMPTY-FILTERED` | "No learning plans contain this competency." | empty-state | `plans.mustache:247` | str `central_noplanswithcompetency` | `alert-warning`; filter on and no result |
| `PLN-EMPTY` | "No learning plans found." | empty-state | `plans.mustache:250` | str `noplans` | `alert-info`; no filter and no templates |
| `PLN-DETAIL-EMPTY` | "No competencies in this structure" | empty-state | `plans.mustache:452` | str `nocompetencies` | `alert-warning`. **See the i18n wart below** |

## Business rules (verified in the code)

- **Deletion has two paths, and the gate is the server, not the dataset.** `deleteTemplate`
  (`plans.js:223-261`) does **not** trust `data-plancount` to decide: it asks the WS
  `core_competency_template_has_related_data` (`:225-228`). Only if **the server** says there are
  plans does `MOD.DELPLANS` open (`:235-252`), and then `data-plancount` is used **only** to display
  the number (`:238`). With no plans it falls through to a plain `deleteCancelPromise` (`:254-260`).
  The radio chooses between unlinking (default) and deleting the learner's plans (`:246-250`).
- **Reordering has three paths and all of them are in-place — none reloads the pane.**
  `moveCompetency` (`plans.js:625-650`, in-place stated explicitly in the comment at `:642`),
  `initDragReorder`'s `dragend` (`:484-523`, in-place confirmation at `:512-516`) and `MOD.MOVETO`'s
  save (`:577-587`). All three end the same way: `refreshMoveState` + `flashRow` (`:514-515`,
  `:585-586`, `:648-649`). **`reloadKeepingScroll` appears only in the `.catch`** of the last two
  (`:521`, `:592`) — restoring the server's order out of a failure, with the
  `eslint-disable-next-line promise/no-nesting` that `CLAUDE.md` requires.
- **The flash is shared and respects `prefers-reduced-motion`.** `flashRow` no longer lives in this
  tab: it is the `local_dimensions/central/flash` module (`flash.js:34-48`, `3c0bf41`), which bails
  out early when the user asked for reduced motion (`:38-40`) and reads the duration from the
  `--mds-motion-flash` token (`styles.css:32`) with a 1500ms fallback (`flash.js:43`).
- **`refreshMoveState` exists because in-place lies about `first`/`last`.** The server marks
  `first`/`last` at render time (`dynamictabs/plans.php:229-232`) and the template uses that to
  disable the arrows (`plans.mustache:412`, `:418`). Since the reorder does not reload,
  `refreshMoveState` (`plans.js:117-129`) recomputes `disabled` on **every** row by index — otherwise
  the first row would keep "move up" enabled after a drag.
- **Core decides which side the row lands on, and both in-place paths mirror that.**
  `reorder_template_competency` puts the source **after** the target when moving down and **before**
  it when moving up; `dragend` derives the reference from the new sibling (`plans.js:499-501`) and
  `MOD.MOVETO` applies `after`/`before` according to the direction (`:578-584`). Getting this wrong
  desynchronises DOM and server with no error at all.
- **The footer is defended against races at two points** (`FWK` has three): `init` only publishes if
  the tab is the active one (`plans.js:785`) and `dispatchPlansAction` ignores clicks once the tab
  loses focus (`:758-760`). Both exist because dynamic tabs re-run `init` from an out-of-order
  asynchronous load.
- **The selected template is auto-chosen, and it prefers a visible one.** With no valid `templateid`
  the server takes the **first visible** one and only falls back to the first of all when none is
  (`dynamictabs/plans.php:143-156`) — so the detail matches the default list, where hidden ones start
  out of sight.
- **`hashiddentemplates` is exported and the template does not use it.** `dynamictabs/plans.php:285`
  sends it, but `plans.mustache` decides on `{{#showhiddentoggle}}` (`:139`) — the same dead key that
  `fwk-structures.md` recorded for `hashiddenframeworks`. The only occurrences of that name are in
  `structure.mustache`.
- **`canmanage` on `PLN-ROOT` travels under another name.** `data-canmanageenrol="{{canmanage}}"`
  (`plans.mustache:133`) — the same value as `canmanage` (`dynamictabs/plans.php:329`) exposed under
  a different name for the participants modal. There is no `data-canmanage`.
- **i18n · the empty-state wart.** `PLN-DETAIL-EMPTY` uses the str `nocompetencies`, which is
  **"No competencies in this structure"** (en, `lang/en:511`) / "Nenhuma competência nesta estrutura"
  (pt_br, `lang/pt_br:511`) — but the container here is a **plan template**, not a structure. The
  string is shared with `EST` (`structure.mustache:225`, where it is correct) and not even the alert
  variant matches: `alert-info` in `EST`, `alert-warning` in `PLN`. An empty template announces the
  wrong entity to the user.

<a id="a11y-cabecalho-gradiente"></a>

- **a11y · the header is the only surface in the hub whose colours the admin picks — and what is
  measured is not what is painted.** The pair comes from two custom fields
  (`constants::CFIELD_CUSTOMBGCOLOR` / `CFIELD_CUSTOMTEXTCOLOR`), defaulting to `#0f6cbf` + `#ffffff`
  (`dynamictabs/plans.php:271-272`). The plugin is **not negligent**: the two forms that edit that
  pair (`template_dynamic_form.php:235-239` and `competency_dynamic_form.php:234-238`) build a WCAG
  panel **in real time** — ratio, verdict, AA/AAA badges and **up to two one-click fixes** when it
  fails (`contrast.js:16-33`, thresholds at `:43`: AA 4.5, AAA 7). But it **advises, it does not
  block**: the module itself says it "never touches how the form saves" (`contrast.js:22-23`), and
  the form's `validation()` only checks `shortname` and the SCSS
  (`template_dynamic_form.php:334-352`) — never the pair.
  **The real gap, though, is elsewhere:** the panel grades **text vs background**, and the header
  **does not paint that pair** — it paints three derived stops (`darken_hex` 0.16/0.34) and
  **translucent** chips on top of them. Nobody grades those derivatives. Measured for the `#0f6cbf`
  default: white text gives **5.36:1** (passes), but the `-plans-chip-glass` chips (white at 13%,
  `styles.css:5983-5987`) give **4.22:1 over stop 0** — **below** the AA minimum — rising to 5.22:1
  at stop 48 and 6.71:1 at stop 100. That is: the same chip passes or fails depending on **where it
  falls in the gradient**. The fixed ones pass comfortably: `-plans-chip-accent`
  (`styles.css:5976-5981`) and the count pill (`:5905-5917`) use `#495057` + `#fff` (**8.18:1**),
  `status.is-enabled` `#217a37` (**5.38:1**), `status.is-disabled` `#6a737b` (**4.83:1**).
- **One count query per row.** `PLN-TPL-COUNT` calls
  `api::count_competencies_in_template($id)` inside the loop (`dynamictabs/plans.php:173`), with no
  batching — unlike `count_plans_by_template`/`count_cohorts_by_template`, which accept an array
  (`:319-324`) but are called only for the selected template. With N templates, N queries.

## The `reloadPane` busy blanket (`976006d`)

Switching tabs **already had** a loading indication, and it comes from core: `core/local/dynamic_tabs`'s
`loadTab` opens with `addIconToContainer` and `show.bs.tab` empties the previous pane. The gap was
the **plugin's own `reloadPane`** (`tabs.js:69-108`), which retraces `loadTab`'s path **without**
that icon and is the one that runs on every post-action refresh. **A precision only this tab
affords:** because `PLN` **never starts active** (`central.php:115`), its pane is **never**
server-rendered on load — the first paint *always* goes through `loadTab`, which **already** shows
the icon. In this tab the gap was **exclusively** `reloadPane`.

**The shape is not a banner, and that is the expectation to correct.** It is not an `alert alert-info`
+ `spinner-border spinner-border-sm` in the style of `FWK-IMP-BANNER`, nor core's
`addIconToContainer`: it is a **whole-pane busy blanket**, entirely in CSS.

- `reloadPane` switches on the `local-dimensions-central-tab-loading` class and writes
  `aria-busy="true"` (`tabs.js:44`, `:77-80`), and clears both in a `finally` **under the generation
  guard** (`:103-106`) — never only on success, so a failure does not leave the pane spinning
  forever, and a superseded reload does not switch off the newest one's blanket.
- The visual is two pseudo-elements (`styles.css:4028-4069`): an `rgba(255,255,255,0.55)` veil on
  `::before` (`:4033-4042`) and a 2rem ring on `::after` (`:4044-4057`) with a **keyframe of its own**
  (`@keyframes local-dimensions-central-spin`, `:4059-4063`) — no dependence on Bootstrap's
  `spinner-border` being present. `prefers-reduced-motion` stretches the rotation to 1500ms
  (`:4065-4069`).
- The minimum height comes from the `--mds-loading-min-height: 12rem` token (`styles.css:34`,
  consumed at `:4030`), so the pane does not collapse behind the veil.
- `aria-busy="true"` shipped as asked. The ARIA quartet that `states.html` specifies (`role="status"`
  + `aria-live="polite"` + `aria-label` + moving focus) does **not** belong to the blanket — it covers
  content that already exists. That quartet belongs to the **first-paint placeholder** of an empty
  pane, which is a different surface and does not exist — see [`mod-participants.md`](mod-participants.md).

**The `reloadPane` census, measured.** There are **24** calls across 5 modules — `structure` 9,
`frameworks` 6, `plans` 6, `context` 2, `competency_browser` 1. One of the 24 is the contextbar's own
refresh control (`context.js:217`), not a post-action refresh. **The 6 in this tab** are
`plans.js:102` (inside `reloadKeepingScroll`), `:233` (delete), `:614` (duplicate), `:668` (clear
filter), `:673` (remove filter chip) and `:825` (add competency to the filter).

**The `{quiet: true}` option is the counter-intuitive point — read it before "fixing" anything.**
`reloadPane` accepts `{quiet}` (`tabs.js:66`, `:69`) to suppress the blanket, and the **only** caller
in the entire plugin is `plans.js:102`, inside `reloadKeepingScroll`. So of this tab's 6 reloads,
**5 show the blanket and 1 does not** — and the one that does not is precisely `reloadKeepingScroll`
(`plans.js:93-109`), which is **not** an in-place path: it **awaits `reloadPane` at `:102`** and only
captures `scrollTop` before (`:96-101`) and restores it after (`:103-108`). It is a whole-pane reload
kept silent **on purpose**: its goal is to preserve the sense of stillness at the five entries that
go through it — `:206` (form submitted), `:287` (remove competency), `:664` (`select-template`), and
the reorder's two failure recoveries (`:521`, `:592`).

The **three genuinely in-place paths** — `moveCompetency` (`:625-650`), the `dragend` (`:512-516`)
and `MOD.MOVETO`'s save (`:577-587`) — **never call `reloadPane`**, so the blanket never comes near
them: they confirm themselves with `flashRow`. The house rule holds, with the amendment `quiet`
introduced: **pane reloaded → blanket, except when the caller opts to preserve the scroll; row
swapped → flash.**

## The contextbar's refresh control (`7ed2a99`)

Before it, **no UI control** fired `reloadPane` — all 24 calls were automatic post-action refreshes.
Today the contextbar carries a `data-action="refresh"` button (`contextbar.mustache:101-105`) that
reuses core's `refresh` string and the `fa fa-rotate` glyph (`:103`), with no new string. The handler
(`context.js:206-230`, selector at `:47`, delegated at `:356-358`) disables the button (`:212`), puts
`fa-spin` on the icon (`:214`), awaits `reloadPane` (`:217`) and, in a `finally`, re-enables it,
removes the spin and **gives focus back** when disabling the button dropped it onto `<body>`
(`:218-228`).

It is **not** the refresh button in the modal headers — that is a different surface,
`amd/src/central/modal_refresh.js` (`7d69197`). Both exist; do not confuse them. Recorded as debt,
not as a gap: the refresh does **not** resynchronise `BAR-COUNT-01`, because the bar lives outside
the panes and `reloadPane` does not re-render it. See [`bar-contextbar.md`](bar-contextbar.md).

## Icons and the indicator on the page tabs (`514d246`)

The page's three tabs carry a FontAwesome glyph to the left of the label and a flat 2px indicator on
the active one. The glyphs are assembled in PHP: `$tabicons` at `central.php:108-112`
(`frameworks` → `fa-sitemap`, `structure` → `fa-crosshairs`, `plans` → `fa-graduation-cap`), the `<i>`
at `:122` and the concatenation at `:125` (`'displayname' => $icon . $tablabels[$shortname]`). What
this tab confirms: `core/dynamic_tabs.mustache` **triple-stashes** `displayname`, so the icon rides
in on the label **without** changing a core template — the comment at `central.php:104-107` records
this.

The indicator is CSS scoped to the hub's `body` (`central.php:57` adds
`local-dimensions-central-page`), at `styles.css:7232-7271` — the only rule in the plugin that still
carries the `IMP-10` tag in its comment (`:7233`). Base: `color: #6a737b`, `border: 0`,
`box-shadow: inset 0 -2px 0 transparent` and a transition driven by the `--mds-motion-base`/`-ease`
tokens (`:7239-7245`). Hover `#1d2125` (`:7247-7249`). Active: `#1d2125` + `font-weight: 500` +
`box-shadow: inset 0 -2px 0 var(--bs-primary, #0f6cbf)` (`:7261-7265`) — the active tab's **text** is
Boost's dark grey, **not** the accent blue; the accent stays in the underline alone. And because
`border: 0` plus the base `box-shadow` knocked out Boost's focus ring, a `:focus-visible` restores it
with `outline: 2px solid var(--bs-primary, #0f6cbf)` (`:7255-7259`). `prefers-reduced-motion` cuts
the transition (`:7267-7271`). mtube's `ResizeObserver` overflow dropdown was **not** ported (zero
occurrences of `ResizeObserver` in `amd/src/central/`).

Because `PLN` does **not** start active (`central.php:115`), it is the tab that exercises the
indicator's **inactive state** on the first paint. See [`hierarchy-nav.html`](../hierarchy-nav.html).

## Retired IDs

> Do not reuse. A dangling ID is worse than a recorded retirement.

| ID | Status | Replacement | Note |
| --- | --- | --- | --- |
| `PLN-ADD` | **Retired** (2026-07-14) | `PLN-BROWSE` → `MOD.BROWSER` | It was the `data-region="competency-add"` autocomplete in the detail panel, for adding a competency without leaving the tab. **It exists in no template and not in `plans.js`** (verified by searching `templates/` and `amd/src/`). Adding a competency now happens only through `MOD.BROWSER`, launched from the footer — the `excludeids` that fed its `data-exclude` survived and today feeds the modal (`dynamictabs/plans.php:328`) |
| `PLN-FILTER-BADGE` | **Retired** (2026-07-14) | `PLN-FILTER-CHIP` | It was the single "Filtered by: X" badge. The filter became **multi**-competency: one removable chip per competency (`plans.mustache:199-206`) plus an add button (`:208-211`). The `filteredbycompetency` flag survived, but today it only gates `PLN-FILTER-CLEAR` (`:191`) |
