# Design kit — Competency hub (`local_dimensions`)

Component library for the admin redesign (and the base for the companion plugin `local_modfields`).
Every `.html` file is a **self-contained preview** (inline tokens, light/dark via
`prefers-color-scheme`) with an `@dsCard` marker on the first line for the Design System index.

This kit is the **single source of the hub's Design System**, mirrored in two places kept in sync:

- this `docs/design-kit/` folder (the source in the repo);
- the project **"Dimensions — Competency Hub (admin kit)"** on Claude Design (`claude.ai/design`).

Everything the kit records is **as-is**: what the shipped code renders, with a `file:line` behind each
claim. What was designed and **not built** is recorded separately, in a **Pending** note carrying its
own evidence of absence — never drawn as if it had shipped. The full list is under
[Pending](#pending).

## Components (component level)
| File | Group | What it is |
|---|---|---|
| `tokens.html` | Foundations | Boost values under `--mds-*` names (semantic, states, focus, elevation, scales); `styles.css` declares motion + loading height and writes the rest as literals. |
| `states.html` | Foundations | default/hover/active/disabled/busy + visible focus (WCAG 2.2 AA) — the Boost `.btn-*` classes the hub actually uses. |
| `toast.html` | Foundations | `local_dimensions/central/toast`, the house wrapper over `core/toast`: the four types (success/info/warning/danger), the close button by default, the region hosted in the modal body (`addToastRegion`, z-index) and the pair with the flash. |
| `tooltip.html` | Foundations | CSS-only balloon on hover/focus + the truncation↔tooltip pair; the full text never leaves the DOM, so the balloon is decoration. |
| `modal-shell.html` | Shell | Header + body + footer of a modal — refresh, expand/restore and close in the header, admin links in the footer. |
| `sticky-footer.html` | Shell | The **3 real variants** + the invariant: the hub builds **17** modals (4 `ModalForm` + 13 `Modal*.create`); the footer reaches **10** directly, is the **only** door for **7**, and **8** depend on it. |
| `form-section.html` | Form | Category heading (**Feel** / **Look**) + field rows (select, text, textarea). |
| `image-dropzone.html` | Form | Core's `filemanager` applied to the two built-in-mode images — empty and with a file. |
| `hierarchy-nav.html` | Navigation | Context bar (context + hidden categories + category + counter + refresh) and hub tabs with icon and indicator. |
| `master-detail.html` | Data | Competency tree + detail panel (header chips + three metrics). |
| `paginated-picker.html` | Data | Core's autocomplete in the plans filter: cross-structure server-side search, origin-structure tag and ancestor trail per suggestion, overflow warning past one page. |
| `cohort-assign.html` | Data | Cohorts tab of the participants modal: add autocomplete + 4-column table with sync and per-row remove. |

> Every card draws the **shipped** UI and cites it. Where a card also carries something that was
> **not built** — the adaptive trail in `hierarchy-nav`, the group-management Cohorts tab in
> `cohort-assign` — it says so in a **Pending** note with the grep that proves the absence.

## Screens (`screens/`)
A replica of each hub surface, faithful to the **shipped output**, with an **ID badge** on every
element. `@dsCard` format, group "Screens (as-is)" in the DS index. They draw in the Moodle (Boost)
palette itself, so the depiction and the live UI share one set of colours.

| File | Card | Surface |
|---|---|---|
| `screens/bar-contextbar.html` | Contextbar | `BAR` — context selector + hidden-categories toggle + counter + Refresh |
| `screens/est-competencies.html` | Competencies | `EST` — tree + detail + sticky footer |
| `screens/fwk-structures.html` | Structures | `FWK` — cards + 3 header actions + footer |
| `screens/pln-plans.html` | Learning plans tab | `PLN` — master-detail, the nested list's kebab, the sticky footer, `reloadPane`'s busy blanket, the icon tabs, and the **CSV transfer** toolbar with both its modals (`PLN-EXP`, `PLN-IMP`) |
| `screens/mod-browser.html` | Modal · Browse structures | `MOD.BROWSER` — structure selector + shared tree; Add is born disabled |
| `screens/mod-links.html` | Modal · Courses & activities | `MOD.LINKS` — two-level outcome, course card with lazy activities |
| `screens/mod-related.html` | Modal · Related competencies | `MOD.RELATED` — shared tree + current relations |
| `screens/mod-rule.html` | Modal · Completion rule | `MOD.RULE` — the outcome → rule → points cascade |
| `screens/mod-scale.html` | Modal · Scale and proficiency | `MOD.SCALE` — one row per scale value + the popup validation |
| `screens/mod-participants.html` | Modal · Participants | `MOD.PART` (+ `MOD.COHORT` / `MOD.ROLES`) — 4 tabs, header actions, footer admin links |
| `screens/mod-enrolmethods.html` | Modal · Enrolment methods | `MOD.ENROL` — the participants modal's 4th tab: cohort + method + role, asynchronous bulk action |
| `screens/mod-delplans.html` | Modal · Delete template with plans | `MOD.DELPLANS` — the two `has_related_data` paths |
| `screens/mod-detail.html` | Modal · Competency card (headless) | `MOD.DETAIL` — the card IS the dialog: core's header hidden, transparent content, a close button of its own |
| `screens/mod-structrelated.html` | Modal · Related-competency peek | `MOD.STRUCTREL` — the same detail card as the inline panel, counters muted, related section omitted |

**Coverage.** The **17 maps** below cover **every** admin surface: the 4 page surfaces
(`BAR`, `EST`, `FWK`, `PLN`), the **12** modals, and the **4** `dynamic_form` bodies. Fourteen of
them also have a screen. The ones that do not, do not by design: `MOD.USAGE` and `MOD.MOVETO` carry
no design decision (a `<li>` list; a `<label>` + a `<select>`), so drawing them would only add
surface to the kit; and the four form bodies borrow `modal-shell.html` as their shell, with the
bodies inventoried in `maps/mod-forms.md`. Where the coverage **does not** reach is recorded in
"Known blind spots", at the end of this file.

## Field maps (`maps/`)
An **as-is** inventory per surface: every element with a **stable ID**, its label (or `[no label]`),
type, **origin** (`mustache:line` or `amd` module), data and business rule. It answers the
"unlabelled field / I cannot find it in the code" question — a repo-side reference, no trip to Claude
Design required.

| File | Surface |
|---|---|
| `maps/bar-contextbar.md` | `BAR` · Contextbar |
| `maps/est-competencies.md` | `EST` · Competencies (+ the tree node) |
| `maps/fwk-structures.md` | `FWK` · Structures (+ the structure card) |
| `maps/pln-plans.md` | `PLN` · Learning plans tab (templates + competencies) |
| `maps/mod-browser.md` | `MOD.BROWSER` · Browse-structures modal |
| `maps/mod-links.md` | `MOD.LINKS` · Course↔activity links modal |
| `maps/mod-related.md` | `MOD.RELATED` · Related competencies modal |
| `maps/mod-rule.md` | `MOD.RULE` · Completion rule |
| `maps/mod-scale.md` | `MOD.SCALE` · Framework scale/proficiency |
| `maps/mod-participants.md` | `MOD.PART` · Participants modal (Cohorts / Users / Assign roles / Enrolment methods) |
| `maps/mod-enrolmethods.md` | `MOD.ENROL` · Enrolment methods |
| `maps/mod-delplans.md` | `MOD.DELPLANS` · Delete a template that has plans |
| `maps/mod-usage.md` | `MOD.USAGE` · Where the competency is used (**no screen** — see the map) |
| `maps/mod-moveto.md` | `MOD.MOVETO` · Move to position — one template, two callers (**no screen**) |
| `maps/mod-structrelated.md` | `MOD.STRUCTREL` · Related-competency peek modal |
| `maps/mod-forms.md` | The five `dynamic_form` bodies — `FORM-FWK` / `FORM-COMP` / `FORM-TPL` / `FORM-IMP` / `FORM-TPLIMP` (**no screen**; the shell is `modal-shell.html`) |
| `maps/mod-detail.md` | **`MOD.DETAIL`** · The competency card as a dialog (headless). The **template** is called `structure_related_modal.mustache` — whoever greps for that name lands here; the files (`maps/mod-detail.md`, `screens/mod-detail.html`) carry the **ID**, which `pln-plans.md` had already coined. It is the template name that aged, not the ID |

> **How a ref is derived.** Every `file:line` is obtained by **opening the file** and reading the
> block's boundaries (opens at the selector/symbol, closes at the brace), never by arithmetic over an
> earlier ref — a block deleted above shifts everything below it and an inherited ref lies without
> warning. **Core** refs (`core/modal`, `core/modal_save_cancel`, Boost, `lib/db/install.xml`) and
> `format_mtube` ones are cited **by symbol/selector, with no line number**: the plugin supports 4.5
> through 5.2 and the number would diverge between branches. The plugin refs are anchored against
> `d0adc3b`.

> **The kit is in English — four Portuguese strings survive on purpose.** They are quoted evidence,
> not leftovers, and a pass that "corrects" them desyncs a file from what it cites. (1) The roles
> pane's **"Público-alvo"** (`central_roles_col_cohort`, `central_roles_selectcohort`): the divergence
> from the uniform English 'Cohort' **is** the finding (`maps/mod-participants.md:50`). (2) `dialog
> "Comunicação Assertiva" modal` is a **measured** Chromium accessibility-tree output
> (`maps/mod-detail.md:157`, `screens/mod-detail.html:199`) — translating a measurement fabricates it.
> (3) The accent-folding examples in `MOD.LINKS` ("prova" finds "Prova diagnóstica";
> "pratica" finds "Prática") need an accented pair or they demonstrate nothing
> (`maps/mod-links.md:87`). (4) "João Silva", a sample person's name. Everything else — prose,
> labels and sample data alike — is English, and the labels are the values in
> `lang/en/local_dimensions.php`, never translations of the pt_br ones.

### ID convention
Format `PREFIX-SECTION-NN`. Prefixes: `BAR` (contextbar), `EST`/`FWK`/`PLN` (tabs),
`MOD.{BROWSER,LINKS,RELATED,RULE,SCALE,DELPLANS,USAGE,MOVETO,DETAIL,STRUCTREL}` (modals) and
`FORM-{FWK,COMP,TPL,IMP}` (the four `dynamic_form` bodies). The participants family is the one
exception to the shape: the **panes** are `MOD.PART` / `MOD.COHORT` / `MOD.ROLES` / `MOD.ENROL`,
while the elements inside them badge with the short `PART-` / `COHORT-` / `ROLES-` / `ENROL-`.
Every interactive element and every static region with meaning (headings, empty states, counters)
gets an ID; pure layout wrappers do not. IDs are stable — they do not change when the screen is
reordered.

> **Why `EST` labels "Competencies" and `FWK` labels "Structures".** Moodle's internal names and its
> visible labels are inverted here: the `structure` tab renders **Competencies**
> (`managecompetencies_structure`, `lang/en:487`) and the `frameworks` tab renders **Structures**
> (`central_frameworks_tab`, `lang/en:164`). Card names, map titles and file names follow the **label**,
> because that is what a reviewer sees on screen. The **ID prefixes follow the code**, and they are
> frozen: `EST` is the competency tree, `FWK` is the structure list. Read the prefix as an opaque key,
> not as an abbreviation of the label.

**One element, one ID.** The trigger belongs to the surface **it lives on**; the modal's map
**references** it instead of coining a second ID (the pattern: `MOD.DELPLANS` ← `PLN-DELETE`).
Coining a new ID for an already-mapped element breaks the stability the convention exists to
guarantee — and leaves the origin map's reference pointing at nothing. For example, the counters that
open `MOD.USAGE` are `EST-DETAIL-COURSES/-ACTIVITIES/-PLANS` and stay in `est-competencies.md`; and
`MOD.DETAIL` keeps the ID `pln-plans.md` coined instead of becoming `MOD.STRUCTRELATED` after the
template name.

`IMP-*` and `D*` are a **separate namespace** — the improvement and decision tags of the
`format_mtube` alignment plan, which lives outside this repo. They are not element IDs and they are
not indexed here; maps, screens and components cite them where a shipped behaviour came from one
(`IMP-03`, `IMP-05`, `IMP-10`, `D2`, `D5`), where the shipped surface is a deliberate
**counter-example** to one (`IMP-06`, cited twice in `screens/mod-browser.html`: the footer is no
ghost, so the call is reused and the save wiring is not), or where one is still **Pending**
(`IMP-11`).

## How to sync with Claude Design
The **DesignSync** tool reads and writes the project:
1. `list_projects` to find the `projectId`.
2. `finalize_plan` (pass `writes` **and** `deletes`, even when `[]`) with `localDir` pointing at this
   folder → returns a `planId`.
3. `write_files` with `localPath` (relative to `localDir`); the contents never enter the model's context.

The `.html` files from `screens/` and the components go up as cards; the `.md` files in `maps/` stay
in the repo only. Cards are built from each preview's first-line `@dsCard` marker, so
`register_assets` is not needed. Alternative: the interactive `claude` terminal with `/design-login`,
or importing through the Claude Design UI.

## Moodle DS alignment (Layer 3)
See [`moodle-ds-alignment.md`](moodle-ds-alignment.md): good practices captured from
`moodlehq/design-system` + the Component Library, the `MDS → Boost/Bootstrap → MDS React (Moodle 5.3 LTS)`
mapping, and the table of **divergences** from the earlier interpretation. All **12** previews
reference the `--mds-*` tokens (the legacy names survive as deprecated aliases), and the `screens/`
draw the shipped output in the Moodle (Boost) palette itself — **Layer 3 complete**.

## Pending
What the kit records as designed-but-**not-built**. Every item carries its evidence of absence in the
file it lives in; this is only the index.

| Where it is recorded | Pending |
|---|---|
| `form-section.html` | **A description line per section.** Core has no category description (a description is *per field*) and the plugin passes `''` everywhere. |
| `form-section.html` | **A colour swatch beside the value.** Both colour fields are plain `text`; nothing in `styles.css` or `amd/src` paints a swatch in the form. |
| `image-dropzone.html` | **The compact one-line card** (thumbnail + file name side by side, to fit a modal body). No template and no class of its own. |
| `hierarchy-nav.html` · `maps/bar-contextbar.md` | **The three-segment adaptive trail** (Context → Structure → Competency). Not built — and, unlike the rest of the kit, a **divergent** redesign of the shipped bar rather than an increment. |
| `paginated-picker.html` | **A structure filter beside the search.** `search_competencies::execute_parameters()` declares only `query`, `limitfrom`, `limitnum` — a new parameter plus a `version.php` bump. |
| `paginated-picker.html` | **Multiple selection by checkbox.** The autocomplete is enhanced with `multiple = false`; today's multi-selection is by accumulating chips. |
| `cohort-assign.html` · `maps/mod-participants.md` | **The Cohorts tab in the "group management" style** (checkbox list, per-row sync pill, plans roll-up, bulk apply). The shipped tab is the 4-column table. |
| `modal-shell.html` | **Refresh/expand at the other 15 construction points.** The header controls reached only the 2 dense modals; the other 15 import neither module. |
| `states.html` · `maps/mod-participants.md` | **The first-paint placeholder (`IMP-11`) and the ARIA quartet** for the lazily mounted panes. Of the quartet, only `aria-busy` shipped, and on the cover. |
| `maps/mod-participants.md` | **Icons on the participants modal's own four tabs.** The hub's *page* tabs have had a glyph since `514d246`; these four are bare strings. |
| `maps/mod-scale.md` · `screens/mod-scale.html` | **The scale state as a pill.** The template still carries the raw radio and checkbox; and no "Initial" string exists in either language. |
| `maps/mod-rule.md` · `screens/mod-rule.html` | **Outcome and rule side by side**, **the rule as a sentence** ("needs N of M points") and **"When" instead of "Rule"**. All three lack markup or a string to lean on. |
| `maps/mod-delplans.md` · `screens/mod-delplans.html` | **A danger pair for the destructive checked state.** Checking "Delete the plans" paints the box the same blue as "Unlink"; there is no variant scoped to `value="delete"`. |

## Known blind spots
The **two** gaps the audit exposed — both **closed** (2026-07-17):

1. ✅ **~~No `dynamic_form` body is mapped~~ — CLOSED.** The bodies got a map in
   [`maps/mod-forms.md`](maps/mod-forms.md) (full fidelity: `FORM-FWK`/`FORM-COMP`/`FORM-TPL`/`FORM-IMP`,
   and `FORM-TPLIMP` since the CSV transfer,
   field inventory, gating, validation and design controls), and `MOD.SCALE`'s provisional IDs migrated
   there (`FORM-FWK-SCALE-*`). In the same pass, `structure_related_modal` — the only modal without a
   map — got [`maps/mod-structrelated.md`](maps/mod-structrelated.md). The kit's maps now cover **every**
   admin surface.
2. ✅ **~~The toast became the house confirmation pattern and is in no component~~ — CLOSED.**
   Now modelled in [`toast.html`](toast.html): the four types (success/info/warning/danger), the
   **region hosted in the modal body** (`addToastRegion(modal.getBody()[0])` on `ModalEvents.shown`,
   because the page's `.toast-wrapper` is `z-index:1051` and the modal is `1055` — the toast would land
   **behind** it; core removes the region itself on close) and the **pair with the flash** for in-place
   changes. Wired in `competency_links`, `participants_manager`, `related_competencies` and
   `frameworks_export`. In the same pass, `MOD.STRUCTREL` got a screen in
   [`screens/mod-structrelated.html`](screens/mod-structrelated.html).

## Mapping to code
- Each component → a **Mustache** partial + **SCSS (Boost)** styles; this kit's tokens → SCSS variables.
- The modals use `core_form\dynamic_form` through `core_form/modalform`; lists/tree/picker use `core/ajax`
  with **server-side pagination** and **lazy loading** (see the redesign spec, section 9.5 — local, not versioned).
