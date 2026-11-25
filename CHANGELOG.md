# Changelog

All notable changes to the **local_dimensions** plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Fixed: a type or tag label holding markup could stop the tree from loading

Version `2026100544`.

- With `formatstringstriptags` off, a type, tag 1 or tag 2 option label holding a tag ("Core <b>skills</b>") failed
  the answer of `local_dimensions_browse_structure` and `local_dimensions_get_structure_node`, so the Competencies
  tab's tree and the workspace did not load for a framework using that label. The node's labels now travel as the
  same plain text whatever the setting says (`helper::plain_text()`, the rule the bulk appearance service already
  applied to its own answer).
- Tests: the reader of a framework sees the root's facts and none of its actions (a Behat scenario, with the
  manager's scenario as its control); the Jest test of the delete texts no longer trips the camelcase rule.

### The plan overview's actions and an address that opens one competency

Version `2026100543`. Phase 2a of the competency pages: what the learner's plan overview needs before a
theme sends plan owners there from Moodle's own plan pages.

- `view-plan.php` takes an optional `competencyid`. The page opens that competency on load the way a press
  would (the row in the list, the dialog in the card layout), once, after the rows are armed. When the
  stored scope or the favourites filter hides its row, the line's own controls are set to show it, so the
  reader undoes it with a press, but nothing is saved: a link never changes the stored view. The id is
  handed over only for a competency the page itself lists, and only to a viewer who may open its detail;
  any other value is ignored and the page is the plain overview. The page's own address stays the plan's.
- An actions row between the hero and the filter line: the plan's status in its draft states (a status
  label), **Request review** or **Cancel review request** exactly when core's plan exporter allows them
  (`plan_access::review_actions()`), calling core's `core_competency_plan_request_review` and
  `core_competency_plan_cancel_review_request` (no web service of the plugin's own), drawn hidden until the
  new module `local_dimensions/plan_actions` arms them; and a link to core's plan page with `noredirect=1`,
  labelled "Manage plan" or "Plan page in Moodle" after `plan::can_manage()`.
- Tests: `view_plan_summary_actions_test`, `plan_actions_js_source_test`, `view_plan_actions.feature`; gates
  in `mutations/plan_overview_actions.conf`.
### Fixed: the hub's display choices were refused in classic mode

Version `2026100542`.

- In the classic hub, and in the workspace once a lens had been chosen, every display choice (the view switch, a
  display option) showed "Connection lost" and was not remembered. The hub wrote the preference through core's
  REST route, which answers HTTP 400 when the preference's cleaning callback changes the value, and the callback
  puts the stored mode and lens back on every write. The hub now writes through `core_user_set_user_preferences`,
  which stores the cleaned value, as its PHPUnit tests already did.
- A scheduled preference write keeps the page pending until the server answers, so a Behat step cannot leave the
  page before it.
- An HTTP 4xx answer is no longer shown as "Connection lost": it is the server refusing the request, and it now
  reaches the error dialogue, which says what the server said (`Error('HTTP 4xx: ...')` from core/ajax, or the
  bare text core's REST helper rejects with, where Moodle writes its own message). A request timeout (408), a
  5xx and a dropped connection keep the toast.

### Sections other plugins add to the workspace's panel

Version `2026100541`. Stage S7 of the hub v2 record (idea I25, job J10, decision H9): a companion plugin adds
sections of its own under the panel of the selected competency, as data only.

- New hook `\local_dimensions\hook\competency_panel_sections`: the competency, its framework, the framework's
  context and whether the viewer manages there, read-only; `add_section()` takes a component, a key, a title,
  rows of a label, a value and an optional tone, a note and one link. Everything is plain text (decoded and
  stripped until stable, so it survives `PARAM_TEXT` whatever `formatstringstriptags` says), the link is a page
  of this site or nothing, and the hook bounds sections (6), rows (12) and every length. No HTML; zero-width and
  bidirectional controls removed, and a text with nothing visible is empty. Nothing passed to `add_section()`
  throws: a refused section (component, key, a taken key: the first wins) or tone is dropped with a
  `debugging()` message, so one listener cannot blank the others.
- New read web service `local_dimensions_get_competency_panel_sections` (`readonlysession`): the framework and
  its context from the competency's row, `validate_context()`, a guest refused, competencies enabled, core's
  read right on the competency, then the dispatch and an allowlisted answer. No statement beyond its checks
  when no plugin listens.
- The panel asks only when the page says a plugin listens (`hub_map::has_panel_sections()`), on every
  selection and after every change; an answer for an earlier selection is dropped, and a failed read is one
  muted line ("Some details could not be loaded.") under the rest of the panel. Each section is a heading, a
  definition list (a tone drawn as a status label around its text) and one link in the same tab; two status
  label tones, danger and info, join the workspace's sheet.
- Mutation spec `mutations/competency_panel_sections.conf` (21 gates).
- Fixed: closing the bulk dock's "Move under…" dialogue gives the focus back to the dock's button, as its two
  other dialogues do; it went to the outline's item, where the picks' actions are a Tab away.

### Acting on several competencies at once in the workspace

Version `2026100540`. Stage S6 of the hub v2 record (idea I16, job J7): a manager picks several
competencies in the outline or on the map and acts on them from a bulk dock, the fleet Design System's data
report "Selection variant" and "Bulk dock" (D129 to D135). A reader gets none of it.

- **Picking**: Space on an item, Shift+Up and Shift+Down from the anchor, Ctrl+A (Cmd+A) for the item's level,
  a real check box at the start of each competency row ("Select <name>", out of the Tab order) and on a card's
  corner (shown on hover, on focus and while anything is picked), Ctrl+click (Cmd+click) on a card, Escape to
  release. For a manager the outline is a multi-select tree: `aria-selected` follows the box on every
  competency row and the competency the panel shows is `aria-current`; a map node says "selected" in its name.
  The picks survive opening and closing branches and lose the competencies that leave the tree.
- **Dock**: after the panes, sticky 12 px above the viewport's bottom (the workspace now clips its overflow
  instead of hiding it, so the dock can stick); the count in a polite live region, **Set appearance…**,
  **Move under…**, **Clear selection** and **Delete…**, last and outlined. Each action opens a dialogue
  titled with the action and the count, naming the first five competencies.
- **Set appearance…** (background colour, text colour, tag 1, tag 2, competency label; each left as it is
  by default) calls the new write service `local_dimensions_set_competencies_appearance`: at most 200
  competencies of one framework, `moodle/competency:competencymanage` in the framework's own context, every
  id the framework's (a foreign or missing id refuses the whole call before any write), the field
  controllers' own validation and the select's own options, writes through the competency handler (so
  `can_edit()` and the `competency_customfields_updated` event behave as a form save does), one transaction,
  and the competency metadata cache invalidated after it. The dialogue previews the two colours and says
  their contrast ratio, warning under 4.5:1.
- **Move under…** is "Move to" with every topmost pick: a target inside a picked competency is refused,
  competencies already under the target are skipped and said so, the others move one core call each in the
  tree's order, the first refusal stops the run.
- **Delete…** asks `local_dimensions_competency_delete_check` for every topmost pick, then one confirmation
  says how many core will delete, what goes with them and which it will refuse and why; only those are
  deleted.
- After each action the touched branches and the insights are read again; what failed stays picked and the
  outcome is said in the workspace's polite region.
- "Export selection" is left out: the plugin's CSV exports neutralise spreadsheet formulas on the server
  (`local\csv_formula`) and match the framework import's columns, which a copy built in the browser would not.

### Lenses and the structure check in the workspace

Version `2026100539`. Stage S5 of the hub v2 record (ideas I19, I20, I22, I26 to I28): the workspace reads a
framework's usage, rules, relations and findings from `local_dimensions_get_framework_insights`, one answer
per framework, and shows them in three lenses and a checker. No write path is added: every fix a finding
offers is an existing action of the registry.

- **Lens switch** in the workspace's bar (None, Usage, Rules, Relations; `aria-pressed` cells in a labelled
  group), also in the palette ("No lens", "Lens: usage", ...). The lens is saved as `structure.lens` in
  `local_dimensions_central_display` (sanitised by `helper::get_central_prefs()` and `preferences.js`),
  kept in the address as `lens=` without a history entry and in the tab's pane data, and handed to the
  workspace in its props, resolved like the view (`hub_map::resolve_lens()`: the address, the saved one,
  none).
- **Usage**: each card's ground steps through the brand's tint, edge and fill by the chosen measure (0; 1
  to 2; 3 to 5; 6 or more), each under an ink that clears 4.5:1 in light and dark; an unused competency
  has a dashed edge; the count in words under the name, as the outline's quiet counter, and in the names.
  The panel holds the measure switch (courses, activities, plan templates, learning plans), the legend
  with its counts and the unused competencies. Under presence counts only used and unused are shown and
  the panel says why; with no usage the lens is not offered and the panel says why.
- **Rules**: a ruled parent carries "6 of 8 points" (or its rule's label) and its children their points
  and "required"; only the children a points rule counts take the rule edge; a rule that can never fire
  is a dashed warning with a triangle and words; the rest is quieter. The panel lists the legend and one
  block per rule with **Set the rule…** for a manager.
- **Relations**: every related pair with both ends drawn is a dotted edge, one end alone shows "+N
  elsewhere"; the panel lists the pairs, each end a button that reveals it.
- **Check**: a button with the findings' count opens the checker in the panel (its own mode, closed by its
  close button or by a selection): the codes present with their counts, **Check again**, each finding with
  its words, **Reveal** and the action that fixes it, and the codes found nowhere. F8 and Shift+F8 walk
  the findings, wrapping, and announce "Finding 2 of 6: ...". While the checker is open, an item with
  findings carries a warning mark with their count, in its name too.
- **Impact preview**: while a delete's confirmation or "Move to" is open, the competencies it touches are
  outlined on the map and in the outline.
- The framework's summary takes the service's numbers once read: levels, competencies with a rule,
  related pairs and findings.
- The insights are read shortly after the workspace mounts, when a lens or the checker is first asked
  for, after any action of the registry marked as changing them, and on "Check again"; a stale answer is
  dropped. Names of competencies the tree has not loaded are read in batches as the panel lists them.

### The framework on the root: the workspace replaces the Structures tab and the classic list

Version `2026100538`. Owner's decisions H2 and H3 of the hub v2 record: on 5.3 the workspace is the
Competencies tab, and the hub's Structures tab and the tab's classic list stay one release behind a
link. No web service was added: every write is the Structures tab's own form or service
(`framework_dynamic_form`, `import_framework_dynamic_form`, `local_dimensions_set_framework_visibility`,
`local_dimensions_export_framework`, core's `core_competency_duplicate_competency_framework` and
`core_competency_delete_competency_framework`), each checking the capability in the framework's
context or in the context the tab lists.

- **The framework is the root node.** Selected in the outline or on the map, the panel shows its name,
  ID number, context, visibility (a status label), scale with the proficient and default values, the
  taxonomy term of each level, the competencies' total and the summary; then **Edit structure** and
  **More actions** (Configure scale, Hide or Show structure, Duplicate, Export to CSV, Delete
  structure), also the root's menu (its "more" button, Shift+F10) and the palette's actions on it.
  They run `amd/src/central/frameworks.js`, whose actions are now exported for the Competencies tab
  without changing them for the Structures tab. After an edit, the scale, the visibility or a
  duplicate the tab is reloaded on the same framework with the opened branches and the selection
  kept (`js/esm/src/hubv2/carry.ts`); after a delete it shows the next framework of the select or the
  empty state.
- **Taxonomy on the columns**: above each column of the map, the level's term as the framework names
  it (core's strings, core's fallback past the named levels), following the view sideways, inside the
  canvas, under no card at rest, hidden from assistive technology, which reads the levels once in the
  canvas's description; a manager's pencil opens the framework's form. The outline's "Show taxonomy"
  meta reads the same function (`columns.ts`).
