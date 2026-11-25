# Competency hub v2 for framework administration: progress

The plan is section 7 of `README.md`; the decisions are its section 9, all taken as recommended on
2026-11-23. This file says where the work stands, one line per fact, newest state on top of each
list. A session that picks the work up reads this file first.

## State at the end of the automatic run (2026-11-25)

Every stage of the plan, S0 to S7, is merged. Main of `uaiblaine/moodle-local_dimensions` is `51aba0b`,
version 2026100541 (#172 to #180); main of `uaiblaine/moodle-local_evidencehub` is `b50e3c2`, version
2026100520 (#26, the first listener of the panel's hook). m503, m503b and the mini cluster run both (two
rounds on 2026-11-25, agreed with the peer sessions; PHPUnit sites re-initialised on m503 and m503b).

What a later session may still pick up:

1. **Looked at by nobody**: colours and layout of the workspace (the browser pane was hidden at every pass), a
   screen reader, phone width, right-to-left, dark mode. The S6 delete dialogue's verdict, the "Move under…"
   focus return and the S7 policy section were proven by Behat only (`hub_bulk.feature`,
   `local_evidencehub`'s `hub_policy_section.feature`), never by eye: the pane's m503 session was logged out
   and a session does not type a password.
2. **The open points** listed below ("Open points found on the way"); three of them were closed after the run
   ("After the run").
3. **The owner's**: MDL Shield reviews of #176, #179, #180, #187 and evidencehub #26 if wanted; releases.

Facts worth keeping for the next piece of work here:

- `mdl ci` and `mdl mutate --fast` mount a plugin's dependency from the local checkout under `~/dev`, not from
  GitHub: `local_evidencehub`'s gate saw the hook only after `~/dev/moodle-local_dimensions` was pulled to
  main. Its first run without the hook failed as it should (the feature found no section, the real-dispatch
  test skipped, two gates held by other tests than the named one).
- On a network from which the CI hosts do not answer, everything runs on the MacBook; with peers' matrices
  running too, four legs at once time out in Selenium: `mdl ci <repo> --matrix --behat --jobs 1 --keep-logs`.
- The worktrees and branches of the merged stages are removed. `~/dev/wt-local_dimensions-hubv2-a11y` (branch
  `spike/hubv2-a11y`, the S0 spike: one commit never merged and uncommitted files) is kept as it is. `spike/scratch-setup/` in this folder rebuilds the Jest and
  type-check setup (`xyflow13-build/` needs `npm install`).

## After the run: two follow-ups (2026-11-25)

Both came after the report of the run, the first from the owner's own use of the hub.

1. **#183, the display preference was refused and shown as a lost connection** (version 2026100542, main
   `61e5c1c`; `MOODLE_503_STABLE` level through #184). Reported by the owner: every switch among Classic, Outline,
   Map and Both showed "Connection lost".
   - Cause: the hub wrote `local_dimensions_central_display` through `core_user/repository`'s
     `setUserPreference()`, which posts to core's REST route. That route answers HTTP 400 when the preference's
     cleaning callback changes the value, and the callback puts the stored mode (`classic`, S4) and the stored
     lens (S5) back on every write. So in the classic hub, and in the workspace once a lens had been chosen, no
     display choice was saved.
   - Fix: the hub writes through `core_user_set_user_preferences`, which stores the cleaned value, and a
     scheduled write keeps the page pending until the server answers. The error routing no longer counts a
     refusal as a lost connection: an HTTP 4xx other than 408 reaches the error dialogue, and so does a bare
     string that is not the status text of a timeout or a 5xx (core's REST helper rejects with Moodle's own
     message as a bare string).
   - Why no test saw it: the PHPUnit tests wrote through the web service, a door the script did not use, and
     the Behat scenarios that say a view or a lens is remembered reload an address that names it. Two scenarios
     now prove the save with a visit whose address names neither (`hub_map_view.feature`,
     `hub_lenses.feature`), and `hub_javascript_guards_test` holds the write to the web service.
   - Gate: one full leg with Behat (1,178 tests, 55 scenarios). By hand, with the REST helper back: the guard
     test and both new scenarios fail. Seen on m503b in classic mode: seven switches, each save answered 200,
     no toast, and a visit without `view` opened on the saved view.
2. **#187, three open points of the list below** (version 2026100544, main `9799755`; `MOODLE_503_STABLE` level
   through #188; 2026100543 is another session's #185).
   - A type or tag label holding markup could stop the tree from loading with `formatstringstriptags` off:
     `helper::structure_nodes()` sends type, tag 1 and tag 2 through `helper::plain_text()`.
   - A Behat scenario shows a reader on the root with no action, with its controls in the manager's scenario.
   - The keys of `js/esm/tests/hubv2/deletion.test.ts` are quoted.
   - `mutations/esc_hub.conf`: gates P05 to P07 follow the changed lines, gate J21 named a line S4 rewrote (the
     spec stopped at its first pattern check, so it had not run since), new gate P42.
   - Gate: the GitHub pipeline on GitHub's own runners (the owner allowed GitHub CI on 2026-11-25), four legs
     green on the first pass, 1,195 tests and 58 scenarios a leg; the mutation sweep of the change, 72 of 72 red
     on pgsql and on mariadb.

Both are installed on m503, m503b and the mini cluster (2026100544, 2026-11-25).

## Where it stands

| Stage | State | Pull request | Notes |
|---|---|---|---|
| S0 spikes | done: `S0.md` holds (a) to (d) | none | spike code in `~/dev/wt-local_dimensions-hubv2-a11y` (`spike/hubv2-a11y`, never merged; S2 takes `keynav.ts`, its tests, the node names and `tests/behat/hubv2_keyboard.feature` from it); S0LOAD framework (2,008 competencies) on m503b, id 4 |
| React Flow 13 | **merged** 2026-11-24, `d1c6665` on main; matrix with Behat 4 of 4 legs; installed on m503, m503b and the cluster with S1 | [#172](https://github.com/uaiblaine/moodle-local_dimensions/pull/172) | version 2026100533 |
| S1 base | **merged** 2026-11-24, `11cf43d` on main; matrix with Behat 4 of 4 legs (1,017 tests, 35 scenarios a leg); `security-diff-read` no finding; installed on m503, m503b and the cluster (2026100534), PHPUnit sites re-initialised; browser pass on m503b: redirect of `hubv2.php`, deep link, bogus id ignored, List and Map switch | [#173](https://github.com/uaiblaine/moodle-local_dimensions/pull/173) | worktree `~/dev/wt-local_dimensions-hubv2-s1` can go |
| S2 two projections | **merged** 2026-11-24, `70cb5e1` on main; matrix with Behat (1,023 tests, 37 scenarios a leg), the phpcs fix re-run on one leg; seen working in the page with real keys; NOT installed on the stacks yet (they are on 2026100534) | [#174](https://github.com/uaiblaine/moodle-local_dimensions/pull/174) | version 2026100535 |
| S3 commands | **merged** 2026-11-24, `8b89660` on main; matrix with Behat 4 of 4 legs (1,023 tests, 41 scenarios, Jest 199); installed on m503, m503b and the cluster (2026100536); seen on m503: the palette (17 commands in two groups, focus back on close) and a node's menu (13 items, focus on the first, Escape gives it back) | [#175](https://github.com/uaiblaine/moodle-local_dimensions/pull/175) | version 2026100536 |
| S5 service | **merged** 2026-11-24, `34d390b` on main: `local_dimensions_get_framework_insights`; matrix with Behat 4 of 4 (1,079 tests, 41 scenarios); mutation sweep 103 of 103 red on pgsql and mariadb; `security-diff-read`: one low finding fixed in the PR (the `exact` level); not installed on the stacks yet | [#176](https://github.com/uaiblaine/moodle-local_dimensions/pull/176) | version 2026100537 |
| S4 the framework on the root | **merged** 2026-11-24, `dbd6742` on main; matrix with Behat 4 of 4 (1,096 tests, 46 scenarios, Jest 215); `security-diff-read`: no blocker; seen on m503 as admin: the strip is Competencies and Learning plans, the root's facts and actions in the panel, the Structures menu (New structure, Import from CSV, Manage scales); "Classic view" and "Workspace view" switch in a real request, stay on Competencies and on the same framework, and leave no session key in the address | [#177](https://github.com/uaiblaine/moodle-local_dimensions/pull/177) | version 2026100538 |
| S5 lenses and checker (client) | **merged** 2026-11-24, `b8ad5c0` on main; matrix with Behat 4 of 4 (1,102 tests, 49 scenarios, Jest 265); seen on m503 as admin: the three lenses with their legends and `lens=` in the address, usage counts in the names, the checker with 6 findings and the focus on its heading; no console error. Colours and layout not looked at (hidden pane) | [#178](https://github.com/uaiblaine/moodle-local_dimensions/pull/178) | version 2026100539 |
| S6 selection and bulk dock | **merged** 2026-11-24, `247e327` on main; matrix with Behat 4 of 4 on `53f92c6`; sweep 21 of 21 red on both databases on `19ba28a`; installed on m503, m503b and the cluster (2026100540) by a peer's round; seen on m503 as admin: the boxes, the dock, the appearance dialogue and the move dialogue's opening | [#179](https://github.com/uaiblaine/moodle-local_dimensions/pull/179) | version 2026100540 |
| S7 companion sections | **merged** 2026-11-25. local_dimensions: `51aba0b` on main, matrix with Behat 4 of 4 one leg at a time on the MacBook (1,177 tests, 53 scenarios, Jest 309), sweep 21 of 21 red on both databases, `security-diff-read` no blocker; it carries the bulk dock's "Move under…" focus fix. local_evidencehub: `b50e3c2` on its main, matrix with Behat 4 of 4 (589 tests, 23 scenarios), 15 of 15 gates red on both databases. Both installed on m503, m503b and the cluster | [#180](https://github.com/uaiblaine/moodle-local_dimensions/pull/180) and [evidencehub #26](https://github.com/uaiblaine/moodle-local_evidencehub/pull/26) | versions 2026100541 and 2026100520 |
| After the run: display preference | **merged** 2026-11-25, `61e5c1c` on main; one full leg with Behat (1,178 tests, 55 scenarios); seen on m503b in classic mode | [#183](https://github.com/uaiblaine/moodle-local_dimensions/pull/183) | version 2026100542 |
| After the run: three open points | **merged** 2026-11-25, `9799755` on main; GitHub pipeline 4 of 4 legs (1,195 tests, 58 scenarios); sweep 72 of 72 red on both databases | [#187](https://github.com/uaiblaine/moodle-local_dimensions/pull/187) | version 2026100544 |

## Open points found on the way

Closed by #187: the label holding markup, the camelcase warnings of the Jest test, the reader's scenario.

- Not checked in a browser at any stage: a screen reader, phone width, right-to-left, dark mode, colours and layout.
- S6's bound of 30 statements a competency (`PER_COMPETENCY`) passes; the exact number was not measured.
- What the client shows for an option label such as `R&D <beta>` was not measured (the server now sends it as
  the same plain text whatever `formatstringstriptags` says).
- The routing of an HTTP 4xx (#183) is on `main` only; whether it fits the 4.5, 5.1 and 5.2 branches was not
  looked at.
- Over HTTP/2 core's REST helper rejects any non-2xx answer with an empty string, which the hub still shows as
  a lost connection. The hub no longer calls that helper; a new caller would meet it.
- The first-pass Behat failures of the GitHub pipeline on the Mac mini's runner (#187's first run: old
  scenarios, WebDriver timeouts, green on the workflow's rerun) were not looked into.

## Order of work, as refined on 2026-11-23

H2 puts the workspace inside the hub's Competencies tab, so the category-locked entry, the context
switch and the unlisted-category rules come from the hub and are not rebuilt for `hubv2.php`. S1
therefore starts by mounting the workspace in that tab behind the view switch, with the classic
list as the default view until S3 reaches parity.

## Integration facts for S1 (read from main at d7ab013 and core 5.3)

- The hub's tabs are core `dynamic_tabs`: the active one is rendered at page load and every pane is
  re-rendered over AJAX by `amd/src/central/tabs.js` `reloadPane()`, which evaluates the response's
  JavaScript. `classes/output/dynamictabs/structure.php` (`export_for_template`, lines 92 to 224)
  already resolves the context (locked entry included), the framework list with the unlisted-category
  filter, the selected framework, `canmanage`, the first 25 roots and the rule and outcome JSON:
  everything `hubv2_page::props()` builds, except `listurl`.
- Core mounts React in content inserted after load: `react_autoinit` is on every page
  (`page_requirements_manager::react_mustache_autoinit()`), watches the document with a
  `MutationObserver`, mounts `[data-react-component]` and unmounts it when removed. The client-side
  Mustache renderer has the `react` helper too. `core/import` lets AMD import an ES module;
  `@moodle/lms/core/amd` `requireAsync` is the other direction (the hub v2 uses it already).
- Seams: `structure.php` (props and the view), `templates/central/structure.mustache` (the mount beside
  `structure-body`; the specifier must stay `@moodle/lms/local_dimensions/bundle`, `bundle_test`),
  `amd/src/central/structure.js:1371` `init` (branch on the view) and `:1491` (framework select),
  `amd/src/central/preferences.js:27-38` with `helper::get_central_prefs()` and `lib.php:115-129` (a
  persisted view), `central.php:109-123, 204-212` and `context.js:169-181` (pane data),
  `hubv2.php` + `settings.php:347-354` + `hubv2_page.php` (redirect, strings handed by the tab).
- Tests that move: `tests/output/hubv2_page_test.php`, `tests/settings_registration_test.php`
  (lines 69 to 72, 94 to 96, 111, 122), `tests/bundle_test.php` (54, 72, 138).
- `hubv2_page::frameworks()` lists with `'self'` only and its list link drops `pagecontextid`; the tab
  does both right.

## Taken without asking (quick to reverse; for the owner to review)

1. **One error message of the vendored React Flow is reworded.** `react-compiler-runtime`, which
   React Flow 13 brings, throws an error whose text starts with the marker the development-leftover
   check refuses in a shipped file, and the module is inlined into the plugin's bundle. The vendored
   build replaces that prefix with "Not implemented:" (`js/esm/vendor/xyflow/README.md`). To reverse:
   drop the plugin from the build recipe; the leftover gate then fails on the bundle.
2. **The canvas is an `application` of named groups, not a second tree.** React Flow hard-codes
   `role="application"` on its wrapper, so the canvas cannot be a valid ARIA tree through its props
   (`S0.md`, section (b)). The outline of S2 is the tree; each canvas node's name carries its level,
   position and state.
3. **One view switch with four values in S2**: Classic (the AMD list, still the default), Outline,
   Map, Both. The mockups show three; Classic leaves when S4 retires the list.
4. **In the map the tab's own "Add competency" button is hidden** and the canvas panel's stays: it
   knows the selection and refreshes the canvas.
5. **The view switch is a group of `aria-pressed` buttons**, as the Design System's segmented-control
   file asks of a view switch, not a radio group.
6. **The canvas block of `styles.css` reads a radius scale** (`--local-dimensions-radius-xs` to
   `-xl`, each reading the family token first), and the token contract test admits it. This is step 3
   of the Design System's plan for this plugin, done for the canvas block only.
7. **The outline's authoring keys are not the mockup's.** The mockup's hint row says Enter adds a
   sibling and Tab a child; in an ARIA tree Enter selects and Tab must leave the tree, its one Tab
   stop. S3 uses F2 to rename, Ctrl/Cmd+Enter (or Insert) for a new sibling and Ctrl/Cmd+Shift+Enter
   for a new child, with an inline editor of two fields (name, ID number) that reopens for the next
   sibling.
8. **The `exact` usage level asks for more than the capabilities at the system context** (S5 service):
   any negative permission for one of those capabilities, for any role in any context, drops a
   non-administrator to `presence`. Conservative: a site with one such override gives exact counts to
   administrators only. To reverse: `get_framework_insights::has_negative_exact_row()`.
9. **The insights service has no cache** (idea I35 named a MUC cache): it is a fixed number of statements
   whatever the framework's size, so there is nothing a cold node pays for; no mini cluster check was needed.
10. **"Classic view" is a link in the context bar, saved per user, off by default** (H3 said "behind a
    Classic view link"; the preference, its single writer and the default are this stage's reading of it).
11. **A lens and the mode are saved by one writer each**; a display save from another tab cannot change them.
12. **"Check" sits before "Commands" in the workspace's bar**, as the Verificador mockup, so "Commands"
    stays the last control before the map.
13. **S6: picks inside closed branches are kept**, which departs from Design System decision D131 (a
    selection belongs to what is on screen); the dock says "<n> selected (<m> not shown)". To follow D131
    instead: drop the picks of a branch when it closes (`multiselect.ts`).
14. **S6: no "Export selection"** (a browser-built CSV would be a second rule beside the server's export,
    which neutralises spreadsheet formulas); Enter keeps showing the competency in the panel, Space picks;
    Ctrl/Cmd+A adds the level's drawn competencies; bulk actions are in the dock only, not in the palette;
    at most 200 competencies a call.
15. **S6: a bulk appearance change does not fire core's `competency_updated` nor move `timemodified`**
    (nothing in the three dimensions plugins keys on it); it fires the plugin's own audit event per
    competency and invalidates the metadata cache.
16. **S7: the hook is data only** (plain rows, a note, one local link; six sections, twelve rows), a refused
    section is dropped with a developer message instead of failing the read, the first section under a key
    wins; the panel shows no loading line for sections. The evidence policy section is shown to
    `local/evidencehub:view` or `managepolicies` in the framework's context, "Edit policy" to
    `managepolicies` only.
17. **S7: lookup by id is not filtered by unlisted categories** in the new read service, as the plugin's
    rule for hub services says (the brief had asked for the filter).

18. **S7: the bulk dock's "Move under…" focus fix went into the S7 pull request** (#180), with one Behat
    scenario, instead of a pull request of its own: it was found by the browser pass of S6 after S6 merged.
19. **S7: `structure_view_test` asserts that `sections` is a boolean**, not that it is false: whether a
    plugin listens depends on what the site has installed.
20. **Gates on the MacBook, one leg at a time**: the CI hosts did not answer from the network the MacBook was
    on after the restart, so S6's sweep, S7's matrix and sweep and evidencehub's matrix and sweep ran here.
21. **Two stack rounds on 2026-11-25** instead of one: local_dimensions 2026100541 first (with four plugins
    of a peer session, at its request: local_ivquiz, local_ivform, mod_flexbook, auth_loginsteps), then
    local_evidencehub 2026100520, because evidencehub's gate needs local_dimensions' main in the mounted
    checkout. local_dimensions 2026100540 had been installed by a peer's round on 2026-11-24.

## Left for the owner

- MDL Shield reviews: only the owner posts the command (candidates: #176, #179, #180, #187, evidencehub #26).
- Releases and tags: none made.
