# Competency hub v2 for framework administration: survey and second brainstorm (2026-11-23)

Status: the brainstorm of 2026-11-23, with its decisions taken the same day (section 9). The work
itself is tracked in `PROGRESS.md` beside this file. Scope, by the
owner's instruction: the administrative side of `local_dimensions` only (viewing and administering
competency frameworks), Moodle 5.3. Learner pages, the theme's competency pages and learning plan
templates are out of this round, except where they explain a choice.

It gathers what the earlier rounds recorded, measures the Competency hub v2 as it stands, reads
what changed around it since (the fleet Design System, the evidence hub, the React data report of
`enrol_apply`, React Flow 13), researches canvas accessibility with Miro as the main case, and
proposes directions. The decisions it asks of the owner are in section 9.

## 1. The record so far

| Date | What | Where |
|---|---|---|
| 2026-09-02 | The hub (v1) opened to course category managers: locked category entry, 18 work items | merged; README, "Competency hub" |
| 2026-10-18/19 | Competency pages design, rounds 1 and 2. Board `HubNodes`: the hub tree as nodes with React Flow. Feasibility study with three open risks (packaging, CSS, accessibility). Actions inventory by persona. Decisions R1 to R6 left open; the round was paused | `moodle-theme_dimensions/docs/competency/` (untracked): `README.md`, `react-flow.md`, `actions-inventory.md` |
| 2026-10-29/31 | Evidence hub approved and built as the companion plugin `local_evidencehub` (stages S0 to S9), with the `competency_status_badges` hook in this plugin | `docs/proposals/2026-10-29-evidence-hub/`; `classes/hook/competency_status_badges.php` |
| 2026-11-12/13 | Design canvas of 76 boards (core today, the October study, three candidate lines and "Fluxo"). The Competency hub v2 built from `OutHubNodes` and merged as a preview (#137, #138), the canvas copied into the repository (#139) | `docs/proposals/2026-11-12-competency-canvas/`; `hubv2.php`, `js/esm/src/hubv2/` |
| 2026-11-14 on | The fleet Design System (decisions D1 to D135) | `moodle-dev/docs/design-system/` |

Open from those rounds and still open: whether the v2 panel lists every related competency or
only those off the canvas; the MDL Shield review of #137; the choice between the canvas lines
("Fluxo over A + C" or "keep A + C"); R2 (where staff screens live). R1 (React Flow in
`local_dimensions` main) was answered in practice by #137.

## 2. The hub v2 as built (measured 2026-11-23, version 2026100532)

### What it is

One page (`hubv2.php`, site administration only) that draws one framework's competency tree left
to right with React Flow 12.12.1 (vendored, 186 KB; the page's bundle is 226 KB). The tree loads
lazily, 25 children at a time. A panel shows the selected competency and its actions, most of them
the hub's own AMD dialogues. It keeps nothing: no table, no preference, no URL state for the
selection, the layout recomputed on every visit.

### Parity with the hub (v1)

The hub offers 60 administrative actions (5 global, 10 on frameworks, 17 on the competency tree,
28 on learning plan templates). Of those, v2 offers 11, partly offers 6 and lacks 43.

| Group | v1 actions | In v2 |
|---|---|---|
| Competency tree (S1 to S17) | 17 | 11 yes (add, add child, edit, delete with the check, move, rule, courses and activities, relate, usage, search, load more); 4 partial (framework select by page reload, reorder without drag, related list without the detail dialogue, expand one node at a time); 2 no (display options, pane sizes) |
| Global (G1 to G5) | 5 | 2 partial (context and category through a GET form); no hidden-categories switch, no remembered place, **no category-locked entry** |
| Frameworks (F1 to F10) | 10 | none: create, edit, taxonomies, scale configuration, duplicate, delete, visibility, CSV import and export |
| Plan templates (P1 to P28) | 28 | none (out of this round's scope) |

A course category manager, who reaches the hub through the category's menu, has no way into v2.

### Accessibility, read from the live page and the code

| Observation | Evidence |
|---|---|
| Every node is its own Tab stop: 7 nodes, 7 stops; a 500-node tree is 500 stops | live DOM on m503, `tabindex="0"` on each `.react-flow__node` |
| No tree semantics: nodes are `role="group"` with a name and a role description; no level, position, set size or expanded state on the node (the expand button inside carries `aria-expanded`) | `graph.ts:152-198`, `Nodes.tsx:177-181` |
| Arrow keys do nothing on a node; the only way from a parent to its child is Tab through everything between | nodes are not draggable, so React Flow's arrow handling is inert |
| React Flow's wrapper is `role="application"` with no name, inside our named region | live DOM |
| Every edge is announced as an image named with internal ids: "Edge from f to c198" | live DOM, `role="img"` on `.react-flow__edge` |
| No list, outline or table inside v2; "List view" is a link to another page (v1) | `templates/hubv2.mustache` |
| No reduced-motion handling (`setCenter(..., {duration: 300})` always) and no right-to-left handling (handles and chevrons fixed) | `Hub.tsx:781`, `Nodes.tsx:183` |
| What is right already: a named region, a live region for move prompts and results, focus returned to the node after a move or a reveal, a visible focus ring, Escape cancels a move, move by picking the new parent (a single-pointer alternative to dragging), zoom only with Ctrl/Cmd so the page still scrolls | `Hub.tsx`, `styles.css:9343` |

### Other findings

- **Density.** A card is 240 by 76 px on a 320 by 100 px pitch. At 1440 by 900 the canvas shows
  about six rows, and with two branches open the third column runs under the panel. The hub's tree
  shows roughly three times as many rows in the same height. The canvas is a tool for a branch,
  not for a whole framework.
- **A move resets rules without saying so.** Core's `set_parent_competency` resets the rule of the
  old parent and of the new one, and of the moved competency when its children change
  (`competency/classes/api.php:396-408`). The delete confirmation names the parent's rule; the move
  confirmation (`hubv2_moveconfirm`) does not.
- **No Behat** for v2, and no component tests for `Hub`, `Panel`, `Nodes` or `Search`; the four pure
  modules have 36 unit cases and `hubv2_page` 7 PHPUnit tests.
- **Large structures are unverified.** m503 holds 24 frameworks and 261 competencies; the largest
  has 30, the deepest two levels.
- **Design System.** The panel's buttons conform; the plugin as a whole has not started its pass
  (64 pill radii, 4 uppercase rules, no scale ratchet: `STATUS.md`).

## 3. What changed around it since #137

| Change | Why it matters here |
|---|---|
| The fleet Design System exists: token chain, radius scale, menu with an icon per item, segmented control, tabs, panel and popover, side layer, skeleton, empty state, tooltip, and the data report with a selection and a bulk dock (D129 to D135) | v2 was drawn before most of it. A second version has ready forms for the view switch, the node menu, the bulk bar and the loading and empty states, and has to obey the three rules (tokens, no pill on a control, sentence case) |
| `enrol_apply` shipped two React views on 5.3 (course report, approval queue) with shared parts in `js/esm/src/datareport/` | A proven pattern for a dense, keyboard-operable React grid with selection, a dock and a decision dialogue, to copy for an outline or table projection |
| `local_evidencehub` is installed, with a validity policy per framework and per competency (`local_evidencehub_policy`) and a hook this plugin already dispatches for status badges | The framework administrator now has a second thing to configure per competency. The canvas panel is the natural place, through a hook, with no hard dependency |
| React Flow 13 (13.0.0 around 2026-11-16, 13.0.3 on 2026-11-23): React 18 minimum, compiled with the React Compiler, store hooks split, styles in `@layer xyflow`, system colour mode by default (`forceColorMode` to opt out), `fitView` padding in pixels; 12.12.1 is the last 12.x release | We vendor the last 12.x. Building more on it means porting later. No accessibility change in 13 beyond one announcement fix |
| Core's design system bundle (MDS 5.3.0) has 18 components and no tree, treegrid or canvas | Whatever tree we draw is ours to make accessible; nothing to import |
| Fleet rules added: cluster-first design, secure-by-design at write time, tiered local gates | Any aggregate the canvas shows (usage counts, checks) must come from a shared, revisioned store; any new write service is written with its refusal tests first |

## 4. Canvas accessibility: what Miro did, and what transfers

Sources and their limits are in section 11. Miro's help centre and its engineering posts on Medium
refused automated reading; facts taken only from search summaries are marked (summary).

### Miro, 2021 to 2026

- **A separate screen reader mode failed.** In 2021 Miro shipped a read-only mode for screen
  readers. In 2023 it made the canvas itself navigable and in December 2023 it deprecated the
  separate mode.
- **Three ways to move among objects**, for every user (2023): linear with Tab in reading order,
  spatial with Ctrl/Cmd and the arrows, and hierarchical with a chord that enters or leaves a
  container; inside a container navigation stays in it. The hierarchy is built from
  container-and-contained links alone (summary).
- **The objects are mirrored for assistive technology**, since the browser sees only pixels: a
  hidden element per object, read on demand (summary of an axe-con 2023 talk). In November 2024
  Miro reported that it had cut its reliance on live regions in favour of information the user
  asks for.
- **Structure in words**: a board summary with counts by type (2023), a table of contents,
  "Explore board content" (2024), object details and connection information on request (2024-25).
- **Commands instead of gestures**: a command palette creates connectors (2025) and deletes them
  (2025), with keyboard resize and multi-select; the floating context menu became reachable by
  keyboard (2024). Moving an object by keyboard was not found.
- **Pointer precision**: a "precise selection" switch for trackballs and other pointers (2026).
- **An accessibility checker** for board authors (2024): contrast, image descriptions, container
  titles.
- **Process**: external audits before general availability, an ACR claiming "meets or partially
  meets" WCAG 2.2 (2025), a study with 12 visually impaired users (2024), tests with JAWS, NVDA
  and VoiceOver.
- **Still open by Miro's own account**: creating diagrams with assistive technology (2024), grids
  and swim lanes, richer announcements (2026), reading order drifting from the hidden mirror's
  order. No independent review of the current canvas was found.

### Others

- **Figma** mirrors each layer as a hidden, positioned element; navigation follows the layer
  hierarchy; Tab moves selection and focus together; repeated nudges are announced once, merged.
- **tldraw** (summary): Tab in reading order, a skip link to the first shape, container keys, a
  hook per shape for its description and for leaving it out of the Tab order.
- **GitHub** calls drag and drop a high-risk pattern (summary) and gives hierarchy lists
  move commands beside it.
- **Research** (summaries): Data Navigator overlays semantic HTML on a graph and navigates it by
  structure; Olli turns a chart into an ARIA tree; Benthic lets blind users walk a diagram by
  parent, child and neighbour.
- **Mind map editors** (summaries): Tab adds a child and Enter a sibling (Whimsical, Mindomo), a
  key collapses a branch, MindNode has a focus mode and an outline.

### Standards

| Rule | What it asks of our canvas |
|---|---|
| APG Tree View | one Tab stop; Right opens or goes to the first child, Left closes or goes to the parent, Up and Down walk the visible items, Home and End, type-ahead; `aria-expanded` on parents only; `aria-level`, `aria-setsize` and `aria-posinset` on every item when the whole set is not in the DOM, which is our case with lazy loading. Core's own `core/tree` does this and swaps Left and Right in a right-to-left language |
| APG Treegrid | the same with columns, when a row carries several values or controls |
| WCAG 2.1.1, 2.4.3, 2.4.7, 2.4.11 | every function by keyboard; focus order follows the tree (React Flow's DOM order follows the nodes array, issue 5189, so we emit it in tree order); a visible ring; the focused node never under the panel, the minimap or the controls |
| WCAG 2.5.7 Dragging | every drag has a single-pointer alternative; a keyboard path alone does not satisfy it. Pick-the-parent, already built, does |
| WCAG 2.5.8 Target size | 24 by 24 px for the expand button and any handle |
| WCAG 1.4.10 Reflow | the diagram itself is exempt; the toolbar, the panel and the outline are not, at 320 px |
| WCAG 1.4.11, 1.4.1 | 3:1 for borders, edges and states; a rule edge or a lens is never told by colour alone |
| WCAG 1.3.1, 1.3.2 | the hierarchy, the relations and the rules exist as structure or text, in tree order |

### What transfers, and what does not

Our case is easier than Miro's in two ways: React Flow draws DOM and SVG, so there is nothing to
mirror, and a framework is a tree, so there is one true order and no need for spatial guessing.
Left to right, "child" is also "to the right": structural and spatial navigation coincide, and a
mirrored layout keeps that in a right-to-left language.

1. **No separate accessible page.** Today's "List view" link is the 2021 Miro model with a better
   list. The outline and the canvas belong on one page, on one selection, with the same commands.
2. **One Tab stop, arrows by structure**, with the keys of core's own tree.
3. **Information on request, few announcements**: a framework summary, a node's details, its
   relations, all as text in the panel; live messages only for the result of a command.
4. **Commands, not gestures**: every action from the panel, from a node menu and from a command
   palette; a move through a searchable "move to" picker, through pick-on-canvas, and only then
   through an optional drag.
5. **Edges are not objects to visit.** A relation or a rule is said on the node ("related to 2",
   "rule: all") and listed in the panel; the edges leave the accessibility tree.
6. **A checker for the author**, Miro's idea turned to our subject: the structure's own defects.
7. **Proof**: one keyboard Behat scenario, a VoiceOver pass recorded in the pull request, the
   Chartability heuristics as the review list.

Does not transfer: nearest-neighbour navigation, the hidden mirror and its drift, sticky notes,
tables, Kanban.

## 5. The administrator's jobs

Read from the hub's 60 actions, core's pages and the October actions inventory.

| Job | Today | Where a canvas helps |
|---|---|---|
| J1 Author a framework: many competencies, fast | one modal form per competency, or a CSV import | little; an outline with Tab and Enter helps most |
| J2 Restructure: move, reorder, merge branches | drag or "move to" in v1; pick-the-parent in v2 | much: seeing the whole branch and the target |
| J3 Configure rules and understand the roll-up | one dialogue per parent | much: the rule as an edge, the points on the children, an unreachable rule visible |
| J4 Relate competencies | a dialogue per competency | much: relations are a graph |
| J5 Audit: unused, incomplete, inconsistent | nothing; row by row | much, with lenses and a checker |
| J6 See the impact before a change | the delete check (new in #137) | much: the affected nodes lit before confirming |
| J7 Set the appearance (colour, image, tags, type) | inside the edit form, one at a time | much with a selection and a bulk bar |
| J8 Manage the framework itself: scale, taxonomies, visibility, duplicate, import, export | the Structures tab | some: the root node as the framework, a column per taxonomy level |
| J9 Show the framework to others | none | an image or PDF export, a link to a node |
| J10 Set the evidence validity policy | `local_evidencehub`'s own pages | a section the companion adds to the panel |

## 6. Brainstorm

### Principles proposed

- P1 **One model, several projections.** Outline, canvas and any later view show the same tree,
  the same selection and the same commands. None is the "accessible version" of another.
- P2 **The canvas earns its place by what a list cannot show**: relations, rules, lenses, impact.
  It does not have to beat the list at typing.
- P3 **Commands before gestures.** A gesture is a shortcut to a command that exists elsewhere.
- P4 **Say the consequence before the change.** Delete does; move, rule changes and bulk edits
  should.
- P5 **Nothing new stored unless a decision needs it.** Deep links and core preferences first.
- P6 **The Design System's forms**, the plugin's tokens, sentence case.
- P7 **Companions add, the hub does not know them** (hooks, as with status badges).

### Four directions

| | A. Two projections | B. Canvas-first editor | C. Map as a lens | D. Framework studio |
|---|---|---|---|---|
| Idea | one workspace: an outline (a real tree or treegrid) beside the canvas, one selection, one command set; either can be hidden | the canvas is where one writes: inline add, rename and drag, as in a mind map editor | the hub stays the editor; the canvas only reads: lenses, rule preview, impact, export | A, plus the framework-level actions and a frameworks home: v2 replaces the Structures and Competencies tabs on 5.3 |
| Best at | J1, J2, accessibility by construction | J1, J2 for pointer users | J3 to J6, J9 | all of J1 to J9 |
| Accessibility | solved by the outline; the canvas adds arrows and one Tab stop | the hardest: inline editing inside React Flow, focus inside cards | simple: a read-only figure with a text alternative | as A |
| Cost | medium: the outline is new, the canvas mostly exists | high, and fights React Flow | low | high, in stages after A |
| Risk | two views to keep in step | reinventing an editor; density stays low | v2 never replaces anything; two hubs for good | scope |

### Ideas, by theme

**Views of the structure**

- I1 Outline beside the canvas, in step: select in one, the other scrolls and focuses. A splitter
  with keyboard resize, as the hub's panes have.
- I2 View switch as a segmented control (Design System): Outline, Map, Both. Remembered per user
  through a core preference.
- I3 Columns named by taxonomy: the canvas's columns are the framework's levels, so each column
  gets a header with its taxonomy term ("Domain", "Competency", "Indicator"), editable there (F2).
- I4 Focus on a branch (hoist): show one branch as the root, with a breadcrumb back. The answer to
  density and to large frameworks.
- I5 Semantic zoom: far out, cards become small coloured marks and only the top level keeps its
  name; closer, the name; closest, counts and rule.
- I6 Compact layout: an indented, top-to-bottom tree on the canvas (one narrow column per level,
  40 px rows) beside today's wide layout.
- I7 Miller columns as a third projection (one list per level, the path highlighted): dense,
  keyboard-native, and the phone layout.
- I8 Free layout with saved positions (the mockup's "Livre"): only if a real need appears; it is
  the first thing that would need a table of its own.
- I9 Coverage matrix: competencies by plan template or by course, as a data report grid. An
  audit view, later.

**Editing**

- I10 Node menu ("more" button, context-menu key): the Design System's menu, an icon per item,
  the same items as the panel.
- I11 Command palette (Ctrl/Cmd+K): go to a competency by name or ID number, run any action on the
  selection, switch lens or view. Miro's central device.
- I12 Authoring keys in the outline: Enter adds a sibling, Tab a child, F2 renames in place; the
  full form stays for everything else. On the canvas, only after the outline proves them.
- I13 Move, three ways into one service: "move to" picker with search, pick-on-canvas (built),
  drag with drop zones (optional). Each says which rules core will reset.
- I14 Reorder with Alt+Up and Alt+Down, as core's reorder lists do.
- I15 Relate by picking: "relate to", then choose nodes on the canvas or in the outline; all
  relations of the visible nodes can be drawn, not only the selected node's.
- I16 Selection of several nodes with the Design System's bulk dock: set colour, tags or type,
  move under, delete (with one combined check), export the selection.
- I17 Paste a list: paste lines of text under a node to create children in one go, with a preview
  dialogue. Indentation gives depth. CSV import stays for whole frameworks.
- I18 Undo for the last structural change (move, reorder) as a toast action, since core has the
  inverse call; no undo for delete.

**Lenses: one question at a time over the same tree**

- I19 Usage: tint and count by courses, activities, templates and plans; "unused" marked.
- I20 Rules: the rule on each parent, points and "required" on the children, the sum against the
  threshold; a rule that can never fire flagged.
- I21 Rule preview: mark children as proficient in a sandbox and see what rolls up. Either a
  server dry run or a client copy that mirrors `competency_rule_points` and says so.
- I22 Relations: every relation at once, bundled; nodes with none dimmed.
- I23 Appearance: each node as the learner will see it (colour, image, tags), the gaps marked.
- I24 Templates (read-only): pick a learning plan template and its competencies light up.
- I25 Evidence policy, contributed by `local_evidencehub` through a hook: kind, validity,
  on-expiry, inherited or overridden.
- I26 Every lens has a text form: a legend with counts and a list in the panel. A lens never
  speaks by colour alone.

**Checker**

- I27 "Check structure": a list of findings, each a link that reveals its node. Candidates: no ID
  number, duplicate ID number, empty description, rule that cannot fire, parent with a rule and one
  child, leaf linked to no course, competency in no template, framework with no proficient scale
  value, relation to a hidden framework's competency, custom colour failing contrast on its text.
- I28 Impact preview: before a delete, a move or a bulk change, the affected nodes are lit and
  counted on the canvas; the dialogue names them.

**The framework itself**

- I29 The root node is the framework: its panel carries F1 to F10 (edit, scale and proficiency,
  taxonomies, visibility, duplicate, export, delete).
- I30 Frameworks home: the context's frameworks as cards with size, depth, usage and checker
  status, in place of a select; import and "new" there.
- I31 The category-locked entry, as the hub has: v2 reachable from a course category's menu.
- I32 v2 inside the hub, not beside it: the hub's Competencies tab gains the view switch, so there
  is one address and one name. "v2" stops being a product name.

**State, scale, delivery**

- I33 Deep links: framework, selected competency, view and lens in the URL; back and forward work;
  a link to a node can be sent to a colleague.
- I34 Expanded branches remembered per framework in `sessionStorage`; view and lens in a core
  preference. No table.
- I35 Aggregates (usage, checks) from one service per framework, in a MUC application cache keyed
  by a revision that course links and template changes bump, computed on the first ask on any node.
  No cron.
- I36 `onlyRenderVisibleElements`, hoist and semantic zoom for large frameworks, measured on a
  seeded 2,000-node framework before any promise.
- I37 Export the current view as SVG or PNG, and the outline as text.
- I38 A summary on request: levels, counts per level, rules, relations, unused, findings. The
  panel's content when nothing is selected, and a palette command.

**Provocations, listed to be argued with**

- I39 Drop React Flow for an owned SVG tree. It would allow a true `role="tree"` and save 186 KB.
  It costs pan, zoom, minimap and culling. Worth it only if the accessibility spike shows React
  Flow cannot carry tree roles.
- I40 No canvas editing at all (direction C taken to its end). Honest about density; leaves two
  hubs.
- I41 A framework diff: compare two frameworks, or a framework with its duplicate, node by node.
  Useful before an import; far from today's needs.

## 7. Recommended line

**A as the base, C's lenses as the canvas's reason to exist, D as where it ends, B only inside the
outline.** In words: one workspace with an outline and a map over one selection and one command
set (I1, I2, I10, I11, I13), the map growing lenses and a checker (I19, I20, I22, I27, I28), the
framework's own actions on the root (I29) so that on 5.3 the workspace can replace the hub's
Structures and Competencies tabs, and fast typing in the outline (I12), not on the canvas.

Why: the Miro record says a separate accessible view is a dead end, and the outline is where
authoring is fastest; v2's density says the canvas should not be the only view; the parity table
says most of what is missing is commands, not drawing; and lenses are what no list of ours does.

### Stages, each one mergeable

| Stage | Content | Check |
|---|---|---|
| S0 Spikes, nothing merged | (a) React Flow 13 in the vendored build (H4: the upgrade is decided; the spike measures what it costs); (b) one Tab stop, arrow keys and tree roles inside React Flow, or the proof that it cannot (I39); (c) a seeded 2,000-node framework on a free stack: first paint, expand, pan; (d) one Behat scenario driving the canvas by keyboard | a page of measures in this folder |
| S1 Base | category-locked entry (I31); deep links (I33); reduced motion and right-to-left; edges out of the accessibility tree; the move dialogue names the rules core resets; Design System tokens for the v2 block | parity row G5 "yes"; the section 2 accessibility table re-measured |
| S2 Two projections | the outline (tree or treegrid) on the same page and selection (I1, I2); arrow keys on the canvas | the keyboard Behat scenario; a VoiceOver pass in the pull request |
| S3 Commands | node menu, palette, "move to" picker, reorder keys, outline authoring keys (I10 to I14) | every action of rows S1 to S17 reachable three ways |
| S4 The framework on the root | F1 to F10 from the root's panel (I29); the hub's two tabs point here on 5.3, if the owner agrees | parity: frameworks 10 of 10 |
| S5 Lenses and checker | usage, rules, relations; "check structure"; the aggregate service with its cache (I19, I20, I22, I27, I35) | cluster check on the mini cluster; counts against SQL |
| S6 Selection and bulk dock | I16, with a combined delete check | refusal tests first (secure-by-design) |
| S7 Companion sections | the panel hook; `local_evidencehub` adds its policy section (I25) | the hub renders with and without the companion |

Later, on demand: template lens, rule preview, export, semantic zoom, frameworks home, paste a
list.

Every stage that adds a web service, a write path or a dependency is tier 3 (local matrix);
`secure-by-design` is loaded before S4, S6 and S7.

## 8. Not verified, and risks

- Whether React Flow's node wrappers can sit under a `role="tree"` container: unknown until S0(b).
- Behaviour above 30 competencies: unknown until S0(c).
- React Flow 13's effect on our ported CSS (`@layer xyflow`, colour mode) and on the hand-written
  type file: unknown until S0(a). Whether 12.x still receives fixes was not stated anywhere read.
- Miro's keyboard model and its hidden mirror come from search summaries of pages that refused
  automated reading; the changelog and two blog posts were read in full.
- No screen reader was run in this round; the accessibility table is from the DOM and the code.
- RTL, the Moodle App and a 320 px viewport were not looked at.

## 9. Decisions for the owner

| Id | Question | Recommendation |
|---|---|---|
| H1 | Which direction? | A as the base, towards D, with C's lenses (section 7) |
| H2 | Does v2 become a view inside the hub (I32), or stay a page of its own? | inside the hub's Competencies tab, behind the view switch; the separate admin entry goes |
| H3 | On 5.3, may the workspace replace the Structures and Competencies tabs once S4 is done? | yes, with the old tabs kept one release behind a link |
| H4 | React Flow: upgrade to 13 first, stay on 12.12.1, or own the drawing (I39)? | **Decided 2026-11-23: React Flow 13, and always the latest release from then on.** I39 stays only as the fallback if S0(b) shows the library cannot carry tree roles |
| H5 | Is fast authoring (Tab, Enter, F2) wanted in the outline only, or on the canvas too? | outline only |
| H6 | Drag on the canvas at all? | not before S3's three command paths exist; then as an option |
| H7 | Which lenses first? | usage and rules, then relations |
| H8 | May the hub keep per-user state (view, lens) in a core preference? | yes; still no table |
| H9 | The evidence policy in the panel through a hook (I25, S7)? | yes, after S5 |
| H10 | The earlier open item: the panel lists every related competency, or only those off the canvas? | every one, in text; the canvas draws those on it (section 4, point 5) |
| H11 | Seed a large demo framework on a stack for S0(c): which stack, and may it stay? | m503b, kept under a clear short name |
| H12 | Mockups before S1? | **Done 2026-11-23**, on a canvas of its own (section 12), since the earlier canvas is the record of a closed round |

### Owner's answer (2026-11-23)

"Sobre as decisões, concordo com as recomendações": **every recommendation of the table above is
the decision** (H4 as already recorded). The owner also said the work may run on its own through
every stage, with commit, pull request and merge authorised, and that a choice which comes up on the
way and is quick to reverse may be taken and reported at the end. What was taken that way is listed
in `PROGRESS.md` beside this file, under "Taken without asking".

## 10. How this was gathered

Memory and the three earlier folders (section 1); the hub v1 and v2 code read file by file for the
parity and accessibility tables; the live page on m503 at 1440 by 900 (DOM attributes, Tab stops,
two frameworks); core's `competency/classes/api.php` and `lib/amd/src/tree.js`; the Design
System's `INDEX.md`, `STATUS.md` and four component files; `local_evidencehub`'s `install.xml`;
web research on 2026-11-23.

## 11. Sources

Read in full or in the part cited:

- Miro accessibility changelog, entries 2021-04 to 2026-09: https://miro.com/accessibility/changelog/
- Miro, Global Accessibility Awareness Day 2025: https://miro.com/blog/global-accessibility-awareness-day-2025/
- Miro, Empowering inclusive accessibility (2024): https://miro.com/blog/empowering-inclusive-accessibility/
- Miro ACR: https://miro.com/acr
- Figma, Building accessibility into a canvas-based product: https://www.figma.com/blog/building-accessibility-into-a-canvas-based-product/
- React Flow accessibility, performance, layouting, API reference and the v13 migration guide:
  https://reactflow.dev/learn/advanced-use/accessibility ,
  https://reactflow.dev/learn/advanced-use/performance ,
  https://reactflow.dev/learn/layouting/layouting ,
  https://reactflow.dev/api-reference/react-flow ,
  https://reactflow.dev/learn/troubleshooting/migrate-to-v13
- xyflow changelog and releases: https://github.com/xyflow/xyflow/blob/main/packages/react/CHANGELOG.md ,
  https://github.com/xyflow/xyflow/releases
- W3C APG Tree View and Treegrid: https://www.w3.org/WAI/ARIA/apg/patterns/treeview/ ,
  https://www.w3.org/WAI/ARIA/apg/patterns/treegrid/
- WCAG 2.2 and its Understanding pages for Dragging Movements, Reflow and Target Size (Minimum):
  https://www.w3.org/TR/WCAG22/ ,
  https://www.w3.org/WAI/WCAG22/Understanding/dragging-movements.html ,
  https://www.w3.org/WAI/WCAG22/Understanding/reflow.html ,
  https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html
- GitHub Projects hierarchy view (2026-01-15): https://github.blog/changelog/2026-01-15-hierarchy-view-now-available-in-github-projects/

Known only from search summaries (the page refused reading or was not opened):

- Miro engineering, keyboard navigation for board objects: https://medium.com/miro-engineering/miro-accessibility-introducing-keyboard-navigation-for-board-objects-dba41d0c4903
- A recap of Miro's axe-con 2023 talk: https://medium.com/@elenamolinari.mail/axe-con-2023-a-talk-by-miro-8394c4561979
- Miro help centre, keyboard navigation and assistive technologies: https://help.miro.com/hc/en-us/articles/11997028019858-Keyboard-navigation-while-working-on-boards ,
  https://help.miro.com/hc/en-us/articles/4403828752274-How-to-access-Miro-boards-with-assistive-technologies
- tldraw accessibility: https://tldraw.dev/sdk-features/accessibility
- Data Navigator: https://dig.cmu.edu/data-navigator/ ; Olli: https://vis.mit.edu/pubs/olli ;
  Benthic: https://vis.csail.mit.edu/pubs/benthic.pdf
- Whimsical shortcuts: https://help.whimsical.com/shortcuts/mac

## 12. Mockups (2026-11-23)

Four boards of the recommended line, on a private Claude design canvas, "Hub de competências v2:
administração" (https://claude.ai/artifact/Dv87v1jHsKnYDNrtgd8jFP). `canvas/project/` beside this file
is the copy of its version `1795485635-45d5`: `canvas.json` (the index), `hub.css` (the shared look, in
the fleet Design System's measures) and one `.dc.html` per board. As in the 2026-11-12 folder, a board
loads the canvas runtime (`./support.js`), which is not part of the copy: open the artifact to see the
boards rendered and read the files for markup and copy. The mockup copy is in Brazilian Portuguese.
`hub.css` sits under `docs/`: run the static gates before committing this folder.

| Board | Shows | Ideas |
|---|---|---|
| `Main` | the workspace inside the hub's Competencies tab: structure line, view switch (Outline, Map, Both), lens switch, search, Check and Commands; the outline as a tree with one Tab stop and its authoring keys; the map with a column per taxonomy level; the panel of the selected competency | I1, I2, I3, I10, I12, I32, I38 |
| `Comandos` (interactive) | the map alone, with the node menu, the command palette and the "move to" dialogue that names the rules core resets; the node's button, "Comandos" and "Mover para" open each one | I10, I11, I13, I14 |
| `Lentes` (interactive) | the usage lens (tint by linked courses, unused dashed) and the rules lens (rule on the parent, points on the children, a rule that cannot fire), each with its legend, counts and list in the panel | I19, I20, I26 |
| `Verificador` | "Check structure": six findings in the panel, the chosen one revealed on the map and in the outline, markers on the nodes that carry a finding | I27 |

Not drawn: the frameworks home (I30), the framework's actions on the root (I29), the selection with
its bulk dock (I16), the relations lens (I22), a phone width, dark mode. The boards were not rendered
or checked after publishing (the canvas type forbids verifying unasked).