- **A Structures menu** beside the framework select (core's dropdown): New structure and Import from
  CSV (each then selects the new framework), Show hidden structures (a `menuitemcheckbox` over the
  tab's saved toggle) and Manage scales; the first two are palette commands too. It is drawn with no
  framework at all, so a manager can create the first one.
- **Classic mode**: a `classic` member of `local_dimensions_central_display` (off by default) and a
  `classic=1|0` address parameter for the visit (`local\hub_mode`). Off: the strip offers
  Competencies and Learning plans, `tab=frameworks` opens Competencies, the view switch offers
  Outline, Map and Both, a saved or requested classic list opens the workspace, and the default view
  is Both. On: the hub as it was, the classic list the default. The context bar's **Classic view** /
  **Workspace view** link switches it, saving it because it carries the session key (an address
  without it changes the visit only), and works without JavaScript. That link is the mode's one writer: a
  display save from the hub's script never carries it, and the preference's clean callback
  (`hub_mode::clean_display()`) keeps the stored mode on every client write. Hiding the Structures tab opens
  and closes nothing: every tab still admits its viewers by its own `is_available()`.
- Tests: `tests/local/hub_mode_test.php` (the session key refusals first, the strip for a site and a
  category manager, the dynamic-tabs service still serving and refusing the Structures pane),
  `structure_view_test` (the default view, the modes, the root's facts, the Structures menu),
  `preferences_test`; Jest `commands` (the framework scope), `columns`, `carry`; Behat
  `hub_framework_root.feature`. The features of the classic surfaces (`manage_frameworks`,
  `configure_scale`, `central_category`, `competency_links`, `related_competencies`, `colour_mode`,
  `hub_map_view`) switch classic mode on in their Background.

### Framework insights for the map's lenses and checker (server side)

Version `2026100537`. A new read-only web service, `local_dimensions_get_framework_insights`, answers
for one framework what the map's lenses and "Check structure" will show; no page uses it yet.

- **Usage** per competency: courses, activities, learning plan templates and learning plans (a plan
  holds a competency of its own, an archived rating of a completed plan, or its template's, as core's
  `plan::get_by_user_and_competency()` reads it). Counts are exact only for a viewer holding at the
  site every capability core's own usage lists ask (course competencies, hidden courses and
  activities, availability, template management, plans and their drafts) and when no role refuses
  any of those capabilities (a prevent or prohibit in any context demotes the viewer to the next
  level; the site administrator is not demoted). A manager of the
  framework's competencies is told which kinds of use apply (0 or 1), what the delete check already
  tells them; any other reader is told nothing about usage.
- **Rules**: type, core's label, outcome and, for a points rule, the points asked, the points its
  listed competencies still add up to, the required ones and whether a learner could ever meet it,
  mirroring `competency_rule_points::matches()`.
- **Relations**: every related pair of the framework once, lower id first (`pairs`, for the lens to
  draw), and per competency the count of its relations, both from one statement; a row that
  reaches another framework, which core never writes, is not read.
- **Checks**, each with its title and sentence in `hubv2_check_<code>` and `_desc`: a rule that can
  never fire, a rule over a single child, a rule without children (core saves an "all" rule on a
  leaf), an empty description, an empty ID number (core keeps ID numbers unique but stores an empty
  one), a competency nothing uses (only from exact counts) and custom colours under WCAG's 4.5:1
  (`local\contrast_ratio`). Core refuses a framework scale with no proficient value, so that check is
  not offered.
- **Summary**: competencies, levels, rules, relation pairs and findings.
- **Access** as the hub's framework lists: logged in, not the guest, competencyview in the
  framework's own context, and a framework in a category `local_unlistedcourses` withholds from the
  viewer refused as a missing one. The answer is a fixed number of statements whatever the
  framework's size, and nothing is cached: each node computes it from the database.

### Commands: a node's menu, a command palette, "Move to" and typing in the outline

Version `2026100536`. Every action of the workspace (Outline, Map, Both) is in one registry
(`js/esm/src/hubv2/commands.ts`) with one decision of when it is offered, and the panel's buttons, a
node's menu, the command palette and the keys all draw from it and run it through one function, so
no place offers what another refuses. No web service was added: the writes are core's
`core_competency_create_competency`, `_update_competency`, `_move_up_competency`,
`_move_down_competency` and `_set_parent_competency`, which check the competency rights themselves.

- **A node's menu**: a "more" button on the selected row and the selected map card ("Actions of
  <name>"), and Shift+F10 or the context-menu key on the focused item of either pane. The Design
  System's menu: an icon per item, separators between groups, Delete last, shortcuts at the line's
  end; the ARIA menu keys (Up, Down, Home, End, type-ahead, Enter, Escape, Tab). It is drawn over the
  page, under its anchor or above it when below would leave the pane or the screen, so the panes never
  clip it; closing it gives the focus back to the item.
- **The command palette** (`Commands` in the workspace's bar, or Ctrl+K / Cmd+K while the focus is in
  the workspace; outside it the browser keeps its own shortcut): a dialogue with one field driving a
  list (the combobox pattern with `aria-activedescendant`), in three groups: the actions on the
  selected competency, "Go to" (the tree's search, from two characters; choosing a hit reveals and
  selects it in both panes), and structure and view (Outline, Map, Both, Expand all, Collapse all,
  Add competency, Framework summary, and the move's own controls while a move is being picked).
- **Move to…**: a dialogue titled with the competency's name, with a search field and the possible
  parents as a single-select tree (branches load as they are opened) or the search's hits with their
  paths; the top level first, the competency and its descendants never selectable, its current parent
  marked. Once a parent is chosen it says which rules core resets (the sentences the pick-on-the-map
  confirmation shows, read afresh); "Pick on the map" starts the existing move mode; the commit names
  the target and runs the same move as picking.
- **Reorder from the keyboard**: Alt+Up and Alt+Down on the focused item of either pane move it among
  its siblings, keep the focus on it and say where it now stands ("moved down, now 2 of 4"), or that it
  is already first or last. A move down past the loaded siblings reloads them as far as its new place,
  so it no longer drops out of the tree.
- **Typing in the outline** (owner's decision H5, for a manager): F2 renames in place; Ctrl+Enter
  (Cmd+Enter, or Insert) opens a row with a name and an ID number for a new sibling right below, and
  Ctrl+Shift+Enter for a new first child. The ID number is offered when the siblings' end in a number
  or a dotted number (`DCE-1.2` gives `DCE-1.3`; zero padding kept); Enter saves and opens the row
  again for the next sibling, Escape stops and returns the focus to the row; the server's refusal (a
  taken ID number, a missing right) shows under the fields. The foot of the outline names the keys.
- **Framework summary** (the panel with nothing selected, and a palette command): the total from the
  server, then levels, rules and open branches counted over the loaded tree and labelled so while it is
  not the whole framework. Relations are not counted: the tree's nodes do not carry them.
- **Display options** in the outline's header: "Show identifiers" and "Show taxonomy", check boxes in a
  menu, kept in the same preference the classic list's switches write
  (`local_dimensions_central_display`, `structure.id` and `structure.tax`).
- **The panel** keeps three controls for the selected competency: **Edit**, **Add child** and **More
  actions**, which opens the same menu under itself (Delete is there, last). Closing a menu, the
  palette or "Move to" gives the focus back to the row or node it belongs to, to the panel's button,
  or, after a layer opened by another, to the selected item of the pane that last had the focus.
- A reader (no `moodle/competency:competencymanage` on the competency) gets no menu button, no action
  in the panel or the palette, and no key that writes.
- New strings: `hubv2_actionsof`, `hubv2_cmd_*`, `hubv2_commands`, `hubv2_editor_*`, `hubv2_hint_*`,
  `hubv2_moveto_*`, `hubv2_palette_*`, `hubv2_reorder_*`, `hubv2_reordered_*`, `hubv2_summary_*`.

### The outline beside the map, and the keyboard of both

Version `2026100535`. The Competencies tab's switch has four views: **Classic** (the list, still the
default; its cell was **List**), **Outline**, **Map** and **Both**. The last three are one React
workspace over one store, so moving among them keeps the opened branches and the selection and
reloads nothing; only a move to or from Classic reloads the tab. `view=map` keeps meaning the map
alone, in links and saved preferences.

- **The outline** is an ARIA tree: a flat list of `treeitem` rows with `aria-level`, `aria-setsize`
  (the parent's total from the server, since a lazy branch is never whole in the page),
  `aria-posinset`, `aria-expanded` on parents and `aria-selected` on the selected row; the framework
  is its root. One Tab stop with a roving tabindex, the keys of core's `core/tree` (Left and Right
  swapped right to left) and type-ahead on the visible names. A row's click selects it, its chevron
  only opens or closes; a "Load more" row loads the next page; a branch loading shows the Design
  System's skeleton, an empty framework its empty state with "Add competency" for a manager.
- **One selection**: selecting in the outline selects on the map and pans it to the node (at once
  under reduced motion); selecting on the map, or revealing a search result, moves the outline's Tab
  stop to the row and scrolls the outline alone to it. The move mode's "choose the new parent" takes
  a click or Enter in either pane, and Escape cancels it.
- **The map's keyboard** (from the stage 0 spike): one Tab stop instead of one per node, the same
  keys as the outline, every node a group named with its level, position, state, children, rule and
  relation to the selected competency (`hubv2_a11y_level`, `_position`, `_expanded`, `_collapsed`,
  `_rule`, `_related`), React Flow's English role description and its own Enter and Space handling
  dropped, the key help (`hubv2_a11y_node`) reworded. A key repeated faster than the panes draw acts
  on where the previous one went, and a branch whose page is loading is asked for once. The pan that
  follows the focus keeps the node clear of the overview map and the controls on a short canvas too.
  A branch opened on the map, by key or by its card's button, moves the view just enough to show the
  parent and the start of its children when they open outside it (at once under reduced motion, no
  move when they fit).
- **Both**: the outline at the left, 264 px by default, behind a splitter (`role="separator"`) moved
  by the arrow keys, Home, End or a drag and reset by a double click; its width is kept in
  `localStorage` (`local_dimensions_hubv2_outline_width`). Below 992 px the panes stack and the
  splitter is not drawn.
- **Expand all and Collapse all** in the outline's header: Expand all opens the visible tree level by
  level, loading each closed branch's first page, and stops before passing 500 competencies with a
  notice (`hubv2_expandall_capped`); Collapse all closes every branch, ending an Expand all still
  running.
- **The panel lists every related competency** (decision H10), not only those off the map; the
  string `hubv2_related_offcanvas` goes.
- **Server**: `structure::VIEWS` is `list`, `outline`, `map`, `both` and `structure::WORKSPACE_VIEWS`
  the last three; the saved preference and the address accept the four; `hub_map::props()` carries
  the first `layout`. The switch tells the workspace a new layout by the `local_dimensions:hubv2-layout`
  event, and `hub_workspace_contract_test` holds the event's name, the view lists of the server, the
  tab's modules and the workspace, and the outline width's custom property equal.

### The competency map becomes the Competencies tab's map view

Version `2026100534`. The canvas leaves its own page for the hub's Competencies tab, where a **List**
/ **Map** switch beside the framework select chooses the view; the list stays the default.

- **One tab, two views.** The map is drawn from what the tab already resolves (the context, the
  framework list with the `local_unlistedcourses` rules, the selected framework, whether the viewer
  manages it), so it lists exactly what the list lists, and a course category's manager reaches it
  from the category's menu, locked to the category and its subcategories. The page's own context
  form, its *List view* link and the canvas's `listurl` prop go.
- **Remembered and linkable.** The view is kept under `structure` in
  `local_dimensions_central_display` and in the address (`view=map`), which beats the saved choice;
  an unknown value reads as none. `competencyid=M` reveals and selects that competency on the map
  when the server finds it in the framework shown, and is ignored otherwise. Selecting a competency
  rewrites the address in place, with no history entry; a framework or context change drops it.
- **`hubv2.php` redirects** to `central.php?tab=structure&view=map`, carrying `contexttype`,
  `categoryid` and `frameworkid` when given, behind the hub's own Site administration gate, so a
  viewer the old page refused is refused the same way. The admin tree entry
  `local_dimensions_centralv2`, `hubv2_page`, the `hubv2` template and the `hubv2_toolbar` module
  are removed, with the strings `hubv2`, `hubv2_apply` and `hubv2_listview`.
- **The canvas's strings** are listed once, in `output\hub_map::STRINGS`, which the tab hands to the
  page in the map view; `hub_map_test` keeps it equal to `js/esm/src/hubv2/strings.ts`.
- **Reduced motion**: the centring on a revealed competency is instant when the system asks for
  reduced motion; the canvas animates nothing in CSS.
- **Right to left**: in a language written right to left the tree grows leftwards (the layout
  mirrored, the edge handles and the expand chevrons swapped, each card in the page's direction).
- **Edges leave the accessibility tree** (hidden, no role, no "Edge from f to c198" name), and React
  Flow's application wrapper is named after the framework.
- **The move confirmation names the rules core resets**: `set_parent_competency()` resets the rule
  of the current and of the new parent, and the dialogue says which of them has one, read fresh
  before asking (`hubv2_moverule_oldparent`, `hubv2_moverule_newparent`).
- **Design System**: the view switch is the segmented control (as a view switch, `aria-pressed`);
  the canvas's radii come from the new `--local-dimensions-radius-*` scale tokens (a counter alone is
  round: the expand button and the rule label lose their pill), and its two 0.6875rem sizes become
  0.75rem.

### Competency hub v2 on React Flow 13

Version `2026100533`. The canvas looks and behaves as before, on the new library.

- **React Flow** (`@xyflow/react`) goes from 12.12.1 to 13.0.3, with `@xyflow/system` 1.0.1,
  zustand 5 and React's `react-compiler-runtime`; `js/esm/vendor/xyflow/xyflow.js` is rebuilt as its
  README says, and `thirdpartylibs.xml` carries the new version and SHA-384. `useStore`,
  `applyNodeChanges` and `applyEdgeChanges`, which 13 no longer exports, leave the module.
  One error message of `react-compiler-runtime` is reworded as the module is built, because its
  text starts with a marker the development-leftover check refuses in a shipped file.
- The canvas reads 13's change batch (`NodeChangeset`) by type for the measured sizes and the
  selection, and keeps 12's `elevateEdgesOnSelect` off, so the selected card's edges, its
  related ones included, stay under the other cards.
- 13 follows the system's colour scheme through its stylesheet only (`color-scheme` and
  `light-dark()`); that stylesheet is not loaded and those rules are not ported, so the canvas
  keeps following Moodle's `data-bs-theme` through the plugin's tokens.
- `styles.css`: the pan cursor moves to the pane's `::before` layer, as 13's base rules do, so
  nothing drawn above the pane (an edge, a rule label) inherits it.

### Design System: the filter line's funnel and eraser (D107, D109, D110)

Version `2026100532`.

- **Filters draws the funnel** (`fa-filter`, core `i/filter`) in place of the sliders.
- **The open search's eraser is a 24 px square button** (radius 4 px) at the end of the field, shown
  only while there is text: `ink-muted` on transparent at rest, the `surface-alt` ground and `ink`
  on hover, the 2 px focus ring, its colours changing over `motion-fast`. It keeps its "Clear text"
  name in `aria-label` and the plugin's `local-dimensions-tip` balloon (after 100 ms) takes the
  place of the `title` attribute. The browser's own clear button stays hidden.
- **The tooltip's arrow sits on the balloon's edge** (D120): with the 5 px border triangle,
  `calc(100% + 3px)` drew the visible half inside the balloon; the arrow is now at
  `calc(100% - 2px)`, above and in the `-tip-bottom` variant.

### Security: the tracker's card services need a plan (MDL Shield, 2026-11-18)

Version `2026100531`.

- **`get_course_progress` and `get_courses_completion_status` refuse a call without a plan.**
  With plan id 0 the only gate was that the course carried some competency link and that the
  caller could browse it, so any user received the section names of any such course, locked cards
  included (MDL Shield finding 1, low). The tracker page always names its plan and competency, and
  nothing else calls the two services; `planid` and `competencyid` are now required, and 0 ends in
  `invalidplan` before any course is read. Gate `mutations/mdlshield_2026_11_18.conf`.
- **The plugin no longer creates the PostgreSQL `unaccent` extension by itself** (finding 2).
  Install and upgrade used to run `CREATE EXTENSION` and swallow the outcome. The plugin settings
  now show the extension's state with a button that creates it (`/local/dimensions/unaccent.php`,
  `moodle/site:config` and a session key), and System status gains a check: OK when installed,
  a warning with the recommendation when missing, not applicable on other databases.
  `helper::has_unaccent()` is memoised per request, so a search asks the catalogue once.
- **Exporting a framework needs `moodle/competency:competencymanage`**, as core's own export page
  does; the hub's export button was already shown only to managers, and the template export now
  offers a referenced framework as a companion download only to a caller who manages it (the CSV
  still names every framework the caller reads).
