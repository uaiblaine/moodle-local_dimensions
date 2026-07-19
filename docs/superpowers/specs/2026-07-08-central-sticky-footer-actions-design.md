# Design: sticky-footer action bar for the Central hub (Structure & Plans)

- **Date:** 2026-07-08
- **Component:** `local_dimensions`
- **Status:** approved design, pending implementation plan
- **Scope:** Structure tab + Plans tab of the Competency hub (`central.php`). Frameworks tab and
  per-competency-row actions are explicitly out of scope.

## 1. Goal

Move the *selection-scoped* action buttons out of the in-container detail panes and into a
single Moodle-core **sticky footer** (`core/sticky-footer`) pinned to the viewport bottom, so the
hub matches the core admin UX (course editing, gradebook, etc.). When a competency (Structure) or
a learning-plan template (Plans) is selected, its actions appear in the shared footer bar.

### Non-goals
- No change to the Frameworks tab.
- No change to per-competency-row actions in Plans (the row kebab: edit / move / remove / drag).
- No change to any web service, data layer, or PHPUnit-covered logic.
- No use of `core/bulkactions` (see §3).

## 2. Investigation summary (facts this design relies on)

- **Version support:** `core/sticky-footer` (JS module, `core\output\sticky_footer` renderable,
  `core/sticky_footer` template) is **byte-identical from v4.5.0 through v5.2.x** — verified with
  `git tag --contains` against the release tags. No version gating is required. Reference by AMD
  **module name** only (`core/sticky-footer`); the physical path moved from `lib/` to `public/lib/`
  in 5.0 but module resolution is unaffected.
- **Primitive choice — `core/sticky-footer`, not `core/bulkactions`.** `bulkactions` is a
  multi-select framework (checkbox "select many → act on all", "N selected" counter, per-set
  deselect); our case is a single-selected-item contextual bar. Core's own contextual footers
  (course-index `bulkedittools.js`, QuickForms `moodleform::add_sticky_action_buttons`) drive
  `core/sticky-footer` directly and bypass `bulkactions`. We follow that precedent.
- **Contract:** `new core\output\sticky_footer($stickycontent, $stickyclasses, $attributes)` renders
  `<div id="sticky-footer" class="stickyfooter">…{{{stickycontent}}}…</div>`, `position: fixed` at
  the viewport bottom, **one per page, full-page** (not container-scoped). JS API from
  `core/sticky-footer`: `enableStickyFooter()` / `disableStickyFooter()`; content is set by swapping
  `#sticky-footer` innerHTML. Rendering with `data-disable="true"` keeps it hidden until enabled.
  Under Behat it degrades to an inline, clickable div (init skipped on `body.behat-site`).
- **Host:** `central.php:106-109` emits `header()` → contextbar → `core/dynamic_tabs` → `footer()`.
  The renderable is emitted between the tabs and `footer()`, so it is page-level and survives tab
  switches and per-tab `reloadPane` fragment reloads.
- **Tabs:** the hub uses `core/dynamic_tabs` (lazy `core_dynamic_tabs_get_content`) with Bootstrap
  tab events; `amd/src/central/context.js:275` already listens on `shown.bs.tab`. That is the
  tab-handoff hook.

## 3. Current state being replaced

- **Structure** (`templates/central/structure.mustache`, detail pane): a `{{#canmanage}}`-gated row
  of **8 CRUD buttons** — `edit`, `addchild`, `rules`, `links`, `related`, `moveup`, `movedown`,
  `delete`. Also present but **staying put:** the 3 usage counters (courses/activities/plans) and
  the tree-toolbar "Add competency". Detail pane is JS-driven; `selectRow` (`structure.js`) reveals
  it. One delegated click listener on `[data-region="structure"]`; selection-scoped actions run
  through `handleDetailAction(...)`, keyed by `data-action`, operating on module-level `activeRow`.
- **Plans** (`templates/central/plans.mustache`): the footer bar (Edit details · Add competency ·
  Manage participants · More → Duplicate/Delete), gated `{{#canmanage}}`, is only *flex-pinned*
  inside the detail card (not sticky). One delegated listener on `[data-region="plans"]`; actions
  keyed by `data-action` in `ACTION_HANDLERS`, operating on `pane.dataset.templateid`.

