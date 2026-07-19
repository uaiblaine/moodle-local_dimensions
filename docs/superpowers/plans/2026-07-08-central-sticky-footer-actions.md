# Central hub sticky-footer action bar — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move the selection-scoped action buttons of the Structure and Plans tabs out of their in-container panes into a single Moodle-core `core/sticky-footer`, matching core admin UX.

**Architecture:** One page-level `core\output\sticky_footer` rendered (disabled) in `central.php`. A new `local_dimensions/central/action_footer` coordinator owns `#sticky-footer` (enable/disable + `innerHTML` swap + one delegated click listener + a `show.bs.tab` reset). Structure feeds it a client-rendered partial on selection; Plans feeds it its server-rendered footer markup on `init`. Existing click handlers are reused via a dispatch callback.

**Tech Stack:** Moodle AMD (ESM) modules, Mustache, `core/sticky-footer`, `core/templates`, PHP output renderables.

**Design reference:** `docs/superpowers/specs/2026-07-08-central-sticky-footer-actions-design.md`

---

## Verification note (read first)

This checkout has **no Moodle install** — PHPUnit and Behat **cannot run locally** (see the `dimensions-dev-environment` memory). Local verification is limited to:

- `cd /Volumes/N1TB/dev/github/moodle && npx grunt amd --root=public/local/dimensions` (build)
- `npx eslint --max-warnings 0 <changed amd/src files>` (run from the Moodle root)
- `npx stylelint styles.css` (from the plugin dir, after `npm install`; treat its output as advisory — CI uses Moodle's own stricter config, so match existing passing patterns in the same file)
- phpcs is eyeballed against the CLAUDE.md rules (line length ≤132, inline-comment style)

Behat/PHPUnit are verified **in CI** and manually on the user's site via a `git archive` zip install. So each task ends with **static** checkpoints; runtime behaviour is confirmed by the user after the final zip. Commits are **batched into one** at the end (Task 7) per this repo's one-clean-commit convention — do **not** commit per task.

Key confirmed facts used below:
- `core/sticky-footer` exports `enableStickyFooter()` / `disableStickyFooter()` only; there is **no** content-container getter. Both the Boost and core-fallback templates render `id="sticky-footer"`; `core/bulkactions` sets `#sticky-footer.innerHTML` **wholesale** (supplying its own inner layout) — we do the same, which is theme-agnostic.
- `core\output\sticky_footer::__construct(string $content = '', ?string $classes = null, array $attrs = [])`; `set_auto_enable(false)` makes the template emit `data-disable="true"` so it loads hidden.
- `core/dynamic_tabs` re-fetches and **re-runs a tab's `init` on every entry** (`shown.bs.tab → loadTab → Templates.replaceNodeContents(..., js)`), and **clears the previous tab's content on `show.bs.tab`**. The page-level `#sticky-footer` lives in `central.php` (outside any tab pane), so it survives. Tab-toggle selector: `.dynamictabs a[data-bs-toggle="tab"]`.

---

## File structure

| File | Responsibility |
|---|---|
| `central.php` | Render one page-level `sticky_footer` (disabled) + init the coordinator. |
| `amd/src/central/action_footer.js` *(new)* | Own `#sticky-footer`: `show(html, dispatch)`, `hide()`, `init()`. |
| `templates/central/structure_footer_actions.mustache` *(new)* | The 8 Structure CRUD buttons in sticky-footer layout, gated on `canmanage`. |
| `templates/central/structure.mustache` | Remove the in-pane CRUD button row. |
| `amd/src/central/structure.js` | Extract `dispatchStructureAction`; render+show the footer on select, hide on deselect. |
| `templates/central/plans.mustache` | Move the footer bar into a hidden holder; add `dropup` to the More menu. |
| `amd/src/central/plans.js` | Extract `dispatchPlansAction`; `show` the footer from the holder in `init`. |
| `styles.css` | Remove the plans flex-footer rules; re-verify scroll math; footer z-index if needed. |
| `version.php` | Bump to `2026070805`. |
| `tests/behat/*.feature` | Update steps for buttons that moved to the footer. |

---

## Task 1: Page-level sticky footer + coordinator module

**Files:**
- Create: `amd/src/central/action_footer.js`
- Modify: `central.php` (after the `core/dynamic_tabs` render, ~line 108; and the requires block ~line 57)

- [ ] **Step 1: Create the coordinator module**

Create `amd/src/central/action_footer.js`:

```javascript
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Shared owner of the page-level sticky footer for the Competency hub.
 *
 * The hub is one page with dynamic tabs, and Moodle allows a single sticky
 * footer per page, so both the Structure and Plans tabs drive this one surface
 * through here. The active tab calls show() with its rendered button markup and
 * a dispatch callback; a single delegated click listener routes footer clicks to
 * whichever dispatch is current. Switching tabs clears the footer.
 *
 * @module     local_dimensions/central/action_footer
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {enableStickyFooter, disableStickyFooter} from 'core/sticky-footer';

const FOOTER_ID = 'sticky-footer';
const TAB_TOGGLE = '.dynamictabs a[data-bs-toggle="tab"]';

/** @type {Function|null} Dispatch for the currently shown tab's footer. */
let currentDispatch = null;

/** @type {boolean} Guard so init() binds its listeners only once. */
let initialised = false;

/**
 * The page-level sticky footer element, or null if the page rendered none.
 *
 * @return {HTMLElement|null}
 */
const getFooter = () => document.getElementById(FOOTER_ID);

/**
 * Fill the footer with the given markup and reveal it.
 *
 * @param {String} html Rendered, trusted button markup (sticky-footer inner layout).
 * @param {Function} dispatch Called with (target, event) for a footer [data-action] click.
 */
export const show = (html, dispatch) => {
    const footer = getFooter();
    if (!footer) {
        return;
    }
    footer.innerHTML = html;
    currentDispatch = dispatch;
    enableStickyFooter();
};

/**
 * Clear the footer and hide it.
 */
export const hide = () => {
    const footer = getFooter();
    currentDispatch = null;
    disableStickyFooter();
    if (footer) {
        footer.innerHTML = '';
    }
};

/**
 * Bind the page-level listeners once. Safe to call multiple times.
 */
export const init = () => {
    if (initialised) {
        return;
    }
    initialised = true;
    const footer = getFooter();
    if (!footer) {
        return;
    }
    footer.addEventListener('click', (event) => {
        const target = event.target.closest('[data-action]');
        if (target && currentDispatch) {
            currentDispatch(target, event);
        }
    });
    // Any tab switch clears the footer for a clean slate; the entering tab's own
    // init re-asserts it (dynamic tabs re-run init on every entry). This also
    // covers the Frameworks tab, which has no footer, with no Frameworks code.
    document.querySelectorAll(TAB_TOGGLE).forEach((toggle) => {
        toggle.addEventListener('show.bs.tab', () => hide());
    });
};
```

- [ ] **Step 2: Render the sticky footer + init the coordinator in `central.php`**

In `central.php`, add the coordinator init next to the existing page-level init (after `central.php:57`):

```php
$PAGE->requires->js_call_amd('local_dimensions/central/context', 'init');
$PAGE->requires->js_call_amd('local_dimensions/central/action_footer', 'init');
```

Then render the footer between the tabs and the page footer. Change:

```php
echo $OUTPUT->render_from_template('core/dynamic_tabs', $tabsdata);
echo $OUTPUT->footer();
```

to:

```php
echo $OUTPUT->render_from_template('core/dynamic_tabs', $tabsdata);

// One page-level sticky footer shared by the Structure and Plans tabs; rendered
// disabled so it stays hidden until a tab enables it on selection.
$stickyfooter = new \core\output\sticky_footer();
$stickyfooter->set_auto_enable(false);
echo $OUTPUT->render($stickyfooter);

echo $OUTPUT->footer();
```

- [ ] **Step 3: Build and lint**

Run:
```bash
cd /Volumes/N1TB/dev/github/moodle
npx grunt amd --root=public/local/dimensions
npx eslint --max-warnings 0 public/local/dimensions/amd/src/central/action_footer.js
```
Expected: build writes `amd/build/central/action_footer.min.js` + `.map`; eslint exits 0.

- [ ] **Step 4: Manual DOM check (optional, if a test site is handy)**

Load the hub; confirm `#sticky-footer.stickyfooter[data-disable]` exists in the DOM and is hidden. No behaviour yet.

---

## Task 2: Structure — footer partial, dispatch extraction, select/deselect wiring, remove in-pane row

**Files:**
- Create: `templates/central/structure_footer_actions.mustache`
- Modify: `amd/src/central/structure.js` (imports; `handleDetailAction` → `dispatchStructureAction`; `selectRow` ~lines 432-495; the empty-state path)
- Modify: `templates/central/structure.mustache` (remove lines 222-249, the `{{#canmanage}}` CRUD row)

- [ ] **Step 1: Create the footer partial**

Create `templates/central/structure_footer_actions.mustache`:

```mustache
{{!
    This file is part of Moodle - http://moodle.org/

    Moodle is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Moodle is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
}}
{{!
    @template local_dimensions/central/structure_footer_actions

    CRUD actions for the selected competency, rendered into the page sticky footer.
    Buttons act on the module-level active row, so they carry no per-item dataset.

    Context variables required for this template:
    * canmanage (bool) - whether the user may manage the framework

    @copyright  2026 Anderson Blaine
    @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

    Example context (json):
    {
        "canmanage": true
    }
}}
<div class="sticky-footer-content-wrapper h-100 d-flex justify-content-center">
    <div class="sticky-footer-content w-100 d-flex flex-wrap align-items-center justify-content-end gap-2 px-3 py-2">
        {{#canmanage}}
        <button type="button" class="btn btn-sm btn-primary" data-action="edit">
            <i class="fa fa-pencil me-1" aria-hidden="true"></i>{{#str}}edit{{/str}}
        </button>
        <button type="button" class="btn btn-sm btn-secondary" data-action="addchild">
            <i class="fa fa-plus me-1" aria-hidden="true"></i>{{#str}}managecompetencies_addchild, local_dimensions{{/str}}
        </button>
        <button type="button" class="btn btn-sm btn-secondary" data-action="rules">
            <i class="fa fa-list me-1" aria-hidden="true"></i>{{#str}}competencyrule, tool_lp{{/str}}
        </button>
        <button type="button" class="btn btn-sm btn-secondary" data-action="links">
            <i class="fa fa-link me-1" aria-hidden="true"></i>{{#str}}central_links_button, local_dimensions{{/str}}
        </button>
        <button type="button" class="btn btn-sm btn-secondary" data-action="related">
            <i class="fa fa-exchange me-1" aria-hidden="true"></i>{{#str}}central_related_button, local_dimensions{{/str}}
        </button>
        <button type="button" class="btn btn-sm btn-secondary" data-action="moveup" title="{{#str}}moveup{{/str}}">
            <i class="fa fa-arrow-up" aria-hidden="true"></i><span class="visually-hidden">{{#str}}moveup{{/str}}</span>
        </button>
        <button type="button" class="btn btn-sm btn-secondary" data-action="movedown" title="{{#str}}movedown{{/str}}">
            <i class="fa fa-arrow-down" aria-hidden="true"></i><span class="visually-hidden">{{#str}}movedown{{/str}}</span>
        </button>
        <button type="button" class="btn btn-sm btn-outline-danger" data-action="delete">
            <i class="fa fa-trash me-1" aria-hidden="true"></i>{{#str}}delete{{/str}}
        </button>
        {{/canmanage}}
    </div>
</div>
```

- [ ] **Step 2: Extract the dispatch and import the coordinator in `structure.js`**

Add the import (near the other `local_dimensions/central/*` imports):

```javascript
import * as ActionFooter from 'local_dimensions/central/action_footer';
```

The current selection-scoped dispatch lives inside `handleDetailAction(region, target)` (`structure.js:1116`). Wrap the existing body in a function that takes just the clicked `target` (it already reads `region`/`frameworkid` from module/closure scope — keep those). Concretely, define:

```javascript
/**
 * Route a footer/detail [data-action] click to the matching handler. Operates on
 * the module-level active row, so it works regardless of where the button lives.
 *
 * @param {HTMLElement} target The clicked [data-action] element.
 */
const dispatchStructureAction = (target) => {
    handleDetailAction(region, target);
};
```

If `region` is not already in scope where the footer needs it, capture the tab region once in `init` (it is already resolved there as `region`) into a module-level `let activeRegion` and use it. Keep the existing region-listener path calling `handleDetailAction` unchanged.

> Implementer note: `structure.js` uses an `if/else if` chain, not an ACTION_HANDLERS map. `handleDetailAction` already encapsulates the selection-scoped subset (edit/addchild/rules/links/related/moveup/movedown/delete). Reusing it verbatim is the goal — do **not** duplicate the chain.

- [ ] **Step 3: Show/hide the footer from `selectRow` and the empty state**

In `selectRow` (`structure.js:432`), after the row is marked active and the detail content is populated, render the partial and show the footer. Add near the end of `selectRow`:

```javascript
// Mirror the selected competency's CRUD actions into the shared sticky footer.
if (row.dataset.canmanage === '1' || CAN_MANAGE) {
    Templates.renderForPromise('local_dimensions/central/structure_footer_actions', {
        canmanage: true,
    }).then(({html}) => {
        if (row.classList.contains('active')) {
            ActionFooter.show(html, dispatchStructureAction);
        }
        return null;
    }).catch(Notification.exception);
} else {
    ActionFooter.hide();
}
```

> `canmanage` is a single region-level flag in this tab (server emits it once; the buttons were gated by `{{#canmanage}}` around the whole row, not per row). Capture it in `init` from `region.dataset` (add `data-canmanage` to the region if not already present — see structure.php export) into a module const `CAN_MANAGE`, and use that instead of a per-row dataset. Remove the `row.dataset.canmanage` fallback once `CAN_MANAGE` is wired.

Wherever the tab returns to the empty state (no selection) — the code path that sets `detailEmpty.hidden = false` / `detailContent.hidden = true` — call `ActionFooter.hide();`.

- [ ] **Step 4: Remove the in-pane CRUD row from `structure.mustache`**

Delete lines `222-249` of `templates/central/structure.mustache` (the entire `{{#canmanage}} … {{/canmanage}}` block containing the 8 buttons). Leave the usage-counter row and everything else intact.

- [ ] **Step 5: Build and lint**

```bash
cd /Volumes/N1TB/dev/github/moodle
npx grunt amd --root=public/local/dimensions
npx eslint --max-warnings 0 public/local/dimensions/amd/src/central/structure.js public/local/dimensions/amd/src/central/action_footer.js
```
Expected: eslint 0. Watch for `promise/no-nesting` — the `renderForPromise(...).then(...)` chain must not nest another `.then` (it doesn't here).

---

## Task 3: Plans — hidden holder, dispatch extraction, show on init, dropup

**Files:**
- Modify: `templates/central/plans.mustache` (the footer band, ~lines 393-429)
- Modify: `amd/src/central/plans.js` (imports; the click listener ~838-847 → `dispatchPlansAction`; `init` ~763)

- [ ] **Step 1: Move the footer bar into a hidden holder + add `dropup`**

In `plans.mustache`, the footer band is the `{{#canmanage}}`-gated `card-body pt-2 flex-grow-0 border-top` at ~line 393. Replace that in-card band with a **hidden holder placed after the card** (still inside `[data-region="plans"]`), containing the same buttons. Concretely:

1. Remove the `card-body … border-top` footer band from inside the detail card.
2. After the detail card (still within the detail pane / plans region), add:

```mustache
{{#canmanage}}
<div data-region="plans-footer-actions" hidden>
    <div class="sticky-footer-content-wrapper h-100 d-flex justify-content-center">
        <div class="sticky-footer-content w-100 d-flex flex-wrap align-items-center justify-content-end gap-2 px-3 py-2">
            {{! Paste the existing footer buttons verbatim: Edit details, Add competency,
                Manage participants, and the More-actions dropdown. Keep every data-action,
                data-id/data-name/data-plancount, and the dropdown's dual data-toggle +
                data-bs-toggle attributes and dropdown-menu-right dropdown-menu-end classes. }}
            {{! ADD class "dropup" to the More-actions .dropdown wrapper so the menu opens
                upward inside the bottom-fixed footer. }}
        </div>
    </div>
</div>
{{/canmanage}}
```

> Preserve the exact button markup from the current footer band (the investigation confirmed the dropdown relies on BS4+BS5 dual attributes). The only additions are the wrapper divs, `data-region="plans-footer-actions" hidden`, and `dropup` on the `.dropdown` wrapper.

- [ ] **Step 2: Extract the dispatch and import the coordinator in `plans.js`**

Add the import (near the other `local_dimensions/central/*` imports):

```javascript
import * as ActionFooter from 'local_dimensions/central/action_footer';
```

The click listener at `plans.js:838-847` does `event.target.closest('[data-action]')` → `ACTION_HANDLERS[action]`. Extract its body into:

```javascript
/**
 * Route a footer/region [data-action] click to its ACTION_HANDLERS entry.
 *
 * @param {HTMLElement} target The clicked [data-action] element.
 */
const dispatchPlansAction = (target) => {
    const action = target.dataset.action;
    const handler = ACTION_HANDLERS[action];
    if (handler) {
        handler(pane, region, target);
    }
};
```

Have the existing region listener call `dispatchPlansAction(target)` too (no behaviour change). `pane` and `region` must be in scope; capture them in `init` into module-level `let` values used by `dispatchPlansAction` (mirrors how `pane`/`region` are already resolved in `init`).

- [ ] **Step 3: Show the footer from `init`**

In `plans.js` `init` (`763`), after `pane`/`region` are resolved and the holder exists, feed the footer:

```javascript
const footerholder = region.querySelector('[data-region="plans-footer-actions"]');
if (footerholder) {
    ActionFooter.show(footerholder.innerHTML, dispatchPlansAction);
} else {
    ActionFooter.hide();
}
```

Because `init` re-runs after every `reloadPane` and on every tab entry, the footer stays in sync with the selected template and is asserted whenever the Plans tab is shown.

- [ ] **Step 4: Build and lint**

```bash
cd /Volumes/N1TB/dev/github/moodle
npx grunt amd --root=public/local/dimensions
npx eslint --max-warnings 0 public/local/dimensions/amd/src/central/plans.js
```
Expected: eslint 0.

---

## Task 4: CSS cleanup

**Files:**
- Modify: `styles.css` (the plans flex-footer rules; the competency-list scroll region ~3361-3369; the expanded-description rule ~3312)

- [ ] **Step 1: Remove obsolete plans flex-footer CSS**

Grep for rules that assumed the footer sat in-flow at the bottom of the plans card:
```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions
grep -nE "plans-scroll|plans-footer|flex-grow-0|border-top" styles.css
```
Remove/adjust any rule whose sole purpose was pinning the in-card footer. The competency-list scroll region (`.local-dimensions-central-plans-scroll`, ~3361-3369) should now be the card's bottom band — verify its `min-height: 0; overflow-y: auto` still yields a scrolling list that fills the card.

- [ ] **Step 2: Confirm the footer's dropdown clears other layers**

The `#sticky-footer` sits at the theme's `level-3` z-index. The plans row menus already bump to `1021`. Confirm the footer's More-actions `dropup` menu is not clipped; if needed add a scoped rule (no `!important`):
```css
#sticky-footer .dropdown-menu {
    z-index: 1056;
}
```
Only add this if a clipping issue is observed on the test site.

- [ ] **Step 3: Lint**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions
npx stylelint styles.css
```
Advisory only (see the Verification note). Ensure no `!important`, no `clamp()`/`min()`/`max()` in height-like properties, single-quoted attribute selectors — match the existing passing rules in this file.

---

## Task 5: Behat updates

**Files:**
- Modify: `tests/behat/*.feature` (any scenario that clicks a moved button)

- [ ] **Step 1: Find affected scenarios**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions
grep -rnE "Edit details|Add competency|Manage participants|Duplicate|Delete|Add child|Related|Links|competency rule|Move up|Move down" tests/behat/
```
For each hit, confirm whether the step targets a button that is now in `#sticky-footer`.

- [ ] **Step 2: Fix the steps**

The buttons keep their labels and `data-action`s, so `I click on "<label>" "button"` still resolves them (the button selector matches anywhere in the DOM). Two things to verify per the CLAUDE.md Behat gotchas:
- The footer is only present after a selection — ensure the scenario **selects a competency / template first** (add a `Then I should see …` barrier if the footer content is fetched async in Structure).
- The Plans More-actions menu keeps its dual `data-toggle`/`data-bs-toggle`; opening it via `I click on "More actions" "button"` must still work on the 4.05 leg.

There is no local Behat runner — budget **one CI fix-and-repush** for these (per CLAUDE.md).

- [ ] **Step 3: Guard against dev-leftover markers in edited files**

Scan the edited Behat/PHP files for stray to-do or merge-conflict markers (the CLAUDE.md leftover checker fails CI on them, docs included). Regex quantifiers keep the check itself free of any literal marker:
```bash
grep -rnE 'X{3}|<{7}|>{7}|={7}' tests/behat/ classes/ central.php
grep -rniE 'to''do|fix''me' tests/behat/ classes/ central.php
```
Expected: no matches.

---

## Task 6: Lang strings check

**Files:** `lang/en/local_dimensions.php`, `lang/pt_br/local_dimensions.php`

- [ ] **Step 1: Confirm no new strings are needed**

Every button reuses an existing string key (`edit`, `managecompetencies_addchild`, `competencyrule`, `central_links_button`, `central_related_button`, `moveup`, `movedown`, `delete`, and the plans footer's existing keys). No new lang strings are introduced. Verify:
```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions
grep -nE "central_links_button|central_related_button|managecompetencies_addchild" lang/en/local_dimensions.php
```
Expected: all present. If any button gains a new label during implementation, add it to **both** language files in the correct alphabetic slot.

---

## Task 7: Finalize — version bump, build, full lint, commit, zip

**Files:** `version.php`, `amd/build/**`

- [ ] **Step 1: Bump the version**

In `version.php`: `$plugin->version = 2026070804;` → `$plugin->version = 2026070805;`

- [ ] **Step 2: Full rebuild + lint**

```bash
cd /Volumes/N1TB/dev/github/moodle
npx grunt amd --root=public/local/dimensions
npx eslint --max-warnings 0 public/local/dimensions/amd/src/central/action_footer.js public/local/dimensions/amd/src/central/structure.js public/local/dimensions/amd/src/central/plans.js
```
Expected: build clean; eslint 0.

- [ ] **Step 3: phpcs eyeball on changed PHP**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions
awk 'length($0)>132{print FILENAME":"NR" ("length($0)")"}' central.php
grep -nE '^\s*// [a-z]' central.php | grep -vE ':([1-9]|1[0-5]):'
```
Expected: no line-length violations; only capitalised-first-line comment continuations, if any.

- [ ] **Step 4: Commit (single, per repo convention)**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions
git add -A
git commit -F - <<'EOF'
feat(central): move Structure & Plans actions into a core sticky footer

Relocate the selection-scoped action buttons out of the in-container detail
panes into one page-level core/sticky-footer, matching core admin UX.

- central.php renders a single disabled core\output\sticky_footer and inits a
  new local_dimensions/central/action_footer coordinator that owns it.
- The coordinator swaps #sticky-footer innerHTML + enable/disable, routes footer
  clicks to the active tab's dispatch, and clears on tab switch (dynamic tabs
  re-run each tab's init on entry, so the entering tab re-asserts).
- Structure renders its 8 CRUD buttons client-side into the footer on select
  (partial central/structure_footer_actions) and hides on deselect.
- Plans moves its footer bar into a hidden holder and feeds it to the footer on
  init; the More-actions menu gains dropup for the bottom-fixed bar.
- Existing click handlers reused via dispatch callbacks; no web-service change.
- styles.css drops the plans flex-footer rules; Behat steps updated for the
  relocated buttons.

version.php bumped for the AMD/template cache revision.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
```

> Only run the commit when the user asks (this repo's workflow is explicit commit control). Otherwise stop after Step 3 and report.

- [ ] **Step 5: Build the test zip**

```bash
PLUGINDIR=/Volumes/N1TB/dev/github/moodle/public/local/dimensions
ver=$(grep -oE '\$plugin->version[[:space:]]*=[[:space:]]*[0-9]+' "$PLUGINDIR/version.php" | grep -oE '[0-9]+')
git -C "$PLUGINDIR" archive --format=zip --prefix=dimensions/ HEAD -o "$HOME/Downloads/dimensions-$ver.zip"
```

- [ ] **Step 6: Hand to the user for on-site verification**

Ask the user to install the zip and confirm, on their site:
1. Selecting a competency (Structure) shows the CRUD footer; deselect hides it.
2. Selecting a template (Plans) shows the footer; the More menu opens upward.
3. Switching tabs clears/re-asserts the footer correctly (incl. entering Frameworks → footer gone).
4. Every action still works (edit/add/rules/links/related/move/delete; edit details/add competency/participants/duplicate/delete).
5. Non-managers see no footer.

---

## Self-review checklist (completed by the plan author)

- **Spec coverage:** every spec section maps to a task — renderable + coordinator (T1), Structure (T2), Plans (T3), CSS (T4), Behat (T5), compat/lang (T6), rollout (T7). ✓
- **Type/name consistency:** `ActionFooter.show/hide/init`, `dispatchStructureAction`, `dispatchPlansAction`, `[data-region="plans-footer-actions"]`, template `local_dimensions/central/structure_footer_actions` used consistently across tasks. ✓
- **Open implementation choices flagged inline** (not placeholders): capturing `CAN_MANAGE`/`region`/`pane` at `init`; the optional footer-dropdown z-index rule (add only if clipping observed). These are concrete decisions with a stated default, resolved at implementation.