- **The reflective write of `core_availability\info::$modinfo`** in `learner_restrictions` is
  documented as intentional (core has no setter and its public filter walks the course's members),
  and `info::get_modinfo()` joins the core-parity manifest beside `info::filter_user_list()`
  (finding 3).
- `cli/core_parity.php` reports a missing manifest instead of failing on the read.
- README: the accent-insensitive search section describes the admin action and the status check,
  and the tracker section tells administrators that a viewer who may read a learner's user
  competencies sees the plan owner's course progress and section names on the tracker.
- Finding 4 (direct writes to core competency and custom field tables) is left as it is: each
  write follows core's own precedent or a documented need, and the field writes reset the
  handlers' caches.

### Security hardening: the rest of the 2026-11 audit

Version `2026100530`. None of these was reachable as an escalation; each brings a surface in line
with core or with the plugin's own rule.

- **The hub's modals trust no posted context.** The template modal created a template in the
  context access was checked in but sent the posted `contextid` on an edit too (core refused the
  change with an exception) and checked short-name uniqueness there; it now takes the template's
  own context. The template and framework modals refuse a context core keeps no template or
  framework in (`invalidcontext`). Both the template and the competency modal apply the custom
  fields' own rules (`instance_form_validation()`), and the competency modal stores custom SCSS
  as plain text, as the template modal does (`helper::force_customscss_plain_data()`).
- **The card services answer each course once.** `get_course_progress` and
  `get_courses_completion_status` deduplicate the requested ids, so a repeated id no longer
  repeats the course's structure and completion reads.
- **Cohort roles go only to active accounts.** `add_cohort_role` refuses a suspended, deleted or
  guest role holder; core's mapping accepts any user id.
- **Adding a user to a template asks core first.** `add_template_user_plan` lets
  `api::create_plan_from_template()` answer "already has one" after its own `planmanage` check,
  so a template manager no longer learns who holds a plan.
- **Plan creation per cohort is serialised.** `task\sync_template_cohort` creates a pair's plans
  under a core lock (`competency_plan` has no unique index on template and user), and
  `task\sync_cohort_roles` ends with a message for a user who lost `moodle/role:manage` instead
  of failing every retry.
- **The enrolment status toggle follows the site and the task.** `set_enrol_instance_status`
  refuses a method disabled on the site, takes the lock of `task\process_enrol_method`, and
  applies the method's own `can_hide_show_instance()` (self enrolment without a required password).
- **Report descriptions survive a missing context.** The plan and template description columns
  fall back to the site context when the plan's owner or the template's category context is gone
  (the plans source joins the owner with a LEFT JOIN, so such a row reaches the column). The
  prior-learning source joins its owner with an inner JOIN, so its column needs no fallback.
- **Hidden templates in a subcategory.** The locked category entry's template list keeps a hidden
  template only for whoever manages it in its own context. The rule is now in `CLAUDE.md`:
  hiding filters lists; a lookup by id follows core.

### Security: four findings of the 2026-11 external audit

Version `2026100529`. The audit's other findings were checked against the code and core 5.3 and
either mirror core, are hardening left for a later change, or do not hold.

- **Custom-field files follow the instance's read rule.** `competency_handler::can_view()` and
  `lp_handler::can_view()` returned true for everyone, and core's textarea and customfield_picture
  file callbacks ask only that, behind no login. A known instance now requires a logged-in user and
  `picture_manager::can_view()`: a visible template to anyone logged in, a hidden one to its
  readers and plan holders, a competency to its framework's readers. Instance 0 (the report
  builder's question) still answers true.
- **Every web service refuses while competencies are disabled.** 45 services read the competency
  tables directly and answered on a site with competencies off; each now calls
  `api::require_enabled()` after its context check, as the hub pages do.
  `tests/local/external_requires_competencies_test.php` scans for it.
- **The competency search and the import's structure hint leave out unlisted categories.**
  `search_competencies` and `template_import_analyser::hint_other_frameworks()` now filter by
  `unlisted_categories`, as the hub's Structure and Frameworks tabs do.
- **A category manager can import template custom fields.** The import analyser resolved the
  fields' editing rights at the site, so a manager holding `templatemanage` in one category had
  every `template_idnumber` and `cf_*` row refused as not writable. It now names the target, and
  both handlers drop a create form's context hint when the save ends.

### Changed: the workload follows core's progress rule, held to it by an oracle and a fingerprint

Version `2026100528`.

- **The activities the bar and the rings count are core's.** `calculator::counts_towards_progress()`
  now asks `cm_info::is_visible_on_course_page()`, as `completion_info::get_user_activities_with_completion()`
  does, where it asked "listed on the course page, or openable now". A stealth activity ("Make
  available but don't show on course page") no longer counts. A hidden activity counts for a
  learner who may see it (`moodle/course:viewhiddenactivities`), as core counts it. Moodle's own
  course progress (the dashboard's *My courses*, block_compass) and the tracker now give the same
  number for the same learner.
- **An oracle holds the two equal.** `tests/local/core_parity_test.php` compares, for every learner
  of a course that carries each case of core's rule, the set of counted activities and the
  percentage. It covers 18 restriction sets, groups of every visibility, groupings, a module
  override, separate groups, hidden, stealth and deleted activities, a restricted section and a
  subsection, in five scenarios. Ported from block_compass's ADR-027.
- **A fingerprint says when core moves the code the rule mirrors.** `local\core_fingerprint`,
  `tests/fixtures/core_parity.json` (23 core functions, audited against v5.3.1, among them
  `cm_info::update_user_visible()`, which MDL-66780 changed), `tests/local/core_fingerprint_test.php`
  and `cli/core_parity.php`. `docs/core-parity.md` holds the audit procedure and its log. The fleet's
  weekly look upstream covers this plugin.

### Improvements from the comment audit

Version `2026100527` (AMD build).

- **Removed**: the inert `@media (prefers-color-scheme: dark)` block and
  `colour_mode::MEDIA_OPTIN_ATTRIBUTE`. This branch serves Moodle 5.3 only, where core resolves the
  *auto* colour mode itself; `MOODLE_405_STABLE` keeps the block. The hub modal's Bootstrap 4 margin
  fallback goes too.
- **Removed**: `calculator::current_user_has_pending_application()`, which had no caller left; call
  `calculator::has_pending_application($courseid, $userid)`.
- The course completion service answers the other courses when one course fails with any error, as
  the progress service already did.
- The user evidence report's file count reads only the owner's user context, as core stores those
  files, and can use the files index.
- The template CSV export checks a framework the viewer cannot read once, not once per competency.
- The Competency hub's context bar no longer needs jQuery, and its deletion dialogue names every
  string literally. Smaller tidying: one plan read in the competency summary service, one option
  splitter for the chip filters, `unaccent` ensured from one place, and no e-mail address in
  `@copyright` lines.

### Fixed

- **The tracker counts progress only for learners, as the plan overview does** (version
  `2026100526`). Core records completion only for its tracked users: those enrolled with
  `moodle/course:isincompletionreports`, which by default only the student role holds. The plan
  overview's progress bar already asked `completion_info::is_tracked_user()` and drew nothing for
  a teacher or manager. The tracker's section rings did not, and drew a card of zeros for a plan
  owner enrolled in such a role. The tracker now asks the same question. An untracked owner's card
  says "Progress is tracked for learners only." where a course without completion says
  "Completion disabled.", and `local_dimensions_get_course_progress` returns a new `tracked`
  field. An owner who is not enrolled keeps the locked card. A manager enrolled as a student is
  tracked, and, holding `moodle/course:ignoreavailabilityrestrictions`, has every restricted
  activity in their workload, as core counts it.

- **The Competency hub's "Connection lost" toast** appears again for a dropped connection, a
  timeout and a 502 or 504 from a load balancer. Moodle 5.3's core/ajax is fetch-based and rejects
  those with error objects (fetch's TypeError, an AbortError, `Error('HTTP n: ...')`), never the
  bare string the hub looked for, so they opened core's technical exception dialogue unless the
  browser reported itself offline. Web-service exceptions and script faults still get the dialogue.
  Version 2026100525 (AMD build).
- **Learning plan reports: a completed plan's competency count, proficient count and progress**
  come from the competencies archived at completion, as core and the plugin's own *Learning plan
  competencies* source list them. A completed plan based on a template counted the template's
  current competencies, so editing the template afterwards changed the numbers of plans already
  completed.

### Code comments reviewed

- The comments of the production code changed since the previous review (classes, AMD modules,
  templates, `db/`, the top-level pages and `styles.css`) say the non-obvious why, the contract and
  the edge case, and no longer carry history or development-environment detail; claims that
  contradicted the code were corrected. No behaviour changes. The AMD build is regenerated because
  its source maps carry the comments (version 2026100524).

### Plugins directory readiness

Version `2026100523`.

- `tests/marketplace_readiness_test.php` is the fleet template again, with this plugin's two
  documented departures (the `styles_<theme>.css` sheets and token-only root rules). It now splits
  a selector list at top-level commas only, skips the paths `thirdpartylibs.xml` declares, and
  checks that every Mustache template carries core's whole GPL block.
- Sentence case in `lang/en`: *Learn more*, *Submit evidence*, *Full plan overview* and
  *Competency tracker* in the strings that still capitalised them; `lang/pt_br` in step.
- The collapsible description's custom properties carry the plugin's prefix:
  `--local-dimensions-collapsible-max-vh` and `--local-dimensions-collapsible-fade-color`.
- README: the version table lists each branch's latest release instead of version numbers that
  had gone stale; "Data stored" names the browser storage (two pane widths in `localStorage`,
  the *Return to plan* button's position in `sessionStorage`); the documentation heading is in
  English only.

### Competency hub v2: the tree as a canvas of nodes (preview)

A second view of a framework's competency tree, beside the Competency hub it does not replace.

- **A new page**, `hubv2.php`, under *Site administration > Competencies > Competency Dimensions >
  Competency hub v2*, with the hub's capability (`moodle/competency:competencymanage`) and absent
  while competencies are disabled. It honours the hub's `contexttype`, `categoryid` and
  `frameworkid` parameters, defaulting to the hub's remembered context, which it never writes.
  A GET form chooses the context (the category through the hub's category search) and the
  framework; *List view* opens the same framework in the hub's Competencies tab.
- **The canvas** (React, `js/esm/src/hubv2`, one bundle) lays the tree out left to right, loads
  each branch when it is first opened, draws rule edges and the selected competency's related ones,
  and has search, zoom, fit and an overview map. The panel shows the selected competency and acts
  through the hub's own dialogues and core's competency services, plus one read web service,
  `local_dimensions_competency_delete_check`: no database change.
- **Deleting is checked first, on the canvas and in the hub's Competencies tab.** The delete action asks `local_dimensions_competency_delete_check`
  (read-only, `moodle/competency:competencymanage` in the framework's context) before attempting
  anything. When core would refuse (the competency or a descendant is in a template, a plan, a
  course, a user's record or a badge) a dialogue says why, with counts that are exact only for a
  site-level manager (a course category's manager sees which reasons apply, not how many); otherwise the confirmation
  names what goes with it: the descendants, their relations and their prior-learning links, and the
  parent's rule that core resets, whether a branch or a single leaf is deleted.
- **React Flow** (`@xyflow/react` 12.12.1, MIT) is vendored under `js/esm/vendor/xyflow` and
  declared in `thirdpartylibs.xml`; its rules are ported into `styles.css` under the page's own
  class, painted with the plugin's colour tokens.
- `stylesheet_markup_contract_test` now reads the classes the ES tree writes; `bundle_test`
  holds the bundle as fresh as its sources and the vendored release.

### The Competency hub honours unlisted course categories

Any logged-in user passes the hub's category readability check (core gives every authenticated
user `moodle/competency:competencyview`), so the hub's category picker could name a course
category that local_unlistedcourses has unlisted.

- **Withheld everywhere the hub names a category.** The picker search
  (`local_dimensions_search_categories`), the selected option, the context the Structure,
  Frameworks and Plans tabs resolve, and the category entry (`central.php?pagecontextid=`) leave
  out a category local_unlistedcourses withholds from the viewer, and any category below it. The
  category entry refuses it with the same error as a missing context. Without
  local_unlistedcourses nothing changes.
- **Template counts for template readers only.** The picker's per-category counts, the locked
  bar's subtree count and the site total of learning plan templates read 0 for a viewer who may
  not read templates there, since core shows such a viewer no template.
- **Unlisted subcategories in the category entry.** Entered from a listed category, the hub lists
  that category and the ones below it. A framework or learning plan template that lives in a
  subcategory local_unlistedcourses withholds from the viewer is now left out of the Frameworks,
  Structure and Plans tabs and of the bar's subtree counts. The category each object lives in
  decides. Without local_unlistedcourses the whole subtree is listed, as before.

### The fleet's course card on the Full plan overview

The *Related content* tab's course cards follow the fleet's course card contract
(`moodle-dev/docs/course-card/CONTRACT.md`), shared with theme_dimensions and block_compass.

- **One card shape.** A 150 px cover, the course name (two lines at most), then one line saying
  what completing the course does to the competency (*Completes the competency* with a trophy,
  *Sends for review* with a paper plane), then the progress row: a 6 px bar and the percentage in
  tabular figures, with `role="progressbar"` and a label. A course with no completion tracking
  draws no row instead of "0%". A completed course draws the green *Completed* seal in place of
  the bar and a green base under its cover. The *N activities* drawer stays as the card's foot.
- **One target.** The course name is the card's only link and stretches over the whole card, so a
  screen reader hears one link named by the course; the card's focus ring is that link's, the hover
  deepens the shadow and underlines the name, and nothing moves. A card whose course has one
  activity or one section keeps the activity's line or the section's ring in place of the bar and
  gets *Go to activity* or *Access content* as a button. A card the viewer cannot open is not a
  link: its cover recedes and the state's own action is its button.
- **Equal heights.** The cards of one carousel share a height and line their feet up.
- **Boost Union's settings.** Under Boost Union or a child theme, *Show course progress*, *Course
  image source* and *Show course images* decide the cards, read on the server
  (`local\card_settings`): progress off removes the bar and its percentage (the seal stays), the
  card view's images off removes the cover and draws a 4 px top edge, and a course with no image
  gets the theme's generated one (`local\course_card`), asked for only when such a course is in
  the answer. The README explains the three settings.
- **Tokens.** Every visual value of the card is one of thirty `--local-dimensions-card-*` tokens,
  each reading the family's `--dimensions-card-*` first, so theme_dimensions restyles the card
  without a selector. `tests/local/course_card_contract_test.php` is the fleet's parity test.
- `local_dimensions_get_competency_courses` returns `hascompletion`, `showprogress` and
  `showcover` per course, and `courseimage` may now be the theme's generated image (a base64 SVG
  data URI or a pluginfile URL of this site, checked on the server).
- The course name no longer takes core's dark-mode underline of `#region-main` links at rest.

### Performance: the competency tracker loads its cards in one request, behind a skeleton

Version `2026100522`.

- **One request instead of one per course.** The tracker asked for the completion status of every
  course and then for each course's progress, one request after another (with a 2 s overlap), so
  a page of six courses made seven round trips. The status call and the progress of up to 20
  courses now share one HTTP request (core/ajax runs them in order on the server); a longer list
  is cut into batches of 20 sent together. The server's work is the same: on the 2026-11-17
  fixture, a warm batch of six heavy courses took 95 ms and 17 reads, the same work the six
  single calls did between them. Every card is drawn once the status has answered, because a card reads it.
- **A failed call costs only its own cards.** A batch that fails, or answers without a course,
  leaves each of its cards the error state and its Retry button, which asks for that course
  alone. When the status call fails, core skips the rest of that request: the first batch's cards
  show Retry and the completion tabs stay hidden, as before.
- **A skeleton instead of a spinner** (fleet Design System, `components/skeleton.md`): grey
  timeline rows, one per top-level visible section of the course (between three and twelve; a
  locked card three), counted for every card in one query on `{course_sections}`, so the body
  that replaces it lands at nearly its height. The rows pulse every 1.3 s and stand still under
  reduced motion; the grid is `aria-busy` with one spoken status line until every card is drawn
  or failed. Layout shift of the fixture's six-course tracker of nine-section courses: 0.060 with
  the spinner, 0.011 with the skeleton.
- **Code scanning alert 6**: the tab strip read a millisecond duration with
  `indexOf('ms') === length - 2`, true for any one-character value, so a theme writing `s` in
  `--local-dimensions-tabs-scroll-duration` made the scroll duration NaN; it now reads the suffix.
  **Alert 9**: the README imagery tool (`docs/`, not shipped) built the script it evaluates in
  Chrome from the SVG markup; it now passes the markup as an argument to a fixed function.
- Tests: `learner_js_source_test` (batches after the status, a failed batch leaves every card a
  retry, the suffix), `view_competency_page_test` (a skeleton row per timeline section),
  `stylesheet_markup_contract_test` (busy, silent and still under reduced motion); gates in
  `mutations/tracker_batch.conf`.

### Performance: progress in very large courses

Version `2026100521`.

- **A course card no longer reads the whole course's group membership.** Whether a group,
  grouping or profile restriction keeps an activity out of a learner's workload was asked through
  core's `filter_user_list()`, which is built for long lists: for one learner it read every member
  of the group, every member of the grouping, or, for "any group", every group member of the
  course, and listed everyone holding `moodle/course:ignoreavailabilityrestrictions`, on every
  activity, three times per card (bar, card shape, section rings). `local\learner_restrictions`
  now gives the same answer for that one learner: group and grouping conditions through their own
  per-user test, which reads the learner's groups from core's shared groups cache; any other
  condition through its own filter with a one-person list; the capability through
  `has_capability()`. The rule is unchanged: only the restrictions that are permanent for a person
  count, so a date-locked activity still counts.
- Measured on a 5.3 twin stack with one course of 100,001 students, 2,000 groups (about 50 members
  each, 5% of students in two groups, 1% in three, 1% in none, 100 groups with hidden visibility),
  20 groupings of 100 groups, 45 teachers in 30 groups each, and 66 restricted activities: the
  tracker's `get_course_progress` for that course fell from 470 ms and 402 queries to 8.6 ms and 8
  queries, and its card on `view-competency.php` now appears at 753 ms instead of 1,116 ms (median
  of five loads at 4x CPU). The three card calls fell from 1,111 ms to 36 ms per learner (mean of
  240 users, 4.5: 1,258 ms to 28 ms). All 16,080 learner-activity answers (4.5: 15,840) and all
  240 cards were identical to the previous code's.
- `tests/local/learner_restrictions_test.php` holds core's `filter_user_list()` as the oracle on
  nineteen restriction sets (groups visible to all, to members, to each member and to nobody, a
  group of another course, grouping 0) for seven kinds of user, read by the learner, a teacher and
  two classmates, and pins that a warm group or grouping check runs no query. `tests/calculator_group_workload_test.php` checks the bar
  and every ring against hand-counted workloads: a learner in two groups (an activity open to
  either counted once), in one group and in none, under forced separate groups, with subsections
  and a section restricted to another group. Mutation spec: `mutations/learner_restrictions.conf`
  (22 gates).

### Performance

Version `2026100517`.

- **The hub modals' close-button chip no longer taxes every page.** The chip keyed on
  `.modal:has(.modal-body [class*='local-dimensions-'])`, and `styles.css` is bundled into the
  theme sheet, so Chrome rechecked that `:has()` on every class change anywhere on any page of the
  site: 16-20 ms of style recalculation per toggle on a 1,700-node page with the CPU throttled
  4x, which a theme's filter line paid several times per fold on a phone. Every modal factory
  now adds `local-dimensions-modal` to the modal root and the chip keys on that class, or on the
  body class of the plugin's three pages.
- **Every modal on the plugin's pages wears the close-button chip.** The hub, the Full plan
  overview and the Competency tracker give it to core's confirm, alert and error dialogues and to
  every form modal too, as well as to the frameworks export, scale configuration, scale description
  and taxonomy definition modals, which kept Bootstrap's faint default before.
- `tests/local/selector_cost_test.php` refuses a `:has()` over the `class` or `style` attribute
  in any shipped stylesheet and checks that every modal the plugin creates carries its marker.

### Competency status badges hook

Version `2026100516`. A new hook, `\local_dimensions\hook\competency_status_badges`, lets another
plugin add plain-text status pills beside a competency on the Full plan overview (in each accordion
header) and on the Competency tracker (after the title). It is dispatched once per page with the
learner who owns the competencies (never the viewer) and the ids on the page; a tone outside the five
tone tokens throws, an id not on the page or an empty label is ignored, and a pill carries no link and
no HTML. Nothing is drawn when no plugin listens. Contract and example: README, "For developers".
Mutation spec: `mutations/status_badges.conf`.

### Security hardening

Version `2026100515`. The surviving points of an external static review, each held by a test and
a mutation spec (`mutations/sec_audit_g1.conf` to `g4.conf`). None let a user past a core
capability; most of the review's other findings matched core's own behaviour and are unchanged.

- **The competency form takes its context from the competency.** Editing an existing competency
  checked the capability in the posted framework's context only, so a manager of one category
  could open another category's competency (its fields and images). The form now resolves the
  competency's own context and refuses a framework id that is not the competency's.
- **The CSV framework import calls only competency rules.** A `ruletype` column naming any other
  class was passed to `migrate_config()` before the persistent refused it; it is now skipped, as a
  missing class is. The importer also checks `moodle/competency:competencymanage` itself, before it
  provisions the custom fields.
- **"Delete all" of a course category is one unit.** It is offered only when the user may manage
  every template and framework below the category, and the deletions run in one transaction, so a
  refusal part-way no longer leaves the templates gone.
- **The evidence button is the owner's.** The plan page tested the plan owner's capability
  instead of the viewer's; it now shows the button only to the owner viewing their own plan, with
  the right to manage their evidence (`plan_access::can_submit_evidence()`).
- **The hub's enrolment toggle and cohort sync stay inside the template.** Switching an enrolment
  instance needs a course linked to the template's competencies, and queueing a sync needs a cohort
  linked to the template.
- **Template export:** a hidden template is exported only to those who can manage it, and a
  framework the caller cannot read is left out of the CSV.
- **Course custom-field chips follow the field's visibility.** A field visible to teachers only, or
  to nobody, no longer reaches students on the competency page when an administrator lists it in
  the filter setting.
- **Report builder.** A completed plan lists the competencies archived at completion instead of
  its template's current ones; descriptions are formatted in their own context; stored evidence
  URLs are links only when they are web addresses; the links are rendered from a template instead
  of `html_writer` (`templates/reportbuilder_link.mustache`).

### Plugins directory readiness

Version `2026100514`. The Moodle plugins directory's submission guidelines, checked by a new test.

- **`LICENSE`** ships beside `COPYING.txt`, both the GPL v3 text (Moodle's tooling still requires
  `COPYING.txt`).
- **Only English ships.** `lang/pt_br` stays in the repository, in step with `lang/en`, but is
  export-ignored: translations reach sites through AMOS.
- **`tests/marketplace_readiness_test.php`** reads the plugin's files and pins the licence files, the
  English-only package, data-only lang files, a README that names every capability and dependency,
  `local_dimensions/<name>` setting names, plugin-scoped selectors in `styles.css` and every
  `styles_<theme>.css`, the GPL boilerplate and `@copyright`, and the privacy cover of core rows
  stamped with the component. A root rule that declares only `--local-dimensions-*` custom properties
  counts as scoped.
- **Built-in images are deleted with their competency or template.** The background and card images
  in the plugin's four file areas outlived a deleted competency or template; the observer now removes
  them (`picture_manager::delete_instance_files()`).
- The hub's user search is pinned to core's `hiddenuserfields` rule too: a hidden identity field
  is shown and searched only for a caller holding `moodle/user:viewhiddendetails`.
- README: new *Settings* (every setting with its default), *Data stored* (what is kept, for how
  long, what removes it, what a data request exports and what a deletion does), *Translations* and
  *License* sections; the version table names the current numbers.

## [v5.3-r1] - 2026-10-18

### Upgrading

- **One branch per Moodle version.** This is `MOODLE_503_STABLE`, for Moodle 5.3 only
  (`$plugin->supported = [503, 503]`, version `2026100500`). It is also `main`. The other Moodle versions are
  served by `MOODLE_405_STABLE`, `MOODLE_501_STABLE`, `MOODLE_502_STABLE`. Each branch numbers its releases inside its Moodle version's
  namespace, as moodle.org expects, so every number is below the date-numbered releases that
  came before (the last was `2026101301`, v1.0). Moodle 5.0 is no longer supported.
- **Switching a site from a date-numbered release** (any of them; no intermediate release is
  needed):
  1. Replace the plugin's files with this branch.
  2. `php admin/cli/cfg.php --component=local_dimensions --name=version --set=2026100499`
     (do the same for block_dimensions when it is installed, with its own branch).
  3. `php admin/cli/upgrade.php`.

  Step 2 comes after step 1; without it Moodle refuses the new files as a downgrade.
- **Upgrade steps are repairs now.** The numbered steps of the date-numbered releases are gone.
  Their lasting effects (prefixed custom field shortnames, the fifth enrolment-filter option,
  two retired settings, PostgreSQL's `unaccent` extension) are applied by
  `local\legacy_repair` on every upgrade, and each leaves an up-to-date site unchanged. The
  enrolment-filter repair now counts the options instead of comparing the label, so an upgrade
  run in another language no longer adds the option twice. The shortname repair leaves a field
  alone when its area already has a field with the new name.

### Changed

- The 5.3 line uses PHP 8.3 constructs: `#[\Override]` on the 185 methods that override or implement
  a core or plugin method (core signature drift is now a compile error, not a silent new method),
  typed class constants on all 180 constants under `classes/`, and readonly properties on value
  objects (`view_competency_page`, `view_plan_summary_page`, the hub's `contextbar` and
  `template_import_preview`, and `template_import_plan`'s constructor-built fields). Branches for
  Moodle 4.5, 5.1 and 5.2 keep the older syntax.

- Web services: the 27 read services now declare `'readonlysession' => true`, as core does for its
  own reads, so they no longer hold the session lock for the whole call. This covers the plan
  accordion's per-competency loads (`get_competency_courses`, `get_user_competency_summary_in_plan`,
  `get_course_progress`), the tracker's, every read of the Competency hub, and the enrolment-queue
  poll. They no longer wait for one another, or for the page's other requests of the same session.
  It takes effect only on a site that sets `$CFG->enable_read_only_sessions`, with its
  session-mode caches mapped outside the session store. Nothing on these paths writes `$SESSION`:
  `helper::ensure_all_fields()`, the plugin's one session writer, runs only from the footer hook,
  and `returncontext`, the one session-mode cache, is touched only by the return-context write
  service. `tests/external/readonly_session_test.php` checks every read and no write carries the
  flag, and compares `$SESSION` around each of the 27 calls. Measured on Moodle 5.2 with the site
  flag on, four simultaneous calls of one service took 3.1 to 7.3 times one call before the change
  and 1.0 to 2.3 times after it. Two accordion expansions fired together went from 463-479 ms to
  118 ms.

- Course cards: an enrolled card no longer shows an "Enrolled" pill, on the competency tracker and
  the plan accordion; every other state keeps its pill. On the tracker the state pill carries no
  icon (the locked card's disc keeps it). The "cardicon" setting description now says the icon
  replaces the disc icon only on a locked card with no enrolment available.

- Report builder: in the "Learning plans" report source, the template's Dimensions custom fields
  moved from the "Learning plan template" entity to their own entity, "Learning plan template:
  Dimensions custom fields" (`template_extras`), as the competency custom fields already were
  (`competency_extras`). The upgrade moves the columns, filters, conditions, stored condition
  values and stored user filter values of saved reports to the new entity
  (`legacy_repair::move_template_customfield_report_elements()`, once per site).

- Report builder: the custom fields that only style or steer the learner pages (display mode,
  card and background images, colours, custom SCSS, subline source, enrolment filter,
  single-course redirect, locked-card mode, availability date, related competencies) are no
  longer offered as columns, filters or conditions, in both Dimensions custom field entities
  (`constants::REPORT_HIDDEN_CFIELDS`). Tag 1, Tag 2, Type, the template identifier and every
  field an administrator adds stay offered. A saved report that used a field now hidden drops
  that column, filter or condition.

- Report builder, "Competencies with custom fields": the user competency "Rating"
  column names the scale item without reading the competency and its scale for every row; it
  reads each scale once per request (`local\reportbuilder\local\helpers\rating`). The entity is
  the plugin's `usercompetency`, a subclass of core's with the same names, so saved reports keep
  their columns; it adds a "Rating (position in the scale)" filter and condition. New reports
  start with the competency's Dimensions type and Tag 1 columns.

- Report builder, "Learning plans": the cohorts entity is titled "Learning plan template cohort",
  and a new "Plan owner cohort" entity (`usercohort`) gives the cohorts of the plan's owner. The
  numeric "User" filter of the "Learning plan" entity is gone (the plan owner's user entity
  filters by name); a saved report that used it drops that filter. New reports no longer start
  with the "Visible" template condition, which left out plans without a template.

- Report builder: the "Competencies with custom fields" and "Learning plans" report
  sources are offered only while competencies are enabled, as "Course and activity
  competencies" already was.

### Fixed

- Report builder: the "Status" column of the "Learning plan" entity no longer fails a report that
  aggregates it as an average or a sum; it is now a text column, like core's user competency
  status, so only the aggregations that make sense for a category are offered.

- **The hero meets the navbar under Boost Union with a smart menu** (`2026100505`). Boost Union moves
  `#page` down by the navbar plus the menu bar (40px) whenever a smart menu exists, even when no menu bar
  is drawn, which left a strip of page between the navbar and the hero on the plan overview and the
  tracker. A new `styles_boost_union.css`, loaded only under Boost Union and its children, puts `#page`
  back at the navbar height on those two pages while no menu bar is drawn (`boost_union_sheet_test`).

### Security

Fixes from the MDL Shield review of 2026-10-18 (version `2026100513`):

- **Framework CSV import no longer creates site scales with markup, or for users who may not create
  them.** Scale items are cleaned with `PARAM_TEXT`, as core's scale form does, before an existing scale
  is matched. A new site scale is created only for a user holding `moodle/course:managescales` at system
  level. Anyone else (a category-level competency manager) can still import against an existing scale
  with the same items and otherwise gets an error naming the items, before anything is written. A
  framework row with no scale values is refused instead of creating an empty scale.
- Report builder: the rating columns and the evidence "Description" column escape their text through
  `format_string()`, as core's evidence exporter does.
- The competency tracker and the accordion's course list name only the linked courses the viewer may
  browse (`core_course_category::can_view_course_info()`), as the course cards already did.
- The hub's Enrolment methods tab checks each course the way core's enrolment forms do: the role
  must be assignable in that course and the cohort visible from it, both when the action is queued and
  again in the adhoc task. A course that fails either check is reported as skipped.
- Custom fields posted alongside core's own competency and template forms are saved only after
  passing each field's own validation and the custom SCSS check.
- The template import preview names another context or framework only when the importer may read it;
  otherwise it says "another context" or "another structure".

### Added

- Report builder: more columns (version 2026100511).
  - "Learning plan": name with a link to the plan, "Based on a template", "Original template",
    "Competencies", "Proficient competencies" and "Progress" (the share proficient, from the
    archive once the plan is complete), with filters on the last four. A "Plan reviewer" entity
    (`planreviewer`) in "Learning plans" and "Learning plan competencies".
  - "Learning plan template": short name with a link to the template, its context, and the number
    of plans and cohorts, with filters on both counts.
  - A "Competency: hierarchy and links" entity (`competency_details`) in every report source that
    shows the competency: the parent, the path of ancestors (each framework's names read once per
    request), the rating scale (the competency's own or its framework's), and the number of
    linked courses and related competencies.
- Report builder: two report sources for ratings (version 2026100510), offered while competencies
  are enabled.
  - "Competency ratings in courses" (`course_competency_ratings`): one row per rating a user
    received for a competency in a course, with the rating and proficiency, the user and their
    cohorts, the course and its category, and the competency with its framework and Dimensions
    custom fields.
  - "Learning plan competencies" (`learning_plan_competencies`): one row per plan and competency
    of that plan (its template's, or its own when it has no template), with "From the template",
    the proficiency and rating in the plan (from the archive once the plan is complete), the
    owner's current user competency, the plan, its template and Dimensions custom fields, the
    owner and their cohorts.
- Report builder: two report sources for competency evidence (version 2026100509), offered while
  competencies are enabled.
  - "Competency evidence" (`competency_evidence`): one row per evidence, with its action, its
    description, link, rating (named from the competency's scale, read once per scale), note and
    date; the user competency, competency, framework and Dimensions custom fields; the learner;
    the "Evidence author" (`evidenceauthor`); the "Evidence context"; and the course of a course or
    activity context.
  - "Evidence of prior learning" (`competency_user_evidence`): one row per evidence and linked
    competency, with its name, description, link, number of files and dates; the user; the
    competency, framework and Dimensions custom fields; and the user's competency. An evidence
    linked to nothing keeps one row.
- Report builder: a "Course and activity competencies" report source (`course_competencies`,
  version 2026100507), one row per competency linked to a course, with the course, its category,
  the competency, its framework, its Dimensions custom fields and the course rule ("Upon course
  completion", sort order, "Push course ratings to individual learning plans"); the activity
  entities add the activities of that course linked to the same competency, with their name, type,
  rule and "Override the existing rating". New entities `course_competency` and
  `module_competency`; the core course module entity gives the activity's ID number and
  visibility. The source is offered only while competencies are enabled.
- **The filter line** (version 2026100504; the fleet's contract in
  `moodle-dev/docs/filter-line/CONTRACT.md`, stage D1). The plan overview's sticky toolbar is one
  line in the contract's order: the scope ("To do" and "All", a radio group with arrow keys), a pill
  search with an eraser, a favourites star that replaces the two favourites pills and keeps the same
  stored "favourites only" choice, Filters (the chip panel, with a count badge, a count on every chip
  and "Show N" at its foot), Organise (the sort menu) and List and Cards. A row of chips under the
  line names each active filter, with "Clear all" from two chips up. The line folds on its own width
  in four steps - Filters loses its word, the search becomes an icon, then two lines with the scope on
  the second and the view inside Organise ("Show as") - so the narrow-screen "Show filters" button is
  gone. From the third step the search grows over the line, with a close button, Escape, and the focus
  given back to the control that opened it; the suggestions offer the filters first (each with the
  number it would leave without the typed text, matched without accents or case) and then the
  competencies whose names match, and `/` opens the search. At the fourth step the Filters panel is a
  bottom sheet. The competency tracker gets the same line: the scope (now opening on "To do"; a
  learner who chose "All" keeps it), a search over the course cards with a "no results" state, and
  Filters where course fields are configured. Each view remembers its own scope in
  `local_dimensions_learner_view` (`filter` for the plan, `trackerfilter` for the tracker); the search
  text is never remembered. New module `local_dimensions/filter_line`, template
  `local_dimensions/filter_line`, class `local\learner_view`; strings `line_*`; the strings the old
  toolbar alone used are removed (`fav_only`, `fav_showall`, `filter_toggle`, `search_competencies`,
  `sort_label`, `toolbar_adjust`, `view_grid`, `view_label`, `view_list`).
- **Family colour tokens.** Under `theme_dimensions` the plugin reads the theme's family tokens
  (`--dimensions-*`, contract in `theme_dimensions/docs/tokens.md`) first, in the light and the dark
  rules (including the local-only motion and state tokens), with its own chain as the fallback.
  Without the theme nothing changes.
- Course cards (competency tracker and plan overview), with "Use the enrolment rules of Unlisted
  courses" on: a locked card whose only refusal is an enrolment window that has not opened yet says
  "Enrolment opens on <date>", whatever "Show availability date" says; on a card with no
  enrolment that chip replaces the "Opens <course start>" chip, and a learner whose enrolment is
  suspended or has ended gets it too. In learn-more mode the chip sits above the "Learn more"
  button. The date is local_unlistedcourses' (its next
  action's `opens`, from version 2026042005); an older build of that plugin shows no chip. Both card
  services return the date and the chip's sentence in the state area (`opens`, `openslabel`).
- Course cards (competency tracker and plan overview): a shared state area. Each card names the
  viewer's enrolment in one pill, with an icon and the same words and colours as the course cards of
  theme_dimensions: "Enrolled", "Access from <date>", "Application under review", "Open for
  enrolment" and "No enrolment available". When the viewer may also enrol now beside an enrolment that
  starts later or an application, the card says both, with "You can also enrol now" under the pill.
  On the tracker the locked card keeps its frame (dashed border, disc, raised message box, blurred
  sections); the disc and the message take the state's colour and icon. On the plan overview, a card
  the viewer cannot open dims its image only and is no longer a link: its button leads on.
- Optional setting "Use the enrolment rules of Unlisted courses", shown only where the
  local_unlistedcourses plugin (Moodle 5.2 and later) is installed, off by default. When on, the cards
  also show "On the waiting list", "Enrolment suspended", "Enrolment ended <date>", "Free access",
  "Access with a key" and "Unlocks when you complete <course>", with that plugin's rules and links to
  the enrolment page; local_dimensions keeps no copy of those rules.

### Changed

- Course cards: an enrolment that starts later is named in the pill ("Access from <date>") whatever
  the "Show availability date" setting says; that setting now shows the course start date on a locked
  card that offers no way in. The custom locked-card icon and the "Learn More" mode apply to that card
  only. A pending application no longer gives way to an open enrolment: the card says both.

Decided by the owner after the comment audit's findings were re-checked against the current code:

- The status icons on the competency tracker's course cards (completed, locked, not started, no
  completion tracking) and the grade badge icons in the plan overview now take their colours from
  the theme and follow dark mode and Windows high-contrast mode; in light mode the completed check
  now uses the theme's success colour.
- The status icons on the competency detail's Rules tab (proficient, in progress, to do) and the
  calendar beside a plan's due date now follow the theme, dark mode and Windows high-contrast mode
  too; the calendar takes the text colour set for the plan's header.
- The learning plan template settings now show 'Locked card display mode' and 'Show availability
  date' in 'Full plan overview' mode as well, because the plan overview uses them.
- Plan accordion: when staff open a learner's plan, the course cards now show the learner's courses,
  progress, activity completion, activity restrictions and card shape rather than the reviewer's
  own; what clicking a card does (open, enrol, pending, locked and its date) is still the
  reviewer's.
- Competency tracker: when staff open it from a learner's plan, the course list, the course cards'
  progress, completion, section restrictions and card shape, and the completed-courses count now
  describe the learner rather than the reviewer; whether a card opens (and its lock, date, enrol and
  pending state) is still the reviewer's, and a reviewer enrolled in a course without a student role
  is no longer shown it as locked. A reviewer who may read a draft plan but not the learner's
  competencies is refused the tracker, as on Moodle's own competency page for a plan.
- Learning plan CSV import: a competency structure named by ID number is now resolved only among the
  structures offered in the target category (its parent and child contexts), so a structure in a
  sibling category is reported as missing instead of being linked.
- Competency hub, Structure tab: the footer's 'Competency rule' button is disabled for a competency
  without child competencies, which a rule cannot be set on, and its tooltip says why.
- Plan overview, competency detail: prior-learning evidence keeps its trophy and now shows a
  paperclip beside it, in the evidence list and in the evidence details, when the linked
  prior-learning record holds attached files at that moment. Only people allowed to open the
  learner's prior-learning records see it (the learner, and staff with that permission); for anyone
  else the page does not say whether the record has files.

### Fixed

- `get_competency_rule_data` no longer fails with a `TypeError` when a competency's stored rule
  configuration is a JSON scalar (`5`, `true`, `"x"`): only a JSON object or array is read as a
  configuration, and anything else, malformed JSON included, reads as none.
- `get_courses_completion_status` no longer fails with `Class "completion_info" not found` when a
  request carries it alone: it loads `lib/completionlib.php` itself instead of relying on a service
  earlier in the same `core/ajax` batch to have loaded it. `calculator::get_course_section_progress()`
  loads it too, rather than through `get_fast_modinfo()` pulling in `course/lib.php`.
  `tests/local/completionlib_require_test.php` fails when a method under `classes/` names
  `completion_info` without requiring the file itself; a behavioural test cannot go red for this,
  because the PHPUnit bootstrap loads completionlib (through `mod/glossary/lib.php`) in every process,
  separate ones included.
- The tracker card shows "Opens on <date>" only while the course start date is still ahead, in both
  locked-card modes, as the plan accordion already did. A start date that has passed is no longer shown.
- A course card no longer announces "Enrolment opens <date>" for an enrolment that cannot open: a
  suspended enrolment, or one on a disabled enrolment method, gives no start date, and neither does a
  period that ends before it starts. The card shows the generic locked state with the course start date.
- An enrol_apply application stays shown as "Application pending" when the enrol_apply plugin or its
  instance has been switched off, as long as the plugin's own queue still counts it. No way in is
  offered while nobody can decide it.
- The tracker card of a user's own course no longer locks an active enrolment that carries no student
  role (a teacher, or an enrolment method that assigns no role), so it is never offered "Enrol to
  start" while enrolled. It asks only whether the user is actively enrolled, as the plan accordion does.
- The tracker card of a course the viewer may not be told about no longer shows an empty "Opens" date
  chip, nor a "Learn more" button that links to nowhere, but the plain locked message.

The findings the 2026-09-23 comment audit reported, re-checked on 2026-09-25 against the code other sessions had
changed since, and fixed where they still held.

#### Learner pages

- The competency detail's result strip now shows the most recent rule completion instead of the
  oldest one.
- Prior-learning evidence is no longer labelled a file attachment because the word 'file' appears in
  the name the learner gave it.
- Favourite competencies are trimmed to what a Moodle 4.5 user preference can hold (other plans'
  oldest favourites go first), instead of every later star silently failing to save.
- Course cards on the learner pages unlock for learners enrolled under any student-archetype role,
  not only a role whose shortname is 'student'.
- The Rules tab's points unit comes from the language pack instead of a hardcoded English 'pts'.
- The progress bar of a rule that counts completed items now announces items, not points, to screen
  readers.
- The competency tracker's course-card load error, retry button and loading text are never shown in
  English on a non-English site.
- The locked course card's opening date follows the language pack's short date format instead of a
  fixed day/month/year order.
- The plan overview now shows core's error when the plan's competencies cannot be read, instead of
  an empty plan.
- Course chip filters on the competency tracker now show values from select-type course custom
  fields (their labels were empty, and every course field raised a PHP warning), and all courses'
  values are read in one query.
- Scale ratings, tag sublines and chip filter values on the learner pages are filtered to the
  learner's language (multilang) and stripped of markup.

#### Competency hub

- Competency hub, Structure tab: closing 'Courses & activities' now updates the competency's
  linked-course count with the true total, not only the courses loaded so far.
- Competency hub, 'Browse frameworks' and 'Related competencies' pickers: a search or framework page
  that answers after the filter or framework changed no longer adds old competencies, or a stray
  empty-state message, to the list.
- Competency hub, Enrolment methods tab: changing the method, cohort, category, hidden-course toggle
  or search while a request is in flight no longer shows one method's status under the other or
  lists competency groups twice.
- Competency hub: script errors and broken server responses now open the error dialogue instead of a
  misleading 'Connection lost' notice.
- Competency hub, Structure and Learning plans tabs: a pane-divider drag that the browser interrupts
  (for example a touch turned into a scroll) now ends cleanly instead of resizing on later hover.
- Competency hub, competency rule dialogue: negative or fractional points are refused with the
  inline message instead of failing when saved.
- Competency hub: the highlight on a changed row now uses the theme's warning tint, so it follows
  the site palette and dark mode.
- The icon picker now caches the Font Awesome icon map it builds from core when Boost Union is not
  installed. Each search no longer rebuilds it. The cache is listed as 'Font Awesome icon map for
  the icon picker'.
- The framework export dialog now announces 'Exporting...' to screen readers on every export, not
  only the first one in the dialog.
- The points-rule alert now names every condition that makes a points configuration invalid: points
  must be whole numbers of zero or more, the required points must be at least 1, and the total
  available points must be at least the required points.
- The cache administration page no longer calls the plan trail cache a session cache.

#### Web services and import

- Rules tab: on a completed learning plan, each rule child the plan held now shows the rating
  archived when the plan was completed, instead of a live rating given afterwards.
- Linking a competency to a course or an activity that is already linked no longer logs a second
  'link added' event.
- The rating scale's 'About this scale' description is now formatted once, in the competency's
  context, so text filters no longer run twice and their output is no longer stripped.
- Enrolment methods tab: a negative offset or a page size below 1 now returns the first page at the
  default size, instead of the list's tail or nothing.
- Competency browser service: a page size below 1 now returns the declared default of 25 (it
  returned 50), and the query parameter states its 2-character search threshold.
- Participants grid: a learner holding only 'Manage own learning plans' can now unlink and delete
  their own template-based plan, as core and the grid's own manage flag already allowed.
- Detaching a cohort from a learning plan template now goes through core and is refused while
  competencies are disabled on the site.
- Template cohort roles listing now reads its cohorts, role holders and sync counts in a fixed
  number of queries, however many rows there are.
- A competency's activity links are now read for the requested course only, not for every course on
  the site.
- Learning plan import preview no longer fails outright when a missing structure's ID number or name
  in the file contains markup.
- Course progress: an error while reading one course now becomes that course's error card, instead
  of failing the tracker response for every course.
- Learning plan import preview: a competency matched by ID number inside a structure found only by
  its name is now badged as a fallback match ('Matched by structure name'), not as an exact match.

#### Custom fields and templates

- Opening a learning plan no longer creates missing template custom fields outside the provisioning
  lock. A missing field now falls back to the site-wide setting until provisioning restores it.
- Custom-field provisioning no longer fails on Moodle 5.1+ when every plugin field category has been
  deleted and a core shared category is enabled for the area. The fields now go into a new plugin
  category.
- In the Competency hub structure tree, type and tag labels no longer shift or disappear when a
  select field's option list contains a blank line.
- The audit events of a hub modal save now report 'isnew' correctly: editing a template or a
  competency logs false and creating one logs true, in both the values event and the image event.
- Copying plugin data onto a duplicated template now deletes the files embedded in the custom-field
  rows it replaces, where they used to be left orphaned.
- A blank line in a tag or type field's option list no longer shifts or blanks the label shown on
  templates and competencies, and a template setting whose stored option no longer exists now reads
  as inherit, which is how the settings form shows it.

#### Caches, events and tasks

- Plan cards: a completed plan lists the competencies it was completed with, as core's plan page
  does, instead of the template's current list.
- Plan cards: a learner's competency trail updates as soon as a teacher rates them or evidence
  arrives. The plan_trail cache is now shared by all users, so invalidating it reaches the learner's
  entry. Before, the old trail could stay for up to 5 minutes.
- Competency and template observers: a POST without a sesskey (for example a web service call) is
  now ignored quietly. It used to throw inside the observer and skip the cache invalidation.
- Template cohort sync: the background plan generation now finishes and logs the reason when the
  template is hidden, competencies are disabled, the task user can no longer view the template, or
  the cohort is hidden from that user or deleted. Before, it failed and was retried.
- Course category deletion: "Delete all" is not offered while a framework or template in the
  category or any subcategory is in use. Before, an in-use subcategory let the parent's frameworks
  and templates be deleted before the deletion stopped half way.
- Framework modal: an ID number already used by another framework anywhere on the site is reported
  on the field, not as an error on save.
- Course log restore now maps the competency, course, course module, role and enrolment instance ids
  of the hub's course link, module link and enrolment method events, and no longer prints a
  developer warning.
- Developer: removed the unused lib.php wrappers local_dimensions_set_return_context(),
  local_dimensions_set_return_context_for_course() and
  local_dimensions_get_return_context_for_course(); call \local_dimensions\helper directly.

#### Accessibility and styles

- the custom SCSS field in the Competency hub's competency and template dialogues now hides its
  redundant text-format selector and shows its intended dark code-editor styling. Both rules
  targeted an outdated field name and never applied.
- screen readers now announce the wait while a structure (competency framework) is exported from the
  Competency hub.
- the per-competency points and required inputs and the total-required input in the competency rule
  dialogue now have accessible names.
- on touch devices, dragging the pane divider on the Competency hub's Structures and Learning plans
  tabs no longer turns into a page scroll.
- the course card header in the competency tracker uses the Bootstrap 5 bold utility (polyfilled on
  Moodle 4.5), not a Bootstrap 4 name that Moodle 5.x deprecates.

### Security

- **The plan accordion's course cards answer only a viewer who may read the learner's competencies.**
  `local_dimensions_get_competency_courses` returned a learner's enrolled courses, course progress
  and activity completion to a viewer who could read their draft plan but not their competencies (a
  custom role holding only `planviewdraft`), whom Moodle's own competency pages refuse. The accordion
  never asked for that viewer, but the service could be called directly. It now asks what the
  competency tracker asks (`plan_access::require_owner_readable()`).
- **The competency tracker, the Rules tab data and the accordion's course cards answer only for a
  competency the plan reaches.** `view-competency.php` rendered any competency by id to anyone who
  could read a plan of their own; `local_dimensions_get_competency_rule_data` returned the plan
  owner's ratings to a reader core would refuse them (a draft reader holding only
  `planviewdraft`); and `local_dimensions_get_competency_courses` answered for any competency and
  hid a failed plan read. All three now read the plan through `plan_access` and ask
  `plan_access::competency_scope()`, which admits the plan's own competencies (the archive for a
  completed plan), a related competency only when `showrelated` and `showrelatedlink` resolve on for
  the template and the viewer holds `moodle/competency:competencyview` in its context (the links the
  accordion renders), and a direct child of a rule-bearing plan competency (the Rules tab's links).
  Anything else gets the same not-found answer as a missing id. The rule data service also asks
  core's `user_competency::can_read_user()`, as `api::get_plan_competency()` does.
- **The hub no longer shows hidden templates, or another template's courses, to those who may not
  see them.** `local_dimensions_competency_usage` listed hidden templates to users holding only
  `templateview`; `local_dimensions_get_enrol_queue_status` answered for any course id and any cohort.
- **The participant search follows `showuseridentity`.** It searched and returned email, ID number
  and username whenever the caller held `moodle/site:viewuseridentity`, so a site listing only email
  (the default) exposed the other two and let the search confirm a username. It now uses core's
  `\core_user\fields::get_identity_fields()`.
- **An exception message can no longer void a whole web-service response.** The template import and
  the course progress service returned raw exception text in `PARAM_TEXT` fields; under developer
  debugging a DML error's SQL made `clean_returnvalue()` reject every row of the response.

### Changed

- **Code comments are written for a Moodle developer, following Moodle's comment guidance.**
  197 files lost the history, the measurements and the references to the local development
  environment that had accumulated in them, and kept the reasons, contracts and edge cases a
  maintainer needs at each line. 285 comments that no longer matched the code they describe were
  corrected. No code changed: the comment-free token stream of every changed PHP and JS file is
  identical before and after, and the AMD build was regenerated only because its module docblocks
  and source maps carry the comment text.

### Fixed
- **A template's metadata has the same shape whether it comes from the cache or was just built.**
  `template_metadata_cache::get_template_metadata()` and `get_metadata_for_many()` returned a
  freshly built payload without resolving it, so a cold read lacked the `enrollmentfilter` and
  `singlecourseredirect` keys, and ordered its keys differently from a warm read of the same template.
  Both readers now resolve the payload on a miss as they did on a hit. The cache still stores
  the unresolved `inherit` keys, so a change to the site settings applies without purging it.
- **The template metadata cache reads a template's own enrolment filter and single-course redirect.**
  It decoded both select fields by parsing each option as `key|label`, but the fields have been
  provisioned with plain labels since May 2026. So every template read as `inherit`
  and got the site setting, whatever it stored. It now decodes by the option's position, as
  `helper::get_template_enrollmentfilter()` does, which also works for fields that still spell
  `key|label`. No page read these two keys from the cache, so neither defect was visible;
  `block_dimensions` now batches its template reads through `get_metadata_for_many()`.
  `template_metadata_cache_test` compares cold and warm reads of both readers, a mixed batch, and
  the two option spellings; `mutations/template_metadata_cache.conf` breaks each guard.
- **The raised-contrast styles never applied.** All four blocks asked for `prefers-contrast: high`,
  an early draft value no browser shipped; they now ask for `more`, the value Media Queries Level 5
  defines. They reach users for the first time: a stronger progress-ring groove and readout, a
  solid hero title and description, thicker evidence dividers and a ringed Return to plan button.
  The hero rule had kept a fixed white ink over any text colour the admin set, which would have
  put white on a light hero the admin paired with dark text; it now strengthens only the
  translucent default and keeps the admin's colour. `preference_queries_test` pins that every
  preference query uses a defined value, that no raised-contrast override loses on specificity, and
  that none drops an admin colour its base rule reads.
- **Reduced motion now stops the movement it had missed.** With `prefers-reduced-motion: reduce`,
  the Return to plan button no longer lifts on hover, focus or press, rises into view or springs
  onto the screen edge after a drag. The enrol and Learn more buttons no longer lift. Progress
  bars and the progress ring are drawn at their value instead of growing to it. The filter tab
  indicator jumps instead of sliding, and the tab panes and enrolment groups appear without
  sliding in. Chevrons and switch knobs still show their state but no longer animate the change.
  Finding a competency in the hub's structure tree jumps to it instead of scrolling smoothly.
  The hub's loading spinner keeps turning, slowed, since its motion is what says the page is
  working. `preference_queries_test` now fails the build for a lift on interaction, a transition
  of position, size or a transform, a moving keyframe animation or a smooth scroll that the
  preference does not switch off.
- **Names are escaped exactly once, on the hub and on the learner pages.** Course, competency,
  framework, cohort, role and activity names were escaped by the server and again by the page, so an
  ampersand showed as `&amp;`. Web services and template data now carry the plain spelling and each
  sink escapes once; the hub's HTML sinks share the new `local_dimensions/central/escape` module, and
  values core's exporters already escaped are decoded rather than escaped again. The icon picker
  setting had the same defect. `hub_plain_names_test` and `learner_plain_names_test` pin both
  directions with a bare `&` and a `<`.
- **A completed plan's trail now reads the ratings core froze when the plan was completed.**
  `api::complete_plan()` archives every rating into `competency_usercompplan`, keyed by the plan,
  and `api::list_plan_competencies()` reads that archive for a complete plan and the live
  `competency_usercomp` for every other status. `plan_trail_cache` always read the live table, so a
  completed plan's trail would have shown the learner's CURRENT state and disagreed with core's own
  plan page about a plan that closed months ago. Nothing showed it yet, because `block_dimensions`
  renders active plans only; the defect would have arrived with its status filter, which is why it
  is fixed first. `get_trail_data()` takes the plan's completeness as a fourth argument (defaulting
  to the old behaviour, so the current caller is unaffected), the archive join is scoped to the plan
  being read, and the two readings are cached under different keys - a plan completed mid-session
  would otherwise keep serving the live trail it cached minutes earlier. `plan_trail_cache_test`
  covers the manual plan, the template plan (a separate query), the plan scoping and the cache;
  each of the three mutations - always read live, drop the plan scope, share one cache key -
  reddens it.

### Added

- **The learner pages log competency views the way core's pages do.** Neither the plan overview
  nor the competency tracker logged anything, so a learner who only used them left no trace of
  what they looked at. The plan overview now logs core's plan viewed event, as
  `admin/tool/lp/plan.php` does. Opening a competency in the accordion or the grid logs its
  view once per page load, through core's own `core_competency_user_competency_viewed_in_plan`
  web service, or `core_competency_user_competency_plan_viewed` on a completed plan, the same
  pair `tool_lp`'s user competency popup calls. The count stays one whether the learner
  collapses and reopens, switches layout or pages through the grid modal. A failed log call
  never reaches the learner. The competency tracker logs the same view before rendering, but
  not when it redirects straight to a single course, and not for a related competency outside
  the plan. Views of someone else's plan are logged too, with the viewer and the owner, as core
  logs them. `classes/local/view_events.php` holds the server side, and
  `tests/behat/view_plan_logging.feature` is the first Behat coverage of the plan overview.
- **The whole colour contract is a build gate now, not a paragraph.**
  `tests/local/colour_tokens_test.php` grew from four accessibility arms to seventeen, covering
  every rule the design states: no colour literal outside the token block or a named exemption
  (checked in both directions, so an exemption that stops matching anything fails too), the
  `:root` block declaring exactly the 34 contract tokens with their exact chains, the 34 suffixes
  matching the array `block_dimensions` carries byte-identically, the two plugins' blocks being
  the same block under two prefixes, the dark block assigning only the three plugin-owned
  decorative tokens, every colour-mode selector anchored at `:root`, the OS-preference fallback
  written and unreachable, three coloured inks never normal-size text on the inset surface
  (resolved through selector ancestry, with a ratchet on what the scanner cannot resolve), every
  declared pair clearing its WCAG floor in all three resolutions with the arithmetic done in the
  test and the values read out of the stylesheet, the admin's colours never owned by the mode
  layer, the hero transport intact and its `{{^hasbgimage}}` guard present, branded islands free
  of mode tokens, the plugin never writing the host's colour-mode attribute, `ink-faint` only ever
  inactive text, and every `var()` naming a token something declares. Every arm was
  mutation-checked before it landed: the mutation applied, the suite run, the redness confirmed
  and the mutation reverted.
- **`classes/local/colour_mode.php`** - the four attribute names in the family's activation
  contract, and deliberately no `is_dark()`: whether the page is dark is not server-knowable, and
  a wrong guess is the exact defect the design exists to prevent.
- **Behat cover for the colour contract** (`tests/behat/colour_mode.feature`, five new steps).
  Three scenarios assert a relative invariant - the plugin surface equals the page surface - so
  they hold on every supported branch with no skip: one with the host saying nothing, one with the
  host in dark mode, and one inside a dialogue, which is the `local_awareness` defect class where
  an unresolved `var()` leaves an element with no background at all. The fourth asserts the three
  plugin-owned tokens flip, and gates itself by measuring at runtime whether this Moodle ships a
  dark palette rather than guessing from a version number. The feature switches `themedesignermode`
  on, and that is load-bearing rather than incidental: Behat saves the compiled theme CSS when the
  site is initialised and restores it around every run, so with the cache in play all four
  scenarios stayed green with the whole dark activation block deleted. Designer mode compiles the
  same CSS per request, which is what makes the assertions about the stylesheet in the tree.
- **A sixth arm on `bootstrap_compat_test`: zero deprecated Bootstrap 4 class names.** The
  asymmetry runs both ways, and this is the direction that is easy to miss - `sr-only`, `ml-*` and
  their siblings do resolve on 5.x, but only through `bs4-compat.scss`, which marks each one
  deprecated and which Moodle 6.0 deletes.

- **Enforcement for the four accessibility rules no other gate can see.**
  `tests/local/colour_tokens_test.php` fails the build when a focus indicator is drawn with a
  `box-shadow` instead of a real outline (nothing paints a shadow in Windows High Contrast mode),
  when a focus ring is anchored to the brand colour or a literal instead of the focus-ring token,
  when the favourite star stops swapping its glyph, and when a control that renders only an
  aria-hidden icon carries no `aria-label`. Each of the five arms was mutation-checked before it
  landed: the mutation was applied, the suite run, the redness confirmed and the mutation
  reverted. phpcs reads PHP, the mustache lint reads markup structure and stylelint reads CSS
  syntax; none of them can tell any of these four things apart from their correct form.
- **Windows High Contrast support, which the plugin had none of.** `prefers-contrast: high` and
  `forced-colors: active` are unrelated media features, and the four blocks this plugin had were
  addressing only the first. Under forced colours every background collapses to the system canvas,
  so the three selected states carried by a fill alone — the toolbar pill groups, the chip filters
  and the list/grid toggle — now also draw a dashed outline, which is a property the browser does
  paint (and remaps) in that mode.
- **The Competency hub from a course category's "More" menu.** A manager who holds
  `moodle/competency:competencymanage` or `templatemanage` in a course category (and nowhere
  else) now finds the hub beside core's *Competency frameworks* and *Learning plan templates*
  entries, opened with `pagecontextid` the way tool_lp's pages are, following the same page setup
  (category heading, category navigation, no admin tree). The page is locked to that category:
  the context switch and the picker give way to the category's name, the System switch is
  offered only to viewers who may read something at the site, and nothing from a locked visit is
  written into the remembered context — the next visit through Site administration reopens
  where it was. The menu entry is gated on managing, not reading, because reading is an
  authenticated-user default at every category.
- **Deleting a course category now accounts for its frameworks and learning plan templates.**
  Core's deletion moves cohorts and deletes grade categories, content bank items and calendar
  events, then drops the context and leaves competency data pointing at it — invisible,
  unreachable and undeletable; neither core nor tool_lp registers a callback. The deletion
  form now lists the category's frameworks and templates and how many are in use; "delete
  all" is refused while a competency is linked to a course, activity, template or plan or a
  template still has plans (mirroring core's refusal to delete a competency in use) and
  otherwise deletes them through the competency API; "move contents" re-homes them to the
  destination category, and is offered only to someone who may manage them there.
- **A Behat generator for competency objects in a course category.** Core's generator hardcodes
  the system context and its Behat generator cannot name one, so a scenario about category
  scoping could only create site-wide objects. `the following "local_dimensions > frameworks"
  exist` and `"local_dimensions > templates"` take the category's idnumber; the new
  `central_category.feature` walks a category manager from the category page into the locked hub.
- **The category entry lists the category's descendants too.** Frameworks, structure and
  learning plans on the locked entry use core's `children` scope, as tool_lp's category pages
  do, and the bar's headline count covers the same subtree. The site entry keeps listing one
  context at a time, so the System view never shows other contexts' objects.
- **Competency and template pictures check who may see the object.** `local_dimensions_pluginfile`
  served every picture to any logged-in user by id, which was harmless while only site
  administrators created these objects and becomes a cross-category leak once categories are
  delegated. A picture of a visible object is still served to any logged-in user, as course
  images are; a hidden template's picture only to those who may read it in its context or hold
  a plan based on it, and a competency's only to those who may read its framework.

### Changed
- **The plan overview fetches each competency's detail once per page.** The cache that stopped a
  second fetch recorded a rendered pane, not the data. The grid modal therefore dropped the
  competency and called both detail web services again on every card open and every pager step,
  and a switch between list and grid dropped every competency at once. The fetched summary and
  course cards are now kept per page, apart from the record of which pane shows them, so paging
  back, reopening a card or switching layout renders from what is already there. Measured on m502
  over a session that opens three cards, pages back and forth and switches layout twice, the two
  services went from 8 calls each to 3. A review request sent from the detail changes that data,
  so it drops the competency and the next pane fetches it again. A rating made elsewhere shows on
  the next page load, as it always did in an expanded list pane. Logging a competency view stays
  once per page and is not tied to this cache. `tests/behat/view_plan_detail_cache.feature` asserts
  the counts in the browser.
- **The colour token layer moved from `:root` to `body`, and the dark rule grew a second arm - so
  the plugin follows a colour-mode scope wherever the host writes it.**
  Thirty-one of the 34 tokens are DERIVED: each resolves a `var(--bs-*)` chain (the other three,
  `shadow`, `scrim` and `favourite`, are plugin-owned literals), and Bootstrap redefines the
  `--bs-*` set on whatever element carries `data-bs-theme`. Its own `color-mode` mixin emits an
  UNANCHORED `[data-bs-theme="..."]` (`bootstrap/mixins/_color-mode.scss:16`) precisely so the
  attribute can scope any subtree, and Bootstrap's components read `--bs-*` at the component for
  the same reason. A derived layer pinned at `:root` snapshots the root's values and is then blind
  to every scope, including core's own. Counted in the compiled 5.2 sheet: of 32
  `[data-bs-theme="dark"]` rules, 28 are unanchored - Bootstrap's and core's - and the only 4
  anchored at `:root` belonged to this fleet's plugins. The anchoring was ours, not the ecosystem's.

  What it cost, measured on m502 at 1440x900 through `theme_moove`'s own dark switch (moove writes
  the attribute on `document.body`, `amd/src/darkmode.js:35`): the page went to `#1d2125` while this
  plugin's `surface` stayed `#f2f3f7`, and text inheriting the page's dark-mode colour landed at
  **1.17:1** on the plugin's own card, against the 4.5:1 AA floor. After the move the same
  measurement reads **12.44:1**, and `surface`, `line`, `ink`, `shadow` and `favourite` all flip.

  `body` rather than the plugin's own surfaces, deliberately: it is the one ancestor every surface
  has. The Return-to-Plan FAB renders on course pages outside every plugin wrapper, and
  `core/modal` appends its dialogue to `document.body` as a SIBLING of the page container - the
  case that once left a dialogue with no background at all when a token block was scoped to a page
  class.

  The activation rule is now `body[data-bs-theme="dark"], [data-bs-theme="dark"] body`. Naming body
  as the SUBJECT is what still forecloses the leak the `:root` anchor existed to prevent - there is
  exactly one body and its only ancestor is html, so the second arm can only ever mean
  `html[data-bs-theme="dark"] body`, and no deeper scope (the navbar one `theme_boost_union`
  re-pins on five templates) can reach it. Both arms verified in the browser: the first through
  moove's switch, the second with the attribute on `<html>` as core writes it. The note that used
  to prescribe a separate `:root:has(> body[...])` rule is superseded - it would have flipped only
  the three plugin-owned tokens and left the other 31 reading the html element's light values,
  which was the whole of the defect.

  `colour_tokens_test` moved with the contract: `token_block()` reads the `body` rule,
  `activation_block()` and `contract_block_selectors()` share a new `DARK_ACTIVATION_SELECTOR`
  constant, and `test_activation_selectors_are_root_anchored` was re-founded as
  `test_activation_selectors_have_body_as_subject`. Mutation-checked, three ways: a bare attribute
  selector reddens it, a `.theme-dark` rule reddens it, and putting the token block back on `:root`
  reddens five arms. `block_dimensions` moved in the same change - the two blocks are compared
  byte-for-byte under a prefix sentinel, so they cannot travel apart.

- **Eleven Bootstrap 4 class names migrated to their Bootstrap 5 spellings** - seven `sr-only`
  spans in `accordion.js`, `competency_view.js` and `central/competency_links.js` become
  `visually-hidden`, and the four `ml-2`/`mr-2` utilities become `ms-2`/`me-2`. The BS5 spelling
  alone is correct on both branches: 4.5's forward bridge covers the spacers, and the plugin's own
  polyfill covers `visually-hidden`.
- **CI checks out `block_dimensions` beside the plugin on every job.** The two share one
  colour-token contract under two frankenstyle prefixes, and the test that compares the two blocks
  can only run where both are installed; a skip nobody notices is how a cross-repo lock quietly
  stops running, so a further test reads the workflow file and fails the build for any job that
  drops the line. It is not a runtime dependency: neither plugin declares the other.

- **Focus indicators converge on one ring across both dimensions plugins.**
  `outline: 2px solid var(--local-dimensions-focus-ring)` with a 2px offset, everywhere: the two
  3px rings and the stray 1px and 3px offsets are gone, and the two duplicate
  `.local-dimensions-filter-tab` rule blocks now declare a byte-identical focus rule so source
  order no longer decides how a filter pill draws focus. Ten controls that had no ring at all
  gained one — the course-card title link and its `-single-go` twin, the activity row, the hub's
  outline button, the structure related chip, the activity-search rows, the plans resizer, the
  drag grip, the plugin-skinned modal close chip and the referenced-competency modal's close.
  On the two branded islands the ring is `currentcolor`, because their ground is the admin's own
  colour in both modes and the emphasis extreme would be wrong there.
- **`prefers-contrast: high` no longer assumes a light page.** The progress-ring block hardcoded
  `#157347` for the arc and `#000` for the readout, both chosen against white; `#157347` measures
  2.63:1 on a dark page, which is *worse* than the value it was replacing (3.40:1) and below the
  3:1 floor. All three declarations now read tokens that flip with the host. The hero and the FAB
  blocks keep a fixed ink and say why: their ground is a branded island, which does not flip.
- **The competency tracker's course card gains the hover and focus feedback it never had**, and
  its face, border, radius and shadow are the companion block's, so a learner moving between the
  two never meets two card shapes. The locked card takes the same 0.5rem radius.
- **The favourite star swaps its glyph as well as its colour** (`fa-star` / `fa-star-o`), in the
  template and when the star is toggled without a reload, matching what `block_dimensions` has
  always done. State carried by colour alone is a WCAG 1.4.1 failure.
- **Two screen-reader-only spans move from `sr-only` to `visually-hidden`**, the spelling the
  other 25 sites in this plugin already use and the one its Bootstrap 4 polyfill defines. `sr-only`
  resolves on 5.x only through the compatibility layer Moodle 6.0 deletes.
- **The hub's course category picker searches on demand instead of listing every category.**
  The context bar used to enumerate every category the viewer could see on every render —
  a context instantiation and up to four capability checks per category, a nested name built
  per category, and thousands of `<option>` elements for the autocomplete to chew through —
  which does not survive a site with thousands of categories. The picker is now a search
  (`local_dimensions_search_categories`, 25 hits per query, name match accent-insensitive)
  that returns the plain nested name and both counts per hit; the server renders only the
  selected category. A viewer who may read competencies at the site skips the per-category
  capability checks altogether (a category can only narrow what the site grants, and the
  page re-checks the chosen category anyway); a category-scoped viewer is checked per hit.
- **The Competency hub decides tab availability in the context the pane names, not at the site.**
  All three tabs asked `can_read_context()` about the system context whatever category the page
  was showing, so a manager holding the competency capabilities in one course category only was
  refused the Learning plans pane (measured on 5.2: `nopermissiontoaccesspage` over AJAX) and, for
  the other two, admitted only through the authenticated-user default for `competencyview`. The
  tab strip now honours that answer the way core's own dynamic-tabs export does — an unavailable
  tab renders disabled — and the active tab falls back to the first available one instead of a
  saved preference throwing on the whole page.
- **The hub's Site administration entry no longer requires `moodle/site:config`.** The plugin
  wrapped its whole admin subtree in `$hassiteconfig`, which core does not impose on local plugins
  and tool_lp does not apply to its own pages, so a system-level competency manager was locked out
  of the hub while core's pages admitted them. The hub is gated by
  `moodle/competency:competencymanage` alone; the settings page and the two custom-field
  definition pages keep the site-configuration guard, because field definitions are site-wide.

### Fixed

- **The Rules tab of a competency shown a second time no longer spins forever.** The tab fetched
  its data once per page and marked the whole page as loaded, but every detail render builds a new
  Rules pane. After a card was reopened, the modal stepped back to a competency, or the list pane
  was rebuilt by a layout switch, the new pane found the page already marked and never filled in.
  The mark now lives on the pane and the fetched data is kept per page, so a rebuilt pane renders
  at once without fetching again.
- **A plan the viewer may not read is no longer reported as an invalid plan.** `view-plan.php` and
  `view-competency.php` turned every exception from `api::read_plan()` into "Invalid learning
  plan". Two refusals were misreported that way:
  - Core's permission error. No default archetype holds `moodle/competency:planviewowndraft`, so
    every learner on an unmodified site is refused their own draft, waiting-for-review or in-review
    plan, and was told that plan did not exist.
  - The "competencies are not enabled" error.

  Both pages now read the plan through `classes/local/plan_access.php`. It reports only a missing
  plan as invalid (a `dml_missing_record_exception`, for an unknown id or one below 1) and lets
  every other error surface as core's own, as `admin/tool/lp/plan.php` does. The learner-facing
  `block_dimensions` was checked and needs no change: its plan and competency cards come only from
  active plans, so it never links a learner to a draft or in-review plan.
- **A viewer allowed to read draft plans no longer gets an error from every competency.** Core
  lets a role holding only `moodle/competency:planviewdraft` read a draft, waiting-for-review or
  in-review plan. But each competency's detail goes through `api::get_plan_competency()`, which
  checks `user_competency::can_read_user()`, and that check never consults the draft
  capabilities. Such a reviewer could open the plan overview, while every competency they expanded
  failed with an error in the row and an exception dialog. The page now works out server-side
  whether the viewer can read the plan owner's user competencies. When they cannot, it renders the
  list with plain headers, no detail region and no chevron, and says once, in a localised notice,
  that the details are not available. Default roles are unaffected: a manager holds every
  capability involved, and the owner reads an active plan normally.
- **The Return to plan button never appeared on a MooTube (`format_mtube`) course page.** Every
  gate passed and `init()` set `display: flex`, but core places the footer hook's HTML inside
  `#region-main`, and the format hides every child of `#page` except its own app with
  `display: none !important` - so the button rendered 0x0, with nothing in any log. Measured on
  m502 as a student with a stored return context. `init()` now steps the button out one ancestor
  at a time while it has no client rects and stops at the first spot where it renders (right after
  `#page` there); on a page that hides nothing it does not move, so it keeps its place in the main
  landmark and in tab order everywhere else. On that page, outside editing (where the format hides
  nothing and core's footer button is back in the corner), it also takes the format's corner: aligned
  with the format's tools button (bottom 2rem, right 2rem), one gap to its left when a teacher has
  it (its menu opens upward in its own column), 5rem up below 768px to clear the format's bottom
  bar, and hidden with the format's own chrome in its HTML frame fullscreen.
- **Three more places a theme's own decisions reached into the plugin, all measured on m502 at
  1440x900 and fixed in the sheet that owns the cause.**

  - *The sticky filter toolbar anchored 10px too high on moove and 4px on trema.* It already
    read `var(--navbar-height, 60px)`, which is the right channel - theme_almondb declares
    `--navbar-height: 70px` for its 70px navbar and the toolbar lands exactly right there with
    no help. moove (70px navbar) and trema (64px) leave core's 60px in place, so rows slid
    under the navbar while scrolling. `styles_moove.css` and `styles_trema.css` now state the
    measured height in the one place the plugin already reads, rather than overriding the
    toolbar's `top` somewhere else and leaving two numbers to drift.

  - *The Return-to-Plan FAB covered the theme's own footer button.* core Boost parks
    `.btn-footer-popover` at `bottom: 2rem` with a 2rem box, i.e. the band 32-64px up from the
    bottom edge; the FAB claimed 24-75px at `z-index: 9999` against the button's 1000, so the
    theme's control was present, focusable and invisible. `styles_boost.css` lifts the FAB to
    `calc(2rem + 2rem + 1rem)` - core's own arithmetic, so it follows if core moves the button -
    which also clears moove's lower placement of the same button (16-48px) and trema's
    `#goto-top-link` (0-38px). A second arm mirrors core's `.hasstickyfooter` offset. Verified
    with the FAB actually rendered: measuring it while it was absent reported "no collision"
    for the wrong reason, twice.

  - *Competency cards lost their border on moove and trema.* `styles.css` styles them with
    `border-color` only, riding on Bootstrap's `.card` carrying `border: 1px solid`; both themes
    remove it, and the cascade resolves longhands independently, so the colour survived and
    painted nothing. The two themes need different repairs and the difference is specificity,
    not taste: trema writes `.card:not(.fp-navbar) { border: none }` at (0,2,0), which ties with
    the plugin's own selector and wins on document order - taking `border-color` with it, which
    is why its border measured `#373a3c` (`--bs-body-color`) rather than the token - so trema
    restates the colour as a token read; moove writes `.card { border: none }` at (0,1,0), loses
    `border-color` to the plugin, and needs only the two structural longhands.

- **The hero sits against the navbar on every theme, and stops guessing at how far to move.**
  `styles.css` pulled the hero up by a flat `margin-top: -1rem`, commented "pull the hero up to
  overlap any remaining spacing" - one constant standing in for a number that is different in
  every theme, so it was right in none of them. Measured on m502 at 1280px, logged in, on
  `view-plan.php`, the white band between the navbar and the hero was 32px on boost and boost_union,
  33px on almondb, 25px on trema and 9px on moove; on Moodle 4.5 it was
  32px on boost and 121px on theme_academi, whose own navigation bar is static and 71px tall. The spacing does not belong to the hero at all - it is spent by the
  containers above it, and the fix is to zero those, which lands the hero at `#page`'s own top
  edge, a value every Boost child already sets to its navbar's height. The plugin therefore needs
  to know nothing about any theme's navbar. New `styles_boost.css` carries it, because core loads
  `styles_<candidate>.css` for every candidate in [base parent ... theme] and so reaches every
  Boost child from one file; `styles_academi.css` carries the two deltas that are academi's own.
  All eight theme/branch combinations now measure 0 or -1px, the -1 being the hero tucked a pixel
  under an opaque fixed navbar.

  Three things this cost, worth keeping:

  - **Specificity, not `!important`.** The first attempt used `body.<class> #topofscroll` and lost
    to Boost Union's `#page.drawers .main-inner`, which has one id and two classes: the class count
    is compared before the element count. Anchoring on two ids settles it on the id count, which is
    compared first, and needs no `!important` - still banned.
  - **trema spends part of its spacing on `#page-content`**, the only theme measured that does, so
    a fix aimed only at `#topofscroll` looked right on five themes and left 16px on the sixth. The
    rule names the whole chain rather than the container that happened to be guilty first.
  - **The last pixel is core's skip-link anchor.** Boost gives the empty `<span id="maincontent">`
    a 1px height, and it sits immediately before the hero; on boost and boost_union it
    lands in the 1px their `#page` margin is already short of the navbar and vanishes, while on
    moove and trema it became a visible hairline. It is collapsed with `height: 0` - the same
    thing core does for embedded media previews - and never with `display: none`, which would take
    "Skip to main content" out of the accessibility tree with it.

- **The admin's chosen hero text colour was inert, and had been for as long as the rule existed.**
  `styles.css` reads `--dimension-customtextcolor` five times - the hero title, description,
  due-date label and value, and the collapse button - and nothing had ever written it: the colour
  reached the DOM as a bare inline `color:` on the ancestor, which all five descendants then
  overrode with their own white fallback. The hero now emits both
  `--dimension-custombgcolor` and `--dimension-customtextcolor` beside the plain properties, which
  is the transport `block_dimensions` has always used, so the two plugins now carry an admin colour
  the same way. The inline background keeps its `{{^hasbgimage}}` guard, which is load-bearing: a
  class selector never outranks an inline style and `!important` is banned fleet-wide, so without
  the guard an opaque colour paints over the hero photograph and no stylesheet can undo it.

- **The icon picker's two icon-only buttons reach a screen reader with a name.** The chevron and
  the clear button had a `title` and nothing else; `title` alone is exposed inconsistently across
  browser and screen-reader pairings and never appears on touch. Both now carry an `aria-label`
  built from the string they already had, so no new lang key was needed.
- **The custom-SCSS editor's focus indicator survives forced colours.** Its only ring was a
  `box-shadow` glow, which that mode does not paint at all; it is a real outline now.
- **The enrolment methods pane explains itself when the plan has no linked courses.** The
  methods act on the courses linked to the plan's competencies; a plan with no competencies,
  or none linked to a course the viewer may configure, showed an empty grid that read as a
  permission problem — a category manager holding every required capability hit exactly that.
  The pane now says so and points at the two steps that fill it.
- **A manager scoped to one course category saw no custom fields on a competency and saved
  none.** `competency_handler::can_edit()` resolved `competencymanage` at the system context
  whatever the competency, and core filters both the rendered and the saved fields through it,
  so the modal opened (the form checks the framework's context) with every plugin field missing
  and each save wrote nothing, without an error. The handler now resolves the competency's own
  framework context, latches the instance while a new one is saved, and takes a context hint
  from the form for the create path — the same shape `lp_handler` already had, which also gains
  the hint so a category manager sees the fields when creating a template.
- **The competency usage popover failed for a category manager.** Its templates section went
  through `api::list_templates_using_competency()`, which requires template read access at the
  system context and throws otherwise. Templates are now read through the persistent and
  filtered on their own context.
- **The participants picker offered the whole site directory.** `search_assignable_users`
  required only `templatemanage` on the template and then listed every active user, with email
  and ID number, while the follow-up `add_template_user_plan` then failed on `planmanage`. The
  search is now filtered to users the caller may create a plan for, the way core's tool_lp user
  search is; the grid also learns per row whether unlink and delete are allowed and renders
  the two actions only there.
- **Eight read services no longer depend on the authenticated-user default for `competencyview`
  at the site.** Structure browsing and search, competency search, course links, linkable
  courses and usage validated the system context and required `competencyview` there before
  their real per-framework gate, so a hardened site (or a category manager without that
  default) lost the Structure tab. Each now validates the framework's own context; the
  cross-framework search keeps only the login gate and its per-framework filter.
- **Course category names with an ampersand rendered as `&amp;` in the hub's context bar.**
  `make_categories_list()` hands back names already run through `format_string()`, and the
  picker's double stashes escape once more. The bar now rebuilds the nested name unescaped.
- **The "open cohorts page" shortcut is judged in the hub's context and opens the category's
  cohort page.** It was evaluated at the system context and hardcoded the site cohort list,
  although `cohort:manage` is a course-category capability and core's page is context-aware.
- **A course whose enrolment places had been freed by expiry still showed as closed.** The check
  for "this course is full" was re-implemented here rather than asked of `enrol_apply`, and that
  copy counted enrolments whose period had already run out. Since the plugin changed its own
  answer, the two disagreed: `enrol_apply` offered the button and accepted the application while
  this surface went on treating the course as full. The question is now put to the plugin, so the
  two cannot drift again.

Macro view of everything since v1.0 — per-change detail lives in the commit history.

### Added
- **Competency hub** (`central.php`): a single admin surface for the whole competency domain —
  three dynamic tabs with modal-based CRUD, a system/category context switch and no full-page
  reloads.
  - *Structures*: lazy competency tree with search-and-reveal, drag-and-drop reorder/reparent,
    move-to-position modal, native rule editor, display toggles and per-competency usage
    counters (courses / activities / plans).
  - *Learning plans*: master-detail template management — search and multi-competency filter,
    create/edit/delete in modals, **full duplication** (custom fields, embedded files and card
    images included), competency picker + framework browser, drag-and-drop ordering, resizable
    panes.
  - *Frameworks*: native create/edit with scale configuration, duplicate, visibility toggle,
    reason-gated delete and **CSV import/export**.
  - *Learning plan CSV transfer*: export the templates the Plans tab lists — competency links in
    order, the plugin's fourteen template custom fields, and a companion download for each
    referenced structure — and import them back with a **dry-run preview**. The preview projects
    every row against the target site (create / update / in sync / skip / conflict / blocked /
    orphan link) with its field diff, its resolved competency links, its effect on existing
    learner plans, and per-row ways out of a conflict; **nothing is written until you apply**, and
    then only the rows you ticked, each in its own transaction and re-validated at write time so a
    site that moved under you is refused rather than half-written. The file has two row types
    (`template` and `link`, every column read by its header), and a file exported by
    `admin/tool/lptmanager` imports too (one direction only).
  - Modals: *Participants* (cohorts with background plan sync, individual users, cohort-role
    assignment), *Courses & activities* (linking with rule outcomes, activity search,
    completion-rule badges), *Related competencies* (shared tree browser).
  - ~30 AJAX web services back the hub; the front-end is ESM, zero-YUI, Bootstrap 4+5
    compatible.
- **Per-user persistent hub state**: last tab/context/framework/template, display toggles and
  gear panels survive sessions and devices (two JSON user preferences + privacy provider).
- **Bulk enrolment methods** (participants modal, 4th tab): apply/remove cohort sync
  (`enrol_cohort`) or cohort-restricted self enrolment (`enrol_self`, `customint5`) on the
  courses linked to a template's competencies — single role per operation (gradebook roles),
  category/hidden filters, lazy per-competency accordion, per-course status against the
  selected cohort, and one background adhoc task per (course, method, cohort) combination
  with queue dedup, Lock API serialisation, idempotent execution, audit events and a queue
  poll driving per-row Processing states.
- **15 administrative audit events** for decisions core never logs: cohort attach/detach,
  cohort-role rules, custom-field value changes (effective-value diff, SCSS redacted),
  course/activity links with rule outcomes, template duplication, and enrolment methods
  applied/removed in bulk.
- **Concurrency safeguards**: custom-field provisioning under a core Lock API lock,
  deduplicated cohort-sync task queueing, and a retry on concurrent first-saves of custom
  fields.
- **Learner views**: taxonomy card, Rules-tab filters and warnings with backend-provided
  texts, status/taxonomy icon assets, plan-trail session cache, and a draggable Return-to-Plan
  button. Both views were then rebuilt end to end (learner kit, Phases 0–6):
  - *Related content*: each competency panel carries its completion-rule **outcome badge** and
    the activities linked to it, grouped by course, drawn with core's own activity icons and
    purpose colours. A restricted activity is shown locked and leads to the course page; a
    hidden one is not shown at all.
  - *Locked course card*: **self-enrolment** where the course offers it, and an anticipatory
    "Opens" / "Enrolment opens" date instead of a bare lock.
  - *Single-activity and single-section courses* resolve to the activity or the section itself,
    rather than to a card leading to a course page with one link on it.
  - **Sort and completion filter**, persisted per user and resolved server-side so the first
    paint is already ordered: plan order, name, completed first or favourites first, over
    "not completed" or "all".
  - **Per-plan favourites**: a star on each competency, a "My favourites" / "Show all" pill
    pair, and a ghost card counting what the filter hides so it cannot be left on unnoticed.
    Own-plan only — staff reviewing someone else's plan get no star — and gated by the new
    `enablefavourites` admin setting (default on, mirroring `block_dimensions`).
  - **Grid layout** beside the list, with a competency **detail modal** carrying a pager across
    competencies and a full-screen expand; both choices persist.
  - *Competency tracker*: completion tabs (Not completed / All), a **"Continue"** shortcut to
    the first started-but-unfinished section, and a seal on a completed course's card.
  - **Collapsible hero**: a handle on the header's bottom edge folds it to a slim row — the
    title with the plan's due date beside it, no description — and back. The choice is stored
    per plan and per competency and applied server-side, so a learner who returns lands on the
    header they left.
  - The toolbar is realigned with `block_dimensions`: sticky at every width, filters folding
    behind an adjustments button on narrow screens, and a "Clear filters" button with an icon.
- **CI**: moodle-an-hochschulen reusable workflow — static checks plus PHPUnit and Behat
  across the supported PHP × DB matrix (Moodle 4.05–5.02).

### Changed
- The **Return-to-Plan button** was hardened end to end: redirect loops are structurally
  impossible, it renders only on course-content layouts (never in secure quiz windows or on
  administrative pages), only for the plan's own user, and stale contexts expire (4h TTL).
- Editing UX unified around core `dynamic_form` modals with in-modal toasts and row flashes;
  pagination standardised at 25 across grids and pickers.

### Security
- **Per-course authorisation on the two tracker card services.** `get_course_progress` and
  `get_courses_completion_status` took a raw course-id list and were gated only by the
  site-wide `local/dimensions:view`, which every authenticated user holds — so a direct AJAX
  call could read the visible section names and start date of any course, including deliberately
  hidden ones. Both now resolve their ids through `helper::readable_competency_courses()`, which
  keeps only courses that exist, that core would let the viewer see listed
  (`core_course_category::can_view_course_info()`) and that carry a competency link — the only
  courses either view ever lists. Everything else gets the same locked, empty row, so a probing
  caller cannot tell a hidden course from a missing one.
- **CSV exports no longer carry live spreadsheet formulas.** A competency or template whose name
  began with `=`, `+`, `-` or `@` was written verbatim into the framework and template exports
  and evaluated when the file was opened. Both serializers now neutralise those cells
  (`local\csv_formula`, matching core's `\core\dataformat::escape_spreadsheet_formula()`), and
  both importers strip the guard again — including from files written by core's own
  `csv_export_writer`, which has no counterpart on the way in.
- **No more DDL from a request path.** `helper::sql_like_ai()` attempted
  `CREATE EXTENSION unaccent` on every search that reached it when the extension was missing,
  which a least-privilege database account can only ever fail. The catalogue check is now
  `helper::has_unaccent()`; provisioning stays in `helper::ensure_unaccent()` and runs from
  `db/install.php` and `db/upgrade.php` only.

### Removed
- The entire **legacy admin surface**: `manage_competencies.php`, `manage_templates.php`, the
  `edit_*` pages, their forms, templates and AMD modules, ~2.3k lines of CSS and 125 orphaned
  language strings — the hub covers every action they offered.
- The **comments** feature (accordion reply threads, its services, JS, CSS and strings).
- Client-side SCSS validation (the server-side validator is the single gate) and the unused
  customfield-aware CRUD web services.
- **The Moodle 4.5 compatibility code.** This branch serves Moodle 5.3 only, and 4.5 keeps that
  code on `MOODLE_405_STABLE`. Gone: `local\bootstrap` and the `local-dimensions-bs4` body class
  it put on the hub, plan and competency pages; the Bootstrap 4 utility polyfill at the tail of
  `styles.css`; the participants filter panel's 4.5-only click guard; the `data-toggle` attribute
  beside `data-bs-toggle` on the hub's dropdowns, and the hub's match on core's 4.5 tab toggle; the
  Moodle 5.2 branch check in `enrolment_provider::unlisted_available()` (`UNLISTED_MIN_BRANCH`),
  which only the plugin-presence check replaces. `tests/local/bootstrap_compat_test.php` becomes
  `tests/local/bootstrap_markup_test.php` without its polyfill and marker checks; the data-API
  check now refuses the Bootstrap 4 attribute instead of requiring it.

### Fixed
- **A course you can only get into by applying is no longer drawn as a padlock.** The predicate
  behind every locked card walked the course's enrolment instances looking for `enrol_self` and
  nothing else, so a course whose only way in is `enrol_apply` was classified as locked — cover
  image dimmed, lock overlay on top, no path forward — for learners who were perfectly eligible
  to apply. Widening it is not a matter of asking a more general question, because there is no
  general question to ask: `enrol_plugin::can_self_enrol()` is an unconditional `return false;`
  in the base class and `enrol_self` is the only plugin in the whole of 5.2 that overrides it,
  so a loop written against it reports every other enrolment method as "cannot". The predicate
  now dispatches per plugin — `can_self_enrol()` for self, `allow_apply()` for apply, with the
  already-applied check and the `customint3` places cap mirrored beside it because they live
  outside `allow_apply()` where self keeps its equivalents inside. `enrol_apply` stays optional:
  an `is_callable()` guard means a site without it, or with a different build of it, simply
  never matches. The method is now `calculator::current_user_can_enrol()`;
  `current_user_can_self_enrol()` remains as a deprecated alias.
- **An application awaiting a decision is now its own card state.** A pending `enrol_apply`
  application writes a *suspended* enrolment row, which answers no to both questions a card
  asks: the learner is not actively enrolled, and the plugin will not accept a second
  application. They were therefore handed the padlock — the same card as somebody who was never
  eligible — and the one thing it could not say was the one thing they needed to know. Both
  learner views now report a third state between open and locked: an hourglass with "Application
  pending" instead of a lock with a date, and no button, because there is nothing left to do.
  The state is scoped to `apply` instances on purpose — a suspended row on a manual or self
  instance is an administrative suspension, not an application — and it excludes a row whose
  enrolment period has run out, which is the clause that separates a real application from an
  approval that `process_expirations()` re-suspended under a "suspend" expiry action. Without
  it a learner whose enrolment merely lapsed would be told, permanently, to wait for a decision
  nobody was going to take. The `enrolledorself` display filter needs no new branch: an
  application is a real enrolment row, so its existing `onlyactive=false` test already counts
  it.
- The **card's shape** is now decided over the same set of activities as its percentages. It was
  not: the shape resolver asked "is there exactly one activity here" using *openable now*, so a
  course holding one open activity beside one released-later activity looked like a one-activity
  course, took the single-activity card and drew a completed tick over a course that was half
  undone — beside a bar reading 50%. Whether an activity may be **offered as a link** stays a
  separate question, and the single-activity card still asks it: a course whose only work has not
  opened yet is real work, and is counted, but it falls through to the section or timeline card
  rather than rendering a button that goes nowhere.
- **One rule now decides what a learner's progress is measured against**, for the course bar and
  the section rings alike: an activity counts when completion is tracked on it, when the learner
  can see it, and when it is theirs to do — now or later. The third condition is the one that had
  never been stated. Core already draws that line for us: `is_applied_to_user_lists()` marks the
  restrictions that are **permanent** for a person (group, grouping, profile) and leaves the ones
  that have merely not come round yet (date, grade, completion of something else). So an activity
  released next week stays in the denominator, because the learner will have to do it; an activity
  restricted to a group they are not in leaves it, because no amount of studying will ever unlock
  it — and counting it would put 100% permanently out of their reach. Previously the rings counted
  only what was **openable right now**, so a course whose remaining work was date-released read a
  finished-looking 100% and then walked backwards on the release date.
- The course card's **progress bar** reading wrong on Moodle 4.5. It no longer calls
  `core_completion\progress::get_course_progress_percentage()`, whose numerator is not a subset
  of its denominator on that branch (MDL-60912, fixed in 5.0.7 / 5.1.4, never backported): the
  denominator drops a module flagged for deletion while the numerator keeps its completion row,
  so deleting an activity the learner had already completed made the bar jump — measured 67%
  where 33% was the truth, and `clamp_percentage()` cannot catch it because the value never
  passes 100. 4.5's denominator also applied no visibility filter at all, so a hidden activity
  still counted and a learner could never reach 100% in a course holding one, which showed as a
  50% bar above a 100% section ring on the same card. Neither 5.1+ helper that fixes these
  upstream exists on 4.5 to call, so `calculator::course_completion_percentage()` reproduces
  what 5.1 and 5.2 core compute — activities visible **on the course page**, minus those a group
  or grouping restriction excludes the learner from. All three branches now answer alike, and
  the bar keeps counting work that is merely date-released rather than reporting a finished
  100% and then walking backwards. One further change worth knowing: the bar is now rounded by
  the same rule as the section rings, so 199 of 200 activities reads 99 rather than rounding up
  to a 100 that claimed the course was finished.
- Course cards counting activities the learner could no longer reach, after a **subsection** was
  deleted. Deleting a subsection flags only the subsection module itself — every activity inside
  its delegated section keeps `deletioninprogress = 0` and stays user-visible until the adhoc
  task runs — so `calculator::get_course_section_progress()` went on cascading those activities
  into the parent section's ring while the course page had already withdrawn the whole
  subsection. Reproduced on 5.1 and 5.2 as 25% where 50% was the honest answer; the window
  closed at the next cron run, or never, on a site whose delete task keeps failing. Plain
  activity deletion was never affected (core forces `uservisible` to false for a flagged
  module, which the counter already tested).
- Custom-field data leaked on competency/template deletion (Moodle 5.1+ context teardown).
- Bootstrap 4 dropdowns dead on Moodle 4.5 (missing `data-toggle` bridges).
- Web-service return structures silently stripping undeclared fields from lazily-fetched rows.
- TinyMCE not initialising on template edit; assorted modal heading/labelling issues.
- Four learner-view defects that predated the rebuild: the filter-tab click handler reaching
  outside its own toolbar, the course-progress payload tripping on a completion-disabled
  course, the last hard-coded colours left by the palette migration, and `isGradeProficient`
  misreading core's scale configuration.
- A partial seed silently erasing a whole-value preference: `local_dimensions_learner_view`
  holds five keys in one JSON value, so any control that saved reset every key the page had
  not seeded — choosing a sort discarded the grid layout, and the favourites filter and the
  modal size were lost the same way, unseen. The whole resolved state is now handed to the
  client as a single object.
- **Bootstrap 5 class names that resolve to nothing on Moodle 4.5.** The bridging between the
  branches is asymmetric — 4.5's forward bridge is 116 lines (`g-0`, `btn-close`, the
  `ms/me/ps/pe` spacers, `float/text/border/rounded-start/end`) while 5.x's backward bridge runs
  past a thousand — so BS4 names resolve on both branches and BS5 names do not. Measured on a
  running 4.5 site: `visually-hidden` did not hide (24 sites, so every table caption printed as a
  heading and "opens in new window" showed after each external link), `form-select` left 21
  selects with no border, radius, chevron or padding, `gap-*` collapsed 15 toolbars into
  run-together controls, and `fw-*`, `font-monospace`, `form-switch` and `form-label` were inert.
  Fixed with a gated Bootstrap 4 utility polyfill at the tail of `styles.css` rather than by
  writing BS4 names, which Moodle 6.0 removes (MDL-84465). The gate is a body class added only
  when `$CFG->branch < 500`, so the block cannot reach 5.x.
- **Badge contrast on both branches.** Bootstrap 4's `.badge` sets no text colour and Bootstrap
  5's defaults it to white, so a badge that did not state its own colour failed AA on one branch
  or the other: measured 3.07:1 for `bg-success` on 4.5, and 1.49:1 for `bg-secondary` on 5.2 —
  a live defect on the current stable target, not only on the old one. Every badge now declares
  its text colour.
- Unwrapped `.form-check-input` controls escaping their row on 4.5, where Bootstrap 4 makes them
  `position: absolute; margin-left: -20px` and expects a `.form-check` parent to compensate.
- The participants filter panel closing mid-interaction on 4.5: `data-bs-auto-close` has no
  Bootstrap 4 equivalent, and BS4 exempts only `input` and `textarea` targets, so the cohort
  select and the switch label both dismissed the panel.
- Core 4.5's modal close button showing two glyphs, its own `&times;` span plus the plugin's
  Font Awesome `::before`.
- **The hub loading two tabs at once on every visit.** `core/dynamic_tabs` opens the first tab in
  the DOM and ignores the server's active flag, and its `loadTab` re-fetches over the web service
  unconditionally — even the tab the server had just rendered. Since `context.js` then clicked the
  saved tab to restore it, any visit whose saved tab was not Structures produced three renders and
  discarded two: a PHP render core replaced, and a full `getContent` for a pane left invisible.
  Measured on 4.5: two calls of 845 ms and 1023 ms starting in the same millisecond, leaving 3250
  bytes of content in a hidden pane. The saved tab now reaches core through the URL fragment, which
  the new `central/tab_hash` template writes synchronously before core initialises — the same
  technique, and the same reason, as core's own template ("We must not use the JS helper otherwise
  this gets executed too late"). The server pre-renders that tab instead of Structures, so it
  paints immediately. Now one `getContent`, one populated pane, whichever tab was saved.

### Changed
- The plugin's motion and loading custom properties moved from `--mds-*` to
  `--local-dimensions-*`. `--mds-` is core's namespace: Moodle 5.2 ships
  `theme/boost/scss/design-system/` with `$mds-*` tokens and 5.3 LTS brings MDS React, so
  declaring those names in `:root` was squatting a namespace core is actively expanding.
- CI no longer runs a Moodle 5.0 job. 5.0 leaves security support on 2026-10-05; the remaining
  jobs are 5.02 (full PHP × DB matrix), 5.01 and 4.05.
- `tests/local/bootstrap_compat_test.php` now enforces the Bootstrap contract that prose had
  failed to hold three times: every BS5 utility used must be polyfilled, the polyfill must carry
  nothing unused, every badge must state its text colour, data-API attributes must be paired,
  the stylesheet must not declare `--mds-*`, and every entry point setting a plugin body class
  must mark the Bootstrap version.

## [1.0] - 2026-03-16

### Added
- Two display modes for learning plans: **Competency tracker** (course card grid) and **Full plan overview** (expandable accordion).
- Custom fields for competencies and learning plan templates: card image, background image, background colour, text colour, tags, display mode, and custom SCSS.
- Auto-provisioning of custom fields on first admin access when core competencies are enabled.
- Real-time course section progress calculation with recursive subsection support (Flexsections, `mod_subsection`).
- Competency completion rules display (Rules tab) in Full plan overview, showing rule type, rule outcome, sub-competency progress, proficiency status, and required flags.
- Evidence cards with detail modals in accordion panels.
- Related competencies display (optionally clickable) in accordion panels.
- Competency hierarchy path display (framework → parent → competency).
- Floating "Return to plan" button with configurable colour.
- FontAwesome icon picker with AJAX search for locked card icons (supports Boost Union extended icon map).
- Custom SCSS injection per template and per competency with client-side validation and server-side compilation.
- Enrolment-aware filtering (all, enrolled, active) for both display modes.
- Single course redirect option when user has only one active enrolment.
- Lock status detection with configurable icons and "Learn More" buttons.
- Availability date display on locked cards.
- Optional "Submit prior learning evidence" button in the Rules tab.
- Moodle Privacy API implementation (`null_provider`).
- Application-level MUC caches for template courses, template SCSS, and competency SCSS.
- Five AJAX web services: course progress, competency courses, user competency summary, FontAwesome icons, and competency rule data.
- Custom capability `local/dimensions:view` for controlling access.
- Event observers for `competency_created` and `competency_updated`.
- Hook callbacks for injecting custom fields into core competency forms and rendering the return button.
- Clean uninstall routine removing all custom fields, file areas, and caches.
- 13 Mustache templates for responsive layouts.
- 4 AMD JavaScript modules: accordion, UI, FontAwesome icon selector, SCSS validation.
- WCAG-compliant accessibility: ARIA labels, keyboard navigation, semantic HTML, screen reader support.
- English and Brazilian Portuguese language packs.

[Unreleased]: https://github.com/uaiblaine/moodle-local_dimensions/compare/v1.0...HEAD
[1.0]: https://github.com/uaiblaine/moodle-local_dimensions/releases/tag/v1.0