Both tabs' handlers act on **module state, not DOM position**, so relocating the buttons needs no
handler rewrite — only a way to route the footer's clicks into the existing dispatch.

## 4. Architecture

Approach A (shared page-level footer + a small coordinator). Components:

| Piece | Change |
|---|---|
| `central.php` | Emit one `core\output\sticky_footer('', 'justify-content-end', ['data-disable' => 'true'])` between the `core/dynamic_tabs` render and `$OUTPUT->footer()`. |
| `amd/src/central/action_footer.js` *(new)* | Coordinator owning `#sticky-footer`. Public API: `show(html, dispatch)`, `hide()`, `init()`. |
| `templates/central/structure_footer_actions.mustache` *(new)* | The 8 CRUD buttons with their existing `data-action` values, wrapped in `{{#canmanage}}`. Rendered client-side. |
| `amd/src/central/structure.js` | Extract the selection-scoped dispatch into `dispatchStructureAction(target)`; call it from both the region listener and the footer. In `selectRow`: render the partial and `actionFooter.show(html, dispatchStructureAction)`; on no-selection, `actionFooter.hide()`. Delete the CRUD row markup from `structure.mustache`. |
| `templates/central/plans.mustache` | Move the footer-bar markup out of the card into a hidden holder (`[data-region="plans-footer-actions"] hidden`); the competency list becomes the card's bottom band. |
| `amd/src/central/plans.js` | Extract the click dispatch into `dispatchPlansAction(target)`. In `init`: read the holder's innerHTML and `actionFooter.show(html, dispatchPlansAction)`. |
| `styles.css` | Remove the plans flex-footer rules; re-verify the competency-list scroll math and the expanded-description `max-height` (§7). |
| `version.php` | Bump the version (new JS module + template; no db/services change). |

### 4.1 `action_footer.js` — the coordinator

Single owner of `#sticky-footer`, so the two tabs never fight over it.

```
let currentDispatch = null;

show(html, dispatch):
    footer = document.getElementById('sticky-footer')
    if (!footer) return                      // page didn't render one (defensive)
    footer.querySelector('.sticky-footer-content').innerHTML = html
    currentDispatch = dispatch
    enableStickyFooter()

hide():
    currentDispatch = null
    disableStickyFooter()
    clear the .sticky-footer-content innerHTML

init():                                        // called once, page-level
    footer.addEventListener('click', (e) => {
        const target = e.target.closest('[data-action]')
        if (target && currentDispatch) currentDispatch(target, e)
    })
    // tab handoff: reset when the user leaves the current tab
    document.addEventListener('hidden.bs.tab', () => hide())
```

- **One delegated listener**, routing to whichever tab most recently called `show`. No per-button
  wiring; no cross-tab listener collisions.
- `init()` is invoked once at page load (from `central.php` via `js_call_amd`, or from the contextbar
  init which is already page-level). Confirm the single init site during implementation.

### 4.2 Structure integration

- New partial `central/structure_footer_actions` holds the 8 buttons (same `data-action`s, same
  `{{#canmanage}}` gate). It receives `{canmanage, ...}` and the per-action dataset values already
  available to `selectRow`.
- `selectRow(region, row)`:
  - if a competency is selected **and** `canmanage`: `Templates.renderForPromise('local_dimensions/central/structure_footer_actions', {...})` then `actionFooter.show(html, dispatchStructureAction)`. Async render is guarded the same way the description render already is — only apply if the row is still `.active`.
  - on the empty state (no selection): `actionFooter.hide()`.
- `dispatchStructureAction(target)` = the current `handleDetailAction` body, extracted so the region
  listener and the footer share it. `activeRow` / `frameworkid` remain module-scoped, unaffected.
- The 3 usage counters stay in the detail pane (they are metrics, and their counts are injected by
  `selectRow` into `detail-*` regions — untouched).

### 4.3 Plans integration

- `plans.mustache` renders the same footer-bar buttons, but inside a hidden holder outside the card
  flow. The More-actions dropdown keeps its BS4+BS5 dual `data-toggle`/`data-bs-toggle` and dual
  alignment classes verbatim (moving the *markup* wholesale preserves the 4.5 gotcha fix).
- `plans.js init()`: `actionFooter.show(holder.innerHTML, dispatchPlansAction)`. Because `init`
  re-runs after every `reloadPane`, the footer is refreshed whenever the selected template changes.
- `dispatchPlansAction(target)` = the existing `closest('[data-action]')` → `ACTION_HANDLERS[...]`
  lookup, extracted so the region listener and the footer share it. Per-target dataset
  (`data-id` / `data-name` / `data-plancount`) travels with the markup.

### 4.4 Visibility & tab handoff

- **Structure:** footer shown only when a competency is selected **and** `canmanage`; hidden on the
  empty state. Non-managers: the partial renders nothing (gate), so `show` injects an empty bar —
  guard by not calling `show` when `!canmanage`.
- **Plans:** footer shown whenever a template is selected **and** `canmanage` (≈ always, since the
  server auto-selects a template). Non-managers: holder is absent (server gate) → skip `show`.
- **Handoff:** `hidden.bs.tab` → `actionFooter.hide()` (coordinator). The entering tab's `init`
  (Plans) or `selectRow` (Structure) re-`show`s if it has a selection. Leaving to the out-of-scope
  Frameworks tab therefore clears the footer with no Frameworks-side code.

## 5. Data flow / lifecycle

1. Page load → `action_footer.init()` binds the delegated listener + `hidden.bs.tab` reset.
2. Structure select → render partial → `show(html, dispatchStructureAction)` → footer slides up.
3. Footer click → coordinator routes `target` to `dispatchStructureAction` → existing handler runs
   on `activeRow`.
4. Deselect / tab change → `hide()`.
5. Plans tab → `init` → `show(holderHtml, dispatchPlansAction)`; template change → `reloadPane` →
   `init` re-runs → footer refreshed.

## 6. Testing

- **Behat:** buttons move out of the detail pane / card. Per the CLAUDE.md rule, grep
  `tests/behat/` for every moved label/`data-action` and fix the affected `.feature` steps in the
  same change. Buttons are matched by label, so most steps keep working, but:
  - The footer degrades to an inline clickable div on `behat-site` (no fixed overlay), so scenarios
    still reach the buttons.
  - Plans More-actions menu keeps its dual BS4/BS5 attributes — the 4.05 leg must still open it.
  - Progressive disclosure: the footer appears on selection; scenarios already select first.
- **PHPUnit:** unaffected — no data-layer change.
- **Lint/build:** `npx eslint --max-warnings 0` on the changed/new modules; `grunt amd` to rebuild
  `amd/build/**`; stylelint constraints (no `!important`, no `clamp()` in height) respected in the
  CSS cleanup.

## 7. CSS cleanup (Plans)

Removing the in-card footer changes the plans detail-card flex math:
- `styles.css:3361-3369` — the competency-list scroll region assumed a fixed footer *after* it in
  flow. With the footer gone, the list becomes the card's bottom band; re-verify it still scrolls
  and the card fills its height.
- `styles.css:~3312` — the expanded-description `max-height: calc(var(--local-dimensions-plans-body-height,60vh)*0.4)`
  still holds (keys off the body height, not the footer), but re-check now that the footer no longer
  consumes card height.
- The structure detail pane loses its CRUD button row; verify spacing of what remains.

## 8. Compatibility

No version gating. `core/sticky-footer` is stable and identical across the whole 4.5–5.2 support
window. Referenced by module name. The Return-to-Plan FAB is a learner-page feature and never
renders on the admin hub, so there is no fixed-element conflict.

## 9. Risks / open questions (resolved during implementation)

1. **Dropdown direction:** the Plans More-actions menu must open *upward* inside a bottom-fixed
   footer — apply `dropup` and check z-index against the footer (`stickyfooter` sits at theme
   `level-3`; the row menus already bump to 1021).
2. **Single `init()` site:** confirm the one page-level place to call `action_footer.init()` (likely
   alongside the contextbar init, which is already page-level).
3. **`central.php` render position:** confirm emitting the renderable between the tabs and
   `footer()` yields correct DOM ordering for the theme's sticky-footer region.
4. **Empty-content guard:** never call `show()` with empty HTML (non-manager path) — gate on
   `canmanage` / holder presence.

## 10. Rollout

Single logical change: new module + partial, edits to `structure.js` / `plans.js` /
`structure.mustache` / `plans.mustache` / `central.php` / `styles.css`, Behat updates, `grunt amd`
rebuild, `version.php` bump. Delivered as one commit (per the repo's direct-to-`main` convention),
then a versioned `git archive` zip for test install.
